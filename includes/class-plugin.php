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
    const DB_VERSION = '1.5.0';

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
        // 檢查版本更新並自動 flush rewrite rules
        $this->check_version_update();

        // 初始化外掛
        $this->load_dependencies();
        $this->register_hooks();
    }

    /**
     * 檢查版本更新
     *
     * 當檢測到版本號變更時，自動執行必要的更新操作：
     * - Flush rewrite rules（修復路由問題）
     * - 清除快取
     *
     * @return void
     */
    private function check_version_update() {
        $stored_version = get_option('buygo_plus_one_version');
        $current_version = BUYGO_PLUS_ONE_VERSION;

        // 如果版本號不同，表示外掛已更新
        if ($stored_version !== $current_version) {
            // 1. 標記需要 flush rewrite rules
            if (class_exists('\BuyGoPlus\Routes')) {
                \BuyGoPlus\Routes::schedule_flush();
            }

            // 2. 更新儲存的版本號
            update_option('buygo_plus_one_version', $current_version);

            // 3. 記錄更新事件（供除錯用）
            error_log(sprintf(
                '[BuyGo+1] Version updated from %s to %s, rewrite rules scheduled for flush',
                $stored_version ?: 'none',
                $current_version
            ));
        }
    }
    
    /**
     * 載入依賴檔案
     *
     * 使用 PSR-4 Autoloader 按需載入 includes/ 下的所有類別。
     * 只保留 autoloader 管不到的檔案（不在 includes/ 目錄下的）。
     *
     * @return void
     */
    private function load_dependencies() {
        // PSR-4 Autoloader — includes/ 下的類別用到才載入
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/autoload.php';

        // 以下檔案不在 includes/ 目錄下，autoloader 無法處理
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'admin/class-admin.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'public/class-public.php';
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

        // =====================================================================
        // 永遠需要：每次 WordPress 頁面載入都執行
        // =====================================================================

        // 初始化角色權限
        \BuyGoPlus\Services\SettingsService::init_roles();

        // 初始化儀表板快取管理（訂單事件時自動清除快取）
        \BuyGoPlus\Services\DashboardCacheManager::init();

        // 初始化訂單同步服務（父子訂單狀態同步）
        $orderSyncService = new \BuyGoPlus\Services\OrderSyncService();
        $orderSyncService->register_hooks();

        // 初始化 Routes
        new Routes();

        // 初始化短連結路由
        ShortLinkRoutes::instance();

        // 初始化 FluentCart 產品頁面自訂
        FluentCartProductPage::instance();

        // 暫存圖片清理 cron（Phase 60）
        \BuyGoPlus\Api\Reserved_API::schedule_cleanup();

        // 初始化 FluentCommunity 整合（若 FluentCommunity 已安裝）
        if (class_exists('FluentCommunity\\App\\App')) {
            new FluentCommunity();
        }

        // 初始化 FluentCart 整合（只在 FluentCart 啟用時）
        if (class_exists('FluentCart\\App\\App')) {
            \BuygoPlus\Integrations\FluentCartChildOrdersIntegration::register_hooks();
            \BuygoPlus\Integrations\FluentCartOfflinePaymentUser::register_hooks();
            \BuygoPlus\Integrations\FluentCartSellerGrantIntegration::register_hooks();
            \BuygoPlus\Integrations\FluentCartHideChildOrders::register_hooks();

            // 會員中心自訂分頁（訂單進度、LINE 綁定）
            \BuyGoPlus\Integrations\FluentCartCustomerPortal::init();
        }

        // 初始化結帳頁面自訂服務（身分證字號等）
        \BuyGoPlus\Services\CheckoutCustomizationService::init();

        // 初始化訂單項目標題修復服務
        \BuyGoPlus\Services\OrderItemTitleFixer::instance();

        // 非管理員隱藏 WordPress 管理工具列（前台上方黑條）
        add_filter('show_admin_bar', function ($show) {
            if (is_user_logged_in() && !current_user_can('manage_options')) {
                return false;
            }
            return $show;
        });

        // 登出後回到原頁面（不跳到 wp-login.php）
        add_action('wp_logout', function () {
            $referer = wp_get_referer();
            $redirect = $referer ? $referer : home_url('/');
            wp_safe_redirect($redirect);
            exit;
        });

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

        // =====================================================================
        // 後台限定：只在 WordPress 後台（is_admin()）才起
        // =====================================================================

        // 初始化 Admin Pages
        if (is_admin()) {
            new \BuyGoPlus\Admin\SettingsPage();
            // 2026-02-04: 賣家管理頁面已移除，功能統一到「角色權限設定」頁面
            // new \BuyGoPlus\Admin\SellerManagementPage();
        }

        // 初始化自動更新檢測（僅在後台啟用）
        if (is_admin()) {
            $api_url = defined('BUYGO_UPDATE_API_URL')
                ? BUYGO_UPDATE_API_URL
                : 'https://buygo-plugin-updater.your-subdomain.workers.dev';

            new Auto_Updater(BUYGO_PLUS_ONE_VERSION, $api_url);
        }

        // =====================================================================
        // REST API 限定：只在 REST 請求時才起
        // =====================================================================

        // API 類別會自動載入並註冊所有 REST API 端點
        // (Products, Orders, Shipments, Customers, GlobalSearch, Dashboard)
        if (defined('REST_REQUEST') && REST_REQUEST) {
            new \BuyGoPlus\Api\API();
            new \BuyGoPlus\Api\Debug_API();
            new \BuyGoPlus\Api\Settings_API();
            new \BuyGoPlus\Api\Keywords_API();
        }

        // =====================================================================
        // LINE 相關：延遲到 rest_api_init 才起（LINE webhook 走 REST 端點進來）
        // =====================================================================

        add_action('rest_api_init', function () {
            // 初始化 LINE Webhook Handler
            // 監聽 LineHub 發出的 webhook action hooks：
            // - line_hub/webhook/message/image: 圖片上傳 → 商品類型選單
            // - line_hub/webhook/message/text: 文字訊息 → 關鍵字回應、命令處理、商品資訊
            // - line_hub/webhook/postback: 按鈕點擊 → 商品類型選擇後發送格式說明
            $webhook_handler = new \BuyGoPlus\Services\LineWebhookHandler();

            // 註冊 BuyGo 自建 Webhook 端點的 Cron hook
            add_action('buygo_process_line_webhook', array($webhook_handler, 'process_events'));

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

            // 監聽上架幫手加入事件 → 發 LINE 通知給賣家
            add_action('buygo_lister_joined', function ($data) {
                $log_prefix = '[BuyGo] lister_joined notification';

                if (empty($data['seller_id']) || empty($data['display_name'])) {
                    error_log("$log_prefix: SKIP — missing seller_id or display_name: " . json_encode($data, JSON_UNESCAPED_UNICODE));
                    return;
                }

                error_log("$log_prefix: START — seller_id={$data['seller_id']}, display_name={$data['display_name']}");

                $template_data = \BuyGoPlus\Services\NotificationTemplates::get('lister_joined', [
                    'display_name' => $data['display_name'],
                ]);

                if (!$template_data || empty($template_data['line']['message'])) {
                    error_log("$log_prefix: SKIP — template empty: " . json_encode($template_data, JSON_UNESCAPED_UNICODE));
                    return;
                }

                error_log("$log_prefix: SENDING — message=" . mb_substr($template_data['line']['message'], 0, 50));

                do_action('line_hub/send/text', [
                    'user_id' => $data['seller_id'],
                    'message' => $template_data['line']['message'],
                ]);

                error_log("$log_prefix: DONE — line_hub/send/text action fired for seller");

                // 通知上架幫手本人（歡迎訊息）
                $seller_user = get_userdata($data['seller_id']);
                $seller_name = $seller_user ? $seller_user->display_name : '賣家';

                $welcome_template = \BuyGoPlus\Services\NotificationTemplates::get('lister_joined_welcome', [
                    'seller_name' => $seller_name,
                ]);

                if (!empty($welcome_template['line']['message']) && !empty($data['user_id'])) {
                    do_action('line_hub/send/text', [
                        'user_id' => $data['user_id'],
                        'message' => $welcome_template['line']['message'],
                    ]);
                    error_log("$log_prefix: WELCOME sent to lister user_id={$data['user_id']}");
                }
            });

            // 監聽小幫手加入事件 → 發 LINE 通知給賣家和小幫手本人
            add_action('buygo_helper_joined', function ($data) {
                $log_prefix = '[BuyGo] helper_joined notification';

                if (empty($data['seller_id']) || empty($data['user_id'])) {
                    error_log("$log_prefix: SKIP — missing seller_id or user_id: " . json_encode($data, JSON_UNESCAPED_UNICODE));
                    return;
                }

                error_log("$log_prefix: START — seller_id={$data['seller_id']}, user_id={$data['user_id']}, display_name=" . ($data['display_name'] ?? ''));

                // 通知賣家：有新小幫手加入
                $template_data = \BuyGoPlus\Services\NotificationTemplates::get('lister_joined', [
                    'display_name' => $data['display_name'] ?? '新用戶',
                ]);
                if (!empty($template_data['line']['message'])) {
                    $message = str_replace('上架幫手', '小幫手', $template_data['line']['message']);
                    do_action('line_hub/send/text', [
                        'user_id' => $data['seller_id'],
                        'message' => $message,
                    ]);
                    error_log("$log_prefix: SENT to seller user_id={$data['seller_id']}");
                }

                // 通知小幫手本人：歡迎訊息
                $seller_user = get_userdata($data['seller_id']);
                $seller_name = $seller_user ? $seller_user->display_name : '賣家';
                $welcome = \BuyGoPlus\Services\NotificationTemplates::get('helper_joined_welcome', [
                    'seller_name' => $seller_name,
                ]);
                if (!empty($welcome['line']['message'])) {
                    do_action('line_hub/send/text', [
                        'user_id' => $data['user_id'],
                        'message' => $welcome['line']['message'],
                    ]);
                    error_log("$log_prefix: WELCOME sent to helper user_id={$data['user_id']}");
                }

                error_log("$log_prefix: DONE");
            });
        }, 5);
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

        // Database 和 DatabaseChecker 由 autoloader 按需載入

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
