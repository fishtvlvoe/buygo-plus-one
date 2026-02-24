# Phase 54-01 Summary: 資料管理 Tab 前端

## 完成狀態: DONE

## 建立的檔案

### 1. `admin/css/data-management.css` (70 行)
- `.bgo-dm-sub-tabs` — 子 Tab 導航（pill 按鈕風格，active 用 #3b82f6）
- `.bgo-dm-filters` — 篩選區佈局（flex, gap, 日期+關鍵字）
- `.bgo-dm-toolbar` — 工具列（全選 checkbox + 刪除按鈕）
- `.bgo-dm-pagination` — 分頁按鈕
- `.bgo-modal` / `.bgo-modal-content` — Modal 遮罩和內容框
- `.bgo-dm-form-row` — 客戶編輯表單行
- `.bgo-dm-message` — 成功/錯誤訊息
- 刪除按鈕紅色 #d63638
- 響應式支援（782px 以下）

### 2. `admin/js/data-management.js` (232 行)
- IIFE 封裝，不污染全域命名空間
- 子 Tab 切換（訂單/商品/客戶）→ 自動清空篩選並重新查詢
- 查詢表單：日期範圍 + 關鍵字 → fetch REST API
- 表格渲染：依 currentType 動態產生不同欄位
- 全選/單選 checkbox 控制 → 更新刪除按鈕狀態
- 刪除流程：勾選 → 開 Modal → 輸入 DELETE → 確認 → POST API
- 客戶編輯：點擊編輯 → 開 Modal → 填入資料 → 儲存 → PUT API
- 分頁：上一頁/下一頁按鈕

### 3. `includes/admin/tabs/data-tab.php` (112 行，重寫)
- 從「即將推出」佔位卡替換為完整 UI
- 用 `<link>` 和 `<script src>` 直接載入 CSS/JS
- `window.bgoDataManagement` 傳入 REST URL + nonce
- HTML 結構：標題 + 子 Tab + 篩選區 + 結果表格 + 刪除 Modal + 客戶編輯 Modal

## REST API 端點（已有，本次只呼叫）
- `GET  /data-management/query` — 查詢
- `POST /data-management/delete-orders` — 刪除訂單
- `POST /data-management/delete-products` — 軟刪除商品
- `POST /data-management/delete-customers` — 刪除客戶
- `PUT  /data-management/customers/{id}` — 編輯客戶

## 驗證
- `php -l data-tab.php` — 語法無誤
- CSS < 200 行、JS < 300 行、PHP < 120 行 — 全部符合

## 對應需求
- DMF-01: 子 Tab 切換（訂單/商品/客戶）
- DMF-02: 查詢篩選（日期範圍 + 關鍵字 + 分頁）
- DMF-03: 批次刪除（Modal + DELETE 確認）
- DMF-04: 客戶編輯（Modal + 儲存）
