## Why

多變體商品（如 post_id=2650「日本皮克敏髮夾四款」共有 ABCD 四個 variation）在分配庫存時，BCD 變體訂單建出的子訂單 `object_id` 全部被錯寫成 A 變體（id 最小者），導致 BCD 訂單永遠顯示「待分配」、A 變體採購池被異常吃光。客戶當前症狀為「A 順利、BCD 沒法分配」。

**正式站證據（buygo.instawp.xyz post_id=2650 已確認）**：
- A (1038)：採購 7、需求 7、實際子訂單 7（5 筆，全部 object_id=1038）
- B (1039)：採購 4、需求 4、子訂單 0
- C (1040)：採購 4、需求 4、子訂單 0
- D (1041)：採購 meta 缺失、需求 4、子訂單 0
- 5 個 A 子訂單實際對應的父訂單需求其實是跨 ABCD 的，但全部錯寫成 A

## What Changes

- **修正 `AllocationWriteService::createChildOrder` 的父訂單品項查詢**：`object_id IN ($var_placeholders)` 改為 `object_id = %d`，由 caller 明確傳入目標 variation_id，杜絕「取到第一筆 = 永遠是 A」的跨變體污染（致命 bug）
- **修正 `AllocationBatchService::allocateAllForCustomer` 的 allocations 結構**：從 `[order_id => qty]` 改為 `[order_item_id => ['order_id' => X, 'object_id' => Y, 'quantity' => Z]]`，避免同一訂單多變體時 needed 互相覆蓋
- **擴充 `AllocationWriteService::updateOrderAllocations` 介面**：支援 per-item 分配（用 `order_item_id` 或 `[order_id, object_id]` 複合鍵），保留原有 `[order_id => qty]` 格式相容（單變體商品仍可用）
- **擴充 `Products_API::allocate_stock` 的請求格式**：接受 `[{order_id, object_id, allocated}]` 陣列形式，前端 admin/partials/products.php 已是此格式（line 1011）只需後端正確處理 `object_id` 欄位
- **新增資料修復 WP-CLI 命令** `bin/fix-cross-variant-child-orders.php`：(a) 將 5 筆 object_id=1038 但父訂單需求為 BCD 的子訂單按比例還原；(b) 補 D 變體 `_buygo_purchased` meta；(c) 重算 post_meta `_buygo_allocated`
- **補 PHPUnit 測試**：跨變體分配、多變體一鍵分配、單筆 BCD 分配、跨變體採購池檢核

## Non-Goals

- 不處理 D 變體採購流程缺 `_buygo_purchased` meta 的根因（採購流程的另一個 bug，本 change 只用一次性修復補上目前缺值）
- 不改前端 UI（後端正確處理 `object_id` 後前端會自然顯示對）
- 不重構整個分配流程架構（只修兩個明確 bug）
- 不處理採購池跨變體共用的「業務語意」討論（系統允許跨變體共用採購池，本次不變更此行為，僅確保子訂單變體正確）

## Capabilities

### New Capabilities

(none)

### Modified Capabilities

- `allocation-variation-filter`: 新增「子訂單建立時必須對應正確變體」的需求，補上「多變體訂單一鍵分配」的場景，補上「allocations 請求格式變動」的需求

## Impact

- Affected specs: `allocation-variation-filter`
- Affected code:
  - Modified: `includes/services/class-allocation-write-service.php`（`createChildOrder` 加 `$variation_id` 參數、SQL 改精確過濾；`updateOrderAllocations` 介面擴充）
  - Modified: `includes/services/class-allocation-batch-service.php`（`allocateAllForCustomer` 改用 `order_item_id` 為 key）
  - Modified: `includes/api/class-products-api.php`（`allocate_stock` 處理 `object_id` 欄位）
  - New: `tests/Unit/Services/AllocationCrossVariantTest.php`
  - New: `bin/fix-cross-variant-child-orders.php`（一次性 WP-CLI 資料修復）
- Affected DB（執行修復腳本後）：
  - `wp_fct_order_items`：5 筆子訂單 object_id 修正
  - `wp_fct_meta`：D 變體（id=1041）`_buygo_purchased` 補值
  - `wp_postmeta`：post_id=2650 `_buygo_allocated` 重算
