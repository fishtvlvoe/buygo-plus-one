# BuyGo 熵減執行計畫

> 目標：所有檔案 < 300 行（理想）、< 500 行（上限）
> 原則：HTML 模板與 JS 邏輯分離、按功能拆分、PHP 零內嵌腳本

## 現況分析

### 檔案載入架構（已確認）

Portal 頁面的 CSS/JS 用 `<?php include ?>` 方式載入：
```php
<style><?php include plugin_dir_path(dirname(__FILE__)) . 'css/orders.css'; ?></style>
<script><?php include plugin_dir_path(dirname(__FILE__)) . 'js/components/OrdersPage.js'; ?></script>
```

這是 PHP inline include — 伺服器端把檔案內容直接嵌入 HTML 回應。
**不是** `<script src="">` 外部載入，也不是因為 WAF 才這樣做。
部署權限問題（unzip 產生 700 權限）已在 build-release.sh 中用 `chmod 755` 修正。
inline include 的好處是減少 HTTP 請求、在任何環境都能運作，作為雙重保險。

### PHP 檔案現況

| 檔案 | 行數 | JS 已分離？ | 內嵌 JS 行數 | 主要問題 |
|------|------|------------|-------------|---------|
| settings.php | 2569 | ❌ | ~1500 | HTML + 大量內嵌 JS，最嚴重 |
| products.php | 941 | ✅ (ProductsPage.js) | ~23 | HTML 模板過長 |
| orders.php | 908 | ✅ (OrdersPage.js) | ~2 | HTML 模板過長 |
| shipment-details.php | 822 | ✅ (ShipmentDetailsPage.js) | ~2 | HTML 模板過長 |
| dashboard.php | 588 | ❌ | ~420 | JS 完全內嵌，沒有獨立 JS 檔 |
| search.php | 528 | ❌ | ~210 | JS 完全內嵌 |
| customers.php | 451 | ✅ (CustomersPage.js) | ~2 | HTML 模板略長但接近上限 |
| shipment-products.php | 430 | ✅ (ShipmentProductsPage.js) | ~2 | HTML 模板略長但接近上限 |

### JS 檔案現況

| 檔案 | 行數 | 位置 | 問題 |
|------|------|------|------|
| OrdersPage.js | 1418 | admin/js/components/ | 單一檔案過大 |
| ProductsPage.js | 1130 | admin/js/components/ | 單一檔案過大 |
| ShipmentDetailsPage.js | 899 | admin/js/components/ | 單一檔案過大 |
| DesignSystem.js | 827 | admin/js/ | 共用元件過大 |
| admin-settings.js | 679 | admin/js/ | JS 過大 |
| CustomersPage.js | 607 | admin/js/components/ | 超過 500 上限 |
| ShipmentProductsPage.js | 606 | admin/js/components/ | 超過 500 上限 |

---

## 執行策略：分三波進行

### 🌊 Wave 1：PHP 檔案 — 內嵌 JS 提取（不影響邀請系統）

**目標**：把 dashboard.php 和 search.php 的內嵌 JS 提取為獨立檔案

已分離 JS 的 PHP 檔案（orders/products/shipment-details/customers/shipment-products）
的主要問題是 HTML 模板過長，但不含業務邏輯，優先級較低。

#### Step 1-1：dashboard.php — 內嵌 JS 提取
- `admin/partials/dashboard.php`（588 行，~420 行內嵌 JS）
- 提取 JS → `admin/js/components/DashboardPage.js`
- dashboard.php 只留 HTML 模板 + `<?php include ?>`
- 預期結果：dashboard.php ~170 行、DashboardPage.js ~420 行
- ✅ 驗證：Portal 首頁圖表、數據載入正常

#### Step 1-2：search.php — 內嵌 JS 提取
- `admin/partials/search.php`（528 行，~210 行內嵌 JS）
- 提取 JS → `admin/js/components/SearchPage.js`
- search.php 只留 HTML 模板 + `<?php include ?>`
- 預期結果：search.php ~320 行、SearchPage.js ~210 行
- ✅ 驗證：Portal 搜尋頁功能正常

---

### 🌊 Wave 2：大型 JS 檔案拆分（不影響邀請系統）

**目標**：把超過 500 行的 JS 檔案按功能邊界拆分

#### Step 2-1：OrdersPage.js 拆分（1418 行）
- 按功能邊界拆成：
  - `OrdersPage.js`（~200 行）— 元件定義 + setup + 生命週期
  - `orders-list.js`（~400 行）— 列表渲染、篩選、分頁邏輯
  - `orders-detail.js`（~400 行）— 詳情 Modal、狀態變更操作
  - `orders-export.js`（~200 行）— 匯出功能（如有）
- 載入方式：OrdersPage.js 在頂部用全域變數或 IIFE 引入拆出的模組
- ✅ 驗證：Portal 訂單頁所有功能正常

#### Step 2-2：ProductsPage.js 拆分（1130 行）
- 同理按功能邊界拆分
- ✅ 驗證：Portal 商品頁所有功能正常

#### Step 2-3：ShipmentDetailsPage.js 拆分（899 行）
- 同理按功能邊界拆分
- ✅ 驗證：Portal 出貨詳情頁所有功能正常

#### Step 2-4：DesignSystem.js 拆分（827 行）
- 按元件類型拆：
  - `DesignSystem.js`（~150 行）— 入口 + 全域註冊
  - `ds-form-components.js`（~250 行）— 表單元件（輸入框、選擇器）
  - `ds-layout-components.js`（~200 行）— 佈局元件（卡片、表格）
  - `ds-feedback-components.js`（~200 行）— 提示、通知、Modal
- ✅ 驗證：所有頁面的共用元件正常

#### Step 2-5：中型 JS 檔案拆分
- `admin-settings.js`（679 行）— 拆分待分析具體結構
- `CustomersPage.js`（607 行）— 拆分待分析
- `ShipmentProductsPage.js`（606 行）— 拆分待分析
- ✅ 驗證：各頁面功能正常

---

### 🌊 Wave 3：settings.php 拆分（邀請系統 merge 後）

**前置條件**：邀請連結系統已通過端對端測試並 merge 到 main

#### Step 3-1：settings.php HTML 模板拆分
- `admin/partials/settings.php`（2569 行）→ 拆成：
  - `settings.php`（~80 行）— 只做載入骨架 + `<?php include ?>`
  - `settings-helpers.php`（~360 行）— 幫手管理區塊的 HTML 模板
  - `settings-templates.php`（~560 行）— 通知模板管理的 HTML 模板
  - `settings-keywords-modal.php`（~80 行）— 關鍵字 Modal
- ✅ 驗證：Portal 設定頁所有功能正常

#### Step 3-2：settings.php 內嵌 JS 提取
- 內嵌 JS（~1500 行）→ 拆成獨立檔案：
  - `admin/js/settings-helpers.js`（~500 行）— 幫手管理邏輯
  - `admin/js/settings-templates.js`（~600 行）— 通知模板邏輯
  - `admin/js/settings-keywords.js`（~400 行）— 關鍵字邏輯
- settings.php 用 `<?php include ?>` 載入
- ✅ 驗證：Portal 設定頁所有功能正常

#### Step 3-3：settings JS 二次拆分（如超過 500 行）
- 視 Step 3-2 結果，進一步拆分超過 500 行的 JS 檔案
- ✅ 驗證：所有功能正常

---

### 🧹 Wave 1 額外：修正程式碼註解

在 Wave 1 執行期間，順便修正各 PHP 檔案中的錯誤註解：
```php
// 舊（錯誤）：<!-- inline 繞過 InstaWP WAF -->
// 新（正確）：<!-- inline include 減少 HTTP 請求 -->
```

---

## 每個 Step 的執行 SOP

1. **確認分支**：在 `feature/entropy-wave-N` 分支上操作
2. **備份**：確認 git status 乾淨
3. **分析**：先讀檔確認功能邊界，再決定拆分點
4. **拆檔**：提取 JS → 獨立檔案，PHP 用 `<?php include ?>` 載入
5. **驗證**：test.buygo.me 實測頁面功能
6. **Commit**：一個 Step 一次提交
7. **下一步**：重複直到 Wave 完成
8. **Merge**：Wave 完成後合併到 main

## 關鍵風險

| 風險 | 對策 |
|------|------|
| JS 拆分後 Vue 元件找不到方法 | 確保拆出的模組在元件定義前載入 |
| PHP include 路徑錯誤 | 使用 `plugin_dir_path(dirname(__FILE__))` |
| 部署後目錄權限 700 導致外部 JS 403 | 部署腳本已修正 chmod 755；inline include 作為雙重保險 |
| 拆分後 CSS 選擇器失效 | CSS 已獨立（不需改動） |
| settings.php 跟邀請系統衝突 | Wave 3 等 merge 後才做 |
| 大型 JS 拆分後載入順序錯誤 | PHP 中按依賴順序 include，底層模組先載入 |

## 預估工作量

| Wave | Steps | 內容 | 預估對話數 |
|------|-------|------|-----------|
| Wave 1 | 2 個 Step | PHP 內嵌 JS 提取（dashboard + search） | 1 次對話 |
| Wave 2 | 5 個 Step | 大型 JS 拆分（Orders/Products/Shipment/DS/中型） | 2-3 次對話 |
| Wave 3 | 3 個 Step | settings.php 完整拆分（等 merge 後） | 2 次對話 |
| **合計** | **10 個 Step** | | **5-6 次對話** |
