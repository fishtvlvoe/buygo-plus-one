<?php
namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Role Permission Service - 角色權限管理服務
 *
 * 負責 BuyGo 角色（buygo_admin、buygo_helper）的建立、指派、移除，
 * 以及多賣家隔離的存取權限判斷。
 *
 * @package BuyGoPlus\Services
 * @since 2.1.0
 */
class RolePermissionService
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

        // 檢查當前用戶角色，判斷查詢方式
        $current_user = wp_get_current_user();
        $is_helper = in_array('buygo_helper', $current_user->roles);

        // 從資料表查詢
        if ($is_helper) {
            // Helper 身份：查詢自己被哪個 seller 綁定
            $helper_records = $wpdb->get_results($wpdb->prepare(
                "SELECT helper_id, seller_id, created_at FROM {$table_name} WHERE helper_id = %d ORDER BY created_at DESC",
                $seller_id
            ));
        } else {
            // Seller 身份：查詢自己綁定了哪些 helper
            $helper_records = $wpdb->get_results($wpdb->prepare(
                "SELECT helper_id, seller_id, created_at FROM {$table_name} WHERE seller_id = %d ORDER BY created_at DESC",
                $seller_id
            ));
        }

        $helpers = [];
        foreach ($helper_records as $record) {
            $user = get_userdata($record->helper_id);
            if ($user) {
                // 取得頭像（優先使用 FluentCommunity 頭像，否則使用 Gravatar）
                $avatar_url = get_user_meta($user->ID, 'fc_customer_photo_url', true);
                if (empty($avatar_url)) {
                    $avatar_url = get_avatar_url($user->user_email, ['size' => 100]);
                }

                // 取得賣家資訊
                $seller = get_userdata($record->seller_id);
                $seller_name = $seller ? $seller->display_name : '未知';

                $helpers[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                    'avatar' => $avatar_url,
                    'created_at' => $record->created_at,
                    'seller_id' => $record->seller_id,
                    'seller_name' => $seller_name,
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
        self::get_debug_service()->log('RolePermissionService', '開始新增小幫手/管理員', array(
            'helper_id' => $user_id,
            'role' => $role,
            'seller_id' => $seller_id,
        ));

        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'buygo_helpers';

            $user = get_userdata($user_id);
            if (!$user) {
                self::get_debug_service()->log('RolePermissionService', '使用者不存在', array(
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

            self::get_debug_service()->log('RolePermissionService', '新增小幫手/管理員成功', array(
                'helper_id' => $user_id,
                'role' => $role,
            ));

            return true;

        } catch (\Exception $e) {
            self::get_debug_service()->log('RolePermissionService', '新增小幫手/管理員失敗', array(
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
}
