<?php
/**
 * 測試用假資料：出貨明細合併顯示功能
 *
 * 使用方式：在 WordPress 環境中透過 WP-CLI 執行
 * wp eval-file wp-content/plugins/buygo-plus-one/tests/seed-merge-test-data.php
 *
 * 會建立一張出貨單，裡面有：
 * - 商品 A × 3 筆（來自不同訂單，同 product_id）→ 合併後應顯示 1 行
 * - 商品 B × 1 筆 → 顯示 1 行
 * 合併前 4 行，合併後 2 行，總計金額不變
 */

if (!defined('ABSPATH')) {
    echo "請透過 WP-CLI 執行此腳本\n";
    exit;
}

global $wpdb;

// 先找一個存在的客戶（取第一個）
$customer = $wpdb->get_row(
    "SELECT id FROM {$wpdb->prefix}fct_customers LIMIT 1"
);

if (!$customer) {
    echo "找不到任何客戶資料，請先確認 FluentCart 有客戶\n";
    exit;
}

$customer_id = $customer->id;

// 找兩個存在的商品（WooCommerce/FluentCart 商品）
$products = $wpdb->get_results(
    "SELECT ID, post_title FROM {$wpdb->prefix}posts
     WHERE post_type = 'fct_product' AND post_status = 'publish'
     LIMIT 2"
);

if (count($products) < 2) {
    // 備援：用任意兩個 post
    $products = $wpdb->get_results(
        "SELECT ID, post_title FROM {$wpdb->prefix}posts
         WHERE post_status = 'publish'
         LIMIT 2"
    );
}

if (count($products) < 2) {
    echo "找不到足夠的商品資料\n";
    exit;
}

$product_a_id = $products[0]->ID;
$product_b_id = $products[1]->ID;
$product_a_name = $products[0]->post_title;
$product_b_name = $products[1]->post_title;

// 取得 seller_id（取 admin user）
$seller_id = 1;

// 建立測試出貨單
$shipment_number = 'SH-TEST-MERGE-' . date('Ymd-His');
$wpdb->insert(
    $wpdb->prefix . 'buygo_shipments',
    [
        'shipment_number' => $shipment_number,
        'customer_id'     => $customer_id,
        'seller_id'       => $seller_id,
        'status'          => 'ready_to_ship',
        'created_at'      => current_time('mysql'),
        'updated_at'      => current_time('mysql'),
    ]
);
$shipment_id = $wpdb->insert_id;

if (!$shipment_id) {
    echo "建立出貨單失敗\n";
    exit;
}

// 找存在的 order_items 或用假的
$order_items = $wpdb->get_results(
    "SELECT id, order_id FROM {$wpdb->prefix}fct_order_items LIMIT 4"
);

// 插入 4 筆商品明細（商品 A × 3 + 商品 B × 1）
$test_items = [
    // 商品 A - 第 1 筆
    [
        'shipment_id'   => $shipment_id,
        'order_id'      => isset($order_items[0]) ? $order_items[0]->order_id : 1001,
        'order_item_id' => isset($order_items[0]) ? $order_items[0]->id : 2001,
        'product_id'    => $product_a_id,
        'quantity'       => 1,
    ],
    // 商品 A - 第 2 筆
    [
        'shipment_id'   => $shipment_id,
        'order_id'      => isset($order_items[1]) ? $order_items[1]->order_id : 1002,
        'order_item_id' => isset($order_items[1]) ? $order_items[1]->id : 2002,
        'product_id'    => $product_a_id,
        'quantity'       => 2,
    ],
    // 商品 A - 第 3 筆
    [
        'shipment_id'   => $shipment_id,
        'order_id'      => isset($order_items[2]) ? $order_items[2]->order_id : 1003,
        'order_item_id' => isset($order_items[2]) ? $order_items[2]->id : 2003,
        'product_id'    => $product_a_id,
        'quantity'       => 1,
    ],
    // 商品 B - 第 1 筆
    [
        'shipment_id'   => $shipment_id,
        'order_id'      => isset($order_items[3]) ? $order_items[3]->order_id : 1004,
        'order_item_id' => isset($order_items[3]) ? $order_items[3]->id : 2004,
        'product_id'    => $product_b_id,
        'quantity'       => 1,
    ],
];

foreach ($test_items as $item) {
    $wpdb->insert($wpdb->prefix . 'buygo_shipment_items', $item);
}

echo "\n";
echo "=== 測試資料建立完成 ===\n";
echo "出貨單號：{$shipment_number}\n";
echo "出貨單 ID：{$shipment_id}\n";
echo "客戶 ID：{$customer_id}\n";
echo "\n";
echo "商品明細（合併前 4 筆）：\n";
echo "  商品 A [{$product_a_name}] (ID:{$product_a_id}) × 1\n";
echo "  商品 A [{$product_a_name}] (ID:{$product_a_id}) × 2\n";
echo "  商品 A [{$product_a_name}] (ID:{$product_a_id}) × 1\n";
echo "  商品 B [{$product_b_name}] (ID:{$product_b_id}) × 1\n";
echo "\n";
echo "預期合併後（2 筆）：\n";
echo "  商品 A [{$product_a_name}] × 4\n";
echo "  商品 B [{$product_b_name}] × 1\n";
echo "\n";
echo "請到出貨明細頁面查看此出貨單：{$shipment_number}\n";
