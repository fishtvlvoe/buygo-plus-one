## Why

業主（BuyGo+1 賣家後台）反映：出貨明細彈窗的「商品明細」區塊把多 variation 的同父商品合併成一行（例：「【預購】Kitty聯名可麗餅吊飾(5月到貨) × 4」），看不出 4 件裡面 (A) 薄荷巧克力 ×1、(B) ??? ×2、(C) ??? ×1 各幾件。客戶收到出貨單／列印的 PDF 也是聚合一行，造成核對與分揀困難。

DB 與架構驗證已完成（production buygo.me 上 shipment SH-20260508-008，product post_id=2560 有 3 個 variations，variation_title 已存「(A) 薄荷巧克力」字串）。資料已存在，純粹是讀取與顯示路徑沒拉出來。

列印路徑 = 前端 `window.print()`（瀏覽器原生），不是後端 PDF 產生器。前端顯示改完，PDF 自動同步反映，**不需動 PDF 代碼**。

## What Changes

- 後端 `ShipmentService::get_shipment_items` JOIN `fct_order_items` 與 `fct_product_variations`，每筆 shipment_item 額外回傳 `variation_id`、`variation_title`、`variation_identifier`、`object_id`（variation 在 FluentCart 的 ID）。API endpoint `GET /shipments/{id}` 回傳的 items 欄位增加上述欄位（既有欄位不變、不破契約）。
- 前端 `useShipmentDetails.js::mergeItemsByProduct` 合併時保留每個 sub-variation 為 `subItems` 陣列，每筆含 `variation_id`、`variation_title`、`quantity`。
- 出貨明細彈窗的「商品明細」表格，當合併開啟（`mergeEnabled = true`）且父商品 `subItems.length > 1` 時，在父商品 row 下方以縮排子列形式顯示每個 variation 的標題與數量（單價、小計欄位留空避免重複加總）。
- 「合併顯示中 / 展開顯示中」toggle 既有行為保留：toggle 為「展開顯示中」時仍走原始扁平 list（每筆 shipment_item 各佔一行，不縮排）。
- 「標記出貨」流程（`markShippedData`）共用同一份 `mergeItemsByProduct`，亦自動受益於子品項顯示。
- 列印（`window.print()`）與 Excel 匯出（`/shipments/export`）的子品項表現：列印因走 DOM 截圖自動正確；Excel 匯出已存在獨立 SQL 路徑，本 change **不動 Excel** 以縮小範圍（列入 Non-Goals）。

## Non-Goals

- 不修改 Excel 匯出（`/shipments/export`）內容格式。本 change 後 Excel 仍是原本扁平 shipment_items 表現；若需 Excel 也顯示 variation 明細，獨立 change 處理（避免本 change 範圍蔓延進另一個 endpoint）。
- 不修改批次匯出 PDF / 批次列印（如有）。本 change 僅針對單張出貨單詳情頁的「列印」按鈕走的 `window.print()` 流程。
- 不修改 shipment 列表頁（`view=shipments`）的商品欄位顯示。
- 不修改 `buygo_shipments` 或 `buygo_shipment_items` 表 schema（純讀取增強）。
- 不新增 PDF 產生器（dompdf / TCPDF）；仍維持瀏覽器原生 print。
- 不變更通知（LINE 訊息、Email）的 `product_list` 格式（`NotificationHandler`、`NotificationTemplates`）。
- 不變更 markShipped 確認頁的核心邏輯，僅自動共用新的 `subItems` 顯示。

## Capabilities

### New Capabilities

(none)

### Modified Capabilities

- `api-layer-isolation`: 新增需求說明 `GET /shipments/{id}` 端點回傳的 items 必須含每筆 shipment_item 對應的 variation_title 與 variation_id，且不得破壞既有欄位契約。

## Impact

- Affected specs: `api-layer-isolation`（modified）
- Affected code:
  - Modified: `includes/services/class-shipment-service.php`（`get_shipment_items` JOIN variations 帶 title）
  - Modified: `includes/api/class-shipments-api.php`（response 欄位文件化；無實質計算變動）
  - Modified: `admin/partials/shipment-details.php`（商品明細 table 加 `v-for` 子列縮排）
  - Modified: `includes/views/composables/useShipmentDetails.js`（`mergeItemsByProduct` 保留 `subItems`）
  - New: `tests/Unit/Services/ShipmentServiceGetItemsTest.php`
  - Removed: (none)
