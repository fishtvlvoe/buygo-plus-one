<?php
/**
 * Short Link Routes Handler
 * 
 * 處理 /item/{post_id} 短連結路由
 * 將短連結重定向到對應的 FluentCart 產品頁面
 */

namespace BuyGoPlus;

defined('ABSPATH') || exit;

class ShortLinkRoutes {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // 註冊 rewrite rule
        add_action('init', array($this, 'add_rewrite_rules'));

        // 註冊 query var
        add_filter('query_vars', array($this, 'add_query_vars'));

        // 處理 template redirect
        add_action('template_redirect', array($this, 'handle_short_link'), 1);

        // 檢查是否需要刷新 rewrite rules（在外掛啟用後首次載入時執行）
        add_action('init', array($this, 'maybe_flush_rewrite_rules'), 20);
    }
    
    /**
     * 註冊 rewrite rules
     */
    public function add_rewrite_rules() {
        // 註冊 /item/{post_id} 路由
        add_rewrite_rule(
            '^item/([0-9]+)/?$',
            'index.php?item_id=$matches[1]',
            'top'
        );
    }
    
    /**
     * 註冊 query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'item_id';
        return $vars;
    }
    
    /**
     * 處理短連結重定向
     */
    public function handle_short_link() {
        $item_id = get_query_var('item_id');
        
        if (empty($item_id)) {
            return;
        }
        
        // 驗證 item_id 是否為數字
        $item_id = intval($item_id);
        if ($item_id <= 0) {
            return;
        }
        
        // 檢查商品是否存在且為 fluent-products 類型
        $post = get_post($item_id);
        if (!$post || $post->post_type !== 'fluent-products') {
            // 如果商品不存在，返回 404
            status_header(404);
            get_template_part(404);
            exit;
        }
        
        // 檢查商品是否已發布
        if ($post->post_status !== 'publish') {
            // 如果商品未發布，返回 404
            status_header(404);
            get_template_part(404);
            exit;
        }
        
        // 重定向到 FluentCart 產品頁面
        $product_url = get_permalink($item_id);
        
        if ($product_url) {
            wp_redirect($product_url, 301);
            exit;
        }
        
        // 如果無法取得 permalink，返回 404
        status_header(404);
        get_template_part(404);
        exit;
    }
    
    /**
     * 標記需要刷新 rewrite rules（在啟用外掛時呼叫）
     *
     * 不直接執行 flush_rewrite_rules()，而是設定 flag
     * 讓系統在下次 init hook 時才執行，確保時序正確
     */
    public function flush_rewrite_rules() {
        // 設定 transient 標記，表示需要刷新 rewrite rules
        set_transient('buygo_plus_one_flush_rewrite_rules', 1, 60);
    }

    /**
     * 檢查是否需要刷新 rewrite rules
     *
     * 在 init hook (priority 20) 執行，確保在 add_rewrite_rules() 之後
     */
    public function maybe_flush_rewrite_rules() {
        // 檢查是否有刷新標記
        if (get_transient('buygo_plus_one_flush_rewrite_rules')) {
            // 刪除標記
            delete_transient('buygo_plus_one_flush_rewrite_rules');

            // 執行刷新（此時 rewrite rules 已經透過 init hook 註冊完成）
            flush_rewrite_rules();
        }
    }
}
