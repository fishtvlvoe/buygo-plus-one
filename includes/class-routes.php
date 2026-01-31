<?php
namespace BuyGoPlus;

if (!defined('ABSPATH')) {
    exit;
}

class Routes {
    public function __construct() {
        add_action('init', [$this, 'register_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_buygo_pages']);

        // 檢查並執行 rewrite rules flush
        add_action('init', [$this, 'maybe_flush_rewrite_rules'], 20);
    }

    /**
     * 設定 flush rewrite rules flag（供 activation hook 呼叫）
     */
    public static function schedule_flush() {
        set_transient('buygo_plus_one_flush_routes', 1, 60);
    }

    /**
     * 檢查並執行 rewrite rules flush
     */
    public function maybe_flush_rewrite_rules() {
        if (get_transient('buygo_plus_one_flush_routes')) {
            delete_transient('buygo_plus_one_flush_routes');
            flush_rewrite_rules(false); // soft flush
        }
    }
    
    public function register_rewrite_rules() {
        // 註冊首頁路由（重定向到 dashboard）
        add_rewrite_rule('^buygo-portal/?$', 'index.php?buygo_page=portal_home', 'top');

        // 註冊 8 個子頁面路由
        add_rewrite_rule('^buygo-portal/dashboard/?$', 'index.php?buygo_page=dashboard', 'top');
        add_rewrite_rule('^buygo-portal/products/?$', 'index.php?buygo_page=products', 'top');
        add_rewrite_rule('^buygo-portal/orders/?$', 'index.php?buygo_page=orders', 'top');
        add_rewrite_rule('^buygo-portal/shipment-products/?$', 'index.php?buygo_page=shipment-products', 'top');
        add_rewrite_rule('^buygo-portal/shipment-details/?$', 'index.php?buygo_page=shipment-details', 'top');
        add_rewrite_rule('^buygo-portal/customers/?$', 'index.php?buygo_page=customers', 'top');
        add_rewrite_rule('^buygo-portal/settings/?$', 'index.php?buygo_page=settings', 'top');
        add_rewrite_rule('^buygo-portal/search/?$', 'index.php?buygo_page=search', 'top');
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'buygo_page';
        return $vars;
    }
    
    public function handle_buygo_pages() {
        $page = get_query_var('buygo_page');
        if ($page) {
            // 如果是首頁，重定向到 dashboard
            if ($page === 'portal_home') {
                wp_redirect(home_url('/buygo-portal/dashboard/'));
                exit;
            }

            // 載入對應的頁面檔案（新路徑：admin/partials/）
            $page_file = BUYGO_PLUS_ONE_PLUGIN_DIR . 'admin/partials/' . $page . '.php';
            if (file_exists($page_file)) {
                // 載入 template.php（包含側邊導航和基本結構）
                require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/template.php';
                exit;
            } else {
                // 如果頁面檔案不存在，載入預設模板
                require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/template.php';
                exit;
            }
        }
    }
}
