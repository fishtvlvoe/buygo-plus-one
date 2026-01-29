# 慢查詢監控系統

## 目的

自動偵測並記錄執行時間超過閾值的資料庫查詢，幫助識別效能瓶頸。

## 功能特色

- ✅ 自動記錄慢查詢到日誌檔
- ✅ 整合 DebugService（可在 Debug 頁面查看）
- ✅ 月度日誌檔（自動輪替）
- ✅ 可設定閾值（預設 500ms）
- ✅ 僅在 WP_DEBUG 模式啟用（避免生產環境效能影響）

## 快速開始

### 在 Service 中使用

```php
use BuyGoPlus\Monitoring\SlowQueryMonitor;

class YourService
{
    private $slowQueryMonitor;

    public function __construct()
    {
        $this->slowQueryMonitor = new SlowQueryMonitor(500); // 500ms 閾值
    }

    public function yourMethod()
    {
        $start_time = microtime(true);

        // 執行查詢
        $results = $this->wpdb->get_results($sql);

        $execution_time = microtime(true) - $start_time;

        // 自動記錄慢查詢（超過閾值才會記錄）
        $this->slowQueryMonitor->log_if_slow(
            $sql,
            $execution_time,
            [
                'service' => 'YourService',
                'method' => 'yourMethod'
            ]
        );

        return $results;
    }
}
```

### 查看慢查詢統計

```php
$monitor = new SlowQueryMonitor();
$stats = $monitor->get_stats(7); // 最近 7 天

print_r($stats);

// 輸出:
// Array
// (
//     [enabled] => true
//     [threshold_ms] => 500
//     [total_slow_queries] => 12
//     [slowest_ms] => 2300
//     [period_days] => 7
//     [log_file] => /path/to/uploads/buygo-logs/slow-queries-2026-01.log
// )
```

## 日誌檔格式

日誌檔位於：`wp-content/uploads/buygo-logs/slow-queries-YYYY-MM.log`

```
[2026-01-29 10:30:45] SLOW QUERY (1200ms) - DashboardService
Caller: class-dashboard-service.php:156 (calculateStats)
Query: SELECT COUNT(*), SUM(total_amount) FROM wp_fct_orders WHERE created_at >= '2026-01-01' AND payment_status = 'paid' AND mode = 'live'
--------------------------------------------------------------------------------
```

## 設定選項

### 調整閾值

```php
// 預設 1000ms
$monitor = new SlowQueryMonitor(1000);

// 更嚴格（500ms）
$monitor = new SlowQueryMonitor(500);

// 更寬鬆（2000ms）
$monitor = new SlowQueryMonitor(2000);
```

### 強制啟用/停用

```php
// 強制啟用（即使非 WP_DEBUG 模式）
$monitor = new SlowQueryMonitor(1000, true);

// 強制停用
$monitor = new SlowQueryMonitor(1000, false);

// 自動（僅 WP_DEBUG 時啟用）
$monitor = new SlowQueryMonitor(1000, null);
```

## 日誌維護

### 自動清理舊日誌

```php
$monitor = new SlowQueryMonitor();

// 清除 3 個月前的日誌
$deleted_count = $monitor->cleanup_old_logs(3);

echo "已刪除 {$deleted_count} 個舊日誌檔";
```

### 手動查看日誌

```bash
# 查看當月日誌
tail -f /path/to/wp-content/uploads/buygo-logs/slow-queries-2026-01.log

# 搜尋特定服務的慢查詢
grep "DashboardService" /path/to/wp-content/uploads/buygo-logs/slow-queries-2026-01.log

# 統計慢查詢數量
grep -c "SLOW QUERY" /path/to/wp-content/uploads/buygo-logs/slow-queries-2026-01.log
```

## 整合 Dashboard Service（範例）

已整合到 `DashboardService`（B21-06），但尚未在查詢方法中啟用自動監控。

**未來改進方向：**

1. 建立 `queryWithMonitoring()` helper method
2. 所有查詢使用此 method 包裝
3. 自動記錄慢查詢，無需手動呼叫

```php
// 未來改進（參考）
private function queryWithMonitoring($sql, $context = [])
{
    $start_time = microtime(true);
    $result = $this->wpdb->get_results($sql);
    $execution_time = microtime(true) - $start_time;

    $this->slowQueryMonitor->log_if_slow($sql, $execution_time, $context);

    return $result;
}
```

## 效能影響

| 模式 | 效能影響 |
|------|---------|
| 生產環境（WP_DEBUG=false） | **無影響** - 監控自動停用 |
| 開發環境（WP_DEBUG=true） | **極小** - 僅增加 0.1-0.5ms 記錄時間 |
| 慢查詢記錄時 | **約 1-2ms** - 寫入日誌檔 |

## Debug Service 整合

慢查詢會自動記錄到 Debug Service，可在後台 Debug 頁面查看：

**BuyGo Plus → Debug 頁面 → 過濾「SlowQueryMonitor」**

顯示內容：
- 時間戳
- 執行時間（毫秒）
- SQL 查詢
- 呼叫者（檔案:行數）
- 額外上下文

## 常見問題

### Q: 為什麼我看不到慢查詢記錄？

**A:** 檢查以下項目：
1. 確認 `WP_DEBUG` 設為 `true`（在 `wp-config.php`）
2. 確認有執行超過閾值的查詢
3. 檢查日誌檔路徑是否可寫：`wp-content/uploads/buygo-logs/`

### Q: 如何查看所有慢查詢？

**A:** 查看 Debug 頁面或直接讀取日誌檔：
```bash
cat /path/to/wp-content/uploads/buygo-logs/slow-queries-2026-01.log
```

### Q: 會影響生產環境效能嗎？

**A:** 不會！監控預設僅在 `WP_DEBUG` 模式啟用，生產環境應設定 `WP_DEBUG = false`。

## 相關檔案

- **監控類別：** `includes/monitoring/class-slow-query-monitor.php`
- **整合範例：** `includes/services/class-dashboard-service.php`
- **日誌檔：** `wp-content/uploads/buygo-logs/slow-queries-YYYY-MM.log`

## 技術債追蹤

| ID | 問題 | 優先級 | 狀態 |
|----|------|--------|------|
| B21-06 | 缺少慢查詢監控 | 高 | ✅ 基礎實作完成 |

**完成日期：** 2026-01-29

**狀態：**
- ✅ `SlowQueryMonitor` 類別實作完成
- ✅ 整合到 `DashboardService`（已建立實例）
- ⏸️ 尚未在所有查詢方法中啟用（需要 helper method）

**下一步（未來改進）：**
1. 建立 `queryWithMonitoring()` helper
2. 重構所有查詢使用 helper
3. 啟用自動監控
