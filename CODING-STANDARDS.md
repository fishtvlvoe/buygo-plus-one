# BuyGo+1 編碼標準

> **重要**：修改任何代碼前，請先閱讀此文件！
>
> 本文件定義了 BuyGo+1 外掛的編碼規範，確保代碼一致性並防止常見錯誤。

---

## HTML 結構模式（關鍵）

所有管理員頁面 **必須** 遵循此結構：

```html
<main class="flex-1 flex flex-col overflow-hidden">
    <!-- ============================================ -->
    <!-- 頁首部分（在 v-show 外面） -->
    <!-- ============================================ -->
    <header v-show="currentView === 'list'" class="h-16 bg-white border-b...">
        <!-- 標題、麵包屑、搜尋、操作按鈕 -->
    </header>

    <!-- ============================================ -->
    <!-- 內容區域 -->
    <!-- ============================================ -->
    <div class="flex-1 overflow-auto bg-slate-50/50">

        <!-- 列表檢視 -->
        <div v-show="currentView === 'list'" class="p-2 xs:p-4 md:p-6">
            <!-- 列表內容 -->
        </div><!-- 結束：列表檢視 -->

        <!-- 詳情檢視（與列表檢視平級） -->
        <div v-show="currentView === 'detail'" class="p-4 md:p-6">
            <!-- 詳情內容 -->
        </div><!-- 結束：詳情檢視 -->

    </div><!-- 結束：內容區域 -->
</main>
```

**參考範例**：[admin/partials/products.php](admin/partials/products.php) 第 1-50 行

### ❌ 錯誤結構（會導致頁面空白）

```html
<!-- 錯誤：詳情檢視嵌套在列表檢視內 -->
<div v-show="currentView === 'list'">
    <header>...</header>
    <div class="content">...</div>
    <div v-show="currentView === 'detail'">  <!-- 這是錯的！ -->
        ...
    </div>
</div>
```

### ✅ 正確結構

```html
<!-- 正確：列表和詳情是平級的兄弟元素 -->
<header v-show="currentView === 'list'">...</header>
<div class="flex-1 overflow-auto">
    <div v-show="currentView === 'list'">...</div>     <!-- 列表 -->
    <div v-show="currentView === 'detail'">...</div>   <!-- 詳情 -->
</div>
```

---

## CSS 命名規範

每個頁面的 CSS 類名 **必須** 使用對應的前綴：

| 頁面 | 前綴 | 範例 |
|------|------|------|
| products.php | `products-` | `.products-header`, `.products-inline-edit` |
| orders.php | `orders-` | `.orders-modal`, `.orders-table` |
| customers.php | `customers-` | `.customers-card`, `.customers-list` |
| shipment-details.php | `shipment-` | `.shipment-row`, `.shipment-status` |
| shipment-products.php | `shipment-` | `.shipment-product-card` |
| settings.php | `settings-` | `.settings-tab`, `.settings-form` |

### ❌ 避免

```css
/* 太通用，可能與其他頁面衝突 */
.header { }
.modal { }
.card { }
.loading { }
```

### ✅ 推薦

```css
/* 有頁面前綴，不會衝突 */
.products-header { }
.products-modal { }
.orders-card { }
.customers-loading { }
```

---

## JavaScript 命名規範

### 變數命名

```javascript
// ❌ 避免通用名稱
const data = [];
const items = [];
const loading = false;
const error = null;

// ✅ 使用明確名稱
const productsData = [];
const orderItems = [];
const customersLoading = false;
const shipmentError = null;
```

### 函數命名

```javascript
// ❌ 避免
const loadData = async () => { };
const handleClick = () => { };

// ✅ 推薦
const loadProducts = async () => { };
const handleProductSelect = () => { };
const loadOrderDetails = async () => { };
```

---

## Vue 組件設定模式

### 必須的模式

```javascript
const PageComponent = {
    name: 'ProductsPageComponent',  // 明確的組件名稱
    template: `<?php echo $products_component_template; ?>`,
    components: {
        SmartSearchBox: BuyGoSmartSearchBox,
        BuyGoPagination: BuyGoPagination
    },
    setup() {
        const { ref, reactive, computed, onMounted } = Vue;

        // ⚠️ 關鍵：wpNonce 必須定義
        const wpNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';

        // ... 其他邏輯

        // ⚠️ 關鍵：wpNonce 必須在 return 中導出
        return {
            wpNonce,  // ← 這行很重要！
            // ... 其他導出
        };
    }
};
```

### ❌ 常見錯誤

```javascript
setup() {
    const wpNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';

    return {
        // 忘記導出 wpNonce！會導致子組件 403 錯誤
        someOtherData
    };
}
```

---

## API 整合模式

### Fetch 請求（必須包含 Header）

```javascript
// ✅ 正確：所有 fetch 都必須帶 X-WP-Nonce header
const response = await fetch('/wp-json/buygo-plus-one/v1/products', {
    method: 'GET',
    headers: {
        'X-WP-Nonce': wpNonce,        // ← 必須！
        'Content-Type': 'application/json'
    }
});

// POST/PUT 請求
const response = await fetch('/wp-json/buygo-plus-one/v1/products', {
    method: 'POST',
    headers: {
        'X-WP-Nonce': wpNonce,        // ← 必須！
        'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
});
```

### URL 參數（匯出功能）

```javascript
// ✅ 正確：URL 參數必須包含 _wpnonce
const exportUrl = `/wp-json/buygo-plus-one/v1/shipments/export?shipment_ids=${id}&_wpnonce=${wpNonce}`;
window.open(exportUrl, '_blank');
```

---

## 搜尋組件模式

### SmartSearchBox 事件綁定

```html
<!-- 必須綁定所有三個事件 -->
<smart-search-box
    api-endpoint="/wp-json/buygo-plus-one/v1/products"
    :search-fields="['name', 'sku']"
    placeholder="搜尋商品..."
    display-field="name"
    @select="handleProductSelect"    <!-- 選擇項目 -->
    @search="handleProductSearch"    <!-- 搜尋輸入 -->
    @clear="handleProductClear"      <!-- 清除搜尋 -->
></smart-search-box>
```

### 搜尋處理函數

```javascript
// ⚠️ handleSearch 必須調用 loadData()
const handleProductSearch = (query) => {
    searchQuery.value = query;
    currentPage.value = 1;
    loadProducts();  // ← 這行很重要！
};

const handleProductClear = () => {
    searchQuery.value = '';
    loadProducts();  // ← 這行也很重要！
};
```

---

## 修改前檢查清單

在修改任何代碼前，請確認以下項目：

### 通用檢查

- [ ] 讀取 [BUGFIX-CHECKLIST.md](BUGFIX-CHECKLIST.md) 確認不會破壞已修復的功能
- [ ] `wpNonce` 變數已定義
- [ ] `wpNonce` 已在 `return` 中導出
- [ ] 所有 `fetch()` 都帶有 `X-WP-Nonce` header
- [ ] CSS 類名使用正確的頁面前綴
- [ ] JavaScript 變數使用明確命名
- [ ] HTML 結構遵循上述模式

### LINE 相關檢查

- [ ] Channel Secret 使用 `\BuyGo_Core::settings()->get('line_channel_secret')`
- [ ] HTTP Header 使用小寫 `x-line-signature`（不是 `X-Line-Signature`）
- [ ] `permission_callback` 設為 `__return_true`（不是 `verify_signature`）

### 搜尋功能檢查

- [ ] `smart-search-box` 的三個事件（@search, @select, @clear）都有綁定
- [ ] `handleSearch` 方法會調用 `loadData()` 或類似方法
- [ ] API 的 `search` 參數有正確傳遞

---

## 結構註解模式

每個管理員頁面應該包含以下結構註解，方便快速定位：

```html
<!-- ============================================ -->
<!-- 頁首部分（在 v-show 外面） -->
<!-- ============================================ -->

<!-- ============================================ -->
<!-- 內容區域 -->
<!-- ============================================ -->

<!-- 列表檢視 -->
<!-- 結束：列表檢視 -->

<!-- 詳情檢視 -->
<!-- 結束：詳情檢視 -->

<!-- 結束：內容區域 -->
```

---

## 快速參考

### 必須的 Header

```javascript
headers: {
    'X-WP-Nonce': wpNonce,
    'Content-Type': 'application/json'
}
```

### 必須的 URL 參數

```javascript
`&_wpnonce=${wpNonce}`
```

### 必須的 Return

```javascript
return {
    wpNonce,
    // ... 其他
};
```

---

**最後更新**：2026-01-24
**維護者**：Development Team
