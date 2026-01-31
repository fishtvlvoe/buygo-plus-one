# BuyGo Plus One UI/UX 黃金設計原則

> **版本**：1.0
> **建立日期**：2026-01-20
> **適用範圍**：商品、訂單、備貨、出貨、客戶管理頁面
> **目的**：統一全站介面設計，確保一致的用戶體驗

---

## 📋 目錄

1. [核心設計理念](#核心設計理念)
2. [頁面結構標準](#頁面結構標準)
3. [Header 設計規範](#header-設計規範)
4. [Smart Search Box 規範](#smart-search-box-規範)
5. [表格設計規範](#表格設計規範)
6. [卡片設計規範](#卡片設計規範)
7. [狀態標籤規範](#狀態標籤規範)
8. [響應式設計原則](#響應式設計原則)
9. [文字與語言規範](#文字與語言規範)
10. [顏色與間距規範](#顏色與間距規範)

---

## 🎯 核心設計理念

### 三大原則

1. **一致性優先**：所有頁面必須使用相同的結構、樣式和互動模式
2. **響應式設計**：桌面版與手機版各有最佳呈現方式，避免割裂感
3. **簡潔高效**：資訊清晰、操作流暢、無冗餘元素

### 設計哲學

> 「每個頁面都應該讓用戶感覺在使用同一個系統，而不是多個拼湊的工具。」

---

## 📐 頁面結構標準

### 完整結構（由上至下）

```
┌─────────────────────────────────────────────────────────┐
│ 1. Header（固定高度 64px / h-16）                          │
│    - 漢堡選單（手機版）                                    │
│    - 頁面標題                                             │
│    - 全域搜尋框（桌面版顯示，手機版點擊圖標彈出）            │
│    - 通知圖標                                             │
│    - 幣別切換按鈕（僅商品、訂單、客戶頁面）                  │
├─────────────────────────────────────────────────────────┤
│ 2. Smart Search Box（智慧搜尋區）                          │
│    - 下拉式搜尋建議                                        │
│    - 支援多欄位搜尋                                        │
│    - 即時過濾結果                                          │
├─────────────────────────────────────────────────────────┤
│ 3. 內容區域                                                │
│    【桌面版】表格模式                                       │
│    【手機版】卡片模式                                       │
└─────────────────────────────────────────────────────────┘
```

### 結構實作參考

**已完成頁面**（參考標準）：
- ✅ 商品頁面（products.php）- 完成度 90%
- ✅ 訂單頁面（orders.php）- 完成度 80%

**待調整頁面**：
- ⏳ 備貨頁面（shipment-products.php）
- ⏳ 出貨頁面（shipment-details.php）
- ⏳ 客戶頁面（customers.php）

---

## 🔝 Header 設計規範

### 桌面版 Header

```html
<header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-6 shrink-0 z-10 sticky top-0 md:static relative">
    <!-- 左側：標題 -->
    <div class="flex items-center gap-3 md:gap-4 overflow-hidden flex-1">
        <div class="flex flex-col overflow-hidden min-w-0 pl-12 md:pl-0">
            <h1 class="text-xl font-bold text-slate-900 leading-tight truncate">頁面標題</h1>
        </div>
    </div>

    <!-- 右側：操作區 -->
    <div class="flex items-center gap-2 md:gap-3 shrink-0">
        <!-- 全域搜尋（桌面版顯示）-->
        <div class="relative hidden sm:block w-32 md:w-48 lg:w-64">
            <input type="text" placeholder="全域搜尋..."
                class="pl-9 pr-4 py-2 bg-slate-100 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary w-full">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" ...>搜尋圖標</svg>
        </div>

        <!-- 通知圖標 -->
        <button class="p-2 text-slate-400 hover:text-slate-600 rounded-full hover:bg-slate-100">
            <svg class="w-6 h-6" ...>通知圖標</svg>
        </button>

        <!-- 幣別切換（僅特定頁面）-->
        <button class="ml-2 px-3 py-1.5 bg-white border border-slate-200 rounded-md text-xs font-bold">
            JPY
        </button>
    </div>
</header>
```

### 手機版 Header

- **搜尋圖標**：點擊後彈出全螢幕搜尋覆蓋層
- **漢堡選單**：固定在左上角（pl-12 md:pl-0）
- **標題**：自動截斷過長文字（truncate）

### Header 規範清單

| 項目 | 規範 | 說明 |
|------|------|------|
| 高度 | `h-16` (64px) | 固定高度，所有頁面一致 |
| 背景 | `bg-white` | 白色背景 |
| 邊框 | `border-b border-slate-200` | 下方淺灰分隔線 |
| 標題字體 | `text-xl font-bold` | 20px 粗體 |
| 標題顏色 | `text-slate-900` | 深灰黑色 |
| 搜尋框寬度 | `w-32 md:w-48 lg:w-64` | 響應式寬度 |
| 通知圖標 | `w-6 h-6` | 24px × 24px |
| 間距 | `gap-2 md:gap-3` | 響應式間距 |

---

## 🔍 Smart Search Box 規範

### 位置與樣式

```html
<div class="bg-white shadow-sm border-b border-slate-200 px-6 py-4">
    <smart-search-box
        api-endpoint="/wp-json/buygo-plus-one/v1/orders"
        :search-fields="['invoice_no', 'customer_name', 'customer_email']"
        placeholder="搜尋訂單編號、客戶名稱或 Email..."
        display-field="invoice_no"
        display-sub-field="customer_name"
        :show-currency-toggle="false"
        @select="handleSearchSelect"
        @search="handleSearchInput"
        @clear="handleSearchClear"
    />
</div>
```

### 配置規範

| 頁面 | API Endpoint | Search Fields | Placeholder | Currency Toggle |
|------|--------------|---------------|-------------|-----------------|
| 商品 | `/products` | name, sku | 搜尋商品名稱或 SKU... | true |
| 訂單 | `/orders` | invoice_no, customer_name, customer_email | 搜尋訂單編號、客戶名稱或 Email... | false |
| 備貨 | `/shipments` | shipment_number, customer_name, product_name | 搜尋出貨單號、客戶或商品... | false |
| 出貨 | `/shipments` | shipment_number, customer_name | 搜尋出貨單號或客戶... | false |
| 客戶 | `/customers` | name, email, phone | 搜尋客戶名稱、Email 或電話... | false |

### 功能要求

1. ✅ 即時搜尋（輸入時觸發）
2. ✅ 下拉建議列表
3. ✅ 鍵盤導航支援
4. ✅ 清除按鈕
5. ✅ 選擇後跳轉

---

## 📊 表格設計規範

### 桌面版表格結構

```html
<div class="hidden md:block buygo-card overflow-x-auto">
    <table class="w-full min-w-max">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider w-10">
                    <input type="checkbox" />
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider w-[20%]">
                    訂單編號
                </th>
                <!-- 其他欄位 -->
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            <!-- 資料列 -->
        </tbody>
    </table>
</div>
```

### 表格規範清單

| 項目 | 規範 | 說明 |
|------|------|------|
| 外層容器 | `hidden md:block` | 手機版隱藏 |
| 卡片樣式 | `buygo-card` | 統一卡片樣式 |
| 水平捲動 | `overflow-x-auto` | 欄位過多時可橫向捲動 |
| 表格寬度 | `w-full min-w-max` | 確保內容不擠壓 |
| 表頭背景 | `bg-slate-50` | 淺灰背景 |
| 表頭文字 | `text-xs font-semibold text-slate-600 uppercase` | 12px 粗體大寫灰色 |
| 欄位間距 | `px-4 py-3` | 左右 16px，上下 12px |
| 分隔線 | `divide-y divide-slate-200` | 淺灰分隔線 |

### 欄位寬度標準

| 欄位類型 | 寬度 | 範例 |
|---------|------|------|
| 勾選框 | `w-10` | ☐ |
| 編號/ID | `w-[15%]` | #129, SH-20260120-001 |
| 客戶名稱 | `w-[15%]` | 余佳鈴 |
| 商品清單 | `w-[30%]` | mc × 7 個 |
| 金額 | `w-[12%]` | 8,400 JPY |
| 狀態 | `w-[10%]` | 處理中 |
| 日期 | `w-[12%]` | 2026/1/20 |
| 操作 | `w-[10%]` | 👁️ ✏️ 🗑️ |

### 表格行為規範

1. **點擊展開**：
   - 商品清單欄位：點擊數字展開商品詳細列表
   - 使用 `v-if` 條件渲染展開區域
   - 展開圖標：`↓` 向下箭頭（未展開）/ `↑` 向上箭頭（已展開）

2. **操作按鈕**：
   - 編輯：鉛筆圖標
   - 查看：眼睛圖標
   - 刪除：垃圾桶圖標（紅色）

3. **狀態標籤**：
   - 使用 Tag 樣式（見下方「狀態標籤規範」）

---

## 📱 卡片設計規範

### 手機版卡片結構

```html
<div class="md:hidden space-y-4">
    <div v-for="item in items" :key="item.id"
         class="buygo-card hover:shadow-md transition-shadow">
        <!-- 卡片內容 -->
        <div class="p-4">
            <!-- 上方：編號 + 狀態 -->
            <div class="flex items-center justify-between mb-3">
                <div class="font-bold text-slate-900">#{{ item.id }}</div>
                <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                    處理中
                </span>
            </div>

            <!-- 中間：客戶資訊 + 金額 -->
            <div class="flex justify-between items-start mb-3">
                <div class="text-sm text-slate-600">{{ item.customer_name }}</div>
                <div class="text-sm font-bold text-slate-900">{{ item.amount }}</div>
            </div>

            <!-- 商品清單（可展開）-->
            <div>
                <button @click="toggle(item.id)"
                        class="flex items-center gap-1 text-xs text-primary">
                    {{ item.items_count }} 個項目
                    <svg :class="{ 'rotate-180': expanded[item.id] }" ...>箭頭</svg>
                </button>

                <!-- 展開的商品清單 -->
                <div v-if="expanded[item.id]" class="mt-2 space-y-2">
                    <!-- 商品項目 -->
                </div>
            </div>
        </div>

        <!-- 下方：操作按鈕 -->
        <div class="grid grid-cols-2 border-t border-slate-100 bg-slate-50/50 divide-x divide-slate-100">
            <button class="py-3 text-xs font-bold text-primary">編輯</button>
            <button class="py-3 text-xs font-bold text-red-500">刪除</button>
        </div>
    </div>
</div>
```

### 卡片規範清單

| 項目 | 規範 | 說明 |
|------|------|------|
| 顯示條件 | `md:hidden` | 僅手機版顯示 |
| 卡片間距 | `space-y-4` | 卡片間隔 16px |
| 內邊距 | `p-4` | 內容區 16px |
| 陰影 | `hover:shadow-md` | hover 時加深陰影 |
| 編號字體 | `font-bold text-slate-900` | 粗體深灰 |
| 狀態標籤 | 參考「狀態標籤規範」 | Tag 樣式 |
| 金額字體 | `text-sm font-bold` | 14px 粗體 |
| 操作按鈕 | `grid grid-cols-2` | 平分寬度 |

### 卡片展開行為

1. **展開按鈕**：
   - 文字：「X 個項目」
   - 圖標：向下箭頭
   - 顏色：primary（藍色）

2. **展開內容**：
   - 商品圖片（左）+ 商品名稱（右）
   - 數量資訊
   - 總數量（展開區域底部）

3. **過渡動畫**：
   - 箭頭旋轉：`rotate-180`
   - 內容顯示：`v-if` 條件渲染

---

## 🏷️ 狀態標籤規範

### Tag 樣式（不是按鈕！）

```html
<!-- ✅ 正確：Tag 樣式 -->
<span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 border border-yellow-200">
    處理中
</span>

<!-- ❌ 錯誤：按鈕樣式（太大、太突兀）-->
<button class="px-4 py-2 text-sm font-bold rounded bg-yellow-500 text-white">
    處理中
</button>
```

### 狀態顏色標準

| 狀態 | 背景 | 文字 | 邊框 | 適用場景 |
|------|------|------|------|---------|
| 處理中 / 備貨中 / Pending | `bg-yellow-100` | `text-yellow-800` | `border-yellow-200` | 進行中的訂單、出貨單 |
| 已完成 / 已出貨 / Shipped | `bg-green-100` | `text-green-800` | `border-green-200` | 完成的訂單、已出貨 |
| 已取消 / Cancelled | `bg-red-100` | `text-red-800` | `border-red-200` | 取消的訂單 |
| 保留 / On Hold | `bg-blue-100` | `text-blue-800` | `border-blue-200` | 保留中的訂單 |
| 已上架 / Published | `bg-green-100` | `text-green-800` | `border-green-200` | 商品狀態 |
| 已下架 / Draft | `bg-slate-100` | `text-slate-800` | `border-slate-200` | 商品狀態 |

### Tag 規範清單

| 項目 | 規範 | 說明 |
|------|------|------|
| 元素類型 | `<span>` | 非互動元素 |
| 字體大小 | `text-xs` | 12px |
| 字體粗細 | `font-medium` | 500 |
| 內邊距 | `px-2 py-1` | 左右 8px，上下 4px |
| 圓角 | `rounded-full` | 完全圓角 |
| 邊框 | 必須加上 | 增加視覺層次 |

---

## 📐 響應式設計原則

### 斷點標準

```css
/* Tailwind CSS 預設斷點 */
sm: 640px   /* 小平板 */
md: 768px   /* 平板 / 小筆電 */
lg: 1024px  /* 筆電 */
xl: 1280px  /* 桌機 */
```

### 響應式規範

| 項目 | 手機版 (< 768px) | 桌面版 (≥ 768px) |
|------|------------------|------------------|
| Header 高度 | 64px | 64px |
| 漢堡選單 | 顯示 | 隱藏 |
| 全域搜尋 | 點擊圖標彈出 | 直接顯示輸入框 |
| Smart Search Box | 顯示 | 顯示 |
| 內容呈現 | 卡片模式 | 表格模式 |
| 卡片間距 | `space-y-4` | N/A |
| 表格間距 | N/A | `px-4 py-3` |

### 避免割裂感的設計

**❌ 錯誤做法**：
- 視窗縮小時表格欄位直向排列
- 手機版與桌面版使用完全不同的顏色
- 卡片與表格的資訊層級不一致
- 視窗縮放時需要左右拉動才能看到內容

**✅ 正確做法**：
- 使用等比例縮放，不出現橫向滾動條
- 手機版卡片保持與桌面版相同的顏色與標籤樣式
- 卡片展開內容與表格展開內容結構一致
- 參考商品頁面（products.php）的縮放邏輯

### 🔴 黃金規則：表格等比例縮放（CRITICAL）

**這是不可妥協的設計規範，必須嚴格遵守**：

1. **表格必須等比例縮放，不需要橫向滾動**
   - 理由：視窗縮小時，使用者不應該需要左右拉動才能看到內容
   - 參考標準：商品頁面（products.php）的縮放效果

2. **表格縮放的實作方式**：
   ```html
   <!-- 外層容器 -->
   <div class="hidden md:block bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
       <div class="overflow-x-auto">
           <table class="min-w-full divide-y divide-slate-200">
               <!-- 表格內容 -->
           </table>
       </div>
   </div>
   ```

3. **表格欄位寬度設定**：
   ```html
   <!-- ❌ 錯誤：使用固定 px 或 inline style 百分比 -->
   <th style="width: 50px;">圖</th>
   <th style="width: 25%;">商品清單</th>

   <!-- ✅ 正確：使用 Tailwind 類別 -->
   <th class="w-12"><!-- 勾選框 --></th>
   <th class="whitespace-nowrap"><!-- 固定寬度欄位 --></th>
   <th class="w-[35%]"><!-- 主要內容欄位 --></th>
   ```

4. **商品圖片整合在主要欄位內**：
   ```html
   <!-- ❌ 錯誤：圖片獨立一欄 -->
   <td><!-- 圖片 --></td>
   <td><!-- 商品清單 --></td>

   <!-- ✅ 正確：圖片整合在商品清單欄位內 -->
   <td class="px-4 py-4">
       <div class="flex items-start gap-3">
           <div class="shrink-0">
               <!-- 商品圖片 (w-12 h-12) -->
           </div>
           <div class="min-w-0 flex-1">
               <!-- 商品清單文字 -->
           </div>
       </div>
   </td>
   ```

5. **表格樣式統一**：
   | 項目 | 類別 |
   |------|------|
   | 外層容器 | `bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden` |
   | 表格本體 | `min-w-full divide-y divide-slate-200` |
   | 表頭 | `bg-slate-50/50` |
   | 表頭文字 | `text-xs font-semibold text-slate-500 uppercase tracking-wider` |
   | 表身 | `bg-white divide-y divide-slate-100` |
   | 行 hover | `hover:bg-slate-50 transition` |

---

## 📝 文字與語言規範

### 🔴 黃金規則：所有文字必須橫排（CRITICAL）

**這是不可妥協的設計規範，必須嚴格遵守**：

1. **絕對禁止文字變成直排**（垂直排列）
   - 理由：電腦螢幕是橫向的，不應該出現直向文字
   - 適用於：所有標題、內容、按鈕、標籤、欄位名稱

2. **強制橫排的實作方式**：
   ```css
   /* 方法 1：使用 white-space: nowrap */
   style="white-space: nowrap;"

   /* 方法 2：設定最小寬度 */
   style="min-width: 80px;"

   /* 方法 3：使用 inline-block */
   class="inline-block"
   ```

3. **文字過長的處理方式**：
   - ✅ **允許**：文字換行（每一行仍然是橫排）
   - ✅ **允許**：使用 truncate 截斷文字
   - ✅ **允許**：橫向滾動（`overflow-x-auto`）
   - ❌ **禁止**：文字變成一個字一行（直排）

4. **常見直排問題與解決方案**：
   | 問題場景 | 原因 | 解決方案 |
   |---------|------|---------|
   | 按鈕文字直排 | 按鈕寬度不足 | 加 `white-space: nowrap` + `min-width` |
   | 狀態 Tag 直排 | Tag 容器太窄 | 加 `inline-block` + `white-space: nowrap` |
   | 表格欄位直排 | 欄位寬度設定錯誤 | 使用 `style="width: 10%; white-space: nowrap;"` |

### 中文優先原則

**所有面向用戶的文字必須使用中文，除非以下例外**：

1. **API 欄位名稱**（程式碼內部使用）
2. **貨幣代碼**：JPY, USD, TWD（國際標準）
3. **技術術語**（但需加中文說明）

### 常見錯誤對照表

| ❌ 錯誤 | ✅ 正確 | 說明 |
|---------|---------|------|
| On Hold | 處理中 / 保留 | 狀態標籤 |
| Pending | 備貨中 | 狀態標籤 |
| Shipped | 已出貨 | 狀態標籤 |
| Published | 已上架 | 商品狀態 |
| Draft | 已下架 | 商品狀態 |
| View | 查看 | 操作按鈕 |
| Edit | 編輯 | 操作按鈕 |
| Delete | 刪除 | 操作按鈕 |

### 語言統一檢查清單

- [ ] 狀態標籤全部中文化
- [ ] 按鈕文字全部中文化
- [ ] 表頭欄位全部中文化
- [ ] Toast 訊息全部中文化
- [ ] Modal 標題與內容全部中文化

---

## 🎨 顏色與間距規範

### 主色系（Primary Colors）

| 用途 | Tailwind Class | HEX | 說明 |
|------|----------------|-----|------|
| 主要色 | `text-primary` | #3B82F6 | 連結、按鈕、圖標 |
| 成功 | `text-green-600` | #059669 | 成功訊息、已完成 |
| 警告 | `text-yellow-600` | #D97706 | 警告訊息、進行中 |
| 錯誤 | `text-red-600` | #DC2626 | 錯誤訊息、已取消 |

### 灰階（Grayscale）

| 用途 | Tailwind Class | 說明 |
|------|----------------|------|
| 深灰（標題）| `text-slate-900` | 主要文字 |
| 中灰（內文）| `text-slate-600` | 副標題、說明 |
| 淺灰（提示）| `text-slate-400` | 圖標、次要資訊 |
| 背景灰 | `bg-slate-50` | 表頭、卡片底部 |
| 分隔線 | `border-slate-200` | 邊框、分隔線 |

### 間距規範

| 項目 | Tailwind Class | 像素值 | 說明 |
|------|----------------|--------|------|
| 超小間距 | `gap-1` | 4px | 圖標與文字間距 |
| 小間距 | `gap-2` | 8px | 按鈕間距 |
| 標準間距 | `gap-3` | 12px | 卡片內元素間距 |
| 大間距 | `gap-4` | 16px | 卡片間距 |
| 區塊間距 | `gap-6` | 24px | 頁面區塊間距 |
| 內邊距（小）| `p-2` | 8px | 按鈕內邊距 |
| 內邊距（中）| `p-4` | 16px | 卡片內邊距 |
| 內邊距（大）| `p-6` | 24px | 頁面內邊距 |

---

## ✅ 設計檢查清單

### Header 檢查

- [ ] 高度固定 64px
- [ ] 標題使用 `text-xl font-bold`
- [ ] 全域搜尋框桌面版顯示，手機版點擊彈出
- [ ] 漢堡選單手機版顯示，桌面版隱藏
- [ ] 通知圖標大小 24px × 24px
- [ ] 幣別切換按鈕（僅特定頁面）

### Smart Search Box 檢查

- [ ] API endpoint 正確
- [ ] Search fields 包含所有可搜尋欄位
- [ ] Placeholder 文字清晰易懂
- [ ] 事件處理器 @select, @search, @clear 正確綁定
- [ ] Currency toggle 根據頁面需求設定

### 表格檢查

- [ ] 手機版隱藏（`hidden md:block`）
- [ ] 使用 `overflow-x-auto` 支援橫向捲動
- [ ] 表頭背景 `bg-slate-50`
- [ ] 表頭文字 `text-xs font-semibold uppercase`
- [ ] 欄位寬度合理分配
- [ ] 分隔線使用 `divide-y divide-slate-200`
- [ ] 展開功能正常（若有）

### 卡片檢查

- [ ] 桌面版隱藏（`md:hidden`）
- [ ] 卡片間距 `space-y-4`
- [ ] 內邊距 `p-4`
- [ ] Hover 陰影效果
- [ ] 操作按鈕使用 `grid grid-cols-2`
- [ ] 展開功能正常（若有）
- [ ] 總數量顯示在展開區域底部

### 狀態標籤檢查

- [ ] 使用 `<span>` 而非 `<button>`
- [ ] 字體大小 `text-xs`
- [ ] 內邊距 `px-2 py-1`
- [ ] 圓角 `rounded-full`
- [ ] 有邊框（`border`）
- [ ] 顏色符合狀態標準

### 語言檢查

- [ ] 所有用戶可見文字使用中文
- [ ] 狀態標籤無英文（On Hold → 處理中）
- [ ] 按鈕文字無英文（View → 查看）
- [ ] Toast 訊息中文化

### 響應式檢查

- [ ] 手機版（< 768px）顯示卡片模式
- [ ] 桌面版（≥ 768px）顯示表格模式
- [ ] 視窗縮小時無明顯割裂感
- [ ] 表格欄位不會變直向排列
- [ ] 卡片與表格樣式一致

---

## 📚 參考範本

### 標準頁面（已完成）

1. **商品頁面**（`products.php`）- 完成度 90%
   - ✅ Header 結構完整
   - ✅ Smart Search Box 功能正常
   - ✅ 桌面版表格設計
   - ✅ 手機版卡片設計
   - ✅ 幣別切換功能
   - ⏳ 部分中英文混雜需統一

2. **訂單頁面**（`orders.php`）- 完成度 80%
   - ✅ Header 結構完整
   - ✅ Smart Search Box 功能正常
   - ✅ 桌面版表格設計
   - ✅ 手機版卡片設計
   - ⏳ 詳情頁資訊需補完
   - ⏳ 狀態標籤需中文化

### 待調整頁面

3. **備貨頁面**（`shipment-products.php`）
   - ❌ 缺少 Header 右上角全域搜尋
   - ❌ 狀態標籤樣式需改為 Tag
   - ❌ 響應式設計需優化

4. **出貨頁面**（`shipment-details.php`）
   - 待評估

5. **客戶頁面**（`customers.php`）
   - 待評估

---

## 🔄 版本歷史

| 版本 | 日期 | 變更內容 | 修改者 |
|------|------|---------|--------|
| 1.0 | 2026-01-20 | 初版建立，統一全站 UI/UX 設計原則 | Claude Code |

---

## 📞 聯絡資訊

如有設計相關問題，請參考：
- **範本頁面**：商品頁面（products.php）、訂單頁面（orders.php）
- **設計文件**：本文件
- **負責人**：Fish (User)

---

**注意**：本文件為 BuyGo Plus One 專案的黃金設計標準，所有新頁面或修改必須遵循此規範，以確保全站一致的用戶體驗。
