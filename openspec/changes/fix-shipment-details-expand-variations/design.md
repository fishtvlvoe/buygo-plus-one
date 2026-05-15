## Context

BuyGo+1 出貨明細彈窗的「商品明細」目前把同一父商品（`product_id`）的多筆 shipment_items 合併成一行顯示，配「合併顯示中」橘色 badge。客戶在 production buygo.me 看到的具體案例：

- Shipment `SH-20260508-008`（id=420）有 4 個 shipment_items，product_id 全部=2560
- 4 個 fct_order_items 分別連到 variation 976 / 977 / 977 / 978（3 個不同 variations，977 出現兩次）
- variation 976 的 `fct_product_variations.variation_title = "(A) 薄荷巧克力"`，977 與 978 同樣有具名 title

前端 `useShipmentDetails.js::mergeItemsByProduct` 按 `product_id` 加總 quantity，回傳簡單的 `[{product_id, product_name, quantity, price}]` 陣列，丟失 variation 維度。彈窗的「列印」按鈕走 `window.print()`，瀏覽器把 DOM 直接列印成紙本或 PDF；Excel 匯出走另一個 endpoint `/shipments/export`（獨立 SQL）。

## Goals / Non-Goals

**Goals:**

- 出貨明細彈窗在「合併顯示中」模式下，父商品 row 下方以縮排子列顯示每個 variation 的 `variation_title` 與該 variation 在這張出貨單的累計數量。
- 後端 `GET /shipments/{id}` 的 items 加入 `variation_id`、`variation_title` 欄位，**不破壞既有欄位契約**（既有欄位名稱、型別、值不變）。
- 列印 / 儲存 PDF 自動顯示子品項（同一份 DOM）。
- 「標記出貨」流程（`markShippedData`）共用 `mergeItemsByProduct`，子品項顯示自動跟著進去。

**Non-Goals:**

- 不動 Excel 匯出端點（`/shipments/export`）— 那是獨立 SQL 路徑，若也要 variation 明細，列為後續獨立 change。
- 不動通知模板（`NotificationHandler::format_product_list`）— 那是 LINE / Email 訊息格式，跟出貨單詳情頁無直接關係。
- 不動 shipment 列表頁（`view=shipments`）的合併顯示。
- 不動 `buygo_shipments` / `buygo_shipment_items` schema。
- 不新增 PDF 產生器或後端列印路徑；仍用 `window.print()`。

## Decisions

**D1：嵌套（保留父行 + 縮排子列）vs 取代（拆成 N 行）**

- 採嵌套：父行保持 `×4 / 16000`、總計 `16000` 不變；子列只顯示 `variation_title` 與該 variation 的數量，單價 / 小計欄位 leave blank。
- 拒絕取代：取代會打亂總計欄位（4 個子列各列 `single_price * sub_qty` 加總 vs 父行 `× 4` 衝突），且改變列數會破壞 Excel 匯出對齊（雖然本 change 不動 Excel，但 API 一致性仍重要）。

**D2：variation 資料源 — JOIN 在 service 還是 separate query**

- 採 service 層 JOIN：`ShipmentService::get_shipment_items` 在原 SQL 內 LEFT JOIN `fct_order_items` 與 `fct_product_variations`，取 `variation_title`、`variation_identifier`。
- 拒絕 separate query：N+1 query 風險、需要在 controller 層做後處理。
- 用 LEFT JOIN 確保即使某筆 shipment_item 對應的 order_item 或 variation row 不存在，shipment_item 本身仍會回傳，只是 `variation_title = null`。

**D3：前端 `mergeItemsByProduct` 結構演進**

- 既有回傳形狀（每筆物件含 `product_id`、`product_name`、`quantity`、`price`、`subtotal` 等欄位）不刪不改，**只加** `subItems` 陣列。
- 每個父商品物件新增 `subItems: [{variation_id, variation_title, quantity}, ...]`；若父商品只有單一 variation（即 `subItems.length === 1`），UI 不渲染子列（避免冗餘）。
- 既有 caller（無論是 `mergedDetailItems` 還是 `mergedMarkShippedItems`）拿到的物件結構向後相容。

**D4：「展開顯示中」toggle 行為保留**

- toggle 為「展開顯示中」（`mergeEnabled = false`）時，繼續走原始 `detailModal.items` 扁平 list，每筆 shipment_item 各一行。
- toggle 為「合併顯示中」（`mergeEnabled = true`）時，走 `mergeItemsByProduct`，再加子列。
- 不引入第三個 mode；不刪 toggle。

## Implementation Contract

**Behavior:**

- `GET /wp-json/buygo-plus-one/v1/shipments/{id}` 的 `data.items[]` 每筆新增三個欄位：
  - `variation_id` (int | null)：FluentCart variation row 的 id（即 fct_order_items.object_id），對應 fct_product_variations.id；找不到時 null。
  - `variation_title` (string | null)：例如 `"(A) 薄荷巧克力"`；找不到時 null。
  - `variation_identifier` (string | null)：例如 `"BUYGO-2560-A"`；找不到時 null。
- 既有欄位（`id`、`shipment_id`、`order_id`、`order_item_id`、`product_id`、`quantity`、`created_at`）值與型別不變。
- 出貨明細彈窗「商品明細」表格在 `mergeEnabled = true` 且父商品 `subItems.length > 1` 時，每個父商品 row 下方渲染 N 個子列（縮排 16px、字級 xs、灰色），子列含「（縮排小角圖示） variation_title × quantity」，單價 / 小計欄位留空。
- 「列印」按鈕走 `window.print()`，瀏覽器直接 print 當前 DOM。
- 「標記出貨」頁面共用 `mergeItemsByProduct`，自動取得相同子品項。

**Interface / data shape:**

- 後端 `ShipmentService::get_shipment_items(int $shipment_id): array`：簽名不變、回傳陣列每筆額外含上述三個欄位。
- 前端 `mergeItemsByProduct(items: ItemRaw[]): MergedItem[]`：回傳每個 `MergedItem` 新增 `subItems: SubItem[]`；其中 `SubItem = { variation_id: number|null, variation_title: string|null, quantity: number }`。
- 既有前端 callers 不需更動，新欄位純讀取。

**Failure modes:**

- `fct_order_items.object_id` 對不到 `fct_product_variations` row → LEFT JOIN 回 null → 子列 fallback 顯示 `「未命名 variation」` 字串（不留空避免 UX 誤判）。
- shipment 沒有任何 items → 既有「尚無商品資料」空狀態維持。
- 同父商品所有 shipment_items 都指向同一 variation → `subItems.length === 1` → UI 不渲染子列（父行已表達完整資訊）。
- API 回 5xx → 既有錯誤處理流程（toast）維持。

**Acceptance criteria:**

- 新增單元測試 `tests/Unit/Services/ShipmentServiceGetItemsTest.php`：
  - `test_get_shipment_items_includes_variation_fields`：mock `$wpdb->get_results` 回傳含 JOIN 後欄位的 fixture，斷言回傳陣列每筆含 `variation_id`、`variation_title`、`variation_identifier`。
  - `test_get_shipment_items_null_variation_for_missing_join`：fixture 中一筆 variation_id 為 null，斷言該筆 `variation_title = null` 不丟錯。
  - `test_get_shipment_items_preserves_existing_fields`：確認 `id`、`shipment_id`、`product_id`、`quantity` 等欄位值與型別不變。
- 線上手動驗收：對 production buygo.me shipment `SH-20260508-008` 開啟出貨明細彈窗：
  - 「合併顯示中」狀態：商品明細顯示父行「【預購】Kitty 可麗餅吊飾 × 4」+ 3 個縮排子列（(A) 薄荷巧克力 × 1、977 對應 title × 2、978 對應 title × 1）。
  - 按「列印」→ 瀏覽器列印預覽含子列。
  - 點「合併顯示中」按鈕切到「展開顯示中」→ 改回 4 行扁平 list（無縮排）。
- `composer test` 全套全綠（既有 + 新增）。

**Scope boundaries:**

- In scope：`includes/services/class-shipment-service.php`、`includes/api/class-shipments-api.php`、`admin/partials/shipment-details.php`、`includes/views/composables/useShipmentDetails.js`、新增測試檔。
- Out of scope：Excel 匯出 endpoint、通知模板、shipment 列表頁、其他 endpoint、schema、PDF 產生器。

## Risks / Trade-offs

**R1：`get_shipment_items` JOIN 多兩張表，query 變慢**

- 機率：低。單張 shipment 通常 < 20 個 items，LEFT JOIN 在有索引下仍 ms 級。
- 對策測試：`test_get_shipment_items_query_uses_single_join`（檢查 SQL 字串只一個 JOIN to `fct_order_items` 一個 JOIN to `fct_product_variations`，無 N+1）。
- 後備：若實測有效能問題，後端可加 transient（5 秒）但本 change 不做。

**R2：既有前端 caller 直接讀 `items[i].variation_title` 拿到 null 導致顯示 "null" 字串**

- 機率：低。新欄位是新增，舊代碼不會去讀。但若有第三方代碼 / 自寫 JS 已存在誤讀，可能出 UI 雜訊。
- 對策：grep `includes/views/`、`admin/js/` 確認沒有任何代碼讀 `variation_title` 字串字面；若有，本 change 先補 null 守衛。

**R3：「標記出貨」頁共用 `mergeItemsByProduct`，子列在該頁顯示可能干擾出貨確認流程**

- 機率：中。標記出貨頁是「確認要出貨什麼」的關鍵畫面，UI 變動需謹慎。
- 對策測試：propose 之後 spectra-apply 開始前 cross-impact 檢查（即用戶要求的 A 壞 B 檢查），確認標記出貨頁面表格現有結構能容納子列；若不能（例如欄位數對齊問題、行為衝突），新增 prop `:show-variations="false"` 給該頁停用子列。
- 對策測試：手動驗收一張多 variation 的 shipment 的「標記出貨」按鈕流程，確認子列顯示不破壞既有「下單/已配/待配」欄位。

**R4：列印（`window.print()`）對縮排子列 CSS 渲染與螢幕不同**

- 機率：低。子列用 Tailwind utility（pl-8、text-xs、text-slate-500）皆為標準屬性，print stylesheet 不會剝奪。
- 對策：應用 `@media print` 規則時測試列印預覽。
- 對策測試：propose 之後實作完成後在 buygo.me 線上開列印預覽截圖。

**R5：variation_title 欄位含特殊字元（emoji、HTML 標記）造成 XSS 或排版破壞**

- 機率：低。Vue 預設用 `{{ }}` 是文字插值（HTML escape），不會 XSS。但若用 `v-html` 就會中招。
- 對策：本 change tasks 內明文限制只能用 `{{ }}` 插值，禁用 `v-html` 渲染 `variation_title`。
- 對策測試：`grep "v-html" admin/partials/shipment-details.php` 應無 variation_title 出現。
