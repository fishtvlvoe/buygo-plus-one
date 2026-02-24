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

        // SPA catch-all：所有 /buygo-portal/* 都指向同一個 template.php
        // JS 端的 BuyGoRouter 負責解析路徑和切換元件
        add_rewrite_rule('^buygo-portal/([a-z-]+)/?$', 'index.php?buygo_page=$matches[1]', 'top');

        // 邀請連結路由（公開頁面，不走 SPA）
        add_rewrite_rule('^buygo-invite/accept/?$', 'index.php?buygo_invite=accept', 'top');
        add_rewrite_rule('^buygo-invite/([a-f0-9]{48})/?$', 'index.php?buygo_invite=landing&invite_token=$matches[1]', 'top');
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'buygo_page';
        $vars[] = 'buygo_invite';
        $vars[] = 'invite_token';
        return $vars;
    }
    
    public function handle_buygo_pages() {
        // 處理邀請連結路由（公開頁面）
        $invite_action = get_query_var('buygo_invite');
        if ($invite_action) {
            if ($invite_action === 'landing') {
                require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/invite-landing.php';
                exit;
            }
            if ($invite_action === 'accept') {
                require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/invite-accept.php';
                exit;
            }
        }

        $page = get_query_var('buygo_page');
        if ($page) {
            // 如果是首頁，重定向到 dashboard
            if ($page === 'portal_home') {
                wp_redirect(home_url('/buygo-portal/dashboard/'));
                exit;
            }

            // SPA 模式：所有頁面統一載入 template.php
            // JS 端的 BuyGoRouter 負責解析路徑和切換元件
            require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/views/template.php';
            exit;
        }
    }
}
