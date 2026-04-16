## Why

商品列表頁載入時，多樣式商品的「下單」、「採購」、「可分配」、「已分配」等統計數字顯示的是所有 variation 的加總，而非當前選中 variation 的個別數字。使用者必須手動切換一次 variation 下拉選單才能看到正確數字，造成誤判庫存狀態。

## What Changes

- 修改 `includes/views/composables/useProducts.js` 的 `loadProducts()` 函式：在初始化 `product.selected_variation` 之後，立即呼叫 `onVariationChange(product)` 觸發 `/variations/{id}/stats` API，讓初始載入就顯示當前選中 variation 的正確統計數字

## Non-Goals

- 不修改後端 API
- 不影響無 variation 商品的行為
- 不修改 variation 下拉選單的切換邏輯（`onVariationChange` 本身邏輯正確，只差初始化未觸發）

## Capabilities

### New Capabilities

（無）

### Modified Capabilities

- `product-variation-stats-display`：多樣式商品統計數字初始化時即顯示當前 variation 的個別數字，而非全體加總

## Impact

- 修改檔案：`includes/views/composables/useProducts.js`（`loadProducts()` 第 511-520 行附近）
- 不影響後端、不影響 API、不影響測試覆蓋率以外的功能
