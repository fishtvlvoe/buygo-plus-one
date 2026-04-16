## Context

BuyGo+1 的訂單結構：一筆父訂單（`fct_orders`）下可有多筆子訂單（`type='split'`），每筆子訂單對應一個商品 + 一個賣家。庫存分配邏輯在 `AllocationService`，子訂單建立時佔用分配名額；但目前沒有「取消子訂單並釋放庫存」的路徑。

後台訂單詳情頁（`admin/partials/orders.php`）已用 Vue 渲染子訂單列表，已有 `childOrder.shipping_status` 的條件判斷；前端 JS 入口在 `assets/js/fluentcart-child-orders.js`。

客戶先下訂不付款，所以取消不涉及任何退款或金流操作。

## Goals / Non-Goals

**Goals:**

- 管理員可對 `shipping_status = 'unshipped'` 的子訂單執行取消
- 取消時：子訂單 `status → 'cancelled'`，且庫存名額自動釋放
- 後台 UI 僅在可取消條件下顯示取消按鈕

**Non-Goals:**

- 不處理退款
- 不支援客戶前台自助取消
- 不批次取消
- 不影響父訂單狀態
- `shipping_status` 非 `unshipped` 的子訂單不可取消

## Decisions

### cancelChildOrder 放在 AllocationService

**決定**：新增 `AllocationService::cancelChildOrder(int $child_order_id): bool|WP_Error`，負責：
1. 驗證子訂單存在且 `shipping_status = 'unshipped'`
2. 將子訂單 `status` 改為 `'cancelled'`
3. 釋放庫存：把 `fct_order_items` 對應的 `_allocated_qty` 歸零（庫存計算 SQL 已排除 cancelled 訂單，所以改狀態本身就釋放了計算上的佔用；但 meta 也要同步清除以保持一致性）

**備選方案**：放在 `OrderService::updateOrderStatus` 加 side effect → 拒絕，因為 `updateOrderStatus` 是通用方法，混入 allocation 邏輯會違反單一職責原則。

### API endpoint 掛在 ChildOrders_API

**決定**：在 `ChildOrders_API` 新增 `DELETE /child-orders/{child_order_id}`，使用現有 `API::check_permission()`（管理員權限）。

**備選方案**：掛在 `OrdersAPI` → 拒絕，子訂單操作已集中在 `ChildOrders_API`，分散會讓路由難以追蹤。

**注意**：現有 `ChildOrders_API` 用的是 customer 權限（`is_user_logged_in()`）；新的 DELETE endpoint 需要 admin 權限（`API::check_permission()`），兩者共存於同一 class，需明確分開 permission_callback。

### 前端取消按鈕插入 orders.php

**決定**：在 `admin/partials/orders.php` 的子訂單列表區塊，在已有的 `v-if="!childOrder.shipping_status || childOrder.shipping_status === 'unshipped'"` 條件區塊內，新增取消按鈕與對應的 Vue method `cancelChildOrder(childOrderId)`。按鈕點擊後：
1. 呼叫 `DELETE /wp-json/buygo-plus-one/v1/child-orders/{id}`
2. 成功後從前端列表移除該子訂單（或重新 fetch）
3. 顯示確認 dialog 避免誤觸

## Risks / Trade-offs

- **[Risk] 狀態競爭**：管理員點取消的同時，另一管理員正在分配出貨 → Mitigation：Service 層在更新前再次驗證 `shipping_status = 'unshipped'`，不符合則回傳 409 錯誤
- **[Risk] `orders.php` 有兩段子訂單區塊**（從 grep 看到行 367 和行 633 各有一段）→ Mitigation：tasks 中明確標示兩處都要修改
- **[Trade-off]** 釋放庫存只靠「改 status 為 cancelled」讓既有 SQL WHERE 自動排除，不額外更新庫存計數表 → 簡單可靠，但依賴 SQL 邏輯的隱式行為；在 tasks 中加一步清除 `_allocated_qty` meta 作為顯式保障
