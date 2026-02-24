# BuyGo Plus One - 專案狀態

**最後更新:** 2026-02-24
**專案版本:** v3.1 WP 後台完善 + 批量上架

---

## 專案參考

**核心價值:** 讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，每個賣家只能看到自己的商品和訂單

**當前焦點:** v3.1 已完成，等待下一個 milestone

**PROJECT.md 最後更新:** 2026-02-24

---

## 當前位置

**Milestone:** v3.1 - WP 後台完善 + 批量上架
**Phase:** All complete
**Status:** ✅ Milestone complete

```
進度 [██████████] 5/5 phases (52-56)
```

**已完成的 Milestones:**
- **v1.0** — 設計系統遷移與核心功能 (Phases 10-22) — Shipped 2026-01-29
- **v1.1** — 部署優化與會員權限 (Phases 23-27) — Shipped 2026-02-01
- **v1.2** — LINE 通知觸發機制整合 (Phases 28-31) — Shipped 2026-02-01
- **v1.3** — 出貨通知與 FluentCart 同步系統 (Phases 32-34) — Shipped 2026-02-02
- **v1.4** — 會員前台子訂單顯示功能 (Phases 35-37) — Shipped 2026-02-02
- **v1.5** — 賣家商品數量限制與 ID 對應系統 (Phases 38-39) — Shipped 2026-02-20
- **v2.0** — 後台 UI 統一化 (Phases 41-46) — Shipped 2026-02-23
- **v3.0** — SPA 改造 + 商品欄位擴充 + 客戶編輯 (Phases 47-51) — Shipped 2026-02-24
- **v3.1** — WP 後台完善 + 批量上架 (Phases 52-56) — Shipped 2026-02-24

**Last activity:** 2026-02-24 — v3.1 所有 Phase 完成（52-56），127 單元測試全部通過

---

## v3.1 Phase 結構

| Phase | Name | Requirements | Status |
|-------|------|--------------|--------|
| 52 | R2 Tab 位置調整 | TAB-01 | ✅ Complete |
| 53 | 開發者 Tab 事件分類重構 | EVT-01~04 | ✅ Complete |
| 54 | 資料管理 Tab 前端 | DMF-01~04 | ✅ Complete |
| 55 | 功能管理 Tab 前端 | FMF-01~04 | ✅ Complete |
| 56 | 批量上架 API 後端 | BATCH-01~04 | ✅ Complete |

**Execution Order:** 52 → 56 → 53 → 54 → 55（全部完成）

---

## v3.0 Phase 結構

| Phase | Name | Requirements | Status |
|-------|------|--------------|--------|
| 47 | SPA 資料載入 + 頁面切換完善 | SPA-01~03 | Complete |
| 48 | Vue/Tailwind 本地打包 | SPA-04~05 | Skipped（CDN 方案已足夠）|
| 49 | 商品成本與來源子分頁 | PROD-01~03 | Complete |
| 50 | 儀表板利潤統計 | PROD-04 | Complete |
| 51 | 客戶頁面編輯模式 | CUST-01~05 | Complete |

---

## v2.0 Phase 結構

| Phase | Name | Requirements | Status |
|-------|------|--------------|--------|
| 41 | 基礎架構 | ARCH-01~04 | Complete |
| 42 | 角色權限優化 | ROLE-01~07 | Complete |
| 43 | 資料管理 Tab | DATA-01~05 | Complete (Plan 01+02) |
| 44 | 功能管理 Tab | FEAT-01~04 | Complete (Plan 01+02) |
| 45 | 開發者 Tab + 預留 API | DEV-01~03, API-01~03 | Complete (Plan 01+02) |
| 46 | 清理 | CLEAN-01~03 | Complete |

---

## 累積決策

**v3.1 核心決策（對話中確認）:**
- 先做 WP 後台（wp-admin），Portal 前台 UI 之後再加
- Tab 順序：角色權限 → R2 圖床 → LINE 模板 → 結帳設定 → 資料管理 → 功能管理 → 開發者
- 開發者事件用分組摺疊式顯示，error 類預設展開
- 批量上架先做 API 後端，前端 UI 之後再做
- 資料管理刪除功能是給管理員（admin）用的，賣家刪除是 Portal 側另外做

**v3.0 核心決策:**
- SPA Phase 1 骨架已完成（useRouter.js + catch-all + App Shell）
- useRouter.js 用 Object.assign 擴展 RouterMixin（不覆蓋），方法名 spaNavigate/initSPA 避免衝突
- 商品自訂欄位使用 buygo_product_meta 表（不改 FluentCart 原生表）
- 客戶自訂欄位使用 buygo_customer_meta 表
- 利潤為前端即時計算（不存資料庫），儀表板統計用 DashboardService 快取
- Phase 48 跳過（用戶決策：.vue SFC 增加部署複雜度，CDN 方案已足夠）

**v2.0 核心決策（對話中確認）:**
- 選單 BuyGo+1 → BGO，取消子選單，改為單頁 6-Tab
- Tab 導航 CSS 跟 LineHub 對齊（各自品牌色 + 共用結構）
- 只改 wp-admin 設定頁，不動前端 Portal
- 通知記錄 Tab 刪除（已壞，功能移至 LineHub）
- 流程監控/測試工具/除錯中心合併為「開發者」Tab
- 角色表格只顯示 BGO 角色，純 WP Admin 不顯示
- 新增流程簡化為「新增賣家」（無需選角色和歸屬賣家）
- 搜尋 UX：點擊搜尋框即顯示（0 字元開始，前 20 筆）
- 小幫手權限簡化為 5 大項（商品/訂單/出貨/客戶/設定）
- Pro 授權透過 BuyGo 外掛控制（不用 FluentCart Licensing）
- buygo_is_pro() 現在永遠回傳 true（授權伺服器未來做）
- 資料管理刪除需二次確認（Modal + 輸入 DELETE）
- Phase 40（小幫手共享配額）已取消

**Phase 43 決策:**
- DataManagementService 使用 wpdb 直接查詢（非 Eloquent），控制複雜 JOIN 和串聯刪除
- 商品刪除沿用 FluentCart 既有軟刪除模式（item_status = inactive）
- 客戶刪除只移除 FluentCart 資料，保留 WP 帳號
- 訂單刪除含遞迴子訂單處理
- REST API 使用 instance method check_permission_for_admin（與 Settings_API 一致）
- 單一 /query 端點搭配 type 參數切換訂單/商品/客戶（更簡潔的 API 設計）
- 所有刪除端點強制 confirmation_token = 'DELETE' 伺服器端驗證（DATA-05）

**Phase 44 決策:**
- functions.php 透過 require_once 在 buygo-plus-one.php 直接載入（非 autoloader，因為不是 class）
- FeatureManagementService 7 個方法全為 static（與 SettingsService 模式一致）
- Pro 功能開關預設全部啟用
- is_pro() 永遠回傳 true，加 TODO 標記未來改接授權伺服器

**Phase 45 決策:**
- developer-tab.php 合併三工具為 .bgo-card 區塊，AJAX handlers 內嵌在同一檔案
- SQL Console 伺服器端驗證 SELECT-only（白名單 + 黑名單雙重檢查）
- Data Cleanup 使用 prompt() 要求輸入 "DELETE" 確認
- Reserved_API 使用 not_implemented() 共用方法產生 501 回應
- 所有預留端點使用 API::check_permission（標準 BuyGo 權限，非管理員限定）

**v2.0 Out of Scope:**
- 前端 Vue Portal 改版（只改 wp-admin）
- 授權伺服器（buygo_is_pro() 先回傳 true）
- 批量上架前端 UI（只留 API 骨架）→ v3.1 Phase 56 已實作後端
- 自定義欄位前端 UI（只留 API 骨架）→ v3.0 Phase 49 已實作
- 多圖片輪播（只留 API 骨架）
- 異地備份模組

---

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 1 | Phase 1: SPA 骨架 — useRouter.js + catch-all 路由 + App Shell | 2026-02-24 | 1464a5f | [1-phase-1-spa-userouter-js-class-routes-ph](./quick/1-phase-1-spa-userouter-js-class-routes-ph/) |

---

*State updated: 2026-02-24 — v3.1 milestone 完成 (5/5 phases)*
