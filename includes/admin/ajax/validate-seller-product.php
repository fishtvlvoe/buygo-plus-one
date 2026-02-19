<?php
/**
 * AJAX: 驗證賣家商品
 *
 * 從 class-settings-page.php 拆分的 AJAX 處理
 */
if (!defined('ABSPATH')) {
    exit;
}

// 驗證 nonce
check_ajax_referer('buygo-settings', 'nonce');

// 權限檢查
if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => '權限不足']);
}

// 取得商品 ID
$product_id = sanitize_text_field($_POST['product_id'] ?? '');

if (empty($product_id)) {
    wp_send_json_error(['message' => '請輸入商品 ID']);
}

global $wpdb;

// FluentCart 商品儲存在 wp_posts（post_type = 'fluent-products'）
$product = $wpdb->get_row($wpdb->prepare(
    "SELECT ID, post_title, post_status FROM {$wpdb->prefix}posts
     WHERE ID = %d AND post_type = 'fluent-products'",
    $product_id
));

if (!$product) {
    wp_send_json_error(['message' => '找不到此 FluentCart 商品 ID']);
}

if ($product->post_status !== 'publish') {
    wp_send_json_error(['message' => "商品狀態為 {$product->post_status}，必須是 publish"]);
}

// 取得商品詳細資料（從 fct_product_details）
$product_detail = $wpdb->get_row($wpdb->prepare(
    "SELECT min_price, fulfillment_type FROM {$wpdb->prefix}fct_product_details
     WHERE post_id = %d",
    $product_id
));

if (!$product_detail) {
    wp_send_json_error(['message' => '找不到此商品的詳細資料']);
}

// 檢查是否為虛擬商品（fulfillment_type = 'digital'）
if ($product_detail->fulfillment_type !== 'digital') {
    wp_send_json_error(['message' => '賣家商品必須是虛擬商品（不需要物流）']);
}

wp_send_json_success([
    'product' => [
        'id' => $product->ID,
        'title' => $product->post_title,
        'price' => $product_detail->min_price ? floatval($product_detail->min_price) : 0,
        'is_virtual' => $product_detail->fulfillment_type === 'digital',
        'admin_url' => admin_url('post.php?post=' . $product->ID . '&action=edit')
    ]
]);
