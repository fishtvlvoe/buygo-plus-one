<?php
/**
 * Plugin Name: PayUNiGateway for FluentCart
 * Description: Add PayUNi (統一金流) payment gateway to FluentCart.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Author: BuyGo
 * License: GPLv2 or later
 * Text Domain: fluentcart-payuni
 */

defined('ABSPATH') || exit;

define('BUYGO_FC_PAYUNI_VERSION', '0.1.0');
define('BUYGO_FC_PAYUNI_FILE', __FILE__);
define('BUYGO_FC_PAYUNI_PATH', plugin_dir_path(__FILE__));
define('BUYGO_FC_PAYUNI_URL', plugin_dir_url(__FILE__));

/**
 * Check dependencies.
 */
function buygo_fc_payuni_check_dependencies(): bool
{
    if (!class_exists('FluentCart\\App\\Modules\\PaymentMethods\\Core\\GatewayManager')) {
        add_action('admin_notices', function () {
            ?>
            <div class="notice notice-error">
                <p><?php echo esc_html__('PayUNiGateway for FluentCart requires FluentCart to be installed and activated.', 'fluentcart-payuni'); ?></p>
            </div>
            <?php
        });
        return false;
    }

    return true;
}

/**
 * Autoloader (no composer required at runtime).
 */
spl_autoload_register(function ($class) {
    $prefix = 'BuyGoFluentCart\\PayUNi\\';
    $base_dir = BUYGO_FC_PAYUNI_PATH . 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Bootstrap.
 */
function buygo_fc_payuni_bootstrap(): void
{
    if (!buygo_fc_payuni_check_dependencies()) {
        return;
    }

    // FluentCart 前台金額顯示：TWD 隱藏不必要的小數（例如 NT$30.00 → NT$30）
    add_filter('fluent_cart/hide_unnecessary_decimals', function ($hide, $context) {
        if (!class_exists(\FluentCart\Api\CurrencySettings::class)) {
            return $hide;
        }

        try {
            $currency = (string) \FluentCart\Api\CurrencySettings::get('currency');
        } catch (\Throwable $e) {
            $currency = '';
        }

        if ($currency === 'TWD') {
            return true;
        }

        return $hide;
    }, 10, 2);

    // 結帳頁只在「購物車包含訂閱」時顯示 payuni_subscription，避免實體商品出現兩個 PayUNi 選項。
    add_filter('fluent_cart/checkout_active_payment_methods', function ($methods, $context) {
        $cart = is_array($context) ? ($context['cart'] ?? null) : null;

        $hasSubscription = false;
        if ($cart && is_object($cart) && method_exists($cart, 'hasSubscription')) {
            try {
                $hasSubscription = (bool) $cart->hasSubscription();
            } catch (\Throwable $e) {
                $hasSubscription = false;
            }
        }

        if ($hasSubscription) {
            return $methods;
        }

        if (!is_array($methods)) {
            return $methods;
        }

        $filtered = array_filter($methods, function ($method) {
            if (!is_object($method) || !method_exists($method, 'getMeta')) {
                return true;
            }

            return (string) $method->getMeta('route') !== 'payuni_subscription';
        });

        return array_values($filtered);
    }, 20, 2);

    add_action('fluent_cart/register_payment_methods', function ($args) {
        try {
            $gatewayManager = $args['gatewayManager'];

            $gateway = new \BuyGoFluentCart\PayUNi\Gateway\PayUNiGateway();

            $gatewayManager->register('payuni', $gateway);

            // PayUNi 信用卡（定期定額 / 訂閱）
            $subGateway = new \BuyGoFluentCart\PayUNi\Gateway\PayUNiSubscriptionGateway();

            $gatewayManager->register('payuni_subscription', $subGateway);
        } catch (\Throwable $e) {
            // Fail-safe: never break FluentCart admin UI.
            error_log('[fluentcart-payuni] Failed to register PayUNi gateway: ' . $e->getMessage());
        }
    }, 10, 1);

    /**
     * FluentCart 前台結帳頁（/checkout/）通常不會輸出 wp_enqueue_script 的 script tag，
     * 所以第三方 gateway 必須用「內嵌」方式掛上 checkout handler。
     *
     * 這段程式會：
     * - 顯示 PayUNi 的付款說明
     * - 在 FluentCart 觸發 fluent_cart_load_payments_payuni 時，啟用「送出訂單」按鈕
     */
    add_action('fluent_cart/checkout_embed_payment_method_content', function ($args) {
        $route = is_array($args) ? ($args['route'] ?? '') : '';
        if ($route !== 'payuni') {
            return;
        }

        $desc = '使用 PayUNi（統一金流）付款，將導向至 PayUNi 付款頁完成付款。';

        $method = is_array($args) ? ($args['method'] ?? null) : null;
        if (is_object($method) && isset($method->settings) && is_object($method->settings) && method_exists($method->settings, 'get')) {
            $custom = (string) ($method->settings->get('gateway_description') ?? '');
            if ($custom) {
                $desc = $custom;
            }
        }

        echo '<p class="fct-payuni-description">' . esc_html($desc) . '</p>';

        // PayUNi（一次性）站內先選擇付款方式，再導向 PayUNi
        echo '<div class="fct-payuni-method-choice" style="margin:12px 0 0;">';

        echo '<div style="font-size:14px;font-weight:600;margin:0 0 8px;">' . esc_html__('付款方式', 'fluentcart-payuni') . '</div>';

        echo '<div style="display:flex;flex-wrap:wrap;gap:10px;">';

        echo '<label style="display:flex;align-items:center;gap:8px;border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px;background:#fff;cursor:pointer;">';
        echo '<input type="radio" name="payuni_payment_type" value="credit" checked>';
        echo '<span>' . esc_html__('信用卡', 'fluentcart-payuni') . '</span>';
        echo '</label>';

        echo '<label style="display:flex;align-items:center;gap:8px;border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px;background:#fff;cursor:pointer;">';
        echo '<input type="radio" name="payuni_payment_type" value="atm">';
        echo '<span>' . esc_html__('ATM 轉帳', 'fluentcart-payuni') . '</span>';
        echo '</label>';

        echo '<label style="display:flex;align-items:center;gap:8px;border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px;background:#fff;cursor:pointer;">';
        echo '<input type="radio" name="payuni_payment_type" value="cvs">';
        echo '<span>' . esc_html__('超商繳費', 'fluentcart-payuni') . '</span>';
        echo '</label>';

        echo '</div>';

        echo '<div style="margin:10px 0 0;color:#6b7280;font-size:13px;line-height:1.4;">' . esc_html__('送出訂單後會導向 PayUNi 付款頁（依你選的付款方式開啟對應流程）。', 'fluentcart-payuni') . '</div>';

        echo '</div>';
    }, 10, 1);

    /**
     * PayUNi 訂閱（定期定額）- 站內信用卡欄位
     *
     * 注意：這裡只輸出輸入欄位，卡號資料不會寫入 DB，只會在本次 checkout request 送到後端呼叫 PayUNi。
     */
    add_action('fluent_cart/checkout_embed_payment_method_content', function ($args) {
        $route = is_array($args) ? ($args['route'] ?? '') : '';
        if ($route !== 'payuni_subscription') {
            return;
        }

        echo '<div class="fct-payuni-subscription-fields" style="margin:12px 0;">';
        echo '<p style="margin:0 0 10px;">' . esc_html__('使用 PayUNi 信用卡定期定額付款（初次需 3D 驗證）。', 'fluentcart-payuni') . '</p>';

        echo '<div style="display:grid;grid-template-columns:1fr;gap:10px;max-width:420px;">';

        echo '<label style="display:block;">';
        echo '<div style="margin:0 0 6px;">' . esc_html__('卡號', 'fluentcart-payuni') . '</div>';
        echo '<input name="payuni_card_number" inputmode="numeric" autocomplete="cc-number" placeholder="4242 4242 4242 4242" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;">';
        echo '</label>';

        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">';
        echo '<label style="display:block;">';
        echo '<div style="margin:0 0 6px;">' . esc_html__('有效期限（MM/YY）', 'fluentcart-payuni') . '</div>';
        echo '<input name="payuni_card_expiry" inputmode="numeric" autocomplete="cc-exp" placeholder="12/30" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;">';
        echo '</label>';
        echo '<label style="display:block;">';
        echo '<div style="margin:0 0 6px;">' . esc_html__('安全碼（CVC）', 'fluentcart-payuni') . '</div>';
        echo '<input name="payuni_card_cvc" inputmode="numeric" autocomplete="cc-csc" placeholder="123" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;">';
        echo '</label>';
        echo '</div>';

        echo '</div>';

        echo '<p style="margin:10px 0 0;color:#6b7280;font-size:13px;line-height:1.4;">' . esc_html__('提示：初次付款會導向 3D 驗證頁，完成後會回到收據頁。', 'fluentcart-payuni') . '</p>';
        echo '</div>';
    }, 10, 1);

    /**
     * PayUNi 訂閱（定期定額）前台保底：
     *
     * FluentCart 在送單前會檢查 window.is_{payment_method}_ready，
     * 若沒有被設成 true，按鈕會保持 disabled，或送單時直接被擋下。
     *
     * 由於 checkout 頁大量使用 fragments/innerHTML 更新，fragment 內的 script 有機率不會執行，
     * 所以這裡用 wp_footer 輸出全域腳本，確保按鈕能正確解鎖。
     */
    add_action('wp_footer', function () {
        if (is_admin()) {
            return;
        }

        echo '<script>(function(){if(window.__buygoFcPayuniReadyBridgeLoaded){return;}window.__buygoFcPayuniReadyBridgeLoaded=true;function getSubmitText(){var sb=window.fluentcart_checkout_vars&&window.fluentcart_checkout_vars.submit_button;return(sb&&sb.text)||"Place Order";}function markReady(method){try{window["is_"+method+"_ready"]=true;}catch(e){}}function enableCheckoutButton(){var txt=getSubmitText();if(window.fluent_cart_checkout_ui_service&&window.fluent_cart_checkout_ui_service.enableCheckoutButton){window.fluent_cart_checkout_ui_service.enableCheckoutButton();if(window.fluent_cart_checkout_ui_service.setCheckoutButtonText){window.fluent_cart_checkout_ui_service.setCheckoutButtonText(txt);}}var btn=document.querySelector("[data-fluent-cart-checkout-page-checkout-button]")||document.getElementById("fluent_cart_order_btn");if(btn){btn.removeAttribute("disabled");if(btn.dataset){btn.dataset.loading="";}btn.innerHTML=txt;}}function getSelectedMethod(){var el=document.querySelector("input[name=\'_fct_pay_method\']:checked");return el?el.value:"";}function findCheckoutForm(){var m=document.querySelector("input[name=\'_fct_pay_method\']");if(m){var f=m.closest("form");if(f){return f;}}return document.querySelector("form");}function ensurePayuniPayTypeHidden(){var form=findCheckoutForm();if(!form){return;}var hidden=form.querySelector("input[name=\'payuni_payment_type\'][type=\'hidden\']");if(!hidden){hidden=document.createElement("input");hidden.type="hidden";hidden.name="payuni_payment_type";form.appendChild(hidden);}var selected=document.querySelector("input[name=\'payuni_payment_type\']:checked");hidden.value=selected?selected.value:"";}function activate(method,event){markReady(method);ensurePayuniPayTypeHidden();var txt=getSubmitText();if(event&&event.detail&&event.detail.paymentLoader&&event.detail.paymentLoader.enableCheckoutButton){event.detail.paymentLoader.enableCheckoutButton(txt);return;}enableCheckoutButton();}function activateIfSelected(){var m=getSelectedMethod();if(m==="payuni"){activate("payuni");return;}if(m==="payuni_subscription"){activate("payuni_subscription");return;}}window.addEventListener("fluent_cart_after_checkout_js_loaded",function(){ensurePayuniPayTypeHidden();activateIfSelected();});window.addEventListener("fluentCartFragmentsReplaced",function(){setTimeout(function(){ensurePayuniPayTypeHidden();activateIfSelected();},0);});document.addEventListener("change",function(e){var t=e&&e.target;if(!t){return;}if(t.name==="payuni_payment_type"){ensurePayuniPayTypeHidden();return;}if(t.name!=="_fct_pay_method"||!t.checked){return;}if(t.value==="payuni"){activate("payuni");return;}if(t.value==="payuni_subscription"){activate("payuni_subscription");return;}});window.addEventListener("fluent_cart_load_payments_payuni",function(e){activate("payuni",e);});window.addEventListener("fluent_cart_load_payments_payuni_subscription",function(e){activate("payuni_subscription",e);});window.addEventListener("fluent_cart_load_payments_payuni-subscription",function(e){activate("payuni_subscription",e);});ensurePayuniPayTypeHidden();activateIfSelected();})();</script>';
    }, 50);

    /**
     * 在 Thank You / 收據頁顯示 ATM/CVS 待付款資訊（銀行代碼、繳費帳號、截止時間）。
     */
    $payuniPendingRendered = false;

    $renderPendingBox = function ($transaction) use (&$payuniPendingRendered) {
        if (!$transaction || ($transaction->payment_method ?? '') !== 'payuni') {
            return;
        }

        $meta = $transaction->meta ?? [];
        $payuni = is_array($meta) ? ($meta['payuni'] ?? []) : [];
        $pending = is_array($payuni) ? ($payuni['pending'] ?? null) : null;

        if (!is_array($pending)) {
            return;
        }

        $paymentType = (string) ($pending['payment_type'] ?? '');
        $bankType = (string) ($pending['bank_type'] ?? '');
        $payNo = (string) ($pending['pay_no'] ?? '');
        $expireDate = (string) ($pending['expire_date'] ?? '');

        if (!$payNo && !$expireDate) {
            return;
        }

        $payuniPendingRendered = true;

        $title = '付款資訊（待完成）';
        if ($paymentType === '2') {
            $title = 'ATM 轉帳資訊（待付款）';
        } elseif ($paymentType === '3') {
            $title = '超商繳費資訊（待付款）';
        }

        echo '<div style="margin:16px 0;padding:12px 14px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;">';
        echo '<div style="font-weight:700;margin-bottom:8px;">' . esc_html($title) . '</div>';

        if ($bankType) {
            echo '<div style="margin-bottom:6px;">銀行代碼：<code style="padding:2px 6px;background:#f3f4f6;border-radius:6px;">' . esc_html($bankType) . '</code></div>';
        }

        if ($payNo) {
            echo '<div style="margin-bottom:6px;">繳費帳號：<code style="padding:2px 6px;background:#f3f4f6;border-radius:6px;">' . esc_html($payNo) . '</code></div>';
        }

        if ($expireDate) {
            echo '<div style="margin-bottom:0;">繳費截止：' . esc_html($expireDate) . '</div>';
        }

        echo '<div style="margin-top:10px;color:#6b7280;font-size:13px;line-height:1.4;">完成付款後，狀態會在 PayUNi 通知（NotifyURL）到達後自動更新。你也可以稍後重新整理這個頁面。</div>';
        echo '</div>';
    };

    add_action('fluent_cart/receipt/thank_you/after_order_header', function ($config) use ($renderPendingBox) {
        // 優先用網址上的 trx_hash 找到正確交易（避免抓到 order->last_transaction 不是同一筆）
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- receipt page
        $trxHash = !empty($_GET['trx_hash']) ? sanitize_text_field(wp_unslash($_GET['trx_hash'])) : '';

        if ($trxHash) {
            $trx = \FluentCart\App\Models\OrderTransaction::query()->where('uuid', $trxHash)->first();
            if ($trx) {
                $renderPendingBox($trx);
                return;
            }
        }

        $order = is_array($config) ? ($config['order'] ?? null) : null;
        $trx = ($order && !empty($order->last_transaction)) ? $order->last_transaction : null;
        $renderPendingBox($trx);
    }, 10, 1);

    add_action('fluent_cart/after_receipt', function ($payload) use ($renderPendingBox, &$payuniPendingRendered) {
        if ($payuniPendingRendered) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- receipt page
        $trxHash = !empty($_GET['trx_hash']) ? sanitize_text_field(wp_unslash($_GET['trx_hash'])) : '';

        if (!$trxHash) {
            return;
        }

        $trx = \FluentCart\App\Models\OrderTransaction::query()->where('uuid', $trxHash)->first();

        if ($trx) {
            $renderPendingBox($trx);
        }
    }, 10, 1);

    /**
     * Back-compat listener:
     * 有些金流後台/舊設定會用 ?fct_payment_listener=1&method=payuni 送回來，
     * 但 FluentCart 的 IPN listener 其實是 ?fluent-cart=fct_payment_listener_ipn&method=xxx。
     *
     * 這裡直接把 payuni 的回呼/回跳在 template_redirect 接住，避免落回主題頁。
     */
    add_action('template_redirect', function () {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- webhook/return
        $isLegacyListener = !empty($_REQUEST['fct_payment_listener']) && sanitize_text_field(wp_unslash($_REQUEST['fct_payment_listener'])) === '1';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- webhook/return
        $method = !empty($_REQUEST['method']) ? sanitize_text_field(wp_unslash($_REQUEST['method'])) : '';

        if (!$isLegacyListener || $method !== 'payuni') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- webhook/return
        $isReturn = !empty($_REQUEST['payuni_return']) && sanitize_text_field(wp_unslash($_REQUEST['payuni_return'])) === '1';

        if ($isReturn) {
            // 有些情境（例如：金流端用 GET 轉址）不會把 EncryptInfo/HashInfo 帶回來，
            // 但網址上仍會有 trx_hash。這種狀況下直接導回收據頁，避免卡在空白 SUCCESS。
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- return from gateway
            $hasEncrypt = !empty($_REQUEST['EncryptInfo']) || !empty($_REQUEST['HashInfo']);

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- return from gateway
            $requestTrxHash = !empty($_REQUEST['trx_hash']) ? sanitize_text_field(wp_unslash($_REQUEST['trx_hash'])) : '';

            if (!$hasEncrypt && $requestTrxHash) {
                $transaction = \FluentCart\App\Models\OrderTransaction::query()
                    ->where('uuid', $requestTrxHash)
                    ->where('transaction_type', \FluentCart\App\Helpers\Status::TRANSACTION_TYPE_CHARGE)
                    ->first();

                if ($transaction) {
                    $receiptUrl = add_query_arg([
                        'trx_hash' => $requestTrxHash,
                        'fct_redirect' => 'yes',
                        'payuni_return' => '1',
                    ], $transaction->getReceiptPageUrl(true));

                    wp_safe_redirect($receiptUrl);
                    exit;
                }
            }

            $trxHash = (new \BuyGoFluentCart\PayUNi\Webhook\ReturnHandler())->handleReturn();

            if ($trxHash) {
                $transaction = \FluentCart\App\Models\OrderTransaction::query()
                    ->where('uuid', $trxHash)
                    ->where('transaction_type', \FluentCart\App\Helpers\Status::TRANSACTION_TYPE_CHARGE)
                    ->first();

                if ($transaction) {
                    $receiptUrl = add_query_arg([
                        'trx_hash' => $trxHash,
                        'fct_redirect' => 'yes',
                        'payuni_return' => '1',
                    ], $transaction->getReceiptPageUrl(true));

                    wp_safe_redirect($receiptUrl);
                    exit;
                }
            }

            echo esc_html('SUCCESS');
            exit;
        }

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo esc_html('OK');
            exit;
        }

        (new \BuyGoFluentCart\PayUNi\Webhook\NotifyHandler())->processNotify();
    }, 1);

    /**
     * PayUNi 訂閱（定期定額）排程扣款
     *
     * FluentCart 會用 Action Scheduler 每 5 分鐘觸發一次 fluent_cart/scheduler/five_minutes_tasks。
     * 我們在這裡掃描「到期」的 payuni_subscription 訂閱，呼叫 PayUNi credit API 幕後扣款，
     * 成功後用 FluentCart 的 SubscriptionService::recordRenewalPayment 建立 renewal 訂單/交易。
     */
    add_action('fluent_cart/scheduler/five_minutes_tasks', function () {
        if (!class_exists(\FluentCart\App\Models\Subscription::class)) {
            return;
        }

        try {
            (new \BuyGoFluentCart\PayUNi\Scheduler\PayUNiSubscriptionRenewalRunner())->run();
        } catch (\Throwable $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[fluentcart-payuni] Renewal runner error: ' . $e->getMessage());
        }
    }, 20);

    /**
     * 保底：如果 FluentCart 把使用者先導到收據頁（付款待處理），
     * 我們在「剛下單的那一次」自動導到 PayUNi 付款頁。
     */
    add_action('template_redirect', function () {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect helper
        $trxHash = isset($_GET['trx_hash']) ? sanitize_text_field(wp_unslash($_GET['trx_hash'])) : '';

        if (!$trxHash) {
            return;
        }

        // If this is a real gateway return (POST back with EncryptInfo/HashInfo), don't redirect.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- gateway return
        if (!empty($_REQUEST['EncryptInfo']) || !empty($_REQUEST['HashInfo'])) {
            return;
        }

        // Only auto-redirect once, shortly after initiating payment
        $autoRedirectKey = 'buygo_fc_payuni_autoredirect_' . $trxHash;
        if (!get_transient($autoRedirectKey)) {
            return;
        }

        delete_transient($autoRedirectKey);

        $payPageUrl = add_query_arg([
            'fluent-cart' => 'payuni_pay',
            'trx_hash' => $trxHash,
        ], home_url('/'));

        wp_redirect($payPageUrl);
        exit;
    }, 8);

    // Hosted payment page (POST form to PayUNi UPP)
    add_action('template_redirect', function () {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- payment redirect page
        $fluentCart = isset($_GET['fluent-cart']) ? sanitize_text_field(wp_unslash($_GET['fluent-cart'])) : '';
        if ($fluentCart !== 'payuni_pay') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- payment redirect page
        $trxHash = isset($_GET['trx_hash']) ? sanitize_text_field(wp_unslash($_GET['trx_hash'])) : '';
        if (!$trxHash) {
            wp_die(esc_html__('Missing transaction hash.', 'fluentcart-payuni'));
        }

        $tokenKey = 'buygo_fc_payuni_pay_' . $trxHash;
        $payload = get_transient($tokenKey);

        if (!is_array($payload) || empty($payload['endpoint']) || empty($payload['params']) || !is_array($payload['params'])) {
            wp_die(esc_html__('PayUNi payment payload expired. Please try again from checkout.', 'fluentcart-payuni'));
        }

        $endpoint = (string) $payload['endpoint'];
        $params = $payload['params'];

        // One-time usage (avoid accidental re-post)
        delete_transient($tokenKey);

        status_header(200);
        header('Content-Type: text/html; charset=utf-8');

        ?>
        <!doctype html>
        <html lang="zh-Hant">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html__('正在導向 PayUNi...', 'fluentcart-payuni'); ?></title>
        </head>
        <body>
            <p><?php echo esc_html__('正在導向 PayUNi 付款頁，請稍候...', 'fluentcart-payuni'); ?></p>

            <form id="payuni_form" method="post" action="<?php echo esc_url($endpoint); ?>">
                <?php foreach ($params as $k => $v): ?>
                    <input type="hidden" name="<?php echo esc_attr((string) $k); ?>" value="<?php echo esc_attr((string) $v); ?>">
                <?php endforeach; ?>
            </form>

            <script>
                (function () {
                    var form = document.getElementById('payuni_form');
                    if (form) form.submit();
                })();
            </script>
        </body>
        </html>
        <?php
        exit;
    });
}

add_action('plugins_loaded', 'buygo_fc_payuni_bootstrap', 20);

/**
 * Activation check.
 */
function buygo_fc_payuni_activate(): void
{
    if (!buygo_fc_payuni_check_dependencies()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('PayUNiGateway for FluentCart requires FluentCart to be installed and activated.', 'fluentcart-payuni'),
            esc_html__('Plugin Activation Error', 'fluentcart-payuni'),
            ['back_link' => true]
        );
    }
}

register_activation_hook(__FILE__, 'buygo_fc_payuni_activate');

