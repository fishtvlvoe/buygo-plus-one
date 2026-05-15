## Context

BuyGo+1 後台對同一商品在「商品列表頁」與「商品分配詳情頁」並排顯示分配統計（已分配、待分配、可分配、已出貨）。實際案例（商品 ID=1055）：分配詳情頁顯示「已分配=2、可分配=0」，商品列表頁顯示「已分配=0、待分配=3」。

兩個畫面打的是同一個 REST 端點家族（`includes/api/class-products-api.php`），但回傳值不同，造成根因如下：

1. **快取未失效**：列表端點在 `get_transient('buygo_products_<user_id>_<md5(params)>')` 與 `set_transient(..., 30)` 之間存活 30 秒。`AllocationWriteService::saveAllocation()` 寫完 `update_post_meta('_buygo_allocated', ...)` 後，沒有任何 `delete_transient` 呼叫。整個 codebase grep 不到該 transient key 的清除點。
2. **公式分叉**：單品端點 `formattedProduct['reserved'] = max(0, ordered - purchased - allocated)`；列表端點 `formattedProducts[]['reserved'] = max(0, ordered - purchased)`。即使無快取，兩個畫面對「待分配」的定義從一開始就不同。

業主回饋：「資料是對的，但畫面說沒分配」→ 信任受損優先於效能。

## Goals / Non-Goals

**Goals:**

- 拔掉列表端點的 transient 快取，使 `_buygo_allocated` 寫入後下一次 API 呼叫即反映最新值。
- 統一兩個端點的 `reserved` 公式為 `max(0, ordered - purchased - allocated)`。
- 加 PHPUnit 純 PHP 邏輯測試：相同輸入下兩個端點 `reserved` / `allocated` 結果一致。

**Non-Goals:**

- 不重建任何形式的快取機制（namespace bust、wp_cache group、版本號 key 皆不採用）。流量低，30 秒省下的 query 不足以抵銷一致性風險。
- 不修改寫入端 service（`AllocationWriteService`、`ShipmentService` 等）—— 拔了讀端快取就無需清快取。
- 不重構 `class-products-api.php` 其他職責或檔案內其他端點。
- 不變更前端 / Vue composable / API contract，回傳欄位名與型別維持。

## Decisions

**D1：採方案 A（拔快取）而非 B（保留快取 + 加失效）**

- 拒絕 B：WordPress transient 無原生 wildcard 失效，需改用 `wp_cache_*` group 或自建版本號 key，需動 4-5 個 write service，引入新的耦合。
- 採 A：刪除 `get_transient` 與 `set_transient` 兩段，總體變更最小、回歸風險最低。
- 條件：未來流量提升到 list 端點每秒 > 10 req 才考慮重評快取策略。

**D2：以單品端點公式為準（`ordered - purchased - allocated`）**

- 單品端點公式語意正確：「待分配 = 已採購但尚未分配的數量」。
- 列表端點少扣 `allocated` 屬於遺漏 bug，非有意設計。
- 同步修正列表端點即可，前端不需改 Vue 模板。

## Implementation Contract

**Behavior:**

- 列表端點 `GET /wp-json/buygo-plus-one/v1/products`：刪除 transient 快取讀寫；對每個商品計算 `reserved = max(0, ordered - purchased - allocated)`。
- 單品端點 `GET /wp-json/buygo-plus-one/v1/products/{id}`：行為不變（公式已正確）。
- 分配寫入後，下一次列表查詢必須回傳更新後的 `allocated` 與 `reserved`。

**Interface / data shape:**

- 列表回傳每筆商品欄位維持：`allocated`、`pending`、`reserved`、`ordered`、`purchased`、`shipped`（皆 int）。
- 不新增不移除欄位；不變更 HTTP status code 或錯誤格式。

**Failure modes:**

- 來源欄位缺失（`$product['ordered']` / `$product['purchased']` / `_buygo_allocated`）→ 預設 0，避免 PHP notice；對外仍回 200。
- 商品不存在（`get_post_meta` 回空）→ 既有行為不變。

**Acceptance criteria:**

- 新增測試 `tests/Unit/Api/ProductsApiStatsTest.php`：
  - `test_list_and_single_product_reserved_match`：同樣 ordered/purchased/allocated 輸入下，列表算式與單品算式回傳的 `reserved` 相等。
  - `test_reserved_subtracts_allocated`：ordered=10、purchased=10、allocated=4 時 `reserved=6`。
  - `test_reserved_floor_at_zero`：ordered=10、purchased=10、allocated=12 時 `reserved=0`。
  - `test_no_transient_calls_in_list_endpoint`：以反射或 grep 斷言 `class-products-api.php` 不再含 `get_transient('buygo_products_'` 與 `set_transient('buygo_products_'` 字串。
- 手動驗收（部署後）：對商品 ID=1055 在分配頁與列表頁同時開啟，按「確認分配」後列表頁重新整理立刻顯示新的 `已分配` 與 `待分配`。
- `composer test` 全綠。

**Scope boundaries:**

- In scope：`includes/api/class-products-api.php`、新增的 PHPUnit 測試檔。
- Out of scope：寫入端 service、Vue 前端模板、其他 endpoint、其他快取機制。

## Risks / Trade-offs

**R1：拔快取後資料庫負載上升**

- 機率：低。BuyGo 後台同時在線用戶通常 < 10。
- 影響：list endpoint 每次都跑 ProductService 查詢；以目前資料量（單一賣家數百商品）單次查詢仍在 ms 級。
- 失敗點：若列表 query 本身有 N+1 或缺索引，拔快取後會放大。
- 對策測試：`test_list_endpoint_query_count_baseline`（用 `$wpdb->num_queries` 量測單次列表呼叫的 query 數，設上限 50 作為迴歸防護）。

**R2：誤刪到其他端點共用的快取邏輯**

- 機率：中。同檔還有單品端點等。
- 對策：只刪以 `buygo_products_` 為前綴的 transient 操作；其他若有不同 key 不動。
- 對策測試：`test_no_transient_calls_in_list_endpoint`（驗證列表端點無 transient）+ `test_single_product_endpoint_unchanged`（單品端點行為快照）。

**R3：列表端點還有其他 reserved 計算路徑被遺漏**

- 機率：低。grep 確認列表端點 `reserved` 只一處計算。
- 對策：紅燈測試 `test_list_and_single_product_reserved_match` 會直接抓到任何路徑不一致。

**R4：前端 Vue 模板對 `reserved` 有隱性依賴假設（如「`reserved + allocated = purchased`」）**

- 機率：低。Vue 模板現在顯示 `pending`（後端送 `pending` 不重算），`reserved` 公式變動只影響該欄位數值。
- 對策：手動驗收 + Vue 模板不需改動（D2 已說明）。
