# Pitfalls Research: v1.4 會員前台子訂單顯示功能

**Domain:** WordPress + FluentCart 子訂單前台顯示整合
**Researched:** 2026-02-02
**Milestone:** v1.4 會員前台子訂單顯示功能
**Confidence:** HIGH

> 本研究聚焦於在現有 BuyGo+1 外掛中新增子訂單前台顯示功能時的常見陷阱。基於專案現有的多賣家權限系統、FluentCart 整合模式和 Hook 架構進行分析。

---

## Critical Pitfalls

### Pitfall 1: 權限隔離失效 — 顧客 A 看到顧客 B 的子訂單

**What goes wrong:**
在前台注入子訂單 UI 時，如果直接使用 `parent_id` 查詢子訂單而未驗證當前使用者權限，會導致任何知道父訂單 ID 的使用者都能查看所有相關子訂單，包括其他顧客的訂單資料。

**Why it happens:**
開發者習慣性複製後台程式碼邏輯到前台，忽略以下關鍵差異：
- 後台有 WordPress `current_user_can()` 和 BuyGo 的賣家權限系統保護
- 前台是「顧客身份」存取，需要驗證訂單所有權（`customer_id` 對應 `user_id`）
- FluentCart 的 `Order::where('parent_id', $parent_id)->get()` 不會自動過濾當前使用者

**How to avoid:**
```php
// ❌ 錯誤：直接查詢子訂單
$child_orders = Order::where('parent_id', $parent_id)->get();

// ✅ 正確：先驗證父訂單所有權，再查詢子訂單
$parent_order = Order::find($parent_id);
if (!$parent_order || $parent_order->customer_id !== get_current_user_id()) {
    wp_send_json_error(['message' => '無權限存取此訂單'], 403);
}
$child_orders = Order::where('parent_id', $parent_id)->get();
```

**防禦層級策略（多層保護）：**
1. **API 端點層**：REST API 端點使用 `permission_callback` 驗證使用者登入狀態
2. **Service 層**：Service 方法接受 `$user_id` 參數，內部驗證訂單所有權
3. **資料庫層**：在 SQL 查詢中加入 `customer_id = $user_id` 條件

**Warning signs:**
- API 測試時使用不同使用者帳號登入，仍能看到其他人的子訂單
- 瀏覽器開發者工具修改 AJAX 請求的 `parent_id` 參數，能取得其他訂單資料
- 單元測試覆蓋率低於 80%，特別是權限驗證部分

**Phase to address:**
**Phase 1: API 端點開發** — 在 REST API 建立時必須實作權限驗證，這是第一道防線

---

### Pitfall 2: N+1 查詢爆炸 — 每個父訂單觸發多次子查詢

**What goes wrong:**
在訂單列表頁顯示 10 筆父訂單時，如果每個父訂單的子訂單資料都是在迴圈中單獨查詢，會產生 `1 + 10*N` 次資料庫查詢（1 次取父訂單 + 每個父訂單觸發 N 次子訂單/商品/地址查詢）。當子訂單數量增加時，頁面載入時間從 1 秒暴增到 30 秒以上。

**Why it happens:**
- 在 `formatOrder()` 方法中動態查詢子訂單和關聯資料（地址、商品圖片）
- FluentCart Model 的 `with()` 預載入被忽略或使用錯誤
- 從父訂單地址表回補子訂單資料時，每個子訂單都觸發一次 `SELECT FROM wp_fct_order_addresses WHERE order_id = ?`

**現有程式碼中的 N+1 風險點：**
```php
// OrderService::formatOrder() 中的問題（1037-1062 行）
// ❌ 如果是子訂單且沒有地址，從父訂單查詢（每個子訂單觸發一次）
$parent_id = $order['parent_id'] ?? null;
if (empty($order_address) && !empty($parent_id)) {
    $order_address = $wpdb->get_row($wpdb->prepare(
        "SELECT name, meta, ... FROM {$table_order_addresses} WHERE order_id = %d ...",
        $parent_id
    ), ARRAY_A);
}
```

**How to avoid:**

**策略 1：在列表查詢時使用 Eager Loading**
```php
// ✅ 正確：一次性預載入所有關聯
$orders = Order::with(['customer', 'order_items', 'children.order_items', 'addresses'])
    ->whereNull('parent_id')
    ->get();
```

**策略 2：批次查詢父訂單地址**
```php
// ✅ 正確：收集所有需要的父訂單 ID，一次查詢
$parent_ids = array_unique(array_filter(array_column($orders, 'parent_id')));
if (!empty($parent_ids)) {
    $addresses = $wpdb->get_results(
        "SELECT order_id, name, meta, ... FROM {$table_addresses}
         WHERE order_id IN (" . implode(',', array_map('intval', $parent_ids)) . ")"
    );
    // 建立 order_id => address 的 map
    $address_map = array_column($addresses, null, 'order_id');
}
```

**策略 3：前台分頁載入子訂單**
```php
// 父訂單列表只顯示摘要（子訂單數量、總金額）
// 點擊「展開」時才 AJAX 載入完整子訂單資料
```

**Warning signs:**
- 使用 Query Monitor 外掛檢測到同一個查詢模式重複執行 10+ 次
- 訂單列表頁的 `wp_fct_order_addresses` 查詢次數 = 顯示的訂單數量
- PHP 執行時間超過 1 秒（在 10 筆訂單的情況下）
- 開發環境正常，但生產環境（訂單量大）頁面載入緩慢

**Phase to address:**
**Phase 2: 前台 UI 開發** — 在實作列表頁時必須使用 Eager Loading 或分頁策略
**Phase 4: 效能優化** — 使用 Query Monitor 偵測並修復 N+1 問題

---

### Pitfall 3: FluentCart Hook 升級失效 — 版本更新後功能中斷

**What goes wrong:**
BuyGo+1 使用 FluentCart 的 Hook（例如 `fluent_cart/checkout/prepare_other_data`）來注入子訂單 UI，當 FluentCart 更新後改變 Hook 名稱、參數順序或執行時機，前台子訂單顯示功能直接中斷，且無明顯錯誤訊息。

**Why it happens:**
- FluentCart 文件中沒有明確標示 Hook 的穩定性級別（stable vs. experimental）
- 開發者依賴未公開的內部 Hook（例如以 `_` 開頭的私有 Hook）
- FluentCart 1.2.6 版本加入 Customization Hooks，但舊 Hook 可能被棄用或參數變更
- 沒有版本檢查機制，無法偵測 FluentCart 版本與外掛的相容性

**FluentCart 近期更新中的 Hook 變更風險：**
根據 [FluentCart Changelog](https://docs.fluentcart.com/guide/changelog) 和 [v1.2.5 公告](https://fluentcart.com/blog/fluentcart-v1-2-5/)：
- **v1.2.6 (2025-10-29):** Added customization hooks in Thank You page, checkout page, and more context to the `fluent_cart/checkout/prepare_other_data` hook
- **v1.2.5:** Customization hooks were added for both the Checkout and Thank You pages
- **v1.2.3 (2025-10-22):** Added new hooks for single product and shop page products

**如果依賴舊 Hook 的風險：**
- `fluent_cart/checkout/prepare_other_data` 在 v1.2.6 增加了 "more context"，參數可能改變
- 新增的 Customization Hooks 可能取代舊 Hook

**How to avoid:**

**策略 1：版本檢查與降級策略**
```php
// ✅ 檢查 FluentCart 版本，使用相容的 Hook
$fluentcart_version = defined('FLUENT_CART_VERSION') ? FLUENT_CART_VERSION : '0.0.0';

if (version_compare($fluentcart_version, '1.2.6', '>=')) {
    // 使用新版 Hook
    add_action('fluent_cart/customer_portal/order_details', [$this, 'render_child_orders_new'], 10, 2);
} else {
    // 使用舊版 Hook（如果存在）
    add_action('fluent_cart/order_details_after', [$this, 'render_child_orders_legacy'], 10, 1);
}
```

**策略 2：優先使用官方穩定 Hook**
根據 [FluentCart Developer Docs](https://dev.fluentcart.com/getting-started)，FluentCart 提供 315+ Hooks。優先選擇：
- 在官方文件中明確列出的 Hook
- 沒有 `_` 前綴的公開 Hook
- 在 Changelog 中標註為「新增」而非「修改」的 Hook

**策略 3：Hook 存在性檢查**
```php
// ✅ 在使用前檢查 Hook 是否存在
if (has_action('fluent_cart/customer_portal/order_details')) {
    add_action('fluent_cart/customer_portal/order_details', [$this, 'render_child_orders'], 10, 2);
} else {
    // 降級方案：使用 Shortcode 或 Template Override
    add_shortcode('buygo_child_orders', [$this, 'render_child_orders_shortcode']);
}
```

**策略 4：Template Override 作為備用方案**
如果 Hook 不穩定，直接覆寫 FluentCart 範本檔案：
```php
// 在主題或外掛中建立：
// wp-content/themes/YOUR_THEME/fluentcart/customer/order-details.php
// 完全控制 UI，不依賴 Hook
```

**Warning signs:**
- FluentCart 更新後前台子訂單區塊消失，無任何錯誤訊息
- PHP 錯誤日誌出現 "Call to undefined function" 或 "Invalid callback"
- 使用者回報「訂單詳情頁空白」或「只看到父訂單」
- 在 FluentCart 的 GitHub Issues 中看到「Hook deprecated」或「Breaking change」

**Phase to address:**
**Phase 1: 技術選型** — 在架構設計階段確認使用的 Hook 是穩定的
**Phase 3: 整合測試** — 在 FluentCart 不同版本環境下測試 Hook 相容性
**Phase 5: 監控與維護** — 建立 FluentCart 版本監控機制，定期檢查相容性

---

### Pitfall 4: 前端 JavaScript 衝突 — FluentCart 原有 JS 與注入的互動衝突

**What goes wrong:**
在前台注入子訂單的「展開/折疊」互動時，如果使用全域變數或 CSS class 名稱與 FluentCart 原有的 JavaScript 衝突，會導致：
- 點擊「展開」按鈕觸發 FluentCart 的訂單操作（例如「取消訂單」）
- FluentCart 的訂單列表 JavaScript 覆蓋子訂單的事件監聽器
- 兩個 AJAX 請求同時觸發，導致資料重複載入或錯誤

**Why it happens:**
- 使用通用的 CSS class（例如 `.order-toggle`, `.order-details`）與 FluentCart 命名衝突
- 未使用 JavaScript 命名空間，全域函式覆蓋 FluentCart 的方法
- Event Delegation 範圍設定錯誤，誤捕獲 FluentCart 的 DOM 元素
- jQuery 版本不一致或 `noConflict()` 模式設定錯誤

**現有 BuyGo+1 專案中的潛在衝突來源：**
BuyGo+1 專案有多個前端 JS 檔案（`assets/js/` 目錄），如果未妥善命名空間管理，可能與 FluentCart 衝突。

**How to avoid:**

**策略 1：使用唯一的 CSS class 前綴**
```javascript
// ❌ 錯誤：通用名稱
<button class="toggle-details">展開子訂單</button>

// ✅ 正確：BuyGo 專屬前綴
<button class="buygo-child-orders-toggle" data-parent-id="123">展開子訂單</button>
```

**策略 2：JavaScript 命名空間**
```javascript
// ✅ 正確：使用 IIFE 和命名空間
(function($) {
    'use strict';

    window.BuyGoChildOrders = window.BuyGoChildOrders || {};

    BuyGoChildOrders.init = function() {
        $('.buygo-child-orders-toggle').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation(); // 防止事件冒泡到 FluentCart
            // ...
        });
    };

    $(document).ready(BuyGoChildOrders.init);
})(jQuery);
```

**策略 3：Event Delegation 精確範圍**
```javascript
// ❌ 錯誤：監聽整個 document
$(document).on('click', '.toggle', function() { ... });

// ✅ 正確：只監聽 BuyGo 子訂單容器內的事件
$('.buygo-child-orders-container').on('click', '.buygo-toggle', function() { ... });
```

**策略 4：Script 載入順序控制**
```php
// ✅ 確保在 FluentCart 之後載入，並宣告依賴
wp_enqueue_script(
    'buygo-child-orders',
    BUYGO_PLUS_ONE_PLUGIN_URL . 'assets/js/child-orders.js',
    ['jquery', 'fluent-cart'], // 宣告依賴
    BUYGO_PLUS_ONE_VERSION,
    true // 在 footer 載入
);
```

**Warning signs:**
- 瀏覽器 Console 出現 "Uncaught TypeError: $ is not a function"
- 點擊「展開」按鈕觸發錯誤的操作或無反應
- 使用 [Plugin Detective](https://wordpress.org/plugins/plugin-detective/) 偵測到與 FluentCart 的 JS 衝突
- 停用其他外掛後功能正常，啟用後失效

**Phase to address:**
**Phase 2: 前台 UI 開發** — 在編寫 JavaScript 時必須使用命名空間和唯一 class
**Phase 3: 整合測試** — 使用瀏覽器開發者工具檢測 JavaScript 衝突

---

### Pitfall 5: 狀態同步失效 — 子訂單狀態更新後前台未刷新

**What goes wrong:**
賣家在後台更新子訂單的 `shipping_status` 或 `payment_status` 後，顧客在前台的「我的訂單」頁面刷新仍看到舊狀態，需要清除瀏覽器快取或等待數分鐘才能看到更新。

**Why it happens:**
- 前台使用 WordPress Transient Cache 或 Object Cache 快取訂單資料，未在狀態更新時清除
- FluentCart 的 Model 使用內部快取，`Order::find($id)` 返回快取的舊資料
- REST API 回應設定了 `Cache-Control` header，瀏覽器快取 API 結果
- 後台更新狀態時觸發的 `do_action('buygo_shipping_status_changed')` Hook 未清除前台快取

**BuyGo+1 現有快取機制：**
```php
// OrderService::updateShippingStatus() 第 452 行
\do_action('buygo_shipping_status_changed', $orderId, $oldStatus, $status);
```
如果前台 API 使用快取，這個 Hook 必須清除相關快取。

**How to avoid:**

**策略 1：在狀態更新時清除 Transient Cache**
```php
// ✅ 在 OrderService::updateShippingStatus() 中加入
public function updateShippingStatus(string $orderId, string $status, string $reason = ''): bool
{
    // ... 更新狀態的程式碼 ...

    // 清除相關快取
    delete_transient('buygo_order_' . $orderId);
    delete_transient('buygo_customer_orders_' . $order->customer_id);

    // 清除 WordPress Object Cache（如果使用 Redis/Memcached）
    wp_cache_delete('order_' . $orderId, 'buygo_orders');

    return true;
}
```

**策略 2：REST API 禁用快取**
```php
// ✅ 在 REST API 端點中設定不快取
add_filter('rest_post_dispatch', function($response, $server, $request) {
    if (strpos($request->get_route(), '/buygo-plus-one/v1/orders') !== false) {
        $response->header('Cache-Control', 'no-cache, must-revalidate, max-age=0');
    }
    return $response;
}, 10, 3);
```

**策略 3：前台使用 ETag 或 Last-Modified 驗證**
```php
// ✅ 使用 ETag 讓瀏覽器驗證快取是否過期
$order_hash = md5(json_encode($order) . $order['updated_at']);
header('ETag: "' . $order_hash . '"');

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === '"' . $order_hash . '"') {
    http_response_code(304); // Not Modified
    exit;
}
```

**策略 4：WebSocket 或 Server-Sent Events（進階方案）**
如果需要即時更新，可使用 WordPress 外掛（例如 Pusher 或 Firebase）推播狀態變更：
```php
// 當狀態更新時推播到前台
add_action('buygo_shipping_status_changed', function($orderId, $oldStatus, $newStatus) {
    // 推播到 WebSocket 或 SSE
    do_action('buygo_realtime_update', [
        'type' => 'order_status_changed',
        'order_id' => $orderId,
        'new_status' => $newStatus
    ]);
}, 10, 3);
```

**Warning signs:**
- 使用者回報「訂單狀態沒更新」，需要手動重新整理頁面
- 在 Chrome DevTools Network 面板看到 API 回應是 `(from disk cache)` 或 `304 Not Modified`，但資料已過期
- 後台更新狀態後，前台 AJAX 請求返回的資料與資料庫不一致
- Redis/Memcached 中存在過期的訂單快取資料

**Phase to address:**
**Phase 1: API 開發** — 在 REST API 端點設定正確的快取策略
**Phase 4: 狀態同步** — 在 `updateShippingStatus()` 中清除快取
**Phase 5: 測試驗證** — 測試後台更新 → 前台即時刷新的流程

---

## Technical Debt Patterns

BuyGo+1 v1.4 中可能採取的捷徑及其長期成本。

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| 直接在父訂單 `formatOrder()` 中查詢子訂單，不使用 Eager Loading | 快速實作，程式碼簡單 | N+1 查詢，訂單量增加後頁面緩慢，需重構 | **Never** — 一開始就用 Eager Loading |
| 使用 FluentCart 未文件化的內部 Hook | 節省尋找官方 Hook 的時間 | FluentCart 更新後功能中斷，需緊急修復 | **Never** — 只使用官方穩定 Hook |
| 前台 API 使用 Transient Cache（5 分鐘） | 減少資料庫查詢，提升效能 | 狀態更新延遲，使用者體驗差 | **MVP 可用** — 但必須在 Phase 4 改為 ETag 或即時清除 |
| CSS class 使用通用名稱（例如 `.order-item`） | 快速套用樣式 | 與 FluentCart 或主題 CSS 衝突，UI 錯亂 | **Never** — 一開始就用 `.buygo-` 前綴 |
| 權限驗證只在前端 JavaScript 檢查 | 減少後端程式碼 | 安全漏洞，任何人可繞過前端存取 API | **Never** — 必須在後端驗證 |
| 子訂單資料不分頁，一次載入所有 | 避免實作分頁邏輯 | 當子訂單超過 50 筆時頁面載入超時 | **MVP 可用** — 如果預期每筆父訂單 < 10 筆子訂單 |

---

## Integration Gotchas

與 FluentCart 整合時的常見錯誤。

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| FluentCart Hook 整合 | 假設所有 Hook 都是穩定的，直接使用 | 檢查 Changelog，只使用官方文件列出的 Hook，加入版本檢查 |
| FluentCart Model 查詢 | 直接使用 `Order::find($id)` 而不驗證當前使用者 | 先查詢後驗證 `customer_id`，或使用 `whereCustomerId()` |
| FluentCart 範本覆寫 | 直接修改 FluentCart 外掛目錄中的範本檔案 | 在主題或外掛中建立 `fluentcart/` 目錄覆寫範本 |
| FluentCart 訂單地址 | 假設子訂單有 `fct_order_addresses` 記錄 | 子訂單可能沒有地址，需從父訂單回補（注意 N+1 問題） |
| FluentCart JavaScript | 未檢查 FluentCart 的 JS 是否載入就使用其方法 | 使用 `wp_script_is('fluent-cart', 'enqueued')` 檢查或宣告依賴 |

---

## Performance Traps

在 v1.4 開發中容易忽略的效能陷阱。

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| N+1 查詢：每個父訂單單獨查詢子訂單 | Query Monitor 顯示 100+ 次相同查詢模式 | 使用 `Order::with('children')` Eager Loading | 訂單列表顯示 10+ 筆父訂單時 |
| N+1 查詢：每個子訂單單獨查詢父訂單地址 | 頁面載入時間 > 3 秒 | 批次查詢所有父訂單 ID 的地址，建立 map | 當有 5+ 筆子訂單需要回補地址時 |
| 未分頁的子訂單列表 | 前台頁面載入超時或白屏 | 展開時使用 AJAX 分頁載入，每頁 20 筆 | 單一父訂單有 50+ 筆子訂單時 |
| 商品圖片未使用縮圖 | 頁面載入緩慢，流量消耗大 | 使用 `wp_get_attachment_image_url($id, 'thumbnail')` | 訂單包含 10+ 筆商品時 |
| 前台 API 未快取 | 每次刷新都查詢資料庫 | 使用 Transient Cache（60 秒），狀態更新時清除 | 每秒 > 5 次 API 請求時 |

---

## Security Mistakes

前台子訂單顯示的安全風險。

| Mistake | Risk | Prevention |
|---------|------|------------|
| 未驗證訂單所有權就回傳子訂單資料 | **HIGH** — 顧客 A 能看到顧客 B 的訂單明細、地址、電話 | API 端點必須驗證 `$order->customer_id === get_current_user_id()` |
| 使用 `$_GET['order_id']` 直接查詢，無防 SQL Injection | **CRITICAL** — 資料庫被竄改或洩漏 | 使用 `absint($_GET['order_id'])` 或 FluentCart Model 的參數綁定 |
| API 端點未使用 WordPress Nonce 驗證 | **MEDIUM** — CSRF 攻擊，惡意網站觸發訂單操作 | 使用 `wp_verify_nonce()` 或 REST API 的 Cookie 驗證 |
| 前台顯示賣家的內部備註或成本資料 | **MEDIUM** — 商業機密洩漏 | `formatOrder()` 中過濾敏感欄位，只返回顧客應看到的資料 |
| 子訂單 API 無請求頻率限制 | **LOW** — DDoS 攻擊或爬蟲濫用 | 使用 WordPress Transient 實作 Rate Limiting（例如每分鐘 60 次） |

**關鍵安全原則：**
根據 [WordPress Security Best Practices 2026](https://www.adwaitx.com/wordpress-security-best-practices/)，Broken Access Control 佔 14.19% 的漏洞，在前台訂單顯示功能中必須：
1. **最小權限原則**：使用者只能存取自己的訂單
2. **後端驗證**：永遠不信任前端傳來的參數
3. **輸入驗證**：所有 `$_GET`, `$_POST`, `$_REQUEST` 參數都要驗證和清理

---

## UX Pitfalls

前台顯示子訂單時的使用者體驗陷阱。

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| 預設展開所有子訂單 | 頁面過長，使用者難以瀏覽，載入緩慢 | 預設折疊，顯示「2 筆子訂單」並提供「展開」按鈕 |
| 子訂單與父訂單資料重複顯示 | 使用者困惑：「為什麼地址和收件人都一樣？」 | 子訂單只顯示差異資料（商品、金額、運送狀態） |
| 沒有視覺區分父訂單和子訂單 | 使用者誤以為是不同的訂單 | 使用縮排、顏色或圖示明確標示階層關係 |
| 子訂單狀態更新無通知 | 使用者不知道訂單已出貨，需要手動刷新 | 使用 AJAX 定時輪詢（30 秒）或 Server-Sent Events |
| 「展開」按鈕無載入狀態指示 | 使用者不知道是否正在載入，重複點擊 | 顯示 Loading 動畫，按鈕 disable |
| 子訂單太多時無分頁或虛擬滾動 | 一次載入 100 筆子訂單，頁面卡死 | 展開時只顯示前 10 筆，提供「載入更多」按鈕 |

---

## "Looks Done But Isn't" Checklist

v1.4 開發完成前的驗證清單，防止「看起來能動」但實際有問題。

- [ ] **權限驗證:** 使用不同顧客帳號測試，確認無法看到其他人的子訂單
- [ ] **N+1 查詢:** 使用 Query Monitor 檢查，確認訂單列表頁查詢次數 < 10 次（不論顯示多少父訂單）
- [ ] **FluentCart 相容性:** 在 FluentCart 1.2.x 和 1.3.x 版本下測試 Hook 是否正常
- [ ] **JavaScript 衝突:** 停用/啟用其他外掛（WooCommerce, Elementor）測試是否有 JS 錯誤
- [ ] **快取同步:** 後台更新子訂單狀態後，前台刷新頁面確認立即顯示新狀態
- [ ] **手機版 UI:** 在手機瀏覽器測試展開/折疊功能是否正常
- [ ] **大量子訂單:** 建立測試訂單（50 筆子訂單），確認前台不會超時或白屏
- [ ] **空資料處理:** 測試沒有子訂單的父訂單，確認不會顯示錯誤或空白區塊
- [ ] **地址回補:** 測試子訂單沒有地址記錄時，是否正確從父訂單取得地址
- [ ] **安全測試:** 使用 Burp Suite 或瀏覽器開發者工具修改 API 請求參數，確認無法存取他人訂單

---

## Recovery Strategies

當陷阱發生時的補救方案。

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| 權限漏洞已上線 | **HIGH** — 需緊急修復並通知受影響使用者 | 1. 立即部署修正版本<br>2. 檢查伺服器日誌，確認是否有未授權存取<br>3. 通知可能受影響的顧客，說明已修復 |
| N+1 查詢導致網站緩慢 | **MEDIUM** — 需重構查詢邏輯，但不影響功能 | 1. 使用 Query Monitor 定位問題查詢<br>2. 改用 Eager Loading 或批次查詢<br>3. 部署後使用 New Relic 或 GTmetrix 驗證改善 |
| FluentCart 更新後 Hook 失效 | **MEDIUM** — 功能中斷但資料無損 | 1. 檢查 FluentCart Changelog 找到替代 Hook<br>2. 如無替代方案，使用 Template Override<br>3. 在外掛中加入版本檢查，阻止不相容的 FluentCart 版本 |
| JavaScript 衝突導致 UI 錯亂 | **LOW** — 只影響前台顯示，後端資料正常 | 1. 在瀏覽器 Console 定位錯誤來源<br>2. 重新命名 CSS class 或 JS 變數<br>3. 使用 `wp_enqueue_script()` 調整載入順序 |
| 狀態同步延遲 | **LOW** — 使用者體驗不佳但資料正確 | 1. 加入快取清除機制<br>2. 在前台顯示「最後更新時間」<br>3. 提供「重新整理」按鈕讓使用者手動更新 |

---

## Pitfall-to-Phase Mapping

將陷阱對應到 v1.4 的開發階段，確保在正確的階段預防問題。

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| 權限隔離失效 | **Phase 1: API 開發** | 單元測試：不同使用者 ID 存取他人訂單時回傳 403 |
| N+1 查詢爆炸 | **Phase 2: 前台 UI 開發** | Query Monitor 檢查：訂單列表頁查詢次數 < 10 |
| FluentCart Hook 升級失效 | **Phase 1: 技術選型** | 在 FluentCart 1.2.x, 1.3.x 測試環境驗證 Hook 相容性 |
| JavaScript 衝突 | **Phase 2: 前台 UI 開發** | 瀏覽器 Console 無錯誤，Plugin Detective 無衝突警告 |
| 狀態同步失效 | **Phase 4: 狀態同步** | 後台更新狀態 → 前台刷新 → 立即顯示新狀態（< 3 秒） |

---

## Sources

### WordPress Security Best Practices (2026)
- [WordPress Security Best Practices 2026: Stop 96% of Breaches](https://www.adwaitx.com/wordpress-security-best-practices/) — Broken Access Control 佔 14.19% 漏洞，強調最小權限原則
- [28 WordPress Security Best Practices and Tips for 2026](https://jetpack.com/resources/wordpress-security-tips-and-best-practices/) — 檔案權限和使用者權限管理
- [The Ultimate WordPress Security Guide - Step by Step (2026)](https://www.wpbeginner.com/wordpress-security/)

### N+1 Query Problem
- [Killing the N+1 Query Problem: Practical Fixes and the Real Trade-offs](https://medium.com/techtrends-digest/killing-the-n-1-query-problem-practical-fixes-and-the-real-trade-offs-7e816d9266f1) — 30 倍效能改善案例
- [Solving the N+1 Query Problem: How I Reduced API Response Time from 30s to <1s](https://medium.com/@nkangprecious26/solving-the-n-1-query-problem-how-i-reduced-api-response-time-from-30s-to-1s-1fcd819c34e6)
- [What is the n+1 problem? (WordPress edition)](https://accreditly.io/articles/what-is-the-n1-problem-wordpress-edition)

### JavaScript Conflicts Detection
- [Plugin Detective – Troubleshooting Conflicts](https://wordpress.org/plugins/plugin-detective/) — 自動偵測 2000+ 外掛的不相容性
- [The Developer's Guide To Conflict-Free JavaScript And CSS In WordPress](https://www.smashingmagazine.com/2011/10/developers-guide-conflict-free-javascript-css-wordpress/)
- [Practical Steps to Check for Plugin Conflicts in WordPress](https://www.codeable.io/blog/wordpress-plugin-conflict/)

### WordPress REST API & State Synchronization
- [Real-Time Data Sync with WordPress Plugins: A Complete Guide](https://eseospace.com/blog/real-time-data-sync-with-wordpress/)
- [Real-time Data Synchronization Across Platforms with WordPress API](https://reintech.io/blog/real-time-data-synchronization-wordpress-api)
- [The Complete Guide To WordPress REST API In 2025](https://wpwebinfotech.com/blog/wordpress-rest-api/)

### FluentCart Documentation
- [FluentCart Changelog](https://docs.fluentcart.com/guide/changelog) — Hook 變更歷史
- [FluentCart v1.2.5: Taxes, Checkout, and Customization updates!](https://fluentcart.com/blog/fluentcart-v1-2-5/) — 新增 Customization Hooks
- [FluentCart v1.3.0: Security, Payment, and Checkout Enhanced!](https://fluentcart.com/blog/fluent-cart-v1-3-0/)
- [Getting Started | FluentCart Developer Docs](https://dev.fluentcart.com/getting-started) — 官方開發者文件，315+ Hooks
- [Introducing FluentCart: WordPress eCommerce Finally Makes Sense](https://fluentforms.com/introducing-fluentcart/)

### BuyGo+1 Codebase
- `includes/services/class-order-service.php` — 現有訂單服務邏輯，多賣家權限過濾實作（88-123 行）
- `includes/services/class-settings-service.php` — `get_accessible_seller_ids()` 權限驗證方法
- `buygo-plus-one.php` — 外掛初始化和 Hook 註冊

---

**Pitfalls research for:** BuyGo+1 v1.4 會員前台子訂單顯示功能
**Researched:** 2026-02-02
**Confidence:** HIGH（基於專案現有程式碼分析和 2026 最新 WordPress 安全/效能最佳實踐）
