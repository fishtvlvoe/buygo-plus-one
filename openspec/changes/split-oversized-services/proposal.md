## Summary

將 AllocationService（1026 行）和 ProductService（946 行）按職責拆分為多個小型 Service，恢復 300 行上限規範。

## Motivation

專案架構規範要求 Service class 不超過 300 行。CR 發現 15 個 Service 超標，其中 AllocationService 和 ProductService 最嚴重，合計近 2000 行。單一 class 承擔過多職責導致：
1. 修改時影響面難以預估
2. 測試覆蓋困難
3. 新人理解成本高

## Proposed Solution

**AllocationService（1026 行）拆為 3 個**：
1. AllocationQueryService — 讀取：查詢待分配訂單（`getProductOrders`）、已分配量、統計數據（`getAllVariationIds`）
2. AllocationWriteService — 寫入：建立子訂單、更新分配量（`updateOrderAllocations`）、取消分配（`cancelChildOrder`）
3. AllocationCalculator — 計算：需求量計算、超量驗證（`validateAdjustment`）、分配量調整（`adjustAllocation`）

**ProductService（946 行）拆為 3 個**：
1. ProductQueryService — 讀取：商品列表與搜尋（`getProductsWithOrderCount`）、單筆查詢（`getProductById`）、購買者查詢（`getProductBuyers`）、權限判斷（`canAddProduct`、`canAddImage`、`isVariableProduct`）
2. ProductWriteService — 寫入：更新商品（`updateProduct`）
3. ProductVariationService — Variation 管理：列表（`getVariations`）、刪除（`deleteProductPost`）、統計（`getVariationStats`）、meta 讀寫（`getVariationMeta`、`updateVariationMeta`）

**Facade 過渡策略**：保留原 AllocationService 和 ProductService 作為 facade（委派到新 Service），避免一次修改所有呼叫端。待所有呼叫端遷移完成後再移除 facade。

## Non-Goals

- 不修改商業邏輯（純拆分，行為不變）
- 不處理其他超標 Service（CheckoutCustomizationService、ProductDataParser、SearchService 留待後續 change）
- 不修改 API 層呼叫端（facade 確保向後相容，API 層遷移留待後續）
- 不移除 facade（facade 移除是獨立任務，本 change 只建立新 Service + facade）

## Alternatives Considered

- 只拆 AllocationService → 拒絕，ProductService 同樣嚴重（946 行超標 215%）
- 拆更細（5+ 個 Service）→ 拒絕，過度拆分增加 DI 複雜度
- 直接遷移所有呼叫端而不建 facade → 拒絕，影響面過大（15+ API 檔案），風險高

## Impact

- Affected specs: service-size-compliance
- Affected code:
  - New: includes/services/class-allocation-query-service.php
  - New: includes/services/class-allocation-write-service.php
  - New: includes/services/class-allocation-calculator.php
  - New: includes/services/class-product-query-service.php
  - New: includes/services/class-product-write-service.php
  - New: includes/services/class-product-variation-service.php
  - Modified: includes/services/class-allocation-service.php
  - Modified: includes/services/class-product-service.php
