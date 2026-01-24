# WordPress 外掛開發最佳實踐

> **來源**: 從 BuyGo+1 專案中提煉的實戰經驗
> **適用對象**: 中高級 WordPress 開發者、AI 開發助手
> **更新日期**: 2026-01-24

---

## 📋 目錄

1. [代碼組織](#代碼組織)
2. [效能優化](#效能優化)
3. [安全強化](#安全強化)
4. [錯誤處理](#錯誤處理)
5. [資料庫最佳化](#資料庫最佳化)
6. [前端最佳實踐](#前端最佳實踐)
7. [除錯與日誌](#除錯與日誌)
8. [版本控制](#版本控制)
9. [部署流程](#部署流程)
10. [維護與更新](#維護與更新)

---

## 代碼組織

### 服務層設計模式

#### 單例模式實作

**為什麼需要單例**:
- 避免重複初始化
- 確保狀態一致性
- 減少記憶體消耗

**標準實作**:

```php
class Service_Name {

    private static $instance = null;
    private $initialized = false;

    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (!$this->initialized) {
            $this->initialize();
            $this->initialized = true;
        }
    }

    private function initialize() {
        // 初始化邏輯
    }

    // 防止克隆
    private function __clone() {}

    // 防止反序列化
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
```

#### 依賴注入 (可選)

```php
class Order_Service {

    private $product_service;
    private $customer_service;

    private function __construct() {
        // 注入依賴
        $this->product_service = Product_Service::getInstance();
        $this->customer_service = Customer_Service::getInstance();
    }

    public function createOrder($data) {
        // 使用注入的服務
        $product = $this->product_service->getById($data['product_id']);
        $customer = $this->customer_service->getById($data['customer_id']);

        // 建立訂單邏輯
    }
}
```

### 命名空間使用

```php
<?php
namespace YourPlugin\Services;

use YourPlugin\Core\Database;
use YourPlugin\Utilities\Logger;

class Product_Service {
    // ...
}
```

**好處**:
- 避免命名衝突
- 更清晰的代碼結構
- 支援 PSR-4 自動載入

---

## 效能優化

### 1. 資料庫查詢優化

#### 使用 Object Cache

```php
public function getProduct($product_id) {
    // 嘗試從快取讀取
    $cache_key = "product_{$product_id}";
    $product = wp_cache_get($cache_key, 'your-plugin');

    if (false !== $product) {
        return $product;
    }

    // 快取不存在,查詢資料庫
    global $wpdb;
    $product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d",
        $product_id
    ));

    // 快取結果 (15 分鐘)
    if ($product) {
        wp_cache_set($cache_key, $product, 'your-plugin', 900);
    }

    return $product;
}
```

#### 批次查詢

```php
// ❌ 錯誤：N+1 問題
foreach ($order_ids as $id) {
    $order = $wpdb->get_row("SELECT * FROM orders WHERE id = {$id}");
    // ...
}

// ✅ 正確：批次查詢
$ids = implode(',', array_map('intval', $order_ids));
$orders = $wpdb->get_results("SELECT * FROM orders WHERE id IN ({$ids})");
```

#### 使用索引

```sql
-- 為常用查詢欄位建立索引
CREATE TABLE wp_yourplugin_products (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    sku varchar(100) DEFAULT '',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY sku (sku),              -- 索引 1
    KEY created_at (created_at) -- 索引 2
);
```

### 2. 資源載入優化

#### 條件式載入

```php
public function enqueue_scripts() {
    $screen = get_current_screen();

    // ✅ 只在特定頁面載入
    if ($screen->id === 'toplevel_page_your-plugin-products') {
        wp_enqueue_script('your-plugin-products', ...);
    }

    // ❌ 不要在所有頁面載入
    // wp_enqueue_script('your-plugin-all', ...);
}
```

#### 延遲載入

```php
// 在頁尾載入腳本
wp_enqueue_script('your-plugin', $url, array(), $version, true); // true = footer
```

#### 資源壓縮

```bash
# 使用工具壓縮 CSS/JS
npm install -g uglify-js uglifycss

# 壓縮 JS
uglifyjs source.js -c -m -o source.min.js

# 壓縮 CSS
uglifycss source.css > source.min.css
```

### 3. Transients API

```php
// 快取 API 回應 (1 小時)
function get_api_data() {
    $cache_key = 'api_data_cache';
    $data = get_transient($cache_key);

    if (false === $data) {
        $data = wp_remote_get('https://api.example.com/data');
        set_transient($cache_key, $data, HOUR_IN_SECONDS);
    }

    return $data;
}
```

---

## 安全強化

### 1. 輸入驗證與清理

#### 全面的驗證策略

```php
public function validateAndSanitize($data) {
    $clean = array();

    // 文字欄位
    if (isset($data['name'])) {
        $clean['name'] = sanitize_text_field($data['name']);
        if (empty($clean['name'])) {
            return new WP_Error('invalid_name', '名稱不能為空');
        }
    }

    // Email
    if (isset($data['email'])) {
        $clean['email'] = sanitize_email($data['email']);
        if (!is_email($clean['email'])) {
            return new WP_Error('invalid_email', 'Email 格式不正確');
        }
    }

    // URL
    if (isset($data['website'])) {
        $clean['website'] = esc_url_raw($data['website']);
    }

    // 數字
    if (isset($data['quantity'])) {
        $clean['quantity'] = absint($data['quantity']);
        if ($clean['quantity'] < 0) {
            return new WP_Error('invalid_quantity', '數量必須大於 0');
        }
    }

    // HTML 內容
    if (isset($data['description'])) {
        $clean['description'] = wp_kses_post($data['description']);
    }

    return $clean;
}
```

### 2. 權限檢查分層

```php
// Layer 1: 頁面層級
add_menu_page(
    'Products',
    'Products',
    'edit_posts',  // 需要編輯文章權限
    'your-plugin-products',
    array($this, 'display_products_page')
);

// Layer 2: API 層級
public function create_item_permissions_check($request) {
    // 檢查用戶角色
    if (!current_user_can('edit_posts')) {
        return new WP_Error('forbidden', '權限不足', array('status' => 403));
    }

    // 檢查 nonce
    if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
        return new WP_Error('invalid_nonce', 'Nonce 驗證失敗', array('status' => 403));
    }

    return true;
}

// Layer 3: 服務層級
public function updateProduct($product_id, $data, $user_id) {
    // 檢查用戶是否有權限編輯這個商品
    if (!$this->userCanEditProduct($user_id, $product_id)) {
        return new WP_Error('forbidden', '沒有權限編輯此商品');
    }

    // 執行更新
}
```

### 3. SQL 注入完全防護

```php
// ✅ 最佳實踐：永遠使用 prepare()
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table} WHERE status = %s AND user_id = %d",
    $status,
    $user_id
));

// ✅ 處理 IN 查詢
$ids = array_map('intval', $ids); // 清理
$placeholders = implode(',', array_fill(0, count($ids), '%d'));
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table} WHERE id IN ({$placeholders})",
    ...$ids
));

// ❌ 絕對不要這樣做
// $results = $wpdb->get_results("SELECT * FROM {$table} WHERE id = {$id}");
```

### 4. CSRF 防護

```php
// 表單
<form method="post">
    <?php wp_nonce_field('your_action', 'your_nonce'); ?>
    <!-- 表單欄位 -->
</form>

// 驗證
if (!isset($_POST['your_nonce']) || !wp_verify_nonce($_POST['your_nonce'], 'your_action')) {
    wp_die('安全驗證失敗');
}
```

### 5. XSS 防護

```php
// 輸出到 HTML
echo esc_html($user_input);

// 輸出到屬性
echo '<div data-name="' . esc_attr($name) . '">';

// 輸出 URL
echo '<a href="' . esc_url($url) . '">';

// 輸出 JavaScript
echo '<script>var name = ' . wp_json_encode($name) . ';</script>';
```

---

## 錯誤處理

### 1. 分層錯誤處理

```php
// 服務層
class Product_Service {

    public function getProduct($id) {
        try {
            // 參數驗證
            if (empty($id) || !is_numeric($id)) {
                throw new InvalidArgumentException('Invalid product ID');
            }

            // 資料庫查詢
            global $wpdb;
            $product = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            ));

            // 檢查資料庫錯誤
            if ($wpdb->last_error) {
                throw new Exception($wpdb->last_error);
            }

            // 檢查結果
            if (!$product) {
                throw new Exception('Product not found');
            }

            return $product;

        } catch (InvalidArgumentException $e) {
            error_log('[Product Service] Invalid argument: ' . $e->getMessage());
            return new WP_Error('invalid_argument', $e->getMessage());

        } catch (Exception $e) {
            error_log('[Product Service] Error: ' . $e->getMessage());
            return new WP_Error('service_error', '取得商品失敗');
        }
    }
}

// API 層
class Products_API {

    public function get_item($request) {
        $id = $request['id'];
        $service = Product_Service::getInstance();
        $product = $service->getProduct($id);

        // 處理錯誤
        if (is_wp_error($product)) {
            return rest_ensure_response($product);
        }

        return rest_ensure_response($product);
    }
}
```

### 2. 用戶友善的錯誤訊息

```php
// ❌ 不好的錯誤訊息
return new WP_Error('error', 'Error in line 42');

// ✅ 好的錯誤訊息
return new WP_Error(
    'product_not_found',
    '找不到指定的商品，請確認商品 ID 是否正確',
    array('status' => 404)
);
```

### 3. 錯誤日誌標準化

```php
class Logger {

    public static function log($level, $message, $context = array()) {
        $timestamp = date('Y-m-d H:i:s');
        $context_str = !empty($context) ? ' | ' . json_encode($context) : '';

        $log_message = sprintf(
            "[%s] [%s] %s%s\n",
            $timestamp,
            strtoupper($level),
            $message,
            $context_str
        );

        // 寫入自定義日誌檔案
        $log_file = WP_CONTENT_DIR . '/your-plugin.log';
        error_log($log_message, 3, $log_file);

        // 嚴重錯誤也寫入 WordPress debug.log
        if ($level === 'error' || $level === 'critical') {
            error_log($log_message);
        }
    }

    public static function error($message, $context = array()) {
        self::log('error', $message, $context);
    }

    public static function warning($message, $context = array()) {
        self::log('warning', $message, $context);
    }

    public static function info($message, $context = array()) {
        self::log('info', $message, $context);
    }
}

// 使用
Logger::error('Product creation failed', array(
    'user_id' => $user_id,
    'data' => $data
));
```

---

## 資料庫最佳化

### 1. 資料表設計原則

```sql
CREATE TABLE wp_yourplugin_products (
    -- 主鍵：永遠使用 bigint(20) AUTO_INCREMENT
    id bigint(20) NOT NULL AUTO_INCREMENT,

    -- 外鍵：與其他表關聯
    category_id bigint(20) DEFAULT NULL,

    -- 字串：適當長度，避免 TEXT
    sku varchar(100) NOT NULL,
    name varchar(255) NOT NULL,

    -- 數字：使用適當的型別
    price decimal(10,2) DEFAULT 0.00,
    quantity int(11) DEFAULT 0,

    -- 布林值：使用 tinyint(1)
    is_active tinyint(1) DEFAULT 1,

    -- 時間戳：自動管理
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- 主鍵
    PRIMARY KEY (id),

    -- 索引：為常用查詢欄位建立
    KEY category_id (category_id),
    KEY sku (sku),
    KEY is_active (is_active),
    KEY created_at (created_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. 查詢優化

```php
// ❌ 錯誤：SELECT *
$products = $wpdb->get_results("SELECT * FROM {$table}");

// ✅ 正確：只選擇需要的欄位
$products = $wpdb->get_results("SELECT id, name, price FROM {$table}");

// ❌ 錯誤：沒有 LIMIT
$products = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");

// ✅ 正確：使用 LIMIT
$products = $wpdb->get_results($wpdb->prepare(
    "SELECT id, name, price FROM {$table} ORDER BY created_at DESC LIMIT %d",
    20
));

// ✅ 使用索引
$products = $wpdb->get_results($wpdb->prepare(
    "SELECT id, name FROM {$table} WHERE is_active = 1 ORDER BY created_at DESC LIMIT %d",
    20
));
```

### 3. 資料庫升級管理

```php
// includes/class-activator.php

public static function activate() {
    $current_version = get_option('yourplugin_db_version', '0');
    $new_version = '1.2';

    if (version_compare($current_version, $new_version, '<')) {
        self::upgrade_database($current_version, $new_version);
        update_option('yourplugin_db_version', $new_version);
    }
}

private static function upgrade_database($from, $to) {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // 版本 1.1: 新增欄位
    if (version_compare($from, '1.1', '<')) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN status varchar(20) DEFAULT 'pending'");
    }

    // 版本 1.2: 新增索引
    if (version_compare($from, '1.2', '<')) {
        $wpdb->query("ALTER TABLE {$table} ADD KEY status (status)");
    }
}
```

---

## 前端最佳實踐

### 1. Vue 組件化

#### 組件分離原則

```
admin/js/components/
├── ProductsPage.js          # 完整頁面邏輯
├── OrdersPage.js           # 完整頁面邏輯
└── shared/
    ├── SearchBox.js        # 可複用的搜尋框
    ├── Pagination.js       # 可複用的分頁
    └── Modal.js            # 可複用的彈窗
```

#### Composables 模式

```javascript
// includes/views/composables/usePagination.js

export function usePagination(itemsPerPage = 20) {
    const currentPage = Vue.ref(1);
    const totalItems = Vue.ref(0);

    const totalPages = Vue.computed(() => {
        return Math.ceil(totalItems.value / itemsPerPage);
    });

    const goToPage = (page) => {
        if (page >= 1 && page <= totalPages.value) {
            currentPage.value = page;
        }
    };

    const nextPage = () => {
        goToPage(currentPage.value + 1);
    };

    const prevPage = () => {
        goToPage(currentPage.value - 1);
    };

    return {
        currentPage,
        totalItems,
        totalPages,
        goToPage,
        nextPage,
        prevPage
    };
}

// 在組件中使用
const { currentPage, totalPages, nextPage, prevPage } = usePagination(20);
```

### 2. API 請求標準化

```javascript
// 建立 API 客戶端
class APIClient {
    constructor(baseURL, nonce) {
        this.baseURL = baseURL;
        this.nonce = nonce;
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const headers = {
            'X-WP-Nonce': this.nonce,
            'Content-Type': 'application/json',
            ...options.headers
        };

        try {
            const response = await fetch(url, {
                ...options,
                headers
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();

        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    }

    post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    put(endpoint, data) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
}

// 使用
const api = new APIClient('/wp-json/yourplugin/v1', wpNonce);

async loadProducts() {
    this.loading = true;
    try {
        this.products = await api.get('/products');
    } catch (error) {
        alert('載入失敗');
    } finally {
        this.loading = false;
    }
}
```

### 3. CSS 組織

```css
/* admin/css/products.css */

/* 1. 使用 BEM 命名 + 頁面前綴 */
.products-page { }
.products-page__header { }
.products-page__content { }
.products-page__footer { }

.products-list { }
.products-list__item { }
.products-list__item--active { }

/* 2. 使用 CSS 變數 */
:root {
    --products-primary-color: #3b82f6;
    --products-danger-color: #ef4444;
    --products-spacing: 1rem;
}

.products-btn {
    background-color: var(--products-primary-color);
    padding: var(--products-spacing);
}

/* 3. 響應式設計 */
@media (max-width: 768px) {
    .products-list__item {
        flex-direction: column;
    }
}
```

---

## 除錯與日誌

### 除錯模式分層

```php
// wp-config.php

// 開發環境
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);
define('SCRIPT_DEBUG', true);

// 測試環境
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// 生產環境
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);
```

### Webhook 日誌系統

參考 BuyGo+1 的實作：

```php
class Webhook_Logger {

    public static function log($event_type, $payload, $status = 'success') {
        global $wpdb;
        $table = $wpdb->prefix . 'yourplugin_webhook_logs';

        $wpdb->insert($table, array(
            'event_type' => $event_type,
            'payload' => json_encode($payload),
            'status' => $status,
            'created_at' => current_time('mysql')
        ));
    }

    public static function get_recent_logs($limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . 'yourplugin_webhook_logs';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }
}
```

---

## 版本控制

### Git Commit 規範

```bash
# 格式
<type>(<scope>): <subject>

<body>

<footer>

# 類型
feat:     新功能
fix:      Bug 修復
docs:     文檔更新
style:    代碼格式
refactor: 重構
test:     測試
chore:    雜項

# 範例
feat(products): 新增商品批次匯入功能

- 支援 CSV 格式
- 自動驗證資料
- 提供進度顯示

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>
```

### .gitignore 範例

```
# WordPress
wp-config.php
wp-content/uploads/
wp-content/cache/

# IDE
.vscode/
.idea/
*.swp

# Dependencies
node_modules/
vendor/

# Build
build/
*.zip

# Logs
*.log
error_log
debug.log

# OS
.DS_Store
Thumbs.db

# 外掛特定
your-plugin.log
```

---

## 部署流程

### 自動化打包

```bash
#!/bin/bash
# scripts/deploy.sh

set -e

VERSION=$1
if [ -z "$VERSION" ]; then
    echo "Usage: ./deploy.sh <version>"
    exit 1
fi

# 1. 更新版本號
sed -i '' "s/Version: .*/Version: $VERSION/" your-plugin.php

# 2. 執行測試 (如果有)
# ./vendor/bin/phpunit

# 3. 建立生產版本
bash scripts/build-production.sh

# 4. Git 提交
git add .
git commit -m "chore: release v$VERSION"
git tag "v$VERSION"
git push origin main --tags

echo "✓ 部署完成: v$VERSION"
```

### 環境變數管理

```php
// 不要將敏感資訊寫死在代碼中
// ❌ 錯誤
$api_key = 'sk-1234567890abcdef';

// ✅ 正確：使用環境變數或 WordPress 設定
$api_key = defined('YOURPLUGIN_API_KEY') ? YOURPLUGIN_API_KEY : get_option('yourplugin_api_key');
```

---

## 維護與更新

### 資料庫遷移策略

```php
public static function migrate_v2_to_v3() {
    global $wpdb;
    $old_table = $wpdb->prefix . 'yourplugin_products_old';
    $new_table = $wpdb->prefix . 'yourplugin_products';

    // 1. 備份舊資料
    $wpdb->query("CREATE TABLE {$old_table}_backup AS SELECT * FROM {$old_table}");

    // 2. 遷移資料
    $wpdb->query("
        INSERT INTO {$new_table} (id, name, sku, price)
        SELECT id, product_name AS name, product_sku AS sku, product_price AS price
        FROM {$old_table}
    ");

    // 3. 驗證
    $old_count = $wpdb->get_var("SELECT COUNT(*) FROM {$old_table}");
    $new_count = $wpdb->get_var("SELECT COUNT(*) FROM {$new_table}");

    if ($old_count !== $new_count) {
        // 回滾
        $wpdb->query("DROP TABLE {$new_table}");
        $wpdb->query("RENAME TABLE {$old_table}_backup TO {$old_table}");
        throw new Exception('Migration failed: count mismatch');
    }

    // 4. 清理
    $wpdb->query("DROP TABLE {$old_table}_backup");
}
```

### 向後兼容

```php
// 檢查函數是否存在
if (!function_exists('wp_get_current_user')) {
    require_once(ABSPATH . 'wp-includes/pluggable.php');
}

// 檢查 WordPress 版本
if (version_compare(get_bloginfo('version'), '5.8', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>Your Plugin requires WordPress 5.8 or higher.</p></div>';
    });
    return;
}
```

---

## 總結

### 品質檢查清單

完成以下檢查，確保外掛達到專業水準：

#### 代碼品質 ✓
- [ ] 所有服務使用單例模式
- [ ] 職責分離清晰
- [ ] 錯誤處理完整
- [ ] 參數驗證完整
- [ ] 日誌記錄清晰

#### 安全性 ✓
- [ ] 所有輸入經過驗證和清理
- [ ] 所有輸出經過轉義
- [ ] SQL 使用 prepare()
- [ ] API 有權限檢查
- [ ] 表單有 nonce 驗證

#### 效能 ✓
- [ ] 使用物件快取
- [ ] 資料庫有索引
- [ ] 批次查詢優化
- [ ] 條件式載入資源
- [ ] 使用 Transients API

#### 前端 ✓
- [ ] Vue 組件化
- [ ] CSS 使用前綴
- [ ] API 請求標準化
- [ ] 錯誤處理友善
- [ ] 響應式設計

#### 維護性 ✓
- [ ] Git commit 規範
- [ ] 版本控制清晰
- [ ] 文檔完整
- [ ] 除錯工具完善
- [ ] 自動化部署

---

**參考專案**: BuyGo+1 v0.03
**文檔維護**: BuyGo Development Team
**最後更新**: 2026-01-24
