## Problem

商品分配統計在三個讀取點各自取值不一致：
- 分配詳情頁卡片：從 child orders 即時計算 → 看到正確值
- 下單名單頁卡片 / 表格：加總 parent `fct_order_items.line_meta._allocated_qty` → 看到 0
- 商品列表頁：讀 `wp_postmeta` 的 `_buygo_allocated` → 看到 0

實機驗證 production（buygo.me，本機 main 與 production md5 一致）product post_id=1055：
- `_buygo_allocated` post_meta = `''`（空 → 讀為 0）
- 2 個 parent order_items `_allocated_qty` 加總 = 0
- 2 個 child orders（id 1753、1754）status=shipped 數量加總 = **2**（真相）

對比 product post_id=1258 三層全為 52（一致），證明 schema 健康，bug 來自部分歷史資料寫入時漏 sync `_allocated_qty` line_meta 與 `_buygo_allocated` post_meta。

## Root Cause

BuyGo+1 把「已分配」分散儲存到三處：
1. 即時來源：`fct_orders` 中 `parent_id IS NOT NULL AND type='split'` 的 child order_items quantity
2. 快照 1：parent order_items 的 `line_meta._allocated_qty`
3. 快照 2：商品 post 的 `_buygo_allocated` post_meta

寫入端在建立 child order 時，多個程式路徑（舊版分配 API、批次操作、出貨流程）未一律呼叫 sync，導致快照與真相發散。讀取端各自挑不同來源，遂在不同畫面顯示不同數字。

## Proposed Solution

**將「已分配」改為從 child orders 即時計算的單一真相來源（SSOT）**，廢除對 `_allocated_qty` 與 `_buygo_allocated` 的讀取依賴。

具體：
- 已有的 `ProductStatsCalculator::calculateAllocatedToChildOrders(array $variationIds)` 即為正確 SSOT 計算（SQL: `SUM(quantity) WHERE parent_id IS NOT NULL AND type='split' AND status NOT IN ('cancelled','refunded') GROUP BY object_id`）。直接接到所有讀取點。
- 新增 `ProductStatsCalculator::calculateAllocatedPerParentOrder(array $parentOrderIds, array $variationIds): array` 回傳 `[parentOrderId][variationId] => allocatedQty`，供 buyers 視圖逐筆訂單計算「該客戶在這筆訂單的已分配量」。SQL：`JOIN fct_orders parent ON child.parent_id=parent.id` 過濾 `parent.id IN (...)` 與 `child.object_id IN (...)`，`type='split'`、`status NOT IN ('cancelled','refunded')`。
- `class-products-api.php` 列表 endpoint 與單品 endpoint 改用 `calculateAllocatedToChildOrders()` 結果填 `allocated` 欄位，移除 `get_post_meta('_buygo_allocated', ...)` 的 fallback。
- `ProductBuyerQueryService::buildBuyerOrderEntry()` 改用 `calculateAllocatedPerParentOrder()` 取代 `$metaData['_allocated_qty'] ?? 0` 的讀取。
- `_buygo_allocated` post_meta 與 `_allocated_qty` line_meta 仍保留寫入（避免影響其他 read site 暫時無法掃描到的依賴），但不再被讀取；後續可獨立 change 移除寫入。

## Non-Goals

- 不修寫入端 sync（既然不讀就不需要寫對；後續若要徹底乾淨可開獨立 change 移除寫入並做 backfill）。
- 不跑 backfill migration（讀取改完後存量資料自動「看起來對了」，舊 meta 留著無害）。
- 不動 allocation 詳情頁卡片邏輯（已從 child orders 計算，本來就對）。
- 不變更 REST API 欄位名稱與型別（`allocated`、`pending`、`reserved` 保持 int，前端 Vue 模板不需改）。
- 不重構 ProductStatsCalculator 既有方法。
- 不涉及 PR #11 已修的 transient 快取與 reserved 公式（互補、可同時 merge）。

## Success Criteria

- 對 product post_id=1055，部署後 `view=buyers&id=1055` 與 `view=allocation&id=1055` 卡片「已分配」數值相等且等於 2（child orders 加總）。
- 對 product post_id=1258，部署後同樣兩個畫面顯示 52，與既有行為一致（迴歸保護）。
- 商品列表頁的 `已分配` 欄位 = 同商品分配詳情頁卡片的「已分配」。
- 新增 PHPUnit 純 PHP 邏輯測試：用 mock `$wpdb` 驗證 `calculateAllocatedPerParentOrder()` 對 fixture 輸入回傳正確 map；驗證三個讀取點對同 fixture 回傳同一 allocated 數字。
- `composer test` 全綠（包含既有 344 筆）。

## Impact

- Affected code:
  - Modified: `includes/services/class-product-stats-calculator.php`（新增 `calculateAllocatedPerParentOrder` 方法）
  - Modified: `includes/api/class-products-api.php`（list + single endpoint allocated 改用 calculator）
  - Modified: `includes/services/class-product-buyer-query-service.php`（`buildBuyerOrderEntry` 改用新方法、`getProductBuyers` 預先 batch 抓 allocated map）
  - New: `tests/Unit/Services/ProductStatsCalculatorAllocatedTest.php`
  - New: `tests/Unit/Services/ProductBuyerQueryServiceAllocatedTest.php`
  - Removed: (none)
