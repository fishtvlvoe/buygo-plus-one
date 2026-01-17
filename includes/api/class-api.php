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
    }
    
    /**
     * 統一權限檢查方法
     * 
     * @return bool
     */
    public static function check_permission() {
        // 檢查是否登入
        if (!is_user_logged_in()) {
            return false;
        }
        
        // 檢查是否有管理權限（可依需求調整）
        // 如果只要求登入即可，可註解以下這行
        // return current_user_can('manage_options');
        
        return true;
    }
    
    /**
     * 權限檢查（給 Products_API 使用）
     */
    public static function check_permission_for_api() {
        return self::check_permission();
    }
}
