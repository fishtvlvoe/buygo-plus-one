<?php

namespace BuyGoFluentCart\PayUNi\Services;

use BuyGoFluentCart\PayUNi\Gateway\PayUNiSettingsBase;

/**
 * PayUNiCryptoService
 *
 * 白話：放加解密/簽章邏輯（不要碰 FluentCart 物件）。
 */
final class PayUNiCryptoService
{
    private PayUNiSettingsBase $settings;

    public function __construct(PayUNiSettingsBase $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Backward-compatible helper for unit tests / old scaffold.
     */
    public function buildStubPayload(string $merchantTradeNo): array
    {
        $mode = $this->settings->getMode();

        return [
            'mode' => $mode,
            'mer_id' => $this->settings->getMerId($mode),
            'merchant_trade_no' => $merchantTradeNo,
        ];
    }

    /**
     * Encrypt EncryptInfo array to PayUNi EncryptInfo string.
     *
     * Flow (from woomp / PayUNi SDK):
     * - http_build_query($encryptInfo)
     * - AES-256-GCM encrypt with HashKey/HashIV
     * - hex( encrypted . ':::' . base64(tag) )
     */
    public function encryptInfo(array $encryptInfo, string $mode = ''): string
    {
        $tag = '';

        $hashKey = trim($this->settings->getHashKey($mode ?: $this->settings->getMode()));
        $hashIV = trim($this->settings->getHashIV($mode ?: $this->settings->getMode()));

        $encrypted = openssl_encrypt(
            http_build_query($encryptInfo),
            'aes-256-gcm',
            $hashKey,
            0,
            $hashIV,
            $tag
        );

        if ($encrypted === false) {
            return '';
        }

        return trim(bin2hex($encrypted . ':::' . base64_encode($tag)));
    }

    /**
     * Decrypt PayUNi EncryptInfo string back to array.
     */
    public function decryptInfo(string $encryptInfo, string $mode = ''): array
    {
        $hashKey = trim($this->settings->getHashKey($mode ?: $this->settings->getMode()));
        $hashIV = trim($this->settings->getHashIV($mode ?: $this->settings->getMode()));

        $raw = hex2bin($encryptInfo);
        if ($raw === false) {
            return [];
        }

        $parts = explode(':::', $raw, 2);
        if (count($parts) !== 2) {
            return [];
        }

        [$encryptData, $tagBase64] = $parts;

        $decrypted = openssl_decrypt(
            $encryptData,
            'aes-256-gcm',
            $hashKey,
            0,
            $hashIV,
            base64_decode($tagBase64)
        );

        if ($decrypted === false) {
            return [];
        }

        parse_str($decrypted, $out);

        return is_array($out) ? $out : [];
    }

    /**
     * Build HashInfo from EncryptInfo.
     */
    public function hashInfo(string $encryptInfo, string $mode = ''): string
    {
        $hashKey = trim($this->settings->getHashKey($mode ?: $this->settings->getMode()));
        $hashIV = trim($this->settings->getHashIV($mode ?: $this->settings->getMode()));

        return strtoupper(hash('sha256', $hashKey . $encryptInfo . $hashIV));
    }

    public function verifyHashInfo(string $encryptInfo, string $hashInfo, string $mode = ''): bool
    {
        return hash_equals($this->hashInfo($encryptInfo, $mode), strtoupper((string) $hashInfo));
    }
}

