# BuyGo Plus One - 專案狀態

**最後更新:** 2026-02-21
**專案版本:** v2.0 後台 UI 統一化

---

## 專案參考

**核心價值:** 讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，每個賣家只能看到自己的商品和訂單

**當前焦點:** v2.0 — 後台 UI 統一化（Phase 43 資料管理 Tab 進行中）

**PROJECT.md 最後更新:** 2026-02-20

---

## 當前位置

**Milestone:** v2.0 - 後台 UI 統一化
**Phase:** 43 - 資料管理 Tab
**Plan:** 01 complete, 02 complete
**Status:** Phase 43 complete — DataManagementService + REST API built

```
進度 [█░░░░░] 1/6 phases complete
```

**已完成的 Milestones:**
- **v1.0** — 設計系統遷移與核心功能 (Phases 10-22) — Shipped 2026-01-29
- **v1.1** — 部署優化與會員權限 (Phases 23-27) — Shipped 2026-02-01
- **v1.2** — LINE 通知觸發機制整合 (Phases 28-31) — Shipped 2026-02-01
- **v1.3** — 出貨通知與 FluentCart 同步系統 (Phases 32-34) — Shipped 2026-02-02
- **v1.4** — 會員前台子訂單顯示功能 (Phases 35-37) — Shipped 2026-02-02
- **v1.5** — 賣家商品數量限制與 ID 對應系統 (Phases 38-39) — Shipped 2026-02-20

**Last activity:** 2026-02-21 — Phase 43 complete (DataManagementService + REST API)

---

## v2.0 Phase 結構

| Phase | Name | Requirements | Status |
|-------|------|--------------|--------|
| 41 | 基礎架構 | ARCH-01~04 | Not started |
| 42 | 角色權限優化 | ROLE-01~07 | Not started |
| 43 | 資料管理 Tab | DATA-01~05 | Complete (Plan 01+02) |
| 44 | 功能管理 Tab | FEAT-01~04 | Not started |
| 45 | 開發者 Tab + 預留 API | DEV-01~03, API-01~03 | Not started |
| 46 | 清理 | CLEAN-01~03 | Not started |

---

## 累積決策

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

**v2.0 Out of Scope:**
- 前端 Vue Portal 改版（只改 wp-admin）
- 授權伺服器（buygo_is_pro() 先回傳 true）
- 批量上架前端 UI（只留 API 骨架）
- 自定義欄位前端 UI（只留 API 骨架）
- 多圖片輪播（只留 API 骨架）
- 異地備份模組

---

*State updated: 2026-02-21 — Phase 43 complete (Plan 01+02), current_phase = 43*
