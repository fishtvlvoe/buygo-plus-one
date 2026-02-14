<?php
/**
 * FluentCart Product Page Customization
 * 
 * 自訂 FluentCart 產品頁面呈現方式
 * 恢復成舊版的呈現模式，確保單品和多樣式產品都能正確顯示
 */

namespace BuyGoPlus;

defined('ABSPATH') || exit;

class FluentCartProductPage {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // 在 FluentCart 產品頁面載入時執行
        add_action('wp', array($this, 'init'), 20);
    }
    
    /**
     * 初始化產品頁面自訂
     */
    public function init() {
        // 只在單一產品頁面執行
        if (!is_singular('fluent-products')) {
            return;
        }
        
        // 確保產品設定為單次付款
        $this->ensure_one_time_payment();

        // 自訂產品頁面樣式
        add_action('wp_head', array($this, 'add_product_page_styles'), 999);

        // 未登入用戶顯示登入提示
        if (!is_user_logged_in()) {
            add_action('wp_footer', array($this, 'add_login_prompt'), 20);
        }

        // 確保數量選擇器顯示
        add_filter('fluent_cart/product_quantity_enabled', array($this, 'enable_quantity_selector'), 10, 2);
    }
    
    /**
     * 確保產品設定為單次付款
     */
    private function ensure_one_time_payment() {
        global $post;
        
        if (!$post || $post->post_type !== 'fluent-products') {
            return;
        }
        
        $product_id = $post->ID;
        
        // 檢查是否為訂閱商品
        $payment_term = get_post_meta($product_id, '_fct_payment_term', true);
        $billing_interval = get_post_meta($product_id, '_fct_billing_interval', true);
        
        // 如果是訂閱商品，強制改為單次付款
        if ($payment_term === 'subscription' || !empty($billing_interval)) {
            update_post_meta($product_id, '_fct_payment_term', 'one_time');
            update_post_meta($product_id, '_fct_billing_interval', '');
            update_post_meta($product_id, '_fct_billing_period', '');
            update_post_meta($product_id, '_fct_subscription_enabled', 'no');
        }
    }
    
    /**
     * 啟用數量選擇器
     */
    public function enable_quantity_selector($enabled, $product_id) {
        // 強制啟用數量選擇器
        return true;
    }
    
    /**
     * 未登入用戶的登入提示橫幅
     */
    public function add_login_prompt() {
        $redirect_to = get_permalink();
        $login_url = home_url('/nextend_social_login/?loginSocial=line&redirect=' . urlencode($redirect_to));
        $wp_login_url = wp_login_url($redirect_to);
        ?>
        <div id="buygo-login-prompt" style="
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 9999;
            background: white; border-top: 1px solid #e0e0e0;
            box-shadow: 0 -2px 12px rgba(0,0,0,0.08);
            padding: 12px 16px; display: flex; align-items: center;
            justify-content: space-between; gap: 12px;
        ">
            <div style="flex: 1; min-width: 0;">
                <div style="font-size: 13px; color: #333; font-weight: 600;">
                    &#x1F4AC; 登入後可追蹤訂單、接收出貨通知
                </div>
            </div>
            <a href="<?php echo esc_url($login_url); ?>" style="
                display: inline-block; padding: 8px 16px;
                background: #06c755; color: white; border-radius: 8px;
                font-size: 13px; font-weight: 600; text-decoration: none;
                white-space: nowrap;
            ">LINE 登入</a>
            <button onclick="this.parentElement.style.display='none'" style="
                background: none; border: none; font-size: 18px;
                color: #999; cursor: pointer; padding: 4px;
                line-height: 1;
            ">&times;</button>
        </div>
        <?php
    }

    /**
     * 添加產品頁面自訂樣式
     */
    public function add_product_page_styles() {
        ?>
        <style id="buygo-product-page-custom-styles">
            /* 確保數量選擇器顯示 */
            .fct-product-quantity,
            .fct-quantity-selector,
            .quantity-selector,
            input[name="quantity"],
            input[type="number"][name*="quantity"] {
                display: block !important;
                visibility: visible !important;
            }
            
            /* 確保加入購物車按鈕顯示 */
            .fct-add-to-cart,
            .add-to-cart-button,
            button[name="add_to_cart"] {
                display: inline-block !important;
                visibility: visible !important;
            }
            
            /* 確保立即購買按鈕顯示 */
            .fct-buy-now,
            .buy-now-button,
            button[name="buy_now"] {
                display: inline-block !important;
                visibility: visible !important;
            }
        </style>
        <?php
    }
}
