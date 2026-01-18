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
     * 統一權限檢查方法
     * 
     * @return bool
     */
    public static function check_permission() {
        // TODO: 測試完成後，統一設定權限檢查
        // 目前暫時移除權限檢查，方便測試
        
        // #region agent log
        error_log('DEBUG: check_permission() - is_user_logged_in: ' . (is_user_logged_in() ? 'true' : 'false'));
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            error_log('DEBUG: check_permission() - user ID: ' . $current_user->ID . ', login: ' . $current_user->user_login);
        }
        // #endregion
        
        // 暫時允許所有請求（測試階段）
        // 測試完成後，改為：
        // if (!is_user_logged_in()) {
        //     return false;
        // }
        // return current_user_can('manage_options') || current_user_can('buygo_admin');
        
        return true;
    }
    
    /**
     * 權限檢查（給 Products_API 使用）
     */
    public static function check_permission_for_api() {
        return self::check_permission();
    }
}
