## Why

商品分配統計在不同畫面顯示不一致：分配詳情頁顯示「已分配=2」，但同商品的商品列表頁顯示「已分配=0、待分配=3」。業主誤以為系統壞掉、不敢繼續操作。根因有二，必須同時修：(1) `includes/api/class-products-api.php` 對列表 endpoint 寫入 30 秒 transient 快取（`buygo_products_<user_id>_<md5(params)>`），但 `AllocationWriteService` 寫完 `update_post_meta('_buygo_allocated', ...)` 後無任何 `delete_transient` 呼叫，導致 30 秒內列表頁回舊值；(2) 同檔 list endpoint 與單品 endpoint 的 `reserved`（待分配）公式不一致——單品扣 `allocated`、列表不扣——即使無快取，兩個畫面也永遠對不上。

## What Changes

- 移除 `includes/api/class-products-api.php` 列表 endpoint 的 transient 快取讀寫邏輯（讀取段與寫入段）。
- 統一 `reserved` 計算公式為 `max(0, ordered - purchased - allocated)`，列表 endpoint 與單品 endpoint 採同一邏輯。
- 新增 PHPUnit 純 PHP 邏輯測試，覆蓋（a）相同輸入下列表與單品計算結果一致、（b）`allocated > 0` 時 `reserved` 正確扣除。
- 不變更前端 API contract（回傳欄位與型別維持），不變更其他 endpoint。

## Non-Goals

- 不為列表 endpoint 重建快取機制（如 namespace bust 或 wp_cache group），因 BuyGo 是低流量 LINE 賣家後台，30 秒快取省的 query 不足以抵銷「業主看到錯數字」的信任成本。
- 不修改 `AllocationWriteService` 或其他寫入服務（拔快取後不需要清快取邏輯）。
- 不重構 `class-products-api.php` 的其他職責（純 bug fix，不擴大範圍）。
- 不變更 `_buygo_allocated` post meta 寫入路徑（資料源本身正確，只修「顯示」）。

## Capabilities

### New Capabilities

(none)

### Modified Capabilities

- `api-layer-isolation`: 新增需求說明 products API 列表端點 SHALL NOT 使用 transient 快取統計欄位，並 SHALL 與單品端點共用同一 `reserved` 計算公式。

## Impact

- Affected specs: `api-layer-isolation`（modified）
- Affected code:
  - Modified: `includes/api/class-products-api.php`
  - New: `tests/Unit/Api/ProductsApiStatsTest.php`
  - Removed: (none)
