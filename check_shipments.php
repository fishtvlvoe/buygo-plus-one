<?php
require_once('/Users/fishtv/Local Sites/buygo/app/public/wp-load.php');

global $wpdb;
$table = $wpdb->prefix . 'buygo_shipments';

echo "檢查出貨單資料表...\n";
echo "資料表: $table\n\n";

// 檢查資料表是否存在
$exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
if (!$exists) {
    echo "❌ 資料表不存在!\n";
    exit(1);
}

echo "✅ 資料表存在\n\n";

// 顯示資料表結構
echo "資料表結構:\n";
$columns = $wpdb->get_results("DESCRIBE $table", ARRAY_A);
foreach ($columns as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

// 計算總筆數
$total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
echo "\n總筆數: $total\n\n";

// 顯示前 5 筆
echo "前 5 筆資料:\n";
$rows = $wpdb->get_results("SELECT * FROM $table LIMIT 5", ARRAY_A);
foreach ($rows as $row) {
    echo "\nID: {$row['id']}\n";
    echo "  shipment_number: {$row['shipment_number']}\n";
    echo "  customer_id: {$row['customer_id']}\n";
    echo "  seller_id: {$row['seller_id']}\n";
    echo "  status: {$row['status']}\n";
    echo "  tracking_number: {$row['tracking_number']}\n";
    echo "  created_at: {$row['created_at']}\n";
}

// 測試搜尋 SH-20260123-002
echo "\n\n測試搜尋 'SH-20260123-002':\n";
$search_term = '%SH-20260123-002%';
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM $table WHERE shipment_number LIKE %s",
        $search_term
    ),
    ARRAY_A
);

echo "找到 " . count($results) . " 筆\n";
foreach ($results as $row) {
    echo "\nID: {$row['id']}\n";
    echo "  shipment_number: {$row['shipment_number']}\n";
    echo "  seller_id: {$row['seller_id']}\n";
}
