<?php
namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug Service - 除錯服務（簡化版）
 * 
 * 提供基本的日誌記錄功能
 */
class DebugService
{
    /**
     * 記錄日誌
     * 
     * @param string $service 服務名稱
     * @param string $message 訊息
     * @param array $context 上下文
     * @param string $level 日誌級別
     */
    public function log(string $service, string $message, array $context = [], string $level = 'debug'): void
    {
        // 開發階段：記錄到 error_log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[%s] %s: %s %s', $level, $service, $message, json_encode($context, JSON_UNESCAPED_UNICODE)));
        }
    }
}
