---
phase: 37-前端ui元件與互動
plan: 01
subsystem: frontend
tags: [javascript, css, ui-components, responsive-design, api-integration]
dependency-graph:
  requires: ["36-02"]
  provides: ["子訂單前端 UI 元件", "API 呼叫邏輯", "四種 UI 狀態"]
  affects: ["37-02"]
tech-stack:
  added: []
  patterns: ["IIFE 模組化", "Vanilla JavaScript", "Mobile First CSS", "CSS 變數主題化"]
key-files:
  created: []
  modified:
    - assets/js/fluentcart-child-orders.js
    - includes/integrations/class-fluentcart-child-orders-integration.php
decisions:
  - id: DEC-37-01-01
    title: "使用 Vanilla JavaScript 而非框架"
    context: "FluentCart 使用 Vue 3，避免衝突"
    chosen: "Vanilla JavaScript + IIFE 模式"
    rationale: "無依賴、輕量、與 Vue 互不干擾"
  - id: DEC-37-01-02
    title: "從 URL 解析訂單 ID"
    context: "fluent_cart/customer_app hook 不傳遞訂單 ID"
    chosen: "使用 preg_match 從 REQUEST_URI 解析"
    rationale: "支援多種 URL 格式，只在訂單詳情頁顯示 widget"
  - id: DEC-37-01-03
    title: "使用 CSS 變數支援主題化"
    context: "需要與 BuyGo+1 設計系統一致"
    chosen: "var(--buygo-*, fallback) 格式"
    rationale: "向下相容無 CSS 變數的環境，同時支援未來主題自訂"
metrics:
  duration: "2 min 26 sec"
  completed: "2026-02-02"
---

# Phase 37 Plan 01: 前端 UI 元件與互動 Summary

**One-liner:** 完整的子訂單前端 UI 實作，包含 JavaScript API 呼叫（含四種狀態渲染）、Mobile First RWD CSS 樣式、URL 解析訂單 ID 機制

## What Was Done

### Task 1: 實作完整的 JavaScript API 呼叫和 UI 渲染邏輯 (076bd33)

完全重寫 `assets/js/fluentcart-child-orders.js`（303 行），實作：

**狀態映射常數 (STATUS_MAP)**
- payment: pending/paid/failed/refunded
- shipping: unshipped/preparing/shipped/completed
- fulfillment: pending/processing/completed/cancelled

**工具函式**
- `formatCurrency(amount, currency)`: 使用 Intl.NumberFormat 格式化金額
- `getStatusBadge(type, status)`: 產生狀態標籤 HTML
- `escapeHtml(text)`: XSS 防護

**四種狀態渲染函式**
- `renderLoading()`: Spinner + 載入中
- `renderEmpty()`: SVG 圖示 + 此訂單沒有子訂單
- `renderError(orderId)`: SVG 圖示 + 重試按鈕
- `renderChildOrderCard(order, currency)`: 子訂單卡片

**API 呼叫邏輯 `loadChildOrders(orderId, container)`**
- 使用 fetch API 搭配 X-WP-Nonce header
- 正確檢查 `response.ok`（處理 4xx/5xx 錯誤）
- 只在第一次展開時載入資料

### Task 2: 擴充 CSS 樣式支援完整 UI 元件 (1410f7d)

擴充 `get_inline_css()` 方法，新增完整 CSS 樣式：

**新增的 CSS 類別**
- `.buygo-child-orders-list`: 列表容器（Mobile: flex-column, Desktop: grid 2 欄）
- `.buygo-child-order-card`: 卡片樣式（圓角、陰影、白色背景）
- `.buygo-card-header/body/footer`: 卡片區塊
- `.buygo-badge-*`: 5 種狀態標籤顏色（success/warning/danger/info/neutral）
- `.buygo-loading-spinner`: 旋轉動畫
- `.buygo-empty-state`: 空狀態/錯誤狀態

**RWD 響應式設計**
- Mobile First 設計原則
- 桌面版 (>= 768px): 雙欄 grid 佈局
- 按鈕 min-height: 44px 符合觸控目標標準

### Task 3: 修正按鈕的 data-order-id 傳遞機制 (cdbbc34)

新增 `get_order_id_from_url()` 方法：

- 支援多種 URL 格式：
  - `/order/123`
  - `/orders/123`
  - `?order_id=123`
- 只在訂單詳情頁面（有訂單 ID）顯示 widget
- 按鈕加入 `data-order-id` 屬性傳遞給 JavaScript

## Deviations from Plan

None - 計畫完全按照規劃執行。

## Decisions Made

| ID | Decision | Rationale |
|----|----------|-----------|
| DEC-37-01-01 | Vanilla JavaScript + IIFE | 避免與 FluentCart Vue 3 衝突 |
| DEC-37-01-02 | 從 URL 解析訂單 ID | Hook 不傳遞訂單 ID，需自行解析 |
| DEC-37-01-03 | CSS 變數 + fallback | 支援主題化同時向下相容 |

## Verification Results

1. **語法檢查:**
   - `node --check fluentcart-child-orders.js` - OK
   - `php -l class-fluentcart-child-orders-integration.php` - No syntax errors

2. **功能驗證:**
   - JavaScript 關鍵函式: 11 個引用（定義 + 呼叫）
   - CSS 關鍵類別: 10 個引用
   - data-order-id: 存在於按鈕元素

3. **檔案行數:**
   - JavaScript: 303 行（超過 150 行最低要求）
   - CSS: 約 280 行樣式

## Next Phase Readiness

**Ready for Phase 37-02 (整合測試):**
- 前端 UI 元件完整
- API 呼叫邏輯就緒
- 四種狀態（Loading/Success/Empty/Error）都有對應 UI

**待測試項目:**
1. 訪問 https://test.buygo.me/my-account/purchase-history
2. 進入訂單詳情頁，確認按鈕顯示
3. 點擊按鈕，確認 Loading → 結果轉換
4. 測試 RWD（縮小視窗）

## Files Changed

| File | Change Type | Lines |
|------|-------------|-------|
| assets/js/fluentcart-child-orders.js | Modified | +266/-60 (rewritten) |
| includes/integrations/class-fluentcart-child-orders-integration.php | Modified | +283/-9 |
