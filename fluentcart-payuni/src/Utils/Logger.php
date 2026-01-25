<?php

namespace BuyGoFluentCart\PayUNi\Utils;

final class Logger
{
    private const OPTION_KEY = 'buygo_fc_payuni_debug';

    public static function enabled(): bool
    {
        $v = get_option(self::OPTION_KEY, 'no');

        return $v === 'yes';
    }

    public static function info(string $message, $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function warning(string $message, $context = []): void
    {
        self::log('WARN', $message, $context);
    }

    public static function error(string $message, $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    private static function log(string $level, string $message, $context = []): void
    {
        if (!self::enabled() && $level !== 'ERROR') {
            return;
        }

        $payload = $context;
        if (is_object($payload)) {
            $payload = ['object' => get_class($payload)];
        }

        if (!is_array($payload)) {
            $payload = ['value' => $payload];
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[buygo-payuni][' . $level . '] ' . $message . ' ' . wp_json_encode($payload));
    }
}

