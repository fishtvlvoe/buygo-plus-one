<?php

namespace BuyGoFluentCart\PayUNi\Scheduler;

use BuyGoFluentCart\PayUNi\API\PayUNiAPI;

use BuyGoFluentCart\PayUNi\Gateway\PayUNiSettingsBase;

use BuyGoFluentCart\PayUNi\Services\PayUNiCryptoService;

use BuyGoFluentCart\PayUNi\Utils\Logger;

use FluentCart\App\Helpers\Status;

use FluentCart\App\Models\OrderTransaction;

use FluentCart\App\Models\Subscription;

use FluentCart\App\Modules\Subscriptions\Services\SubscriptionService;

use FluentCart\App\Services\DateTime\DateTime;

use FluentCart\Framework\Support\Arr;

/**
 * PayUNiSubscriptionRenewalRunner
 *
 * 掃描 payuni_subscription 的到期訂閱並嘗試自動扣款。
 */
final class PayUNiSubscriptionRenewalRunner
{
    private PayUNiSettingsBase $settings;

    public function __construct()
    {
        $this->settings = new PayUNiSettingsBase();
    }

    public function run(): void
    {
        $nowGmt = DateTime::gmtNow()->format('Y-m-d H:i:s');

        // 只處理：啟用中/試用中、付款方式是 payuni_subscription、有 next_billing_date 且已到期的
        $subs = Subscription::query()
            ->where('current_payment_method', 'payuni_subscription')
            ->whereIn('status', [
                Status::SUBSCRIPTION_ACTIVE,
                Status::SUBSCRIPTION_TRIALING,
            ])
            ->whereNotNull('next_billing_date')
            ->where('next_billing_date', '<=', $nowGmt)
            ->limit(25)
            ->get();

        if (!$subs || $subs->isEmpty()) {
            return;
        }

        foreach ($subs as $subscription) {
            $this->processOne($subscription);
        }
    }

    private function processOne(Subscription $subscription): void
    {
        $subscription->load('customer');

        $email = '';
        try {
            $email = (string) ($subscription->customer->email ?? '');
        } catch (\Throwable $e) {
            $email = '';
        }

        $creditHash = (string) $subscription->getMeta('payuni_credit_hash', '');
        if (!$creditHash) {
            // 沒 token 不能扣款 → 標記 failing 讓後台/前台能看出需要更新付款方式
            SubscriptionService::syncSubscriptionStates($subscription, [
                'status' => Status::SUBSCRIPTION_FAILING,
                'meta' => [
                    'payuni_last_error' => [
                        'message' => 'missing_credit_hash',
                        'at' => current_time('mysql'),
                    ],
                ],
            ]);
            return;
        }

        if (!$email) {
            SubscriptionService::syncSubscriptionStates($subscription, [
                'status' => Status::SUBSCRIPTION_FAILING,
                'meta' => [
                    'payuni_last_error' => [
                        'message' => 'missing_customer_email',
                        'at' => current_time('mysql'),
                    ],
                ],
            ]);
            return;
        }

        // 避免短時間重複扣款：如果 15 分鐘內已經有成功扣款交易，就先跳過
        $recentSuccess = OrderTransaction::query()
            ->where('subscription_id', $subscription->id)
            ->where('transaction_type', Status::TRANSACTION_TYPE_CHARGE)
            ->where('status', Status::TRANSACTION_SUCCEEDED)
            ->where('created_at', '>=', gmdate('Y-m-d H:i:s', time() - (15 * 60)))
            ->exists();

        if ($recentSuccess) {
            return;
        }

        $amount = (int) $subscription->getCurrentRenewalAmount();
        $tradeAmt = $this->normalizeTradeAmount($amount);

        $mode = $subscription->order ? (string) $subscription->order->mode : $this->settings->getMode();
        if ($mode !== 'live') {
            $mode = 'test';
        }

        $merchantTradeNo = $this->generateRenewalMerTradeNo($subscription->id);

        $encryptInfo = [
            'MerID' => $this->settings->getMerId($mode),
            'MerTradeNo' => $merchantTradeNo,
            'TradeAmt' => $tradeAmt,
            'Timestamp' => time(),
            'ProdDesc' => (string) ($subscription->item_name ?: get_bloginfo('name')),
            'CreditToken' => $email,
            'CreditHash' => $creditHash,
            'Lang' => 'zh-tw',
        ];

        Logger::info('PayUNi subscription renewal attempt', [
            'subscription_id' => $subscription->id,
            'mer_trade_no' => $merchantTradeNo,
            'trade_amt' => $tradeAmt,
            'mode' => $mode,
        ]);

        $api = new PayUNiAPI($this->settings);
        $resp = $api->post('credit', $encryptInfo, '1.0', $mode);

        if (is_wp_error($resp)) {
            SubscriptionService::syncSubscriptionStates($subscription, [
                'status' => Status::SUBSCRIPTION_FAILING,
                'meta' => [
                    'payuni_last_error' => [
                        'message' => $resp->get_error_message(),
                        'at' => current_time('mysql'),
                    ],
                ],
            ]);
            return;
        }

        if (empty($resp['EncryptInfo']) || empty($resp['HashInfo'])) {
            SubscriptionService::syncSubscriptionStates($subscription, [
                'status' => Status::SUBSCRIPTION_FAILING,
                'meta' => [
                    'payuni_last_error' => [
                        'message' => 'invalid_response_missing_encryptinfo',
                        'at' => current_time('mysql'),
                        'raw' => $resp,
                    ],
                ],
            ]);
            return;
        }

        $crypto = new PayUNiCryptoService($this->settings);
        if (!$crypto->verifyHashInfo((string) $resp['EncryptInfo'], (string) $resp['HashInfo'], $mode)) {
            SubscriptionService::syncSubscriptionStates($subscription, [
                'status' => Status::SUBSCRIPTION_FAILING,
                'meta' => [
                    'payuni_last_error' => [
                        'message' => 'hash_mismatch',
                        'at' => current_time('mysql'),
                    ],
                ],
            ]);
            return;
        }

        $decrypted = $crypto->decryptInfo((string) $resp['EncryptInfo'], $mode);
        $status = (string) Arr::get($decrypted, 'Status', '');

        // 若 PayUNi 要求 3D（回傳 URL），代表客戶需要重新驗證 → 標 failing 並留 URL
        $url = (string) Arr::get($decrypted, 'URL', '');
        if ($url) {
            SubscriptionService::syncSubscriptionStates($subscription, [
                'status' => Status::SUBSCRIPTION_FAILING,
                'meta' => [
                    'payuni_last_error' => [
                        'message' => 'requires_3d',
                        'url' => $url,
                        'at' => current_time('mysql'),
                        'raw' => $decrypted,
                    ],
                ],
            ]);
            return;
        }

        if ($status !== 'SUCCESS') {
            SubscriptionService::syncSubscriptionStates($subscription, [
                'status' => Status::SUBSCRIPTION_FAILING,
                'meta' => [
                    'payuni_last_error' => [
                        'message' => (string) Arr::get($decrypted, 'Message', $status),
                        'at' => current_time('mysql'),
                        'raw' => $decrypted,
                    ],
                ],
            ]);
            return;
        }

        $tradeNo = (string) Arr::get($decrypted, 'TradeNo', '');

        // 成功：建立 FluentCart renewal 訂單/交易
        $result = SubscriptionService::recordRenewalPayment([
            'subscription_id' => $subscription->id,
            'payment_method' => 'payuni_subscription',
            'vendor_charge_id' => $tradeNo,
            'total' => $subscription->getCurrentRenewalAmount(),
            'created_at' => DateTime::now()->format('Y-m-d H:i:s'),
            'meta' => [
                'payuni' => [
                    'trade_type' => 'credit_renewal',
                    'mer_trade_no' => $merchantTradeNo,
                    'trade_no' => $tradeNo,
                    'raw' => $decrypted,
                ],
            ],
        ], $subscription, [
            'status' => Status::SUBSCRIPTION_ACTIVE,
        ]);

        if (is_wp_error($result)) {
            SubscriptionService::syncSubscriptionStates($subscription, [
                'status' => Status::SUBSCRIPTION_FAILING,
                'meta' => [
                    'payuni_last_error' => [
                        'message' => $result->get_error_message(),
                        'at' => current_time('mysql'),
                    ],
                ],
            ]);
            return;
        }

        // 清掉錯誤標記（如果有）
        $subscription->updateMeta('payuni_last_error', null);
    }

    private function normalizeTradeAmount($rawAmount): int
    {
        $amountInt = is_numeric($rawAmount) ? (int) $rawAmount : 0;
        $tradeAmt = (int) round($amountInt / 100);

        if ($tradeAmt < 1 && $amountInt >= 1) {
            $tradeAmt = $amountInt;
        }

        if ($tradeAmt < 1) {
            $tradeAmt = 1;
        }

        return $tradeAmt;
    }

    private function generateRenewalMerTradeNo(int $subscriptionId): string
    {
        $timePart = base_convert((string) time(), 10, 36);
        $randPart = substr(md5(wp_generate_password(12, false, false)), 0, 2);
        // 盡量短，避免 PayUNi 長度限制
        return 'S' . $subscriptionId . 'A' . $timePart . $randPart;
    }
}

