# Architecture Research - v1.4 會員前台子訂單顯示功能

**Domain:** WordPress + FluentCart 前台訂單展示系統
**Researched:** 2026-02-02
**Confidence:** HIGH

## 整合目標

在 FluentCart 會員前台（Account Dashboard）注入子訂單顯示功能，讓顧客可以查看父訂單和所有子訂單的詳細資訊。

### 核心需求
- 顯示父訂單和所有關聯子訂單
- 顯示已付款和未付款金額
- 權限控制：僅訂單所屬顧客可查詢
- 整合到現有 FluentCart 前台頁面

## 現有架構整合點

### BuyGo+1 現有架構層

```
┌─────────────────────────────────────────────────────────────┐
│                    BuyGo+1 後台 (Admin)                      │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Vue 3 + Tailwind CSS (Single Page Application)     │   │
│  │  - Dashboard, Products, Orders, Shipments, etc.     │   │
│  └────────────────────┬─────────────────────────────────┘   │
│                       │ REST API                             │
├───────────────────────┴──────────────────────────────────────┤
│                    API Layer (includes/api/)                 │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│  │ Orders   │  │ Products │  │ Shipments│  │ Settings │   │
│  │ API      │  │ API      │  │ API      │  │ API      │   │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘   │
│       │             │             │             │           │
├───────┴─────────────┴─────────────┴─────────────┴───────────┤
│                 Service Layer (includes/services/)           │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│  │ Order    │  │ Product  │  │ Shipment │  │ Settings │   │
│  │ Service  │  │ Service  │  │ Service  │  │ Service  │   │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘   │
│       │             │             │             │           │
├───────┴─────────────┴─────────────┴─────────────┴───────────┤
│                       Data Layer                             │
│  ┌────────────────────────────────────────────────────┐     │
│  │  FluentCart: wp_fct_orders, wp_fct_order_items    │     │
│  │  BuyGo:      wp_buygo_shipments, wp_buygo_*       │     │
│  └────────────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────────┘
```

### FluentCart 前台頁面

```
┌─────────────────────────────────────────────────────────────┐
│              FluentCart Account Dashboard                    │
│  (WordPress 前台，客戶登入後可存取)                           │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  現有 FluentCart 內容                                │   │
│  │  - 訂單列表 (只顯示父訂單)                           │   │
│  │  - 訂單詳情                                          │   │
│  │  - 個人資料                                          │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  【v1.4 整合點】                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  子訂單顯示區塊 (BuyGo+1 注入)                       │   │
│  │  - 使用 WordPress Hook 注入 HTML                     │   │
│  │  - 使用 Inline JavaScript 或獨立 Vue Component       │   │
│  │  - 呼叫 BuyGo+1 REST API 取得子訂單資料              │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

## v1.4 新增組件架構

### 方案比較

| 方案 | 優點 | 缺點 | 建議 |
|------|------|------|------|
| **方案 A: Inline JavaScript** | 簡單快速，不需要打包工具 | 缺乏響應式更新，程式碼難維護 | ✅ MVP 首選 |
| **方案 B: 獨立 Vue 3 Component** | 程式碼結構清晰，可重用 | 需要打包工具，整合複雜 | 後續優化 |
| **方案 C: 整合到 FluentCart Template** | 深度整合，體驗一致 | 需要修改 FluentCart 核心，升級風險 | ❌ 不建議 |

### 推薦架構：方案 A (Inline JavaScript + REST API)

```
┌─────────────────────────────────────────────────────────────┐
│  FluentCart Account Page (WordPress 前台)                    │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  【WordPress Hook: 'fluent_cart/account_page'】             │
│      ↓                                                       │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  BuyGo Child Order Display Component                │   │
│  │  (class-child-order-frontend.php)                   │   │
│  │                                                      │   │
│  │  1. 檢查當前頁面是否為訂單詳情頁                     │   │
│  │  2. 注入 HTML 容器 <div id="buygo-child-orders">     │   │
│  │  3. enqueue inline JavaScript                       │   │
│  │  4. 傳遞 nonce 和 API URL 給 JavaScript             │   │
│  └─────────────────────────────────────────────────────┘   │
│                       ↓                                      │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Inline JavaScript (buygo-child-orders.js)          │   │
│  │                                                      │   │
│  │  1. 從 URL 或 DOM 取得 parent_order_id               │   │
│  │  2. 呼叫 REST API:                                   │   │
│  │     GET /wp-json/buygo-plus-one/v1/child-orders/    │   │
│  │     ?parent_id={parent_id}                          │   │
│  │  3. 渲染子訂單列表到 DOM                             │   │
│  │  4. 計算已付款/未付款金額                            │   │
│  └─────────────────────────────────────────────────────┘   │
│                       ↓ (REST API Call)                      │
├─────────────────────────────────────────────────────────────┤
│  【新增】Child Order API                                     │
│  (includes/api/class-child-orders-api.php)                  │
│                                                              │
│  Route: GET /wp-json/buygo-plus-one/v1/child-orders/       │
│  Params: ?parent_id={id}                                    │
│  Auth: Cookie + Nonce (只允許訂單所屬客戶查詢)               │
│                       ↓                                      │
├─────────────────────────────────────────────────────────────┤
│  【新增】Child Order Service                                 │
│  (includes/services/class-child-order-service.php)          │
│                                                              │
│  - get_child_orders($parent_id, $customer_id)              │
│    └─ 查詢 wp_fct_orders WHERE parent_id = X               │
│    └─ 權限檢查：customer_id 必須匹配                        │
│  - format_child_order_summary($parent_order, $child_orders) │
│    └─ 計算已付款/未付款金額                                 │
│                       ↓                                      │
├─────────────────────────────────────────────────────────────┤
│  FluentCart Database (wp_fct_orders)                        │
│  - parent_id: 父訂單 ID                                      │
│  - type: 'split' (子訂單標記)                                │
│  - status: 'pending', 'shipped', 'completed', 'cancelled'   │
│  - total_amount: 訂單金額 (分為單位)                         │
└─────────────────────────────────────────────────────────────┘
```

## 新增組件清單

### 1. Frontend Integration Hook
**檔案**: `includes/class-child-order-frontend.php`

**責任**:
- 使用 WordPress Hook 在 FluentCart 前台注入 HTML
- enqueue inline JavaScript 和 CSS
- 傳遞 nonce 和 API URL 給前端

**初始化位置**: `includes/class-plugin.php` → `register_hooks()`

**Hook 選擇**:
```php
// 選項 1: FluentCart 提供的 hook (如果有)
add_action('fluent_cart/account_order_detail', [$this, 'inject_child_orders_display']);

// 選項 2: WordPress 通用 hook
add_action('wp_footer', [$this, 'inject_child_orders_display']);
// 僅在 is_page('account') 或特定條件下執行
```

### 2. Child Order Service
**檔案**: `includes/services/class-child-order-service.php`

**方法**:
```php
/**
 * 取得子訂單列表
 * @param int $parent_id 父訂單 ID
 * @param int $customer_id 客戶 ID (用於權限檢查)
 * @return array|WP_Error
 */
public function get_child_orders($parent_id, $customer_id);

/**
 * 格式化子訂單摘要 (已付款/未付款金額)
 * @param object $parent_order 父訂單物件
 * @param array $child_orders 子訂單陣列
 * @return array
 */
public function format_child_order_summary($parent_order, $child_orders);
```

**資料查詢**:
```php
// 使用 FluentCart Order Model
use FluentCart\App\Models\Order;

$child_orders = Order::where('parent_id', $parent_id)
    ->with(['order_items', 'customer'])
    ->orderBy('created_at', 'asc')
    ->get();
```

### 3. Child Order API
**檔案**: `includes/api/class-child-orders-api.php`

**端點**:
```php
// GET /wp-json/buygo-plus-one/v1/child-orders/?parent_id={id}
register_rest_route('buygo-plus-one/v1', '/child-orders/', [
    'methods' => 'GET',
    'callback' => [$this, 'get_child_orders'],
    'permission_callback' => [$this, 'check_customer_permission'],
    'args' => [
        'parent_id' => [
            'required' => true,
            'validate_callback' => function($param) {
                return is_numeric($param);
            }
        ]
    ]
]);
```

**權限檢查**:
```php
public function check_customer_permission($request) {
    // 1. 檢查用戶是否登入
    if (!is_user_logged_in()) {
        return false;
    }

    // 2. 取得父訂單
    $parent_id = $request->get_param('parent_id');
    $parent_order = Order::find($parent_id);

    if (!$parent_order) {
        return false;
    }

    // 3. 檢查是否為訂單所屬客戶
    $current_user_id = get_current_user_id();
    $customer_id = $parent_order->customer_id;

    // 需要查詢 wp_fct_customers 表取得 user_id
    $customer = Customer::find($customer_id);

    return $customer && $customer->user_id === $current_user_id;
}
```

### 4. Frontend JavaScript
**檔案**: `assets/js/child-orders-frontend.js` (透過 inline script 注入)

**結構**:
```javascript
(function() {
    // 1. 從 DOM 或 URL 取得 parent_order_id
    const parentOrderId = getParentOrderId();

    if (!parentOrderId) return;

    // 2. 呼叫 REST API
    fetch(`/wp-json/buygo-plus-one/v1/child-orders/?parent_id=${parentOrderId}`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'X-WP-Nonce': buygoChildOrders.nonce
        }
    })
    .then(response => response.json())
    .then(data => {
        renderChildOrders(data);
    })
    .catch(error => {
        console.error('Error fetching child orders:', error);
    });

    // 3. 渲染子訂單
    function renderChildOrders(data) {
        const container = document.getElementById('buygo-child-orders');
        if (!container) return;

        // 生成 HTML
        let html = '<div class="child-orders-summary">';
        html += `<h3>子訂單列表</h3>`;
        html += `<p>已付款: ${formatMoney(data.paid_amount)}</p>`;
        html += `<p>未付款: ${formatMoney(data.unpaid_amount)}</p>`;
        html += '<ul>';

        data.child_orders.forEach(order => {
            html += `<li>訂單 #${order.id} - ${order.status} - ${formatMoney(order.total_amount)}</li>`;
        });

        html += '</ul></div>';
        container.innerHTML = html;
    }

    function formatMoney(amount) {
        return `NT$ ${(amount / 100).toLocaleString()}`;
    }

    function getParentOrderId() {
        // 從 URL 參數或 DOM 取得
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('order_id') ||
               document.querySelector('[data-order-id]')?.dataset.orderId;
    }
})();
```

### 5. Frontend CSS
**檔案**: `assets/css/child-orders-frontend.css` (透過 inline style 注入)

```css
#buygo-child-orders {
    margin-top: 2rem;
    padding: 1.5rem;
    background: #f9fafb;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
}

.child-orders-summary h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.child-orders-summary ul {
    list-style: none;
    padding: 0;
}

.child-orders-summary li {
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    background: white;
    border-radius: 0.25rem;
    border: 1px solid #e5e7eb;
}
```

## 資料流

### 前台查詢子訂單流程

```
[客戶瀏覽訂單詳情頁]
    ↓
[WordPress 載入 FluentCart 前台頁面]
    ↓
[觸發 Hook: 'fluent_cart/account_order_detail' 或 'wp_footer']
    ↓
[ChildOrderFrontend::inject_child_orders_display()]
    ├─ 檢查是否為訂單詳情頁
    ├─ 輸出 HTML 容器: <div id="buygo-child-orders"></div>
    ├─ wp_enqueue_script('buygo-child-orders-js')
    └─ wp_localize_script() 傳遞 nonce 和 API URL
    ↓
[瀏覽器執行 JavaScript]
    ├─ 從 URL 或 DOM 取得 parent_order_id
    ├─ Fetch API 呼叫:
    │  GET /wp-json/buygo-plus-one/v1/child-orders/?parent_id=123
    │  Headers: { 'X-WP-Nonce': nonce }
    ↓
[Child_Orders_API::get_child_orders()]
    ├─ 權限檢查 (check_customer_permission)
    │  ├─ 是否登入？
    │  ├─ 訂單是否存在？
    │  └─ 是否為訂單所屬客戶？
    ├─ 呼叫 ChildOrderService::get_child_orders()
    └─ 返回 JSON:
       {
           "parent_order": { ... },
           "child_orders": [ ... ],
           "paid_amount": 162500,
           "unpaid_amount": 487500
       }
    ↓
[JavaScript 渲染子訂單到 DOM]
    └─ 顯示子訂單列表、已付款/未付款金額
```

## 資料查詢最佳化

### 查詢策略比較

| 策略 | SQL 查詢次數 | 效能 | 複雜度 | 建議 |
|------|-------------|------|--------|------|
| **策略 A: 單次 JOIN 查詢** | 1 次 | 最佳 | 中等 | ✅ 推薦 |
| **策略 B: 多次分開查詢** | 2+ 次 | 較差 | 簡單 | 適合 MVP |
| **策略 C: FluentCart Model with()** | 1-2 次 | 良好 | 簡單 | ✅ 推薦 |

### 推薦查詢方式 (策略 C)

```php
// 使用 FluentCart Eloquent Model with eager loading
use FluentCart\App\Models\Order;

public function get_child_orders($parent_id, $customer_id) {
    // 1. 取得父訂單 (包含客戶資料)
    $parent_order = Order::with(['customer', 'order_items'])
        ->find($parent_id);

    if (!$parent_order) {
        return new WP_Error('NOT_FOUND', '訂單不存在');
    }

    // 2. 權限檢查
    if ($parent_order->customer_id !== $customer_id) {
        return new WP_Error('FORBIDDEN', '無權限查看此訂單');
    }

    // 3. 取得所有子訂單 (包含訂單項目)
    $child_orders = Order::where('parent_id', $parent_id)
        ->with(['order_items'])
        ->orderBy('created_at', 'asc')
        ->get();

    // 4. 計算金額
    $paid_amount = 0;
    $unpaid_amount = 0;

    foreach ($child_orders as $child) {
        if (in_array($child->status, ['shipped', 'completed'])) {
            $paid_amount += $child->total_amount;
        } else {
            $unpaid_amount += $child->total_amount;
        }
    }

    return [
        'parent_order' => $parent_order,
        'child_orders' => $child_orders,
        'paid_amount' => $paid_amount,
        'unpaid_amount' => $unpaid_amount
    ];
}
```

### 快取策略 (可選，Phase 2)

```php
// 使用 WordPress Transient API 快取子訂單資料
public function get_child_orders_cached($parent_id, $customer_id) {
    $cache_key = "buygo_child_orders_{$parent_id}";
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        return $cached;
    }

    $data = $this->get_child_orders($parent_id, $customer_id);

    if (!is_wp_error($data)) {
        // 快取 5 分鐘
        set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
    }

    return $data;
}

// 當訂單狀態更新時清除快取
public function clear_child_orders_cache($parent_id) {
    delete_transient("buygo_child_orders_{$parent_id}");
}
```

## 整合流程 (Hook 註冊)

### 在 includes/class-plugin.php 中註冊

```php
private function register_hooks() {
    // ... 現有 hooks ...

    // 【v1.4】初始化前台子訂單顯示
    require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-child-order-frontend.php';
    new \BuyGoPlus\ChildOrderFrontend();

    // 【v1.4】註冊子訂單 API
    require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-child-orders-api.php';
    add_action('rest_api_init', function() {
        $api = new \BuyGoPlus\Api\Child_Orders_API();
        $api->register_routes();
    });
}

private function load_dependencies() {
    // ... 現有依賴 ...

    // 【v1.4】載入子訂單服務
    require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-child-order-service.php';
}
```

## Hook 整合模式 (參考 buygo-line-notify)

BuyGo 生態系統使用 **WordPress Hook 整合模式** 來整合多個外掛，而不是深度耦合。

### 參考案例: buygo-line-notify

**架構**:
```
buygo-plus-one (主外掛)
    ↓ (觸發 action)
do_action('buygo_order_status_changed', $order_id, $old_status, $new_status)
    ↓ (監聽 action)
buygo-line-notify (獨立外掛)
    ├─ add_action('buygo_order_status_changed', callback)
    └─ 發送 LINE 通知
```

### v1.4 應用此模式

**前台注入 Hook**:
```php
// 在 FluentCart 訂單詳情頁觸發
do_action('buygo_after_order_detail', $order_id);
```

**BuyGo+1 監聽並注入內容**:
```php
add_action('buygo_after_order_detail', function($order_id) {
    // 注入子訂單顯示區塊
});
```

但由於 FluentCart 可能沒有提供這樣的 hook，我們需要使用 WordPress 通用 hook：

```php
add_action('wp_footer', function() {
    // 檢查是否為 FluentCart 訂單詳情頁
    if (!is_fluent_cart_order_page()) {
        return;
    }

    // 注入 JavaScript 和 HTML
});
```

## 建議 Phase 結構

### Phase 1: 後端 API 和權限 (2-3h)
1. 建立 `ChildOrderService`
   - `get_child_orders()` 方法
   - 權限檢查邏輯
2. 建立 `Child_Orders_API`
   - REST 端點註冊
   - 權限回調函數
3. 單元測試 (Service 層)

**輸出**: API 端點可正常呼叫，權限檢查正確

### Phase 2: 前台注入和渲染 (2-3h)
1. 建立 `ChildOrderFrontend` 類別
   - Hook 註冊
   - HTML 容器注入
   - JavaScript/CSS enqueue
2. 撰寫前端 JavaScript
   - API 呼叫邏輯
   - DOM 渲染邏輯
3. 撰寫 CSS 樣式

**輸出**: 前台可顯示子訂單列表

### Phase 3: 樣式和 UX 優化 (1-2h)
1. 改善視覺呈現
2. 載入狀態和錯誤處理
3. 響應式設計

**輸出**: 使用者體驗良好，視覺符合 FluentCart 風格

### Phase 4: 測試和部署 (1h)
1. 瀏覽器測試（權限、顯示）
2. 多筆子訂單測試
3. 無子訂單情況測試

**輸出**: 功能穩定，可上線

## Architectural Patterns

### Pattern 1: WordPress Hook 注入模式

**What:** 使用 WordPress action/filter hook 在第三方外掛頁面注入自訂內容

**When to use:**
- 需要在第三方外掛頁面顯示自訂內容
- 不想修改第三方外掛核心程式碼
- 需要保持外掛升級相容性

**Trade-offs:**
- ✅ 優點: 低耦合，易維護，升級安全
- ❌ 缺點: 受限於第三方提供的 hook，可能找不到理想的注入點

**Example:**
```php
class ChildOrderFrontend {
    public function __construct() {
        // 在頁面底部注入
        add_action('wp_footer', [$this, 'inject_display'], 10);
    }

    public function inject_display() {
        if (!$this->is_order_detail_page()) {
            return;
        }

        echo '<div id="buygo-child-orders"></div>';
        $this->enqueue_scripts();
    }
}
```

### Pattern 2: REST API + Cookie Auth 模式

**What:** 前台使用 WordPress Cookie 和 Nonce 進行 REST API 認證

**When to use:**
- 前台需要呼叫需要認證的 API
- 使用者已透過 WordPress 登入
- 不需要支援外部應用

**Trade-offs:**
- ✅ 優點: 利用 WordPress 內建認證，無需額外設定
- ❌ 缺點: 僅適用於同源請求，不支援跨域

**Example:**
```php
// PHP 端傳遞 nonce
wp_localize_script('buygo-child-orders', 'buygoChildOrders', [
    'nonce' => wp_create_nonce('wp_rest'),
    'apiUrl' => rest_url('buygo-plus-one/v1/')
]);

// JavaScript 端使用
fetch(apiUrl + 'child-orders/?parent_id=123', {
    credentials: 'same-origin',
    headers: {
        'X-WP-Nonce': buygoChildOrders.nonce
    }
});
```

### Pattern 3: Service Layer 權限檢查模式

**What:** 在 Service 層進行權限檢查，而不只在 API 層

**When to use:**
- Service 可能被多個入口點呼叫 (API, WP-CLI, Cron)
- 需要確保所有呼叫都經過權限檢查
- 商業邏輯需要與權限邏輯耦合

**Trade-offs:**
- ✅ 優點: 防止繞過 API 直接呼叫 Service 的安全漏洞
- ❌ 缺點: 權限邏輯重複，API 和 Service 都需要檢查

**Example:**
```php
class ChildOrderService {
    public function get_child_orders($parent_id, $customer_id) {
        // Service 層權限檢查
        $parent = Order::find($parent_id);

        if (!$parent || $parent->customer_id !== $customer_id) {
            return new WP_Error('FORBIDDEN', '無權限');
        }

        // 執行商業邏輯
        return Order::where('parent_id', $parent_id)->get();
    }
}
```

## Anti-Patterns

### Anti-Pattern 1: 修改 FluentCart 核心檔案

**What people do:** 直接修改 `/wp-content/plugins/fluent-cart/` 內的檔案來新增功能

**Why it's wrong:**
- FluentCart 升級時會覆蓋修改
- 無法追蹤變更
- 難以除錯和維護

**Do this instead:** 使用 WordPress Hook 和 Filter 在外部注入功能

### Anti-Pattern 2: 全域載入前端 JavaScript

**What people do:** 在所有頁面都載入子訂單顯示的 JavaScript

**Why it's wrong:**
- 增加不必要的頁面載入時間
- 浪費伺服器和客戶端資源
- JavaScript 可能在不相關頁面執行錯誤

**Do this instead:**
```php
public function enqueue_scripts() {
    // 僅在訂單詳情頁載入
    if (!$this->is_order_detail_page()) {
        return;
    }

    wp_add_inline_script('buygo-child-orders', $this->get_inline_script());
}
```

### Anti-Pattern 3: 在前端直接查詢資料庫

**What people do:** 使用 JavaScript 直接查詢 MySQL 資料庫

**Why it's wrong:**
- 暴露資料庫憑證給前端
- 繞過權限檢查
- 嚴重安全漏洞

**Do this instead:** 使用 REST API，在後端進行權限檢查和資料查詢

### Anti-Pattern 4: 每次請求都查詢完整訂單資料

**What people do:**
```php
// 查詢父訂單所有資料
$parent = Order::with('customer', 'order_items', 'transactions')->find($id);
// 但實際只需要 customer_id
```

**Why it's wrong:**
- 載入不必要的關聯資料
- 增加記憶體使用
- 降低查詢效能

**Do this instead:**
```php
// 只查詢需要的欄位
$parent = Order::select('id', 'customer_id')->find($id);

// 或使用 lazy loading
if ($need_items) {
    $parent->load('order_items');
}
```

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| FluentCart Orders | Eloquent Model (直接查詢) | 使用 `FluentCart\App\Models\Order` |
| WordPress User Auth | Cookie + Nonce | 前台認證使用 WordPress 內建機制 |
| WordPress REST API | 註冊自訂端點 | 命名空間: `buygo-plus-one/v1` |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| Frontend ↔ API | REST + JSON | 使用 Fetch API，Cookie 認證 |
| API ↔ Service | 直接呼叫 | Service 返回陣列或 WP_Error |
| Service ↔ Database | Eloquent ORM | FluentCart 提供的 Model |

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| 0-1k orders | 當前架構足夠，無需快取 |
| 1k-10k orders | 新增 Transient 快取 (5 分鐘) |
| 10k+ orders | 考慮 Redis Object Cache，資料庫索引優化 |

### Scaling Priorities

1. **First bottleneck:** 子訂單查詢變慢
   - **Fix:** 在 `wp_fct_orders.parent_id` 新增索引 (FluentCart 應該已建立)
   - **Fix:** 使用 `with()` eager loading 減少 N+1 查詢

2. **Second bottleneck:** 前台 API 呼叫頻繁
   - **Fix:** 新增 Transient 快取，減少重複查詢
   - **Fix:** 使用 localStorage 快取前端資料 (5 分鐘過期)

## Sources

### WordPress REST API & Authentication
- [WordPress REST API Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/)
- [How to Secure WordPress REST API Requests with JavaScript and Nonces](https://www.voxfor.com/how-to-secure-wordpress-rest-api-requests-with-javascript-and-nonces/)
- [Learning Vue.js as a WordPress Developer Part 4 – Authenticated Rest API](https://anchor.host/learning-vue-js-as-a-wordpress-developer-part-4-authenticated-rest-api/)

### WordPress Script Enqueue Best Practices
- [wp_enqueue_script() – Function](https://developer.wordpress.org/reference/functions/wp_enqueue_script/)
- [wp_add_inline_script() – Function](https://developer.wordpress.org/reference/functions/wp_add_inline_script/)
- [Better Way to Add Inline Scripts](https://digwp.com/2019/07/better-inline-script/)
- [How to Properly Add JavaScripts and Styles in WordPress](https://www.wpbeginner.com/wp-tutorials/how-to-properly-add-javascripts-and-styles-in-wordpress/)

### FluentCart Integration
- [FluentCart Documentation](https://docs.fluentcart.com/guide/changelog)
- [FluentCart Integrations](https://fluentcart.com/integrations/)
- [Fluent Support Integration - FluentCart](https://docs.fluentcart.com/guide/integrations/fluentsupport-integration/)

### WooCommerce Parent-Child Order Pattern
- [Parent-Child Order Concept · Issue #20922](https://github.com/woocommerce/woocommerce/issues/20922)
- [WooCommerce Subscription Orders](https://woocommerce.com/document/subscriptions/orders/)
- [WC_Abstract_Order::get_parent_id](https://wp-kama.com/plugin/woocommerce/function/WC_Abstract_Order::get_parent_id)

---
*Architecture research for: BuyGo Plus One v1.4 - 會員前台子訂單顯示功能*
*Researched: 2026-02-02*
*Confidence: HIGH (Based on existing BuyGo+1 codebase + WordPress/FluentCart best practices)*
