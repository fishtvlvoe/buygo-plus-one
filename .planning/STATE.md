# BuyGo Plus One - 專案狀態

**最後更新:** 2026-02-02
**專案版本:** v1.4 Phase 37 Plan 01 完成

---

## 專案參考

**核心價值:** 讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，每個賣家只能看到自己的商品和訂單

**當前焦點:** v1.4 會員前台子訂單顯示功能（Phase 37）

**PROJECT.md 最後更新:** 2026-02-02

---

## 當前位置

**Milestone:** v1.4 - 會員前台子訂單顯示功能
**Phase:** 37 (前端 UI 元件與互動)
**Plan:** 01 of TBD
**Status:** 完成 (37-01-PLAN.md 執行完畢)

**已完成的 Milestones:**
- **v1.0** — 設計系統遷移與核心功能 (Phases 10-22) — Shipped 2026-01-29
- **v1.1** — 部署優化與會員權限 (Phases 23-27) — Shipped 2026-02-01
- **v1.2** — LINE 通知觸發機制整合 (Phases 28-31) — Shipped 2026-02-01
- **v1.3** — 出貨通知與 FluentCart 同步系統 (Phases 32-34) — Shipped 2026-02-02

**當前 Milestone:**
- **v1.4** — 會員前台子訂單顯示功能 (Phases 35-37) — In Progress

**下一個 Milestone:**
- **v1.5** — TBD

**Progress (v1.4):** [████████░░] 80% (Phase 35 complete, Phase 36 complete, Phase 37-01 complete)

---

## 效能指標

**Velocity (v1.4):**
- Total plans completed: 4/TBD (35-01, 36-01, 36-02, 37-01)
- Roadmap created: 2026-02-02
- Total phases: 3

**Phase Structure:**

| Phase | Requirements | Plans | Status |
|-------|--------------|-------|--------|
| 35 - FluentCart Hook 探索與注入點設定 | 3 (INTEG-01~03) | 1 | Complete |
| 36 - 子訂單查詢與 API 服務 | 8 (QUERY-01~04, API-01~04) | 2 | Complete |
| 37 - 前端 UI 元件與互動 | 6 (UI-01~06) | TBD | In progress (37-01 done) |

**Recent Activity:**
- 2026-02-02: Phase 37 Plan 01 完成 - 前端 UI 元件與互動
- 2026-02-02: Phase 36 Plan 02 完成 - ChildOrders_API REST 端點
- 2026-02-02: Phase 36 Plan 01 完成 - ChildOrderService 子訂單查詢服務
- 2026-02-02: Phase 35 完成 - FluentCart Hook 整合

---

## 累積決策

最近影響當前開發的決策（詳見 PROJECT.md 和 ROADMAP.md）：

**Phase 37-01 決策:**
- **使用 Vanilla JavaScript + IIFE**: 避免與 FluentCart Vue 3 衝突，無額外依賴
- **從 URL 解析訂單 ID**: Hook 不傳遞訂單 ID，使用 preg_match 從 REQUEST_URI 解析
- **使用 CSS 變數支援主題化**: `var(--buygo-*, fallback)` 格式，向下相容

**Phase 36-02 決策:**
- **使用 is_user_logged_in() 權限驗證**: 顧客前台 API 不需後台權限，搭配 Service 層 customer_id 驗證實現雙層安全
- **require_once 在 __construct() 中**: 符合現有 API 類別模式

**Phase 36-01 決策:**
- **getCustomerIdFromUserId 為靜態方法**: 方便在 API 層直接呼叫，無需實例化 Service
- **使用 Eager Loading**: `with(['order_items'])` 預載入商品，避免 N+1 查詢

**v1.4 Milestone 核心決策:**
- **子訂單顯示僅做購物者前台** — 賣家後台目前不需要，避免過度開發
- **使用 Hook 整合而非修改 FluentCart** — 確保升級相容性，降低維護成本
- **展開/折疊 UI 交互** — 減少頁面初始載入量，提升 UX
- **使用 BuyGo+1 設計系統** — 視覺一致性，減少 CSS 開發成本
- **Vanilla JavaScript 實作** — 無額外依賴，降低複雜度
- **三層權限驗證** — API nonce + Service customer_id + SQL WHERE

**技術背景:**
- FluentCart 資料表：wp_fct_orders（主訂單）、wp_fct_child_orders（子訂單）、wp_fct_order_items（商品）
- 訂單關係：1 主訂單 → N 子訂單（每個子訂單對應一個賣家）
- 整合模式：參考 buygo-line-notify 的 LINE 登入按鈕整合方式

---

## 待辦事項

### 立即執行

1. **完成 Phase 37 剩餘計畫**
   - 確認 Phase 37 是否有更多計畫（如 37-02）
   - 或進行整合測試

2. **v1.4 整合測試**
   - 訪問 https://test.buygo.me/my-account/purchase-history
   - 進入訂單詳情頁測試子訂單功能
   - 測試 RWD 響應式設計

### 待辦清單（v1.4）

- [x] Phase 35: FluentCart Hook 探索與注入點設定
- [x] Phase 36-01: ChildOrderService 子訂單查詢服務
- [x] Phase 36-02: ChildOrders_API REST 端點
- [x] Phase 37-01: 前端 UI 元件與互動
- [ ] Phase 37-02: 整合測試（如需要）
- [ ] v1.4 整合測試與發佈

---

## 阻礙和疑慮

### 待解決

| ID | 問題 | 影響 | 可能解決方案 |
|----|------|------|--------------|
| - | 無 | - | - |

### 已解決

| ID | 問題 | 解決方案 |
|----|------|----------|
| B35-01 | FluentCart 可能無提供合適的 Hook 點 | 找到 `fluent_cart/customer_app` Hook，成功整合 |

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
- Phase 35, 36, 37-01 已完成
- 每個 phase 有明確的 goal 和 success criteria
- 需求映射清楚（INTEG → Phase 35, QUERY+API → Phase 36, UI → Phase 37）

---

## 會話連續性

**Last session:** 2026-02-02
**Stopped at:** Phase 37 Plan 01 完成（前端 UI 元件與互動）
**Resume file:** 無

**下一步:**
1. 確認 Phase 37 是否還有其他計畫（如 37-02）
2. 若 Phase 37 完成，進行 v1.4 整合測試
3. 發佈 v1.4 milestone

**Resume command:**
```bash
# 檢查 Phase 37 剩餘計畫
ls -la .planning/phases/37-前端ui元件與互動/

# 測試子訂單功能
# 訪問 https://test.buygo.me/my-account/purchase-history
```

---

*State updated: 2026-02-02 after Phase 37 Plan 01 completion*
