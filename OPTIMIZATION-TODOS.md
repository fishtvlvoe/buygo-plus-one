# BuyGo Plus One 優化待辦清單

## 📅 建立日期
2026-02-03

---

## 🎯 優化項目

### 1. PHP 頁面響應式優化

**現況問題**：
- 部分 PHP 頁面不是響應式設計
- 當寬度 > 768px 時，表格或內容會被裁切（超出可視範圍）

**目標**：
- 所有頁面在電腦版時，欄寬能等比例縮放
- 寬度 < 768px 時，自動切換成手機版樣式
- 避免內容被裁切或需要橫向滾動

**優先級**：中
**預估工作量**：需要逐頁檢查並修正

---

### 2. PHP 頁面架構重構（關注點分離）

#### 問題描述

**現況**：
- PHP 頁面檔案過長（例：`admin/partials/products.php` 約 800+ 行）
- 單一檔案混雜：
  - PHP 邏輯
  - HTML 結構（電腦版 + 手機版）
  - Vue 模板和元件
  - JavaScript 邏輯
  - CSS 樣式

**影響**：
- 檔案難以維護
- 載入速度慢（約 1 秒，目標：0.3 秒）
- 程式碼可讀性差
- 難以重用元件

#### 優化目標

**架構目標**：
```
PHP 頁面
├── PHP 邏輯層（資料處理、權限檢查）
├── HTML 結構（透過獨立檔案嵌入）
├── Vue 元件（獨立 .vue 或 .js 檔案）
├── JavaScript（獨立 .js 檔案）
└── CSS（獨立 .css 檔案）
```

**關注點分離**：
1. **PHP 檔案**：只負責伺服器端邏輯
   - 資料查詢
   - 權限驗證
   - API 路由
   - 載入必要的資源

2. **HTML 模板**：獨立的 HTML 片段
   - 可透過 `include()` 或模板引擎載入
   - 響應式設計（使用 CSS 控制電腦/手機版）

3. **Vue 元件**：獨立的元件檔案
   - 單一職責元件
   - 可重用
   - 易於測試

4. **JavaScript**：獨立的 JS 檔案
   - 按需載入
   - 壓縮優化

5. **CSS**：獨立的樣式檔案
   - 模組化
   - 壓縮優化

#### 效能目標

**載入速度**：
- 現況：約 1.0 秒
- 目標：< 0.3 秒

**優化策略**：
- [ ] 程式碼分割（Code Splitting）
- [ ] 懶加載（Lazy Loading）
- [ ] 資源壓縮（Minification）
- [ ] 快取策略（Browser Caching）
- [ ] CDN 使用（可選）

#### 實作方式建議

**方案 A：保持 PHP 生態，使用 include**
```php
<?php
// products.php
// 1. PHP 邏輯
require_once 'includes/product-logic.php';

// 2. 載入 HTML 模板
include 'templates/product-list.html';

// 3. 載入 JS/CSS（enqueue）
wp_enqueue_script('product-list-vue', 'components/ProductList.js');
wp_enqueue_style('product-list-css', 'assets/css/product-list.css');
?>
```

**方案 B：使用模板引擎（例：Twig、Blade）**
- 更清晰的模板語法
- 自動轉義防 XSS
- 模板繼承和區塊

**方案 C：完全前後端分離**
- PHP 只提供 REST API
- 前端使用 Vue SPA
- 工作量最大，但最現代化

#### 優先級

**優先級**：中高
**建議執行時機**：
1. 先完成當前的 Bug 修復
2. 選擇 1-2 個頁面作為試點（例：商品頁、訂單頁）
3. 驗證效果後，再逐步推廣到其他頁面

---

## 📊 執行計畫

### Phase 1: 評估與試點（預估 2-3 天）
- [ ] 分析現有頁面結構
- [ ] 選擇架構方案（A/B/C）
- [ ] 挑選 1-2 個頁面作為試點
- [ ] 測量當前效能基準

### Phase 2: 試點重構（預估 1 週）
- [ ] 拆分 PHP 邏輯層
- [ ] 獨立 HTML 模板
- [ ] 元件化 Vue 代碼
- [ ] 獨立 JS/CSS 檔案
- [ ] 效能測試與優化

### Phase 3: 推廣與優化（預估 2-3 週）
- [ ] 逐頁應用新架構
- [ ] 響應式設計統一
- [ ] 效能監控與調優
- [ ] 文件更新

---

## 🔍 相關參考

### 效能優化技術
- Code Splitting
- Tree Shaking
- Lazy Loading
- Image Optimization
- Browser Caching
- HTTP/2 Server Push

### 前端架構
- Vue Component Best Practices
- CSS Modules
- Tailwind CSS（已在使用）

### 工具
- Webpack / Vite（打包工具）
- Lighthouse（效能測試）
- Chrome DevTools Performance

---

## 📝 備註

- 此文件記錄長期優化目標，不影響當前的 Bug 修復
- 優化應該是漸進式的，避免一次性大重構
- 每個階段都要有效能測量，確保改進有效

---

最後更新：2026-02-03
