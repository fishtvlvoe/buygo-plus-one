## Context

庫存分配頁面（`/products/?view=allocation&id=XXX`）讓管理員為多樣式商品的每個訂單分配庫存數量。

目前的驗證邏輯在 `AllocationService::updateOrderAllocations()`（class-allocation-service.php 第 254 行起）：
1. 第 279 行：從 `fct_order_items` 查詢父訂單的品項（含 `quantity` 欄位）
2. 第 300-309 行：JOIN 子訂單表，計算該父訂單下所有子訂單已分配的總量（`$actual_child_allocated`）
3. 第 311 行：`$total_item_allocated = $actual_child_allocated + $new_allocation`
4. 第 314 行：比較 `$total_item_allocated > $item['quantity']`（父訂單原始 quantity）

API 層 `get_unallocated_orders()`（class-products-api.php 第 1144 行）也只查 `parent_id IS NULL`，回傳父訂單的 `oi.quantity`。

**Bug 情境**：當父訂單下單量 5 但管理員只想配 4（部分分配），`$item['quantity']` 仍為 5，但前端顯示的「需求數量」可能因子訂單拆分邏輯而被錯誤覆寫，導致顯示值與實際 DB 值不一致。

## Goals / Non-Goals

**Goals:**

- 修正分配量驗證邏輯，確保「需求數量」正確反映每筆訂單的實際下單量
- 確保子訂單建立後不會破壞父訂單的 quantity 值
- 補充測試覆蓋分配驗證的邊界情境

**Non-Goals:**

- 不重構分配流程的整體架構
- 不修改前端 Vue 元件邏輯（前端忠實顯示 API 回傳值）
- 不處理 FluentCart 整合層的同步問題

## Decisions

### 需求量取值修正策略

**決策**：在 `updateOrderAllocations()` 第 314 行的驗證邏輯中，保持使用父訂單的 `$item['quantity']` 作為需求上限。問題不在這個比較本身，而在 `$actual_child_allocated` 的計算或子訂單建立時 quantity 的寫入。

**替代方案考量**：
- 方案 A（改讀子訂單 quantity）：子訂單是分配結果，不是需求來源，概念上不正確
- **方案 B（確認父訂單 quantity 完整性 + 修正計算邏輯）**：確保父訂單 `fct_order_items.quantity` 在子訂單建立流程中不被修改，且 `$actual_child_allocated` 正確加總

採用方案 B，因為父訂單 quantity 才是「客戶下了多少」的真實來源。

### 子訂單建立流程中的 quantity 保護

**決策**：審查 `create_child_order()`（第 439 行）和 `updateOrderAllocations()` 流程，確認父訂單的 `fct_order_items.quantity` 不會在分配過程中被意外更新。若發現有覆寫邏輯，加入保護。

子訂單的 `fct_order_items.quantity`（第 565 行寫入）應等於本次分配量，與父訂單 quantity 獨立。

### API 回傳的需求量欄位校正

**決策**：`get_unallocated_orders()` 回傳的待分配訂單資料中，`quantity` 欄位應為父訂單的原始下單量，`allocated` 欄位應為所有子訂單已分配量的正確加總。前端用 `quantity - allocated` 計算「待分配」。

確認 SQL JOIN 的加總邏輯是否正確包含所有子訂單（含不同 variation 的子訂單）。

## Risks / Trade-offs

- [Risk] 修改 quantity 取值邏輯可能影響其他使用 `$item['quantity']` 的地方 → Mitigation：grep 全專案中 AllocationService 讀取 quantity 的所有位置，逐一確認
- [Risk] 子訂單建立流程可能有多個入口點 → Mitigation：搜尋所有呼叫 `create_child_order` 的地方，確認一致性
- [Risk] 既有子訂單資料可能已經有不正確的 quantity → Mitigation：本次只修代碼邏輯，不做資料修復。若需資料修復，另開 change

## has_variations 兩種情況

- `has_variations=true`：商品有多個 variation，每個子訂單對應特定 variation。需求量按 variation 分別驗證
- `has_variations=false`：商品無 variation，所有子訂單對應同一商品。需求量驗證邏輯相同，但只有一個 object_id
