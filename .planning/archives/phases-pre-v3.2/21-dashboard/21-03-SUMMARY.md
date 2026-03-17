---
phase: 21-dashboard
plan: 03
subsystem: ui
tags: [vue3, chartjs, dashboard, frontend, responsive]

# Dependency graph
requires:
  - phase: 21-01
    provides: DashboardService with 4 query methods
  - phase: 21-02
    provides: Dashboard_API with 4 REST endpoints
provides:
  - dashboard.php Vue 3 frontend page
  - Chart.js integration for revenue trends
  - Responsive stats grid with 4 metric cards
  - Products overview and activities timeline

affects: [22-orders-ui, 23-customers-ui, frontend-pages]

# Tech tracking
tech-stack:
  added: [Chart.js 4.x CDN]
  patterns:
    - Vue 3 composition with setup()
    - Promise.all parallel API loading
    - Chart.js canvas rendering with $nextTick
    - Responsive grid layout (desktop/tablet/mobile)

key-files:
  created:
    - buygo-plus-one-dev/admin/partials/dashboard.php
  modified:
    - buygo-plus-one-dev/admin/css/dashboard.css

key-decisions:
  - "使用 Promise.all 平行載入 4 個 API 端點（效能優化）"
  - "Chart.js 圖表使用 $nextTick 確保 DOM 渲染完成"
  - "金額顯示統一為「分 → 元」格式，千分位逗號"
  - "時間顯示為相對時間（X 分鐘前、X 小時前、X 天前）"

patterns-established:
  - "Dashboard 頁面模式：stats-grid → charts-grid → bottom-grid (products + activities)"
  - "響應式佈局：4欄 → 2欄 → 1欄（桌面 → 平板 → 手機）"
  - "載入狀態使用 skeleton screen with animate-pulse"
  - "API 請求統一使用 window.buygoWpNonce 驗證"

# Metrics
duration: 15min
completed: 2026-01-29
---

# Phase 21 Plan 03: Dashboard 前端頁面 Summary

**Vue 3 Dashboard 頁面含統計卡片、Chart.js 營收圖表、商品概覽、活動時間軸，響應式三段式佈局**

## Performance

- **Duration:** 15 min
- **Started:** 2026-01-28T22:38:13Z
- **Completed:** 2026-01-28T22:53:00Z
- **Tasks:** 5 (merged into 1 commit)
- **Files modified:** 2

## Accomplishments

- 建立完整的 Dashboard Vue 3 頁面（300+ 行）
- 整合 Chart.js 4.x 顯示 30 天營收趨勢折線圖
- 實作 4 個統計卡片（營收、訂單、客戶、平均金額）含變化百分比
- 實作商品概覽和最近活動時間軸
- 響應式佈局支援桌面/平板/手機三種尺寸
- 完整的載入狀態（skeleton screen）和錯誤處理

## Task Commits

All tasks completed in single atomic commit:

1. **Tasks 1-5: Complete Dashboard Frontend** - `5c9c4b8` (feat)

## Files Created/Modified

- `admin/partials/dashboard.php` - Vue 3 Dashboard 頁面元件，包含統計卡片、圖表、商品概覽、活動列表
- `admin/css/dashboard.css` - Dashboard 專用樣式，含響應式網格佈局和底部區域網格
- `flush-rewrite.php` - 臨時 rewrite flush 腳本（未提交，僅本地使用）

## Decisions Made

1. **平行載入 API** - 使用 `Promise.all([loadStats(), loadRevenue(), ...])` 同時載入 4 個端點，減少等待時間
2. **Chart.js 渲染時機** - 使用 `this.$nextTick(() => this.renderRevenueChart())` 確保 canvas 元素已渲染
3. **金額格式化** - 統一使用 `cents / 100` 轉換為元，搭配 `toLocaleString()` 顯示千分位
4. **時間格式化** - 實作 `formatTimeAgo()` 顯示相對時間（5 分鐘前、2 小時前、3 天前）
5. **響應式佈局** - 底部區域使用 `grid-template-columns: 1fr 2fr`（商品概覽:活動列表），手機版改為單欄

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

**1. Rewrite Rules 未 Flush**
- **問題:** 新建立的 dashboard.php 頁面路由返回 404
- **原因:** 路由已在 `class-routes.php` 註冊，但 WordPress rewrite rules 未重新整理
- **解決:** 建立 `flush-rewrite.php` 腳本供使用者手動執行
- **影響:** 需要使用者在遠端環境執行一次 rewrite flush 才能訪問頁面

**2. InstaWP 掛載路徑不可用**
- **問題:** `/Volumes/insta-mount/` 路徑不存在，無法直接使用 WP-CLI
- **解決:** 透過 PHP 腳本處理 rewrite flush
- **影響:** 無法在本次執行中完整驗證頁面載入

## User Setup Required

**需要手動操作：Flush Rewrite Rules**

由於 WordPress rewrite rules 需要重新整理才能識別新路由，請執行以下任一方式：

**方式 1: 透過 WordPress 後台（推薦）**
1. 登入 WordPress 後台
2. 前往「設定 → 永久連結」
3. 不需修改任何設定，直接點擊「儲存變更」
4. 訪問 http://buygo.me/buygo-portal/dashboard/

**方式 2: 使用臨時腳本（需 FTP/SSH 權限）**
1. 將 `flush-rewrite.php` 上傳到 WordPress 根目錄
2. 訪問 http://buygo.me/flush-rewrite.php
3. 看到成功訊息後，**立即刪除該檔案**（安全考量）
4. 訪問 http://buygo.me/buygo-portal/dashboard/

**方式 3: 透過 WP-CLI（需 SSH 權限）**
```bash
wp rewrite flush --hard
```

**驗證：**
- 訪問 http://buygo.me/buygo-portal/dashboard/ 應顯示 Dashboard 頁面（非 404）
- Console 應可看到 4 個 API 請求（/dashboard/stats, /revenue, /products, /activities）
- 統計卡片應正常顯示數據
- 營收趨勢圖表應正常渲染

## Next Phase Readiness

**已完成：**
- Dashboard 前端頁面完整實作
- 所有 Vue 元件和方法定義完成
- API 整合邏輯完成
- 響應式佈局完成
- 載入和錯誤狀態處理完成

**待完成（後續計畫）：**
- 21-04: Dashboard 整合測試和樣式優化
- 22-xx: Orders 頁面重構
- 23-xx: Customers 頁面重構

**阻礙：**
- 需要 flush rewrite rules 才能訪問頁面（一次性操作）
- API 端點功能依賴 21-01 和 21-02 的實作

**技術債：**
- Chart.js 圖表目前無法調整時間範圍（hardcoded 30 天）
- 活動列表固定顯示 10 筆，未來可考慮分頁或無限滾動
- 商品概覽僅顯示基本統計，未來可擴展為詳細圖表

---
*Phase: 21-dashboard*
*Completed: 2026-01-29*
