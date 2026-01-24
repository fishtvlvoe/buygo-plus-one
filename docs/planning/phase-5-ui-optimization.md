# 第 5 階段：UI 組件庫優化計劃

> **階段目標**: 提取和標準化可複用 UI 組件，建立完整的組件庫系統
> **預估時間**: 2-3 週
> **優先級**: 中 (可選階段)
> **前置條件**: 第 4 階段完成（自動化工具已建立）
> **建立日期**: 2026-01-24

---

## 📋 目錄

1. [階段概覽](#階段概覽)
2. [當前狀況分析](#當前狀況分析)
3. [優化目標](#優化目標)
4. [組件提取計劃](#組件提取計劃)
5. [實作步驟](#實作步驟)
6. [品質標準](#品質標準)
7. [測試計劃](#測試計劃)
8. [風險評估](#風險評估)
9. [成功指標](#成功指標)
10. [參考資源](#參考資源)

---

## 階段概覽

### 為什麼需要這個階段？

#### 當前問題

1. **組件散落**: 相似的 UI 元素在多個頁面重複實作
2. **維護困難**: 修改一個組件需要同時修改多個檔案
3. **不一致性**: 同樣功能的組件在不同頁面有細微差異
4. **開發效率**: 新增頁面時需要重複寫相同的代碼

#### 解決方案

建立一個**統一的 UI 組件庫**，包含：
- 可複用的 Vue 組件
- 標準化的 PHP 組件
- 統一的樣式系統
- 完整的使用文檔

### 階段重要性評估

| 評估項 | 分數 | 說明 |
|--------|------|------|
| **急迫性** | ⭐⭐☆☆☆ | 非緊急，現有系統運作正常 |
| **重要性** | ⭐⭐⭐⭐☆ | 長期維護和擴展很重要 |
| **影響範圍** | ⭐⭐⭐⭐⭐ | 影響所有頁面和未來開發 |
| **技術債務** | ⭐⭐⭐☆☆ | 中等程度的技術債務 |
| **投資回報** | ⭐⭐⭐⭐☆ | 高回報（提升開發效率）|

**結論**: 建議執行，但可以在系統穩定後進行

---

## 當前狀況分析

### 已完成的組件化工作

#### ✅ 第 3 階段成果

| 組件類型 | 位置 | 狀態 | 複用次數 |
|---------|------|------|---------|
| **smart-search-box.php** | components/shared/ | ✅ 完成 | 5 頁面 |
| **pagination.php** | components/shared/ | ✅ 完成 | 5 頁面 |
| **ProductsPage.js** | admin/js/components/ | ✅ 完成 | 1 頁面 |
| **OrdersPage.js** | admin/js/components/ | ✅ 完成 | 1 頁面 |
| **CustomersPage.js** | admin/js/components/ | ✅ 完成 | 1 頁面 |
| **ShipmentDetailsPage.js** | admin/js/components/ | ✅ 完成 | 1 頁面 |
| **ShipmentProductsPage.js** | admin/js/components/ | ✅ 完成 | 1 頁面 |

#### ✅ Composables (共享邏輯)

| Composable | 位置 | 功能 |
|-----------|------|------|
| **useFilters.js** | includes/views/composables/ | 過濾器狀態管理 |
| **usePagination.js** | includes/views/composables/ | 分頁邏輯 |
| **usePermissions.js** | includes/views/composables/ | 權限檢查 |

### 待優化的組件

#### 🔄 需要提取的組件

基於代碼掃描和 UI 分析，以下組件在多個頁面重複出現：

| 組件 | 出現次數 | 當前狀態 | 優先級 |
|------|---------|---------|--------|
| **全域搜尋框** (Global Search) | 5 頁面 | 散落在各頁面 | 高 |
| **通知 Header** (Notification Header) | 5 頁面 | 散落在各頁面 | 高 |
| **資料表格** (Data Table) | 5 頁面 | 部分代碼重複 | 中 |
| **狀態標籤** (Status Tag) | 5 頁面 | 樣式不完全統一 | 中 |
| **操作按鈕組** (Action Buttons) | 5 頁面 | 樣式類似但分散 | 低 |
| **空狀態提示** (Empty State) | 3 頁面 | 文字不統一 | 低 |
| **載入動畫** (Loading Spinner) | 5 頁面 | 樣式分散 | 低 |

#### 📊 組件複用機會分析

```
高優先級組件 (影響所有頁面):
├── 全域搜尋框       → 5 頁面 × 約 80 行代碼 = 400 行可省略
├── 通知 Header      → 5 頁面 × 約 60 行代碼 = 300 行可省略
└── 資料表格         → 5 頁面 × 約 150 行代碼 = 750 行可省略

中優先級組件 (常用但影響較小):
├── 狀態標籤         → 5 頁面 × 約 30 行代碼 = 150 行可省略
└── 操作按鈕組       → 5 頁面 × 約 40 行代碼 = 200 行可省略

預估總計: ~1800 行代碼可複用
```

---

## 優化目標

### 主要目標

1. **提升代碼複用率**: 從當前 30% 提升到 70%
2. **統一 UI 樣式**: 所有組件遵循 BuyGo UI/UX 黃金設計原則
3. **提高開發效率**: 新增頁面時間從 4 小時減少到 1.5 小時
4. **降低維護成本**: 修改一個組件即可影響所有使用處

### 具體指標

| 指標 | 當前值 | 目標值 | 測量方式 |
|------|--------|--------|---------|
| 組件複用率 | 30% | 70% | 共享組件代碼 / 總 UI 代碼 |
| 新頁面開發時間 | 4 小時 | 1.5 小時 | 時間追蹤 |
| UI 一致性評分 | 7/10 | 9/10 | 人工審查 |
| 重複代碼行數 | ~1800 行 | ~300 行 | 代碼分析工具 |
| 組件文檔覆蓋率 | 40% | 100% | 文檔完整性檢查 |

---

## 組件提取計劃

### 優先級排序

#### 第 1 批：關鍵組件 (Week 1)

| 組件 | 說明 | 影響頁面 | 預估工時 |
|------|------|---------|---------|
| **GlobalSearchBox** | 全域搜尋框組件 | 5 頁面 | 4 小時 |
| **NotificationHeader** | 通知區域 header | 5 頁面 | 3 小時 |
| **DataTable** | 通用資料表格組件 | 5 頁面 | 8 小時 |

**總計**: 15 小時 (~2 天)

#### 第 2 批：常用組件 (Week 2)

| 組件 | 說明 | 影響頁面 | 預估工時 |
|------|------|---------|---------|
| **StatusTag** | 狀態標籤組件 | 5 頁面 | 2 小時 |
| **ActionButtons** | 操作按鈕組 | 5 頁面 | 3 小時 |
| **EmptyState** | 空狀態提示 | 3 頁面 | 2 小時 |
| **LoadingSpinner** | 載入動畫 | 5 頁面 | 1 小時 |
| **Modal** | 彈窗組件 | 3 頁面 | 4 小時 |

**總計**: 12 小時 (~1.5 天)

#### 第 3 批：細節優化 (Week 3)

| 任務 | 說明 | 預估工時 |
|------|------|---------|
| **組件文檔撰寫** | 所有組件的使用說明 | 6 小時 |
| **Storybook 設置** (選擇性) | 組件展示系統 | 8 小時 |
| **測試撰寫** | 組件單元測試 | 8 小時 |
| **代碼審查與優化** | 品質提升 | 4 小時 |

**總計**: 26 小時 (~3.5 天)

### 總工時估算

```
Week 1: 15 小時 (關鍵組件)
Week 2: 12 小時 (常用組件)
Week 3: 26 小時 (文檔與測試)
────────────────────────
總計:  53 小時 (~7 個工作天)
```

**建議執行時程**: 2-3 週（考慮測試和修正時間）

---

## 實作步驟

### Step 1: 建立組件目錄結構

```bash
# 建立新的組件目錄
mkdir -p components/ui
mkdir -p components/ui/vue        # Vue 3 組件
mkdir -p components/ui/php        # PHP 組件
mkdir -p components/ui/docs       # 組件文檔

# 組件結構範例
components/
├── shared/                        # 已存在（保留）
│   ├── smart-search-box.php
│   └── pagination.php
└── ui/                           # 新建 UI 組件庫
    ├── vue/
    │   ├── GlobalSearchBox.js    # 全域搜尋
    │   ├── NotificationHeader.js # 通知 Header
    │   ├── DataTable.js          # 資料表格
    │   ├── StatusTag.js          # 狀態標籤
    │   ├── ActionButtons.js      # 操作按鈕
    │   ├── EmptyState.js         # 空狀態
    │   ├── LoadingSpinner.js     # 載入動畫
    │   └── Modal.js              # 彈窗
    ├── php/
    │   └── notification-header.php
    └── docs/
        ├── GlobalSearchBox.md
        ├── DataTable.md
        └── ...
```

### Step 2: 提取 GlobalSearchBox 組件 (範例)

#### 2.1 分析當前實作

查看 5 個頁面中全域搜尋的共同模式：

```html
<!-- products.php -->
<div class="relative hidden sm:block w-32 md:w-48 lg:w-64">
    <input type="text" v-model="globalSearch" @input="handleGlobalSearch"
           placeholder="全域搜尋..." class="...">
    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5">...</svg>
</div>

<!-- orders.php -->
<!-- 類似結構，但可能有細微差異 -->
```

#### 2.2 建立組件檔案

```javascript
// components/ui/vue/GlobalSearchBox.js

const GlobalSearchBox = {
    template: `
        <div class="relative hidden sm:block" :class="widthClass">
            <input
                type="text"
                v-model="searchQuery"
                @input="handleInput"
                :placeholder="placeholder"
                class="pl-9 pr-4 py-2 bg-slate-100 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary w-full"
            >
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
    `,

    props: {
        placeholder: {
            type: String,
            default: '全域搜尋...'
        },
        widthClass: {
            type: String,
            default: 'w-32 md:w-48 lg:w-64'
        },
        debounceMs: {
            type: Number,
            default: 300
        }
    },

    data() {
        return {
            searchQuery: '',
            debounceTimer: null
        };
    },

    methods: {
        handleInput() {
            // 防抖處理
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.$emit('search', this.searchQuery);
            }, this.debounceMs);
        },

        clear() {
            this.searchQuery = '';
            this.$emit('search', '');
        }
    }
};

// 註冊全域組件
if (typeof window.Vue !== 'undefined') {
    window.GlobalSearchBox = GlobalSearchBox;
}
```

#### 2.3 在頁面中使用

```php
<!-- products.php -->

<!-- 載入組件 -->
<script src="<?php echo BUYGO_PLUS_ONE_PLUGIN_URL; ?>components/ui/vue/GlobalSearchBox.js"></script>

<!-- 使用組件 -->
<header class="...">
    <div class="flex items-center gap-2 md:gap-3">
        <!-- 使用組件 -->
        <global-search-box
            @search="handleGlobalSearch"
            placeholder="搜尋商品、訂單、客戶..."
        />

        <!-- 其他 header 元素 -->
    </div>
</header>
```

#### 2.4 撰寫組件文檔

```markdown
<!-- components/ui/docs/GlobalSearchBox.md -->

# GlobalSearchBox 組件

全域搜尋框組件，支援防抖、清除、鍵盤導航。

## Props

| 名稱 | 類型 | 預設值 | 說明 |
|------|------|--------|------|
| placeholder | String | '全域搜尋...' | 輸入框提示文字 |
| widthClass | String | 'w-32 md:w-48 lg:w-64' | 寬度類別 |
| debounceMs | Number | 300 | 防抖延遲（毫秒）|

## Events

| 事件名 | 參數 | 說明 |
|--------|------|------|
| search | (query: string) | 搜尋輸入變更時觸發 |

## 使用範例

```vue
<global-search-box
    @search="handleGlobalSearch"
    placeholder="搜尋商品..."
    width-class="w-64"
/>
```

## 注意事項

- 僅在桌面版顯示 (hidden sm:block)
- 支援 300ms 防抖，避免頻繁請求
```

### Step 3: 重複 Step 2 for 其他組件

依照相同流程提取其他組件：
1. 分析當前實作
2. 建立組件檔案
3. 在頁面中使用
4. 撰寫文檔

### Step 4: 測試與驗證

```javascript
// tests/components/GlobalSearchBox.test.js

describe('GlobalSearchBox', () => {
    test('should emit search event on input', async () => {
        // 測試邏輯
    });

    test('should debounce input', async () => {
        // 測試邏輯
    });

    test('should clear input', async () => {
        // 測試邏輯
    });
});
```

### Step 5: 代碼審查與優化

使用自動化工具驗證：

```bash
# 執行結構驗證
bash scripts/validate-structure.sh

# 檢查組件是否符合規範
bash scripts/validate-components.sh  # 新建腳本
```

---

## 品質標準

### 組件品質評分標準

所有組件必須達到 4.0/5 以上：

| 評分項目 | 權重 | 標準 |
|---------|------|------|
| **可複用性** | 25% | 可在 3+ 頁面使用 |
| **文檔完整度** | 20% | Props、Events、範例齊全 |
| **代碼品質** | 20% | 遵循 Vue 3 最佳實踐 |
| **樣式一致性** | 15% | 符合 UI/UX 黃金原則 |
| **效能** | 10% | 無不必要的重新渲染 |
| **可訪問性** | 10% | 支援鍵盤操作、ARIA |

### 組件必須包含

#### 1. Props 驗證
```javascript
props: {
    items: {
        type: Array,
        required: true,
        validator: (value) => Array.isArray(value)
    }
}
```

#### 2. 事件文檔
```javascript
// 清楚說明每個 emit 事件
this.$emit('select', item);  // @select="handleSelect"
this.$emit('change', value); // @change="handleChange"
```

#### 3. 預設值
```javascript
props: {
    showPagination: {
        type: Boolean,
        default: true  // 合理的預設值
    }
}
```

#### 4. CSS 隔離
```css
/* 使用組件前綴 */
.global-search-box { }
.global-search-box__input { }
.global-search-box__icon { }
```

---

## 測試計劃

### 單元測試

每個組件至少包含以下測試：

```javascript
// 範例：GlobalSearchBox.test.js

describe('GlobalSearchBox', () => {
    describe('Props', () => {
        test('accepts custom placeholder', () => {});
        test('accepts custom widthClass', () => {});
        test('validates debounceMs', () => {});
    });

    describe('Events', () => {
        test('emits search event on input', () => {});
        test('debounces rapid input', () => {});
    });

    describe('UI', () => {
        test('shows search icon', () => {});
        test('applies correct styles', () => {});
        test('hides on mobile', () => {});
    });

    describe('Accessibility', () => {
        test('has accessible label', () => {});
        test('supports keyboard navigation', () => {});
    });
});
```

### 整合測試

測試組件在實際頁面中的運作：

```bash
# 測試頁面
tests/integration/
├── products-page.test.js    # 測試商品頁面所有組件
├── orders-page.test.js      # 測試訂單頁面所有組件
└── ...
```

### 視覺回歸測試 (選擇性)

使用 Storybook + Chromatic 進行視覺測試：

```javascript
// stories/GlobalSearchBox.stories.js

export default {
    title: 'UI/GlobalSearchBox',
    component: GlobalSearchBox,
};

export const Default = () => ({
    components: { GlobalSearchBox },
    template: '<global-search-box />'
});

export const CustomPlaceholder = () => ({
    components: { GlobalSearchBox },
    template: '<global-search-box placeholder="搜尋商品..." />'
});
```

---

## 風險評估

### 潛在風險

| 風險 | 影響 | 可能性 | 緩解措施 |
|------|------|--------|---------|
| **破壞現有功能** | 高 | 中 | 充分測試、漸進式遷移 |
| **效能下降** | 中 | 低 | 效能測試、優化組件 |
| **學習曲線** | 低 | 中 | 詳細文檔、範例代碼 |
| **維護負擔增加** | 中 | 低 | 自動化測試、CI/CD |

### 風險緩解策略

#### 1. 漸進式遷移

```
Phase 5.1: 提取第 1 批組件 → 測試 → 部署
Phase 5.2: 提取第 2 批組件 → 測試 → 部署
Phase 5.3: 文檔與優化 → 審查 → 完成
```

不要一次性替換所有頁面，逐步驗證每個組件。

#### 2. 回滾計劃

```bash
# 每個階段建立 Git tag
git tag phase-5.1-complete
git tag phase-5.2-complete

# 如果出問題，快速回滾
git checkout phase-5.1-complete
```

#### 3. A/B 測試

保留舊頁面作為備用：

```php
// 使用 feature flag 控制
$use_new_components = get_option('buygo_use_new_components', false);

if ($use_new_components) {
    include 'new-products-page.php';
} else {
    include 'legacy-products-page.php';
}
```

---

## 成功指標

### 量化指標

| 指標 | 目標 | 測量時間 |
|------|------|---------|
| 組件複用率 | ≥ 70% | 階段結束時 |
| 新頁面開發時間 | ≤ 1.5 小時 | 下次新增頁面時 |
| UI 一致性評分 | ≥ 9/10 | 人工審查 |
| 重複代碼減少 | ≥ 80% | 代碼分析 |
| 組件文檔覆蓋率 | 100% | 階段結束時 |
| 測試覆蓋率 | ≥ 80% | 階段結束時 |

### 質化指標

- [ ] 所有組件有清晰的使用文檔
- [ ] 新開發者可以在 30 分鐘內理解組件系統
- [ ] 修改一個組件樣式可以同步到所有使用處
- [ ] 新增頁面時可以快速組合現有組件
- [ ] 用戶感受不到功能變更（無破壞性更新）

---

## 參考資源

### 設計規範

- [BuyGo UI/UX 黃金設計原則](./ui-ux-golden-principles.md)
- [WordPress Plugin Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Vue 3 Style Guide](https://vuejs.org/style-guide/)
- [Tailwind CSS Best Practices](https://tailwindcss.com/docs/reusing-styles)

### 組件庫參考

- [Headless UI](https://headlessui.com/) - Unstyled components
- [Radix UI](https://www.radix-ui.com/) - Accessible components
- [shadcn/ui](https://ui.shadcn.com/) - Re-usable components

### 工具推薦

- **Storybook**: 組件展示和測試
- **Chromatic**: 視覺回歸測試
- **Jest**: 單元測試框架
- **Testing Library**: 整合測試

---

## 下一步

完成第 5 階段後：

### 短期 (1-2 個月)

1. **持續優化**: 根據使用反饋調整組件
2. **新增組件**: 根據需求建立新組件
3. **效能監控**: 追蹤組件效能指標

### 中期 (3-6 個月)

1. **Vite 構建系統** (Phase 6): 更現代的前端構建
2. **TypeScript 遷移**: 增加型別安全
3. **組件版本管理**: 建立版本更新機制

### 長期 (6-12 個月)

1. **獨立組件庫**: 將組件庫分離為獨立套件
2. **主題系統**: 支援多主題切換
3. **國際化支援**: 多語言組件

---

## 總結

### 執行建議

1. **非緊急**: 可以等系統穩定後再執行
2. **高回報**: 長期來看可以大幅提升開發效率
3. **漸進式**: 分批執行，降低風險
4. **可回滾**: 每個階段建立檢查點

### 決策參考

**建議執行，如果**:
- ✅ 系統已穩定運行 1 個月以上
- ✅ 有計劃新增更多頁面
- ✅ 團隊有足夠的時間和資源
- ✅ 想要提升長期維護性

**建議延後，如果**:
- ❌ 系統還在頻繁修復 Bug
- ❌ 近期沒有新增頁面計劃
- ❌ 團隊資源緊張
- ❌ 短期內有更重要的任務

---

**文檔維護者**: BuyGo Development Team
**最後更新**: 2026-01-24
**相關文檔**: [UI/UX 黃金設計原則](./ui-ux-golden-principles.md)
