## Why

下單名單頁與庫存分配頁顯示的「下單數量」數字不一致（一顯示 4、一顯示 3），導致賣家無法信任統計數字。此外，BuyGo 後台刪除商品後 FluentCart 商品本體未同步刪除，造成賣家困惑與資料不一致。

## What Changes

- **Bug 1 修復**：`allocationPageStats`（`useProducts.js:195`）在「全部」模式下，改為從已載入的 `productOrders` 陣列即時加總 `quantity`，取代使用 `selectedProduct.ordered`（商品列表快取值）
- **Bug 2 修復**：`batch_delete`（`class-products-api.php:568`）刪除 variation 後，同步將對應的 WordPress post（`fc_product` post type）移至垃圾桶（`wp_trash_post()`）；多樣式商品需確認同一 `post_id` 下所有 variation 都已 inactive 才執行

## Non-Goals

- 不修改 `/products` API 的快取機制或主動 refresh 邏輯
- 不硬刪除（`wp_delete_post(true)`）FluentCart 商品，只移到垃圾桶
- 不處理已有子訂單的商品刪除保護（已有子訂單的刪除行為維持現狀）
- 不修改 FluentCart variation 的刪除方式（仍用 `item_status = inactive` 軟刪除）

## Capabilities

### New Capabilities

（無）

### Modified Capabilities

- `product-variation-stats-display`：庫存分配頁「全部」模式的 `ordered` 統計改為從即時訂單資料計算，確保與下單名單頁數字一致

## Impact

- Affected code:
  - `includes/views/composables/useProducts.js`（`allocationPageStats` computed，第 195-204 行）
  - `includes/api/class-products-api.php`（`batch_delete` 方法，第 605-618 行）
- Affected APIs:
  - `POST /products/batch-delete`（新增同步刪除 WordPress post 邏輯）
- Affected specs:
  - `openspec/specs/product-variation-stats-display/spec.md`（需新增庫存分配頁統計一致性需求）
