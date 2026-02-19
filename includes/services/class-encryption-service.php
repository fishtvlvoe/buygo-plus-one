<?php
namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encryption Service - 加密解密服務
 *
 * 負責 LINE 敏感設定（Channel Secret、Access Token）的加密與解密。
 * 使用 AES-128-ECB 加密方式，金鑰可在 wp-config.php 中定義 BUYGO_ENCRYPTION_KEY。
 *
 * @package BuyGoPlus\Services
 * @since 2.1.0
 */
class EncryptionService
{
    /**
     * Debug Service
     *
     * @var DebugService|null
     */
    private static $debugService = null;

    /**
     * 取得 Debug Service 實例
     *
     * @return DebugService
     */
    private static function get_debug_service(): DebugService
    {
        if (self::$debugService === null) {
            self::$debugService = DebugService::get_instance();
        }
        return self::$debugService;
    }

    /**
     * 加密金鑰（可在 wp-config.php 中定義 BUYGO_ENCRYPTION_KEY）
     * 注意：必須與舊外掛使用相同的預設金鑰，才能正確解密舊資料
     */
    public static function get_encryption_key(): string
    {
        return defined('BUYGO_ENCRYPTION_KEY') ? BUYGO_ENCRYPTION_KEY : 'buygo-secret-key-default';
    }

    /**
     * 加密方法
     */
    public static function cipher(): string
    {
        return 'AES-128-ECB';
    }

    /**
     * 檢查欄位是否需要加密
     */
    public static function is_encrypted_field(string $key): bool
    {
        $encrypted_fields = [
            'line_channel_secret',
            'line_channel_access_token',
            'line_login_channel_secret',
        ];
        return in_array($key, $encrypted_fields, true);
    }

    /**
     * 加密資料
     */
    public static function encrypt(string $data): string
    {
        if (empty($data)) {
            return $data;
        }
        return openssl_encrypt($data, self::cipher(), self::get_encryption_key());
    }

    /**
     * 解密資料
     */
    public static function decrypt(string $data): string
    {
        if (empty($data)) {
            self::get_debug_service()->log('EncryptionService', '解密：空資料', [], 'warning');
            return $data;
        }

        self::get_debug_service()->log('EncryptionService', '開始解密', array(
            'input_length' => strlen($data),
            'cipher' => self::cipher(),
        ));

        try {
            $decrypted = openssl_decrypt($data, self::cipher(), self::get_encryption_key());

            if ($decrypted === false) {
                $error = openssl_error_string();
                self::get_debug_service()->log('EncryptionService', '解密失敗', array(
                    'openssl_error' => $error,
                ), 'error');
                return $data;
            }

            self::get_debug_service()->log('EncryptionService', '解密成功', array(
                'output_length' => strlen($decrypted),
            ));

            return $decrypted;

        } catch (\Exception $e) {
            self::get_debug_service()->log('EncryptionService', '解密異常', array(
                'error' => $e->getMessage(),
            ), 'error');
            return $data;
        }
    }
}
