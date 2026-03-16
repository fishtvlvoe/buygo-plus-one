<?php
namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LINE Settings Service - LINE 相關設定服務
 *
 * 負責 LINE 設定的讀寫（含加密解密）、使用者 LINE 綁定狀態查詢、
 * 以及發送綁定連結通知。
 *
 * @package BuyGoPlus\Services
 * @since 2.1.0
 */
class LineSettingsService
{
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
        $helpers = RolePermissionService::get_helpers($seller_id);

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
        // 優先使用 LINE Hub 設定（統一管理）
        if (class_exists('LineHub\Services\SettingsService')) {
            try {
                $hub_settings = \LineHub\Services\SettingsService::get_group('general');

                // 如果 LINE Hub 有設定 Access Token，就使用 LINE Hub 的設定
                if (!empty($hub_settings['access_token'])) {
                    return [
                        'channel_access_token' => $hub_settings['access_token'],
                        'channel_secret' => $hub_settings['channel_secret'] ?? '',
                        'liff_id' => $hub_settings['liff_id'] ?? '',
                        'webhook_url' => rest_url('line-hub/v1/webhook'),
                    ];
                }
            } catch (\Exception $e) {
                error_log('[BuyGo] 無法讀取 LINE Hub 設定：' . $e->getMessage());
            }
        }

        // Fallback: 使用舊的 BuyGo 設定（向後相容，但不應該執行到這裡）
        $token_raw = get_option('buygo_line_channel_access_token', '');
        $secret_raw = get_option('buygo_line_channel_secret', '');

        // 嘗試解密敏感資料（如果解密失敗，使用原始值）
        $token = $token_raw;
        if (!empty($token_raw) && EncryptionService::is_encrypted_field('line_channel_access_token')) {
            $decrypted = EncryptionService::decrypt($token_raw);
            // 如果解密成功且結果不同，使用解密後的值
            if ($decrypted !== $token_raw && !empty($decrypted)) {
                $token = $decrypted;
            }
        }

        $secret = $secret_raw;
        if (!empty($secret_raw) && EncryptionService::is_encrypted_field('line_channel_secret')) {
            $decrypted = EncryptionService::decrypt($secret_raw);
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
            if (EncryptionService::is_encrypted_field('line_channel_access_token') && !empty($token)) {
                $token = EncryptionService::encrypt($token);
            }
            update_option('buygo_line_channel_access_token', $token);
        }

        if (isset($settings['channel_secret'])) {
            $secret = sanitize_text_field($settings['channel_secret']);
            // 加密儲存
            if (EncryptionService::is_encrypted_field('line_channel_secret') && !empty($secret)) {
                $secret = EncryptionService::encrypt($secret);
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
        global $wpdb;

        // 方式 1：從 LINE Hub 表查詢（最優先）
        if (class_exists('LineHub\Services\UserService')) {
            try {
                $binding = \LineHub\Services\UserService::getBinding($user_id);
                if ($binding && !empty($binding->line_uid)) {
                    return $binding->line_uid;
                }
            } catch (\Exception $e) {
                error_log('[BuyGo] 無法從 LINE Hub 查詢 LINE UID：' . $e->getMessage());
            }
        }

        // 方式 2：從 wp_usermeta 查詢
        // line_uid 是舊版使用的 meta key
        // _mygo_line_uid 是舊系統使用的 meta key（向後相容）
        $meta_keys = ['line_uid', '_mygo_line_uid', 'buygo_line_user_id', 'm_line_user_id', 'line_user_id'];

        foreach ($meta_keys as $meta_key) {
            $line_id = get_user_meta($user_id, $meta_key, true);
            if (!empty($line_id)) {
                return $line_id;
            }
        }

        // 方式 3：從 wp_buygo_line_users 表查詢（舊表，向後相容）
        $buygo_line_users_table = $wpdb->prefix . 'buygo_line_users';

        // 檢查資料表是否存在
        $buygo_table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $buygo_line_users_table
        ));

        if ($buygo_table_exists) {
            // 檢查欄位是否存在，避免正式站表結構不一致時產生 DB 錯誤
            $columns = $wpdb->get_col("DESCRIBE {$buygo_line_users_table}", 0);
            if (in_array('identifier', $columns) && in_array('user_id', $columns)) {
                $line_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT identifier FROM {$buygo_line_users_table} WHERE user_id = %d AND type = 'line' LIMIT 1",
                    $user_id
                ));

                if (!empty($line_id)) {
                    return $line_id;
                }
            }
        }

        // 方式 4：從 wp_social_users 表查詢（備用）
        // 某些社交登入外掛（如 Super Socializer）會將 UID 儲存在此表
        // 注意：此表的 User ID 欄位名稱是 ID（大寫），不是 user_id
        $social_users_table = $wpdb->prefix . 'social_users';

        // 檢查資料表是否存在
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $social_users_table
        ));

        if ($table_exists) {
            $columns = $wpdb->get_col("DESCRIBE {$social_users_table}", 0);
            if (in_array('identifier', $columns)) {
                $id_col = in_array('ID', $columns) ? 'ID' : 'user_id';
                $line_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT identifier FROM {$social_users_table} WHERE {$id_col} = %d AND type = 'line' LIMIT 1",
                    $user_id
                ));

                if (!empty($line_id)) {
                    return $line_id;
                }
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
            // 檢查 LineHub MessagingService 是否可用
            if (!class_exists('\\LineHub\\Messaging\\MessagingService')) {
                return [
                    'success' => false,
                    'message' => '請先啟用 LINE Hub 外掛'
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
                // 透過 LineHub UserService 取得 user_id，再用 MessagingService 發送
                $wp_user_id = null;
                if (class_exists('\\LineHub\\Services\\UserService')) {
                    $wp_user_id = \LineHub\Services\UserService::getUserByLineUid($line_uid);
                }
                if (!$wp_user_id) {
                    $wp_user_id = $user_id; // fallback to the user_id we already have
                }
                $messaging = new \LineHub\Messaging\MessagingService();
                $messaging->pushText($wp_user_id, $message);

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
}
