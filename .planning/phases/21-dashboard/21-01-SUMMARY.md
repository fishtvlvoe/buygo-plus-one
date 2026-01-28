---
phase: 21-dashboard
plan: 01
subsystem: dashboard
tags: [service-layer, fluentcart-integration, statistics]
requires:
  - fluentcart-database
  - debug-service
provides:
  - dashboard-service
  - statistics-queries
  - revenue-trend
  - product-overview
  - recent-activities
affects:
  - 21-02 (Dashboard API)
  - 21-03 (Dashboard Frontend)
tech-stack:
  added:
    - FluentCart Database Queries (fct_orders, fct_customers, fct_order_items)
  patterns:
    - Service Layer Pattern
    - SQL Aggregation with COALESCE
    - Date Range Filtering
    - UNION ALL for Activity Aggregation
key-files:
  created:
    - includes/services/class-dashboard-service.php
decisions:
  - title: 金額以「分」為單位儲存
    rationale: FluentCart 使用 BIGINT 儲存金額（分為單位），避免浮點數精度問題
    impact: 所有金額查詢使用 SUM(total_amount) 直接返回分為單位，前端或 API 層負責除以 100 顯示
  - title: 使用 COALESCE 避免 NULL 值
    rationale: SUM() 在沒有資料時返回 NULL，會導致 PHP 計算錯誤
    impact: 所有聚合查詢使用 COALESCE(SUM(...), 0)
  - title: 變化百分比計算邏輯
    rationale: 需要與上期比較計算成長率，避免除以零錯誤
    impact: 當上期為 0 時，若當前有值返回 100%，否則返回 0%
  - title: 營收趨勢填補缺失日期
    rationale: Chart.js 需要完整的日期序列，否則圖表會斷開
    impact: 使用迴圈產生完整日期陣列，未找到資料的日期填入 0
  - title: 最近活動使用 UNION ALL
    rationale: 合併訂單和客戶註冊兩種活動，按時間排序
    impact: 查詢效率較高（vs JOIN），限制 7 天內資料避免效能問題
metrics:
  duration: 1 分鐘
  completed: 2026-01-29
---

# Phase 21 Plan 01: Dashboard Service 實作 Summary

**一行總結:** 建立 DashboardService，封裝儀表板統計查詢邏輯（本月統計、營收趨勢、商品概覽、最近活動）

---

## 執行結果

### 任務完成狀況

✅ **Task 1: 建立 DashboardService 類別架構** (commit 9b8a5bb)
- 建立 `includes/services/class-dashboard-service.php`
- 注入 `DebugService` 用於日誌記錄
- 定義 FluentCart 資料表屬性（`fct_orders`, `fct_customers`, `fct_order_items`）

✅ **Task 2: 實作 calculateStats 方法** (commit 5ce6908)
- 查詢本月和上月統計（訂單數、總金額、客戶數）
- 計算變化百分比（與上月比較）
- 計算平均訂單價值
- 回傳格式符合 API 規格（包含 value, currency, change_percent, period）

✅ **Task 3: 實作 getRevenueTrend 方法** (commit 42b04ad)
- 查詢過去 N 天的每日營收（支援 7, 30, 90 天）
- 支援多幣別（TWD, USD, CNY）
- 填補缺失日期（沒有訂單的日期顯示 0）
- 回傳 Chart.js 相容格式（labels + datasets）

✅ **Task 4: 實作 getProductOverview 和 getRecentActivities** (commit a8f63e0)
- `getProductOverview`: 查詢商品統計（總數、已上架、草稿）
- `getRecentActivities`: 查詢最近 10 筆活動（訂單 + 客戶註冊）
- 使用 UNION ALL 合併兩種活動類型
- 格式化活動資料（包含 type, icon, url）

**進度:** 4/4 任務完成 (100%)

---

## 核心功能

### 1. calculateStats() - 本月統計總覽

**查詢邏輯:**
```sql
-- 本月統計
SELECT
    COUNT(*) as order_count,
    COALESCE(SUM(total_amount), 0) as total_revenue,
    COUNT(DISTINCT customer_id) as customer_count
FROM fct_orders
WHERE created_at >= '2026-01-01 00:00:00'
    AND payment_status = 'paid'
    AND mode = 'live'
```

**回傳格式:**
```php
[
    'total_revenue' => [
        'value' => 158000,  // 分為單位
        'currency' => 'TWD',
        'change_percent' => 12.5,
        'period' => '本月'
    ],
    'total_orders' => [...],
    'total_customers' => [...],
    'avg_order_value' => [...]
]
```

**技術亮點:**
- 使用 `COALESCE(SUM(...), 0)` 避免 NULL 值問題
- 變化百分比邏輯處理除以零情況
- 平均訂單價值計算避免整數除法錯誤

### 2. getRevenueTrend() - 營收趨勢

**查詢邏輯:**
```sql
SELECT
    DATE(created_at) as date,
    COALESCE(SUM(total_amount), 0) as daily_revenue
FROM fct_orders
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND payment_status = 'paid'
    AND currency = 'TWD'
    AND mode = 'live'
GROUP BY DATE(created_at)
ORDER BY date ASC
```

**填補缺失日期演算法:**
```php
// 1. 先建立日期對營收的映射
$revenue_map = [];
foreach ($results as $row) {
    $revenue_map[$row['date']] = (int)$row['daily_revenue'];
}

// 2. 產生完整日期序列
for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $labels[] = date('m/d', strtotime($date));
    $data[] = $revenue_map[$date] ?? 0;  // 未找到填 0
}
```

**為什麼使用映射而非巢狀迴圈:**
- 時間複雜度: O(n + m) vs O(n × m)
- 當 days=90 時，巢狀迴圈最差需要 90 × 90 = 8100 次比較
- 映射方式只需 90 次查找（PHP 陣列查找 O(1)）

### 3. getProductOverview() - 商品概覽

**查詢邏輯:**
```sql
SELECT
    COUNT(*) as total_products,
    SUM(CASE WHEN post_status = 'publish' THEN 1 ELSE 0 END) as published,
    SUM(CASE WHEN post_status = 'draft' THEN 1 ELSE 0 END) as draft
FROM wp_posts
WHERE post_type = 'fluent-products'
```

**技術亮點:**
- 使用 `CASE WHEN` 單次查詢取得多種統計
- 避免多次 COUNT(*) 查詢（效能優化）

### 4. getRecentActivities() - 最近活動

**查詢邏輯:**
```sql
-- 訂單活動（最近 5 筆）
(SELECT
    'order' as type,
    CONCAT('新訂單 #', id) as title,
    CONCAT('客戶下單 ', ROUND(total_amount / 100, 0), ' 元') as description,
    created_at as timestamp,
    id
FROM fct_orders
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND mode = 'live'
ORDER BY created_at DESC LIMIT 5)

UNION ALL

-- 客戶註冊活動（最近 5 筆）
(SELECT
    'customer' as type,
    '新客戶註冊' as title,
    CONCAT(first_name, ' ', last_name) as description,
    created_at as timestamp,
    id
FROM fct_customers
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY created_at DESC LIMIT 5)

ORDER BY timestamp DESC LIMIT 10
```

**為什麼使用 UNION ALL:**
- `UNION ALL` 不去重，效能較 `UNION` 快（訂單和客戶 ID 不會重複）
- 各自 LIMIT 5 避免某種活動過多淹沒另一種
- 最外層 ORDER BY + LIMIT 10 確保總數限制

---

## 技術決策與影響

### 決策 1: 金額儲存單位

**背景:**
FluentCart 使用 BIGINT 儲存金額，單位為「分」（避免浮點數精度問題）。

**決策:**
Service Layer 查詢直接返回分為單位，由 API 層或前端負責除以 100 顯示。

**理由:**
- 避免在 SQL 中進行除法運算（保持整數精度）
- API 層統一處理格式化（例如 `NT$ 1,580.00`）
- 符合 FluentCart 的資料慣例

**影響:**
- ✅ 計算精確（無浮點數誤差）
- ✅ API 層可靈活格式化（支援多幣別）
- ⚠️ 前端開發者需注意除以 100

### 決策 2: 使用 COALESCE 避免 NULL 值

**背景:**
SQL 的 `SUM()` 在沒有資料時返回 NULL（而非 0），會導致 PHP 計算錯誤。

**決策:**
所有聚合查詢使用 `COALESCE(SUM(...), 0)`。

**範例:**
```php
// ❌ 錯誤：當沒有訂單時，total_revenue 為 NULL
$revenue = $result['total_revenue'];  // NULL
$avg = $revenue / $order_count;  // Warning: Division by zero

// ✅ 正確：使用 COALESCE 確保為 0
COALESCE(SUM(total_amount), 0) as total_revenue
$revenue = $result['total_revenue'];  // 0
```

### 決策 3: 變化百分比計算邏輯

**背景:**
需要計算與上期相比的成長率，但上期可能為 0（除以零錯誤）。

**決策:**
```php
private function calculateChangePercent($current, $previous): float
{
    if ($previous == 0) {
        return $current > 0 ? 100.0 : 0.0;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}
```

**邏輯說明:**
- 上期 = 0，本期 > 0 → 返回 100%（代表從無到有）
- 上期 = 0，本期 = 0 → 返回 0%（無變化）
- 上期 > 0 → 正常計算百分比

**替代方案（未採用）:**
- 返回 `null`（前端需額外處理）
- 返回 `Infinity`（不適合 JSON 傳輸）

### 決策 4: 營收趨勢填補缺失日期

**背景:**
FluentCart 資料庫只儲存有訂單的日期，沒有訂單的日期不會有記錄。但 Chart.js 需要完整日期序列，否則圖表會斷開。

**決策:**
使用迴圈產生完整日期陣列，未找到資料的日期填入 0。

**範例:**
```
資料庫: [2026-01-01: 1000, 2026-01-03: 1500]
輸出: [
    '01/01': 1000,
    '01/02': 0,      // 填補
    '01/03': 1500
]
```

**為什麼不在 SQL 中填補:**
- MySQL 沒有內建的日期序列產生函數（需要 recursive CTE，複雜且效能差）
- PHP 迴圈更簡單直觀

### 決策 5: 最近活動限制 7 天

**背景:**
活動表可能有大量歷史資料，查詢全部會影響效能。

**決策:**
只查詢最近 7 天的活動（`WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)`）。

**理由:**
- 儀表板不需要顯示過舊的活動
- 7 天資料量可控（即使每天 1000 筆訂單，也只有 7000 筆）
- 可利用 `created_at` 索引加速查詢

**效能測試（待驗證）:**
- 預期查詢時間 < 100ms（使用索引）
- 如果超過 500ms，需考慮加入快取

---

## 資料查詢細節

### FluentCart 資料表結構

**fct_orders (訂單表)**
```
id BIGINT - 訂單 ID
customer_id BIGINT - 客戶 ID
status VARCHAR(20) - 訂單狀態（pending, completed, failed...）
payment_status VARCHAR(20) - 付款狀態（paid, unpaid, refunded...）
total_amount BIGINT - 訂單總金額（分）
currency VARCHAR(10) - 幣別（TWD, USD, CNY）
created_at DATETIME - 建立時間
mode ENUM('live','test') - 模式（正式/測試）
```

**fct_customers (客戶表)**
```
id BIGINT - 客戶 ID
first_name VARCHAR(192) - 名
last_name VARCHAR(192) - 姓
email VARCHAR(192) - Email
created_at DATETIME - 註冊時間
```

**fct_order_items (訂單項目表)**
```
id BIGINT - 項目 ID
order_id BIGINT - 訂單 ID
post_id BIGINT - 商品 ID（WordPress Post）
quantity INT - 數量
unit_price BIGINT - 單價（分）
line_total BIGINT - 小計（分）
```

### 索引建議（供 DBA 參考）

```sql
-- 訂單表
CREATE INDEX idx_orders_stats ON fct_orders (payment_status, created_at, mode);
CREATE INDEX idx_orders_currency ON fct_orders (currency, payment_status, created_at);

-- 客戶表
CREATE INDEX idx_customers_created ON fct_customers (created_at);

-- 商品表
CREATE INDEX idx_posts_type_status ON wp_posts (post_type, post_status);
```

**說明:**
- `idx_orders_stats`: 用於 calculateStats() 查詢
- `idx_orders_currency`: 用於 getRevenueTrend() 查詢（支援幣別篩選）
- `idx_customers_created`: 用於 getRecentActivities() 查詢
- `idx_posts_type_status`: 用於 getProductOverview() 查詢

---

## 偏離計畫記錄

### 無偏離

計畫執行完全符合 PLAN.md，所有任務按預期完成。

---

## 已知問題與限制

### 1. 缺少快取機制

**問題:**
DashboardService 每次調用都直接查詢資料庫，可能影響效能。

**影響:**
- 如果儀表板頁面有多個使用者同時訪問，資料庫負載會增加
- 統計資料變化頻率低（5 分鐘更新一次足夠），但每次請求都重新查詢

**解決方案（留待 Phase 21-02 實作）:**
- API 層使用 WordPress Transients API 實作快取
- 快取時效: 5 分鐘（統計數據）、15 分鐘（趨勢數據）

### 2. 沒有測試大量資料效能

**問題:**
目前測試環境資料量較小，未測試百萬級訂單的查詢效能。

**風險:**
- `getRevenueTrend()` 需要 GROUP BY DATE(created_at)，可能慢
- `getRecentActivities()` 的 UNION ALL 查詢可能超時

**緩解策略（已實作）:**
- 限制查詢範圍（7 天內、30 天內）
- 使用 `created_at` 索引
- UNION ALL 各自 LIMIT 5，避免掃描過多資料

**待辦（Phase 21-04 效能優化）:**
- 使用假資料產生工具建立 100 萬筆訂單測試
- 監控慢查詢並優化

### 3. 缺少多賣家隔離機制

**問題:**
當前 DashboardService 查詢全部訂單和客戶，沒有按賣家過濾。

**影響:**
- 如果 BuyGo Plus One 是多賣家平台，目前邏輯會顯示所有賣家的統計
- 需要在查詢中加入賣家 ID 條件

**解決方案（留待需求確認後實作）:**
```php
// 方案 1: 在查詢中加入 JOIN customer.user_id
WHERE c.user_id = {current_seller_id}

// 方案 2: 在 orders 表新增 seller_id 欄位
WHERE o.seller_id = {current_seller_id}
```

**決定延後的原因:**
- FLUENTCART-DATABASE-ANALYSIS.md 中提到多種賣家隔離方案，但尚未確定採用哪一種
- 目前先實作單賣家邏輯，待需求明確後再重構

---

## 下一步行動

### Phase 21-02: Dashboard API 實作

**需要做的事:**
1. 建立 `includes/api/class-dashboard-api.php`
2. 註冊 4 個 REST 端點:
   - `GET /dashboard/stats`
   - `GET /dashboard/revenue`
   - `GET /dashboard/products`
   - `GET /dashboard/activities`
3. 實作快取機制（Transients API）
4. 錯誤處理和權限檢查

**技術依賴:**
- ✅ DashboardService 已完成
- ⏳ 需要沿用現有 API 類別的模式（參考 `Customers_API`, `Orders_API`）

### Phase 21-03: Dashboard 前端頁面

**需要做的事:**
1. 建立 `admin/partials/dashboard.php` Vue 3 頁面
2. 載入 Chart.js CDN
3. 實作統計卡片組件
4. 實作營收趨勢圖表組件
5. 實作活動列表組件

**技術依賴:**
- ✅ DashboardService 已完成
- ⏳ Dashboard API 需先完成

---

## 提交記錄

```
9b8a5bb feat(21-01): create DashboardService class structure
5ce6908 feat(21-01): implement calculateStats method
42b04ad feat(21-01): implement getRevenueTrend method
a8f63e0 feat(21-01): implement getProductOverview and getRecentActivities
```

**檔案變更:**
- ✅ 新增 `includes/services/class-dashboard-service.php` (346 行)

**測試狀態:**
- ✅ PHP 語法檢查通過
- ⏳ 單元測試待補（Phase 21-04）
- ⏳ 整合測試待補（Phase 21-04）

---

## 參考文件

- `.planning/phases/21-dashboard/FLUENTCART-DATABASE-ANALYSIS.md` - FluentCart 資料庫結構分析
- `.planning/phases/21-dashboard/TECH-SOLUTION.md` - Dashboard 技術方案（包含 SQL 範例）
- `includes/services/class-order-service.php` - 現有 Service 實作模式參考

---

**執行時長:** 1 分鐘
**執行日期:** 2026-01-29
**執行者:** Claude Sonnet 4.5 (GSD Execute Agent)
