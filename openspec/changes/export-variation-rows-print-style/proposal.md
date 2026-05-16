## Why

出貨單詳情頁前端已正確顯示子產品 variation（如「漢頓 x3」「大耳狗 x3」），但 CSV 匯出時 SQL 用 `GROUP BY product_id` 把所有 variation 合併成一行，遺失子產品明細。列印功能直接呼叫 `window.print()` 沒有 `@media print` 樣式，按鈕、sidebar 都會印出來，排版不可控。

## What Changes

- CSV 匯出 SQL 移除 `GROUP BY product_id`，改為按 variation 分行輸出
- 每個 variation 獨立一行，商品名稱格式為「產品名稱 - (識別碼) variation 名稱」
- 無 variation 的商品維持原行為（單行輸出）
- 新增 `@media print` 樣式，列印時隱藏按鈕、sidebar、頁籤等非內容 UI，只保留商品明細表格與出貨資訊

## Non-Goals

- 不做 Excel .xlsx 格式匯出（目前 Excel 匯出實際上呼叫 CSV）
- 不改前端商品明細的合併顯示邏輯（已正常運作）
- 不做列印版面的頁首頁尾自訂（瀏覽器預設即可）
- 不改 ExportService 的檔案產生邏輯（generate_csv_file），只改資料查詢層

## Capabilities

### New Capabilities

- `shipment-export-variations`: CSV 匯出時按 variation 分行輸出子產品明細
- `shipment-print-style`: 出貨單詳情頁列印時的 @media print 樣式

### Modified Capabilities

（無）

## Impact

- 受影響的 Service / API：
  - ShipmentsApi (`includes/api/class-shipments-api.php`) — 匯出端點的 SQL 查詢與 CSV 行組裝
  - ExportService (`includes/services/class-export-service.php`) — 可能需調整 CSV header 或資料格式
- 受影響的前端：
  - `admin/partials/shipment-details.php` — 新增 `@media print` 樣式區塊
- 受影響的測試：
  - 新增：匯出含 variation 的出貨單測試
