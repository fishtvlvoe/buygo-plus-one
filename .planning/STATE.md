# BuyGo Plus One - 專案狀態

**最後更新:** 2026-02-02
**專案版本:** v1.3 milestone 初始化

---

## 當前位置

**Phase:** 未開始（定義需求中）
**Plan:** —
**Status:** 定義 v1.3 milestone 需求
**Last activity:** 2026-02-02 — Milestone v1.3 started

**所有已完成的 Milestones:**

- **v1.0** — 設計系統遷移與核心功能 (Phase 10-22)
- **v1.1** — 部署優化與會員權限 (Phase 23-27)
- **v1.2** — LINE 通知觸發機制整合 (Phase 28-31)

**當前 Milestone:**
- **v1.3** — 出貨通知與 FluentCart 同步系統

---

## v1.3 Milestone 目標

### 資料模型擴充
- 新增 `estimated_delivery_at` 欄位到 `buygo_shipments` 表
- 賣家可在建立/編輯出貨單時輸入預計送達時間

### LINE 出貨通知
- 賣家標記出貨單為「已出貨」→ 觸發 LINE 通知給買家
- 一張出貨單 → 一次通知（即使包含多個子訂單）
- 通知內容：商品清單、數量、物流方式、預計送達時間
- 僅通知買家（賣家和小幫手不收通知）

### 通知模板管理
- 後台 Settings 頁面新增「通知模板」設定區塊
- 預設出貨通知模板
- 客戶可自訂模板內容

---

## 累積決策

| ID | 決策 | 影響 | 日期 | 來源 |
|----|------|------|------|------|
| D21-01 | 金額以「分」為單位儲存 | Service Layer 返回分為單位,API/前端負責格式化 | 2026-01-29 | 21-01 |
| D21-02 | 使用 COALESCE 避免 NULL 值 | 所有聚合查詢使用 COALESCE(SUM(...), 0) | 2026-01-29 | 21-01 |
| D24-01 | 使用 post_author 作為賣家識別 | 商品建立時需設定正確的 post_author | 2026-02-01 | 24 |
| D24-02 | 訂單過濾使用 SQL JOIN | 透過 order_items 關聯到商品的 post_author | 2026-02-01 | 24 |
| D24-03 | 小幫手可存取多個賣場 | get_accessible_seller_ids() 返回陣列 | 2026-02-01 | 24 |
| D29-01 | 使用 Filter 進行跨外掛通訊 | buygo-line-notify/get_response filter | 2026-02-01 | 29 |
| D29-02 | buygo-line-notify 負責發送 | buygo-plus-one 只提供模板內容 | 2026-02-01 | 29 |

---

## 阻礙和疑慮

### 待解決

| ID | 問題 | 優先級 | 影響範圍 | 提出日期 |
|----|------|--------|----------|----------|
| （目前無待解決的技術債） | - | - | - | - |

### 已解決

| ID | 問題 | 解決方案 | 解決日期 |
|----|------|---------|----------|
| B21-01 | 缺少快取機制 | 實作 WordPress Transients 快取 | 2026-01-29 |
| B21-05 | 缺少快取失效機制 | DashboardCacheManager | 2026-01-31 |
| B21-06 | 缺少慢查詢監控 | SlowQueryMonitor | 2026-01-31 |
| B21-07 | 資料庫索引未建立 | DashboardIndexes | 2026-01-31 |

---

## 對齊狀態

**與使用者對齊:** ✅ 良好
- v1.3 出貨通知需求已明確定義
- 資料流程已確認

**與技術棧對齊:** ✅ 良好
- 使用現有 buygo-line-notify 整合模式
- 資料庫升級機制已建立

**與計畫對齊:** ✅ 完美
- 準備進入 requirements 定義階段

---

## 會話連續性

**Last session:** 2026-02-02
**Stopped at:** v1.3 milestone 初始化，準備定義 requirements
**Resume file:** 無

**下一步:**
- 定義詳細 requirements (REQUIREMENTS.md)
- 建立 roadmap (ROADMAP.md)
- 開始 Phase planning
