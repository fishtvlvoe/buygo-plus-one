<?php
/**
 * 簡化版 Debug - 不需要資料庫
 */

echo "=== 簡化 Debug 報告 ===\n\n";

// 1. 檢查檔案是否存在
echo "1. 檢查關鍵檔案\n";
$files = [
    'NotificationHandler' => 'includes/services/class-notification-handler.php',
    'NotificationService' => 'includes/services/class-notification-service.php',
    'NotificationTemplates' => 'includes/services/class-notification-templates.php',
    'ShipmentService' => 'includes/services/class-shipment-service.php',
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "   ✅ {$name}: {$path}\n";
    } else {
        echo "   ❌ {$name}: {$path} (不存在)\n";
    }
}
echo "\n";

// 2. 檢查 NotificationHandler 是否有 register_hooks
echo "2. 檢查 NotificationHandler::register_hooks\n";
$handler_content = file_get_contents('includes/services/class-notification-handler.php');
if (strpos($handler_content, 'function register_hooks') !== false) {
    echo "   ✅ register_hooks() 方法存在\n";
    if (strpos($handler_content, "add_action('buygo/shipment/marked_as_shipped'") !== false) {
        echo "   ✅ Hook 'buygo/shipment/marked_as_shipped' 已註冊\n";
    } else {
        echo "   ❌ Hook 未註冊\n";
    }
} else {
    echo "   ❌ register_hooks() 方法不存在\n";
}
echo "\n";

// 3. 檢查 Plugin 是否有呼叫 register_hooks
echo "3. 檢查 Plugin 初始化\n";
$plugin_content = file_get_contents('includes/class-plugin.php');
if (strpos($plugin_content, 'NotificationHandler::get_instance') !== false) {
    echo "   ✅ Plugin 有初始化 NotificationHandler\n";
    if (strpos($plugin_content, '->register_hooks()') !== false) {
        echo "   ✅ Plugin 有呼叫 register_hooks()\n";
    } else {
        echo "   ❌ Plugin 沒有呼叫 register_hooks()\n";
    }
} else {
    echo "   ❌ Plugin 沒有初始化 NotificationHandler\n";
}
echo "\n";

// 4. 檢查 ShipmentService 是否有觸發 Hook
echo "4. 檢查 ShipmentService::mark_shipped\n";
$shipment_content = file_get_contents('includes/services/class-shipment-service.php');
if (strpos($shipment_content, "do_action('buygo/shipment/marked_as_shipped'") !== false) {
    echo "   ✅ ShipmentService 有觸發 Hook\n";
} else {
    echo "   ❌ ShipmentService 沒有觸發 Hook\n";
}
echo "\n";

// 5. 檢查模板定義
echo "5. 檢查 NotificationTemplates\n";
$template_content = file_get_contents('includes/services/class-notification-templates.php');
if (strpos($template_content, "'shipment_shipped'") !== false) {
    echo "   ✅ shipment_shipped 模板定義存在\n";
} else {
    echo "   ❌ shipment_shipped 模板定義不存在\n";
}
echo "\n";

echo "=== Debug 報告結束 ===\n";
echo "\n如果所有都是 ✅，那問題可能在：\n";
echo "1. 買家沒綁定 LINE（檢查 wp_buygo_identities 表）\n";
echo "2. buygo-line-notify 沒啟用（檢查外掛列表）\n";
echo "3. 模板設定為空（檢查後台設定）\n";
