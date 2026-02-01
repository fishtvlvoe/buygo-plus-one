<?php
/**
 * Identity Service
 *
 * 身份識別服務 - 整合 LINE UID 和 WordPress 用戶身份判斷
 *
 * @package BuyGoPlus
 * @since 1.2.0
 */

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class IdentityService
 *
 * 提供統一的介面判斷用戶角色：
 * - seller: 賣家（buygo_admin 角色）
 * - helper: 小幫手（在 wp_buygo_helpers 表中）
 * - buyer: 買家（有 LINE 綁定但非賣家/小幫手）
 * - unbound: 未綁定用戶
 */
class IdentityService
{
    /**
     * 角色常數
     */
    const ROLE_SELLER = 'seller';
    const ROLE_HELPER = 'helper';
    const ROLE_BUYER = 'buyer';
    const ROLE_UNBOUND = 'unbound';

    /**
     * Debug Service 實例
     *
     * @var DebugService|null
     */
    private static $debug_service = null;

    /**
     * 初始化 Debug Service
     */
    private static function init_debug_service(): void
    {
        if (self::$debug_service === null) {
            self::$debug_service = DebugService::get_instance();
        }
    }

    /**
     * 根據 LINE UID 取得用戶身份
     *
     * @param string $line_uid LINE User ID
     * @return array 身份資訊
     */
    public static function getIdentityByLineUid(string $line_uid): array
    {
        self::init_debug_service();

        // 預設返回未綁定
        $identity = [
            'user_id' => null,
            'line_uid' => $line_uid,
            'role' => self::ROLE_UNBOUND,
            'is_bound' => false,
            'seller_id' => null,
        ];

        if (empty($line_uid)) {
            return $identity;
        }

        // 查詢 WordPress User ID
        $user_id = self::getUserIdByLineUid($line_uid);

        if (!$user_id) {
            self::$debug_service->log('IdentityService', '未找到綁定用戶', [
                'line_uid' => $line_uid,
            ]);
            return $identity;
        }

        // 找到用戶，繼續判斷角色
        return self::getIdentityByUserId($user_id, $line_uid);
    }

    /**
     * 根據 WordPress User ID 取得用戶身份
     *
     * @param int $user_id WordPress User ID
     * @param string|null $line_uid LINE UID（可選，如果已知可傳入避免重複查詢）
     * @return array 身份資訊
     */
    public static function getIdentityByUserId(int $user_id, ?string $line_uid = null): array
    {
        self::init_debug_service();

        // 查詢 LINE UID（如果未提供）
        if ($line_uid === null) {
            $line_uid = self::getLineUid($user_id);
        }

        $is_bound = !empty($line_uid);

        // 預設身份
        $identity = [
            'user_id' => $user_id,
            'line_uid' => $line_uid,
            'role' => $is_bound ? self::ROLE_BUYER : self::ROLE_UNBOUND,
            'is_bound' => $is_bound,
            'seller_id' => null,
        ];

        // 檢查是否為賣家
        if (self::isSeller($user_id)) {
            $identity['role'] = self::ROLE_SELLER;
            $identity['seller_id'] = $user_id;

            self::$debug_service->log('IdentityService', '識別為賣家', [
                'user_id' => $user_id,
                'is_bound' => $is_bound,
            ]);

            return $identity;
        }

        // 檢查是否為小幫手
        $helper_info = self::getHelperInfo($user_id);
        if ($helper_info) {
            $identity['role'] = self::ROLE_HELPER;
            $identity['seller_id'] = $helper_info['seller_id'];

            self::$debug_service->log('IdentityService', '識別為小幫手', [
                'user_id' => $user_id,
                'seller_id' => $helper_info['seller_id'],
                'is_bound' => $is_bound,
            ]);

            return $identity;
        }

        // 非賣家也非小幫手
        self::$debug_service->log('IdentityService', '識別為買家或未綁定', [
            'user_id' => $user_id,
            'role' => $identity['role'],
            'is_bound' => $is_bound,
        ]);

        return $identity;
    }

    /**
     * 判斷用戶是否為賣家
     *
     * @param int $user_id WordPress User ID
     * @return bool
     */
    public static function isSeller(int $user_id): bool
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // 檢查是否有 buygo_admin 角色或 seller_type meta
        if (in_array('buygo_admin', (array) $user->roles)) {
            return true;
        }

        // 檢查 seller_type meta（測試賣家或正式賣家）
        $seller_type = get_user_meta($user_id, 'buygo_seller_type', true);
        if (in_array($seller_type, ['test', 'official'])) {
            return true;
        }

        return false;
    }

    /**
     * 判斷用戶是否為小幫手
     *
     * @param int $user_id WordPress User ID
     * @return bool
     */
    public static function isHelper(int $user_id): bool
    {
        return self::getHelperInfo($user_id) !== null;
    }

    /**
     * 取得小幫手資訊
     *
     * @param int $user_id WordPress User ID
     * @return array|null 小幫手資訊或 null
     */
    public static function getHelperInfo(int $user_id): ?array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'buygo_helpers';

        // 檢查資料表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return null;
        }

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d LIMIT 1",
            $user_id
        ), ARRAY_A);

        return $result ?: null;
    }

    /**
     * 判斷用戶是否有 LINE 綁定
     *
     * @param int $user_id WordPress User ID
     * @return bool
     */
    public static function hasLineBinding(int $user_id): bool
    {
        return !empty(self::getLineUid($user_id));
    }

    /**
     * 取得用戶的 LINE UID
     *
     * 查詢順序：
     * 1. buygo-line-notify 的 LineUserService（如果可用）
     * 2. wp_buygo_line_bindings 資料表
     * 3. wp_usermeta（_mygo_line_uid）
     *
     * @param int $user_id WordPress User ID
     * @return string|null LINE UID 或 null
     */
    public static function getLineUid(int $user_id): ?string
    {
        // 嘗試使用 buygo-line-notify 的服務
        if (class_exists('\\BuygoLineNotify\\Services\\LineUserService')) {
            $line_uid = \BuygoLineNotify\Services\LineUserService::getLineUidByUserId($user_id);
            if ($line_uid) {
                return $line_uid;
            }
        }

        // 查詢本地資料表
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_bindings';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            $line_uid = $wpdb->get_var($wpdb->prepare(
                "SELECT line_uid FROM {$table_name} WHERE user_id = %d AND status = 'completed' ORDER BY id DESC LIMIT 1",
                $user_id
            ));

            if ($line_uid) {
                return $line_uid;
            }
        }

        // 查詢 usermeta（向後相容）
        $line_uid = get_user_meta($user_id, '_mygo_line_uid', true);
        if ($line_uid) {
            return $line_uid;
        }

        return null;
    }

    /**
     * 根據 LINE UID 取得 WordPress User ID
     *
     * @param string $line_uid LINE User ID
     * @return int|null WordPress User ID 或 null
     */
    public static function getUserIdByLineUid(string $line_uid): ?int
    {
        // 嘗試使用 buygo-line-notify 的服務
        if (class_exists('\\BuygoLineNotify\\Services\\LineUserService')) {
            $user_id = \BuygoLineNotify\Services\LineUserService::getUserByLineUid($line_uid);
            if ($user_id) {
                return $user_id;
            }
        }

        // 使用本地 LineService
        $line_service = new LineService();
        $user = $line_service->get_user_by_line_uid($line_uid);

        return $user ? $user->ID : null;
    }

    /**
     * 判斷用戶是否可以與 bot 互動（賣家或小幫手）
     *
     * @param int $user_id WordPress User ID
     * @return bool
     */
    public static function canInteractWithBot(int $user_id): bool
    {
        return self::isSeller($user_id) || self::isHelper($user_id);
    }

    /**
     * 根據 LINE UID 判斷是否可以與 bot 互動
     *
     * @param string $line_uid LINE User ID
     * @return bool
     */
    public static function canInteractWithBotByLineUid(string $line_uid): bool
    {
        $identity = self::getIdentityByLineUid($line_uid);

        return in_array($identity['role'], [self::ROLE_SELLER, self::ROLE_HELPER]);
    }
}
