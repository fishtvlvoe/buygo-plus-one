## Why

BuyGo 後台的訂單詳情頁沒有操作按鈕，管理員無法直接移除訂單內的單一商品行。當客戶下訂多件商品但只要其中部分時，目前必須切換到 FluentCart 後台才能操作，流程不直覺。

## What Changes

- 在 BuyGo 後台訂單詳情頁（`order-detail-modal.php`）的商品列，對可編輯訂單的每個商品行顯示「移除」按鈕
- 點擊後透過確認對話框，呼叫新增的 BuyGo API endpoint，從訂單中刪除該商品行並重新計算金額
- 新增後端 API endpoint：`DELETE /orders/{order_id}/items/{item_id}`，內部呼叫 FluentCart 的 `Orders::update()` 帶 `deleteItems` 參數執行刪除

## Non-Goals

- 不修改 FluentCart 本身的 Edit 模式或 Remove Item 功能
- 不支援批次移除（一次只能移除一個商品行）
- 不處理退款（系統無付款機制）
- 不支援已完成（completed）或已取消（cancelled）訂單的商品行移除
- 不調整訂單折扣或運費的重新計算邏輯（交由 FluentCart 處理）

## Capabilities

### New Capabilities

- `remove-order-item`: 在 BuyGo 後台訂單詳情頁移除單一商品行，透過新 API endpoint 呼叫 FluentCart 刪除邏輯

### Modified Capabilities

（無，現有 spec 層級行為不變）

## Impact

- Affected specs: `remove-order-item`（新增）
- Affected code:
  - `includes/api/`：新增 `class-order-items-api.php`（`DELETE /orders/{order_id}/items/{item_id}` endpoint）
  - `components/order/order-detail-modal.php`：商品列新增移除按鈕與呼叫邏輯
  - `includes/views/composables/useOrders.js`：確認現有 `loadOrders()` 或新增 `removeOrderItem()` 方法
