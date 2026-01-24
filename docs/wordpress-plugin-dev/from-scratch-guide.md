# WordPress 外掛從零開始開發指南

> **目標受眾**: AI 開發助手 + 人類開發者
> **適用場景**: 從零開始建立一個高品質、可維護的 WordPress 外掛
> **參考專案**: BuyGo+1 (本外掛即為最佳實踐範例)

---

## 📋 目錄

1. [開發前準備](#開發前準備)
2. [專案初始化](#專案初始化)
3. [架構設計](#架構設計)
4. [核心功能實作](#核心功能實作)
5. [前端開發](#前端開發)
6. [安全性](#安全性)
7. [測試與除錯](#測試與除錯)
8. [文檔與交付](#文檔與交付)
9. [常見問題](#常見問題)

---

## 開發前準備

### Step 1: 需求分析

#### 必須回答的問題

```markdown
## 外掛基本資訊
- [ ] 外掛名稱是什麼？
- [ ] 主要功能是什麼？（列出 3-5 個核心功能）
- [ ] 目標用戶是誰？（管理員、編輯者、訂閱者？）
- [ ] 是否需要資料庫？需要幾張表？

## 技術需求
- [ ] 需要前端 UI 嗎？（Vue、React、純 JS？）
- [ ] 需要 REST API 嗎？
- [ ] 需要與第三方服務整合嗎？（LINE、支付、Email？）
- [ ] 需要定時任務嗎？（WP-Cron？）

## 規模評估
- [ ] 預估代碼量？（< 5000 行：小型，5000-15000：中型，> 15000：大型）
- [ ] 預估開發時間？
- [ ] 需要多人協作嗎？
```

### Step 2: 技術選型

#### 外掛架構選擇

| 專案規模 | 建議架構 | 範例 |
|---------|---------|------|
| **小型** (< 5000 行) | 單一 PHP 檔案 + 簡單結構 | Hello Dolly |
| **中型** (5000-15000 行) | WordPress Plugin Boilerplate | **BuyGo+1** ⭐ |
| **大型** (> 15000 行) | 自定義框架 + Composer | WooCommerce |

**建議**: 99% 的情況下使用 **WordPress Plugin Boilerplate**

#### 前端技術選擇

| 需求 | 技術 | 優點 | 缺點 |
|------|------|------|------|
| **簡單表單** | 純 HTML + jQuery | 簡單、輕量 | 難以維護大型 UI |
| **互動式 UI** | Vue 3 | 輕量、易學、響應式 | 需要學習成本 |
| **複雜應用** | React | 生態豐富、強大 | 體積較大、複雜 |

**建議**: 使用 **Vue 3**（如 BuyGo+1）

### Step 3: 開發環境設置

#### 必備工具

```bash
# 1. Local by Flywheel (推薦) 或 XAMPP
# 2. IDE: VSCode + PHP Intelephense
# 3. Git 版本控制
# 4. WP-CLI (選擇性，但強烈建議)

# 安裝 WP-CLI
brew install wp-cli  # macOS
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar  # Linux
```

#### 開發用插件

```bash
# 使用 WP-CLI 安裝開發必備插件
wp plugin install query-monitor --activate  # 除錯工具
wp plugin install debug-bar --activate       # 除錯面板
```

---

## 專案初始化

### Step 1: 使用 WordPress Plugin Boilerplate

#### 1.1 下載 Boilerplate

```bash
# 方法 1: 使用生成器 (推薦)
# 訪問: https://wppb.me/
# 填寫外掛資訊，下載 zip

# 方法 2: 克隆 GitHub 倉庫
git clone https://github.com/DevinVinson/WordPress-Plugin-Boilerplate.git my-plugin
cd my-plugin
```

#### 1.2 自定義外掛資訊

修改以下檔案中的佔位符：

```bash
# 需要替換的佔位符
Plugin Name → Your Plugin Name
plugin-name → your-plugin-slug
Plugin_Name → Your_Plugin_Name
PLUGIN_NAME → YOUR_PLUGIN_NAME
```

**工具**: 使用 find-and-replace 工具批量替換

```bash
# macOS/Linux 範例
find . -type f -name "*.php" -exec sed -i '' 's/plugin-name/buygo-plus-one/g' {} +
```

### Step 2: 建立基礎結構

#### 標準目錄結構

```
your-plugin/
├── your-plugin.php              # 主入口檔案
├── includes/                    # PHP 核心代碼
│   ├── class-your-plugin.php   # 主類別
│   ├── class-loader.php        # Hook 加載器
│   ├── class-activator.php     # 啟用處理
│   ├── class-deactivator.php   # 停用處理
│   ├── services/               # 業務邏輯層 ⭐
│   │   └── class-*-service.php
│   ├── api/                    # REST API 層 ⭐
│   │   └── class-*-api.php
│   └── views/                  # 前端資源 ⭐
│       ├── composables/        # Vue Composables
│       └── components/         # 共享組件
├── admin/                      # 後台管理
│   ├── class-admin.php        # 後台類別
│   ├── css/                   # CSS 檔案
│   ├── js/                    # JavaScript 檔案
│   │   └── components/        # Vue 組件
│   └── partials/              # 頁面模板
├── public/                    # 前台
│   ├── class-public.php
│   ├── css/
│   └── js/
├── assets/                    # 共用資源
├── languages/                 # 多語言
├── docs/                      # 文檔
│   ├── development/          # 開發文檔
│   ├── planning/             # 計畫文檔
│   ├── bugfix/               # Bug 修復記錄
│   └── testing/              # 測試文檔
├── scripts/                  # 自動化腳本
├── templates/                # 代碼範本
└── tests/                    # 測試檔案
```

### Step 3: 初始化 Git 倉庫

```bash
# 1. 初始化 Git
git init

# 2. 建立 .gitignore
cat > .gitignore << 'EOF'
# WordPress
wp-config.php
wp-content/uploads/
wp-content/cache/

# IDE
.vscode/
.idea/

# Dependencies
node_modules/
vendor/

# Build
build/
*.zip

# Logs
*.log
.DS_Store
EOF

# 3. 首次提交
git add .
git commit -m "chore: 初始化外掛結構

- 使用 WordPress Plugin Boilerplate
- 建立基礎目錄結構
- 設置 Git 版本控制

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
```

---

## 架構設計

### 核心設計原則

#### 1. 單例模式 (Singleton Pattern)

**為什麼需要**: 確保服務層只有一個實例，避免重複初始化

**實作範例**:

```php
<?php
/**
 * 商品服務層
 */
class Product_Service {

    private static $instance = null;

    /**
     * 取得實例
     */
    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 私有建構函數（防止外部實例化）
     */
    private function __construct() {
        // 初始化邏輯
    }

    /**
     * 防止克隆
     */
    private function __clone() {}

    /**
     * 防止反序列化
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// 使用方式
$product_service = Product_Service::getInstance();
```

#### 2. 職責分離 (Separation of Concerns)

| 層級 | 職責 | 禁止事項 |
|------|------|---------|
| **Admin / Public** | UI 渲染、用戶交互 | ❌ 直接資料庫操作 |
| **API** | 接收請求、返回資料 | ❌ 業務邏輯處理 |
| **Service** | 業務邏輯、資料處理 | ❌ 直接輸出 HTML |
| **Database** | 資料持久化 | ❌ 業務邏輯 |

**錯誤範例** ❌:

```php
// 在 Admin 類別中直接操作資料庫（違反職責分離）
class Admin {
    public function display_products() {
        global $wpdb;
        $products = $wpdb->get_results("SELECT * FROM wp_products"); // ❌
        // ...
    }
}
```

**正確範例** ✅:

```php
// Admin 調用 Service，Service 處理資料庫
class Admin {
    public function display_products() {
        $service = Product_Service::getInstance();
        $products = $service->getAllProducts(); // ✅
        // ...
    }
}

class Product_Service {
    public function getAllProducts() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM wp_products");
    }
}
```

#### 3. REST API 優先

**為什麼**:
- 前後端解耦
- 支援第三方整合
- 更好的測試性

**實作步驟**:

```php
<?php
/**
 * 商品 API 端點
 */
class Products_API extends WP_REST_Controller {

    public function register_routes() {
        register_rest_route('your-plugin/v1', '/products', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_items'),
            'permission_callback' => array($this, 'get_items_permissions_check'),
        ));
    }

    public function get_items($request) {
        $service = Product_Service::getInstance();
        $products = $service->getAllProducts();

        return rest_ensure_response($products);
    }

    public function get_items_permissions_check($request) {
        return current_user_can('edit_posts');
    }
}
```

### 資料庫設計

#### 建表原則

```php
// includes/class-activator.php

public static function activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // 表名使用外掛前綴
    $table_name = $wpdb->prefix . 'yourplugin_products';

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        sku varchar(100) DEFAULT '',
        price decimal(10,2) DEFAULT 0.00,
        quantity int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY sku (sku),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // 記錄資料庫版本
    add_option('yourplugin_db_version', '1.0');
}
```

**設計規範**:
- ✅ 表名使用外掛前綴 (`yourplugin_`)
- ✅ 主鍵必須是 `id bigint(20) AUTO_INCREMENT`
- ✅ 添加必要的索引 (KEY)
- ✅ 時間欄位使用 `datetime` 並設置預設值
- ✅ 使用 `dbDelta()` 而非直接執行 SQL

---

## 核心功能實作

### 建立服務層

#### 範本: Service Template

```php
<?php
/**
 * {Entity} Service
 *
 * 處理 {entity} 相關的業務邏輯
 */
class {Entity}_Service {

    private static $instance = null;

    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * 取得所有 {entities}
     *
     * @return array|false
     */
    public function getAll() {
        global $wpdb;
        $table = $wpdb->prefix . 'yourplugin_{entities}';

        try {
            $results = $wpdb->get_results("SELECT * FROM {$table}");

            if ($wpdb->last_error) {
                throw new Exception($wpdb->last_error);
            }

            return $results;

        } catch (Exception $e) {
            error_log('Service Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 根據 ID 取得單筆資料
     *
     * @param int $id
     * @return object|false
     */
    public function getById($id) {
        if (empty($id) || !is_numeric($id)) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'yourplugin_{entities}';

        try {
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            ));

            if ($wpdb->last_error) {
                throw new Exception($wpdb->last_error);
            }

            return $result;

        } catch (Exception $e) {
            error_log('Service Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 建立新資料
     *
     * @param array $data
     * @return int|false 新資料的 ID，失敗返回 false
     */
    public function create($data) {
        if (empty($data)) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'yourplugin_{entities}';

        try {
            $wpdb->insert($table, $data);

            if ($wpdb->last_error) {
                throw new Exception($wpdb->last_error);
            }

            return $wpdb->insert_id;

        } catch (Exception $e) {
            error_log('Service Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 更新資料
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        if (empty($id) || !is_numeric($id) || empty($data)) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'yourplugin_{entities}';

        try {
            $result = $wpdb->update(
                $table,
                $data,
                array('id' => $id)
            );

            if ($wpdb->last_error) {
                throw new Exception($wpdb->last_error);
            }

            return $result !== false;

        } catch (Exception $e) {
            error_log('Service Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 刪除資料
     *
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        if (empty($id) || !is_numeric($id)) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'yourplugin_{entities}';

        try {
            $result = $wpdb->delete($table, array('id' => $id));

            if ($wpdb->last_error) {
                throw new Exception($wpdb->last_error);
            }

            return $result !== false;

        } catch (Exception $e) {
            error_log('Service Error: ' . $e->getMessage());
            return false;
        }
    }
}
```

### 建立 REST API

#### 範本: API Template

```php
<?php
/**
 * {Entities} API
 *
 * 提供 {entities} 的 REST API 端點
 */
class {Entities}_API extends WP_REST_Controller {

    protected $namespace = 'yourplugin/v1';
    protected $rest_base = '{entities}';

    public function register_routes() {

        // GET /yourplugin/v1/{entities}
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_items'),
                'permission_callback' => array($this, 'get_items_permissions_check'),
            ),
        ));

        // GET /yourplugin/v1/{entities}/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_item'),
                'permission_callback' => array($this, 'get_item_permissions_check'),
            ),
        ));

        // POST /yourplugin/v1/{entities}
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'create_item'),
                'permission_callback' => array($this, 'create_item_permissions_check'),
            ),
        ));

        // PUT /yourplugin/v1/{entities}/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_item'),
                'permission_callback' => array($this, 'update_item_permissions_check'),
            ),
        ));

        // DELETE /yourplugin/v1/{entities}/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array($this, 'delete_item'),
                'permission_callback' => array($this, 'delete_item_permissions_check'),
            ),
        ));
    }

    /**
     * 取得列表
     */
    public function get_items($request) {
        $service = {Entity}_Service::getInstance();
        $items = $service->getAll();

        if ($items === false) {
            return new WP_Error('service_error', '取得資料失敗', array('status' => 500));
        }

        return rest_ensure_response($items);
    }

    /**
     * 取得單筆
     */
    public function get_item($request) {
        $id = $request['id'];
        $service = {Entity}_Service::getInstance();
        $item = $service->getById($id);

        if ($item === false) {
            return new WP_Error('not_found', '找不到資料', array('status' => 404));
        }

        return rest_ensure_response($item);
    }

    /**
     * 建立新資料
     */
    public function create_item($request) {
        $data = $request->get_json_params();
        $service = {Entity}_Service::getInstance();
        $id = $service->create($data);

        if ($id === false) {
            return new WP_Error('create_failed', '建立失敗', array('status' => 500));
        }

        return rest_ensure_response(array(
            'success' => true,
            'id' => $id
        ));
    }

    /**
     * 更新資料
     */
    public function update_item($request) {
        $id = $request['id'];
        $data = $request->get_json_params();
        $service = {Entity}_Service::getInstance();
        $result = $service->update($id, $data);

        if ($result === false) {
            return new WP_Error('update_failed', '更新失敗', array('status' => 500));
        }

        return rest_ensure_response(array('success' => true));
    }

    /**
     * 刪除資料
     */
    public function delete_item($request) {
        $id = $request['id'];
        $service = {Entity}_Service::getInstance();
        $result = $service->delete($id);

        if ($result === false) {
            return new WP_Error('delete_failed', '刪除失敗', array('status' => 500));
        }

        return rest_ensure_response(array('success' => true));
    }

    /**
     * 權限檢查
     */
    public function get_items_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function get_item_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function create_item_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function update_item_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function delete_item_permissions_check($request) {
        return current_user_can('delete_posts');
    }
}
```

---

## 前端開發

### Vue 3 組件化架構

#### 頁面結構標準

```php
<?php
/**
 * 管理員頁面範本
 *
 * 遵循 BuyGo+1 標準結構
 */

// 安全檢查
if (!defined('ABSPATH')) {
    exit;
}

// 建立 nonce
$nonce = wp_create_nonce('wp_rest');
?>

<div id="app" class="{page-name}-page">
    <!-- 頁首部分: 固定在頂部，不受檢視切換影響 -->
    <header class="{page-name}-header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">{頁面標題}</h1>

            <div class="flex items-center gap-4">
                <!-- 搜尋框 -->
                <?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/smart-search-box.php'; ?>

                <!-- 操作按鈕 -->
                <button @click="showAddModal" class="btn-primary">
                    新增
                </button>
            </div>
        </div>
    </header>

    <!-- 內容區域: 可滾動，包含所有檢視 -->
    <div class="flex-1 overflow-auto">

        <!-- 列表檢視 -->
        <div v-show="currentView === 'list'" class="{page-name}-list">
            <!-- 列表內容 -->
            <table class="{page-name}-table">
                <!-- ... -->
            </table>

            <!-- 分頁組件 -->
            <?php include BUYGO_PLUS_ONE_PLUGIN_DIR . 'components/shared/pagination.php'; ?>
        </div>

        <!-- 詳情檢視 -->
        <div v-show="currentView === 'detail'" class="{page-name}-detail">
            <!-- 詳情內容 -->
        </div>

    </div>
</div>

<script>
const { createApp } = Vue;

createApp({
    data() {
        return {
            // Nonce (必須在 return 中)
            wpNonce: '<?php echo $nonce; ?>',

            // 檢視狀態
            currentView: 'list',

            // 資料
            items: [],
            currentItem: null,

            // UI 狀態
            loading: false,
        };
    },

    methods: {
        async loadData() {
            this.loading = true;

            try {
                const response = await fetch('/wp-json/yourplugin/v1/{entities}', {
                    headers: {
                        'X-WP-Nonce': this.wpNonce
                    }
                });

                if (!response.ok) {
                    throw new Error('API 請求失敗');
                }

                this.items = await response.json();

            } catch (error) {
                console.error('載入資料失敗:', error);
                alert('載入資料失敗');
            } finally {
                this.loading = false;
            }
        },

        showDetail(item) {
            this.currentItem = item;
            this.currentView = 'detail';
        },

        backToList() {
            this.currentView = 'list';
        },
    },

    mounted() {
        this.loadData();
    }
}).mount('#app');
</script>

<style>
/* 使用頁面前綴避免衝突 */
.{page-name}-page { }
.{page-name}-header { }
.{page-name}-list { }
.{page-name}-table { }
</style>
```

### CSS 隔離策略

#### 命名規範

```css
/* ✅ 正確：使用頁面前綴 */
.products-page { }
.products-header { }
.products-modal { }
.products-card { }
.products-table { }

/* ❌ 錯誤：通用名稱會衝突 */
.page { }
.header { }
.modal { }
.card { }
.table { }
```

#### 提取到獨立檔案

```php
// 在 admin/class-admin.php 中載入

public function enqueue_styles() {
    $screen = get_current_screen();

    if ($screen->id === 'toplevel_page_your-plugin-products') {
        wp_enqueue_style(
            'your-plugin-products',
            plugin_dir_url(__FILE__) . 'css/products.css',
            array(),
            $this->version
        );
    }
}
```

---

## 安全性

### 1. Nonce 驗證

#### 後台表單

```php
<!-- 表單中加入 nonce -->
<form method="post">
    <?php wp_nonce_field('your_plugin_action', 'your_plugin_nonce'); ?>
    <!-- 表單欄位 -->
</form>

<?php
// 處理表單時驗證
if (isset($_POST['your_plugin_nonce'])) {
    if (!wp_verify_nonce($_POST['your_plugin_nonce'], 'your_plugin_action')) {
        wp_die('安全驗證失敗');
    }

    // 處理表單
}
?>
```

#### REST API

```javascript
// 前端: 在 fetch 請求中加入 nonce
const wpNonce = '<?php echo wp_create_nonce("wp_rest"); ?>';

fetch('/wp-json/yourplugin/v1/items', {
    headers: {
        'X-WP-Nonce': wpNonce
    }
});
```

```php
// 後端: WordPress 自動驗證 nonce
// 只需確保 permission_callback 正確設置
'permission_callback' => function() {
    return current_user_can('edit_posts');
}
```

### 2. 資料清理與驗證

```php
// 輸入驗證
$name = sanitize_text_field($_POST['name']);
$email = sanitize_email($_POST['email']);
$content = wp_kses_post($_POST['content']); // 允許部分 HTML

// 輸出轉義
echo esc_html($user_input);           // 純文字
echo esc_attr($attribute_value);      // HTML 屬性
echo esc_url($url);                   // URL
echo wp_kses_post($html_content);     // HTML 內容
```

### 3. SQL 注入防護

```php
// ✅ 正確：使用 prepare()
$wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table} WHERE id = %d AND name = %s",
    $id,
    $name
));

// ❌ 錯誤：直接拼接 SQL
$wpdb->get_results("SELECT * FROM {$table} WHERE id = {$id}");
```

### 4. CSRF 防護

```php
// 檢查 referer
check_admin_referer('your_plugin_action', 'your_plugin_nonce');

// 或使用 REST API 的自動 nonce 驗證
```

---

## 測試與除錯

### 除錯工具

#### 1. WordPress Debug 模式

```php
// wp-config.php

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

#### 2. 日誌記錄

```php
// 記錄到 debug.log
error_log('Debug message: ' . print_r($data, true));

// 建立自定義日誌
if (!function_exists('your_plugin_log')) {
    function your_plugin_log($message) {
        $log_file = WP_CONTENT_DIR . '/your-plugin.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }
}
```

#### 3. API 測試

```bash
# 使用 WP-CLI 測試 API
wp rest list

# 使用 curl 測試端點
curl -X GET "http://yoursite.local/wp-json/yourplugin/v1/items" \
     -H "X-WP-Nonce: YOUR_NONCE"
```

### 自動化測試 (選擇性)

```bash
# 安裝 PHPUnit
composer require --dev phpunit/phpunit

# 建立測試檔案
# tests/test-product-service.php

class Test_Product_Service extends WP_UnitTestCase {

    public function test_get_all_products() {
        $service = Product_Service::getInstance();
        $products = $service->getAll();

        $this->assertIsArray($products);
    }
}

# 執行測試
./vendor/bin/phpunit
```

---

## 文檔與交付

### 必備文檔

#### 1. README.md

```markdown
# Your Plugin Name

簡短描述外掛功能

## 功能特性

- 功能 1
- 功能 2
- 功能 3

## 安裝

1. 上傳到 `/wp-content/plugins/`
2. 啟用外掛
3. 訪問設定頁面

## 使用方法

...

## 系統需求

- PHP 7.4+
- WordPress 5.8+
- MySQL 5.6+

## 授權

GPL v2 或更新版本
```

#### 2. CODING-STANDARDS.md

參考 BuyGo+1 的 [docs/development/CODING-STANDARDS.md](../development/CODING-STANDARDS.md)

#### 3. CHANGELOG.md

```markdown
# 更新日誌

## [1.0.0] - 2026-01-24

### 新增
- 初始版本發布
- 商品管理功能
- REST API 支援

### 修復
- N/A

### 變更
- N/A
```

### 打包發布

#### 建立 build 腳本

```bash
#!/bin/bash
# scripts/build-production.sh

VERSION="1.0.0"
PLUGIN_NAME="your-plugin"
OUTPUT_FILE="${PLUGIN_NAME}-${VERSION}.zip"

# 排除開發檔案
rsync -av \
    --exclude='.git/' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    --exclude='tests/' \
    --exclude='docs/' \
    --exclude='scripts/' \
    --exclude='*.zip' \
    ./ build/${PLUGIN_NAME}/

# 建立 zip
cd build
zip -r ../${OUTPUT_FILE} ${PLUGIN_NAME}
cd ..

echo "✓ 完成: ${OUTPUT_FILE}"
```

---

## 常見問題

### Q1: 如何處理大量資料？

**A**: 使用分頁和快取

```php
public function getAll($page = 1, $per_page = 20) {
    $offset = ($page - 1) * $per_page;

    global $wpdb;
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));

    // 快取結果 (15 分鐘)
    wp_cache_set("items_page_{$page}", $results, 'your-plugin', 900);

    return $results;
}
```

### Q2: 如何整合第三方 API？

**A**: 使用 `wp_remote_post()` / `wp_remote_get()`

```php
$response = wp_remote_post('https://api.example.com/endpoint', array(
    'headers' => array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json',
    ),
    'body' => json_encode($data),
    'timeout' => 30,
));

if (is_wp_error($response)) {
    error_log('API Error: ' . $response->get_error_message());
    return false;
}

$body = json_decode(wp_remote_retrieve_body($response), true);
```

### Q3: 如何處理多語言？

**A**: 使用 WordPress i18n 系統

```php
// 在主檔案中載入文字域
load_plugin_textdomain('your-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages');

// 在代碼中使用
__('Text to translate', 'your-plugin');
_e('Text to echo', 'your-plugin');
```

---

## 總結

### 開發檢查清單

完成以下所有項目，你就有一個高品質的 WordPress 外掛：

#### 架構 ✓
- [ ] 使用 WordPress Plugin Boilerplate
- [ ] 實作單例模式
- [ ] 職責分離清晰
- [ ] REST API 完整

#### 安全性 ✓
- [ ] 所有表單有 nonce 驗證
- [ ] 所有 API 有權限檢查
- [ ] 所有 SQL 使用 prepare()
- [ ] 所有輸出使用轉義函數

#### 代碼品質 ✓
- [ ] 服務層有錯誤處理
- [ ] 參數驗證完整
- [ ] 日誌記錄清晰
- [ ] 遵循 WordPress Coding Standards

#### 文檔 ✓
- [ ] README.md 清晰
- [ ] CODING-STANDARDS.md 完整
- [ ] CHANGELOG.md 更新
- [ ] 代碼註解充分

#### 測試 ✓
- [ ] 手動測試所有功能
- [ ] 測試錯誤處理
- [ ] 測試權限控制
- [ ] (選擇性) 自動化測試

### 下一步

1. **參考 BuyGo+1 原始碼**: 這是最佳實踐範例
2. **閱讀 WordPress Codex**: https://codex.wordpress.org/
3. **加入社群**: WordPress Stack Exchange, Facebook Groups

---

**文檔維護者**: BuyGo Development Team
**最後更新**: 2026-01-24
**參考專案**: BuyGo+1 v0.03
