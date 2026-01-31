# Dashboard 資料庫索引設定指南

## 目的

為 Dashboard 查詢建立必要的資料庫索引，提升大量資料時的查詢效能。

## 影響範圍

建立索引後，以下 Dashboard 查詢將大幅提速（預估 10x-100x）：

1. **統計查詢** (`calculateStats`) - 本月營收/訂單/客戶數
2. **營收趨勢** (`getRevenueTrend`) - 過去 30 天營收圖表
3. **最近活動** (`getRecentActivities`) - 7 天內訂單活動

## 索引列表

### 1. `idx_orders_stats` - 統計查詢複合索引

```sql
CREATE INDEX `idx_orders_stats`
ON `wp_fct_orders` (`created_at`, `payment_status`, `mode`);
```

**用途：** 加速本月/上月統計查詢（營收、訂單數、客戶數）

**查詢範例：**
```sql
SELECT COUNT(*), SUM(total_amount), COUNT(DISTINCT customer_id)
FROM wp_fct_orders
WHERE created_at >= '2026-01-01 00:00:00'
  AND payment_status = 'paid'
  AND mode = 'live';
```

---

### 2. `idx_orders_revenue` - 營收趨勢查詢索引

```sql
CREATE INDEX `idx_orders_revenue`
ON `wp_fct_orders` (`created_at`, `currency`, `payment_status`);
```

**用途：** 加速營收趨勢圖表查詢（按日期分組）

**查詢範例：**
```sql
SELECT DATE(created_at) as date, SUM(total_amount) as daily_revenue
FROM wp_fct_orders
WHERE created_at >= '2025-12-30 00:00:00'
  AND payment_status = 'paid'
  AND currency = 'TWD'
  AND mode = 'live'
GROUP BY DATE(created_at);
```

---

### 3. `idx_orders_activities` - 最近活動查詢索引

```sql
CREATE INDEX `idx_orders_activities`
ON `wp_fct_orders` (`created_at`, `mode`);
```

**用途：** 加速最近 7 天活動列表查詢

**查詢範例：**
```sql
SELECT o.id, o.total_amount, o.created_at, c.first_name, c.last_name
FROM wp_fct_orders o
LEFT JOIN wp_fct_customers c ON o.customer_id = c.id
WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
  AND o.mode = 'live'
ORDER BY o.created_at DESC
LIMIT 10;
```

---

### 4. `idx_orders_customer` - 訂單-客戶關聯索引

```sql
CREATE INDEX `idx_orders_customer`
ON `wp_fct_orders` (`customer_id`);
```

**用途：** 加速 JOIN 查詢（訂單 ↔ 客戶）

---

## 手動執行方式

### 方法 1：使用 phpMyAdmin

1. 登入 phpMyAdmin
2. 選擇資料庫（通常是 `local` 或 `buygo`）
3. 找到 `wp_fct_orders` 資料表
4. 點擊「結構」標籤
5. 點擊「索引」標籤
6. 執行上述 4 個 `CREATE INDEX` SQL 語句

### 方法 2：使用 SQL 命令列

```bash
# 連接到資料庫
mysql -u root -p

# 選擇資料庫
USE local;

# 執行索引建立（複製貼上上方 4 個 SQL）
CREATE INDEX `idx_orders_stats` ON `wp_fct_orders` (`created_at`, `payment_status`, `mode`);
CREATE INDEX `idx_orders_revenue` ON `wp_fct_orders` (`created_at`, `currency`, `payment_status`);
CREATE INDEX `idx_orders_activities` ON `wp_fct_orders` (`created_at`, `mode`);
CREATE INDEX `idx_orders_customer` ON `wp_fct_orders` (`customer_id`);

# 驗證索引建立成功
SHOW INDEX FROM `wp_fct_orders`;

# 分析表格以更新統計資訊
ANALYZE TABLE `wp_fct_orders`;
```

### 方法 3：使用 WP-CLI（未來支援）

```bash
# 建立索引
wp buygo dashboard create-indexes

# 分析索引
wp buygo dashboard analyze-indexes

# 刪除索引（清理用）
wp buygo dashboard drop-indexes
```

⚠️ **注意：** WP-CLI 方式目前尚未在 Local by Flywheel 環境測試通過，建議使用方法 1 或 2。

---

## 效能預期

### 建立前

| 查詢類型 | 資料量 | 執行時間 |
|---------|-------|---------|
| 統計查詢 | 100K 訂單 | ~5000ms |
| 營收趨勢 | 30 天 | ~3000ms |
| 最近活動 | 7 天 | ~2000ms |

### 建立後

| 查詢類型 | 資料量 | 執行時間 |
|---------|-------|---------|
| 統計查詢 | 100K 訂單 | ~50ms |
| 營收趨勢 | 30 天 | ~30ms |
| 最近活動 | 7 天 | ~20ms |

**改善倍數：** 約 50-100 倍

---

## 索引維護

### 何時需要重建索引？

1. 資料表結構變更（ALTER TABLE）
2. 大量資料匯入後
3. 索引損壞（EXPLAIN 顯示未使用索引）

### 如何驗證索引有效？

```sql
-- 檢查索引存在
SHOW INDEX FROM wp_fct_orders WHERE Key_name LIKE 'idx_orders%';

-- 驗證查詢使用索引（應該顯示 "Using index"）
EXPLAIN SELECT COUNT(*), SUM(total_amount)
FROM wp_fct_orders
WHERE created_at >= '2026-01-01'
  AND payment_status = 'paid'
  AND mode = 'live';
```

---

## 故障排除

### 錯誤：Duplicate key name 'idx_orders_stats'

**原因：** 索引已存在

**解決：** 跳過此索引，或先刪除再重建
```sql
DROP INDEX `idx_orders_stats` ON `wp_fct_orders`;
CREATE INDEX `idx_orders_stats` ON `wp_fct_orders` (`created_at`, `payment_status`, `mode`);
```

### 錯誤：Table 'wp_fct_orders' doesn't exist

**原因：** FluentCart 尚未建立訂單資料表

**解決：** 確認 FluentCart 外掛已啟用並建立資料表

---

## 相關檔案

- **索引管理類別：** `includes/database/class-dashboard-indexes.php`
- **WP-CLI 指令：** `includes/cli/class-dashboard-cli.php`
- **執行腳本：** `scripts/create-dashboard-indexes.php`（目前待修復）

---

## 技術債追蹤

| ID | 問題 | 優先級 | 狀態 |
|----|------|--------|------|
| B21-07 | 資料庫索引未建立 | 高 | ✅ 文件完成 |

**完成日期：** 2026-01-29

**下一步：** 使用者手動執行 SQL 建立索引（方法 1 或 2）
