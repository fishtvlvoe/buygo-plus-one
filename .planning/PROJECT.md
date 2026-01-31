# BuyGo Plus One - 賣場後台管理系統

## What This Is

BuyGo+1 是一個 WordPress 外掛，為 LINE 社群賣家提供獨立的後台管理系統。賣家可以透過 LINE 上架商品，在後台管理訂單、出貨、分配和客戶資料。採用 Vue 3 + Tailwind CSS 前端和 PHP + WordPress REST API 後端架構。

## Core Value

讓 LINE 社群賣家能夠在一個統一的後台管理所有銷售活動，**每個賣家只能看到自己的商品和訂單**，同時支援小幫手協作。

## Current Milestone: v1.1 部署優化與會員權限

**Goal:** 完成部署優化（GitHub 自動更新、永久連結 flush、Portal 按鈕）和多賣家權限隔離系統

**Target features:**

### P0 部署優化
- GitHub Releases 自動更新機制（客戶 WP 後台可收到更新通知）
- 外掛啟用時自動 flush rewrite rules
- 後台新增「前往 BuyGo Portal」快捷按鈕

### P1 會員權限管理系統
- `wp_buygo_helpers` 資料表（多賣家隔離）
- Settings_Service 擴充（get_helpers, add_helper, remove_helper）
- 商品歸屬機制（需查詢 FluentCart 是否有 owner 欄位）
- Settings 頁面「會員權限管理」UI
- 小幫手 LINE 綁定狀態顯示（已綁定/未綁定）
- 小幫手 LINE 綁定按鈕（未綁定時可點擊綁定）

## Requirements

### Validated

<!-- v1.0 已完成的功能 -->

- ✓ 設計系統遷移（表格、卡片、按鈕、狀態標籤、分頁器）— v1.0
- ✓ Dashboard 功能（DashboardService、4 個 API 端點、Chart.js 整合）— v1.0
- ✓ 全域搜尋功能（Search API、搜尋結果頁、Header 搜尋框）— v1.0
- ✓ 快取機制（WordPress Transients、1/5/15 分鐘分層）— v1.0
- ✓ 效能優化（SlowQueryMonitor、DashboardIndexes）— v1.0

### Active - v1.1 Milestone

#### 部署優化 (P0)

- [ ] **DEPLOY-01**: GitHub Releases 自動更新機制
- [ ] **DEPLOY-02**: 外掛啟用時自動 flush rewrite rules
- [ ] **DEPLOY-03**: 後台新增「前往 BuyGo Portal」按鈕

#### 會員權限管理 (P1)

##### 資料架構

- [ ] **PERM-01**: 建立 `wp_buygo_helpers` 資料表（id, user_id, seller_id, created_at, updated_at）
- [ ] **PERM-02**: 查詢並確認 FluentCart 商品歸屬機制（是否有 owner 欄位）
- [ ] **PERM-03**: 若 FluentCart 無 owner 欄位，需建立商品歸屬資料表或欄位

##### Service Layer

- [ ] **PERM-04**: Settings_Service.get_helpers(seller_id) — 取得特定賣家的小幫手
- [ ] **PERM-05**: Settings_Service.add_helper(user_id, seller_id) — 新增小幫手
- [ ] **PERM-06**: Settings_Service.remove_helper(user_id, seller_id) — 移除小幫手
- [ ] **PERM-07**: 權限檢查整合 — 判斷用戶可存取哪些賣場的商品

##### API Layer

- [ ] **API-01**: GET /settings/helpers — 取得當前賣家的小幫手列表
- [ ] **API-02**: POST /settings/helpers — 新增小幫手（只有賣家可以，小幫手不行）
- [ ] **API-03**: DELETE /settings/helpers/{user_id} — 移除小幫手（只有賣家可以）
- [ ] **API-04**: 所有商品/訂單 API 加入賣場權限過濾

##### 前端 UI

- [ ] **UI-01**: Settings 頁面「會員權限管理」區塊
- [ ] **UI-02**: 小幫手列表（姓名、Email、LINE 綁定狀態、新增時間）
- [ ] **UI-03**: 新增小幫手功能（從 WP 用戶選擇）
- [ ] **UI-04**: 移除小幫手功能
- [ ] **UI-05**: LINE 綁定狀態顯示（✅ 已綁定 / ⚠️ 未綁定警告）
- [ ] **UI-06**: LINE 綁定按鈕（小幫手登入後，未綁定時顯示按鈕）
- [ ] **UI-07**: 小幫手不顯示「會員權限管理」區塊

### Out of Scope (v1.1)

- **多樣式商品功能** — 延後到 v1.2
- **LINE 通知功能** — 延後到 v1.2（需先完成 buygo-line-notify v0.3）
- **FluentCommunity 側邊欄連結** — 可選功能，看時間決定是否加入
- **資料遷移** — 現有商品會清空，不需遷移

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
| v1.1: P0 + P1 合併一個 Milestone | 部署優化快速，權限系統是核心需求 | ✅ Confirmed 2026-01-31 |
| 使用 GitHub Releases 更新機制 | 客戶可在 WP 後台收到更新通知 | ✅ Confirmed 2026-01-31 |
| 小幫手權限 = 賣家權限（除了管理小幫手） | 簡化權限模型 | ✅ Confirmed 2026-01-31 |
| 賣場識別 = LINE UID + WP user_id | 確保賣家已綁定 LINE 才能運作 | ✅ Confirmed 2026-01-31 |
| 小幫手來源 = 任何 WP 用戶 | 靈活選擇 | ✅ Confirmed 2026-01-31 |
| 未綁定 LINE 的小幫手顯示警告 | UX 防呆，避免用戶誤解 | ✅ Confirmed 2026-01-31 |

---
*Last updated: 2026-01-31 after v1.1 Milestone initialization*
