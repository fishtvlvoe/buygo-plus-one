# BuyGo Plus One - 專案狀態

**最後更新:** 2026-02-20
**專案版本:** v2.0 後台 UI 統一化

---

## 專案參考

**核心價值:** 讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，每個賣家只能看到自己的商品和訂單

**當前焦點:** v2.0 — 後台 UI 統一化（定義需求中）

**PROJECT.md 最後更新:** 2026-02-20

---

## 當前位置

**Milestone:** v2.0 - 後台 UI 統一化
**Phase:** Not started (defining requirements)
**Plan:** —
**Status:** Defining requirements

**已完成的 Milestones:**
- **v1.0** — 設計系統遷移與核心功能 (Phases 10-22) — Shipped 2026-01-29
- **v1.1** — 部署優化與會員權限 (Phases 23-27) — Shipped 2026-02-01
- **v1.2** — LINE 通知觸發機制整合 (Phases 28-31) — Shipped 2026-02-01
- **v1.3** — 出貨通知與 FluentCart 同步系統 (Phases 32-34) — Shipped 2026-02-02
- **v1.4** — 會員前台子訂單顯示功能 (Phases 35-37) — Shipped 2026-02-02
- **v1.5** — 賣家商品數量限制與 ID 對應系統 (Phases 38-39) — Shipped 2026-02-20

**Last activity:** 2026-02-20 — v1.5 結案，開始 v2.0 milestone

---

## 累積決策

**v2.0 核心決策（對話中確認）:**
- 選單 BuyGo+1 → BGO，取消子選單，改為單頁 6-Tab
- Tab 導航 CSS 跟 LineHub 對齊（方案 A：各自品牌色 + 共用結構）
- 只改 wp-admin 設定頁，不動前端 Portal
- 通知記錄 Tab 刪除（已壞，功能移至 LineHub）
- 流程監控/測試工具/除錯中心合併為「開發者」Tab
- 角色表格只顯示 BGO 角色，純 WP Admin 不顯示
- 新增流程簡化為「新增賣家」
- 搜尋 UX：點擊搜尋框即顯示（0 字元開始）
- 小幫手權限簡化為 5 大項（商品/訂單/出貨/客戶/設定）
- Pro 授權透過 BuyGo 外掛控制（不用 FluentCart Licensing）
- 資料管理刪除需二次確認（Modal + 輸入 DELETE）
- Phase 40（小幫手共享配額）已取消

---

*State updated: 2026-02-20 — v2.0 milestone started*
