<?php
namespace BuyGoPlus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Checker - 資料庫完整性檢查
 *
 * 在外掛啟動時檢查所有必要的資料表是否存在且結構正確
 */
class DatabaseChecker
{
    /**
     * 必要的資料表及其必須欄位
     */
    private static $required_tables = [
        'buygo_shipments' => [
            'id', 'shipment_number', 'customer_id', 'seller_id', 'status',
            'shipping_method', 'tracking_number', 'shipped_at', 'created_at', 'updated_at'
        ],
        'buygo_shipment_items' => [
            'id', 'shipment_id', 'order_id', 'order_item_id', 'product_id', 'quantity', 'created_at'
        ],
        'buygo_helpers' => [
            'id', 'user_id', 'seller_id', 'created_at'
        ],
        'buygo_line_bindings' => [
            'id', 'user_id', 'line_uid', 'binding_code', 'status', 'created_at'
        ],
        'buygo_debug_logs' => [
            'id', 'level', 'module', 'message', 'data', 'created_at'
        ],
        'buygo_notification_logs' => [
            'id', 'receiver', 'channel', 'status', 'content', 'created_at'
        ],
        'buygo_workflow_logs' => [
            'id', 'workflow_name', 'status', 'created_at'
        ],
        'buygo_webhook_logs' => [
            'id', 'event_type', 'event_data', 'created_at'
        ],
        'buygo_order_status_history' => [
            'id', 'order_id', 'old_status', 'new_status', 'created_at'
        ],
    ];

    /**
     * 執行完整性檢查
     *
     * @return array 檢查結果
     */
    public static function check(): array
    {
        global $wpdb;

        $results = [
            'status' => 'ok',
            'missing_tables' => [],
            'missing_columns' => [],
            'errors' => []
        ];

        foreach (self::$required_tables as $table_name => $required_columns) {
            $full_table_name = $wpdb->prefix . $table_name;

            // 檢查資料表是否存在
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_table_name}'") === $full_table_name;

            if (!$table_exists) {
                $results['missing_tables'][] = $table_name;
                $results['status'] = 'error';
                continue;
            }

            // 檢查必要欄位是否存在
            $existing_columns = $wpdb->get_col("DESCRIBE {$full_table_name}", 0);

            foreach ($required_columns as $column) {
                if (!in_array($column, $existing_columns)) {
                    $results['missing_columns'][] = "{$table_name}.{$column}";
                    $results['status'] = 'warning';
                }
            }
        }

        return $results;
    }

    /**
     * 執行檢查並自動修復
     *
     * @return array 修復結果
     */
    public static function check_and_repair(): array
    {
        $check_result = self::check();

        if ($check_result['status'] === 'ok') {
            return [
                'status' => 'ok',
                'message' => '所有資料表結構正確',
                'repairs' => []
            ];
        }

        $repairs = [];

        // 如果有缺失的資料表或欄位，執行升級
        if (!empty($check_result['missing_tables']) || !empty($check_result['missing_columns'])) {
            require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-database.php';

            // 建立缺失的資料表
            Database::create_tables();

            // 升級現有資料表結構
            Database::upgrade_tables();

            $repairs[] = '已執行資料表建立和升級';

            // 記錄修復動作
            foreach ($check_result['missing_tables'] as $table) {
                $repairs[] = "建立資料表: {$table}";
            }

            foreach ($check_result['missing_columns'] as $column) {
                $repairs[] = "添加欄位: {$column}";
            }
        }

        // 重新檢查
        $final_check = self::check();

        return [
            'status' => $final_check['status'],
            'message' => $final_check['status'] === 'ok' ? '修復完成' : '仍有問題需要手動處理',
            'repairs' => $repairs,
            'remaining_issues' => $final_check
        ];
    }

    /**
     * 取得檢查報告（用於診斷頁面）
     */
    public static function get_report(): string
    {
        $result = self::check();

        $html = '<div class="buygo-db-check-report">';
        $html .= '<h3>資料庫完整性檢查</h3>';

        if ($result['status'] === 'ok') {
            $html .= '<p style="color: green;">✅ 所有資料表結構正確</p>';
        } else {
            if (!empty($result['missing_tables'])) {
                $html .= '<p style="color: red;">❌ 缺失的資料表:</p><ul>';
                foreach ($result['missing_tables'] as $table) {
                    $html .= "<li>{$table}</li>";
                }
                $html .= '</ul>';
            }

            if (!empty($result['missing_columns'])) {
                $html .= '<p style="color: orange;">⚠️ 缺失的欄位:</p><ul>';
                foreach ($result['missing_columns'] as $column) {
                    $html .= "<li>{$column}</li>";
                }
                $html .= '</ul>';
            }
        }

        $html .= '</div>';

        return $html;
    }
}
