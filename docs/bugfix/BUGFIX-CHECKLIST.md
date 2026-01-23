# BuyGo+1 已修復問題清單

> ⚠️ **重要**：修改以下功能前，請先閱讀相關說明，避免再次踩坑！

---

## 問題 1：LINE 上架沒有反應

**症狀**：用戶從 LINE 發送圖片和文字，官方帳號沒有反應

**根本原因**：
1. Channel Secret 讀取位置錯誤
2. HTTP Header 大小寫錯誤（`X-Line-Signature` vs `x-line-signature`）
3. REST API 權限設定錯誤（`permission_callback` 不能用 `verify_signature`）
4. 權限檢查使用舊的 option 而非 `wp_buygo_helpers` 資料表

**修復位置**：
- `/includes/api/class-line-webhook-api.php` - 簽名驗證
- `/includes/services/class-line-webhook-handler.php` - 權限檢查

**關鍵代碼（不要修改！）**：
```php
// class-line-webhook-api.php
// ✅ 必須使用小寫
$signature = $request->get_header('x-line-signature');

// ✅ permission_callback 必須是 __return_true
'permission_callback' => '__return_true'

// ✅ 從正確位置讀取 Channel Secret
$channel_secret = \BuyGo_Core::settings()->get('line_channel_secret', '');
```

**驗證方法**：LINE Developers Console 點擊「驗證」應返回 200 OK

**相關 Commits**：
- `fce684e` - 修復 LINE 上架權限檢查 Bug
- `3ef405e` - 修復 Channel Secret 讀取邏輯與 Header 大小寫
- `7a6577d` - 修正 REST API 權限設定（401 問題）

---

## 問題 2：找不到客戶 UID

**症狀**：客戶頁面顯示空白或找不到用戶

**根本原因**：
- 用戶 LINE ID 沒有正確綁定到 WordPress 用戶
- 查詢時使用錯誤的 meta_key

**修復位置**：
- `/includes/services/class-line-service.php` - 用戶綁定邏輯
- `/includes/api/class-customers-api.php` - 客戶查詢

**關鍵代碼（不要修改！）**：
```php
// 正確的 meta_key
$meta_key = 'line_user_id';  // ✅ 不是 'buygo_line_id' 或其他

// 正確的查詢方式
$users = get_users([
    'meta_key' => 'line_user_id',
    'meta_value' => $line_uid
]);
```

**驗證方法**：客戶頁面應顯示有 LINE 綁定的用戶

---

## 問題 3：產品跟單問題

**症狀**：產品的訂單數量計算錯誤，或無法正確關聯訂單

**根本原因**：
- 父子訂單邏輯混淆
- 統計時重複計算父訂單和子訂單

**修復位置**：
- `/includes/services/class-product-service.php` - 訂單統計
- `/includes/services/class-order-service.php` - 父子訂單邏輯

**關鍵代碼（不要修改！）**：
```php
// 統計時只計算「沒有子訂單的訂單」或「子訂單」
// 避免父訂單被重複計算
$orders = $this->get_orders_for_product($product_id);
foreach ($orders as $order) {
    // ✅ 如果是父訂單且有子訂單，跳過（避免重複計算）
    if ($order->has_child_orders()) {
        continue;
    }
    // 計算邏輯...
}
```

**驗證方法**：產品的「已下單」數量應等於所有獨立訂單項目的總和

---

## 問題 4：搜尋框沒有功能

**症狀**：輸入關鍵字後，列表沒有過濾

**根本原因**：
1. `smart-search-box` 組件的事件沒有正確觸發
2. 頁面的 `handleProductSearch` 方法沒有正確綁定
3. API 的 `search` 參數沒有傳遞

**修復位置**：
- `/components/shared/smart-search-box.php` - 搜尋組件
- `/admin/partials/products.php` - 事件處理
- `/includes/api/class-products-api.php` - 搜尋參數處理

**關鍵代碼（不要修改！）**：
```javascript
// smart-search-box.php 必須 emit 這些事件
this.$emit('search', searchQuery);
this.$emit('select', item);
this.$emit('clear');

// products.php 必須監聽這些事件
<smart-search-box
    @search="handleProductSearch"
    @select="handleProductSelect"
    @clear="handleProductSearchClear"
></smart-search-box>

// handleProductSearch 必須調用 API
const handleProductSearch = (query) => {
    globalSearchQuery.value = query;
    loadProducts();  // ← 這行很重要！
};
```

**驗證方法**：在搜尋框輸入文字，列表應即時過濾

---

## 問題 5：API 401 權限錯誤

**症狀**：頁面載入時顯示 401 Unauthorized

**根本原因**：
- fetch 請求沒有帶 `X-WP-Nonce` header
- `wpNonce` 變數沒有定義

**修復位置**：
- 所有 `/admin/partials/*.php` 頁面

**關鍵代碼（每個頁面都必須有！）**：
```php
// 在 <script> 開頭定義
const wpNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';

// 每個 fetch 都必須帶這個 header
fetch(url, {
    headers: {
        'X-WP-Nonce': wpNonce,
        'Content-Type': 'application/json'
    }
});
```

**驗證方法**：開啟 DevTools Network，確認所有 API 請求都返回 200

**相關 Commit**：`fc06d7a` - 修復 API 401 權限錯誤

---

## 問題 6：訂單詳情 403 錯誤

**症狀**：點擊訂單詳情時，API 返回 403 Forbidden

**根本原因**：`wpNonce` 在 setup() 中定義但未在 return 中導出

**修復位置**：
- `/admin/partials/orders.php` - return 中加入 wpNonce
- `/components/order/order-detail-modal.php` - 加入 wpNonce prop + X-WP-Nonce headers

**關鍵代碼（不要修改！）**：
```javascript
// orders.php - 必須在 return 中導出
return {
    // ... other exports
    wpNonce  // ← 這行很重要！
};

// order-detail-modal.php - 必須接收 prop
props: {
    wpNonce: { type: String, required: true }
}

// order-detail-modal.php - 每個 fetch 都要帶 header
headers: { 'X-WP-Nonce': props.wpNonce }
```

**驗證方法**：點擊訂單詳情，應能正常顯示訂單資訊

**相關 Commit**：`fc439d9` - 修復訂單詳情 403 錯誤

---

## 問題 7：商品分配頁面顯示 0 筆訂單

**症狀**：商品的「分配」子分頁顯示 0 筆訂單，但實際有訂單

**根本原因**：缺少 `wp_buygo_shipments` 和 `wp_buygo_shipment_items` 資料表

**修復位置**：
- `/includes/class-database.php` - 加入 shipments 和 shipment_items 資料表建立
- `/includes/class-plugin.php` - 加入 maybe_upgrade_database() 自動升級機制

**關鍵代碼（不要修改！）**：
```php
// class-plugin.php - 自動升級資料庫
private function maybe_upgrade_database(): void
{
    $current_db_version = get_option('buygo_plus_one_db_version', '0');
    $required_db_version = '1.1.0';

    if (version_compare($current_db_version, $required_db_version, '<')) {
        Database::create_tables();
        update_option('buygo_plus_one_db_version', $required_db_version);
    }
}
```

**驗證方法**：商品的「分配」子分頁應顯示所有未分配的訂單

**相關 Commit**：`fc439d9` - 修復商品分配頁面 0 筆訂單問題

---

## 問題 8：產品名稱顯示「預設」

**症狀**：訂單中的產品名稱顯示為「預設」而非實際產品名稱

**根本原因**：只讀取 `$item['title']`，未讀取 `variation_title`

**修復位置**：
- `/includes/services/class-order-service.php` - formatOrder() 讀取 variation_title

**關鍵代碼（不要修改！）**：
```php
// class-order-service.php
$product_name = $item['title'] ?? '';

// Fix: If title is "預設" or empty, read from variation_title
if (empty($product_name) || $product_name === '預設' || $product_name === '预设') {
    $variation_id = (int)($item['object_id'] ?? 0);
    if ($variation_id > 0) {
        $table_variations = $wpdb->prefix . 'fct_product_variations';
        $variation_title = $wpdb->get_var($wpdb->prepare(
            "SELECT variation_title FROM {$table_variations} WHERE id = %d",
            $variation_id
        ));
        if (!empty($variation_title)) {
            $product_name = $variation_title;
        }
    }
}
```

**驗證方法**：訂單中的產品名稱應顯示正確的變體名稱（如「紅色-L」）

**相關 Commit**：`fc439d9` - 修復產品名稱顯示「預設」問題

---

## 問題 9：SQL NULL 處理錯誤

**症狀**：某些訂單查詢時返回空結果

**根本原因**：`NOT IN` 對 NULL 值返回 NULL，導致查詢失敗

**修復位置**：
- `/includes/services/class-allocation-service.php` - 修正 SQL NULL 處理

**關鍵代碼（不要修改！）**：
```php
// class-allocation-service.php
// ❌ 錯誤：NOT IN 對 NULL 返回 NULL
WHERE shipping_status NOT IN ('shipped', 'completed')

// ✅ 正確：處理 NULL 值
WHERE (shipping_status IS NULL OR shipping_status NOT IN ('shipped', 'completed'))
```

**驗證方法**：所有未出貨的訂單都應顯示在列表中

**相關 Commit**：`fc439d9` - 修復 SQL NULL 處理錯誤

---

**最後更新**：2026-01-23
**維護者**：Development Team
