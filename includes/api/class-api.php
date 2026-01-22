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
    }
    
    /**
     * 統一權限檢查方法（所有 BuyGo 使用者）
     *
     * 允許以下角色存取 BuyGo+1 功能：
     * - WordPress 管理員
     * - BuyGo 管理員
     * - 小幫手
     *
     * @return bool
     */
    public static function check_permission(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        return current_user_can('manage_options')      // WordPress 管理員
            || current_user_can('buygo_admin')         // BuyGo 管理員
            || current_user_can('buygo_helper');       // 小幫手
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
}
