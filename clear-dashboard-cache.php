<?php
/**
 * 清除 Dashboard 快取
 *
 * 在瀏覽器中訪問此檔案: https://test.buygo.me/wp-content/plugins/buygo-plus-one/clear-dashboard-cache.php
 */

// 載入 WordPress
require_once '../../../../wp-load.php';

// 清除所有 Dashboard 快取
$cache_keys = [
    'buygo_dashboard_stats',
    'buygo_dashboard_stats_time',
    'buygo_dashboard_products',
    'buygo_dashboard_activities_10',
    'buygo_dashboard_revenue_30_TWD',
    'buygo_dashboard_revenue_7_TWD',
    'buygo_dashboard_revenue_90_TWD'
];

echo "<h1>清除 Dashboard 快取</h1>";
echo "<pre>";

$cleared = 0;
foreach ($cache_keys as $key) {
    $result = delete_transient($key);
    if ($result) {
        echo "✓ 已清除: {$key}\n";
        $cleared++;
    } else {
        echo "✗ 不存在或已清除: {$key}\n";
    }
}

echo "\n總共清除 {$cleared} 個快取\n";
echo "</pre>";

echo "<hr>";
echo "<h2>現在測試 Dashboard API</h2>";
echo "<p>請刷新儀表板頁面，或訪問以下 API：</p>";
echo "<ul>";
echo "<li><a href='/wp-json/buygo-plus-one/v1/dashboard/stats' target='_blank'>/wp-json/buygo-plus-one/v1/dashboard/stats</a></li>";
echo "<li><a href='/wp-json/buygo-plus-one/v1/dashboard/activities' target='_blank'>/wp-json/buygo-plus-one/v1/dashboard/activities</a></li>";
echo "<li><a href='/wp-json/buygo-plus-one/v1/dashboard/revenue?period=7' target='_blank'>/wp-json/buygo-plus-one/v1/dashboard/revenue?period=7</a></li>";
echo "</ul>";
