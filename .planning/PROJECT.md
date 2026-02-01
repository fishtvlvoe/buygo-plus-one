# BuyGo Plus One - 賣場後台管理系統

## What This Is

BuyGo+1 是一個 WordPress 外掛，為 LINE 社群賣家提供獨立的後台管理系統。賣家可以透過 LINE 上架商品，在後台管理訂單、出貨、分配和客戶資料。採用 Vue 3 + Tailwind CSS 前端和 PHP + WordPress REST API 後端架構。

## Core Value

讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，**每個賣家只能看到自己的商品和訂單**，同時支援小幫手協作。

## Current Milestone: v1.3 出貨通知與 FluentCart 同步系統

**Goal:** 完善出貨流程，實作 LINE 出貨通知功能，讓買家在商品出貨時收到即時通知

**Target features:**

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
- 客戶可自訂模板內容（變數：商品清單、物流方式、預計送達時間）

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

### Active - v1.3 Milestone

#### 資料模型擴充 (DATA)

- [ ] **DATA-01**: 新增 estimated_delivery_at 欄位到 buygo_shipments 表
- [ ] **DATA-02**: 資料庫升級腳本（Database::upgrade_shipments_table）
- [ ] **DATA-03**: 後台出貨單建立/編輯表單新增「預計送達時間」輸入欄位

#### LINE 出貨通知 (NOTIF)

- [ ] **NOTIF-01**: ShipmentNotificationHandler 監聽出貨單標記為「已出貨」事件
- [ ] **NOTIF-02**: 收集出貨單資訊（商品清單、數量、物流方式、預計送達時間）
- [ ] **NOTIF-03**: 套用出貨通知模板（NotificationTemplates::shipment_shipped）
- [ ] **NOTIF-04**: 透過 NotificationService 發送 LINE 通知給買家
- [ ] **NOTIF-05**: 確保一張出貨單只發送一次通知（即使包含多個子訂單）

#### 通知模板管理 (TMPL)

- [ ] **TMPL-01**: Settings 頁面新增「通知模板」設定區塊
- [ ] **TMPL-02**: 出貨通知模板編輯器（支援變數：{product_list}、{shipping_method}、{estimated_delivery}）
- [ ] **TMPL-03**: 預設出貨通知模板
- [ ] **TMPL-04**: 模板儲存到 wp_options（buygo_notification_template_shipment_shipped）
- [ ] **TMPL-05**: 模板變數替換邏輯

### Out of Scope (v1.3)

- **追蹤編號顯示** — 模板中暫不顯示追蹤編號
- **出貨單編號顯示** — 模板中暫不顯示出貨單編號
- **賣家/小幫手出貨通知** — 僅通知買家，賣家和小幫手不收通知
- **多語系支援** — 僅支援繁體中文
- **進階模板編輯器** — 基礎文字編輯即可，不需要視覺化編輯器

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

**出貨單資料模型（已完成）：**

`buygo_shipments` 表：
```
id, shipment_number, customer_id, seller_id, status,
shipping_method, tracking_number, shipped_at,
created_at, updated_at
```

`buygo_shipment_items` 表：
```
id, shipment_id, order_id, order_item_id,
product_id, quantity, created_at
```

**現有出貨流程：**
1. 賣家建立出貨單（包含多個子訂單的商品）
2. 賣家標記為「已出貨」
3. `ShipmentService::markAsShipped()` 自動更新相關子訂單 `shipping_status = 'shipped'`
4. ❌ 缺少：LINE 通知買家

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
| 預計送達時間由賣家手動輸入 | 簡化邏輯，客戶可彈性填寫 | — Pending |
| 出貨通知僅發給買家 | 賣家/小幫手不需收到出貨確認 | — Pending |
| 一張出貨單 = 一次通知 | 即使包含多個子訂單也只發一次 | — Pending |
| 模板可由客戶自訂 | 提供基礎架構，客戶可客製化內容 | — Pending |

---
*Last updated: 2026-02-02 after v1.3 Milestone initialization*
