# Phase 35: FluentCart Hook 探索與注入點設定 - Research

**Researched:** 2026-02-02
**Domain:** WordPress Plugin Integration / FluentCart Hooks
**Confidence:** HIGH

## Summary

本次研究深入探討 FluentCart 的 Hook 架構和整合模式，目標是在會員訂單詳情頁中注入「查看子訂單」按鈕和子訂單列表。研究結果顯示 FluentCart 提供完整的 Hook 系統（315+ action/filter hooks），並且已有成功的整合案例（buygo-line-notify）可供參考。

核心發現：
- FluentCart 使用 `fluent_cart/customer_app` Hook 在會員檔案頁面（Customer Profile）的主內容區域注入自訂 HTML
- 會員檔案頁面包含 Dashboard、Purchase History（訂單歷史）、Subscriptions 等子頁面，由 Vue 3 SPA 渲染
- buygo-line-notify 外掛已成功使用相同模式在 FluentCart 頁面中注入 LINE 登入按鈕和綁定狀態 Widget
- FluentCart 支援自訂端點（custom endpoints），可透過 `fluent_cart/customer_portal/custom_endpoints` filter 注入完全自訂的頁面內容

**Primary recommendation:** 使用 `fluent_cart/customer_app` action hook 在會員檔案頁面注入子訂單 UI，搭配 Vanilla JavaScript 實作展開/折疊功能，參考 buygo-line-notify 的整合模式確保與 FluentCart Vue App 相容。

## Standard Stack

### Core Integration Technologies
| Technology | Version | Purpose | Why Standard |
|------------|---------|---------|--------------|
| WordPress Action Hooks | Core API | 注入自訂 HTML 到 FluentCart 頁面 | WordPress 標準擴展機制，向後相容性最佳 |
| `fluent_cart/customer_app` | FluentCart Hook | 在會員檔案主內容區域注入內容 | FluentCart 官方提供的客戶檔案頁面注入點 |
| Vanilla JavaScript | ES6+ | 前端互動邏輯（展開/折疊） | 無框架依賴，避免與 FluentCart Vue 3 衝突 |
| Inline CSS / `wp_add_inline_style()` | WordPress Core | 載入自訂樣式 | 避免額外 HTTP 請求，確保樣式隔離 |

### Supporting Tools
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `wp_enqueue_script()` | WordPress Core | 安全載入 JavaScript | 所有前端腳本都需透過此 API 註冊 |
| `wp_localize_script()` | WordPress Core | 傳遞 PHP 資料到 JavaScript | 傳遞 REST API URL、nonce 等配置 |
| `wp_create_nonce()` | WordPress Core | 產生安全 token | 驗證 AJAX/REST API 請求 |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Action Hook 注入 | Template Override | Template Override 需要修改主題檔案，升級 FluentCart 時可能失效 |
| Vanilla JavaScript | Vue 3 Component | 需要整合 FluentCart 的 Vue 3 Build Pipeline，複雜度高且可能版本衝突 |
| Custom Endpoint | 直接修改 FluentCart | 違反外掛隔離原則，FluentCart 更新時會遺失修改 |

**Installation:**
```bash
# 無需額外安裝依賴，使用 WordPress 和 FluentCart 現有 API
```

## Architecture Patterns

### Pattern 1: Action Hook 注入模式（推薦）
**What:** 在 WordPress 外掛中註冊 FluentCart Action Hook，在特定位置注入 HTML

**When to use:** 需要在 FluentCart 頁面中加入額外內容，但不需要完全客製化頁面結構

**Example:**
```php
// Source: buygo-line-notify/includes/integrations/class-fluentcart-customer-profile-integration.php
class FluentCartCustomerProfileIntegration {
    public static function register_hooks(): void {
        // 在 FluentCart 客戶檔案頁面的 Vue app 之後注入內容
        add_action('fluent_cart/customer_app', [__CLASS__, 'render_section'], 100);

        // 載入 JavaScript 和 CSS
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function render_section(): void {
        if (!is_user_logged_in()) {
            return;
        }
        ?>
        <div id="custom-widget" class="custom-widget"></div>
        <?php
    }

    public static function enqueue_assets(): void {
        // 只在客戶檔案頁面載入
        if (!self::is_customer_profile_page()) {
            return;
        }

        wp_enqueue_script(
            'custom-integration',
            plugin_url() . 'assets/js/integration.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script('custom-integration', 'customConfig', [
            'apiBase' => rest_url('custom-plugin/v1'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);

        wp_add_inline_style('custom-widget', self::get_inline_css());
    }

    private static function is_customer_profile_page(): bool {
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        return is_user_logged_in() &&
               (strpos($current_url, '/my-account/') !== false);
    }
}
```

### Pattern 2: Custom Endpoint 模式（適用於完整頁面）
**What:** 透過 Filter Hook 註冊自訂端點，讓 FluentCart 路由系統處理自訂頁面

**When to use:** 需要在會員檔案中新增一個完整的獨立頁面（如「子訂單管理」分頁）

**Example:**
```php
// Source: FluentCart CustomerProfileHandler.php (maybeCustomEndpointContent method)
add_filter('fluent_cart/customer_portal/custom_endpoints', function($endpoints) {
    $endpoints['child-orders'] = [
        'label' => '子訂單',
        'icon_svg' => '<svg>...</svg>',
        'render_callback' => function() {
            // 渲染完整頁面內容
            echo '<div class="child-orders-page">...</div>';
        }
    ];
    return $endpoints;
});

// 設定 active tab
add_filter('fluent_cart/customer_portal/active_tab', function($activeTab) {
    global $wp;
    $requestedPath = $wp->request;
    if (strpos($requestedPath, 'child-orders') !== false) {
        return 'child-orders';
    }
    return $activeTab;
});
```

### Pattern 3: 頁面檢測模式
**What:** 偵測當前是否為 FluentCart 特定頁面，避免在不必要的地方載入資源

**When to use:** 確保 CSS/JS 只在需要的頁面載入，優化效能

**Example:**
```php
private static function is_customer_profile_page(): bool {
    $current_url = $_SERVER['REQUEST_URI'] ?? '';

    return (
        is_user_logged_in() &&
        (strpos($current_url, '/my-account/') !== false ||
         strpos($current_url, '/customer-profile/') !== false)
    );
}

// 或使用 FluentCart 的 TemplateService
use FluentCart\App\Services\TemplateService;

if (TemplateService::getCurrentFcPageType() === 'customer_profile') {
    // 載入資源
}
```

### Anti-Patterns to Avoid
- **直接修改 FluentCart 核心檔案**：FluentCart 更新時會覆蓋修改，且違反 WordPress 外掛開發規範
- **使用 jQuery 依賴**：FluentCart 使用 Vue 3，避免引入 jQuery 造成額外負擔
- **在所有頁面載入 CSS/JS**：造成效能浪費，應只在需要的頁面載入
- **忽略 nonce 驗證**：AJAX/REST API 請求必須驗證 nonce，否則有安全風險
- **使用過早的 Hook Priority**：可能在 FluentCart 初始化前執行，導致功能失效

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| 頁面路由檢測 | 自己寫 URL 解析邏輯 | `TemplateService::getCurrentFcPageType()` | FluentCart 提供官方 API，處理了 subdirectory 安裝等邊緣情況 |
| REST API 驗證 | 自己寫 token 系統 | `wp_create_nonce()` + `check_ajax_referer()` | WordPress 核心提供完整的 nonce 機制，已處理時效性和安全性 |
| 使用者權限檢查 | 手動查詢資料庫 | `is_user_logged_in()` + `current_user_can()` | WordPress 核心 API 已快取，效能更好 |
| CSS 樣式隔離 | 寫複雜的 CSS Reset | BEM 命名 + 唯一前綴 | 使用 `.buygo-child-orders-*` 前綴確保樣式不衝突 |
| JavaScript 模組載入 | 自己寫 script loader | `wp_enqueue_script()` + dependency 管理 | WordPress 自動處理依賴順序和去重 |

**Key insight:** FluentCart 和 WordPress 已提供完整的整合 API，自己重新實作不僅浪費時間，還可能引入安全漏洞和相容性問題。應優先使用官方 API。

## Common Pitfalls

### Pitfall 1: Hook Priority 設定不當
**What goes wrong:** 使用預設 priority (10) 可能導致內容出現在 FluentCart Vue App 之前，造成版面錯亂

**Why it happens:** FluentCart 的 Vue App 也是透過 `fluent_cart/customer_app` hook 載入，預設 priority 也是 10

**How to avoid:**
- 設定較晚的 priority（例如 100）確保在 FluentCart Vue App 之後載入
- 檢查實際渲染順序，必要時調整 priority

**Warning signs:**
- 注入的 HTML 出現在頁面最上方
- Vue App 無法正常初始化
- CSS 樣式被 FluentCart 覆蓋

**Example:**
```php
// ❌ 錯誤：使用預設 priority，可能在 Vue App 之前載入
add_action('fluent_cart/customer_app', 'render_child_orders');

// ✅ 正確：使用較晚的 priority
add_action('fluent_cart/customer_app', 'render_child_orders', 100);
```

### Pitfall 2: 在所有頁面載入資源
**What goes wrong:** JavaScript/CSS 在網站所有頁面載入，造成效能浪費

**Why it happens:** 直接在 `wp_enqueue_scripts` hook 中註冊資源，沒有檢查當前頁面

**How to avoid:**
- 在 `enqueue_assets()` 中加入頁面檢測邏輯
- 只在 FluentCart 客戶檔案頁面載入資源

**Warning signs:**
- 網站其他頁面的開發者工具顯示不必要的 script/style
- PageSpeed Insights 顯示 "Unused JavaScript/CSS"

**Example:**
```php
// ❌ 錯誤：在所有頁面載入
public static function enqueue_assets(): void {
    wp_enqueue_script('child-orders-js', ...);
}

// ✅ 正確：只在需要的頁面載入
public static function enqueue_assets(): void {
    if (!self::is_customer_profile_page()) {
        return;
    }
    wp_enqueue_script('child-orders-js', ...);
}
```

### Pitfall 3: Vue 3 框架衝突
**What goes wrong:** 嘗試在注入的 HTML 中使用 Vue 3 directives（如 `v-if`、`v-for`），但功能無法運作

**Why it happens:** FluentCart 的 Vue 3 App 只管理特定的 `<div data-fluent-cart-customer-profile-app>` 區域，外部注入的 HTML 不在 Vue 管轄範圍

**How to avoid:**
- 使用 Vanilla JavaScript 實作前端邏輯
- 或使用獨立的 Vue 3 實例（需額外處理 build pipeline）

**Warning signs:**
- Vue directives 被當作普通 HTML 屬性顯示
- `{{ variable }}` 直接顯示在頁面上
- Console 出現 Vue warnings

**Example:**
```php
// ❌ 錯誤：嘗試使用 Vue directives
echo '<div v-if="showOrders">...</div>';

// ✅ 正確：使用 Vanilla JavaScript
echo '<div id="child-orders-container" style="display:none;">...</div>';
// 在 JS 中：document.getElementById('child-orders-container').style.display = 'block';
```

### Pitfall 4: 忽略行動裝置體驗
**What goes wrong:** UI 在桌面版正常，但在手機上版面錯亂或按鈕無法點擊

**Why it happens:** FluentCart 客戶檔案頁面有響應式設計，但注入的 HTML 沒有配合

**How to avoid:**
- 使用 FluentCart 現有的 CSS 變數和樣式類別
- 加入 `@media` query 適配小螢幕
- 測試不同裝置尺寸

**Warning signs:**
- Mobile DevTools 顯示水平捲軸
- 按鈕太小無法點擊（< 44x44px）
- 文字超出容器

**Example:**
```css
/* ✅ 正確：加入響應式設計 */
.buygo-child-orders-container {
    padding: 20px;
}

@media (max-width: 768px) {
    .buygo-child-orders-container {
        padding: 12px;
    }

    .buygo-child-orders-button {
        width: 100%;
        font-size: 14px;
    }
}
```

### Pitfall 5: 未處理空狀態和錯誤
**What goes wrong:** 當訂單沒有子訂單時，UI 顯示空白或錯誤訊息

**Why it happens:** 前端程式碼假設 API 一定回傳資料，沒有處理空陣列或錯誤情況

**How to avoid:**
- 在後端 API 中檢查子訂單是否存在，回傳明確的狀態
- 前端顯示友善的空狀態訊息
- 錯誤時顯示重試按鈕

**Warning signs:**
- Console 出現 "Cannot read property of undefined"
- 使用者看到白畫面或載入圖示一直轉
- 沒有任何提示訊息

**Example:**
```javascript
// ✅ 正確：處理空狀態和錯誤
fetch(apiUrl)
    .then(response => response.json())
    .then(data => {
        if (data.child_orders && data.child_orders.length > 0) {
            renderChildOrders(data.child_orders);
        } else {
            container.innerHTML = '<p>此訂單沒有子訂單</p>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        container.innerHTML = '<p>載入失敗，<button onclick="retry()">重試</button></p>';
    });
```

## Code Examples

### Example 1: 基本 Hook 註冊（參考 buygo-line-notify）
```php
// Source: buygo-line-notify/includes/integrations/class-fluentcart-customer-profile-integration.php
namespace BuygoLineNotify\Integrations;

class FluentCartCustomerProfileIntegration {

    public static function register_hooks(): void {
        // 在 FluentCart 客戶檔案頁面的 Vue app 之後注入
        add_action('fluent_cart/customer_app', [__CLASS__, 'render_line_binding_section'], 100);

        // 載入 JavaScript 和 CSS
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function render_line_binding_section(): void {
        if (!is_user_logged_in()) {
            return;
        }
        ?>
        <div id="buygo-line-binding-widget" class="buygo-line-fluentcart-widget"></div>
        <?php
    }

    public static function enqueue_assets(): void {
        if (!self::is_customer_profile_page()) {
            return;
        }

        wp_enqueue_script(
            'buygo-line-fluentcart-integration',
            plugin_url() . 'assets/js/fluentcart-line-integration-standalone.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script('buygo-line-fluentcart-integration', 'buygoLineFluentCart', [
            'apiBase' => rest_url('buygo-line-notify/v1/fluentcart'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);

        wp_register_style('buygo-line-fluentcart-widget', false);
        wp_enqueue_style('buygo-line-fluentcart-widget');
        wp_add_inline_style('buygo-line-fluentcart-widget', self::get_inline_css());
    }

    private static function is_customer_profile_page(): bool {
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        return is_user_logged_in() &&
               (strpos($current_url, '/my-account/') !== false ||
                strpos($current_url, '/customer-profile/') !== false);
    }

    private static function get_inline_css(): string {
        return '
        .buygo-line-fluentcart-widget {
            margin: 20px 0;
            padding: 20px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            .buygo-line-fluentcart-widget {
                padding: 12px;
            }
        }
        ';
    }
}
```

### Example 2: FluentCart 客戶檔案頁面結構（FluentCart Core）
```php
// Source: fluent-cart/app/Views/frontend/customer_app.php
<div class="fct_customer_profile_wrap">
    <div class="fct-customer-root-container">
        <!-- 載入圖示 -->
        <div id="fct-customer-loader">...</div>

        <div class="fct-customer-dashboard-app-container">
            <!-- 側邊選單 -->
            <?php do_action('fluent_cart/customer_menu'); ?>

            <!-- 主內容區域 -->
            <div class="fct-customer-dashboard-main-content">
                <?php do_action('fluent_cart/customer_app'); ?>
                <!-- ↑ 這是注入點！我們的子訂單 UI 會在這裡注入 -->
            </div>
        </div>
    </div>
</div>
```

### Example 3: Vanilla JavaScript 展開/折疊功能
```javascript
// Source: 實作建議
(function() {
    'use strict';

    const button = document.getElementById('buygo-view-child-orders-btn');
    const container = document.getElementById('buygo-child-orders-container');

    if (!button || !container) {
        return;
    }

    button.addEventListener('click', function() {
        const isHidden = container.style.display === 'none' || !container.style.display;

        if (isHidden) {
            container.style.display = 'block';
            button.textContent = '隱藏子訂單';

            // 如果尚未載入資料，從 API 取得
            if (!container.dataset.loaded) {
                loadChildOrders();
            }
        } else {
            container.style.display = 'none';
            button.textContent = '查看子訂單';
        }
    });

    function loadChildOrders() {
        const orderId = button.dataset.orderId;
        const apiUrl = window.buygoChildOrders.apiBase + '/orders/' + orderId + '/children';

        container.innerHTML = '<p>載入中...</p>';

        fetch(apiUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': window.buygoChildOrders.nonce
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.child_orders) {
                renderChildOrders(data.child_orders);
                container.dataset.loaded = 'true';
            } else {
                container.innerHTML = '<p>此訂單沒有子訂單</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<p>載入失敗</p>';
        });
    }

    function renderChildOrders(orders) {
        let html = '<ul class="buygo-child-orders-list">';
        orders.forEach(order => {
            html += `
                <li class="buygo-child-order-item">
                    <span class="order-id">#${order.id}</span>
                    <span class="seller-name">${order.seller_name}</span>
                    <span class="amount">${order.total}</span>
                </li>
            `;
        });
        html += '</ul>';
        container.innerHTML = html;
    }
})();
```

### Example 4: REST API 端點定義（後端）
```php
// Source: 實作建議
namespace BuygoPlus\API;

use WP_REST_Request;
use WP_REST_Response;

class ChildOrdersAPI {

    public function register_routes(): void {
        register_rest_route('buygo-plus-one/v1', '/orders/(?P<id>\d+)/children', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_child_orders'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
    }

    public function get_child_orders(WP_REST_Request $request): WP_REST_Response {
        $order_id = $request->get_param('id');
        $customer_id = get_current_user_id();

        // 驗證訂單屬於當前使用者
        global $wpdb;
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fct_orders WHERE id = %d AND customer_id = %d",
            $order_id,
            $customer_id
        ));

        if (!$order) {
            return new WP_REST_Response([
                'success' => false,
                'message' => '訂單不存在或無權訪問'
            ], 403);
        }

        // 取得子訂單
        $child_orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fct_child_orders WHERE order_id = %d",
            $order_id
        ));

        return new WP_REST_Response([
            'success' => true,
            'child_orders' => $child_orders
        ], 200);
    }

    public function check_permission(): bool {
        return is_user_logged_in();
    }
}
```

## FluentCart Hook 資訊

### 已識別的關鍵 Hooks

根據程式碼分析和文件研究，FluentCart 提供以下關鍵 Hooks：

#### Customer Profile Hooks（客戶檔案頁面）
- `fluent_cart/customer_menu` - 渲染側邊選單
- `fluent_cart/customer_app` - 渲染主內容區域（**主要注入點**）
- `fluent_cart/customer_portal/active_tab` - 設定當前活躍分頁
- `fluent_cart/customer_portal/custom_endpoints` - 註冊自訂端點
- `fluent_cart/global_customer_menu_items` - 修改選單項目

#### Order Hooks（訂單）
- `fluent_cart/order_paid` - 訂單付款成功
- `fluent_cart/order_paid_done` - 訂單付款完成（延遲執行）
- `fluent_cart/order/invoice_number_added` - 發票號碼新增

#### Frontend Hooks（前端顯示）
- `fluent_cart/template/main_content` - 主要內容區域
- `fluent_cart/template/product_archive` - 商品分類頁面
- `fluent_cart/product/render_product_header` - 商品頁面標題
- `fluent_cart/product/after_product_content` - 商品內容之後

#### Checkout Hooks（結帳）
- `fluent_cart/checkout/prepare_other_data` - 準備結帳資料
- `fluent_cart/checkout_embed_payment_method_content` - 嵌入付款方式內容

### FluentCart 頁面結構分析

根據 `CustomerProfileHandler.php` 分析：

1. **頁面 Slug**: 從 `StoreSettings()->getCustomerDashboardPageSlug()` 取得（預設為 `my-account`）
2. **Rewrite Rule**: 使用 `add_rewrite_rule()` 建立動態路由
3. **內建端點**:
   - `dashboard` - 儀表板
   - `purchase-history` - 訂單歷史（**目標頁面**）
   - `subscriptions` - 訂閱方案
   - `licenses` - 授權
   - `downloads` - 下載
   - `profile` - 個人資料

4. **Custom Endpoints**: 可透過 `fluent_cart/customer_portal/custom_endpoints` filter 新增自訂端點

### 會員檔案頁面 HTML 結構
```html
<div class="fct_customer_profile_wrap">
    <div class="fct-customer-root-container">
        <div class="fct-customer-dashboard-app-container">
            <!-- 側邊選單（透過 fluent_cart/customer_menu hook 渲染）-->
            <div class="fct-customer-dashboard-navs-wrap">
                <nav>
                    <a href="/my-account/">Dashboard</a>
                    <a href="/my-account/purchase-history">Purchase History</a>
                    ...
                </nav>
            </div>

            <!-- 主內容區域（透過 fluent_cart/customer_app hook 渲染）-->
            <div class="fct-customer-dashboard-main-content">
                <!-- FluentCart Vue 3 App -->
                <div data-fluent-cart-customer-profile-app>
                    <app/>
                </div>

                <!-- ↓ 我們的注入點在這裡！priority 設為 100 確保在 Vue App 之後 -->
                <!-- 自訂 HTML 內容 -->
            </div>
        </div>
    </div>
</div>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| 修改 FluentCart 核心檔案 | 使用 Action/Filter Hooks | FluentCart 1.0 開始 | 升級相容性大幅提升，維護成本降低 |
| jQuery + 傳統 AJAX | Vanilla JS + REST API | 2024-2025 趨勢 | 減少依賴，降低檔案大小，提升效能 |
| 直接 SQL 查詢 | FluentCart Model API | FluentCart 架構設計 | 更好的資料抽象，避免 SQL Injection |
| Template Override | Hook 注入 | WordPress 外掛最佳實踐 | 降低主題相依性，提升可移植性 |

**Deprecated/outdated:**
- **jQuery 依賴**: FluentCart 使用 Vue 3，不再需要 jQuery
- **`$wpdb->query()` 無參數化**: 應使用 `$wpdb->prepare()` 避免 SQL Injection
- **Global JavaScript 變數**: 應使用 `wp_localize_script()` 傳遞配置
- **直接修改 `$_REQUEST`**: 應使用 `$request->get_param()` (REST API) 或 `sanitize_text_field($_POST['key'])` (傳統 POST)

## Integration Strategy

### 方案 A: Action Hook 注入（推薦）

**適用場景:** 在現有的訂單詳情頁面下方加入子訂單資訊

**優點:**
- ✅ 實作簡單，程式碼量少
- ✅ 與 FluentCart 升級相容性好
- ✅ 可參考 buygo-line-notify 現有實作
- ✅ 無需修改 FluentCart 核心

**缺點:**
- ❌ 無法完全控制頁面結構
- ❌ 需配合 FluentCart 現有 CSS 樣式

**實作步驟:**
1. 建立 `includes/integrations/class-fluentcart-child-orders-integration.php`
2. 註冊 `fluent_cart/customer_app` hook (priority: 100)
3. 在 hook callback 中渲染按鈕和容器
4. 使用 `wp_enqueue_script()` 載入 Vanilla JavaScript
5. 建立 REST API 端點提供子訂單資料

### 方案 B: Custom Endpoint（進階）

**適用場景:** 需要在側邊選單新增「子訂單管理」獨立分頁

**優點:**
- ✅ 完全控制頁面結構
- ✅ 可使用 FluentCart 路由系統
- ✅ 可加入側邊選單

**缺點:**
- ❌ 實作複雜度較高
- ❌ 需處理路由和權限
- ❌ 需自己處理空狀態

**實作步驟:**
1. 使用 `fluent_cart/customer_portal/custom_endpoints` filter 註冊端點
2. 使用 `fluent_cart/global_customer_menu_items` filter 加入選單項目
3. 實作 `render_callback` 函數渲染完整頁面
4. 建立獨立的 Vue 3 元件（選擇性）

## Open Questions

1. **訂單詳情頁面的精確注入位置**
   - What we know: `fluent_cart/customer_app` 可注入客戶檔案頁面的主內容區域
   - What's unclear: 是否需要判斷當前是否在 "Purchase History" 頁面？還是直接注入到所有頁面？
   - Recommendation:
     - 方案 1（簡單）：注入到所有客戶檔案頁面，透過 JavaScript 偵測 URL 路徑決定是否顯示
     - 方案 2（精確）：使用 `fluent_cart/customer_portal/active_tab` filter 判斷當前分頁，只在 "purchase-history" 時注入

2. **子訂單資料結構**
   - What we know: FluentCart 使用 `wp_fct_child_orders` 資料表，與 `wp_fct_orders` 透過 `order_id` 關聯
   - What's unclear: 子訂單資料表的完整欄位結構（賣家資訊、商品、金額等）
   - Recommendation: 執行 `DESCRIBE wp_fct_child_orders` 取得完整 schema，在 Phase 36 實作 API 時處理

3. **效能考量**
   - What we know: FluentCart 客戶檔案頁面使用 Vue 3 SPA，初次載入後切換分頁不會重新載入頁面
   - What's unclear: 是否需要實作快取機制避免重複查詢子訂單？
   - Recommendation: 先實作基本版本，在 JavaScript 中使用 `dataset.loaded` flag 避免重複 API 請求，後續若效能問題再考慮 WordPress Transients API

4. **FluentCart Pro 整合**
   - What we know: 專案同時安裝 fluent-cart (免費版) 和 fluent-cart-pro
   - What's unclear: Pro 版本是否提供額外的訂單相關 Hooks？
   - Recommendation: Phase 35 以免費版 Hooks 為主，Pro 版本的功能在後續 Phase 探索

## Sources

### Primary (HIGH confidence)
- FluentCart 官方開發者文件: [dev.fluentcart.com/hooks/filters](https://dev.fluentcart.com/hooks/filters)
- FluentCart 開始使用指南: [dev.fluentcart.com/getting-started](https://dev.fluentcart.com/getting-started)
- buygo-line-notify 外掛原始碼: `/Users/fishtv/Development/buygo-line-notify/includes/integrations/class-fluentcart-customer-profile-integration.php`
- FluentCart 核心原始碼: `/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/fluent-cart/`
  - `app/Views/frontend/customer_app.php`
  - `app/Hooks/Handlers/ShortCodes/CustomerProfileHandler.php`
  - `app/Modules/Templating/TemplateActions.php`

### Secondary (MEDIUM confidence)
- WordPress 外掛開發最佳實踐: [developer.wordpress.org/plugins/plugin-basics/best-practices/](https://developer.wordpress.org/plugins/plugin-basics/best-practices/)
- WordPress Hooks 指南: [developer.wordpress.org/plugins/hooks/](https://developer.wordpress.org/plugins/hooks/)
- Kinsta WordPress Hooks 教學: [kinsta.com/blog/wordpress-hooks/](https://kinsta.com/blog/wordpress-hooks/)

### Tertiary (LOW confidence)
- FluentCart 功能列表: [fluentcart.com/all-features/](https://fluentcart.com/all-features/)
- FluentCart 部落格更新: [fluentcart.com/blog/](https://fluentcart.com/blog/) - 提到客製化 hooks 但無具體文件

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - 基於 WordPress 和 FluentCart 官方 API，有現成案例可參考
- Architecture: HIGH - buygo-line-notify 已成功實作相同模式，程式碼可直接參考
- Pitfalls: MEDIUM - 基於程式碼審查和 WordPress 開發經驗，但未實際測試所有情境

**Research date:** 2026-02-02
**Valid until:** 2026-03-02 (30 days - FluentCart 為穩定專案，API 變動機率低)

**Key Takeaways:**
1. FluentCart 提供完整的 Hook 系統，`fluent_cart/customer_app` 是客戶檔案頁面的主要注入點
2. buygo-line-notify 外掛已成功使用相同模式整合 LINE 功能，可作為最佳實踐參考
3. 使用 Vanilla JavaScript + REST API 模式可避免與 FluentCart Vue 3 App 衝突
4. 必須注意 Hook Priority、頁面檢測、響應式設計等常見陷阱
5. 方案 A (Action Hook 注入) 為推薦方案，實作簡單且維護性好
