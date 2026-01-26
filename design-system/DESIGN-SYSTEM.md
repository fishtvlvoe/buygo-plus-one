# BuyGo Plus One 設計系統

> **專案類型：** 電商後台管理系統（訂單、商品、出貨、客戶管理）
> **設計風格：** Data-Dense Dashboard（數據密集型儀表板）
> **技術棧：** Vue 3 + Tailwind CSS
> **產生時間：** 2026-01-27

---

## 📐 設計原則

### 核心理念
- **數據優先**：最大化數據可見性，減少不必要裝飾
- **清晰直觀**：資訊層級分明，操作路徑清楚
- **高效操作**：減少點擊次數，支援批量操作
- **響應式設計**：桌面和手機版一致的使用體驗

### 適用場景
- 業務智能儀表板
- 訂單管理系統
- 庫存和出貨管理
- 客戶關係管理

---

## 🎨 色彩系統

### 主要色彩

| 角色 | 顏色 | Tailwind Class | 使用場景 |
|------|------|----------------|----------|
| **Primary** | `#F97316` | `bg-primary` `text-primary` | 主要按鈕、重要標籤 |
| **Secondary** | `#64748B` | `bg-slate-600` | 次要文字、圖示 |
| **Success** | `#10B981` | `bg-green-500` | 成功狀態、已完成 |
| **Warning** | `#F59E0B` | `bg-amber-500` | 警告、待處理 |
| **Error** | `#EF4444` | `bg-red-500` | 錯誤、失敗狀態 |
| **Info** | `#3B82F6` | `bg-blue-500` | 資訊提示、連結 |

### 中性色彩

| 名稱 | 顏色 | Tailwind Class | 使用場景 |
|------|------|----------------|----------|
| **Dark** | `#0F172A` | `text-slate-900` | 主要文字 |
| **Medium** | `#475569` | `text-slate-600` | 次要文字 |
| **Light** | `#94A3B8` | `text-slate-400` | 輔助文字、佔位符 |
| **Border** | `#E2E8F0` | `border-slate-200` | 邊框、分隔線 |
| **Background** | `#F8FAFC` | `bg-slate-50` | 頁面背景 |
| **Surface** | `#FFFFFF` | `bg-white` | 卡片、表格背景 |

### 色彩使用規範

**對比度要求：**
- 正常文字：最低 4.5:1
- 大標題（18px+）：最低 3:1
- 圖示：最低 3:1

**無障礙原則：**
- 不使用顏色作為唯一的視覺提示
- 提供文字標籤或圖示輔助
- 支援 `prefers-color-scheme` 深色模式（未來功能）

---

## 📝 字體系統

### 字體家族

```css
/* 標題字體 */
--font-heading: 'Rubik', -apple-system, BlinkMacSystemFont, sans-serif;

/* 內文字體 */
--font-body: 'Nunito Sans', -apple-system, BlinkMacSystemFont, sans-serif;

/* 等寬字體（代碼、數字） */
--font-mono: 'SF Mono', 'Consolas', 'Monaco', monospace;
```

**Google Fonts 載入：**
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;500;600;700&family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
```

### 字體大小

| 名稱 | 大小 | Tailwind | 使用場景 |
|------|------|----------|----------|
| **3XL** | 30px / 1.875rem | `text-3xl` | 頁面主標題 |
| **2XL** | 24px / 1.5rem | `text-2xl` | 區塊標題 |
| **XL** | 20px / 1.25rem | `text-xl` | 卡片標題 |
| **LG** | 18px / 1.125rem | `text-lg` | 次要標題 |
| **Base** | 16px / 1rem | `text-base` | 正文 |
| **SM** | 14px / 0.875rem | `text-sm` | 表格內容、按鈕文字 |
| **XS** | 12px / 0.75rem | `text-xs` | 標籤、輔助文字 |

### 字重

| 名稱 | 數值 | Tailwind | 使用場景 |
|------|------|----------|----------|
| Light | 300 | `font-light` | 大標題輔助文字 |
| Normal | 400 | `font-normal` | 正文 |
| Medium | 500 | `font-medium` | 次要標題、導航 |
| Semibold | 600 | `font-semibold` | 按鈕、強調文字 |
| Bold | 700 | `font-bold` | 主標題、數字 |

### 行高

| 名稱 | 數值 | Tailwind | 使用場景 |
|------|------|----------|----------|
| Tight | 1.25 | `leading-tight` | 大標題 |
| Normal | 1.5 | `leading-normal` | 正文 |
| Relaxed | 1.75 | `leading-relaxed` | 長文閱讀 |

---

## 📦 間距系統

### Tailwind 間距刻度

| Token | 數值 | Tailwind | 使用場景 |
|-------|------|----------|----------|
| `1` | 4px | `p-1` `m-1` `gap-1` | 極小間距 |
| `2` | 8px | `p-2` `m-2` `gap-2` | 圖示與文字間距 |
| `3` | 12px | `p-3` `m-3` `gap-3` | 小間距 |
| `4` | 16px | `p-4` `m-4` `gap-4` | 標準間距 |
| `6` | 24px | `p-6` `m-6` `gap-6` | 區塊內距 |
| `8` | 32px | `p-8` `m-8` `gap-8` | 大間距 |
| `12` | 48px | `p-12` `m-12` `gap-12` | 區塊外距 |
| `16` | 64px | `p-16` `m-16` `gap-16` | 頁面留白 |

### 使用建議
- **按鈕內距**：`px-4 py-2`（小）、`px-6 py-3`（大）
- **卡片內距**：`p-6`
- **區塊間距**：`space-y-4` 或 `gap-4`
- **表格 cell**：`px-4 py-4`

---

## 🎯 組件規範

### 1. 按鈕（Buttons）

#### 主要按鈕（Primary）
```html
<button class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:opacity-90 transition-colors duration-200 cursor-pointer">
  確認
</button>
```

#### 次要按鈕（Secondary）
```html
<button class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200 transition-colors duration-200 cursor-pointer">
  取消
</button>
```

#### 文字按鈕（Text）
```html
<button class="px-3 py-1.5 text-primary text-sm font-medium hover:underline cursor-pointer">
  查看更多
</button>
```

#### 按鈕狀態

| 狀態 | Class | 說明 |
|------|-------|------|
| Default | `bg-primary` | 預設狀態 |
| Hover | `hover:opacity-90` | 滑鼠懸停 |
| Disabled | `opacity-50 cursor-not-allowed` | 禁用狀態 |
| Loading | `opacity-70 cursor-wait` | 載入中 |

### 2. 表格（Tables）

```html
<table class="w-full">
  <thead class="bg-slate-50 border-b border-slate-200">
    <tr>
      <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">欄位名稱</th>
    </tr>
  </thead>
  <tbody>
    <tr class="hover:bg-slate-50 border-b border-slate-100">
      <td class="px-4 py-4 text-sm text-slate-900">內容</td>
    </tr>
  </tbody>
</table>
```

**規範：**
- Header 背景：`bg-slate-50`
- Row hover：`hover:bg-slate-50`
- 文字大小：`text-sm`
- Cell 內距：`px-4 py-4`

### 3. 卡片（Cards）

```html
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
  <!-- 卡片內容 -->
</div>
```

**規範：**
- 圓角：`rounded-2xl`（16px）
- 陰影：`shadow-sm`
- 邊框：`border border-slate-200`
- 內距：`p-6`

### 4. 輸入框（Inputs）

```html
<input
  type="text"
  placeholder="請輸入..."
  class="pl-9 pr-4 py-2.5 bg-white border border-slate-200 rounded-lg text-sm w-full focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
>
```

**規範：**
- 高度：`py-2.5`（42px 總高）
- 圓角：`rounded-lg`
- Focus 狀態：`focus:border-primary focus:ring-2 focus:ring-primary/20`
- 最小字體：16px（避免手機自動縮放）

### 5. 標籤（Badges）

```html
<!-- 成功 -->
<span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-medium">已完成</span>

<!-- 警告 -->
<span class="px-2 py-1 bg-amber-100 text-amber-700 rounded text-xs font-medium">待處理</span>

<!-- 錯誤 -->
<span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-medium">失敗</span>
```

### 6. 導航（Navigation）

#### 側邊欄導航
```html
<aside class="w-60 bg-white border-r border-slate-200 fixed left-0 top-0 h-screen">
  <nav class="p-4">
    <a href="#" class="sidebar-nav-item active">
      <svg class="w-5 h-5"><!-- icon --></svg>
      <span>導航項目</span>
    </a>
  </nav>
</aside>
```

**CSS：**
```css
.sidebar-nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  color: #64748b;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.2s;
  border-radius: 8px;
}

.sidebar-nav-item:hover {
  background: #f8fafc;
  color: #f97316;
}

.sidebar-nav-item.active {
  background: #fff7ed;
  color: #f97316;
  font-weight: 600;
}
```

### 7. 分頁器（Pagination）

```html
<div class="pagination">
  <div class="pagination-info">顯示 1 到 10 筆，共 100 筆</div>
  <div class="pagination-controls">
    <button class="pagination-btn">上一頁</button>
    <button class="pagination-btn">1</button>
    <button class="pagination-btn active">2</button>
    <button class="pagination-btn">3</button>
    <button class="pagination-btn">下一頁</button>
  </div>
</div>
```

**CSS：**
```css
.pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 24px;
  border-top: 1px solid #e2e8f0;
}

.pagination-info {
  font-size: 14px;
  color: #64748b;
}

.pagination-controls {
  display: flex;
  gap: 8px;
}

.pagination-btn {
  padding: 8px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
  color: #475569;
  background: white;
  cursor: pointer;
  transition: all 0.2s;
}

.pagination-btn:hover {
  border-color: #f97316;
  color: #f97316;
}

.pagination-btn.active {
  background: #f97316;
  color: white;
  border-color: #f97316;
}
```

### 8. 搜尋框（Search）

```html
<!-- 全域搜尋 -->
<div class="relative w-64">
  <input
    type="text"
    placeholder="全域搜尋..."
    class="pl-9 pr-4 py-2 bg-slate-100 rounded-lg text-sm w-full"
  >
  <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
  </svg>
</div>

<!-- 頁面搜尋 -->
<div class="relative">
  <input
    type="text"
    placeholder="搜尋訂單編號、客戶名稱或 Email..."
    class="pl-9 pr-4 py-2.5 bg-white border border-slate-200 rounded-lg text-sm w-full"
  >
  <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
  </svg>
</div>
```

---

## 📱 響應式設計

### 斷點（Breakpoints）

| 名稱 | 寬度 | Tailwind | 使用場景 |
|------|------|----------|----------|
| Mobile | < 640px | `sm:` | 手機直式 |
| Tablet | 640px - 1023px | `md:` | 平板、手機橫式 |
| Desktop | ≥ 1024px | `lg:` | 桌面、筆電 |
| Large | ≥ 1440px | `xl:` | 大螢幕 |

### 設計規範

#### 桌面版（≥ 1024px）
- 側邊欄：固定 240px 寬
- 主內容：`ml-60`（避開側邊欄）
- 最大寬度：無限制（跟隨視窗）
- Header 高度：64px

#### 手機版（< 640px）
- 側邊欄：隱藏（顯示漢堡選單）
- 內容寬度：100%
- 卡片：`rounded-xl`（較小圓角）
- 表格：可橫向滾動

---

## 🎭 動畫與互動

### 過渡效果（Transitions）

| 類型 | 時長 | Timing | Tailwind |
|------|------|--------|----------|
| 快速 | 150ms | ease | `transition duration-150` |
| 標準 | 200ms | ease | `transition duration-200` |
| 中速 | 300ms | ease | `transition duration-300` |

**使用建議：**
- 按鈕 hover：200ms
- 模態框出現：300ms
- 下拉選單：150ms

### Hover 效果

```css
/* 按鈕 hover */
.btn:hover {
  opacity: 0.9;
  transition: opacity 200ms ease;
}

/* 卡片 hover */
.card:hover {
  box-shadow: 0 10px 15px rgba(0,0,0,0.1);
  transform: translateY(-2px);
  transition: all 200ms ease;
}

/* 表格 row hover */
tr:hover {
  background: #f8fafc;
  transition: background 150ms ease;
}
```

### 載入狀態

```html
<!-- Spinner -->
<svg class="animate-spin h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
</svg>

<!-- Skeleton -->
<div class="animate-pulse bg-slate-200 h-4 w-full rounded"></div>
```

---

## 🚫 反模式（避免使用）

### 設計反模式
- ❌ 使用 Emoji 作為圖示（應使用 SVG）
- ❌ 過度裝飾的設計
- ❌ 沒有篩選功能的數據表格
- ❌ 低對比度文字（< 4.5:1）
- ❌ 沒有 hover 狀態的可點擊元素
- ❌ 缺少 `cursor-pointer` 的按鈕

### 技術反模式
- ❌ 使用內聯樣式（應使用 Tailwind class）
- ❌ 沒有 loading 狀態的異步操作
- ❌ 沒有錯誤處理的表單
- ❌ 忽略無障礙需求（alt text, aria-label）

---

## ✅ 交付檢查清單

在交付 UI 代碼前，請確認：

### 視覺品質
- [ ] 沒有使用 Emoji 作為圖示（使用 Heroicons/Lucide SVG）
- [ ] 所有圖示來自一致的圖示集
- [ ] Hover 狀態不會導致版面跳動
- [ ] 使用主題色直接使用（`bg-primary` 而非 `var()`）

### 互動性
- [ ] 所有可點擊元素都有 `cursor-pointer`
- [ ] Hover 狀態提供清楚的視覺回饋
- [ ] 過渡動畫流暢（150-300ms）
- [ ] Focus 狀態可見（鍵盤導航）

### 響應式
- [ ] 在 375px（手機）下可正常顯示
- [ ] 在 768px（平板）下可正常顯示
- [ ] 在 1024px（桌面）下可正常顯示
- [ ] 在 1440px（大螢幕）下可正常顯示
- [ ] 沒有橫向滾動條

### 無障礙
- [ ] 所有圖片都有 alt 文字
- [ ] 表單輸入都有 label
- [ ] 顏色不是唯一的視覺提示
- [ ] 支援 `prefers-reduced-motion`
- [ ] 文字對比度符合 WCAG AA（4.5:1）

### 效能
- [ ] 圖片使用 lazy loading
- [ ] 沒有內容跳動（為異步內容預留空間）
- [ ] 沒有固定導航遮擋內容

---

## 📚 參考資源

### 圖示庫
- [Heroicons](https://heroicons.com/) - Tailwind 官方圖示
- [Lucide Icons](https://lucide.dev/) - 美觀的 SVG 圖示

### 顏色工具
- [Tailwind Color Palette](https://tailwindcss.com/docs/customizing-colors)
- [WebAIM Contrast Checker](https://webaim.org/resources/contrastchecker/)

### 字體
- [Google Fonts](https://fonts.google.com/)
- [Rubik](https://fonts.google.com/specimen/Rubik)
- [Nunito Sans](https://fonts.google.com/specimen/Nunito+Sans)

---

## 📝 版本歷史

| 版本 | 日期 | 變更內容 |
|------|------|----------|
| 1.0 | 2026-01-27 | 初始版本，建立設計系統基礎規範 |

---

**最後更新：** 2026-01-27
**維護者：** BuyGo Plus One 團隊
