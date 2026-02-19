# Roadmap: BuyGo+1

**Created:** 2026-02-04 (v1.5)
**Updated:** 2026-02-20 (v2.0 roadmap added)

## Milestones

- ✅ **v1.0 設計系統遷移與核心功能** - Phases 10-22 (shipped 2026-01-29)
- ✅ **v1.1 部署優化與會員權限** - Phases 23-27 (shipped 2026-02-01)
- ✅ **v1.2 LINE 通知觸發機制整合** - Phases 28-31 (shipped 2026-02-01)
- ✅ **v1.3 出貨通知與 FluentCart 同步系統** - Phases 32-34 (shipped 2026-02-02)
- ✅ **v1.4 會員前台子訂單顯示功能** - Phases 35-37 (shipped 2026-02-02)
- ✅ **v1.5 賣家商品數量限制與 ID 對應系統** - Phases 38-39 (shipped 2026-02-20)
- 🚧 **v2.0 後台 UI 統一化** - Phases 41-46 (in progress)

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

## 🚧 v2.0 後台 UI 統一化 (In Progress)

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
| 41 | 基礎架構 | CSS + 選單合併為 6-Tab 單頁結構 | ARCH-01~04 | 4 | ○ Not started |
| 42 | 角色權限優化 | 表格過濾 + 5 大項細粒度權限 + 搜尋 UX | ROLE-01~07 | 5 | ○ Not started |
| 43 | 資料管理 Tab | 訂單/商品/客戶查詢、編輯、刪除 | DATA-01~05 | 4 | ○ Not started |
| 44 | 功能管理 Tab | Free/Pro 功能列表 + 授權碼驗證 | FEAT-01~04 | 4 | ○ Not started |
| 45 | 開發者 Tab + 預留 API | 三工具合併 + 3 組 API 骨架 | DEV-01~03, API-01~03 | 4 | ○ Not started |
| 46 | 清理 | 刪除廢棄檔案和舊樣式 | CLEAN-01~03 | 3 | ○ Not started |

---

## Phase Details

### Phase 41: 基礎架構

**Goal:** BGO 後台從多子選單重構為 6-Tab 單頁設計，CSS 與 LineHub 設計語言對齊

**Depends on:** Phase 39 (v1.5 完成)

**Requirements:** ARCH-01, ARCH-02, ARCH-03, ARCH-04

**Success Criteria** (what must be TRUE):
  1. WP 後台左側選單顯示「BGO」（原 BuyGo+1），點擊後進入單一頁面，不展開子選單
  2. 頁面頂部顯示 6 個 Tab（角色權限、LINE 模板、結帳設定、資料管理、功能管理、開發者），點擊各 Tab 切換內容不重新載入頁面
  3. Tab 的視覺樣式（品牌藍 #3b82f6 底線、padding、transition）與 LineHub 後台 Tab 結構一致
  4. 頁面大標顯示「BGO」，Tab 名稱大小寫正確（例如「LINE 模板」不是「Line 模板」）

**Plans:** TBD

Plans:
- [ ] 41-01: 選單重構（add_menu_page 改名 + 刪除 add_submenu_page + render_page）
- [ ] 41-02: Tab 導航 CSS（admin-tabs.css + .bgo- 前綴 + LineHub 對齊）
- [ ] 41-03: Tab 框架整合（PHP 頁面切換邏輯 + 現有 Tab 內容掛載）

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

**Plans:** TBD

Plans:
- [ ] 42-01: 表格過濾邏輯（只顯示 BGO 角色 + 移除後從表格消失）
- [ ] 42-02: 搜尋 UX 改進（0 字元觸發 + 後端空 query 回傳前 20 筆）
- [ ] 42-03: 新增流程簡化（移除 dropdown + 移除歸屬搜尋 + 自動 buygo_admin）
- [ ] 42-04: 5 大項細粒度權限 Modal（checkbox UI + user_meta 儲存 + 重設按鈕）
- [ ] 42-05: API 和 Portal 層權限檢查（buygo_helper_can() + no-access.php）

---

### Phase 43: 資料管理 Tab

**Goal:** 管理員可按日期範圍查詢、編輯、刪除訂單/商品/客戶資料，刪除需二次確認

**Depends on:** Phase 41

**Requirements:** DATA-01, DATA-02, DATA-03, DATA-04, DATA-05

**Success Criteria** (what must be TRUE):
  1. 資料管理 Tab 有篩選區：選擇資料類型（訂單/商品/客戶）、設定日期範圍、輸入關鍵字，按下查詢後顯示結果列表
  2. 訂單查詢結果可單筆刪除或勾選後批次刪除，刪除同時清理 buygo_shipment_items 關聯資料
  3. 客戶資料列表可點擊進入編輯 Modal，修改姓名、電話、地址、身分證字號後儲存到 fct_customers 和 fct_customer_addresses
  4. 所有刪除操作必須通過二次確認：第一次 Modal 顯示將刪除數量，第二次需輸入「DELETE」文字才啟用確認按鈕

**Plans:** TBD

Plans:
- [ ] 43-01: 篩選區 UI + 查詢 API（三種資料類型 + 日期範圍 + 關鍵字）
- [ ] 43-02: 訂單刪除功能（單筆 + 批次 + 關聯清理）
- [ ] 43-03: 商品刪除功能（接入現有 /products/batch-delete 端點）
- [ ] 43-04: 客戶編輯 Modal（姓名/電話/地址/身分證 + fct_customers 更新）
- [ ] 43-05: 二次確認機制（數量 Modal + DELETE 輸入驗證）

---

### Phase 44: 功能管理 Tab

**Goal:** 顯示 Free/Pro 功能列表、授權碼輸入驗證、Pro 後各功能可獨立開關

**Depends on:** Phase 41

**Requirements:** FEAT-01, FEAT-02, FEAT-03, FEAT-04

**Success Criteria** (what must be TRUE):
  1. 功能管理 Tab 顯示完整 Free 和 Pro 功能列表，未授權時 Pro 功能顯示「升級 Pro」提示
  2. 授權碼輸入框點擊驗證後顯示狀態（未啟用 / Pro 已啟用 / 到期日），狀態儲存到 wp_options
  3. Pro 啟用後，各 Pro 功能有獨立開關 toggle，開關狀態存入 wp_options 並立即生效
  4. buygo_is_pro() 函式可被其他程式呼叫，目前永遠回傳 true（授權伺服器未來實作）

**Plans:** TBD

Plans:
- [ ] 44-01: Free/Pro 功能列表 UI（分組顯示 + 未授權鎖定狀態）
- [ ] 44-02: 授權碼欄位（輸入框 + 驗證按鈕 + 狀態顯示 + wp_options 儲存）
- [ ] 44-03: 功能開關（Pro 後 toggle + wp_options + buygo_is_pro() 輔助函式）

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

**Plans:** TBD

Plans:
- [ ] 45-01: 開發者 Tab 整合（三工具合併到 developer-tab.php + .bgo-card 包裝）
- [ ] 45-02: 預留 API 骨架（batch-create + images + custom-fields，全回傳 501）

---

### Phase 46: 清理

**Goal:** 刪除 v2.0 重構後已無用的廢棄檔案和舊樣式，降低維護熵

**Depends on:** Phase 45（所有功能整合完成後才清理）

**Requirements:** CLEAN-01, CLEAN-02, CLEAN-03

**Success Criteria** (what must be TRUE):
  1. notifications-tab.php 不存在於檔案系統，且刪除後後台無任何功能損壞
  2. workflow-tab.php、test-tools-tab.php、debug-center-tab.php 三個檔案不存在，其功能已由 developer-tab.php 承接
  3. admin-settings.css 中 .tab-content 和 .status-badge 選擇器不存在，但 modal 和使用者搜尋等功能性樣式保留正常

**Plans:** TBD

Plans:
- [ ] 46-01: 刪除廢棄 Tab 檔案（notifications + workflow + test-tools + debug-center）
- [ ] 46-02: 清理 admin-settings.css（移除被 .bgo-* 取代的舊選擇器）

---

## Progress

**Execution Order:**
Phases execute in numeric order: 41 → 42 → 43 → 44 → 45 → 46

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 38. 角色權限頁面 UI 重構 | v1.5 | 3/3 | Complete | 2026-02-04 |
| 39. FluentCart 自動賦予賣家權限 | v1.5 | 4/4 | Complete | 2026-02-20 |
| 40. 小幫手共享配額驗證 | v1.5 | — | Cancelled | — |
| 41. 基礎架構 | v2.0 | 0/TBD | Not started | — |
| 42. 角色權限優化 | v2.0 | 0/TBD | Not started | — |
| 43. 資料管理 Tab | v2.0 | 0/TBD | Not started | — |
| 44. 功能管理 Tab | v2.0 | 0/TBD | Not started | — |
| 45. 開發者 Tab + 預留 API | v2.0 | 0/TBD | Not started | — |
| 46. 清理 | v2.0 | 0/TBD | Not started | — |

---

*Roadmap created: 2026-02-04*
*v2.0 phases added: 2026-02-20*
