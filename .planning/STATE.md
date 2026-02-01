# BuyGo Plus One - 專案狀態

**最後更新:** 2026-02-02
**專案版本:** v1.4 milestone roadmap 完成

---

## 專案參考

**核心價值:** 讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，每個賣家只能看到自己的商品和訂單

**當前焦點:** v1.4 會員前台子訂單顯示功能

**PROJECT.md 最後更新:** 2026-02-02

---

## 當前位置

**Milestone:** v1.4 - 會員前台子訂單顯示功能
**Phase:** 35 (FluentCart Hook 探索與注入點設定)
**Plan:** Pending (roadmap 已建立，待開始 planning)
**Status:** Ready to plan

**所有已完成的 Milestones:**
- **v1.0** — 設計系統遷移與核心功能 (Phases 10-22) — Shipped 2026-01-29
- **v1.1** — 部署優化與會員權限 (Phases 23-27) — Shipped 2026-02-01
- **v1.2** — LINE 通知觸發機制整合 (Phases 28-31) — Shipped 2026-02-01
- **v1.3** — 出貨通知與 FluentCart 同步系統 (Phases 32-34) — Shipped 2026-02-02

**當前 Milestone:**
- **v1.4** — 會員前台子訂單顯示功能 (Phases 35-37) — Planning

**Progress (v1.4):** [░░░░░░░░░░] 0% (Roadmap created, ready to start Phase 35)

---

## 效能指標

**Velocity (v1.4):**
- Total plans completed: 0/TBD
- Roadmap created: 2026-02-02
- Total phases: 3

**Phase Structure:**

| Phase | Requirements | Plans | Status |
|-------|--------------|-------|--------|
| 35 - FluentCart Hook 探索與注入點設定 | 3 (INTEG-01~03) | TBD | Not started |
| 36 - 子訂單查詢與 API 服務 | 8 (QUERY-01~04, API-01~04) | TBD | Not started |
| 37 - 前端 UI 元件與互動 | 6 (UI-01~06) | TBD | Not started |

**Recent Activity:**
- 2026-02-02: v1.4 ROADMAP.md 建立完成
- 2026-02-02: v1.3 milestone 完成 (Phases 32-34)

---

## 累積決策

最近影響當前開發的決策（詳見 PROJECT.md 和 ROADMAP.md）：

**v1.4 Milestone 核心決策:**
- **子訂單顯示僅做購物者前台** — 賣家後台目前不需要，避免過度開發
- **使用 Hook 整合而非修改 FluentCart** — 確保升級相容性，降低維護成本
- **展開/折疊 UI 交互** — 減少頁面初始載入量，提升 UX
- **使用 BuyGo+1 設計系統** — 視覺一致性，減少 CSS 開發成本
- **Vanilla JavaScript 實作** — 無額外依賴，降低複雜度
- **三層權限驗證** — API nonce + Service customer_id + SQL WHERE

**v1.4 Phase 分解邏輯:**
- Phase 35: 先探索整合可行性（Hook 點），建立基礎
- Phase 36: 完成後端（Service + API），獨立可測試
- Phase 37: 完成前端（UI + 整合），交付完整功能

**技術背景:**
- FluentCart 資料表：wp_fct_orders（主訂單）、wp_fct_child_orders（子訂單）、wp_fct_order_items（商品）
- 訂單關係：1 主訂單 → N 子訂單（每個子訂單對應一個賣家）
- 整合模式：參考 buygo-line-notify 的 LINE 登入按鈕整合方式

---

## 待辦事項

### 立即執行

1. **開始 Phase 35 planning**
   - 探索 FluentCart 會員訂單詳情頁的 Hook 點
   - 建立 UI 注入機制（按鈕 + 容器）
   - 產出 35-01-PLAN.md

2. **確認技術可行性**
   - 檢查 FluentCart 原始碼或文件
   - 測試 WordPress Action Hook
   - 如無合適 Hook，考慮 Shortcode 或其他整合方式

### 待辦清單（v1.4）

- [ ] Phase 35: FluentCart Hook 探索與注入點設定
- [ ] Phase 36: 子訂單查詢與 API 服務
- [ ] Phase 37: 前端 UI 元件與互動
- [ ] v1.4 整合測試
- [ ] v1.4 發佈準備

---

## 阻礙和疑慮

### 待解決

| ID | 問題 | 影響 | 可能解決方案 |
|----|------|------|--------------|
| B35-01 | FluentCart 可能無提供合適的 Hook 點 | 高 | 1. 先探索 FluentCart 原始碼，2. 改用 Shortcode，3. 聯繫 FluentCart 官方 |

### 已解決

（v1.3 和之前的問題已移至 MILESTONES.md）

---

## 對齊狀態

**與使用者對齊:** ✅ 良好
- v1.4 需求已收集並定義（18 個需求）
- ROADMAP.md 已建立，phase 結構清晰
- 100% 需求覆蓋率（18/18）

**與技術棧對齊:** ✅ 良好
- 使用現有 Hook 整合模式（類似 LINE 登入按鈕）
- 遵循 WordPress 標準（Action Hook、REST API）
- 可重用 BuyGo+1 設計系統元件（.btn, .card, .status-tag）
- Vanilla JavaScript（無額外依賴）

**與計畫對齊:** ✅ 良好
- ROADMAP.md 已建立（Phases 35-37）
- 每個 phase 有明確的 goal 和 success criteria
- 需求映射清楚（INTEG → Phase 35, QUERY+API → Phase 36, UI → Phase 37）
- REQUIREMENTS.md traceability 已更新

---

## 會話連續性

**Last session:** 2026-02-02
**Stopped at:** v1.4 ROADMAP 建立完成
**Resume file:** 無

**下一步:**
1. 執行 `/gsd:plan-phase 35` — 開始規劃 FluentCart Hook 探索
2. 探索 FluentCart 會員訂單詳情頁的 Hook 點
3. 建立 UI 注入機制

**Resume command:**
```bash
# 開始 Phase 35 planning
/gsd:plan-phase 35
```

---

*State updated: 2026-02-02 after v1.4 ROADMAP creation*
