<?php

namespace BuyGoPlus\CLI;

use BuyGoPlus\Database\DashboardIndexes;

/**
 * Dashboard CLI Commands - 儀表板管理指令
 *
 * WP-CLI 指令集，用於 Dashboard 資料庫維護和效能優化
 *
 * @package BuyGoPlus\CLI
 * @version 1.0.0
 */
class DashboardCLI
{
    /**
     * 建立 Dashboard 資料庫索引
     *
     * ## EXAMPLES
     *
     *     wp buygo dashboard create-indexes
     *
     * @when after_wp_load
     */
    public function create_indexes($args, $assoc_args)
    {
        \WP_CLI::line('開始建立 Dashboard 資料庫索引...');
        \WP_CLI::line('');

        $indexes = new DashboardIndexes();
        $results = $indexes->create_indexes();

        // 顯示結果
        foreach ($results as $result) {
            $status_icon = $this->get_status_icon($result['status']);
            $color = $this->get_status_color($result['status']);

            \WP_CLI::line(
                \WP_CLI::colorize(
                    "{$status_icon} %{$color}{$result['description']}%n: {$result['message']}"
                )
            );
        }

        \WP_CLI::line('');

        // 統計結果
        $created = array_filter($results, fn($r) => $r['status'] === 'created');
        $exists = array_filter($results, fn($r) => $r['status'] === 'exists');
        $failed = array_filter($results, fn($r) => $r['status'] === 'error' || $r['status'] === 'failed');

        \WP_CLI::line('統計：');
        \WP_CLI::line('  新建: ' . count($created));
        \WP_CLI::line('  已存在: ' . count($exists));
        \WP_CLI::line('  失敗: ' . count($failed));

        if (count($failed) > 0) {
            \WP_CLI::error('部分索引建立失敗，請檢查錯誤訊息');
        } else {
            \WP_CLI::success('所有索引處理完成！');
        }
    }

    /**
     * 刪除 Dashboard 資料庫索引
     *
     * ## EXAMPLES
     *
     *     wp buygo dashboard drop-indexes
     *
     * @when after_wp_load
     */
    public function drop_indexes($args, $assoc_args)
    {
        \WP_CLI::confirm('確定要刪除所有 Dashboard 索引嗎？');

        \WP_CLI::line('開始刪除 Dashboard 資料庫索引...');
        \WP_CLI::line('');

        $indexes = new DashboardIndexes();
        $results = $indexes->drop_indexes();

        foreach ($results as $result) {
            $status_icon = $this->get_status_icon($result['status']);
            \WP_CLI::line("{$status_icon} {$result['index']}: {$result['message']}");
        }

        \WP_CLI::success('索引刪除完成！');
    }

    /**
     * 分析 Dashboard 索引使用情況
     *
     * ## EXAMPLES
     *
     *     wp buygo dashboard analyze-indexes
     *
     * @when after_wp_load
     */
    public function analyze_indexes($args, $assoc_args)
    {
        \WP_CLI::line('分析 Dashboard 索引使用情況...');
        \WP_CLI::line('');

        $indexes = new DashboardIndexes();
        $result = $indexes->analyze_indexes();

        \WP_CLI::line("資料表: {$result['table']}");
        \WP_CLI::line("索引數量: {$result['total_indexes']}");
        \WP_CLI::line('');

        if (!empty($result['indexes'])) {
            \WP_CLI::line('索引列表:');

            $table_data = [];
            foreach ($result['indexes'] as $index) {
                $table_data[] = [
                    'Key_name' => $index['Key_name'],
                    'Column_name' => $index['Column_name'],
                    'Seq_in_index' => $index['Seq_in_index'],
                    'Non_unique' => $index['Non_unique'] ? 'Yes' : 'No',
                    'Cardinality' => $index['Cardinality']
                ];
            }

            \WP_CLI\Utils\format_items('table', $table_data, ['Key_name', 'Column_name', 'Seq_in_index', 'Non_unique', 'Cardinality']);
        }

        \WP_CLI::success('分析完成！');
    }

    /**
     * 取得狀態圖示
     */
    private function get_status_icon(string $status): string
    {
        $icons = [
            'created' => '✓',
            'exists' => '→',
            'error' => '✗',
            'failed' => '✗',
            'dropped' => '✓',
            'not_exists' => '→'
        ];

        return $icons[$status] ?? '?';
    }

    /**
     * 取得狀態顏色
     */
    private function get_status_color(string $status): string
    {
        $colors = [
            'created' => 'g',  // green
            'exists' => 'y',   // yellow
            'error' => 'r',    // red
            'failed' => 'r',   // red
            'dropped' => 'g',  // green
            'not_exists' => 'y' // yellow
        ];

        return $colors[$status] ?? 'n'; // n = no color
    }
}
