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
        // 暫時允許所有已登入使用者訪問
        // TODO: 後續可以加入 BuyGo 特定權限檢查
        return is_user_logged_in() || current_user_can('read');
    }
}
