<?php
/**
 * Phase 19 測試腳本 - 賣家權限隔離與限制檢查
 *
 * 測試項目:
 * 1. 賣家類型欄位 (test/real)
 * 2. ProductService 權限過濾 (post_author)
 * 3. canAddProduct() 限制檢查
 * 4. canAddImage() 限制檢查
 */

require_once __DIR__ . '/../../../wp-load.php';

use BuyGoPlus\Admin\SellerTypeField;
use BuyGoPlus\Services\ProductService;

echo "=== Phase 19 測試: 賣家權限隔離與限制檢查 ===\n\n";

// ========================================
// 測試 1: 賣家類型欄位
// ========================================
echo "【測試 1】賣家類型欄位\n";
echo "----------------------------------------\n";

// 取得測試使用者 (假設 user_id = 1)
$test_user_id = 1;
$current_type = SellerTypeField::get_seller_type($test_user_id);
$is_test = SellerTypeField::is_test_seller($test_user_id);

echo "使用者 ID: {$test_user_id}\n";
echo "賣家類型: {$current_type}\n";
echo "是測試賣家: " . ($is_test ? '是' : '否') . "\n";

// 測試設定為 test
update_user_meta($test_user_id, 'buygo_seller_type', 'test');
echo "\n[動作] 設定為測試賣家 (test)\n";
$current_type = SellerTypeField::get_seller_type($test_user_id);
echo "賣家類型: {$current_type}\n";
echo "是測試賣家: " . (SellerTypeField::is_test_seller($test_user_id) ? '是' : '否') . "\n";

// 測試設定為 real
update_user_meta($test_user_id, 'buygo_seller_type', 'real');
echo "\n[動作] 設定為真實賣家 (real)\n";
$current_type = SellerTypeField::get_seller_type($test_user_id);
echo "賣家類型: {$current_type}\n";
echo "是測試賣家: " . (SellerTypeField::is_test_seller($test_user_id) ? '是' : '否') . "\n";

// 恢復為 test 方便後續測試
update_user_meta($test_user_id, 'buygo_seller_type', 'test');

echo "\n✓ 測試 1 完成\n\n";

// ========================================
// 測試 2: canAddProduct() 限制檢查
// ========================================
echo "【測試 2】canAddProduct() 限制檢查\n";
echo "----------------------------------------\n";

$product_service = new ProductService();

// 測試賣家 (限制 2 個商品)
update_user_meta($test_user_id, 'buygo_seller_type', 'test');
$limit_status = $product_service->canAddProduct($test_user_id);

echo "測試賣家 (user_id: {$test_user_id}):\n";
echo "  can_add: " . ($limit_status['can_add'] ? '是' : '否') . "\n";
echo "  current: {$limit_status['current']}\n";
echo "  limit: {$limit_status['limit']}\n";
echo "  message: {$limit_status['message']}\n";

// 真實賣家 (無限制)
update_user_meta($test_user_id, 'buygo_seller_type', 'real');
$limit_status = $product_service->canAddProduct($test_user_id);

echo "\n真實賣家 (user_id: {$test_user_id}):\n";
echo "  can_add: " . ($limit_status['can_add'] ? '是' : '否') . "\n";
echo "  current: {$limit_status['current']}\n";
echo "  limit: {$limit_status['limit']}\n";
echo "  message: {$limit_status['message']}\n";

// 恢復為 test
update_user_meta($test_user_id, 'buygo_seller_type', 'test');

echo "\n✓ 測試 2 完成\n\n";

// ========================================
// 測試 3: canAddImage() 限制檢查
// ========================================
echo "【測試 3】canAddImage() 限制檢查\n";
echo "----------------------------------------\n";

// 取得第一個商品 ID (假設存在)
global $wpdb;
$product_id = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' LIMIT 1");

if ($product_id) {
    // 測試賣家 (限制 2 張圖片)
    update_user_meta($test_user_id, 'buygo_seller_type', 'test');
    $image_limit = $product_service->canAddImage($product_id, $test_user_id);

    echo "測試賣家 (product_id: {$product_id}):\n";
    echo "  can_add: " . ($image_limit['can_add'] ? '是' : '否') . "\n";
    echo "  current: {$image_limit['current']}\n";
    echo "  limit: {$image_limit['limit']}\n";
    echo "  message: {$image_limit['message']}\n";

    // 真實賣家 (無限制)
    update_user_meta($test_user_id, 'buygo_seller_type', 'real');
    $image_limit = $product_service->canAddImage($product_id, $test_user_id);

    echo "\n真實賣家 (product_id: {$product_id}):\n";
    echo "  can_add: " . ($image_limit['can_add'] ? '是' : '否') . "\n";
    echo "  current: {$image_limit['current']}\n";
    echo "  limit: {$image_limit['limit']}\n";
    echo "  message: {$image_limit['message']}\n";
} else {
    echo "⚠️  找不到商品，跳過此測試\n";
}

// 恢復為 test
update_user_meta($test_user_id, 'buygo_seller_type', 'test');

echo "\n✓ 測試 3 完成\n\n";

// ========================================
// 測試 4: ProductService 權限過濾
// ========================================
echo "【測試 4】ProductService 權限過濾\n";
echo "----------------------------------------\n";

// 模擬賣家登入
wp_set_current_user($test_user_id);

// 測試前端模式 (應該只顯示該賣家的商品)
$products = $product_service->getProducts([
    'view_mode' => 'frontend',
    'page' => 1,
    'per_page' => 10
]);

echo "前端模式 (賣家 {$test_user_id}):\n";
echo "  總商品數: {$products['total']}\n";
echo "  返回商品數: " . count($products['products']) . "\n";

if (!empty($products['products'])) {
    echo "  商品列表:\n";
    foreach ($products['products'] as $product) {
        $author_id = get_post_field('post_author', $product['id']);
        $match = ($author_id == $test_user_id) ? '✓' : '✗';
        echo "    {$match} ID: {$product['id']}, Author: {$author_id}, 名稱: {$product['name']}\n";
    }
}

echo "\n✓ 測試 4 完成\n\n";

echo "=== 所有測試完成 ===\n";
