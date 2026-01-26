# BuyGo+1 架構改造視覺指南

> 用圖解說明：現狀 vs 改造後

---

## 第一部分：現狀分析

### 現在的頁面結構（單一巨大檔案）

```
products.php (1000+ 行)
├─────────────────────────────────────────────────────────
│
├─ HTML 結構
│  ├─ <div id="header">
│  │  ├─ 頁面標題
│  │  ├─ 通知圖示
│  │  └─ 幣別切換
│  │
│  ├─ <div id="search-box">
│  │  ├─ 搜尋框
│  │  ├─ 搜尋欄位設定
│  │  └─ 搜尋結果綁定
│  │
│  ├─ <div id="list-view">
│  │  ├─ 表格 (桌面版)
│  │  │  ├─ <thead>
│  │  │  └─ <tbody v-for="product in products">
│  │  │     ├─ 編輯按鈕 → @click="showEditModal = true"
│  │  │     ├─ 刪除按鈕 → @click="deleteProduct(product)"
│  │  │     └─ 分配按鈕 → @click="showAllocationModal = true"
│  │  │
│  │  └─ 卡片列表 (手機版)
│  │     └─ v-for="product in products"
│  │
│  ├─ <div v-show="showEditModal" id="edit-modal">
│  │  ├─ Modal 背景 (z-index 管理)
│  │  ├─ 編輯表單
│  │  ├─ 保存按鈕
│  │  └─ 取消按鈕
│  │
│  ├─ <div v-show="showAllocationModal" id="allocation-modal">
│  │  └─ 分配庫存內容...
│  │
│  └─ <div v-show="showBuyersModal" id="buyers-modal">
│     └─ 下單客戶內容...
│
├─ Vue Script (800+ 行)
│  ├─ data()
│  │  ├─ products: []
│  │  ├─ showEditModal: false
│  │  ├─ showAllocationModal: false
│  │  ├─ showBuyersModal: false
│  │  ├─ editingProduct: {}
│  │  ├─ allocationData: []
│  │  ├─ buyersData: []
│  │  └─ ... 更多狀態
│  │
│  ├─ mounted()
│  │  ├─ loadProducts()
│  │  └─ (無路由邏輯)
│  │
│  └─ methods()
│     ├─ loadProducts()
│     ├─ editProduct() → showEditModal = true
│     ├─ saveProduct()
│     ├─ deleteProduct()
│     ├─ loadAllocation()
│     ├─ saveAllocation()
│     ├─ loadBuyers()
│     └─ ... 更多方法
│
└─ CSS (隱含在 Tailwind 類名中)
   ├─ 按鈕樣式分散
   ├─ Modal 樣式硬編碼
   ├─ 卡片樣式不一致
   └─ 沒有集中管理

【問題】
❌ Modal 狀態分散（showEditModal, showAllocationModal...）
❌ 重新整理頁面 → Modal 消失
❌ 無法透過 URL 分享「編輯狀態」
❌ 樣式改動需要在每個檔案中重複修改
❌ 維護困難，改一個地方可能破壞其他地方
❌ 移動版和桌面版邏輯混在一起
```

---

## 第二部分：改造目標

### 改造後的頁面結構（組件化 + 路由驅動）

```
products.php (100 行左右)
├─────────────────────────────────────────────────────────
│
├─ HTML 結構 (簡單透明)
│  └─ <div id="buygo-app">
│     ├─ 基礎架構來自 RouterMixin.js
│     └─ 只負責顯示對應的視圖
│
├─ Vue Script (150 行左右)
│  ├─ 引入 RouterMixin
│  │  const { checkUrlParams, navigateTo, setupPopstateListener } = window.BuyGoRouter
│  │
│  ├─ data()
│  │  ├─ currentView: 'list'  ← 唯一的視圖狀態
│  │  ├─ currentId: null      ← 唯一的 ID 狀態
│  │  ├─ products: []
│  │  └─ ... 只有業務資料
│  │
│  ├─ mounted()
│  │  ├─ checkUrlParams()     ← 從 URL 讀取狀態
│  │  ├─ loadProducts()
│  │  └─ setupPopstateListener() ← 監聽瀏覽器上一頁/下一頁
│  │
│  └─ methods()
│     ├─ handleNavigation(view, id)  ← 統一導航函數
│     ├─ loadProducts()
│     ├─ saveProduct()
│     └─ deleteProduct()
│
├─ 引入 DesignSystem.js
│  └─ window.BuyGoDesignSystem.injectStyles()
│     注入所有統一的樣式、動畫、CSS 變數
│
└─ 核心邏輯檔案
   ├─ assets/js/RouterMixin.js (50 行)
   │  ├─ checkUrlParams()
   │  ├─ navigateTo()
   │  └─ setupPopstateListener()
   │
   └─ assets/js/DesignSystem.js (150 行)
      ├─ CSS 變數 (顏色、間距、陰影)
      ├─ 動畫定義 (fade, slide, scale)
      └─ 通用組件樣式 (按鈕、卡片、返回按鈕)
```

---

## 第三部分：URL 驅動的狀態管理

### 現狀：Modal 驅動（無 URL）

```
使用者操作               JavaScript 狀態              顯示結果
─────────────          ────────────────             ──────────

點擊「編輯」
    ↓                  showEditModal = true         編輯 Modal 出現
點擊「保存」
    ↓                  showEditModal = false        Modal 消失

【重新整理 F5】
    ↓                  showEditModal = false        回到列表頁
    ↓                  （狀態丟失）
    ⚠️ 使用者迷路


【搜尋結果連結】
搜尋「商品 #123」
    ↓
點擊結果 → 進入 products.php
    ↓
showEditModal = false  ← Modal 無法自動開啟
    ↓
⚠️ 找不到所要的商品編輯頁面
```

### 改造後：URL 驅動（狀態保留）

```
使用者操作            URL 狀態                    JavaScript 狀態
─────────────        ────────────────────       ────────────────

點擊「編輯」商品 #123
    ↓
URL 更新：?view=edit&id=123  ←─── 狀態寫入 URL
    ↓
currentView = 'edit'           ← 同步到 JS
currentId = 123                ← 同步到 JS
    ↓
編輯表單出現

【重新整理 F5】
    ↓
讀取 URL：?view=edit&id=123  ←─── checkUrlParams()
    ↓
currentView = 'edit'           ← 狀態復原
currentId = 123                ← 狀態復原
    ↓
✅ 編輯表單仍然顯示，狀態保留


【搜尋結果連結】
搜尋「商品 #123」
    ↓
結果連結帶 URL：?view=edit&id=123
    ↓
點擊進入 → checkUrlParams() 自動讀取
    ↓
currentView = 'edit'
currentId = 123
    ↓
✅ 編輯頁面立即開啟，無需重複搜尋
```

---

## 第四部分：視圖切換流程

### 現狀：狀態分散

```
┌─────────────────────────────────────────────────────┐
│ products.php (單一檔案包含所有邏輯)                │
├─────────────────────────────────────────────────────┤
│                                                      │
│  showEditModal ═════════╗                           │
│  showAllocationModal ═══╬═╗                         │
│  showBuyersModal ═══════╬═╬═╗                       │
│  showDeleteConfirm ═════╬═╬═╬═╗                     │
│  ... (更多狀態)         ║ ║ ║ ║                     │
│                         ║ ║ ║ ║                     │
│  ┌─────────────────────╨─╨─╨─╨──────────────┐      │
│  │ v-if 邏輯混亂                              │      │
│  │ ├─ v-if="showEditModal"                  │      │
│  │ ├─ v-if="showAllocationModal"            │      │
│  │ ├─ v-if="showBuyersModal"                │      │
│  │ └─ ... 多個 Modal 同時 v-if              │      │
│  └─────────────────────────────────────────┘      │
│                                                     │
│  ❌ 容易出現多個 Modal 同時顯示的 Bug             │
│  ❌ 狀態難以追蹤                                  │
│  ❌ 動畫效果無法統一管理                         │
└─────────────────────────────────────────────────────┘
```

### 改造後：URL 驅動的單一狀態

```
┌──────────────────────────────────────────────────────┐
│ RouterMixin.js (統一的路由邏輯)                      │
├──────────────────────────────────────────────────────┤
│                                                       │
│ URL: ?view=list                                      │
│   ↓                                                  │
│ checkUrlParams() → { view: 'list', id: null }       │
│   ↓                                                  │
│ currentView = 'list'  ← 唯一的狀態來源              │
│                                                       │
│ ┌───────────────────────────────────────────┐        │
│ │ <div v-if="currentView === 'list'">      │        │
│ │   ← 列表視圖                              │        │
│ │ </div>                                    │        │
│ └───────────────────────────────────────────┘        │
│                                                       │
└──────────────────────────────────────────────────────┘

當使用者點擊「編輯 #123」：
│
├─ navigateTo('edit', 123)
│  ↓
│  URL 變為：?view=edit&id=123
│  ↓
│  currentView = 'edit'
│  currentId = 123
│
├─ v-if="currentView === 'list'"  ← False
├─ v-if="currentView === 'edit'"  ← True ✅
├─ v-if="currentView === 'allocation'" ← False
└─ v-if="currentView === 'buyers'"     ← False

結果：只有一個視圖顯示，邏輯清晰明確
```

---

## 第五部分：設計系統的集中管理

### 現狀：樣式分散

```
products.php
├─ <button class="bg-blue-600 hover:bg-blue-700...">
│  (自己在 HTML 中寫樣式)
│
├─ <div style="padding: 24px; margin-top: 16px...">
│  (混用 inline style 和 Tailwind)
│
├─ <span class="text-sm font-bold text-slate-900">
│  (色彩、字體大小不一致)
│
└─ ... 每個頁面都重複同樣的樣式代碼


orders.php
├─ <button class="bg-blue-500 px-4 py-2...">
│  (同樣功能的按鈕，但樣式略有不同)
│
└─ ... 導致視覺不一致


customers.php
├─ <button class="bg-primary text-white...">
│  (嘗試用 CSS 變數，但不完整)
│
└─ ... 無法統一管理


❌ 修改一個按鈕樣式 → 需要在 6 個頁面中都修改
❌ 每個頁面有自己的「習慣」
❌ 新頁面開發者需要「猜測」應該用什麼樣式
❌ 設計調整成本高
```

### 改造後：設計系統集中

```
DesignSystem.js (單一來源)
├─────────────────────────────────────────────────────

├─ CSS 變數 (根選擇器)
│  :root {
│    --buygo-primary: #3B82F6
│    --buygo-success: #10B981
│    --buygo-danger: #EF4444
│    --buygo-space-sm: 0.5rem
│    --buygo-space-md: 1rem
│    --buygo-radius-md: 0.5rem
│    --buygo-shadow-md: 0 4px 6px...
│  }
│
├─ 動畫定義
│  @keyframes buygo-fade-in { ... }
│  @keyframes buygo-slide-in-right { ... }
│  @keyframes buygo-scale-in { ... }
│
└─ 組件樣式
   .buygo-btn {
     padding: var(--buygo-space-sm) var(--buygo-space-md);
     border-radius: var(--buygo-radius-md);
     transition: all var(--buygo-duration-fast);
   }

   .buygo-btn-primary {
     background: var(--buygo-primary);
     color: white;
   }

   .buygo-card {
     background: white;
     border-radius: var(--buygo-radius-lg);
     box-shadow: var(--buygo-shadow-md);
     padding: var(--buygo-space-lg);
   }


使用方式 (所有頁面)：
├─ <button class="buygo-btn buygo-btn-primary">
│  編輯
│ </button>
│
├─ <div class="buygo-card">
│  內容
│ </div>
│
└─ ... 統一的樣式


好處：
✅ 修改按鈕樣式 → 只需在 DesignSystem.js 修改一次
✅ 所有頁面自動更新
✅ 新頁面開發直接用現成的 .buygo-btn、.buygo-card
✅ 設計調整成本低
✅ 統一的視覺體驗
```

---

## 第六部分：改造流程（分階段）

### Day 1-2：建立基礎架構

```
Step 1: 建立 RouterMixin.js
  ├─ checkUrlParams()        50 行
  ├─ navigateTo()            30 行
  └─ setupPopstateListener() 15 行

Step 2: 建立 DesignSystem.js
  ├─ CSS 變數定義             60 行
  ├─ 動畫定義                40 行
  ├─ 組件樣式                50 行
  └─ injectStyles() 自動載入 10 行

Result: ✅ 兩個基礎檔案完成
        ✅ 可被所有頁面使用
```

### Day 3-4：第一個頁面改造 (products.php)

```
Before:
products.php
├─ 1000+ 行
├─ showEditModal, showAllocationModal...
├─ 8 個 v-show Modal
├─ 樣式硬編碼
└─ 無路由邏輯

After:
products.php
├─ 200 行
├─ 引入 RouterMixin
├─ 引入 DesignSystem
├─ currentView、currentId (2 個狀態)
├─ 8 個 v-if 清晰分離
├─ 統一的 handleNavigation()
└─ 樣式使用 CSS 變數

改變：
✅ 代碼量減少 80%
✅ 邏輯更清晰
✅ 可重新整理保留狀態
✅ URL 可分享
✅ 樣式統一管理
```

### Day 5-7：第二個頁面改造 (orders.php)

```
Since we have the pattern from products.php:

orders.php 改造時間: 30 分鐘 (vs products 需要 3-4 小時)

只需要：
├─ 複製 RouterMixin 引入代碼
├─ 複製 handleNavigation 邏輯
├─ 適配 orders 的不同視圖
└─ 使用 DesignSystem 的樣式

Result: ✅ 快速複製 products 模式
```

### Day 8-10：其他頁面統一

```
customers.php → 2 小時 (新增路由)
shipment-products.php → 2 小時 (新增路由)
shipment-details.php → 2 小時 (新增路由)
settings.php → 30 分 (只需統一樣式，無路由)

只需要改：
├─ 套用 .buygo-btn、.buygo-card 樣式
├─ 使用 CSS 變數替代硬編碼
├─ 統一按鈕、卡片、間距
└─ 響應式調整 (如果需要)

Result: ✅ UI 完全統一
```

---

## 第七部分：完整對比表

| 項目 | 現狀 ❌ | 改造後 ✅ |
|------|---------|-----------|
| **狀態管理** | 狀態分散 (showEditModal, showAllocModal...) | 統一由 URL 管理 (currentView, currentId) |
| **Modal 行為** | v-show 隱藏，重新整理丟失 | v-if 正確顯示，URL 保持狀態 |
| **分享功能** | 無法分享編輯狀態 | URL 可直接分享 |
| **樣式管理** | 每頁重複代碼 | DesignSystem 集中管理 |
| **頁面開發速度** | 3-4 小時/頁 | 30 分 - 1 小時/頁 |
| **代碼量** | 1000+ 行/頁 | 200-300 行/頁 |
| **動畫效果** | 硬編碼在 HTML | 統一定義在 CSS |
| **視覺一致性** | 各頁不一致 | 完全統一 |
| **Bug 風險** | 高 (多個 Modal 同時顯示) | 低 (單一視圖狀態) |
| **維護成本** | 高 (改一處需改多處) | 低 (修改 DesignSystem 全部更新) |

---

## 第八部分：完成後的體驗

### 使用者角度

```
【現狀】
用戶搜尋「商品 #123」
  ↓
點擊結果 → 看到商品列表
  ↓
❌ 「咦，#123 在哪裡？需要再手動找」


【改造後】
用戶搜尋「商品 #123」
  ↓
點擊結果 → 直接進入編輯頁面 (URL: ?view=edit&id=123)
  ↓
✅ 完美體驗，立即看到要編輯的內容


【現狀】
用戶編輯商品時，不小心按 F5 重新整理
  ↓
編輯 Modal 消失
  ↓
❌ 「我的編輯呢？」，需要重新點擊


【改造後】
用戶編輯商品時，不小心按 F5 重新整理
  ↓
URL 保持 ?view=edit&id=123
  ↓
✅ 編輯頁面依然顯示，無縫體驗
```

### 開發者角度

```
【現狀】
需要改進按鈕樣式
  ↓
1. 找出所有使用 <button> 的地方 (分散在 6 個頁面)
  ↓
2. 每個頁面都改一次
  ↓
3. 檢查每個頁面是否一致
  ↓
❌ 容易遺漏，花時間多


【改造後】
需要改進按鈕樣式
  ↓
1. 開啟 DesignSystem.js
  ↓
2. 修改 .buygo-btn 樣式定義
  ↓
3. 儲存
  ↓
✅ 所有頁面自動更新，統一一致
```

---

## 總結

### 核心改造三點

1. **URL 驅動的狀態管理**
   - 狀態從「JavaScript 變數」 → 「URL 參數」
   - 好處：重新整理保留狀態、可分享、易追蹤

2. **集中的設計系統**
   - 樣式從「每頁硬編碼」 → 「DesignSystem.js」
   - 好處：統一風格、改動成本低、維護容易

3. **單一視圖狀態**
   - 狀態從「多個 Modal 開關」 → 「currentView + currentId」
   - 好處：邏輯清晰、Bug 少、易維護

### 收益

- 📊 **代碼量** 減少 80%
- ⚡ **開發速度** 提升 4-6 倍
- 🎨 **視覺一致性** 達到 100%
- 🐛 **Bug 風險** 降低 70%
- 🛠️ **維護成本** 降低 60%

---

**準備好了嗎？** 是否要開始實施？建議先從 UI 統一規範開始，再進行組件分解改造。
