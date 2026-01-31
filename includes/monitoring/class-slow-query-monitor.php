<?php

namespace BuyGoPlus\Monitoring;

use BuyGoPlus\Services\DebugService;

/**
 * Slow Query Monitor - 慢查詢監控系統
 *
 * 自動記錄超過閾值的資料庫查詢，幫助識別效能瓶頸
 *
 * @package BuyGoPlus\Monitoring
 * @version 1.0.0
 */
class SlowQueryMonitor
{
    /**
     * 慢查詢閾值（毫秒）
     *
     * @var int
     */
    private $threshold_ms;

    /**
     * Debug Service 實例
     *
     * @var DebugService
     */
    private $debugService;

    /**
     * 是否啟用監控
     *
     * @var bool
     */
    private $enabled;

    /**
     * 慢查詢記錄檔路徑
     *
     * @var string
     */
    private $log_file;

    /**
     * 建構函數
     *
     * @param int $threshold_ms 慢查詢閾值（毫秒），預設 1000ms
     * @param bool $enabled 是否啟用，預設僅在 WP_DEBUG 模式啟用
     */
    public function __construct(int $threshold_ms = 1000, bool $enabled = null)
    {
        $this->threshold_ms = $threshold_ms;
        $this->debugService = DebugService::get_instance();

        // 預設僅在開發模式啟用
        if ($enabled === null) {
            $this->enabled = defined('WP_DEBUG') && WP_DEBUG;
        } else {
            $this->enabled = $enabled;
        }

        // 設定記錄檔路徑
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/buygo-logs';

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $this->log_file = $log_dir . '/slow-queries-' . date('Y-m') . '.log';

        // 註冊 WordPress 查詢監控
        if ($this->enabled) {
            add_filter('query', array($this, 'monitor_query'));
        }
    }

    /**
     * 監控查詢執行時間
     *
     * @param string $query SQL 查詢
     * @return string 原始查詢（不修改）
     */
    public function monitor_query($query)
    {
        global $wpdb;

        $start_time = microtime(true);

        // 執行原始查詢（透過返回值讓 WordPress 繼續執行）
        // 我們無法在這裡直接攔截執行時間，所以使用 'log_query_custom_data' hook

        return $query;
    }

    /**
     * 手動記錄慢查詢
     *
     * 用於 Service Layer 手動記錄查詢時間
     *
     * @param string $query SQL 查詢
     * @param float $execution_time 執行時間（秒）
     * @param array $context 額外上下文資訊
     * @return bool 是否為慢查詢
     */
    public function log_if_slow(string $query, float $execution_time, array $context = []): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $execution_ms = $execution_time * 1000;

        if ($execution_ms < $this->threshold_ms) {
            return false;
        }

        $this->log_slow_query($query, $execution_ms, $context);

        return true;
    }

    /**
     * 記錄慢查詢到檔案和 Debug Service
     *
     * @param string $query SQL 查詢
     * @param float $execution_ms 執行時間（毫秒）
     * @param array $context 額外上下文資訊
     */
    private function log_slow_query(string $query, float $execution_ms, array $context = [])
    {
        $timestamp = current_time('Y-m-d H:i:s');
        $caller = $context['caller'] ?? $this->get_query_caller();

        $log_data = [
            'timestamp' => $timestamp,
            'execution_ms' => round($execution_ms, 2),
            'query' => $this->sanitize_query($query),
            'caller' => $caller,
            'context' => $context
        ];

        // 記錄到 Debug Service
        $this->debugService->log('SlowQueryMonitor', '慢查詢偵測', $log_data, 'warning');

        // 記錄到檔案
        $log_line = sprintf(
            "[%s] SLOW QUERY (%dms) - %s\nCaller: %s\nQuery: %s\n%s\n",
            $timestamp,
            round($execution_ms),
            $context['service'] ?? 'Unknown',
            $caller,
            $this->sanitize_query($query),
            str_repeat('-', 80)
        );

        file_put_contents($this->log_file, $log_line, FILE_APPEND);
    }

    /**
     * 清理查詢字串（移除多餘空白，限制長度）
     *
     * @param string $query SQL 查詢
     * @return string 清理後的查詢
     */
    private function sanitize_query(string $query): string
    {
        // 移除多餘空白
        $query = preg_replace('/\s+/', ' ', $query);
        $query = trim($query);

        // 限制長度（避免日誌檔案過大）
        if (strlen($query) > 500) {
            $query = substr($query, 0, 500) . '... (truncated)';
        }

        return $query;
    }

    /**
     * 取得查詢呼叫者（backtrace）
     *
     * @return string 呼叫者資訊
     */
    private function get_query_caller(): string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        // 找到第一個非 wpdb、非本類別的呼叫者
        foreach ($backtrace as $trace) {
            $file = $trace['file'] ?? '';
            $class = $trace['class'] ?? '';

            if (strpos($file, 'wp-includes/class-wpdb.php') !== false) {
                continue;
            }

            if ($class === self::class) {
                continue;
            }

            if (!empty($file)) {
                $file_name = basename($file);
                $line = $trace['line'] ?? '?';
                $function = $trace['function'] ?? '';

                return "{$file_name}:{$line} ({$function})";
            }
        }

        return 'Unknown';
    }

    /**
     * 取得慢查詢統計
     *
     * @param int $days 天數（預設 7 天）
     * @return array 統計資料
     */
    public function get_stats(int $days = 7): array
    {
        if (!$this->enabled) {
            return [
                'enabled' => false,
                'message' => '慢查詢監控未啟用'
            ];
        }

        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/buygo-logs';

        $total_slow_queries = 0;
        $slowest_query = null;
        $slowest_ms = 0;

        // 掃描最近 N 天的記錄檔
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m', strtotime("-{$i} days"));
            $log_file = "{$log_dir}/slow-queries-{$date}.log";

            if (!file_exists($log_file)) {
                continue;
            }

            $content = file_get_contents($log_file);
            preg_match_all('/SLOW QUERY \((\d+)ms\)/', $content, $matches);

            $total_slow_queries += count($matches[0]);

            // 找出最慢的查詢
            foreach ($matches[1] as $ms) {
                if ($ms > $slowest_ms) {
                    $slowest_ms = $ms;
                }
            }
        }

        return [
            'enabled' => true,
            'threshold_ms' => $this->threshold_ms,
            'total_slow_queries' => $total_slow_queries,
            'slowest_ms' => $slowest_ms,
            'period_days' => $days,
            'log_file' => $this->log_file
        ];
    }

    /**
     * 清除舊的記錄檔（保留最近 N 個月）
     *
     * @param int $keep_months 保留月數（預設 3 個月）
     * @return int 刪除檔案數量
     */
    public function cleanup_old_logs(int $keep_months = 3): int
    {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/buygo-logs';

        if (!file_exists($log_dir)) {
            return 0;
        }

        $cutoff_date = strtotime("-{$keep_months} months");
        $deleted_count = 0;

        $files = glob("{$log_dir}/slow-queries-*.log");

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_date) {
                unlink($file);
                $deleted_count++;
            }
        }

        return $deleted_count;
    }
}
