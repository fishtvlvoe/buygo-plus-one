# 重構後 Bug 修復記錄

> 記錄第 3 階段重構後發現和修復的問題

## 2026-01-24 - Vue 組件提取後的修復

### 問題 1：JS 檔案包含 HTML 標籤
**發現時間**：2026-01-24  
**影響頁面**：Orders, Customers, Shipment Details, Shipment Products  
**錯誤訊息**：
```
Uncaught SyntaxError: Unexpected token '<' (at OrdersPage.js:1092)
```

**原因**：提取組件時不小心保留了 `</script>` 標籤

**修復**：移除所有 JS 組件檔案中的 `<script>` 和 `</script>` 標籤

**Commit**：`e6a42fd`

---

### 問題 2：CSS 路徑 404 錯誤
**發現時間**：2026-01-24  
**影響頁面**：Products, Orders, Customers, Shipment Products  
**錯誤訊息**：
```
Failed to load resource: 404 (products.css:1)
```

**原因**：CSS 連結使用了錯誤的相對路徑

**修復**：改用 `'../css/'` 相對路徑

**Commit**：`e6a42fd`

---

### 問題 3：Vue Template 編譯錯誤
**發現時間**：2026-01-24  
**影響頁面**：Orders, Customers, Shipment Details, Shipment Products  

**原因**：
1. PHP 檔案缺少 template script 標籤
2. JS 組件中使用內嵌 PHP 語法

**修復**：添加 template 標籤並改用選擇器

**Commit**：`267b49e`

---

### 問題 4：ShipmentDetailsPage ref 未定義
**發現時間**：2026-01-24  
**影響頁面**：Shipment Details  

**原因**：setup() 中沒有 destructure ref

**修復**：添加 ref 到 Vue destructuring

**Commit**：`267b49e`

---

### 問題 5：Template 標籤嵌套錯誤
**發現時間**：2026-01-24
**影響頁面**：Customers, Shipment Details, Shipment Products
**錯誤訊息**：
```
[Vue warn]: Template element not found or is empty: #customers-page-template
```

**原因**：template script 標籤被錯誤地放在 wpNonce script 標籤內部

**修復前**：
```html
<script>
  window.buygoWpNonce = '...';
  <script type="text/x-template">...</script>  ❌
</script>
```

**修復後**：
```html
<script type="text/x-template">...</script>  ✅
<script>
  window.buygoWpNonce = '...';
</script>
```

**Commit**：`e63e699`

---

**最後更新**：2026-01-24
**總計修復**：5 個關鍵問題
