# BuyGo+1 UI 統一計畫

> **計畫開始日期**：2026-01-24
> **預計完成日期**：2026-01-31（下週一前）
> **參考標準**：[UI-UX-GOLDEN-PRINCIPLES.md](../development/UI-UX-GOLDEN-PRINCIPLES.md)
> **目標**：確保全站 UI/UX 一致性，為 P2 多樣式產品功能做準備

---

## 📋 概述

根據黃金設計原則，目前需要統一的頁面包括：

| 頁面 | 完成度 | 優先級 | 預估時間 |
|------|--------|--------|----------|
| 商品（products.php） | 90% | 低 | 1 小時 |
| 訂單（orders.php） | 80% | 低 | 1.5 小時 |
| 備貨（shipment-products.php） | 60% | 高 | 2 小時 |
| 出貨（shipment-details.php） | 50% | 高 | 2 小時 |
| 客戶（customers.php） | 60% | 中 | 1.5 小時 |

**總預估時間**：8 小時

---

## 🎯 統一重點

根據黃金設計原則（見參考檔案），以下是必須統一的核心項目：

### 1️⃣ Header 設計規範（所有頁面）

**檢查項目**：
- [ ] 高度固定 64px（`h-16`）
- [ ] 白色背景 + 淺灰分隔線
- [ ] 頁面標題使用 `text-xl font-bold`
- [ ] 全域搜尋框（桌面版顯示，手機版點擊彈出）
- [ ] 通知圖標（24px × 24px）
- [ ] 幣別切換按鈕（僅商品、訂單、客戶頁面）
- [ ] **新增**：前往 Portal 按鈕（已完成 ✅）

**相關頁面**：
- [x] products.php ✓
- [x] orders.php ✓
- [ ] shipment-products.php
- [ ] shipment-details.php
- [ ] customers.php

---

### 2️⃣ Smart Search Box 規範（所有頁面）

**檢查項目**：
- [ ] API endpoint 正確
- [ ] Search fields 包含所有可搜尋欄位
- [ ] Placeholder 文字清晰易懂
- [ ] 事件處理器 @select, @search, @clear 正確綁定
- [ ] Currency toggle 根據頁面需求設定

**配置標準**：

| 頁面 | API Endpoint | Search Fields | Placeholder |
|------|--------------|---------------|-------------|
| 商品 | `/products` | name, sku | 搜尋商品名稱或 SKU... |
| 訂單 | `/orders` | invoice_no, customer_name, customer_email | 搜尋訂單編號、客戶名稱或 Email... |
| 備貨 | `/shipments` | shipment_number, customer_name, product_name | 搜尋出貨單號、客戶或商品... |
| 出貨 | `/shipments` | shipment_number, customer_name | 搜尋出貨單號或客戶... |
| 客戶 | `/customers` | name, email, phone | 搜尋客戶名稱、Email 或電話... |

---

### 3️⃣ 表格 vs 卡片設計（桌面 vs 手機）

**桌面版表格**（≥ 768px）：
- [ ] 使用 `hidden md:block` 條件
- [ ] 表頭背景 `bg-slate-50`
- [ ] 表頭文字 `text-xs font-semibold uppercase`
- [ ] 分隔線 `divide-y divide-slate-200`
- [ ] 支援橫向捲動（`overflow-x-auto`）
- [ ] **黃金規則**：表格必須等比例縮放，不出現直向排列

**手機版卡片**（< 768px）：
- [ ] 使用 `md:hidden` 條件
- [ ] 卡片間距 `space-y-4`
- [ ] 內邊距 `p-4`
- [ ] Hover 陰影效果 `hover:shadow-md`
- [ ] 操作按鈕 `grid grid-cols-2`
- [ ] 展開功能正常（若有）

---

### 4️⃣ 狀態標籤規範（所有頁面）

**必須改正**：
- [ ] 所有狀態使用 `<span>` 而非 `<button>`
- [ ] 字體大小 `text-xs`
- [ ] 內邊距 `px-2 py-1`
- [ ] 圓角 `rounded-full`
- [ ] 必須有邊框（`border`）

**顏色標準**：

| 狀態 | 背景 | 文字 | 邊框 | 適用場景 |
|------|------|------|------|----------|
| 處理中 / 備貨中 | `bg-yellow-100` | `text-yellow-800` | `border-yellow-200` | 進行中 |
| 已完成 / 已出貨 | `bg-green-100` | `text-green-800` | `border-green-200` | 完成 |
| 已取消 | `bg-red-100` | `text-red-800` | `border-red-200` | 取消 |
| 保留 / On Hold | `bg-blue-100` | `text-blue-800` | `border-blue-200` | 保留中 |

---

### 5️⃣ 文字與語言規範（所有頁面）

**必須改正**：
- [ ] 所有用戶可見文字使用**中文**（無英文混雜）
- [ ] 狀態標籤中文化（On Hold → 處理中 / 保留）
- [ ] 按鈕文字中文化（View → 查看，Edit → 編輯，Delete → 刪除）
- [ ] 所有文字**必須橫排**（禁止直向排列）
- [ ] Toast 訊息全部中文化

**常見需要改正的詞彙**：

| ❌ 英文 | ✅ 中文 |
|--------|--------|
| View | 查看 |
| Edit | 編輯 |
| Delete | 刪除 |
| On Hold | 處理中 / 保留 |
| Pending | 備貨中 |
| Shipped | 已出貨 |
| Published | 已上架 |
| Draft | 已下架 |

---

### 6️⃣ 顏色與間距規範（所有頁面）

**主色系**：
- 主要色：`text-primary` (#3B82F6)
- 成功：`text-green-600`
- 警告：`text-yellow-600`
- 錯誤：`text-red-600`

**灰階**：
- 標題：`text-slate-900`
- 內文：`text-slate-600`
- 提示：`text-slate-400`
- 背景：`bg-slate-50`
- 分隔線：`border-slate-200`

**間距標準**：
- 超小：`gap-1` (4px)
- 小：`gap-2` (8px)
- 標準：`gap-3` (12px)
- 大：`gap-4` (16px)
- 區塊：`gap-6` (24px)

---

## 📐 分頁優化計畫

### Phase 1：快速修復（高優先級）

#### A. 備貨頁面（shipment-products.php）
**工作量**：2 小時

**待改正項目**：
- [ ] 添加完整 Header（含全域搜尋）
- [ ] 添加 Smart Search Box
- [ ] 狀態標籤改為 Tag 樣式（非按鈕）
- [ ] 中文化所有英文標籤
- [ ] 優化響應式設計

**檢查清單**：參考[UI-UX-GOLDEN-PRINCIPLES.md](../development/UI-UX-GOLDEN-PRINCIPLES.md)的 Smart Search Box 規範（Line 132-169）

---

#### B. 出貨頁面（shipment-details.php）
**工作量**：2 小時

**待改正項目**：
- [ ] Header 結構檢查
- [ ] 狀態標籤規範化
- [ ] 中文化標籤
- [ ] 響應式設計優化
- [ ] 表格 vs 卡片結構檢查

---

#### C. 客戶頁面（customers.php）
**工作量**：1.5 小時

**待改正項目**：
- [ ] Header 完整性檢查
- [ ] Smart Search Box 配置
- [ ] 狀態標籤規範化
- [ ] 中文化標籤
- [ ] 響應式設計

---

### Phase 2：微調（低優先級）

#### A. 商品頁面微調（products.php）
**工作量**：1 小時

**待改正項目**：
- [ ] 檢查所有標籤是否中文化
- [ ] 檢查狀態標籤顏色是否正確
- [ ] 驗證表格/卡片一致性

---

#### B. 訂單頁面微調（orders.php）
**工作量**：1.5 小時

**待改正項目**：
- [ ] 中文化剩餘英文標籤
- [ ] 詳情頁資訊補完
- [ ] 狀態標籤中文化
- [ ] 驗證表格/卡片一致性

---

## ✅ 完成標準

每個頁面改完後需要檢查以下項目：

### Header 檢查
- [ ] 高度 64px
- [ ] 標題格式正確
- [ ] 全域搜尋顯示正確
- [ ] 通知圖標正確
- [ ] 幣別切換（若需要）

### Smart Search Box 檢查
- [ ] 功能正常
- [ ] Placeholder 清晰
- [ ] 搜尋欄位完整
- [ ] 事件綁定正確

### 表格 vs 卡片檢查
- [ ] 桌面版顯示表格
- [ ] 手機版顯示卡片
- [ ] 表格等比例縮放（無直向排列）
- [ ] 卡片內容一致

### 狀態標籤檢查
- [ ] 所有標籤改為 `<span>`（非 `<button>`）
- [ ] 顏色符合標準
- [ ] 有邊框
- [ ] 字體大小正確

### 語言檢查
- [ ] 所有用戶文字中文化
- [ ] 狀態標籤全中文
- [ ] 按鈕文字全中文
- [ ] 無直向排列

### 響應式檢查
- [ ] 手機版正常
- [ ] 桌面版正常
- [ ] 視窗縮放無割裂感
- [ ] 表格無直向排列

---

## 📅 工作時程

| 日期 | 任務 | 預估時間 |
|------|------|----------|
| 1/24 (今) | 計畫製定 + P1-A 開始 | 0.5 小時 |
| 1/25-26 | P1-A 實作 + Phase 1A (備貨頁) | 4 小時 |
| 1/27-28 | Phase 1B (出貨頁) + 1C (客戶頁) | 4 小時 |
| 1/29-30 | Phase 2 微調 + 測試 | 2.5 小時 |
| 1/31 | 最終檢查 + 提交 | 1 小時 |

**總計**：~12 小時

---

## 🔗 相關參考

- [UI-UX-GOLDEN-PRINCIPLES.md](../development/UI-UX-GOLDEN-PRINCIPLES.md) - 完整設計規範
- [PROGRESS-TRACKER.md](PROGRESS-TRACKER.md) - 進度追蹤
- [PRE-COMMIT-HOOK-REPORT.md](../development/PRE-COMMIT-HOOK-REPORT.md) - Hook 自動化檢查

---

## 🚀 開始前準備

1. ✅ 複製黃金設計原則文檔到 docs/development/
2. ✅ 建立 UI 統一計畫
3. ⏳ 實作 P1-A（GitHub 自動更新）
4. ⏳ 按照計畫逐頁統一 UI

**準備好了嗎？** 建議先完成 P1-A，再開始 UI 統一工作。

---

**建立日期**：2026-01-24 by Claude Haiku 4.5
