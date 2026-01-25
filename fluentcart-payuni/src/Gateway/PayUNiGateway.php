<?php

namespace BuyGoFluentCart\PayUNi\Gateway;

use FluentCart\App\Modules\PaymentMethods\Core\AbstractPaymentGateway;

use FluentCart\App\Services\Payments\PaymentInstance;

use BuyGoFluentCart\PayUNi\Processor\PaymentProcessor;

use BuyGoFluentCart\PayUNi\Webhook\NotifyHandler;

use BuyGoFluentCart\PayUNi\Webhook\ReturnHandler;

use BuyGoFluentCart\PayUNi\Utils\Logger;
use BuyGoFluentCart\PayUNi\API\PayUNiAPI;

// 如果 FluentCart 沒載入，避免 class extends 直接炸掉
if (!class_exists(AbstractPaymentGateway::class)) {
    return;
}

/**
 * PayUNiGateway
 *
 * 這個類別只負責「跟 FluentCart 對接」：
 * - 註冊設定欄位
 * - 付款入口
 * - webhook 入口
 * - return 加速入口
 */
class PayUNiGateway extends AbstractPaymentGateway
{
    private string $methodSlug = 'payuni';

    public array $supportedFeatures = ['payment', 'refund', 'webhook'];

    public function __construct()
    {
        $settings = new PayUNiSettingsBase();

        parent::__construct($settings);
    }

    public function meta(): array
    {
        return [
            'title' => 'PayUNi 統一金流',
            'route' => 'payuni',
            'slug' => 'payuni',
            'label' => 'PayUNi',
            'admin_title' => 'PayUNi',
            'description' => esc_html__('使用 PayUNi（統一金流）安全付款。', 'fluentcart-payuni'),
            'logo' => BUYGO_FC_PAYUNI_URL . 'assets/payuni-logo.svg',
            'icon' => BUYGO_FC_PAYUNI_URL . 'assets/payuni-logo.svg',
            'brand_color' => '#136196',
            'status' => ($this->settings->get('is_active') === 'yes'),
            'upcoming' => false,
            'supported_features' => $this->supportedFeatures,
        ];
    }

    public static function validateSettings($data): array
    {
        $gatewayMode = (string) ($data['gateway_mode'] ?? 'follow_store');
        if ($gatewayMode !== 'follow_store' && $gatewayMode !== 'test' && $gatewayMode !== 'live') {
            $gatewayMode = 'follow_store';
        }

        if ($gatewayMode === 'test' || $gatewayMode === 'live') {
            $mode = $gatewayMode;
        } else {
            $storeMode = 'test';
            try {
                $storeMode = (string) (new \FluentCart\Api\StoreSettings())->get('order_mode');
            } catch (\Throwable $e) {
                $storeMode = 'test';
            }
            $mode = ($storeMode === 'live') ? 'live' : 'test';
        }

        $merId = (string) ($data[$mode . '_mer_id'] ?? '');

        $hashKey = (string) ($data[$mode . '_hash_key'] ?? '');

        $hashIv = (string) ($data[$mode . '_hash_iv'] ?? '');

        if (!$merId || !$hashKey || !$hashIv) {
            return [
                'status' => 'failed',
                'message' => __('要啟用 PayUNi，請先填好商店目前模式（測試/正式）對應的 MerID、Hash Key、Hash IV。', 'fluentcart-payuni'),
            ];
        }

        return [
            'status' => 'success',
        ];
    }

    public function boot()
    {
        // PayUNi UPP ReturnURL usually POST EncryptInfo/HashInfo to ReturnURL.
        // We use trx_hash + fct_redirect=yes as our "is return" marker.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- return from gateway
        if (!empty($_REQUEST['trx_hash']) &&
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- return from gateway
            !empty($_REQUEST['fct_redirect']) &&
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- return from gateway
            sanitize_text_field(wp_unslash($_REQUEST['fct_redirect'])) === 'yes' &&
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- return from gateway
            (!empty($_REQUEST['EncryptInfo']) || !empty($_REQUEST['HashInfo']))) {
            (new ReturnHandler())->handleReturn();
        }
    }

    public function fields()
    {
        $notifyUrl = add_query_arg([
            'fct_payment_listener' => '1',
            'method' => 'payuni',
        ], site_url('/'));

        $returnUrl = add_query_arg([
            'fct_payment_listener' => '1',
            'method' => 'payuni',
            'payuni_return' => '1',
        ], site_url('/'));

        return [
            'notice' => [
                'value' => $this->renderStoreModeNotice(),
                'label' => __('PayUNi', 'fluentcart-payuni'),
                'type' => 'notice',
            ],

            'gateway_mode' => [
                'type' => 'radio',
                'label' => __('PayUNi 模式', 'fluentcart-payuni'),
                'value' => (string) ($this->settings->get('gateway_mode') ?? 'follow_store'),
                'options' => [
                    'follow_store' => __('跟隨商店（依 FluentCart 訂單模式）', 'fluentcart-payuni'),
                    'test' => __('強制測試（Sandbox）', 'fluentcart-payuni'),
                    'live' => __('強制正式（Live）', 'fluentcart-payuni'),
                ],
                'description' => __('預設會跟隨 FluentCart 的「訂單模式」。若你需要在同一個商店裡切換測試/正式金鑰，可在這裡強制指定。', 'fluentcart-payuni'),
            ],

            'gateway_description' => [
                'type' => 'text',
                'label' => __('付款方式說明', 'fluentcart-payuni'),
                'placeholder' => __('使用 PayUNi（統一金流）安全付款。', 'fluentcart-payuni'),
                'help' => __('這段文字會顯示在結帳頁，客人選擇 PayUNi 時看到。可留空使用預設。', 'fluentcart-payuni'),
            ],

            'payment_mode' => [
                'type' => 'tabs',
                'schema' => [
                    [
                        'type' => 'tab',
                        'label' => __('正式環境資料', 'fluentcart-payuni'),
                        'value' => 'live',
                        'schema' => [
                            'live_mer_id' => [
                                'type' => 'text',
                                'label' => __('MerID（商店代號）', 'fluentcart-payuni'),
                                'placeholder' => 'ABC1234567',
                                'required' => false,
                            ],
                            'live_hash_key' => [
                                'type' => 'password',
                                'label' => __('Hash Key', 'fluentcart-payuni'),
                                'required' => false,
                            ],
                            'live_hash_iv' => [
                                'type' => 'password',
                                'label' => __('Hash IV', 'fluentcart-payuni'),
                                'required' => false,
                            ],
                        ],
                    ],
                    [
                        'type' => 'tab',
                        'label' => __('測試環境資料', 'fluentcart-payuni'),
                        'value' => 'test',
                        'schema' => [
                            'test_mer_id' => [
                                'type' => 'text',
                                'label' => __('MerID（商店代號）', 'fluentcart-payuni'),
                                'placeholder' => 'ABC1234567',
                                'required' => false,
                            ],
                            'test_hash_key' => [
                                'type' => 'password',
                                'label' => __('Hash Key', 'fluentcart-payuni'),
                                'required' => false,
                            ],
                            'test_hash_iv' => [
                                'type' => 'password',
                                'label' => __('Hash IV', 'fluentcart-payuni'),
                                'required' => false,
                            ],
                        ],
                    ],
                ],
            ],

            'debug' => [
                'type' => 'checkbox',
                'label' => __('啟用除錯紀錄（寫入 PHP error log）', 'fluentcart-payuni'),
            ],

            'notify_url_info' => [
                'type' => 'html_attr',
                'label' => __('Notify URL（Webhook）', 'fluentcart-payuni'),
                'value' => sprintf(
                    '<div class="mt-3"><p class="mb-2">%s</p><code class="copyable-content">%s</code></div>',
                    esc_html__('請到 PayUNi 後台設定這個網址：', 'fluentcart-payuni'),
                    esc_html($notifyUrl)
                ),
            ],

            'return_url_info' => [
                'type' => 'html_attr',
                'label' => __('Return URL（回跳）', 'fluentcart-payuni'),
                'value' => sprintf(
                    '<div class="mt-3"><p class="mb-2">%s</p><code class="copyable-content">%s</code><p class="mt-2 text-sm text-gray-600">%s</p></div>',
                    esc_html__('請到 PayUNi 後台設定這個網址：', 'fluentcart-payuni'),
                    esc_html($returnUrl),
                    esc_html__('提示：外掛每筆訂單也會另外送出「帶 trx_hash 的 ReturnURL」給 PayUNi，這條是固定備援入口。', 'fluentcart-payuni')
                ),
            ],
        ];
    }

    public static function beforeSettingsUpdate($data, $oldSettings): array
    {
        // Remove display-only fields
        if (isset($data['notice'])) {
            unset($data['notice']);
        }

        if (isset($data['notify_url_info'])) {
            unset($data['notify_url_info']);
        }

        if (isset($data['return_url_info'])) {
            unset($data['return_url_info']);
        }

        // Keep Logger option aligned (best-effort)
        if (isset($data['debug'])) {
            update_option('buygo_fc_payuni_debug', $data['debug'] ? 'yes' : 'no');
        }

        return $data;
    }

    public function renderStoreModeNotice(): string
    {
        $storeMode = 'test';

        try {
            $storeMode = (string) (new \FluentCart\Api\StoreSettings())->get('order_mode');
        } catch (\Throwable $e) {
            $storeMode = 'test';
        }

        $storeMode = ($storeMode === 'live') ? 'live' : 'test';
        $override = (string) ($this->settings->get('gateway_mode') ?? 'follow_store');
        $effective = ($override === 'test' || $override === 'live') ? $override : $storeMode;

        if ($effective === 'test') {
            $prefix = ($override === 'test')
                ? esc_html__('目前 PayUNi 已強制使用測試環境（Sandbox）。', 'fluentcart-payuni')
                : esc_html__('目前商店是測試模式。', 'fluentcart-payuni');

            return '<div class="mt-5"><span class="text-warning-500">' . $prefix . ' ' . esc_html__('要使用正式收款，請填好正式環境的 MerID/Hash Key/Hash IV，並將模式切換到正式（Live）。', 'fluentcart-payuni') . '</span></div>';
        }

        $prefix = ($override === 'live')
            ? esc_html__('目前 PayUNi 已強制使用正式環境（Live）。', 'fluentcart-payuni')
            : esc_html__('目前商店是正式模式（Live）。', 'fluentcart-payuni');

        return '<div class="mt-5"><span class="text-success-500">' . $prefix . '</span></div>';
    }

    public function makePaymentFromPaymentInstance(PaymentInstance $paymentInstance)
    {
        try {
            $processor = new PaymentProcessor($this->settings);
            return $processor->processSinglePayment($paymentInstance);
        } catch (\Exception $e) {
            Logger::error('Payment processing exception', $e->getMessage());
            return [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getEnqueueScriptSrc($hasSubscription = 'no'): array
    {
        return [
            [
                'handle' => 'buygo-fc-payuni-checkout',
                'src' => BUYGO_FC_PAYUNI_URL . 'assets/js/payuni-checkout.js',
            ],
        ];
    }

    public function getLocalizeData(): array
    {
        $customDescription = (string) ($this->settings->get('gateway_description') ?? '');
        // 不要在程式內硬編碼舊版「導向 PayUNi 付款頁」文案；
        // 若後台未填寫描述，就交由前端使用更符合現況的預設說明（或不顯示）。
        $description = $customDescription ?: '';

        return [
            'buygo_fc_payuni_data' => [
                'description' => $description,
                'css_url' => BUYGO_FC_PAYUNI_URL . 'assets/css/payuni-checkout.css',
                'accent' => '#136196',
            ],
        ];
    }

    public function handleIPN()
    {
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // If PayUNi backend uses a fixed Return_URL (without trx_hash),
        // allow routing by querystring marker.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- webhook/return
        $isReturn = !empty($_REQUEST['payuni_return']) && sanitize_text_field(wp_unslash($_REQUEST['payuni_return'])) === '1';

        if ($isReturn) {
            $trxHash = (new ReturnHandler())->handleReturn();

            if (!$trxHash) {
                echo esc_html('SUCCESS');
                exit;
            }

            $transaction = \FluentCart\App\Models\OrderTransaction::query()
                ->where('uuid', $trxHash)
                ->where('transaction_type', \FluentCart\App\Helpers\Status::TRANSACTION_TYPE_CHARGE)
                ->first();

            if (!$transaction) {
                echo esc_html('SUCCESS');
                exit;
            }

            $receiptUrl = add_query_arg([
                'trx_hash' => $trxHash,
                'fct_redirect' => 'yes',
                'payuni_return' => '1',
            ], $transaction->getReceiptPageUrl(true));

            wp_safe_redirect($receiptUrl);
            exit;
        }

        (new NotifyHandler())->processNotify();
    }

    /**
     * Get order information for checkout.
     *
     * FluentCart will call this during checkout to confirm the gateway is ready.
     * We respond with a simple JSON payload (same pattern as other gateways).
     *
     * @param array $data Request data
     * @return void
     */
    public function getOrderInfo(array $data)
    {
        wp_send_json([
            'status' => 'success',
            'message' => __('Ready to process payment', 'fluentcart-payuni'),
            'data' => [
                'gateway' => 'payuni',
            ],
        ], 200);
    }

    /**
     * FluentCart refund entrypoint.
     *
     * Called by FluentCart Refund service. Should return vendor refund id or WP_Error.
     */
    public function processRefund($transaction, $amount, $args)
    {
        if (!$amount || $amount <= 0) {
            return new \WP_Error(
                'invalid_refund_amount',
                __('Refund amount is required and must be greater than zero.', 'fluentcart-payuni')
            );
        }

        $tradeNo = (string) ($transaction->vendor_charge_id ?? '');
        if (!$tradeNo) {
            $tradeNo = (string) (($transaction->meta['payuni']['trade_no'] ?? '') ?: '');
        }

        if (!$tradeNo) {
            return new \WP_Error(
                'missing_trade_no',
                __('Cannot process refund: missing PayUNi TradeNo.', 'fluentcart-payuni')
            );
        }

        // FluentCart passes amount in cents typically
        $tradeAmt = (int) round(((int) $amount) / 100);
        if ($tradeAmt < 1 && (int) $amount >= 1) {
            $tradeAmt = (int) $amount;
        }

        $mode = $this->settings->getMode();

        $encryptInfo = [
            'MerID' => $this->settings->getMerId($mode),
            'TradeNo' => $tradeNo,
            'TradeAmt' => $tradeAmt,
            'Timestamp' => time(),
            'CloseType' => 2,
        ];

        $api = new PayUNiAPI($this->settings);
        $resp = $api->post('trade_close', $encryptInfo, '1.0', $mode);

        if (is_wp_error($resp)) {
            return $resp;
        }

        // Decrypt response
        if (!isset($resp['EncryptInfo'], $resp['HashInfo'])) {
            return new \WP_Error('payuni_invalid_refund_response', __('Invalid PayUNi refund response.', 'fluentcart-payuni'));
        }

        $crypto = new \BuyGoFluentCart\PayUNi\Services\PayUNiCryptoService($this->settings);
        if (!$crypto->verifyHashInfo((string) $resp['EncryptInfo'], (string) $resp['HashInfo'], $mode)) {
            return new \WP_Error('payuni_refund_hash_mismatch', __('PayUNi refund HashInfo mismatch.', 'fluentcart-payuni'));
        }

        $decrypted = $crypto->decryptInfo((string) $resp['EncryptInfo'], $mode);
        $status = (string) ($decrypted['Status'] ?? '');
        $message = (string) ($decrypted['Message'] ?? '');

        if ($status !== 'SUCCESS') {
            return new \WP_Error(
                'payuni_refund_failed',
                sprintf(
                    /* translators: %s: payuni message */
                    __('PayUNi refund failed: %s', 'fluentcart-payuni'),
                    $message ?: $status
                )
            );
        }

        // Return vendor refund id (TradeNo is acceptable for linking)
        return $tradeNo;
    }
}

