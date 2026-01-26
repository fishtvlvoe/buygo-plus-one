# BuyGo+1 設計系統使用指南

## 📖 簡介

本設計系統基於訂單頁面（最舒服的狀態）提取，提供統一的設計語言和可重用的 CSS 元件。

## 🚀 快速開始

### 1. 引入設計系統

在 WordPress 外掛中引入設計系統：

```php
// 在 admin/class-admin.php 或相關檔案中
wp_enqueue_style(
    'buygo-design-system',
    plugin_dir_url(__FILE__) . '../design-system/index.css',
    array(),
    '1.0.0'
);
```

### 2. 使用 CSS 變數

設計系統使用 CSS 變數，可直接在樣式中使用：

```css
/* 顏色 */
.my-element {
  background-color: var(--color-primary);
  color: var(--color-text-primary);
  border: 1px solid var(--color-border-base);
}

/* 間距 */
.my-card {
  padding: var(--card-padding);
  margin-bottom: var(--space-6);
  border-radius: var(--radius-card);
}

/* 字體 */
.my-heading {
  font-size: var(--text-xl);
  font-weight: var(--font-bold);
  line-height: var(--leading-tight);
}
```

### 3. 使用預設類別

設計系統提供許多預設類別，可直接在 HTML 中使用：

```html
<!-- 表格 -->
<div class="table-container">
  <table class="buygo-table">
    <thead>
      <tr>
        <th class="text-table-header">標題</th>
      </tr>
    </thead>
    <tbody>
      <tr class="table-row-hover">
        <td class="text-table-cell">內容</td>
      </tr>
    </tbody>
  </table>
</div>

<!-- 按鈕 -->
<button class="btn btn-primary btn-md">主要按鈕</button>
<button class="btn btn-secondary btn-sm">次要按鈕</button>

<!-- 卡片 -->
<div class="buygo-card buygo-card-padded">
  <div class="buygo-card-header">
    <h2 class="buygo-card-header-title">卡片標題</h2>
  </div>
  <div class="buygo-card-content">
    卡片內容
  </div>
</div>

<!-- 徽章 -->
<span class="table-badge table-badge-pending">待處理</span>
<span class="table-badge table-badge-completed">已完成</span>
```

## 📚 設計 Token

### 顏色系統

#### 主要色彩
```css
--color-primary: #2563EB           /* Primary 藍色 */
--color-primary-hover: #1D4ED8     /* Primary Hover */
--color-primary-light: #DBEAFE     /* Primary 淺色背景 */
```

#### 中性色（Slate）
```css
--color-bg-base: #F8FAFC           /* 頁面主背景 */
--color-bg-surface: #FFFFFF        /* 卡片、表格背景 */
--color-bg-subtle: #F1F5F9         /* 輸入框、次要背景 */
--color-text-primary: #0F172A      /* 主要文字 */
--color-text-secondary: #475569    /* 次要文字 */
--color-border-base: #E2E8F0       /* 主要邊框 */
```

#### 語義色彩
```css
--color-success: #16A34A           /* 成功綠色 */
--color-warning: #EA580C           /* 警告橙色 */
--color-error: #DC2626             /* 錯誤紅色 */
--color-info: #0284C7              /* 資訊藍色 */
```

### 字體系統

#### 字體大小
```css
--text-xs: 0.75rem      /* 12px - 表格標題、次要文字 */
--text-sm: 0.875rem     /* 14px - 表格內容、按鈕 */
--text-base: 1rem       /* 16px - 主要內容 */
--text-lg: 1.125rem     /* 18px - 卡片標題 */
--text-xl: 1.25rem      /* 20px - 頁面標題 */
```

#### 字重
```css
--font-normal: 400      /* 一般文字 */
--font-medium: 500      /* 次要強調 */
--font-semibold: 600    /* 主要強調、按鈕 */
--font-bold: 700        /* 標題、重要資訊 */
```

### 間距系統

#### 基礎間距
```css
--space-1: 0.25rem      /* 4px */
--space-2: 0.5rem       /* 8px */
--space-3: 0.75rem      /* 12px */
--space-4: 1rem         /* 16px */
--space-6: 1.5rem       /* 24px */
--space-8: 2rem         /* 32px */
```

#### 語義化間距
```css
--card-padding: 1.5rem              /* 24px - 卡片內距 */
--table-cell-padding-x: 1rem        /* 16px - 表格橫向內距 */
--table-cell-padding-y: 0.75rem     /* 12px - 表格縱向內距 */
--header-height: 4rem               /* 64px - Header 高度 */
```

#### 圓角
```css
--radius-lg: 0.5rem        /* 8px - 按鈕、輸入框 */
--radius-xl: 0.75rem       /* 12px - 小卡片 */
--radius-2xl: 1rem         /* 16px - 大卡片、表格 */
--radius-full: 9999px      /* 全圓 - 徽章 */
```

### 陰影系統

```css
--shadow-xs: ...           /* 極小陰影 */
--shadow-sm: ...           /* 小陰影 - 卡片、下拉選單 */
--shadow-md: ...           /* 中等陰影 - 表格容器 */
--shadow-lg: ...           /* 大陰影 - 對話框 */
```

## 🎨 元件使用

### 按鈕

#### 尺寸
```html
<button class="btn btn-primary btn-sm">小按鈕</button>
<button class="btn btn-primary btn-md">中按鈕（預設）</button>
<button class="btn btn-primary btn-lg">大按鈕</button>
```

#### 變體
```html
<button class="btn btn-primary">主要按鈕</button>
<button class="btn btn-secondary">次要按鈕</button>
<button class="btn btn-outline">外框按鈕</button>
<button class="btn btn-ghost">幽靈按鈕</button>
<button class="btn btn-danger">危險按鈕</button>
<button class="btn btn-success">成功按鈕</button>
<button class="btn btn-link">連結按鈕</button>
```

#### 圖示按鈕
```html
<button class="btn btn-icon btn-primary">
  <svg>...</svg>
</button>

<button class="btn btn-primary btn-with-icon">
  <svg>...</svg>
  <span>帶圖示按鈕</span>
</button>
```

#### 載入狀態
```html
<button class="btn btn-primary btn-loading">載入中...</button>
```

### 卡片

#### 基礎卡片
```html
<div class="buygo-card buygo-card-padded">
  卡片內容
</div>
```

#### 完整卡片
```html
<div class="buygo-card">
  <div class="buygo-card-header">
    <h2 class="buygo-card-header-title">
      <svg class="buygo-card-header-icon">...</svg>
      卡片標題
    </h2>
    <p class="buygo-card-header-subtitle">副標題</p>
  </div>
  <div class="buygo-card-content">
    主要內容
  </div>
  <div class="buygo-card-footer">
    <button class="btn btn-secondary btn-sm">取消</button>
    <button class="btn btn-primary btn-sm">確定</button>
  </div>
</div>
```

#### 統計卡片
```html
<div class="stat-card">
  <div class="stat-card-label">總訂單數</div>
  <div class="stat-card-value">1,234</div>
  <div class="stat-card-change positive">
    ↑ 12.5%
  </div>
</div>
```

#### 標籤卡片
```html
<div class="tab-card">
  <div class="tab-card-header">
    <button class="tab-card-tab active">
      待出貨
      <span class="tab-card-tab-badge">3</span>
    </button>
    <button class="tab-card-tab">
      已出貨
      <span class="tab-card-tab-badge">12</span>
    </button>
  </div>
  <div class="tab-card-content">
    標籤內容
  </div>
</div>
```

### 表格

#### 桌面版表格
```html
<div class="table-container table-container-desktop">
  <table class="buygo-table">
    <thead>
      <tr>
        <th class="text-left">編號</th>
        <th class="text-left">客戶</th>
        <th class="text-right">金額</th>
        <th class="text-center">狀態</th>
        <th class="text-center">操作</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>#001</td>
        <td>王小明</td>
        <td class="text-right">NT$ 1,200</td>
        <td class="text-center">
          <span class="table-badge table-badge-completed">已完成</span>
        </td>
        <td class="text-center">
          <button class="btn btn-primary btn-sm">查看</button>
        </td>
      </tr>
    </tbody>
  </table>
</div>
```

#### 移動版卡片
```html
<div class="table-card-mobile">
  <div class="table-card">
    <div class="table-card-header">
      <div class="table-card-title">#001</div>
      <span class="table-badge table-badge-completed">已完成</span>
    </div>
    <div class="table-card-content">
      <div class="table-card-row">
        <span class="table-card-label">客戶</span>
        <span class="table-card-value">王小明</span>
      </div>
      <div class="table-card-row">
        <span class="table-card-label">金額</span>
        <span class="table-card-value">NT$ 1,200</span>
      </div>
    </div>
    <div class="table-card-footer">
      <button class="btn btn-primary btn-sm">查看詳情</button>
    </div>
  </div>
</div>
```

#### 分頁控制
```html
<div class="table-pagination">
  <div class="pagination-info">
    顯示 <span class="font-medium">1</span> 到 <span class="font-medium">10</span> 筆，共 <span class="font-medium">50</span> 筆
  </div>
  <div class="pagination-controls">
    <select class="pagination-select">
      <option value="10">10 筆</option>
      <option value="20">20 筆</option>
      <option value="50">50 筆</option>
    </select>
    <nav class="pagination-buttons">
      <button class="pagination-button" disabled>上一頁</button>
      <button class="pagination-button active">1</button>
      <button class="pagination-button">2</button>
      <button class="pagination-button">3</button>
      <button class="pagination-button">下一頁</button>
    </nav>
  </div>
</div>
```

### 徽章

```html
<span class="table-badge table-badge-pending">待處理</span>
<span class="table-badge table-badge-processing">處理中</span>
<span class="table-badge table-badge-completed">已完成</span>
<span class="table-badge table-badge-canceled">已取消</span>
```

### 表單元素

#### 輸入框
```html
<label class="label">Email</label>
<input type="email" class="input" placeholder="請輸入 Email">
```

#### 下拉選單
```html
<label class="label">選擇選項</label>
<select class="select">
  <option>選項 1</option>
  <option>選項 2</option>
</select>
```

## 🎯 最佳實務

### 1. 優先使用設計 Token

❌ **不要這樣**：
```css
.my-element {
  color: #0F172A;
  padding: 16px;
  border-radius: 8px;
}
```

✅ **要這樣**：
```css
.my-element {
  color: var(--color-text-primary);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
}
```

### 2. 使用語義化類別

❌ **不要這樣**：
```html
<span style="background: #FFF7ED; color: #EA580C; padding: 4px 12px; border-radius: 9999px;">
  待處理
</span>
```

✅ **要這樣**：
```html
<span class="table-badge table-badge-pending">待處理</span>
```

### 3. 響應式設計

使用設計系統的響應式工具類別：

```html
<!-- 桌面版顯示 -->
<div class="hidden-mobile visible-desktop">
  桌面版內容
</div>

<!-- 移動版顯示 -->
<div class="visible-mobile hidden-desktop">
  移動版內容
</div>
```

### 4. 無障礙支援

使用適當的 ARIA 屬性和語義化 HTML：

```html
<!-- 按鈕 -->
<button class="btn btn-primary" aria-label="儲存變更">
  <svg aria-hidden="true">...</svg>
  儲存
</button>

<!-- 表格 -->
<table class="buygo-table" role="table">
  <thead role="rowgroup">
    <tr role="row">
      <th role="columnheader">...</th>
    </tr>
  </thead>
</table>
```

## 📱 響應式斷點

設計系統使用以下斷點：

- **Mobile**: < 768px
- **Desktop**: ≥ 768px

```css
/* 移動版樣式 */
@media (max-width: 767px) {
  /* ... */
}

/* 桌面版樣式 */
@media (min-width: 768px) {
  /* ... */
}
```

## 🔧 自訂與擴展

### 覆寫 CSS 變數

如需自訂設計 Token，可在引入設計系統後覆寫變數：

```css
:root {
  /* 覆寫主要色 */
  --color-primary: #3B82F6;

  /* 覆寫間距 */
  --card-padding: 2rem;

  /* 覆寫圓角 */
  --radius-card: 0.5rem;
}
```

### 新增自訂元件

建議在 `design-system/pages/` 目錄下建立頁面特定樣式：

```
design-system/
├── pages/
│   ├── orders.css        # 訂單頁面特定樣式
│   ├── customers.css     # 客戶頁面特定樣式
│   └── settings.css      # 設定頁面特定樣式
```

## 📖 參考文件

- [VISUAL-ANALYSIS.md](./VISUAL-ANALYSIS.md) - 設計分析和提取過程
- [README.md](./README.md) - 設計系統概述
- [all-pages-preview.html](./all-pages-preview.html) - 視覺預覽

## 💡 常見問題

### Q: 如何在既有頁面中使用設計系統？

A: 逐步遷移：
1. 引入 `design-system/index.css`
2. 開始使用設計 Token 替換硬編碼值
3. 逐步替換自訂類別為設計系統類別
4. 移除舊的 CSS 檔案

### Q: 設計系統與 Tailwind CSS 有什麼不同？

A: 設計系統基於專案實際需求提取，提供：
- 專案特定的設計 Token
- 預設的元件樣式
- 更小的檔案大小
- 不需要建置流程

### Q: 如何貢獻或更新設計系統？

A:
1. 基於訂單頁面（參考基準）提取新模式
2. 在 `design-system/` 相應目錄新增樣式
3. 更新 `index.css` 引入新檔案
4. 更新此文件說明新增功能
