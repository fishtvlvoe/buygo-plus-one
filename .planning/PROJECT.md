# BuyGo Plus One - 賣場後台管理系統

## What This Is

BuyGo+1 是一個 WordPress 外掛，為 LINE 社群賣家提供獨立的後台管理系統。賣家可以透過 LINE 上架商品，在後台管理訂單、出貨、分配和客戶資料。採用 Vue 3 + Tailwind CSS 前端和 PHP + WordPress REST API 後端架構。

## Core Value

讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，**每個賣家只能看到自己的商品和訂單**，同時支援小幫手協作。

## Current Milestone: v2.0 後台 UI 統一化

**Goal:** 重構 BGO 後台 UI，與 LineHub 設計語言統一，精簡 Tab 結構（6-Tab），新增細粒度權限、資料管理和 Pro 版授權機制。

**Target features:**
- 選單 BuyGo+1 → BGO，取消子選單，改為單頁 6-Tab 導航（仿 LineHub 模式）
- 角色權限重構：表格只顯示 BGO 角色 + 5 大項細粒度權限 + 搜尋 UX 改進
- 資料管理 Tab：訂單/商品/客戶按日期篩選+編輯+刪除（二次確認）
- 功能管理 Tab：Free/Pro 功能列表 + 授權碼驗證
- 開發者工具合併：流程日誌 + 資料清除 + SQL 查詢
- 預留 API 端點：批量上架、多圖上傳、自定義欄位（骨架）

**Previous:** v1.0~v1.5 完成核心功能（設計系統、Dashboard、搜尋、多賣家權限、LINE 通知、出貨同步、子訂單顯示、商品限制、FluentCart 自動賦予）。

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

### Active - v2.0 Milestone

（需求定義中，將建立 REQUIREMENTS.md）

<details>
<summary>v1.5 已驗證的功能</summary>

- ✓ 角色權限 UI（WP ID + BuyGo ID 顯示、隱藏賣家類型）— v1.5
- ✓ FluentCart 購買自動賦予賣家角色 — v1.5
- ✓ 退款自動撤銷賣家角色 — v1.5
- ✓ 商品限制可編輯（預設 3，0=無限制）— v1.5
- ✓ LINE + Email 賣家權限通知 — v1.5
</details>

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
| 小幫手配額取消 | 單站情境不需要共享配額，v2.0 會重構權限系統 | ❌ Cancelled (v1.5) |
| 後台 UI 統一化 | BGO 和 LineHub 設計語言統一，Tab 結構重整 | — Pending (v2.0) |

---
*Last updated: 2026-02-20 after v2.0 milestone start*
