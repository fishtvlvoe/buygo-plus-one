<?php

namespace BuyGoFluentCart\PayUNi\Processor;

use BuyGoFluentCart\PayUNi\Utils\Logger;

/**
 * RefundProcessor
 *
 * 第一版：先留骨架，讓 FluentCart 後台能走退款流程。
 */
final class RefundProcessor
{
    public function register(): void
    {
        // TODO: 若 FluentCart 有提供 hook/handler 註冊點，可在這裡掛上
    }

    public function refund($transaction, int $amount, array $args = [])
    {
        Logger::info('Refund requested', [
            'transaction_id' => $transaction->id ?? null,
            'amount' => $amount,
        ]);

        // TODO: 呼叫 PayUNi 退款 API，成功後回傳 vendor refund id
        return new \WP_Error('not_implemented', __('Refund is not implemented yet.', 'fluentcart-payuni'));
    }
}

