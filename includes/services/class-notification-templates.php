<?php

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * NotificationTemplates - 通知模板管理服務
 *
 * 負責管理所有 LINE 通知模板（已移除 Email 通知）
 * 從舊版 BuyGo 外掛複製並調整命名空間
 */
class NotificationTemplates {

    /**
     * Debug Service 實例
     *
     * @var Debug_Service
     */
    private static $debug_service;

    /**
     * 快取所有自訂模板（單次請求內）
     */
    private static $cached_custom_templates = null;

    /**
     * 快取 key
     */
    private static $cache_key = 'buygo_notification_templates_cache';

    /**
     * 快取群組
     */
    private static $cache_group = 'buygo_notification_templates';

    /**
     * 初始化 Debug Service
     */
    private static function init_debug_service() {
        if (self::$debug_service === null) {
            self::$debug_service = Debug_Service::get_instance();
        }
    }

    public static function get($key, $args = []) {
        self::init_debug_service();

        try {
            $templates = self::definitions();

            if (!isset($templates[$key])) {
                self::$debug_service->log('NotificationTemplates', '模板不存在', [
                    'key' => $key
                ], 'warning');
                return null;
            }

            // 先從資料庫讀取自訂模板，如果沒有則使用預設值
            $custom_template = self::get_custom_template($key);

            // 如果自訂模板存在，使用自訂模板（即使 line.message 為空，也使用自訂模板）
            // 這樣可以讓用戶清空模板內容，而不會被預設模板覆蓋
            if ($custom_template !== null) {
                $template = $custom_template;
            } else {
                $template = $templates[$key];
            }

            // 檢查是否為 Flex Message 類型
            $template_type = $template['type'] ?? 'text';

            if ($template_type === 'flex') {
                // Flex Message 模板
                $flex_template = $template['line']['flex_template'] ?? [];

                // 處理變數替換
                if (!empty($flex_template)) {
                    $flex_template['title'] = self::replace_placeholders($flex_template['title'] ?? '', $args);
                    $flex_template['description'] = self::replace_placeholders($flex_template['description'] ?? '', $args);

                    // 處理按鈕的 label（action 不需要替換）
                    if (isset($flex_template['buttons']) && is_array($flex_template['buttons'])) {
                        foreach ($flex_template['buttons'] as &$button) {
                            if (isset($button['label'])) {
                                $button['label'] = self::replace_placeholders($button['label'], $args);
                            }
                        }
                    }
                }

                return [
                    'type' => 'flex',
                    'line' => [
                        'flex_template' => $flex_template
                    ]
                ];
            } else {
                // 文字模板
                $line_message = self::replace_placeholders($template['line']['message'] ?? '', $args);

                return [
                    'type' => 'text',
                    'line' => [
                        'type' => 'text',
                        'text' => $line_message
                    ]
                ];
            }

        } catch (\Exception $e) {
            self::$debug_service->log('NotificationTemplates', '取得模板失敗', [
                'key' => $key,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 'error');

            // 返回預設空模板
            return [
                'type' => 'text',
                'line' => [
                    'type' => 'text',
                    'text' => ''
                ]
            ];
        }
    }

    /**
     * 根據 trigger_condition 取得模板（優先使用自訂模板）
     * 如果有多個自訂模板，返回所有匹配的模板（按 message_order 排序）
     * 
     * @param string $trigger_condition 觸發條件（例如 'system_image_uploaded'）
     * @param array $args 模板變數參數
     * @return array|array[] 單個模板或模板陣列（格式與 get() 相同），每個模板包含額外的 'message_order' 和 'send_interval' 欄位
     */
    public static function get_by_trigger_condition($trigger_condition, $args = []) {
        // 查找所有匹配的自訂模板（根據 trigger_condition）
        $custom_templates = self::get_all_custom_templates_by_trigger($trigger_condition);
        
        $result = [];
        
        // 檢查是否有自訂模板設定了 message_order = 1
        $has_custom_order_1 = false;
        foreach ($custom_templates as $template_data) {
            $message_order = intval($template_data['message_order'] ?? 1);
            if ($message_order === 1) {
                $has_custom_order_1 = true;
                break;
            }
        }
        
        // 預設模板邏輯：
        // 1. 如果沒有自訂模板，使用預設模板
        // 2. 如果有自訂模板但沒有 message_order = 1 的，預設模板作為第 1 則
        // 3. 如果有自訂模板且 message_order = 1，預設模板不會出現（被自訂模板替換）
        $default_templates = self::definitions();
        if (!$has_custom_order_1 && isset($default_templates[$trigger_condition])) {
            $template = $default_templates[$trigger_condition];
            $template_type = $template['type'] ?? 'text';
            
            if ($template_type === 'flex') {
                // Flex Message 模板
                $flex_template = $template['line']['flex_template'] ?? [];
                if (!empty($flex_template)) {
                    $flex_template['title'] = self::replace_placeholders($flex_template['title'] ?? '', $args);
                    $flex_template['description'] = self::replace_placeholders($flex_template['description'] ?? '', $args);
                    if (isset($flex_template['buttons']) && is_array($flex_template['buttons'])) {
                        foreach ($flex_template['buttons'] as &$button) {
                            if (isset($button['label'])) {
                                $button['label'] = self::replace_placeholders($button['label'], $args);
                            }
                        }
                    }
                }
                
                $result[] = [
                    'type' => 'flex',
                    'line' => [
                        'flex_template' => $flex_template
                    ],
                    'message_order' => 1,
                    'send_interval' => 0
                ];
            } else {
                // 文字模板
                $line_message = self::replace_placeholders($template['line']['message'] ?? '', $args);
                
                $result[] = [
                    'type' => 'text',
                    'line' => [
                        'type' => 'text',
                        'text' => $line_message
                    ],
                    'message_order' => 1, // 預設模板為第一則
                    'send_interval' => 0 // 預設模板沒有間隔
                ];
            }
        }
        
        // 然後加入所有自訂模板（按 message_order 排序）
        if (!empty($custom_templates)) {
            foreach ($custom_templates as $template_data) {
                $template = $template_data['template'];
                $message_order = $template_data['message_order'] ?? 1;
                $send_interval = $template_data['send_interval'] ?? 0.5;
                $template_type = $template['type'] ?? 'text';
                
                if ($template_type === 'flex') {
                    // Flex Message 模板
                    $flex_template = $template['line']['flex_template'] ?? [];
                    if (!empty($flex_template)) {
                        $flex_template['title'] = self::replace_placeholders($flex_template['title'] ?? '', $args);
                        $flex_template['description'] = self::replace_placeholders($flex_template['description'] ?? '', $args);
                        if (isset($flex_template['buttons']) && is_array($flex_template['buttons'])) {
                            foreach ($flex_template['buttons'] as &$button) {
                                if (isset($button['label'])) {
                                    $button['label'] = self::replace_placeholders($button['label'], $args);
                                }
                            }
                        }
                    }
                    
                    $result[] = [
                        'type' => 'flex',
                        'line' => [
                            'flex_template' => $flex_template
                        ],
                        'message_order' => $message_order,
                        'send_interval' => $send_interval
                    ];
                } else {
                    // 文字模板
                    $line_message = self::replace_placeholders($template['line']['message'] ?? '', $args);
                    
                    $result[] = [
                        'type' => 'text',
                        'line' => [
                            'type' => 'text',
                            'text' => $line_message
                        ],
                        'message_order' => $message_order,
                        'send_interval' => $send_interval
                    ];
                }
            }
        }
        
        // 如果沒有任何模板，返回空陣列
        if (empty($result)) {
            return [];
        }
        
        // 按照 message_order 排序（確保順序正確）
        usort($result, function($a, $b) {
            return ($a['message_order'] ?? 1) - ($b['message_order'] ?? 1);
        });
        
        // 返回陣列（即使只有一個模板，也返回陣列以便統一處理）
        return $result;
    }

    /**
     * 根據 trigger_condition 取得所有自訂模板（按 message_order 排序）
     * 
     * @param string $trigger_condition 觸發條件
     * @return array 自訂模板陣列，每個元素包含 'template', 'message_order', 'send_interval'
     */
    private static function get_all_custom_templates_by_trigger($trigger_condition) {
        $custom_templates = self::get_all_custom_templates();
        $custom_metadata = get_option('buygo_notification_templates_metadata', []);
        
        // 查找所有匹配的自訂模板
        $matched_templates = [];
        foreach ($custom_templates as $key => $template) {
            $metadata = $custom_metadata[$key] ?? [];
            $metadata_trigger = $metadata['trigger_condition'] ?? '';
            
            // 匹配條件：
            // 1. metadata 中有 trigger_condition 且匹配
            // 2. 或者模板的 key 等於 trigger_condition（系統預設模板的自定義版本）
            $matches = false;
            if (isset($metadata['trigger_condition']) && $metadata['trigger_condition'] === $trigger_condition) {
                $matches = true;
            } elseif ($key === $trigger_condition) {
                // 系統預設模板的自定義版本（key 就是 trigger_condition）
                $matches = true;
            }
            
            if ($matches) {
                $matched_templates[] = [
                    'key' => $key,
                    'template' => $template,
                    'message_order' => intval($metadata['message_order'] ?? 1),
                    'send_interval' => floatval($metadata['send_interval'] ?? 0.5)
                ];
            }
        }
        
        // 按照 message_order 排序
        if (!empty($matched_templates)) {
            usort($matched_templates, function($a, $b) {
                return ($a['message_order'] ?? 1) - ($b['message_order'] ?? 1);
            });
        }
        
        return $matched_templates;
    }

    /**
     * 從資料庫讀取自訂模板（帶快取）
     */
    private static function get_custom_template($key) {
        $custom_templates = self::get_all_custom_templates();
        return isset($custom_templates[$key]) ? $custom_templates[$key] : null;
    }

    /**
     * 取得所有自訂模板（帶快取）
     */
    private static function get_all_custom_templates() {
        // 先檢查 static 變數快取（單次請求內最快）
        if (self::$cached_custom_templates !== null) {
            return self::$cached_custom_templates;
        }

        // 再檢查 WordPress object cache（跨請求快取）
        $cached = wp_cache_get(self::$cache_key, self::$cache_group);
        
        if ($cached !== false) {
            self::$cached_custom_templates = $cached;
            return $cached;
        }

        // 最後才從資料庫讀取
        $templates = get_option('buygo_notification_templates', []);
        
        // 儲存到快取
        self::$cached_custom_templates = $templates;
        wp_cache_set(self::$cache_key, $templates, self::$cache_group, 3600); // 快取 1 小時

        return $templates;
    }

    /**
     * 取得所有模板（包含自訂和預設）
     * 確保返回的資料格式標準化，前後端一致
     */
    public static function get_all_templates() {
        $default_templates = self::definitions();
        $custom_templates = self::get_all_custom_templates();
        
        $result = [];
        // 先加入所有預設模板（如果有自訂版本則使用自訂版本）
        foreach ($default_templates as $key => $default) {
            if (isset($custom_templates[$key])) {
                // 使用自訂模板，但確保格式標準化
                $result[$key] = self::normalize_single_template($key, $custom_templates[$key], $default);
            } else {
                // 使用預設模板
                $result[$key] = $default;
            }
        }
        
        // 再加入所有完全自訂的模板（key 以 custom_ 開頭）
        foreach ($custom_templates as $key => $custom) {
            if (strpos($key, 'custom_') === 0 && !isset($default_templates[$key])) {
                // 標準化自訂模板格式
                $result[$key] = self::normalize_single_template($key, $custom, null);
            }
        }
        
        return $result;
    }
    
    /**
     * 標準化單一模板資料格式
     * 
     * @param string $key 模板鍵值
     * @param array $template 模板資料
     * @param array|null $default_template 預設模板（用於推斷類型）
     * @return array 標準化後的模板資料
     */
    private static function normalize_single_template($key, $template, $default_template = null) {
        // 確保 template 是陣列
        if (!is_array($template)) {
            $template = [];
        }
        
        // 如果已經是標準格式，確保結構完整後返回
        if (isset($template['line']['message'])) {
            // 文字模板：確保結構完整，保留原始內容
            $message = $template['line']['message'];
            return [
                'line' => [
                    'message' => $message
                ]
            ];
        }
        
        if (isset($template['line']['flex_template']) && isset($template['type']) && $template['type'] === 'flex') {
            // Flex Message 模板：確保結構完整
            return [
                'type' => 'flex',
                'line' => [
                    'flex_template' => [
                        'logo_url' => $template['line']['flex_template']['logo_url'] ?? '',
                        'title' => $template['line']['flex_template']['title'] ?? '',
                        'description' => $template['line']['flex_template']['description'] ?? '',
                        'buttons' => $template['line']['flex_template']['buttons'] ?? []
                    ]
                ]
            ];
        }
        
        // 嘗試從預設模板推斷類型
        if ($default_template && isset($default_template['line']['flex_template'])) {
            // Flex Message 類型
            return [
                'type' => 'flex',
                'line' => [
                    'flex_template' => [
                        'logo_url' => $template['line']['flex_template']['logo_url'] ?? '',
                        'title' => $template['line']['flex_template']['title'] ?? '',
                        'description' => $template['line']['flex_template']['description'] ?? '',
                        'buttons' => $template['line']['flex_template']['buttons'] ?? []
                    ]
                ]
            ];
        } else {
            // 文字模板類型：嘗試從多種可能的欄位讀取
            $message = $template['line']['message'] ?? $template['line']['text'] ?? '';
            return [
                'line' => [
                    'message' => $message
                ]
            ];
        }
    }

    /**
     * 儲存自訂模板
     *
     * @param array $templates 模板資料陣列
     * @return bool|\WP_Error 成功返回 true，失敗返回 WP_Error
     */
    public static function save_custom_templates($templates) {
        self::init_debug_service();

        self::$debug_service->log('NotificationTemplates', '開始儲存自訂模板', [
            'template_count' => count($templates)
        ]);

        try {
            // 驗證並標準化資料格式
            $normalized_templates = self::normalize_templates($templates);

            // 儲存到資料庫
            $result = update_option('buygo_notification_templates', $normalized_templates);

            if (!$result && get_option('buygo_notification_templates') !== $normalized_templates) {
                self::$debug_service->log('NotificationTemplates', '儲存模板失敗', [
                    'templates' => $templates
                ], 'error');
                return new \WP_Error('save_failed', '儲存模板失敗');
            }

            // 清除所有快取（確保前後端都能讀取到最新資料）
            self::clear_cache();

            self::$debug_service->log('NotificationTemplates', '儲存模板成功', [
                'template_count' => count($normalized_templates)
            ]);

            return true;

        } catch (\Exception $e) {
            self::$debug_service->log('NotificationTemplates', '儲存模板異常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 'error');

            return new \WP_Error('save_exception', $e->getMessage());
        }
    }
    
    /**
     * 標準化模板資料格式
     * 確保前後端使用相同的資料結構
     * 
     * @param array $templates 原始模板資料
     * @return array 標準化後的模板資料
     */
    private static function normalize_templates($templates) {
        if (!is_array($templates)) {
            return [];
        }
        
        $normalized = [];
        $default_templates = self::definitions();
        
        foreach ($templates as $key => $template_data) {
            // 只處理已定義的模板或自訂模板（custom_ 開頭）
            if (!isset($default_templates[$key]) && strpos($key, 'custom_') !== 0) {
                continue;
            }
            
            // 標準化文字模板
            if (isset($template_data['line']['message'])) {
                $normalized[$key] = [
                    'line' => [
                        'message' => sanitize_textarea_field($template_data['line']['message'])
                    ]
                ];
            }
            // 標準化 Flex Message 模板
            elseif (isset($template_data['line']['flex_template']) || (isset($template_data['type']) && $template_data['type'] === 'flex')) {
                $flex_template = $template_data['line']['flex_template'] ?? [];
                
                $normalized[$key] = [
                    'type' => 'flex',
                    'line' => [
                        'flex_template' => [
                            'logo_url' => sanitize_text_field($flex_template['logo_url'] ?? ''),
                            'title' => sanitize_text_field($flex_template['title'] ?? ''),
                            'description' => sanitize_textarea_field($flex_template['description'] ?? ''),
                            'buttons' => []
                        ]
                    ]
                ];
                
                // 處理按鈕（只保留有效的按鈕）
                if (isset($flex_template['buttons']) && is_array($flex_template['buttons'])) {
                    foreach ($flex_template['buttons'] as $button) {
                        if (!empty($button['label']) || !empty($button['action'])) {
                            $normalized[$key]['line']['flex_template']['buttons'][] = [
                                'label' => sanitize_text_field($button['label'] ?? ''),
                                'action' => sanitize_text_field($button['action'] ?? '')
                            ];
                        }
                    }
                }
            }
            // 如果格式不符合，嘗試從預設模板結構推斷
            elseif (isset($default_templates[$key])) {
                $default = $default_templates[$key];
                // 如果是 Flex Message 類型，保留結構但清空內容
                if (isset($default['line']['flex_template'])) {
                    $normalized[$key] = [
                        'type' => 'flex',
                        'line' => [
                            'flex_template' => [
                                'logo_url' => '',
                                'title' => '',
                                'description' => '',
                                'buttons' => []
                            ]
                        ]
                    ];
                } else {
                    // 文字模板，保留空訊息
                    $normalized[$key] = [
                        'line' => [
                            'message' => ''
                        ]
                    ];
                }
            }
        }
        
        return $normalized;
    }

    /**
     * 清除快取
     * 確保前後端都能讀取到最新資料
     */
    public static function clear_cache() {
        // 清除 static 變數快取
        self::$cached_custom_templates = null;
        
        // 清除 WordPress object cache
        wp_cache_delete(self::$cache_key, self::$cache_group);
        
        // 清除所有可能的快取變體
        wp_cache_delete(self::$cache_key . '_all', self::$cache_group);
        
        // 如果 WordPress 版本 >= 6.1，使用 flush_group（更徹底）
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::$cache_group);
        }
    }

    /**
     * 組裝 Flex Message JSON
     *
     * @param array $flex_template Flex Message 模板資料
     * @return array|null LINE Flex Message JSON 格式或 null
     */
    public static function build_flex_message($flex_template) {
        self::init_debug_service();

        try {
            if (empty($flex_template)) {
                self::$debug_service->log('NotificationTemplates', 'Flex 模板為空', [], 'warning');
                return null;
            }

            // 驗證必要欄位
            if (!isset($flex_template['logo_url']) || !isset($flex_template['title'])) {
                self::$debug_service->log('NotificationTemplates', 'Flex 模板缺少必要欄位', [
                    'flex_template' => $flex_template
                ], 'error');
                return null;
            }

            $logo_url = $flex_template['logo_url'] ?? '';
            $title = $flex_template['title'] ?? '圖片已收到！';
            $description = $flex_template['description'] ?? '請選擇您要使用的上架格式：';
            $buttons = $flex_template['buttons'] ?? [];

        // 建立 Flex Message 結構
        $flex_message = [
            'type' => 'flex',
            'altText' => '收到商品圖片，請選擇上架方式',
            'contents' => [
                'type' => 'bubble',
                'hero' => [
                    'type' => 'image',
                    'url' => $logo_url,
                    'size' => 'full',
                    'aspectRatio' => '20:13',
                    'aspectMode' => 'cover',
                ],
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => $title,
                            'weight' => 'bold',
                            'size' => 'xl',
                            'color' => '#111827'
                        ],
                        [
                            'type' => 'text',
                            'text' => $description,
                            'wrap' => true,
                            'color' => '#666666',
                            'size' => 'sm',
                            'margin' => 'md'
                        ]
                    ]
                ],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'spacing' => 'sm',
                    'contents' => []
                ]
            ]
        ];

        // 加入按鈕
        if (!empty($buttons)) {
            $footer_contents = [];
            
            foreach ($buttons as $index => $button) {
                $label = $button['label'] ?? '';
                $action = $button['action'] ?? '';
                
                if (empty($label) || empty($action)) {
                    continue;
                }

                // 第一個按鈕使用 primary 樣式，其他使用 secondary
                $button_style = $index === 0 ? 'primary' : 'secondary';
                $button_color = $index === 0 ? '#111827' : '#E5E7EB';
                $text_color = $index === 0 ? '#FFFFFF' : '#374151';

                $footer_contents[] = [
                    'type' => 'button',
                    'style' => $button_style,
                    'color' => $button_color,
                    'action' => [
                        'type' => 'message',
                        'label' => $label,
                        'text' => $action
                    ]
                ];
            }

            // 如果有超過 2 個按鈕，最後一個改為 link 樣式
            if (count($footer_contents) > 2) {
                $last_button = array_pop($footer_contents);
                $last_button['style'] = 'link';
                $last_button['color'] = '#0066CC';
                $last_button['action']['type'] = 'message';
                
                // 在最後一個按鈕前加入分隔線
                if (count($footer_contents) > 0) {
                    $footer_contents[] = [
                        'type' => 'separator',
                        'margin' => 'md'
                    ];
                }
                
                $footer_contents[] = $last_button;
            }

            $flex_message['contents']['footer']['contents'] = $footer_contents;
        }

        return $flex_message;

        } catch (\Exception $e) {
            self::$debug_service->log('NotificationTemplates', '組裝 Flex Message 失敗', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'flex_template' => $flex_template
            ], 'error');

            return null;
        }
    }

    private static function replace_placeholders($text, $args) {
        // 先替換所有變數
        foreach ($args as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        
        // 清理多餘的空行：
        // 1. 移除連續的多個空行（3個或以上），只保留一個空行
        // 2. 移除只包含標籤但沒有值的行（例如「原價：」後面沒有值）
        // 3. 清理開頭和結尾的空行
        
        // 先將連續的多個空行（3個或以上）合併為兩個換行符
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        // 然後逐行處理，移除只包含標籤但沒有值的行
        $lines = explode("\n", $text);
        $cleaned_lines = [];
        $prev_empty = false;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            $is_empty = $trimmed === '';
            
            // 檢查是否是只包含標籤但沒有值的行（例如「原價：」）
            // 這種行通常以「：」結尾，且後面沒有內容
            $is_label_only = false;
            if (!$is_empty && preg_match('/^[^：:]+[：:]\s*$/', $trimmed)) {
                $is_label_only = true;
            }
            
            // 如果是只包含標籤的行，跳過（不加入結果）
            if ($is_label_only) {
                $prev_empty = true; // 標記為空，以便後續處理
                continue;
            }
            
            // 如果是空行，且前一行也是空行，跳過（避免連續空行）
            if ($is_empty && $prev_empty) {
                continue;
            }
            
            $cleaned_lines[] = $line;
            $prev_empty = $is_empty;
        }
        
        $text = implode("\n", $cleaned_lines);
        
        // 清理開頭和結尾的空行
        $text = trim($text);
        
        return $text;
    }

    /**
     * 取得付款狀態的中文翻譯
     * 
     * @param string $status 付款狀態代碼
     * @return string 中文翻譯
     */
    public static function get_payment_status_text($status) {
        $status_map = [
            'unpaid' => '未付款',
            'paid' => '已付款',
            'refunded' => '已退款'
        ];
        
        return $status_map[$status] ?? $status;
    }

    /**
     * 取得採購狀態的中文翻譯
     * 
     * @param string $status 採購狀態代碼
     * @return string 中文翻譯
     */
    public static function get_procurement_status_text($status) {
        $status_map = [
            'pending' => '未處理',
            'processing' => '處理中',
            'active' => '處理中',
            'purchased' => '已採購',
            'completed' => '已到貨',
            'cancelled' => '斷貨'
        ];
        
        return $status_map[$status] ?? $status;
    }

    /**
     * 取得訂單狀態的中文翻譯
     * 
     * @param string $status 訂單狀態代碼
     * @return string 中文翻譯
     */
    public static function get_order_status_text($status) {
        $status_map = [
            'active' => '進行中',
            'completed' => '已完成',
            'cancelled' => '已取消'
        ];
        
        return $status_map[$status] ?? $status;
    }

    private static function definitions() {
        return [
            // 客戶（買家）通知
            'order_created' => [
                'line' => [
                    'message' => "✅ 訂單已建立\n\n訂單編號：#{order_id}\n訂單金額：NT$ {total}\n\n感謝您的訂購！\n我們會盡快為您處理。"
                ]
            ],
            'order_cancelled' => [
                'line' => [
                    'message' => "❌ 您的訂單有異動/取消。\n\n訂單編號：{order_id}\n說明：{note}"
                ]
            ],
            'plusone_order_confirmation' => [
                'line' => [
                    'message' => "已收到您的訂單！\n商品：{product_name}\n數量：{quantity}\n金額：NT$ {total}"
                ]
            ],
            
            // 賣家通知
            'seller_order_created' => [
                'line' => [
                    'message' => "🛒 您有新的訂單！\n\n訂單編號：{order_id}\n買家：{buyer_name}\n金額：NT$ {order_total}\n\n請盡快處理訂單。"
                ]
            ],
            'seller_order_cancelled' => [
                'line' => [
                    'message' => "❌ 訂單已取消\n\n訂單編號：{order_id}\n買家：{buyer_name}\n取消原因：{note}"
                ]
            ],
            
            // 系統通知
            'system_line_follow' => [
                'line' => [
                    'message' => "歡迎使用 BuyGo 商品上架 🎉\n\n【快速開始】\n1️⃣ 發送商品圖片\n2️⃣ 發送商品資訊\n\n【格式範例】\n商品名稱\n價格：350\n數量：20\n\n💡 輸入 /help 查看完整說明"
                ]
            ],
            'flex_image_upload_menu' => [
                'type' => 'flex',
                'line' => [
                    'flex_template' => [
                        'logo_url' => 'https://pub-5ec21b01ebe8403c850311d4ddf55acd.r2.dev/2025/12/line-buygo-logo.png',
                        'title' => '圖片已收到！',
                        'description' => '請選擇您要使用的上架格式：',
                        'buttons' => [
                            ['label' => '單一商品模板', 'action' => '/one'],
                            ['label' => '多樣商品模板', 'action' => '/many'],
                            ['label' => '需要幫助', 'action' => '/help']
                        ]
                    ]
                ]
            ],
            'system_image_upload_failed' => [
                'line' => [
                    'message' => '圖片上傳失敗，請稍後再試。'
                ]
            ],
            'system_product_published' => [
                'line' => [
                    'message' => "商品名稱：{product_name}\n價格：{currency_symbol} {price}{original_price_section}\n數量：{quantity} 個{category_section}{arrival_date_section}{preorder_date_section}\n\n直接下單連結：\n{product_url}{community_url_section}"
                ]
            ],
            'system_product_publish_failed' => [
                'line' => [
                    'message' => '❌ 商品上架失敗：{error_message}'
                ]
            ],
            'system_product_data_incomplete' => [
                'line' => [
                    'message' => "商品資料不完整，缺少：{missing_fields}\n\n請使用以下格式：\n商品名稱\n價格：350\n數量：20"
                ]
            ],
            'system_keyword_reply' => [
                'line' => [
                    'message' => '關鍵字回覆訊息'
                ]
            ],
            // 命令模板
            'system_command_one_template' => [
                'line' => [
                    'message' => "📋 複製以下格式發送：\n\n商品名稱\n價格：\n數量："
                ]
            ],
            'system_command_many_template' => [
                'line' => [
                    'message' => "📋 複製以下格式發送 (多樣)：\n\n商品名稱\n價格：\n數量：\n款式1：\n款式2："
                ]
            ]
        ];
    }
}
