## 1. 後端：OrderItemService + API endpoint

<!-- d2: 商業邏輯放在新的 orderitemservice::removeitem()，不放在 api 層 -->
<!-- d1: 新增獨立 endpoint 而非複用 fluentcart api -->
- [x] [P] 1.1 [Tool: copilot] TDD（Remove order item via API）：在 `tests/Unit/Services/OrderItemServiceTest.php` 撰寫 3 個測試案例：(a) 正常移除成功（HTTP 200，item 從 DB 刪除）；(b) completed 訂單拒絕（HTTP 422）；(c) item 不屬於該 order 拒絕（HTTP 404）。依設計決策 d2: 商業邏輯放在新的 `orderitemservice::removeitem()`，不放在 api 層。確認紅燈。
- [x] [P] 1.2 [Tool: copilot] 實作 `includes/services/class-order-item-service.php`：新增 `OrderItemService::removeItem($order_id, $item_id)` 方法，守衛條件檢查 order status（completed/cancelled 拋出 Exception），確認 item 屬於該 order，呼叫 `FluentCart\Api\Orders::update($order, $orderData, [$item_id])` 執行刪除並重算金額。依設計決策 d2。跑測試確認綠燈。
- [x] 1.3 [Tool: copilot] 新增 `includes/api/class-order-items-api.php`：註冊 REST route `DELETE /wp-json/buygo-plus-one/v1/orders/(?P<order_id>\d+)/items/(?P<item_id>\d+)`，呼叫 `API::check_permission()` 守衛，從 URL 取 `$order_id` 和 `$item_id`，呼叫 `OrderItemService::removeItem()`，成功回傳 `['success' => true]`，失敗依 Exception 回傳對應 HTTP status。依設計決策 d1: 新增獨立 endpoint 而非複用 fluentcart api。在 `class-plugin.php` 的 `load_dependencies()` 和 `register_hooks()` 中註冊新 API class。

## 2. 前端：order-detail-modal 商品行加移除按鈕

<!-- d3: 移除按鈕條件：orderdata.status !== 'completed' && orderdata.status !== 'cancelled' -->
<!-- d4: 移除後呼叫現有 loadorderdetail() 重新載入，不手動更新 dom -->
- [x] [P] 2.1 [Tool: cursor] 實作「Remove button visible for editable order items」和「Remove button triggers confirmation before proceeding」：在 `components/order/order-detail-modal.php` 的「訂單明細」商品列（`v-for="item in (orderData.items || [])"`，約第 154 行），在 badge 區塊內新增移除按鈕：`<button v-if="orderData.status !== 'completed' && orderData.status !== 'cancelled'" @click="removeOrderItem(item)" class="text-[10px] md:text-xs bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 px-1.5 md:px-2 py-0.5 md:py-1 rounded-full font-medium transition">移除</button>`。依設計決策 d3: 移除按鈕條件使用 `orderdata.status !== 'completed' && orderdata.status !== 'cancelled'`。
- [x] [P] 2.2 [Tool: cursor] 在 `components/order/order-detail-modal.php` 的 JS `setup()` 區塊新增 `removeOrderItem(item)` 方法：顯示 `showConfirm` 確認對話框（複用現有 pattern）→ 確認後呼叫 `fetch('DELETE /wp-json/buygo-plus-one/v1/orders/{orderData.id}/items/{item.id}', {method: 'DELETE', headers: {'X-WP-Nonce': wpNonce}})` → 成功後呼叫 `loadOrderDetail()`（依設計決策 d4: 移除後呼叫現有 `loadorderdetail()` 重新載入，不手動更新 dom）並顯示 success toast → 失敗顯示 error toast。將 `removeOrderItem` 加入 `return {}` 區塊。「UI updates after successful removal」需求由此實現。

## 3. 驗收

- [x] 3.1 [Tool: copilot] 執行 `composer test` 確認所有測試通過，無回歸
- [x] 3.2 [Tool: kimi] Code Review：讀取 `class-order-item-service.php`、`class-order-items-api.php`、`order-detail-modal.php` 的 diff，確認 d1: 新增獨立 endpoint 而非複用 fluentcart api、d2: 商業邏輯放在新的 `orderitemservice::removeitem()`，不放在 api 層、d3: 移除按鈕條件使用 `orderdata.status !== 'completed' && orderdata.status !== 'cancelled'`、d4: 移除後呼叫現有 `loadorderdetail()` 重新載入，不手動更新 dom 四個決策均正確實作
