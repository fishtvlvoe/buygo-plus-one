#!/usr/bin/env php
<?php
/**
 * Dashboard Indexes 建立腳本
 *
 * 直接執行以建立 Dashboard 查詢所需的資料庫索引
 *
 * 使用方式:
 *   php scripts/create-dashboard-indexes.php
 */

// 載入 WordPress
$wp_load_paths = [
    '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php',
    dirname(__DIR__) . '/../../../../wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    echo "錯誤: 找不到 WordPress 載入檔案\n";
    echo "請確認 WordPress 安裝路徑正確\n";
    exit(1);
}

// 載入索引類別
require_once dirname(__DIR__) . '/includes/database/class-dashboard-indexes.php';

use BuyGoPlus\Database\DashboardIndexes;

echo "========================================\n";
echo "  Dashboard 資料庫索引建立工具\n";
echo "========================================\n\n";

try {
    $indexes = new DashboardIndexes();

    echo "開始建立索引...\n\n";

    $results = $indexes->create_indexes();

    // 顯示結果
    foreach ($results as $result) {
        $status_icon = match($result['status']) {
            'created' => '✓',
            'exists' => '→',
            'error', 'failed' => '✗',
            default => '?'
        };

        $status_color = match($result['status']) {
            'created' => "\033[32m", // green
            'exists' => "\033[33m",  // yellow
            'error', 'failed' => "\033[31m", // red
            default => "\033[0m"     // no color
        };

        echo "{$status_icon} {$status_color}{$result['description']}\033[0m: {$result['message']}\n";
    }

    echo "\n";

    // 統計結果
    $created = array_filter($results, fn($r) => $r['status'] === 'created');
    $exists = array_filter($results, fn($r) => $r['status'] === 'exists');
    $failed = array_filter($results, fn($r) => $r['status'] === 'error' || $r['status'] === 'failed');

    echo "統計：\n";
    echo "  新建: " . count($created) . "\n";
    echo "  已存在: " . count($exists) . "\n";
    echo "  失敗: " . count($failed) . "\n\n";

    if (count($failed) > 0) {
        echo "\033[31m部分索引建立失敗，請檢查錯誤訊息\033[0m\n";
        exit(1);
    }

    echo "\033[32m✓ 所有索引處理完成！\033[0m\n\n";

    // 顯示分析結果
    echo "索引分析：\n";
    echo str_repeat('-', 40) . "\n";

    $analysis = $indexes->analyze_indexes();
    echo "資料表: {$analysis['table']}\n";
    echo "索引數量: {$analysis['total_indexes']}\n";

    exit(0);

} catch (Exception $e) {
    echo "\033[31m錯誤: {$e->getMessage()}\033[0m\n";
    exit(1);
}
