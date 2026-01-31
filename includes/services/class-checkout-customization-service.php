<?php
/**
 * CheckoutCustomizationService - FluentCart 結帳頁面自訂服務
 *
 * 提供結帳頁面自訂功能：
 * - 隱藏運送方式區塊
 * - 隱藏「寄送到其他地址」選項
 * - 新增身分證字號欄位（海運報關用）
 *
 * @package BuyGoPlus\Services
 * @since 0.0.5
 */

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

use BuyGoPlus\Services\TaiwanIdValidator;

class CheckoutCustomizationService
{
    /**
     * Option keys
     */
    const OPTION_HIDE_SHIPPING = 'buygo_checkout_hide_shipping';
    const OPTION_HIDE_SHIP_DIFFERENT = 'buygo_checkout_hide_ship_to_different';
    const OPTION_ENABLE_ID_NUMBER = 'buygo_checkout_enable_id_number';

    /**
     * Constructor - 註冊 hooks
     */
    public function __construct()
    {
        // CSS 注入（隱藏結帳元素）
        add_action('wp_head', [$this, 'inject_checkout_css'], 999);

        // 身分證欄位相關 hooks
        add_filter('fluent_cart/checkout_address_fields', [$this, 'add_id_number_field'], 10, 2);
        add_action('fluent_cart/order_created', [$this, 'save_id_number_to_order'], 10, 2);

        // 前端驗證腳本
        add_action('wp_footer', [$this, 'inject_validation_script'], 999);
    }

    /**
     * 初始化服務
     */
    public static function init(): void
    {
        new self();
    }

    /**
     * 取得所有結帳設定
     *
     * @return array
     */
    public static function get_settings(): array
    {
        return [
            'hide_shipping' => (bool) get_option(self::OPTION_HIDE_SHIPPING, false),
            'hide_ship_to_different' => (bool) get_option(self::OPTION_HIDE_SHIP_DIFFERENT, false),
            'enable_id_number' => (bool) get_option(self::OPTION_ENABLE_ID_NUMBER, false),
        ];
    }

    /**
     * 儲存結帳設定
     *
     * @param array $data 設定資料
     * @return void
     */
    public static function save_settings(array $data): void
    {
        update_option(self::OPTION_HIDE_SHIPPING, !empty($data['hide_shipping']));
        update_option(self::OPTION_HIDE_SHIP_DIFFERENT, !empty($data['hide_ship_to_different']));
        update_option(self::OPTION_ENABLE_ID_NUMBER, !empty($data['enable_id_number']));
    }

    /**
     * 檢查是否隱藏運送方式
     *
     * @return bool
     */
    public static function should_hide_shipping(): bool
    {
        return (bool) get_option(self::OPTION_HIDE_SHIPPING, false);
    }

    /**
     * 檢查是否隱藏寄送到其他地址
     *
     * @return bool
     */
    public static function should_hide_ship_to_different(): bool
    {
        return (bool) get_option(self::OPTION_HIDE_SHIP_DIFFERENT, false);
    }

    /**
     * 檢查是否啟用身分證字號欄位
     *
     * @return bool
     */
    public static function is_id_number_enabled(): bool
    {
        return (bool) get_option(self::OPTION_ENABLE_ID_NUMBER, false);
    }

    /**
     * 注入隱藏結帳元素的 CSS
     */
    public function inject_checkout_css(): void
    {
        // 只在前台時載入
        if (is_admin()) {
            return;
        }

        $hide_shipping = self::should_hide_shipping();
        $hide_ship_different = self::should_hide_ship_to_different();

        // 如果都沒啟用，不輸出任何 CSS
        if (!$hide_shipping && !$hide_ship_different) {
            return;
        }

        ?>
        <style id="buygo-checkout-customization">
        <?php if ($hide_shipping): ?>
        /* 隱藏運送方式/配送選項區塊 - FluentCart 結帳頁面 */
        .fct_shipping_methods_wrapper,
        .fct_checkout_shipping_section,
        [class*="fct_shipping"],
        [class*="fct-shipping"],
        /* 隱藏配送選項標題 */
        h4:has(+ .fct_shipping_methods_wrapper),
        .fct_shipping_methods_section {
            display: none !important;
        }
        <?php endif; ?>

        <?php if ($hide_ship_different): ?>
        /* 隱藏「寄送到其他地址」選項 - FluentCart 結帳頁面 */
        .fct_ship_to_different_wrapper,
        [class*="ship_to_different"],
        [class*="ship-to-different"],
        [data-fluent-cart-ship-to-different-address] {
            display: none !important;
        }
        /* 確保 shipping address 區塊也隱藏 */
        .fct_shipping_address_section,
        #shipping_address_section_section,
        [id*="shipping_address"] {
            display: none !important;
        }
        <?php endif; ?>
        </style>

        <?php if ($hide_shipping): ?>
        <script id="buygo-auto-select-shipping">
        /**
         * 自動選擇第一個配送方式
         * 當隱藏配送選項時，FluentCart 仍然需要選擇一個配送方式
         * 這個腳本會自動選擇第一個可用的配送方式
         */
        (function() {
            function autoSelectShipping() {
                // 找到配送方式的 radio button 並自動選擇第一個
                const shippingRadios = document.querySelectorAll('input[name="shipping_method_id"], input[name="shipping_method"], input[type="radio"][name*="shipping"]');

                if (shippingRadios.length > 0) {
                    const firstRadio = shippingRadios[0];
                    if (!firstRadio.checked) {
                        firstRadio.checked = true;
                        // 觸發 change 事件讓 FluentCart 知道選擇已變更
                        firstRadio.dispatchEvent(new Event('change', { bubbles: true }));
                        firstRadio.dispatchEvent(new Event('click', { bubbles: true }));
                        console.log('[BuyGo] 自動選擇配送方式:', firstRadio.value);
                    }
                    return true;
                }
                return false;
            }

            // 頁面載入後嘗試選擇
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(autoSelectShipping, 500);
                });
            } else {
                setTimeout(autoSelectShipping, 500);
            }

            // 監聽 DOM 變化（FluentCart 可能動態載入配送選項）
            const observer = new MutationObserver(function(mutations) {
                autoSelectShipping();
            });

            observer.observe(document.body, { childList: true, subtree: true });

            // 5 秒後停止監聽
            setTimeout(function() {
                observer.disconnect();
            }, 5000);
        })();
        </script>
        <?php endif; ?>
        <?php
    }

    /**
     * 新增身分證字號欄位到結帳表單
     *
     * @param array $fields 原有欄位
     * @param mixed $data 結帳資料
     * @return array 修改後的欄位
     */
    public function add_id_number_field(array $fields, $data): array
    {
        if (!self::is_id_number_enabled()) {
            return $fields;
        }

        $fields['taiwan_id_number'] = [
            'label' => '身分證字號',
            'type' => 'text',
            'required' => true,
            'placeholder' => 'A123456789',
            'description' => '海運報關使用（1 字母 + 9 數字）',
            'priority' => 100,
            'custom_attributes' => [
                'pattern' => '^[A-Za-z]{1}[1-2A-Da-d8-9]{1}[0-9]{8}$',
                'maxlength' => '10',
                'style' => 'text-transform: uppercase;',
                'autocomplete' => 'off',
                'data-validate' => 'taiwan-id'
            ]
        ];

        return $fields;
    }

    /**
     * 儲存身分證字號到訂單 meta
     *
     * @param int $order_id 訂單 ID
     * @param array $order_data 訂單資料
     * @return void
     */
    public function save_id_number_to_order($order_id, $order_data): void
    {
        if (!self::is_id_number_enabled()) {
            return;
        }

        $id_number = isset($_POST['taiwan_id_number'])
            ? sanitize_text_field($_POST['taiwan_id_number'])
            : '';

        if (empty($id_number)) {
            return;
        }

        // 轉大寫
        $id_number = strtoupper($id_number);

        // 後端驗證
        if (!TaiwanIdValidator::validate($id_number)) {
            // 記錄錯誤但不阻擋訂單（前端應該已驗證）
            error_log("[BuyGo Checkout] Invalid Taiwan ID: $id_number for order $order_id");
        }

        // 儲存到 FluentCart 訂單 meta 表 (fct_order_meta)
        global $wpdb;
        $table_name = $wpdb->prefix . 'fct_order_meta';

        // 檢查是否已存在
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE order_id = %d AND meta_key = %s",
            $order_id,
            'taiwan_id_number'
        ));

        if ($existing) {
            // 更新現有記錄
            $wpdb->update(
                $table_name,
                [
                    'meta_value' => $id_number,
                    'updated_at' => current_time('mysql')
                ],
                [
                    'order_id' => $order_id,
                    'meta_key' => 'taiwan_id_number'
                ],
                ['%s', '%s'],
                ['%d', '%s']
            );
        } else {
            // 新增記錄
            $wpdb->insert(
                $table_name,
                [
                    'order_id' => $order_id,
                    'meta_key' => 'taiwan_id_number',
                    'meta_value' => $id_number,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%s', '%s']
            );
        }

        // 記錄儲存成功
        error_log("[BuyGo Checkout] Taiwan ID saved: $id_number for order $order_id");
    }

    /**
     * 從訂單取得身分證字號
     *
     * @param int $order_id 訂單 ID
     * @return string|null 身分證字號或 null
     */
    public static function get_id_number_from_order(int $order_id): ?string
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fct_order_meta';

        $id_number = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$table_name} WHERE order_id = %d AND meta_key = %s",
            $order_id,
            'taiwan_id_number'
        ));

        return $id_number ?: null;
    }

    /**
     * 注入身分證欄位和驗證腳本
     *
     * 由於 FluentCart 的 fluent_cart/checkout_address_fields Filter
     * 在實際環境中未觸發，改用 JavaScript 動態插入欄位
     *
     * @return void
     */
    public function inject_validation_script(): void
    {
        if (is_admin() || !self::is_id_number_enabled()) {
            return;
        }
        ?>
        <script id="buygo-taiwan-id-field">
        (function() {
            function initTaiwanIdField() {
                // 檢查是否已存在
                if (document.querySelector('input[name="taiwan_id_number"]')) return;

                // 找到帳單地址區塊後面插入
                const billingSection = document.querySelector('[class*="billing"]');
                const addressSection = document.querySelector('h4');
                let insertPoint = null;

                // 嘗試多種方式找到插入點
                if (billingSection) {
                    insertPoint = billingSection.parentElement;
                } else if (addressSection) {
                    insertPoint = addressSection.parentElement;
                }

                // 找到表單中的適當位置
                const form = document.querySelector('form[class*="checkout"], form');
                if (!insertPoint && form) {
                    // 在付款區塊前面插入
                    const paymentHeading = Array.from(form.querySelectorAll('h4')).find(
                        h => h.textContent.includes('付款') || h.textContent.includes('Payment')
                    );
                    if (paymentHeading) {
                        insertPoint = paymentHeading.parentElement;
                    }
                }

                if (!insertPoint) return;

                // 建立身分證欄位區塊
                const fieldWrapper = document.createElement('div');
                fieldWrapper.className = 'buygo-taiwan-id-field';
                fieldWrapper.style.cssText = 'margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;';
                fieldWrapper.innerHTML = `
                    <label for="taiwan_id_number" style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">
                        身分證字號 <span style="color: #dc3545;">*</span>
                    </label>
                    <input
                        type="text"
                        id="taiwan_id_number"
                        name="taiwan_id_number"
                        placeholder="A123456789"
                        maxlength="10"
                        required
                        style="width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 16px; text-transform: uppercase;"
                    />
                    <small style="display: block; margin-top: 6px; color: #6c757d; font-size: 13px;">
                        海運報關使用（範例：A123456789）
                    </small>
                    <span class="field-error" style="display: none; color: #dc3545; font-size: 12px; margin-top: 4px;"></span>
                `;

                // 找到付款區塊並在前面插入
                const paymentSection = insertPoint.querySelector('h4');
                if (paymentSection && paymentSection.textContent.includes('付款')) {
                    paymentSection.parentElement.insertBefore(fieldWrapper, paymentSection);
                } else {
                    // 否則插入到區塊最後
                    insertPoint.appendChild(fieldWrapper);
                }

                // 取得 input 元素
                const idInput = document.getElementById('taiwan_id_number');
                if (!idInput) return;

                // 自動轉大寫
                idInput.addEventListener('input', function(e) {
                    e.target.value = e.target.value.toUpperCase();
                });

                // 離開欄位時驗證
                idInput.addEventListener('blur', function(e) {
                    const value = e.target.value;
                    const errorEl = fieldWrapper.querySelector('.field-error');

                    if (!value) {
                        errorEl.textContent = '請輸入身分證字號';
                        errorEl.style.display = 'block';
                        idInput.style.borderColor = '#dc3545';
                        return;
                    }

                    const isValid = validateTaiwanId(value);

                    if (!isValid) {
                        errorEl.textContent = '身分證字號格式錯誤';
                        errorEl.style.display = 'block';
                        idInput.style.borderColor = '#dc3545';
                    } else {
                        errorEl.style.display = 'none';
                        idInput.style.borderColor = '#28a745';
                    }
                });

                // 表單提交前驗證
                const parentForm = idInput.closest('form');
                if (parentForm) {
                    parentForm.addEventListener('submit', function(e) {
                        const value = idInput.value.trim();
                        if (!value || !validateTaiwanId(value)) {
                            e.preventDefault();
                            idInput.focus();
                            const errorEl = fieldWrapper.querySelector('.field-error');
                            errorEl.textContent = value ? '身分證字號格式錯誤' : '請輸入身分證字號';
                            errorEl.style.display = 'block';
                            idInput.style.borderColor = '#dc3545';
                        }
                    });
                }
            }

            function validateTaiwanId(id) {
                if (!/^[A-Z]{1}[1-2A-D8-9]{1}[0-9]{8}$/.test(id)) {
                    return false;
                }

                const letterMap = {
                    A:10,B:11,C:12,D:13,E:14,F:15,G:16,H:17,I:34,J:18,K:19,L:20,
                    M:21,N:22,O:35,P:23,Q:24,R:25,S:26,T:27,U:28,V:29,W:32,X:30,Y:31,Z:33
                };

                const letterValue = letterMap[id[0]];
                if (!letterValue) return false;

                let secondDigit;
                if (/[A-D]/.test(id[1])) {
                    secondDigit = id.charCodeAt(1) - 'A'.charCodeAt(0);
                } else {
                    secondDigit = parseInt(id[1]);
                }

                let sum = Math.floor(letterValue / 10) + (letterValue % 10) * 9;
                sum += secondDigit * 8;

                const coefficients = [7, 6, 5, 4, 3, 2, 1, 1];
                for (let i = 2; i < 10; i++) {
                    sum += parseInt(id[i]) * coefficients[i - 2];
                }

                return sum % 10 === 0;
            }

            // 初始化（支援 DOM ready 和動態載入）
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initTaiwanIdField);
            } else {
                initTaiwanIdField();
            }

            // 監聽動態內容變化（FluentCart 可能用 AJAX 載入結帳頁面）
            const observer = new MutationObserver(function(mutations) {
                if (!document.querySelector('input[name="taiwan_id_number"]')) {
                    initTaiwanIdField();
                }
            });

            observer.observe(document.body, { childList: true, subtree: true });

            // 3 秒後停止監聽（避免效能問題）
            setTimeout(function() {
                observer.disconnect();
            }, 3000);
        })();
        </script>
        <?php
    }
}
