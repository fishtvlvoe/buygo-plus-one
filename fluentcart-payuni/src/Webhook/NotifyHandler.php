<?php

namespace BuyGoFluentCart\PayUNi\Webhook;

use FluentCart\App\Models\OrderTransaction;
use FluentCart\App\Helpers\Status;

use BuyGoFluentCart\PayUNi\Gateway\PayUNiSettingsBase;
use BuyGoFluentCart\PayUNi\Processor\PaymentProcessor;
use BuyGoFluentCart\PayUNi\Services\PayUNiCryptoService;
use BuyGoFluentCart\PayUNi\Utils\Logger;

/**
 * NotifyHandler
 *
 * 白話：處理 PayUNi NotifyURL（webhook）。
 *
 * 第一版：先把「去重 + 只處理自己的交易」骨架做出來。
 */
final class NotifyHandler
{
    public function processNotify(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- webhook
        $data = wp_unslash($_POST);

        Logger::info('PayUNi notify received', [
            'keys' => array_keys(is_array($data) ? $data : []),
        ]);

        if (!is_array($data)) {
            $this->sendResponse('FAIL');
            return;
        }

        $notifyId = isset($data['notify_id']) ? (string) $data['notify_id'] : '';
        $encryptInfo = isset($data['EncryptInfo']) ? (string) $data['EncryptInfo'] : '';
        $hashInfo = isset($data['HashInfo']) ? (string) $data['HashInfo'] : '';

        $dedupKey = 'payuni_notify_' . md5($notifyId ?: ($encryptInfo . '|' . $hashInfo));
        if (get_transient($dedupKey)) {
            $this->sendResponse('SUCCESS');
            return;
        }

        set_transient($dedupKey, true, 10 * MINUTE_IN_SECONDS);

        if (!$encryptInfo || !$hashInfo) {
            Logger::warning('Notify missing EncryptInfo/HashInfo', []);
            $this->sendResponse('FAIL');
            return;
        }

        $settings = new PayUNiSettingsBase();
        $crypto = new PayUNiCryptoService($settings);

        if (!$crypto->verifyHashInfo($encryptInfo, $hashInfo)) {
            Logger::warning('Notify HashInfo mismatch', []);
            $this->sendResponse('FAIL');
            return;
        }

        $decrypted = $crypto->decryptInfo($encryptInfo);
        if (!$decrypted) {
            Logger::warning('Notify decrypt failed', []);
            $this->sendResponse('FAIL');
            return;
        }

        // Always log notify payload (for debugging)
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[buygo-payuni][NOTIFY] ' . wp_json_encode([
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

        $merchantTradeNo = (string) ($decrypted['MerTradeNo'] ?? '');
        $trxHash = $this->extractTrxHashFromMerTradeNo($merchantTradeNo);

        if (!$trxHash) {
            Logger::warning('Notify cannot resolve trx_hash', [
                'MerTradeNo' => $merchantTradeNo,
            ]);
            $this->sendResponse('FAIL');
            return;
        }

        $transaction = OrderTransaction::query()
            ->where('uuid', $trxHash)
            ->where('transaction_type', Status::TRANSACTION_TYPE_CHARGE)
            ->first();

        if (!$transaction) {
            Logger::warning('Notify transaction not found', [
                'trx_hash' => $trxHash,
                'MerTradeNo' => $merchantTradeNo,
            ]);
            $this->sendResponse('FAIL');
            return;
        }

        if (($transaction->payment_method ?? '') !== 'payuni') {
            Logger::warning('Skip notify: not payuni transaction', [
                'uuid' => $transaction->uuid,
                'payment_method' => $transaction->payment_method ?? '',
            ]);
            $this->sendResponse('SUCCESS');
            return;
        }

        $processor = new PaymentProcessor($settings);

        $meta = $transaction->meta ?? [];
        $payuniMeta = is_array($meta) ? ($meta['payuni'] ?? []) : [];
        $tradeType = is_array($payuniMeta) ? (string) ($payuniMeta['trade_type'] ?? '') : '';

        $tradeStatus = (string) ($decrypted['TradeStatus'] ?? '');
        $paymentType = (string) ($decrypted['PaymentType'] ?? '');

        // ATM/CVS 幕後通知（通常是 Status=SUCCESS + PayTime 等欄位）
        // 這裡不要用 TradeStatus 判斷，因為 atm/cvs 的 notify payload 格式不同。
        if (isset($decrypted['Status']) && !$tradeStatus && ($tradeType === 'atm' || $tradeType === 'cvs')) {
            $status = (string) ($decrypted['Status'] ?? '');

            if ($status === 'SUCCESS') {
                $processor->confirmPaymentSuccess($transaction, $decrypted, 'notify_' . $tradeType);
            } else {
                $processor->processFailedPayment($transaction, $decrypted, 'notify_' . $tradeType);
            }

            $this->sendResponse('SUCCESS');
            return;
        }

        // 一次性信用卡（站內刷卡 + 3D）：PayUNi credit API notify（可能沒有 TradeStatus）
        $maybeCredit = ($tradeType === 'credit') || (isset($decrypted['Status']) && !$tradeStatus);

        if ($maybeCredit) {
            $status = (string) ($decrypted['Status'] ?? '');
            if ($status === 'SUCCESS') {
                $processor->confirmCreditPaymentSuccess($transaction, $decrypted, 'notify_credit');
            } else {
                $processor->processFailedPayment($transaction, $decrypted, 'notify_credit');
            }

            $this->sendResponse('SUCCESS');
            return;
        }

        // TradeStatus: 0 待付款 / 1 已付款 / 2 付款失敗 / 3 付款取消
        if ($tradeStatus === '1') {
            $processor->confirmPaymentSuccess($transaction, $decrypted, 'notify');
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
            $processor->processFailedPayment($transaction, $decrypted, 'notify');
        }

        $this->sendResponse('SUCCESS');
    }

    private function sendResponse(string $result): void
    {
        echo esc_html($result);
        exit;
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

