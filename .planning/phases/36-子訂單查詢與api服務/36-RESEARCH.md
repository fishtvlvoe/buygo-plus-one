# Phase 36: 子訂單查詢與 API 服務 - Research

**Researched:** 2026-02-02
**Domain:** WordPress REST API + FluentCart Data Access
**Confidence:** HIGH

## Summary

本次研究聚焦於實作後端資料查詢邏輯和 REST API 端點，提供子訂單資料給前端（Phase 35 已完成的 UI 整合）。研究分析了現有專案架構、FluentCart 資料表結構、以及 WordPress REST API 最佳實踐。

核心發現：
- BuyGo+1 已有成熟的 Service Layer 模式和 REST API 架構，可直接參考 `OrderService` 和 `Orders_API` 的實作
- FluentCart 使用 Eloquent ORM（Laravel 風格），支援 `with()` Eager Loading 避免 N+1 查詢
- 三層權限驗證是安全核心：API nonce + Service customer_id 檢查 + SQL WHERE 條件
- Phase 35 已傳遞 `apiBase` 和 `nonce` 給前端 JavaScript，Phase 36 只需建立對應的 API 端點

**Primary recommendation:** 建立 `ChildOrderService` 和 `ChildOrders_API` 兩個新類別，遵循現有專案架構模式。使用 FluentCart `Order::with(['order_items'])` 查詢子訂單，透過 `wp_fct_customers` 表將 `customer_id` 轉換為 `user_id` 進行權限驗證。

## Standard Stack

### Core Technologies
| Technology | Version | Purpose | Why Standard |
|------------|---------|---------|--------------|
| WordPress REST API | Core | 建立 `/child-orders/{parent_order_id}` 端點 | BuyGo+1 現有 11 個 API 類別都使用此標準 |
| FluentCart Order Model | FluentCart Core | 查詢 `wp_fct_orders` 資料表 | 現有 `OrderService` 已使用，提供 Eloquent ORM 功能 |
| PHP 8.0+ | 8.0+ | Service Layer 實作 | 專案已設定的最低版本要求 |
| `$wpdb->prepare()` | WordPress Core | 安全的 SQL 查詢 | 防止 SQL Injection，WordPress 標準 |

### Supporting Tools
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `wp_create_nonce()` / `wp_verify_nonce()` | WordPress Core | API 請求驗證 | 所有 REST API 端點都需要 nonce 驗證 |
| `absint()` | WordPress Core | 整數清理 | 清理所有 ID 參數（order_id, customer_id） |
| FluentCart Customer Model | FluentCart Core | 查詢客戶資料 | 將 `customer_id` 轉換為 `user_id` |
| `DebugService` | BuyGo+1 | 日誌記錄 | 記錄 API 請求和錯誤以便除錯 |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| FluentCart Order Model | 直接 SQL 查詢 | SQL 查詢更快但失去 Model 抽象和 Eager Loading 功能 |
| Service Layer | 直接在 API callback 中查詢 | 程式碼重複、難測試、違反專案架構規範 |
| Cookie + Nonce 驗證 | API Key 驗證 | 顧客前台使用 Cookie 驗證更自然，API Key 適合後台系統 |

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── api/
│   ├── class-child-orders-api.php   # 新增：子訂單 REST API 端點
│   └── class-api.php                # 修改：註冊新 API 類別
├── services/
│   └── class-child-order-service.php # 新增：子訂單查詢服務
```

### Pattern 1: Service Layer + API 分離（專案標準）
**What:** 商業邏輯放在 Service 類別，API 類別負責路由和格式化回應

**When to use:** 所有 BuyGo+1 API 端點都遵循此模式

**Example:**
```php
// Source: includes/api/class-orders-api.php (現有模式)
class Orders_API {
    private $orderService;

    public function __construct() {
        $this->orderService = new OrderService();
    }

    public function get_order($request) {
        $order_id = $request['id'];
        $order = $this->orderService->getOrderById($order_id);

        return new \WP_REST_Response([
            'success' => true,
            'data' => $order
        ], 200);
    }
}
```

### Pattern 2: 三層權限驗證
**What:** API 端點、Service 層、SQL 查詢三層都驗證權限

**When to use:** 所有涉及顧客資料的 API 端點

**Example:**
```php
// 第一層：API permission_callback
public function check_customer_permission(): bool {
    return is_user_logged_in();  // 驗證登入狀態
}

// 第二層：Service 方法驗證訂單所有權
public function getChildOrdersByParentId(int $parent_order_id, int $customer_id): array {
    // 先查詢父訂單，驗證 customer_id 匹配
    $parent_order = Order::find($parent_order_id);
    if (!$parent_order || $parent_order->customer_id !== $customer_id) {
        throw new \Exception('無權限存取此訂單');
    }
    // ...
}

// 第三層：SQL WHERE 條件
$child_orders = Order::where('parent_id', $parent_order_id)
    ->whereHas('parent', function($q) use ($customer_id) {
        $q->where('customer_id', $customer_id);
    })
    ->get();
```

### Pattern 3: FluentCart Eager Loading（避免 N+1）
**What:** 使用 `with()` 預載入關聯資料

**When to use:** 查詢子訂單和商品清單時必須使用

**Example:**
```php
// Source: includes/services/class-order-service.php 第 47-48 行
$query = Order::with(['customer', 'order_items']);

// 子訂單查詢應使用
$child_orders = Order::where('parent_id', $parent_id)
    ->with(['order_items', 'customer'])  // Eager Loading
    ->orderBy('created_at', 'desc')
    ->get();
```

### Pattern 4: customer_id 轉 user_id 查詢
**What:** FluentCart 的 `customer_id` 對應 `wp_fct_customers.id`，需要查詢 `user_id` 欄位才能與 `get_current_user_id()` 比對

**When to use:** 驗證訂單所有權時

**Example:**
```php
// FluentCart 資料表結構：
// wp_fct_orders.customer_id --> wp_fct_customers.id
// wp_fct_customers.user_id  --> WordPress users.ID

// 方法 1：使用 FluentCart Customer Model
use FluentCart\App\Models\Customer;

$current_user_id = get_current_user_id();
$customer = Customer::where('user_id', $current_user_id)->first();
$customer_id = $customer ? $customer->id : null;

// 方法 2：直接 SQL 查詢
$customer_id = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}fct_customers WHERE user_id = %d",
    $current_user_id
));
```

### Anti-Patterns to Avoid
- **在 API callback 中直接寫 SQL**：違反 Service Layer 模式，程式碼難以測試和重用
- **只在前端驗證權限**：前端驗證可被繞過，必須在後端三層驗證
- **忽略 Eager Loading**：每個子訂單的商品清單都會觸發額外查詢，造成 N+1 問題
- **硬編碼資料表名稱**：應使用 `$wpdb->prefix . 'fct_orders'` 確保多站點相容
- **信任前端傳來的 customer_id**：必須從 `get_current_user_id()` 取得並轉換

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| 訂單狀態標籤翻譯 | 自己寫 switch-case | 現有 `ShippingStatusService::getStatusLabel()` | 專案已有統一的狀態標籤翻譯 |
| 金額格式化 | 自己處理幣別和小數點 | FluentCart 的 `total_amount / 100` 轉換 | FluentCart 金額以「分」為單位儲存 |
| 權限檢查邏輯 | 重新實作登入驗證 | WordPress `is_user_logged_in()` + `get_current_user_id()` | WordPress 核心 API 已處理 session 和 cache |
| API 回應格式 | 自己定義 JSON 結構 | 參考現有 `Orders_API` 的回應格式 | 保持 API 一致性 |
| 訂單商品圖片 | 直接查詢 attachment | `wp_get_attachment_image_url($id, 'thumbnail')` | WordPress 標準函式，自動處理尺寸 |

**Key insight:** BuyGo+1 v1.0-v1.2 已建立成熟的架構模式，Phase 36 應完全遵循現有模式，不需要發明新的實作方式。

## Common Pitfalls

### Pitfall 1: customer_id 與 user_id 混淆
**What goes wrong:** 直接用 `get_current_user_id()` 與 `wp_fct_orders.customer_id` 比對，永遠不匹配

**Why it happens:** FluentCart 的 `customer_id` 是 `wp_fct_customers.id`，不是 WordPress `users.ID`

**How to avoid:**
```php
// ❌ 錯誤：直接比對
$parent_order->customer_id === get_current_user_id()

// ✅ 正確：先查詢 customer_id
$current_user_id = get_current_user_id();
$customer = Customer::where('user_id', $current_user_id)->first();
$customer_id = $customer ? $customer->id : null;

// 然後比對
$parent_order->customer_id === $customer_id
```

**Warning signs:**
- 所有使用者都看到「無權限存取」錯誤
- 登入使用者查詢自己的訂單卻被拒絕

### Pitfall 2: N+1 查詢導致效能問題
**What goes wrong:** 查詢 10 筆子訂單，觸發 10 次 `order_items` 查詢

**Why it happens:** 在迴圈中存取 `$order->order_items` 而未使用 Eager Loading

**How to avoid:**
```php
// ❌ 錯誤：N+1 查詢
$child_orders = Order::where('parent_id', $parent_id)->get();
foreach ($child_orders as $order) {
    $items = $order->order_items;  // 每次迴圈觸發一次查詢
}

// ✅ 正確：Eager Loading
$child_orders = Order::where('parent_id', $parent_id)
    ->with(['order_items'])  // 一次查詢所有商品
    ->get();
```

**Warning signs:**
- Query Monitor 顯示相同查詢模式重複執行
- API 回應時間 > 1 秒（在正常訂單量下）

### Pitfall 3: 未處理空子訂單情況
**What goes wrong:** 父訂單沒有子訂單時，前端顯示「載入中...」永不消失或顯示錯誤

**Why it happens:** API 回傳空陣列但前端未正確處理

**How to avoid:**
```php
// ✅ 正確：明確回傳成功狀態和空陣列
return new \WP_REST_Response([
    'success' => true,
    'data' => [
        'child_orders' => [],       // 空陣列，非 null
        'count' => 0,
        'message' => '此訂單沒有子訂單'
    ]
], 200);
```

**Warning signs:**
- 無子訂單的父訂單顯示載入狀態或錯誤訊息

### Pitfall 4: 金額單位錯誤
**What goes wrong:** 子訂單金額顯示為「10000」而非「100」

**Why it happens:** FluentCart 金額以「分」為單位儲存，需要除以 100 轉換為「元」

**How to avoid:**
```php
// ✅ 正確：轉換金額單位
$formatted_total = $order->total_amount / 100;  // 分 → 元
```

**Warning signs:**
- 金額顯示異常大（例如 10,000 而非 100）

### Pitfall 5: 賣家資訊查詢錯誤
**What goes wrong:** 子訂單的賣家名稱顯示為空或「未知賣家」

**Why it happens:** 子訂單沒有直接的 `seller_id` 欄位，需要從商品的 `post_author` 取得

**How to avoid:**
```php
// 從子訂單的商品取得賣家 ID
$order_items = $child_order->order_items;
$first_item = $order_items->first();
if ($first_item && $first_item->post_id) {
    $product = get_post($first_item->post_id);
    $seller_id = $product ? $product->post_author : null;
    $seller_name = $seller_id ? get_the_author_meta('display_name', $seller_id) : '未知賣家';
}
```

**Warning signs:**
- 賣家名稱為空或顯示 ID 而非名稱

## Code Examples

### Example 1: ChildOrderService 核心方法
```php
// Source: 建議實作
namespace BuyGoPlus\Services;

use FluentCart\App\Models\Order;
use FluentCart\App\Models\Customer;

class ChildOrderService {

    /**
     * 取得指定父訂單的所有子訂單
     *
     * @param int $parent_order_id 父訂單 ID
     * @param int $customer_id FluentCart customer_id（非 WordPress user_id）
     * @return array 格式化的子訂單資料
     * @throws \Exception 權限驗證失敗
     */
    public function getChildOrdersByParentId(int $parent_order_id, int $customer_id): array
    {
        // 1. 驗證父訂單存在且屬於此顧客
        $parent_order = Order::find($parent_order_id);
        if (!$parent_order) {
            throw new \Exception('訂單不存在', 404);
        }

        if ($parent_order->customer_id !== $customer_id) {
            throw new \Exception('無權限存取此訂單', 403);
        }

        // 2. 使用 Eager Loading 查詢子訂單和商品
        $child_orders = Order::where('parent_id', $parent_order_id)
            ->with(['order_items'])
            ->orderBy('created_at', 'desc')
            ->get();

        // 3. 格式化回傳資料
        $formatted = [];
        foreach ($child_orders as $order) {
            $formatted[] = $this->formatChildOrder($order);
        }

        return [
            'child_orders' => $formatted,
            'count' => count($formatted),
            'currency' => $parent_order->currency ?? 'TWD'
        ];
    }

    /**
     * 格式化單一子訂單
     */
    private function formatChildOrder(Order $order): array
    {
        $items = $order->order_items;
        $seller_name = $this->getSellerNameFromItems($items);

        return [
            'id' => $order->id,
            'invoice_no' => $order->invoice_no,
            'payment_status' => $order->payment_status ?? 'pending',
            'shipping_status' => $order->shipping_status ?? 'unshipped',
            'fulfillment_status' => $order->status ?? 'pending',
            'total_amount' => ($order->total_amount ?? 0) / 100,  // 分 → 元
            'currency' => $order->currency ?? 'TWD',
            'seller_name' => $seller_name,
            'items' => $this->formatItems($items),
            'created_at' => $order->created_at
        ];
    }

    /**
     * 從商品項目取得賣家名稱
     */
    private function getSellerNameFromItems($items): string
    {
        if ($items->isEmpty()) {
            return '未知賣家';
        }

        $first_item = $items->first();
        $post_id = $first_item->post_id ?? null;

        if (!$post_id) {
            return '未知賣家';
        }

        $product = get_post($post_id);
        if (!$product) {
            return '未知賣家';
        }

        // 如果是 variation，取得 parent 的 author
        if ($product->post_type === 'product_variation' && $product->post_parent > 0) {
            $parent_product = get_post($product->post_parent);
            $seller_id = $parent_product ? $parent_product->post_author : null;
        } else {
            $seller_id = $product->post_author;
        }

        return $seller_id ? get_the_author_meta('display_name', $seller_id) : '未知賣家';
    }

    /**
     * 格式化商品項目清單
     */
    private function formatItems($items): array
    {
        $formatted = [];
        foreach ($items as $item) {
            $formatted[] = [
                'id' => $item->id,
                'product_id' => $item->post_id ?? $item->product_id ?? 0,
                'title' => $item->title ?? $item->post_title ?? '未知商品',
                'quantity' => $item->quantity ?? 1,
                'unit_price' => ($item->unit_price ?? 0) / 100,
                'line_total' => ($item->line_total ?? 0) / 100
            ];
        }
        return $formatted;
    }
}
```

### Example 2: ChildOrders_API 端點定義
```php
// Source: 建議實作
namespace BuyGoPlus\Api;

use BuyGoPlus\Services\ChildOrderService;
use FluentCart\App\Models\Customer;

class ChildOrders_API {

    private $namespace = 'buygo-plus-one/v1';
    private $childOrderService;

    public function __construct() {
        $this->childOrderService = new ChildOrderService();
    }

    public function register_routes() {
        // GET /child-orders/{parent_order_id}
        register_rest_route($this->namespace, '/child-orders/(?P<parent_order_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_child_orders'],
            'permission_callback' => [$this, 'check_customer_permission'],
            'args' => [
                'parent_order_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
    }

    /**
     * 第一層權限驗證：檢查是否登入
     */
    public function check_customer_permission(\WP_REST_Request $request): bool {
        return is_user_logged_in();
    }

    /**
     * 取得子訂單
     */
    public function get_child_orders(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $parent_order_id = (int) $request->get_param('parent_order_id');

            // 取得當前使用者的 FluentCart customer_id
            $current_user_id = get_current_user_id();
            $customer = Customer::where('user_id', $current_user_id)->first();

            if (!$customer) {
                return new \WP_REST_Response([
                    'success' => false,
                    'code' => 'CUSTOMER_NOT_FOUND',
                    'message' => '找不到顧客資料'
                ], 404);
            }

            // 呼叫 Service 取得子訂單
            $result = $this->childOrderService->getChildOrdersByParentId(
                $parent_order_id,
                $customer->id
            );

            return new \WP_REST_Response([
                'success' => true,
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            $code = $e->getCode();
            $http_status = in_array($code, [403, 404]) ? $code : 500;

            return new \WP_REST_Response([
                'success' => false,
                'code' => $code === 403 ? 'FORBIDDEN' : ($code === 404 ? 'NOT_FOUND' : 'SERVER_ERROR'),
                'message' => $e->getMessage()
            ], $http_status);
        }
    }
}
```

### Example 3: API 註冊（修改 class-api.php）
```php
// Source: includes/api/class-api.php（需修改）
public function register_routes() {
    // ... 現有 API 載入 ...

    // 新增：載入子訂單 API
    require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-child-orders-api.php';

    // ... 現有 API 註冊 ...

    // 新增：註冊子訂單 API
    $child_orders_api = new ChildOrders_API();
    $child_orders_api->register_routes();
}
```

### Example 4: 狀態標籤格式化
```php
// Source: 參考現有 ShippingStatusService
$status_labels = [
    // payment_status
    'pending' => '待付款',
    'paid' => '已付款',
    'failed' => '付款失敗',
    'refunded' => '已退款',

    // shipping_status
    'unshipped' => '待出貨',
    'preparing' => '備貨中',
    'shipped' => '已出貨',
    'completed' => '已完成',

    // fulfillment_status (order status)
    'processing' => '處理中',
    'cancelled' => '已取消'
];

$label = $status_labels[$status] ?? $status;
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| 每個子訂單單獨查詢商品 | Eloquent `with()` Eager Loading | Laravel/FluentCart 標準 | N 次查詢 → 2 次查詢 |
| 直接在 API callback 寫 SQL | Service Layer 分離 | BuyGo+1 v1.0 架構決策 | 程式碼可測試、可重用 |
| 只在前端驗證權限 | 三層後端驗證 | 2026 WordPress 安全最佳實踐 | 防止 IDOR 攻擊 |
| 回傳 FluentCart Model 原始資料 | 格式化後回傳 | BuyGo+1 v1.0 API 規範 | 前端不需處理金額單位轉換 |

**Deprecated/outdated:**
- **直接操作 `$_GET` 參數**：應使用 `$request->get_param()` 取得已清理的參數
- **回傳敏感欄位**：不應回傳內部備註、成本等商業機密資料
- **依賴 FluentCart 未文件化的 Model 方法**：只使用官方文件列出的 API

## FluentCart 資料表結構

### wp_fct_orders（主訂單/子訂單）
```sql
id                  -- 訂單 ID
parent_id           -- 父訂單 ID（子訂單才有值）
customer_id         -- FluentCart 顧客 ID（對應 wp_fct_customers.id）
invoice_no          -- 發票編號
status              -- 訂單狀態（pending, processing, completed, cancelled）
payment_status      -- 付款狀態（pending, paid, failed, refunded）
shipping_status     -- 運送狀態（unshipped, preparing, shipped, completed）
total_amount        -- 總金額（分為單位）
currency            -- 幣別（TWD）
created_at          -- 建立時間
updated_at          -- 更新時間
```

### wp_fct_order_items（訂單商品）
```sql
id                  -- 項目 ID
order_id            -- 訂單 ID
post_id             -- WordPress 商品 ID（fluent-products）
object_id           -- FluentCart variation ID
quantity            -- 數量
unit_price          -- 單價（分為單位）
line_total          -- 小計（分為單位）
title               -- 商品名稱
created_at          -- 建立時間
```

### wp_fct_customers（顧客）
```sql
id                  -- FluentCart 顧客 ID
user_id             -- WordPress 使用者 ID
email               -- 電子郵件
first_name          -- 名字
last_name           -- 姓氏
```

## Open Questions

1. **子訂單分頁需求**
   - What we know: Phase 36 需求未提及分頁
   - What's unclear: 單一父訂單可能有多少子訂單？是否需要分頁或延遲載入？
   - Recommendation: MVP 不實作分頁，一次回傳所有子訂單。若未來子訂單數量超過 50 筆，再加入分頁邏輯

2. **快取策略**
   - What we know: 現有 Orders_API 在 `get_orders` 設定 `Cache-Control: no-cache`
   - What's unclear: 子訂單 API 是否需要快取？狀態更新時如何清除？
   - Recommendation: MVP 同樣使用 no-cache，確保資料即時性。效能優化在後續 Phase 處理

3. **錯誤訊息國際化**
   - What we know: 現有 API 使用中文錯誤訊息
   - What's unclear: 是否需要支援多語言？
   - Recommendation: 保持與現有 API 一致，使用中文錯誤訊息

## Sources

### Primary (HIGH confidence)
- BuyGo+1 現有程式碼：
  - `includes/services/class-order-service.php` - 訂單服務標準實作
  - `includes/api/class-orders-api.php` - API 端點標準實作
  - `includes/api/class-api.php` - API 註冊模式
- FluentCart Order Model：`FluentCart\App\Models\Order`
- FluentCart Customer Model：`FluentCart\App\Models\Customer`
- Phase 35 RESEARCH.md：FluentCart Hook 整合研究

### Secondary (MEDIUM confidence)
- `.planning/research-v1.4/ARCHITECTURE.md` - 架構設計文件
- `.planning/research-v1.4/PITFALLS.md` - 常見陷阱研究
- WordPress REST API Handbook

### Tertiary (LOW confidence)
- [FluentCart Developer Docs](https://dev.fluentcart.com/) - 官方文件（API 細節可能未完整）

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - 完全基於專案現有架構和 FluentCart 官方 API
- Architecture: HIGH - 參考現有 11 個 API 類別的實作模式
- Pitfalls: HIGH - 基於 v1.4 研究文件和專案程式碼分析

**Research date:** 2026-02-02
**Valid until:** 2026-03-02 (30 days - BuyGo+1 架構穩定)

**Key Takeaways:**
1. 完全遵循現有 Service Layer + API 分離模式
2. customer_id 與 user_id 的轉換是權限驗證的關鍵
3. 必須使用 Eager Loading 避免 N+1 查詢
4. 金額單位為「分」，需除以 100 轉換為「元」
5. 從商品的 post_author 取得賣家資訊
