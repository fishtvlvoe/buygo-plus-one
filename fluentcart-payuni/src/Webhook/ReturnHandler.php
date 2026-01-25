<?php

namespace BuyGoFluentCart\PayUNi\Webhook;

use FluentCart\App\Models\OrderTransaction;
use FluentCart\App\Helpers\Status;

use BuyGoFluentCart\PayUNi\Gateway\PayUNiSettingsBase;
use BuyGoFluentCart\PayUNi\Processor\PaymentProcessor;
use BuyGoFluentCart\PayUNi\Processor\SubscriptionPaymentProcessor;
use BuyGoFluentCart\PayUNi\Services\PayUNiCryptoService;
use BuyGoFluentCart\PayUNi\Utils\Logger;

/**
 * ReturnHandler
 *
 * 白話：處理 PayUNi ReturnURL（回跳）。
 *
 * 角色是「加速更新」，最後仍以 webhook 為準。
 */
final class ReturnHandler
{
    public function handleReturn(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- return from gateway
        $trxHash = isset($_REQUEST['trx_hash']) ? sanitize_text_field(wp_unslash($_REQUEST['trx_hash'])) : '';

        // If trx_hash is missing (fixed Return_URL from PayUNi backend),
        // attempt to resolve it from decrypted MerTradeNo.

        // PayUNi UPP return will POST EncryptInfo + HashInfo back to ReturnURL
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- return from gateway
        $encryptInfo = isset($_REQUEST['EncryptInfo']) ? (string) wp_unslash($_REQUEST['EncryptInfo']) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- return from gateway
        $hashInfo = isset($_REQUEST['HashInfo']) ? (string) wp_unslash($_REQUEST['HashInfo']) : '';

        if (!$encryptInfo || !$hashInfo) {
            return '';
        }

        $settings = new PayUNiSettingsBase();
        $crypto = new PayUNiCryptoService($settings);

        if (!$crypto->verifyHashInfo($encryptInfo, $hashInfo)) {
            Logger::warning('Return HashInfo mismatch', [
                'trx_hash' => $trxHash,
            ]);
            return '';
        }

        $decrypted = $crypto->decryptInfo($encryptInfo);
        if (!$decrypted) {
            Logger::warning('Return decrypt failed', [
                'trx_hash' => $trxHash,
            ]);
            return '';
        }

        if (!$trxHash) {
            $merchantTradeNo = (string) ($decrypted['MerTradeNo'] ?? '');
            $trxHash = $this->extractTrxHashFromMerTradeNo($merchantTradeNo);
        }

        if (!$trxHash) {
            return '';
        }

        // Always log return payload (for debugging the "stuck pending" issue)
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[buygo-payuni][RETURN] ' . wp_json_encode([
            'trx_hash' => $trxHash,
            'TradeStatus' => (string) ($decrypted['TradeStatus'] ?? ''),
            'PaymentType' => (string) ($decrypted['PaymentType'] ?? ''),
            'Message' => (string) ($decrypted['Message'] ?? ''),
            'TradeNo' => (string) ($decrypted['TradeNo'] ?? ''),
            'MerTradeNo' => (string) ($decrypted['MerTradeNo'] ?? ''),
            'TradeAmt' => (string) ($decrypted['TradeAmt'] ?? ''),
            'PayNo' => (string) ($decrypted['PayNo'] ?? ''),
            'BankType' => (string) ($decrypted['BankType'] ?? ''),
            'ExpireDate' => (string) ($decrypted['ExpireDate'] ?? ''),
            'raw_keys' => array_keys($decrypted),
        ]));

        $transaction = OrderTransaction::query()
            ->where('uuid', $trxHash)
            ->where('transaction_type', Status::TRANSACTION_TYPE_CHARGE)
            ->first();

        if (!$transaction) {
            return '';
        }

        Logger::info('PayUNi return received', [
            'transaction_uuid' => $trxHash,
        ]);

        // 這裡不要用「有沒有 TradeStatus」來猜是哪一種回傳，
        // 直接以 FluentCart 這筆 transaction 的 payment_method 作為準據，避免誤判導致訂閱永遠卡未付款。
        $paymentMethod = (string) ($transaction->payment_method ?? '');

        if ($paymentMethod === 'payuni_subscription') {
            // credit（訂閱/定期定額初次 3D 回跳）
            $status = (string) ($decrypted['Status'] ?? '');

            // 少數情況 PayUNi 可能沒有 Status，但會有 TradeStatus
            if (!$status && array_key_exists('TradeStatus', $decrypted)) {
                $status = ((string) ($decrypted['TradeStatus'] ?? '') === '1') ? 'SUCCESS' : 'FAILED';
            }

            if ($status === 'SUCCESS') {
                (new SubscriptionPaymentProcessor($settings))->confirmCreditPaymentSucceeded($transaction, $decrypted, 'return_credit');
            } else {
                (new PaymentProcessor($settings))->processFailedPayment($transaction, $decrypted, 'return_credit');
            }
        } elseif ($paymentMethod === 'payuni') {
            $processor = new PaymentProcessor($settings);
            $meta = $transaction->meta ?? [];
            $payuniMeta = is_array($meta) ? ($meta['payuni'] ?? []) : [];
            $tradeType = is_array($payuniMeta) ? (string) ($payuniMeta['trade_type'] ?? '') : '';

            // 一次性信用卡（站內刷卡 + 3D）：PayUNi credit API 回跳
            // 判斷準據：transaction meta 的 trade_type=credit（其次才用 payload 的欄位做保底）
            $maybeCredit = ($tradeType === 'credit') || (isset($decrypted['Status']) && !isset($decrypted['TradeStatus']));

            if ($maybeCredit) {
                $status = (string) ($decrypted['Status'] ?? '');

                if ($status === 'SUCCESS') {
                    $processor->confirmCreditPaymentSuccess($transaction, $decrypted, 'return_credit');
                } else {
                    $processor->processFailedPayment($transaction, $decrypted, 'return_credit');
                }

                return $trxHash;
            }

            // UPP（一次性，含 ATM/CVS/信用卡導轉頁）
            $tradeStatus = (string) ($decrypted['TradeStatus'] ?? '');
            $paymentType = (string) ($decrypted['PaymentType'] ?? '');

            // TradeStatus: 0 待付款 / 1 已付款 / 2 付款失敗 / 3 付款取消
            if ($tradeStatus === '1') {
                $processor->confirmPaymentSuccess($transaction, $decrypted, 'return');
            } elseif ($tradeStatus === '0') {
                $transaction->meta = array_merge($transaction->meta ?? [], [
                    'payuni' => array_merge(($transaction->meta['payuni'] ?? []), [
                        'pending' => [
                            'payment_type' => $paymentType,
                            'trade_no' => (string) ($decrypted['TradeNo'] ?? ''),
                            'trade_amt' => (string) ($decrypted['TradeAmt'] ?? ''),
                            'message' => (string) ($decrypted['Message'] ?? ''),
                            'bank_type' => (string) ($decrypted['BankType'] ?? ''),
                            'pay_no' => (string) ($decrypted['PayNo'] ?? ''),
                            'expire_date' => (string) ($decrypted['ExpireDate'] ?? ''),
                            'raw' => $decrypted,
                        ],
                    ]),
                ]);
                $transaction->save();
            } else {
                $processor->processFailedPayment($transaction, $decrypted, 'return');
            }
        } else {
            return '';
        }

        return $trxHash;
    }

    private function extractTrxHashFromMerTradeNo(string $merTradeNo): string
    {
        if (!$merTradeNo) {
            return '';
        }

        // We generate: "{$trxHash}__{time}_{rand}"
        $parts = explode('__', $merTradeNo, 2);
        if (!empty($parts[0])) {
            return (string) $parts[0];
        }

        // Fallback: old format "{$trxHash}_{time}_{rand}"
        $parts = explode('_', $merTradeNo, 2);
        if (!empty($parts[0])) {
            return (string) $parts[0];
        }

        // New format: "{transaction_id}A{timebase36}{rand}"
        if (preg_match('/^(\d+)A/i', $merTradeNo, $m)) {
            $trxId = (int) ($m[1] ?? 0);
            if ($trxId > 0) {
                $transaction = OrderTransaction::query()->where('id', $trxId)->first();
                if ($transaction && !empty($transaction->uuid)) {
                    return (string) $transaction->uuid;
                }
            }
        }

        return '';
    }
}

