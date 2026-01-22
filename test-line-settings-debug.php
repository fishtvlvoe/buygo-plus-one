<?php
/**
 * Debug LINE 設定讀取
 *
 * 用法：在 WordPress 管理後台執行此腳本來查看 debug 日誌
 */

// 載入 WordPress
require_once('/Users/fishtv/Local Sites/buygo/app/public/wp-load.php');

echo "=== LINE 設定 Debug 測試 ===\n\n";

// 檢查 BUYGO_ENCRYPTION_KEY
echo "1. 檢查加密金鑰\n";
echo "   BUYGO_ENCRYPTION_KEY 是否定義: " . (defined('BUYGO_ENCRYPTION_KEY') ? "是" : "否") . "\n";
if (defined('BUYGO_ENCRYPTION_KEY')) {
    echo "   金鑰長度: " . strlen(BUYGO_ENCRYPTION_KEY) . "\n";
}
echo "\n";

// 檢查舊資料是否存在
echo "2. 檢查舊資料 (buygo_core_settings)\n";
$core_settings = get_option('buygo_core_settings', []);
echo "   buygo_core_settings 是否存在: " . (empty($core_settings) ? "否" : "是") . "\n";
if (!empty($core_settings)) {
    echo "   包含的 keys: " . implode(', ', array_keys($core_settings)) . "\n";

    // 檢查 LINE 相關設定
    $line_keys = ['line_channel_access_token', 'line_channel_secret', 'line_liff_id'];
    foreach ($line_keys as $key) {
        if (isset($core_settings[$key])) {
            $value = $core_settings[$key];
            echo "   - $key: 存在 (長度: " . strlen($value) . ", 前 20 字元: " . substr($value, 0, 20) . "...)\n";
        } else {
            echo "   - $key: 不存在\n";
        }
    }
}
echo "\n";

// 檢查新資料是否存在
echo "3. 檢查新資料 (獨立 option)\n";
$new_options = [
    'buygo_line_channel_access_token',
    'buygo_line_channel_secret',
    'buygo_line_liff_id'
];
foreach ($new_options as $option) {
    $value = get_option($option, false);
    if ($value !== false) {
        echo "   - $option: 存在 (長度: " . strlen($value) . ", 前 20 字元: " . substr($value, 0, 20) . "...)\n";
    } else {
        echo "   - $option: 不存在\n";
    }
}
echo "\n";

// 測試讀取 (會觸發 debug 日誌)
echo "4. 測試讀取 LINE 設定 (會產生 debug 日誌)\n";
echo "   開始測試...\n";

// 載入新外掛的 SettingsService
require_once('/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/buygo-plus-one/includes/services/class-settings-service.php');

$test_keys = [
    'line_channel_access_token',
    'line_channel_secret',
    'line_liff_id'
];

foreach ($test_keys as $key) {
    echo "\n   測試讀取: $key\n";
    $value = BuyGo_Settings_Service::get($key);
    if ($value) {
        echo "   ✓ 讀取成功 (長度: " . strlen($value) . ", 前 20 字元: " . substr($value, 0, 20) . "...)\n";
    } else {
        echo "   ✗ 讀取失敗或為空\n";
    }
}

echo "\n\n5. 請檢查 debug.log 檔案以查看詳細的解密過程\n";
echo "   檔案位置: /Users/fishtv/Local Sites/buygo/app/public/wp-content/debug.log\n";
echo "\n=== 測試完成 ===\n";
