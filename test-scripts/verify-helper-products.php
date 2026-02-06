<?php
/**
 * 驗證小幫手商品權限修復
 * 
 * 使用方式：
 * 1. 瀏覽器登入為小幫手（User ID 42）
 * 2. 訪問：https://test.buygo.me/verify-helper-products.php
 */

// 載入 WordPress
require_once('/Users/fishtv/Local Sites/buygo/app/public/wp-load.php');

// 檢查當前使用者
$current_user = wp_get_current_user();
echo "<h2>當前使用者</h2>";
echo "<p>ID: {$current_user->ID}</p>";
echo "<p>名稱: {$current_user->display_name}</p>";
echo "<p>Email: {$current_user->user_email}</p>";

// 檢查小幫手權限
$helper_settings = new \BuyGoPlus\Services\SettingsService();
$accessible_seller_ids = $helper_settings::get_accessible_seller_ids($current_user->ID);

echo "<h2>可存取的賣家 ID</h2>";
echo "<pre>" . print_r($accessible_seller_ids, true) . "</pre>";

// 測試商品查詢
$product_service = new \BuyGoPlus\Services\ProductService();

try {
    $products = $product_service->getProductsWithOrderCount(
        ['status' => 'all', 'search' => ''],
        'frontend'
    );

    echo "<h2>商品查詢結果</h2>";
    echo "<p>總共 " . count($products) . " 個商品</p>";

    if (count($products) > 0) {
        echo "<h3>商品清單</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>名稱</th><th>Post ID</th><th>賣家 ID</th><th>賣家名稱</th></tr>";
        foreach ($products as $product) {
            echo "<tr>";
            echo "<td>{$product['id']}</td>";
            echo "<td>{$product['name']}</td>";
            echo "<td>{$product['post_id']}</td>";
            echo "<td>{$product['seller_id']}</td>";
            echo "<td>{$product['seller_name']}</td>";
            echo "</tr>";
        }
        echo "</table>";

        echo "<h3 style='color: green;'>✅ 修復成功！小幫手可以看到賣家的商品</h3>";
    } else {
        echo "<h3 style='color: red;'>❌ 修復失敗：仍然看不到商品</h3>";
    }

} catch (\Exception $e) {
    echo "<h3 style='color: red;'>錯誤</h3>";
    echo "<p>{$e->getMessage()}</p>";
    echo "<pre>{$e->getTraceAsString()}</pre>";
}
