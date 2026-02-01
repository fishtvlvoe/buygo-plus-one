<?php
/**
 * 檢查訂單地址資料
 *
 * 用途：檢查父訂單和子訂單的地址資料是否正確
 */

require_once('/Users/fishtv/Local Sites/buygo/app/public/wp-load.php');

global $wpdb;

$parent_order_id = 274;
$child_order_id = 278;

echo "=== 檢查訂單地址資料 ===\n\n";

// 檢查父訂單地址
$parent_addresses = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}fct_order_addresses WHERE order_id = %d",
    $parent_order_id
), ARRAY_A);

echo "父訂單 #{$parent_order_id} 的地址資料：\n";
if (empty($parent_addresses)) {
    echo "❌ 沒有地址資料\n\n";
} else {
    echo "✅ 找到 " . count($parent_addresses) . " 筆地址資料\n";
    foreach ($parent_addresses as $addr) {
        echo "  - ID: {$addr['id']}, Type: {$addr['type']}, Name: {$addr['name']}\n";
        echo "    Address: {$addr['address_1']}\n";
    }
    echo "\n";
}

// 檢查子訂單地址
$child_addresses = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}fct_order_addresses WHERE order_id = %d",
    $child_order_id
), ARRAY_A);

echo "子訂單 #{$child_order_id} 的地址資料：\n";
if (empty($child_addresses)) {
    echo "❌ 沒有地址資料\n\n";
} else {
    echo "✅ 找到 " . count($child_addresses) . " 筆地址資料\n";
    foreach ($child_addresses as $addr) {
        echo "  - ID: {$addr['id']}, Type: {$addr['type']}, Name: {$addr['name']}\n";
        echo "    Address: {$addr['address_1']}\n";
    }
    echo "\n";
}

// 檢查訂單資訊
$parent_order = $wpdb->get_row($wpdb->prepare(
    "SELECT id, parent_id, invoice_no, type FROM {$wpdb->prefix}fct_orders WHERE id = %d",
    $parent_order_id
), ARRAY_A);

$child_order = $wpdb->get_row($wpdb->prepare(
    "SELECT id, parent_id, invoice_no, type FROM {$wpdb->prefix}fct_orders WHERE id = %d",
    $child_order_id
), ARRAY_A);

echo "=== 訂單關聯資訊 ===\n";
echo "父訂單 #{$parent_order_id}:\n";
echo "  - invoice_no: {$parent_order['invoice_no']}\n";
echo "  - parent_id: " . ($parent_order['parent_id'] ?? 'NULL') . "\n";
echo "  - type: {$parent_order['type']}\n\n";

echo "子訂單 #{$child_order_id}:\n";
echo "  - invoice_no: {$child_order['invoice_no']}\n";
echo "  - parent_id: " . ($child_order['parent_id'] ?? 'NULL') . "\n";
echo "  - type: {$child_order['type']}\n\n";

// 檢查最近的 debug log
echo "=== 最近的地址複製 Log ===\n";
$debug_log_file = WP_CONTENT_DIR . '/debug.log';
if (file_exists($debug_log_file)) {
    $log_content = file_get_contents($debug_log_file);
    $lines = explode("\n", $log_content);
    $found = false;
    foreach (array_reverse($lines) as $line) {
        if (strpos($line, '複製父訂單地址') !== false) {
            echo $line . "\n";
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo "❌ 沒有找到地址複製的 log 記錄\n";
    }
} else {
    echo "❌ Debug log 檔案不存在\n";
}
