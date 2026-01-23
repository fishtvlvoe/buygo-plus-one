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
    }
    
    public function register_rewrite_rules() {
        // 註冊 7 個路由
        add_rewrite_rule('^buygo-portal/dashboard/?$', 'index.php?buygo_page=dashboard', 'top');
        add_rewrite_rule('^buygo-portal/products/?$', 'index.php?buygo_page=products', 'top');
        add_rewrite_rule('^buygo-portal/orders/?$', 'index.php?buygo_page=orders', 'top');
        add_rewrite_rule('^buygo-portal/shipment-products/?$', 'index.php?buygo_page=shipment-products', 'top');
        add_rewrite_rule('^buygo-portal/shipment-details/?$', 'index.php?buygo_page=shipment-details', 'top');
        add_rewrite_rule('^buygo-portal/customers/?$', 'index.php?buygo_page=customers', 'top');
        add_rewrite_rule('^buygo-portal/settings/?$', 'index.php?buygo_page=settings', 'top');
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'buygo_page';
        return $vars;
    }
    
    public function handle_buygo_pages() {
        $page = get_query_var('buygo_page');
        if ($page) {
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
