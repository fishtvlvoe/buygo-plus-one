<?php

namespace BuyGoFluentCart\PayUNi\Processor;

use FluentCart\App\Helpers\Status;
use FluentCart\App\Helpers\StatusHelper;
use FluentCart\App\Models\Order;
use FluentCart\App\Models\OrderTransaction;
use FluentCart\App\Services\Payments\PaymentInstance;
use FluentCart\App\Services\Payments\PaymentHelper;

use BuyGoFluentCart\PayUNi\Gateway\PayUNiSettingsBase;

use BuyGoFluentCart\PayUNi\API\PayUNiAPI;
use BuyGoFluentCart\PayUNi\Services\PayUNiCryptoService;

use BuyGoFluentCart\PayUNi\Utils\Logger;

/**
 * PaymentProcessor
 *
 * 白話：把 FluentCart 的交易資料轉成 PayUNi 請求，並把必要識別存回 transaction meta。
 */
final class PaymentProcessor
{
    private PayUNiSettingsBase $settings;

    public function __construct(PayUNiSettingsBase $settings)
    {
        $this->settings = $settings;
    }

    public function processSinglePayment(PaymentInstance $paymentInstance): array
    {
        $transaction = $paymentInstance->transaction;
        $order = $paymentInstance->order;

        $trxHash = $transaction->uuid;

        $merchantTradeNo = $trxHash . '__' . time() . '_' . substr(md5(wp_generate_password(12, false, false)), 0, 8);

        $mode = $this->settings->getMode();

        $tradeAmt = $this->normalizeTradeAmount($transaction->total ?? 0);

        Logger::info('Create PayUNi payment', [
            'transaction_uuid' => $trxHash,
            'merchant_trade_no' => $merchantTradeNo,
            'total' => $transaction->total ?? null,
            'trade_amt' => $tradeAmt,
            'mode' => $mode,
        ]);

        $receiptUrl = $transaction->getReceiptPageUrl(true);

        $returnUrl = add_query_arg([
            'trx_hash' => $trxHash,
            'fct_redirect' => 'yes',
            'payuni_return' => '1',
        ], $receiptUrl);

        $notifyUrl = add_query_arg([
            'fct_payment_listener' => '1',
            'method' => 'payuni',
        ], site_url('/'));

        // UPP: 建立「整合式支付頁」(consumer redirect by POST form)
        // Required: MerID, MerTradeNo, TradeAmt, Timestamp
        // Recommended: UsrMail, ProdDesc, ReturnURL, NotifyURL
        $encryptInfo = [
            'MerID' => $this->settings->getMerId($mode),
            'MerTradeNo' => $merchantTradeNo,
            'TradeAmt' => $tradeAmt,
            'Timestamp' => time(),
        ];

        if (!empty($order->customer) && !empty($order->customer->email)) {
            $encryptInfo['UsrMail'] = (string) $order->customer->email;
        }

        $encryptInfo['ProdDesc'] = $this->buildProdDesc($order);
        $encryptInfo['ReturnURL'] = $returnUrl;
        $encryptInfo['NotifyURL'] = $notifyUrl;

        $api = new PayUNiAPI($this->settings);
        $params = $api->buildParams($encryptInfo, 'upp', '2.0', $mode);

        if (empty($params['EncryptInfo'])) {
            return [
                'status' => 'failed',
                'message' => __('PayUNi encrypt failed. Please check HashKey/HashIV.', 'fluentcart-payuni'),
            ];
        }

        // Persist mapping for callbacks + refunds
        $transaction->meta = array_merge($transaction->meta ?? [], [
            'payuni' => array_merge(($transaction->meta['payuni'] ?? []), [
                'mode' => $mode,
                'trade_type' => 'upp',
                'mer_trade_no' => $merchantTradeNo,
                'trade_amt' => $tradeAmt,
                'return_url' => $returnUrl,
                'notify_url' => $notifyUrl,
            ]),
        ]);
        $transaction->save();

        // Store form params temporarily (avoid putting EncryptInfo in URL)
        $tokenKey = 'buygo_fc_payuni_pay_' . $trxHash;
        set_transient($tokenKey, [
            'endpoint' => $api->getEndpointUrl('upp', $mode),
            'params' => [
                'MerID' => $params['MerID'],
                'Version' => $params['Version'],
                'EncryptInfo' => $params['EncryptInfo'],
                'HashInfo' => $params['HashInfo'],
            ],
        ], 30 * MINUTE_IN_SECONDS);

        // Auto-redirect helper:
        // FluentCart 有些結帳情境會先把使用者帶到收據頁（付款待處理），
        // 這個旗標讓我們在「那一次」收據頁載入時自動導去 PayUNi 付款頁。
        $autoRedirectKey = 'buygo_fc_payuni_autoredirect_' . $trxHash;
        set_transient($autoRedirectKey, true, 5 * MINUTE_IN_SECONDS);

        $payPageUrl = add_query_arg([
            'fluent-cart' => 'payuni_pay',
            'trx_hash' => $trxHash,
        ], home_url('/'));

        return [
            'status' => 'success',
            'nextAction' => 'redirect',
            'actionName' => 'custom',
            'message' => __('Redirecting to PayUNi...', 'fluentcart-payuni'),
            'data' => [
                'order' => [
                    'uuid' => $order->uuid,
                ],
                'transaction' => [
                    'uuid' => $transaction->uuid,
                ],
            ],
            'redirect_to' => $payPageUrl,
            'custom_payment_url' => PaymentHelper::getCustomPaymentLink($order->uuid),
        ];
    }

    public function confirmPaymentSuccess(OrderTransaction $transaction, array $payuniData, string $source = 'unknown'): void
    {
        if ($transaction->status === Status::TRANSACTION_SUCCEEDED) {
            return;
        }

        $order = Order::query()->where('id', $transaction->order_id)->first();
        if (!$order) {
            return;
        }

        $tradeNo = (string) ($payuniData['TradeNo'] ?? $payuniData['trade_no'] ?? '');
        $status = (string) ($payuniData['Status'] ?? $payuniData['status'] ?? '');
        $message = (string) ($payuniData['Message'] ?? $payuniData['message'] ?? '');

        $transaction->fill([
            'vendor_charge_id' => $tradeNo ?: ($transaction->vendor_charge_id ?? ''),
            'payment_method' => 'payuni',
            'status' => Status::TRANSACTION_SUCCEEDED,
            'payment_method_type' => 'PayUNi',
            'meta' => array_merge($transaction->meta ?? [], [
                'payuni' => array_merge(($transaction->meta['payuni'] ?? []), [
                    'trade_no' => $tradeNo,
                    'status' => $status,
                    'message' => $message,
                    'source' => $source,
                    'updated_at' => current_time('mysql'),
                    'raw' => $payuniData,
                ]),
            ]),
        ]);
        $transaction->save();

        fluent_cart_add_log(
            __('PayUNi Payment Confirmation', 'fluentcart-payuni'),
            sprintf(
                /* translators: 1: trade no, 2: source */
                __('Payment confirmed from PayUNi. TradeNo: %1$s (source: %2$s)', 'fluentcart-payuni'),
                $tradeNo ?: 'N/A',
                $source
            ),
            'info',
            [
                'module_name' => 'order',
                'module_id' => $order->id,
            ]
        );

        (new StatusHelper($order))->syncOrderStatuses($transaction);
    }

    public function processFailedPayment(OrderTransaction $transaction, array $reasonData, string $source = 'unknown'): void
    {
        $order = Order::query()->where('id', $transaction->order_id)->first();
        if (!$order) {
            return;
        }

        $status = (string) ($reasonData['Status'] ?? $reasonData['status'] ?? '');
        $message = (string) ($reasonData['Message'] ?? $reasonData['message'] ?? ($reasonData['reason'] ?? ''));

        $transaction->meta = array_merge($transaction->meta ?? [], [
            'payuni' => array_merge(($transaction->meta['payuni'] ?? []), [
                'failed' => true,
                'status' => $status,
                'message' => $message,
                'source' => $source,
                'updated_at' => current_time('mysql'),
                'raw' => $reasonData,
            ]),
        ]);
        $transaction->save();

        fluent_cart_add_log(
            __('PayUNi Payment Failed', 'fluentcart-payuni'),
            sprintf(
                /* translators: 1: message, 2: source */
                __('Payment failed. %1$s (source: %2$s)', 'fluentcart-payuni'),
                $message ?: 'Unknown',
                $source
            ),
            'error',
            [
                'module_name' => 'order',
                'module_id' => $order->id,
            ]
        );
    }

    private function normalizeTradeAmount($rawAmount): int
    {
        // FluentCart most commonly stores amounts in cents (integer).
        $amountInt = is_numeric($rawAmount) ? (int) $rawAmount : 0;
        $tradeAmt = (int) round($amountInt / 100);

        // Fallback: if cents-division becomes 0 but original is positive, assume already in "元"
        if ($tradeAmt < 1 && $amountInt >= 1) {
            $tradeAmt = $amountInt;
        }

        // PayUNi requires positive integer
        if ($tradeAmt < 1) {
            $tradeAmt = 1;
        }

        return $tradeAmt;
    }

    private function buildProdDesc($order): string
    {
        try {
            $items = $order->order_items ?? [];
            if (is_array($items) && count($items) > 0) {
                $first = $items[0];
                $title = (string) ($first->title ?? $first->post_title ?? '');
                if ($title) {
                    return $title;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return (string) get_bloginfo('name');
    }
}

