<?php
namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FluentCart_Customizer - FluentCart 產品頁面客製化服務
 *
 * 負責 FluentCart 產品頁面的客製化功能，包含：
 * - 原價/特價顯示
 * - 限量資訊提示
 * - 數量按鈕客製化
 * - 樣式設定
 *
 * @package BuyGoPlus\Services
 * @since 0.0.4
 */
class FluentCart_Customizer {

    /**
     * 預設設定選項
     *
     * @var array
     */
    private static $default_options = [
        'show_regular_price' => true,      // 顯示原價
        'show_sale_badge'    => true,      // 顯示特價標籤
        'quantity_style'     => 'plus-minus', // 數量按鈕樣式: 'default' | 'plus-minus'
        'stock_threshold'    => 10,        // 限量提示門檻
        'show_stock_info'    => true,      // 顯示庫存資訊
        'price_color'        => '#e63946', // 特價顏色
        'button_style'       => 'rounded'  // 按鈕樣式: 'rounded' | 'square'
    ];

    /**
     * Option name in wp_options table
     *
     * @var string
     */
    const OPTION_NAME = 'buygo_fluentcart_product_display';

    /**
     * 取得設定選項
     *
     * 合併預設值與儲存的設定
     *
     * @return array 設定選項
     */
    public static function get_options() {
        $saved_options = get_option(self::OPTION_NAME, []);

        // 合併預設值
        return wp_parse_args($saved_options, self::$default_options);
    }

    /**
     * 取得單一設定選項
     *
     * @param string $key 設定鍵名
     * @return mixed 設定值，若不存在則返回 null
     */
    public static function get_option($key) {
        $options = self::get_options();
        return isset($options[$key]) ? $options[$key] : null;
    }

    /**
     * 更新設定選項
     *
     * @param array $options 要更新的設定
     * @return bool 是否更新成功
     */
    public static function update_options($options) {
        // Sanitize 輸入
        $sanitized = self::sanitize_options($options);

        // 更新到資料庫
        return update_option(self::OPTION_NAME, $sanitized);
    }

    /**
     * 驗證和清理設定輸入
     *
     * @param array $input 原始輸入
     * @return array 清理後的設定
     */
    public static function sanitize_options($input) {
        $sanitized = [];

        // Boolean 設定
        $sanitized['show_regular_price'] = !empty($input['show_regular_price']);
        $sanitized['show_sale_badge']    = !empty($input['show_sale_badge']);
        $sanitized['show_stock_info']    = !empty($input['show_stock_info']);

        // 數量按鈕樣式
        $quantity_style = $input['quantity_style'] ?? 'plus-minus';
        $sanitized['quantity_style'] = in_array($quantity_style, ['default', 'plus-minus'])
            ? $quantity_style
            : 'plus-minus';

        // 庫存門檻（整數，最小 1）
        $stock_threshold = absint($input['stock_threshold'] ?? 10);
        $sanitized['stock_threshold'] = max(1, $stock_threshold);

        // 特價顏色（Hex color）
        $price_color = $input['price_color'] ?? '#e63946';
        $sanitized['price_color'] = sanitize_hex_color($price_color) ?: '#e63946';

        // 按鈕樣式
        $button_style = $input['button_style'] ?? 'rounded';
        $sanitized['button_style'] = in_array($button_style, ['rounded', 'square'])
            ? $button_style
            : 'rounded';

        return $sanitized;
    }

    /**
     * 初始化 FluentCart 客製化功能
     *
     * 註冊所有 hooks
     */
    public static function init() {
        // 只在前台且有 FluentCart 時執行
        if (is_admin() || !class_exists('\FluentCart\App\Models\Product')) {
            return;
        }

        // 註冊 enqueue hooks
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // 註冊產品頁面 hooks
        add_action('fluent_cart/product/single/after_price_block', [__CLASS__, 'render_custom_price_display'], 20);
        add_action('fluent_cart/product/single/before_quantity_block', [__CLASS__, 'render_stock_warning'], 20);
    }

    /**
     * 載入前端資源（CSS/JS）
     *
     * 只在 FluentCart 產品頁面載入
     */
    public static function enqueue_assets() {
        // 只在 FluentCart 產品頁面載入
        if (!is_singular('fc_product')) {
            return;
        }

        $options = self::get_options();

        // 載入 CSS
        wp_enqueue_style(
            'buygo-fluentcart-product',
            BUYGO_PLUS_ONE_PLUGIN_URL . 'assets/css/fluentcart-product-page.css',
            [],
            BUYGO_PLUS_ONE_VERSION
        );

        // 注入動態 CSS 變數
        $custom_css = ':root { --buygo-sale-color: ' . esc_attr($options['price_color']) . '; }';
        wp_add_inline_style('buygo-fluentcart-product', $custom_css);

        // 載入數量按鈕 JavaScript（如果設定為 plus-minus）
        if ($options['quantity_style'] === 'plus-minus') {
            wp_enqueue_script(
                'buygo-quantity-buttons',
                BUYGO_PLUS_ONE_PLUGIN_URL . 'assets/js/quantity-buttons.js',
                [],
                BUYGO_PLUS_ONE_VERSION,
                true // 在 footer 載入
            );

            // 傳遞設定到 JavaScript
            wp_localize_script('buygo-quantity-buttons', 'buygoQuantityConfig', [
                'style'       => $options['quantity_style'],
                'buttonStyle' => $options['button_style']
            ]);
        }
    }

    /**
     * 渲染客製化價格顯示
     *
     * 在 FluentCart 產品頁面顯示原價和特價
     *
     * @param array $args Hook 傳入的參數
     */
    public static function render_custom_price_display($args) {
        $options = self::get_options();

        // 取得產品資訊
        $product = $args['product'] ?? null;
        if (!$product || !isset($product->detail)) {
            return;
        }

        $detail = $product->detail;

        // 檢查是否有價格變化
        $has_variation = method_exists($detail, 'hasPriceVariation') && $detail->hasPriceVariation();

        // 只有當有價格變動且設定啟用時才顯示
        if (!$has_variation || !$options['show_regular_price']) {
            return;
        }

        echo '<div class="buygo-price-display">';

        // 原價（刪除線）
        $max_price = $detail->formatted_max_price ?? '';
        if ($max_price) {
            echo '<span class="original-price">' . esc_html($max_price) . '</span>';
        }

        // 特價（醒目顏色）
        $min_price = $detail->formatted_min_price ?? '';
        if ($min_price) {
            echo '<span class="sale-price">' . esc_html($min_price) . '</span>';
        }

        // 特價標籤
        if ($options['show_sale_badge']) {
            echo '<span class="buygo-sale-badge">特價</span>';
        }

        echo '</div>';
    }

    /**
     * 渲染庫存警告提示
     *
     * 當庫存低於門檻時顯示限量提示
     *
     * @param array $args Hook 傳入的參數
     */
    public static function render_stock_warning($args) {
        $options = self::get_options();

        // 檢查是否啟用庫存資訊顯示
        if (!$options['show_stock_info']) {
            return;
        }

        $product = $args['product'] ?? null;
        if (!$product || !isset($product->detail)) {
            return;
        }

        $detail = $product->detail;

        // 檢查庫存資訊
        $stock_info = method_exists($detail, 'getStockAvailability')
            ? $detail->getStockAvailability()
            : ['manage_stock' => false];

        if (!$stock_info['manage_stock']) {
            return;
        }

        $quantity = $stock_info['available_quantity'] ?? 0;
        $threshold = $options['stock_threshold'];

        // 只有庫存低於門檻且大於 0 時顯示
        if ($quantity <= 0 || $quantity > $threshold) {
            return;
        }

        echo '<div class="buygo-stock-warning">';
        // Warning icon (SVG)
        echo '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">';
        echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>';
        echo '</svg>';
        echo '<span>限購 ' . esc_html($quantity) . ' 個</span>';
        echo '</div>';
    }
}
