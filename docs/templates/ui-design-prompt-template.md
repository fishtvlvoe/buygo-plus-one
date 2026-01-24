# Cursor 提示模板 (簡潔版)

> **工作流程**:
> 1. 與 Claude Code 討論需求
> 2. Claude Code 填寫此模板
> 3. 貼給 Cursor + Pencil 生成視覺稿
> 4. 帶回視覺稿給 Claude Code 寫程式碼

---

## 🎯 給 Cursor 的提示格式

```
請使用 Pencil 幫我設計一個 WordPress 後台頁面：

【頁面名稱】: [例如：商品列表頁]
【URL】: [例如：/buygo-portal/products]
【使用者】: [例如：BuyGo 管理員]

【核心功能】:
- [功能 1，例如：瀏覽所有商品]
- [功能 2，例如：搜尋和篩選]
- [功能 3，例如：編輯/刪除商品]

【版面結構】:
1. Header (固定置頂，64px)
   - 左側: [頁面標題 + Icon]
   - 右側: [全域搜尋框] [主要操作按鈕]

2. Smart Search Box (可選)
   - [智能搜尋框，針對當前頁面內容]

3. 主要內容區
   - 桌面版: [表格/表單/卡片群組]
   - 手機版: [卡片列表]

4. Pagination (置底)
   - 左側: 顯示筆數
   - 右側: 上一頁/下一頁

【設計規範】:
- 參考 BuyGo UI/UX Golden Principles (已提供)
- 色系: 藍色主題 (#3b82f6)
- 間距: Tailwind 標準 (4/8/16/24/32px)
- 字體: system-ui, 14px-24px
- 狀態標籤: 使用 <span> 標籤 (NOT button)
- 表格: 欄位寬度百分比 (NOT 固定 px)

【特殊需求】:
- [例如：庫存 < 10 顯示紅色警告]
- [例如：SKU 可複製到剪貼簿]
- [例如：批次選擇功能]

【輸出需求】:
1. 桌面版全頁設計稿 (1920x1080)
2. 手機版全頁設計稿 (375x812)
3. 關鍵元件特寫（可選）
4. 標註所有間距、字體、顏色
```

---

## 🎨 設計請求模板

```markdown
# UI 設計請求 - [頁面名稱]

## 1️⃣ 設計概述

**頁面名稱**: [例如：商品列表頁、訂單詳情頁、會員權限管理]
**URL 路徑**: [例如：/buygo-portal/products, /buygo-portal/settings?view=members]
**主要使用者**: [例如：BuyGo 管理員、小幫手、一般店員]
**核心功能**: [用 1-2 句話描述此頁面的主要用途]

---

## 2️⃣ 設計系統規範

### 🎨 色彩規範

**主要色彩**:
- Primary: `#3b82f6` (藍色 - 主要按鈕、連結)
- Success: `#10b981` (綠色 - 成功狀態、已上架)
- Warning: `#f59e0b` (橘色 - 警告狀態、處理中)
- Danger: `#ef4444` (紅色 - 錯誤、刪除按鈕)
- Neutral: `#64748b` (灰色 - 次要文字、邊框)

**背景色**:
- Page Background: `#f8fafc` (淺灰 - 頁面底色)
- Card Background: `#ffffff` (白色 - 卡片、表格)
- Hover Background: `#f1f5f9` (極淺灰 - hover 狀態)

**文字色**:
- Primary Text: `#0f172a` (深灰黑 - 主要內容)
- Secondary Text: `#64748b` (灰色 - 次要說明)
- Disabled Text: `#cbd5e1` (淺灰 - 禁用狀態)

### 📏 間距規範

**Spacing Scale** (Tailwind 標準):
- xs: `4px` (元件內小間距)
- sm: `8px` (元件內間距)
- md: `16px` (元件間距)
- lg: `24px` (區塊間距)
- xl: `32px` (大區塊間距)
- 2xl: `48px` (章節間距)

**Component Padding**:
- 按鈕: `px-4 py-2` (水平 16px, 垂直 8px)
- 卡片: `p-6` (24px)
- 表格儲存格: `px-4 py-4` (16px)
- 頁面容器: `p-6` (24px)

### 🔤 字體規範

**字體家族**: system-ui (macOS/iOS San Francisco, Windows Segoe UI)

**字體大小**:
- Heading 1: `text-2xl` (24px) - 頁面標題
- Heading 2: `text-xl` (20px) - 區塊標題
- Heading 3: `text-lg` (18px) - 子區塊標題
- Body: `text-base` (16px) - 一般內容
- Small: `text-sm` (14px) - 次要說明
- Tiny: `text-xs` (12px) - 標籤、輔助文字

**字重**:
- Bold: `font-bold` (700) - 標題、重要數據
- Semibold: `font-semibold` (600) - 次標題
- Medium: `font-medium` (500) - 按鈕文字
- Normal: `font-normal` (400) - 一般內容

### 📐 圓角規範

- Button/Input: `rounded-lg` (8px)
- Card: `rounded-2xl` (16px)
- Tag/Badge: `rounded-full` (完全圓角)
- Avatar: `rounded-full` (圓形)

### 🔲 邊框規範

- Default Border: `border border-slate-200` (1px, 淺灰)
- Hover Border: `border-slate-300` (懸停時加深)
- Focus Border: `ring-2 ring-blue-500` (聚焦時藍色外框)

---

## 3️⃣ 版面結構

### 🖥️ 桌面版 (≥768px)

```
┌─────────────────────────────────────────────────────┐
│ Header (64px 高, 固定置頂)                           │
│ ├─ 左側: [頁面標題 + Icon]                          │
│ └─ 右側: [全域搜尋框] [主要操作按鈕]                 │
├─────────────────────────────────────────────────────┤
│ Smart Search Box (可選，48px 高)                     │
│ └─ [智能搜尋輸入框 (針對當前頁面內容)]              │
├─────────────────────────────────────────────────────┤
│ Content Area (padding: 24px)                        │
│ ┌─────────────────────────────────────────────────┐ │
│ │ 主要內容區 (表格 / 表單 / 卡片群組)             │ │
│ │                                                   │ │
│ │ [具體內容結構見下方「元件規格」]                │ │
│ │                                                   │ │
│ └─────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────┐ │
│ │ Pagination (置底)                                │ │
│ │ 左側: 顯示 1-10 / 共 50 筆                       │ │
│ │ 右側: [上一頁] [下一頁]                          │ │
│ └─────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────┘
```

### 📱 手機版 (<768px)

```
┌─────────────────────────┐
│ Header (56px 高)        │
│ ├─ 標題                 │
│ └─ 漢堡選單             │
├─────────────────────────┤
│ Smart Search (展開後)   │
│ └─ [搜尋框]            │
├─────────────────────────┤
│ Content (padding: 16px) │
│ ┌─────────────────────┐ │
│ │ Card 1              │ │
│ └─────────────────────┘ │
│ ┌─────────────────────┐ │
│ │ Card 2              │ │
│ └─────────────────────┘ │
│ ...                     │
│ ┌─────────────────────┐ │
│ │ Pagination (簡化版) │ │
│ └─────────────────────┘ │
└─────────────────────────┘
```

---

## 4️⃣ 元件規格

### 🔘 按鈕元件

**主要按鈕 (Primary Button)**:
```html
<button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
    + 新增商品
</button>
```

**次要按鈕 (Secondary Button)**:
```html
<button class="px-4 py-2 bg-white text-slate-700 border border-slate-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">
    取消
</button>
```

**危險按鈕 (Danger Button)**:
```html
<button class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
    刪除
</button>
```

**圖示按鈕 (Icon Button)**:
```html
<button class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
    </svg>
</button>
```

### 🏷️ 狀態標籤 (Status Tag)

**規則**:
- ✅ 使用 `<span>` 標籤 (NOT `<button>`)
- ✅ 樣式: `px-2 py-1 text-xs font-medium rounded-full`
- ✅ 背景色淡、邊框同色系、文字深色

**已上架 (Active)**:
```html
<span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 border border-green-200">
    已上架
</span>
```

**處理中 (Processing)**:
```html
<span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 border border-yellow-200">
    處理中
</span>
```

**已下架 (Inactive)**:
```html
<span class="px-2 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-800 border border-slate-200">
    已下架
</span>
```

**錯誤 (Error)**:
```html
<span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 border border-red-200">
    失敗
</span>
```

### 📊 表格元件

**桌面版表格 (Data Table)**:

**結構要求**:
- ✅ 欄位寬度必須使用 `w-[15%]` 百分比形式（絕對不可使用固定 px）
- ✅ 所有欄位寬度總和 = 100%
- ✅ 表頭必須有背景色 `bg-slate-50`
- ✅ 每列 hover 效果 `hover:bg-slate-50`
- ✅ 所有文字必須水平顯示（絕對不可有垂直文字）

**範例結構**:
```html
<table class="w-full">
    <thead>
        <tr class="bg-slate-50 border-b border-slate-200">
            <th class="px-4 py-3 text-left w-[5%] text-xs font-semibold text-slate-600 uppercase">
                <input type="checkbox" class="rounded">
            </th>
            <th class="px-4 py-3 text-left w-[25%] text-xs font-semibold text-slate-600 uppercase">商品名稱</th>
            <th class="px-4 py-3 text-left w-[15%] text-xs font-semibold text-slate-600 uppercase">SKU</th>
            <th class="px-4 py-3 text-left w-[15%] text-xs font-semibold text-slate-600 uppercase">價格</th>
            <th class="px-4 py-3 text-left w-[10%] text-xs font-semibold text-slate-600 uppercase">庫存</th>
            <th class="px-4 py-3 text-left w-[15%] text-xs font-semibold text-slate-600 uppercase">狀態</th>
            <th class="px-4 py-3 text-left w-[15%] text-xs font-semibold text-slate-600 uppercase">操作</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
        <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-4 py-4">
                <input type="checkbox" class="rounded">
            </td>
            <td class="px-4 py-4">
                <div class="flex items-center gap-3">
                    <img src="..." class="w-12 h-12 rounded object-cover">
                    <span class="font-medium text-slate-900">商品名稱</span>
                </div>
            </td>
            <td class="px-4 py-4 text-sm text-slate-600">SKU-001</td>
            <td class="px-4 py-4 text-sm font-bold text-slate-900">1,000 TWD</td>
            <td class="px-4 py-4 text-sm text-slate-600">50</td>
            <td class="px-4 py-4">
                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 border border-green-200">
                    已上架
                </span>
            </td>
            <td class="px-4 py-4">
                <div class="flex items-center gap-2">
                    <!-- 圖示按鈕群組 -->
                </div>
            </td>
        </tr>
    </tbody>
</table>
```

**手機版卡片 (Mobile Card)**:

**結構要求**:
- 隱藏表格，改用卡片 `<div class="md:hidden">`
- 每張卡片包含完整資訊
- 底部操作按鈕改用 2 欄 Grid

```html
<div class="md:hidden space-y-4">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="font-bold text-slate-900">商品名稱</div>
            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 border border-green-200">
                已上架
            </span>
        </div>
        <div class="flex justify-between items-start mb-3">
            <div class="text-sm text-slate-600">SKU: SKU-001</div>
            <div class="text-sm font-bold text-slate-900">1,000 TWD</div>
        </div>
        <div class="text-sm text-slate-600 mb-3">庫存: 50</div>
        <div class="grid grid-cols-2 border-t border-slate-100 bg-slate-50/50 divide-x divide-slate-100 -mx-4 -mb-4">
            <button class="py-3 text-xs font-bold text-blue-600">編輯</button>
            <button class="py-3 text-xs font-bold text-red-500">刪除</button>
        </div>
    </div>
</div>
```

### 🔍 搜尋框元件

**全域搜尋框 (Global Search Box)**:
```html
<div class="relative w-64">
    <input
        type="text"
        placeholder="全域搜尋..."
        class="pl-9 pr-4 py-2 bg-slate-100 border-none rounded-lg text-sm focus:ring-2 focus:ring-blue-500 w-full"
    >
    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
    </svg>
</div>
```

**智能搜尋框 (Smart Search Box)**:
```html
<div class="bg-white shadow-sm border-b border-slate-200 px-6 py-4">
    <div class="relative">
        <input
            type="text"
            placeholder="搜尋商品名稱或 SKU..."
            class="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
        >
        <svg class="w-5 h-5 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
    </div>
</div>
```

### 📄 分頁元件 (Pagination)

```html
<div class="mt-6 flex items-center justify-between">
    <div class="text-sm text-slate-600">
        顯示 1 - 10 / 共 50 筆
    </div>
    <div class="flex items-center gap-2">
        <button class="px-3 py-1 border border-slate-300 rounded text-sm hover:bg-slate-50 disabled:opacity-50">
            上一頁
        </button>
        <button class="px-3 py-1 border border-slate-300 rounded text-sm hover:bg-slate-50 disabled:opacity-50">
            下一頁
        </button>
    </div>
</div>
```

### 📢 通知訊息元件 (Notification)

**資訊通知 (Info)**:
```html
<div class="p-4 bg-blue-50 border border-blue-200 rounded-xl">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <h3 class="text-sm font-semibold text-blue-900 mb-1">通知標題</h3>
            <p class="text-sm text-blue-800">通知內容說明</p>
        </div>
    </div>
</div>
```

**成功通知 (Success)**:
```html
<div class="p-4 bg-green-50 border border-green-200 rounded-xl">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <h3 class="text-sm font-semibold text-green-900 mb-1">操作成功</h3>
            <p class="text-sm text-green-800">您的操作已成功完成</p>
        </div>
    </div>
</div>
```

**警告通知 (Warning)**:
```html
<div class="p-4 bg-yellow-50 border border-yellow-200 rounded-xl">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
            <h3 class="text-sm font-semibold text-yellow-900 mb-1">注意事項</h3>
            <p class="text-sm text-yellow-800">請注意此操作可能的影響</p>
        </div>
    </div>
</div>
```

**錯誤通知 (Error)**:
```html
<div class="p-4 bg-red-50 border border-red-200 rounded-xl">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <h3 class="text-sm font-semibold text-red-900 mb-1">發生錯誤</h3>
            <p class="text-sm text-red-800">操作失敗，請稍後再試</p>
        </div>
    </div>
</div>
```

---

## 5️⃣ 互動規格

### 🖱️ 滑鼠懸停效果 (Hover)

**按鈕懸停**:
- 背景色加深一階 (例如：`bg-blue-600` → `hover:bg-blue-700`)
- 加入 `transition-colors` 動畫

**表格列懸停**:
- 背景變淺灰 `hover:bg-slate-50`
- 加入 `transition-colors` 動畫

**卡片懸停**:
- 陰影增強 `hover:shadow-md`
- 輕微上移 `hover:-translate-y-0.5`
- 加入 `transition-all` 動畫

### ⌨️ 鍵盤導航

**Focus 狀態**:
- 所有可互動元件必須有明顯的 focus 樣式
- 使用 `focus:ring-2 focus:ring-blue-500` 藍色外框
- Tab 鍵順序必須符合視覺順序

### 📲 觸控互動 (手機版)

**觸控區域**:
- 最小觸控面積 44x44px
- 按鈕間距至少 8px
- 避免過於接近的多個小按鈕

**滑動手勢**:
- 列表支援下拉刷新
- 卡片支援左滑顯示操作選項（可選）

---

## 6️⃣ 響應式行為

### 🖥️ 桌面版 (≥768px)

- Header 固定置頂 `sticky top-0 z-10`
- 顯示完整表格
- 全域搜尋框顯示在 Header
- 側邊欄顯示（如有）

### 📱 手機版 (<768px)

- Header 高度從 64px 縮減為 56px
- 隱藏表格 `hidden md:block`
- 顯示卡片列表 `md:hidden`
- 全域搜尋框移到漢堡選單內
- Smart Search 展開後佔滿寬度

### 🔄 斷點切換

使用 Tailwind 響應式前綴:
```html
<!-- 手機版隱藏，桌面版顯示 -->
<div class="hidden md:block">...</div>

<!-- 手機版顯示，桌面版隱藏 -->
<div class="md:hidden">...</div>

<!-- 響應式間距 -->
<div class="p-4 md:p-6">...</div>

<!-- 響應式字體 -->
<h1 class="text-xl md:text-2xl">...</h1>
```

---

## 7️⃣ 無障礙規範 (Accessibility)

### ♿ 必須遵守

1. **語意化 HTML**:
   - 使用正確的標籤 (`<button>`, `<table>`, `<form>`)
   - 不使用 `<div>` 模擬按鈕

2. **ARIA 屬性**:
   - 圖示按鈕必須有 `aria-label`
   - 狀態變化必須有 `aria-live`
   - 模態框必須有 `aria-modal="true"`

3. **鍵盤操作**:
   - 所有功能必須可用鍵盤操作
   - Focus 順序符合邏輯
   - 模態框支援 ESC 關閉

4. **對比度**:
   - 文字與背景對比度 ≥ 4.5:1
   - 大字體（18px+）對比度 ≥ 3:1

---

## 8️⃣ 效能考量

### ⚡ 必須注意

1. **圖片優化**:
   - 使用 WebP 格式
   - 設定 width/height 避免 layout shift
   - 使用 lazy loading

2. **動畫效能**:
   - 使用 `transform` 和 `opacity` (GPU 加速)
   - 避免使用 `width`/`height`/`top`/`left` 動畫

3. **列表渲染**:
   - 超過 50 筆資料考慮虛擬滾動
   - 使用分頁或無限滾動

---

## 9️⃣ 特定頁面需求

### [在此填寫具體頁面的特殊需求]

**範例 - 商品列表頁**:

**頁面標題**: 商品管理
**URL**: /buygo-portal/products
**主要功能**: 瀏覽、搜尋、編輯、刪除商品

**特殊元件**:
1. 商品縮圖 (64x64px, 圓角 8px)
2. SKU 複製按鈕（點擊複製到剪貼簿）
3. 庫存數量顏色標示（<10 顯示紅色警告）
4. 批次操作按鈕（選擇多個商品後顯示）

**表格欄位**:
| 欄位 | 寬度 | 對齊 | 備註 |
|------|------|------|------|
| 勾選框 | 5% | 左 | 全選功能 |
| 商品名稱 | 25% | 左 | 包含縮圖 + 名稱 |
| SKU | 15% | 左 | 可複製 |
| 價格 | 15% | 左 | 粗體顯示 |
| 庫存 | 10% | 左 | <10 顯示紅色 |
| 狀態 | 15% | 左 | 狀態標籤 |
| 操作 | 15% | 左 | 檢視/編輯/刪除 |

**互動流程**:
1. 使用者進入頁面 → 載入前 10 筆商品
2. 輸入搜尋關鍵字 → 即時過濾結果（debounce 300ms）
3. 點擊「編輯」→ 開啟編輯模態框
4. 點擊「刪除」→ 顯示確認對話框
5. 勾選多個商品 → 顯示批次操作按鈕

**手機版特殊處理**:
- 商品縮圖改為 48x48px
- 隱藏 SKU 欄位（併入卡片標題下方）
- 操作按鈕改為底部 2 欄 Grid

---

## 🔟 輸出格式要求

### 📤 給 Cursor + Pencil 的輸出

請根據以上所有規格，生成以下格式的設計稿：

**視覺格式**:
- [ ] 桌面版全頁截圖 (1920x1080)
- [ ] 手機版全頁截圖 (375x812)
- [ ] 關鍵元件特寫（按鈕、狀態標籤、表格列）

**標註要求**:
- [ ] 標示所有間距 (padding, margin)
- [ ] 標示所有字體大小和字重
- [ ] 標示所有顏色色碼
- [ ] 標示響應式斷點

**互動狀態**:
- [ ] Normal (預設)
- [ ] Hover (懸停)
- [ ] Active (按下)
- [ ] Disabled (禁用)
- [ ] Focus (聚焦)

**變體展示**:
- [ ] 空狀態 (無資料時)
- [ ] 載入中狀態
- [ ] 錯誤狀態
- [ ] 成功狀態

---

## ✅ 檢查清單

在提交給 Cursor 之前，請確認：

**設計系統遵循**:
- [ ] 色彩使用符合規範（Primary/Success/Warning/Danger）
- [ ] 間距使用 Tailwind Scale (4/8/16/24/32/48px)
- [ ] 字體大小符合層級 (xs/sm/base/lg/xl/2xl)
- [ ] 圓角使用統一規格 (lg/2xl/full)

**版面結構**:
- [ ] Header 高度 64px (桌面) / 56px (手機)
- [ ] 表格欄位寬度總和 = 100%
- [ ] 所有文字水平顯示（無垂直文字）
- [ ] 響應式斷點正確 (768px)

**元件使用**:
- [ ] 狀態標籤使用 `<span>` (NOT `<button>`)
- [ ] 按鈕有正確的 hover/focus 狀態
- [ ] 表格有 hover 效果和分隔線
- [ ] 分頁元件顯示總筆數

**互動設計**:
- [ ] 所有按鈕有 hover 效果
- [ ] 所有輸入框有 focus 樣式
- [ ] 所有互動元件有 transition 動畫
- [ ] 觸控區域 ≥ 44x44px (手機版)

**無障礙**:
- [ ] 使用語意化 HTML
- [ ] 圖示按鈕有 aria-label
- [ ] 對比度符合 WCAG 標準
- [ ] 鍵盤導航順序正確

---

## 📝 範例：完整填寫後的設計請求

> **這個區塊是給 Cursor + Pencil 的完整範例，可以直接複製貼上**

```markdown
# UI 設計請求 - 商品列表頁

## 1️⃣ 設計概述

**頁面名稱**: 商品列表頁
**URL 路徑**: /buygo-portal/products
**主要使用者**: BuyGo 管理員、小幫手
**核心功能**: 瀏覽所有商品、搜尋商品、編輯商品資訊、刪除商品

---

## 2️⃣ 設計系統規範

[參考上方完整規範]

---

## 3️⃣ 版面結構

### 🖥️ 桌面版 (≥768px)

```
┌─────────────────────────────────────────────────────┐
│ Header (64px, sticky top-0)                         │
│ ├─ 左: 🛍️ 商品管理                                  │
│ └─ 右: [全域搜尋框] [+ 新增商品]                    │
├─────────────────────────────────────────────────────┤
│ Smart Search (48px)                                 │
│ └─ [搜尋商品名稱或 SKU...]                         │
├─────────────────────────────────────────────────────┤
│ Content (p-6)                                       │
│ ┌─────────────────────────────────────────────────┐ │
│ │ Table: Products                                  │ │
│ │ ┌──┬────────┬────┬────┬────┬────┬────┐          │ │
│ │ │☑ │商品名稱│SKU │價格│庫存│狀態│操作│          │ │
│ │ ├──┼────────┼────┼────┼────┼────┼────┤          │ │
│ │ │☐ │iPhone  │... │... │... │上架│... │          │ │
│ │ │☐ │MacBook │... │... │... │上架│... │          │ │
│ │ └──┴────────┴────┴────┴────┴────┴────┘          │ │
│ └─────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────┐ │
│ │ Pagination: 顯示 1-10 / 共 50 筆 [上][下]       │ │
│ └─────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────┘
```

---

## 4️⃣ 元件規格

[參考上方完整規範，特別注意表格元件]

**本頁面表格欄位**:
| 欄位 | 寬度 | HTML |
|------|------|------|
| 勾選框 | w-[5%] | `<input type="checkbox">` |
| 商品名稱 | w-[25%] | `<img> + <span>` |
| SKU | w-[15%] | `text-sm text-slate-600` |
| 價格 | w-[15%] | `text-sm font-bold` |
| 庫存 | w-[10%] | `text-sm text-slate-600` (< 10 顯示紅色) |
| 狀態 | w-[15%] | Status Tag 元件 |
| 操作 | w-[15%] | Icon Button 群組 (檢視/編輯/刪除) |

---

## 5️⃣ 互動規格

**搜尋行為**:
- 輸入後 300ms debounce 觸發搜尋
- 即時過濾表格內容
- 顯示「搜尋到 X 筆結果」提示

**批次操作**:
- 勾選 ≥ 1 個商品時，Header 右側顯示批次操作按鈕
- 批次操作包含：批次刪除、批次上架、批次下架

**刪除確認**:
- 點擊刪除按鈕 → 顯示確認對話框
- 對話框標題：「確定要刪除此商品？」
- 對話框按鈕：「取消」(次要) + 「刪除」(危險)

---

## 6️⃣ 響應式行為

**手機版卡片內容**:
```
┌─────────────────────┐
│ [商品名稱]   [狀態] │
│ SKU: XXX    1,000元 │
│ 庫存: 50            │
│ ├─────────┬────────┤│
│ │ 編輯    │ 刪除   ││
│ └─────────┴────────┘│
└─────────────────────┘
```

---

## 7️⃣ 無障礙規範

- 表格使用 `<table>` 語意標籤
- 每個圖示按鈕有 `aria-label="編輯商品"`
- 狀態標籤有 `role="status"`

---

## 8️⃣ 輸出格式要求

**請提供**:
1. 桌面版全頁設計稿 (1920x1080, 顯示 10 筆商品)
2. 手機版全頁設計稿 (375x812, 顯示 5 張卡片)
3. 表格列 hover 狀態特寫
4. 批次操作按鈕顯示狀態
5. 刪除確認對話框設計
6. 空狀態設計（無商品時顯示）

**標註需求**:
- 所有間距 (Header 內部、表格 padding、卡片 margin)
- 所有字體大小和顏色
- 狀態標籤的具體樣式
- 響應式斷點 (768px)

---

完成後請將設計稿和標註文件提供給我，我會根據視覺稿撰寫實際的 WordPress 外掛程式碼。謝謝！
```

---

## 📚 附錄：常用 Tailwind Classes 速查

### 間距 (Spacing)
```
p-0   = 0px      m-0   = 0px
p-1   = 4px      m-1   = 4px
p-2   = 8px      m-2   = 8px
p-3   = 12px     m-3   = 12px
p-4   = 16px     m-4   = 16px
p-6   = 24px     m-6   = 24px
p-8   = 32px     m-8   = 32px
```

### 字體 (Typography)
```
text-xs   = 12px
text-sm   = 14px
text-base = 16px
text-lg   = 18px
text-xl   = 20px
text-2xl  = 24px

font-normal   = 400
font-medium   = 500
font-semibold = 600
font-bold     = 700
```

### 顏色 (Colors)
```
slate-50  = #f8fafc (最淺)
slate-100 = #f1f5f9
slate-200 = #e2e8f0
slate-300 = #cbd5e1
slate-600 = #475569
slate-900 = #0f172a (最深)

blue-50   = #eff6ff
blue-600  = #2563eb
blue-700  = #1d4ed8

green-100 = #dcfce7
green-800 = #166534
```

### 圓角 (Border Radius)
```
rounded     = 4px
rounded-md  = 6px
rounded-lg  = 8px
rounded-xl  = 12px
rounded-2xl = 16px
rounded-full= 9999px
```

---

**版本**: 1.0
**最後更新**: 2026-01-24
**維護者**: Claude Code + BuyGo Team
