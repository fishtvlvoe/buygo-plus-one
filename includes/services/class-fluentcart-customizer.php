<?php
namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FluentCart_Customizer - FluentCart 產品頁面客製化服務
 *
 * 使用純 CSS 方案客製化 FluentCart 產品頁面，包含：
 * - 價格顯示樣式（原價刪除線、特價醒目）
 * - 數量按鈕樣式
 * - 響應式設計
 *
 * 注意：FluentCart 沒有提供產品頁面模板的 action hooks
 * （如 after_price_block、before_quantity_block）
 * 因此我們使用純 CSS 方案來客製化 FluentCart 現有元素
 *
 * FluentCart 實際 HTML 結構：
 * - .fct-product-price - 價格區域
 * - .fct-quantity-selector - 數量選擇器容器
 * - .fct-quantity-decrease-button - 減少按鈕
 * - .fct-quantity-increase-button - 增加按鈕
 * - .fct-quantity-input - 數量輸入框 (type="text")
 *
 * @package BuyGoPlus\Services
 * @since 0.0.4
 * @updated 0.0.5 改用純 CSS 方案
 */
class FluentCart_Customizer {

    /**
     * 預設設定選項
     *
     * @var array
     */
    private static $default_options = [
        'enabled'         => true,       // 是否啟用客製化
        'price_color'     => '#e63946',  // 特價顏色
        'quantity_style'  => 'rounded',  // 數量按鈕樣式: 'rounded' | 'square'
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
        $sanitized['enabled'] = !empty($input['enabled']);

        // 特價顏色（Hex color）
        $price_color = $input['price_color'] ?? '#e63946';
        $sanitized['price_color'] = sanitize_hex_color($price_color) ?: '#e63946';

        // 按鈕樣式
        $quantity_style = $input['quantity_style'] ?? 'rounded';
        $sanitized['quantity_style'] = in_array($quantity_style, ['rounded', 'square'])
            ? $quantity_style
            : 'rounded';

        return $sanitized;
    }

    /**
     * 初始化 FluentCart 客製化功能
     *
     * 註冊 CSS enqueue hooks（純 CSS 方案）
     */
    public static function init() {
        // 只在前台且有 FluentCart 時執行
        if (is_admin() || !class_exists('\FluentCart\App\Models\Product')) {
            return;
        }

        // 註冊 enqueue hooks（純 CSS 客製化）
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * 載入前端資源（CSS）
     *
     * 只在 FluentCart 產品頁面載入
     */
    public static function enqueue_assets() {
        // 只在 FluentCart 產品頁面載入
        if (!is_singular('fluent-products')) {
            return;
        }

        $options = self::get_options();

        // 如果停用客製化，不載入任何資源
        if (empty($options['enabled'])) {
            return;
        }

        // 載入 CSS
        wp_enqueue_style(
            'buygo-fluentcart-product',
            BUYGO_PLUS_ONE_PLUGIN_URL . 'assets/css/fluentcart-product-page.css',
            [],
            BUYGO_PLUS_ONE_VERSION
        );

        // 注入動態 CSS 變數
        $custom_css = self::generate_custom_css($options);
        wp_add_inline_style('buygo-fluentcart-product', $custom_css);
    }

    /**
     * 產生動態 CSS
     *
     * @param array $options 設定選項
     * @return string CSS 字串
     */
    private static function generate_custom_css($options) {
        $css = ':root { --buygo-sale-color: ' . esc_attr($options['price_color']) . '; }';

        // 根據按鈕樣式調整圓角
        if ($options['quantity_style'] === 'square') {
            $css .= '
                .fct-quantity-selector { border-radius: 4px !important; }
                .fct-quantity-decrease-button,
                .fct-quantity-increase-button { border-radius: 0 !important; }
            ';
        }

        return $css;
    }
}
