## 1. 後端：formatOrderItem 補回 child_order_id 欄位

<!-- d4: 子訂單 id 從父訂單詳情的商品行資料取得 -->
- [x] 1.1 [Tool: copilot] TDD（Order formatter includes child_order_id in item data）：在 `tests/Unit/Services/OrderFormatterChildOrderIdTest.php` 撰寫 2 個測試案例，確認 `formatOrderItem()` 回傳的 item 陣列包含 `child_order_id` 欄位（有子訂單時有值，無子訂單時為 null）。依設計決策 d4: 子訂單 id 從父訂單詳情的商品行資料取得。確認紅燈。
- [x] 1.2 [Tool: copilot] 在 `includes/services/class-order-formatter.php` 的 `formatOrderItem()` 補回 `child_order_id` 欄位：查詢 `fct_orders` WHERE `parent_id = 父訂單id AND type='split'`，取對應子訂單 id 回傳為 `child_order_id`（無則 null）。跑測試確認綠燈。

## 2. 前端：order-detail-modal 商品行加取消按鈕

<!-- d1: 複用現有 `useorders.js::cancelchildorder()` 而非新增方法 -->
<!-- d2: 取消按鈕放在商品行的分配狀態 badge 旁邊 -->
<!-- d3: v-if 條件使用 `childorder.shipping_status === 'unshipped' && childorder.status !== 'cancelled'` -->
- [x] [P] 2.1 [Tool: cursor] 實作「Cancel button visible in parent order detail page for unshipped items」：依設計決策 d2: 取消按鈕放在商品行的分配狀態 badge 旁邊，在 `components/order/order-detail-modal.php` 的「訂單明細」商品列（`v-for="item in (orderData.items || [])"`，約第 154 行），在 badge 區塊（`div.flex.flex-wrap.items-center.gap-1.5`）內，依 d3: v-if 條件使用 `childorder.shipping_status === 'unshipped' && childorder.status !== 'cancelled'`，新增取消按鈕：`<button v-if="item.child_order_id && (!item.shipping_status || item.shipping_status === 'unshipped') && item.status !== 'cancelled'" @click="cancelChildOrder({id: item.child_order_id, invoice_no: item.child_invoice_no})" class="text-[10px] md:text-xs bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 px-1.5 md:px-2 py-0.5 md:py-1 rounded-full font-medium transition">取消</button>`。
- [x] [P] 2.2 [Tool: cursor] 依設計決策 d1: 複用現有 `useorders.js::cancelchildorder()` 而非新增方法，確認 `order-detail-modal` component 可存取 `cancelChildOrder()`：檢查 `components/order/order-detail-modal.php` 的 JS setup() 區塊，確認 `cancelChildOrder` 已從 props 或 inject 傳入；若無，新增 `cancelChildOrder` prop（type: Function）並在 `orders.php` 的 `<order-detail-modal>` 標籤補上 `:cancel-child-order="cancelChildOrder"`。

## 3. 驗收

- [x] 3.1 [Tool: copilot] 執行 `composer test` 確認所有測試通過，無回歸
- [ ] 3.2 [Tool: kimi] Code Review：讀取 `class-order-formatter.php`、`order-detail-modal.php`、`useOrders.js` 的 diff，確認 d1: 複用現有 `useorders.js::cancelchildorder()` 而非新增方法、d2: 取消按鈕放在商品行的分配狀態 badge 旁邊、d3: v-if 條件使用 `childorder.shipping_status === 'unshipped' && childorder.status !== 'cancelled'`、d4: 子訂單 id 從父訂單詳情的商品行資料取得 四個決策均正確實作
