# Tasks: export-variation-rows-print-style

## 1. CSV 匯出 variation 分行（Variation-level CSV rows）

- [x] 1.1 [P] 實作 Variation-level CSV rows：修改匯出 SQL 查詢，移除 `GROUP BY product_id`，加入 `variation_id`、`variation_title`、`variation_identifier` 欄位（參照 `get_shipment_detail` 的 SQL JOIN `fct_product_variations`）。修改後每個 variation 獨立一筆結果。驗證：在 `export_shipments` 方法中加入 variation JOIN，`composer test` 通過。[Tool: copilot]

- [x] 1.2 [P] 修改 CSV 行組裝邏輯，當商品有 variation 時，商品名稱格式為 `{product_name} - ({variation_identifier}) {variation_title}`；無 variation 時維持原商品名稱。驗證：匯出含 variation 的出貨單，CSV 中每個 variation 獨立一行，名稱格式正確。[Tool: copilot]

- [x] 1.3 測試：匯出含多 variation 的出貨單，確認 CSV 輸出兩行（如「產品測試 - (A) 漢頓」qty 3 和「產品測試 - (C) 大耳狗」qty 3）；匯出無 variation 的出貨單，確認 CSV 維持單行輸出。驗證：在 InstaWP 測試站手動匯出，CSV 內容符合 spec 的三個 scenario。[Tool: 手動驗證]

## 2. 列印樣式（Print-optimized layout）

- [x] 2.1 實作 Print-optimized layout：在 `admin/partials/shipment-details.php` 新增 `<style>` 區塊，加入 `@media print` 規則：隱藏 action 按鈕（匯出、列印、標記出貨）、sidebar 導航、tab 控制、modal 背景與關閉按鈕、合併切換開關。保留出貨單標題資訊、客戶資料、商品明細表格（含子品項 variation 行）、訂單總計。驗證：開啟列印預覽（Cmd+P），確認只有商品明細和客戶資訊可見，按鈕和 sidebar 隱藏。[Tool: copilot]

- [x] 2.2 設定列印表格樣式：表格寬度 100%、邊框可見、字體 10pt 以上。驗證：列印預覽中表格橫跨整頁寬度，邊框和文字清晰可讀。[Tool: copilot]

- [x] 2.3 測試：在 InstaWP 測試站開啟出貨單詳情頁，按 Cmd+P 列印預覽，確認樣式符合 spec 的三個 scenario（隱藏非內容元素、保留內容元素、表格排版）。驗證：截圖列印預覽畫面確認。[Tool: 手動驗證]
