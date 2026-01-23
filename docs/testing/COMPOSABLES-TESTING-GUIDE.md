# Composables 測試指南

本文檔說明如何測試新建立的 Vue Composables（useApi、usePermissions）。

---

## 📋 測試前準備

### 1. 確保 Composables 已載入

檢查 composables 是否正確載入到頁面中。打開瀏覽器 DevTools Console，輸入：

```javascript
// 檢查 useApi 是否存在
typeof useApi === 'function'  // 應該返回 true

// 檢查 usePermissions 是否存在
typeof usePermissions === 'function'  // 應該返回 true

// 檢查 useCurrency 是否存在
typeof useCurrency === 'function'  // 應該返回 true
```

如果返回 `false`，表示 composables 未正確載入。

---

## 🧪 測試方法

### 方法 1：瀏覽器 Console 測試（快速驗證）

最簡單的測試方式，適合快速驗證功能。

#### 測試 useApi

1. **打開任何管理頁面**（例如：商品頁、訂單頁）
2. **打開瀏覽器 DevTools**（F12 或 Cmd+Option+I）
3. **切換到 Console 標籤**
4. **執行以下測試代碼：**

```javascript
// ========================================
// 測試 1：初始化 useApi
// ========================================
const api = useApi();
console.log('useApi 初始化成功:', api);

// 驗證返回的方法存在
console.log('get 方法存在:', typeof api.get === 'function');
console.log('post 方法存在:', typeof api.post === 'function');
console.log('put 方法存在:', typeof api.put === 'function');
console.log('delete 方法存在:', typeof api.delete === 'function');

// ========================================
// 測試 2：GET 請求
// ========================================
// 測試讀取訂單列表
api.get('/wp-json/buygo-plus-one/v1/orders?page=1&per_page=5')
    .then(result => {
        console.log('✅ GET 請求成功:', result);
        console.log('訂單數量:', result.data?.length);
    })
    .catch(err => {
        console.error('❌ GET 請求失敗:', err);
    });

// ========================================
// 測試 3：檢查 loading 狀態
// ========================================
console.log('初始 loading 狀態:', api.isLoading.value);

// 發送請求並觀察 loading 狀態變化
(async () => {
    console.log('請求前 loading:', api.isLoading.value);

    try {
        await api.get('/wp-json/buygo-plus-one/v1/orders?page=1&per_page=1');
        console.log('請求後 loading:', api.isLoading.value);
    } catch (err) {
        console.error('錯誤:', err);
    }
})();

// ========================================
// 測試 4：錯誤處理
// ========================================
// 測試無效的 API 端點
api.get('/wp-json/buygo-plus-one/v1/invalid-endpoint', {
    showError: true  // 應該顯示錯誤 toast
})
    .then(result => {
        console.log('不應該執行到這裡:', result);
    })
    .catch(err => {
        console.log('✅ 錯誤處理正常:', err.message);
    });

// ========================================
// 測試 5：POST 請求（模擬更新狀態）
// ========================================
// 注意：這個測試會實際修改數據，謹慎使用
// api.post('/wp-json/buygo-plus-one/v1/debug/log', {
//     module: 'Test',
//     message: 'useApi 測試',
//     level: 'info',
//     data: { test: true }
// }, {
//     showSuccess: true,  // 應該顯示成功 toast
//     successMessage: 'POST 測試成功'
// })
//     .then(result => {
//         console.log('✅ POST 請求成功:', result);
//     })
//     .catch(err => {
//         console.error('❌ POST 請求失敗:', err);
//     });
```

#### 測試 usePermissions

```javascript
// ========================================
// 測試 1：初始化 usePermissions
// ========================================
const permissions = usePermissions();
console.log('usePermissions 初始化成功:', permissions);

// 驗證返回的方法和狀態存在
console.log('isAdmin 存在:', permissions.isAdmin !== undefined);
console.log('isHelper 存在:', permissions.isHelper !== undefined);
console.log('can 方法存在:', typeof permissions.can === 'function');
console.log('canAccessPage 方法存在:', typeof permissions.canAccessPage === 'function');

// ========================================
// 測試 2：載入權限
// ========================================
permissions.loadPermissions()
    .then(data => {
        console.log('✅ 權限載入成功:', data);
        console.log('是否為管理員:', permissions.isAdmin.value);
        console.log('是否為小幫手:', permissions.isHelper.value);
        console.log('用戶角色:', permissions.userRole.value);
        console.log('用戶 ID:', permissions.userId.value);
        console.log('顯示名稱:', permissions.displayName.value);
    })
    .catch(err => {
        console.error('❌ 權限載入失敗:', err);
    });

// ========================================
// 測試 3：權限檢查
// ========================================
// 等待權限載入後執行
setTimeout(() => {
    console.log('=== 權限檢查測試 ===');
    console.log('可以管理小幫手:', permissions.can('manage_helpers'));
    console.log('可以查看商品:', permissions.can('view_products'));
    console.log('可以訪問設定頁:', permissions.canAccessPage('settings'));
    console.log('可以訪問商品頁:', permissions.canAccessPage('products'));

    // 測試多個權限（OR 邏輯）
    console.log('擁有任一權限:', permissions.canAny(['view_products', 'manage_products']));

    // 測試多個權限（AND 邏輯）
    console.log('擁有所有權限:', permissions.canAll(['view_products', 'manage_products']));
}, 2000);

// ========================================
// 測試 4：權限不足提示
// ========================================
// 模擬權限不足的操作
permissions.requirePermission('non_existent_permission', '執行測試操作');
// 應該顯示「您沒有權限執行測試操作」的錯誤 toast
```

---

### 方法 2：在組件中測試（實際使用場景）

創建一個測試頁面或在現有組件中測試。

#### 測試檔案：test-composables.html

創建 `/Users/fishtv/Development/buygo-plus-one-dev/admin/partials/test-composables.php`：

```php
<?php
/**
 * Composables 測試頁面
 *
 * 用於測試 useApi 和 usePermissions composables
 */

// 確保在 WordPress 環境中
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="test-composables-app" class="wrap">
    <h1>🧪 Composables 測試頁面</h1>

    <!-- useApi 測試 -->
    <div class="buygo-card mb-4">
        <h2>1. useApi 測試</h2>

        <div class="mb-3">
            <button @click="testApiGet" class="button button-primary" :disabled="apiLoading">
                {{ apiLoading ? '測試中...' : '測試 GET 請求' }}
            </button>
            <button @click="testApiPost" class="button" :disabled="apiLoading">
                測試 POST 請求
            </button>
            <button @click="testApiError" class="button" :disabled="apiLoading">
                測試錯誤處理
            </button>
        </div>

        <div v-if="apiResult" class="notice notice-success">
            <p><strong>✅ API 測試結果：</strong></p>
            <pre>{{ JSON.stringify(apiResult, null, 2) }}</pre>
        </div>

        <div v-if="apiError" class="notice notice-error">
            <p><strong>❌ API 錯誤：</strong>{{ apiError }}</p>
        </div>

        <div class="mt-2">
            <p><strong>Loading 狀態：</strong> {{ apiLoading ? '載入中 🔄' : '閒置 ✅' }}</p>
        </div>
    </div>

    <!-- usePermissions 測試 -->
    <div class="buygo-card mb-4">
        <h2>2. usePermissions 測試</h2>

        <div class="mb-3">
            <button @click="testLoadPermissions" class="button button-primary" :disabled="permLoading">
                {{ permLoading ? '載入中...' : '載入權限' }}
            </button>
            <button @click="testCheckPermissions" class="button" :disabled="!permLoaded">
                檢查權限
            </button>
        </div>

        <div v-if="permLoaded" class="notice notice-info">
            <p><strong>📋 當前權限資訊：</strong></p>
            <ul>
                <li><strong>是否為管理員：</strong> {{ isAdmin ? '✅ 是' : '❌ 否' }}</li>
                <li><strong>是否為小幫手：</strong> {{ isHelper ? '✅ 是' : '❌ 否' }}</li>
                <li><strong>用戶角色：</strong> {{ userRole || '未登入' }}</li>
                <li><strong>用戶 ID：</strong> {{ userId || 'N/A' }}</li>
                <li><strong>顯示名稱：</strong> {{ displayName || 'N/A' }}</li>
            </ul>
        </div>

        <div v-if="permChecks" class="notice notice-success">
            <p><strong>✅ 權限檢查結果：</strong></p>
            <ul>
                <li v-for="(result, key) in permChecks" :key="key">
                    <strong>{{ key }}:</strong> {{ result ? '✅ 有權限' : '❌ 無權限' }}
                </li>
            </ul>
        </div>
    </div>

    <!-- useCurrency 測試 -->
    <div class="buygo-card">
        <h2>3. useCurrency 測試</h2>

        <div class="notice notice-info">
            <p><strong>💴 價格格式化測試：</strong></p>
            <ul>
                <li>1000 JPY: {{ formatPrice(1000, 'JPY') }}</li>
                <li>500 TWD: {{ formatPrice(500, 'TWD') }}</li>
                <li>99.99 USD: {{ formatPrice(99.99, 'USD') }}</li>
                <li>系統預設幣別 (1234): {{ formatPrice(1234) }}</li>
            </ul>
        </div>
    </div>
</div>

<script>
const { createApp } = Vue;

createApp({
    setup() {
        const { ref } = Vue;

        // ========================================
        // useApi 測試
        // ========================================
        const { get, post, isLoading: apiLoading, error: apiErrorRef } = useApi();
        const apiResult = ref(null);
        const apiError = ref(null);

        const testApiGet = async () => {
            apiResult.value = null;
            apiError.value = null;

            try {
                const result = await get('/wp-json/buygo-plus-one/v1/orders?page=1&per_page=3', {
                    showError: true
                });
                apiResult.value = result;
            } catch (err) {
                apiError.value = err.message;
            }
        };

        const testApiPost = async () => {
            apiResult.value = null;
            apiError.value = null;

            try {
                const result = await post('/wp-json/buygo-plus-one/v1/debug/log', {
                    module: 'ComposablesTest',
                    message: 'useApi POST 測試',
                    level: 'info',
                    data: { timestamp: new Date().toISOString() }
                }, {
                    showSuccess: true,
                    successMessage: 'POST 測試成功'
                });
                apiResult.value = result;
            } catch (err) {
                apiError.value = err.message;
            }
        };

        const testApiError = async () => {
            apiResult.value = null;
            apiError.value = null;

            try {
                await get('/wp-json/buygo-plus-one/v1/invalid-endpoint', {
                    showError: true
                });
            } catch (err) {
                apiError.value = err.message;
                console.log('✅ 錯誤處理測試通過');
            }
        };

        // ========================================
        // usePermissions 測試
        // ========================================
        const {
            isAdmin,
            isHelper,
            userRole,
            userId,
            displayName,
            loading: permLoading,
            can,
            canAccessPage,
            loadPermissions
        } = usePermissions();

        const permLoaded = ref(false);
        const permChecks = ref(null);

        const testLoadPermissions = async () => {
            try {
                await loadPermissions();
                permLoaded.value = true;
                console.log('✅ 權限載入成功');
            } catch (err) {
                console.error('❌ 權限載入失敗:', err);
            }
        };

        const testCheckPermissions = () => {
            permChecks.value = {
                '管理小幫手': can('manage_helpers'),
                '管理設定': can('manage_settings'),
                '查看商品': can('view_products'),
                '管理商品': can('manage_products'),
                '查看訂單': can('view_orders'),
                '訪問設定頁': canAccessPage('settings'),
                '訪問商品頁': canAccessPage('products'),
                '訪問訂單頁': canAccessPage('orders')
            };
            console.log('✅ 權限檢查完成:', permChecks.value);
        };

        // ========================================
        // useCurrency 測試
        // ========================================
        const { formatPrice } = useCurrency();

        return {
            // useApi
            apiLoading,
            apiResult,
            apiError,
            testApiGet,
            testApiPost,
            testApiError,

            // usePermissions
            isAdmin,
            isHelper,
            userRole,
            userId,
            displayName,
            permLoading,
            permLoaded,
            permChecks,
            testLoadPermissions,
            testCheckPermissions,

            // useCurrency
            formatPrice
        };
    }
}).mount('#test-composables-app');
</script>

<style>
.buygo-card {
    background: white;
    padding: 20px;
    border: 1px solid #ccc;
    border-radius: 4px;
    margin-bottom: 20px;
}

.mb-3 { margin-bottom: 15px; }
.mb-4 { margin-bottom: 20px; }
.mt-2 { margin-top: 10px; }

pre {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
    max-height: 300px;
}
</style>
```

#### 註冊測試頁面

在 `includes/admin/class-admin.php` 或類似的管理頁面註冊文件中添加：

```php
public function add_admin_menu() {
    // 其他選單項目...

    // 僅在開發環境顯示測試頁面
    if (defined('WP_DEBUG') && WP_DEBUG) {
        add_submenu_page(
            'buygo-plus-one',
            'Composables 測試',
            '🧪 測試',
            'manage_options',
            'buygo-test-composables',
            array($this, 'render_test_composables_page')
        );
    }
}

public function render_test_composables_page() {
    require_once BUYGO_PLUS_ONE_PATH . 'admin/partials/test-composables.php';
}
```

---

### 方法 3：Network 標籤檢查（驗證 HTTP 請求）

1. **打開瀏覽器 DevTools → Network 標籤**
2. **執行任何 API 調用**（使用上面的測試代碼）
3. **檢查請求詳情：**

**應該看到：**
```
Request URL: /wp-json/buygo-plus-one/v1/orders?page=1&per_page=5&_t=1706054400000
Request Method: GET
Status Code: 200 OK

Request Headers:
- X-WP-Nonce: abc123... ✅ 存在
- Cache-Control: no-cache ✅ 存在
- Pragma: no-cache ✅ 存在
```

**如果缺少 X-WP-Nonce：**
- ❌ wpNonce 未正確傳遞
- 檢查 `window.buygoWpNonce` 是否存在

---

## ✅ 驗證清單

完成以下檢查以確保 composables 正常運作：

### useApi 驗證

- [ ] `useApi()` 可以成功初始化
- [ ] `get()` 方法可以正常發送請求
- [ ] `post()` 方法可以正常發送請求
- [ ] `isLoading` 狀態正確反映載入狀態
- [ ] HTTP 請求包含 `X-WP-Nonce` header
- [ ] HTTP 請求包含防快取 headers
- [ ] GET 請求自動添加時間戳記（`_t=...`）
- [ ] 錯誤時顯示 toast 通知
- [ ] 成功時可選顯示 toast 通知
- [ ] `error` ref 正確保存錯誤訊息

### usePermissions 驗證

- [ ] `usePermissions()` 可以成功初始化
- [ ] `loadPermissions()` 可以正常載入權限
- [ ] `isAdmin` 正確反映用戶角色
- [ ] `isHelper` 正確反映用戶角色
- [ ] `can()` 正確檢查特定權限
- [ ] `canAccessPage()` 正確檢查頁面訪問權限
- [ ] `requirePermission()` 在無權限時顯示錯誤 toast
- [ ] `loading` 狀態正確反映載入狀態

### useCurrency 驗證（既有功能）

- [ ] `formatPrice()` 正確格式化價格
- [ ] 幣別符號正確顯示
- [ ] 千分位逗號正確添加

---

## 🐛 常見問題排查

### 問題 1：`useApi is not defined`

**原因：** Composables 未載入到頁面

**解決方案：**
1. 檢查 composables 檔案是否存在於正確位置
2. 確認頁面有 enqueue 這些 JS 檔案
3. 檢查瀏覽器 Console 是否有 JS 載入錯誤

### 問題 2：API 請求返回 401 Unauthorized

**原因：** wpNonce 未正確傳遞

**解決方案：**
1. 檢查 `window.buygoWpNonce` 是否存在
2. 檢查 PHP 端是否有設定 `wp_localize_script()`
3. 檢查 Network 標籤，確認 `X-WP-Nonce` header 存在

### 問題 3：權限載入失敗

**原因：** API 端點不存在或權限檢查失敗

**解決方案：**
1. 檢查 `/wp-json/buygo-plus-one/v1/settings/permissions` 端點是否存在
2. 確認用戶已登入
3. 檢查後端 API 是否有正確實現

### 問題 4：Toast 通知不顯示

**原因：** `showToast` 函數不存在

**解決方案：**
1. 檢查 `window.showToast` 是否存在
2. 確認 toast 函數已在頁面中定義
3. 檢查 Console 是否有相關錯誤

---

## 📊 測試報告範本

完成測試後，使用以下範本記錄結果：

```markdown
## Composables 測試報告

**測試日期：** YYYY-MM-DD
**測試者：** [您的名字]
**測試環境：** [瀏覽器 + 版本]

### useApi 測試結果

| 測試項目 | 結果 | 備註 |
|---------|------|------|
| 初始化 | ✅ / ❌ | |
| GET 請求 | ✅ / ❌ | |
| POST 請求 | ✅ / ❌ | |
| PUT 請求 | ✅ / ❌ | |
| DELETE 請求 | ✅ / ❌ | |
| Loading 狀態 | ✅ / ❌ | |
| 錯誤處理 | ✅ / ❌ | |
| wpNonce header | ✅ / ❌ | |
| 防快取 | ✅ / ❌ | |
| Toast 通知 | ✅ / ❌ | |

### usePermissions 測試結果

| 測試項目 | 結果 | 備註 |
|---------|------|------|
| 初始化 | ✅ / ❌ | |
| 載入權限 | ✅ / ❌ | |
| isAdmin 檢查 | ✅ / ❌ | |
| can() 檢查 | ✅ / ❌ | |
| canAccessPage() | ✅ / ❌ | |
| requirePermission() | ✅ / ❌ | |

### 整體評估

- **總測試項目：** XX
- **通過項目：** XX
- **失敗項目：** XX
- **通過率：** XX%

### 發現的問題

1. [問題描述]
2. [問題描述]

### 建議

1. [建議內容]
2. [建議內容]
```

---

## 🎯 下一步

測試完成後：

1. ✅ 如果所有測試通過 → 可以開始在實際組件中使用 composables
2. ⚠️ 如果部分測試失敗 → 根據「常見問題排查」解決問題
3. 📝 記錄測試結果 → 提交測試報告

---

**最後更新：** 2026-01-24
**維護者：** Development Team
