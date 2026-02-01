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
     * 計算儀表板統計數據（最近 30 天）
     *
     * 不做幣別篩選，回傳各幣別的分組統計，由前端做匯率換算
     *
     * @return array 統計數據陣列（含各幣別分組）
     */
    public function calculateStats(): array
    {
        $this->debugService->log('DashboardService', '開始計算儀表板統計（全幣別）', []);

        try {
            // 最近 30 天 (今天往前推 30 天)
            $current_period_start = date('Y-m-d 00:00:00', strtotime('-30 days'));
            // 前 30 天 (31-60 天前)
            $last_period_start = date('Y-m-d 00:00:00', strtotime('-60 days'));
            $last_period_end = date('Y-m-d 23:59:59', strtotime('-31 days'));

            // 最近 30 天統計（按幣別分組）
            $current_query = $this->wpdb->prepare(
                "SELECT
                    currency,
                    COUNT(*) as order_count,
                    COALESCE(SUM(total_amount), 0) as total_revenue,
                    COUNT(DISTINCT customer_id) as customer_count
                 FROM {$this->table_orders}
                 WHERE created_at >= %s
                     AND mode = 'live'
                 GROUP BY currency",
                $current_period_start
            );
            $current_results = $this->executeResultsWithMonitoring($current_query, 'calculateStats:current');

            // 前 30 天統計（按幣別分組）
            $last_query = $this->wpdb->prepare(
                "SELECT
                    currency,
                    COUNT(*) as order_count,
                    COALESCE(SUM(total_amount), 0) as total_revenue,
                    COUNT(DISTINCT customer_id) as customer_count
                 FROM {$this->table_orders}
                 WHERE created_at BETWEEN %s AND %s
                     AND mode = 'live'
                 GROUP BY currency",
                $last_period_start,
                $last_period_end
            );
            $last_results = $this->executeResultsWithMonitoring($last_query, 'calculateStats:last');

            // 整理成以幣別為 key 的陣列
            $current_by_currency = [];
            foreach ($current_results as $row) {
                $current_by_currency[$row['currency']] = [
                    'order_count' => (int)$row['order_count'],
                    'total_revenue' => (int)$row['total_revenue'],
                    'customer_count' => (int)$row['customer_count']
                ];
            }

            $last_by_currency = [];
            foreach ($last_results as $row) {
                $last_by_currency[$row['currency']] = [
                    'order_count' => (int)$row['order_count'],
                    'total_revenue' => (int)$row['total_revenue'],
                    'customer_count' => (int)$row['customer_count']
                ];
            }

            // 計算總計（不分幣別）
            $total_orders = 0;
            $total_customers = 0;
            foreach ($current_by_currency as $stats) {
                $total_orders += $stats['order_count'];
                $total_customers += $stats['customer_count'];
            }

            $last_total_orders = 0;
            $last_total_customers = 0;
            foreach ($last_by_currency as $stats) {
                $last_total_orders += $stats['order_count'];
                $last_total_customers += $stats['customer_count'];
            }

            $this->debugService->log('DashboardService', '統計計算完成（全幣別）', [
                'currencies' => array_keys($current_by_currency),
                'total_orders' => $total_orders
            ]);

            return [
                // 各幣別的原始數據（供前端做匯率換算）
                'by_currency' => [
                    'current' => $current_by_currency,
                    'last' => $last_by_currency
                ],
                // 訂單數和客戶數（不需要幣別換算）
                'total_orders' => [
                    'value' => $total_orders,
                    'change_percent' => $this->calculateChangePercent($total_orders, $last_total_orders),
                    'period' => '最近 30 天'
                ],
                'total_customers' => [
                    'value' => $total_customers,
                    'change_percent' => $this->calculateChangePercent($total_customers, $last_total_customers),
                    'period' => '最近 30 天'
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
     * 不做幣別篩選，回傳各幣別的每日營收，由前端做匯率換算
     *
     * @param int $days 天數 (預設 30，支援 7, 30, 90)
     * @return array 各幣別的每日營收資料
     */
    public function getRevenueTrend(int $days = 30): array
    {
        $this->debugService->log('DashboardService', '取得營收趨勢（全幣別）', [
            'days' => $days
        ]);

        try {
            $start_date = date('Y-m-d 00:00:00', strtotime("-{$days} days"));

            // 按日期和幣別分組查詢
            $query = $this->wpdb->prepare(
                "SELECT
                    DATE(created_at) as date,
                    currency,
                    COALESCE(SUM(total_amount), 0) as daily_revenue
                 FROM {$this->table_orders}
                 WHERE created_at >= %s
                     AND mode = 'live'
                 GROUP BY DATE(created_at), currency
                 ORDER BY date ASC",
                $start_date
            );
            $results = $this->executeResultsWithMonitoring($query, 'getRevenueTrend');

            // 建立日期和幣別的映射: { '2026-02-01': { 'JPY': 100000, 'TWD': 50000 } }
            $revenue_map = [];
            foreach ($results as $row) {
                if (!isset($revenue_map[$row['date']])) {
                    $revenue_map[$row['date']] = [];
                }
                $revenue_map[$row['date']][$row['currency']] = (int)$row['daily_revenue'];
            }

            // 建立日期標籤和各幣別的每日資料
            $labels = [];
            $data_by_currency = [];

            for ($i = $days - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $labels[] = date('m/d', strtotime($date));

                // 該日期各幣別的營收
                $daily_data = $revenue_map[$date] ?? [];
                foreach ($daily_data as $currency => $amount) {
                    if (!isset($data_by_currency[$currency])) {
                        // 初始化該幣別的陣列（填入 0）
                        $data_by_currency[$currency] = array_fill(0, $days, 0);
                    }
                    // 計算陣列索引（從第一天開始）
                    $index = $days - 1 - $i;
                    $data_by_currency[$currency][$index] = $amount;
                }
            }

            $this->debugService->log('DashboardService', '營收趨勢查詢完成', [
                'data_points' => count($labels),
                'currencies' => array_keys($data_by_currency)
            ]);

            return [
                'labels' => $labels,
                'by_currency' => $data_by_currency  // { 'JPY': [100, 200, ...], 'TWD': [50, 60, ...] }
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
     * 包含訂單的原始幣別，供前端做匯率換算
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

            // 查詢最近的訂單（從訂單地址表取得收件人真實姓名，含幣別）
            $query = $this->wpdb->prepare(
                "SELECT
                    o.id,
                    o.total_amount,
                    o.currency,
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

                // 金額保持「分」單位，讓前端統一處理
                $activities[] = [
                    'type' => 'order',
                    'order_id' => '#' . $row['id'],
                    'customer_name' => $customer_name,
                    'amount' => (int)$row['total_amount'],  // 「分」單位
                    'currency' => $row['currency'] ?? 'JPY',  // 原始幣別
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
