# FluentCart 商品歸屬機制研究報告

**研究日期**: 2026-01-31
**研究對象**: FluentCart 商品資料結構中的 owner 欄位
**信心度**: HIGH (基於官方文件和現有程式碼分析)

---

## 執行摘要

FluentCart 商品**確實有 owner 欄位**，透過 WordPress 原生的 `post_author` 欄位實現。BuyGo+1 現有程式碼已在使用此欄位進行賣家隔離，可直接擴展。

**關鍵發現**:
1. FluentCart 商品存儲在 `wp_posts` 表（`post_type = 'fc_product'`）
2. 商品 owner = WordPress `post_author` 欄位
3. BuyGo+1 已使用 `post_author` 進行賣家權限過濾
4. LINE UID 存儲在 `post_meta` 的 `_mygo_line_user_id` 欄位

---

## 一、FluentCart 商品資料結構

### 1.1 核心資料表

FluentCart 商品系統涉及三個主要資料表：

| 資料表 | 說明 | Owner 欄位 |
|--------|------|------------|
| `wp_posts` | 商品主表（`post_type = 'fc_product'`） | **`post_author`** (WordPress User ID) |
| `wp_fct_product_details` | 商品詳情（庫存類型、價格範圍） | 無（透過 `post_id` 關聯） |
| `wp_fct_product_variations` | 商品變體（價格、庫存） | 無（透過 `post_id` 關聯） |

### 1.2 商品 Owner 欄位確認

**來源**: FluentCart 官方文件 `database_models_product.md`

```php
// FluentCart Product Model 使用 WordPress posts 表
| DB Table Name | {wp_db_prefix}_posts (WordPress posts table) |
| post_author   | Integer | Post author ID |
```

**確認**: FluentCart 商品的 owner 就是 WordPress 的 `post_author` 欄位。

### 1.3 現有 BuyGo+1 使用方式

在 `class-product-service.php` 中已有使用：

```php
// 權限篩選：賣家只能看到自己的商品
if (!$isAdmin) {
    $query->whereHas('product', function($q) use ($user) {
        $q->where('post_author', $user->ID);
    });
}
```

在 `class-fluentcart-service.php` 中創建商品時：

```php
$post_data = array(
    'post_title' => sanitize_text_field($product_data['name']),
    'post_status' => 'publish',
    'post_type' => 'fluent-products',  // 或 'fc_product'
    // post_author 由 wp_insert_post 自動設定為當前用戶
);

$product_id = wp_insert_post($post_data, true);

// LINE UID 存儲在 post_meta
if ($line_uid) {
    update_post_meta($product_id, '_mygo_line_user_id', $line_uid);
}
```

---

## 二、賣家識別機制

### 2.1 現有識別方式

BuyGo+1 目前支援兩種賣家識別：

| 識別方式 | 存儲位置 | 用途 |
|----------|----------|------|
| WordPress User ID | `wp_posts.post_author` | 商品歸屬、權限檢查 |
| LINE UID | `wp_postmeta._mygo_line_user_id` | LINE 訊息來源追蹤 |

### 2.2 LINE UID 與 WordPress User 的綁定

根據現有程式碼分析，LINE UID 與 WordPress User 的關係：

1. **商品建立時**: LINE Webhook 接收商品資料 → 查找對應 WordPress User → 以該 User 身份建立商品
2. **商品查詢時**: 透過 `post_author` 過濾 → 賣家只能看到自己的商品

### 2.3 相關資料表

```
wp_usermeta
├── buygo_seller_type: 'test' | 'real' (賣家類型)
├── buygo_product_limit: int (商品數量限制，0 = 無限制)
└── (需要新增) buygo_line_uid: string (LINE UID 綁定)

wp_buygo_helpers
├── user_id: 小幫手的 WordPress User ID
├── seller_id: 管理員的 WordPress User ID
└── created_at, updated_at
```

---

## 三、多賣家隔離實現方案

### 3.1 推薦方案：使用現有 `post_author`

**信心度**: HIGH

FluentCart 已使用 WordPress 原生架構，無需新增欄位：

```php
// 查詢賣家的商品
Product::where('post_author', $seller_user_id)->get();

// 或使用 FluentCart Query Builder
$products = FluentCart\App\Models\Product::query()
    ->where('post_author', $seller_user_id)
    ->with(['detail', 'variants'])
    ->get();
```

**優點**:
- 零修改成本（使用現有欄位）
- 符合 WordPress 慣例
- FluentCart Model 已支援

**缺點**:
- 需確保 LINE 建立商品時正確設定 `post_author`

### 3.2 LINE UID 與 WordPress User 綁定

需要新增綁定機制：

```php
// 方案 A: 使用 user_meta
update_user_meta($user_id, 'buygo_line_uid', $line_uid);

// 方案 B: 使用 buygo-line-notify 外掛的現有綁定
// 查看 buygo-line-notify 是否有 line_uid → user_id 的對照表
```

### 3.3 查詢效能優化

由於 `post_author` 已有索引，查詢效能不會是問題：

```sql
-- 現有索引
KEY post_author (post_author)

-- 查詢範例
SELECT * FROM wp_posts
WHERE post_type = 'fc_product'
  AND post_author = 123;
```

---

## 四、現有程式碼分析

### 4.1 ProductService 權限過濾

檔案: `/includes/services/class-product-service.php`

```php
public function getProductsWithOrderCount(array $filters = [], string $viewMode = 'frontend'): array
{
    $user = wp_get_current_user();
    $isAdmin = in_array('administrator', (array)$user->roles, true) ||
               in_array('buygo_admin', (array)$user->roles, true);

    if ($viewMode === 'frontend') {
        if (!$isAdmin) {
            // 一般賣家：只顯示自己的商品
            $query->whereHas('product', function($q) use ($user) {
                $q->where('post_author', $user->ID);
            });
        }
    }
}
```

### 4.2 FluentCartService 商品建立

檔案: `/includes/services/class-fluentcart-service.php`

```php
public function create_product($product_data, $image_ids = array())
{
    // 使用 WordPress 原生函數建立商品
    $post_data = array(
        'post_title' => sanitize_text_field($product_data['name'] ?? ''),
        'post_content' => sanitize_textarea_field($product_data['description'] ?? ''),
        'post_status' => 'publish',
        'post_type' => 'fluent-products',
    );

    // post_author 自動為當前登入用戶
    $product_id = wp_insert_post($post_data, true);

    // 儲存 LINE UID 到 post_meta
    $line_uid = $product_data['line_uid'] ?? null;
    if ($line_uid) {
        update_post_meta($product_id, '_mygo_line_user_id', $line_uid);
    }
}
```

### 4.3 賣家類型檢查

檔案: `/includes/admin/class-seller-type-field.php`

```php
class SellerTypeField {
    public static function get_seller_type($user_id) {
        $seller_type = get_user_meta($user_id, 'buygo_seller_type', true);
        return empty($seller_type) ? 'test' : $seller_type;
    }

    public static function is_test_seller($user_id) {
        return self::get_seller_type($user_id) === 'test';
    }
}
```

---

## 五、待解決問題

### 5.1 LINE UID → WordPress User 對照

**問題**: LINE Webhook 收到商品資料時，如何知道要用哪個 WordPress User 建立商品？

**現有機制**（需確認）:
1. 可能透過 `buygo-line-notify` 外掛的綁定表
2. 可能透過 `wp_usermeta` 的某個欄位

**建議**: 在 Milestone 實施時，需確認 LINE 登入綁定機制。

### 5.2 多賣家訂單隔離

**問題**: 訂單資料如何隔離？

**現有架構分析**:
- FluentCart 訂單 (`fct_orders`) 有 `customer_id` 欄位
- `fct_customers` 有 `user_id` 欄位對應 WordPress User

**建議方案**:
1. 透過 `order_items.post_id` → `posts.post_author` 反查賣家
2. 或在訂單建立時記錄 `seller_id` 到訂單 meta

---

## 六、實施建議

### 6.1 Phase 1: 確認綁定機制

```
[ ] 確認 buygo-line-notify 的 LINE UID ↔ WordPress User 綁定表結構
[ ] 確認 LINE Webhook 建立商品時如何取得 post_author
```

### 6.2 Phase 2: 強化權限過濾

```
[ ] 確保所有 API 端點都有 post_author 過濾
[ ] 新增訂單查詢的賣家過濾（透過商品關聯）
```

### 6.3 Phase 3: UI 隔離

```
[ ] 前端只顯示當前賣家的商品/訂單
[ ] 管理員可切換查看不同賣家的資料
```

---

## 七、結論

| 問題 | 答案 | 信心度 |
|------|------|--------|
| FluentCart 商品有 owner 欄位嗎？ | **有**，使用 `wp_posts.post_author` | HIGH |
| 如何識別商品屬於哪個賣家？ | 透過 `post_author` (WordPress User ID) | HIGH |
| 現有程式碼是否已使用？ | **是**，ProductService 已有權限過濾 | HIGH |
| LINE UID 存儲位置？ | `wp_postmeta._mygo_line_user_id` | HIGH |
| 需要新增資料表嗎？ | **不需要**，使用現有欄位即可 | HIGH |

**推薦實施路徑**: 直接使用 `post_author` 進行賣家隔離，無需修改資料庫結構。

---

## 參考文件

- FluentCart Product Model: `fluentcart-payuni/docs/fluentcart-reference/fluentcart.com_doc/database_models_product.md`
- FluentCart Database Analysis: `.planning/phases/21-dashboard/FLUENTCART-DATABASE-ANALYSIS.md`
- BuyGo+1 ProductService: `includes/services/class-product-service.php`
- BuyGo+1 FluentCartService: `includes/services/class-fluentcart-service.php`
- BuyGo+1 SellerTypeField: `includes/admin/class-seller-type-field.php`
