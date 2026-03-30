# 買家會員頁面 — 待修問題清單

## 已確認的問題

### 1. iframe 載入整個 WordPress 頁面
- 現象：iframe 把 /my-account/ 整頁載入，包含 WordPress header、導航列、頁面標題「會員帳戶」
- 原因：iframe src 是完整的 WordPress 頁面 URL
- 解法：給 /my-account/ 加一個參數（如 ?embed=1），FluentCart 頁面偵測到參數時只輸出內容區，不輸出 header/footer。或改用 WordPress 的 template_redirect hook 提供 bare template。

### 2. LINE 帳號綁定在儀表板重複出現
- 現象：FluentCart 儀表板頁面底部有「LINE 帳號綁定」區塊（綠色圓點 + 照片 + 解除綁定按鈕）
- 原因：這不是我們的 shortcode，是透過 `fluent_cart/customer_dashboard_data` filter 或其他 FluentCart hook 注入的
- 解法：找到注入點並移除（已有獨立的 LINE 綁定頁面，不需要在儀表板顯示）

### 3. icon 大小不一致
- 現象：LINE 綁定的 LINE logo icon 比其他 icon 大，視覺不協調
- 解法：調整 SVG 的 viewBox 和 width/height，確保跟 FluentCart 原生 icon 同大小

### 4. 訂單進度的 emoji 排版
- 現象：狀態標籤用 emoji（📦🚚✅⏳）在不同裝置上大小不一
- 解法：改用純文字 + 背景色標籤，不用 emoji

### 5. 買家在 /buygo-portal/ 看不到 BuyGo 側邊欄
- 現象：買家進 /buygo-portal/ 只看到 iframe 裡的 FluentCart，沒有 BuyGo 的側邊欄或 header
- 預期：應該有 BuyGo 的外框包住 FluentCart 內容
- 解法：iframe 外面要保留 BuyGo 的基本框架（至少有頂部 bar 或最小化的側邊欄）

### 6. 賣家頁面待確認
- 需確認：賣家帳號登入 /buygo-portal/ 後台是否正常
- 需確認：賣家側邊欄是否需要「我的帳戶」入口
