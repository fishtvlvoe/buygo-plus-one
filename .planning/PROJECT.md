# BuyGo Plus One - 賣場後台管理系統

## What This Is

BuyGo+1 是一個 WordPress 外掛，為 LINE 社群賣家提供獨立的後台管理系統。賣家可以透過 LINE 上架商品，在後台管理訂單、出貨、分配和客戶資料。採用 Vue 3 + Tailwind CSS 前端和 PHP + WordPress REST API 後端架構。

## Core Value

讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，**每個賣家只能看到自己的商品和訂單**，同時支援小幫手協作。

## Current Milestone: v3.1 WP 後台完善 + 批量上架

**Goal:** 完善 WP 後台 6 個 Tab 的功能（資料管理、功能管理、開發者事件分類），調整 Tab 順序整合 R2 設定，並實作批量上架 API 後端。

**Target features:**
- R2 Tab 位置調整：Tab 順序整合 R2 圖床（角色權限之後）
- 開發者 Tab 事件分類：分組摺疊式 + error 預設展開 + 分頁
- 資料管理 Tab 前端：查詢表格 + 刪除確認 + 客戶編輯 Modal
- 功能管理 Tab 前端：功能列表 + toggle + 授權碼 UI
- 批量上架 API 後端：POST /products/batch-create 實作

**Previous:**
- v3.0 完成 Portal 前台 SPA 改造、商品成本欄位、儀表板利潤、客戶編輯
- v2.0 完成後台 UI 統一化（6-Tab 結構、角色權限、資料管理/功能管理 API、開發者工具合併）
- v1.0~v1.5 完成核心功能（設計系統、Dashboard、搜尋、多賣家權限、LINE 通知、出貨同步、子訂單顯示、商品限制、FluentCart 自動賦予）

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

<!-- v2.0 已完成的功能 -->

- ✓ 後台 6-Tab 統一化（BGO 選單、admin-tabs.css）— v2.0
- ✓ 角色權限優化（表格過濾、5 大項細粒度權限、搜尋 UX）— v2.0
- ✓ 資料管理 API（訂單/商品/客戶 查詢+刪除+編輯）— v2.0
- ✓ 功能管理 API（Free/Pro 列表、功能 toggle、授權碼）— v2.0
- ✓ 開發者工具合併（流程日誌+資料清除+SQL 查詢）— v2.0
- ✓ 預留 API 骨架（批量上架、多圖、自定義欄位）— v2.0

<!-- v3.0 已完成的功能 -->

- ✓ SPA 改造（useRouter.js、useDataLoader、BuyGoCache、8 頁面適配）— v3.0
- ✓ 商品成本與來源（buygo_product_meta 表、成本/利潤計算）— v3.0
- ✓ 儀表板利潤統計（利潤概覽卡片、Top 5 商品）— v3.0
- ✓ 客戶頁面編輯模式（inline 編輯、buygo_customer_meta 表、FC 同步）— v3.0

### Active - v3.1 Milestone

（詳見 REQUIREMENTS.md v3.1 章節）

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
- **Database**: MySQL (WordPress + FluentCart 資料表) / MariaDB（雲端 InstaWP）
- **Testing**: PHPUnit 9.6（單元測試）

### 現有架構

**已完成的基礎設施：**
- 設計系統（design-system/）
- Service Layer 模式（includes/services/）
- REST API 端點（includes/api/）
- Dashboard 和搜尋功能
- LINE 通知整合（v1.2）
- SPA 路由系統（useRouter.js + RouterMixin）— v3.0
- 商品/客戶自訂欄位系統（buygo_product_meta / buygo_customer_meta）— v3.0

**相關外掛：**
- LineHub (line-hub) — LINE 登入、LIFF、Webhook、訊息發送
- FluentCart — 電商平台（商品、訂單資料）
- BGO R2 (bgo-r2) — R2 圖床設定

**FluentCart 資料結構：**

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

### 權限模型

**賣場識別：** LINE UID + WP user_id 兩者綁定

**權限結構：**
| 身份 | WP 帳號 | LINE 綁定 | 後台存取 | LINE 上架 | LINE 通知 | 管理小幫手 |
|------|---------|-----------|----------|-----------|-----------|------------|
| 賣家（Owner） | 必須 | 必須 | 有 | 有 | 有 | 有 |
| 小幫手（已綁定） | 必須 | 有 | 有 | 有 | 有 | 無 |
| 小幫手（未綁定） | 必須 | 無 | 有 | 無 | 無 | 無 |
| 買家 | 必須 | 有 | 無 | 無 | 有（僅收通知） | 無 |

**隔離規則：**
- A 賣場的人員無法看到 B 賣場的商品/訂單
- 一個用戶可以同時是多個賣場的小幫手
- 賣家 A 被設為賣家 C 的小幫手時，A 可同時看到 A 和 C 的商品

## Constraints

- **零中斷約束**: 現有功能必須繼續運作 — 向後相容
- **FluentCart 依賴**: 商品和訂單資料來自 FluentCart — 必須查詢其歸屬機制
- **LINE 綁定依賴**: 需要 LineHub 外掛提供 LINE 綁定查詢 API — 已有
- **安全約束**: API 權限檢查必須嚴格 — 防止跨賣場存取
- **資料庫升級約束**: 必須透過 upgrade_tables() 機制新增欄位 — 確保平滑升級
- **資料庫相容性**: SQL 語法必須相容 MySQL 8.0+ 和 MariaDB（不用 ANY_VALUE() 等專有函數）

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| v1.1: P0 + P1 合併一個 Milestone | 部署優化快速，權限系統是核心需求 | Good 2026-02-01 |
| 使用 GitHub Releases 更新機制 | 客戶可在 WP 後台收到更新通知 | Good 2026-02-01 |
| 小幫手權限 = 賣家權限（除了管理小幫手） | 簡化權限模型 | Good 2026-02-01 |
| v3.0 Phase 48 跳過 | CDN 方案已足夠，.vue SFC 增加部署複雜度 | Good 2026-02-24 |
| 利潤前端即時計算 | 不存資料庫，避免同步問題 | Good 2026-02-24 |
| SQL 用 MAX() 取代 ANY_VALUE() | MariaDB 相容性 | Good 2026-02-24 |

---
*Last updated: 2026-02-24 after v3.1 milestone start*
