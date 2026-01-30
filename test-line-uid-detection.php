<?php
/**
 * 測試 LINE UID 偵測
 *
 * 在瀏覽器訪問: https://test.buygo.me/wp-content/plugins/buygo-plus-one/test-line-uid-detection.php
 */

require_once __DIR__ . '/../../../wp-load.php';

// 檢查是否已登入且為管理員
if (!is_user_logged_in() || !current_user_can('administrator')) {
    wp_die('❌ 請先以管理員身份登入 WordPress 後台');
}

echo '<pre style="background: #f0f0f0; padding: 20px; border: 1px solid #ccc;">';
echo "=== LINE UID 偵測測試 ===\n\n";

// 測試所有有 buygo_admin 或 buygo_helper 角色的使用者
$users = get_users(['role__in' => ['administrator', 'buygo_admin', 'buygo_helper']]);

foreach ($users as $user) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "使用者: {$user->display_name} (ID: {$user->ID})\n";
    echo "Email: {$user->user_email}\n";
    echo "角色: " . implode(', ', $user->roles) . "\n";
    echo "\n";

    // 檢查所有可能的 meta keys
    $meta_keys = ['line_uid', '_mygo_line_uid', 'buygo_line_user_id', 'm_line_user_id', 'line_user_id'];

    $found = false;
    foreach ($meta_keys as $key) {
        $value = get_user_meta($user->ID, $key, true);
        if (!empty($value)) {
            echo "✅ {$key}: {$value}\n";
            $found = true;
        }
    }

    if (!$found) {
        echo "❌ 未找到任何 LINE UID\n";
    }

    echo "\n";

    // 測試 SettingsService::get_user_line_id()
    $line_id = \BuyGoPlus\Services\SettingsService::get_user_line_id($user->ID);
    if ($line_id) {
        echo "🎯 SettingsService::get_user_line_id() 返回: {$line_id}\n";
    } else {
        echo "⚠️  SettingsService::get_user_line_id() 返回: null\n";
    }

    echo "\n";
}

echo "=== 測試完成 ===\n";
echo '</pre>';
