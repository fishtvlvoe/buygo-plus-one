<?php
/**
 * 測試並修復 FluentCart Hook 整合
 *
 * 在 Test Script Manager 中執行此腳本
 */

if (!defined('ABSPATH')) {
    die('此腳本必須在 WordPress 環境中執行');
}

echo "<div style='font-family: monospace; padding: 20px;'>";
echo "<h2>🔍 FluentCart Hook 整合診斷</h2>";
echo "<hr>";

// 1. 檢查常數
echo "<h3>1️⃣ 檢查常數定義</h3>";
if (defined('BUYGO_PLUS_ONE_PLUGIN_DIR')) {
    echo "✅ BUYGO_PLUS_ONE_PLUGIN_DIR: <code>" . BUYGO_PLUS_ONE_PLUGIN_DIR . "</code><br>";
} else {
    echo "❌ BUYGO_PLUS_ONE_PLUGIN_DIR 未定義<br>";
}

if (defined('BUYGO_PLUS_ONE_PLUGIN_URL')) {
    echo "✅ BUYGO_PLUS_ONE_PLUGIN_URL: <code>" . BUYGO_PLUS_ONE_PLUGIN_URL . "</code><br>";
} else {
    echo "❌ BUYGO_PLUS_ONE_PLUGIN_URL 未定義<br>";
}

// 2. 檢查檔案
echo "<h3>2️⃣ 檢查檔案存在</h3>";
$integration_path = BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/integrations/class-fluentcart-child-orders-integration.php';
if (file_exists($integration_path)) {
    echo "✅ 整合類別檔案存在<br>";
    echo "&nbsp;&nbsp;&nbsp;路徑: <code>$integration_path</code><br>";
    echo "&nbsp;&nbsp;&nbsp;大小: " . filesize($integration_path) . " bytes<br>";
    echo "&nbsp;&nbsp;&nbsp;修改時間: " . date('Y-m-d H:i:s', filemtime($integration_path)) . "<br>";
} else {
    echo "❌ 整合類別檔案不存在: <code>$integration_path</code><br>";
}

$js_path = BUYGO_PLUS_ONE_PLUGIN_DIR . 'assets/js/fluentcart-child-orders.js';
if (file_exists($js_path)) {
    echo "✅ JavaScript 檔案存在<br>";
    echo "&nbsp;&nbsp;&nbsp;路徑: <code>$js_path</code><br>";
} else {
    echo "❌ JavaScript 檔案不存在<br>";
}

// 3. 檢查 FluentCart
echo "<h3>3️⃣ 檢查 FluentCart</h3>";
if (class_exists('FluentCart\\App\\App')) {
    echo "✅ FluentCart\\App\\App 類別存在<br>";
} else {
    echo "❌ FluentCart\\App\\App 類別不存在<br>";
    echo "&nbsp;&nbsp;&nbsp;<strong>FluentCart 可能未啟用！</strong><br>";
}

// 4. 嘗試手動載入整合類別
echo "<h3>4️⃣ 手動載入整合類別</h3>";
if (file_exists($integration_path)) {
    require_once $integration_path;

    if (class_exists('BuygoPlus\\Integrations\\FluentCartChildOrdersIntegration')) {
        echo "✅ 整合類別載入成功<br>";

        // 手動註冊 hooks
        \BuygoPlus\Integrations\FluentCartChildOrdersIntegration::register_hooks();
        echo "✅ 已手動呼叫 register_hooks()<br>";
    } else {
        echo "❌ 整合類別載入失敗<br>";
    }
} else {
    echo "❌ 無法載入整合類別（檔案不存在）<br>";
}

// 5. 檢查 Hook 註冊
echo "<h3>5️⃣ 檢查 Hook 註冊狀態</h3>";
global $wp_filter;
if (isset($wp_filter['fluent_cart/customer_app'])) {
    echo "✅ fluent_cart/customer_app hook 已註冊<br>";
    echo "<strong>已註冊的 callbacks:</strong><br>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin-top: 10px;'>";
    echo "<tr><th>Priority</th><th>Callback</th></tr>";
    foreach ($wp_filter['fluent_cart/customer_app']->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $idx => $callback) {
            if (is_array($callback['function'])) {
                $class = is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0];
                $method = $callback['function'][1];
                $callback_name = "{$class}::{$method}";
            } else {
                $callback_name = $callback['function'];
            }
            echo "<tr><td>$priority</td><td><code>$callback_name</code></td></tr>";
        }
    }
    echo "</table>";
} else {
    echo "❌ fluent_cart/customer_app hook 未註冊<br>";
}

// 6. 手動測試 Hook 輸出
echo "<h3>6️⃣ 手動觸發 Hook 測試</h3>";
echo "<div style='border: 2px solid #4CAF50; padding: 15px; background: #f9f9f9; margin: 10px 0;'>";
echo "<strong>Hook 輸出預覽:</strong><br><br>";
ob_start();
do_action('fluent_cart/customer_app');
$hook_output = ob_get_clean();
if (!empty($hook_output)) {
    echo $hook_output;
} else {
    echo "<em style='color: #f44336;'>⚠️ Hook 已觸發，但沒有輸出內容</em><br>";
    echo "<small>這可能是因為 is_customer_profile_page() 回傳 false</small>";
}
echo "</div>";

// 7. 檢查頁面條件
echo "<h3>7️⃣ 檢查頁面條件</h3>";
echo "當前用戶登入狀態: " . (is_user_logged_in() ? "✅ 已登入" : "❌ 未登入") . "<br>";
echo "當前 URL: <code>" . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "</code><br>";
$is_profile_page = is_user_logged_in() &&
    (strpos($_SERVER['REQUEST_URI'] ?? '', '/my-account/') !== false ||
     strpos($_SERVER['REQUEST_URI'] ?? '', '/customer-profile/') !== false);
echo "is_customer_profile_page 判斷: " . ($is_profile_page ? "✅ true" : "❌ false") . "<br>";

// 8. 建議
echo "<h3>8️⃣ 下一步建議</h3>";
echo "<ol>";
echo "<li>如果 FluentCart 類別不存在，請先啟用 FluentCart 外掛</li>";
echo "<li>如果 Hook 已註冊但沒有輸出，請前往 <a href='/my-account/' target='_blank'>會員帳戶頁面</a> 測試</li>";
echo "<li>清除瀏覽器快取（Cmd + Shift + R）</li>";
echo "<li>如果問題持續，請停用並重新啟用 BuyGo Plus One Dev 外掛</li>";
echo "</ol>";

// 9. 強制重新整理 OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<p style='color: green;'>✅ OPcache 已清除</p>";
}

echo "<hr>";
echo "<p><strong>診斷完成！</strong></p>";
echo "</div>";
