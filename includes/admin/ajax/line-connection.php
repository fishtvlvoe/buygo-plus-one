<?php
/**
 * AJAX: 測試 LINE 連線
 *
 * 從 class-settings-page.php 拆分的 AJAX 處理
 * 由 SettingsPage::ajax_test_line_connection() require 載入
 * $this 指向 SettingsPage 實例
 */
if (!defined('ABSPATH')) {
    exit;
}

// Verify nonce
if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'buygo_settings_nonce')) {
    wp_send_json_error(['message' => '安全驗證失敗']);
    return;
}

if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => '權限不足']);
    return;
}

$token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : null;
$result = \BuyGoPlus\Services\SettingsService::test_line_connection($token);

if ($result['success']) {
    wp_send_json_success($result);
} else {
    wp_send_json_error($result);
}
