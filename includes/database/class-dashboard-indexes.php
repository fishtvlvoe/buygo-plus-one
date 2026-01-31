<?php

namespace BuyGoPlus\Database;

/**
 * Dashboard Indexes - 儀表板查詢索引優化
 *
 * 為 Dashboard 查詢建立必要的資料庫索引，提升大量資料時的查詢效能
 *
 * @package BuyGoPlus\Database
 * @version 1.0.0
 */
class DashboardIndexes
{
    private $wpdb;
    private $table_orders;
    private $table_customers;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_orders = $wpdb->prefix . 'fct_orders';
        $this->table_customers = $wpdb->prefix . 'fct_customers';
    }

    /**
     * 建立所有 Dashboard 需要的索引
     *
     * @return array 執行結果陣列
     */
    public function create_indexes(): array
    {
        $results = [];

        // 1. Orders 表：統計查詢索引（created_at + payment_status + mode）
        $results['orders_stats_idx'] = $this->create_index_if_not_exists(
            $this->table_orders,
            'idx_orders_stats',
            ['created_at', 'payment_status', 'mode'],
            '統計查詢複合索引'
        );

        // 2. Orders 表：營收趨勢索引（created_at + currency + payment_status）
        $results['orders_revenue_idx'] = $this->create_index_if_not_exists(
            $this->table_orders,
            'idx_orders_revenue',
            ['created_at', 'currency', 'payment_status'],
            '營收趨勢查詢索引'
        );

        // 3. Orders 表：最近活動索引（created_at DESC + mode）
        $results['orders_activities_idx'] = $this->create_index_if_not_exists(
            $this->table_orders,
            'idx_orders_activities',
            ['created_at', 'mode'],
            '最近活動查詢索引'
        );

        // 4. Orders 表：客戶關聯索引（customer_id）
        $results['orders_customer_idx'] = $this->create_index_if_not_exists(
            $this->table_orders,
            'idx_orders_customer',
            ['customer_id'],
            '訂單-客戶關聯索引'
        );

        return $results;
    }

    /**
     * 檢查並建立索引（如果不存在）
     *
     * @param string $table 資料表名稱
     * @param string $index_name 索引名稱
     * @param array $columns 索引欄位陣列
     * @param string $description 索引描述
     * @return array 執行結果
     */
    private function create_index_if_not_exists(
        string $table,
        string $index_name,
        array $columns,
        string $description
    ): array {
        $result = [
            'index' => $index_name,
            'description' => $description,
            'status' => 'unknown',
            'message' => ''
        ];

        try {
            // 檢查索引是否存在
            $exists = $this->index_exists($table, $index_name);

            if ($exists) {
                $result['status'] = 'exists';
                $result['message'] = '索引已存在，跳過';
                return $result;
            }

            // 建立索引
            $columns_str = implode(', ', array_map(function($col) {
                return "`{$col}`";
            }, $columns));

            $sql = "CREATE INDEX `{$index_name}` ON `{$table}` ({$columns_str})";

            $this->wpdb->query($sql);

            // 驗證索引建立成功
            if ($this->index_exists($table, $index_name)) {
                $result['status'] = 'created';
                $result['message'] = '索引建立成功';
            } else {
                $result['status'] = 'failed';
                $result['message'] = '索引建立失敗（未知原因）';
            }

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * 檢查索引是否存在
     *
     * @param string $table 資料表名稱
     * @param string $index_name 索引名稱
     * @return bool 是否存在
     */
    private function index_exists(string $table, string $index_name): bool
    {
        $query = $this->wpdb->prepare(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = %s",
            $index_name
        );

        $results = $this->wpdb->get_results($query);

        return !empty($results);
    }

    /**
     * 刪除所有 Dashboard 索引（清理用）
     *
     * @return array 執行結果陣列
     */
    public function drop_indexes(): array
    {
        $results = [];

        $indexes = [
            'idx_orders_stats',
            'idx_orders_revenue',
            'idx_orders_activities',
            'idx_orders_customer'
        ];

        foreach ($indexes as $index_name) {
            $results[$index_name] = $this->drop_index_if_exists(
                $this->table_orders,
                $index_name
            );
        }

        return $results;
    }

    /**
     * 刪除索引（如果存在）
     *
     * @param string $table 資料表名稱
     * @param string $index_name 索引名稱
     * @return array 執行結果
     */
    private function drop_index_if_exists(string $table, string $index_name): array
    {
        $result = [
            'index' => $index_name,
            'status' => 'unknown',
            'message' => ''
        ];

        try {
            $exists = $this->index_exists($table, $index_name);

            if (!$exists) {
                $result['status'] = 'not_exists';
                $result['message'] = '索引不存在，跳過';
                return $result;
            }

            $sql = "DROP INDEX `{$index_name}` ON `{$table}`";
            $this->wpdb->query($sql);

            $result['status'] = 'dropped';
            $result['message'] = '索引刪除成功';

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * 分析表格並顯示索引使用情況
     *
     * @return array 索引分析結果
     */
    public function analyze_indexes(): array
    {
        $table = $this->table_orders;

        // 顯示所有索引
        $indexes = $this->wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);

        // 分析表格統計
        $this->wpdb->query("ANALYZE TABLE `{$table}`");

        return [
            'table' => $table,
            'indexes' => $indexes,
            'total_indexes' => count($indexes)
        ];
    }
}
