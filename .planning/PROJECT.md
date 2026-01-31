# BuyGo Plus One - 賣場後台管理系統

## What This Is

BuyGo+1 是一個 WordPress 外掛，為 LINE 社群賣家提供獨立的後台管理系統。賣家可以透過 LINE 上架商品，在後台管理訂單、出貨、分配和客戶資料。採用 Vue 3 + Tailwind CSS 前端和 PHP + WordPress REST API 後端架構。

## Core Value

讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，**每個賣家只能看到自己的商品和訂單**，同時支援小幫手協作。

## Current Milestone: v1.2 LINE 通知觸發機制整合

**Goal:** 在 buygo-plus-one 中實作商品上架和訂單通知的觸發邏輯，與 buygo-line-notify 串接

**Target features:**

### 商品上架通知
- 透過 LINE 上架商品時，通知賣家和所有已綁定 LINE 的小幫手
- FluentCart 後台操作不觸發通知（僅 LINE 上架觸發）

### 訂單通知
- 新訂單建立 → 通知賣家 + 小幫手 + 買家（如果買家有 LINE 綁定）
- 訂單狀態變更 → 僅通知買家

### 身份識別與回應邏輯
- LINE UID → WordPress User ID → 角色判斷
- 賣家/小幫手：可與 bot 互動，收到通知
- 買家：只收推播，bot 不回應其訊息
- 未綁定用戶：bot 完全不回應（靜默忽略）

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

### Active - v1.2 Milestone

#### LINE 通知觸發 (NOTIF)

- [ ] **NOTIF-01**: 商品上架通知觸發（僅 LINE 上架觸發，FluentCart 後台不觸發）
- [ ] **NOTIF-02**: 通知賣家和所有已綁定 LINE 的小幫手
- [ ] **NOTIF-03**: 新訂單通知（賣家 + 小幫手 + 買家）
- [ ] **NOTIF-04**: 訂單狀態變更通知（僅買家）

#### 身份識別與回應 (IDENT)

- [ ] **IDENT-01**: LINE UID → WordPress User ID 查詢
- [ ] **IDENT-02**: 角色判斷（賣家/小幫手/買家/未綁定）
- [ ] **IDENT-03**: 賣家/小幫手 bot 回應邏輯
- [ ] **IDENT-04**: 買家僅推播不回應
- [ ] **IDENT-05**: 未綁定用戶靜默忽略

#### 與 buygo-line-notify 整合 (INTEG)

- [ ] **INTEG-01**: 監聽 buygo-line-notify 的 WordPress hooks
- [ ] **INTEG-02**: 使用 MessagingService 發送推播
- [ ] **INTEG-03**: 移植 NotificationTemplates 模板系統

### Out of Scope (v1.2)

- **多樣式商品功能** — 延後到 v1.3
- **FluentCommunity 側邊欄連結** — 可選功能
- **資料遷移** — 不需要

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

**相關外掛：**
- buygo-line-notify — LINE 登入和 Webhook（v0.2 已完成）
- FluentCart — 電商平台（商品、訂單資料）

### 權限模型

**賣場識別：** LINE UID + WP user_id 兩者綁定

**權限結構：**
| 身份 | WP 帳號 | LINE 綁定 | 後台存取 | LINE 上架 | LINE 通知 | 管理小幫手 |
|------|---------|-----------|----------|-----------|-----------|------------|
| 賣家（Owner） | ✅ 必須 | ✅ 必須 | ✅ | ✅ | ✅ | ✅ |
| 小幫手（已綁定） | ✅ 必須 | ✅ 有 | ✅ | ✅ | ✅ | ❌ |
| 小幫手（未綁定） | ✅ 必須 | ❌ 無 | ✅ | ❌ | ❌ | ❌ |

**隔離規則：**
- A 賣場的人員無法看到 B 賣場的商品/訂單
- 一個用戶可以同時是多個賣場的小幫手
- 賣家 A 被設為賣家 C 的小幫手時，A 可同時看到 A 和 C 的商品

### 小幫手管理流程

1. 賣家在 Settings 頁面新增小幫手（從 WP 用戶選擇）
2. 小幫手登入後，可看到該賣家的商品
3. 若小幫手未綁定 LINE，顯示警告並提供綁定按鈕
4. 小幫手不能新增/移除其他小幫手

## Constraints

- **零中斷約束**: 現有功能必須繼續運作 — 向後相容
- **FluentCart 依賴**: 商品和訂單資料來自 FluentCart — 必須查詢其歸屬機制
- **LINE 綁定依賴**: 需要 buygo-line-notify 外掛提供 LINE 綁定查詢 API — 已有
- **安全約束**: API 權限檢查必須嚴格 — 防止跨賣場存取

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| v1.1: P0 + P1 合併一個 Milestone | 部署優化快速，權限系統是核心需求 | ✅ Good 2026-02-01 |
| 使用 GitHub Releases 更新機制 | 客戶可在 WP 後台收到更新通知 | ✅ Good 2026-02-01 |
| 小幫手權限 = 賣家權限（除了管理小幫手） | 簡化權限模型 | ✅ Good 2026-02-01 |
| 賣場識別 = LINE UID + WP user_id | 確保賣家已綁定 LINE 才能運作 | ✅ Good 2026-02-01 |
| 小幫手來源 = 任何 WP 用戶 | 靈活選擇 | ✅ Good 2026-02-01 |
| 未綁定 LINE 的小幫手顯示警告 | UX 防呆，避免用戶誤解 | ✅ Good 2026-02-01 |
| LINE 通知只觸發於 LINE 上架 | FluentCart 後台操作不需通知 | — Pending |
| 未綁定用戶完全靜默忽略 | 不要發送任何訊息給未綁定用戶 | — Pending |
| 買家只收推播不回應 bot 訊息 | 買家身份明確區分於賣家/小幫手 | — Pending |

---
*Last updated: 2026-02-01 after v1.2 Milestone initialization*
