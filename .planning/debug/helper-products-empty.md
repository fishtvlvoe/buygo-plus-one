---
status: verifying
trigger: "小幫手（Helper）登入後無法看到賣家的商品，但可以看到訂單和客戶"
created: 2026-02-03T00:00:00Z
updated: 2026-02-03T00:05:00Z
---

## Current Focus

hypothesis: ProductService 的 whereHas('product') 查詢可能因為 Product::post_author 在 $hidden 陣列中而無法正確過濾，或者關聯查詢有問題
test: 檢查 ProductService 生成的 SQL 查詢是否正確，並與 OrderService 的原生 SQL 方式比較
expecting: whereHas 查詢可能沒有正確過濾 post_author，導致返回空結果
next_action: 修改 ProductService 使用原生 SQL 查詢（如同 OrderService）來過濾賣家商品

## Symptoms

expected: 小幫手（User ID 42, Fish Yu）登入賣家賣場後，應該能看到賣家（User ID 8）的商品。已知賣家有 1 個商品。
actual: 商品頁面完全空白，沒有顯示任何商品。但同一個小幫手可以正常看到訂單頁面（顯示 1 個訂單）和客戶頁面。
errors: 沒有 JavaScript 錯誤或 PHP 錯誤訊息，API 返回成功但資料為空陣列。
reproduction:
1. 以 User ID 42 (Fish Yu) 登入
2. 訪問 https://test.buygo.me/buygo-portal/products/
3. 頁面顯示空白（無商品）
4. 但訪問 /orders 和 /customers 頁面都正常顯示資料
started: 修復 helper_id/user_id 欄位名稱錯誤後，訂單和客戶恢復正常，但商品仍然空白

## Eliminated

## Evidence

- timestamp: 2026-02-03T00:00:00Z
  checked: 資料庫 wp_buygo_helpers 表
  found: helper_id=42, seller_id=8, can_manage_products=1 資料正確
  implication: 權限資料本身沒有問題

- timestamp: 2026-02-03T00:00:00Z
  checked: 訂單和客戶 API
  found: 小幫手可以正常看到資料
  implication: get_accessible_seller_ids() 方法已修復且運作正常

- timestamp: 2026-02-03T00:00:00Z
  checked: 商品頁面
  found: 完全空白，API 返回空陣列
  implication: 商品查詢的權限過濾邏輯有問題

- timestamp: 2026-02-03T00:01:00Z
  checked: ProductService::getProductsWithOrderCount() 權限過濾邏輯 (lines 59-74)
  found: |
    使用 whereHas('product', function($q) use ($accessible_seller_ids) {
        $q->whereIn('post_author', $accessible_seller_ids);
    })
    過濾商品，accessible_seller_ids 應該包含 [8]
  implication: ProductService 的權限過濾邏輯存在，但可能 product 關聯或 post_author 有問題

- timestamp: 2026-02-03T00:01:00Z
  checked: OrderService::getOrders() 權限過濾邏輯 (lines 86-123)
  found: |
    使用原生 SQL 查詢：
    SELECT DISTINCT oi.order_id FROM fct_order_items oi
    INNER JOIN wp_posts p ON oi.post_id = p.ID OR oi.post_id = p.post_parent
    WHERE p.post_author IN (seller_ids)
  implication: OrderService 使用不同的過濾方式（原生 SQL），可能更穩定

- timestamp: 2026-02-03T00:02:00Z
  checked: FluentCart Product 模型 (wp-content/plugins/fluent-cart/app/Models/Product.php)
  found: |
    Product 模型來自 wp_posts 表，post_author 在 $hidden 陣列中（line 42）
    $hidden 屬性會影響序列化，但不應該影響查詢
    ProductVariation::product() 關聯使用 belongsTo(Product::class, 'post_id', 'ID')
  implication: 關聯定義看起來正確，但 whereHas 可能因為某些原因沒有正確執行

- timestamp: 2026-02-03T00:03:00Z
  checked: 比較兩種過濾方式
  found: |
    OrderService: 使用原生 SQL，直接查詢 wp_posts.post_author
    ProductService: 使用 Eloquent whereHas + relation，可能因為 ORM 層問題失敗
  implication: 應該改用原生 SQL 查詢（與 OrderService 一致）來確保穩定性

## Resolution

root_cause: |
  ProductService::getProductsWithOrderCount() 使用 Eloquent ORM 的 whereHas('product')
  查詢來過濾賣家商品，但這個查詢在小幫手登入時失敗。

  原因：
  1. whereHas 需要正確載入 ProductVariation -> Product 關聯
  2. Product 模型的 post_author 欄位在 $hidden 陣列中（雖然不應該影響查詢）
  3. Eloquent 關聯查詢可能因為複雜度或快取問題而失敗

  相比之下，OrderService 使用原生 SQL 直接查詢 wp_posts.post_author，更穩定可靠。

fix: |
  將 ProductService 的權限過濾邏輯改為使用原生 SQL 查詢（與 OrderService 一致）：

  修改檔案：includes/services/class-product-service.php (lines 59-74)

  改為：
  1. 使用 $wpdb->get_col() 直接查詢 wp_posts 表
  2. 取得符合條件的 post_id 列表
  3. 使用 whereIn('post_id', $post_ids) 過濾 ProductVariation

  這樣避免了 Eloquent 關聯查詢的不確定性，直接查詢資料庫更可靠。

verification: |
  驗證步驟：
  1. 以小幫手身分登入（User ID 42, Fish Yu）
  2. 方法 1：訪問 https://test.buygo.me/verify-helper-products.php
  3. 方法 2：訪問 https://test.buygo.me/buygo-portal/products/
  4. 確認可以看到賣家（User ID 8）的商品

  預期結果：
  - 商品頁面顯示至少 1 個商品
  - 商品的 seller_id = 8
  - 商品的 seller_name = 'andy' 或 'Fish'

files_changed:
  - includes/services/class-product-service.php
