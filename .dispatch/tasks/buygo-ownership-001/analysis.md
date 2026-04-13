# BuyGo 多租戶隔離分析報告

## 現有 Seller 驗證機制

### 1. class-api.php 核心權限方法

| 方法名稱 | 行號 | 用途 |
|---------|------|------|
| `check_permission()` | 83-93 | 基礎權限檢查，允許 WP 管理員、buygo_admin、buygo_helper、buygo_lister |
| `check_admin_permission()` | 100-108 | 僅限管理員（manage_options 或 buygo_add_helper）|
| `check_permission_for_api()` | 113-116 | Products_API 專用代理 |
| `check_permission_with_scope($scope)` | 127-133 | 細粒度權限檢查（products/orders/shipments/customers/settings/listing）|

### 2. RolePermissionService 關鍵方法

檔案：`includes/services/class-role-permission-service.php`

| 方法名稱 | 行號 | 用途 |
|---------|------|------|
| `get_accessible_seller_ids(?int $user_id)` | 449-491 | **核心方法** - 取得使用者可存取的所有 seller_ids |
| `is_seller(?int $user_id)` | 499-515 | 檢查是否為 buygo_admin |
| `is_helper(?int $user_id)` | 523-539 | 檢查是否為 buygo_helper |
| `can_manage_helpers()` | 550-553 | 檢查是否可以管理小幫手 |

`get_accessible_seller_ids()` 邏輯：
- buygo_admin → 返回自己的 user_id
- buygo_helper → 從 `wp_buygo_helpers` 表查詢所有綁定的 seller_ids
- 其他 → 返回空陣列

### 3. CustomerEditService::check_ownership()（現有範例）

檔案：`includes/services/class-customer-edit-service.php` 行 214-246

已實作的客戶所有權檢查邏輯：
```php
public static function check_ownership(int $customer_id): bool
{
    // 1. 管理員可存取所有
    if (current_user_can('manage_options') || current_user_can('buygo_admin')) {
        return true;
    }
    
    // 2. 取得可存取的 seller_ids
    $seller_ids = SettingsService::get_accessible_seller_ids();
    
    // 3. 透過訂單 → 訂單項目 → 商品 post_author 確認歸屬
    // 檢查該客戶是否有購買當前賣家的商品
}
```

此模式可複用於其他資源類型。

---

## 各端點清單（檔名、方法名、行號、是否已驗證）

### Products_API（class-products-api.php）

| 路由 | 方法 | 行號 | 目前驗證 | 需加 Ownership |
|-----|------|------|---------|---------------|
| `GET /products` | get_products | 275 | ❌ 僅 check_permission | ✅ 列表過濾已存在（ProductService）|
| `PUT /products/{id}` | update_product | 492 | ❌ 僅 check_permission | ✅ 需驗證商品歸屬 |
| `POST /products/batch-delete` | batch_delete | 561 | ❌ 僅 check_permission | ✅ 需驗證所有商品歸屬 |
| `GET /products/export` | export_csv | 627 | ❌ 僅 check_permission | ⚠️ 建議過濾（可接受目前行為）|
| `POST /products/{id}/image` | upload_image | 704 | ❌ 僅 check_permission | ✅ 需驗證商品歸屬 |
| `DELETE /products/{id}/image` | delete_image | 799 | ❌ 僅 check_permission | ✅ 需驗證商品歸屬 |
| `GET /products/{id}/buyers` | get_buyers | 844 | ❌ 僅 check_permission | ✅ 需驗證商品歸屬 |
| `GET /products/{id}/orders` | get_product_orders | 895 | ❌ 僅 check_permission | ✅ 需驗證商品歸屬 |
| `POST /products/allocate` | allocate_stock | 933 | ❌ 僅 check_permission | ✅ 需驗證 product_id 歸屬 |
| `POST /products/{id}/allocate-all` | allocate_all_for_customer | 1031 | ❌ 僅 check_permission | ✅ 需驗證商品歸屬 |
| `POST /products/adjust-allocation` | adjust_allocation | 1353 | ❌ 僅 check_permission | ✅ 需驗證 product_id 歸屬 |
| `GET /products/{id}/variations` | get_product_variations | 1182 | ❌ 僅 check_permission | ✅ 需驗證商品歸屬 |
| `GET /variations/{id}/stats` | get_variation_stats | 1214 | ❌ 僅 check_permission | ✅ 需驗證 variation 歸屬 |
| `PUT /variations/{id}` | update_variation | 1248 | ❌ 僅 check_permission | ✅ 需驗證 variation 歸屬 |
| `GET /products/limit-check` | check_seller_limit | 1314 | ❌ 僅 check_permission | ❌ 無需（基於當前使用者）|

### Orders_API（class-orders-api.php）

| 路由 | 方法 | 行號 | 目前驗證 | 需加 Ownership |
|-----|------|------|---------|---------------|
| `GET /orders` | get_orders | 156 | ❌ 僅 check_permission | ✅ 列表過濾（OrderService 已實作 seller_id 過濾）|
| `GET /orders/{id}` | get_order | 223 | ❌ 僅 check_permission | ✅ 需驗證訂單歸屬 |
| `PUT /orders/{id}/status` | update_order_status | 251 | ❌ 僅 check_permission | ✅ 需驗證訂單歸屬 |
| `PUT /orders/{id}/shipping-status` | update_shipping_status | 289 | ❌ 僅 check_permission | ✅ 需驗證訂單歸屬 |
| `POST /orders/{id}/ship` | ship_order | 328 | ❌ 僅 check_permission | ✅ 需驗證訂單歸屬 |
| `POST /orders/{id}/split` | split_order | 384 | ❌ 僅 check_permission | ✅ 需驗證訂單歸屬 |
| `POST /orders/{id}/prepare` | prepare_order | 718 | ❌ 僅 check_permission | ✅ 需驗證訂單歸屬 |

### Customers_API（class-customers-api.php）

| 路由 | 方法 | 行號 | 目前驗證 | 需加 Ownership |
|-----|------|------|---------|---------------|
| `GET /customers` | get_customers | 106 | ❌ 僅 check_permission | ✅ 列表過濾已實作（行 136-159）|
| `GET /customers/{id}` | get_customer | 292 | ❌ 僅 check_permission | ✅ 需驗證客戶歸屬 |
| `PUT /customers/{id}` | update_customer | 437 | ✅ **已驗證**（行 448-454）| ✅ 已實作（CustomerEditService::check_ownership）|
| `PUT /customers/{id}/note` | update_note | 465 | ❌ 僅 check_permission | ✅ 需驗證客戶歸屬 |

### Shipments_API（class-shipments-api.php）

| 路由 | 方法 | 行號 | 目前驗證 | 需加 Ownership |
|-----|------|------|---------|---------------|
| `GET /shipments` | get_shipments | 308 | ❌ check_permission | ✅ 列表過濾已實作（行 327-340）|
| `POST /shipments` | create_shipment | 565 | ❌ check_permission | ⚠️ 使用當前 user_id 作 seller_id（可接受）|
| `GET /shipments/{id}` | get_shipment | 539 | ❌ check_permission | ✅ 需驗證出貨單歸屬 |
| `PUT /shipments/{id}` | update_shipment | 602 | ❌ check_permission | ✅ 需驗證出貨單歸屬 |
| `POST /shipments/batch-mark-shipped` | batch_mark_shipped | 664 | ❌ check_permission | ✅ 需驗證所有出貨單歸屬 |
| `POST /shipments/{id}/archive` | archive_shipment | 706 | ❌ check_permission | ✅ 需驗證出貨單歸屬 |
| `POST /shipments/merge` | merge_shipments | 756 | ❌ check_permission | ✅ 需驗證所有出貨單歸屬 |
| `POST /shipments/batch-archive` | batch_archive_shipments | 857 | ❌ check_permission | ✅ 需驗證所有出貨單歸屬 |
| `GET /shipments/{id}/detail` | get_shipment_detail | 910 | ❌ check_permission | ✅ 需驗證出貨單歸屬 |
| `GET /shipments/export` | export_shipments | 1047 | ❌ check_permission | ⚠️ 建議過濾 |
| `POST /shipments/{id}/transfer` | transfer_to_shipment | 1293 | ❌ check_permission | ✅ 需驗證出貨單歸屬 |
| `POST /shipments/batch-transfer` | batch_transfer_to_shipment | 1368 | ❌ check_permission | ✅ 需驗證所有出貨單歸屬 |

---

## 建議 Guard 設計

### 1. 核心 Guard 方法（建議加入 class-api.php）

```php
/**
 * 驗證資源所有權
 * 
 * 檢查當前使用者是否有權存取指定資源。
 * 管理員始終有權限，小幫手只能存取授權賣家的資源。
 *
 * @param string $resource_type 資源類型：'product', 'order', 'customer', 'shipment', 'variation'
 * @param int    $resource_id   資源 ID
 * @return bool|WP_Error 有權限回傳 true，無權限回傳 WP_Error
 */
public static function verify_ownership(string $resource_type, int $resource_id)
```

### 2. 輔助方法

```php
/**
 * 驗證商品所有權
 * 邏輯：檢查商品的 post_author 是否在 accessible_seller_ids 中
 * 
 * @param int $product_id 商品 ID（post_id 或 variation_id）
 * @return bool|WP_Error
 */
public static function verify_product_ownership(int $product_id)

/**
 * 驗證訂單所有權
 * 邏輯：檢查訂單中是否有任何商品屬於當前 seller
 * 
 * @param int $order_id 訂單 ID
 * @return bool|WP_Error
 */
public static function verify_order_ownership(int $order_id)

/**
 * 驗證客戶所有權
 * 邏輯：檢查客戶是否購買過當前 seller 的商品
 * 
 * @param int $customer_id 客戶 ID
 * @return bool|WP_Error
 */
public static function verify_customer_ownership(int $customer_id)

/**
 * 驗證出貨單所有權
 * 邏輯：檢查出貨單的 seller_id 是否等於當前 seller
 * 
 * @param int $shipment_id 出貨單 ID
 * @return bool|WP_Error
 */
public static function verify_shipment_ownership(int $shipment_id)
```

### 3. 方法參數與回傳值

| 方法 | 參數 | 回傳值 | 錯誤碼 |
|-----|------|-------|-------|
| `verify_ownership()` | `$resource_type`, `$resource_id` | `true` 或 `WP_Error` | `invalid_resource_type`, `resource_not_found`, `access_denied` |
| `verify_product_ownership()` | `$product_id` | `true` 或 `WP_Error` | `product_not_found`, `access_denied` |
| `verify_order_ownership()` | `$order_id` | `true` 或 `WP_Error` | `order_not_found`, `access_denied` |
| `verify_customer_ownership()` | `$customer_id` | `true` 或 `WP_Error` | `customer_not_found`, `access_denied` |
| `verify_shipment_ownership()` | `$shipment_id` | `true` 或 `WP_Error` | `shipment_not_found`, `access_denied` |

### 4. 呼叫層級建議

**推薦做法**：在 API 方法**開頭**呼叫，統一阻擋未授權存取：

```php
public function get_order($request) {
    $order_id = $request['id'];
    
    // 在 API 層呼叫 guard
    $ownership_check = API::verify_order_ownership($order_id);
    if (is_wp_error($ownership_check)) {
        return $ownership_check;
    }
    
    // ... 原有邏輯
}
```

**替代做法**：在 Service 層加入（如 CustomerEditService），但 API 層仍需驗證回傳值。

---

## 修改建議（每個端點要加什麼、加在哪裡）

### Products_API 修改

| 方法 | 行號 | 修改內容 |
|-----|------|---------|
| `update_product()` | 492 | 開頭加入 `API::verify_product_ownership($id)` |
| `batch_delete()` | 561 | foreach 前加入 `API::verify_product_ownership($id)` 檢查所有 IDs |
| `upload_image()` | 704 | 開頭加入 `API::verify_product_ownership($id)` |
| `delete_image()` | 799 | 開頭加入 `API::verify_product_ownership($id)` |
| `get_buyers()` | 844 | 開頭加入 `API::verify_product_ownership($product_id)` |
| `get_product_orders()` | 895 | 開頭加入 `API::verify_product_ownership($product_id)` |
| `allocate_stock()` | 933 | 開頭加入 `API::verify_product_ownership($product_id)` |
| `allocate_all_for_customer()` | 1031 | 開頭加入 `API::verify_product_ownership($product_id)` |
| `adjust_allocation()` | 1353 | 開頭加入 `API::verify_product_ownership($product_id)` |
| `get_product_variations()` | 1182 | 開頭加入 `API::verify_product_ownership($product_id)` |
| `get_variation_stats()` | 1214 | 開頭加入 `API::verify_variation_ownership($variation_id)` |
| `update_variation()` | 1248 | 開頭加入 `API::verify_variation_ownership($variation_id)` |

### Orders_API 修改

| 方法 | 行號 | 修改內容 |
|-----|------|---------|
| `get_order()` | 223 | 開頭加入 `API::verify_order_ownership($order_id)` |
| `update_order_status()` | 251 | 開頭加入 `API::verify_order_ownership($order_id)` |
| `update_shipping_status()` | 289 | 開頭加入 `API::verify_order_ownership($order_id)` |
| `ship_order()` | 328 | 開頭加入 `API::verify_order_ownership($order_id)` |
| `split_order()` | 384 | 開頭加入 `API::verify_order_ownership($order_id)` |
| `prepare_order()` | 718 | 開頭加入 `API::verify_order_ownership($order_id)` |

### Customers_API 修改

| 方法 | 行號 | 修改內容 |
|-----|------|---------|
| `get_customer()` | 292 | 開頭加入 `API::verify_customer_ownership($customer_id)` |
| `update_note()` | 465 | 開頭加入 `API::verify_customer_ownership($customer_id)` |
| `update_customer()` | 437 | 已實作（檢查 CustomerEditService::check_ownership），可保持不變 |

### Shipments_API 修改

| 方法 | 行號 | 修改內容 |
|-----|------|---------|
| `get_shipment()` | 539 | 開頭加入 `API::verify_shipment_ownership($shipment_id)` |
| `update_shipment()` | 602 | 開頭加入 `API::verify_shipment_ownership($shipment_id)` |
| `batch_mark_shipped()` | 664 | foreach 加入 `API::verify_shipment_ownership($id)` 檢查所有 IDs |
| `archive_shipment()` | 706 | 開頭加入 `API::verify_shipment_ownership($shipment_id)` |
| `merge_shipments()` | 756 | 開頭加入 `API::verify_shipment_ownership()` 檢查所有 IDs |
| `batch_archive_shipments()` | 857 | 開頭加入 `API::verify_shipment_ownership()` 檢查所有 IDs |
| `get_shipment_detail()` | 910 | 開頭加入 `API::verify_shipment_ownership($shipment_id)` |
| `transfer_to_shipment()` | 1293 | 開頭加入 `API::verify_shipment_ownership($shipment_id)` |
| `batch_transfer_to_shipment()` | 1368 | 開頭加入 `API::verify_shipment_ownership()` 檢查所有 IDs |

---

## 實作優先順序

### P0（最高優先）
1. `API::verify_product_ownership()` - 影響範圍最大
2. `API::verify_order_ownership()` - 敏感資料
3. `API::verify_customer_ownership()` - 敏感資料
4. `API::verify_shipment_ownership()` - 敏感資料

### P1
5. 所有單筆資源端點加入驗證

### P2
6. 批次操作加入驗證
7. 測試覆蓋

---

## 參考現有實作

### CustomerEditService::check_ownership()（已驗證可用）
檔案：`includes/services/class-customer-edit-service.php` 行 214-246

邏輯：
1. 管理員直接放行
2. 取得 `get_accessible_seller_ids()`
3. 透過訂單關聯查詢確認客戶是否購買過賣家商品

### OrderService 列表過濾（已驗證可用）
檔案：`includes/services/class-order-service.php`

已實作 seller_id 過濾邏輯，列表查詢已正確隔離。

### Shipments_API::get_shipments() 列表過濾（已驗證可用）
檔案：`includes/api/class-shipments-api.php` 行 327-340

已實作 `seller_id IN (...)` 過濾。

---

## SQL 參考模式

### 商品所有權驗證
```sql
-- 取得商品的 post_author
SELECT post_author FROM wp_posts WHERE ID = {product_id}
-- 或 variation 的 parent post_author
SELECT p.post_author FROM wp_posts p 
JOIN wp_fct_product_variations v ON p.ID = v.post_id 
WHERE v.id = {variation_id}
```

### 訂單所有權驗證
```sql
-- 檢查訂單中是否有商品屬於指定 sellers
SELECT COUNT(DISTINCT o.id)
FROM wp_fct_orders o
JOIN wp_fct_order_items oi ON o.id = oi.order_id
JOIN wp_posts p ON oi.post_id = p.ID OR oi.post_id = p.post_parent
WHERE o.id = {order_id} AND p.post_author IN ({seller_ids})
```

### 出貨單所有權驗證
```sql
-- 直接檢查 seller_id 欄位
SELECT seller_id FROM wp_buygo_shipments WHERE id = {shipment_id}
```

---

*分析完成時間：2026-04-04*
*分析工具：Kimi CLI*
