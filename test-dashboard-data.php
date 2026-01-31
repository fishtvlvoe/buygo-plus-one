<?php
/**
 * Dashboard 資料診斷腳本
 *
 * 檢查:
 * 1. 快取狀態
 * 2. 本月訂單統計
 * 3. 最近 7 天訂單
 */

require_once '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';

echo "=== Dashboard 資料診斷 ===\n\n";

// 1. 檢查快取
echo "【1. 快取狀態】\n";
$cache_keys = [
    'buygo_dashboard_stats',
    'buygo_dashboard_products',
    'buygo_dashboard_activities_10'
];

foreach ($cache_keys as $key) {
    $cached = get_transient($key);
    if ($cached !== false) {
        echo "  ✓ {$key}: 已快取\n";
        if ($key === 'buygo_dashboard_stats') {
            echo "     營收: " . ($cached['total_revenue']['value'] ?? 0) . "\n";
            echo "     訂單: " . ($cached['total_orders']['value'] ?? 0) . "\n";
        }
    } else {
        echo "  ✗ {$key}: 無快取\n";
    }
}

// 2. 直接查詢資料庫 - 本月訂單
echo "\n【2. 本月訂單（直接查詢）】\n";
global $wpdb;
$table_orders = $wpdb->prefix . 'fct_orders';
$current_month_start = date('Y-m-01 00:00:00');

$current_stats = $wpdb->get_row($wpdb->prepare(
    "SELECT
        COUNT(*) as order_count,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COUNT(DISTINCT customer_id) as customer_count
     FROM {$table_orders}
     WHERE created_at >= %s
         AND payment_status = 'paid'
         AND mode = 'live'",
    $current_month_start
), ARRAY_A);

echo "  本月起始: {$current_month_start}\n";
echo "  訂單數: " . $current_stats['order_count'] . "\n";
echo "  總營收: " . $current_stats['total_revenue'] . "\n";
echo "  客戶數: " . $current_stats['customer_count'] . "\n";

// 3. 最近 7 天訂單
echo "\n【3. 最近 7 天訂單】\n";
$recent_orders = $wpdb->get_results(
    "SELECT
        id,
        total_amount,
        payment_status,
        created_at,
        mode
     FROM {$table_orders}
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY created_at DESC
     LIMIT 10",
    ARRAY_A
);

if (empty($recent_orders)) {
    echo "  ✗ 沒有最近 7 天的訂單\n";
} else {
    echo "  找到 " . count($recent_orders) . " 筆訂單:\n";
    foreach ($recent_orders as $order) {
        $amount = round($order['total_amount'] / 100, 0);
        echo "    - 訂單 #{$order['id']}: {$amount} TWD, {$order['payment_status']}, {$order['created_at']}, mode={$order['mode']}\n";
    }
}

// 4. 所有訂單（不限日期）
echo "\n【4. 所有訂單統計】\n";
$all_stats = $wpdb->get_row(
    "SELECT
        COUNT(*) as total_count,
        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN mode = 'live' THEN 1 ELSE 0 END) as live_count,
        MIN(created_at) as first_order,
        MAX(created_at) as last_order
     FROM {$table_orders}",
    ARRAY_A
);

echo "  總訂單數: " . $all_stats['total_count'] . "\n";
echo "  已付款: " . $all_stats['paid_count'] . "\n";
echo "  正式環境: " . $all_stats['live_count'] . "\n";
echo "  最早訂單: " . ($all_stats['first_order'] ?? 'N/A') . "\n";
echo "  最新訂單: " . ($all_stats['last_order'] ?? 'N/A') . "\n";

// 5. 建議動作
echo "\n【5. 建議動作】\n";
if ($current_stats['order_count'] == 0) {
    echo "  ⚠️  本月沒有 paid + live 的訂單\n";
    echo "  → 請確認測試訂單的 payment_status 和 mode 欄位\n";
}

if (!empty($recent_orders)) {
    $has_unpaid = false;
    $has_test_mode = false;
    foreach ($recent_orders as $order) {
        if ($order['payment_status'] !== 'paid') {
            $has_unpaid = true;
        }
        if ($order['mode'] !== 'live') {
            $has_test_mode = true;
        }
    }

    if ($has_unpaid) {
        echo "  ⚠️  有訂單 payment_status 不是 'paid'\n";
    }
    if ($has_test_mode) {
        echo "  ⚠️  有訂單 mode 不是 'live'\n";
    }
}

echo "\n=== 診斷完成 ===\n";
