<?php
/**
 * Debug Notification 問題
 * 檢查為什麼沒收到出貨通知
 */

// 載入 WordPress
require_once('/Users/fishtv/Local Sites/buygo/app/public/wp-load.php');

echo "=== 出貨通知 Debug 報告 ===\n\n";

// 1. 檢查 NotificationHandler 是否有註冊
echo "1. 檢查 NotificationHandler 註冊狀態\n";
global $wp_filter;
if (isset($wp_filter['buygo/shipment/marked_as_shipped'])) {
    echo "   ✅ Hook 'buygo/shipment/marked_as_shipped' 已註冊\n";
    echo "   監聽者數量: " . count($wp_filter['buygo/shipment/marked_as_shipped']->callbacks) . "\n";
} else {
    echo "   ❌ Hook 'buygo/shipment/marked_as_shipped' 未註冊！\n";
}
echo "\n";

// 2. 檢查最近的 Debug Log
echo "2. 最近的 Debug Log（NotificationHandler）\n";
global $wpdb;
$logs = $wpdb->get_results("
    SELECT * FROM {$wpdb->prefix}buygo_debug_logs 
    WHERE component = 'NotificationHandler'
    ORDER BY created_at DESC 
    LIMIT 10
");

if ($logs) {
    foreach ($logs as $log) {
        echo "   [{$log->created_at}] {$log->level}: {$log->message}\n";
        if ($log->context) {
            $context = json_decode($log->context, true);
            echo "   Context: " . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
        echo "\n";
    }
} else {
    echo "   ⚠️ 沒有 NotificationHandler 的 Debug Log\n\n";
}

// 3. 檢查最近標記出貨的記錄
echo "3. 最近標記出貨的記錄（ShipmentService）\n";
$shipment_logs = $wpdb->get_results("
    SELECT * FROM {$wpdb->prefix}buygo_debug_logs 
    WHERE component = 'ShipmentService' AND message LIKE '%標記%出貨%'
    ORDER BY created_at DESC 
    LIMIT 5
");

if ($shipment_logs) {
    foreach ($shipment_logs as $log) {
        echo "   [{$log->created_at}] {$log->message}\n";
        if ($log->context) {
            $context = json_decode($log->context, true);
            echo "   Context: " . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
        echo "\n";
    }
} else {
    echo "   ⚠️ 沒有標記出貨的 Log\n\n";
}

// 4. 檢查最近出貨的訂單狀態
echo "4. 最近出貨的訂單\n";
$shipments = $wpdb->get_results("
    SELECT id, shipment_number, customer_id, status, shipped_at, created_at
    FROM {$wpdb->prefix}buygo_shipments
    WHERE status = 'shipped'
    ORDER BY shipped_at DESC
    LIMIT 5
");

if ($shipments) {
    foreach ($shipments as $shipment) {
        echo "   出貨單 #{$shipment->shipment_number}\n";
        echo "     ID: {$shipment->id}\n";
        echo "     客戶 ID: {$shipment->customer_id}\n";
        echo "     狀態: {$shipment->status}\n";
        echo "     出貨時間: {$shipment->shipped_at}\n";
        
        // 檢查客戶是否有 LINE 綁定
        $line_binding = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}buygo_identities
            WHERE wp_user_id = %d AND provider = 'line'
        ", $shipment->customer_id));
        
        if ($line_binding) {
            echo "     ✅ 客戶已綁定 LINE: {$line_binding->provider_user_id}\n";
        } else {
            echo "     ❌ 客戶未綁定 LINE\n";
        }
        echo "\n";
    }
} else {
    echo "   ⚠️ 沒有已出貨的訂單\n\n";
}

// 5. 檢查 buygo-line-notify 是否啟用
echo "5. 檢查 buygo-line-notify 外掛\n";
if (class_exists('\\BuygoLineNotify\\Services\\MessagingService')) {
    echo "   ✅ buygo-line-notify 已啟用\n";
} else {
    echo "   ❌ buygo-line-notify 未啟用或未載入\n";
}
echo "\n";

echo "=== Debug 報告結束 ===\n";
