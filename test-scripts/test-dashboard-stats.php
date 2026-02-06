<?php
/**
 * 測試 Dashboard 統計數據修復
 *
 * 驗證項目:
 * 1. 最近 30 天的訂單是否正確查詢
 * 2. 不篩選 payment_status
 * 3. 幣別篩選是否正確
 */

require_once('/Users/fishtv/Local Sites/buygo/app/public/wp-load.php');

global $wpdb;

echo "=== Dashboard 統計測試 ===\n\n";

// 測試參數
$currency = 'JPY';  // 或 'TWD'
$current_period_start = date('Y-m-d 00:00:00', strtotime('-30 days'));

echo "測試幣別: {$currency}\n";
echo "計算區間: {$current_period_start} ~ 現在\n\n";

// 1. 查詢最近 30 天的所有訂單
$table_orders = $wpdb->prefix . 'fct_orders';

$query = $wpdb->prepare(
    "SELECT
        COUNT(*) as order_count,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COUNT(DISTINCT customer_id) as customer_count,
        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
        SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_orders
     FROM {$table_orders}
     WHERE created_at >= %s
         AND currency = %s
         AND mode = 'live'",
    $current_period_start,
    $currency
);

$result = $wpdb->get_row($query, ARRAY_A);

echo "【統計結果】\n";
echo "總訂單數: {$result['order_count']}\n";
echo "總營收 (分): {$result['total_revenue']}\n";
echo "總營收 (元): " . round($result['total_revenue'] / 100) . "\n";
echo "客戶數: {$result['customer_count']}\n";
echo "已付款訂單: {$result['paid_orders']}\n";
echo "待付款訂單: {$result['pending_orders']}\n\n";

// 2. 查詢最近的訂單明細
$recent_orders_query = $wpdb->prepare(
    "SELECT id, invoice_no, total_amount, payment_status, created_at
     FROM {$table_orders}
     WHERE created_at >= %s
         AND currency = %s
         AND mode = 'live'
     ORDER BY created_at DESC
     LIMIT 10",
    $current_period_start,
    $currency
);

$recent_orders = $wpdb->get_results($recent_orders_query, ARRAY_A);

echo "【最近訂單】\n";
foreach ($recent_orders as $order) {
    $amount_yuan = round($order['total_amount'] / 100);
    echo "#{$order['id']} | {$order['invoice_no']} | {$currency} {$amount_yuan} | {$order['payment_status']} | {$order['created_at']}\n";
}

echo "\n=== 測試完成 ===\n";
