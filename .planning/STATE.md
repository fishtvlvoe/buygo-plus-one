# BuyGo Plus One - 專案狀態

**最後更新:** 2026-02-04
**專案版本:** v1.5 Phase 39 進行中（Plan 39-01 已完成）

---

## 專案參考

**核心價值:** 讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，每個賣家只能看到自己的商品和訂單

**當前焦點:** Phase 39 - FluentCart 自動賦予賣家權限

**PROJECT.md 最後更新:** 2026-02-04

---

## 當前位置

**Milestone:** v1.5 - 賣家商品數量限制與 ID 對應系統
**Phase:** 39 of 40（FluentCart 自動賦予賣家權限 - 🔄 In Progress）
**Plan:** 39-01 已完成，準備 39-02
**Status:** Phase 39 Plan 01 已完成（賣家商品 ID 設定介面）

**已完成的 Milestones:**
- **v1.0** — 設計系統遷移與核心功能 (Phases 10-22) — Shipped 2026-01-29
- **v1.1** — 部署優化與會員權限 (Phases 23-27) — Shipped 2026-02-01
- **v1.2** — LINE 通知觸發機制整合 (Phases 28-31) — Shipped 2026-02-01
- **v1.3** — 出貨通知與 FluentCart 同步系統 (Phases 32-34) — Shipped 2026-02-02
- **v1.4** — 會員前台子訂單顯示功能 (Phases 35-37) — Shipped 2026-02-02

**當前 Milestone:**
- **v1.5** — 賣家商品數量限制與 ID 對應系統（Phases 38-40，進行中）

**Progress (v1.5):** [█████░░░░░] 50% (Phase 38-39 完成/進行中，1 個 phase 待執行)

**Last activity:** 2026-02-04 — 完成 Phase 39 Plan 01（賣家商品 ID 設定介面）

---

## 效能指標

**Velocity (v1.5):**
- Milestone started: 2026-02-04
- Roadmap created: 2026-02-04
- Phase 38 completed: 2026-02-04
- Current phase: Phase 38 完成，準備 Phase 39
- Total phases: 3
- Completed phases: 1/3 (33%)
- Total requirements: 12
- Completed requirements: 5/12 (42%)
- Coverage: 12/12 (100%)

**Phase Breakdown:**
- Phase 38: 5 requirements (UI-01 ~ UI-05)
- Phase 39: 4 requirements (FC-01 ~ FC-04)
- Phase 40: 3 requirements (QUOTA-01 ~ QUOTA-03)

**Recent Activity:**
- 2026-02-04: ✅ 完成 39-01-PLAN（賣家商品 ID 設定介面）
- 2026-02-04: ✅ Phase 38 完成並通過驗證（12/12 must-haves verified）
- 2026-02-04: 完成 38-03-PLAN（商品限制邏輯統一與預設值調整）
- 2026-02-04: 完成 38-02-PLAN（隱藏賣家類型和移除發送綁定按鈕）
- 2026-02-04: 完成 38-01-PLAN（WP ID + BuyGo ID 顯示）
- 2026-02-04: Phase 38 規劃完成（3 個計畫，通過驗證）
- 2026-02-04: v1.5 路線圖建立完成
- 2026-02-04: v1.5 需求定義完成（12 個需求）

---

## 累積決策

最近影響當前開發的決策（詳見 PROJECT.md Key Decisions）：

**v1.5 Milestone 核心決策:**
- **保留但隱藏 buygo_seller_type** — 避免資料遷移風險，未來可能需要參考 ✅ 已實作
- **商品限制預設從 2 改為 3** — 用戶反饋認為 2 個太少 ✅ 已實作
- **移除「發送綁定」按鈕** — 簡化 UI，用戶自行處理綁定 ✅ 已實作
- **商品限制 0 值明確處理** — 使用 `=== '' || === false` 檢查，避免 0 被誤判 ✅ 已實作
- **FluentCart 整合為中優先級** — 先完成 UI 改造，整合可延後
- **小幫手配額必須在 v1.5 完成** — 核心功能，防止超配是高優先

**Phase 39 決策:**
- **商品驗證時機** — 點擊驗證按鈕時觸發，避免頻繁查詢資料庫 ✅ 已實作
- **虛擬商品判斷** — 使用 `fct_products.is_shippable = 0` 判斷 ✅ 已實作
- **設定區塊位置** — 放在角色權限頁面頂部，語意相關 ✅ 已實作

**Phase 策略:**
- Phase 38 先完成 UI 重構（基礎）
- Phase 39 實作 FluentCart 整合（自動化）
- Phase 40 實作配額驗證（核心功能）

---

## 待辦清單（v1.5）

**Phase 38（角色權限頁面 UI 重構）:** ✅ 完成
- [x] 規劃 Phase 38 執行計畫（3 個計畫，通過驗證）
- [x] 實作 WordPress User ID 顯示（Plan 38-01）
- [x] 實作 BuyGo ID 顯示邏輯（Plan 38-01）
- [x] 隱藏賣家類型欄位（Plan 38-02）
- [x] 移除發送綁定按鈕（Plan 38-02）
- [x] 統一商品限制編輯體驗（Plan 38-03）

**Phase 39（FluentCart 自動賦予賣家權限）:** 🔄 進行中
- [x] 規劃 Phase 39 執行計畫
- [x] 後台設定頁面新增賣家商品 ID 輸入框（Plan 39-01）
- [x] 商品驗證 AJAX handler（Plan 39-01）
- [x] 前端驗證邏輯（Plan 39-01）
- [ ] 監聽 fluent_cart/order_paid hook（Plan 39-02）
- [ ] 自動賦予 buygo_admin 角色（Plan 39-02）
- [ ] 自動設定預設配額（Plan 39-02）

**Phase 40（小幫手共享配額驗證）:**
- [ ] 規劃 Phase 40 執行計畫
- [ ] 實作配額驗證邏輯
- [ ] 阻止超限上架
- [ ] 錯誤訊息顯示

---

## 阻礙和疑慮

### 待解決

**Phase 39 相關:**
- 需探索 fluent_cart/order_paid hook 的參數結構
- 需確認如何從 hook 取得購買的商品清單

**Phase 40 相關:**
- 配額計算邏輯可能複雜（特別是多賣家小幫手情況）
- 需確認現有商品上架流程的程式碼位置

### 已解決

**Phase 38 相關:**
- ✅ 角色權限頁面程式碼位置已確認：`includes/admin/class-settings-page.php`（純 PHP 模板，非 Vue 元件）
- ✅ BuyGo ID 查詢邏輯已確認：從 `wp_buygo_helpers.id` 表查詢

---

## 對齊狀態

**與使用者對齊:** ✅ 良好
- v1.5 目標已確認
- 核心決策已確認
- 路線圖已建立並等待用戶批准

**與技術棧對齊:** ✅ 良好
- 遵循現有 WordPress + Vue 3 架構
- 使用現有 Service Layer 模式
- 向後相容（保留但隱藏舊欄位）

**與計畫對齊:** ✅ 完成
- REQUIREMENTS.md 已完成（12 個需求，100% 映射）
- ROADMAP.md 已建立（3 個 Phase，清楚的成功標準）
- 等待執行第一個 Phase

---

## 會話連續性

**Last session:** 2026-02-04
**Stopped at:** 完成 Phase 39 Plan 01（賣家商品 ID 設定介面）
**Resume file:** 無

**下一步:**
1. 執行 Phase 39 Plan 02（FluentCart order_paid hook 整合）
2. 執行 Phase 39 Plan 03（通知與記錄系統）
3. 執行 Phase 39 Plan 04（退款撤銷機制）

**Ready to execute:** Phase 39 Plan 02 - FluentCart Hook 整合與角色賦予

**Plans completed:**
- ✅ 38-01-PLAN.md (WP ID + BuyGo ID 顯示)
- ✅ 38-02-PLAN.md (隱藏賣家類型和移除發送綁定)
- ✅ 38-03-PLAN.md (商品限制邏輯統一)
- ✅ 39-01-PLAN.md (賣家商品 ID 設定介面)

---

## 最近完成的計畫

### 39-01: 賣家商品 ID 設定介面

**完成時間:** 2026-02-04
**Duration:** 113 秒（約 2 分鐘）
**Commit:** e3d12ad, 021f064, 87edb40

**成果:**
- ✅ 在角色權限頁面新增「FluentCart 自動賦予設定」區塊
- ✅ 實作商品 ID 輸入框和儲存機制（`buygo_seller_product_id` option）
- ✅ 實作 AJAX 商品驗證 handler（存在性、狀態、虛擬商品類型）
- ✅ 實作前端驗證邏輯（即時驗證、商品資訊顯示）
- ✅ 加入說明文字和錯誤處理

**決策:**
- SETTINGS-UI-PLACEMENT: 放在角色權限頁面頂部（語意相關）
- VALIDATION-TIMING: 點擊驗證按鈕時觸發（避免頻繁查詢）
- PRODUCT-TYPE-CHECK: 使用 `fct_products.is_shippable = 0` 判斷虛擬商品

### 38-03: 商品限制邏輯統一與預設值調整

**完成時間:** 2026-02-04
**Duration:** 1 分鐘
**Commit:** 5916ee1

**成果:**
- ✅ 確認商品限制欄位無 disabled 邏輯（所有賣家統一可編輯）
- ✅ 預設值從 2 改為 3（根據用戶反饋）
- ✅ 使用明確的 `=== ''` 或 `=== false` 檢查，避免 0 值被誤判
- ✅ 加入清晰註解說明商品限制邏輯
- ✅ 加入 placeholder 和 title 提示（"0 = 無限制，預設 = 3"）

**決策:**
- PROD-LIMIT-DEFAULT-3: 預設值從 2 改為 3（用戶反饋 2 個太少）
- PROD-LIMIT-ZERO-EXPLICIT: 使用明確的 null/false 檢查而非 empty()

### 38-02: 隱藏賣家類型和移除發送綁定按鈕

**完成時間:** 2026-02-04
**Commit:** (See previous session)

**成果:**
- ✅ 隱藏賣家類型欄位（保留資料但不顯示）
- ✅ 移除發送綁定按鈕（簡化 UI）

### 38-01: UI 欄位顯示改造（WP ID + BuyGo ID）

**完成時間:** 2026-02-04
**Duration:** 1.5 分鐘
**Commit:** 47256d9

**成果:**
- ✅ 使用者欄位顯示 WP-{user_id}
- ✅ 角色欄位顯示 BuyGo-{helpers.id}（小幫手）或「（無 BuyGo ID）」（賣家）
- ✅ 優化資料庫查詢，一次取得 BuyGo ID 和賣家資訊
- ✅ 統一 ID 顯示格式（兩行，主要文字 + 灰色小字）

**決策:**
- 使用 wp_buygo_helpers.id 作為 BuyGo ID（而非 helper_id）
- 賣家顯示「（無 BuyGo ID）」明確告知預期行為
- 優化 SQL 查詢結構，一次取得所有需要的資料

---

*State updated: 2026-02-04 after completing Phase 39 Plan 01*
