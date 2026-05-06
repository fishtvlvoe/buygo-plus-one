## Problem

客戶反應多變體商品（如一個商品有 A/B/C/D 四種款式）的分配數量會「疊加」，無法正確對應實際庫存比例。開發者本機單人測試正常，但客戶實際操作時出現數量不一致。

經代碼分析確認雲端與本機代碼完全一致（非版本差異），根因在代碼邏輯本身。

## Root Cause

經 DB 實查 + 代碼分析（Sonnet + Haiku 交叉驗證），依「DB 已確認」優先排序：

**DB 已確認的真實問題：**
1. **🔴 cancel 拼寫不一致**：DB 中有 3 筆 status="canceled"（美式拼寫）和 4 筆 "cancelled"（英式拼寫），但代碼全部只過濾 "cancelled"。這 3 筆 "canceled" 子訂單被當成 active 計算，佔用分配額度。
2. **🔴 splitOrder 未過濾 cancelled 子訂單**：`OrderService::splitOrder()` 計算已拆分數量時（行 726-736），SQL 沒有 status 過濾。行 914-922 更新 `_allocated_qty` 時也沒有（Haiku 新發現）。取消子訂單後額度不歸還。
3. **🔴 出貨後 `_buygo_allocated` 未更新**：`OrderService::shipOrder()` 完成出貨後只扣 `line_meta._allocated_qty`，不更新 `wp_postmeta._buygo_allocated`。DB 實查確認 post 2546（drift=5）、post 1903（drift=4）有真實漂移。
4. **🔴 `_allocated_qty` 用累減而非重算**：`shipOrder()` 用 `current - shipped_qty` 累減，DB 實查確認 order 1442（meta=4, actual=2）有漂移。
5. **🟡 allocateAllForCustomer 三值 max 邏輯**（Haiku 新發現）：`max($child_allocated, $_allocated_qty, $shipped)` 依賴可能已漂移的 `_allocated_qty`，導致 needed 計算偏低。

**理論風險（DB 未發生但需防禦）：**
6. **🟡 Race Condition（無分配鎖）**：DB 中沒有實際雙重分配記錄，但代碼無排他鎖，遲早會發生。
7. **🟡 splitOrder 無 Transaction 保護**：並發時可能產生孤兒訂單。
8. **🟢 legacy object_id=0 fallback**：多變體時可能配錯 variation。

## Proposed Solution

### Phase 1 — 本機測試驗證（先不改 code）
用 PHPUnit 測試重現每個問題，確認問題確實存在：
- 測試 race condition（模擬並發分配）
- 測試 cancelled 子訂單對可分配量的影響
- 測試出貨後 `_buygo_allocated` 的值變化

### Phase 2 — 修復（按 DB 實查優先排序）
**P0（DB 已確認）：**
1. 全域 SQL 統一 cancel 拼寫過濾：`NOT IN ('cancelled', 'canceled', 'refunded')`
2. `OrderService::splitOrder()` 兩處 SQL（行 726 和行 914）加 cancelled filter
3. `OrderService::shipOrder()` 完成後同步重算 `_buygo_allocated` post meta
4. `shipOrder()` 的 `_allocated_qty` 改用重算取代累減

**P1（防禦性）：**
5. `AllocationWriteService::updateOrderAllocations()` 加 MySQL `GET_LOCK` 排他鎖
6. `OrderService::splitOrder()` 包裹 `START TRANSACTION / COMMIT / ROLLBACK`

**P2（低優先）：**
7. `AllocationBatchService::allocateAllForCustomer()` 移除三值 max 中的 `_allocated_qty` 依賴
8. legacy `object_id=0` fallback 拒絕多 variation 商品的模糊 match

### Phase 3 — 雲端 DB 修復 + 部署
- 雲端 DB：3 筆 "canceled" → "cancelled" 統一拼寫
- 本機測試全綠後部署到雲端主機
- 部署後驗證分配+出貨流程數字一致

## Non-Goals

- **不動既有的 `fix-multi-variant-allocation-cross-contamination` change**：那個 change 有自己的 lifecycle，本 change 獨立處理新發現的問題
- **不做歷史資料批量修復**：本 change 只修代碼邏輯，資料修復由既有 change 的 Phase 6-8 處理
- **不重構整體分配架構**：只針對發現的六個具體問題做最小修復
- **不加前端防抖（debounce）**：前端防抖是症狀緩解，後端鎖才是根本解法（但可作為後續 enhancement）

## Capabilities

### New Capabilities

- `allocation-concurrency-lock`: 分配操作的排他鎖機制，防止並發請求造成雙重分配

### Modified Capabilities

- `cancel-child-order`: 取消子訂單後正確歸還分配額度（splitOrder 過濾 cancelled 狀態）
- `allocation-variation-filter`: 修正 `_buygo_allocated` 在出貨後的同步更新，以及 `_allocated_qty` 重算邏輯

## Impact

- Affected specs: `allocation-concurrency-lock`（新建）、`cancel-child-order`（修改）、`allocation-variation-filter`（修改）
- Affected code:
  - Modified: includes/services/class-allocation-write-service.php, includes/services/class-order-service.php, includes/services/class-allocation-batch-service.php, includes/services/class-allocation-query-service.php
  - New: tests/unit/CancelSpellingFilterTest.php, tests/unit/ShipOrderMetaSyncTest.php, tests/unit/AllocationLockTest.php, tests/unit/SplitOrderTransactionTest.php
  - Removed: 無
- Affected Services: AllocationWriteService, OrderService, AllocationBatchService, AllocationQueryService
- Affected API: POST /products/allocate, POST /products/{id}/allocate-all（行為不變，但內部加鎖）
