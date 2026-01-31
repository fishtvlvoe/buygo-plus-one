# BuyGo+1 頁面組件分解計畫

> **計畫開始日期**：2026-01-24
> **基於舊檔案庫的經驗**：`/Users/fishtv/Desktop/VT工作流/_Archive/舊項目/claude-code-buygo-pluse-one/`
> **目標**：將單一頁面檔案分解成獨立的可重用組件，提升維護性和開發效率

---

## 📋 概述

根據舊檔案庫中詳細的 UI 重構計畫，我們發現一套成熟的**頁面分解策略**。此計畫將這套策略應用於 buygo-plus-one-dev，分階段實現每個頁面的組件化。

### 核心改革

**從**：單一 PHP 檔案包含 Header、搜尋框、列表、詳情、所有業邏輯
```
pages/products.php (1000+ 行)
├── Header HTML
├── Search HTML
├── List HTML
├── Edit Modal HTML
├── Delete Logic
└── API 整合
```

**改為**：模組化的組件結構
```
pages/products.php (主容器)
├── components/ProductHeader.vue
├── components/ProductSearch.vue
├── components/ProductList.vue
├── components/ProductEdit.vue
├── services/productService.js
└── styles/products.css (隔離樣式)
```

---

## 🎯 分解原則

### 1. URL 驅動的路由系統

**核心概念**：所有頁面狀態由 URL 決定，而非 JavaScript 變數

```javascript
// 舊做法（有問題）
showEditModal = true  // 重新整理會丟失狀態

// 新做法（正確）
URL: ?view=edit&id=123  // 重新整理後保留狀態
```

**URL 結構規範**：
```
?page=buygo-plus-one-products               // 列表頁
?page=buygo-plus-one-products&view=edit&id=123    // 編輯頁
?page=buygo-plus-one-products&view=allocation&id=123  // 分配庫存頁
?page=buygo-plus-one-products&view=buyers&id=123   // 下單客戶頁
```

### 2. 非破壞性整合

保留原本的所有邏輯，只是將其「包裝」在新的路由系統中：
- ✅ 舊的 Modal 程式碼仍保留作為備份
- ✅ 新的路由層只負責顯示/隱藏邏輯
- ✅ 沒有刪除任何功能

### 3. 設計系統集中管理

建立統一的設計系統檔案：
```
assets/
├── js/
│   ├── RouterMixin.js      // 路由邏輯
│   └── DesignSystem.js     // 設計系統
└── css/
    └── design-tokens.css   // CSS 變數
```

---

## 📐 實作架構

### 組件層級

```
頁面 (products.php)
├── Level 1: RouterMixin (URL 管理層)
│   ├── checkUrlParams()    - 讀取 URL
│   ├── navigateTo()        - 切換檢視
│   └── setupPopstateListener() - 監聽瀏覽器
│
├── Level 2: Design System (樣式層)
│   ├── CSS 變數定義
│   ├── 動畫效果
│   └── 通用組件樣式
│
└── Level 3: 業務組件 (內容層)
    ├── 列表視圖 <ProductList>
    ├── 編輯視圖 <ProductEdit>
    ├── 分配視圖 <ProductAllocation>
    └── 下單視圖 <ProductBuyers>
```

### 關鍵 API

#### RouterMixin.js

```javascript
// 初始化：讀取 URL 參數
function checkUrlParams() {
    const urlParams = new URLSearchParams(window.location.search);
    return {
        view: urlParams.get('view') || 'list',
        id: urlParams.get('id'),
        action: urlParams.get('action')
    };
}

// 導航：切換頁面並更新 URL
function navigateTo(view, id = null, action = null) {
    const url = new URL(window.location);

    if (view === 'list') {
        url.searchParams.delete('view');
        url.searchParams.delete('id');
        url.searchParams.delete('action');
    } else {
        url.searchParams.set('view', view);
        if (id) url.searchParams.set('id', id);
        if (action) url.searchParams.set('action', action);
    }

    window.history.pushState({}, '', url);
    return checkUrlParams();
}

// 監聽：處理瀏覽器上一頁/下一頁
function setupPopstateListener(callback) {
    window.addEventListener('popstate', () => {
        callback(checkUrlParams());
    });
}
```

#### DesignSystem.js

包含以下內容：
- CSS 變數（顏色、間距、陰影、Z-index）
- 動畫定義（fade, slide, scale）
- 通用組件樣式（按鈕、卡片、返回按鈕）

### 使用方式

**在 products.php 中**：

```php
<script>
// 1. 引入核心系統
const { checkUrlParams, navigateTo, setupPopstateListener } = window.BuyGoRouter;
window.BuyGoDesignSystem.injectStyles();

// 2. Vue 應用
const app = Vue.createApp({
    data() {
        return {
            currentView: 'list',
            currentId: null,
            products: []
        };
    },
    mounted() {
        // 讀取 URL 初始化檢視
        const params = checkUrlParams();
        this.currentView = params.view;
        this.currentId = params.id;

        // 監聽瀏覽器上一頁/下一頁
        setupPopstateListener((params) => {
            this.currentView = params.view;
            this.currentId = params.id;
        });
    },
    methods: {
        handleNavigation(view, product = null) {
            const params = navigateTo(view, product?.id);
            this.currentView = params.view;
            this.currentId = params.id;
        }
    }
});
</script>

<!-- 3. HTML 結構 -->
<div id="buygo-app">
    <!-- 列表視圖 -->
    <div v-if="currentView === 'list'">
        <!-- 表格 -->
        <button @click="handleNavigation('edit', product)">編輯</button>
    </div>

    <!-- 編輯視圖 -->
    <div v-if="currentView === 'edit'" class="buygo-subpage">
        <button @click="handleNavigation('list')">返回列表</button>
    </div>
</div>
```

---

## 📊 實作時程

### Phase 1：基礎架構建立（Day 1-2）
- [ ] 建立 `RouterMixin.js`
- [ ] 建立 `DesignSystem.js`
- [ ] 驗證兩個檔案可正常載入

### Phase 2：核心頁面實作（Day 3-4）
- [ ] Products 頁面整合 RouterMixin
- [ ] 建立 `handleNavigation()` 統一函數
- [ ] 實作轉換動畫（fade/slide）

### Phase 3：第二個頁面改造（Day 5-7）
- [ ] Orders 頁面改造（參照 Products 模式）
- [ ] 驗證行為一致性

### Phase 4：其他頁面 UI 統一（Day 8-9）
- [ ] Customers 頁面
- [ ] Shipment 頁面們
- [ ] Settings 頁面

### Phase 5：最終驗收（Day 10）
- [ ] 完整功能測試
- [ ] 跨瀏覽器測試
- [ ] 響應式測試

---

## 🔄 與 UI 統一計畫的結合

此計畫與 [UI-UNIFICATION-PLAN.md](UI-UNIFICATION-PLAN.md) **並行進行**：

| 工作 | 時間表 | 負責方 | 依賴性 |
|------|--------|--------|--------|
| **頁面組件分解** (本計畫) | Week 1 整週 | 架構層面 | 基礎 |
| **UI 統一規範** | Week 1 (1/24-1/31) | 樣式層面 | 依賴分解完成 |
| **P2 多樣式產品** | Week 2 開始 (2/3) | 功能層面 | 依賴兩者完成 |

**實施策略**：
1. 優先完成 RouterMixin + DesignSystem（基礎層）
2. 平行進行 UI 規範統一（樣式層）
3. 使用新架構 + 新 UI 重構所有頁面

---

## ✅ 完成標準

### 每個頁面改完後必須檢查

**路由功能**：
- [ ] 列表頁正常顯示
- [ ] 點擊操作按鈕 → URL 正確變更
- [ ] 子頁面正常顯示
- [ ] 重新整理 → 停留在子頁面
- [ ] 返回按鈕 → 回到列表
- [ ] 瀏覽器上一頁 → 正確返回

**動畫效果**：
- [ ] 進入子頁面有動畫（slide/fade）
- [ ] 返回列表有動畫
- [ ] 動畫流暢，無卡頓

**API 功能**：
- [ ] 所有資料正確載入
- [ ] 操作（編輯、刪除）正常
- [ ] 錯誤處理正確

**UI 一致性**：
- [ ] 按鈕樣式統一
- [ ] 間距一致
- [ ] 響應式正常（手機、桌面）

**Console 檢查**：
- [ ] 無 JavaScript 錯誤
- [ ] 無 Vue 警告
- [ ] 無資源 404

---

## 🚀 好處

### 開發效率
- **第一頁**：需要建立架構（2-3 小時）
- **第二頁開始**：只需套用模式（30 分鐘 - 1 小時）

### 可維護性
- 樣式改動集中在 DesignSystem.js
- 路由邏輯集中在 RouterMixin.js
- 各頁面可獨立修改

### 使用者體驗
- URL 可分享（搜尋結果直接打開詳情頁）
- 重新整理保留狀態
- 瀏覽器上一頁/下一頁正常運作

### 測試友善
- 路由層可單獨測試
- 各組件可獨立測試
- 更容易追蹤 Bug 來源

---

## 📁 相關參考

- [UI-UNIFICATION-PLAN.md](UI-UNIFICATION-PLAN.md) - UI 統一規範
- [PROJECT_EXECUTION_PLAN.md](../../../Desktop/VT工作流/_Archive/舊項目/claude-code-buygo-pluse-one/03_開發文件與指南/PROJECT_EXECUTION_PLAN.md) - 舊檔案庫詳細計畫
- [HANDOVER_TO_CLAUDE_DESKTOP.md](../../../Desktop/VT工作流/_Archive/舊項目/claude-code-buygo-pluse-one/03_開發文件與指南/HANDOVER_TO_CLAUDE_DESKTOP.md) - 實作指南及程式碼範本
- [buygo-frontend-router-architecture.md](../../../Desktop/VT工作流/_Archive/舊項目/claude-code-buygo-pluse-one/03_開發文件與指南/buygo-frontend-router-architecture.md) - 路由架構詳細說明

---

**建立日期**：2026-01-24 by Claude Haiku 4.5
**基於**：舊檔案庫 Phase 2-3 成功經驗整理
