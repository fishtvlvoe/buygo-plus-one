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

### 問題 6：ShipmentDetailsPage 搜尋屬性未定義
**發現時間**：2026-01-24
**影響頁面**：Shipment Details
**錯誤訊息**：
```
[Vue warn]: Property "globalSearchQuery" was accessed during render but is not defined on instance
[Vue warn]: Property "handleGlobalSearch" was accessed during render but is not defined on instance
```

**原因**：Template 使用了 `globalSearchQuery` 和 `handleGlobalSearch`，但組件只返回了 `searchQuery` 和 `handleSearchInput`

**修復**：在組件 return 中添加別名映射
```javascript
return {
    searchQuery,
    globalSearchQuery: searchQuery,  // Alias for template
    handleSearchInput,
    handleGlobalSearch: handleSearchInput,  // Alias for template
    // ...
}
```

**Commit**：`ec6e2c0`

---

### 問題 7：ShipmentDetailsPage Template 缺少閉合標籤（第一次修復）
**發現時間**：2026-01-24
**影響頁面**：Shipment Details
**錯誤訊息**：
```
[Vue warn]: Template compilation error: Element is missing end tag
```

**原因**：Template 缺少 `</main>` 閉合標籤

**檢測方式**：使用 Python 腳本計算標籤數量
```python
<div>: 61 開, 60 閉 ❌
<main>: 1 開, 0 閉 ❌
```

**修復**：在 template 結尾處添加 `</main>` 標籤（在最後的 `</div>` 之前）

**Commit**：`2364519`

---

### 問題 8：ShipmentDetailsPage 內容區域 div 未閉合
**發現時間**：2026-01-24（第二輪檢查）
**影響頁面**：Shipment Details
**錯誤訊息**：
```
[Vue warn]: Template compilation error: Element is missing end tag.
```

**原因**：
- 問題 7 的修復添加了 `</main>` 標籤，但實際問題更深層
- 第 50 行的內容區域 div (`<div class="flex-1 overflow-auto bg-slate-50/50 relative">`) 沒有閉合標籤
- 結構應為：根 div → main → 內容區域 div → (列表檢視 + 詳情檢視)

**檢測方式**：
```python
<div>: 61 開, 60 閉 ❌  # 仍然缺少 1 個
<main>: 1 開, 1 閉 ✅   # 已修復
```

**修復**：
- 在第 560 行（詳情檢視結束）後添加 `</div>` 來閉合內容區域 div
- 添加註解 `<!-- 結束：內容區域 -->`

**修復後**：
```python
<div>: 61 開, 61 閉 ✅
<main>: 1 開, 1 閉 ✅
```

**Commit**：`dd4ad5f`

---

## 驗證結果（2026-01-24）

### HTML 結構完整性檢查
使用自動化腳本驗證所有 PHP template 的 HTML 標籤平衡：

✅ **admin/partials/products.php**
- `<div>`: 208 開, 208 閉
- `<main>`: 1 開, 1 閉

✅ **admin/partials/orders.php**
- `<div>`: 98 開, 98 閉
- `<main>`: 1 開, 1 閉

✅ **admin/partials/customers.php**
- `<div>`: 91 開, 91 閉
- `<main>`: 1 開, 1 閉

✅ **admin/partials/shipment-products.php**
- `<div>`: 54 開, 54 閉
- `<main>`: 1 開, 1 閉

✅ **admin/partials/shipment-details.php**
- `<div>`: 61 開, 61 閉 (修復後 - 經過 2 次修復)
- `<main>`: 1 開, 1 閉 (修復後)
- 問題 7：添加 `</main>` 標籤
- 問題 8：添加內容區域 div 閉合標籤

**結論**：所有頁面的 HTML 結構現已正確，標籤完全平衡。

---

**最後更新**：2026-01-24
**總計修復**：8 個關鍵問題
**驗證狀態**：✅ 所有頁面 HTML 結構已驗證
**注意事項**：shipment-details.php 經過 2 輪修復才完全解決標籤問題
