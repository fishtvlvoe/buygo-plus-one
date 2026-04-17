# Tasks: fix-order-quantity-and-fluentcart-delete

## 1. Bug 1：修復 allocationPageStats 改為即時計算（Allocation page ordered count matches buyers page ordered count）

- [x] 1.1 [Tool: cursor] 實作 Allocation page ordered count matches buyers page ordered count：修改 `includes/views/composables/useProducts.js`，在 `loadProductOrders` 成功回傳後，即時用 `data.data.reduce((sum,o) => sum + (o.required||o.quantity||0), 0)` 更新 `selectedProduct.value.ordered`；`allocationPageStats` computed 維持使用 `selectedProduct.value?.ordered`（不改 computed 本身）
  - 注意：初版實作用 reduce 直接改 computed，導致 variant 篩選模式數字錯亂；已回退並改為在 loadProductOrders 更新來源值
- [ ] 1.2 [Tool: cursor] 手動驗證：進入庫存分配頁（全部模式），確認「已下單」數字與下單名單頁的「下單數量」相符（對應 spec: Allocation page ordered count equals buyers page ordered count）
- [ ] 1.3 [Tool: cursor] 修復 variant 篩選模式「已下單」數字：目前 variant 模式呼叫 `/variations/{id}/stats` API，其 `ordered` 含已出貨歷史訂單（如 id=353 回傳 5），但下方列表只顯示待分配訂單（加總為 3），上下不一致。
  - **根因**：stats API 與 orders API 範疇不同（前者含已出貨、後者只含待分配）
  - **修法（方案 A）**：variant 模式不再呼叫 stats API，改從已載入的 `productOrders` 直接 filter + reduce：
    - `ordered = productOrders.filter(o => o.object_id === varId).reduce(sum + required)`
    - `allocated = productOrders.filter(o => o.object_id === varId).reduce(sum + already_allocated)`
    - `purchased` 仍從 `allocationVariationStats`（stats API）取，因為只有它有採購數量
  - **影響範圍**：`useProducts.js` 的 `allocationPageStats` computed + watch(allocationSelectedVariant) 邏輯
  - **驗證**：切換各 variant，上方「已下單」= 下方列表 required 加總

## 2. Bug 2：ProductService::deleteProductPost — Deleting a product in BuyGo moves the FluentCart product post to trash

- [x] 2.1 [Tool: codex] 實作 Deleting a product in BuyGo moves the FluentCart product post to trash：在 `includes/services/class-product-service.php` 新增 `deleteProductPost(int $variationId): bool` 方法，邏輯如下：
  1. 查 `fct_product_variations` 取得 `$post_id`（WHERE id = $variationId）
  2. 查詢同一 `$post_id` 下仍有 `item_status = 'active'` 且 `id != $variationId` 的 variation 數量
  3. 若數量 > 0 → return false（不刪除）
  4. 若數量 = 0 → 呼叫 `wp_trash_post($post_id)`，catch 任何異常並 return false，成功 return true
  （對應 design: Bug 2：wp_trash_post 同步刪除邏輯放在 ProductService）
- [x] 2.2 [Tool: codex] 修改 `includes/api/class-products-api.php:605-618`：在 `$variation->save()` 成功後呼叫 `$productService->deleteProductPost($id)`（靜默失敗，不影響 API 回傳）
  （對應 spec: wp_trash_post failure does not block variation delete）
- [x] 2.3 [Tool: codex] 在 `tests/Unit/Services/` 新增 `ProductServiceDeletePostTest.php`，測試案例：
  - 單一 variation 商品刪除後 `wp_trash_post` 被呼叫一次
  - 多樣式商品仍有其他 active variation 時 `wp_trash_post` 不被呼叫
  - 多樣式商品最後一個 active variation 刪除後 `wp_trash_post` 被呼叫
  - `wp_trash_post` 拋出異常時方法回傳 false 且不 throw
  執行 `composer test -- --filter ProductServiceDeletePostTest` 確認全綠
  （對應 spec: Single-variation product deleted、Multi-variation product — only one variation deleted、Multi-variation product — all variations deleted）
- [x] 2.4 [Tool: codex] 執行 `composer test` 確認全套測試無回歸

## 3. 確認既有行為不受影響（Product list initializes variation stats on load）

- [x] 3.1 [Tool: cursor] 驗證商品列表頁初始載入時，多樣式商品仍透過 `/variations/{id}/stats` 取得 default variation 統計（Product list initializes variation stats on load 行為不受本次修改影響）

## 4. Code Review 與收尾

- [x] 4.1 [Tool: kimi] Code Review：review `useProducts.js`（第 195-204 行 diff）、`class-product-service.php`（新增方法）、`class-products-api.php`（第 605-618 行 diff）三個檔案的變更，確認邏輯正確、無架構規範違反
- [x] 4.2 [Tool: codex] git add + commit，訊息格式：`fix: allocation page ordered count uses live productOrders; trash fc_product on delete`
