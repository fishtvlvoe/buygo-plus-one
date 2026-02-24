# Phase 53-01 Summary: 開發者 Tab 事件分組摺疊

## 完成狀態：已完成

## 改動檔案
- `includes/admin/tabs/developer-tab.php` — Section 1 重構為分組摺疊式

## 實作內容

### EVT-01: PHP 資料準備
- 移除 `event_type_filter` GET 參數篩選邏輯
- 從 WebhookLogger 取得所有事件（limit 500）
- 按 `event_type` 做 groupBy，每組計算 count、earliest、latest
- 排序：error > permission_denied > 其他依計數降序
- 每組只保留前 20 筆用於初始渲染

### EVT-02: AJAX Handler
- 新增 `buygo_dev_event_page` action
- 接收 event_type + page 參數，每頁 20 筆
- 回傳 JSON：rows, total, page, pages
- 權限：manage_options，Nonce：buygo_dev_nonce

### EVT-03: HTML 分組區塊
- 移除篩選下拉選單（bgo-dev-filters form）
- 改為 `.bgo-event-group` 分組區塊渲染
- 每組有可點擊標題列：箭頭 + badge + 計數 + 時間範圍
- error / permission_denied 的 body 預設展開，其他收合
- 超過 20 筆的分組顯示分頁導航

### EVT-04: CSS + JS
- CSS：分組容器、標題列 hover、箭頭旋轉動畫、分頁按鈕
- JS：`bgoToggleGroup()` 展開/收合、`bgoLoadEventPage()` AJAX 分頁
- 分頁防重複請求（loading flag）
- `bgoEscHtml()` 用於 XSS 防護

## 未動到的區塊
- Section 2 (Data Cleanup) — 完全不動
- Section 3 (SQL Console) — 完全不動

## 驗證
- `php -l` 語法檢查通過
- Git commit: `8598ae7`
