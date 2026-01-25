<?php

namespace BuyGoFluentCart\PayUNi\Processor;

use BuyGoFluentCart\PayUNi\API\PayUNiAPI;

use BuyGoFluentCart\PayUNi\Gateway\PayUNiSettingsBase;

use BuyGoFluentCart\PayUNi\Services\PayUNiCryptoService;

use BuyGoFluentCart\PayUNi\Utils\Logger;

use FluentCart\App\Helpers\Status;

use FluentCart\App\Helpers\StatusHelper;

use FluentCart\App\Models\Order;

use FluentCart\App\Models\OrderTransaction;

use FluentCart\App\Services\Payments\PaymentInstance;

use FluentCart\App\Services\Payments\PaymentHelper;

use FluentCart\App\Modules\Subscriptions\Services\SubscriptionService;

/**
 * SubscriptionPaymentProcessor
 *
 * 初次付款（建立訂閱）：
 * - 站內輸入卡號 → 打 PayUNi credit API
 * - 若回傳需要 3D，導到 PayUNi 的 3D URL
 * - 3D 完成後 PayUNi 會回傳 EncryptInfo/HashInfo 到 ReturnURL（由 ReturnHandler 統一處理）
 *
 * 注意：卡號資料只用於當次請求，不落地、不寫入 DB。
 */
final class SubscriptionPaymentProcessor
{
    private PayUNiSettingsBase $settings;

    public function __construct(PayUNiSettingsBase $settings)
    {
        $this->settings = $settings;
    }

    public function processInitialSubscriptionPayment(PaymentInstance $paymentInstance): array
    {
        $transaction = $paymentInstance->transaction;
        $order = $paymentInstance->order;

        if (!$transaction || !$order) {
            return [
                'status' => 'failed',
                'message' => __('訂單/交易資料不存在，無法建立付款。', 'fluentcart-payuni'),
            ];
        }

        // 保底：只有在 FluentCart 已經建立 subscription model 的情況下才走「定期定額」。
        // 否則會變成只是用 credit API 做一筆一次性信用卡交易。
        if (empty($paymentInstance->subscription) || empty($paymentInstance->subscription->id)) {
            return [
                'status' => 'failed',
                'message' => __('此付款方式僅適用於「訂閱」商品。請確認你購物車內的商品/方案為訂閱，或改用一次性付款方式。', 'fluentcart-payuni'),
            ];
        }

        $trxHash = (string) $transaction->uuid;

        $mode = $this->settings->getMode();

        $tradeAmt = $this->normalizeTradeAmount($transaction->total ?? 0);

        $card = $this->getCardInputFromRequest();

        if (!$card['number'] || !$card['expiry'] || !$card['cvc']) {
            return [
                'status' => 'failed',
                'message' => __('請先填寫信用卡卡號、有效期限與安全碼。', 'fluentcart-payuni'),
            ];
        }

        $merchantTradeNo = $this->generateMerTradeNo($transaction);

        $returnUrl = add_query_arg([
            'fct_payment_listener' => '1',
            'method' => 'payuni',
            'payuni_return' => '1',
            'trx_hash' => $trxHash,
        ], site_url('/'));

        $notifyUrl = add_query_arg([
            'fct_payment_listener' => '1',
            'method' => 'payuni',
        ], site_url('/'));

        $usrMail = '';
        try {
            if (!empty($order->customer) && !empty($order->customer->email)) {
                $usrMail = (string) $order->customer->email;
            }
        } catch (\Throwable $e) {
            $usrMail = '';
        }

        if (!$usrMail) {
            // FluentCart checkout 基本上一定有 email，保底
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checkout request
            $usrMail = !empty($_REQUEST['billing_email']) ? sanitize_email(wp_unslash($_REQUEST['billing_email'])) : '';
        }

        $encryptInfo = [
            'MerID' => $this->settings->getMerId($mode),
            'MerTradeNo' => $merchantTradeNo,
            'TradeAmt' => $tradeAmt,
            'Timestamp' => time(),
            'ProdDesc' => (string) get_bloginfo('name'),
            'ReturnURL' => $returnUrl,
            'NotifyURL' => $notifyUrl,
            'CardNo' => $card['number'],
            'CardExpired' => $card['expiry'],
            'CardCVC' => $card['cvc'],
            'Lang' => 'zh-tw',
            // 訂閱一定先做 3D 驗證（拿到 CreditHash 才能做後續幕後扣款）
            'API3D' => 1,
        ];

        if ($usrMail) {
            $encryptInfo['UsrMail'] = $usrMail;
            $encryptInfo['CreditToken'] = $usrMail;
        }

        Logger::info('Create PayUNi credit (subscription) payment', [
            'trx_hash' => $trxHash,
            'mode' => $mode,
            'mer_trade_no' => $merchantTradeNo,
            'trade_amt' => $tradeAmt,
        ]);

        $api = new PayUNiAPI($this->settings);
        $resp = $api->post('credit', $encryptInfo, '1.0', $mode);

        if (is_wp_error($resp)) {
            return [
                'status' => 'failed',
                'message' => $resp->get_error_message(),
            ];
        }

        if (empty($resp['EncryptInfo']) || empty($resp['HashInfo'])) {
            return [
                'status' => 'failed',
                'message' => __('PayUNi 回傳格式不正確（缺少 EncryptInfo/HashInfo）。', 'fluentcart-payuni'),
            ];
        }

        $crypto = new PayUNiCryptoService($this->settings);
        if (!$crypto->verifyHashInfo((string) $resp['EncryptInfo'], (string) $resp['HashInfo'], $mode)) {
            return [
                'status' => 'failed',
                'message' => __('PayUNi 回傳 HashInfo 驗證失敗。', 'fluentcart-payuni'),
            ];
        }

        $decrypted = $crypto->decryptInfo((string) $resp['EncryptInfo'], $mode);

        $status = (string) ($decrypted['Status'] ?? '');
        $message = (string) ($decrypted['Message'] ?? '');

        $transaction->meta = array_merge($transaction->meta ?? [], [
            'payuni' => array_merge(($transaction->meta['payuni'] ?? []), [
                'mode' => $mode,
                'trade_type' => 'credit',
                'mer_trade_no' => $merchantTradeNo,
                'trade_amt' => $tradeAmt,
                'return_url' => $returnUrl,
                'notify_url' => $notifyUrl,
                'credit_init' => [
                    'status' => $status,
                    'message' => $message,
                    'raw' => $decrypted,
                ],
            ]),
        ]);
        $transaction->save();

        if ($status !== 'SUCCESS') {
            return [
                'status' => 'failed',
                'message' => $message ?: __('信用卡付款建立失敗。', 'fluentcart-payuni'),
            ];
        }

        // 有開 3D 驗證時，PayUNi 會回傳 URL（導去 3D 驗證頁）
        $nextUrl = (string) ($decrypted['URL'] ?? '');
        if ($nextUrl) {
            $transaction->meta = array_merge($transaction->meta ?? [], [
                'payuni' => array_merge(($transaction->meta['payuni'] ?? []), [
                    'credit_init' => array_merge(($transaction->meta['payuni']['credit_init'] ?? []), [
                        'is_3d' => true,
                        'redirect_url' => $nextUrl,
                    ]),
                ]),
            ]);
            $transaction->save();

            return [
                'status' => 'success',
                'nextAction' => 'redirect',
                'actionName' => 'custom',
                'message' => __('正在前往 3D 驗證頁面...', 'fluentcart-payuni'),
                'data' => [
                    'order' => [
                        'uuid' => $order->uuid,
                    ],
                    'transaction' => [
                        'uuid' => $transaction->uuid,
                    ],
                ],
                'redirect_to' => $nextUrl,
                'custom_payment_url' => PaymentHelper::getCustomPaymentLink($order->uuid),
            ];
        }

        // 沒有 3D 直接成功（少見），這裡直接把交易標成成功
        $this->confirmCreditPaymentSucceeded($transaction, $decrypted, 'credit_init');

        return [
            'status' => 'success',
            'nextAction' => 'success',
            'message' => __('付款成功', 'fluentcart-payuni'),
            'data' => [
                'order' => [
                    'uuid' => $order->uuid,
                ],
                'transaction' => [
                    'uuid' => $transaction->uuid,
                ],
            ],
        ];
    }

    public function confirmCreditPaymentSucceeded(OrderTransaction $transaction, array $payuniData, string $source = 'unknown'): void
    {
        if ($transaction->status === Status::TRANSACTION_SUCCEEDED) {
            return;
        }

        $order = Order::query()->where('id', $transaction->order_id)->first();
        if (!$order) {
            return;
        }

        $tradeNo = (string) ($payuniData['TradeNo'] ?? '');
        $status = (string) ($payuniData['Status'] ?? '');
        $message = (string) ($payuniData['Message'] ?? '');
        $creditHash = (string) ($payuniData['CreditHash'] ?? '');
        $card4No = (string) ($payuniData['Card4No'] ?? '');
        $creditLife = (string) ($payuniData['CreditLife'] ?? '');

        $transaction->fill([
            'vendor_charge_id' => $tradeNo ?: ($transaction->vendor_charge_id ?? ''),
            'payment_method' => $order->payment_method,
            'status' => Status::TRANSACTION_SUCCEEDED,
            'payment_method_type' => 'PayUNi',
            'meta' => array_merge($transaction->meta ?? [], [
                'payuni' => array_merge(($transaction->meta['payuni'] ?? []), [
                    'trade_no' => $tradeNo,
                    'status' => $status,
                    'message' => $message,
                    'source' => $source,
                    'credit' => [
                        'credit_hash' => $creditHash,
                        'card_4no' => $card4No,
                        'credit_life' => $creditLife,
                    ],
                    'raw' => $payuniData,
                ]),
            ]),
        ]);
        $transaction->save();

        // 訂閱：把 CreditHash 存到 subscription meta，供後續排程扣款
        try {
            $paymentInstance = new PaymentInstance($order);
            $subscription = $paymentInstance->subscription;
            if ($subscription && $creditHash) {
                $subscription->updateMeta('payuni_credit_hash', $creditHash);
                $subscription->updateMeta('active_payment_method', [
                    'details' => [
                        'method' => 'PayUNi',
                        'brand' => 'card',
                        'last_4' => $card4No,
                    ],
                ]);

                // 關鍵：把 subscription 狀態同步成 active，並確保有 next_billing_date。
                // 否則後台會顯示「未付款」/「Invalid Date」，也不會進入我們的續扣 runner 條件。
                SubscriptionService::syncSubscriptionStates($subscription, [
                    'status' => Status::SUBSCRIPTION_ACTIVE,
                    'current_payment_method' => 'payuni_subscription',
                ]);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        (new StatusHelper($order))->syncOrderStatuses($transaction);
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

    private function generateMerTradeNo($transaction): string
    {
        $id = (int) ($transaction->id ?? 0);
        if ($id < 1) {
            $idPart = substr((string) ($transaction->uuid ?? ''), 0, 10);
            $idPart = preg_replace('/[^a-zA-Z0-9]/', '', (string) $idPart);
            $idPart = $idPart ?: (string) time();
            return 'T' . $idPart;
        }

        $timePart = base_convert((string) time(), 10, 36);
        $randPart = substr(md5(wp_generate_password(12, false, false)), 0, 2);

        return $id . 'A' . $timePart . $randPart;
    }

    /**
     * 從 checkout request 讀取卡片欄位（只用於當次交易，不保存）。
     *
     * @return array{number:string,expiry:string,cvc:string}
     */
    private function getCardInputFromRequest(): array
    {
        // FluentCart checkout 會把欄位一起 POST 過來（AJAX）
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checkout request
        $number = !empty($_REQUEST['payuni_card_number']) ? sanitize_text_field(wp_unslash($_REQUEST['payuni_card_number'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checkout request
        $expiry = !empty($_REQUEST['payuni_card_expiry']) ? sanitize_text_field(wp_unslash($_REQUEST['payuni_card_expiry'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checkout request
        $cvc = !empty($_REQUEST['payuni_card_cvc']) ? sanitize_text_field(wp_unslash($_REQUEST['payuni_card_cvc'])) : '';

        $number = preg_replace('/\s+/', '', (string) $number);
        $expiry = preg_replace('/\s+/', '', (string) $expiry);
        $cvc = preg_replace('/\s+/', '', (string) $cvc);

        // expiry: 期望 MMYY
        $expiry = str_replace(['/', '-'], '', (string) $expiry);

        return [
            'number' => (string) $number,
            'expiry' => (string) $expiry,
            'cvc' => (string) $cvc,
        ];
    }
}

