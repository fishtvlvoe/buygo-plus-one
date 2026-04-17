## Why

管理員在父訂單詳情頁無法直接取消單一商品行——當客戶下訂多件商品後只要其中幾件時，目前沒有對應的 UI 入口，必須繞到其他頁面操作，流程不直覺。

## What Changes

- 在父訂單詳情頁（`components/order/order-detail-modal.php`）的「訂單明細」商品列，對每個 `shipping_status = 'unshipped'` 的商品行顯示「取消」按鈕
- 點擊按鈕後呼叫現有的 `DELETE /child-orders/{id}` endpoint，取消對應子訂單並釋放庫存
- 複用現有的 `showConfirm + showToast` UX 模式（與 `cancelChildOrder()` 一致）
- `formatOrderItem()` 補回 `child_order_id` 欄位，讓前端取消按鈕知道要呼叫哪個子訂單 ID

## Non-Goals

- 不修改後端 `AllocationService::cancelChildOrder()` 邏輯
- 不修改 `DELETE /child-orders/{id}` API
- 不取消整筆父訂單
- 不處理退款（無付款機制）

## Capabilities

### New Capabilities

（無）

### Modified Capabilities

- `cancel-child-order`：在父訂單詳情頁的商品明細列新增取消按鈕 UI 入口，讓管理員可直接取消單一未出貨商品行；`formatOrderItem()` 補回 `child_order_id` 欄位

## Impact

- Affected specs: `cancel-child-order`（新增 UI 入口場景）
- Affected code:
  - `includes/services/class-order-formatter.php`（`formatOrderItem()` 補 `child_order_id`）
  - `components/order/order-detail-modal.php`（商品明細列加取消按鈕）
  - `includes/views/composables/useOrders.js`（確認 `cancelChildOrder()` 可在 modal scope 存取）
  - `tests/Unit/Services/OrderFormatterChildOrderIdTest.php`（新增）
