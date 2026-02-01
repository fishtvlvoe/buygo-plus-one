# Stack Research: 出貨通知功能

**Domain:** WordPress E-Commerce 出貨通知系統
**Researched:** 2026-02-02
**Confidence:** HIGH

## 執行摘要

本研究針對在現有 BuyGo Plus One WordPress 外掛中新增出貨通知功能所需的技術棧進行分析。核心發現：

1. **資料庫欄位類型**：使用 DATETIME 而非 TIMESTAMP 儲存預計送達時間
2. **通知觸發機制**：使用 WordPress action hooks (`do_action`) 而非直接函式呼叫
3. **模板管理**：採用 WordPress Options API 搭配多層快取策略
4. **資料庫升級**：使用 dbDelta() 搭配版本檢查機制

## 核心技術決策

### 資料庫架構

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| MySQL DATETIME | MySQL 5.7+ | 儲存 estimated_delivery_at 欄位 | **HIGH confidence** - DATETIME 比 TIMESTAMP 更適合用於未來日期：(1) 範圍更大（1000-9999 vs 1970-2038），(2) 不受時區變動影響，(3) WordPress 核心標準（post_date, post_modified 皆用 DATETIME），(4) 儲存空間僅多 1 byte (5 vs 4) 但避免 2038 年問題 |
| dbDelta() | WordPress 核心函式 | 資料表結構升級 | **HIGH confidence** - WordPress 官方推薦的資料表升級機制，可安全新增欄位而不影響現有資料，支援 idempotent 操作（多次執行相同結果） |
| WordPress Options API | WordPress 核心 | 儲存通知模板設定 | **HIGH confidence** - 標準化的設定儲存方式，支援序列化陣列，與現有 NotificationTemplates 服務整合 |

**資料庫欄位定義範例：**
```sql
ALTER TABLE {$wpdb->prefix}buygo_shipments
ADD COLUMN estimated_delivery_at DATETIME NULL
COMMENT '預計送達時間'
AFTER shipped_at;
```

**理由說明：**
- **DATETIME vs TIMESTAMP**：根據 [MySQL Best Practices](https://accreditly.io/articles/storing-dates-and-times-in-mysql-best-practices-and-pitfalls) 和 [OWOX SQL Date Types Guide](https://www.owox.com/blog/articles/bigquery-date-types-date-datetime-timestamp)，出貨預計送達時間應使用 DATETIME 因為：
  - 送達時間可能超過 2038 年（TIMESTAMP 上限）
  - 送達時間應該固定，不應隨伺服器時區改變而變動
  - WordPress 核心慣例：所有日期相關欄位皆用 DATETIME ([WordPress Date Handling](https://wp-punk.com/how-to-deal-with-date-and-time-in-wordpress/))

- **NULL vs NOT NULL**：estimated_delivery_at 應設為 NULL 可選，因為：
  - 不是所有出貨單都會立即設定預計送達時間
  - 允許賣家稍後補充資訊
  - 避免使用 '0000-00-00 00:00:00' 等無意義預設值

### 通知觸發機制

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| WordPress Action Hooks | WordPress 核心 | 觸發出貨通知 | **HIGH confidence** - WordPress 標準事件驅動模式，允許其他外掛或主題掛載到出貨事件，支援優先級排序和參數傳遞 |
| do_action() | WordPress 核心函式 | 執行掛載的回調函式 | **HIGH confidence** - WordPress 官方推薦的事件觸發方式，支援多個回調函式和參數傳遞 |

**實作模式：**
```php
// ShipmentService::mark_shipped() 中觸發 hook
do_action('buygo/shipment_marked_shipped', $shipment_id, $shipment, $order_ids);

// LineNotificationService 監聽 hook
add_action('buygo/shipment_marked_shipped', [$this, 'send_shipment_notification'], 10, 3);
```

**理由說明：**
- 根據 [WordPress Action Hooks Guide](https://developer.wordpress.org/plugins/hooks/actions/) 和 [WordPress do_action Reference](https://developer.wordpress.org/reference/functions/do_action/)，使用 action hooks 而非直接函式呼叫的優勢：
  - **解耦合**：ShipmentService 不需要知道 LINE 通知的存在
  - **可擴展**：未來可新增 Email、SMS 等通知管道，只需監聽同一個 hook
  - **可測試**：可單獨測試 ShipmentService 和通知服務
  - **社群標準**：符合 WordPress 外掛開發最佳實務

### LINE Messaging API 整合

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| LINE Messaging API | v2+ | 發送交易通知訊息 | **HIGH confidence** - LINE 官方推薦用於電商交易通知（訂單確認、出貨通知），提供 500 則免費訊息/月，每則額外 0.10 TWD |
| cURL PHP Extension | PHP 內建 | HTTP 請求 LINE API | **HIGH confidence** - WordPress 核心依賴，無需額外安裝，支援 HTTPS 和 Header 設定 |

**LINE Messaging API 選擇理由：**
- 根據 [LINE Messaging API Overview](https://developers.line.biz/en/docs/messaging-api/overview/) 和 [LINE Notification Best Practices](https://respond.io/blog/line-notification)：
  - **LINE Notify vs Messaging API**：LINE Notify 將於 2025 年 3 月停止服務，所有新專案應使用 Messaging API
  - **交易通知最佳實務**：Messaging API 專為頻繁的自動化通知設計（訂單確認、付款通知、出貨更新）
  - **API 限制**：根據 [LINE API Technical Specs](https://developers.line.biz/en/docs/partner-docs/line-notification-messages/technical-specs/)，不需在 Security Settings 註冊伺服器 IP

**現有整合狀況：**
- BuyGo Plus One 已整合 LINE Messaging API（v1.2 功能）
- 現有 NotificationTemplates 服務已支援模板管理
- buygo-line-notify 外掛提供 LINE UID 查詢 API

### 模板管理架構

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| WordPress Options API | WordPress 核心 | 儲存自訂模板 | **HIGH confidence** - 標準化設定儲存，支援陣列序列化，與現有系統一致 |
| WordPress Object Cache | WordPress 核心 | 快取模板資料 | **HIGH confidence** - 減少資料庫查詢，支援 Redis/Memcached 等持久化快取 |
| Static Variable Cache | PHP 語言特性 | 單次請求快取 | **HIGH confidence** - 最快速的快取層，避免重複反序列化 |

**三層快取策略（現有 NotificationTemplates 實作）：**
```php
// Layer 1: Static variable (fastest, per-request)
private static $cached_custom_templates = null;

// Layer 2: WordPress Object Cache (fast, persistent)
wp_cache_get(self::$cache_key, self::$cache_group);

// Layer 3: Database (slowest, authoritative)
get_option('buygo_notification_templates', []);
```

**理由說明：**
- **為何不用 Custom Post Type**：模板數量少（<50），結構簡單，使用 Options API 更簡潔
- **為何不用 JSON 檔案**：需要支援後台 UI 編輯，Options API 提供原子更新保證
- **快取失效機制**：更新模板時自動清除所有快取層（`wp_cache_delete()` + `self::$cached_custom_templates = null`）

## 支援函式庫與工具

### 不需要額外安裝的函式庫

BuyGo Plus One 出貨通知功能**不需要安裝新的 Composer 或 npm 套件**，完全使用現有技術棧：

| Component | Source | Status |
|-----------|--------|--------|
| WordPress Core | 內建 | ✅ 已安裝（5.8+） |
| PHP | 系統環境 | ✅ 已安裝（8.0+） |
| MySQL | 系統環境 | ✅ 已安裝 |
| LINE Messaging API | HTTP API | ✅ 已整合（v1.2） |
| NotificationTemplates | 現有服務 | ✅ 已實作 |
| LineService | 現有服務 | ✅ 已實作 |
| ShipmentService | 現有服務 | ✅ 已實作 |

### 開發工具

| Tool | Purpose | Notes |
|------|---------|-------|
| PHPUnit 9.6 | 單元測試 | 已配置於 composer.json，使用 Yoast PHPUnit Polyfills |
| Test Script Manager | 快速開發測試 | WordPress 後台外掛，用於快速驗證資料庫查詢和 API 呼叫 |
| WordPress Debug Log | 除錯紀錄 | 現有 DebugService 提供結構化日誌 |

## 資料庫升級機制

### 推薦方式：dbDelta() + Version Check

**實作模式（參考現有 Database::upgrade_tables()）：**
```php
public static function upgrade_tables(): void
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'buygo_shipments';

    // 檢查表格是否存在
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
        // 表不存在，跳過升級
        return;
    }

    // 檢查欄位是否已存在
    $columns = $wpdb->get_col("DESCRIBE {$table_name}", 0);

    if (!in_array('estimated_delivery_at', $columns)) {
        // 新增欄位
        $wpdb->query(
            "ALTER TABLE {$table_name}
             ADD COLUMN estimated_delivery_at DATETIME NULL
             COMMENT '預計送達時間'
             AFTER shipped_at"
        );

        // 建立索引（如果需要按日期查詢）
        $wpdb->query(
            "ALTER TABLE {$table_name}
             ADD KEY idx_estimated_delivery_at (estimated_delivery_at)"
        );
    }
}
```

**觸發時機：**
```php
// buygo-plus-one.php (啟用時執行)
register_activation_hook(__FILE__, function () {
    require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-database.php';
    \BuyGoPlus\Database::create_tables();
    \BuyGoPlus\Database::upgrade_tables(); // 升級現有資料表
});
```

**理由說明：**
- 根據 [WordPress dbDelta Guide](https://yourwpweb.com/2025/09/26/how-to-create-database-tables-on-plugin-activation-dbdelta-in-wordpress/) 和 [WordPress Plugin Database Updates](https://wordpress.org/support/topic/adding-a-new-field-in-database-upon-plugin-update/)：
  - **idempotent 操作**：多次執行不會重複新增欄位（先檢查 `DESCRIBE` 結果）
  - **零中斷**：ALTER TABLE ADD COLUMN 不會鎖定現有資料，不影響線上服務
  - **向後相容**：舊版外掛不會因為新欄位而出錯

## 替代方案分析

### DATETIME vs TIMESTAMP

| Aspect | DATETIME (推薦) | TIMESTAMP (不推薦) |
|--------|----------------|-------------------|
| 範圍 | 1000-9999 | 1970-2038 |
| 時區行為 | 固定，不受時區影響 | 自動轉換 UTC |
| WordPress 慣例 | ✅ 核心標準 | ❌ 少數使用 |
| 儲存空間 | 5 bytes | 4 bytes |
| 2038 年問題 | ✅ 無影響 | ❌ 會溢位 |

**選擇理由：**
根據 [MySQL DATETIME vs TIMESTAMP](https://www.pingcap.com/article/exploring-mysql-timestamp-vs-datetime-key-differences/) 和 [Database Timestamp Best Practices](https://medium.com/@abdelaz9z/best-practices-for-database-design-incorporating-timestamps-and-user-metadata-in-tables-2310527dd677)，DATETIME 更適合出貨預計送達時間因為：
- 時間應該固定，不應隨伺服器時區改變（例如：預計 2026-02-05 10:00 送達，不應因為伺服器從 UTC+8 改為 UTC+0 而變成 02:00）
- 避免 2038 年問題（企業系統可能長期運行）
- 符合 WordPress 核心慣例，降低認知負擔

### Action Hooks vs Direct Function Call

| Approach | 優點 | 缺點 |
|----------|------|------|
| Action Hooks (推薦) | 解耦合、可擴展、符合 WordPress 標準 | 需要理解 hook 機制 |
| Direct Function Call (不推薦) | 簡單直觀 | 緊耦合、難以擴展、不符合社群標準 |

**選擇理由：**
根據 [WordPress Action Hooks Best Practices](https://developer.wordpress.org/plugins/hooks/actions/)，使用 action hooks 的核心優勢：
- **可插拔架構**：buygo-line-notify 外掛可獨立停用而不影響 ShipmentService
- **優先級控制**：可控制多個通知管道的執行順序
- **社群相容**：其他開發者可掛載自訂邏輯到出貨事件

### Options API vs Custom Post Type

| Approach | 適用情境 | 不適用情境 |
|----------|---------|-----------|
| Options API (推薦) | 模板數量少（<50），結構簡單 | 需要版本控制、搜尋、批次操作 |
| Custom Post Type (不推薦) | 內容型資料，需要 UI、搜尋 | 設定型資料，結構簡單 |

**選擇理由：**
- 通知模板屬於「設定」而非「內容」
- 數量少（預計 <10 個模板）
- 不需要版本控制、修訂歷史等 CPT 特性
- 現有 NotificationTemplates 已採用 Options API

## 不建議使用的技術

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| TIMESTAMP 欄位 | 2038 年溢位問題，時區自動轉換不適合固定日期 | DATETIME |
| Direct $wpdb->query() without prepare | SQL injection 風險 | $wpdb->prepare() |
| WordPress Transients for Templates | 過期時間不確定，可能導致資料遺失 | Options API + Object Cache |
| wp_mail() for Critical Notifications | 可靠性低，容易被垃圾郵件過濾 | LINE Messaging API（已整合） |
| Cron Job Polling | 延遲高，資源浪費 | Action Hooks（即時觸發） |
| Serialized PHP Objects in Options | 難以偵錯，版本更新風險 | JSON 或陣列 |

## REST API 驗證模式

### 現有驗證機制

BuyGo Plus One 已實作的 REST API 驗證方式：

| Method | Use Case | Implementation |
|--------|----------|----------------|
| Cookie + Nonce | 前端 Vue.js 呼叫 | `wp_create_nonce('wp_rest')` + `X-WP-Nonce` header |
| API Key | 外部系統整合 | 自訂 API 金鑰驗證（SettingsService） |

**Nonce 最佳實務（2025-2026）：**
根據 [WordPress REST API Authentication Guide](https://oddjar.com/wordpress-rest-api-authentication-guide-2025/) 和 [WordPress Nonce Security](https://developer.wordpress.org/apis/security/nonces/)：

```php
// 前端：Localize Script
wp_localize_script('buygo-shipments', 'buygoSettings', [
    'apiUrl' => rest_url('buygo-plus-one/v1'),
    'nonce' => wp_create_nonce('wp_rest')
]);

// 前端：設定 Headers
fetch(apiUrl + '/shipments/mark-shipped', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': buygoSettings.nonce
    },
    body: JSON.stringify({shipment_ids: [1, 2, 3]})
});

// 後端：自動驗證
// WordPress 在 rest_cookie_check_errors() 自動驗證 nonce
// 不需要手動呼叫 wp_verify_nonce()
```

**重要限制：**
- Nonce 有效期預設 12 小時（可過濾 `nonce_life`）
- Nonce 綁定當前使用者 session，登入/登出後失效
- Nonce 不能用於身分驗證，必須搭配 `current_user_can()` 檢查權限
- 根據 [WordPress REST API Security](https://wpplugins.tips/wordpress-rest-api-security-best-practices-and-tools/)，68% 的 WordPress API 漏洞源於不當驗證實作

## Hook 命名慣例

### 推薦的 Hook 命名

遵循 WordPress 核心和 WooCommerce 慣例：

```php
// ✅ 推薦：使用命名空間前綴 + 動作描述 + 過去式
do_action('buygo/shipment_marked_shipped', $shipment_id, $shipment, $order_ids);
do_action('buygo/shipment_notification_sent', $shipment_id, $line_uid, $result);
do_action('buygo/shipment_notification_failed', $shipment_id, $error);

// ❌ 不推薦：沒有命名空間，容易衝突
do_action('shipment_shipped', $shipment_id);

// ❌ 不推薦：使用現在式而非過去式
do_action('buygo/mark_shipment_shipped', $shipment_id);
```

**命名規則：**
- **命名空間**：`buygo/` 避免與其他外掛衝突
- **動作描述**：`shipment_marked_shipped` 清楚說明發生什麼事
- **時態**：過去式表示「事件已發生」，現在式表示「即將執行」
- **參數順序**：最重要的參數（如 ID）放在最前面

## 版本相容性

### WordPress 版本需求

| Component | Minimum Version | Recommended Version | Notes |
|-----------|----------------|---------------------|-------|
| WordPress | 5.8 | 6.4+ | WordPress 5.8 引入 REST API nonce 自動驗證 |
| PHP | 7.4 | 8.0+ | 支援 typed properties 和 null coalescing |
| MySQL | 5.7 | 8.0+ | DATETIME 支援完整，ON UPDATE CURRENT_TIMESTAMP |

### 外掛相依性

| Plugin | Required | Version | Purpose |
|--------|----------|---------|---------|
| FluentCart | ✅ Yes | Latest | 訂單和商品資料來源 |
| buygo-line-notify | ✅ Yes | 0.1.1+ | LINE UID 查詢 API |

**相依性檢查機制（建議）：**
```php
// 在 Plugin::init() 中檢查
if (!class_exists('\\BuygoLineNotify\\Plugin')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '出貨通知功能需要 buygo-line-notify 外掛。';
        echo '</p></div>';
    });
}
```

## 實作檢查清單

### 資料庫升級
- [ ] 在 `Database::upgrade_tables()` 新增 `estimated_delivery_at` 欄位
- [ ] 使用 `DESCRIBE` 檢查欄位是否已存在（idempotent）
- [ ] 欄位設為 `DATETIME NULL`（允許未設定）
- [ ] 在 `shipped_at` 之後插入（符合時間順序邏輯）
- [ ] 考慮是否需要索引（如果會按日期查詢）

### Action Hook 實作
- [ ] 在 `ShipmentService::mark_shipped()` 中觸發 `buygo/shipment_marked_shipped`
- [ ] 傳遞完整參數：`$shipment_id`, `$shipment`, `$order_ids`
- [ ] 在 `LineNotificationService` 監聽 hook（priority 10）
- [ ] 使用 `try-catch` 包裹通知邏輯（避免阻塞主流程）

### 通知模板管理
- [ ] 在 `NotificationTemplates::definitions()` 新增預設模板
- [ ] 模板 key：`shipment_shipped_notification`
- [ ] 支援變數：`{product_name}`, `{quantity}`, `{shipping_method}`, `{tracking_number}`, `{estimated_delivery_at}`
- [ ] 使用現有 `replace_placeholders()` 處理變數替換
- [ ] 在 Settings 頁面新增模板編輯 UI

### REST API 端點
- [ ] 不需要新增 API（使用現有 ShipmentAPI）
- [ ] 確認 `mark-shipped` 端點正確觸發 hook
- [ ] 驗證 nonce 和權限（`current_user_can('edit_posts')`）

### 測試
- [ ] 單元測試：`ShipmentService::mark_shipped()` 觸發正確 hook
- [ ] 單元測試：模板變數替換正確
- [ ] 整合測試：標記出貨後 LINE 通知發送
- [ ] 邊界測試：estimated_delivery_at 為 NULL 時的處理

## 效能考量

### 資料庫查詢優化

**現有架構已最佳化：**
- ShipmentService 使用 `$wpdb->prepare()` 防止 SQL injection
- NotificationTemplates 實作三層快取（static → object cache → database）
- LINE UID 查詢使用索引（`buygo_line_bindings.line_uid`）

**新增欄位影響：**
- `estimated_delivery_at` 欄位為 DATETIME NULL，不影響現有查詢
- 如需按日期查詢，建議新增索引：`KEY idx_estimated_delivery_at (estimated_delivery_at)`

### 通知發送最佳實務

**同步 vs 非同步：**
```php
// ✅ 推薦：同步發送（出貨通知屬於即時性高的交易通知）
add_action('buygo/shipment_marked_shipped', [$this, 'send_shipment_notification'], 10, 3);

// ❌ 不推薦：WordPress Cron（延遲不確定，可能數小時後才執行）
wp_schedule_single_event(time(), 'buygo_send_shipment_notification', [$shipment_id]);
```

**理由：**
- 出貨通知屬於交易通知，使用者期待即時收到
- LINE Messaging API 回應時間通常 <1 秒，不會顯著影響 HTTP 請求時間
- 使用 `try-catch` 確保通知失敗不影響出貨流程

**錯誤處理：**
```php
public function send_shipment_notification($shipment_id, $shipment, $order_ids) {
    try {
        // 發送通知邏輯
        $result = $this->lineService->push_message($line_uid, $message);

        if (is_wp_error($result)) {
            // 記錄錯誤但不中斷流程
            $this->debugService->log('LineNotification', '出貨通知發送失敗', [
                'shipment_id' => $shipment_id,
                'error' => $result->get_error_message()
            ], 'error');
        }
    } catch (\Exception $e) {
        // 捕捉異常，避免影響主流程
        $this->debugService->log('LineNotification', '出貨通知異常', [
            'shipment_id' => $shipment_id,
            'error' => $e->getMessage()
        ], 'error');
    }
}
```

## 信心度評估

| 領域 | 信心度 | 理由 |
|------|--------|------|
| 資料庫架構 (DATETIME) | **HIGH** | 基於 WordPress 核心慣例、MySQL 官方文件、多份業界最佳實務指南 |
| Action Hooks 模式 | **HIGH** | WordPress 官方文件、現有 codebase 已大量使用、社群標準 |
| LINE Messaging API | **HIGH** | LINE 官方文件、現有外掛已整合、v1.2 功能驗證 |
| 模板管理策略 | **HIGH** | 基於現有 NotificationTemplates 實作、已驗證的快取策略 |
| dbDelta 升級機制 | **HIGH** | WordPress 官方推薦、現有 Database::upgrade_tables() 實作模式 |
| REST API Nonce 驗證 | **HIGH** | WordPress 官方安全指南、2025 安全報告、現有 API 實作 |

## 資料來源

**高信心度來源（官方文件）：**
- [WordPress REST API Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) — REST API 驗證機制
- [WordPress Action Hooks Reference](https://developer.wordpress.org/plugins/hooks/actions/) — Action hooks 實作指南
- [WordPress Nonces API](https://developer.wordpress.org/apis/security/nonces/) — Nonce 安全最佳實務
- [LINE Messaging API Overview](https://developers.line.biz/en/docs/messaging-api/overview/) — LINE 官方 API 文件
- [LINE Notification Messages API](https://developers.line.biz/en/reference/line-notification-messages/) — 交易通知 API 規範
- [WordPress dbDelta Function](https://codex.wordpress.org/Creating_Tables_with_Plugins) — 資料庫升級官方指南

**中信心度來源（業界最佳實務）：**
- [MySQL DATETIME vs TIMESTAMP Comparison](https://www.pingcap.com/article/exploring-mysql-timestamp-vs-datetime-key-differences/) — PingCAP 技術文章，2025 更新
- [Storing Dates in MySQL Best Practices](https://accreditly.io/articles/storing-dates-and-times-in-mysql-best-practices-and-pitfalls) — Accreditly 資料庫設計指南
- [WordPress Database Schema Guide](https://jetpack.com/blog/wordpress-database/) — Jetpack 官方部落格
- [WordPress REST API Security 2025](https://oddjar.com/wordpress-rest-api-authentication-guide-2025/) — Odd Jar 安全實務指南
- [Database Timestamps Best Practices](https://medium.com/@abdelaz9z/best-practices-for-database-design-incorporating-timestamps-and-user-metadata-in-tables-2310527dd677) — Medium 技術文章

**低信心度來源（參考資料）：**
- [WordPress Plugin Database Updates Discussion](https://wordpress.org/support/topic/adding-a-new-field-in-database-upon-plugin-update/) — WordPress.org 論壇討論
- [LINE Notify Deprecation Notice](https://ke2b.com/en/line-notify-closing-alt/) — 第三方部落格，關於 LINE Notify 停止服務

**現有 Codebase 驗證：**
- `includes/class-database.php` — 現有 `upgrade_tables()` 實作模式
- `includes/services/class-notification-templates.php` — 現有模板管理和快取策略
- `includes/services/class-shipment-service.php` — 現有 ShipmentService 架構
- `includes/services/class-line-service.php` — 現有 LINE UID 查詢邏輯

## 與現有系統的整合點

### 1. ShipmentService 整合
**現有功能：** `mark_shipped()` 方法標記出貨單為已出貨
**整合方式：** 在標記成功後觸發 `do_action('buygo/shipment_marked_shipped', ...)`
**修改範圍：** 新增 1-2 行程式碼，不影響現有邏輯

### 2. NotificationTemplates 整合
**現有功能：** 管理 LINE 通知模板（商品上架、訂單建立、訂單取消）
**整合方式：** 在 `definitions()` 新增 `shipment_shipped_notification` 模板
**修改範圍：** 新增模板定義，不影響現有模板

### 3. LineService 整合
**現有功能：** 查詢 WordPress User 對應的 LINE UID
**整合方式：** 使用現有 `get_user_by_line_uid()` 查詢買家 LINE UID
**修改範圍：** 無需修改，直接使用現有 API

### 4. Database 整合
**現有功能：** `upgrade_tables()` 機制升級資料表結構
**整合方式：** 新增 `estimated_delivery_at` 欄位檢查和新增邏輯
**修改範圍：** 在 `upgrade_shipments_table()` 新增欄位檢查區塊

### 5. Settings API 整合
**現有功能：** 後台 Settings 頁面管理外掛設定
**整合方式：** 新增「出貨通知模板」設定區塊
**修改範圍：** 在 Settings Vue 元件新增 UI，後端 API 已支援

---
*Stack research for: WordPress E-Commerce 出貨通知系統*
*Researched: 2026-02-02*
*Researcher: GSD Project Researcher*
*Confidence: HIGH (基於官方文件、現有 codebase、業界最佳實務)*
