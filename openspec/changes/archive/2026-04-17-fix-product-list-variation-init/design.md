## Context

商品列表頁載入時，`loadProducts()` 在 `.map()` 中設定 `product.selected_variation`，但從未呼叫 `onVariationChange(product)`。`onVariationChange` 會打 `/variations/{id}/stats` API 取得該 variation 的個別統計數字（ordered/purchased/allocated/shipped）。未呼叫的結果是：顯示的是後端 API 回傳的父商品總計，而非選中 variation 的個別數字。

相關檔案：`includes/views/composables/useProducts.js`

## Goals / Non-Goals

**Goals:**
- 初始載入後，多樣式商品的統計數字顯示當前選中 variation 的個別數字
- 使用者不需手動切換 variation 才能看到正確數字

**Non-Goals:**
- 不修改後端 API
- 不修改 `onVariationChange` 本身的邏輯
- 不影響無 variation 商品（`has_variations = false`）

## Decisions

### 用 Promise.all 並行初始化，不在 .map() 內直接 await

**決定**：在 `loadProducts()` 的 `.map()` 結束後，收集所有有 variation 的商品，用 `Promise.all()` 並行呼叫 `onVariationChange()`。

```js
// 位置：useProducts.js L519 之後（.map() 結束後）
const productsWithVariations = products.value.filter(
    p => p.has_variations && p.selected_variation_id
);
await Promise.all(productsWithVariations.map(p => onVariationChange(p)));
```

**拒絕方案：在 `.map()` callback 直接 await**
- `.map()` 不是 async context，直接 await 會變成 unhandled Promise
- 所有 variation stats API 的錯誤不會進入 `loadProducts` 的 catch 區塊
- Race condition：`products.value` 可能在所有 API 回來前就渲染

**拒絕方案：改用 for...of 循序處理**
- 每頁最多幾十個商品，並行比循序快，使用者體驗較好
- 無特別理由需要循序

## Risks / Trade-offs

- **[Risk] 多個並行 API 請求**：每頁商品數量 × 1 個 `/variations/{id}/stats` 請求。每頁預設 20 筆，有 variation 的商品可能 5-15 筆 → 5-15 個並行請求，在正常網路環境下可接受。→ Mitigation：`onVariationChange` 已有 try/catch，單一請求失敗不影響其他
- **[Trade-off]** 初始載入時間略增（需等所有 variation stats 回來才算完成）→ 可接受，因為數字正確比速度更重要
