# FluentCart 與 BGo 相容性與更新摘要

本機 FluentCart 版本：**1.3.12**（DB 1.0.33）。官方 changelog 已出到 **1.3.13**（2026-02-26）。

---

## 一、BGo 與 FluentCart 是否有衝突？

**結論：沒有核心衝突，但已發現並修正一處 BGo 自己的 bug（錯誤表名）。**

BGo（buygo-plus-one）依賴 FluentCart 的方式：

• **類別檢查**：`class_exists('FluentCart\App\App')`（FluentCartService）

• **Model**：`\FluentCart\App\Models\ProductVariation::find($id)`（DataManagementService 軟刪商品時用）

• **資料表**（皆為 FluentCart 實際存在）：  
  `wp_posts`（post_type=`fluent-products`）、`fct_product_details`、`fct_product_variations`、  
  `fct_orders`、`fct_order_items`、`fct_customers`、`fct_customer_addresses`、`fct_order_addresses`

• **Post type**：商品為 `fluent-products`，不是 WooCommerce 的 `product`

**已修正的 BGo bug：**  
開發者分頁（developer-tab）先前誤用不存在的表 `fct_products` 做清除與筆數統計。  
FluentCart 沒有 `fct_products`，商品是「post type = fluent-products + fct_product_details + fct_product_variations」。  
已改為：清除/統計改為使用 `fct_product_details` 與 `fluent-products`，重置時也改為刪除 FluentCart 商品（fluent-products）而非 WooCommerce（product）。

---

## 二、FluentCart 新版本更新內容（與你本機 1.3.12 對照）

以下依官方 [Changelog](https://docs.fluentcart.com/guide/changelog) 整理，**粗體**為與 BGo／大量上架／後續規劃較相關的項目。

### 1.3.13（2026-02-26）— 你目前可再升一版

• 新增：SKU Gutenberg block  
• 修正：SKU  sanitization、手動付款結帳說明、付款方式設定自訂、日圓等零小數金額

### 1.3.12（2026-02-26）— 你本機現有

• 修正：新版本升級時的快取問題

### 1.3.11（2026-02-25）

• **新增 Product SKU**（可考慮在大量上架時一併寫入 SKU）  
• **新增 Test Data Cleanup Tool**（與我們開發者分頁「重置資料」功能類似，可參考其做法）  
• 新增：Related Products / Customer Dashboard Button / Store Logo blocks、Media Carousel Block  
• Elementor 小工具：Checkout、Add to Cart、Buy Now、Mini Cart、Products、Product Carousel、Product Categories List  
• Razorpay 訂閱、Customer LTV 重算、訂單狀態同步 action  
• 修正：庫存管理員（Inventory Manager）改為免費、新設定 UI、畫廊圖片 overflow、訂閱到期事件等

### 1.3.10（2026-02-04）

• FSE Block Theme 支援、Mini cart / Product Carousel / title / image blocks 與 shortcodes、產品分類 shortcode  
• Gutenberg blocks 升級 v3  
• 修正：續訂郵件稅額、VAT、深色/淺色主題衝突、Modal checkout 響應式

### 1.3.9（2026-01-28）

• Mercado Pago、Ghost product checkout、Gutenberg Add to Cart block  
• Shortcodes：`[fluent_cart_checkout_button]`、`[fluent_cart_add_to_cart_button]`  
• 修正：第三方 IPN、後台樣式

### 1.3.8（2026-01-23）

• **Instant checkout**、**Product duplicate**、**複製 variation ID**（右鍵選單）  
• Product Button block、JS 優化  
• 修正：S3 目錄分隔符

### 1.3.7（2026-01-20）

• 前台模板、Order UUID/hash filter、Stripe metadata hook、數位訂單自動完成 hook  
• **翻譯**：收據頁翻譯支援、多模組翻譯改進  
• 修正：Stripe 訂閱、驗證錯誤訊息、報表、結帳欄位等

### 1.3.6 及更早

• FSE、結帳條款、商品 min-max 價、Shortcode、訂閱訂單、Breakdance 結帳等修正；1.3.4 有 Bundle、Stripe hosted checkout、Razorpay、訂閱留存報表等。

---

## 三、與 BGo 後續計畫的相關性（含大量上架）

• **Product SKU（1.3.11）**：若之後要做「大量上架」或批次建立商品，可一併寫入 FluentCart 的 SKU，方便對應與搜尋。  
• **Product duplicate（1.3.8）**：複製商品功能，可作為「範本商品再上架」的參考流程。  
• **Copy variation ID（1.3.8）**：有助除錯與 API/批次操作時鎖定變體。  
• **Test Data Cleanup Tool（1.3.11）**：與我們開發者分頁重置邏輯類似，可對照官方怎麼清 FluentCart 資料，避免漏表或順序錯誤。  
• **REST API**：1.3.0 起有 [REST API 文件](https://dev.fluentcart.com/restapi/)，若未來要改為用 API 做大量建立，可評估端點與權限。  
• **翻譯**：1.3.7 加強收據與多模組翻譯，若你站上有用 FluentCart 前台字串，更新後可再跑一輪翻譯檢查。

其餘（付款閘道、訂閱、報表、FSE、Elementor 等）與目前 BGo 核心流程無直接衝突，可按需再評估是否採用。

---

## 四、FluentCart 端點／介面可優化或可接的點

BGo 目前**沒有**用 FluentCart 的 REST API，而是：

• 直接寫入 `wp_posts`（fluent-products）、`fct_product_details`、`fct_product_variations`  
• 使用 FluentCart Model：`ProductVariation::find()`  
• 使用表：fct_orders、fct_order_items、fct_customers、fct_customer_addresses 等

**可考慮的優化或擴充：**

1. **商品建立**  
   • 維持現有「直接寫表 + post」做法，相容性沒問題。  
   • 若希望少碰底層表，可評估 FluentCart REST API（若提供 product/variation 建立）或官方 hook（例如商品建立前後）再包一層。

2. **Product SKU**  
   • 1.3.11 起有 SKU；若 FluentCart 把 SKU 存在 post meta 或 variation，大量上架時可一併寫入，方便之後查詢與對應。

3. **開發者／測試資料**  
   • 官方 Test Data Cleanup Tool（1.3.11）可對照我們「重置資料」的順序與範圍，避免漏清或依賴錯誤表（如已修正的 fct_products）。

4. **Hooks / 事件**  
   • FluentCart 有 Order 與訂閱等事件（OrderCreated、OrderPaid、Subscription* 等），若 BGo 要接「訂單／付款／訂閱」流程，可查官方文件掛 hook，而不是只讀表。

5. **Customer Dashboard 自訂端點**  
   • `FluentCartGeneralApi::addCustomerDashboardEndpoint()` 可加自訂客戶入口頁，若 BGo 要導流到自己的頁面可善用。

6. **翻譯**  
   • FluentCart 使用 text domain `fluent-cart`、`language/fluent-cart.pot`。  
   • 翻譯可更新：用 Loco 從新版 FluentCart 的 .pot 拉新字串，或用我們既有的 `/translate check` 檢查既有 .po（例如 `LANG_DIR` 下 fluent-cart 的 zh_TW），再視需要 `/translate fix` 與部署。

---

## 五、翻譯是否可一併更新？

可以。建議步驟：

1. **更新 FluentCart**  
   你已更新到 1.3.12；若要跟到 1.3.13，再升一版即可。

2. **取得新字串**  
   • FluentCart 外掛內有 `language/fluent-cart.pot`（POT 日期約 2026-02-25）。  
   • 在 Loco 中對「fluent-cart」專案從該 .pot 更新/匯入字串，會出現新增/變更的條目。

3. **既有 .po/.mo**  
   • 若你翻譯檔放在 `wp-content/languages/loco/plugins/`（與 translate skill 的 LANG_DIR 一致），可用：  
     - `/translate check`：檢查 fluent-cart 的 zh_TW .po 大陸用詞與缺譯。  
     - `/translate fix`：套用 GLOSSARY 對照後重新編譯。  
   • 更新 FluentCart 後若有新字串，在 Loco 裡從 .pot 更新後再跑一次 check/fix 即可。

4. **部署**  
   用 `/translate deploy` 把更新後的 .po/.mo 同步到 buygo.me / one.buygo.me。

---

## 六、功能更新與修正詳細列表（節錄自官方 Changelog）

已整合在上方「二、FluentCart 新版本更新內容」各版本中；完整逐條以官方頁面為準：  
https://docs.fluentcart.com/guide/changelog

---

## 七、總結

| 項目 | 狀態 |
|------|------|
| BGo 與 FluentCart 衝突 | 無；依賴的類別與表皆存在且正確 |
| BGo 錯誤表名 fct_products | 已修正為 fct_product_details + fluent-products（developer-tab） |
| 1.3.12 → 1.3.13 升級 | 可升；多 SKU block 與數個 bug 修正 |
| 與大量上架／後續計畫 | SKU、複製商品、Test Data Cleanup、REST API 可納入考量 |
| 翻譯更新 | 可；用 Loco 從 .pot 更新後再跑 /translate check 與 fix、deploy |
