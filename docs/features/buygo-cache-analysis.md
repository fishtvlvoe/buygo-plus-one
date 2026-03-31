# BuyGoCache 切頁效能分析

> 分析日期：2026-04-01
> 問題：SPA 切頁需要 2-3 秒，期望接近 0 秒

## 現狀架構

```
切頁流程：
BuyGoRouter.spaNavigate(page)
  → history.pushState
  → BuyGoCache.preloadPage(page)  // 同步觸發但不等結果
  → Vue 更新 currentPage
    → 新頁面 mounted()
      → BuyGoCache.get(key)       // 讀快取
      → 有快取 → 先渲染舊資料
      → loadXxx()                 // 立刻打 API（問題在此）
```

## Bug 1（P0）：loadXxx() 破壞 SWR

位置：useOrders.js, useProducts.js, useShipmentDetails.js, useShipmentProducts.js

```js
// onMounted 流程
const cached = BuyGoCache.get('orders');
if (cached) {
    orders.value = cached;       // ✅ 先用快取渲染
    loading.value = false;       // ✅ 不顯示 loading
}
loadOrders();                    // ❌ 立刻又呼叫

// loadOrders() 第一行
const loadOrders = async () => {
    loading.value = true;        // ❌ 畫面立刻變回 skeleton！
    // ... fetch API ...
};
```

快取渲染存活不到 1 個 render frame 就被 `loading=true` 蓋掉。

### 修法

```js
// loadOrders 加入 silent 參數
const loadOrders = async (options = {}) => {
    if (!options.silent) {
        loading.value = true;
    }
    // ... fetch API ...
};

// onMounted 背景刷新用 silent
if (cached) {
    loading.value = false;
    loadOrders({ silent: true });  // 不顯示 loading
} else {
    loadOrders();                  // 首次載入正常顯示 loading
}
```

需套用到：useOrders, useProducts, useShipmentDetails, useShipmentProducts, CustomersPage

## Bug 2（P0）：背景刷新不檢查快取新鮮度

即使快取是 5 秒前剛存的，切頁時也立刻打 API。

### 修法

```js
const cached = BuyGoCache.get('orders');
const isFresh = BuyGoCache.isFresh('orders', 30000); // 30 秒內算新鮮

if (cached) {
    orders.value = cached;
    loading.value = false;

    if (!isFresh) {
        loadOrders({ silent: true });  // 只在過期時才背景刷新
    }
} else {
    loadOrders();
}
```

## Bug 3（P1）：API 呼叫強制繞過所有快取

```js
// 現在（壞）
let url = `/wp-json/...?_t=${Date.now()}`;  // 時間戳讓每次 URL 不同
fetch(url, {
    cache: 'no-store',
    headers: { 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' }
});

// 修法：移除 _t 和 no-store（只在使用者主動操作後才 bypass）
let url = `/wp-json/...`;
fetch(url);  // 讓瀏覽器 HTTP cache 生效
```

## Bug 4（P1）：preload() 延遲 2 秒

位置：BuyGoCache.js 第 157 行

```js
setTimeout(function() {
    endpoints.forEach(function(ep) { ... });
}, 2000);  // 使用者 2 秒內切頁 → 預載還沒開始
```

### 修法

改為 500ms 或立即執行（用 requestIdleCallback）。

## 修復優先順序

| 順序 | Bug | 影響範圍 | 修改檔案 |
|------|-----|---------|---------|
| 1 | loadXxx() 加 silent 模式 | 所有頁面切頁 | useOrders.js, useProducts.js, useShipmentDetails.js, useShipmentProducts.js, customers.php |
| 2 | 背景刷新前檢查快取新鮮度 | 所有頁面切頁 | 同上 |
| 3 | 移除 _t=Date.now() 和 no-store | API 效能 | 同上 |
| 4 | preload 延遲改為 500ms | 首次切頁 | BuyGoCache.js |

## 預期效果

修復 Bug 1+2 後：
- 快取命中時：切頁 ~0ms（直接顯示快取資料，不閃爍）
- 快取過期時：切頁 ~0ms 顯示舊資料，背景 2-3 秒後靜默更新
- 快取未命中（首次）：仍需 2-3 秒（顯示 skeleton）

## 注意事項

- `useDataLoader` composable 已設計正確的 silent 模式，但各頁面的 useXxx 沒有使用它
- `buygoInitialData` PHP 預注入已停用（template.php），可考慮重新啟用加速首次載入
- FluentCart 的快是因為 React 編譯打包 + 正確的資料快取策略，不是框架差異
