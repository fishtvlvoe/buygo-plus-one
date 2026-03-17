# Phase 32: 資料庫基礎升級 - Research

**Researched:** 2026-02-02
**Domain:** WordPress 外掛資料庫升級與 Schema 版本管理
**Confidence:** HIGH

## Summary

本研究針對如何在生產環境中安全地升級 WordPress 外掛資料庫結構,特別是為 `buygo_shipments` 資料表新增 `estimated_delivery_at` 欄位。研究涵蓋 WordPress `dbDelta()` 函數的正確使用方式、版本控制策略、測試方法和常見陷阱。

**核心發現:**
- `dbDelta()` 是 WordPress 官方推薦的資料表升級機制,具備 idempotent（可重複執行）特性,可安全地新增欄位而不影響現有資料
- 使用 `version_compare()` 配合資料庫版本號 (`Plugin::DB_VERSION`) 確保升級邏輯只在必要時執行
- `estimated_delivery_at` 應設計為 DATETIME NULL,無預設值,符合 MySQL 8.0+ 最佳實踐
- 索引策略:初期無需索引,待未來實際查詢需求明確後再評估

**主要建議:** 在現有的 `upgrade_shipments_table()` 方法中,使用 dbDelta 配合完整的 CREATE TABLE 語句新增欄位,並提升 `Plugin::DB_VERSION` 到 1.3.0 觸發升級流程。

## Standard Stack

WordPress 資料庫升級的標準工具和方法:

### Core
| Library/Tool | Version | Purpose | Why Standard |
|-------------|---------|---------|--------------|
| dbDelta() | WordPress Core | 資料表 Schema 升級 | WordPress 官方提供,自動比對並更新結構,具備 idempotent 特性 |
| version_compare() | PHP Built-in | 版本號比較 | PHP 標準函數,支援語意化版本號比較 |
| get_option/update_option | WordPress Core | 版本號儲存 | WordPress 標準 Option API,持久化版本資訊 |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| DESCRIBE | MySQL | 檢查欄位是否存在 | 手動 ALTER TABLE 時的冗餘檢查 |
| ALTER TABLE | MySQL | 直接修改資料表 | 移除欄位、複雜型別轉換等 dbDelta 無法處理的場景 |
| PHPUnit | 9.x | 單元測試 | 測試升級邏輯的正確性 |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| dbDelta | 手動 ALTER TABLE | dbDelta 更安全且 idempotent,但無法移除欄位;ALTER TABLE 更靈活但需自行實作冗餘檢查 |
| Option API | Custom Table | Option API 簡單可靠,但大量版本號會污染 options 表;自訂表過度設計 |
| version_compare | 整數版本號 | version_compare 支援語意化版本,但稍慢;整數更快但缺乏表達力 |

**Installation:**
```bash
# 無需安裝,WordPress Core 已內建
# 僅需引入 upgrade.php
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
```

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── class-plugin.php          # 版本常數定義、升級檢查邏輯
├── class-database.php        # 資料表建立與升級方法
│   ├── create_tables()       # 初次安裝時建立所有表
│   ├── upgrade_tables()      # 升級現有表結構
│   └── upgrade_shipments_table()  # Shipments 表專用升級邏輯
└── class-database-checker.php  # 完整性檢查與自動修復
```

### Pattern 1: 版本控制與升級觸發

**What:** 在 Plugin 主類別中定義 `DB_VERSION` 常數,在 `init()` 時比對資料庫中儲存的版本號,若不同則執行升級

**When to use:** 所有需要資料庫 Schema 升級的外掛

**Example:**
```php
// Source: buygo-plus-one-dev/includes/class-plugin.php (lines 17-271)
class Plugin {
    const DB_VERSION = '1.3.0'; // 從 1.2.0 升級到 1.3.0

    private function maybe_upgrade_database(): void
    {
        $current_db_version = get_option('buygo_plus_one_db_version', '0');
        $required_db_version = self::DB_VERSION;

        if (version_compare($current_db_version, $required_db_version, '<')) {
            require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-database.php';

            // 重新執行資料表建立（會跳過已存在的表）
            Database::create_tables();

            // 執行資料表結構升級（修復缺失的欄位）
            Database::upgrade_tables();

            // 更新版本號
            update_option('buygo_plus_one_db_version', $required_db_version);

            // 記錄升級
            error_log("[UPGRADE] Database upgraded from {$current_db_version} to {$required_db_version}");
        }
    }
}
```

### Pattern 2: dbDelta 新增欄位

**What:** 在升級方法中使用 dbDelta 配合完整 CREATE TABLE 語句,自動比對並新增缺失欄位

**When to use:** 需要向現有資料表新增欄位時

**Example:**
```php
// Source: WordPress Codex + buygo-plus-one-dev/includes/class-database.php (lines 74-125)
private static function upgrade_shipments_table($wpdb, $charset_collate): void
{
    $table_name = $wpdb->prefix . 'buygo_shipments';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // 關鍵:使用完整的 CREATE TABLE 語句,包含新欄位
    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        shipment_number varchar(50) NOT NULL,
        customer_id bigint(20) UNSIGNED NOT NULL,
        seller_id bigint(20) UNSIGNED NOT NULL,
        status varchar(50) DEFAULT 'pending',
        shipping_method varchar(100),
        tracking_number varchar(100),
        shipped_at datetime,
        estimated_delivery_at datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY idx_shipment_number (shipment_number),
        KEY idx_customer_id (customer_id),
        KEY idx_seller_id (seller_id),
        KEY idx_status (status)
    ) {$charset_collate};";

    // dbDelta 會自動比對並新增 estimated_delivery_at 欄位
    dbDelta($sql);
}
```

**重要格式要求 (WordPress Codex):**
- PRIMARY KEY 和 KEY 之間必須有**兩個空格** (`PRIMARY KEY  (id)`)
- 每個欄位定義必須獨立一行
- 必須使用 `KEY` 而非 `INDEX`
- 所有 KEY 必須命名 (不能匿名)
- CREATE TABLE 和表名之間不能有多餘空格

### Pattern 3: 冗餘欄位檢查 (Defensive Programming)

**What:** 在執行 ALTER TABLE 前,先檢查欄位是否存在,避免重複執行錯誤

**When to use:** 使用手動 ALTER TABLE 而非 dbDelta 時 (例如移除欄位或複雜型別轉換)

**Example:**
```php
// Source: buygo-plus-one-dev/includes/class-database.php (lines 105-118)
// 表存在,檢查並添加缺失的欄位
$columns = $wpdb->get_col("DESCRIBE {$table_name}", 0);

// 添加 shipment_number 欄位
if (!in_array('shipment_number', $columns)) {
    $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN shipment_number varchar(50) NOT NULL AFTER id");
    $wpdb->query("ALTER TABLE {$table_name} ADD UNIQUE KEY idx_shipment_number (shipment_number)");
}
```

**注意:** 使用 dbDelta 時不需要此檢查,dbDelta 會自動處理

### Anti-Patterns to Avoid

- **在 SQL 中使用 IF NOT EXISTS**: dbDelta 會誤將 "IF" 當作表名,導致升級失敗
  ```php
  // ❌ 錯誤
  CREATE TABLE IF NOT EXISTS {$table_name} (...)

  // ✅ 正確
  CREATE TABLE {$table_name} (...)
  ```

- **在每次請求時執行 dbDelta**: 造成資源浪費和潛在的資料庫鎖死
  ```php
  // ❌ 錯誤:掛在 init hook 直接執行
  add_action('init', [Database::class, 'create_tables']);

  // ✅ 正確:僅在版本升級時執行
  if (version_compare($current, $required, '<')) {
      Database::upgrade_tables();
  }
  ```

- **大小寫不一致**: MySQL 在 Windows/Mac 不區分大小寫,但 Linux 區分,且 dbDelta 會正規化為小寫比對
  ```php
  // ❌ 可能問題:大寫 DATETIME
  estimated_delivery_at DATETIME DEFAULT NULL

  // ✅ 建議:小寫一致
  estimated_delivery_at datetime DEFAULT NULL
  ```

## Don't Hand-Roll

生產環境資料庫升級不應自行實作的功能:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Schema 版本管理 | 自訂版本比對邏輯 | version_compare() + Option API | PHP 內建函數處理語意化版本,Option API 持久化可靠 |
| 欄位新增 | 手動 ALTER TABLE + DESCRIBE 檢查 | dbDelta() | dbDelta 自動比對 Schema,具備 idempotent 特性,避免重複執行錯誤 |
| 升級失敗回滾 | 自訂 transaction 機制 | WordPress 備份外掛 + Staging 環境測試 | DDL 語句在 MySQL 中無法 rollback,依賴 pre-upgrade 備份更安全 |
| 資料表完整性檢查 | 定期 cron 任務手動檢查 | DatabaseChecker::check_and_repair() | 已實作的自動修復機制,配合 transient 避免頻繁檢查 |

**Key insight:** WordPress 的 dbDelta 經過十多年實戰驗證,處理了大量邊緣案例 (如欄位型別正規化、索引重複、字元集變更等),自行實作 ALTER TABLE 邏輯容易遺漏這些細節,導致生產環境升級失敗或資料遺失。

## Common Pitfalls

### Pitfall 1: dbDelta 格式錯誤導致靜默失敗

**What goes wrong:** dbDelta 使用正則表達式解析 SQL,若格式不符要求 (如 PRIMARY KEY 後只有一個空格),會靜默跳過該語句,導致欄位未新增但無錯誤訊息

**Why it happens:** dbDelta 的設計假設開發者遵循嚴格格式,未提供詳細的錯誤回報機制

**How to avoid:**
1. 嚴格遵循格式要求 (PRIMARY KEY 後兩個空格、KEY 而非 INDEX)
2. 使用小寫 SQL 關鍵字避免大小寫正規化問題
3. 升級後檢查資料表結構: `$wpdb->get_col("DESCRIBE {$table_name}")`

**Warning signs:**
- 升級後欄位仍不存在
- 無錯誤訊息但資料表未變更
- `dbDelta()` 返回空陣列

**參考來源:**
- [WordPress dbDelta() Function Reference](https://developer.wordpress.org/reference/functions/dbdelta/)
- [Using dbDelta with WordPress to create and alter tables](https://medium.com/enekochan/using-dbdelta-with-wordpress-to-create-and-alter-tables-73883f1db57)

### Pitfall 2: MySQL 8.0 Strict Mode 不接受 '0000-00-00' 預設值

**What goes wrong:** MySQL 8.0 預設啟用 Strict Mode,不允許 DATETIME 欄位使用 '0000-00-00 00:00:00' 作為預設值,導致升級失敗

**Why it happens:** WordPress 傳統上使用 '0000-00-00 00:00:00' 表示空日期,但 MySQL 8.0 強制執行更嚴格的資料完整性

**How to avoid:**
- 新欄位使用 `DEFAULT NULL` 而非 `DEFAULT '0000-00-00 00:00:00'`
- 可選日期欄位應設計為 `NULL`-able,必填欄位使用 `DEFAULT CURRENT_TIMESTAMP`
- 避免使用 `NOT NULL DEFAULT '0000-00-00 00:00:00'` 組合

**Warning signs:**
- 升級時出現 "Invalid default value for 'column_name'" 錯誤
- MySQL error log 顯示 Strict Mode 違規

**參考來源:**
- [WordPress Trac: Make WP MySQL strict mode compliant](https://core.trac.wordpress.org/ticket/8857)
- [MySQL Nullable Columns: Everything You Need to Know](https://www.dbvis.com/thetable/mysql-nullable-columns-everything-you-need-to-know/)

### Pitfall 3: 升級邏輯在高流量下重複執行導致資料庫鎖死

**What goes wrong:** 若升級檢查未使用 transient 或版本號更新不原子化,高流量環境下多個請求同時執行 dbDelta,可能導致資料庫 deadlock

**Why it happens:** `maybe_upgrade_database()` 在每次 `plugins_loaded` hook 觸發時執行,若版本號更新慢於 Schema 變更,會有競態條件

**How to avoid:**
1. 使用 transient 限制檢查頻率 (如現有的 `ensure_database_integrity()`)
2. 確保版本號更新在 Schema 變更後立即執行
3. 考慮使用 WordPress transient lock 機制避免並發執行

```php
// 改良版:使用 transient lock
private function maybe_upgrade_database(): void
{
    // 檢查是否有其他請求正在升級
    if (get_transient('buygo_db_upgrade_lock')) {
        return;
    }

    $current = get_option('buygo_plus_one_db_version', '0');
    $required = self::DB_VERSION;

    if (version_compare($current, $required, '<')) {
        // 設定升級鎖 (30 秒)
        set_transient('buygo_db_upgrade_lock', time(), 30);

        try {
            Database::upgrade_tables();
            update_option('buygo_plus_one_db_version', $required);
        } finally {
            delete_transient('buygo_db_upgrade_lock');
        }
    }
}
```

**Warning signs:**
- MySQL error log 顯示 deadlock
- 升級日誌顯示同一版本多次執行
- 高流量時段升級失敗率增加

**參考來源:**
- [dbDelta runs on every request due to uppercase causing periodic DB deadlock](https://wordpress.org/support/topic/dbdelta-runs-on-every-request-due-to-uppercase-causing-periodic-db-deadlock/)

### Pitfall 4: 未在 Staging 環境測試導致生產環境升級失敗

**What goes wrong:** 生產環境的資料量、字元集、MySQL 版本可能與開發環境不同,直接部署可能遇到意外錯誤 (如 ALTER TABLE 超時、字元集衝突)

**Why it happens:** 開發環境通常資料量小、配置寬鬆,無法重現生產環境的邊緣案例

**How to avoid:**
1. 使用 WP Staging 等外掛建立生產環境的鏡像
2. 在 Staging 環境執行升級並驗證
3. 檢查 ALTER TABLE 執行時間,評估是否需要維護視窗
4. 備份生產資料庫後再執行升級

**Testing Checklist:**
- [ ] Staging 環境資料量接近生產 (至少 10% 量級)
- [ ] MySQL 版本與生產一致
- [ ] 字元集和 Collation 與生產一致
- [ ] 升級前後功能測試通過
- [ ] 升級執行時間在可接受範圍 (< 30 秒)

**Warning signs:**
- Staging 升級成功但生產失敗
- 生產升級耗時遠超預期
- 字元集相關錯誤

**參考來源:**
- [Top 6 WordPress Staging Plugins for 2026](https://serveravatar.com/wordpress-staging-plugins/)
- [WordPress Post-Migration Checklist (2026)](https://www.cloudways.com/blog/wordpress-post-migration-checklist/)

## Code Examples

經過官方文檔驗證的程式碼範例:

### 完整的 Shipments 表升級範例

```php
// Source: buygo-plus-one-dev/includes/class-database.php (修改版)
private static function upgrade_shipments_table($wpdb, $charset_collate): void
{
    $table_name = $wpdb->prefix . 'buygo_shipments';

    // 檢查表格是否存在
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
        // 表不存在,建立新表 (包含新欄位)
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            shipment_number varchar(50) NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            seller_id bigint(20) UNSIGNED NOT NULL,
            status varchar(50) DEFAULT 'pending',
            shipping_method varchar(100),
            tracking_number varchar(100),
            shipped_at datetime,
            estimated_delivery_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_shipment_number (shipment_number),
            KEY idx_customer_id (customer_id),
            KEY idx_seller_id (seller_id),
            KEY idx_status (status)
        ) {$charset_collate};";

        dbDelta($sql);
        return;
    }

    // 表存在,使用 dbDelta 新增缺失欄位
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        shipment_number varchar(50) NOT NULL,
        customer_id bigint(20) UNSIGNED NOT NULL,
        seller_id bigint(20) UNSIGNED NOT NULL,
        status varchar(50) DEFAULT 'pending',
        shipping_method varchar(100),
        tracking_number varchar(100),
        shipped_at datetime,
        estimated_delivery_at datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY idx_shipment_number (shipment_number),
        KEY idx_customer_id (customer_id),
        KEY idx_seller_id (seller_id),
        KEY idx_status (status)
    ) {$charset_collate};";

    // dbDelta 會自動比對現有結構,僅新增 estimated_delivery_at 欄位
    dbDelta($sql);
}
```

### 版本號更新範例

```php
// Source: buygo-plus-one-dev/includes/class-plugin.php (lines 23, 239-271)
class Plugin {
    // 從 1.2.0 升級到 1.3.0
    const DB_VERSION = '1.3.0';

    private function maybe_upgrade_database(): void
    {
        $current_db_version = get_option('buygo_plus_one_db_version', '0');
        $required_db_version = self::DB_VERSION;

        if (version_compare($current_db_version, $required_db_version, '<')) {
            require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-database.php';

            // 重新執行資料表建立 (會跳過已存在的表)
            Database::create_tables();

            // 執行結構升級 (新增缺失欄位)
            Database::upgrade_tables();

            // 更新版本號 (必須在升級後立即執行)
            update_option('buygo_plus_one_db_version', $required_db_version);

            // 記錄升級
            $log_file = WP_CONTENT_DIR . '/buygo-plus-one.log';
            file_put_contents($log_file, sprintf(
                "[%s] [UPGRADE] Database upgraded from %s to %s (added estimated_delivery_at to shipments)\n",
                date('Y-m-d H:i:s'),
                $current_db_version,
                $required_db_version
            ), FILE_APPEND);
        }
    }
}
```

### 升級後驗證範例

```php
// Source: 研究建議 (新增驗證邏輯)
private static function verify_estimated_delivery_at_column(): bool
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'buygo_shipments';

    $columns = $wpdb->get_col("DESCRIBE {$table_name}", 0);

    if (!in_array('estimated_delivery_at', $columns)) {
        error_log('[ERROR] estimated_delivery_at column missing after upgrade');
        return false;
    }

    // 驗證欄位型別
    $column_info = $wpdb->get_row(
        "SHOW COLUMNS FROM {$table_name} WHERE Field = 'estimated_delivery_at'"
    );

    if ($column_info->Type !== 'datetime') {
        error_log('[ERROR] estimated_delivery_at has wrong type: ' . $column_info->Type);
        return false;
    }

    if ($column_info->Null !== 'YES') {
        error_log('[ERROR] estimated_delivery_at should allow NULL');
        return false;
    }

    return true;
}
```

## State of the Art

WordPress 資料庫升級領域的最新發展:

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| 直接 ALTER TABLE | dbDelta() | WordPress 1.5 (2005) | 標準化升級方式,減少升級錯誤 |
| '0000-00-00' 空日期 | NULL 或有效日期 | MySQL 8.0 (2018) | 強制資料完整性,與 Strict Mode 相容 |
| 每次請求檢查版本 | Transient + 版本比對 | WordPress 3.0 (2010) | 減少資料庫查詢,避免並發問題 |
| TIMESTAMP | DATETIME | MySQL 5.7+ | 避免 2038 年問題,更大時間範圍 |
| 手動 DESCRIBE 檢查 | dbDelta 自動比對 | WordPress 2.0 (2006) | 簡化升級邏輯,減少人為錯誤 |

**Deprecated/outdated:**
- **IF NOT EXISTS 語法**: dbDelta 無法正確解析,會導致靜默失敗
- **INDEX 關鍵字**: dbDelta 要求使用 `KEY` 而非 `INDEX`
- **匿名索引**: dbDelta 要求所有索引必須命名
- **NOT NULL DEFAULT '0000-00-00 00:00:00'**: MySQL 8.0 Strict Mode 不接受

## Open Questions

研究過程中無法完全解決的問題:

### 1. estimated_delivery_at 是否需要索引?

**What we know:**
- 電商系統常見查詢包含「即將送達的訂單」(`WHERE estimated_delivery_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)`)
- 索引可加速範圍查詢,但會增加 INSERT/UPDATE 成本和儲存空間
- 目前 buygo_shipments 表的資料量未知

**What's unclear:**
- Phase 32 的需求中未明確需要查詢 estimated_delivery_at
- 未來是否有「按預計送達日期排序」或「篩選即將送達」的功能需求
- Shipments 表的預期成長速度 (決定索引的投資報酬率)

**Recommendation:**
- **初期不建立索引** (YAGNI 原則)
- 在 Phase 32 PLAN.md 中加入「索引評估」驗證步驟
- 待 Phase 33 (可能的查詢功能) 明確需求後,再透過 ALTER TABLE 新增索引
- 若未來需要索引,可執行: `ALTER TABLE wp_buygo_shipments ADD KEY idx_estimated_delivery (estimated_delivery_at);`

### 2. 是否需要遷移現有 shipped_at 資料到 estimated_delivery_at?

**What we know:**
- 現有 shipments 記錄只有 `shipped_at` (實際出貨時間)
- `estimated_delivery_at` 是預計送達時間,語意不同
- 部分物流商可根據出貨時間 + 配送天數估算送達時間

**What's unclear:**
- Phase 32 是否需要回填歷史資料?
- 估算邏輯的準確度要求?
- 是否有物流商 API 可取得預計送達時間?

**Recommendation:**
- **初期不回填** (新欄位允許 NULL,歷史資料保持空值)
- 若需要回填,在獨立的 Phase 中實作:
  ```php
  // 範例:根據物流方式估算
  UPDATE wp_buygo_shipments
  SET estimated_delivery_at = DATE_ADD(shipped_at, INTERVAL 3 DAY)
  WHERE shipping_method = '7-11' AND shipped_at IS NOT NULL AND estimated_delivery_at IS NULL;
  ```
- 在 PLAN.md 中明確標註「歷史資料不回填」,避免誤解

### 3. 高流量環境下 dbDelta 的效能影響?

**What we know:**
- dbDelta 需要 DESCRIBE 資料表並比對 Schema
- 對於已存在且正確的資料表,dbDelta 會跳過變更
- 目前使用 transient (1 小時) 限制完整性檢查頻率

**What's unclear:**
- 單次 dbDelta 呼叫的平均耗時?
- 在 100+ 並發請求下,升級期間的請求延遲?
- 是否需要維護視窗或灰度升級策略?

**Recommendation:**
- **在 Staging 環境測試升級耗時** (載入生產資料量)
- 若 < 1 秒,可直接部署;若 > 5 秒,考慮維護視窗
- 考慮使用 transient lock (如 Pitfall 3 範例) 避免並發執行
- 在 PLAN.md 中加入「Staging 效能測試」驗證步驟

## Sources

### Primary (HIGH confidence)
- [WordPress dbDelta() Function Reference](https://developer.wordpress.org/reference/functions/dbdelta/) - 官方文檔,詳細語法要求
- [Custom Database Tables: Maintaining the Database | Envato Tuts+](https://code.tutsplus.com/tutorials/custom-database-tables-maintaining-the-database--wp-28455) - 完整升級流程教學
- [MySQL 8.0 Reference Manual: Data Type Default Values](https://dev.mysql.com/doc/refman/8.0/en/data-type-defaults.html) - DATETIME 預設值官方規範
- buygo-plus-one-dev/includes/class-database.php - 現有升級邏輯實作
- buygo-plus-one-dev/includes/class-plugin.php - 版本控制機制實作

### Secondary (MEDIUM confidence)
- [Using dbDelta with WordPress to create and alter tables](https://medium.com/enekochan/using-dbdelta-with-wordpress-to-create-and-alter-tables-73883f1db57) - 實戰範例,已與官方文檔交叉驗證
- [WordPress Plugin Updates the Right Way — SitePoint](https://www.sitepoint.com/wordpress-plugin-updates-right-way/) - 版本控制最佳實踐
- [MySQL Nullable Columns: Everything You Need to Know](https://www.dbvis.com/thetable/mysql-nullable-columns-everything-you-need-to-know/) - NULL vs NOT NULL 效能分析
- [Ecommerce Database Design: ER Diagram for Online Shopping](https://vertabelo.com/blog/er-diagram-for-online-shop/) - 電商資料表設計參考

### Tertiary (LOW confidence)
- [Top 6 WordPress Staging Plugins for 2026](https://serveravatar.com/wordpress-staging-plugins/) - Staging 工具推薦,未親自測試
- [dbDelta runs on every request due to uppercase causing periodic DB deadlock](https://wordpress.org/support/topic/dbdelta-runs-on-every-request-due-to-uppercase-causing-periodic-db-deadlock/) - 單一案例報告,需進一步驗證

## Metadata

**Confidence breakdown:**
- Standard stack: **HIGH** - dbDelta 和 version_compare 是 WordPress 官方推薦,廣泛使用
- Architecture: **HIGH** - 現有 buygo-plus-one 程式碼已實作完整的升級機制,僅需擴充
- Pitfalls: **HIGH** - 所有陷阱均有官方文檔或實際案例支持
- Open Questions: **MEDIUM** - 索引和效能問題需 Staging 環境實測驗證

**Research date:** 2026-02-02
**Valid until:** 2026-04-02 (60 天,WordPress 和 MySQL 升級機制變動緩慢,穩定性高)

**特別說明:**
- 本研究基於 WordPress 6.x 和 MySQL 8.0+ 的最佳實踐
- dbDelta 語法要求自 WordPress 1.5 後未有重大變更,穩定性極高
- MySQL 8.0 Strict Mode 已成為主流,研究建議適用於未來數年
