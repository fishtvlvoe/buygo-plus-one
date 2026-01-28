---
phase: 21-dashboard
plan: 02
type: summary
status: completed
subsystem: api
tags:
  - dashboard
  - rest-api
  - caching
  - php
requires:
  - 21-01
provides:
  - dashboard-rest-endpoints
  - api-caching-mechanism
affects:
  - 21-03
  - 21-04
tech-stack:
  added: []
  patterns:
    - WordPress-Transients-API
    - REST-API-with-caching
key-files:
  created:
    - includes/api/class-dashboard-api.php
  modified:
    - includes/api/class-api.php
    - includes/class-plugin.php
decisions:
  - id: cache-strategy
    what: Dashboard API 快取策略
    why: 統計資料查詢可能較慢，需要快取以提升效能
    options:
      - WordPress Transients API
      - Object Cache
      - Custom Cache Table
    chosen: WordPress-Transients-API
    reason: 簡單、符合 WordPress 慣例、支援過期時間
  - id: cache-duration
    what: 不同端點的快取時間
    why: 不同資料的即時性需求不同
    chosen:
      - stats: 5 分鐘
      - revenue: 15 分鐘
      - products: 15 分鐘
      - activities: 1 分鐘
    reason: 統計數據可快取較久，活動需要即時性
metrics:
  duration: 170 秒
  completed: 2026-01-29
---

# Phase 21 Plan 02: Dashboard_API - Dashboard REST API 端點層 Summary

**一句話總結:** 建立 4 個 Dashboard REST API 端點，整合 DashboardService 並實作 WordPress Transients 快取機制

## 執行結果

### 完成任務 (5/5)

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | 建立 Dashboard_API 類別架構 | `f6ba563` | includes/api/class-dashboard-api.php |
| 2 | 註冊 4 個 REST API 端點 | `f6ba563` | includes/api/class-dashboard-api.php |
| 3 | 實作 get_stats 方法（含快取） | `3e17fdd` | includes/api/class-dashboard-api.php |
| 4 | 實作 get_revenue, get_products, get_activities 方法 | `3db2de3` | includes/api/class-dashboard-api.php |
| 5 | 在 Plugin 中註冊 Dashboard_API | `2dc1ecc` | includes/api/class-api.php, includes/class-plugin.php |

### 驗證狀態

✅ **所有驗證通過**

- Dashboard_API 類別存在且語法正確
- 4 個端點已註冊到 WordPress REST API
- 所有端點有權限檢查（`API::check_permission`）
- 所有端點有快取機制（不同快取時間）
- 參數驗證和錯誤處理完整
- Plugin 中已註冊 Dashboard_API

### API 端點清單

| 端點 | 方法 | 用途 | 快取時間 | 參數 |
|------|------|------|----------|------|
| `/dashboard/stats` | GET | 總覽統計 | 5 分鐘 | - |
| `/dashboard/revenue` | GET | 營收趨勢 | 15 分鐘 | `period` (預設 30), `currency` (預設 TWD) |
| `/dashboard/products` | GET | 商品概覽 | 15 分鐘 | - |
| `/dashboard/activities` | GET | 最近活動 | 1 分鐘 | `limit` (預設 10) |

## 技術實作細節

### 1. 快取機制

使用 WordPress Transients API 實作快取，每個端點有獨立的快取鍵和過期時間：

```php
// 範例：get_stats 方法
$cache_key = 'buygo_dashboard_stats';
$cached = get_transient($cache_key);

if ($cached !== false) {
    return new \WP_REST_Response([
        'success' => true,
        'data' => $cached,
        'cached_at' => get_transient($cache_key . '_time')
    ], 200);
}

$stats = $this->dashboardService->calculateStats();
set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);
set_transient($cache_key . '_time', current_time('mysql'), 5 * MINUTE_IN_SECONDS);
```

### 2. 快取策略

| 端點 | 快取鍵 | 快取時間 | 理由 |
|------|--------|----------|------|
| `stats` | `buygo_dashboard_stats` | 5 分鐘 | 統計數據變化頻繁 |
| `revenue` | `buygo_dashboard_revenue_{period}_{currency}` | 15 分鐘 | 趨勢數據變化緩慢 |
| `products` | `buygo_dashboard_products` | 15 分鐘 | 商品概覽變化緩慢 |
| `activities` | `buygo_dashboard_activities_{limit}` | 1 分鐘 | 需要即時性 |

**時間戳快取:**
- `stats` 端點額外快取時間戳（`{cache_key}_time`），用於顯示「最後更新時間」

### 3. 權限檢查

所有端點使用統一的權限檢查方法 `API::check_permission`，允許以下角色存取：
- WordPress 管理員（`manage_options`）
- BuyGo 管理員（`buygo_admin`）
- 小幫手（`buygo_helper`）

### 4. 錯誤處理

統一錯誤處理模式：

```php
try {
    // 查詢邏輯
} catch (\Exception $e) {
    error_log('BuyGo Dashboard API Error (get_stats): ' . $e->getMessage());

    return new \WP_REST_Response([
        'success' => false,
        'message' => '取得統計資料失敗：' . $e->getMessage()
    ], 500);
}
```

### 5. 回應格式

統一回應格式：

```json
{
    "success": true,
    "data": { /* 資料內容 */ },
    "cached_at": "2026-01-29 10:30:00"  // 僅 stats 端點包含
}
```

## 決策記錄

### 決策 1: 快取策略

**問題:** Dashboard 統計查詢可能較慢，需要快取以提升效能

**選項:**
1. WordPress Transients API
2. Object Cache（需要 Redis/Memcached）
3. Custom Cache Table

**選擇:** WordPress Transients API

**理由:**
- 簡單易用，符合 WordPress 慣例
- 內建過期時間支援
- 無需額外依賴（Object Cache 需要安裝 Redis/Memcached）
- 適合中小型專案的快取需求

### 決策 2: 快取時間設定

**問題:** 不同資料的即時性需求不同

**選擇:**
- **stats (5 分鐘):** 統計數據變化頻繁，使用較短快取時間
- **revenue (15 分鐘):** 趨勢數據變化緩慢，可快取較久
- **products (15 分鐘):** 商品概覽變化緩慢，可快取較久
- **activities (1 分鐘):** 最近活動需要即時性，使用最短快取時間

**理由:**
- 平衡效能和即時性需求
- 統計數據（stats）每 5 分鐘更新一次已足夠
- 活動列表（activities）需要顯示最新動態，1 分鐘快取是底線

## 與其他計劃的關聯

### 前置依賴 (Requires)

- **21-01:** DashboardService - Dashboard 服務層
  - 提供 `calculateStats()`, `getRevenueTrend()`, `getProductOverview()`, `getRecentActivities()` 方法
  - Dashboard_API 直接調用這些方法取得資料

### 提供給後續計劃 (Provides)

- **dashboard-rest-endpoints:** 4 個 REST API 端點供前端調用
- **api-caching-mechanism:** 快取機制模式可供其他 API 參考

### 影響的計劃 (Affects)

- **21-03:** Dashboard 前端頁面 - 需要調用這些 API 端點
- **21-04:** Dashboard 圖表組件 - 需要使用 revenue 和 products 端點的資料

## 下一步

### 立即可進行的任務

1. **21-03:** 建立 Dashboard 前端頁面（Vue 3）
   - 調用 4 個 API 端點
   - 顯示統計卡片和圖表

2. **21-04:** 整合 Chart.js 圖表庫
   - 使用 revenue 端點資料繪製營收趨勢圖
   - 使用 products 端點資料繪製商品圓餅圖

### 建議的優化方向

1. **效能監控:**
   - 記錄 API 回應時間
   - 監控快取命中率
   - 若查詢慢於 500ms，考慮加索引

2. **快取清除機制:**
   - 訂單建立時清除 stats 和 activities 快取
   - 客戶註冊時清除 activities 快取
   - 商品更新時清除 products 快取

3. **API 擴展:**
   - 支援更多期間選項（7 天、90 天、自訂範圍）
   - 支援更多幣別（USD、JPY 等）
   - 支援分頁（activities 端點）

## 風險和挑戰

### 已知限制

1. **FluentCart 資料表變更風險:**
   - DashboardService 直接查詢 FluentCart 資料表
   - 若 FluentCart 更新改變資料表結構，可能導致查詢失敗
   - **緩解:** 定期檢查 FluentCart 更新日誌，及時調整查詢

2. **快取一致性:**
   - 訂單建立後最多 5 分鐘才會反映在統計中
   - **緩解:** 在訂單建立事件中清除相關快取（留待後續優化）

3. **大量訂單查詢效能:**
   - 若訂單數量超過 10 萬筆，查詢可能變慢
   - **緩解:** 已限制查詢範圍（本月、7 天內），並使用快取

## 測試建議

### 手動測試清單

- [ ] 測試 `/dashboard/stats` 端點，確認回傳格式正確
- [ ] 測試 `/dashboard/revenue?period=7` 端點，確認參數生效
- [ ] 測試快取機制，第二次請求應更快
- [ ] 測試權限檢查，未登入應返回 401
- [ ] 測試錯誤處理，資料庫錯誤應返回 500

### 效能測試

```bash
# 測試首次查詢時間（無快取）
time curl "http://buygo.me/wp-json/buygo-plus-one/v1/dashboard/stats" \
  -H "X-WP-Nonce: $(wp eval 'echo wp_create_nonce("wp_rest");')"

# 測試第二次查詢時間（有快取）
time curl "http://buygo.me/wp-json/buygo-plus-one/v1/dashboard/stats" \
  -H "X-WP-Nonce: $(wp eval 'echo wp_create_nonce("wp_rest");')"
```

預期：第二次查詢應明顯更快（< 100ms）

## 相關文件

- **技術方案:** `.planning/phases/21-dashboard/TECH-SOLUTION.md` (第 3 節 API 設計規格)
- **計劃檔案:** `.planning/phases/21-dashboard/21-02-PLAN.md`
- **前置計劃:** `.planning/phases/21-dashboard/21-01-SUMMARY.md`

---

**執行時間:** 170 秒 (約 3 分鐘)
**完成日期:** 2026-01-29
**執行者:** Claude Sonnet 4.5
