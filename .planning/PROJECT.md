# BuyGo Plus One - 賣場後台管理系統

## What This Is

BuyGo+1 是一個 WordPress 外掛，為 LINE 社群賣家提供獨立的後台管理系統。賣家可以透過 LINE 上架商品，在後台管理訂單、出貨、分配和客戶資料。採用 Vue 3 + Tailwind CSS 前端和 PHP + WordPress REST API 後端架構。

## Core Value

讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，**每個賣家只能看到自己的商品和訂單**，同時支援小幫手協作。

## Current Milestone: v1.4 會員前台子訂單顯示功能

**Goal:** 在 FluentCart 會員前台訂單頁面中，讓購物者能查看該主訂單包含的所有子訂單詳細資訊

**Target features:**

### 子訂單顯示區塊
- 在主訂單下方新增「查看子訂單」展開按鈕
- 展開後顯示該主訂單的所有子訂單列表
- 每個子訂單顯示：編號、商品清單、狀態、金額

### UI 整合方式
- 使用 WordPress Hook 注入 UI（不修改 FluentCart 原始碼）
- 類似 LINE 登入按鈕的整合模式
- 折疊/展開交互功能

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

### Active - v1.4 Milestone

#### FluentCart 訂單頁面整合 (INTEG)

- [ ] **INTEG-01**: 找出 FluentCart 會員訂單詳情頁的 Hook 點
- [ ] **INTEG-02**: 透過 Hook 在主訂單下方注入「查看子訂單」按鈕
- [ ] **INTEG-03**: 注入子訂單列表容器（初始隱藏）

#### 子訂單查詢服務 (QUERY)

- [ ] **QUERY-01**: ChildOrderService 查詢指定主訂單的所有子訂單
- [ ] **QUERY-02**: 查詢每個子訂單的商品清單（從 order_items 表）
- [ ] **QUERY-03**: 整合子訂單狀態資訊（付款、出貨、處理狀態）

#### REST API 端點 (API)

- [ ] **API-01**: GET /child-orders/{parent_order_id} 端點
- [ ] **API-02**: 權限驗證（僅訂單所屬顧客可查詢）
- [ ] **API-03**: 回傳格式化的子訂單資料（含商品、狀態、金額）

#### 前端 UI 元件 (UI)

- [ ] **UI-01**: 「查看子訂單」按鈕樣式（使用 BuyGo+1 設計系統）
- [ ] **UI-02**: 子訂單列表卡片樣式
- [ ] **UI-03**: 折疊/展開交互邏輯（Vue 3 或原生 JS）
- [ ] **UI-04**: 狀態標籤顯示（使用 .status-tag 元件）
- [ ] **UI-05**: RWD 響應式設計

### Out of Scope (v1.4)

- **賣家後台子訂單顯示** — 目前僅做購物者前台，賣家後台未來再考慮
- **子訂單編輯功能** — 僅顯示，不提供編輯
- **子訂單搜尋/篩選** — 單純列表顯示即可
- **子訂單匯出功能** — 目前不需要
- **FluentCart 原始碼修改** — 必須透過 Hook 整合

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
| 子訂單顯示僅做購物者前台 | 賣家後台目前不需要，避免過度開發 | — Pending (v1.4) |
| 使用 Hook 整合而非修改 FluentCart | 確保升級相容性，降低維護成本 | — Pending (v1.4) |
| 展開/折疊 UI 交互 | 減少頁面初始載入量，提升 UX | — Pending (v1.4) |

---
*Last updated: 2026-02-02 after v1.4 Milestone initialization*
