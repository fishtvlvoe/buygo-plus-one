# Roadmap: BuyGo+1 v1.5

**Created:** 2026-02-04
**Milestone:** v1.5 - 賣家商品數量限制與 ID 對應系統
**Starting Phase:** 38（延續 v1.4 的 Phase 37）

## Milestones

- ✅ **v1.0 MVP** - Phases 1-22 (shipped 2026-01-29)
- ✅ **v1.1 部署優化與會員權限** - Phases 23-27 (shipped 2026-02-01)
- ✅ **v1.2 LINE 通知觸發機制整合** - Phases 28-31 (shipped 2026-02-01)
- ✅ **v1.3 出貨通知與 FluentCart 同步系統** - Phases 32-34 (shipped 2026-02-02)
- ✅ **v1.4 會員前台子訂單顯示功能** - Phases 35-37 (shipped 2026-02-02)
- 🚧 **v1.5 賣家商品數量限制與 ID 對應系統** - Phases 38-40 (in progress)

## Phase Overview

| # | Phase | Goal | Requirements | Success Criteria | Status |
|---|-------|------|--------------|------------------|--------|
| 38 | 角色權限頁面 UI 重構 | 顯示 ID 對應、簡化欄位、統一編輯體驗 | UI-01~05 | 5 | ✅ Complete |
| 39 | FluentCart 自動賦予賣家權限 | 購買指定商品自動獲得賣家身份和預設配額 | FC-01~04 | 4 | ○ Pending |
| 40 | 小幫手共享配額驗證 | 防止賣家和小幫手總上架商品數超過限制 | QUOTA-01~03 | 3 | ○ Pending |

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

<details>
<summary>✅ v1.4 會員前台子訂單顯示功能 (Phases 35-37) - SHIPPED 2026-02-02</summary>

**Milestone Goal:** 在 FluentCart 會員前台訂單頁面中，讓購物者能查看該主訂單包含的所有子訂單詳細資訊

### Phase 35-37: [詳細內容見 v1.4 ROADMAP.md]

</details>

---

## 🚧 v1.5 賣家商品數量限制與 ID 對應系統 (In Progress)

**Milestone Goal:** 重構賣家管理功能，移除「賣家類型」概念，改用統一的「商品數量限制」機制，並整合 FluentCart 自動賦予權限

**Context:**
- 現有的「賣家類型」（測試/真實）系統過於複雜且維護成本高
- 小幫手配額未與賣家共享，存在超配風險
- 手動發送 LINE 綁定已不再需要（用戶自行綁定）
- FluentCart 購買流程可自動化賣家角色賦予

**Technical Background:**
- 現有 user meta: `buygo_seller_type` (test/real), `buygo_product_limit` (數字)
- 現有資料表: `wp_buygo_helpers` (賣家-小幫手關係)
- FluentCart hook: `fluent_cart/order_paid` (訂單付款完成事件)

**Key Decisions:**
- 保留但隱藏 `buygo_seller_type` user meta（避免資料遷移風險）
- 商品限制預設從 2 改為 3（用戶反饋）
- 移除「發送綁定」按鈕（簡化 UI）
- 小幫手配額必須在 v1.5 完成（核心功能）

---

### Phase 38: 角色權限頁面 UI 重構

**Goal:** 顯示 WordPress User ID 和 BuyGo ID 對應關係，簡化欄位，統一商品限制編輯體驗

**Depends on:** v1.4 Phase 37 完成

**Requirements:**
- UI-01: 使用者欄位顯示 WordPress User ID
- UI-02: 角色欄位顯示 BuyGo ID（小幫手）或「無 BuyGo ID」（賣家）
- UI-03: 完全隱藏賣家類型欄位
- UI-04: 移除發送綁定按鈕
- UI-05: 商品限制欄位全部可編輯（預設值改為 3）

**Success Criteria** (what must be TRUE):
1. 角色權限頁面的使用者欄位顯示格式：「使用者名稱\nWP-{user_id}」（兩行，所有使用者都顯示）
2. 角色欄位顯示：小幫手顯示「BuyGo 小幫手\nBuyGo-{helpers.id}」，賣家顯示「BuyGo 管理員\n（無 BuyGo ID）」
3. 賣家類型欄位完全消失（列表和詳情頁都不顯示），但 `buygo_seller_type` user meta 繼續保留在資料庫中
4. 操作欄位只有「移除」按鈕，完全沒有「發送綁定」按鈕
5. 商品限制欄位在所有賣家都可以編輯（無 disabled 狀態），新賣家預設值為 3，輸入 0 表示無限制

**Status:** ✅ Complete (2026-02-04)

**Plans:** 3 plans

Plans:
- [x] 38-01-PLAN.md — UI 欄位顯示改造（WP ID + BuyGo ID）
- [x] 38-02-PLAN.md — 欄位隱藏與移除（賣家類型、發送綁定）
- [x] 38-03-PLAN.md — 商品限制邏輯統一（預設值、disabled 移除）

---

### Phase 39: FluentCart 自動賦予賣家權限

**Goal:** 購買指定商品的顧客自動獲得 buygo_admin 角色和預設商品配額

**Depends on:** Phase 38（UI 改造完成後，自動賦予功能才有意義）

**Requirements:**
- FC-01: 後台設定賣家商品 ID 輸入框
- FC-02: 監聽 fluent_cart/order_paid hook
- FC-03: 購買指定商品後自動賦予 buygo_admin 角色
- FC-04: 自動設定 user meta（buygo_product_limit = 3）

**Success Criteria** (what must be TRUE):
1. 角色權限設定頁面有「賣家商品 ID（FluentCart）」輸入框，可以輸入 FluentCart 商品 ID 並儲存到 `buygo_seller_product_id` option
2. 當顧客購買該商品並付款完成時，`fluent_cart/order_paid` hook 被正確監聽並執行賦予流程
3. 該顧客自動獲得 `buygo_admin` WordPress 角色，可以存取 BuyGo+1 後台
4. 該顧客自動獲得 `buygo_product_limit` = 3 和 `buygo_seller_type` = 'test' user meta（如果不存在才寫入）
5. 整個流程記錄 debug log（包含訂單 ID、用戶 ID、商品 ID、賦予結果）

**Plans:** TBD

Plans:
- [ ] 39-01: [待規劃]

---

### Phase 40: 小幫手共享配額驗證

**Goal:** 防止賣家和所有小幫手的總上架商品數超過賣家的商品限制

**Depends on:** Phase 38（需要商品限制機制已正確實作）

**Requirements:**
- QUOTA-01: 小幫手上架商品計入賣家配額
- QUOTA-02: 配額驗證邏輯（賣家 + 所有小幫手總數 <= 限制）
- QUOTA-03: 阻止超限上架

**Success Criteria** (what must be TRUE):
1. 當小幫手嘗試上架商品時，系統識別該小幫手屬於哪個賣家（查詢 `wp_buygo_helpers` 表）
2. 配額驗證邏輯正確計算：`賣家商品數 + SUM(所有小幫手商品數) <= 賣家的 buygo_product_limit`
3. 當總數達到限制時，賣家和小幫手都無法上架新商品，前端顯示錯誤訊息：「商品數量已達上限（X/Y），無法上架」
4. 配額為 0 時表示無限制，可以無限上架
5. 小幫手同時屬於多個賣家時，配額計算正確（小幫手的商品計入所有關聯賣家的配額）

**Plans:** TBD

Plans:
- [ ] 40-01: [待規劃]

---

## Progress

**Execution Order:**
Phases execute in numeric order: 38 → 39 → 40

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 38. 角色權限頁面 UI 重構 | 0/3 | Planning complete | - |
| 39. FluentCart 自動賦予賣家權限 | 0/TBD | Not started | - |
| 40. 小幫手共享配額驗證 | 0/TBD | Not started | - |

---

## Milestone Success Criteria

v1.5 完成時，系統應具備：

1. **角色權限頁面改進**
   - 清楚顯示 WordPress User ID 和 BuyGo ID 對應關係
   - 賣家類型欄位完全隱藏（但資料保留）
   - 所有賣家都可以編輯商品限制（預設值為 3）
   - 移除「發送綁定」按鈕（簡化 UI）

2. **FluentCart 自動化整合**
   - 購買指定商品自動獲得賣家身份
   - 自動設定預設商品配額（3 個）
   - 降低手動操作成本和錯誤率

3. **小幫手配額共享**
   - 小幫手上架商品計入賣家配額
   - 防止賣家 + 小幫手總上架數超過限制
   - 超限時阻止上架並顯示友善錯誤訊息

4. **向後相容性**
   - 保留 `buygo_seller_type` user meta（不刪除舊資料）
   - 現有賣家和小幫手功能繼續正常運作
   - 升級過程平滑無中斷

5. **資料一致性**
   - ID 對應關係正確顯示
   - 配額計算邏輯正確（支援多賣家小幫手）
   - Debug log 完整記錄關鍵操作

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| 移除賣家類型破壞現有邏輯 | Low | High | 只隱藏欄位不刪除資料，保持程式邏輯完整 |
| FluentCart Hook 找不到訂單商品 | Low | Medium | 先探索 Hook 參數結構，確認可取得商品清單 |
| 小幫手配額計算邏輯複雜 | Medium | Medium | 分步實作：先單一賣家，再多賣家，充分測試 |
| 配額驗證影響效能 | Low | Low | 使用快取機制，避免重複查詢 |
| UI 改造影響現有功能 | Low | Medium | 先備份舊程式碼，小步迭代，每步驗證 |

---

## Out of Scope (v1.5)

以下功能明確**不包含**在 v1.5：

- **刪除 buygo_seller_type user meta** — 保留但不顯示，避免資料遷移風險
- **後台發送 LINE 綁定** — 完全移除，用戶自行綁定
- **配額歷史記錄** — 只計算當前上架商品數量
- **配額報表和統計** — 簡化功能範圍
- **FluentCart 提前實作判斷** — 如果 UI 改造複雜可延後到 v1.6
- **多層級配額系統** — 維持簡單的單一數字限制
- **配額預警通知** — 達到限制時才阻止，不提前通知
- **賣家自助升級配額** — 配額由後台管理員手動調整

---

*Roadmap created: 2026-02-04*
*Last updated: 2026-02-04*
