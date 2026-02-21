# Requirements: BuyGo+1

**Core Value:** 讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，每個賣家只能看到自己的商品和訂單

---

## v2.0 Requirements (Active)

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

- [ ] **ROLE-01**: 表格只顯示 BGO 管理員和小幫手
  - 純 WP Admin（無 BGO 角色）不在表格中
  - WP Admin + BGO Admin 顯示，可移除 BGO 角色

- [ ] **ROLE-02**: WP Admin + BGO Admin 可移除 BGO 角色
  - 移除後從表格消失（只剩 WP Admin）
  - 不影響 WordPress 管理員身份

- [ ] **ROLE-03**: 新增流程簡化為「新增賣家」按鈕
  - 移除角色選擇 dropdown
  - 移除歸屬賣家搜尋欄位
  - 新增即自動獲得 buygo_admin 角色

- [ ] **ROLE-04**: 搜尋 UX 改進
  - 點擊搜尋框立即顯示使用者列表（0 字元開始）
  - 後端 API 支援空 query 回傳前 20 筆

- [ ] **ROLE-05**: 小幫手 5 大項細粒度權限
  - 商品管理（products）：檢視、上架、編輯、刪除、分配
  - 訂單管理（orders）：檢視、狀態更新、拆分合併、轉備貨
  - 出貨管理（shipments）：檢視、建立、標記出貨、匯出
  - 客戶資料（customers）：檢視列表、詳細資料
  - 設定管理（settings）：模板、關鍵字、小幫手管理
  - 預設：商品管理開，其餘關

- [ ] **ROLE-06**: 權限設定 Modal
  - 表格中小幫手的「操作」欄位新增「權限設定」按鈕
  - Modal 顯示 5 個 checkbox + 說明
  - 儲存到 user_meta: buygo_helper_capabilities
  - 「重設為預設」按鈕

- [ ] **ROLE-07**: API 層和 Portal 層權限檢查
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

- [ ] **FEAT-01**: Free/Pro 功能列表顯示
  - Free 功能：角色權限、LINE 模板、結帳設定、單一商品管理、訂單檢視、基本出貨
  - Pro 功能：小幫手系統、合併訂單、批次操作、資料管理、自定義欄位、多圖輪播、匯出
  - 未授權時 Pro 功能顯示「升級 Pro」

- [ ] **FEAT-02**: 功能啟用/關閉開關
  - Pro 啟用後各功能可獨立開關
  - 開關狀態存在 wp_options

- [ ] **FEAT-03**: 授權碼欄位
  - 授權碼輸入框 + 驗證按鈕
  - 顯示狀態（未啟用 / Pro 已啟用 / 到期日）
  - wp_options: buygo_license_key, buygo_license_status, buygo_license_expires

- [ ] **FEAT-04**: buygo_is_pro() 輔助函式
  - 檢查授權狀態和到期日
  - 這次先永遠回傳 true（授權伺服器未來做）

### 開發者工具 (DEV)

- [ ] **DEV-01**: 合併流程日誌
  - 原 workflow-tab.php 的統計卡片 + 事件篩選 + 日誌表格
  - 用 .bgo-card 區塊包裝

- [ ] **DEV-02**: 合併資料清除
  - 原 test-tools-tab.php 的統計 + 一鍵清除功能
  - 用 .bgo-card 區塊包裝

- [ ] **DEV-03**: 合併 SQL 查詢
  - 原 debug-center-tab.php 的 SELECT-only 查詢控制台
  - 用 .bgo-card 區塊包裝

### 預留 API (API)

- [ ] **API-01**: 批量上架端點骨架
  - POST /products/batch-create — 回傳 501 Not Implemented

- [ ] **API-02**: 多圖上傳端點骨架
  - POST /products/{id}/images — 回傳 501
  - GET /products/{id}/images — 回傳 501

- [ ] **API-03**: 自定義欄位端點骨架
  - GET /products/{id}/custom-fields — 回傳 501
  - PUT /products/{id}/custom-fields — 回傳 501

### 清理 (CLEAN)

- [ ] **CLEAN-01**: 刪除 notifications-tab.php
  - 自 2025/12/14 後無新記錄，功能已移至 LineHub

- [ ] **CLEAN-02**: 刪除被合併的舊 Tab 檔案
  - workflow-tab.php、test-tools-tab.php、debug-center-tab.php
  - 內容已合併至 developer-tab.php

- [ ] **CLEAN-03**: 清理 admin-settings.css
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
| ROLE-01 | Phase 42 | Pending |
| ROLE-02 | Phase 42 | Pending |
| ROLE-03 | Phase 42 | Pending |
| ROLE-04 | Phase 42 | Pending |
| ROLE-05 | Phase 42 | Pending |
| ROLE-06 | Phase 42 | Pending |
| ROLE-07 | Phase 42 | Pending |
| DATA-01 | Phase 43 | Complete |
| DATA-02 | Phase 43 | Complete |
| DATA-03 | Phase 43 | Complete |
| DATA-04 | Phase 43 | Complete |
| DATA-05 | Phase 43 | Complete |
| FEAT-01 | Phase 44 | Pending |
| FEAT-02 | Phase 44 | Pending |
| FEAT-03 | Phase 44 | Pending |
| FEAT-04 | Phase 44 | Pending |
| DEV-01 | Phase 45 | Pending |
| DEV-02 | Phase 45 | Pending |
| DEV-03 | Phase 45 | Pending |
| API-01 | Phase 45 | Pending |
| API-02 | Phase 45 | Pending |
| API-03 | Phase 45 | Pending |
| CLEAN-01 | Phase 46 | Pending |
| CLEAN-02 | Phase 46 | Pending |
| CLEAN-03 | Phase 46 | Pending |

**Coverage:**
- v2.0 requirements: 29 total
- Mapped to phases: 29/29 (100%)

---

*Last updated: 2026-02-20 — traceability table completed, all 29 requirements mapped to phases 41-46*
