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

    add_action('fluent_cart/register_payment_methods', function ($args) {
        try {
            $gatewayManager = $args['gatewayManager'];

            $gateway = new \BuyGoFluentCart\PayUNi\Gateway\PayUNiGateway();

            $gatewayManager->register('payuni', $gateway);
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

        // Inline handler (avoid relying on wp_enqueue_script on checkout page)
        echo '<script>(function(){if(window.__buygoFcPayuniCheckoutLoaded){return;}window.__buygoFcPayuniCheckoutLoaded=true;window.addEventListener(\"fluent_cart_load_payments_payuni\",function(event){var submitButton=window.fluentcart_checkout_vars&&window.fluentcart_checkout_vars.submit_button;if(event&&event.detail&&event.detail.paymentLoader){event.detail.paymentLoader.enableCheckoutButton((submitButton&&submitButton.text)||\"送出訂單\");}});})();</script>';
    }, 10, 1);

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

