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

        $status = (string) ($decrypted['Status'] ?? '');
        if ($status === 'SUCCESS') {
            $processor->confirmPaymentSuccess($transaction, $decrypted, 'notify');
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

        return '';
    }
}

