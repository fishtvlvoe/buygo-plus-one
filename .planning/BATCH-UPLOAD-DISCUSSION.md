# 批量上架前端 UI — 待討論

**建立日期：** 2026-02-24
**狀態：** 待討論

---

## 背景

Phase 56 已完成批量上架 API 後端（`BatchCreateService`），但沒有前端 UI。
目前 API 支援：title（必填）、price（必填）、quantity、description、currency。
單次上限 50 筆，會檢查賣家配額。

---

## 要問用戶的問題

### 1. 使用場景
- 賣家通常是「一個商品一個款式」居多，還是「一個商品多個款式」（如 S/M/L 尺寸）？
- 一次批量上架大概幾筆？5-10 筆？還是 20-50 筆？
- 賣家的商品資料來源是什麼？手打？Excel？LINE 群組複製？

### 2. 多樣式（Variations）
- FluentCart 的多樣式結構：一個商品（post）對應多個 product_variations
- 批量上架是否需要支援多樣式？還是先做「一商品一款式」就好？
- 如果要支援多樣式，輸入格式會複雜很多（每個 variation 有自己的名稱、價格、庫存）

### 3. 圖片處理
- 用戶提到「單一產品」跟「多樣式產品」的圖片問題
- 圖片上傳方案：
  - A：先批次上傳圖片到媒體庫 → 在表格中選取關聯
  - B：CSV 欄位填圖片 URL → 系統自動下載建立 attachment
  - C：先不管圖片，批量建立後再逐筆補圖
- R2 圖床已整合，圖片是走 R2 還是 WordPress 媒體庫？

### 4. UI 放在哪裡
- WP 後台（管理員用）？
- Portal 前台（賣家用）？
- 兩邊都要？

### 5. 前端方案
- 方案 A：動態表格輸入（適合少量，直覺）
- 方案 B：CSV/Excel 上傳 + 預覽（適合大量）
- 方案 A+B：兩者結合（建議）
- 用戶已初步同意 A+B 方向

---

## 需要的背景資料

### 需要先調查
1. **FluentCart 商品建立完整流程** — `FluentCartService::create_product()` 支援哪些參數？圖片怎麼關聯？
2. **多樣式建立流程** — FluentCart 如何建立帶 variations 的商品？API 還是直接寫資料表？
3. **R2 圖床整合** — 目前圖片上傳流程是什麼？前端怎麼觸發？
4. **現有商品編輯頁的圖片 UI** — Portal 前台的商品編輯頁怎麼處理圖片？可以複用嗎？

### 已知資訊
- `BatchCreateService` 在 `includes/services/class-batch-create-service.php`
- API 端點在 `includes/api/class-reserved-api.php`
- `ProductLimitChecker` 處理配額檢查
- FluentCart 商品表：`wp_posts`（post_type=fluent-products）+ `wp_fct_product_variations`
- R2 圖床設定在 BGO 設定頁的 R2 Tab

---

## 初步方向（待確認）

1. **第一版**：動態表格 + CSV 匯入，支援「一商品一款式」，不含圖片
2. **第二版**：加入圖片上傳（批次拖拉 + 關聯）
3. **第三版**：支援多樣式商品批量建立
