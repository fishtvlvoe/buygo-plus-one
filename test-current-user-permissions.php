<?php
/**
 * Diagnostic script: Check current user's permissions
 *
 * Usage: Access via browser while logged in
 */

require_once __DIR__ . '/../../../wp-load.php';

if (!is_user_logged_in()) {
    wp_die('請先登入');
}

$current_user = wp_get_current_user();

echo "<h1>當前使用者權限診斷</h1>";
echo "<h2>基本資訊</h2>";
echo "<p><strong>使用者 ID:</strong> " . $current_user->ID . "</p>";
echo "<p><strong>使用者名稱:</strong> " . $current_user->user_login . "</p>";
echo "<p><strong>Email:</strong> " . $current_user->user_email . "</p>";
echo "<p><strong>顯示名稱:</strong> " . $current_user->display_name . "</p>";

echo "<h2>角色 (Roles)</h2>";
echo "<ul>";
foreach ($current_user->roles as $role) {
    echo "<li>$role</li>";
}
echo "</ul>";

echo "<h2>權限 (Capabilities)</h2>";
echo "<pre>";
print_r($current_user->allcaps);
echo "</pre>";

echo "<h2>賣家類型 (Seller Type)</h2>";
$seller_type = get_user_meta($current_user->ID, 'buygo_seller_type', true);
echo "<p><strong>buygo_seller_type:</strong> " . ($seller_type ?: '(未設定)') . "</p>";

echo "<h2>權限檢查結果</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>權限</th><th>結果</th></tr>";

$permissions_to_check = [
    'manage_options' => 'WordPress 管理員',
    'buygo_admin' => 'BuyGo 管理員',
    'buygo_helper' => 'BuyGo 小幫手',
    'buygo_add_helper' => '新增小幫手權限',
    'buygo_manage_all' => 'BuyGo 管理全部',
];

foreach ($permissions_to_check as $cap => $label) {
    $has_cap = current_user_can($cap) ? '✅ 有' : '❌ 無';
    echo "<tr><td>$label ($cap)</td><td>$has_cap</td></tr>";
}

echo "</table>";

echo "<h2>Settings API 權限檢查模擬</h2>";

// 模擬 Settings_API::check_permission_for_admin()
$can_admin = current_user_can('buygo_admin') || current_user_can('manage_options');
echo "<p><strong>check_permission_for_admin():</strong> " . ($can_admin ? '✅ 通過' : '❌ 拒絕') . "</p>";

// 模擬 API::check_permission()
$can_access = current_user_can('manage_options') || current_user_can('buygo_admin') || current_user_can('buygo_helper');
echo "<p><strong>check_permission():</strong> " . ($can_access ? '✅ 通過' : '❌ 拒絕') . "</p>";

echo "<h2>小幫手資料表</h2>";
global $wpdb;
$table_name = $wpdb->prefix . 'buygo_helpers';
$helpers = $wpdb->get_results("SELECT * FROM $table_name WHERE seller_id = {$current_user->ID} OR user_id = {$current_user->ID}");
echo "<pre>";
print_r($helpers);
echo "</pre>";
