## 1. AllocationService 拆分

- [x] 1.1 [P] AllocationService 拆分邊界：讀取 / 寫入 / 計算 — 本 Group 實作讀取部分，建立 AllocationQueryService（class-allocation-query-service.php）：遷入 `getAllVariationIds()` 和 `getProductOrders()` 方法，符合 AllocationService child classes must not exceed 300 lines 規範，確認 wc -l ≤ 300 [Tool: sonnet]
- [x] 1.2 [P] AllocationService 拆分邊界：計算 — 建立 AllocationCalculator（class-allocation-calculator.php）：遷入 `validateAdjustment()` 和 `adjustAllocation()` 純計算方法，符合 AllocationService child classes must not exceed 300 lines 規範，確認 wc -l ≤ 300 [Tool: sonnet]
- [x] 1.3 AllocationService 拆分邊界：寫入 — 建立 AllocationWriteService（class-allocation-write-service.php）：遷入 `updateOrderAllocations()` 和 `cancelChildOrder()` 寫入方法，符合 AllocationService child classes must not exceed 300 lines 規範（已知 updateOrderAllocations 約 400 行，需拆解為私有輔助方法），確認 wc -l ≤ 300 [Tool: sonnet]
- [x] 1.4 改寫 AllocationService 為 Facade 過渡模式取代直接遷移：持有 AllocationQueryService、AllocationWriteService、AllocationCalculator 實例，所有 public 方法委派，符合 Facade preserves backward compatibility for AllocationService callers 規範 [Tool: sonnet]
- [x] 1.5 測試拆分策略：測試跟著方法走 — 建立 AllocationQueryServiceTest.php、AllocationWriteServiceTest.php、AllocationCalculatorTest.php：從 AllocationServiceTest 遷移對應 case，確認 All existing tests must pass after refactoring [Tool: sonnet]

## 2. ProductService 拆分

- [x] 2.1 [P] ProductService 拆分邊界：查詢 / 寫入 / Variation — 本 Group 實作查詢部分，建立 ProductQueryService（class-product-query-service.php）：遷入 `getProductsWithOrderCount()`、`getProductById()`、`getProductBuyers()`、`canAddProduct()`、`canAddImage()`、`isVariableProduct()` 方法，符合 ProductService child classes must not exceed 300 lines 規範，確認 wc -l ≤ 300 [Tool: sonnet]
- [x] 2.2 [P] ProductService 拆分邊界：Variation — 建立 ProductVariationService（class-product-variation-service.php）：遷入 `getVariations()`、`deleteProductPost()`、`getVariationStats()`、`getVariationMeta()`、`updateVariationMeta()` 方法，符合 ProductService child classes must not exceed 300 lines 規範，確認 wc -l ≤ 300 [Tool: sonnet]
- [x] 2.3 ProductService 拆分邊界：寫入 — 建立 ProductWriteService（class-product-write-service.php）：遷入 `updateProduct()` 及其私有輔助邏輯，符合 ProductService child classes must not exceed 300 lines 規範，確認 wc -l ≤ 300 [Tool: sonnet]
- [x] 2.4 改寫 ProductService 為 Facade 過渡模式取代直接遷移：持有 ProductQueryService、ProductWriteService、ProductVariationService 實例，所有 public 方法委派，符合 Facade preserves backward compatibility for ProductService callers 規範 [Tool: sonnet]
- [x] 2.5 測試拆分策略：測試跟著方法走 — 建立 ProductQueryServiceTest.php、ProductWriteServiceTest.php、ProductVariationServiceTest.php：從 ProductServiceTest 遷移對應 case，確認 All existing tests must pass after refactoring [Tool: sonnet]

## 3. 整合驗證

- [x] 3.1 跑完整測試套件：執行 `composer test`，確認 All existing tests must pass after refactoring — 零 failure，exit code 0 [Tool: sonnet]
- [x] 3.2 確認 AllocationService child classes must not exceed 300 lines 和 ProductService child classes must not exceed 300 lines：執行 `wc -l includes/services/class-allocation-*.php includes/services/class-product-*.php`，列出每個新 class 的行數，確認全部 ≤ 300
