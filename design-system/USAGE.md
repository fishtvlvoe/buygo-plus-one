# 設計系統使用指南

## 快速開始

### 1. 載入設計系統

在 WordPress 外掛的 admin class 中載入：

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

### 2. 替換 Tailwind Classes

#### 替換前（Tailwind）

```html
<header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6">
    <h1 class="text-xl font-bold text-slate-900">客戶</h1>
</header>
```

#### 替換後（設計系統）

```html
<header class="page-header">
    <h1 class="page-header-title">客戶</h1>
</header>
```

## 常見使用情境

### 情境 1：Header 區域

**需求**：顯示頁面標題、麵包屑、全域搜尋框和通知圖示

```html
<header class="page-header">
    <!-- 左側：標題和麵包屑 -->
    <div>
        <h1 class="page-header-title">客戶</h1>
        <nav class="page-header-breadcrumb">
            <a href="/">首頁</a>
            <svg>...</svg>
            <span class="active">客戶</span>
        </nav>
    </div>

    <!-- 右側：搜尋和通知 -->
    <div>
        <div class="global-search">
            <input type="text" placeholder="全域搜尋...">
            <svg class="search-icon">...</svg>
        </div>
        <button class="notification-bell">
            <svg>...</svg>
        </button>
    </div>
</header>
```

### 情境 2：SmartSearchBox

**需求**：頁面內的智慧搜尋框

```html
<div class="smart-search-box">
    <div class="smart-search-wrapper">
        <div class="smart-search-input-wrapper">
            <div class="smart-search-icon">
                <svg>...</svg>
            </div>
            <input
                type="text"
                class="smart-search-input"
                placeholder="搜尋客戶名稱、電話或 Email..."
            >
        </div>
    </div>
</div>
```

### 情境 3：桌面版表格 + 手機版卡片

**需求**：桌面版顯示表格，手機版顯示卡片

```html
<!-- 桌面版表格（≥ 768px 自動顯示） -->
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
                <td>張三</td>
                <td class="text-center">5</td>
                <td class="text-right">$1,000</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- 手機版卡片（< 768px 自動顯示） -->
<div class="card-list">
    <div class="card">
        <div class="card-title">張三</div>
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
        <button class="btn btn-primary btn-block">查看詳情</button>
    </div>
</div>
```

### 情境 4：按鈕

**需求**：不同狀態和樣式的按鈕

```html
<!-- 主要按鈕 -->
<button class="btn btn-primary">查看詳情</button>

<!-- 次要按鈕 -->
<button class="btn btn-secondary">取消</button>

<!-- 小按鈕 -->
<button class="btn btn-primary btn-sm">編輯</button>

<!-- 禁用按鈕 -->
<button class="btn btn-primary" disabled>處理中</button>

<!-- 完整寬度按鈕 -->
<button class="btn btn-primary btn-block">提交</button>
```

### 情境 5：狀態標籤

**需求**：顯示訂單或客戶狀態

**重要**：必須使用 `<span>` 而非 `<button>`，因為狀態標籤不應該可以點擊！

```html
<!-- 成功狀態 -->
<span class="status-tag status-tag-success">已完成</span>

<!-- 錯誤狀態 -->
<span class="status-tag status-tag-error">失敗</span>

<!-- 警告狀態 -->
<span class="status-tag status-tag-warning">待處理</span>

<!-- 資訊狀態 -->
<span class="status-tag status-tag-info">進行中</span>

<!-- 中性狀態 -->
<span class="status-tag status-tag-neutral">草稿</span>
```

### 情境 6：分頁器

**需求**：顯示資料分頁和每頁筆數選擇

```html
<div class="pagination-container">
    <div class="pagination-info">
        顯示 <span class="font-medium">1</span> 到 <span class="font-medium">10</span> 筆，共 <span class="font-medium">50</span> 筆
    </div>
    <div class="pagination-controls">
        <!-- 每頁筆數選擇器 -->
        <select class="pagination-select" v-model="perPage">
            <option value="5">5 筆</option>
            <option value="10">10 筆</option>
            <option value="50">50 筆</option>
        </select>

        <!-- 分頁按鈕 -->
        <nav class="pagination-nav">
            <button class="pagination-button first" @click="previousPage">
                <svg>...</svg>
            </button>
            <button class="pagination-button page active">1</button>
            <button class="pagination-button page">2</button>
            <button class="pagination-button page">3</button>
            <button class="pagination-button last" @click="nextPage">
                <svg>...</svg>
            </button>
        </nav>
    </div>
</div>
```

### 情境 7：表單輸入

**需求**：各種表單元素

```html
<!-- 文字輸入 -->
<input type="text" class="form-input" placeholder="請輸入...">

<!-- 搜尋輸入（有圖示） -->
<div class="search-input-wrapper">
    <input type="text" class="form-input search-input" placeholder="搜尋...">
    <svg class="search-icon">...</svg>
</div>

<!-- 下拉選單 -->
<select class="form-select">
    <option>選項 1</option>
    <option>選項 2</option>
</select>

<!-- 多行文字 -->
<textarea class="form-textarea" rows="4" placeholder="備註..."></textarea>

<!-- 勾選框 -->
<input type="checkbox" class="form-checkbox">
```

## 響應式設計最佳實踐

### 修改間距

✅ **正確做法**：修改對應的 spacing token

```css
/* 只修改桌面版 */
@media (min-width: 768px) {
  :root {
    --spacing-desktop-page-padding-x: 2rem;  /* 改為 32px */
  }
}

/* 只修改手機版 */
@media (max-width: 767px) {
  :root {
    --spacing-mobile-page-padding-x: 1rem;  /* 改為 16px */
  }
}
```

❌ **錯誤做法**：直接修改組件樣式

```css
/* 這樣會影響桌面版和手機版 */
.page-header {
  padding-left: 2rem;  /* 不要這樣做！ */
}
```

### 隱藏/顯示元素

使用提供的 utility classes：

```html
<!-- 桌面版顯示，手機版隱藏 -->
<div class="hidden-mobile">
    桌面版專用內容
</div>

<!-- 手機版顯示，桌面版隱藏 -->
<div class="hidden-desktop">
    手機版專用內容
</div>
```

## 遷移檢查清單

遷移一個頁面時，請按照以下順序檢查：

### Phase 1: Header 區域
- [ ] Header 容器替換為 `.page-header`
- [ ] 標題替換為 `.page-header-title`
- [ ] 麵包屑替換為 `.page-header-breadcrumb`
- [ ] 全域搜尋框替換為 `.global-search`
- [ ] 通知圖示替換為 `.notification-bell`

### Phase 2: 搜尋區域
- [ ] SmartSearchBox 替換為 `.smart-search-box`
- [ ] 搜尋輸入框替換為 `.smart-search-input`
- [ ] 搜尋圖示替換為 `.smart-search-icon`

### Phase 3: 表格區域
- [ ] 桌面版表格替換為 `.data-table`
- [ ] 手機版卡片替換為 `.card-list` 和 `.card`

### Phase 4: 按鈕與標籤
- [ ] 所有按鈕替換為 `.btn .btn-primary` 或 `.btn .btn-secondary`
- [ ] 狀態標籤從 `<button>` 改為 `<span class="status-tag">`

### Phase 5: 分頁器
- [ ] 分頁容器替換為 `.pagination-container`
- [ ] 分頁資訊替換為 `.pagination-info`
- [ ] 分頁控制替換為 `.pagination-controls`

### Phase 6: 測試
- [ ] 桌面版視覺測試（≥ 768px）
- [ ] 手機版視覺測試（< 768px）
- [ ] 所有功能測試（搜尋、分頁、按鈕點擊等）
- [ ] 無控制台錯誤

## 常見問題

### Q1: 為什麼狀態標籤要用 `<span>` 而不是 `<button>`？

A: 狀態標籤只是顯示資訊，不應該可以點擊。使用 `<span>` 可以：
- 避免誤觸
- 正確的語意化 HTML
- 更好的無障礙支援

### Q2: 如何確保桌面版和手機版樣式不互相影響？

A: 使用設計系統提供的響應式 tokens：
```css
/* 使用 current tokens，會自動根據螢幕寬度切換 */
padding: var(--current-page-padding-x);

/* 不要直接寫固定值 */
padding: 1rem;  /* ❌ */
```

### Q3: 可以混用 Tailwind classes 和設計系統嗎？

A: **不建議**。遷移時應該完全替換，混用會導致：
- CSS 衝突
- 樣式優先級問題
- 難以維護

### Q4: 如何自訂顏色？

A: 修改 `tokens/colors.css` 中的 CSS 變數：
```css
:root {
  --color-primary: #your-color;
}
```

所有使用 `--color-primary` 的組件都會自動更新。

## 支援

如有問題或建議，請：
1. 查看 [DESIGN-SYSTEM.md](./DESIGN-SYSTEM.md) 完整文件
2. 檢查是否正確載入 design-system/index.css
3. 使用瀏覽器開發者工具檢查 CSS 變數是否正確載入
