<?php
namespace BuyGoPlus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin - 外掛主類別
 * 
 * 使用 Singleton 模式確保只有一個實例
 * 負責載入所有依賴和註冊 Hooks
 * 
 * @package BuyGoPlus
 * @since 0.0.1
 */
class Plugin {
    /**
     * 單例實例
     * 
     * @var Plugin|null
     */
    private static $instance = null;
    
    /**
     * 取得單例實例
     * 
     * @return Plugin
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 私有建構函數（Singleton 模式）
     * 
     * 防止外部直接實例化
     */
    private function __construct() {
        // 私有建構函數（Singleton）
    }
    
    /**
     * 初始化外掛
     * 
     * 載入所有依賴並註冊 Hooks
     * 
     * @return void
     */
    public function init() {
        // 初始化外掛
        $this->load_dependencies();
        $this->register_hooks();
    }
    
    /**
     * 載入依賴檔案
     * 
     * 載入順序：
     * 1. Services（服務層）
     * 2. Admin（後台頁面）
     * 3. Database（資料庫）
     * 4. API（REST API）
     * 5. Routes（路由）
     * 
     * @return void
     */
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
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-export-service.php';
        
        // 載入核心服務（遷移自舊外掛，讓新外掛可以獨立執行）
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-line-service.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/core/class-buygo-plus-core.php';
        
        // 載入 Webhook 相關 Services（階段 2：LINE 後端功能）
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-webhook-logger.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-image-uploader.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-product-data-parser.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-fluentcart-service.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-line-webhook-handler.php';

        // 載入診斷工具（WP-CLI 命令）
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/diagnostics/class-diagnostics-command.php';
        }

        // 載入 Admin
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/class-debug-page.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
        
        // 載入 Database
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-database.php';
        
        // 載入 API
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-debug-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-settings-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-keywords-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-line-webhook-api.php';
        
        // 載入其他類別
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-routes.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-short-link-routes.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-fluentcart-product-page.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-api.php';

        // 載入 FluentCommunity 整合
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-fluent-community.php';
    }
    
    /**
     * 註冊 WordPress Hooks
     * 
     * 初始化順序：
     * 1. 角色權限（init_roles）
     * 2. 後台頁面（DebugPage, SettingsPage）
     * 3. 路由（Routes）
     * 4. REST API（API, Debug_API, Settings_API, Keywords_API）
     * 
     * @return void
     */
    private function register_hooks() {
        // 初始化角色權限
        \BuyGoPlus\Services\SettingsService::init_roles();
        
        // 初始化 Admin Pages
        // 將 DebugPage 實例儲存為全域變數，供 SettingsPage 使用
        // 注意：DebugPage 必須在 SettingsPage 之前初始化
        global $buygo_plus_one_debug_page;
        $buygo_plus_one_debug_page = new \BuyGoPlus\Admin\DebugPage();
        new \BuyGoPlus\Admin\SettingsPage();
        
        // 初始化 Routes
        new Routes();
        
        // 初始化短連結路由
        ShortLinkRoutes::instance();
        
        // 初始化 FluentCart 產品頁面自訂
        FluentCartProductPage::instance();
        
        // 初始化 API
        new \BuyGoPlus\Api\API();
        new \BuyGoPlus\Api\Debug_API();
        new \BuyGoPlus\Api\Settings_API();
        new \BuyGoPlus\Api\Keywords_API();
        
        // 初始化 Webhook API
        $webhook_api = new \BuyGoPlus\Api\Line_Webhook_API();
        add_action('rest_api_init', array($webhook_api, 'register_routes'));

        // 初始化 FluentCommunity 整合（若 FluentCommunity 已安裝）
        if (class_exists('FluentCommunity\\App\\App')) {
            new FluentCommunity();
        }
    }
}
