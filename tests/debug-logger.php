<?php
/**
 * 快速測試 WebhookLogger
 */

// 載入 WordPress
require_once dirname(__DIR__, 4) . '/wp-load.php';

echo "<h2>WebhookLogger 診斷</h2>";

// 1. 檢查資料表是否存在
global $wpdb;
$table_name = $wpdb->prefix . 'buygo_webhook_logs';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

echo "<p><strong>1. 資料表檢查：</strong> ";
if ($table_exists) {
    echo "✅ 資料表存在：$table_name</p>";
} else {
    echo "❌ 資料表不存在：$table_name</p>";
    echo "<p>嘗試建立資料表...</p>";
    \BuyGoPlus\Services\WebhookLogger::create_table();
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    if ($table_exists) {
        echo "<p>✅ 資料表建立成功！</p>";
    } else {
        echo "<p>❌ 資料表建立失敗！</p>";
        echo "<p>錯誤：" . $wpdb->last_error . "</p>";
    }
}

// 2. 測試寫入
echo "<p><strong>2. 測試寫入：</strong></p>";
$logger = \BuyGoPlus\Services\WebhookLogger::get_instance();
$result = $logger->log('test_event', ['message' => 'Test at ' . date('Y-m-d H:i:s')], null, null);

if ($result) {
    echo "<p>✅ 寫入成功！Log ID: $result</p>";
} else {
    echo "<p>❌ 寫入失敗！</p>";
    echo "<p>錯誤：" . $wpdb->last_error . "</p>";
}

// 3. 讀取資料
echo "<p><strong>3. 讀取資料：</strong></p>";
$logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 5");
if ($logs) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Event Type</th><th>Created At</th></tr>";
    foreach ($logs as $log) {
        echo "<tr>";
        echo "<td>{$log->id}</td>";
        echo "<td>{$log->event_type}</td>";
        echo "<td>{$log->created_at}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>沒有資料</p>";
}
