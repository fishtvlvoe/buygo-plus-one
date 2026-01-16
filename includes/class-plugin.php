<?php
namespace BuyGoPlus;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // 私有建構函數（Singleton）
    }
    
    public function init() {
        // 初始化外掛
        $this->load_dependencies();
        $this->register_hooks();
    }
    
    private function load_dependencies() {
        // 載入 Services
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-debug-service.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-product-service.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-order-service.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-shipment-service.php';
        
        // 載入其他類別
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-routes.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-api.php';
    }
    
    private function register_hooks() {
        // 初始化 Routes
        new Routes();
        
        // 初始化 API
        new \BuyGoPlus\Api\API();
    }
}
