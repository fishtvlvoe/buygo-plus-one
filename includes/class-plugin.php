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
    const DB_VERSION = '1.3.1';

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

        // 載入監控工具
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/monitoring/class-slow-query-monitor.php';

        // 載入診斷工具（WP-CLI 命令）
        if (defined('WP_CLI') && WP_CLI) {
            require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/diagnostics/class-diagnostics-command.php';
        }

        // 載入 Admin Pages
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/class-debug-page.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/class-seller-management-page.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/class-seller-type-field.php';

        // 載入 Database
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-database.php';

        // 使用 glob 批次載入 API（5 個 API）
        foreach (glob(BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-*.php') as $api) {
            require_once $api;
        }

        // 載入 Routes
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-routes.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-short-link-routes.php';

        // 載入 Shortcodes
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-seller-application-shortcode.php';

        // 載入 FluentCart/FluentCommunity 整合
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-fluentcart-product-page.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-fluent-community.php';

        // FluentCart 整合
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/integrations/class-fluentcart-child-orders-integration.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/integrations/class-fluentcart-offline-payment-user.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/integrations/class-fluentcart-seller-grant.php';
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

        // 初始化訂單同步服務（父子訂單狀態同步）
        $orderSyncService = new \BuyGoPlus\Services\OrderSyncService();
        $orderSyncService->register_hooks();

        // 初始化 Admin Pages
        new \BuyGoPlus\Admin\SettingsPage();
        // 2026-02-04: 賣家管理頁面已移除，功能統一到「角色權限設定」頁面
        // new \BuyGoPlus\Admin\SellerManagementPage();
        
        // 初始化 Routes
        new Routes();
        
        // 初始化短連結路由
        ShortLinkRoutes::instance();
        
        // 初始化 FluentCart 產品頁面自訂
        FluentCartProductPage::instance();
        
        // 初始化 API
        // API 類別會自動載入並註冊所有 REST API 端點
        // (Products, Orders, Shipments, Customers, GlobalSearch, Dashboard)
        new \BuyGoPlus\Api\API();
        new \BuyGoPlus\Api\Debug_API();
        new \BuyGoPlus\Api\Settings_API();
        new \BuyGoPlus\Api\Keywords_API();
        new \BuyGoPlus\Api\Seller_Application_API();

        // LINE Webhook API 已移除
        // 根據架構設計，LINE webhook 由 buygo-line-notify 接收
        // BuyGo Plus One 透過 hook 機制接收事件，不直接處理 webhook
        // 參考：.planning/debug/line-webhook-integration-failure.md 外掛架構定位

        // 初始化 FluentCommunity 整合（若 FluentCommunity 已安裝）
        if (class_exists('FluentCommunity\\App\\App')) {
            new FluentCommunity();
        }

        // 初始化 FluentCart 整合（只在 FluentCart 啟用時）
        if (class_exists('FluentCart\\App\\App')) {
            \BuygoPlus\Integrations\FluentCartChildOrdersIntegration::register_hooks();
            \BuygoPlus\Integrations\FluentCartOfflinePaymentUser::register_hooks();
            \BuygoPlus\Integrations\FluentCartSellerGrantIntegration::register_hooks();
        }

        // 初始化結帳頁面自訂服務（身分證字號等）
        \BuyGoPlus\Services\CheckoutCustomizationService::init();

        // 初始化賣家申請 Shortcode
        SellerApplicationShortcode::instance();

        // 初始化訂單項目標題修復服務
        \BuyGoPlus\Services\OrderItemTitleFixer::instance();

        // 初始化 LINE Response Provider
        // 透過 filter hook 向 buygo-line-notify 提供回覆模板內容
        // 參考架構：buygo-line-notify 發送訊息，buygo-plus-one 提供模板
        \BuyGoPlus\Services\LineResponseProvider::init();

        // 初始化 LINE Webhook Handler
        // 處理 buygo-line-notify 發出的 webhook hooks：
        // - webhook_message_image: 圖片上傳 → 商品類型選單
        // - webhook_message_text: 文字訊息 → 關鍵字回應、命令處理、商品資訊
        // - webhook_postback: 按鈕點擊 → 商品類型選擇後發送格式說明
        new \BuyGoPlus\Services\LineWebhookHandler();

        // 初始化商品上架通知（Phase 30）
        // 當賣家透過 LINE 上架商品時，通知賣家和小幫手
        new \BuyGoPlus\Services\ProductNotificationHandler();

        // 初始化訂單通知（Phase 31）
        // 新訂單：通知賣家 + 小幫手 + 買家
        // 訂單狀態變更：僅通知買家
        new \BuyGoPlus\Services\LineOrderNotifier();

        // 初始化出貨通知處理器（Phase 33）
        // 監聽 ShipmentService 出貨事件，觸發出貨通知
        $notification_handler = \BuyGoPlus\Services\NotificationHandler::get_instance();
        $notification_handler->register_hooks();

        // 初始化 LINE 關鍵字回覆功能
        // 用戶可在 LINE 中輸入 /ID、/綁定、/help 等指令查詢狀態
        \BuyGoPlus\Services\LineKeywordResponder::instance()->init();

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
