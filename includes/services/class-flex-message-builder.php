<?php

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FlexMessageBuilder - Flex Message 組裝與變數替換
 *
 * 負責：
 * - 組裝 LINE Flex Message JSON (build_flex_message)
 * - 模板變數替換與清理 (replace_placeholders)
 *
 * 從 NotificationTemplates 拆分而來，保持向後相容。
 */
class FlexMessageBuilder {

    /**
     * Debug Service 實例
     *
     * @var DebugService
     */
    private static $debug_service;

    /**
     * 初始化 Debug Service
     */
    private static function init_debug_service() {
        if (self::$debug_service === null) {
            self::$debug_service = DebugService::get_instance();
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
                self::$debug_service->log('FlexMessageBuilder', 'Flex 模板為空', [], 'warning');
                return null;
            }

            // 驗證必要欄位
            if (!isset($flex_template['logo_url']) || !isset($flex_template['title'])) {
                self::$debug_service->log('FlexMessageBuilder', 'Flex 模板缺少必要欄位', [
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
            self::$debug_service->log('FlexMessageBuilder', '組裝 Flex Message 失敗', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'flex_template' => $flex_template
            ], 'error');

            return null;
        }
    }

    /**
     * 替換模板中的變數佔位符，並清理多餘空行
     *
     * @param string $text 包含 {key} 佔位符的模板文字
     * @param array $args 鍵值對，用於替換佔位符
     * @return string 替換並清理後的文字
     */
    public static function replace_placeholders($text, $args) {
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
}
