# Requirements: BuyGo+1

**Core Value:** 讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，每個賣家只能看到自己的商品和訂單

---

## v3.2 Requirements (Active)

**Milestone:** v3.2 - 批量上架前端
**Defined:** 2026-03-01

**Goal:** 實作批量上架的完整前端體驗 — 從商品列表頁的「+上架」按鈕進入，經過數量選擇、表單填寫（手動輸入或 CSV 匯入），到批次建立商品。後端 API（Phase 56 BATCH-01~04）已完成，本 Milestone 專注前端 UI。

**設計圖：** `.planning/designs/` 目錄（5 張 PNG）

### 路由與入口 (ROUTE)

- [x] **ROUTE-01**: 商品列表頁新增「+上架」按鈕
  - 桌面版：搜尋框右側的藍色按鈕「+ 上架」
  - 手機版：搜尋框右側的藍色按鈕「+ 上架」
  - 點擊後進入批量上架流程（數量選擇頁）

- [x] **ROUTE-02**: 批量上架路由註冊
  - SPA 路由新增 `/products/batch-create` 路徑
  - 支援子步驟：數量選擇 → 表單填寫
  - 返回箭頭（←）可回到商品列表頁

### 數量選擇 (SELECT)

- [x] **SELECT-01**: 數量選擇頁面
  - 標題「要上架幾個商品？」
  - 副標「選擇數量後，一次展開所有欄位填寫」
  - 四個快選按鈕：5 個、10 個、15 個、20 個
  - 選中按鈕以藍色實心顯示

- [x] **SELECT-02**: 自訂數量輸入
  - 快選按鈕下方顯示「或」分隔
  - 自訂數量輸入框，範圍 1-20
  - 輸入自訂數量時取消快選按鈕的選取狀態

- [x] **SELECT-03**: 配額資訊顯示
  - 顯示剩餘配額：「剩餘配額：38 個（已用 12 / 上限 50）」
  - 配額資料從 API 取得（ProductLimitChecker）
  - 選擇數量超過剩餘配額時禁用「開始填寫」按鈕

- [x] **SELECT-04**: 開始填寫按鈕
  - 底部藍色全寬按鈕「開始填寫（N 個商品）→」
  - 動態顯示選取的數量
  - 點擊後進入表單填寫頁

### 批量表單 (FORM)

- [ ] **FORM-01**: 手機版卡片式表單
  - 每個商品一張卡片，標題「商品 #N」
  - 欄位：商品名稱（必填）、售價（必填）、數量（0=無限）、描述（選填）
  - 每張卡片右上角有刪除按鈕（垃圾桶圖示）

- [ ] **FORM-02**: 桌面版表格式表單
  - 表頭：# / 商品名稱 / 售價 / 數量 / 描述 / 操作
  - 每行一個商品，行內直接編輯
  - 操作欄有刪除按鈕（垃圾桶圖示）

- [ ] **FORM-03**: 新增/刪除商品列
  - 底部「+ 新增商品」或「+ 新增商品列」按鈕
  - 點擊新增一個空白商品表單
  - 刪除按鈕移除該商品（至少保留 1 個）

- [ ] **FORM-04**: 配額進度顯示
  - 頂部顯示「商品配額：N / 上限」和進度條
  - 即時更新（新增/刪除商品時）
  - 桌面版顯示為 badge「配額 N/上限」

- [ ] **FORM-05**: 手動輸入 / CSV 匯入子 Tab 切換
  - 手機版：兩個 pill 按鈕「手動輸入」「CSV 匯入」
  - 桌面版：右上角「匯入 CSV」按鈕（獨立功能，非 Tab）
  - 切換時保留已填寫的手動資料

### CSV 匯入 (CSV)

- [ ] **CSV-01**: CSV 檔案上傳
  - 支援 .csv 檔案上傳（file input 或拖放）
  - CSV 欄位：名稱、售價、數量、描述
  - 解析後填入表單（覆蓋現有空白列或新增列）

- [ ] **CSV-02**: CSV 解析驗證
  - 驗證 CSV 格式（必須有名稱和售價欄位）
  - 數量欄位缺失時預設為 0（無限）
  - 解析錯誤時顯示錯誤提示

- [ ] **CSV-03**: CSV 匯入結果預覽
  - 匯入後在表單中顯示解析結果
  - 使用者可在表單中修改匯入的資料
  - 顯示匯入筆數提示

### 提交與結果 (SUBMIT)

- [x] **SUBMIT-01**: 批量上架按鈕
  - 手機版：底部固定欄「N 件商品」+「批量上架」藍色按鈕
  - 桌面版：右上角「批量上架 (N)」藍色按鈕
  - 按鈕顯示有效商品數量（已填寫必填欄位的商品）
  - 提交前驗證：至少 1 個有效商品、必填欄位檢查

- [x] **SUBMIT-02**: 呼叫批量上架 API
  - 呼叫已完成的 POST /products/batch-create API
  - 提交時顯示 loading 狀態
  - 防止重複提交（按鈕 disabled）

- [x] **SUBMIT-03**: 提交結果回饋
  - 成功：顯示成功數量，自動返回商品列表頁
  - 部分失敗：顯示成功/失敗數量和失敗原因
  - 全部失敗：顯示錯誤訊息，留在表單頁面可修改重試

- [x] **SUBMIT-04**: 提示資訊
  - 桌面版底部提示：「商品名稱和售價為必填。數量填 0 代表無限上架。支援 CSV 匯入（欄位：名稱、售價、數量、描述）。」
  - 手機版簡化版提示或省略

### Out of Scope (v3.2)

- **商品圖片上傳** — 批量上架只填基本欄位，圖片之後在商品編輯頁處理
- **商品成本/來源** — 成本和來源欄位在商品詳情頁編輯（Phase 49 已完成）
- **商品編輯功能** — 批量上架只做「新增」，編輯用現有的商品詳情頁
- **批量上架 API 修改** — API 已在 Phase 56 完成，前端直接呼叫不改後端
- **Portal 前台批量上架** — 本次只做 Vue SPA 內的前端，不做 Portal 側

### Traceability (v3.2)

| Requirement | Phase | Status |
|-------------|-------|--------|
| ROUTE-01 | Phase 57 | Not started |
| ROUTE-02 | Phase 57 | Not started |
| SELECT-01 | Phase 57 | Not started |
| SELECT-02 | Phase 57 | Not started |
| SELECT-03 | Phase 57 | Not started |
| SELECT-04 | Phase 57 | Not started |
| FORM-01 | Phase 58 | Not started |
| FORM-02 | Phase 58 | Not started |
| FORM-03 | Phase 58 | Not started |
| FORM-04 | Phase 58 | Not started |
| FORM-05 | Phase 58 | Not started |
| CSV-01 | Phase 58 | Not started |
| CSV-02 | Phase 58 | Not started |
| CSV-03 | Phase 58 | Not started |
| SUBMIT-01 | Phase 59 | Not started |
| SUBMIT-02 | Phase 59 | Not started |
| SUBMIT-03 | Phase 59 | Not started |
| SUBMIT-04 | Phase 59 | Not started |

**Coverage:**
- v3.2 requirements: 18 total
- Mapped to phases: 18/18 (100%)

---

## v3.1 Requirements (Complete — Shipped 2026-02-24)

**Milestone:** v3.1 - WP 後台完善 + 批量上架
**Defined:** 2026-02-24

**Goal:** 完善 WP 後台 6 個 Tab 的功能（資料管理、功能管理、開發者事件分類），調整 Tab 順序整合 R2 設定，並實作批量上架 API 後端。

### Tab 順序調整 (TAB)

- [x] **TAB-01**: R2 Tab 位置調整
  - Tab 順序從「角色權限 → LINE 模板 → 結帳設定 → ...」改為「角色權限 → R2 圖床 → LINE 模板 → 結帳設定 → 資料管理 → 功能管理 → 開發者」
  - 改動範圍：`bgo-r2/admin/class-settings.php` — `register_buygo_tab()` 方法的優先級

### 事件分類 (EVT)

- [ ] **EVT-01**: 事件按 event_type 分組顯示
  - 從平面列表改為分組摺疊式顯示
  - 每組顯示事件計數和時間範圍

- [ ] **EVT-02**: error 類預設展開
  - error / permission_denied 類別預設展開
  - 其他類別預設收合

- [ ] **EVT-03**: 展開查看個別事件明細
  - 點擊分組可展開查看個別事件明細

- [ ] **EVT-04**: 事件明細分頁
  - 展開明細支援分頁（每頁 20 筆）
  - 有上一頁/下一頁導航

### 資料管理前端 (DMF)

- [ ] **DMF-01**: 三個子 Tab 切換
  - 訂單/商品/客戶子 Tab 可切換
  - 各自顯示查詢表格

- [ ] **DMF-02**: 查詢篩選功能
  - 支援日期範圍篩選和關鍵字搜尋
  - 結果分頁顯示

- [ ] **DMF-03**: 批次刪除 UI
  - 勾選多筆後點「刪除」
  - Modal 要求輸入 DELETE 確認
  - 呼叫對應已完成的 REST API（Phase 43）

- [ ] **DMF-04**: 客戶編輯 Modal
  - 客戶列表有「編輯」按鈕
  - Modal 可修改姓名/電話/地址/身分證
  - 儲存後更新表格
  - 呼叫已完成的客戶編輯 API（Phase 43）

### 功能管理前端 (FMF)

- [ ] **FMF-01**: 授權狀態卡片
  - 頂部顯示授權狀態（Free/Pro）
  - 授權碼輸入欄和驗證按鈕

- [ ] **FMF-02**: 功能列表卡片
  - 功能以卡片形式顯示
  - 每張卡片有名稱、說明、toggle 開關

- [ ] **FMF-03**: Free/Pro 功能區分
  - Free 功能 toggle 可直接操作
  - Pro 功能需先驗證授權碼

- [ ] **FMF-04**: toggle 即時生效
  - toggle 操作即時生效
  - 呼叫已完成的功能管理 API（Phase 44）儲存狀態

### 批量上架 (BATCH)

- [ ] **BATCH-01**: batch-create API 接收 JSON 陣列
  - POST /products/batch-create 接受 JSON 陣列
  - 每個物件含 title、price 等商品資料
  - 替換 Phase 45 預留的 501 骨架

- [ ] **BATCH-02**: 必填欄位和配額驗證
  - 驗證每筆商品的必填欄位
  - 檢查賣家商品配額，超過配額時回傳錯誤

- [ ] **BATCH-03**: 部分失敗容錯
  - 逐筆建立商品
  - 部分失敗不影響已成功的商品

- [ ] **BATCH-04**: 回傳結果明細
  - 回傳結果包含每筆的成功/失敗狀態和原因

### Out of Scope (v3.1)

- **批量上架前端 UI** — 只做 API 後端，前端 UI 之後再做
- **資料管理賣家側** — Portal 前台的賣家刪除另外做，資料管理 Tab 是給管理員用的
- **授權伺服器** — buygo_is_pro() 繼續回傳 true
- **多圖片輪播** — 預留 API 骨架不變

### Traceability (v3.1)

| Requirement | Phase | Status |
|-------------|-------|--------|
| TAB-01 | Phase 52 | Not started |
| EVT-01 | Phase 53 | Not started |
| EVT-02 | Phase 53 | Not started |
| EVT-03 | Phase 53 | Not started |
| EVT-04 | Phase 53 | Not started |
| DMF-01 | Phase 54 | Not started |
| DMF-02 | Phase 54 | Not started |
| DMF-03 | Phase 54 | Not started |
| DMF-04 | Phase 54 | Not started |
| FMF-01 | Phase 55 | Not started |
| FMF-02 | Phase 55 | Not started |
| FMF-03 | Phase 55 | Not started |
| FMF-04 | Phase 55 | Not started |
| BATCH-01 | Phase 56 | Not started |
| BATCH-02 | Phase 56 | Not started |
| BATCH-03 | Phase 56 | Not started |
| BATCH-04 | Phase 56 | Not started |

**Coverage:**
- v3.1 requirements: 17 total
- Mapped to phases: 17/17 (100%)

---

## v2.0 Requirements (Complete)

**Milestone:** v2.0 - 後台 UI 統一化
**Defined:** 2026-02-20

**Goal:** 重構 BGO 後台 UI，與 LineHub 設計語言統一，精簡 Tab 結構（6-Tab），新增細粒度權限、資料管理和 Pro 版授權機制。

### 基礎架構 (ARCH)

- [x] **ARCH-01**: 選單名稱從 BuyGo+1 改為 BGO
  - add_menu_page() 標題和選單文字都改為 'BGO'
  - dashicons-cart 圖示保留

- [x] **ARCH-02**: 取消子選單，改為單頁 6-Tab 導航
  - 刪除兩個 add_submenu_page()
  - 新增統一的 render_page() 方法
  - Tab 順序：角色權限 > LINE 模板 > 結帳設定 > 資料管理 > 功能管理 > 開發者

- [x] **ARCH-03**: Tab 導航 CSS 仿 LineHub 結構
  - 新建 admin-tabs.css，使用 `.bgo-` 前綴
  - 品牌色：#3b82f6（藍）
  - 結構：tab padding、底線、transition、響應式斷點跟 LineHub 完全一致

- [x] **ARCH-04**: 頁面標題一致性
  - 大標 `<h1>BGO</h1>`
  - Tab 名稱使用正確大小寫（LINE 模板，不是 Line 模板）

### 角色權限 (ROLE)

- [x] **ROLE-01**: 表格只顯示 BGO 管理員和小幫手
  - 純 WP Admin（無 BGO 角色）不在表格中
  - WP Admin + BGO Admin 顯示，可移除 BGO 角色

- [x] **ROLE-02**: WP Admin + BGO Admin 可移除 BGO 角色
  - 移除後從表格消失（只剩 WP Admin）
  - 不影響 WordPress 管理員身份

- [x] **ROLE-03**: 新增流程簡化為「新增賣家」按鈕
  - 移除角色選擇 dropdown
  - 移除歸屬賣家搜尋欄位
  - 新增即自動獲得 buygo_admin 角色

- [x] **ROLE-04**: 搜尋 UX 改進
  - 點擊搜尋框立即顯示使用者列表（0 字元開始）
  - 後端 API 支援空 query 回傳前 20 筆

- [x] **ROLE-05**: 小幫手 5 大項細粒度權限
  - 商品管理（products）：檢視、上架、編輯、刪除、分配
  - 訂單管理（orders）：檢視、狀態更新、拆分合併、轉備貨
  - 出貨管理（shipments）：檢視、建立、標記出貨、匯出
  - 客戶資料（customers）：檢視列表、詳細資料
  - 設定管理（settings）：模板、關鍵字、小幫手管理
  - 預設：商品管理開，其餘關

- [x] **ROLE-06**: 權限設定 Modal
  - 表格中小幫手的「操作」欄位新增「權限設定」按鈕
  - Modal 顯示 5 個 checkbox + 說明
  - 儲存到 user_meta: buygo_helper_capabilities
  - 「重設為預設」按鈕

- [x] **ROLE-07**: API 層和 Portal 層權限檢查
  - buygo_helper_can($user_id, $capability) 函式
  - 賣家自動通過所有權限檢查
  - Portal 導航列只顯示有權限的頁面
  - 無權限頁面顯示 no-access.php

### 資料管理 (DATA)

- [x] **DATA-01**: 篩選區
  - 資料類型選擇：訂單 / 商品 / 客戶（radio）
  - 時間範圍：開始日期 + 結束日期
  - 關鍵字搜尋

- [x] **DATA-02**: 訂單查詢和刪除
  - 按日期範圍查詢 fct_orders
  - 單筆刪除和批次刪除
  - 直接操作資料庫（繞過 FluentCart 限制）
  - 同步清理 buygo_shipment_items 關聯

- [x] **DATA-03**: 商品查詢和刪除
  - 按日期範圍查詢商品
  - 使用現有 /products/batch-delete 端點

- [x] **DATA-04**: 客戶資料查詢、編輯和刪除
  - 按日期範圍查詢 fct_customers
  - 編輯 Modal：姓名、電話、地址、身分證字號
  - 更新 fct_customers + fct_customer_addresses

- [x] **DATA-05**: 批次刪除二次確認
  - 第一次：Modal 顯示將刪除的數量
  - 第二次：輸入 "DELETE" 文字才啟用確認按鈕
  - 刪除後不可復原

### 功能管理 (FEAT)

- [x] **FEAT-01**: Free/Pro 功能列表顯示
  - Free 功能：角色權限、LINE 模板、結帳設定、單一商品管理、訂單檢視、基本出貨
  - Pro 功能：小幫手系統、合併訂單、批次操作、資料管理、自定義欄位、多圖輪播、匯出
  - 未授權時 Pro 功能顯示「升級 Pro」

- [x] **FEAT-02**: 功能啟用/關閉開關
  - Pro 啟用後各功能可獨立開關
  - 開關狀態存在 wp_options

- [x] **FEAT-03**: 授權碼欄位
  - 授權碼輸入框 + 驗證按鈕
  - 顯示狀態（未啟用 / Pro 已啟用 / 到期日）
  - wp_options: buygo_license_key, buygo_license_status, buygo_license_expires

- [x] **FEAT-04**: buygo_is_pro() 輔助函式
  - 檢查授權狀態和到期日
  - 這次先永遠回傳 true（授權伺服器未來做）

### 開發者工具 (DEV)

- [x] **DEV-01**: 合併流程日誌
  - 原 workflow-tab.php 的統計卡片 + 事件篩選 + 日誌表格
  - 用 .bgo-card 區塊包裝

- [x] **DEV-02**: 合併資料清除
  - 原 test-tools-tab.php 的統計 + 一鍵清除功能
  - 用 .bgo-card 區塊包裝

- [x] **DEV-03**: 合併 SQL 查詢
  - 原 debug-center-tab.php 的 SELECT-only 查詢控制台
  - 用 .bgo-card 區塊包裝

### 預留 API (API)

- [x] **API-01**: 批量上架端點骨架
  - POST /products/batch-create — 回傳 501 Not Implemented

- [x] **API-02**: 多圖上傳端點骨架
  - POST /products/{id}/images — 回傳 501
  - GET /products/{id}/images — 回傳 501

- [x] **API-03**: 自定義欄位端點骨架
  - GET /products/{id}/custom-fields — 回傳 501
  - PUT /products/{id}/custom-fields — 回傳 501

### 清理 (CLEAN)

- [x] **CLEAN-01**: 刪除 notifications-tab.php
  - 自 2025/12/14 後無新記錄，功能已移至 LineHub

- [x] **CLEAN-02**: 刪除被合併的舊 Tab 檔案
  - workflow-tab.php、test-tools-tab.php、debug-center-tab.php
  - 內容已合併至 developer-tab.php

- [x] **CLEAN-03**: 清理 admin-settings.css
  - 移除 .tab-content 和 .status-badge（被 .bgo-* 取代）
  - 保留 modal、使用者搜尋等功能性樣式

### Out of Scope (v2.0)

- **前端 Vue Portal 改版** — 只改 wp-admin，不動 /buygo-portal/*
- **授權伺服器** — buygo_is_pro() 先回傳 true，伺服器未來做
- **批量上架前端 UI** — 只留 API 骨架
- **自定義欄位前端 UI** — 只留 API 骨架
- **多圖片輪播** — 只留 API 骨架
- **異地備份模組** — 獨立功能，需先完成服務條款
- **跨外掛共用 CSS** — 違反隔離原則，各自維護結構相同的樣式
- **AJAX/表單處理重構** — 不改動現有邏輯

### Traceability (v2.0)

| Requirement | Phase | Status |
|-------------|-------|--------|
| ARCH-01 | Phase 41 | Complete |
| ARCH-02 | Phase 41 | Complete |
| ARCH-03 | Phase 41 | Complete |
| ARCH-04 | Phase 41 | Complete |
| ROLE-01 | Phase 42 | Complete |
| ROLE-02 | Phase 42 | Complete |
| ROLE-03 | Phase 42 | Complete |
| ROLE-04 | Phase 42 | Complete |
| ROLE-05 | Phase 42 | Complete |
| ROLE-06 | Phase 42 | Complete |
| ROLE-07 | Phase 42 | Complete |
| DATA-01 | Phase 43 | Complete |
| DATA-02 | Phase 43 | Complete |
| DATA-03 | Phase 43 | Complete |
| DATA-04 | Phase 43 | Complete |
| DATA-05 | Phase 43 | Complete |
| FEAT-01 | Phase 44 | Complete |
| FEAT-02 | Phase 44 | Complete |
| FEAT-03 | Phase 44 | Complete |
| FEAT-04 | Phase 44 | Complete |
| DEV-01 | Phase 45 | Complete |
| DEV-02 | Phase 45 | Complete |
| DEV-03 | Phase 45 | Complete |
| API-01 | Phase 45 | Complete |
| API-02 | Phase 45 | Complete |
| API-03 | Phase 45 | Complete |
| CLEAN-01 | Phase 46 | Complete |
| CLEAN-02 | Phase 46 | Complete |
| CLEAN-03 | Phase 46 | Complete |

**Coverage:**
- v2.0 requirements: 29 total
- Mapped to phases: 29/29 (100%)

---

*Last updated: 2026-03-01 — v3.2 roadmap complete (18/18 mapped, Phases 57-59), v3.1 complete (17), v2.0 complete (29)*
