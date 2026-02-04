# BuyGo Plus One - 賣場後台管理系統

## What This Is

BuyGo+1 是一個 WordPress 外掛，為 LINE 社群賣家提供獨立的後台管理系統。賣家可以透過 LINE 上架商品，在後台管理訂單、出貨、分配和客戶資料。採用 Vue 3 + Tailwind CSS 前端和 PHP + WordPress REST API 後端架構。

## Core Value

讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，**每個賣家只能看到自己的商品和訂單**，同時支援小幫手協作。

## Current Milestone: v1.5 賣家商品數量限制與 ID 對應系統

**Goal:** 重構賣家管理功能，移除「賣家類型」概念，改用統一的「商品數量限制」機制，並整合 FluentCart 自動賦予權限。

**Target features:**
- 角色權限頁面 UI 改造（顯示 WP ID 和 BuyGo ID）
- 移除「賣家類型」欄位和「發送綁定」按鈕
- 所有賣家可編輯商品限制（預設 3）
- FluentCart 購買指定商品自動賦予 buygo_admin 角色
- 小幫手與賣家共享商品配額驗證

## Requirements

### Validated

<!-- v1.0 已完成的功能 -->

- ✓ 設計系統遷移（表格、卡片、按鈕、狀態標籤、分頁器）— v1.0
- ✓ Dashboard 功能（DashboardService、4 個 API 端點、Chart.js 整合）— v1.0
- ✓ 全域搜尋功能（Search API、搜尋結果頁、Header 搜尋框）— v1.0
- ✓ 快取機制（WordPress Transients、1/5/15 分鐘分層）— v1.0
- ✓ 效能優化（SlowQueryMonitor、DashboardIndexes）— v1.0

<!-- v1.1 已完成的功能 -->

- ✓ GitHub Releases 自動更新機制 — v1.1
- ✓ Rewrite Rules 自動 Flush — v1.1
- ✓ Portal 社群連結按鈕 — v1.1
- ✓ 多賣家權限隔離（wp_buygo_helpers、get_accessible_seller_ids）— v1.1
- ✓ 會員權限管理 UI（Portal Settings）— v1.1
- ✓ 賣家申請系統（Shortcode、自動批准測試賣家）— v1.1
- ✓ WP 後台賣家管理（申請列表、升級功能）— v1.1

<!-- v1.2 已完成的功能 -->

- ✓ 商品上架通知觸發（僅 LINE 上架觸發）— v1.2
- ✓ 通知賣家和所有已綁定 LINE 的小幫手 — v1.2
- ✓ 新訂單通知（賣家 + 小幫手 + 買家）— v1.2
- ✓ 訂單狀態變更通知（僅買家）— v1.2
- ✓ LINE UID → WordPress User ID 查詢 — v1.2
- ✓ 角色判斷（賣家/小幫手/買家/未綁定）— v1.2
- ✓ 賣家/小幫手 bot 回應邏輯 — v1.2
- ✓ 買家僅推播不回應 — v1.2
- ✓ 未綁定用戶靜默忽略 — v1.2
- ✓ 與 buygo-line-notify 整合（WordPress hooks、MessagingService、NotificationTemplates）— v1.2
- ✓ 出貨單資料模型（buygo_shipments、buygo_shipment_items）— v1.2 前
- ✓ 標記出貨時更新子訂單 shipping_status = 'shipped' — v1.2 前

<!-- v1.3 已完成的功能 -->

- ✓ 出貨通知系統（NotificationHandler、buygo/parent_order_completed hook）— v1.3
- ✓ FluentCart 子訂單同步（ChildOrdersSyncService、shipping_status 更新）— v1.3

<!-- v1.4 已完成的功能 -->

- ✓ FluentCart 會員前台子訂單顯示（Hook 整合、ChildOrderService、REST API）— v1.4
- ✓ 子訂單展開/折疊 UI（Vanilla JavaScript）— v1.4

### Active - v1.5 Milestone

#### 角色權限頁面 UI 改造 (UI)

- [ ] **UI-01**: 「使用者」欄位顯示 WordPress User ID
- [ ] **UI-02**: 「角色」欄位顯示 BuyGo ID（小幫手）或「無 BuyGo ID」（賣家）
- [ ] **UI-03**: 完全隱藏「賣家類型」欄位
- [ ] **UI-04**: 移除「發送綁定」按鈕
- [ ] **UI-05**: 「商品限制」欄位全部可編輯（預設值改為 3）

#### FluentCart 自動賦予權限 (FC)

- [ ] **FC-01**: 後台設定「賣家商品 ID」輸入框
- [ ] **FC-02**: 監聽 `fluent_cart/order_paid` hook
- [ ] **FC-03**: 購買指定商品後自動賦予 `buygo_admin` 角色
- [ ] **FC-04**: 自動設定 user meta（`buygo_product_limit` = 3）

#### 小幫手共享配額驗證 (QUOTA)

- [ ] **QUOTA-01**: 小幫手上架商品計入賣家配額
- [ ] **QUOTA-02**: 配額驗證邏輯（賣家 + 所有小幫手總數 <= 限制）
- [ ] **QUOTA-03**: 阻止超限上架

### Out of Scope (v1.5)

- **刪除舊資料** — 保留 `buygo_seller_type` user meta，不刪除也不再寫入
- **後台發送 LINE 綁定** — 完全移除按鈕，用戶自行綁定
- **FluentCart 提前實作** — 如果 UI 改造複雜，FluentCart 整合可延後到 v1.6
- **多層級配額系統** — 維持簡單的單一數字限制，不引入複雜層級

## Context

### 技術環境

- **Framework**: WordPress Plugin
- **Frontend**: Vue 3 + Tailwind CSS（CDN）
- **Backend**: PHP 8.0+ + WordPress REST API
- **Database**: MySQL (WordPress + FluentCart 資料表)
- **Testing**: PHPUnit 9.6（單元測試）

### 現有架構

**已完成的基礎設施：**
- 設計系統（design-system/）
- Service Layer 模式（includes/services/）
- REST API 端點（includes/api/）
- Dashboard 和搜尋功能
- LINE 通知整合（v1.2）

**相關外掛：**
- buygo-line-notify — LINE 登入和 Webhook（v0.2 已完成）
- FluentCart — 電商平台（商品、訂單資料）

**FluentCart 資料結構（v1.4 相關）：**

`wp_fct_orders` 主訂單表：
```
id, invoice_no, customer_id, total_amount, currency,
payment_status, status, created_at, updated_at
```

`wp_fct_child_orders` 子訂單表：
```
id, parent_order_id, seller_id, invoice_no,
total_amount, payment_status, shipping_status,
fulfillment_status, created_at, updated_at
```

`wp_fct_order_items` 訂單商品表：
```
id, order_id, product_id, quantity, price,
created_at, updated_at
```

**已知訂單關係：**
- 1 個主訂單（parent_order） → N 個子訂單（child_orders）
- 每個子訂單對應一個賣家（seller_id）
- 每個子訂單有獨立的商品清單（order_items）
- 每個子訂單有獨立的狀態（付款、出貨、處理）

**目前問題：**
- FluentCart 會員前台訂單頁面僅顯示主訂單
- 購物者無法查看子訂單的詳細資訊
- 需要透過 Hook 注入 UI 元件來展示子訂單

### 權限模型

**賣場識別：** LINE UID + WP user_id 兩者綁定

**權限結構：**
| 身份 | WP 帳號 | LINE 綁定 | 後台存取 | LINE 上架 | LINE 通知 | 管理小幫手 |
|------|---------|-----------|----------|-----------|-----------|------------|
| 賣家（Owner） | ✅ 必須 | ✅ 必須 | ✅ | ✅ | ✅ | ✅ |
| 小幫手（已綁定） | ✅ 必須 | ✅ 有 | ✅ | ✅ | ✅ | ❌ |
| 小幫手（未綁定） | ✅ 必須 | ❌ 無 | ✅ | ❌ | ❌ | ❌ |
| 買家 | ✅ 必須 | ✅ 有 | ❌ | ❌ | ✅（僅收通知） | ❌ |

**隔離規則：**
- A 賣場的人員無法看到 B 賣場的商品/訂單
- 一個用戶可以同時是多個賣場的小幫手
- 賣家 A 被設為賣家 C 的小幫手時，A 可同時看到 A 和 C 的商品

## Constraints

- **零中斷約束**: 現有功能必須繼續運作 — 向後相容
- **FluentCart 依賴**: 商品和訂單資料來自 FluentCart — 必須查詢其歸屬機制
- **LINE 綁定依賴**: 需要 buygo-line-notify 外掛提供 LINE 綁定查詢 API — 已有
- **安全約束**: API 權限檢查必須嚴格 — 防止跨賣場存取
- **資料庫升級約束**: 必須透過 upgrade_tables() 機制新增欄位 — 確保平滑升級

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| v1.1: P0 + P1 合併一個 Milestone | 部署優化快速，權限系統是核心需求 | ✅ Good 2026-02-01 |
| 使用 GitHub Releases 更新機制 | 客戶可在 WP 後台收到更新通知 | ✅ Good 2026-02-01 |
| 小幫手權限 = 賣家權限（除了管理小幫手） | 簡化權限模型 | ✅ Good 2026-02-01 |
| 賣場識別 = LINE UID + WP user_id | 確保賣家已綁定 LINE 才能運作 | ✅ Good 2026-02-01 |
| 小幫手來源 = 任何 WP 用戶 | 靈活選擇 | ✅ Good 2026-02-01 |
| 未綁定 LINE 的小幫手顯示警告 | UX 防呆，避免用戶誤解 | ✅ Good 2026-02-01 |
| LINE 通知只觸發於 LINE 上架 | FluentCart 後台操作不需通知 | ✅ Good 2026-02-01 |
| 未綁定用戶完全靜默忽略 | 不要發送任何訊息給未綁定用戶 | ✅ Good 2026-02-01 |
| 買家只收推播不回應 bot 訊息 | 買家身份明確區分於賣家/小幫手 | ✅ Good 2026-02-01 |
| 預計送達時間由賣家手動輸入 | 簡化邏輯，客戶可彈性填寫 | — Pending (v1.3) |
| 出貨通知僅發給買家 | 賣家/小幫手不需收到出貨確認 | — Pending (v1.3) |
| 一張出貨單 = 一次通知 | 即使包含多個子訂單也只發一次 | — Pending (v1.3) |
| 模板可由客戶自訂 | 提供基礎架構，客戶可客製化內容 | — Pending (v1.3) |
| 子訂單顯示僅做購物者前台 | 賣家後台目前不需要，避免過度開發 | ✅ Good (v1.4) |
| 使用 Hook 整合而非修改 FluentCart | 確保升級相容性，降低維護成本 | ✅ Good (v1.4) |
| 展開/折疊 UI 交互 | 減少頁面初始載入量，提升 UX | ✅ Good (v1.4) |
| 保留但隱藏 buygo_seller_type | 避免資料遷移風險，未來可能需要參考 | — Pending (v1.5) |
| 商品限制預設從 2 改為 3 | 用戶反饋認為 2 個太少 | — Pending (v1.5) |
| 移除「發送綁定」按鈕 | 簡化 UI，用戶自行處理綁定 | — Pending (v1.5) |
| FluentCart 整合為中優先級 | 先完成 UI 改造，整合可延後 | — Pending (v1.5) |
| 小幫手配額必須在 v1.5 完成 | 核心功能，防止超配是高優先 | — Pending (v1.5) |

---
*Last updated: 2026-02-04 after v1.5 Milestone initialization*
