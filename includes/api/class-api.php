<?php
namespace BuyGoPlus\Api;

if (!defined('ABSPATH')) {
    exit;
}

class API {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes() {
        // 載入所有 API 控制器
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-products-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-orders-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-shipments-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-customers-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-global-search-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-dashboard-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-child-orders-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-data-management-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-feature-management-api.php';

        // 註冊商品 API
        $products_api = new Products_API();
        $products_api->register_routes();

        // 註冊訂單 API
        $orders_api = new Orders_API();
        $orders_api->register_routes();

        // 註冊出貨單 API
        $shipments_api = new Shipments_API();
        $shipments_api->register_routes();

        // 註冊客戶 API
        $customers_api = new Customers_API();
        $customers_api->register_routes();

        // 註冊全局搜索 API
        $global_search_api = new GlobalSearch_API();
        $global_search_api->register_routes();

        // 註冊 Dashboard API
        $dashboard_api = new Dashboard_API();
        $dashboard_api->register_routes();

        // 註冊子訂單 API（顧客前台用）
        $child_orders_api = new ChildOrders_API();
        $child_orders_api->register_routes();

        // 註冊資料管理 API（僅管理員）
        $data_management_api = new DataManagement_API();
        $data_management_api->register_routes();

        // 註冊功能管理 API（僅管理員）
        $feature_management_api = new FeatureManagement_API();
        $feature_management_api->register_routes();

        // 註冊邀請連結 API
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-invite-api.php';
        $invite_api = new Invite_API();
        $invite_api->register_routes();

        // 註冊預留 API（Pro 功能骨架，全回傳 501）
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-reserved-api.php';
        $reserved_api = new Reserved_API();
        $reserved_api->register_routes();
    }
    
    /**
     * 統一權限檢查方法（所有 BuyGo 使用者）
     *
     * 允許以下角色存取 BuyGo+1 功能：
     * - WordPress 管理員
     * - BuyGo 管理員
     * - 小幫手
     * - 上架幫手
     *
     * @return bool
     */
    public static function check_permission(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        return current_user_can('manage_options')
            || current_user_can('buygo_admin')
            || current_user_can('buygo_helper')
            || current_user_can('buygo_lister');
    }

    /**
     * 權限檢查（僅管理員可新增/刪除小幫手）
     *
     * @return bool
     */
    public static function check_admin_permission(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        return current_user_can('manage_options')      // WordPress 管理員
            || current_user_can('buygo_add_helper');   // 有新增小幫手權限的人
    }

    /**
     * 權限檢查（給 Products_API 使用）
     */
    public static function check_permission_for_api(): bool
    {
        return self::check_permission();
    }

    /**
     * 帶細粒度權限的檢查（小幫手 scope 檢查）
     *
     * 先通過基礎權限檢查，再檢查小幫手是否有特定 scope 權限。
     * 賣家和 WP Admin 不受 scope 限制。
     *
     * @param string $scope 權限範圍：products, orders, shipments, customers, settings, listing
     * @return bool
     */
    public static function check_permission_with_scope(string $scope): bool
    {
        if (!self::check_permission()) {
            return false;
        }
        return \BuyGoPlus\Services\SettingsService::helper_can($scope);
    }

    // =========================================================
    // 多租戶所有權驗證 — Ownership Guard Methods
    // =========================================================

    /**
     * 驗證商品所有權
     *
     * 管理員（manage_options 或 buygo_admin）直接放行。
     * 其他使用者檢查商品的 post_author 是否在可存取的 seller_ids 中。
     *
     * @param int $product_id WP post ID（商品）
     * @return true|\WP_Error 有權限回傳 true，否則回傳 WP_Error
     */
    public static function verify_product_ownership(int $product_id)
    {
        // 管理員始終放行
        if (current_user_can('manage_options') || current_user_can('buygo_admin')) {
            return true;
        }

        global $wpdb;

        // 取得商品的 post_author
        $author_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT post_author FROM {$wpdb->posts} WHERE ID = %d",
            $product_id
        ));

        if (!$author_id) {
            return new \WP_Error('product_not_found', '商品不存在', ['status' => 404]);
        }

        $seller_ids = \BuyGoPlus\Services\SettingsService::get_accessible_seller_ids();

        if (in_array($author_id, $seller_ids, true)) {
            return true;
        }

        return new \WP_Error('access_denied', '無存取權限', ['status' => 403]);
    }

    /**
     * 驗證 Variation 所有權
     *
     * 透過 wp_fct_product_variations → parent post 的 post_author 確認歸屬。
     *
     * @param int $variation_id Variation ID（wp_fct_product_variations.id）
     * @return true|\WP_Error
     */
    public static function verify_variation_ownership(int $variation_id)
    {
        // 管理員始終放行
        if (current_user_can('manage_options') || current_user_can('buygo_admin')) {
            return true;
        }

        global $wpdb;

        // 取得 variation 所屬 parent product 的 post_author
        $author_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT p.post_author
             FROM {$wpdb->posts} p
             JOIN {$wpdb->prefix}fct_product_variations v ON p.ID = v.post_id
             WHERE v.id = %d",
            $variation_id
        ));

        if (!$author_id) {
            return new \WP_Error('product_not_found', '商品不存在', ['status' => 404]);
        }

        $seller_ids = \BuyGoPlus\Services\SettingsService::get_accessible_seller_ids();

        if (in_array($author_id, $seller_ids, true)) {
            return true;
        }

        return new \WP_Error('access_denied', '無存取權限', ['status' => 403]);
    }

    /**
     * 驗證訂單所有權
     *
     * 管理員直接放行。其他使用者檢查訂單中是否有商品屬於可存取的 sellers。
     *
     * @param int $order_id FluentCart order ID
     * @return true|\WP_Error
     */
    public static function verify_order_ownership(int $order_id)
    {
        // 管理員始終放行
        if (current_user_can('manage_options') || current_user_can('buygo_admin')) {
            return true;
        }

        $seller_ids = \BuyGoPlus\Services\SettingsService::get_accessible_seller_ids();
        if (empty($seller_ids)) {
            return new \WP_Error('access_denied', '無存取權限', ['status' => 403]);
        }

        global $wpdb;

        $orders_table = $wpdb->prefix . 'fct_orders';
        $items_table  = $wpdb->prefix . 'fct_order_items';
        $posts_table  = $wpdb->posts;

        $seller_ids_str = implode(',', array_map('intval', $seller_ids));

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT o.id)
             FROM {$orders_table} o
             INNER JOIN {$items_table} oi ON o.id = oi.order_id
             INNER JOIN {$posts_table} p ON oi.post_id = p.ID OR oi.post_id = p.post_parent
             WHERE o.id = %d AND p.post_author IN ({$seller_ids_str})",
            $order_id
        ));

        if ($count > 0) {
            return true;
        }

        return new \WP_Error('access_denied', '無存取權限', ['status' => 403]);
    }

    /**
     * 驗證客戶所有權
     *
     * 委派給 CustomerEditService::check_ownership()（已有完整實作）。
     *
     * @param int $customer_id FluentCart customer ID
     * @return true|\WP_Error
     */
    public static function verify_customer_ownership(int $customer_id)
    {
        // 管理員始終放行
        if (current_user_can('manage_options') || current_user_can('buygo_admin')) {
            return true;
        }

        if (\BuyGoPlus\Services\CustomerEditService::check_ownership($customer_id)) {
            return true;
        }

        return new \WP_Error('access_denied', '無存取權限', ['status' => 403]);
    }

    /**
     * 驗證出貨單所有權
     *
     * 管理員直接放行。其他使用者比對出貨單的 seller_id 是否在可存取的 sellers 中。
     *
     * @param int $shipment_id wp_buygo_shipments.id
     * @return true|\WP_Error
     */
    public static function verify_shipment_ownership(int $shipment_id)
    {
        // 管理員始終放行
        if (current_user_can('manage_options') || current_user_can('buygo_admin')) {
            return true;
        }

        global $wpdb;

        $table_shipments = $wpdb->prefix . 'buygo_shipments';

        $seller_id = $wpdb->get_var($wpdb->prepare(
            "SELECT seller_id FROM {$table_shipments} WHERE id = %d",
            $shipment_id
        ));

        if ($seller_id === null) {
            return new \WP_Error('shipment_not_found', '出貨單不存在', ['status' => 404]);
        }

        $seller_ids = \BuyGoPlus\Services\SettingsService::get_accessible_seller_ids();

        if (in_array((int) $seller_id, $seller_ids, true)) {
            return true;
        }

        return new \WP_Error('access_denied', '無存取權限', ['status' => 403]);
    }
}
