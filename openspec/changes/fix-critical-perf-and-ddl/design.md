## Context

Code Review 發現兩個高風險問題需立即修復：
1. `class-customers-api.php` 第 514 行在 API handler 中執行 `ALTER TABLE`
2. `class-allocation-service.php` 第 365-390 行迴圈內 N+1 查詢

專案已有 `class-database.php` 的 migration 機制（`upgrade_tables()` 方法，第 67 行），可直接復用。

## Goals / Non-Goals

**Goals:**
- 將 DDL 從 API handler 移至 database migration hook
- 將 N+1 迴圈改為批次查詢 + 批次更新
- 保持所有測試通過

**Non-Goals:**
- 不遷移其他 API 層隔離違規
- 不拆分 AllocationService
- 不修改前端

## Decisions

### 將 ALTER TABLE 遷移至 Database migration

將 `class-customers-api.php` 第 502-515 行的 `INFORMATION_SCHEMA.COLUMNS` 檢查 + `ALTER TABLE` 移至 `class-database.php` 的 `upgrade_tables()` 方法。新增 `upgrade_customers_table()` 子方法，依照既有慣例（第 89-99 行 `upgrade_shipments_table()` 的模式）：先 `SHOW COLUMNS FROM` 檢查欄位存在，不存在才 `ALTER TABLE ADD COLUMN`。

API handler `update_note()` 簡化為：移除 DDL 相關代碼（第 502-515 行），直接執行 UPDATE。

**替代方案**：在 `update_note()` 中保留檢查但改用 try-catch — 拒絕，因為 API handler 不應承擔 schema 責任。

### 將 N+1 查詢改為批次操作

將 `class-allocation-service.php` 第 365-390 行的迴圈改為：
1. 迴圈前：一次 `SELECT order_id, SUM(quantity) ... GROUP BY order_id` 查出所有子訂單的分配量 map
2. 迴圈內：從 map 讀取（零 DB 操作），組裝要更新的資料
3. 迴圈後：批次 update line_meta（用 CASE WHEN 或多筆 prepare + 單一事務）

效果：2N 次 DB → 2 次 DB（1 次批次查詢 + 1 次批次更新）。

**替代方案**：使用 WordPress transient 快取 — 拒絕，因為分配量是即時數據不適合快取。

## Risks / Trade-offs

- [Risk] migration 執行順序 — 若用戶先更新外掛但未重新啟動 → Mitigation：`upgrade_tables()` 在 `plugins_loaded` hook 中執行，每次載入都檢查
- [Risk] 批次 update 的 SQL 長度限制 → Mitigation：控制單次批次不超過 100 筆，超過則分批

## has_variations 考量

N+1 修復適用於所有商品類型。批次查詢的 GROUP BY 自然包含所有 variation 的子訂單。
