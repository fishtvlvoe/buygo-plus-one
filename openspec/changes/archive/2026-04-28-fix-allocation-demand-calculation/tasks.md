## 1. 根因確認與測試先行

- [x] 1.1 [Tool: sonnet] 根因確認：閱讀 includes/services/class-allocation-service.php 第 270-330 行（updateOrderAllocations 方法），驗證 design 中「需求量取值修正策略」的假設——確認父訂單 quantity 完整性是否在子訂單建立流程中被破壞。同時閱讀 includes/api/class-products-api.php 第 1140-1200 行，確認「API 回傳的需求量欄位校正」所描述的 SQL JOIN 加總是否正確。檢查「子訂單建立流程中的 quantity 保護」：`create_child_order()` 第 559-576 行是否有覆寫父訂單 quantity 的副作用。產出根因確認報告（含行號與程式片段）
- [x] 1.2 [Tool: sonnet] 撰寫 TDD 紅燈測試 tests/unit/services/AllocationDemandCalculationTest.php，覆蓋 spec 中「Allocation demand quantity uses child order actual quantity」的所有場景：(1) 子訂單 demand 反映自身 quantity (2) 多個子訂單各自獨立驗證 demand (3) 父訂單 demand 使用自身 quantity (4) 超過 demand 時觸發警告。同時覆蓋 design 中 has_variations=true 和 false 兩種情況。測試應 mock fct_order_items 資料，不依賴 WordPress 環境。執行 `composer test -- --filter AllocationDemandCalculation` 確認全部紅燈

## 2. 修復 AllocationService 需求量計算

- [x] [P] 2.1 [Tool: copilot-codex] 依 design「需求量取值修正策略」方案 B，修正 includes/services/class-allocation-service.php 的 updateOrderAllocations 方法（第 278-320 行）：確保父訂單 `$item['quantity']` 未被覆寫，且 `$actual_child_allocated`（第 300-311 行）正確加總所有子訂單的已分配量。依「子訂單建立流程中的 quantity 保護」，若 `create_child_order()` 有覆寫父訂單 quantity 的邏輯則移除
- [x] [P] 2.2 [Tool: copilot-codex] 依 design「API 回傳的需求量欄位校正」，檢查 includes/api/class-products-api.php 第 1140-1200 行的 `get_unallocated_orders()` SQL：確認回傳的 quantity 為父訂單原始下單量，allocated 為所有子訂單已分配量的正確加總（含不同 variation 的子訂單）。若 JOIN 邏輯有遺漏則修正
- [x] 2.3 [Tool: sonnet] 執行 `composer test -- --filter AllocationDemandCalculation` 確認所有測試從紅轉綠。若有失敗，修正實作直到全部通過。再執行 `composer test` 確認無回歸

## 3. 整合驗證

- [x] 3.1 [Tool: sonnet] 執行完整測試套件 `composer test` 確認無回歸。檢查 AllocationService 中是否有其他方法也用了相同的 quantity 取值模式（grep `$item->quantity` 或 `$item['quantity']`），若有則依相同策略修正並補測試
