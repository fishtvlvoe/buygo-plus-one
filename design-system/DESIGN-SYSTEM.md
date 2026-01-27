# BuyGo Plus One 設計系統

## 概述

這是 BuyGo Plus One WordPress 外掛的統一設計系統，從現有的 Tailwind CSS 樣式提取而來。設計系統的目標是實現 **UI 樣式與代碼的完全隔離**，讓 HTML 結構與視覺樣式分離，便於維護和更新。

## 核心原則

1. **樣式與代碼分離**：不在 HTML 中直接使用 Tailwind utility classes
2. **響應式隔離**：桌面版和手機版樣式完全獨立，互不影響
3. **語意化命名**：使用有意義的 class 名稱，如 `.page-header`、`.data-table`
4. **設計 tokens**：使用 CSS 變數統一管理顏色、間距、字體等

## 目錄結構

```
design-system/
├── index.css                    # 主入口檔案
├── tokens/                      # Design Tokens
│   ├── colors.css              # 顏色系統
│   ├── spacing.css             # 間距系統（含響應式）
│   ├── typography.css          # 字體系統
│   └── effects.css             # 視覺效果
└── components/                  # 組件樣式
    ├── header.css              # Header 區域
    ├── smart-search-box.css    # 智慧搜尋框
    ├── table.css               # 表格（桌面版）
    ├── card.css                # 卡片（手機版）
    ├── button.css              # 按鈕
    ├── form.css                # 表單元素
    ├── status-tag.css          # 狀態標籤
    └── pagination.css          # 分頁器
```

## Design Tokens

### 顏色系統

```css
/* Primary Colors */
--color-primary: #3b82f6;
--color-primary-dark: #2563eb;

/* Slate Colors (灰階) */
--color-slate-50: #f8fafc;
--color-slate-900: #0f172a;

/* Status Colors */
--color-success: #10b981;
--color-error: #ef4444;
--color-warning: #f59e0b;
```

### 間距系統

設計系統使用 **響應式間距 tokens** 來解決桌面版/手機版互相影響的問題：

```css
/* Desktop Spacing (≥ 768px) */
--spacing-desktop-page-padding-x: 1.5rem;  /* 24px */
--spacing-desktop-section-gap: 1.5rem;     /* 24px */

/* Mobile Spacing (< 768px) */
--spacing-mobile-page-padding-x: 0.5rem;   /* 8px */
--spacing-mobile-section-gap: 1rem;        /* 16px */

/* Current Spacing (根據螢幕寬度自動切換) */
--current-page-padding-x: var(--spacing-desktop-page-padding-x);
```

### 字體系統

```css
/* Font Sizes */
--font-size-xs: 0.75rem;    /* 12px */
--font-size-sm: 0.875rem;   /* 14px */
--font-size-base: 1rem;     /* 16px */
--font-size-xl: 1.25rem;    /* 20px */

/* Font Weights */
--font-weight-medium: 500;
--font-weight-semibold: 600;
--font-weight-bold: 700;
```

## 組件使用

### Header

```html
<header class="page-header">
  <div>
    <h1 class="page-header-title">頁面標題</h1>
    <nav class="page-header-breadcrumb">
      <a href="/">首頁</a>
      <span class="active">當前頁面</span>
    </nav>
  </div>

  <div class="global-search">
    <input type="text" placeholder="全域搜尋...">
    <svg class="search-icon">...</svg>
  </div>

  <button class="notification-bell">
    <svg>...</svg>
  </button>
</header>
```

### 按鈕

```html
<!-- Primary Button -->
<button class="btn btn-primary">查看詳情</button>

<!-- Secondary Button -->
<button class="btn btn-secondary">取消</button>

<!-- Small Button -->
<button class="btn btn-primary btn-sm">小按鈕</button>

<!-- Full Width Button -->
<button class="btn btn-primary btn-block">完整寬度</button>
```

### 表格（桌面版）

```html
<div class="data-table">
  <table>
    <thead>
      <tr>
        <th>客戶</th>
        <th class="text-center">訂單數</th>
        <th class="text-right">總消費</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>客戶名稱</td>
        <td class="text-center">5</td>
        <td class="text-right">$1,000</td>
      </tr>
    </tbody>
  </table>
</div>
```

### 卡片（手機版）

```html
<div class="card-list">
  <div class="card">
    <div class="card-title">客戶名稱</div>
    <div class="card-subtitle">0912-345-678</div>
    <div class="card-meta">
      <div class="card-meta-item">
        <span class="card-meta-label">訂單數</span>
        <span class="card-meta-value">5</span>
      </div>
      <div class="card-meta-item">
        <span class="card-meta-label">總消費</span>
        <span class="card-meta-value">$1,000</span>
      </div>
    </div>
  </div>
</div>
```

### 狀態標籤

```html
<!-- Success -->
<span class="status-tag status-tag-success">已完成</span>

<!-- Error -->
<span class="status-tag status-tag-error">失敗</span>

<!-- Warning -->
<span class="status-tag status-tag-warning">待處理</span>

<!-- Info -->
<span class="status-tag status-tag-info">進行中</span>
```

### 分頁器

```html
<div class="pagination-container">
  <div class="pagination-info">
    顯示 <span class="font-medium">1</span> 到 <span class="font-medium">10</span> 筆
  </div>
  <div class="pagination-controls">
    <select class="pagination-select">
      <option value="5">5 筆</option>
      <option value="10">10 筆</option>
      <option value="50">50 筆</option>
    </select>
    <nav class="pagination-nav">
      <button class="pagination-button first">←</button>
      <button class="pagination-button page active">1</button>
      <button class="pagination-button page">2</button>
      <button class="pagination-button last">→</button>
    </nav>
  </div>
</div>
```

## 響應式設計

設計系統使用 **768px** 作為桌面版和手機版的分界點：

- **桌面版** (≥ 768px)：使用 `.data-table` 顯示表格
- **手機版** (< 768px)：使用 `.card-list` 顯示卡片

### 響應式隔離原理

使用 media queries 和獨立的 spacing tokens：

```css
/* 桌面版專用間距 */
@media (min-width: 768px) {
  :root {
    --current-page-padding-x: var(--spacing-desktop-page-padding-x);
  }
}

/* 手機版專用間距 */
@media (max-width: 767px) {
  :root {
    --current-page-padding-x: var(--spacing-mobile-page-padding-x);
  }
}
```

這樣修改桌面版間距時，手機版完全不受影響，反之亦然。

## 載入方式

在 WordPress 外掛中全域載入：

```php
// admin/class-admin.php
public function enqueue_admin_styles() {
    wp_enqueue_style(
        'buygo-design-system',
        plugins_url('design-system/index.css', dirname(__FILE__)),
        [],
        BUYGO_PLUS_ONE_VERSION
    );
}
```

## 遷移指南

從 Tailwind classes 遷移到設計系統：

| Tailwind | 設計系統 |
|----------|---------|
| `bg-white border border-slate-200 rounded-2xl` | `.data-table` |
| `px-4 py-2 bg-primary text-white rounded-lg` | `.btn .btn-primary` |
| `text-xs font-medium bg-green-50 text-green-700 rounded-full` | `.status-tag .status-tag-success` |
| `<button>狀態</button>` | `<span class="status-tag">狀態</span>` |

## 注意事項

1. **不要混用 Tailwind 和設計系統**：遷移時應完全替換，不要部分使用
2. **狀態標籤必須使用 `<span>`**：不要使用 `<button>`，避免可點擊
3. **響應式測試**：修改樣式後務必測試桌面版和手機版
4. **CSS 載入順序**：設計系統應該在 Tailwind 之後載入

## 版本

- **版本**: 1.0.0
- **建立日期**: 2026-01-27
- **基於**: customers.php Tailwind classes 提取
