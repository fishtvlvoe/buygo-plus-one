<?php
/**
 * 檢查最近訂單狀態
 *
 * 訪問: https://test.buygo.me/wp-content/plugins/buygo-plus-one/check-orders.php
 */

require_once '../../../../wp-load.php';

global $wpdb;
$table_orders = $wpdb->prefix . 'fct_orders';

echo "<h1>訂單狀態檢查</h1>";
echo "<style>
    body { font-family: -apple-system, sans-serif; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f0f0f0; }
    .paid { color: green; font-weight: bold; }
    .not-paid { color: red; font-weight: bold; }
    .test-mode { color: orange; }
    .live-mode { color: blue; }
</style>";

// 查詢最近 10 筆訂單
$orders = $wpdb->get_results(
    "SELECT
        id,
        customer_id,
        total_amount,
        payment_status,
        mode,
        currency,
        created_at
     FROM {$table_orders}
     ORDER BY created_at DESC
     LIMIT 15",
    ARRAY_A
);

echo "<h2>最近 15 筆訂單</h2>";
echo "<table>";
echo "<tr>
    <th>訂單 ID</th>
    <th>金額</th>
    <th>付款狀態</th>
    <th>模式</th>
    <th>幣別</th>
    <th>建立時間</th>
    <th>會被統計？</th>
</tr>";

$counted = 0;
$not_counted = 0;

foreach ($orders as $order) {
    $amount = round($order['total_amount'] / 100, 0);
    $is_paid = $order['payment_status'] === 'paid';
    $is_live = $order['mode'] === 'live';
    $will_count = $is_paid && $is_live;

    if ($will_count) {
        $counted++;
    } else {
        $not_counted++;
    }

    $status_class = $is_paid ? 'paid' : 'not-paid';
    $mode_class = $is_live ? 'live-mode' : 'test-mode';
    $count_text = $will_count ? '✓ 會' : '✗ 不會';

    echo "<tr>";
    echo "<td>#{$order['id']}</td>";
    echo "<td>NT$ {$amount}</td>";
    echo "<td class='{$status_class}'>{$order['payment_status']}</td>";
    echo "<td class='{$mode_class}'>{$order['mode']}</td>";
    echo "<td>{$order['currency']}</td>";
    echo "<td>{$order['created_at']}</td>";
    echo "<td>{$count_text}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>統計摘要</h2>";
echo "<ul>";
echo "<li>會被統計（paid + live）: <strong>{$counted}</strong> 筆</li>";
echo "<li>不會被統計: <strong>{$not_counted}</strong> 筆</li>";
echo "</ul>";

// 檢查本月統計
$current_month_start = date('Y-m-01 00:00:00');
$current_stats = $wpdb->get_row($wpdb->prepare(
    "SELECT
        COUNT(*) as order_count,
        COALESCE(SUM(total_amount), 0) as total_revenue
     FROM {$table_orders}
     WHERE created_at >= %s
         AND payment_status = 'paid'
         AND mode = 'live'",
    $current_month_start
), ARRAY_A);

echo "<h2>本月統計（{$current_month_start} 起）</h2>";
echo "<ul>";
echo "<li>符合條件的訂單數: <strong>{$current_stats['order_count']}</strong></li>";
echo "<li>總營收: <strong>NT$ " . round($current_stats['total_revenue'] / 100, 0) . "</strong></li>";
echo "</ul>";

if ($current_stats['order_count'] == 0) {
    echo "<hr>";
    echo "<h2>⚠️ 問題診斷</h2>";
    echo "<p style='color: red; font-weight: bold;'>本月沒有符合條件（paid + live）的訂單！</p>";
    echo "<p>可能原因：</p>";
    echo "<ol>";
    echo "<li>測試訂單的 <code>payment_status</code> 不是 <code>'paid'</code></li>";
    echo "<li>測試訂單的 <code>mode</code> 不是 <code>'live'</code></li>";
    echo "<li>訂單建立時間不在本月</li>";
    echo "</ol>";
}
