<?php

namespace BuyGoFluentCart\PayUNi\Gateway;

use BuyGoFluentCart\PayUNi\Processor\SubscriptionPaymentProcessor;

use BuyGoFluentCart\PayUNi\Utils\Logger;

use FluentCart\App\Modules\PaymentMethods\Core\AbstractPaymentGateway;

use FluentCart\App\Helpers\CartCheckoutHelper;

use FluentCart\App\Services\Payments\PaymentInstance;

// 如果 FluentCart 沒載入，避免 class extends 直接炸掉
if (!class_exists(AbstractPaymentGateway::class)) {
    return;
}

/**
 * PayUNiSubscriptionGateway
 *
 * PayUNi 信用卡「定期定額」用閘道（站內輸入卡號，初次付款會走 3D 驗證取得 CreditHash）。
 */
class PayUNiSubscriptionGateway extends AbstractPaymentGateway
{
    private string $methodSlug = 'payuni_subscription';

    /**
     * supported features (給 FluentCart UI/邏輯判斷用)
     * - subscriptions: 代表這個閘道支援訂閱/定期扣款（我們在外掛內自己跑排程扣款）
     */
    public array $supportedFeatures = ['payment', 'webhook', 'subscriptions', 'card_update'];

    public function __construct()
    {
        $settings = new PayUNiSettingsBase();

        parent::__construct($settings);
    }

    public function meta(): array
    {
        return [
            'title' => 'PayUNi 信用卡（定期定額）',
            'route' => $this->methodSlug,
            'slug' => $this->methodSlug,
            'label' => 'PayUNi（訂閱）',
            'admin_title' => 'PayUNi（訂閱 / 定期定額）',
            'description' => esc_html__('使用 PayUNi 信用卡定期定額付款（初次需 3D 驗證）。', 'fluentcart-payuni'),
            'logo' => BUYGO_FC_PAYUNI_URL . 'assets/payuni-logo.svg',
            'icon' => BUYGO_FC_PAYUNI_URL . 'assets/payuni-logo.svg',
            'brand_color' => '#136196',
            'status' => ($this->settings->get('is_active') === 'yes'),
            'upcoming' => false,
            'supported_features' => $this->supportedFeatures,
        ];
    }

    public function isCurrencySupported(): bool
    {
        // 結帳頁會用這個來決定「要不要顯示」付款方式。
        // 我們用它來避免在「非訂閱」購物車中出現 payuni_subscription（會造成誤會）。
        if (is_admin()) {
            return true;
        }

        if (!class_exists(CartCheckoutHelper::class)) {
            return true;
        }

        try {
            return CartCheckoutHelper::make()->hasSubscription() === 'yes';
        } catch (\Throwable $e) {
            return true;
        }
    }

    public function fields()
    {
        // 共用 PayUNiGateway 的設定欄位即可（同一組 key/iv/merId）
        return (new PayUNiGateway())->fields();
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
        $description = $customDescription ?: '';

        return [
            'buygo_fc_payuni_subscription_data' => [
                'description' => $description,
                'css_url' => BUYGO_FC_PAYUNI_URL . 'assets/css/payuni-checkout.css',
                'accent' => '#136196',
            ],
        ];
    }

    public static function beforeSettingsUpdate($data, $oldSettings): array
    {
        return PayUNiGateway::beforeSettingsUpdate($data, $oldSettings);
    }

    public static function validateSettings($data): array
    {
        return PayUNiGateway::validateSettings($data);
    }

    public function boot()
    {
        // 讓 PayUNi 3D ReturnURL 的回傳能在頁面 load 時被處理（同 PayUNiGateway）
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- return from gateway
        if (!empty($_REQUEST['trx_hash']) &&
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- return from gateway
            !empty($_REQUEST['fct_redirect']) &&
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- return from gateway
            sanitize_text_field(wp_unslash($_REQUEST['fct_redirect'])) === 'yes' &&
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- return from gateway
            (!empty($_REQUEST['EncryptInfo']) || !empty($_REQUEST['HashInfo']))) {
            (new \BuyGoFluentCart\PayUNi\Webhook\ReturnHandler())->handleReturn();
        }
    }

    public function handleIPN()
    {
        // 直接沿用 PayUNiGateway 的 listener 行為（同一個 Return/Notify handler）
        (new PayUNiGateway())->handleIPN();
    }

    public function getOrderInfo(array $data)
    {
        wp_send_json([
            'status' => 'success',
            'message' => __('Ready to process payment', 'fluentcart-payuni'),
            'data' => [
                'gateway' => $this->methodSlug,
            ],
        ], 200);
    }

    public function makePaymentFromPaymentInstance(PaymentInstance $paymentInstance)
    {
        try {
            // 這個付款方式只應該用在「訂閱」結帳。
            // 若購物車是一次性商品，FluentCart 不會建立 subscription model，
            // 但我們仍可能收到卡號資料並完成一筆「一次付清」交易，造成誤會。
            if (empty($paymentInstance->subscription) || empty($paymentInstance->subscription->id)) {
                return [
                    'status' => 'failed',
                    'message' => __('你目前下單的不是「訂閱」商品，請改用 PayUNi（一次性付款）或確認商品/方案已設定為訂閱。', 'fluentcart-payuni'),
                ];
            }

            $processor = new SubscriptionPaymentProcessor($this->settings);
            return $processor->processInitialSubscriptionPayment($paymentInstance);
        } catch (\Throwable $e) {
            Logger::error('Subscription payment exception', $e->getMessage());

            return [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        }
    }
}

