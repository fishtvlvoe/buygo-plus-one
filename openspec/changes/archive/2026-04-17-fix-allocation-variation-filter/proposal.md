## Why

庫存分配頁面（AllocationPage）有一個 variation 篩選下拉選單（全部 / 1號 / 2號），但切換後頁面上方的統計數字（已下單件數、已採購、可分配、已分配）不會跟著變動，一直顯示所有 variation 的加總。使用者無法在分配頁面直觀掌握單一 variation 的庫存狀況。

## What Changes

- 修改分配頁前端邏輯：variation 篩選下拉選單切換時，上方統計數字根據選取的 variation 重新計算並顯示
- **選「全部」**：已下單件數 = 所有 variation 購買件數總和；已採購/可分配/已分配 = 所有 variation 對應數字加總
- **選特定 variation（如 1號）**：已下單件數 = 該 variation 的購買件數；已採購/可分配/已分配 = 僅該 variation 的數字
- 訂單列表區塊的篩選行為（已有正確實作）不動

## Non-Goals

- 不修改後端 API（現有 `/variations/{id}/stats` 已可提供所需數據）
- 不修改訂單列表的篩選邏輯（已正常運作）
- 不影響無 variation 商品的分配頁面

## Capabilities

### New Capabilities

（無）

### Modified Capabilities

- `allocation-variation-filter`：分配頁面的 variation 篩選下拉選單切換時，上方統計數字（已下單件數、已採購、可分配、已分配）跟著對應 variation 更新

## Impact

- 修改檔案：分配頁面前端 Vue 元件（`admin/partials/products.php` 分配子頁面區塊，或對應的 JS composable）
- 可能需要新增 `/variations/{id}/stats` 的呼叫邏輯，或從現有商品資料中計算各 variation 個別數字
- 不影響後端 Service 層
