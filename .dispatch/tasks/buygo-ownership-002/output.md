# buygo-ownership-002 執行結果

**完成時間：** 2026-04-04
**測試結果：** 227 / 227 passed（含新增 18 個 OwnershipGuard 測試）

---

## 完成項目

### 1. Bootstrap 更新（tests/bootstrap-unit.php）
- `current_user_can()` 改為透過 `$GLOBALS['mock_current_user_can']` 可程式化控制
- `get_current_user_id()` 改為透過 `$GLOBALS['mock_current_user_id']` 可程式化控制
- 新增 `wpdb::get_col()` mock（支援小幫手 seller_id 查詢）
- 新增載入 `class-customer-edit-service.php` 與 `class-api.php`

### 2. 測試（TDD 先行）
**新增：** `tests/Unit/Services/OwnershipGuardTest.php`
- 18 個測試案例，覆蓋：
  - 管理員（manage_options 或 buygo_admin）永遠放行
  - Helper 只能存取授權 seller 的資源
  - 跨 seller 存取回傳 403 WP_Error（code: `access_denied`）
  - 資源不存在回傳 404 WP_Error（code: `product_not_found` / `shipment_not_found`）

### 3. Guard 方法（includes/api/class-api.php）

新增 5 個 static 方法（Guard Methods 區塊）：

| 方法 | 邏輯 |
|-----|------|
| `verify_product_ownership(int $product_id)` | 查 wp_posts.post_author，比對 accessible_seller_ids |
| `verify_variation_ownership(int $variation_id)` | 透過 fct_product_variations JOIN wp_posts |
| `verify_order_ownership(int $order_id)` | COUNT 訂單項目中屬於 seller 的商品 |
| `verify_customer_ownership(int $customer_id)` | 委派 CustomerEditService::check_ownership() |
| `verify_shipment_ownership(int $shipment_id)` | 查 wp_buygo_shipments.seller_id |

### 4. API 端點修改

#### Products API（12 個端點）
| 端點 | 方法 | Guard |
|-----|------|-------|
| PUT /products/{id} | update_product | verify_product_ownership($id) |
| POST /products/batch-delete | batch_delete | verify_product_ownership（foreach 所有 IDs） |
| POST /products/{id}/image | upload_image | verify_product_ownership($id) |
| DELETE /products/{id}/image | delete_image | verify_product_ownership($id) |
| GET /products/{id}/buyers | get_buyers | verify_product_ownership($product_id) |
| GET /products/{id}/orders | get_product_orders | verify_product_ownership($product_id) |
| POST /products/allocate | allocate_stock | verify_product_ownership($product_id) |
| POST /products/{id}/allocate-all | allocate_all_for_customer | verify_product_ownership($product_id) |
| POST /products/adjust-allocation | adjust_allocation | verify_product_ownership($product_id) |
| GET /products/{id}/variations | get_product_variations | verify_product_ownership($product_id) |
| GET /variations/{id}/stats | get_variation_stats | verify_variation_ownership($variation_id) |
| PUT /variations/{id} | update_variation | verify_variation_ownership($variation_id) |

#### Orders API（6 個端點）
全部加入 `verify_order_ownership($order_id)`：
- get_order、update_order_status、update_shipping_status、ship_order、split_order、prepare_order

#### Customers API（2 個端點）
| 端點 | 備注 |
|-----|------|
| GET /customers/{id} → get_customer | 新增 verify_customer_ownership |
| PUT /customers/{id}/note → update_note | 新增 verify_customer_ownership |
| PUT /customers/{id} → update_customer | 已有 CustomerEditService::check_ownership，未改動 |

#### Shipments API（9 個端點）
全部加入 `verify_shipment_ownership`，批次操作使用 foreach 逐一驗證：
- get_shipment、update_shipment、archive_shipment、get_shipment_detail、transfer_to_shipment
- batch_mark_shipped（foreach）、merge_shipments（foreach）、batch_archive_shipments（foreach）、batch_transfer_to_shipment（foreach）

---

## 設計原則

- **管理員永遠放行**（manage_options 或 buygo_admin）
- **無權限一律回傳** `WP_Error('access_denied', '無存取權限', ['status' => 403])`
- **資源不存在回傳** 404 WP_Error（而非 403）
- **Guard 在 API 層最前端呼叫**，不影響 Service 層邏輯
