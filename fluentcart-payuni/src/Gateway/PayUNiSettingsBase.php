<?php

namespace BuyGoFluentCart\PayUNi\Gateway;

use FluentCart\App\Modules\PaymentMethods\Core\BaseGatewaySettings;

use FluentCart\Api\StoreSettings;

/**
 * PayUNi settings storage wrapper.
 *
 * 白話：後台填的 MerID/HashKey/HashIV 都存在這裡。
 */
if (!class_exists(BaseGatewaySettings::class)) {
    /**
     * Fallback for unit tests (no FluentCart).
     */
    class PayUNiSettingsBase
    {
        public function getMode(): string
        {
            return 'test';
        }

        public function getMerId(string $mode = ''): string
        {
            return '';
        }

        public function getHashKey(string $mode = ''): string
        {
            return '';
        }

        public function getHashIV(string $mode = ''): string
        {
            return '';
        }

        public function isDebug(): bool
        {
            return false;
        }
    }

    return;
}

class PayUNiSettingsBase extends BaseGatewaySettings
{
    public $methodHandler = 'fluent_cart_payment_settings_payuni';

    public ?StoreSettings $storeSettings = null;

    public function __construct()
    {
        parent::__construct();

        $settings = $this->getCachedSettings();
        $defaults = static::getDefaults();

        if (!$settings || !is_array($settings) || empty($settings)) {
            $settings = $defaults;
        } else {
            $settings = wp_parse_args($settings, $defaults);
        }

        $this->settings = $settings;

        if (!$this->storeSettings) {
            $this->storeSettings = new StoreSettings();
        }
    }

    public static function getDefaults(): array
    {
        return [
            'is_active' => 'no',
            'payment_mode' => 'test',
            // 跟隨商店（FluentCart order_mode）/ 強制測試 / 強制正式
            // follow_store | test | live
            'gateway_mode' => 'follow_store',
            'gateway_description' => '',
            'test_mer_id' => '',
            'test_hash_key' => '',
            'test_hash_iv' => '',
            'live_mer_id' => '',
            'live_hash_key' => '',
            'live_hash_iv' => '',
            'debug' => 'no',
        ];
    }

    public function get($key = '')
    {
        if ($key) {
            return $this->settings[$key] ?? null;
        }

        return $this->settings;
    }

    public function getMode(): string
    {
        if (!$this->storeSettings) {
            $this->storeSettings = new StoreSettings();
        }

        $override = (string) ($this->get('gateway_mode') ?? '');
        if ($override === 'test' || $override === 'live') {
            return $override;
        }

        // FluentCart 的 store 設定會有 order_mode（test/live）
        $storeMode = (string) $this->storeSettings->get('order_mode');
        return ($storeMode === 'live') ? 'live' : 'test';
    }

    public function isActive(): bool
    {
        return ($this->get('is_active') === 'yes');
    }

    public function getMerId(string $mode = ''): string
    {
        $mode = $mode ?: $this->getMode();

        return (string) $this->get($mode . '_mer_id');
    }

    public function getHashKey(string $mode = ''): string
    {
        $mode = $mode ?: $this->getMode();

        return (string) $this->get($mode . '_hash_key');
    }

    public function getHashIV(string $mode = ''): string
    {
        $mode = $mode ?: $this->getMode();

        return (string) $this->get($mode . '_hash_iv');
    }

    public function isDebug(): bool
    {
        return ($this->get('debug') === 'yes');
    }
}

