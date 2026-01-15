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
        
        // 註冊商品 API
        $products_api = new Products_API();
        $products_api->register_routes();
    }
    
    /**
     * 統一權限檢查方法
     * 
     * @return bool
     */
    public static function check_permission() {
        // 檢查使用者是否登入
        if (!is_user_logged_in()) {
            return false;
        }
        
        // 檢查使用者權限（可以擴展為檢查 BuyGo 特定權限）
        return current_user_can('read');
    }
}
