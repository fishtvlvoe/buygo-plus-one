<?php
/**
 * AJAX: 更新商品限制數量
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
$product_limit = isset($_POST['product_limit']) ? intval($_POST['product_limit']) : 0;

// 驗證參數
if ($user_id <= 0) {
    wp_send_json_error('無效的使用者 ID');
    return;
}

if ($product_limit < 0) {
    wp_send_json_error('商品限制數量不能為負數');
    return;
}

// 更新 user meta
$result = update_user_meta($user_id, 'buygo_product_limit', $product_limit);

if ($result !== false) {
    wp_send_json_success([
        'message' => '商品限制已更新',
        'user_id' => $user_id,
        'product_limit' => $product_limit
    ]);
} else {
    wp_send_json_error('更新失敗');
}
