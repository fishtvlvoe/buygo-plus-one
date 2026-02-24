---
phase: 47-spa-data-loading
plan: 02
status: completed
commits:
  - e394508: "feat(47-02): 4 個 Composable 頁面加入 onUnmounted 事件清理"
  - 3abc3fd: "feat(47-02): CustomersPage + Dashboard SPA 適配"
  - a47b1d2: "feat(47-02): 5 個列表頁面 loading spinner 替換為 shimmer skeleton"
---

# Plan 47-02 Summary — 8 頁面 SPA 適配

## 完成項目

### Task 1: 4 Composable 頁面 onUnmounted 事件清理
- **useOrders.js**: 5 個 listener（click, storage, pageshow, visibilitychange, popstate）提取為具名函式 + onUnmounted 配對清理
- **useProducts.js**: 4 個 listener（pageshow, visibilitychange, resize, popstate）+ userPreferredMode/isAutoSwitched 提取到 onMounted 外部
- **useShipmentProducts.js**: 2 個 listener（pageshow, visibilitychange）清理
- **useShipmentDetails.js**: 4 個 listener（pageshow, visibilitychange, click, popstate）+ flatpickr instance 清理

### Task 2a: CustomersPage + Dashboard SPA 適配
- **CustomersPage.js**: 加入 onUnmounted 清理 popstate listener
- **Dashboard**: 新增 `beforeUnmount()` 銷毀 Chart.js；新增 `initFromCache()` 三層載入策略；四個 load 方法成功後 `BuyGoCache.set()`
- **Settings**: 已有 onUnmounted 清理機制，不需改動
- **Search**: 無初始資料載入，不需改動

### Task 2b: 5 頁面 Loading Skeleton 替換
- orders/products/customers/shipment-products/shipment-details
- `animate-spin` spinner 和 `buygo-loading-spinner` 統一替換為 `buygo-content-skeleton` shimmer 動畫
- 子頁面 loading spinner（buyersLoading, allocationLoading, detailLoading 等）保持不變

## 修改檔案清單
- `includes/views/composables/useOrders.js` — onUnmounted 清理
- `includes/views/composables/useProducts.js` — onUnmounted 清理
- `includes/views/composables/useShipmentProducts.js` — onUnmounted 清理
- `includes/views/composables/useShipmentDetails.js` — onUnmounted 清理
- `admin/js/components/CustomersPage.js` — onUnmounted 清理
- `admin/partials/dashboard.php` — BuyGoCache 快取整合 + Chart.js cleanup
- `admin/partials/orders.php` — skeleton 替換
- `admin/partials/products.php` — skeleton 替換
- `admin/partials/customers.php` — skeleton 替換
- `admin/partials/shipment-products.php` — skeleton 替換
- `admin/partials/shipment-details.php` — skeleton 替換

## 設計決策
- **不使用 useDataLoader 替換現有快取邏輯**：各頁面的資料處理邏輯太複雜（filter/map/stats 特殊處理），直接替換風險過高。保留現有 initFromPreloadedData + BuyGoCache 手動快取。onUnmounted 清理才是 SPA 的關鍵需求。
- **Dashboard 手動實作 cache-first**：因為使用 Options API，無法直接用 useDataLoader (Composition API)
- **具名 handler 模式**：統一使用 `handleXxx` 具名函式取代匿名 listener，確保 addEventListener/removeEventListener 配對正確

## 待人工驗證（Task 3）
見 47-02-PLAN.md Task 3 的測試步驟。
