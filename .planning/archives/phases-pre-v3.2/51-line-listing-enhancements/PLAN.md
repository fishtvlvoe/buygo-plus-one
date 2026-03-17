# Phase 51：LINE 上架功能增強

## 概述

兩個獨立功能，可分開執行：
- **功能 A**：數量 0 = 無限量上架（小改動，30 分鐘）
- **功能 B**：URL 抓取自動上架（新功能，需要新 class）

---

## 功能 A：數量 0 = 無限量上架

### 現況
- `quantity = 0` → `stock_status = 'out-of-stock'`（缺貨，無法購買）
- FluentCart 本身支援 `manage_stock = 0`（不管理庫存 = 永遠有貨）

### 改動範圍
修改 `includes/services/class-fluentcart-service.php`，3 個方法：

1. **`create_product_details()`**（~L259-269）
   - `quantity === 0` → `manage_stock = 0`, `stock_availability = 'in-stock'`
   - `quantity > 0` → `manage_stock = 1`, `stock_availability = 'in-stock'`

2. **`create_default_variation()`**（~L562-607）
   - `quantity === 0` → `manage_stock = 0`, `stock_status = 'in-stock'`, `total_stock = 0`, `available = 0`
   - `quantity > 0` → 維持原邏輯

3. **`create_variation()`**（多樣式商品）
   - 同上邏輯

### 驗證方式
- LINE 傳商品資訊，數量填 0 → 商品應為 `in-stock` 且可購買
- LINE 傳商品資訊，數量填 10 → 商品庫存 10，賣完變缺貨
- FluentCart 後台確認商品顯示正確

---

## 功能 B：URL 抓取自動上架

### 使用場景
賣家在 LINE 貼一個商品連結 → 系統自動抓取商品名稱、價格、圖片 → 上架到 FluentCart

### 架構設計

```
使用者貼 URL
  ↓
LineTextRouter 偵測 URL（正則：https?://...）
  ↓
UrlProductScraper::scrape($url)
  ├── wp_remote_get() 抓網頁
  ├── 解析策略（依優先順序）：
  │   1. JSON-LD（StructuredData）→ 最準確
  │   2. Open Graph meta（og:title, og:image）
  │   3. DOM fallback（<title>, <img>, 價格正則）
  ├── 下載圖片 → media_sideload_image()
  └── 回傳 ['name', 'price', 'image_id', 'source_url']
  ↓
回覆使用者 Flex Message 確認（顯示抓到的資訊）
  ↓
使用者確認 → FluentCartService::create_product()
使用者取消 → 清除暫存
```

### 新增檔案
- `includes/services/class-url-product-scraper.php` — 網頁抓取與解析

### 修改檔案
- `includes/services/class-line-text-router.php` — 加 URL 偵測分支
- `includes/services/class-line-product-creator.php` — 可能需要調整確認流程
- `includes/services/class-line-flex-templates.php` — 新增 URL 抓取確認的 Flex 模板

### 關鍵設計決策
1. **需要確認步驟**：抓到的資訊可能不準確，讓使用者確認後再上架
2. **數量預設**：URL 抓取的商品預設 `quantity = 0`（無限量，配合功能 A）
3. **價格解析**：不同網站格式不同，需要處理 `$`, `NT$`, `TWD`, 逗號分隔等
4. **圖片處理**：優先抓 OG image，fallback 到頁面內最大的商品圖

### 風險與限制
- 部分網站有反爬蟲（Cloudflare、reCAPTCHA）→ 回覆「無法抓取此網站」
- 價格可能抓不到（有些網站需要 JS 渲染）→ 讓使用者手動補
- 圖片可能被 hotlink 保護 → 需要帶 Referer header

### 驗證方式
- 貼 momo/蝦皮/PChome 商品連結 → 確認能抓到名稱、價格、圖片
- 貼一般網頁（非商品）→ 回覆「找不到商品資訊」
- 貼被擋的網站 → 回覆「無法抓取」而非 crash

---

## 執行順序建議

1. **先做功能 A**（數量 0 = 無限量）— 獨立、小改動、功能 B 也需要
2. **再做功能 B**（URL 抓取）— 依賴功能 A 的預設數量邏輯

## 分支策略
- 功能 A：`feature/unlimited-stock`
- 功能 B：`feature/url-product-scraper`
