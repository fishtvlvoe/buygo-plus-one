<?php

namespace BuyGoPlus\Services;

use BuyGoPlus\Monitoring\SlowQueryMonitor;

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
    private $slowQueryMonitor;
    private $table_orders;
    private $table_customers;
    private $table_order_items;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->debugService = DebugService::get_instance();
        $this->slowQueryMonitor = new SlowQueryMonitor(500); // 500ms 閾值
        $this->table_orders = $wpdb->prefix . 'fct_orders';
        $this->table_customers = $wpdb->prefix . 'fct_customers';
        $this->table_order_items = $wpdb->prefix . 'fct_order_items';
    }

    /**
     * 計算儀表板統計數據（本月總覽）
     *
     * @param string $currency 幣別 (預設 JPY)
     * @return array 統計數據陣列
     */
    public function calculateStats(string $currency = 'JPY'): array
    {
        $this->debugService->log('DashboardService', '開始計算儀表板統計', [
            'currency' => $currency
        ]);

        try {
            $current_month_start = date('Y-m-01 00:00:00');
            $last_month_start = date('Y-m-01 00:00:00', strtotime('-1 month'));
            $last_month_end = date('Y-m-t 23:59:59', strtotime('-1 month'));

            // 本月統計（使用慢查詢監控，按幣別篩選）
            $current_query = $this->wpdb->prepare(
                "SELECT
                    COUNT(*) as order_count,
                    COALESCE(SUM(total_amount), 0) as total_revenue,
                    COUNT(DISTINCT customer_id) as customer_count
                 FROM {$this->table_orders}
                 WHERE created_at >= %s
                     AND payment_status = 'paid'
                     AND currency = %s
                     AND mode = 'live'",
                $current_month_start,
                $currency
            );
            $current_stats = $this->executeWithMonitoring($current_query, 'calculateStats:current');

            // 上月統計（使用慢查詢監控，按幣別篩選）
            $last_query = $this->wpdb->prepare(
                "SELECT
                    COUNT(*) as order_count,
                    COALESCE(SUM(total_amount), 0) as total_revenue,
                    COUNT(DISTINCT customer_id) as customer_count
                 FROM {$this->table_orders}
                 WHERE created_at BETWEEN %s AND %s
                     AND payment_status = 'paid'
                     AND currency = %s
                     AND mode = 'live'",
                $last_month_start,
                $last_month_end,
                $currency
            );
            $last_stats = $this->executeWithMonitoring($last_query, 'calculateStats:last');

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
                    'currency' => $currency,
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
                    'currency' => $currency,
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
     * 取得營收趨勢資料（過去 N 天）
     *
     * @param int $days 天數 (預設 30，支援 7, 30, 90)
     * @param string $currency 幣別 (預設 TWD，支援 USD, CNY)
     * @return array Chart.js 格式的資料
     */
    public function getRevenueTrend(int $days = 30, string $currency = 'TWD'): array
    {
        $this->debugService->log('DashboardService', '取得營收趨勢', [
            'days' => $days,
            'currency' => $currency
        ]);

        try {
            $start_date = date('Y-m-d 00:00:00', strtotime("-{$days} days"));

            $query = $this->wpdb->prepare(
                "SELECT
                    DATE(created_at) as date,
                    COALESCE(SUM(total_amount), 0) as daily_revenue
                 FROM {$this->table_orders}
                 WHERE created_at >= %s
                     AND payment_status = 'paid'
                     AND currency = %s
                     AND mode = 'live'
                 GROUP BY DATE(created_at)
                 ORDER BY date ASC",
                $start_date,
                $currency
            );
            $results = $this->executeResultsWithMonitoring($query, 'getRevenueTrend');

            // 建立日期對營收的映射
            $revenue_map = [];
            foreach ($results as $row) {
                $revenue_map[$row['date']] = (int)$row['daily_revenue'];
            }

            // 填補缺失日期 (沒有訂單的日期顯示 0)
            $labels = [];
            $data = [];

            for ($i = $days - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $labels[] = date('m/d', strtotime($date));

                // 從映射中取得該日期的營收，如果不存在則為 0
                $data[] = $revenue_map[$date] ?? 0;
            }

            $this->debugService->log('DashboardService', '營收趨勢查詢完成', [
                'data_points' => count($data)
            ]);

            return [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => '營收',
                        'data' => $data,
                        'borderColor' => '#3b82f6',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                        'tension' => 0.4
                    ]
                ],
                'currency' => $currency
            ];

        } catch (\Exception $e) {
            $this->debugService->log('DashboardService', '營收趨勢查詢失敗', [
                'error' => $e->getMessage()
            ], 'error');

            throw new \Exception('無法取得營收趨勢：' . $e->getMessage());
        }
    }

    /**
     * 取得商品概覽統計
     *
     * @return array 商品統計陣列
     */
    public function getProductOverview(): array
    {
        $this->debugService->log('DashboardService', '取得商品概覽', []);

        try {
            $result = $this->wpdb->get_row(
                "SELECT
                    COUNT(*) as total_products,
                    SUM(CASE WHEN post_status = 'publish' THEN 1 ELSE 0 END) as published,
                    SUM(CASE WHEN post_status = 'draft' THEN 1 ELSE 0 END) as draft
                 FROM {$this->wpdb->posts}
                 WHERE post_type = 'fluent-products'",
                ARRAY_A
            );

            $this->debugService->log('DashboardService', '商品概覽查詢完成', [
                'total' => $result['total_products']
            ]);

            return [
                'total_products' => (int)$result['total_products'],
                'published' => (int)$result['published'],
                'draft' => (int)$result['draft']
            ];

        } catch (\Exception $e) {
            $this->debugService->log('DashboardService', '商品概覽查詢失敗', [
                'error' => $e->getMessage()
            ], 'error');

            throw new \Exception('無法取得商品概覽：' . $e->getMessage());
        }
    }

    /**
     * 取得最近活動（訂單 + 客戶註冊）
     *
     * @param int $limit 活動數量限制 (預設 10)
     * @return array 活動列表
     */
    public function getRecentActivities(int $limit = 10): array
    {
        $this->debugService->log('DashboardService', '取得最近活動', [
            'limit' => $limit
        ]);

        try {
            $table_order_addresses = $this->wpdb->prefix . 'fct_order_addresses';

            // 查詢最近的訂單（從訂單地址表取得收件人真實姓名）
            $query = $this->wpdb->prepare(
                "SELECT
                    o.id,
                    o.total_amount,
                    o.created_at,
                    oa.name as recipient_name,
                    c.email
                FROM {$this->table_orders} o
                LEFT JOIN {$table_order_addresses} oa ON o.id = oa.order_id
                LEFT JOIN {$this->table_customers} c ON o.customer_id = c.id
                WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND o.mode = 'live'
                ORDER BY o.created_at DESC
                LIMIT %d",
                $limit
            );

            $results = $this->executeResultsWithMonitoring($query, 'getRecentActivities');

            // 格式化活動資料
            $activities = [];
            $seen_orders = []; // 避免同一訂單因多個地址記錄重複
            foreach ($results as $row) {
                // 避免重複訂單
                if (isset($seen_orders[$row['id']])) {
                    continue;
                }
                $seen_orders[$row['id']] = true;

                // 優先使用訂單地址的收件人姓名
                $customer_name = trim($row['recipient_name'] ?? '');
                if (empty($customer_name)) {
                    $customer_name = $row['email'] ?? '訪客';
                }

                $amount = round($row['total_amount'] / 100, 0);

                $activities[] = [
                    'type' => 'order',
                    'order_id' => '#' . $row['id'],
                    'customer_name' => $customer_name,
                    'amount' => $amount,
                    'timestamp' => $row['created_at'],
                    'icon' => 'shopping-cart',
                    'url' => '/buygo-portal/orders/?id=' . $row['id']
                ];
            }

            $this->debugService->log('DashboardService', '最近活動查詢完成', [
                'count' => count($activities)
            ]);

            return $activities;

        } catch (\Exception $e) {
            $this->debugService->log('DashboardService', '最近活動查詢失敗', [
                'error' => $e->getMessage()
            ], 'error');

            throw new \Exception('無法取得最近活動：' . $e->getMessage());
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

    /**
     * 執行查詢並監控執行時間
     *
     * @param string $query SQL 查詢
     * @param string $method 呼叫方法名稱
     * @param string $output_type 輸出類型 (ARRAY_A, OBJECT, etc.)
     * @return mixed 查詢結果
     */
    private function executeWithMonitoring(string $query, string $method, string $output_type = ARRAY_A)
    {
        $start_time = microtime(true);

        $result = $this->wpdb->get_row($query, $output_type);

        $execution_time = microtime(true) - $start_time;

        $this->slowQueryMonitor->log_if_slow($query, $execution_time, [
            'service' => 'DashboardService',
            'method' => $method
        ]);

        return $result;
    }

    /**
     * 執行多行查詢並監控執行時間
     *
     * @param string $query SQL 查詢
     * @param string $method 呼叫方法名稱
     * @param string $output_type 輸出類型 (ARRAY_A, OBJECT, etc.)
     * @return array 查詢結果
     */
    private function executeResultsWithMonitoring(string $query, string $method, string $output_type = ARRAY_A): array
    {
        $start_time = microtime(true);

        $results = $this->wpdb->get_results($query, $output_type);

        $execution_time = microtime(true) - $start_time;

        $this->slowQueryMonitor->log_if_slow($query, $execution_time, [
            'service' => 'DashboardService',
            'method' => $method
        ]);

        return $results ?: [];
    }
}
