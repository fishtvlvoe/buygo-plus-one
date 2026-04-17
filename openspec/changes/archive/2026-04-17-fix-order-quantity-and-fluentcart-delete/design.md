## Context

本 change 修復兩個獨立 bug：

**Bug 1 — 下單數量不一致**

庫存分配頁（`admin/partials/products.php:975`）的「已下單」數字使用 `allocationPageStats.ordered`，在「全部」模式（未選擇特定 variant）時取值自 `selectedProduct.ordered`。`selectedProduct` 是用戶進入商品列表時由 `/products` API 填入的快取物件，進入分配頁後不會再更新。

下單名單頁（`admin/partials/products.php:536`）的「下單數量」使用 `buyersSummary.totalQuantity`，每次進入下單名單頁都會重新呼叫 `/products/{id}/buyers` API，加總每筆訂單的 `quantity`，屬於即時資料。

兩者資料來源不同，在訂單進來後的時間差期間會顯示不一致的數字。

**Bug 2 — FluentCart 商品本體未同步刪除**

`batch_delete`（`includes/api/class-products-api.php:568`）只將 FluentCart `ProductVariation.item_status` 設為 `inactive`（variation 層的軟刪除），但未對 `wp_posts` 中的 `fc_product` post 做任何處理，導致 FluentCart 後台商品列表仍顯示該商品。

`ProductVariation.post_id` 直接對應 `wp_posts.ID`（post_type = `fc_product`），可直接用 `wp_trash_post($post_id)` 移除。

## Goals / Non-Goals

**Goals:**

- 庫存分配頁「全部」模式的「已下單」數字與下單名單頁一致（均反映即時訂單資料）
- BuyGo 後台刪除商品後，FluentCart 後台商品移至垃圾桶
- 多樣式商品：只有同一 `post_id` 下所有 variation 都 inactive 才刪除商品本體

**Non-Goals:**

- 不重構 `/products` API 的快取機制
- 不硬刪除 FluentCart 商品（`wp_delete_post(true)`），只移垃圾桶
- 不保護「已有子訂單的商品」不被刪除（此屬另一 change 範疇）
- 不修改 variation 刪除方式（仍用 `item_status = inactive`）

## Decisions

### Bug 1：allocationPageStats 改為即時計算

**決策**：`allocationPageStats` 在「全部」模式下，`ordered` 改為 `productOrders.value.reduce((sum, o) => sum + (o.quantity || 0), 0)`。

**理由**：
- `productOrders` 是進入庫存分配頁時即時從 `/products/{id}/orders` API 載入的，與 `buyersSummary` 資料來源語意等價
- 替代方案「進入分配頁時重新 fetch selectedProduct」需要額外 API call 且影響頁面載入流程，成本較高
- `allocated` 同樣改為從 `productOrders` 加總 `allocated_quantity`，保持一致性
- `purchased` 維持使用 `selectedProduct.purchased`，因 productOrders 不含採購數量

**影響位置**：`includes/views/composables/useProducts.js:195-204`

### Bug 2：wp_trash_post 同步刪除邏輯放在 ProductService

**決策**：將「刪除商品本體」的邏輯移入 `ProductService`（新增 `deleteProductPost` 方法），由 `batch_delete` API handler 呼叫，而非直接在 API 層寫 `wp_trash_post`。

**理由**：
- 架構規範：商業邏輯禁止放在 `includes/api/`
- 邏輯包含「確認同一 post_id 下所有 variation 均 inactive」的判斷，屬於商業規則
- 替代方案：在 API 層直接呼叫 `wp_trash_post` — 違反架構規範，且難以測試

**多樣式商品判斷邏輯**（放在 `ProductService::deleteProductPost`）：
```
1. 取得 $variation->post_id
2. 查 fct_product_variations WHERE post_id = $post_id AND item_status = 'active' AND id != $variation_id
3. 若還有其他 active variation → 不刪除商品本體
4. 若全部 inactive → wp_trash_post($post_id)
```

**影響位置**：
- `includes/services/class-product-service.php`（新增 `deleteProductPost` 方法）
- `includes/api/class-products-api.php:605-618`（呼叫 `ProductService::deleteProductPost`）

## Risks / Trade-offs

- **[Bug 1] productOrders 為空時顯示 0**：用戶進入分配頁但訂單尚未載入完成的瞬間，`ordered` 會顯示 0。但原本行為也有快取不一致問題，此 trade-off 可接受。→ 緩解：`productOrders` 載入前 `allocationLoading` 為 true，UI 顯示 loading state，不顯示數字。
- **[Bug 2] wp_trash_post 失敗不阻斷 variation 軟刪除**：`wp_trash_post` 若失敗（例如 post 已被刪除），應靜默 catch，確保 variation inactive 仍成功回傳。

## Implementation Distribution Strategy

| 任務 | Agent | 理由 |
|------|-------|------|
| Bug 1 前端 JS 修改（useProducts.js） | `[Tool: cursor]` | 純 JS 邏輯，單檔修改 |
| Bug 2 ProductService 新增方法 + API 層修改 | `[Tool: codex]` | 需跑 PHP 測試驗證 |
| PHPUnit 測試（ProductService::deleteProductPost） | `[Tool: codex]` | 需執行 `composer test` 確認 |
| Code Review | `[Tool: kimi]` | 跨 3 個檔案 diff |
