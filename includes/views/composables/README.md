# BuyGo+1 Composables

Vue 3 Composition API 可重用邏輯庫

## 📁 檔案結構

```
composables/
├── README.md              # 本檔案
├── useCurrency.js         # 幣別處理
├── useApi.js             # API 調用統一管理
└── usePermissions.js     # 權限管理
```

## 🎯 Composables 說明

### 1. useCurrency.js

**功能：** 統一的幣別格式化與匯率轉換

**使用方式：**
```javascript
const { formatPrice, convertCurrency, getCurrencySymbol } = useCurrency();

// 格式化價格
formatPrice(1000, 'JPY');  // "¥1,000"
formatPrice(500, 'TWD');   // "NT$500"

// 匯率轉換
convertCurrency(1000, 'JPY', 'TWD');  // 230
```

**何時使用：**
- 需要顯示商品價格
- 需要進行幣別轉換
- 需要統一價格格式

---

### 2. useApi.js ⭐ NEW

**功能：** 統一 API 調用管理，自動處理認證、錯誤、loading 狀態

**使用方式：**
```javascript
const { get, post, put, delete: del, isLoading, error } = useApi();

// GET 請求
const result = await get('/wp-json/buygo-plus-one/v1/orders', {
    showError: true,      // 顯示錯誤 toast（預設 true）
    showSuccess: false,   // 顯示成功 toast（預設 false）
    preventCache: true    // 防快取（預設 true）
});

// POST 請求
const result = await post('/wp-json/buygo-plus-one/v1/orders', {
    customer_id: 123,
    product_id: 456
}, {
    showSuccess: true,
    successMessage: '訂單已建立'
});

// PUT 請求
const result = await put(`/wp-json/buygo-plus-one/v1/orders/${orderId}`, {
    status: 'processing'
});

// DELETE 請求
const result = await del(`/wp-json/buygo-plus-one/v1/orders/${orderId}`);
```

**完整選項：**
```javascript
const options = {
    showError: true,           // 是否顯示錯誤 toast
    showSuccess: false,        // 是否顯示成功 toast
    successMessage: '操作成功', // 成功訊息文字
    errorMessage: '操作失敗',   // 錯誤訊息文字
    preventCache: true,        // 是否防快取（GET 請求）
    logErrorToBackend: false,  // 是否記錄錯誤到後端
    module: 'API',            // 錯誤記錄的模組名稱
    onSuccess: (result) => {}, // 成功回調
    onError: (err) => {}       // 失敗回調
};
```

**何時使用：**
- 所有 API 調用場景
- 需要統一錯誤處理
- 需要自動管理 loading 狀態
- 需要自動添加認證 headers

**優點：**
- 自動管理 wpNonce
- 自動處理 HTTP 錯誤
- 統一錯誤提示
- 減少 50% 重複代碼

---

### 3. usePermissions.js ⭐ NEW

**功能：** 統一權限檢查與管理

**使用方式：**
```javascript
const {
    isAdmin,
    isHelper,
    can,
    canAccessPage,
    loadPermissions,
    requirePermission
} = usePermissions();

// 載入用戶權限（通常在組件掛載時）
onMounted(async () => {
    await loadPermissions();
});

// 檢查是否為管理員
if (isAdmin.value) {
    // 管理員專屬功能
}

// 檢查特定權限
if (can('manage_helpers')) {
    // 可以管理小幫手
}

// 檢查是否可訪問某頁面
if (canAccessPage('settings')) {
    // 可以訪問設定頁
}

// 需要權限時的確認檢查（會自動顯示錯誤訊息）
if (requirePermission('manage_helpers', '管理小幫手')) {
    // 執行操作
}

// 檢查多個權限（OR 邏輯）
if (canAny(['view_products', 'manage_products'])) {
    // 至少擁有其中一個權限
}

// 檢查多個權限（AND 邏輯）
if (canAll(['view_orders', 'manage_orders'])) {
    // 必須擁有所有權限
}
```

**可用權限列表：**
```javascript
// 管理員專屬權限
'manage_helpers'      // 管理小幫手
'manage_settings'     // 管理設定
'view_all_orders'     // 查看所有訂單
'export_data'         // 匯出數據

// 小幫手權限
'view_products'       // 查看商品
'manage_products'     // 管理商品
'view_orders'         // 查看訂單
'manage_orders'       // 管理訂單
'view_customers'      // 查看客戶
'manage_shipments'    // 管理出貨
```

**何時使用：**
- 需要根據用戶角色顯示/隱藏 UI
- 需要在執行操作前檢查權限
- 需要顯示權限相關資訊

**優點：**
- 統一權限檢查邏輯
- 自動提示權限不足
- 支援複雜權限組合
- 易於擴展新權限

---

## 📝 開發指南

### 如何建立新的 Composable

1. **檔案命名：** 使用 `use` 開頭的駝峰命名（如 `useMyFeature.js`）

2. **結構範本：**
```javascript
/**
 * 功能說明
 * @version 1.0.0
 * @date YYYY-MM-DD
 */
function useMyFeature() {
    const { ref, computed } = Vue;

    // 1. 狀態
    const data = ref(null);
    const loading = ref(false);
    const error = ref(null);

    // 2. 計算屬性
    const someComputed = computed(() => {
        return data.value ? data.value.length : 0;
    });

    // 3. 方法
    const doSomething = async () => {
        loading.value = true;
        try {
            // 實現邏輯
        } catch (err) {
            error.value = err.message;
            throw err;
        } finally {
            loading.value = false;
        }
    };

    // 4. 公開接口
    return {
        // 狀態
        data,
        loading,
        error,

        // 計算屬性
        someComputed,

        // 方法
        doSomething
    };
}
```

3. **注意事項：**
   - 使用全局函數而非 ES6 modules（WordPress 環境相容性）
   - 提供完整的 JSDoc 註釋
   - 包含使用範例
   - 處理錯誤情況
   - 提供合理的預設值

### 如何使用 Composables

1. **在組件中引入：**
```javascript
setup() {
    const { ref, onMounted } = Vue;

    // 使用 composable
    const { formatPrice } = useCurrency();
    const { get, post } = useApi();
    const { isAdmin, can } = usePermissions();

    // ... 其他邏輯

    return {
        formatPrice,
        isAdmin,
        can
    };
}
```

2. **在模板中使用：**
```html
<template>
    <div v-if="isAdmin">
        <p>{{ formatPrice(1000) }}</p>
    </div>
</template>
```

---

## 🧪 測試

### 手動測試步驟

1. **測試 useCurrency：**
   - 打開任何商品頁面
   - 確認價格顯示正確格式
   - 確認幣別符號正確

2. **測試 useApi：**
   - 打開瀏覽器 DevTools → Network
   - 執行 API 調用
   - 確認 `X-WP-Nonce` header 存在
   - 確認錯誤提示正確顯示
   - 確認 loading 狀態正確

3. **測試 usePermissions：**
   - 以管理員身份登入
   - 確認 `v-if="isAdmin"` 的元素顯示
   - 以小幫手身份登入
   - 確認權限受限的元素隱藏

---

## 📚 參考資源

- [Vue 3 Composition API 官方文檔](https://vuejs.org/guide/reusability/composables.html)
- [WordPress REST API 認證](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/)
- [項目編碼規範](../../../docs/development/CODING-STANDARDS.md)

---

## 🔄 版本歷史

| 版本 | 日期 | 更新內容 |
|------|------|----------|
| 1.0.0 | 2026-01-24 | 初始版本：useCurrency, useApi, usePermissions |

---

**最後更新**：2026-01-24
**維護者**：Development Team
