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
}
