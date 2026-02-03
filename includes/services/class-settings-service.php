<?php
namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Service - 設定管理服務
 *
 * 負責管理外掛的各種設定，使用 WordPress Options API 儲存
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
    /**
     * 加密金鑰（可在 wp-config.php 中定義 BUYGO_ENCRYPTION_KEY）
     * 注意：必須與舊外掛使用相同的預設金鑰，才能正確解密舊資料
     */
    private static function get_encryption_key(): string
    {
        return defined('BUYGO_ENCRYPTION_KEY') ? BUYGO_ENCRYPTION_KEY : 'buygo-secret-key-default';
    }
    
    /**
     * 加密方法
     */
    private static function cipher(): string
    {
        return 'AES-128-ECB';
    }
    
    /**
     * 檢查欄位是否需要加密
     */
    private static function is_encrypted_field(string $key): bool
    {
        $encrypted_fields = [
            'line_channel_secret',
            'line_channel_access_token',
            'line_login_channel_secret',
        ];
        return in_array($key, $encrypted_fields, true);
    }
    
    /**
     * 加密資料
     */
    private static function encrypt(string $data): string
    {
        if (empty($data)) {
            return $data;
        }
        return openssl_encrypt($data, self::cipher(), self::get_encryption_key());
    }
    
    /**
     * 解密資料
     */
    private static function decrypt(string $data): string
    {
        if (empty($data)) {
            self::get_debug_service()->log('SettingsService', '解密：空資料', [], 'warning');
            return $data;
        }

        self::get_debug_service()->log('SettingsService', '開始解密', array(
            'input_length' => strlen($data),
            'cipher' => self::cipher(),
        ));

        try {
            $decrypted = openssl_decrypt($data, self::cipher(), self::get_encryption_key());

            if ($decrypted === false) {
                $error = openssl_error_string();
                self::get_debug_service()->log('SettingsService', '解密失敗', array(
                    'openssl_error' => $error,
                ), 'error');
                return $data;
            }

            self::get_debug_service()->log('SettingsService', '解密成功', array(
                'output_length' => strlen($decrypted),
            ));

            return $decrypted;

        } catch (\Exception $e) {
            self::get_debug_service()->log('SettingsService', '解密異常', array(
                'error' => $e->getMessage(),
            ), 'error');
            return $data;
        }
    }
    /**
     * 初始化角色權限
     * 
     * @return void
     */
    public static function init_roles(): void
    {
        // 建立 BuyGo 管理員角色
        if (!get_role('buygo_admin')) {
            add_role('buygo_admin', 'BuyGo 管理員', [
                'read' => true,
                'buygo_manage_all' => true,
                'buygo_add_helper' => true,
            ]);
        }
        
        // 建立 BuyGo 小幫手角色
        if (!get_role('buygo_helper')) {
            add_role('buygo_helper', 'BuyGo 小幫手', [
                'read' => true,
                'buygo_manage_all' => true,
                'buygo_add_helper' => false,
            ]);
        }
    }
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
    
    /**
     * 取得小幫手列表（依 seller_id 過濾）
     *
     * @param int|null $seller_id 管理員 ID，若為 null 則使用當前使用者
     * @return array
     */
    public static function get_helpers(?int $seller_id = null): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_helpers';

        // 如果沒有指定 seller_id，使用當前使用者
        if ($seller_id === null) {
            $seller_id = get_current_user_id();
        }

        // 檢查資料表是否存在（向後相容）
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            // 使用舊的 Option API（向後相容）
            return self::get_helpers_from_option();
        }

        // 從資料表查詢
        $helper_records = $wpdb->get_results($wpdb->prepare(
            "SELECT helper_id, created_at FROM {$table_name} WHERE seller_id = %d ORDER BY created_at DESC",
            $seller_id
        ));

        $helpers = [];
        foreach ($helper_records as $record) {
            $user = get_userdata($record->helper_id);
            if ($user) {
                // 取得頭像（優先使用 FluentCommunity 頭像，否則使用 Gravatar）
                $avatar_url = get_user_meta($user->ID, 'fc_customer_photo_url', true);
                if (empty($avatar_url)) {
                    $avatar_url = get_avatar_url($user->user_email, ['size' => 100]);
                }

                $helpers[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                    'avatar' => $avatar_url,
                    'created_at' => $record->created_at,
                ];
            }
        }

        // 如果資料表查詢為空，嘗試從 Option API 取得（向後相容）
        if (empty($helpers)) {
            $helpers = self::get_helpers_from_option();
        }

        return $helpers;
    }

    /**
     * 舊版取得小幫手（從 Option，向後相容用）
     *
     * @return array
     */
    private static function get_helpers_from_option(): array
    {
        $helper_ids = get_option('buygo_helpers', []);

        if (empty($helper_ids) || !is_array($helper_ids)) {
            return [];
        }

        $helpers = [];
        foreach ($helper_ids as $user_id) {
            $user = get_userdata($user_id);
            if ($user) {
                // 取得頭像：優先使用 FluentCommunity 照片，否則使用 Gravatar
                $avatar_url = get_user_meta($user->ID, 'fc_customer_photo_url', true);
                if (empty($avatar_url)) {
                    $avatar_url = get_avatar_url($user->user_email, ['size' => 100]);
                }

                $helpers[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                    'avatar' => $avatar_url,
                ];
            }
        }

        return $helpers;
    }
    
    /**
     * 新增小幫手或管理員（記錄 seller_id）
     *
     * @param int $user_id 使用者 ID
     * @param string $role 角色：'buygo_helper' 或 'buygo_admin'
     * @param int|null $seller_id 管理員 ID，若為 null 則使用當前使用者
     * @return bool
     */
    public static function add_helper(int $user_id, string $role = 'buygo_helper', ?int $seller_id = null): bool
    {
        self::get_debug_service()->log('SettingsService', '開始新增小幫手/管理員', array(
            'helper_id' => $user_id,
            'role' => $role,
            'seller_id' => $seller_id,
        ));

        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'buygo_helpers';

            $user = get_userdata($user_id);
            if (!$user) {
                self::get_debug_service()->log('SettingsService', '使用者不存在', array(
                    'helper_id' => $user_id,
                ), 'warning');
                return false;
            }

        // 如果沒有指定 seller_id，使用當前使用者
        if ($seller_id === null) {
            $seller_id = get_current_user_id();
        }

        if ($role === 'buygo_helper') {
            // 檢查資料表是否存在
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
                // 檢查是否已存在
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE helper_id = %d AND seller_id = %d",
                    $user_id,
                    $seller_id
                ));

                if (!$exists) {
                    // 插入到新資料表
                    $wpdb->insert(
                        $table_name,
                        [
                            'helper_id' => $user_id,
                            'seller_id' => $seller_id,
                        ],
                        ['%d', '%d']
                    );
                }
            }

            // 向後相容：也同時更新 Option API
            $helper_ids = get_option('buygo_helpers', []);
            if (!is_array($helper_ids)) {
                $helper_ids = [];
            }
            if (!in_array($user_id, $helper_ids)) {
                $helper_ids[] = $user_id;
                update_option('buygo_helpers', $helper_ids);
            }

            // 賦予小幫手角色
            $user->add_role('buygo_helper');
        } elseif ($role === 'buygo_admin') {
            // 新增管理員
            $user->add_role('buygo_admin');
        }

            self::get_debug_service()->log('SettingsService', '新增小幫手/管理員成功', array(
                'helper_id' => $user_id,
                'role' => $role,
            ));

            return true;

        } catch (\Exception $e) {
            self::get_debug_service()->log('SettingsService', '新增小幫手/管理員失敗', array(
                'helper_id' => $user_id,
                'role' => $role,
                'error' => $e->getMessage(),
            ), 'error');
            return false;
        }
    }
    
    /**
     * 移除小幫手
     *
     * @param int $user_id 使用者 ID
     * @param int|null $seller_id 管理員 ID，若為 null 則使用當前使用者
     * @return bool
     */
    public static function remove_helper(int $user_id, ?int $seller_id = null): bool
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_helpers';

        // 如果沒有指定 seller_id，使用當前使用者
        if ($seller_id === null) {
            $seller_id = get_current_user_id();
        }

        // 檢查資料表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            // 從新資料表刪除
            $wpdb->delete(
                $table_name,
                [
                    'helper_id' => $user_id,
                    'seller_id' => $seller_id,
                ],
                ['%d', '%d']
            );
        }

        // 向後相容：也從 Option 中移除
        $helper_ids = get_option('buygo_helpers', []);
        if (is_array($helper_ids)) {
            $key = array_search($user_id, $helper_ids);
            if ($key !== false) {
                unset($helper_ids[$key]);
                $helper_ids = array_values($helper_ids);
                update_option('buygo_helpers', $helper_ids);
            }
        }

        // 檢查使用者是否還是其他賣家的小幫手
        $remaining = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            $remaining = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE helper_id = %d",
                $user_id
            ));
        }

        // 如果沒有其他關聯，移除角色
        if (!$remaining) {
            $user = get_userdata($user_id);
            if ($user) {
                $user->remove_role('buygo_helper');
            }
        }

        return true;
    }
    
    /**
     * 移除角色（管理員或小幫手）
     * 
     * @param int $user_id
     * @param string $role 角色：'buygo_helper' 或 'buygo_admin'
     * @return bool
     */
    public static function remove_role(int $user_id, string $role): bool
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        if ($role === 'buygo_helper') {
            // 移除小幫手
            // 1. 從選項中移除
            self::remove_helper($user_id);
            // 2. 移除角色（如果有的話）
            if (in_array('buygo_helper', $user->roles)) {
                $user->remove_role('buygo_helper');
            }
        } elseif ($role === 'buygo_admin') {
            // 移除管理員角色
            if (in_array('buygo_admin', $user->roles)) {
                $user->remove_role('buygo_admin');
            }
            // 如果也在小幫手列表中，也移除
            $helper_ids = get_option('buygo_helpers', []);
            if (is_array($helper_ids) && in_array($user_id, $helper_ids)) {
                self::remove_helper($user_id);
            }
        }
        
        return true;
    }

    /**
     * 取得使用者可存取的 seller_ids 列表
     *
     * 用於多賣家隔離：
     * - 如果是 buygo_admin，只返回自己的 ID
     * - 如果是 buygo_helper，返回所有授權賣場的 seller_ids
     * - 如果同時是多個賣場的小幫手，返回所有 seller_ids
     *
     * @param int|null $user_id 使用者 ID，若為 null 則使用當前使用者
     * @return array<int> 可存取的 seller_ids 列表
     */
    public static function get_accessible_seller_ids(?int $user_id = null): array
    {
        global $wpdb;

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return [];
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }

        $seller_ids = [];

        // 檢查是否為 buygo_admin（賣家本人）
        if (in_array('buygo_admin', (array) $user->roles, true)) {
            $seller_ids[] = $user_id;
        }

        // 檢查是否為 buygo_helper（小幫手）
        // 從 wp_buygo_helpers 表查詢此用戶被授權的賣場
        $table_name = $wpdb->prefix . 'buygo_helpers';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            $authorized_sellers = $wpdb->get_col($wpdb->prepare(
                "SELECT seller_id FROM {$table_name} WHERE helper_id = %d",
                $user_id
            ));

            foreach ($authorized_sellers as $seller_id) {
                if (!in_array((int) $seller_id, $seller_ids, true)) {
                    $seller_ids[] = (int) $seller_id;
                }
            }
        }

        return $seller_ids;
    }

    /**
     * 檢查使用者是否為賣家（buygo_admin）
     *
     * @param int|null $user_id 使用者 ID，若為 null 則使用當前使用者
     * @return bool
     */
    public static function is_seller(?int $user_id = null): bool
    {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        return in_array('buygo_admin', (array) $user->roles, true);
    }

    /**
     * 檢查使用者是否為小幫手（buygo_helper）
     *
     * @param int|null $user_id 使用者 ID，若為 null 則使用當前使用者
     * @return bool
     */
    public static function is_helper(?int $user_id = null): bool
    {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        return in_array('buygo_helper', (array) $user->roles, true);
    }

    /**
     * 檢查使用者是否可以管理小幫手
     *
     * 只有賣家（buygo_admin）可以新增/移除小幫手
     * 小幫手不能管理其他小幫手
     *
     * @param int|null $user_id 使用者 ID，若為 null 則使用當前使用者
     * @return bool
     */
    public static function can_manage_helpers(?int $user_id = null): bool
    {
        return self::is_seller($user_id);
    }

    /**
     * 檢查使用者的 LINE 綁定狀態
     *
     * @param int $user_id 使用者 ID
     * @return array 包含 is_linked 和 line_uid
     */
    public static function get_line_binding_status(int $user_id): array
    {
        $line_uid = self::get_user_line_id($user_id);

        return [
            'is_linked' => !empty($line_uid),
            'line_uid' => $line_uid,
        ];
    }

    /**
     * 取得小幫手列表（含 LINE 綁定狀態）
     *
     * @param int|null $seller_id 管理員 ID，若為 null 則使用當前使用者
     * @return array
     */
    public static function get_helpers_with_line_status(?int $seller_id = null): array
    {
        $helpers = self::get_helpers($seller_id);

        foreach ($helpers as &$helper) {
            $line_status = self::get_line_binding_status($helper['id']);
            $helper['line_linked'] = $line_status['is_linked'];
            $helper['line_uid'] = $line_status['line_uid'];
        }

        return $helpers;
    }

    /**
     * 取得 LINE 設定（自動解密敏感資料）
     *
     * @return array
     */
    public static function get_line_settings(): array
    {
        $token_raw = get_option('buygo_line_channel_access_token', '');
        $secret_raw = get_option('buygo_line_channel_secret', '');
        
        // 嘗試解密敏感資料（如果解密失敗，使用原始值）
        $token = $token_raw;
        if (!empty($token_raw) && self::is_encrypted_field('line_channel_access_token')) {
            $decrypted = self::decrypt($token_raw);
            // 如果解密成功且結果不同，使用解密後的值
            if ($decrypted !== $token_raw && !empty($decrypted)) {
                $token = $decrypted;
            }
        }
        
        $secret = $secret_raw;
        if (!empty($secret_raw) && self::is_encrypted_field('line_channel_secret')) {
            $decrypted = self::decrypt($secret_raw);
            // 如果解密成功且結果不同，使用解密後的值
            if ($decrypted !== $secret_raw && !empty($decrypted)) {
                $secret = $decrypted;
            }
        }
        
        return [
            'channel_access_token' => $token,
            'channel_secret' => $secret,
            'liff_id' => get_option('buygo_line_liff_id', ''),
            'webhook_url' => rest_url('buygo-plus-one/v1/line/webhook'),
        ];
    }
    
    /**
     * 更新 LINE 設定（自動加密敏感資料）
     * 
     * @param array $settings
     * @return bool
     */
    public static function update_line_settings(array $settings): bool
    {
        if (isset($settings['channel_access_token'])) {
            $token = sanitize_text_field($settings['channel_access_token']);
            // 加密儲存
            if (self::is_encrypted_field('line_channel_access_token') && !empty($token)) {
                $token = self::encrypt($token);
            }
            update_option('buygo_line_channel_access_token', $token);
        }
        
        if (isset($settings['channel_secret'])) {
            $secret = sanitize_text_field($settings['channel_secret']);
            // 加密儲存
            if (self::is_encrypted_field('line_channel_secret') && !empty($secret)) {
                $secret = self::encrypt($secret);
            }
            update_option('buygo_line_channel_secret', $secret);
        }
        
        if (isset($settings['liff_id'])) {
            update_option('buygo_line_liff_id', sanitize_text_field($settings['liff_id']));
        }
        
        return true;
    }
    
    /**
     * 取得使用者的 LINE User ID
     * 
     * 支援兩種儲存方式：
     * 1. wp_usermeta 表（優先，meta_key: _mygo_line_uid, buygo_line_user_id, m_line_user_id, line_user_id）
     * 2. wp_social_users 表（備用，由社交登入外掛建立）
     * 
     * @param int $user_id WordPress 使用者 ID
     * @return string|null LINE User ID，如果未綁定則返回 null
     */
    public static function get_user_line_id(int $user_id): ?string
    {
        // 方式 1：從 wp_usermeta 查詢（優先）
        // line_uid 是 buygo-line-notify 使用的 meta key（最優先）
        // _mygo_line_uid 是舊系統使用的 meta key（向後相容）
        $meta_keys = ['line_uid', '_mygo_line_uid', 'buygo_line_user_id', 'm_line_user_id', 'line_user_id'];
        
        foreach ($meta_keys as $meta_key) {
            $line_id = get_user_meta($user_id, $meta_key, true);
            if (!empty($line_id)) {
                return $line_id;
            }
        }
        
        // 方式 2：從 wp_buygo_line_users 表查詢（buygo-line-notify 外掛專用）
        global $wpdb;
        $buygo_line_users_table = $wpdb->prefix . 'buygo_line_users';

        // 檢查 buygo-line-notify 資料表是否存在
        $buygo_table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $buygo_line_users_table
        ));

        if ($buygo_table_exists) {
            $line_id = $wpdb->get_var($wpdb->prepare(
                "SELECT identifier FROM {$buygo_line_users_table} WHERE user_id = %d AND type = 'line' LIMIT 1",
                $user_id
            ));

            if (!empty($line_id)) {
                return $line_id;
            }
        }

        // 方式 3：從 wp_social_users 表查詢（備用）
        // 某些社交登入外掛（如 Super Socializer）會將 UID 儲存在此表
        // 注意：此表的 User ID 欄位名稱是 ID（大寫），不是 user_id
        $social_users_table = $wpdb->prefix . 'social_users';
        
        // 檢查資料表是否存在
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $social_users_table
        ));
        
        if ($table_exists) {
            $line_id = $wpdb->get_var($wpdb->prepare(
                "SELECT identifier FROM {$social_users_table} WHERE ID = %d AND type = 'line' LIMIT 1",
                $user_id
            ));
            
            if (!empty($line_id)) {
                return $line_id;
            }
        }
        
        return null;
    }
    
    /**
     * 發送賣家設定通知
     *
     * 優先透過 LINE 發送訊息（如果已綁定）
     * 如果未綁定則透過 Email 發送綁定連結
     *
     * @param int $user_id WordPress 使用者 ID
     * @return array 包含 success 和 message
     */
    public static function send_binding_link(int $user_id): array
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return [
                'success' => false,
                'message' => '使用者不存在'
            ];
        }

        // 檢查是否已綁定 LINE
        $line_uid = self::get_user_line_id($user_id);

        // 情況 1: 已綁定 LINE → 直接發送 LINE 訊息通知賣家設定完成
        if (!empty($line_uid)) {
            // 檢查 buygo-line-notify 外掛是否啟用
            if (!class_exists('\\BuygoLineNotify\\BuygoLineNotify')) {
                return [
                    'success' => false,
                    'message' => '請先啟用 BuyGo LINE Notify 外掛'
                ];
            }

            // 發送 LINE 訊息
            $message = "🎉 您已成為 BuyGo 賣家！\n\n";
            $message .= "您現在可以透過 LINE 上架商品：\n";
            $message .= "1️⃣ 直接上傳商品圖片\n";
            $message .= "2️⃣ 輸入商品資訊（名稱/價格/描述）\n";
            $message .= "3️⃣ 系統自動上架到商城\n\n";
            $message .= "立即上傳第一張商品圖片試試看吧！";

            try {
                $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
                $messaging->pushText($line_uid, $message);

                return [
                    'success' => true,
                    'message' => '賣家設定完成通知已透過 LINE 發送'
                ];
            } catch (\Exception $e) {
                error_log('[Settings] LINE 訊息發送失敗: ' . $e->getMessage());
                return [
                    'success' => false,
                    'message' => 'LINE 訊息發送失敗：' . $e->getMessage()
                ];
            }
        }

        // 情況 2: 未綁定 LINE → 透過 Email 發送綁定連結
        $line_settings = self::get_line_settings();
        $channel_access_token = $line_settings['channel_access_token'] ?? '';

        if (empty($channel_access_token)) {
            return [
                'success' => false,
                'message' => 'LINE Channel Access Token 未設定'
            ];
        }

        // 產生綁定連結
        $binding_url = wp_login_url() . '?action=line&redirect_to=' . urlencode(admin_url('admin.php?page=buygo-settings&tab=roles'));

        if (!empty($user->user_email)) {
            $subject = 'BuyGo+1 LINE 帳號綁定連結';
            $email_message = "親愛的 {$user->display_name}，\n\n您已成為 BuyGo 賣家，請先完成 LINE 帳號綁定：\n{$binding_url}\n\n綁定後即可透過 LINE 上架商品。\n\n如果無法點擊連結，請複製以下網址到瀏覽器：\n{$binding_url}";

            $email_sent = wp_mail($user->user_email, $subject, $email_message);

            if ($email_sent) {
                return [
                    'success' => true,
                    'message' => 'LINE 綁定連結已透過 Email 發送給 ' . $user->user_email
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Email 發送失敗，請檢查 WordPress 郵件設定'
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => '使用者沒有 Email，無法發送綁定連結'
        ];
    }
    
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
                if (self::is_encrypted_field($key)) {
                    $decrypted = self::decrypt($value);
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
            if (self::is_encrypted_field($key) && !empty($value)) {
                $decrypted = self::decrypt($value);

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
        if (self::is_encrypted_field($key) && !empty($value)) {
            $value = self::encrypt($value);
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
