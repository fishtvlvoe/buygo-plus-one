## Problem

Code Review 發現兩個高風險問題：

1. **API handler 內執行 DDL（ALTER TABLE）**：`class-customers-api.php` 第 514 行，在 `update_customer_note()` API handler 中直接執行 `ALTER TABLE ... ADD COLUMN`。每次 API 呼叫都會嘗試修改資料表結構，違反架構規範且有並發風險。

2. **AllocationService N+1 查詢**：`class-allocation-service.php` 第 365-390 行，`updateOrderAllocations()` 在迴圈內對每個 order item 各執行 1 次 `get_var`（查子訂單 SUM）+ 1 次 `$wpdb->update`。N 筆訂單 = 2N 次 DB 操作，高並發下嚴重影響效能。

## Root Cause

1. DDL 問題：開發者為了向後相容，在 API handler 中加了「如果欄位不存在就加」的防禦性代碼，但正確做法是放在 plugin 啟動的 migration hook。

2. N+1 問題：原始實作逐筆處理 order items，沒有批次化。每個 item 個別查子訂單分配量、個別更新 line_meta，導致 DB 請求數量隨訂單數線性增長。

## Proposed Solution

1. **DDL 遷移**：將 `ALTER TABLE` 從 `update_customer_note()` 移至 `class-database.php` 的 migration hook（plugin activate / upgrade），API handler 只做資料讀寫。

2. **N+1 批次化**：將迴圈內的逐筆查詢改為一次 `GROUP BY order_id` 批次查出所有子訂單 SUM，再用批次 update（或合併為單一事務）減少 DB 操作次數。

## Non-Goals

- 不遷移其他 API 層的隔離違規（customers-api 的其他 query、orders-api 的 split_order 等）
- 不拆分 AllocationService（1026 行），僅修效能問題
- 不修改前端邏輯
- 不調整 FluentCart 整合層

## Success Criteria

- `class-customers-api.php` 中零個 ALTER TABLE / DDL 語句
- `updateOrderAllocations()` 迴圈內零個獨立 DB 查詢，改為迴圈外批次查詢
- 既有測試全部通過（272+ tests），無回歸
- Plugin activation hook 正確執行新欄位的 migration

## Impact

- Affected code:
  - Modified: includes/api/class-customers-api.php（移除 DDL，簡化 update_customer_note）
  - Modified: includes/class-database.php（新增 migration 方法處理 customer_note 欄位）
  - Modified: includes/services/class-allocation-service.php（N+1 批次化）
  - New: tests/Unit/Services/AllocationBatchPerformanceTest.php（批次化效能測試）
