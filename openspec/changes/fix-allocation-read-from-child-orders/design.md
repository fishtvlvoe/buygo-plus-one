## Context

BuyGo+1 把「已分配」拆寫在三處 storage（child orders、parent line_meta、product post_meta），歷史寫入路徑未一律 sync，導致 production product 1055 出現 0/0/2 的三層發散。讀取端三條路各自取一處：

- `includes/api/class-products-api.php`（列表 + 單品 endpoint）→ `_buygo_allocated` post_meta，含 ProductService 已預讀的 fallback
- `includes/services/class-product-buyer-query-service.php::buildBuyerOrderEntry()` → parent order item `line_meta._allocated_qty`
- `admin/partials/products.php`（allocation 詳情頁）→ 已從 child orders 算（不在本 change 範圍）

Production 驗證：product 1055（attachment 類型異常但有 fct_order_items 連結）`_buygo_allocated=''`、parent `_allocated_qty` 加總 0、2 筆 child orders（status=shipped）quantity 加總 2。Product 1258 三層一致 52，證明 schema 與計算公式都對，差異純粹來自寫入端歷史路徑。

`ProductStatsCalculator::calculateAllocatedToChildOrders(array $variationIds)` 已存在且正確（SQL 篩 `parent_id IS NOT NULL AND type='split' AND status NOT IN ('cancelled','refunded')` 後 `SUM(quantity) GROUP BY object_id`）。只是還沒被 list 端點與 buyers 端點使用。

## Goals / Non-Goals

**Goals:**

- 把「已分配」讀取統一收斂到 `ProductStatsCalculator` 的 child-order SQL 計算，所有讀取點同源。
- 部署後 product 1055 的 buyers 頁與 allocation 詳情頁卡片「已分配」相等且等於 2。
- 既有正常資料（product 1258）行為不退化，仍顯示 52。
- 新增單元測試確保三個讀取點對同 fixture 回傳相同 allocated 值。

**Non-Goals:**

- 不修寫入端漏 sync 的歷史 bug（既然不再讀那兩處快照，寫漏不影響顯示）。
- 不跑 backfill migration（讀取改完即視同 backfill）。
- 不改 `allocated` 欄位 contract、不改 REST API shape、不動前端 Vue 模板。
- 不刪 `_buygo_allocated` 與 `_allocated_qty` 寫入路徑（後續獨立 change 處理）。
- 不影響 PR #11（拔 transient 快取、統一 reserved 公式）— 兩 change 互補可同時 merge。

## Decisions

**D1：讀取端 SSOT 採方案 A（child orders SQL），不採方案 B（保留 meta + 加 sync）**

- A：新邏輯每次 list 都查 child orders SUM，正確性 100%、不需 backfill、不需動寫入端。代價：每次 list query 多打一次 GROUP BY join。
- B：保留 meta 讀取 + 強化寫入端 sync + backfill migration。代價：寫入路徑分散在 4-5 個 service，全部要改；backfill SQL 風險 + 無法保證未來新寫入路徑不再漏。
- 採 A：BuyGo 後台流量低、商品列表通常 < 200 筆，多一個 GROUP BY 仍 ms 級；且把分散風險集中到單一讀取函式，未來進新寫入路徑也不會破。

**D2：每商品多次查 vs 一次 batch 查**

- buyers 視圖一次只看一個商品的所有訂單 → 直接呼叫 `calculateAllocatedPerParentOrder()` 一次 batch 拿所有 parent order id 的 allocated map，避免 per-row 查詢。
- products list 已支援 batch（`calculateAllocatedToChildOrders` 接受 variation ids 陣列），對列表中所有 variation 一次查。

**D3：新方法 `calculateAllocatedPerParentOrder(array $parentOrderIds, array $variationIds): array`**

- 簽名：回傳 `array<int $parentOrderId, array<int $variationId, int $allocatedQty>>`，未出現的組合視為 0。
- 實作 SQL：
  ```
  SELECT parent.id AS parent_id, child_item.object_id AS variation_id,
         SUM(child_item.quantity) AS allocated_qty
  FROM fct_order_items child_item
  INNER JOIN fct_orders child ON child.id = child_item.order_id
  INNER JOIN fct_orders parent ON parent.id = child.parent_id
  WHERE parent.id IN (?...)
    AND child_item.object_id IN (?...)
    AND child.type = 'split'
    AND child.status NOT IN ('cancelled','refunded')
  GROUP BY parent.id, child_item.object_id
  ```
- 空輸入回空陣列、SQL 例外 log 後回空陣列（沿用既有錯誤處理模式）。

**D4：保留 `allocated` 欄位於 API response，型別維持 int**

- 不改前端任何 Vue 模板或 composable，純後端讀取邏輯替換。

## Implementation Contract

**Behavior:**

- `GET /wp-json/buygo-plus-one/v1/products`（list）：對每個商品的 `allocated` 改用 `calculateAllocatedToChildOrders([...variationIds])`，廢除 `get_post_meta('_buygo_allocated', ...)` 與 `$product['allocated']` 的讀取。
- `GET /wp-json/buygo-plus-one/v1/products/{id}`（single）：同上。
- `GET /wp-json/buygo-plus-one/v1/products/{id}/buyers`：頂部卡片 `totalAllocated` 由前端 `summary.totalAllocated += order.allocated_quantity` 加總，即依賴每筆 order entry 的 `allocated_quantity` 來自新方法。
- 每筆 buyer order entry 的 `allocated_quantity` 來自 `calculateAllocatedPerParentOrder()[parentOrderId][objectId] ?? 0`，不再讀 `line_meta._allocated_qty`。
- `pending_quantity` 仍由 `max(0, quantity - allocated_quantity)` 計算，新公式不需改前端。

**Interface / data shape:**

- API response 欄位名與型別維持：`allocated` (int)、`allocated_quantity` (int)、`pending_quantity` (int)、`reserved` (int)。
- 新方法 `ProductStatsCalculator::calculateAllocatedPerParentOrder(array $parentOrderIds, array $variationIds): array`：回傳 `[parentOrderId => [variationId => int]]`。
- 不變更任何 REST endpoint URL。

**Failure modes:**

- 空 `$parentOrderIds` 或 `$variationIds` → 回 `[]`。
- SQL 例外 → DebugService 紀錄並回 `[]`，呼叫端取 0（與 `calculateAllocatedToChildOrders` 既有錯誤處理一致）。
- Child order status 為 `cancelled` 或 `refunded` → 不計入加總（與既有 SQL 一致）。

**Acceptance criteria:**

- 新增測試 `tests/Unit/Services/ProductStatsCalculatorAllocatedTest.php`：
  - `test_calculateAllocatedPerParentOrder_groups_by_parent_and_variation`：mock `$wpdb` 回傳 fixture 行（parent_id, variation_id, allocated_qty），驗證 method 組成正確的 nested map。
  - `test_calculateAllocatedPerParentOrder_excludes_cancelled_and_refunded`：fixture 含 cancelled child order，驗證 SQL 過濾條件正確（`$wpdb->prepare` 內含對應 NOT IN clause）。
  - `test_calculateAllocatedPerParentOrder_empty_inputs_return_empty_array`：空陣列輸入直接回 `[]` 不打 query。
  - `test_calculateAllocatedToChildOrders_unchanged_behavior`：保護既有方法行為不退化。
- 新增測試 `tests/Unit/Services/ProductBuyerQueryServiceAllocatedTest.php`：
  - `test_buildBuyerOrderEntry_uses_child_orders_not_line_meta`：mock `_allocated_qty=0` line_meta + child orders fixture 加總=2，驗證輸出 `allocated_quantity=2`。
  - `test_buildBuyerOrderEntry_pending_equals_quantity_minus_child_allocated`：驗 `pending_quantity = quantity - allocated_quantity` 仍正確。
- 端到端（手動）：對 buygo.me product 1055 部署後分配卡片顯示 2、訂單明細表格每筆 已分配=1。
- `composer test` 全綠（包含既有 344 + 新增）。

**Scope boundaries:**

- In scope：3 個檔案的讀取邏輯替換（products-api、buyer-query-service、stats-calculator）+ 2 個測試檔。
- Out of scope：寫入端、backfill、allocation 詳情頁、PR #11 已修內容、其他 endpoint。

## Risks / Trade-offs

**R1：每次 list 多一次 GROUP BY join，列表頁變慢**

- 機率：低。後台流量低、商品數通常 < 200、`fct_order_items.object_id` 有索引（外部鍵級別）、`fct_orders.parent_id` 通常也有索引。
- 對策測試：`test_calculateAllocatedToChildOrders_uses_indexes`（用 EXPLAIN 或斷言 SQL 結構，確認 WHERE 條件能命中索引）。若實測 query > 200ms，後續以 micro-cache（request-scope memo）補救。

**R2：誤改 buildBuyerOrderEntry 破壞其他 caller**

- 機率：低。`getProductBuyers` 是唯一呼叫點（grep `buildBuyerOrderEntry` 應只有一處內部使用）。
- 對策測試：`test_buildBuyerOrderEntry_uses_child_orders_not_line_meta` 直接驗 happy path；另跑 `composer test` 全套迴歸。

**R3：cancelled / refunded child orders 處理不一致**

- 既有 `calculateAllocatedToChildOrders` SQL 排除 `('cancelled','refunded')`，新 `calculateAllocatedPerParentOrder` 必須使用相同 IN clause，否則兩個讀取點對「已取消的分配」會給不同數字。
- 對策測試：`test_calculateAllocatedPerParentOrder_excludes_cancelled_and_refunded`。

**R4：寫入端漏 sync 的歷史 bug 不修，未來其他開發者誤用 `_buygo_allocated` 或 `_allocated_qty`**

- 機率：中。其他開發者可能 grep 到舊欄位名繼續用。
- 對策：在 `_buygo_allocated` 寫入點與 `_allocated_qty` 寫入點添加 `@deprecated` PHP doc 註解，註明「請改用 ProductStatsCalculator::calculateAllocatedToChildOrders / calculateAllocatedPerParentOrder」。**註解動作列入本 change 的 tasks**。
- 不在本 change 移除寫入（保守，避免破壞未發現的依賴）。

**R5：與 PR #11 同檔修改的衝突**

- PR #11 已動 `class-products-api.php` 的列表 endpoint（拔快取 + reserved 公式統一）。本 change 也動同一段（替換 allocated 來源）。
- 對策：本 change 從 PR #11 已 merge 後的 main 開分支再修；若 PR #11 還沒 merge，本 change 在 PR #11 基礎上 rebase。
