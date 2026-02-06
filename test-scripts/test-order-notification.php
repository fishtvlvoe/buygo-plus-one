<?php
/**
 * 測試訂單通知系統
 *
 * 使用方式：在 Test Script Manager 後台執行此程式碼
 */

// 檢查最近的訂單
global $wpdb;

echo "<h2>檢查訂單通知系統</h2>";

//1. 檢查 FluentCart 訂單
$orders = $wpdb->get_results("
    SELECT id, customer_id, total_amount, status, created_at
    FROM {$wpdb->prefix}fc_orders
    ORDER BY created_at DESC
    LIMIT 5
");

echo "<h3>1. 最近 5 筆訂單</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>客戶ID</th><th>金額</th><th>狀態</th><th>建立時間</th><th>通知狀態</th></tr>";

foreach ($orders as $order) {
    // 檢查通知狀態
    $sent_status = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->prefix}fc_order_meta
         WHERE order_id = %d AND meta_key = 'buygo_line_notify_sent_order_created'",
        $order->id
    ));

    $status_key = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->prefix}fc_order_meta
         WHERE order_id = %d AND meta_key = 'buygo_line_notify_status_order_created'",
        $order->id
    ));

    $last_error = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->prefix}fc_order_meta
         WHERE order_id = %d AND meta_key = 'buygo_line_notify_last_error_order_created'",
        $order->id
    ));

    $amount = number_format($order->total_amount / 100, 0);
    $notify_status = $sent_status ? "✓ 已發送 ({$sent_status})" : "✗ 未發送";
    if ($status_key) {
        $notify_status .= "<br><small>狀態: {$status_key}</small>";
    }
    if ($last_error) {
        $notify_status .= "<br><small style='color:red'>錯誤: {$last_error}</small>";
    }

    echo "<tr>";
    echo "<td>{$order->id}</td>";
    echo "<td>{$order->customer_id}</td>";
    echo "<td>NT$ {$amount}</td>";
    echo "<td>{$order->status}</td>";
    echo "<td>{$order->created_at}</td>";
    echo "<td>{$notify_status}</td>";
    echo "</tr>";
}
echo "</table>";

// 2. 檢查 Cron 任務
echo "<h3>2. 檢查排程任務</h3>";
$cron_hook = 'buygo_plus_one_line_notify_attempt';
$crons = _get_cron_array();
$found_crons = [];

foreach ($crons as $timestamp => $cron) {
    if (isset($cron[$cron_hook])) {
        foreach ($cron[$cron_hook] as $hash => $event) {
            $found_crons[] = [
                'timestamp' => $timestamp,
                'scheduled_time' => date('Y-m-d H:i:s', $timestamp),
                'args' => $event['args'],
            ];
        }
    }
}

if (!empty($found_crons)) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>排程時間</th><th>訂單ID</th><th>事件</th><th>嘗試次數</th></tr>";
    foreach ($found_crons as $cron) {
        echo "<tr>";
        echo "<td>{$cron['scheduled_time']}</td>";
        echo "<td>{$cron['args'][0]}</td>";
        echo "<td>{$cron['args'][1]}</td>";
        echo "<td>{$cron['args'][2]}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>⚠️ 沒有找到排程中的通知任務</p>";
}

// 3. 檢查外掛狀態
echo "<h3>3. 檢查外掛狀態</h3>";
echo "<ul>";
echo "<li>BuyGo Plus One: " . (class_exists('\BuyGoPlus\Plugin') ? "✓ 已啟用" : "✗ 未啟用") . "</li>";
echo "<li>BuyGo Line Notify: " . (class_exists('\BuygoLineNotify\BuygoLineNotify') ? "✓ 已啟用" : "✗ 未啟用") . "</li>";

if (class_exists('\BuygoLineNotify\BuygoLineNotify')) {
    $is_active = \BuygoLineNotify\BuygoLineNotify::is_active();
    echo "<li>BuyGo Line Notify Active: " . ($is_active ? "✓ 是" : "✗ 否") . "</li>";
}

echo "<li>LineOrderNotifier Class: " . (class_exists('\BuyGoPlus\Services\LineOrderNotifier') ? "✓ 存在" : "✗ 不存在") . "</li>";
echo "</ul>";

// 4. 檢查 Hook 註冊
echo "<h3>4. 檢查 Hook 註冊</h3>";
global $wp_filter;

$hooks_to_check = [
    'fluent_cart/order_created',
    'fluent_cart/shipping_status_changed_to_shipped',
    'buygo_order_shipped',
];

echo "<ul>";
foreach ($hooks_to_check as $hook) {
    if (isset($wp_filter[$hook])) {
        $count = count($wp_filter[$hook]->callbacks);
        echo "<li><strong>{$hook}</strong>: ✓ 已註冊 ({$count} 個回調)</li>";
    } else {
        echo "<li><strong>{$hook}</strong>: ✗ 未註冊</li>";
    }
}
echo "</ul>";

// 5. 測試手動觸發
echo "<h3>5. 測試資訊</h3>";
echo "<p>如需測試通知功能，請：</p>";
echo "<ol>";
echo "<li>建立一筆新訂單</li>";
echo "<li>等待 60 秒（第一次重試延遲）</li>";
echo "<li>重新執行此腳本查看結果</li>";
echo "</ol>";

echo "<hr>";
echo "<p><strong>檢查完成時間：</strong>" . date('Y-m-d H:i:s') . "</p>";
