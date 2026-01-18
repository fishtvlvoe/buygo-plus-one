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
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-allocation-service.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-shipping-status-service.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-settings-service.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-notification-templates.php';
        
        // 載入 Admin
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/class-debug-page.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
        
        // 載入 Database
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-database.php';
        
        // 載入 API
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-debug-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-settings-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-keywords-api.php';
        
        // 載入其他類別
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-routes.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-api.php';
    }
    
    private function register_hooks() {
        // 初始化角色權限
        \BuyGoPlus\Services\SettingsService::init_roles();
        
        // 初始化 Admin Pages
        new \BuyGoPlus\Admin\DebugPage();
        new \BuyGoPlus\Admin\SettingsPage();
        
        // 初始化 Routes
        new Routes();
        
        // 初始化 API
        new \BuyGoPlus\Api\API();
        new \BuyGoPlus\Api\Debug_API();
        new \BuyGoPlus\Api\Settings_API();
        new \BuyGoPlus\Api\Keywords_API();
    }
}
