# Phase 39: FluentCart 自動賦予賣家權限 - Research

**Researched:** 2026-02-04
**Domain:** WordPress FluentCart Integration / WordPress Role Management / Automated User Provisioning
**Confidence:** HIGH

## Summary

本次研究探討如何整合 FluentCart 訂單系統與 BuyGo 角色權限系統，實現「購買指定商品後自動賦予賣家權限」的自動化流程。研究範圍包含 FluentCart Hook 系統、WordPress 使用者角色管理、資料庫記錄機制、LINE/Email 通知整合、以及錯誤處理與重試策略。

核心發現：
- FluentCart 提供 `fluent_cart/order_created` 和 `fluent_cart/order_paid` 兩個關鍵 Hook，參數格式為陣列而非物件
- BuyGo 已有完整的 FluentCart 整合案例（`FluentCartOfflinePaymentUser`），可複用相同架構模式
- 現有的通知系統（`NotificationHandler` + `NotificationService`）可用於發送 LINE/Email 通知
- 資料庫已有多個 log 表（`wp_buygo_debug_logs`、`wp_buygo_notification_logs`、`wp_buygo_workflow_logs`），可複用或新增賣家賦予記錄表
- WordPress 內建的 `add_role()`、`update_user_meta()` API 足以處理角色和配額管理

**Primary recommendation:** 建立 `FluentCartSellerGrantIntegration` 整合類別，監聽 `fluent_cart/order_paid` hook（優先級 20），驗證商品 ID 和虛擬商品類型後，執行賦予流程（角色 + meta + 通知 + 記錄），並建立 `wp_buygo_seller_grants` 表記錄每次賦予歷史。

## Standard Stack

### Core Integration Technologies
| Technology | Version | Purpose | Why Standard |
|------------|---------|---------|--------------|
| WordPress Action Hooks | Core API | 監聽 FluentCart 訂單事件 | WordPress 標準擴展機制，所有外掛整合的基礎 |
| `fluent_cart/order_paid` | FluentCart Hook | 訂單付款完成事件 | FluentCart 官方提供，確保訂單已付款且不會取消 |
| WordPress Roles API | Core API | 賦予和管理使用者角色 | WordPress 核心權限系統，向後相容性最佳 |
| WordPress User Meta API | Core API | 儲存商品配額和賣家類型 | WordPress 標準使用者資料擴展方式 |
| `wp_mail()` | WordPress Core | 發送 Email 通知 | WordPress 核心郵件 API，支援 SMTP 外掛擴展 |

### Supporting Tools
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `NotificationService` | BuyGo Internal | 發送 LINE 通知 | 已綁定 LINE 的使用者優先使用 |
| `DebugService` | BuyGo Internal | 記錄詳細 debug log | 所有關鍵步驟都需記錄 |
| `IdentityService` | BuyGo Internal | 檢查使用者角色和 LINE 綁定 | 判斷通知管道和避免重複賦予 |
| `get_transient()` / `set_transient()` | WordPress Core | 實作去重機制（Idempotency） | 防止同一訂單重複處理 |
| `dbDelta()` | WordPress Core | 建立/更新資料表 | 建立 `wp_buygo_seller_grants` 記錄表 |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `order_paid` Hook | `order_created` Hook | `order_created` 在付款前觸發，可能有未付款訂單，需額外驗證 payment_status |
| WordPress Roles API | 直接修改資料庫 | 直接改 DB 繞過 WordPress 快取和 Hook 系統，容易出錯 |
| 新建 `seller_grants` 表 | 使用 `wp_buygo_workflow_logs` | workflow_logs 是通用流程表，語意不明確且查詢效率低 |
| Email 通知 | 只使用 LINE 通知 | 未綁定 LINE 的使用者無法收到通知 |

**Installation:**
```bash
# 無需額外安裝依賴，使用 WordPress 和 BuyGo 現有架構
```

## Architecture Patterns

### Pattern 1: FluentCart Hook 整合模式（推薦）
**What:** 建立獨立的整合類別監聽 FluentCart Hook，處理訂單事件並執行業務邏輯

**When to use:** 需要在 FluentCart 特定事件發生時執行自訂邏輯

**Example:**
```php
// Source: buygo-plus-one-dev/includes/integrations/class-fluentcart-offline-payment-user.php
namespace BuygoPlus\Integrations;

class FluentCartSellerGrantIntegration {
    const OFFLINE_PAYMENT_METHODS = ['offline_payment', 'cod', 'bank_transfer', 'cash'];

    public static function register_hooks(): void {
        // 監聽訂單付款完成事件（priority 20，晚於 FluentCart 原生處理）
        add_action('fluent_cart/order_paid', [__CLASS__, 'handle_order_paid'], 20);
    }

    /**
     * 處理訂單付款完成事件
     *
     * @param array $data FluentCart 事件資料陣列
     *                    包含 'order', 'prev_order', 'customer', 'transaction'
     */
    public static function handle_order_paid($data): void {
        // FluentCart 傳遞的是陣列，不是物件
        $order = $data['order'] ?? null;

        if (!$order) {
            error_log('[BuyGo+1][SellerGrant] Hook triggered but no order data');
            return;
        }

        // 檢查是否為指定的賣家商品
        if (!self::is_seller_product_order($order)) {
            return;
        }

        // 執行賦予流程
        self::grant_seller_access($order);
    }

    private static function is_seller_product_order($order): bool {
        // 取得設定的賣家商品 ID
        $seller_product_id = get_option('buygo_seller_product_id', '');
        if (empty($seller_product_id)) {
            return false;
        }

        // 檢查訂單中的商品
        global $wpdb;
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id FROM {$wpdb->prefix}fct_order_items WHERE order_id = %d",
            $order->id
        ));

        // 必須是單一商品訂單
        if (count($items) !== 1) {
            return false;
        }

        // 必須是指定的賣家商品
        return (string)$items[0]->product_id === (string)$seller_product_id;
    }

    private static function grant_seller_access($order): void {
        // 1. 取得 WordPress User ID
        // 2. 檢查是否已有權限（避免重複）
        // 3. 賦予 buygo_admin 角色
        // 4. 設定 user meta
        // 5. 發送通知
        // 6. 記錄到資料表
    }
}
```

### Pattern 2: 資料表記錄模式
**What:** 建立專用資料表記錄每次權限賦予，用於審計和除錯

**When to use:** 需要追蹤歷史記錄、分析賦予成功率、或提供管理後台查詢介面

**Example:**
```php
// Source: includes/class-database.php
private static function create_seller_grants_table($wpdb, $charset_collate): void {
    $table_name = $wpdb->prefix . 'buygo_seller_grants';

    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
        return;
    }

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id bigint(20) UNSIGNED NOT NULL,
        user_id bigint(20) UNSIGNED NOT NULL,
        product_id bigint(20) UNSIGNED NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'success',
        error_message text,
        granted_role varchar(50) DEFAULT 'buygo_admin',
        granted_quota int(11) DEFAULT 3,
        notification_sent tinyint(1) DEFAULT 0,
        notification_channel varchar(20),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_order (order_id),
        KEY idx_user_id (user_id),
        KEY idx_status (status),
        KEY idx_created_at (created_at)
    ) {$charset_collate};";

    dbDelta($sql);
}
```

### Pattern 3: 去重機制（Idempotency）
**What:** 使用 transient 或資料表記錄已處理的訂單，防止重複執行

**When to use:** Hook 可能被多次觸發，或同時監聽 `order_created` 和 `order_paid`

**Example:**
```php
// 方案 A: 使用 WordPress Transient（簡單但有時效限制）
private static function is_order_processed($order_id): bool {
    $transient_key = 'buygo_seller_grant_' . $order_id;
    return get_transient($transient_key) !== false;
}

private static function mark_order_processed($order_id): void {
    $transient_key = 'buygo_seller_grant_' . $order_id;
    set_transient($transient_key, time(), DAY_IN_SECONDS);
}

// 方案 B: 使用資料表 UNIQUE KEY（永久記錄）
private static function is_order_processed($order_id): bool {
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}buygo_seller_grants WHERE order_id = %d",
        $order_id
    ));
    return $exists > 0;
}

// 在 grant_seller_access() 開頭檢查
if (self::is_order_processed($order->id)) {
    error_log('[BuyGo+1][SellerGrant] Order already processed, skipping');
    return;
}
```

### Pattern 4: 通知管道判斷模式
**What:** 根據使用者 LINE 綁定狀態選擇通知管道（LINE 優先，fallback 到 Email）

**When to use:** 需要確保所有使用者都能收到通知

**Example:**
```php
// Source: includes/services/class-notification-handler.php
private static function send_seller_grant_notification($user_id, $product_limit = 3): void {
    $notification_channel = null;

    // 檢查是否有 LINE 綁定
    if (IdentityService::hasLineBinding($user_id)) {
        // 發送 LINE 通知
        $template_args = [
            'product_limit' => $product_limit,
            'dashboard_url' => home_url('/buygo-admin'),
            'line_official_url' => 'https://line.me/ti/p/@buygo'
        ];

        $result = NotificationService::sendText($user_id, 'seller_granted', $template_args);

        if ($result) {
            $notification_channel = 'line';
            error_log("[BuyGo+1][SellerGrant] LINE notification sent to user {$user_id}");
        }
    }

    // Fallback 到 Email（或 LINE 發送失敗時）
    if (!$notification_channel) {
        $user = get_userdata($user_id);
        if ($user && $user->user_email) {
            self::send_seller_grant_email($user);
            $notification_channel = 'email';
            error_log("[BuyGo+1][SellerGrant] Email notification sent to {$user->user_email}");
        }
    }

    return $notification_channel;
}
```

### Pattern 5: 商品驗證模式
**What:** 在後台設定頁面輸入商品 ID 時，即時查詢 FluentCart 資料庫驗證商品存在性

**When to use:** 避免儲存無效的商品 ID，提升使用者體驗

**Example:**
```php
// 在 Settings Page 的 AJAX handler
public function ajax_validate_seller_product(): void {
    check_ajax_referer('buygo-settings', 'nonce');

    $product_id = sanitize_text_field($_POST['product_id'] ?? '');

    if (empty($product_id)) {
        wp_send_json_error(['message' => '請輸入商品 ID']);
    }

    global $wpdb;
    $product = $wpdb->get_row($wpdb->prepare(
        "SELECT ID, post_title, post_status FROM {$wpdb->prefix}posts
         WHERE ID = %d AND post_type = 'fct_product'",
        $product_id
    ));

    if (!$product) {
        wp_send_json_error(['message' => '找不到此商品 ID']);
    }

    if ($product->post_status !== 'publish') {
        wp_send_json_error(['message' => "商品狀態為 {$product->post_status}，必須是 publish"]);
    }

    // 檢查是否為虛擬商品（不需要物流）
    $require_shipping = get_post_meta($product_id, '_require_shipping', true);

    if ($require_shipping === 'yes') {
        wp_send_json_error(['message' => '賣家商品必須是虛擬商品（不需要物流）']);
    }

    wp_send_json_success([
        'product' => [
            'id' => $product->ID,
            'title' => $product->post_title,
            'status' => $product->post_status,
            'admin_url' => admin_url('post.php?post=' . $product->ID . '&action=edit')
        ]
    ]);
}
```

### Anti-Patterns to Avoid
- **直接修改 `wp_users` 或 `wp_usermeta` 表**：繞過 WordPress 快取和 Hook 系統，導致資料不一致
- **在 Hook 中執行長時間操作**：FluentCart 訂單流程會被阻塞，應使用 `wp_schedule_single_event()` 異步處理
- **忽略錯誤處理**：賦予失敗時應記錄錯誤並通知管理員，不應靜默失敗
- **硬編碼通知訊息**：應使用模板系統（`NotificationTemplates`），方便未來多語言和自訂
- **不驗證商品類型**：實體商品需要物流，賣家商品應限制為虛擬商品

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| 使用者角色管理 | 直接改資料庫 `wp_usermeta` | `$user->add_role('buygo_admin')` | WordPress 會自動更新快取、觸發 Hook、記錄 log |
| 去重機制 | 自己寫檔案鎖或 Redis | `get_transient()` + 資料表 UNIQUE KEY | WordPress Transient 已處理快取層級，資料表 UNIQUE KEY 自動防止重複插入 |
| Email 發送 | 直接用 `mail()` 函式 | `wp_mail()` | 支援 SMTP 外掛、HTML 模板、過濾器擴展 |
| LINE 通知 | 直接呼叫 LINE API | `NotificationService::sendText()` | 已整合 LINE Messaging API，處理 token、錯誤重試、log 記錄 |
| 訂單查詢 | 自己寫 SQL JOIN | FluentCart Model API | FluentCart 提供 ORM，避免 SQL Injection |
| 資料表遷移 | 手動執行 ALTER TABLE | `dbDelta()` | WordPress 標準資料表遷移工具，自動比對差異 |

**Key insight:** WordPress 和 BuyGo 已提供完整的基礎設施，重新實作不僅浪費時間，還可能引入安全漏洞（SQL Injection、權限繞過）和相容性問題（快取失效、Hook 未觸發）。

## Common Pitfalls

### Pitfall 1: Hook 參數格式錯誤
**What goes wrong:** 錯誤地預期 `fluent_cart/order_paid` 傳遞 `$order` 物件，實際上是陣列

**Why it happens:** FluentCart Event 系統內部使用 Event 物件，但透過 WordPress Hook 傳遞時會呼叫 `toArray()` 轉換

**How to avoid:**
- 始終使用 `$data['order'] ?? null` 提取訂單物件
- 加入 null 檢查避免 Fatal Error
- 參考現有整合（`FluentCartOfflinePaymentUser`）的寫法

**Warning signs:**
- PHP Warning: `Trying to get property 'id' of non-object`
- Hook 被觸發但業務邏輯未執行
- `$order->id` 返回 null

**Example:**
```php
// ❌ 錯誤：直接預期物件
public static function handle_order_paid($order): void {
    $order_id = $order->id; // Fatal Error if $order is array
}

// ✅ 正確：提取陣列中的 order 鍵
public static function handle_order_paid($data): void {
    $order = $data['order'] ?? null;
    if (!$order) {
        error_log('[BuyGo] Hook triggered but no order data');
        return;
    }
    $order_id = $order->id; // Safe
}
```

### Pitfall 2: 未實作去重機制導致重複賦予
**What goes wrong:** 同一訂單觸發多次 Hook，使用者被重複賦予角色或收到多次通知

**Why it happens:**
- FluentCart 可能在訂單狀態變更時多次觸發 Hook
- 系統重試機制可能重複執行
- 手動測試時重複觸發

**How to avoid:**
- 在 `grant_seller_access()` 開頭檢查訂單是否已處理
- 使用資料表 UNIQUE KEY 防止重複插入
- 或使用 WordPress Transient 記錄短期狀態

**Warning signs:**
- `wp_buygo_seller_grants` 表中同一 order_id 出現多次（應被 UNIQUE KEY 阻擋）
- 使用者收到多次通知
- Debug log 顯示同一訂單被處理多次

**Example:**
```php
// ✅ 正確：在執行前檢查
private static function grant_seller_access($order): void {
    // 檢查是否已處理
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}buygo_seller_grants WHERE order_id = %d",
        $order->id
    ));

    if ($exists > 0) {
        error_log("[BuyGo] Order {$order->id} already processed, skipping");
        return;
    }

    // 繼續執行賦予流程...
}
```

### Pitfall 3: 商品驗證不完整
**What goes wrong:** 使用者購買「實體商品」或「包含多個商品的訂單」也被賦予賣家權限

**Why it happens:** 只檢查 product_id 是否匹配，未驗證商品類型和訂單項目數量

**How to avoid:**
- 檢查訂單項目數量必須為 1（單一商品訂單）
- 檢查商品 meta `_require_shipping` 必須為 'no'（虛擬商品）
- 在後台設定時就先驗證商品類型

**Warning signs:**
- 購買多個商品的訂單也觸發賦予
- 實體商品訂單觸發賦予（應該被排除）
- 管理員收到「商品驗證失敗」通知

**Example:**
```php
// ✅ 正確：完整驗證
private static function is_seller_product_order($order): bool {
    $seller_product_id = get_option('buygo_seller_product_id', '');
    if (empty($seller_product_id)) {
        return false;
    }

    global $wpdb;
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT product_id FROM {$wpdb->prefix}fct_order_items WHERE order_id = %d",
        $order->id
    ));

    // 必須是單一商品訂單
    if (count($items) !== 1) {
        error_log("[BuyGo] Order {$order->id} has " . count($items) . " items, must be 1");
        return false;
    }

    // 必須是指定的賣家商品
    if ((string)$items[0]->product_id !== (string)$seller_product_id) {
        return false;
    }

    // 必須是虛擬商品
    $require_shipping = get_post_meta($seller_product_id, '_require_shipping', true);
    if ($require_shipping === 'yes') {
        error_log("[BuyGo] Product {$seller_product_id} requires shipping, must be virtual");
        return false;
    }

    return true;
}
```

### Pitfall 4: 重複購買處理邏輯錯誤
**What goes wrong:** 已經是賣家的使用者再次購買賣家商品時，配額被覆蓋為 3（而非累加或保持原值）

**Why it happens:** User Decision 明確要求「重複購買時跳過所有操作」，但實作時可能誤判條件

**How to avoid:**
- 在賦予流程最開頭檢查使用者是否已有 `buygo_admin` 角色
- 如果已有角色，記錄 log 並直接返回，不更新任何 meta
- 在資料表記錄狀態為 'skipped'（而非 'success' 或 'failed'）

**Warning signs:**
- 已經是賣家的使用者配額被重設為 3
- `wp_buygo_seller_grants` 表中同一使用者出現多次成功記錄
- 賣家反應配額異常減少

**Example:**
```php
// ✅ 正確：檢查現有角色
private static function grant_seller_access($order): void {
    global $wpdb;

    // 取得 WordPress User ID
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->prefix}fct_customers WHERE id = %d",
        $order->customer_id
    ));

    if (!$customer || !$customer->user_id) {
        error_log("[BuyGo] Order {$order->id} customer not linked to WordPress user");
        return;
    }

    $user_id = $customer->user_id;

    // 檢查是否已是賣家
    if (IdentityService::isSeller($user_id)) {
        error_log("[BuyGo] User {$user_id} already has seller role, skipping");

        // 記錄到資料表（狀態為 skipped）
        self::log_grant_attempt($order->id, $user_id, 'skipped', 'User already has seller role');
        return;
    }

    // 繼續執行賦予流程...
}
```

### Pitfall 5: 退款處理未實作
**What goes wrong:** 使用者購買賣家商品後獲得權限，但退款後權限未被撤銷

**Why it happens:** User Decision 要求監聽退款 Hook 並移除權限，但忘記實作

**How to avoid:**
- 監聽 FluentCart 退款相關 Hook（需要查詢 FluentCart 原始碼確認 Hook 名稱）
- 在退款 Handler 中檢查是否為賣家商品訂單
- 移除 `buygo_admin` 角色和相關 user meta
- 記錄退款撤銷操作到 `wp_buygo_seller_grants` 表

**Warning signs:**
- 退款後使用者仍可存取 BuyGo 後台
- `wp_buygo_seller_grants` 表中沒有退款撤銷記錄
- 賣家數量異常增長（應與銷售數量減退款數量一致）

**Example:**
```php
// 需要先研究 FluentCart 退款 Hook（暫時使用假設名稱）
public static function register_hooks(): void {
    add_action('fluent_cart/order_paid', [__CLASS__, 'handle_order_paid'], 20);

    // 監聽退款事件
    add_action('fluent_cart/order_refunded', [__CLASS__, 'handle_order_refunded'], 20);
}

public static function handle_order_refunded($data): void {
    $order = $data['order'] ?? null;
    if (!$order) {
        return;
    }

    // 檢查是否為賣家商品訂單
    if (!self::is_seller_product_order($order)) {
        return;
    }

    // 撤銷賣家權限
    self::revoke_seller_access($order);
}

private static function revoke_seller_access($order): void {
    global $wpdb;

    // 取得使用者
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->prefix}fct_customers WHERE id = %d",
        $order->customer_id
    ));

    if (!$customer || !$customer->user_id) {
        return;
    }

    $user = get_userdata($customer->user_id);
    if (!$user) {
        return;
    }

    // 移除角色
    $user->remove_role('buygo_admin');

    // 刪除 user meta
    delete_user_meta($customer->user_id, 'buygo_product_limit');
    delete_user_meta($customer->user_id, 'buygo_seller_type');

    // 記錄撤銷操作
    $wpdb->insert(
        $wpdb->prefix . 'buygo_seller_grants',
        [
            'order_id' => $order->id,
            'user_id' => $customer->user_id,
            'product_id' => self::get_seller_product_id(),
            'status' => 'revoked',
            'error_message' => 'Seller access revoked due to refund'
        ],
        ['%d', '%d', '%d', '%s', '%s']
    );

    error_log("[BuyGo] Seller access revoked for user {$customer->user_id} due to refund");
}
```

## Code Examples

Verified patterns from existing codebase:

### FluentCart Hook 整合（完整範例）
```php
// Source: includes/integrations/class-fluentcart-offline-payment-user.php
namespace BuygoPlus\Integrations;

class FluentCartOfflinePaymentUser {
    const OFFLINE_PAYMENT_METHODS = ['offline_payment', 'cod', 'bank_transfer', 'cash'];

    public static function register_hooks(): void {
        add_action('fluent_cart/order_created', [__CLASS__, 'handle_order_created'], 20);
    }

    public static function handle_order_created($data): void {
        $order = $data['order'] ?? null;

        if (!$order) {
            error_log('[BuyGo+1][OfflinePayment] Hook triggered but no order data');
            return;
        }

        if (!self::is_offline_payment($order)) {
            return;
        }

        if (!self::should_create_user($order)) {
            return;
        }

        self::create_user_from_order($order);
    }

    private static function is_offline_payment($order): bool {
        $payment_method = $order->payment_method ?? '';
        return in_array($payment_method, self::OFFLINE_PAYMENT_METHODS, true);
    }

    private static function should_create_user($order): bool {
        global $wpdb;

        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}fct_customers WHERE id = %d",
            $order->customer_id
        ));

        if ($customer && $customer->user_id) {
            return false;
        }

        return true;
    }

    private static function create_user_from_order($order): void {
        global $wpdb;

        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fct_customers WHERE id = %d",
            $order->customer_id
        ));

        if (!$customer || !$customer->email) {
            return;
        }

        if (email_exists($customer->email)) {
            $user = get_user_by('email', $customer->email);
            if ($user) {
                self::link_customer_to_user($customer->id, $user->ID);
            }
            return;
        }

        $username = sanitize_user($customer->email);
        if (username_exists($username)) {
            $username = $username . '_' . wp_rand(100, 999);
        }

        $user_data = [
            'user_login' => $username,
            'user_email' => $customer->email,
            'user_pass'  => wp_generate_password(12, false),
            'first_name' => $customer->first_name ?? '',
            'last_name'  => $customer->last_name ?? '',
            'role'       => 'customer',
        ];

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            error_log("[BuyGo+1] Failed to create user: {$user_id->get_error_message()}");
            return;
        }

        self::link_customer_to_user($customer->id, $user_id);
        wp_new_user_notification($user_id, null, 'user');

        error_log("[BuyGo+1] User created for order {$order->id}, User ID: {$user_id}");
    }

    private static function link_customer_to_user(int $customer_id, int $user_id): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'fct_customers',
            ['user_id' => $user_id],
            ['id' => $customer_id],
            ['%d'],
            ['%d']
        );
    }
}
```

### 資料表建立範例
```php
// Source: includes/class-database.php
private static function create_seller_grants_table($wpdb, $charset_collate): void {
    $table_name = $wpdb->prefix . 'buygo_seller_grants';

    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
        return;
    }

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id bigint(20) UNSIGNED NOT NULL,
        user_id bigint(20) UNSIGNED NOT NULL,
        product_id bigint(20) UNSIGNED NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'success',
        error_message text,
        granted_role varchar(50) DEFAULT 'buygo_admin',
        granted_quota int(11) DEFAULT 3,
        notification_sent tinyint(1) DEFAULT 0,
        notification_channel varchar(20),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_order (order_id),
        KEY idx_user_id (user_id),
        KEY idx_status (status),
        KEY idx_created_at (created_at)
    ) {$charset_collate};";

    dbDelta($sql);
}
```

### LINE/Email 通知範例
```php
// Source: includes/services/class-notification-handler.php
private static function send_order_notification($order_id, $message_template, $event_name): void {
    try {
        global $wpdb;

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT o.*, c.user_id as wp_user_id FROM {$wpdb->prefix}fct_orders o
             LEFT JOIN {$wpdb->prefix}fct_customers c ON o.customer_id = c.id
             WHERE o.id = %s",
            $order_id
        ));

        if (!$order || !$order->wp_user_id) {
            return;
        }

        $wp_user_id = $order->wp_user_id;

        if (!IdentityService::hasLineBinding($wp_user_id)) {
            // Fallback to Email
            $user = get_userdata($wp_user_id);
            if ($user && $user->user_email) {
                wp_mail(
                    $user->user_email,
                    'BuyGo 通知',
                    $message_template
                );
            }
            return;
        }

        $message = str_replace(
            ['{order_id}', '{order_total}'],
            [$order_id, number_format($order->total ?? 0, 0)],
            $message_template
        );

        NotificationService::sendRawText($wp_user_id, $message);

    } catch (\Exception $e) {
        error_log("[BuyGo] Notification error: {$e->getMessage()}");
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| 手動賦予賣家權限 | 購買商品自動賦予 | Phase 39 實作 | 降低人工成本，提升使用者體驗 |
| 硬編碼商品 ID | 後台設定介面 | Phase 39 實作 | 彈性調整，無需修改程式碼 |
| 單一通知管道（Email） | LINE 優先 + Email Fallback | v1.3.0 | 提升通知到達率 |
| 直接修改資料庫 | WordPress Roles API | WordPress 最佳實踐 | 觸發 Hook、更新快取、記錄 log |
| 無退款處理 | 監聽退款 Hook 自動撤銷 | Phase 39 實作 | 防止濫用，符合退款政策 |

**Deprecated/outdated:**
- **手動 SQL 賦予角色**: 應使用 `$user->add_role('buygo_admin')`
- **無去重機制**: 必須實作 Idempotency 防止重複處理
- **靜默失敗**: 所有錯誤都應記錄到 `wp_buygo_debug_logs` 並通知管理員
- **無商品驗證**: 後台設定時應即時驗證商品存在性和類型

## Open Questions

Things that couldn't be fully resolved:

1. **FluentCart 退款 Hook 名稱**
   - What we know: FluentCart 有訂單狀態變更 Hook，可能包含退款事件
   - What's unclear: 確切的 Hook 名稱和參數格式（可能是 `fluent_cart/order_refunded` 或 `fluent_cart/order_status_changed`）
   - Recommendation: 檢查 FluentCart 原始碼 `app/Hooks/Handlers/` 目錄，或在實作時測試退款流程並監聽所有 Hook

2. **虛擬商品判斷欄位**
   - What we know: FluentCart 支援虛擬商品和實體商品，實體商品需要物流地址
   - What's unclear: 儲存在 `wp_postmeta` 的欄位名稱（可能是 `_require_shipping` 或 `_virtual`）
   - Recommendation: 查詢 FluentCart 商品編輯頁面的程式碼，或直接測試建立虛擬商品並檢查資料庫

3. **重試機制實作方式**
   - What we know: User Decision 要求「失敗時重試 3 次」
   - What's unclear: 是使用 `wp_schedule_single_event()` 異步重試，還是在同一請求中同步重試
   - Recommendation: 同步重試 3 次（簡單），如果仍失敗則記錄錯誤並發送管理員通知；未來可擴展為異步重試

4. **管理員通知方式**
   - What we know: 失敗時應通知管理員
   - What's unclear: 通知管道（Email、LINE、或後台 Notice）
   - Recommendation: 發送 Email 到 `get_option('admin_email')`，主旨為「[BuyGo] 賣家權限賦予失敗」

## Sources

### Primary (HIGH confidence)
- Codebase: `buygo-plus-one-dev/includes/integrations/class-fluentcart-offline-payment-user.php` - FluentCart Hook 整合範例
- Codebase: `buygo-plus-one-dev/includes/services/class-notification-handler.php` - 通知系統實作
- Codebase: `buygo-plus-one-dev/includes/class-database.php` - 資料表建立模式
- Codebase: `buygo-plus-one-dev/includes/services/class-identity-service.php` - 身份判斷邏輯
- Research: `.planning/phases/35-fluentcart-hook-探索與注入點設定/35-RESEARCH.md` - FluentCart Hook 系統文件
- User Context: `.planning/phases/39-fluentcart-自動賦予賣家權限-/39-CONTEXT.md` - User Decision 明確要求

### Secondary (MEDIUM confidence)
- Web Search: [FluentCart WordPress Plugin](https://wordpress.org/plugins/fluent-cart/) - 官方外掛頁面，確認虛擬商品支援
- Web Search: [FluentCart Official Site](https://fluentcart.com/) - 官方網站，確認產品類型功能

### Tertiary (LOW confidence)
- FluentCart 原始碼中退款 Hook 名稱（需要實際查詢確認）
- 虛擬商品欄位名稱（需要資料庫查詢或測試確認）

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - 所有技術都是 WordPress 核心 API 和現有 BuyGo 服務
- Architecture: HIGH - 已有成功的 FluentCart 整合案例可參考
- Pitfalls: HIGH - 基於現有程式碼分析和 User Decision 推導
- Virtual Product Detection: MEDIUM - 需要實際測試確認欄位名稱
- Refund Hook: MEDIUM - 需要查詢 FluentCart 原始碼確認

**Research date:** 2026-02-04
**Valid until:** 60 days (FluentCart 和 WordPress Core API 相對穩定)
