## Context

父訂單詳情頁（`components/order/order-detail-modal.php`）目前只顯示商品明細（品名、數量、金額、分配狀態），沒有任何操作按鈕。當客戶下訂多件商品後只想保留部分時，管理員無法在這個頁面直接取消單一商品行，必須繞到其他頁面操作。

取消子訂單的後端邏輯（`AllocationService::cancelChildOrder()`）與 API（`DELETE /child-orders/{id}`）已完整實作並通過測試。前端 composable（`useOrders.js`）也已有 `cancelChildOrder(childOrder)` 方法（使用 showConfirm + showToast 模式）。

`OrderFormatter::formatOrderItem()` 原本沒有回傳 `child_order_id`，已於 Task 1.1/1.2 補齊（查 `fct_orders` WHERE `parent_id = 父訂單id AND type='split'` 取對應子訂單 id）。

本次變更：後端補欄位 + UI 入口補齊，不涉及取消邏輯修改。

## Goals / Non-Goals

**Goals:**

- `formatOrderItem()` 回傳 `child_order_id`（有子訂單則為 id，無則為 null）
- 在父訂單詳情頁的「訂單明細」商品列，對 `shipping_status = 'unshipped'` 且 `status != 'cancelled'` 的商品行顯示取消按鈕
- 複用現有 `cancelChildOrder()` 方法，不重複實作

**Non-Goals:**

- 不修改後端 `AllocationService::cancelChildOrder()`
- 不修改 `DELETE /child-orders/{id}` API
- 不取消整筆父訂單
- 不處理退款

## Decisions

### D1: 複用現有 `useorders.js::cancelchildorder()` 而非新增方法

**理由：** `cancelChildOrder(childOrder)` 已實作 showConfirm + fetch DELETE + showToast + loadOrders() 完整流程，直接在 template 呼叫即可。不需要新增方法，避免重複邏輯。

**替代方案：** 新增 `cancelOrderItem()` wrapper — 否決，因為行為完全相同。

### D2: 取消按鈕放在商品行的分配狀態 badge 旁邊

**理由：** 與現有子訂單列表頁的取消按鈕位置一致（badge 旁），視覺關聯性強。按鈕使用紅色 pill 樣式（`bg-red-50 text-red-600 border border-red-200`），與 badge 同高同圓角。

### D3: v-if 條件使用 `childorder.shipping_status === 'unshipped' && childorder.status !== 'cancelled'`

**理由：** 對齊後端 `cancelChildOrder()` 的守衛條件。`item.child_order_id` 需存在才顯示按鈕（無子訂單的商品行不能取消）。

### D4: 子訂單 id 從父訂單詳情的商品行資料取得

**理由：** `formatOrderItem()` 已補 `child_order_id` 欄位，前端直接取 `item.child_order_id` 傳給 `cancelChildOrder({id: item.child_order_id})`，不需要額外 API 呼叫。

## Implementation Distribution Strategy

| 任務 | Agent | 原因 |
|------|-------|------|
| 1.1-1.2 TDD + formatOrderItem | Copilot | 後端邏輯 + 測試（已完成） |
| 2.1-2.2 UI template | Cursor | 前端 template 修改 |
| 3.1 驗收測試 | Copilot | 跑 composer test |
| 3.2 Code Review | Kimi | 多檔案 review |

預估 token 成本：< 5K tokens（Cursor 為零 token）

## Risks / Trade-offs

- **Risk: modal 的 cancelChildOrder 作用域** — `order-detail-modal` 是獨立 Vue component，需確認 `cancelChildOrder` 是透過 props/emit 傳入還是 inject。若無法直接呼叫需補 emit。
- **Trade-off: child_order_id 查詢效能** — 每個 item 各查一次子訂單，N+1 問題。父訂單明細商品數通常 < 10 筆，可接受。未來可優化為批次查詢。
