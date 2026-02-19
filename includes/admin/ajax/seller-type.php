<?php
/**
 * AJAX: 更新賣家類型
 *
 * 從 class-settings-page.php 拆分的 AJAX 處理
 */
if (!defined('ABSPATH')) {
    exit;
}

// 驗證 nonce
if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'buygo-settings')) {
    wp_send_json_error('無效的請求');
    return;
}

// 權限檢查
if (!current_user_can('manage_options')) {
    wp_send_json_error('權限不足');
    return;
}

// 取得參數
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$seller_type = isset($_POST['seller_type']) ? sanitize_text_field($_POST['seller_type']) : '';

// 驗證參數
if ($user_id <= 0) {
    wp_send_json_error('無效的使用者 ID');
    return;
}

if (!in_array($seller_type, ['test', 'real'], true)) {
    wp_send_json_error('無效的賣家類型');
    return;
}

// 更新 user meta
$result = update_user_meta($user_id, 'buygo_seller_type', $seller_type);

if ($result !== false) {
    wp_send_json_success([
        'message' => '賣家類型已更新',
        'user_id' => $user_id,
        'seller_type' => $seller_type
    ]);
} else {
    wp_send_json_error('更新失敗');
}
