<?php
/**
 * AJAX: 搜尋虛擬商品（賣家商品選擇）
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

// 取得搜尋關鍵字
$search = sanitize_text_field($_POST['search'] ?? '');

global $wpdb;

// 查詢虛擬商品（is_shippable = 0）
// 支援商品 ID 或標題搜尋
$query = "
    SELECT p.ID, p.post_title, fct.price, p.post_status
    FROM {$wpdb->prefix}posts AS p
    INNER JOIN {$wpdb->prefix}fct_products AS fct ON p.ID = fct.id
    WHERE p.post_type = 'fct_product'
    AND fct.is_shippable = 0
    AND p.post_status = 'publish'
";

// 如果有搜尋關鍵字，加入搜尋條件
if (!empty($search)) {
    $search_like = '%' . $wpdb->esc_like($search) . '%';
    $query .= $wpdb->prepare(
        " AND (p.ID = %s OR p.post_title LIKE %s)",
        $search,
        $search_like
    );
}

$query .= " ORDER BY p.post_modified DESC LIMIT 20";

$products = $wpdb->get_results($query);

// 格式化結果
$results = array_map(function($product) {
    return [
        'id' => $product->ID,
        'title' => $product->post_title,
        'price' => number_format($product->price, 0),
        'status' => $product->post_status
    ];
}, $products);

wp_send_json_success([
    'products' => $results,
    'count' => count($results)
]);
