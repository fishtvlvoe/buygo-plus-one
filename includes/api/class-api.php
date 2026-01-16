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
        
        // 註冊商品 API
        $products_api = new Products_API();
        $products_api->register_routes();
        
        // 註冊訂單 API
        $orders_api = new Orders_API();
        $orders_api->register_routes();
    }
    
    /**
     * 統一權限檢查方法
     * 
     * @return bool
     */
    public static function check_permission() {
        // 開發階段暫時開放，正式環境需要加入權限檢查
        // TODO: 正式環境改為檢查 WordPress Nonce 或 Application Password
        return true;
    }
    
    /**
     * 權限檢查（給 Products_API 使用）
     */
    public static function check_permission_for_api() {
        return self::check_permission();
    }
}
