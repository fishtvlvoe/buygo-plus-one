## 1. ALTER TABLE 遷移至 Database migration

- [x] [P] 1.1 [Tool: sonnet] 依 design「將 ALTER TABLE 遷移至 Database migration」，在 includes/class-database.php 的 upgrade_tables() 方法（第 67 行）中新增 upgrade_customers_table() 子方法：依照既有慣例（upgrade_shipments_table 第 89-99 行），用 SHOW COLUMNS FROM 檢查 fct_customers 是否有 note 欄位，不存在才 ALTER TABLE ADD COLUMN note TEXT NULL。確認 upgrade_tables() 呼叫此新方法
- [x] [P] 1.2 [Tool: sonnet] 依 design「將 ALTER TABLE 遷移至 Database migration」，修改 includes/api/class-customers-api.php 的 update_note() 方法（第 471 行起）：移除第 502-515 行的 INFORMATION_SCHEMA 查詢和 ALTER TABLE 邏輯，只保留 UPDATE 操作（第 518-524 行）。確認 spec「API handlers must not execute DDL statements」— 方法中零個 ALTER TABLE / INFORMATION_SCHEMA 語句
- [x] 1.3 [Tool: sonnet] 執行 `composer test` 確認無回歸。Grep class-customers-api.php 確認零個 ALTER TABLE / CREATE TABLE / INFORMATION_SCHEMA 出現

## 2. AllocationService N+1 批次化

- [x] 2.1 [Tool: sonnet] 撰寫 TDD 紅燈測試 tests/Unit/Services/AllocationBatchPerformanceTest.php，覆蓋 spec「Allocation updates use batch database operations」：(1) 批次查詢取代逐筆查詢 (2) 5 筆 order items 只產生 2 次 DB 操作 (3) 批次結果與逐筆結果等價。執行 `composer test -- --filter AllocationBatchPerformance` 確認紅燈
- [x] 2.2 [Tool: sonnet] 依 design「將 N+1 查詢改為批次操作」，修改 includes/services/class-allocation-service.php 第 365-390 行：(1) 迴圈前新增一次 GROUP BY order_id 的批次 SELECT 查出所有子訂單 SUM (2) 迴圈內改從 map 讀取，不做 DB 操作 (3) 迴圈後批次 update line_meta。確保 2N 次 DB 降為 2 次
- [x] 2.3 [Tool: sonnet] 執行 `composer test -- --filter AllocationBatchPerformance` 確認綠燈，再跑 `composer test` 確認全套件無回歸
