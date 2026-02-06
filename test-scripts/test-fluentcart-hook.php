<?php
/**
 * 測試 FluentCart Hook 整合
 *
 * 此檔案用於測試 FluentCartChildOrdersIntegration 是否正常運作
 * 可在 Test Script Manager 中執行
 */

// 檢查是否在 WordPress 環境中
if (!defined('ABSPATH')) {
    echo "❌ 錯誤：此腳本必須在 WordPress 環境中執行\n";
    exit;
}

echo "<h2>FluentCart Hook 整合測試</h2>\n";
echo "<hr>\n";

// 1. 檢查 FluentCart 是否存在
echo "<h3>1. 檢查 FluentCart</h3>\n";
if (class_exists('FluentCart\\App\\App')) {
    echo "✅ FluentCart 類別存在<br>\n";
} else {
    echo "❌ FluentCart 類別不存在<br>\n";
}

// 2. 檢查整合類別是否存在
echo "<h3>2. 檢查整合類別</h3>\n";
if (class_exists('BuygoPlus\\Integrations\\FluentCartChildOrdersIntegration')) {
    echo "✅ FluentCartChildOrdersIntegration 類別存在<br>\n";
} else {
    echo "❌ FluentCartChildOrdersIntegration 類別不存在<br>\n";
    echo "檔案路徑: " . BUYGO_PLUS_ONE_PLUGIN_DIR . "includes/integrations/class-fluentcart-child-orders-integration.php<br>\n";
    if (file_exists(BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/integrations/class-fluentcart-child-orders-integration.php')) {
        echo "✅ 檔案存在<br>\n";
    } else {
        echo "❌ 檔案不存在<br>\n";
    }
}

// 3. 檢查 Hook 是否被註冊
echo "<h3>3. 檢查 Hook 註冊</h3>\n";
global $wp_filter;
if (isset($wp_filter['fluent_cart/customer_app'])) {
    echo "✅ fluent_cart/customer_app hook 已註冊<br>\n";
    echo "<strong>已註冊的 callbacks:</strong><br>\n";
    foreach ($wp_filter['fluent_cart/customer_app']->callbacks as $priority => $callbacks) {
        echo "&nbsp;&nbsp;Priority $priority:<br>\n";
        foreach ($callbacks as $idx => $callback) {
            if (is_array($callback['function'])) {
                $class = is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0];
                $method = $callback['function'][1];
                echo "&nbsp;&nbsp;&nbsp;&nbsp;- {$class}::{$method}<br>\n";
            } else {
                echo "&nbsp;&nbsp;&nbsp;&nbsp;- {$callback['function']}<br>\n";
            }
        }
    }
} else {
    echo "❌ fluent_cart/customer_app hook 未註冊<br>\n";
}

// 4. 測試手動觸發 Hook
echo "<h3>4. 手動觸發 Hook 測試</h3>\n";
echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f5f5f5;'>\n";
ob_start();
do_action('fluent_cart/customer_app');
$output = ob_get_clean();
if (!empty($output)) {
    echo "✅ Hook 輸出內容:<br>\n";
    echo "<pre>" . htmlspecialchars($output) . "</pre>\n";
} else {
    echo "⚠️ Hook 已觸發，但沒有輸出內容<br>\n";
}
echo "</div>\n";

// 5. 檢查 JavaScript 檔案
echo "<h3>5. 檢查 JavaScript 檔案</h3>\n";
$js_path = BUYGO_PLUS_ONE_PLUGIN_DIR . 'assets/js/fluentcart-child-orders.js';
if (file_exists($js_path)) {
    echo "✅ JavaScript 檔案存在<br>\n";
    echo "路徑: {$js_path}<br>\n";
    echo "大小: " . filesize($js_path) . " bytes<br>\n";
} else {
    echo "❌ JavaScript 檔案不存在<br>\n";
    echo "預期路徑: {$js_path}<br>\n";
}

echo "<hr>\n";
echo "<h3>測試完成</h3>\n";
echo "<p>如果所有檢查都通過，但頁面仍未顯示按鈕，請嘗試：</p>\n";
echo "<ol>\n";
echo "<li>清除 WordPress 快取</li>\n";
echo "<li>清除瀏覽器快取（Cmd + Shift + R）</li>\n";
echo "<li>停用並重新啟用 BuyGo Plus One Dev 外掛</li>\n";
echo "</ol>\n";
