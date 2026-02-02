# Roadmap: BuyGo+1 v1.4

**Created:** 2026-02-02
**Milestone:** v1.4 - 會員前台子訂單顯示功能
**Starting Phase:** 35（延續 v1.3 的 Phase 34）

## Milestones

- ✅ **v1.0 MVP** - Phases 1-22 (shipped 2026-01-29)
- ✅ **v1.1 部署優化與會員權限** - Phases 23-27 (shipped 2026-02-01)
- ✅ **v1.2 LINE 通知觸發機制整合** - Phases 28-31 (shipped 2026-02-01)
- ✅ **v1.3 出貨通知與 FluentCart 同步系統** - Phases 32-34 (shipped 2026-02-02)
- 🚧 **v1.4 會員前台子訂單顯示功能** - Phases 35-37 (in progress)

## Phase Overview

| # | Phase | Goal | Requirements | Success Criteria |
|---|-------|------|--------------|------------------|
| 35 | FluentCart Hook 探索與注入點設定 | 確定整合技術可行性，建立 UI 注入基礎 | INTEG-01~03 | 3 |
| 36 | 子訂單查詢與 API 服務 | 完成後端資料層，可通過 API 查詢子訂單 | QUERY-01~04, API-01~04 | 5 |
| 37 | 前端 UI 元件與互動 | 完成前端顯示和交互，整合 API | UI-01~06 | 5 |

---

<details>
<summary>✅ v1.0 MVP (Phases 1-22) - SHIPPED 2026-01-29</summary>

**Milestone Goal:** 完成設計系統遷移與核心功能

### Phase 10-22: [詳細內容見 v1.0 ROADMAP.md]

</details>

<details>
<summary>✅ v1.1 部署優化與會員權限 (Phases 23-27) - SHIPPED 2026-02-01</summary>

**Milestone Goal:** 實作 GitHub Releases 自動更新機制與多賣家權限系統

### Phase 23-27: [詳細內容見 v1.1 ROADMAP.md]

</details>

<details>
<summary>✅ v1.2 LINE 通知觸發機制整合 (Phases 28-31) - SHIPPED 2026-02-01</summary>

**Milestone Goal:** 整合 buygo-line-notify，實作商品上架和訂單通知

### Phase 28-31: [詳細內容見 v1.2 ROADMAP.md]

</details>

<details>
<summary>✅ v1.3 出貨通知與 FluentCart 同步系統 (Phases 32-34) - SHIPPED 2026-02-02</summary>

**Milestone Goal:** 完善出貨流程，實作 LINE 出貨通知功能

### Phase 32-34: [詳細內容見 v1.3 ROADMAP.md]

</details>

---

## 🚧 v1.4 會員前台子訂單顯示功能 (In Progress)

**Milestone Goal:** 在 FluentCart 會員前台訂單頁面中，讓購物者能查看該主訂單包含的所有子訂單詳細資訊

**Context:**
- FluentCart 會員前台目前僅顯示主訂單資訊
- 購物者無法查看子訂單的商品清單、狀態、金額
- 需使用 WordPress Hook 注入 UI（不修改 FluentCart 原始碼）
- 類似 LINE 登入按鈕的整合模式（參考 buygo-line-notify）
- UI 使用 BuyGo+1 設計系統（.btn, .card, .status-tag）

**技術背景:**
- FluentCart 資料表：wp_fct_orders（主訂單）、wp_fct_child_orders（子訂單）、wp_fct_order_items（商品）
- 訂單關係：1 主訂單 → N 子訂單（每個子訂單對應一個賣家）
- 子訂單狀態：payment_status, shipping_status, fulfillment_status

---

### Phase 35: FluentCart Hook 探索與注入點設定

**Goal:** 確定 FluentCart 會員訂單詳情頁的整合技術可行性，建立 UI 注入基礎

**Depends on:** v1.3 Phase 34 完成

**Requirements:**
- INTEG-01: 找出 FluentCart 會員訂單詳情頁的 Hook 點位置
- INTEG-02: 透過 WordPress Hook 在主訂單下方注入「查看子訂單」按鈕
- INTEG-03: 注入子訂單列表容器（初始隱藏，點擊按鈕展開）

**Success Criteria** (what must be TRUE):
1. 識別出 FluentCart 會員訂單詳情頁的 WordPress Action Hook（例如：fluent_cart/order_details/after_content）
2. 在主訂單詳情頁面可以看到「查看子訂單」按鈕（位於主訂單資訊下方）
3. 按鈕下方存在隱藏的 div 容器（id="buygo-child-orders-container"），點擊按鈕時容器展開

**Plans:** 1 plan

Plans:
- [ ] 35-01-PLAN.md — FluentCart Hook 整合基礎，注入按鈕和容器

---

### Phase 36: 子訂單查詢與 API 服務

**Goal:** 實作後端資料查詢邏輯和 REST API 端點，提供子訂單資料給前端

**Depends on:** Phase 35（需要確認整合可行性）

**Requirements:**
- QUERY-01: ChildOrderService 查詢指定主訂單的所有子訂單（含賣家資訊）
- QUERY-02: 使用 Eager Loading 查詢子訂單商品清單（避免 N+1 查詢）
- QUERY-03: 整合子訂單狀態資訊（payment_status、shipping_status、fulfillment_status）
- QUERY-04: 子訂單金額小計計算（含幣別資訊）
- API-01: GET /buygo-plus-one/v1/child-orders/{parent_order_id} 端點
- API-02: 三層權限驗證（API nonce + Service customer_id + SQL WHERE）
- API-03: 回傳格式化的子訂單資料（編號、商品、狀態、金額、賣家）
- API-04: 錯誤處理（訂單不存在、無權限、系統錯誤）

**Success Criteria** (what must be TRUE):
1. ChildOrderService::getChildOrdersByParentId($parent_order_id, $customer_id) 可以查詢指定主訂單的所有子訂單，並使用一次 JOIN 查詢載入所有商品資訊（無 N+1 問題）
2. API 端點 GET /wp-json/buygo-plus-one/v1/child-orders/123 可以成功回傳子訂單資料（JSON 格式）
3. 僅訂單所屬顧客可以查詢（其他用戶會收到 403 Forbidden 錯誤）
4. 回傳資料包含：子訂單編號、商品清單（名稱、數量、價格）、狀態標籤（付款、出貨、處理）、金額小計、賣家名稱
5. 錯誤情況正確處理：訂單不存在回傳 404、無權限回傳 403、系統錯誤回傳 500 + 錯誤訊息

**Plans:** 2 plans

Plans:
- [ ] 36-01-PLAN.md — ChildOrderService 子訂單查詢服務（QUERY-01~04）
- [ ] 36-02-PLAN.md — ChildOrders_API REST 端點（API-01~04）

---

### Phase 37: 前端 UI 元件與互動

**Goal:** 實作前端 UI 元件，整合 API 資料，提供流暢的子訂單顯示體驗

**Depends on:** Phase 36（需要 API 端點完成）

**Requirements:**
- UI-01: 「查看子訂單」按鈕樣式（使用 BuyGo+1 .btn 設計系統）
- UI-02: 子訂單列表卡片樣式（復用 .data-table 和 .card）
- UI-03: 折疊/展開交互邏輯（Vanilla JavaScript，使用 .buygo- 命名空間）
- UI-04: 子訂單狀態標籤顯示（使用 .status-tag 元件）
- UI-05: RWD 響應式設計（手機優先，60%+ 流量）
- UI-06: Loading 狀態和錯誤提示

**Success Criteria** (what must be TRUE):
1. 「查看子訂單」按鈕使用 BuyGo+1 設計系統樣式（.btn .btn-primary），與 FluentCart 原生按鈕視覺區分但不突兀
2. 點擊按鈕後，發送 AJAX 請求到 API，顯示 Loading 動畫（Spinner 或骨架屏）
3. 子訂單列表以卡片形式顯示，每張卡片包含：子訂單編號、商品列表（表格）、狀態標籤、金額小計、賣家名稱
4. 狀態標籤使用 .status-tag 系統（已付款=綠色、未付款=黃色、已出貨=藍色、處理中=灰色）
5. 在手機（< 768px）和桌面（>= 768px）都能正確顯示，手機版自動切換為垂直佈局
6. 錯誤情況顯示友善提示（訂單無子訂單、API 錯誤、權限不足）

**Plans:** TBD

Plans:
- [ ] 37-01: TBD

---

## Progress

**Execution Order:**
Phases execute in numeric order: 35 → 36 → 37

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 35. FluentCart Hook 探索與注入點設定 | 0/TBD | Not started | - |
| 36. 子訂單查詢與 API 服務 | 0/TBD | Not started | - |
| 37. 前端 UI 元件與互動 | 0/TBD | Not started | - |

---

## Milestone Success Criteria

v1.4 完成時，系統應具備：

1. **FluentCart 整合**
   - 在 FluentCart 會員訂單詳情頁成功注入 UI 元件
   - 使用 WordPress Hook，不修改 FluentCart 原始碼
   - 升級 FluentCart 不影響整合功能

2. **子訂單顯示**
   - 購物者可以點擊「查看子訂單」展開子訂單列表
   - 每個子訂單顯示：編號、商品清單、狀態、金額、賣家
   - 子訂單狀態使用顏色標籤清楚區分（付款、出貨、處理）

3. **權限與安全**
   - 僅訂單所屬顧客可以查看子訂單
   - API 端點有三層權限驗證（nonce + customer_id + SQL）
   - 無權限用戶會收到友善的錯誤提示

4. **使用者體驗**
   - 初始載入時子訂單列表隱藏，減少頁面負擔
   - Loading 狀態清楚，錯誤提示友善
   - RWD 響應式設計，手機和桌面都能正常使用
   - 視覺風格與 FluentCart 融合，使用 BuyGo+1 設計系統

5. **效能與維護性**
   - 查詢使用 Eager Loading，避免 N+1 問題
   - Vanilla JavaScript 實作，無額外依賴
   - 程式碼遵循 WordPress 標準和 BuyGo+1 慣例

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| FluentCart 無提供合適的 Hook 點 | Medium | High | 先探索 Hook 點（Phase 35），若無法找到改用其他整合方式（例如：Shortcode + 手動插入） |
| FluentCart 升級改變 Hook 結構 | Low | Medium | 使用 FluentCart 官方文件建議的 Hook，定期追蹤 FluentCart 更新日誌 |
| 權限驗證漏洞（跨顧客存取） | Low | High | 三層權限驗證（API nonce + Service + SQL），單元測試覆蓋權限邏輯 |
| N+1 查詢問題影響效能 | Low | Medium | 使用 Eager Loading（一次 JOIN 查詢），SlowQueryMonitor 監控 |
| 手機版 RWD 佈局問題 | Low | Low | 手機優先設計，測試 iPhone/Android 實機 |

---

## Out of Scope (v1.4)

以下功能明確**不包含**在 v1.4：

- **賣家後台子訂單顯示** — v1.4 僅做購物者前台，賣家後台未來再評估
- **商品縮圖顯示** — 延後至 v1.5+（視覺增強）
- **物流追蹤連結** — 延後至 v1.5+（需整合物流商 API）
- **訂單狀態時間軸** — 延後至 v1.5+（互動增強）
- **重複購買按鈕** — 延後至 v1.5+（提升 GMV）
- **LINE 通知整合** — 延後至 v1.5+（利用現有基礎設施）
- **子訂單編輯功能** — 前台不應允許編輯，避免資料不一致
- **子訂單搜尋/篩選** — 單純列表顯示即可
- **子訂單匯出功能** — 目前不需要
- **即時更新（WebSocket）** — v1.4 使用 AJAX 查詢，夠用

---

*Roadmap created: 2026-02-02*
*Last updated: 2026-02-02*
