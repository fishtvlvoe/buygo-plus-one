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
     * 資料庫版本號
     *
     * @var string
     */
    const DB_VERSION = '1.2.0';

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
     * 使用 WordPress Plugin Boilerplate 結構
     *
     * 載入順序：
     * 1. 核心類別（Loader, Admin, Public）
     * 2. Services（使用 glob 批次載入）
     * 3. API（使用 glob 批次載入）
     * 4. Admin Pages
     * 5. Database
     * 6. Routes
     * 7. 其他整合
     *
     * @return void
     */
    private function load_dependencies() {
        // 載入核心類別（WordPress Plugin Boilerplate）
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-loader.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'admin/class-admin.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'public/class-public.php';

        // 使用 glob 批次載入 Services（15 個服務）
        foreach (glob(BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-*.php') as $service) {
            require_once $service;
        }

        // 載入核心服務
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/core/class-buygo-plus-core.php';

        // 載入診斷工具（WP-CLI 命令）
        if (defined('WP_CLI') && WP_CLI) {
            require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/diagnostics/class-diagnostics-command.php';
        }

        // 載入 Admin Pages
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/class-debug-page.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/class-settings-page.php';

        // 載入 Database
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-database.php';

        // 使用 glob 批次載入 API（5 個 API）
        foreach (glob(BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-*.php') as $api) {
            require_once $api;
        }

        // 載入 Routes
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-routes.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-short-link-routes.php';

        // 載入 FluentCart/FluentCommunity 整合
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-fluentcart-product-page.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-fluent-community.php';
    }
    
    /**
     * 註冊 WordPress Hooks
     *
     * 初始化順序：
     * 1. 角色權限（init_roles）
     * 2. 後台頁面（SettingsPage）
     * 3. 路由（Routes）
     * 4. REST API（API, Debug_API, Settings_API, Keywords_API）
     *
     * @return void
     */
    private function register_hooks() {
        // 檢查並建立缺失的資料表（用於升級安裝）
        $this->maybe_upgrade_database();

        // 初始化角色權限
        \BuyGoPlus\Services\SettingsService::init_roles();

        // 初始化 Admin Pages
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

        // 註冊 WordPress Cron 處理 LINE webhook 事件（用於非 FastCGI 環境）
        add_action('buygo_process_line_webhook', function($events) {
            $webhook_handler = new \BuyGoPlus\Services\LineWebhookHandler();
            $webhook_handler->process_events($events, false);
        });

        // 初始化 FluentCommunity 整合（若 FluentCommunity 已安裝）
        if (class_exists('FluentCommunity\\App\\App')) {
            new FluentCommunity();
        }

        // 阻擋 Cloudflare Beacon 以修復效能問題
        add_action('wp_footer', function() {
            ?>
            <script>
            // 阻擋 Cloudflare Web Analytics Beacon
            (function() {
                if (typeof window !== 'undefined') {
                    window.__cfBeacon = null;
                    window.cfjsloader = null;
                }
            })();
            </script>
            <?php
        }, 1);
    }

    /**
     * 檢查並升級資料庫
     *
     * 用於處理外掛升級時新增的資料表
     * 檢查目前版本，若有更新則執行資料表建立
     */
    private function maybe_upgrade_database(): void
    {
        $current_db_version = get_option('buygo_plus_one_db_version', '0');
        $required_db_version = self::DB_VERSION; // 使用類別常數

        // 載入必要的類別
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-database.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-database-checker.php';

        // 版本升級邏輯
        if (version_compare($current_db_version, $required_db_version, '<')) {
            // 重新執行資料表建立（會自動跳過已存在的資料表）
            Database::create_tables();

            // 執行資料表結構升級（修復缺失的欄位）
            Database::upgrade_tables();

            // 更新資料庫版本
            update_option('buygo_plus_one_db_version', $required_db_version);

            // 記錄升級
            $log_file = WP_CONTENT_DIR . '/buygo-plus-one.log';
            file_put_contents($log_file, sprintf(
                "[%s] [UPGRADE] Database upgraded from %s to %s (fixed shipment table structure)\n",
                date('Y-m-d H:i:s'),
                $current_db_version,
                $required_db_version
            ), FILE_APPEND);
        }

        // 每次啟動時檢查資料表完整性（防止資料表被意外刪除或損壞）
        $this->ensure_database_integrity();
    }

    /**
     * 確保資料庫完整性
     *
     * 每次外掛啟動時執行，自動修復缺失的資料表或欄位
     * 這可以防止新舊外掛切換時資料表不完整的問題
     */
    private function ensure_database_integrity(): void
    {
        // 使用 transient 避免每次請求都執行（每小時檢查一次）
        $last_check = get_transient('buygo_db_integrity_check');
        if ($last_check) {
            return;
        }

        // 執行檢查
        $check_result = DatabaseChecker::check();

        // 如果有問題，自動修復
        if ($check_result['status'] !== 'ok') {
            $repair_result = DatabaseChecker::check_and_repair();

            // 記錄修復動作
            $log_file = WP_CONTENT_DIR . '/buygo-plus-one.log';
            file_put_contents($log_file, sprintf(
                "[%s] [DB_INTEGRITY] Auto-repair executed: %s\n",
                date('Y-m-d H:i:s'),
                json_encode($repair_result, JSON_UNESCAPED_UNICODE)
            ), FILE_APPEND);
        }

        // 設定 transient（1 小時後過期）
        set_transient('buygo_db_integrity_check', time(), HOUR_IN_SECONDS);
    }
}
