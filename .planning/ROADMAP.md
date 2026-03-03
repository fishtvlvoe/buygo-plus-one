# Roadmap: BuyGo+1

**Created:** 2026-02-04 (v1.5)
**Updated:** 2026-03-01 (v3.2 milestone added)

## Milestones

- ✅ **v1.0 設計系統遷移與核心功能** - Phases 10-22 (shipped 2026-01-29)
- ✅ **v1.1 部署優化與會員權限** - Phases 23-27 (shipped 2026-02-01)
- ✅ **v1.2 LINE 通知觸發機制整合** - Phases 28-31 (shipped 2026-02-01)
- ✅ **v1.3 出貨通知與 FluentCart 同步系統** - Phases 32-34 (shipped 2026-02-02)
- ✅ **v1.4 會員前台子訂單顯示功能** - Phases 35-37 (shipped 2026-02-02)
- ✅ **v1.5 賣家商品數量限制與 ID 對應系統** - Phases 38-39 (shipped 2026-02-20)
- ✅ **v2.0 後台 UI 統一化** - Phases 41-46 (shipped 2026-02-23)
- ✅ **v3.0 SPA 改造 + 商品欄位擴充 + 客戶編輯** - Phases 47-51 (shipped 2026-02-24)
- ✅ **v3.1 WP 後台完善 + 批量上架** - Phases 52-56 (shipped 2026-02-24)
- ✅ **v3.2 批量上架前端** - Phases 57-59 (shipped 2026-03-03)

---

<details>
<summary>✅ v1.0 MVP (Phases 10-22) - SHIPPED 2026-01-29</summary>

**Milestone Goal:** 完成設計系統遷移（表格、卡片、按鈕、狀態標籤、分頁器）、Dashboard 功能和全域搜尋功能

### Phase 10-22: [詳細內容見 MILESTONES.md]

</details>

<details>
<summary>✅ v1.1 部署優化與會員權限 (Phases 23-27) - SHIPPED 2026-02-01</summary>

**Milestone Goal:** 實作 GitHub Releases 自動更新機制與多賣家權限隔離系統

### Phase 23-27: [詳細內容見 MILESTONES.md]

</details>

<details>
<summary>✅ v1.2 LINE 通知觸發機制整合 (Phases 28-31) - SHIPPED 2026-02-01</summary>

**Milestone Goal:** 整合 buygo-line-notify，實作商品上架和訂單通知觸發邏輯

### Phase 28-31: [詳細內容見 MILESTONES.md]

</details>

<details>
<summary>✅ v1.3 出貨通知與 FluentCart 同步系統 (Phases 32-34) - SHIPPED 2026-02-02</summary>

**Milestone Goal:** 完善出貨流程，實作 LINE 出貨通知與子訂單狀態同步

### Phase 32-34: [詳細內容見 MILESTONES.md]

</details>

<details>
<summary>✅ v1.4 會員前台子訂單顯示功能 (Phases 35-37) - SHIPPED 2026-02-02</summary>

**Milestone Goal:** 在 FluentCart 會員前台訂單頁面中讓購物者查看子訂單詳細資訊

### Phase 35-37: [詳細內容見 MILESTONES.md]

</details>

<details>
<summary>✅ v1.5 賣家商品數量限制與 ID 對應系統 (Phases 38-39) - SHIPPED 2026-02-20</summary>

**Milestone Goal:** 重構賣家管理 UI（WP ID + BuyGo ID 對應）、FluentCart 購買自動賦予賣家角色

### Phase 38: 角色權限頁面 UI 重構
**Goal:** 顯示 WordPress User ID 和 BuyGo ID 對應關係，簡化欄位，統一商品限制編輯體驗
**Status:** ✅ Complete (2026-02-04)
**Plans:** 3/3

### Phase 39: FluentCart 自動賦予賣家權限
**Goal:** 購買指定商品的顧客自動獲得 buygo_admin 角色和預設商品配額
**Status:** ✅ Complete (2026-02-20)
**Plans:** 4/4

### Phase 40: 小幫手共享配額驗證
**Status:** ❌ Cancelled — 單站情境不需要共享配額（決策 2026-02-20）

</details>

---

## ✅ v2.0 後台 UI 統一化 (Shipped 2026-02-23)

**Milestone Goal:** 重構 BGO 後台 UI，與 LineHub 設計語言統一，精簡 Tab 結構（6-Tab），新增細粒度權限、資料管理和 Pro 版授權機制

**Context:**
- v1.5 完成賣家管理功能重構，現有後台仍是多子選單結構
- LineHub 外掛已採用單頁 6-Tab 設計，BGO 需對齊統一
- 現有角色權限 UI 缺乏細粒度控制（僅有「有/無」權限）
- 缺乏資料管理工具（訂單/商品/客戶的批次清理）
- Pro 版功能機制未建立

**Key Decisions:**
- 選單 BuyGo+1 → BGO，取消子選單，改為單頁 6-Tab
- Tab 導航 CSS 跟 LineHub 對齊（各自品牌色 + 共用結構）
- 只改 wp-admin 設定頁，不動前端 Portal
- 通知記錄 Tab 刪除（已壞，功能移至 LineHub）
- 流程監控/測試工具/除錯中心合併為「開發者」Tab
- 角色表格只顯示 BGO 角色，純 WP Admin 不顯示
- 小幫手權限簡化為 5 大項（商品/訂單/出貨/客戶/設定）
- Pro 授權透過 BuyGo 外掛控制（不用 FluentCart Licensing）
- 資料管理刪除需二次確認（Modal + 輸入 DELETE）

---

## Phase Overview (v2.0)

| # | Phase | Goal | Requirements | Success Criteria | Status |
|---|-------|------|--------------|------------------|--------|
| 41 | 基礎架構 | CSS + 選單合併為 6-Tab 單頁結構 | ARCH-01~04 | 4 | ✅ Complete |
| 42 | 角色權限優化 | 表格過濾 + 5 大項細粒度權限 + 搜尋 UX | ROLE-01~07 | 5 | ✅ Complete |
| 43 | 資料管理 Tab | 管理員查詢/編輯/刪除訂單商品客戶 | DATA-01~05 | 4 | ✅ Complete |
| 44 | 功能管理 Tab | Free/Pro 功能列表 + toggle + 授權碼 | FEAT-01~04 | 4 | ✅ Complete |
| 45 | 開發者 Tab + 預留 API | 三工具合併 + 3 組 API 骨架 | DEV-01~03, API-01~03 | 4 | ✅ Complete |
| 46 | 清理 | 刪除廢棄檔案和舊樣式 | CLEAN-01~03 | 3 | ✅ Complete |

---

## Phase Details (v2.0)

### Phase 41: 基礎架構

**Goal:** BGO 後台從多子選單重構為 6-Tab 單頁設計，CSS 與 LineHub 設計語言對齊

**Depends on:** Phase 39 (v1.5 完成)

**Requirements:** ARCH-01, ARCH-02, ARCH-03, ARCH-04

**Success Criteria** (what must be TRUE):
  1. WP 後台左側選單顯示「BGO」（原 BuyGo+1），點擊後進入單一頁面，不展開子選單
  2. 頁面頂部顯示 6 個 Tab（角色權限、LINE 模板、結帳設定、資料管理、功能管理、開發者），點擊各 Tab 切換內容不重新載入頁面
  3. Tab 的視覺樣式（品牌藍 #3b82f6 底線、padding、transition）與 LineHub 後台 Tab 結構一致
  4. 頁面大標顯示「BGO」，Tab 名稱大小寫正確（例如「LINE 模板」不是「Line 模板」）

**Status:** ✅ Complete (2026-02-20)
**Plans:** 3/3

Plans:
- [x] 41-01: 選單重構（add_menu_page 改名 + 刪除 add_submenu_page + render_page）
- [x] 41-02: Tab 導航 CSS（admin-tabs.css + .bgo- 前綴 + LineHub 對齊）
- [x] 41-03: Tab 框架整合（PHP 頁面切換邏輯 + 現有 Tab 內容掛載）

---

### Phase 42: 角色權限優化

**Goal:** 角色權限 Tab 只顯示 BGO 相關用戶、支援細粒度 5 大項權限設定、改善搜尋 UX

**Depends on:** Phase 41

**Requirements:** ROLE-01, ROLE-02, ROLE-03, ROLE-04, ROLE-05, ROLE-06, ROLE-07

**Success Criteria** (what must be TRUE):
  1. 角色權限表格只顯示有 BGO 角色的用戶（WP Admin 無 BGO 角色時不出現），WP Admin + BGO Admin 可以被移除 BGO 角色後從表格消失
  2. 搜尋框點擊後立即顯示前 20 筆用戶列表（不需輸入任何字元），輸入字元後即時篩選
  3. 「新增賣家」按鈕不再需要選擇角色或歸屬賣家，點擊搜尋、選擇用戶後直接賦予 buygo_admin 角色
  4. 小幫手的操作欄位有「權限設定」按鈕，點擊後 Modal 顯示 5 大項 checkbox（商品/訂單/出貨/客戶/設定），儲存後立即生效
  5. API 和 Portal 導航套用 buygo_helper_can() 檢查，無權限頁面顯示提示而非空白

**Status:** ✅ Complete (2026-02-20)

Plans: 5/5
- [x] 42-01: 表格過濾邏輯（只顯示 BGO 角色 + 移除後從表格消失）
- [x] 42-02: 搜尋 UX 改進（1 字元觸發 + 後端空 query 回傳前 20 筆）
- [x] 42-03: 新增流程簡化（移除 dropdown + 移除歸屬搜尋 + 自動 buygo_admin）
- [x] 42-04: 6 大項細粒度權限 Modal（上架/商品/訂單/出貨/客戶/設定）
- [x] 42-05: API 和 Portal 層權限檢查（helper_can() + no-access.php）

---

### Phase 43: 資料管理 Tab

**Goal:** 建立資料管理後端 API（僅 Backend），讓管理員可按日期範圍查詢、編輯、刪除訂單/商品/客戶資料，刪除需二次確認 token

**Depends on:** Phase 41

**Requirements:** DATA-01, DATA-02, DATA-03, DATA-04, DATA-05

**Success Criteria** (what must be TRUE):
  1. REST API 支援按資料類型（訂單/商品/客戶）、日期範圍、關鍵字查詢，回傳分頁結果
  2. 訂單刪除 API 同步清理 buygo_shipment_items 和 buygo_shipments 關聯資料
  3. 客戶編輯 API 可修改姓名、電話、地址、身分證字號，同步更新 fct_customers 和 fct_customer_addresses
  4. 所有刪除 API 端點強制要求 confirmation_token = 'DELETE'

**Status:** ✅ Complete (2026-02-21)
**Plans:** 2/2

---

### Phase 44: 功能管理 Tab

**Goal:** 建立功能管理後端 API（僅 Backend），提供 Free/Pro 功能列表資料、功能開關狀態儲存、授權碼驗證，以及 buygo_is_pro() 全域輔助函式

**Depends on:** Phase 41

**Requirements:** FEAT-01, FEAT-02, FEAT-03, FEAT-04

**Success Criteria** (what must be TRUE):
  1. REST API 回傳完整 Free 和 Pro 功能列表（含 id、名稱、描述、分類、啟用狀態）
  2. REST API 支援授權碼輸入和狀態查詢，狀態儲存到 wp_options（buygo_license_key, buygo_license_status, buygo_license_expires）
  3. REST API 支援各 Pro 功能獨立開關 toggle，開關狀態存入 wp_options（buygo_feature_toggles）
  4. buygo_is_pro() 函式可被其他程式呼叫，目前永遠回傳 true（授權伺服器未來實作）

**Status:** ✅ Complete (2026-02-22)
**Plans:** 2/2

---

### Phase 45: 開發者 Tab + 預留 API

**Goal:** 三個開發工具合併為單一開發者 Tab，並建立三組未來 API 的骨架端點

**Depends on:** Phase 41

**Requirements:** DEV-01, DEV-02, DEV-03, API-01, API-02, API-03

**Success Criteria** (what must be TRUE):
  1. 開發者 Tab 內包含三個 .bgo-card 區塊：流程日誌（統計卡片 + 事件篩選 + 日誌表格）、資料清除（統計 + 一鍵清除）、SQL 查詢（SELECT-only 控制台）
  2. 三個舊 Tab 檔案的功能在新開發者 Tab 中完整呈現，行為與原本一致
  3. POST /buygo-plus-one/v1/products/batch-create 端點存在，回傳 HTTP 501 Not Implemented
  4. POST 和 GET /products/{id}/images 端點存在，回傳 HTTP 501；GET 和 PUT /products/{id}/custom-fields 端點存在，回傳 HTTP 501

**Status:** ✅ Complete (2026-02-22)
**Plans:** 2/2

---

### Phase 46: 清理

**Goal:** 刪除 v2.0 重構後已無用的廢棄檔案和舊樣式，降低維護熵

**Depends on:** Phase 45（所有功能整合完成後才清理）

**Requirements:** CLEAN-01, CLEAN-02, CLEAN-03

**Success Criteria** (what must be TRUE):
  1. notifications-tab.php 不存在於檔案系統，且刪除後後台無任何功能損壞
  2. workflow-tab.php、test-tools-tab.php、debug-center-tab.php 三個檔案不存在，其功能已由 developer-tab.php 承接
  3. admin-settings.css 中 .tab-content 和 .status-badge 選擇器不存在，但 modal 和使用者搜尋等功能性樣式保留正常

**Status:** ✅ Complete (2026-02-23)
**Plans:** 2/2

---

## Progress (v2.0)

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 38. 角色權限頁面 UI 重構 | v1.5 | 3/3 | Complete | 2026-02-04 |
| 39. FluentCart 自動賦予賣家權限 | v1.5 | 4/4 | Complete | 2026-02-20 |
| 40. 小幫手共享配額驗證 | v1.5 | — | Cancelled | — |
| 41. 基礎架構 | v2.0 | 3/3 | Complete | 2026-02-20 |
| 42. 角色權限優化 | v2.0 | 5/5 | Complete | 2026-02-20 |
| 43. 資料管理 Tab | v2.0 | 2/2 | Complete | 2026-02-21 |
| 44. 功能管理 Tab | v2.0 | 2/2 | Complete | 2026-02-22 |
| 45. 開發者 Tab + 預留 API | v2.0 | 2/2 | Complete | 2026-02-22 |
| 46. 清理 | v2.0 | 2/2 | Complete | 2026-02-23 |

---

---

## ✅ v3.0 SPA 改造 + 商品欄位擴充 + 客戶編輯 (Shipped 2026-02-24)

**Milestone Goal:** 將 BuyGo Portal 從 MPA 改為 SPA（頁面切換 <500ms），擴充商品子分頁欄位（成本/利潤/來源），新增客戶資料編輯模式並同步 FluentCart。

**Context:**
- v2.0 完成後台 wp-admin UI 統一化，前端 Portal 仍為 MPA 架構
- 每次頁面切換約 2.7 秒（全頁重載），用戶體驗差
- Vue 3 和 Tailwind CSS 使用 CDN 載入（~1.5MB runtime），需預編譯降低體積
- 商品缺少成本/利潤欄位，賣家無法追蹤利潤
- 客戶頁面不支援 inline 編輯，需要透過 wp-admin 資料管理 Tab 操作

**Key Decisions:**
- SPA Phase 1 骨架已完成（useRouter.js + catch-all + App Shell），commit 1464a5f & 2da290d
- useRouter.js 用 Object.assign 擴展 RouterMixin（不覆蓋），方法名 spaNavigate/initSPA 避免衝突
- 商品自訂欄位使用 buygo_product_meta 表（不改 FluentCart 原生表）
- 客戶自訂欄位使用 buygo_customer_meta 表
- 利潤為前端即時計算（不存資料庫），儀表板統計用 DashboardService 快取

---

## Phase Overview (v3.0)

| # | Phase | Goal | Requirements | Status |
|---|-------|------|--------------|--------|
| 47 | SPA 資料載入 + 頁面切換完善 | 各頁面 API 獨立載入 + loading/error 狀態 | SPA-01~03 | ✅ Complete |
| 48 | Vue/Tailwind 本地打包 | 移除 CDN，npm build vendor.min.js + portal.min.css | SPA-04~05 | ❌ Skipped |
| 49 | 商品成本與來源子分頁 | 新增欄位 + meta 表 + API + 利潤計算 | PROD-01~03 | ✅ Complete |
| 50 | 儀表板利潤統計 | Dashboard 利潤卡片 + Top 5 | PROD-04 | ✅ Complete |
| 51 | 客戶頁面編輯模式 | Tab 對調 + inline 編輯 + API + FC 同步 | CUST-01~05 | ✅ Complete |

---

## Phase Details (v3.0)

### Phase 47: SPA 資料載入 + 頁面切換完善

**Goal:** 在 Phase 1 SPA 骨架基礎上，讓各頁面切換時透過 API 獨立載入資料，包含 loading/error 狀態處理

**Depends on:** Quick Task 1（SPA 骨架，commit 1464a5f + 2da290d）

**Requirements:** SPA-01, SPA-02, SPA-03

**Success Criteria** (what must be TRUE):
  1. 8 個頁面之間切換無白屏、無 tab spinner、無全頁重載，切換時間 < 500ms
  2. 瀏覽器前進/後退按鈕正確切換頁面，直接 URL 存取載入正確頁面
  3. 頁面切換時顯示 loading skeleton，API 回應後渲染真實資料
  4. API 呼叫失敗時顯示錯誤提示（非空白頁面），可重試

**Status:** ✅ Complete (2026-02-24)
**Plans:** 2/2

---

### Phase 48: Vue/Tailwind 本地打包

**Status:** ❌ Skipped — 用戶決策：.vue SFC 需要每次修改後 build，增加部署複雜度。FluentCommunity/FluentCRM 也未使用 .vue 檔案。CDN 方案目前足夠。（決策 2026-02-24）

---

### Phase 49: 商品成本與來源子分頁

**Goal:** 商品詳情頁新增「成本與來源」Tab，存儲自訂欄位到 buygo_product_meta，支援利潤計算

**Depends on:** Phase 47（SPA 框架就緒）

**Requirements:** PROD-01, PROD-02, PROD-03

**Success Criteria** (what must be TRUE):
  1. 商品編輯頁有兩個 Tab：「基本資訊」（現有內容）和「成本與來源」（新增）
  2. 成本與來源 Tab 包含 6 個可選填欄位：成本價、原價、購買地、供應商、條碼、製造內容
  3. GET/PUT /products/{id}/custom-fields API 正常運作（替換 Phase 45 的 501 骨架），權限檢查與現有 API 一致
  4. 商品詳情頁顯示利潤和利潤率（售價 - 成本價），即時計算

**Status:** ✅ Complete (2026-02-24)

---

### Phase 50: 儀表板利潤統計

**Goal:** Dashboard 新增利潤概覽卡片，顯示總利潤、平均利潤率、Top 5 商品

**Depends on:** Phase 49（需要商品成本資料）

**Requirements:** PROD-04

**Success Criteria** (what must be TRUE):
  1. Dashboard 頁面有「利潤概覽」卡片，顯示總利潤金額和平均利潤率百分比
  2. 卡片內顯示利潤最高的 Top 5 商品名稱和利潤金額
  3. 統計來源為已完成訂單 × 商品成本價，與手動計算結果一致

**Status:** ✅ Complete (2026-02-24)

---

### Phase 51: 客戶頁面編輯模式

**Goal:** 客戶詳情頁 Tab 順序對調（客戶資訊優先），新增 inline 全欄位編輯模式，同步 FluentCart

**Depends on:** Phase 47（SPA 框架就緒）

**Requirements:** CUST-01, CUST-02, CUST-03, CUST-04, CUST-05

**Success Criteria** (what must be TRUE):
  1. 進入客戶詳情頁時預設顯示「客戶資訊」Tab（非「訂單紀錄」）
  2. 客戶資訊 Tab 右上角有「編輯」按鈕，點擊後所有欄位轉為可編輯狀態
  3. 可編輯欄位包含：姓名、電話、地址、身分證字號、自訂編號；Email 唯讀
  4. PUT /customers/{id} API 正常運作，權限檢查通過賣家和有 customers 權限的小幫手，拒絕跨賣場存取
  5. 儲存時同步更新 fct_customers + fct_customer_addresses，自訂欄位存 buygo_customer_meta

**Status:** ✅ Complete (2026-02-24)

---

## Progress (v3.0)

**Execution Order:**
47 → 49 + 51（平行）→ 50 → MariaDB 修正
Phase 48 跳過（用戶決策：.vue SFC 增加部署複雜度，CDN 方案已足夠）

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 47. SPA 資料載入 | v3.0 | 2/2 | Complete | 2026-02-24 |
| 48. Vue/Tailwind 本地打包 | v3.0 | — | Skipped | — |
| 49. 商品成本與來源 | v3.0 | 1/1 | Complete | 2026-02-24 |
| 50. 儀表板利潤統計 | v3.0 | 1/1 | Complete | 2026-02-24 |
| 51. 客戶頁面編輯模式 | v3.0 | 1/1 | Complete | 2026-02-24 |

---

---

## ✅ v3.1 WP 後台完善 + 批量上架 (Shipped 2026-02-24)

**Milestone Goal:** 完善 WP 後台 6 個 Tab 的功能（資料管理、功能管理、開發者事件分類），調整 Tab 順序整合 R2 設定，並實作批量上架 API 後端。

**Context:**
- v3.0 完成 Portal 前台 SPA 改造，WP 後台仍有 2 個 Tab 顯示「即將推出」
- 資料管理和功能管理的後端 API 已完成（Phase 43-44），但 wp-admin 前端未接線
- 開發者 Tab 事件日誌缺乏分類，大量重複事件淹沒重要錯誤
- BGO R2 外掛的設定 Tab 排序不正確（在最後面），需移到角色權限之後
- 批量上架 API 預留端點回傳 501，需實作後端邏輯

**Key Decisions:**
- 先做 WP 後台（wp-admin），Portal 前台 UI 之後再加
- Tab 順序：角色權限 → R2 圖床 → LINE 模板 → 結帳設定 → 資料管理 → 功能管理 → 開發者
- 開發者事件用分組摺疊式顯示，error 類預設展開
- 批量上架先做 API 後端，前端 UI 之後再做
- 資料管理刪除功能是給管理員（admin）用的，賣家刪除是 Portal 側另外做

---

## Phase Overview (v3.1)

| # | Phase | Goal | Status |
|---|-------|------|--------|
| 52 | R2 Tab 位置調整 | Tab 順序調整，R2 移到角色權限之後 | ✅ Complete |
| 53 | 開發者 Tab 事件分類重構 | 事件平面列表改為分組摺疊式 | ✅ Complete |
| 54 | 資料管理 Tab 前端 | 「即將推出」替換為真實 UI | ✅ Complete |
| 55 | 功能管理 Tab 前端 | 「即將推出」替換為功能管理 UI | ✅ Complete |
| 56 | 批量上架 API 後端 | 實作 POST /products/batch-create | ✅ Complete |

---

## Phase Details (v3.1)

### Phase 52: R2 Tab 位置調整

**Goal:** 調整 BGO R2 外掛的 Tab 位置，從最後移到角色權限之後

**Depends on:** None

**Requirements:** TAB-01

**Success Criteria:**
  1. Tab 順序為：角色權限 → R2 圖床 → LINE 模板 → 結帳設定 → 資料管理 → 功能管理 → 開發者

**Status:** ✅ Complete (2026-02-24)
**Plans:** 1/1

---

### Phase 53: 開發者 Tab 事件分類重構

**Goal:** 事件日誌從平面列表改為分組摺疊式，降低大量重複事件的視覺噪音

**Requirements:** EVT-01, EVT-02, EVT-03, EVT-04

**Success Criteria:**
  1. 事件按 event_type 分組顯示，每組顯示計數和時間範圍
  2. error / permission_denied 類預設展開，其他預設收合
  3. 點擊分組可展開查看個別事件明細
  4. 支援分頁（每頁 20 筆展開明細），有上一頁/下一頁導航

**Status:** ✅ Complete (2026-02-24)

---

### Phase 54: 資料管理 Tab 前端

**Goal:** 將「即將推出」佔位卡替換為真實的資料管理 UI，接上已完成的 REST API

**Requirements:** DMF-01, DMF-02, DMF-03, DMF-04

**Success Criteria:**
  1. 三個子 Tab（訂單/商品/客戶）可切換，各自顯示查詢表格
  2. 查詢支援日期範圍篩選和關鍵字搜尋，結果分頁顯示
  3. 勾選多筆後點「刪除」，Modal 要求輸入 DELETE 確認，呼叫對應 API
  4. 客戶列表有「編輯」按鈕，點擊後 Modal 可修改姓名/電話/地址/身分證，儲存後更新表格

**Status:** ✅ Complete (2026-02-24)

---

### Phase 55: 功能管理 Tab 前端

**Goal:** 將「即將推出」佔位卡替換為功能管理 UI，接上已完成的 REST API

**Requirements:** FMF-01, FMF-02, FMF-03, FMF-04

**Success Criteria:**
  1. 頂部顯示授權狀態卡片（Free/Pro），有授權碼輸入欄和驗證按鈕
  2. 功能列表以卡片形式顯示，每張卡片有名稱、說明、toggle 開關
  3. Free 功能 toggle 可直接操作，Pro 功能需先驗證授權碼
  4. toggle 操作即時生效，呼叫 API 儲存狀態

**Status:** ✅ Complete (2026-02-24)

---

### Phase 56: 批量上架 API 後端

**Goal:** 實作 POST /products/batch-create API，讓賣家可一次上架多個商品

**Depends on:** Phase 45（Reserved_API 501 骨架）

**Requirements:** BATCH-01, BATCH-02, BATCH-03, BATCH-04

**Success Criteria:**
  1. POST /products/batch-create 接受 JSON 陣列，每個物件含 title、price 等商品資料
  2. 驗證必填欄位和賣家商品配額，超過配額時回傳錯誤
  3. 逐筆建立商品，部分失敗不影響已成功的
  4. 回傳結果包含每筆的成功/失敗狀態和原因

**Status:** ✅ Complete (2026-02-24)
**Plans:** 2/2

---

## Progress (v3.1)

**Execution Order:**
52（順手做）→ 56（核心後端）→ 53 → 54 → 55（UI 接線可批次）

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 52. R2 Tab 位置調整 | v3.1 | 1/1 | Complete | 2026-02-24 |
| 53. 開發者事件分類 | v3.1 | 1/1 | Complete | 2026-02-24 |
| 54. 資料管理前端 | v3.1 | 1/1 | Complete | 2026-02-24 |
| 55. 功能管理前端 | v3.1 | 1/1 | Complete | 2026-02-24 |
| 56. 批量上架後端 | v3.1 | 2/2 | Complete | 2026-02-24 |

---

---

## ✅ v3.2 批量上架前端 (Shipped 2026-03-03)

**Milestone Goal:** 實作批量上架的完整前端體驗 — 從商品列表頁的「+上架」按鈕進入，經過數量選擇、表單填寫（手動輸入或 CSV 匯入），到批次建立商品。

**Context:**
- v3.1 完成批量上架 API 後端（Phase 56 BATCH-01~04），前端 UI 尚未實作
- SPA 路由系統（useRouter.js + RouterMixin）已就緒（v3.0 Phase 47）
- 設計圖已完成（.planning/designs/）：5 張 PNG（手機商品列表、桌面商品列表、數量選擇、手機批量表單、桌面批量表單）
- Vue 3 + Tailwind CSS CDN 方案繼續沿用（Phase 48 跳過決策）

**Key Decisions:**
- 純前端 Milestone — 不修改後端 API
- 路由入口與數量選擇合為一個 Phase（都是流程入口的準備工作）
- 批量表單和 CSV 匯入合為一個 Phase（CSV 是表單的延伸功能，同頁面）
- 提交流程獨立為最後 Phase（收尾，含 API 呼叫 + 結果回饋）

---

## Phases (v3.2)

- [x] **Phase 57: 路由與數量選擇** - 新增「+上架」入口按鈕 + SPA 路由 + 數量選擇頁完整體驗
- [x] **Phase 58: 批量表單 + CSV 匯入** - 響應式批量表單（手機卡片/桌面表格）+ CSV 解析填入
- [x] **Phase 59: 提交與結果回饋** - 提交驗證 + API 呼叫 + 成功/失敗處理 + 桌面提示

---

## Phase Details (v3.2)

### Phase 57: 路由與數量選擇

**Goal:** 賣家可從商品列表頁進入批量上架流程，選擇要上架的商品數量後準備進入填寫

**Depends on:** Phase 47（SPA 路由框架）, Phase 56（batch-create API 已存在）

**Requirements:** ROUTE-01, ROUTE-02, SELECT-01, SELECT-02, SELECT-03, SELECT-04

**Success Criteria** (what must be TRUE):
  1. 商品列表頁在搜尋框右側顯示藍色「+ 上架」按鈕（手機版和桌面版皆有），點擊後切換到數量選擇頁（無全頁重載）
  2. 數量選擇頁顯示標題、副標、四個快選按鈕（5/10/15/20 個），選中按鈕以藍色實心顯示；自訂輸入框在輸入時自動取消快選按鈕選取狀態
  3. 頁面顯示剩餘配額資訊（「剩餘配額：N 個（已用 X / 上限 Y）」），選擇數量超過配額時「開始填寫」按鈕變為禁用狀態
  4. 底部「開始填寫（N 個商品）→」按鈕動態顯示當前選取數量，點擊後切換到表單填寫頁；返回箭頭「←」可回到商品列表頁
  5. 瀏覽器直接存取 `/products/batch-create` 路徑可正確載入數量選擇頁（SPA 路由已登記）

**Plans:** 2/2 plans complete

Plans:
- [x] 57-01-PLAN.md — SPA 路由註冊 + 商品列表頁「+ 上架」按鈕
- [x] 57-02-PLAN.md — 數量選擇頁 UI（快選按鈕 + 自訂輸入 + 配額 + CTA）

---

### Phase 58: 批量表單 + CSV 匯入

**Goal:** 賣家可在響應式表單中填寫多個商品資料，也可上傳 CSV 批次填入

**Depends on:** Phase 57（數量選擇完成，知道要建幾個商品）

**Requirements:** FORM-01, FORM-02, FORM-03, FORM-04, FORM-05, CSV-01, CSV-02, CSV-03

**Success Criteria** (what must be TRUE):
  1. 手機版以卡片形式顯示（每個商品一張，標題「商品 #N」），桌面版以表格形式顯示（行內直接編輯），兩種佈局可響應式切換
  2. 每個商品欄位包含商品名稱（必填）、售價（必填）、數量（0=無限）、描述（選填）；每個商品有刪除按鈕，底部有「+ 新增商品」按鈕，最少保留 1 個商品
  3. 頂部顯示配額進度（「商品配額：N / 上限」+ 進度條），新增或刪除商品時即時更新
  4. 手機版有「手動輸入」/ 「CSV 匯入」pill 按鈕切換；桌面版有「匯入 CSV」按鈕；切換到 CSV 模式時保留已填寫的手動資料
  5. CSV 上傳後正確解析（名稱/售價/數量/描述欄位），數量缺失時預設 0；格式錯誤時顯示錯誤提示；解析成功後填入表單並顯示匯入筆數提示，使用者可在表單中繼續修改

**Plans:** 2/2 plans complete

Plans:
- [x] 58-01-PLAN.md — 響應式表單 UI（手機卡片 + 桌面表格）+ useBatchCreate 表單狀態
- [x] 58-02-PLAN.md — CSV 匯入功能（FORM-05, CSV-01~03）

---

### Phase 59: 提交與結果回饋

**Goal:** 賣家可提交批量上架請求，得到明確的成功或失敗結果，並能在失敗時修改重試

**Depends on:** Phase 58（表單填寫完成）

**Requirements:** SUBMIT-01, SUBMIT-02, SUBMIT-03, SUBMIT-04

**Success Criteria** (what must be TRUE):
  1. 手機版底部固定欄顯示「N 件商品」+「批量上架」藍色按鈕；桌面版右上角顯示「批量上架 (N)」按鈕；N 只計算已填寫必填欄位的有效商品數量
  2. 點擊提交後按鈕變為禁用並顯示 loading 狀態，成功呼叫 POST /products/batch-create API（防止重複點擊）
  3. 全部成功時顯示成功數量並自動返回商品列表頁；部分失敗時顯示成功/失敗數量和失敗原因；全部失敗時顯示錯誤訊息並留在表單頁面可修改重試
  4. 桌面版表單底部顯示提示文字（「商品名稱和售價為必填。數量填 0 代表無限上架。支援 CSV 匯入（欄位：名稱、售價、數量、描述）。」）

**Status:** ✅ Complete (2026-03-03)
**Plans:** 1/1 plans complete

Plans:
- [x] 59-01-PLAN.md -- 提交按鈕 UI + API 呼叫 + 三種結果回饋 + 桌面提示文字

---

## Progress (v3.2)

**Execution Order:**
57 → 58 → 59（線性，每個 Phase 依賴前一個的輸出）

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 57. 路由與數量選擇 | v3.2 | 2/2 | Complete | 2026-03-02 |
| 58. 批量表單 + CSV 匯入 | v3.2 | Complete    | 2026-03-02 | 2026-03-03 |
| 59. 提交與結果回饋 | 1/1 | Complete   | 2026-03-03 | 2026-03-03 |

---

*Roadmap created: 2026-02-04*
*v2.0 phases added: 2026-02-20*
*v3.0 phases added: 2026-02-24*
*v3.1 phases added: 2026-02-24 — 5 phases (52-56)*
*v3.1 milestone complete: 2026-02-24 — 5/5 phases shipped*
*v3.2 phases added: 2026-03-01 — 3 phases (57-59), 18 requirements, frontend-only milestone*
*v3.2 Phase 58 Plan 01 complete: 2026-03-03 — 響應式表單 UI + composable 表單狀態管理*
*v3.2 Phase 58 Plan 02 complete: 2026-03-03 — CSV 匯入功能 + 模式切換 UI（Phase 58 全部完成）*
*v3.2 Phase 59 Plan 01 complete: 2026-03-03 — 提交按鈕 + API 呼叫 + 三種結果回饋 + 桌面提示文字（v3.2 milestone shipped）*
