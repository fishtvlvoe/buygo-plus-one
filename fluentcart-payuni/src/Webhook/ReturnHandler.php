<?php

namespace BuyGoFluentCart\PayUNi\Webhook;

use FluentCart\App\Models\OrderTransaction;
use FluentCart\App\Helpers\Status;

use BuyGoFluentCart\PayUNi\Gateway\PayUNiSettingsBase;
use BuyGoFluentCart\PayUNi\Processor\PaymentProcessor;
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
    public function handleReturn(): void
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
            return;
        }

        $settings = new PayUNiSettingsBase();
        $crypto = new PayUNiCryptoService($settings);

        if (!$crypto->verifyHashInfo($encryptInfo, $hashInfo)) {
            Logger::warning('Return HashInfo mismatch', [
                'trx_hash' => $trxHash,
            ]);
            return;
        }

        $decrypted = $crypto->decryptInfo($encryptInfo);
        if (!$decrypted) {
            Logger::warning('Return decrypt failed', [
                'trx_hash' => $trxHash,
            ]);
            return;
        }

        if (!$trxHash) {
            $merchantTradeNo = (string) ($decrypted['MerTradeNo'] ?? '');
            $trxHash = $this->extractTrxHashFromMerTradeNo($merchantTradeNo);
        }

        if (!$trxHash) {
            return;
        }

        $transaction = OrderTransaction::query()
            ->where('uuid', $trxHash)
            ->where('transaction_type', Status::TRANSACTION_TYPE_CHARGE)
            ->first();

        if (!$transaction) {
            return;
        }

        if (($transaction->payment_method ?? '') !== 'payuni') {
            return;
        }

        Logger::info('PayUNi return received', [
            'transaction_uuid' => $trxHash,
        ]);

        $processor = new PaymentProcessor($settings);
        $status = (string) ($decrypted['Status'] ?? '');

        if ($status === 'SUCCESS') {
            $processor->confirmPaymentSuccess($transaction, $decrypted, 'return');
        } else {
            $processor->processFailedPayment($transaction, $decrypted, 'return');
        }
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

