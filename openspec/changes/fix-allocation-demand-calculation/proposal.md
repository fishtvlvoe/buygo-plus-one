## Problem

在「多樣式商品」（has_variations）的庫存分配頁面中，當父訂單拆分出子訂單後，系統顯示的「需求數量」計算錯誤。

具體現象：子訂單 #1420 實際下單量為 3，但系統警告「訂單 #1420 的總分配數量 (3) 超過需求數量 (1)」。需求數量被錯誤地計算為 1，導致管理員無法正確分配庫存。

## Root Cause

`AllocationService::updateOrderAllocations()`（class-allocation-service.php 第 278-317 行）在驗證分配量是否超過需求時，使用 `fct_order_items.quantity` 作為需求數量。

當父訂單拆分為子訂單時，子訂單的 `fct_order_items.quantity` 可能未正確反映該子訂單的實際需求量（仍殘留父訂單拆分前的值，或被覆寫為不正確的數字）。這導致需求量與實際下單量不一致。

同時，`ProductsApi`（class-products-api.php 第 1167 行）在查詢待分配訂單時也使用相同的 quantity 欄位，問題可能同時影響 API 回傳的統計數字。

## Proposed Solution

1. 追蹤子訂單建立流程中 `fct_order_items.quantity` 的寫入邏輯，確認是在建立時就寫錯，還是後續更新時被覆蓋
2. 修正 `AllocationService` 中需求量的取值邏輯，確保子訂單使用自身的正確數量
3. 若根因在子訂單建立流程（`createChildOrder` 或相關方法），則一併修正寫入邏輯
4. 補充單元測試覆蓋「父訂單拆子訂單後分配驗證」的場景

## Non-Goals

- 不重構整個分配流程架構
- 不處理退款邏輯（系統無退款機制）
- 不修改前端 Vue 元件的顯示邏輯（前端只是忠實顯示 API 回傳值）
- 不處理 FluentCart 同步問題（若根因不在 FluentCart 整合層）

## Success Criteria

- 子訂單的需求數量正確反映該子訂單的實際下單量
- 分配頁面不再出現錯誤的「超過需求數量」警告
- 父訂單拆分後，所有子訂單的分配驗證邏輯正確
- 新增測試覆蓋多樣式商品 + 子訂單拆分 + 分配驗證場景

## Impact

- Affected specs: `allocation-variation-filter`（分配量驗證邏輯可能需要更新）
- Affected code:
  - Modified: includes/services/class-allocation-service.php（需求量計算與驗證邏輯）
  - Modified: includes/api/class-products-api.php（待分配訂單查詢的 quantity 取值）
  - New: tests/unit/services/AllocationDemandCalculationTest.php（測試子訂單場景）
