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

        // PayUNi MerTradeNo 有長度限制，不能直接用 32 位 uuid + timestamp。
        // 用「交易 id + 非數字分隔 + 短時間戳」來保持短且可回找。
        $merchantTradeNo = $this->generateMerTradeNo($transaction);

        $mode = $this->settings->getMode();

        $tradeAmt = $this->normalizeTradeAmount($transaction->total ?? 0);

        Logger::info('Create PayUNi payment', [
            'transaction_uuid' => $trxHash,
            'merchant_trade_no' => $merchantTradeNo,
            'total' => $transaction->total ?? null,
            'trade_amt' => $tradeAmt,
            'mode' => $mode,
        ]);

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

        // UPP: 建立「整合式支付頁」(consumer redirect by POST form)
        // Required: MerID, MerTradeNo, TradeAmt, Timestamp
        // Recommended: UsrMail, ProdDesc, ReturnURL, NotifyURL
        $encryptInfo = [
            'MerID' => $this->settings->getMerId($mode),
            'MerTradeNo' => $merchantTradeNo,
            'TradeAmt' => $tradeAmt,
            'ExpireDate' => gmdate('Y-m-d', strtotime('+7 days')),
            'Timestamp' => time(),
        ];

        if (!empty($order->customer) && !empty($order->customer->email)) {
            $encryptInfo['UsrMail'] = (string) $order->customer->email;
        }

        $encryptInfo['ProdDesc'] = $this->buildProdDesc($order);
        $encryptInfo['ReturnURL'] = $returnUrl;
        $encryptInfo['NotifyURL'] = $notifyUrl;
        $encryptInfo['Lang'] = 'zh-tw';

        // 站內選擇付款方式（一次性）：credit / atm / cvs
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checkout request
        $payType = !empty($_REQUEST['payuni_payment_type']) ? sanitize_text_field(wp_unslash($_REQUEST['payuni_payment_type'])) : '';

        // 至少要指定一種支付方式，否則 PayUNi 可能直接回跳（看起來像「沒進入付款頁」）
        $encryptInfo['Credit'] = 0;
        $encryptInfo['ATM'] = 0;
        $encryptInfo['CVS'] = 0;

        if ($payType === 'atm') {
            $encryptInfo['ATM'] = 1;
        } elseif ($payType === 'cvs') {
            $encryptInfo['CVS'] = 1;
        } elseif ($payType === 'credit') {
            $encryptInfo['Credit'] = 1;
        } else {
            // fallback: 全開（維持舊行為）
            $encryptInfo['Credit'] = 1;
            $encryptInfo['ATM'] = 1;
            $encryptInfo['CVS'] = 1;
            $payType = 'all';
        }

        // Always log initiation (for debugging)
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[buygo-payuni][INIT] ' . wp_json_encode([
            'trx_hash' => $trxHash,
            'mode' => $mode,
            'endpoint' => (new PayUNiAPI($this->settings))->getEndpointUrl('upp', $mode),
            'MerTradeNo' => $merchantTradeNo,
            'TradeAmt' => $tradeAmt,
            'ReturnURL' => $returnUrl,
            'NotifyURL' => $notifyUrl,
        ]));

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
                'checkout_payment_type' => $payType,
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

    private function generateMerTradeNo($transaction): string
    {
        $id = (int) ($transaction->id ?? 0);
        if ($id < 1) {
            // fallback: 取 uuid 前 10 碼（仍然短）
            $idPart = substr((string) ($transaction->uuid ?? ''), 0, 10);
            $idPart = preg_replace('/[^a-zA-Z0-9]/', '', (string) $idPart);
            $idPart = $idPart ?: (string) time();
            return 'T' . $idPart;
        }

        $timePart = base_convert((string) time(), 10, 36);
        $randPart = substr(md5(wp_generate_password(12, false, false)), 0, 2);

        // Example: "123Akw3f9zq" (digit id + 'A' + base36 time + 2 chars)
        return $id . 'A' . $timePart . $randPart;
    }
}

