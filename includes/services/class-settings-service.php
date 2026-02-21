<?php
namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Service - 設定管理服務（Facade）
 *
 * 保留所有 public static 方法簽名，將實作委派至子服務：
 * - EncryptionService：加密解密
 * - RolePermissionService：角色權限管理
 * - LineSettingsService：LINE 設定與綁定
 *
 * @package BuyGoPlus\Services
 * @since 2.0.0
 */
class SettingsService
{
    /**
     * Debug Service
     *
     * @var DebugService|null
     */
    private static $debugService = null;

    /**
     * 取得 Debug Service 實例
     *
     * @return DebugService
     */
    private static function get_debug_service(): DebugService
    {
        if (self::$debugService === null) {
            self::$debugService = DebugService::get_instance();
        }
        return self::$debugService;
    }

    // ────────────────────────────────────────────────
    // 加密相關（委派至 EncryptionService）
    // ────────────────────────────────────────────────

    /**
     * @see EncryptionService::get_encryption_key()
     */
    private static function get_encryption_key(): string
    {
        return EncryptionService::get_encryption_key();
    }

    /**
     * @see EncryptionService::cipher()
     */
    private static function cipher(): string
    {
        return EncryptionService::cipher();
    }

    /**
     * @see EncryptionService::is_encrypted_field()
     */
    private static function is_encrypted_field(string $key): bool
    {
        return EncryptionService::is_encrypted_field($key);
    }

    /**
     * @see EncryptionService::encrypt()
     */
    private static function encrypt(string $data): string
    {
        return EncryptionService::encrypt($data);
    }

    /**
     * @see EncryptionService::decrypt()
     */
    private static function decrypt(string $data): string
    {
        return EncryptionService::decrypt($data);
    }

    // ────────────────────────────────────────────────
    // 角色權限（委派至 RolePermissionService）
    // ────────────────────────────────────────────────

    /**
     * @see RolePermissionService::init_roles()
     */
    public static function init_roles(): void
    {
        RolePermissionService::init_roles();
    }

    /**
     * @see RolePermissionService::get_helpers()
     */
    public static function get_helpers(?int $seller_id = null): array
    {
        return RolePermissionService::get_helpers($seller_id);
    }

    /**
     * @see RolePermissionService::add_helper()
     */
    public static function add_helper(int $user_id, string $role = 'buygo_helper', ?int $seller_id = null): bool
    {
        return RolePermissionService::add_helper($user_id, $role, $seller_id);
    }

    /**
     * @see RolePermissionService::remove_helper()
     */
    public static function remove_helper(int $user_id, ?int $seller_id = null): bool
    {
        return RolePermissionService::remove_helper($user_id, $seller_id);
    }

    /**
     * @see RolePermissionService::remove_role()
     */
    public static function remove_role(int $user_id, string $role): bool
    {
        return RolePermissionService::remove_role($user_id, $role);
    }

    /**
     * @see RolePermissionService::upgrade_lister_to_helper()
     */
    public static function upgrade_lister_to_helper(int $user_id): bool
    {
        return RolePermissionService::upgrade_lister_to_helper($user_id);
    }

    /**
     * @see RolePermissionService::get_accessible_seller_ids()
     */
    public static function get_accessible_seller_ids(?int $user_id = null): array
    {
        return RolePermissionService::get_accessible_seller_ids($user_id);
    }

    /**
     * @see RolePermissionService::is_seller()
     */
    public static function is_seller(?int $user_id = null): bool
    {
        return RolePermissionService::is_seller($user_id);
    }

    /**
     * @see RolePermissionService::is_helper()
     */
    public static function is_helper(?int $user_id = null): bool
    {
        return RolePermissionService::is_helper($user_id);
    }

    /**
     * @see RolePermissionService::can_manage_helpers()
     */
    public static function can_manage_helpers(?int $user_id = null): bool
    {
        return RolePermissionService::can_manage_helpers($user_id);
    }

    // ────────────────────────────────────────────────
    // 小幫手細粒度權限（v2.0）
    // ────────────────────────────────────────────────

    /**
     * 檢查用戶是否有特定操作權限
     *
     * 賣家和 WP Admin 永遠回傳 true。
     * 小幫手依 user_meta 中的 buygo_helper_permissions 判斷。
     * 未設定時預設全部開啟（向後相容）。
     *
     * @param string   $permission  權限名稱：listing, products, orders, shipments, customers, settings
     * @param int|null $user_id     用戶 ID，null 為當前用戶
     * @return bool
     */
    public static function helper_can(string $permission, ?int $user_id = null): bool
    {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // 賣家和 WP Admin 永遠有權限
        if (in_array('administrator', (array) $user->roles) || in_array('buygo_admin', (array) $user->roles)) {
            return true;
        }

        // 上架幫手：固定權限（listing + products）
        if (in_array('buygo_lister', (array) $user->roles)) {
            return in_array($permission, ['listing', 'products']);
        }

        // 小幫手：檢查細粒度權限
        if (in_array('buygo_helper', (array) $user->roles)) {
            $permissions = get_user_meta($user_id, 'buygo_helper_permissions', true);
            if (empty($permissions) || !is_array($permissions)) {
                return true; // 未設定 = 全部開啟（向後相容）
            }
            return !empty($permissions[$permission]);
        }

        return false;
    }

    /**
     * 取得小幫手的所有權限設定
     *
     * @param int $user_id
     * @return array
     */
    public static function get_helper_permissions(int $user_id): array
    {
        $defaults = [
            'listing'   => true,
            'products'  => true,
            'orders'    => true,
            'shipments' => true,
            'customers' => true,
            'settings'  => true,
        ];
        $saved = get_user_meta($user_id, 'buygo_helper_permissions', true);
        if (empty($saved) || !is_array($saved)) {
            return $defaults;
        }
        return array_merge($defaults, $saved);
    }

    /**
     * 儲存小幫手的權限設定
     *
     * @param int   $user_id
     * @param array $permissions
     * @return bool
     */
    public static function save_helper_permissions(int $user_id, array $permissions): bool
    {
        $valid_keys = ['listing', 'products', 'orders', 'shipments', 'customers', 'settings'];
        $clean = [];
        foreach ($valid_keys as $key) {
            $clean[$key] = !empty($permissions[$key]);
        }
        // update_user_meta() 在值沒有變化時回傳 false，不代表失敗
        // 改用 update 後再 get 來驗證結果
        update_user_meta($user_id, 'buygo_helper_permissions', $clean);
        $saved = get_user_meta($user_id, 'buygo_helper_permissions', true);
        return $saved === $clean;
    }

    // ────────────────────────────────────────────────
    // 模板設定（保留在此，直接操作 NotificationTemplates）
    // ────────────────────────────────────────────────

    /**
     * 取得模板設定（統一使用 NotificationTemplates 系統）
     *
     * @return array
     */
    public static function get_templates(): array
    {
        // 使用 NotificationTemplates 系統取得所有模板
        $all_templates = \BuyGoPlus\Services\NotificationTemplates::get_all_templates();

        // 分類模板
        $buyer_templates = [];
        $seller_templates = [];
        $system_templates = [];

        // 買家通知
        $buyer_keys = ['order_created', 'order_cancelled', 'plusone_order_confirmation'];
        foreach ($buyer_keys as $key) {
            if (isset($all_templates[$key])) {
                $buyer_templates[$key] = $all_templates[$key];
            }
        }

        // 賣家通知
        $seller_keys = ['seller_order_created', 'seller_order_cancelled'];
        foreach ($seller_keys as $key) {
            if (isset($all_templates[$key])) {
                $seller_templates[$key] = $all_templates[$key];
            }
        }

        // 系統通知
        $system_keys = [
            'system_line_follow',
            'flex_image_upload_menu',
            'system_image_upload_failed',
            'system_product_published',
            'system_product_publish_failed',
            'system_product_data_incomplete',
            'system_keyword_reply'
        ];
        foreach ($system_keys as $key) {
            if (isset($all_templates[$key])) {
                $system_templates[$key] = $all_templates[$key];
            }
        }

        return [
            'buyer' => $buyer_templates,
            'seller' => $seller_templates,
            'system' => $system_templates,
            'all' => $all_templates
        ];
    }

    /**
     * 更新模板設定（統一使用 NotificationTemplates 系統）
     * 資料格式會自動標準化，確保前後端一致
     *
     * @param array $templates 完整的模板資料結構
     * @return bool
     */
    public static function update_templates(array $templates): bool
    {
        // 取得所有現有自訂模板
        $all_custom = get_option('buygo_notification_templates', []);

        // 處理每個提交的模板
        foreach ($templates as $key => $template_data) {
            if (isset($template_data['type']) && $template_data['type'] === 'flex') {
                // Flex Message 模板
                $flex_template = $template_data['line']['flex_template'] ?? [];

                if (!empty($flex_template)) {
                    $all_custom[$key] = [
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

                    // 處理按鈕
                    if (isset($flex_template['buttons']) && is_array($flex_template['buttons'])) {
                        foreach ($flex_template['buttons'] as $button) {
                            if (!empty($button['label']) || !empty($button['action'])) {
                                $all_custom[$key]['line']['flex_template']['buttons'][] = [
                                    'label' => sanitize_text_field($button['label'] ?? ''),
                                    'action' => sanitize_text_field($button['action'] ?? '')
                                ];
                            }
                        }
                    }
                }
            } elseif (isset($template_data['line']['message'])) {
                // 文字模板
                $all_custom[$key] = [
                    'line' => [
                        'message' => sanitize_textarea_field($template_data['line']['message'])
                    ]
                ];
            } elseif (isset($template_data['line']['flex_template'])) {
                // Flex Message 模板（另一種格式）
                $flex_template = $template_data['line']['flex_template'];
                $all_custom[$key] = [
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

                if (isset($flex_template['buttons']) && is_array($flex_template['buttons'])) {
                    foreach ($flex_template['buttons'] as $button) {
                        if (!empty($button['label']) || !empty($button['action'])) {
                            $all_custom[$key]['line']['flex_template']['buttons'][] = [
                                'label' => sanitize_text_field($button['label'] ?? ''),
                                'action' => sanitize_text_field($button['action'] ?? '')
                            ];
                        }
                    }
                }
            }
        }

        // 使用 NotificationTemplates 系統儲存（會自動標準化資料格式）
        \BuyGoPlus\Services\NotificationTemplates::save_custom_templates($all_custom);

        return true;
    }

    // ────────────────────────────────────────────────
    // LINE 設定（委派至 LineSettingsService）
    // ────────────────────────────────────────────────

    /**
     * @see LineSettingsService::get_line_binding_status()
     */
    public static function get_line_binding_status(int $user_id): array
    {
        return LineSettingsService::get_line_binding_status($user_id);
    }

    /**
     * @see LineSettingsService::get_helpers_with_line_status()
     */
    public static function get_helpers_with_line_status(?int $seller_id = null): array
    {
        return LineSettingsService::get_helpers_with_line_status($seller_id);
    }

    /**
     * @see LineSettingsService::get_line_settings()
     */
    public static function get_line_settings(): array
    {
        return LineSettingsService::get_line_settings();
    }

    /**
     * @see LineSettingsService::update_line_settings()
     */
    public static function update_line_settings(array $settings): bool
    {
        return LineSettingsService::update_line_settings($settings);
    }

    /**
     * @see LineSettingsService::get_user_line_id()
     */
    public static function get_user_line_id(int $user_id): ?string
    {
        return LineSettingsService::get_user_line_id($user_id);
    }

    /**
     * @see LineSettingsService::send_binding_link()
     */
    public static function send_binding_link(int $user_id): array
    {
        return LineSettingsService::send_binding_link($user_id);
    }

    // ────────────────────────────────────────────────
    // 通用設定（保留在此）
    // ────────────────────────────────────────────────

    /**
     * 取得設定值（通用方法，支援兩種儲存方式）
     *
     * 支援兩種 option key：
     * 1. buygo_core_settings（舊外掛使用，陣列格式，支援加密）
     * 2. buygo_line_*（新外掛使用，獨立 option）
     *
     * @param string $key 設定 key（例如 'line_channel_access_token'）
     * @param mixed $default 預設值
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        // 方式 1：優先從獨立 option 讀取（新外掛格式）
        // 這些是後台設定頁面保存的值，優先使用
        $option_key_map = [
            'line_channel_access_token' => 'buygo_line_channel_access_token',
            'line_channel_secret' => 'buygo_line_channel_secret',
            'line_liff_id' => 'buygo_line_liff_id',
        ];

        if (isset($option_key_map[$key])) {
            $value = get_option($option_key_map[$key], '');

            if (!empty($value)) {
                self::get_debug_service()->log('SettingsService', '從獨立 option 讀取設定', array(
                    'key' => $key,
                    'value_length' => strlen($value),
                ));

                // 新外掛的資料也可能是加密的，嘗試解密
                if (EncryptionService::is_encrypted_field($key)) {
                    $decrypted = EncryptionService::decrypt($value);
                    // 如果解密成功且結果與原值不同，使用解密後的值
                    if ($decrypted !== false && $decrypted !== $value && !empty($decrypted)) {
                        self::get_debug_service()->log('SettingsService', '解密成功', array(
                            'key' => $key,
                        ));
                        return $decrypted;
                    }
                }

                // 如果不需要解密或解密失敗，返回原值（可能是明文）
                return $value;
            }
        }

        // 方式 2：如果新外掛沒有值，從 buygo_core_settings 讀取（舊外掛格式）
        $core_settings = get_option('buygo_core_settings', []);
        if (is_array($core_settings) && isset($core_settings[$key])) {
            $value = $core_settings[$key];

            self::get_debug_service()->log('SettingsService', '從 buygo_core_settings 讀取設定（備用）', array(
                'key' => $key,
                'value_length' => strlen($value),
            ));

            // 如果是加密欄位，嘗試解密
            if (EncryptionService::is_encrypted_field($key) && !empty($value)) {
                $decrypted = EncryptionService::decrypt($value);

                // 如果解密成功（返回非 false 且不為空），使用解密後的值
                if ($decrypted !== false && !empty($decrypted)) {
                    self::get_debug_service()->log('SettingsService', '使用解密後的值', array(
                        'key' => $key,
                    ));
                    return $decrypted;
                }
            }

            return $value;
        }

        self::get_debug_service()->log('SettingsService', '找不到設定，使用預設值', array(
            'key' => $key,
        ), 'warning');

        return $default;
    }

    /**
     * 設定值（通用方法，支援兩種儲存方式）
     *
     * @param string $key 設定 key
     * @param mixed $value 設定值
     * @return bool
     */
    public static function set(string $key, $value): bool
    {
        // 如果是加密欄位，先加密
        if (EncryptionService::is_encrypted_field($key) && !empty($value)) {
            $value = EncryptionService::encrypt($value);
        }

        // 方式 1：寫入 buygo_core_settings（舊外掛格式）
        $core_settings = get_option('buygo_core_settings', []);
        if (!is_array($core_settings)) {
            $core_settings = [];
        }
        $core_settings[$key] = $value;
        update_option('buygo_core_settings', $core_settings);

        // 方式 2：同時寫入獨立 option（新外掛格式，保持向後相容）
        $option_key_map = [
            'line_channel_access_token' => 'buygo_line_channel_access_token',
            'line_channel_secret' => 'buygo_line_channel_secret',
            'line_liff_id' => 'buygo_line_liff_id',
        ];

        if (isset($option_key_map[$key])) {
            update_option($option_key_map[$key], $value);
        }

        return true;
    }

    // ────────────────────────────────────────────────
    // LINE 連線測試（保留在此）
    // ────────────────────────────────────────────────

    /**
     * 測試 LINE 連線
     *
     * @param string|null $custom_token 測試用的 Token（選填，若未填則使用已儲存的設定）
     * @return array
     */
    public static function test_line_connection(?string $custom_token = null): array
    {
        self::get_debug_service()->log('SettingsService', '開始測試 LINE 連線', array(
            'using_custom_token' => !empty($custom_token),
        ));

        try {
            if (!empty($custom_token)) {
                $token = $custom_token;
            } else {
                $settings = self::get_line_settings();
                $token = $settings['channel_access_token'] ?? '';
            }

            if (empty($token)) {
                self::get_debug_service()->log('SettingsService', 'Token 未設定', [], 'warning');
                return [
                    'success' => false,
                    'message' => 'Channel Access Token 未設定'
                ];
            }

            // 測試 API 呼叫
            $response = wp_remote_get('https://api.line.me/v2/bot/info', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token
                ],
                'timeout' => 10
            ]);

            if (is_wp_error($response)) {
                self::get_debug_service()->log('SettingsService', 'LINE API 連線失敗', array(
                    'error' => $response->get_error_message(),
                ), 'error');
                return [
                    'success' => false,
                    'message' => '連線失敗：' . $response->get_error_message()
                ];
            }

            $status_code = wp_remote_retrieve_response_code($response);

            if ($status_code === 200) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                self::get_debug_service()->log('SettingsService', 'LINE 連線測試成功', array(
                    'bot_info' => $body,
                ));
                return [
                    'success' => true,
                    'message' => '連線成功',
                    'data' => $body
                ];
            } else {
                // 嘗試解析錯誤訊息
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $error_msg = $body['message'] ?? 'HTTP ' . $status_code;

                self::get_debug_service()->log('SettingsService', 'LINE API 返回錯誤', array(
                    'status_code' => $status_code,
                    'error_message' => $error_msg,
                ), 'error');

                return [
                    'success' => false,
                    'message' => '連線失敗：' . $error_msg
                ];
            }

        } catch (\Exception $e) {
            self::get_debug_service()->log('SettingsService', '測試 LINE 連線時發生異常', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ), 'error');

            return [
                'success' => false,
                'message' => '測試連線時發生錯誤：' . $e->getMessage()
            ];
        }
    }
}
