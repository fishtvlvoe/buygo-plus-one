## 1. Service 層：cancelChildOrder 放在 AllocationService（Admin can cancel an unshipped child order、Inventory released on child order cancellation）

- [x] 1.1 [Tool: codex] 在 `includes/services/class-allocation-service.php` 新增 `cancelChildOrder(int $child_order_id): bool|WP_Error` 方法（實作 "Admin can cancel an unshipped child order" 需求）：驗證子訂單存在且 `shipping_status = 'unshipped'`（否則回傳對應 WP_Error），將 `status` 更新為 `'cancelled'`，並將 `fct_order_items` 對應列的 `_allocated_qty`（line_meta JSON 欄位）清為 0 以確保 "Inventory released on child order cancellation"，實作 concurrent cancellation guard（再次檢查 shipping_status，不符回傳 STATUS_CONFLICT 錯誤）
- [x] 1.2 [Tool: codex] 為 `cancelChildOrder` 撰寫 PHPUnit 測試，覆蓋：成功取消（驗證 inventory released on child order cancellation）、已出貨拒絕（CANNOT_CANCEL_SHIPPED）、已取消拒絕（ALREADY_CANCELLED）、找不到訂單（NOT_FOUND）、狀態競爭（STATUS_CONFLICT）；執行 `composer test` 確認全綠

## 2. API 層：API endpoint 掛在 ChildOrders_API（Admin can cancel an unshipped child order）

- [x] 2.1 [Tool: copilot] 在 `includes/api/class-child-orders-api.php` 的 `register_routes()` 新增 `DELETE /child-orders/(?P<child_order_id>\d+)` endpoint（API endpoint 掛在 ChildOrders_API），`permission_callback` 使用 `API::check_permission()`（管理員權限，與現有 GET 的 customer 權限分開），callback 呼叫 `AllocationService::cancelChildOrder()`，將 WP_Error 對應轉為 HTTP 404/409/422/500，成功回傳 HTTP 200 `{ "success": true }`
- [x] 2.2 [Tool: codex] 為 DELETE endpoint 撰寫整合測試，覆蓋：admin can cancel an unshipped child order（HTTP 200）、非管理員回傳 403、shipping_status 非 unshipped 回傳 422、子訂單不存在回傳 404；執行 `composer test` 確認全綠

## 3. 前端 UI：前端取消按鈕插入 orders.php（Cancel button shown only for cancellable child orders）

- [x] 3.1 [Tool: cursor] 實作 "Cancel button shown only for cancellable child orders"：在 `admin/partials/orders.php` 第 381 行區塊（`v-if="!childOrder.shipping_status || childOrder.shipping_status === 'unshipped'"`）內，新增取消按鈕元件（`<button @click="confirmCancelChildOrder(childOrder.id)">`），按鈕文字「取消子訂單」，樣式參照同頁其他危險操作按鈕
- [x] 3.2 [Tool: cursor] 在 `admin/partials/orders.php` 第 652 行同條件區塊（第二個子訂單列表）新增相同取消按鈕，確保兩處一致
- [x] 3.3 [Tool: cursor] 在 `assets/js/fluentcart-child-orders.js`（或 orders.php 的 Vue methods 區塊）新增 `confirmCancelChildOrder(childOrderId)` method：顯示 `window.confirm()` 確認 dialog，確認後呼叫 `DELETE /wp-json/buygo-plus-one/v1/child-orders/{childOrderId}`，成功後從 `childOrders` 陣列移除該筆（或觸發重新 fetch），失敗時以 `alert()` 或 toast 顯示錯誤訊息

## 4. 驗收

- [x] 4.1 [Tool: codex] 執行完整測試套件 `composer test`，確認全部通過、無回歸
- [x] 4.2 [Tool: kimi] Code Review：讀取 `class-allocation-service.php`、`class-child-orders-api.php`、`admin/partials/orders.php` 的 diff，確認：cancelChildOrder 邏輯正確、API 權限分離正確、UI 兩處都已更新、無遺漏 edge case
