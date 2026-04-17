## Context

BuyGo 後台訂單詳情頁（`components/order/order-detail-modal.php`）顯示商品明細但無操作按鈕。FluentCart 已實作完整的 Remove Item 邏輯：`FluentCart\Api\Orders::update()` 接受 `$deleteItems` 陣列，執行 `OrderItem::destroy($deleteItems)` 直接從 `fct_order_items` 刪除，並重新計算訂單金額。

BuyGo 需要一個代理 endpoint，讓前端在不離開 BuyGo 後台的情況下呼叫此邏輯，同時保持 BuyGo 的權限管控。

## Goals / Non-Goals

**Goals:**

- 新增 `DELETE /wp-json/buygo-plus-one/v1/orders/{order_id}/items/{item_id}` endpoint
- 在 `order-detail-modal.php` 商品列加「移除」按鈕，呼叫上述 endpoint
- 移除後重新載入訂單詳情，反映最新金額

**Non-Goals:**

- 不修改 FluentCart 的 Edit 模式
- 不批次移除
- 不處理退款
- 不調整折扣 / 運費重算邏輯（由 FluentCart 處理）

## Decisions

### D1: 新增獨立 endpoint 而非複用 FluentCart API

**理由：** 直接從前端呼叫 FluentCart 的 admin REST API 需要 FluentCart 的 nonce，繞過 BuyGo 的權限控制。透過 BuyGo endpoint 代理，可沿用現有 `API::check_permission()` 守衛，維持一致的 auth 模式。

**替代方案：** 前端直接打 FluentCart API — 否決，因為繞過 BuyGo auth。

### D2: 商業邏輯放在新的 OrderItemService::removeItem()，不放在 API 層

**理由：** 遵守架構規範：`includes/api/` 只做驗證和路由，商業邏輯放 `includes/services/`。`OrderItemService::removeItem($order_id, $item_id)` 負責守衛條件（order status 不能是 completed/cancelled）與呼叫 FluentCart `Orders::update()`。

### D3: 移除按鈕條件：orderData.status !== 'completed' && orderData.status !== 'cancelled'

**理由：** 對齊 FluentCart 後端守衛（completed 訂單拋出 Exception）。條件放在訂單層級而非商品行層級，因為 order_item 沒有獨立 status 欄位。

### D4: 移除後呼叫現有 loadOrderDetail() 重新載入，不手動更新 DOM

**理由：** `loadOrderDetail()` 已有完整的訂單資料載入邏輯，重新呼叫可確保金額、商品列表與後端同步，避免前端手動計算金額產生的一致性問題。

## Risks / Trade-offs

- **Risk: FluentCart Orders::update() 介面變動** → 版本升級時需重新確認 method signature。Mitigation：在 `OrderItemService` 中封裝呼叫，隔離依賴點。
- **Trade-off: 每次移除觸發一次完整 loadOrderDetail()** → 小額 N+1，但父訂單商品數通常 < 10 筆，可接受。

## Implementation Distribution Strategy

| 任務 | Agent | 原因 |
|------|-------|------|
| 1.1 TDD + OrderItemService::removeItem() | Copilot | 後端邏輯 + 測試 |
| 1.2 API endpoint class-order-items-api.php | Copilot | API 路由 |
| 2.1 前端移除按鈕 | Cursor | UI template |
| 3.1 composer test 驗收 | Copilot | 跑測試 |
| 3.2 Code Review | Kimi | 多檔案 review |
