<?php

namespace BuyGoPlus\Services;

/**
 * Dashboard Service - 儀表板統計服務
 *
 * 封裝儀表板統計查詢邏輯，為 API 層提供乾淨的資料介面
 *
 * @package BuyGoPlus\Services
 * @version 1.0.0
 */
class DashboardService
{
    private $wpdb;
    private $debugService;
    private $table_orders;
    private $table_customers;
    private $table_order_items;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->debugService = DebugService::get_instance();
        $this->table_orders = $wpdb->prefix . 'fct_orders';
        $this->table_customers = $wpdb->prefix . 'fct_customers';
        $this->table_order_items = $wpdb->prefix . 'fct_order_items';
    }

    /**
     * 計算儀表板統計數據（本月總覽）
     *
     * @return array 統計數據陣列
     */
    public function calculateStats(): array
    {
        $this->debugService->log('DashboardService', '開始計算儀表板統計', []);

        try {
            $current_month_start = date('Y-m-01 00:00:00');
            $last_month_start = date('Y-m-01 00:00:00', strtotime('-1 month'));
            $last_month_end = date('Y-m-t 23:59:59', strtotime('-1 month'));

            // 本月統計
            $current_stats = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT
                    COUNT(*) as order_count,
                    COALESCE(SUM(total_amount), 0) as total_revenue,
                    COUNT(DISTINCT customer_id) as customer_count
                 FROM {$this->table_orders}
                 WHERE created_at >= %s
                     AND payment_status = 'paid'
                     AND mode = 'live'",
                $current_month_start
            ), ARRAY_A);

            // 上月統計 (用於計算變化百分比)
            $last_stats = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT
                    COUNT(*) as order_count,
                    COALESCE(SUM(total_amount), 0) as total_revenue,
                    COUNT(DISTINCT customer_id) as customer_count
                 FROM {$this->table_orders}
                 WHERE created_at BETWEEN %s AND %s
                     AND payment_status = 'paid'
                     AND mode = 'live'",
                $last_month_start,
                $last_month_end
            ), ARRAY_A);

            // 計算變化百分比
            $revenue_change = $this->calculateChangePercent(
                $current_stats['total_revenue'],
                $last_stats['total_revenue']
            );

            $order_change = $this->calculateChangePercent(
                $current_stats['order_count'],
                $last_stats['order_count']
            );

            $customer_change = $this->calculateChangePercent(
                $current_stats['customer_count'],
                $last_stats['customer_count']
            );

            // 平均訂單價值
            $avg_order_value = $current_stats['order_count'] > 0
                ? round($current_stats['total_revenue'] / $current_stats['order_count'])
                : 0;

            $last_avg_order_value = $last_stats['order_count'] > 0
                ? round($last_stats['total_revenue'] / $last_stats['order_count'])
                : 0;

            $avg_change = $this->calculateChangePercent($avg_order_value, $last_avg_order_value);

            $this->debugService->log('DashboardService', '統計計算完成', [
                'current_revenue' => $current_stats['total_revenue'],
                'current_orders' => $current_stats['order_count']
            ]);

            return [
                'total_revenue' => [
                    'value' => (int)$current_stats['total_revenue'],
                    'currency' => 'TWD',
                    'change_percent' => $revenue_change,
                    'period' => '本月'
                ],
                'total_orders' => [
                    'value' => (int)$current_stats['order_count'],
                    'change_percent' => $order_change,
                    'period' => '本月'
                ],
                'total_customers' => [
                    'value' => (int)$current_stats['customer_count'],
                    'change_percent' => $customer_change,
                    'period' => '本月'
                ],
                'avg_order_value' => [
                    'value' => $avg_order_value,
                    'currency' => 'TWD',
                    'change_percent' => $avg_change,
                    'period' => '本月'
                ]
            ];

        } catch (\Exception $e) {
            $this->debugService->log('DashboardService', '統計計算失敗', [
                'error' => $e->getMessage()
            ], 'error');

            throw new \Exception('無法計算儀表板統計：' . $e->getMessage());
        }
    }

    /**
     * 計算變化百分比
     *
     * @param int $current 當前值
     * @param int $previous 前期值
     * @return float 變化百分比 (正數=成長,負數=下降)
     */
    private function calculateChangePercent($current, $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
