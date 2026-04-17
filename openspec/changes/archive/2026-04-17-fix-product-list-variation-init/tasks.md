## 1. 修復初始化邏輯（Product list initializes variation stats on load）

- [x] 1.1 [Tool: cursor] 在 `includes/views/composables/useProducts.js` 的 `loadProducts()` 函式（L519，`.map()` 結束後、`totalProducts.value = ...` 之前），實作「用 Promise.all 並行初始化，不在 .map() 內直接 await」的設計決策：收集 `products.value` 中所有 `has_variations = true` 且有 `selected_variation_id` 的商品，用 `Promise.all()` 並行呼叫 `onVariationChange(p)`，確保初始載入時即顯示 "Product list initializes variation stats on load" 的正確 variation 數字
- [x] 1.2 [Tool: cursor] 確認 `onVariationChange` 已有 try/catch（L1130），不需額外包裝；確認 `Promise.all` 的 reject 不會導致 `loadProducts` 整體失敗（各自 catch 即可）

## 2. CR 問題修復

- [x] 2.1 [Tool: cursor] 修復 Thundering-herd：在 `includes/views/composables/useProducts.js` 的 `loadProducts()` 中，將並行 `onVariationChange` 改為限制最多 3 個並行執行（用簡易 semaphore 或分批 `Promise.all`），避免一頁 20 個商品同時打 20 個 API 導致 server 壓力
- [x] 2.2 [Tool: cursor] 修復 loading spinner 過長：將 variation stats 的並行 fetch 改為「靜默二次載入」——商品列表先渲染（`loading.value = false`），再用 fire-and-forget 方式補齊各 variation 的統計數字，使用者看到列表後數字才陸續填入
- [ ] 2.3 [Tool: cursor] 修復快速翻頁 race condition：在 `loadProducts()` 中加入 request token（每次呼叫產生新 token），`onVariationChange` 回來後先比對 token，若已過期則放棄更新

## 3. 驗證

- [x] 3.1 [Tool: codex] 執行 `composer test` 確認 245 tests 全過，無回歸
- [x] 3.2 [Tool: kimi] 二次 Code Review：確認 thundering-herd、race condition、loading 三個修復正確，無新問題引入
