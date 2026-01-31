# FluentCart 資料庫結構分析 - Dashboard 儀表板設計

**分析日期**：2026-01-29
**目的**：為 BuyGo Plus One Dashboard 儀表板提供 FluentCart 資料來源基礎

---

## 一、執行摘要

本文件深入分析 FluentCart 資料庫結構，為 BuyGo Plus One 外掛的 Dashboard 儀表板設計提供完整的資料來源基礎。

### 核心發現

1. **FluentCart 使用自定義資料表** - 不依賴 WordPress 標準表，擁有完整獨立的資料庫架構
2. **金額儲存為 BIGINT (cents)** - 所有金額欄位以「分」為單位儲存，避免浮點數精度問題
3. **完整的關聯架構** - Orders、Customers、Products 透過 Eloquent ORM 建立清晰的關係
4. **賣家隔離機制** - 需透過 WordPress User ID 或 Customer ID 進行資料權限隔離
5. **現成統計 API** - FluentCart 提供 `/dashboard/stats` 和 `/reports/overview` 端點

---

## 二、FluentCart 核心資料表結構

### 2.1 Orders 訂單系統

#### `{prefix}_fct_orders` - 訂單主表

FluentCart 訂單資料表包含所有訂單核心資訊。

| 欄位 | 類型 | 說明 | Dashboard 用途 |
|------|------|------|----------------|
| `id` | BIGINT | 訂單 ID（主鍵） | 訂單計數 |
| `customer_id` | BIGINT | 客戶 ID（外鍵） | **賣家隔離關鍵** |
| `status` | VARCHAR(20) | 訂單狀態 | 狀態統計 |
| `payment_status` | VARCHAR(20) | 付款狀態 | 已付款/未付款分類 |
| `payment_method` | VARCHAR(100) | 付款方式 | 金流分析 |
| `total_amount` | BIGINT | 訂單總金額（分） | **營收統計** |
| `total_paid` | BIGINT | 已付款金額（分） | 實際收入 |
| `total_refund` | BIGINT | 退款金額（分） | 退款統計 |
| `currency` | VARCHAR(10) | 幣別 | 多幣別支援 |
| `created_at` | DATETIME | 建立時間 | **時間序列** |
| `completed_at` | DATETIME | 完成時間 | 訂單完成率 |
| `type` | VARCHAR(20) | 訂單類型（payment/renewal/refund） | 訂單分類 |
| `mode` | ENUM('live','test') | 模式（正式/測試） | 排除測試訂單 |

**重要索引**：
- `customer_id` - 賣家隔離必備
- `created_at`, `completed_at` - 時間範圍查詢
- `status`, `payment_status` - 狀態篩選

**訂單狀態值**：
- `draft` - 草稿
- `pending` - 待處理
- `processing` - 處理中
- `completed` - 已完成
- `failed` - 失敗
- `refunded` - 已退款
- `partial-refund` - 部分退款

---

#### `{prefix}_fct_order_items` - 訂單項目表

儲存訂單內的每個商品項目。

| 欄位 | 類型 | 說明 | Dashboard 用途 |
|------|------|------|----------------|
| `id` | BIGINT | 項目 ID | - |
| `order_id` | BIGINT | 訂單 ID（外鍵） | 關聯訂單 |
| `post_id` | BIGINT | 商品 ID（WP Post） | 商品銷售統計 |
| `object_id` | BIGINT | 變體 ID | 變體銷售統計 |
| `quantity` | INT | 數量 | 銷量統計 |
| `unit_price` | BIGINT | 單價（分） | 定價分析 |
| `line_total` | BIGINT | 小計（分） | 商品營收 |

**索引**：
- `order_id, object_id` - 訂單商品查詢
- `post_id` - 商品銷售統計

---

#### `{prefix}_fct_order_transactions` - 交易紀錄表

儲存付款、退款交易記錄。

| 欄位 | 類型 | 說明 | Dashboard 用途 |
|------|------|------|----------------|
| `order_id` | BIGINT | 訂單 ID（外鍵） | 關聯訂單 |
| `transaction_type` | VARCHAR(192) | 交易類型（charge/refund） | 區分付款/退款 |
| `status` | VARCHAR(20) | 交易狀態 | 交易成功率 |
| `total` | BIGINT | 交易金額（分） | 實際金流 |
| `payment_method` | VARCHAR(100) | 付款方式 | 金流管道分析 |

---

### 2.2 Customers 客戶系統

#### `{prefix}_fct_customers` - 客戶主表

| 欄位 | 類型 | 說明 | Dashboard 用途 |
|------|------|------|----------------|
| `id` | BIGINT | 客戶 ID（主鍵） | 客戶計數 |
| `user_id` | BIGINT | WordPress User ID | **賣家隔離關鍵** |
| `email` | VARCHAR(192) | Email | 客戶識別 |
| `first_name`, `last_name` | VARCHAR(192) | 姓名 | 客戶資訊 |
| `purchase_count` | BIGINT | 購買次數 | 回購率 |
| `ltv` | BIGINT | 終身價值（分） | 客戶價值分析 |
| `aov` | DECIMAL(18,2) | 平均訂單金額 | 客戶消費行為 |
| `first_purchase_date` | DATETIME | 首次購買 | 新客戶分析 |
| `last_purchase_date` | DATETIME | 最近購買 | 活躍度分析 |
| `status` | VARCHAR(45) | 狀態（active/inactive） | 客戶狀態 |

**索引**：
- `user_id` - WordPress 用戶關聯
- `email` - Email 查詢

---

### 2.3 Products 商品系統

#### `{prefix}_posts` (WordPress 標準表) - 商品主表

FluentCart 商品使用 WordPress 的 `posts` 表，`post_type = 'fluent-products'`。

| 欄位 | 類型 | 說明 | Dashboard 用途 |
|------|------|------|----------------|
| `ID` | BIGINT | 商品 ID（主鍵） | 商品計數 |
| `post_title` | TEXT | 商品名稱 | 商品資訊 |
| `post_status` | VARCHAR(20) | 狀態（publish/draft） | 上架狀態統計 |
| `post_author` | BIGINT | 作者 ID | **賣家隔離關鍵** |

---

#### `{prefix}_fct_product_details` - 商品詳細資訊表

| 欄位 | 類型 | 說明 | Dashboard 用途 |
|------|------|------|----------------|
| `post_id` | BIGINT | 商品 ID（外鍵） | 關聯商品 |
| `fulfillment_type` | VARCHAR(100) | 類型（physical/digital） | 實體/數位分類 |
| `min_price`, `max_price` | DOUBLE | 價格範圍 | 價格分析 |
| `stock_availability` | VARCHAR(100) | 庫存狀態 | 庫存統計 |
| `variation_type` | VARCHAR(30) | 變體類型 | 商品複雜度 |

---

#### `{prefix}_fct_product_variations` - 商品變體表

| 欄位 | 類型 | 說明 | Dashboard 用途 |
|------|------|------|----------------|
| `post_id` | BIGINT | 商品 ID（外鍵） | 關聯商品 |
| `variation_title` | VARCHAR(192) | 變體名稱 | 變體資訊 |
| `item_price` | DOUBLE | 售價 | 定價分析 |
| `stock_status` | VARCHAR(30) | 庫存狀態 | 庫存管理 |
| `total_stock` | INT | 總庫存 | 庫存數量 |
| `available` | INT | 可用庫存 | 可銷售庫存 |

---

### 2.4 Subscriptions 訂閱系統

#### `{prefix}_fct_subscriptions` - 訂閱表

| 欄位 | 類型 | 說明 | Dashboard 用途 |
|------|------|------|----------------|
| `customer_id` | BIGINT | 客戶 ID（外鍵） | 客戶訂閱 |
| `parent_order_id` | BIGINT | 初始訂單 ID | 訂閱來源 |
| `status` | VARCHAR(45) | 狀態（active/canceled/expired） | 訂閱狀態統計 |
| `recurring_amount` | BIGINT | 週期金額（分） | MRR 計算 |
| `next_billing_date` | DATETIME | 下次扣款日 | 未來營收預測 |

---

## 三、Model 關聯關係 (Eloquent ORM)

FluentCart 使用 Laravel Eloquent ORM，以下是主要關聯：

### 3.1 Order Model 關聯

```php
// Order 屬於一個 Customer
$order->customer; // FluentCart\App\Models\Customer

// Order 有多個 OrderItem
$order->items; // Collection of OrderItem

// Order 有多個 Transaction
$order->transactions; // Collection of OrderTransaction

// Order 可能有 Subscription
$order->subscription; // Subscription (nullable)
```

### 3.2 Customer Model 關聯

```php
// Customer 有多個 Order
$customer->orders; // Collection of Order

// Customer 有多個 Subscription
$customer->subscriptions; // Collection of Subscription

// Customer 關聯 WordPress User
$customer->wpUser; // WP_User
```

### 3.3 Product Model 關聯

```php
// Product 有詳細資訊
$product->detail; // ProductDetail

// Product 有多個變體
$product->variants; // Collection of ProductVariation

// Product 在多個訂單項目中
$product->orderItems; // Collection of OrderItem
```

---

## 四、Dashboard 資料需求對應

### 4.1 核心統計卡片

| 統計項目 | 資料來源 | SQL 查詢方式 |
|---------|---------|-------------|
| **今日訂單數** | `fct_orders` | `WHERE DATE(created_at) = CURDATE()` |
| **今日營收** | `fct_orders` | `SUM(total_amount) WHERE DATE(created_at) = CURDATE()` |
| **本週訂單數** | `fct_orders` | `WHERE YEARWEEK(created_at) = YEARWEEK(NOW())` |
| **本月營收** | `fct_orders` | `SUM(total_amount) WHERE MONTH(created_at) = MONTH(NOW())` |
| **待處理訂單** | `fct_orders` | `WHERE status IN ('pending', 'processing')` |
| **總客戶數** | `fct_customers` | `COUNT(*)` |
| **已上架商品** | `posts` | `WHERE post_type='fluent-products' AND post_status='publish'` |
| **活躍訂閱** | `fct_subscriptions` | `WHERE status='active'` |

---

### 4.2 營收趨勢圖（時間序列）

**需求**：顯示過去 7 天、30 天、90 天的每日營收趨勢。

**查詢邏輯**：

```sql
SELECT
    DATE(created_at) as date,
    SUM(total_amount) as revenue,
    COUNT(*) as order_count
FROM {prefix}_fct_orders
WHERE
    created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND status = 'completed'
    AND payment_status = 'paid'
    AND mode = 'live'
    -- 賣家隔離條件（見下方）
GROUP BY DATE(created_at)
ORDER BY date ASC
```

**資料表**：
- 主表：`fct_orders`
- 關鍵欄位：`created_at`, `total_amount`, `status`, `payment_status`

---

### 4.3 商品銷售排行

**需求**：列出銷量前 10 的商品。

**查詢邏輯**：

```sql
SELECT
    p.ID,
    p.post_title,
    SUM(oi.quantity) as total_sold,
    SUM(oi.line_total) as total_revenue
FROM {prefix}_fct_order_items oi
JOIN {prefix}_posts p ON oi.post_id = p.ID
JOIN {prefix}_fct_orders o ON oi.order_id = o.id
WHERE
    o.status = 'completed'
    AND o.mode = 'live'
    -- 賣家隔離條件
GROUP BY p.ID, p.post_title
ORDER BY total_sold DESC
LIMIT 10
```

**資料表**：
- 主表：`fct_order_items`
- 關聯表：`posts` (商品), `fct_orders` (訂單狀態)

---

### 4.4 訂單狀態分佈

**需求**：顯示各狀態訂單的數量（圓餅圖或長條圖）。

**查詢邏輯**：

```sql
SELECT
    status,
    COUNT(*) as count,
    SUM(total_amount) as total_amount
FROM {prefix}_fct_orders
WHERE
    mode = 'live'
    -- 賣家隔離條件
GROUP BY status
ORDER BY count DESC
```

---

### 4.5 客戶價值分析

**需求**：顯示高價值客戶（LTV 排行）。

**查詢邏輯**：

```sql
SELECT
    id,
    email,
    CONCAT(first_name, ' ', last_name) as full_name,
    ltv,
    purchase_count,
    aov
FROM {prefix}_fct_customers
WHERE
    status = 'active'
    -- 賣家隔離條件
ORDER BY ltv DESC
LIMIT 10
```

**資料表**：
- 主表：`fct_customers`
- 關鍵欄位：`ltv` (終身價值), `purchase_count`, `aov`

---

## 五、賣家隔離策略

**核心問題**：BuyGo Plus One 是多賣家平台，每個賣家只能看到自己的資料。

### 5.1 隔離層級

FluentCart 本身是單一賣家系統，BuyGo Plus One 需要自行實作權限隔離。

| 層級 | 實作方式 | 說明 |
|------|---------|------|
| **WordPress User Level** | 透過 `post_author` (商品) | 商品由誰建立 |
| **Customer Level** | 透過 `customer_id` → `user_id` | 訂單屬於哪個客戶的 WP User |
| **Order Level** | 透過 `customer_id` 反查 | 訂單 → 客戶 → WP User |

---

### 5.2 推薦隔離方案

#### 方案 A：透過 Customer 的 `user_id` 隔離

**邏輯**：
1. 每個賣家擁有一個 WordPress User ID
2. FluentCart 的 Customer 表有 `user_id` 欄位關聯 WP User
3. 訂單透過 `customer_id` → Customer 的 `user_id` 判斷所屬賣家

**查詢範例**（取得當前賣家的訂單）：

```sql
SELECT o.*
FROM {prefix}_fct_orders o
JOIN {prefix}_fct_customers c ON o.customer_id = c.id
WHERE c.user_id = {current_wp_user_id}
```

**優點**：
- 利用 FluentCart 現有欄位
- 無需修改資料表結構

**缺點**：
- 需多一層 JOIN
- Customer 的 `user_id` 可能為空（訪客訂單）

---

#### 方案 B：新增 `seller_id` 欄位到核心表

**邏輯**：
1. 在 `fct_orders`, `posts` (商品) 表新增 `seller_id` 欄位
2. 儲存賣家的 WordPress User ID
3. 直接透過 `seller_id` 篩選

**修改方案**：

```sql
-- 新增 seller_id 到訂單表
ALTER TABLE {prefix}_fct_orders
ADD COLUMN seller_id BIGINT UNSIGNED NULL,
ADD INDEX idx_seller_id (seller_id);

-- 新增 seller_id 到商品表（使用 post_meta）
-- WordPress posts 表不建議直接修改，使用 postmeta 儲存
```

**查詢範例**：

```sql
SELECT * FROM {prefix}_fct_orders
WHERE seller_id = {current_wp_user_id}
```

**優點**：
- 查詢效能最佳（單表查詢）
- 邏輯清晰

**缺點**：
- 需修改 FluentCart 核心表
- 可能影響外掛更新

---

#### 方案 C：使用 WordPress User Roles + Meta

**邏輯**：
1. 為每個賣家建立 WordPress User (Role: `seller`)
2. 使用 `user_meta` 儲存賣家與 Customer 的對應關係
3. 查詢時透過 meta 表 JOIN

**實作**：

```php
// 將 Customer 關聯到賣家
update_user_meta($seller_user_id, 'buygo_customers', [$customer_id_1, $customer_id_2]);

// 查詢時
$customer_ids = get_user_meta($seller_user_id, 'buygo_customers', true);
// WHERE customer_id IN ($customer_ids)
```

**優點**：
- 不修改 FluentCart 表結構
- 符合 WordPress 慣例

**缺點**：
- 需額外維護 meta 資料
- 查詢複雜度較高

---

### 5.3 推薦方案

**建議採用「方案 A + WordPress Capabilities」混合策略**：

1. **基礎隔離**：透過 Customer 的 `user_id` 篩選訂單
2. **商品隔離**：利用 WordPress 原生 `post_author` 欄位（商品作者）
3. **權限管理**：使用 WordPress Capabilities 定義賣家權限

**實作範例**（PHP Service Layer）：

```php
class DashboardStatsService {

    public function getSellerOrders($seller_user_id, $date_range = 'today') {
        global $wpdb;

        $date_condition = $this->getDateCondition($date_range);

        $sql = "
            SELECT o.*
            FROM {$wpdb->prefix}fct_orders o
            JOIN {$wpdb->prefix}fct_customers c ON o.customer_id = c.id
            WHERE c.user_id = %d
            AND o.mode = 'live'
            AND {$date_condition}
        ";

        return $wpdb->get_results($wpdb->prepare($sql, $seller_user_id));
    }

    public function getSellerRevenue($seller_user_id, $date_range = 'today') {
        global $wpdb;

        $date_condition = $this->getDateCondition($date_range);

        $sql = "
            SELECT
                SUM(o.total_amount) as total_revenue,
                COUNT(o.id) as order_count
            FROM {$wpdb->prefix}fct_orders o
            JOIN {$wpdb->prefix}fct_customers c ON o.customer_id = c.id
            WHERE c.user_id = %d
            AND o.status = 'completed'
            AND o.payment_status = 'paid'
            AND o.mode = 'live'
            AND {$date_condition}
        ";

        return $wpdb->get_row($wpdb->prepare($sql, $seller_user_id));
    }
}
```

---

## 六、效能優化建議

### 6.1 必要索引

確保以下索引存在（FluentCart 預設已建立大部分）：

```sql
-- Orders 表
ALTER TABLE {prefix}_fct_orders
ADD INDEX idx_customer_status (customer_id, status),
ADD INDEX idx_created_status (created_at, status),
ADD INDEX idx_payment_status (payment_status);

-- Customers 表
ALTER TABLE {prefix}_fct_customers
ADD INDEX idx_user_id (user_id);

-- Order Items 表
ALTER TABLE {prefix}_fct_order_items
ADD INDEX idx_order_post (order_id, post_id);
```

---

### 6.2 快取策略

Dashboard 統計資料變動頻率低，建議使用 WordPress Transients API：

```php
public function getCachedStats($seller_user_id, $date_range = 'today') {
    $cache_key = "buygo_stats_{$seller_user_id}_{$date_range}";

    $stats = get_transient($cache_key);

    if (false === $stats) {
        $stats = $this->calculateStats($seller_user_id, $date_range);
        // 快取 5 分鐘
        set_transient($cache_key, $stats, 300);
    }

    return $stats;
}
```

**快取時效建議**：
- 即時統計（今日訂單）：5 分鐘
- 歷史統計（上週、上月）：1 小時
- 總計資料（全部客戶）：24 小時

---

### 6.3 查詢優化技巧

#### 避免 N+1 查詢

```php
// ❌ 錯誤：每個訂單都查一次客戶
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->customer->name; // N+1 query
}

// ✅ 正確：預載入關聯
$orders = Order::with('customer')->get();
foreach ($orders as $order) {
    echo $order->customer->name; // No additional query
}
```

#### 只查詢必要欄位

```php
// ❌ 錯誤：查詢所有欄位
$orders = Order::all();

// ✅ 正確：只查詢需要的欄位
$orders = Order::select(['id', 'customer_id', 'total_amount', 'created_at'])->get();
```

#### 使用 Database Views（進階）

為複雜統計建立 MySQL View：

```sql
CREATE VIEW v_seller_daily_stats AS
SELECT
    c.user_id as seller_id,
    DATE(o.created_at) as date,
    COUNT(o.id) as order_count,
    SUM(o.total_amount) as revenue
FROM {prefix}_fct_orders o
JOIN {prefix}_fct_customers c ON o.customer_id = c.id
WHERE o.mode = 'live' AND o.status = 'completed'
GROUP BY c.user_id, DATE(o.created_at);
```

查詢時直接使用 View：

```php
$wpdb->get_results("SELECT * FROM v_seller_daily_stats WHERE seller_id = $user_id");
```

---

## 七、FluentCart REST API 整合

FluentCart 提供現成的 REST API，可直接使用或參考實作。

### 7.1 可用端點

| 端點 | 方法 | 說明 | Dashboard 用途 |
|------|------|------|----------------|
| `/dashboard/stats` | GET | 儀表板統計 | 總覽卡片 |
| `/reports/overview` | GET | 報表總覽 | 營收分析 |
| `/reports/quick-order-stats` | GET | 快速訂單統計 | 訂單趨勢 |
| `/orders` | GET | 訂單列表 | 訂單管理 |
| `/customers` | GET | 客戶列表 | 客戶管理 |
| `/products` | GET | 商品列表 | 商品統計 |

---

### 7.2 Dashboard Stats API 範例

**請求**：
```http
GET /wp-json/fluent-cart/v2/dashboard/stats
Authorization: Basic {base64(username:app_password)}
```

**回應結構**：
```json
{
  "stats": [
    {
      "title": "Total Products",
      "current_count": "54",
      "icon": "Frame",
      "url": "https://example.com/wp-admin/admin.php?page=fluent-cart#/products",
      "has_currency": true
    }
  ]
}
```

---

### 7.3 Reports Overview API 範例

**請求**：
```http
GET /wp-json/fluent-cart/v2/reports/overview
Authorization: Basic {base64(username:app_password)}
```

**回應結構**：
```json
{
  "data": {
    "gross_revenue": {
      "value": 125000,
      "formatted": "$1,250.00"
    },
    "net_revenue": { },
    "order_count": { },
    "average_order_value": { }
  }
}
```

---

### 7.4 整合建議

**BuyGo Plus One 可以**：

1. **參考 API 實作** - 學習 FluentCart 的統計邏輯（但加上賣家隔離）
2. **直接呼叫 API** - 前端透過 AJAX 呼叫 FluentCart API（需修改權限檢查）
3. **自建 Service Layer** - 在 PHP 後端建立自己的統計服務（推薦）

**推薦方案**：自建 Service Layer

**原因**：
- FluentCart API 缺少賣家隔離機制
- 需要客製化統計邏輯（例如多賣家排行）
- 避免依賴 FluentCart 版本更新

---

## 八、實作建議

### 8.1 建立 Dashboard Service 類別

**檔案位置**：`/includes/services/class-dashboard-service.php`

**職責**：
- 封裝所有 Dashboard 資料查詢邏輯
- 處理賣家權限隔離
- 提供快取機制
- 格式化資料（金額從 cents 轉換）

**範例架構**：

```php
<?php
namespace BuyGoPlusOne\Services;

class DashboardService {

    private $wpdb;
    private $table_orders;
    private $table_customers;
    private $table_order_items;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_orders = $wpdb->prefix . 'fct_orders';
        $this->table_customers = $wpdb->prefix . 'fct_customers';
        $this->table_order_items = $wpdb->prefix . 'fct_order_items';
    }

    /**
     * 取得賣家的訂單統計
     */
    public function getOrderStats($seller_user_id, $date_range = 'today') {
        $cache_key = "buygo_order_stats_{$seller_user_id}_{$date_range}";
        $stats = get_transient($cache_key);

        if (false === $stats) {
            $stats = $this->calculateOrderStats($seller_user_id, $date_range);
            set_transient($cache_key, $stats, 300); // 5分鐘
        }

        return $stats;
    }

    /**
     * 計算訂單統計（實際查詢）
     */
    private function calculateOrderStats($seller_user_id, $date_range) {
        $date_condition = $this->getDateCondition($date_range);

        $sql = $this->wpdb->prepare("
            SELECT
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_revenue,
                AVG(o.total_amount) as avg_order_value
            FROM {$this->table_orders} o
            JOIN {$this->table_customers} c ON o.customer_id = c.id
            WHERE c.user_id = %d
            AND o.status = 'completed'
            AND o.payment_status = 'paid'
            AND o.mode = 'live'
            AND {$date_condition}
        ", $seller_user_id);

        $result = $this->wpdb->get_row($sql);

        return [
            'order_count' => (int) $result->order_count,
            'total_revenue' => $this->formatAmount($result->total_revenue),
            'avg_order_value' => $this->formatAmount($result->avg_order_value),
        ];
    }

    /**
     * 取得營收趨勢（時間序列）
     */
    public function getRevenueTrend($seller_user_id, $days = 7) {
        $sql = $this->wpdb->prepare("
            SELECT
                DATE(o.created_at) as date,
                SUM(o.total_amount) as revenue,
                COUNT(o.id) as order_count
            FROM {$this->table_orders} o
            JOIN {$this->table_customers} c ON o.customer_id = c.id
            WHERE c.user_id = %d
            AND o.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND o.status = 'completed'
            AND o.mode = 'live'
            GROUP BY DATE(o.created_at)
            ORDER BY date ASC
        ", $seller_user_id, $days);

        $results = $this->wpdb->get_results($sql);

        return array_map(function($row) {
            return [
                'date' => $row->date,
                'revenue' => $this->formatAmount($row->revenue),
                'order_count' => (int) $row->order_count,
            ];
        }, $results);
    }

    /**
     * 取得商品銷售排行
     */
    public function getTopProducts($seller_user_id, $limit = 10) {
        $sql = $this->wpdb->prepare("
            SELECT
                p.ID,
                p.post_title,
                SUM(oi.quantity) as total_sold,
                SUM(oi.line_total) as total_revenue
            FROM {$this->table_order_items} oi
            JOIN {$this->wpdb->posts} p ON oi.post_id = p.ID
            JOIN {$this->table_orders} o ON oi.order_id = o.id
            JOIN {$this->table_customers} c ON o.customer_id = c.id
            WHERE c.user_id = %d
            AND o.status = 'completed'
            AND o.mode = 'live'
            AND p.post_author = %d
            GROUP BY p.ID, p.post_title
            ORDER BY total_sold DESC
            LIMIT %d
        ", $seller_user_id, $seller_user_id, $limit);

        $results = $this->wpdb->get_results($sql);

        return array_map(function($row) {
            return [
                'product_id' => (int) $row->ID,
                'product_title' => $row->post_title,
                'total_sold' => (int) $row->total_sold,
                'total_revenue' => $this->formatAmount($row->total_revenue),
            ];
        }, $results);
    }

    // === Helper Methods ===

    private function getDateCondition($range) {
        switch ($range) {
            case 'today':
                return "DATE(o.created_at) = CURDATE()";
            case 'this_week':
                return "YEARWEEK(o.created_at) = YEARWEEK(NOW())";
            case 'this_month':
                return "MONTH(o.created_at) = MONTH(NOW()) AND YEAR(o.created_at) = YEAR(NOW())";
            default:
                return "1=1";
        }
    }

    private function formatAmount($cents) {
        return [
            'cents' => (int) $cents,
            'dollars' => round($cents / 100, 2),
            'formatted' => '$' . number_format($cents / 100, 2),
        ];
    }
}
```

---

### 8.2 建立 REST API 端點

**檔案位置**：`/includes/api/class-dashboard-api.php`

```php
<?php
namespace BuyGoPlusOne\API;

use WP_REST_Controller;
use WP_REST_Request;
use BuyGoPlusOne\Services\DashboardService;

class DashboardAPI extends WP_REST_Controller {

    private $dashboard_service;

    public function __construct() {
        $this->namespace = 'buygo-plus-one/v1';
        $this->rest_base = 'dashboard';
        $this->dashboard_service = new DashboardService();
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/stats', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_stats'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => [
                    'date_range' => [
                        'default' => 'today',
                        'enum' => ['today', 'this_week', 'this_month', 'all_time'],
                    ],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/revenue-trend', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_revenue_trend'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => [
                    'days' => [
                        'default' => 7,
                        'type' => 'integer',
                    ],
                ],
            ],
        ]);
    }

    public function get_stats(WP_REST_Request $request) {
        $seller_id = get_current_user_id();
        $date_range = $request->get_param('date_range');

        $stats = $this->dashboard_service->getOrderStats($seller_id, $date_range);

        return rest_ensure_response([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function get_revenue_trend(WP_REST_Request $request) {
        $seller_id = get_current_user_id();
        $days = $request->get_param('days');

        $trend = $this->dashboard_service->getRevenueTrend($seller_id, $days);

        return rest_ensure_response([
            'success' => true,
            'data' => $trend,
        ]);
    }

    public function check_permissions() {
        return current_user_can('buygo_seller') || current_user_can('administrator');
    }
}
```

---

### 8.3 Vue 前端整合

**檔案位置**：`/components/dashboard/DashboardStats.vue`

```vue
<template>
  <div class="dashboard-stats">
    <div class="stats-cards">
      <StatCard
        title="今日訂單"
        :value="stats.order_count"
        icon="shopping-cart"
      />
      <StatCard
        title="今日營收"
        :value="stats.total_revenue.formatted"
        icon="dollar-sign"
      />
    </div>

    <RevenueTrendChart :data="revenueTrend" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const stats = ref({});
const revenueTrend = ref([]);

onMounted(async () => {
  // 取得統計資料
  const statsRes = await axios.get('/wp-json/buygo-plus-one/v1/dashboard/stats', {
    params: { date_range: 'today' }
  });
  stats.value = statsRes.data.data;

  // 取得營收趨勢
  const trendRes = await axios.get('/wp-json/buygo-plus-one/v1/dashboard/revenue-trend', {
    params: { days: 7 }
  });
  revenueTrend.value = trendRes.data.data;
});
</script>
```

---

## 九、開放問題與建議

### 9.1 未確認項目

| 項目 | 現況 | 建議 |
|------|------|------|
| **FluentCart 多站台支援** | 未確認是否支援 WordPress Multisite | 需測試 Multisite 環境 |
| **自訂欄位擴充性** | Meta 表設計是否滿足需求 | 需評估是否新增自訂表 |
| **大數據效能** | 未知百萬級訂單查詢效能 | 需建立效能測試基準 |

---

### 9.2 下一步行動

1. **建立 Dashboard Service 原型** - 驗證查詢邏輯可行性
2. **效能測試** - 使用假資料測試大量訂單查詢效能
3. **賣家隔離驗證** - 確認 `user_id` 關聯的完整性
4. **API 設計** - 定義完整的 Dashboard REST API 規格
5. **前端 Mock 資料** - 建立假資料供 Vue 元件開發

---

## 十、參考資源

### 10.1 FluentCart 官方文件

- **Database Schema**: https://dev.fluentcart.com/database/schema.html
- **Model Relationships**: https://dev.fluentcart.com/database/models/relationships.html
- **REST API Documentation**: https://dev.fluentcart.com/restapi/

### 10.2 本地參考文件

- FluentCart Orders API: `/老魚資料庫/05_外掛開發文件/FluentCart Orders API.md`
- FluentCart Products API: `/老魚資料庫/05_外掛開發文件/FluentCart Products API.md`
- FluentCart Customers API: `/老魚資料庫/05_外掛開發文件/FluentCart Customers API.md`
- FluentCart Database Models: `/老魚資料庫/05_外掛開發文件/fluentcart.com_doc/database_models_*.md`

---

**文件版本**：1.0
**撰寫者**：Claude (Sonnet 4.5)
**最後更新**：2026-01-29
