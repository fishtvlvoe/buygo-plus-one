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

        // LINE 內建瀏覽器偵測：未登入用戶自動跳轉 LIFF 登入
        // LIFF 登入完成後會 redirect 回此商品頁
        if ($this->is_line_browser() && !is_user_logged_in()) {
            $liff_url = $this->get_liff_url("/item/{$item_id}");
            if ($liff_url) {
                // 用 cookie 保存 redirect 路徑（LIFF login 過程中 URL 參數會丟失）
                setcookie('liff_redirect', "/item/{$item_id}", time() + 300, '/', '', is_ssl(), true);
                wp_redirect($liff_url);
                exit;
            }
            // 無 LIFF 設定時，顯示引導頁面
            $this->render_line_browser_notice($item_id, $post->post_title);
            exit;
        }

        // 重定向到 FluentCart 產品頁面
        $product_url = get_permalink($item_id);

        if ($product_url) {
            // 取得當前請求的完整 URL
            $current_url = home_url(add_query_arg(array(), $GLOBALS['wp']->request));

            // 標準化 URL 進行比較（移除尾部斜線）
            $current_url_normalized = untrailingslashit($current_url);
            $product_url_normalized = untrailingslashit($product_url);

            // 如果目標 URL 與當前 URL 相同，直接載入商品頁面而不重定向
            if ($current_url_normalized === $product_url_normalized) {
                // 設定正確的 query，讓 WordPress 載入商品頁面
                global $wp_query;
                $wp_query = new \WP_Query(array(
                    'p' => $item_id,
                    'post_type' => 'fluent-products',
                ));
                return; // 讓 WordPress 繼續載入模板
            }

            wp_redirect($product_url, 301);
            exit;
        }
        
        // 如果無法取得 permalink，返回 404
        status_header(404);
        get_template_part(404);
        exit;
    }
    
    /**
     * 取得 LIFF URL（帶 redirect 參數）
     *
     * @param string $redirect_path 登入後重定向的相對路徑
     * @return string|null LIFF URL 或 null（未設定 LIFF）
     */
    private function get_liff_url($redirect_path) {
        if (!class_exists('\\LineHub\\Services\\SettingsService')) {
            return null;
        }
        $liff_id = \LineHub\Services\SettingsService::get('general', 'liff_id', '');
        if (empty($liff_id)) {
            return null;
        }
        $redirect = urlencode($redirect_path);
        return "https://liff.line.me/{$liff_id}?redirect={$redirect}";
    }

    /**
     * 偵測是否為 LINE 內建瀏覽器
     */
    private function is_line_browser() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return stripos($user_agent, 'Line/') !== false;
    }

    /**
     * 顯示 LINE 瀏覽器引導頁面
     */
    private function render_line_browser_notice($item_id, $product_name) {
        $external_url = home_url("/item/{$item_id}");
        $product_name = esc_html($product_name);

        status_header(200);
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product_name; ?> - BuyGo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 32px 24px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            max-width: 380px;
            width: 100%;
            text-align: center;
        }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h1 { font-size: 18px; color: #333; margin-bottom: 8px; }
        .product-name {
            font-size: 15px; color: #666; margin-bottom: 24px;
            padding: 8px 12px; background: #f8f8f8; border-radius: 8px;
        }
        .notice {
            font-size: 14px; color: #555; line-height: 1.6; margin-bottom: 24px;
        }
        .btn {
            display: block; width: 100%; padding: 14px;
            border-radius: 10px; font-weight: 600; font-size: 15px;
            text-decoration: none; text-align: center; cursor: pointer;
            border: none; margin-bottom: 10px; transition: opacity 0.2s;
        }
        .btn:active { opacity: 0.8; }
        .btn-primary {
            background: #06c755; color: white;
        }
        .btn-secondary {
            background: #f0f0f0; color: #333;
        }
        .copied {
            display: none; font-size: 13px; color: #06c755;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">&#x1F6D2;</div>
        <h1>BuyGo</h1>
        <div class="product-name"><?php echo $product_name; ?></div>
        <p class="notice">
            LINE 瀏覽器不支援完整購物功能<br>
            請在手機瀏覽器中開啟此連結
        </p>
        <a href="<?php echo esc_url($external_url); ?>" class="btn btn-primary">
            &#x1F310; 在瀏覽器中開啟
        </a>
        <button class="btn btn-secondary" onclick="copyLink()">
            &#x1F4CB; 複製連結
        </button>
        <div class="copied" id="copied-msg">&#x2705; 連結已複製！請貼到瀏覽器開啟</div>
    </div>
    <script>
    function copyLink() {
        var url = <?php echo wp_json_encode($external_url); ?>;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function() {
                showCopied();
            }).catch(function() {
                fallbackCopy(url);
            });
        } else {
            fallbackCopy(url);
        }
    }
    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); showCopied(); }
        catch(e) { alert(text); }
        document.body.removeChild(ta);
    }
    function showCopied() {
        var el = document.getElementById('copied-msg');
        el.style.display = 'block';
        setTimeout(function() { el.style.display = 'none'; }, 3000);
    }
    </script>
</body>
</html>
        <?php
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
