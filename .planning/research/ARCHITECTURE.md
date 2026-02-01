# Architecture Research: WordPress 出貨通知系統

**Domain:** WordPress E-commerce Plugin Shipment Notification System
**Researched:** 2026-02-02
**Confidence:** HIGH

## 系統概述

### 整體架構圖

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Presentation Layer                           │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐   │
│  │ Settings Page    │  │ Admin Dashboard  │  │ Shipment List    │   │
│  │ (Template Editor)│  │                  │  │                  │   │
│  └────────┬─────────┘  └──────────────────┘  └────────┬─────────┘   │
│           │                                             │             │
├───────────┴─────────────────────────────────────────────┴─────────────┤
│                          Service Layer                                │
│  ┌──────────────┐  ┌──────────────────┐  ┌──────────────────────┐   │
│  │ ShipmentSvc  │  │ NotificationSvc  │  │ TemplateManager      │   │
│  │              │  │                  │  │ (NotificationTemplates)│  │
│  └──────┬───────┘  └─────────┬────────┘  └──────────────────────┘   │
│         │                    │                                        │
├─────────┴────────────────────┴────────────────────────────────────────┤
│                        Integration Layer                              │
│  ┌───────────────────────────────────────────────────────────────┐   │
│  │           WordPress Action Hooks (do_action)                  │   │
│  │  'buygo/shipment/marked_as_shipped'                           │   │
│  └─────────────────────────┬─────────────────────────────────────┘   │
│                            │                                          │
│  ┌─────────────────────────┴─────────────────────────────────────┐   │
│  │           Cross-Plugin Communication Bridge                   │   │
│  │  NotificationService → buygo-line-notify                      │   │
│  └───────────────────────────────────────────────────────────────┘   │
├─────────────────────────────────────────────────────────────────────┤
│                           Data Layer                                 │
│  ┌──────────────────────┐  ┌──────────────────────────────────┐     │
│  │ buygo_shipments      │  │ wp_options                       │     │
│  │ - estimated_delivery │  │ - buygo_notification_templates   │     │
│  └──────────────────────┘  └──────────────────────────────────┘     │
└─────────────────────────────────────────────────────────────────────┘
```

### 元件職責

| Component | Responsibility | Typical Implementation |
|-----------|----------------|------------------------|
| **ShipmentService** | 出貨單生命週期管理，包含建立、查詢、標記出貨 | PHP Class with database operations |
| **NotificationHandler** | 監聽出貨事件，觸發通知流程 | WordPress Action Hook Listener |
| **NotificationService** | 跨外掛通訊橋接，整合 buygo-line-notify | Static Class with soft dependency check |
| **NotificationTemplates** | 模板管理（CRUD），變數替換，快取機制 | Static Class with wp_options storage |
| **SettingsPage** | 後台設定頁面，模板編輯 UI | Vue 3 SPA + REST API |
| **Database Upgrader** | 版本化資料庫升級（新增欄位） | dbDelta + version check pattern |

## 推薦的專案結構

```
buygo-plus-one/
├── includes/
│   ├── services/
│   │   ├── class-shipment-service.php          # 現有：出貨單管理
│   │   ├── class-notification-service.php      # 現有：跨外掛通訊
│   │   ├── class-notification-templates.php    # 現有：模板管理
│   │   └── class-notification-handler.php      # ⭐️ 新增：事件監聽器
│   ├── admin/
│   │   └── class-settings-page.php             # 擴充：新增模板編輯區塊
│   ├── class-database.php                      # 擴充：新增升級方法
│   └── class-plugin.php                        # 擴充：註冊新 Hook
└── includes/views/
    └── settings/
        └── NotificationTemplates.vue            # ⭐️ 新增：模板管理 UI
```

### 結構理由

- **services/class-notification-handler.php**: 新增獨立的事件監聽器，遵循單一職責原則（SRP），與 ShipmentService 解耦
- **NotificationTemplates.vue**: 前端模板管理 UI，與現有 Settings 頁面整合
- **Database Upgrade**: 使用 WordPress 標準 dbDelta + version check 模式，確保向下相容

## 架構模式

### Pattern 1: WordPress Action Hook Event-Driven Architecture

**What:** 使用 WordPress Action Hook 系統實現事件驅動架構，業務邏輯透過 `do_action()` 觸發事件，監聽器透過 `add_action()` 訂閱事件。

**When to use:** 當需要解耦元件、允許第三方擴展、或在系統生命週期特定點觸發副作用時使用。

**Trade-offs:**
- ✅ **Pros:** 鬆耦合、可擴展性高、符合 WordPress 生態慣例
- ❌ **Cons:** 除錯較困難（事件流不明顯）、效能有輕微開銷

**Example:**
```php
// ShipmentService::mark_shipped() 中觸發事件
public function mark_shipped($shipment_ids) {
    // ... 更新出貨狀態邏輯 ...

    foreach ($shipment_ids as $shipment_id) {
        // 觸發出貨事件
        do_action('buygo/shipment/marked_as_shipped', $shipment_id);
    }
}

// NotificationHandler 監聽事件
class NotificationHandler {
    public function register_hooks() {
        add_action('buygo/shipment/marked_as_shipped', [$this, 'send_shipment_notification'], 10, 1);
    }

    public function send_shipment_notification($shipment_id) {
        // 取得出貨單資料
        $shipment = ShipmentService::get_shipment($shipment_id);

        // 發送通知給買家
        NotificationService::send($shipment->customer_id, 'order_shipped', [
            'order_id' => $shipment->order_id,
            'tracking_number' => $shipment->tracking_number
        ]);
    }
}
```

### Pattern 2: Soft Dependency Cross-Plugin Communication

**What:** 外掛間透過 WordPress Hook 系統通訊，使用 `class_exists()` 檢查依賴是否存在，優雅降級（graceful degradation）。

**When to use:** 當功能依賴其他外掛，但不希望強制要求該外掛必須啟用時使用。

**Trade-offs:**
- ✅ **Pros:** 鬆耦合、獨立安裝、功能可選
- ❌ **Cons:** 需要處理依賴缺失情況、測試複雜度增加

**Example:**
```php
class NotificationService {
    public static function isLineNotifyAvailable(): bool {
        return class_exists('\\BuygoLineNotify\\Services\\MessagingService');
    }

    public static function send(int $user_id, string $template_key, array $args = []): bool {
        // Soft dependency check
        if (!self::isLineNotifyAvailable()) {
            DebugService::log('NotificationService', 'buygo-line-notify 未啟用', [], 'warning');
            return false; // 優雅降級
        }

        // 呼叫外部外掛 API
        $message = NotificationTemplates::get($template_key, $args);
        return \BuygoLineNotify\Services\MessagingService::pushText($user_id, $message['line']['text']);
    }
}
```

### Pattern 3: Database Schema Versioning with dbDelta

**What:** 使用 WordPress 內建的 `dbDelta()` 函式搭配版本號管理資料庫升級，避免重複執行升級邏輯。

**When to use:** 當外掛需要新增資料表欄位、索引或修改資料表結構時使用。

**Trade-offs:**
- ✅ **Pros:** WordPress 原生支援、自動偵測差異、不丟失資料
- ❌ **Cons:** dbDelta 語法嚴格（空格、大小寫敏感）、無法刪除欄位

**Example:**
```php
class Plugin {
    const DB_VERSION = '1.3.0'; // Milestone v1.3 版本

    public function init() {
        $this->maybe_upgrade_database();
    }

    private function maybe_upgrade_database() {
        $current_version = get_option('buygo_plus_one_db_version', '0.0.0');

        // 只在版本號改變時升級
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            Database::upgrade_tables();
            update_option('buygo_plus_one_db_version', self::DB_VERSION);
        }
    }
}

class Database {
    public static function upgrade_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'buygo_shipments';

        // 使用 CREATE TABLE 語法（dbDelta 會自動偵測差異）
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            shipment_number varchar(50) NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            seller_id bigint(20) UNSIGNED NOT NULL,
            status varchar(50) DEFAULT 'pending',
            estimated_delivery_at datetime DEFAULT NULL,
            tracking_number varchar(100),
            shipped_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_shipment_number (shipment_number),
            KEY idx_customer_id (customer_id),
            KEY idx_seller_id (seller_id),
            KEY idx_status (status)
        ) {$charset_collate};";

        dbDelta($sql); // dbDelta 自動比對並新增 estimated_delivery_at 欄位
    }
}
```

### Pattern 4: Template Management with wp_options + Caching

**What:** 使用 WordPress Options API 儲存模板，搭配多層快取（static cache + wp_cache）提升效能。

**When to use:** 當需要儲存小量結構化設定資料，且需要快速讀取時使用。

**Trade-offs:**
- ✅ **Pros:** 簡單、原生支援、自動序列化
- ❌ **Cons:** wp_options 表可能成為效能瓶頸（需快取）、儲存大量資料不適合

**Example:**
```php
class NotificationTemplates {
    private static $cached_custom_templates = null; // Static cache
    private static $cache_key = 'buygo_notification_templates_cache';
    private static $cache_group = 'buygo_notification_templates';

    private static function get_all_custom_templates() {
        // Layer 1: Static cache (fastest)
        if (self::$cached_custom_templates !== null) {
            return self::$cached_custom_templates;
        }

        // Layer 2: WordPress object cache (persistent)
        $cached = wp_cache_get(self::$cache_key, self::$cache_group);
        if ($cached !== false) {
            self::$cached_custom_templates = $cached;
            return $cached;
        }

        // Layer 3: Database (slowest)
        $templates = get_option('buygo_notification_templates', []);

        // 儲存到快取
        self::$cached_custom_templates = $templates;
        wp_cache_set(self::$cache_key, $templates, self::$cache_group, 3600); // 快取 1 小時

        return $templates;
    }

    public static function save_custom_templates($templates) {
        update_option('buygo_notification_templates', $templates);

        // 清除所有快取
        self::$cached_custom_templates = null;
        wp_cache_delete(self::$cache_key, self::$cache_group);
    }
}
```

## 資料流

### Request Flow: 出貨通知流程

```
[賣家操作]「標記為已出貨」
    ↓
[ShipmentService::mark_shipped()]
    ↓ (更新資料庫 status = 'shipped')
[do_action('buygo/shipment/marked_as_shipped', $shipment_id)]
    ↓
[NotificationHandler::send_shipment_notification()]
    ↓ (取得出貨單資料、買家資訊)
[NotificationService::send($customer_id, 'order_shipped', $args)]
    ↓ (檢查 buygo-line-notify 是否啟用)
[NotificationTemplates::get('order_shipped', $args)]
    ↓ (取得模板、替換變數)
[\BuygoLineNotify\Services\MessagingService::pushText()]
    ↓
[LINE Messaging API] → [買家收到通知]
```

### Data Flow: 模板管理流程

```
[Settings Page] → [POST /wp-json/buygo-plus-one/v1/templates]
    ↓
[NotificationTemplates::save_custom_templates($templates)]
    ↓
[update_option('buygo_notification_templates', $templates)]
    ↓
[NotificationTemplates::clear_cache()]
    ↓ (清除 static cache + wp_cache)
[200 OK] ← [Settings Page 顯示儲存成功]
```

### Database Upgrade Flow

```
[Plugin::init()] → [maybe_upgrade_database()]
    ↓
[get_option('buygo_plus_one_db_version')] → '1.2.0'
    ↓ (version_compare('1.2.0', '1.3.0') < 0)
[Database::upgrade_tables()]
    ↓
[dbDelta($sql)] → 自動新增 estimated_delivery_at 欄位
    ↓
[update_option('buygo_plus_one_db_version', '1.3.0')]
```

## 擴充點（Extension Points）

### 1. 自訂通知渠道

**Hook:** `apply_filters('buygo/notification/channels', $channels)`

**用途:** 允許第三方外掛新增通知渠道（例如：Email、SMS、Telegram）

**Example:**
```php
add_filter('buygo/notification/channels', function($channels) {
    $channels[] = 'telegram';
    return $channels;
});

add_action('buygo/notification/send_telegram', function($user_id, $message) {
    // 自訂 Telegram 通知邏輯
}, 10, 2);
```

### 2. 模板變數擴充

**Hook:** `apply_filters('buygo/notification/template_args', $args, $template_key)`

**用途:** 允許第三方外掛新增模板變數

**Example:**
```php
add_filter('buygo/notification/template_args', function($args, $template_key) {
    if ($template_key === 'order_shipped') {
        $args['estimated_delivery'] = '3-5 個工作天';
    }
    return $args;
}, 10, 2);
```

## 反模式（Anti-Patterns）

### Anti-Pattern 1: 直接在 Service 中硬編碼通知邏輯

**What people do:** 在 `ShipmentService::mark_shipped()` 中直接呼叫 `NotificationService::send()`

**Why it's wrong:**
- 違反單一職責原則（SRP）
- 無法擴展或替換通知邏輯
- 難以測試（需要 mock 外部依賴）

**Do this instead:** 使用 WordPress Action Hook 解耦

```php
// ❌ 錯誤示範
class ShipmentService {
    public function mark_shipped($shipment_id) {
        // ... 更新資料庫 ...

        // 直接呼叫（緊耦合）
        NotificationService::send($customer_id, 'order_shipped', []);
    }
}

// ✅ 正確示範
class ShipmentService {
    public function mark_shipped($shipment_id) {
        // ... 更新資料庫 ...

        // 觸發事件（鬆耦合）
        do_action('buygo/shipment/marked_as_shipped', $shipment_id);
    }
}

// 在 NotificationHandler 中監聽
add_action('buygo/shipment/marked_as_shipped', function($shipment_id) {
    $shipment = ShipmentService::get_shipment($shipment_id);
    NotificationService::send($shipment->customer_id, 'order_shipped', []);
});
```

### Anti-Pattern 2: 使用 ALTER TABLE 直接修改資料表

**What people do:** 直接執行 `ALTER TABLE ADD COLUMN` SQL 語句

**Why it's wrong:**
- dbDelta 無法追蹤手動修改
- 可能重複執行導致錯誤
- 不符合 WordPress 慣例

**Do this instead:** 使用 dbDelta + version check

```php
// ❌ 錯誤示範
$wpdb->query("ALTER TABLE {$table_name} ADD COLUMN estimated_delivery_at datetime");

// ✅ 正確示範
// 1. 更新 Plugin::DB_VERSION
const DB_VERSION = '1.3.0';

// 2. 使用 dbDelta（完整的 CREATE TABLE 語句）
$sql = "CREATE TABLE {$table_name} (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    estimated_delivery_at datetime DEFAULT NULL,
    -- ... 其他欄位 ...
    PRIMARY KEY (id)
) {$charset_collate};";

dbDelta($sql); // dbDelta 自動比對差異並新增欄位
```

### Anti-Pattern 3: 每次請求都從資料庫讀取模板

**What people do:** 在 `NotificationTemplates::get()` 中直接呼叫 `get_option()`

**Why it's wrong:**
- 資料庫查詢開銷大（每次通知都查詢一次）
- wp_options 表可能成為效能瓶頸
- 浪費資源

**Do this instead:** 使用多層快取

```php
// ❌ 錯誤示範
public static function get($key) {
    $templates = get_option('buygo_notification_templates', []); // 每次都查詢資料庫
    return $templates[$key] ?? null;
}

// ✅ 正確示範（見 Pattern 4）
private static function get_all_custom_templates() {
    // Layer 1: Static cache
    if (self::$cached_custom_templates !== null) {
        return self::$cached_custom_templates;
    }

    // Layer 2: WordPress object cache
    $cached = wp_cache_get(self::$cache_key, self::$cache_group);
    if ($cached !== false) {
        return $cached;
    }

    // Layer 3: Database
    $templates = get_option('buygo_notification_templates', []);

    // 儲存到快取
    self::$cached_custom_templates = $templates;
    wp_cache_set(self::$cache_key, $templates, self::$cache_group, 3600);

    return $templates;
}
```

## 擴展性考量（Scalability Considerations）

| Scale | Architecture Adjustments |
|-------|--------------------------|
| 0-1k users | 當前架構足夠（同步通知 + wp_cache 快取） |
| 1k-10k users | 考慮使用持久化 Object Cache（Redis/Memcached），wp_cache 預設不持久化 |
| 10k-100k users | 引入非同步通知（WordPress Cron 或 Action Scheduler），避免阻塞請求 |
| 100k+ users | 考慮解耦通知服務為獨立微服務（使用訊息佇列如 RabbitMQ/Redis Queue） |

### 擴展優先順序

1. **First bottleneck:** 資料庫查詢（wp_options 表）
   - **解決方案:** 啟用 Redis/Memcached Object Cache，確保 wp_cache 持久化

2. **Second bottleneck:** 同步通知阻塞請求
   - **解決方案:** 使用 Action Scheduler（WooCommerce 使用的背景任務處理器）改為非同步通知

3. **Third bottleneck:** LINE Messaging API 請求速率限制
   - **解決方案:** 實作通知排程，批次發送，避免超過 API 限制

## 整合點（Integration Points）

### 外部服務整合

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| **buygo-line-notify** | Soft dependency via `class_exists()` check | 透過 MessagingService::pushText() API 發送訊息 |
| **LINE Messaging API** | 由 buygo-line-notify 處理 | 無需直接整合，透過外掛封裝 |
| **WordPress Cron** | `wp_schedule_single_event()` for async notifications | 可用於延遲通知、排程通知 |

### 內部邊界

| Boundary | Communication | Notes |
|----------|---------------|-------|
| **ShipmentService ↔ NotificationHandler** | WordPress Action Hook (`do_action()`) | 鬆耦合，支援第三方擴展 |
| **NotificationService ↔ buygo-line-notify** | Direct PHP Class Call (with soft dependency check) | 檢查 class_exists() 確保外掛啟用 |
| **SettingsPage ↔ NotificationTemplates** | REST API (`/wp-json/buygo-plus-one/v1/templates`) | Vue 3 前端透過 REST API 操作模板 |
| **Database ↔ NotificationTemplates** | WordPress Options API (`get_option()`/`update_option()`) | 使用多層快取減少查詢 |

## 建議的建置順序

基於依賴關係和風險管理，建議以下建置順序：

### Phase 1: 資料庫升級（Foundation）
**Why first:** 其他功能都依賴新欄位存在
- 新增 `estimated_delivery_at` 欄位到 `buygo_shipments` 資料表
- 更新 `Plugin::DB_VERSION` 為 '1.3.0'
- 測試 dbDelta 升級流程

### Phase 2: 通知觸發器（Core Logic）
**Why second:** 核心業務邏輯，獨立於 UI
- 建立 `NotificationHandler` class
- 在 `ShipmentService::mark_shipped()` 中新增 `do_action()` Hook
- 註冊事件監聽器到 `Plugin::register_hooks()`
- 測試出貨通知流程

### Phase 3: 模板管理 UI（Frontend）
**Why last:** 依賴前兩階段完成，最低風險
- 新增 REST API 端點 (`/templates`)
- 建立 Vue 3 元件 `NotificationTemplates.vue`
- 整合到 Settings 頁面
- 測試模板編輯、儲存、預覽

## 關鍵設計決策

### 決策 1: 為什麼使用 WordPress Action Hook 而非直接呼叫？

**原因:**
1. **可擴展性:** 第三方外掛可以監聽相同事件，新增自訂邏輯（例如：寄送 Email、記錄 Analytics）
2. **可測試性:** 可以 mock Hook，單獨測試 ShipmentService 和 NotificationHandler
3. **符合 WordPress 生態:** WordPress 核心和主流外掛（WooCommerce、FluentCRM）都使用這種模式

**Trade-off:** 除錯較困難，需要使用 `add_action()` 追蹤事件流

### 決策 2: 為什麼使用 wp_options 而非自訂資料表？

**原因:**
1. **簡單性:** 模板數量不大（預計 < 50 個），wp_options 足夠
2. **原生支援:** 自動序列化/反序列化，不需要自訂 schema
3. **快取機制:** 搭配多層快取（static + wp_cache），效能足夠

**Trade-off:** 當模板數量 > 100 時，考慮遷移到自訂資料表

### 決策 3: 為什麼使用 dbDelta 而非手動 ALTER TABLE？

**原因:**
1. **WordPress 標準:** WordPress 核心推薦的資料庫升級方式
2. **向下相容:** dbDelta 會自動偵測差異，不會重複執行
3. **安全性:** 減少手動 SQL 錯誤風險

**Trade-off:** dbDelta 語法嚴格（空格、大小寫敏感），需要嚴格遵守格式

## Sources

**WordPress Official Documentation:**
- [dbDelta() Function Reference](https://developer.wordpress.org/reference/functions/dbdelta/)
- [Actions Hook Reference](https://developer.wordpress.org/plugins/hooks/actions/)
- [apply_filters() Function Reference](https://developer.wordpress.org/reference/functions/apply_filters/)

**Community Resources:**
- [Using dbDelta with WordPress to create and alter tables (Medium)](https://medium.com/enekochan/using-dbdelta-with-wordpress-to-create-and-alter-tables-73883f1db57)
- [How to Handling Database Migrations in WordPress Plugins (Voxfor)](https://www.voxfor.com/how-to-handling-database-migrations-in-wordpress-plugins/)
- [Building An Advanced Notification System For WordPress (Smashing Magazine)](https://www.smashingmagazine.com/2015/05/building-wordpress-notification-system/)
- [How to Use apply_filters() and do_action() to Create Extensible WordPress Plugins (WPShout)](https://wpshout.com/apply_filters-do_action/)

**E-commerce Architecture:**
- [WooCommerce Trends of 2026 (ZetaMatic)](https://zetamatic.com/blog/2025/12/woocommerce-trends-of-2026/)

**Current Project Analysis:**
- buygo-plus-one-dev/includes/services/class-shipment-service.php (現有實作)
- buygo-plus-one-dev/includes/services/class-notification-service.php (現有實作)
- buygo-plus-one-dev/includes/services/class-notification-templates.php (現有實作)
- buygo-plus-one-dev/includes/class-database.php (現有資料庫管理)

---
*Architecture research for: WordPress 出貨通知系統*
*Researched: 2026-02-02*
*Confidence: HIGH*
