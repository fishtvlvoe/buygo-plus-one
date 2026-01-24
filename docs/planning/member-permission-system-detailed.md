# 會員權限管理系統 - 詳細實作規格

> **基於**: [calm-mapping-hedgehog.md](~/.claude/plans/calm-mapping-hedgehog.md)
> **制定日期**: 2026-01-24
> **執行時機**: 環境穩定後 + API 權限修復完成後
> **預估總工時**: 8-10 小時

---

## 🎯 系統目標

### 核心需求

1. **多賣家支援**: 一個 WordPress 站點可能有多個賣家，每個賣家有自己的小幫手
2. **權限隔離**: A 賣家的小幫手只能看到 A 的資料，不能看到 B 的資料
3. **角色管理**: 清楚區分管理員、BuyGo 管理員、小幫手三種角色
4. **UI 友善**: 賣家可以輕鬆新增/移除小幫手，無需理解技術細節

### 當前問題

**現況**:
- 小幫手資料存在 WordPress Options (`buygo_helpers`)
- 所有賣家共用同一份小幫手列表
- 無法區分「誰的小幫手」

**問題場景**:
```
賣家 A 新增小幫手 User #123
賣家 B 也看到 User #123 在他的小幫手列表中
→ 這是錯誤的，應該要隔離
```

---

## 📊 資料庫設計

### 新資料表: `wp_buygo_helpers`

#### 表結構

```sql
CREATE TABLE wp_buygo_helpers (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED NOT NULL COMMENT '小幫手的 WP User ID',
    seller_id bigint(20) UNSIGNED NOT NULL COMMENT '賣家的 WP User ID',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_helper (user_id, seller_id) COMMENT '同一個人只能被同一個賣家新增一次',
    KEY idx_seller (seller_id) COMMENT '快速查詢某賣家的所有小幫手',
    KEY idx_user (user_id) COMMENT '快速查詢某使用者是誰的小幫手'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 欄位說明

| 欄位 | 類型 | 說明 | 範例 |
|------|------|------|------|
| `id` | bigint(20) | 自動遞增主鍵 | 1, 2, 3... |
| `user_id` | bigint(20) | 小幫手的 WordPress User ID | 456 (假設 User #456 是小幫手) |
| `seller_id` | bigint(20) | 賣家的 WordPress User ID | 123 (假設 User #123 是賣家) |
| `created_at` | datetime | 建立時間 | 2026-01-24 10:30:00 |
| `updated_at` | datetime | 更新時間 | 2026-01-24 10:30:00 |

#### 索引設計

1. **PRIMARY KEY (`id`)**
   - 用途: 唯一識別每一筆記錄
   - 類型: 叢集索引 (Clustered Index)

2. **UNIQUE KEY `unique_helper` (`user_id`, `seller_id`)**
   - 用途: 防止同一個人被同一個賣家重複新增
   - 範例: User #456 不能被 Seller #123 新增兩次
   - 但 User #456 可以同時是 Seller #123 和 Seller #789 的小幫手

3. **KEY `idx_seller` (`seller_id`)**
   - 用途: 快速查詢「某賣家的所有小幫手」
   - 查詢範例:
     ```sql
     SELECT * FROM wp_buygo_helpers WHERE seller_id = 123;
     ```

4. **KEY `idx_user` (`user_id`)**
   - 用途: 快速查詢「某使用者是誰的小幫手」
   - 查詢範例:
     ```sql
     SELECT * FROM wp_buygo_helpers WHERE user_id = 456;
     ```

#### 資料範例

```sql
-- 假設賣家 A (User #10) 有 2 個小幫手
INSERT INTO wp_buygo_helpers (user_id, seller_id) VALUES (20, 10);
INSERT INTO wp_buygo_helpers (user_id, seller_id) VALUES (21, 10);

-- 假設賣家 B (User #30) 有 1 個小幫手
INSERT INTO wp_buygo_helpers (user_id, seller_id) VALUES (40, 30);

-- 假設 User #50 同時是賣家 A 和賣家 B 的小幫手（合法）
INSERT INTO wp_buygo_helpers (user_id, seller_id) VALUES (50, 10);
INSERT INTO wp_buygo_helpers (user_id, seller_id) VALUES (50, 30);

-- 嘗試重複新增（會失敗，因為 UNIQUE KEY）
INSERT INTO wp_buygo_helpers (user_id, seller_id) VALUES (20, 10);
-- Error: Duplicate entry '20-10' for key 'unique_helper'
```

#### 查詢範例

**查詢賣家 A (User #10) 的所有小幫手**:
```sql
SELECT u.ID, u.user_login, u.user_email, h.created_at
FROM wp_buygo_helpers h
INNER JOIN wp_users u ON h.user_id = u.ID
WHERE h.seller_id = 10
ORDER BY h.created_at DESC;
```

**查詢 User #50 是誰的小幫手**:
```sql
SELECT s.ID, s.user_login, s.display_name
FROM wp_buygo_helpers h
INNER JOIN wp_users s ON h.seller_id = s.ID
WHERE h.user_id = 50;
```

**檢查 User #20 是否是賣家 A 的小幫手**:
```sql
SELECT COUNT(*) FROM wp_buygo_helpers
WHERE user_id = 20 AND seller_id = 10;
-- 結果: 1 (是) 或 0 (不是)
```

---

## 🔧 後端實作細節

### Phase 1.1: 建立資料表

**檔案**: `includes/class-database.php`

#### 修改位置

在 `create_tables()` 方法中，於現有的資料表建立邏輯後新增：

```php
public static function create_tables(): void
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // 現有的資料表建立邏輯
    self::create_allocation_items_table($wpdb, $charset_collate);
    self::create_shipments_table($wpdb, $charset_collate);

    // ✅ 新增這行
    self::create_helpers_table($wpdb, $charset_collate);
}
```

#### 新增方法

```php
/**
 * 建立小幫手關聯資料表
 *
 * @param wpdb $wpdb WordPress 資料庫物件
 * @param string $charset_collate 字元集設定
 * @return void
 */
private static function create_helpers_table($wpdb, $charset_collate): void
{
    $table_name = $wpdb->prefix . 'buygo_helpers';

    // 檢查資料表是否已存在
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
        error_log("BuyGo+1: Table {$table_name} already exists, skipping creation.");
        return;
    }

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL COMMENT '小幫手的 WP User ID',
        seller_id bigint(20) UNSIGNED NOT NULL COMMENT '賣家的 WP User ID',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_helper (user_id, seller_id),
        KEY idx_seller (seller_id),
        KEY idx_user (user_id)
    ) {$charset_collate};";

    dbDelta($sql);

    // 記錄建立結果
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
        error_log("BuyGo+1: Table {$table_name} created successfully.");
    } else {
        error_log("BuyGo+1: ERROR - Failed to create table {$table_name}.");
    }
}
```

#### 資料遷移策略（可選）

如果需要將現有的 `buygo_helpers` Option 資料遷移到新資料表：

```php
/**
 * 遷移舊資料（從 Options 到資料表）
 *
 * 注意: 舊資料不包含 seller_id，所以需要決定如何處理
 * 建議: 將所有舊的小幫手都歸屬給第一個管理員
 */
private static function migrate_old_helpers_data(): void
{
    global $wpdb;

    // 取得舊資料
    $old_helpers = get_option('buygo_helpers', []);
    if (empty($old_helpers)) {
        return;
    }

    // 找出第一個 WordPress 管理員
    $admin_users = get_users(['role' => 'administrator', 'number' => 1]);
    if (empty($admin_users)) {
        error_log("BuyGo+1: No admin user found for migration.");
        return;
    }
    $default_seller_id = $admin_users[0]->ID;

    $table_name = $wpdb->prefix . 'buygo_helpers';
    $migrated_count = 0;

    foreach ($old_helpers as $user_id) {
        // 檢查是否已存在
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND seller_id = %d",
            $user_id,
            $default_seller_id
        ));

        if ($exists > 0) {
            continue;
        }

        // 插入到新資料表
        $result = $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'seller_id' => $default_seller_id,
            ],
            ['%d', '%d']
        );

        if ($result) {
            $migrated_count++;
        }
    }

    error_log("BuyGo+1: Migrated {$migrated_count} helpers to new table.");

    // 可選: 備份舊資料後刪除
    // update_option('buygo_helpers_backup', $old_helpers);
    // delete_option('buygo_helpers');
}
```

---

### Phase 1.2: 修改 SettingsService

**檔案**: `includes/services/class-settings-service.php`

#### 修改 #1: get_helpers() 方法

**當前程式碼** (約第 235-256 行):
```php
public function get_helpers(): array
{
    $helpers = get_option('buygo_helpers', []);

    if (!is_array($helpers)) {
        return [];
    }

    $helper_data = [];
    foreach ($helpers as $user_id) {
        $user = get_userdata($user_id);
        if ($user) {
            $helper_data[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
            ];
        }
    }

    return $helper_data;
}
```

**修改後**:
```php
/**
 * 取得小幫手列表（針對特定賣家）
 *
 * @param int|null $seller_id 賣家 ID，若為 null 則使用當前使用者
 * @return array 小幫手資料陣列
 */
public function get_helpers(?int $seller_id = null): array
{
    global $wpdb;

    // 若未指定 seller_id，使用當前登入使用者
    if ($seller_id === null) {
        $seller_id = get_current_user_id();
    }

    // 檢查新資料表是否存在
    $table_name = $wpdb->prefix . 'buygo_helpers';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

    if (!$table_exists) {
        // 向後相容：如果新資料表不存在，使用舊的 Option API
        error_log("BuyGo+1: Helpers table not found, falling back to options.");
        return $this->get_helpers_from_options();
    }

    // 從資料表查詢
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT u.ID, u.display_name, u.user_email, h.created_at
         FROM {$table_name} h
         INNER JOIN {$wpdb->users} u ON h.user_id = u.ID
         WHERE h.seller_id = %d
         ORDER BY h.created_at DESC",
        $seller_id
    ));

    if ($wpdb->last_error) {
        error_log("BuyGo+1: Database error in get_helpers: " . $wpdb->last_error);
        return [];
    }

    $helper_data = [];
    foreach ($results as $row) {
        $helper_data[] = [
            'id' => (int) $row->ID,
            'name' => $row->display_name,
            'email' => $row->user_email,
            'added_at' => $row->created_at,
        ];
    }

    return $helper_data;
}

/**
 * 舊版方法：從 Options 讀取小幫手
 * 用於向後相容
 *
 * @return array
 */
private function get_helpers_from_options(): array
{
    $helpers = get_option('buygo_helpers', []);

    if (!is_array($helpers)) {
        return [];
    }

    $helper_data = [];
    foreach ($helpers as $user_id) {
        $user = get_userdata($user_id);
        if ($user) {
            $helper_data[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'added_at' => null, // 舊資料沒有時間戳記
            ];
        }
    }

    return $helper_data;
}
```

#### 修改 #2: add_helper() 方法

**當前程式碼** (約第 265-293 行):
```php
public function add_helper(int $user_id): bool
{
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    $helpers = get_option('buygo_helpers', []);
    if (!is_array($helpers)) {
        $helpers = [];
    }

    if (in_array($user_id, $helpers)) {
        return false; // 已存在
    }

    $helpers[] = $user_id;
    update_option('buygo_helpers', $helpers);

    // 新增角色
    $user->add_role('buygo_helper');

    return true;
}
```

**修改後**:
```php
/**
 * 新增小幫手（針對特定賣家）
 *
 * @param int $user_id 要新增為小幫手的使用者 ID
 * @param int|null $seller_id 賣家 ID，若為 null 則使用當前使用者
 * @return bool|WP_Error 成功返回 true，失敗返回 WP_Error
 */
public function add_helper(int $user_id, ?int $seller_id = null)
{
    global $wpdb;

    // 若未指定 seller_id，使用當前登入使用者
    if ($seller_id === null) {
        $seller_id = get_current_user_id();
    }

    // 驗證使用者存在
    $user = get_userdata($user_id);
    if (!$user) {
        return new \WP_Error('invalid_user', '使用者不存在');
    }

    // 防止將自己新增為小幫手
    if ($user_id === $seller_id) {
        return new \WP_Error('self_assignment', '不能將自己新增為小幫手');
    }

    // 檢查新資料表是否存在
    $table_name = $wpdb->prefix . 'buygo_helpers';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

    if (!$table_exists) {
        // 向後相容：使用舊的 Option API
        return $this->add_helper_to_options($user_id);
    }

    // 檢查是否已存在
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND seller_id = %d",
        $user_id,
        $seller_id
    ));

    if ($exists > 0) {
        return new \WP_Error('already_exists', '此使用者已經是您的小幫手');
    }

    // 插入資料
    $result = $wpdb->insert(
        $table_name,
        [
            'user_id' => $user_id,
            'seller_id' => $seller_id,
        ],
        ['%d', '%d']
    );

    if ($result === false) {
        error_log("BuyGo+1: Failed to add helper: " . $wpdb->last_error);
        return new \WP_Error('database_error', '新增小幫手失敗');
    }

    // 新增 WordPress 角色
    $user->add_role('buygo_helper');

    // 記錄日誌
    error_log("BuyGo+1: User #{$user_id} added as helper for Seller #{$seller_id}");

    return true;
}

/**
 * 舊版方法：新增小幫手到 Options
 * 用於向後相容
 *
 * @param int $user_id
 * @return bool|WP_Error
 */
private function add_helper_to_options(int $user_id)
{
    $user = get_userdata($user_id);
    if (!$user) {
        return new \WP_Error('invalid_user', '使用者不存在');
    }

    $helpers = get_option('buygo_helpers', []);
    if (!is_array($helpers)) {
        $helpers = [];
    }

    if (in_array($user_id, $helpers)) {
        return new \WP_Error('already_exists', '此使用者已經是小幫手');
    }

    $helpers[] = $user_id;
    update_option('buygo_helpers', $helpers);

    $user->add_role('buygo_helper');

    return true;
}
```

#### 修改 #3: remove_helper() 方法

**當前程式碼** (約第 301-323 行):
```php
public function remove_helper(int $user_id): bool
{
    $helpers = get_option('buygo_helpers', []);
    if (!is_array($helpers)) {
        return false;
    }

    $key = array_search($user_id, $helpers);
    if ($key === false) {
        return false;
    }

    unset($helpers[$key]);
    update_option('buygo_helpers', array_values($helpers));

    // 移除角色
    $user = get_userdata($user_id);
    if ($user) {
        $user->remove_role('buygo_helper');
    }

    return true;
}
```

**修改後**:
```php
/**
 * 移除小幫手（針對特定賣家）
 *
 * @param int $user_id 要移除的小幫手使用者 ID
 * @param int|null $seller_id 賣家 ID，若為 null 則使用當前使用者
 * @return bool|WP_Error 成功返回 true，失敗返回 WP_Error
 */
public function remove_helper(int $user_id, ?int $seller_id = null)
{
    global $wpdb;

    // 若未指定 seller_id，使用當前登入使用者
    if ($seller_id === null) {
        $seller_id = get_current_user_id();
    }

    // 檢查新資料表是否存在
    $table_name = $wpdb->prefix . 'buygo_helpers';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

    if (!$table_exists) {
        // 向後相容：使用舊的 Option API
        return $this->remove_helper_from_options($user_id);
    }

    // 刪除記錄
    $result = $wpdb->delete(
        $table_name,
        [
            'user_id' => $user_id,
            'seller_id' => $seller_id,
        ],
        ['%d', '%d']
    );

    if ($result === false) {
        error_log("BuyGo+1: Failed to remove helper: " . $wpdb->last_error);
        return new \WP_Error('database_error', '移除小幫手失敗');
    }

    if ($result === 0) {
        return new \WP_Error('not_found', '此使用者不是您的小幫手');
    }

    // 檢查此使用者是否還是其他賣家的小幫手
    $other_sellers = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
        $user_id
    ));

    // 如果不再是任何人的小幫手，移除角色
    if ($other_sellers == 0) {
        $user = get_userdata($user_id);
        if ($user) {
            $user->remove_role('buygo_helper');
            error_log("BuyGo+1: Removed buygo_helper role from User #{$user_id}");
        }
    }

    // 記錄日誌
    error_log("BuyGo+1: User #{$user_id} removed as helper for Seller #{$seller_id}");

    return true;
}

/**
 * 舊版方法：從 Options 移除小幫手
 * 用於向後相容
 *
 * @param int $user_id
 * @return bool|WP_Error
 */
private function remove_helper_from_options(int $user_id)
{
    $helpers = get_option('buygo_helpers', []);
    if (!is_array($helpers)) {
        return new \WP_Error('not_found', '找不到小幫手列表');
    }

    $key = array_search($user_id, $helpers);
    if ($key === false) {
        return new \WP_Error('not_found', '此使用者不是小幫手');
    }

    unset($helpers[$key]);
    update_option('buygo_helpers', array_values($helpers));

    $user = get_userdata($user_id);
    if ($user) {
        $user->remove_role('buygo_helper');
    }

    return true;
}
```

---

## 🔌 API 層修改

### Phase 2.1: 統一權限檢查

**檔案**: `includes/api/class-api.php`

#### 修改 check_permission()

**位置**: 約第 47-59 行

```php
/**
 * 檢查使用者是否有權限訪問 API
 *
 * @return bool
 */
public static function check_permission(): bool
{
    if (!is_user_logged_in()) {
        return false;
    }

    return current_user_can('manage_options')      // WordPress 管理員
        || current_user_can('buygo_admin')         // BuyGo 管理員
        || current_user_can('buygo_helper');       // 小幫手
}
```

#### 新增 check_admin_permission()

```php
/**
 * 檢查使用者是否有管理員權限（可新增小幫手）
 *
 * @return bool
 */
public static function check_admin_permission(): bool
{
    if (!is_user_logged_in()) {
        return false;
    }

    return current_user_can('manage_options')      // WordPress 管理員
        || current_user_can('buygo_add_helper');   // 有新增小幫手能力的角色
}
```

### Phase 2.2: Settings API 權限差異化

**檔案**: `includes/api/class-settings-api.php`

假設當前有以下端點（需要實際檢查檔案確認）：

```php
public function register_routes(): void
{
    $namespace = 'buygo-plus-one/v1';

    // 讀取設定 - 所有 BuyGo 成員都可以
    register_rest_route($namespace, '/settings', [
        'methods' => 'GET',
        'callback' => [$this, 'get_settings'],
        'permission_callback' => [API::class, 'check_permission'], // ✅ 修改
    ]);

    // 修改設定 - 只有管理員
    register_rest_route($namespace, '/settings', [
        'methods' => 'POST',
        'callback' => [$this, 'update_settings'],
        'permission_callback' => [API::class, 'check_admin_permission'], // ✅ 修改
    ]);

    // 取得小幫手列表 - 所有 BuyGo 成員都可以（但只能看到自己的）
    register_rest_route($namespace, '/settings/helpers', [
        'methods' => 'GET',
        'callback' => [$this, 'get_helpers'],
        'permission_callback' => [API::class, 'check_permission'], // ✅ 修改
    ]);

    // 新增小幫手 - 只有管理員
    register_rest_route($namespace, '/settings/helpers', [
        'methods' => 'POST',
        'callback' => [$this, 'add_helper'],
        'permission_callback' => [API::class, 'check_admin_permission'], // ✅ 修改
    ]);

    // 刪除小幫手 - 只有管理員
    register_rest_route($namespace, '/settings/helpers/(?P<user_id>\d+)', [
        'methods' => 'DELETE',
        'callback' => [$this, 'remove_helper'],
        'permission_callback' => [API::class, 'check_admin_permission'], // ✅ 修改
    ]);
}
```

#### API 回呼方法範例

```php
/**
 * API: 取得小幫手列表
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
public function get_helpers(\WP_REST_Request $request)
{
    $settings_service = Settings_Service::getInstance();
    $current_user_id = get_current_user_id();

    // 自動使用當前使用者作為 seller_id
    $helpers = $settings_service->get_helpers($current_user_id);

    return new \WP_REST_Response([
        'success' => true,
        'data' => $helpers,
    ], 200);
}

/**
 * API: 新增小幫手
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
public function add_helper(\WP_REST_Request $request)
{
    $user_id = $request->get_param('user_id');

    if (!$user_id || !is_numeric($user_id)) {
        return new \WP_Error(
            'invalid_parameter',
            '缺少或無效的 user_id 參數',
            ['status' => 400]
        );
    }

    $settings_service = Settings_Service::getInstance();
    $current_user_id = get_current_user_id();

    $result = $settings_service->add_helper((int) $user_id, $current_user_id);

    if (is_wp_error($result)) {
        return new \WP_Error(
            $result->get_error_code(),
            $result->get_error_message(),
            ['status' => 400]
        );
    }

    return new \WP_REST_Response([
        'success' => true,
        'message' => '小幫手新增成功',
    ], 200);
}

/**
 * API: 移除小幫手
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
public function remove_helper(\WP_REST_Request $request)
{
    $user_id = $request->get_param('user_id');

    if (!$user_id || !is_numeric($user_id)) {
        return new \WP_Error(
            'invalid_parameter',
            '缺少或無效的 user_id 參數',
            ['status' => 400]
        );
    }

    $settings_service = Settings_Service::getInstance();
    $current_user_id = get_current_user_id();

    $result = $settings_service->remove_helper((int) $user_id, $current_user_id);

    if (is_wp_error($result)) {
        return new \WP_Error(
            $result->get_error_code(),
            $result->get_error_message(),
            ['status' => 400]
        );
    }

    return new \WP_REST_Response([
        'success' => true,
        'message' => '小幫手移除成功',
    ], 200);
}
```

---

## 🎨 前端 UI 實作

### Phase 3: Settings 頁面改造

**檔案**: `admin/partials/settings.php`

#### UI 變更清單

1. ✅ **重新命名「小幫手管理」→「會員權限管理」**
2. ✅ **小幫手角色隱藏此區塊**（根據 `isAdmin` 狀態）
3. ✅ **顯示「新增時間」欄位**（新資料表有 `created_at`）
4. ✅ **改善錯誤訊息顯示**（API 返回 WP_Error）

#### 具體修改建議（詳見檔案後再確認行號）

**標題區塊**:
```html
<!-- 修改前 (約第 611 行) -->
<h2 class="text-lg font-semibold text-slate-900">👥 小幫手管理</h2>

<!-- 修改後 -->
<h2 class="text-lg font-semibold text-slate-900">
    <svg class="w-5 h-5 inline-block mr-2 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
    </svg>
    會員權限管理
</h2>
```

**權限控制**:
```html
<!-- 只有管理員可以看到會員權限管理區塊 -->
<div v-if="isAdmin" class="settings-section">
    <h2>會員權限管理</h2>
    <!-- ... 內容 -->
</div>
```

**小幫手列表表格**:
```html
<table class="w-full">
    <thead>
        <tr class="bg-slate-50 border-b border-slate-200">
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">姓名</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Email</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">新增時間</th> <!-- ✅ 新增欄位 -->
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase">操作</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
        <tr v-for="helper in helpers" :key="helper.id" class="hover:bg-slate-50">
            <td class="px-4 py-4 text-sm text-slate-900">{{ helper.name }}</td>
            <td class="px-4 py-4 text-sm text-slate-600">{{ helper.email }}</td>
            <td class="px-4 py-4 text-sm text-slate-600">{{ formatDate(helper.added_at) }}</td> <!-- ✅ 新增欄位 -->
            <td class="px-4 py-4">
                <button @click="removeHelper(helper.id)" class="text-red-600 hover:text-red-700 text-sm font-medium">
                    移除
                </button>
            </td>
        </tr>
    </tbody>
</table>
```

**Vue 方法新增**:
```javascript
// 在 Vue setup() 中新增
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('zh-TW', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// 記得在 return 中導出
return {
    // ... 其他
    formatDate,
};
```

**錯誤訊息改善**:
```javascript
async function removeHelper(userId) {
    if (!confirm('確定要移除此小幫手？')) {
        return;
    }

    try {
        const response = await fetch(`/wp-json/buygo-plus-one/v1/settings/helpers/${userId}`, {
            method: 'DELETE',
            headers: {
                'X-WP-Nonce': wpNonce,
            },
        });

        const data = await response.json();

        if (!response.ok) {
            // API 返回錯誤
            alert(`移除失敗: ${data.message || '未知錯誤'}`);
            return;
        }

        // 成功：重新載入列表
        await loadHelpers();
        alert('小幫手移除成功');

    } catch (error) {
        console.error('Remove helper error:', error);
        alert('移除失敗，請稍後再試');
    }
}
```

---

## 🔗 FluentCommunity 整合

### Phase 4: 側邊欄連結

**新建檔案**: `includes/class-fluent-community.php`

```php
<?php
namespace BuyGoPlus;

/**
 * FluentCommunity 整合類別
 *
 * 功能:
 * - 在 FluentCommunity 側邊欄新增 BuyGo+1 連結
 * - 只有 BuyGo 成員（管理員、BuyGo Admin、小幫手）可以看到
 */
class FluentCommunity
{
    public function __construct()
    {
        add_filter('fluent_community/sidebar_menu_groups_config', [$this, 'add_buygo_menu'], 10, 2);
    }

    /**
     * 新增 BuyGo+1 選單項目到 FluentCommunity 側邊欄
     *
     * @param array $config 當前選單配置
     * @param object $user FluentCommunity 使用者物件
     * @return array 修改後的選單配置
     */
    public function add_buygo_menu($config, $user)
    {
        // 驗證使用者物件
        if (!$user || !isset($user->user_id)) {
            return $config;
        }

        // 取得 WordPress 使用者
        $wp_user = get_user_by('id', $user->user_id);
        if (!$wp_user) {
            return $config;
        }

        // 檢查是否為 BuyGo 成員
        $is_buygo_member = user_can($wp_user, 'manage_options')    // WordPress 管理員
                        || user_can($wp_user, 'buygo_admin')       // BuyGo 管理員
                        || user_can($wp_user, 'buygo_helper');     // 小幫手

        if (!$is_buygo_member) {
            return $config;
        }

        // 新增選單項目
        if (!isset($config['primaryItems'])) {
            $config['primaryItems'] = [];
        }

        $config['primaryItems'][] = [
            'title'     => 'BuyGo+1 管理',
            'permalink' => admin_url('admin.php?page=buygo-plus-one'),
            'slug'      => 'buygo-portal',
            'shape_svg' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>',
        ];

        return $config;
    }
}
```

**載入此類別**: 在 `includes/class-plugin.php`

找到 `__construct()` 方法，新增：

```php
public function __construct()
{
    // ... 現有的初始化代碼

    // FluentCommunity 整合
    if (class_exists('FluentCommunity\Framework\Foundation\Application')) {
        new FluentCommunity();
    }
}
```

---

## 🧪 測試計畫

### 單元測試（可選）

建議使用 PHPUnit 撰寫單元測試：

**測試檔案**: `tests/services/test-settings-service.php`

```php
<?php
namespace BuyGoPlus\Tests\Services;

use BuyGoPlus\Services\Settings_Service;
use WP_UnitTestCase;

class Test_Settings_Service extends WP_UnitTestCase
{
    private $service;
    private $seller_id;
    private $helper_id;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = Settings_Service::getInstance();

        // 建立測試使用者
        $this->seller_id = $this->factory->user->create(['role' => 'administrator']);
        $this->helper_id = $this->factory->user->create(['role' => 'subscriber']);
    }

    public function test_add_helper_success()
    {
        $result = $this->service->add_helper($this->helper_id, $this->seller_id);
        $this->assertTrue($result);

        // 驗證角色
        $user = get_userdata($this->helper_id);
        $this->assertTrue(in_array('buygo_helper', $user->roles));
    }

    public function test_add_helper_duplicate()
    {
        $this->service->add_helper($this->helper_id, $this->seller_id);
        $result = $this->service->add_helper($this->helper_id, $this->seller_id);

        $this->assertWPError($result);
        $this->assertEquals('already_exists', $result->get_error_code());
    }

    public function test_get_helpers_by_seller()
    {
        $this->service->add_helper($this->helper_id, $this->seller_id);

        $helpers = $this->service->get_helpers($this->seller_id);
        $this->assertCount(1, $helpers);
        $this->assertEquals($this->helper_id, $helpers[0]['id']);
    }

    public function test_remove_helper_success()
    {
        $this->service->add_helper($this->helper_id, $this->seller_id);
        $result = $this->service->remove_helper($this->helper_id, $this->seller_id);

        $this->assertTrue($result);

        // 驗證角色已移除
        $user = get_userdata($this->helper_id);
        $this->assertFalse(in_array('buygo_helper', $user->roles));
    }

    public function test_helper_multiple_sellers()
    {
        $seller2_id = $this->factory->user->create(['role' => 'administrator']);

        // 同一個人被兩個賣家新增為小幫手
        $this->service->add_helper($this->helper_id, $this->seller_id);
        $this->service->add_helper($this->helper_id, $seller2_id);

        // 賣家 1 移除小幫手
        $this->service->remove_helper($this->helper_id, $this->seller_id);

        // 角色應該還在（因為還是賣家 2 的小幫手）
        $user = get_userdata($this->helper_id);
        $this->assertTrue(in_array('buygo_helper', $user->roles));

        // 賣家 2 也移除
        $this->service->remove_helper($this->helper_id, $seller2_id);

        // 現在角色應該被移除了
        $user = get_userdata($this->helper_id);
        $this->assertFalse(in_array('buygo_helper', $user->roles));
    }
}
```

### 整合測試（使用 Chrome MCP）

**測試腳本**: `tests/integration/test-member-permission.md`

```markdown
# 會員權限管理系統整合測試

## 準備工作

1. 建立測試使用者:
   - 管理員 A (admin_a@test.com)
   - 管理員 B (admin_b@test.com)
   - 一般使用者 C (user_c@test.com)
   - 一般使用者 D (user_d@test.com)

2. 資料庫確認:
   ```sql
   SELECT COUNT(*) FROM wp_buygo_helpers;  -- 應該是 0
   ```

## 測試案例 1: 新增小幫手

**操作**:
1. 以管理員 A 登入
2. 前往設定頁面
3. 點擊「會員權限管理」區塊
4. 新增使用者 C 為小幫手

**預期結果**:
- [x] 成功新增
- [x] 列表中顯示使用者 C
- [x] 顯示新增時間
- [x] 資料庫檢查:
  ```sql
  SELECT * FROM wp_buygo_helpers WHERE seller_id = [A的ID];
  -- 應該有 1 筆記錄，user_id = C的ID
  ```

## 測試案例 2: 權限隔離

**操作**:
1. 以管理員 B 登入
2. 前往設定頁面的「會員權限管理」

**預期結果**:
- [x] 列表是空的（不應該看到使用者 C）
- [x] 資料庫檢查:
  ```sql
  SELECT * FROM wp_buygo_helpers WHERE seller_id = [B的ID];
  -- 應該是 0 筆
  ```

## 測試案例 3: 多賣家共用小幫手

**操作**:
1. 管理員 B 也新增使用者 C 為小幫手

**預期結果**:
- [x] 成功新增
- [x] 資料庫檢查:
  ```sql
  SELECT * FROM wp_buygo_helpers WHERE user_id = [C的ID];
  -- 應該有 2 筆記錄（seller_id 分別是 A 和 B）
  ```

## 測試案例 4: 移除小幫手（角色保留）

**操作**:
1. 以管理員 A 登入
2. 移除使用者 C

**預期結果**:
- [x] 成功移除
- [x] 管理員 A 的列表中不再顯示使用者 C
- [x] 使用者 C 仍然有 `buygo_helper` 角色（因為還是 B 的小幫手）
- [x] 資料庫檢查:
  ```sql
  SELECT * FROM wp_buygo_helpers WHERE user_id = [C的ID];
  -- 應該只剩 1 筆（seller_id = B的ID）
  ```

## 測試案例 5: 移除小幫手（角色移除）

**操作**:
1. 以管理員 B 登入
2. 移除使用者 C

**預期結果**:
- [x] 成功移除
- [x] 使用者 C 的 `buygo_helper` 角色被移除
- [x] 資料庫檢查:
  ```sql
  SELECT * FROM wp_buygo_helpers WHERE user_id = [C的ID];
  -- 應該是 0 筆
  ```

## 測試案例 6: 小幫手看不到管理介面

**操作**:
1. 以使用者 D 登入（尚未是小幫手）
2. 前往設定頁面

**預期結果**:
- [x] 看不到「會員權限管理」區塊

**操作**:
1. 管理員 A 將使用者 D 新增為小幫手
2. 使用者 D 重新載入頁面

**預期結果**:
- [x] 仍然看不到「會員權限管理」區塊（小幫手不能管理其他小幫手）

## 測試案例 7: API 權限測試

**操作**:
1. 使用未登入的 curl 測試

```bash
curl -X GET "http://localhost/wp-json/buygo-plus-one/v1/settings/helpers"
```

**預期結果**:
- [x] HTTP 401 Unauthorized

**操作**:
2. 使用小幫手的 nonce 測試

```bash
curl -X POST "http://localhost/wp-json/buygo-plus-one/v1/settings/helpers" \
  -H "X-WP-Nonce: [小幫手的nonce]" \
  -d '{"user_id": 999}'
```

**預期結果**:
- [x] HTTP 403 Forbidden（小幫手不能新增其他小幫手）

**操作**:
3. 使用管理員的 nonce 測試

```bash
curl -X POST "http://localhost/wp-json/buygo-plus-one/v1/settings/helpers" \
  -H "X-WP-Nonce: [管理員的nonce]" \
  -d '{"user_id": 999}'
```

**預期結果**:
- [x] HTTP 200 OK 或 400 Bad Request (視 user_id 是否有效)
```

---

## 📊 預期成果

### 功能完整性

實作完成後，系統應具備：

- ✅ **多賣家支援**: 每個賣家管理自己的小幫手列表
- ✅ **權限隔離**: A 賣家看不到 B 賣家的小幫手
- ✅ **共用小幫手**: 同一個人可以是多個賣家的小幫手
- ✅ **智能角色管理**: 只有當使用者不再是任何人的小幫手時，才移除角色
- ✅ **向後相容**: 如果新資料表不存在，自動降級使用舊的 Option API
- ✅ **API 權限控制**: 小幫手不能新增其他小幫手，只有管理員可以

### 資料庫效能

- ✅ 索引優化：`idx_seller` 加速查詢賣家的小幫手
- ✅ 索引優化：`idx_user` 加速檢查使用者是誰的小幫手
- ✅ 唯一約束：防止重複新增

### 使用者體驗

- ✅ 清楚的UI：「會員權限管理」比「小幫手管理」更專業
- ✅ 即時反饋：新增/刪除後立即顯示結果
- ✅ 錯誤訊息：清楚告知錯誤原因（已存在、不存在、無權限等）
- ✅ 時間戳記：顯示每個小幫手的新增時間

---

## 🚀 部署建議

### 上線前檢查清單

- [ ] 在開發環境完整測試所有測試案例
- [ ] 運行結構驗證: `bash scripts/validate-structure.sh`（應該 0 errors）
- [ ] 資料庫備份
- [ ] 準備回退計畫
- [ ] 更新版本號（建議從 0.03 升級到 0.04）
- [ ] 更新 CHANGELOG

### 分階段部署

**階段 1: 資料表建立**（低風險）
- 只建立新資料表
- 不修改任何現有功能
- 觀察 1-2 天

**階段 2: 後端服務修改**（中風險）
- 修改 SettingsService
- 保留向後相容
- 觀察 1-2 天

**階段 3: API 權限修復**（中風險）
- 修改 API 權限設定
- 詳細測試所有 API 端點
- 觀察 1-2 天

**階段 4: 前端 UI 改造**（低風險）
- 修改設定頁面
- 純視覺變更，不影響邏輯

**階段 5: FluentCommunity 整合**（低風險）
- 新增側邊欄連結
- 可選功能，不影響核心

---

## 📝 相關文檔

- [原始計畫](~/.claude/plans/calm-mapping-hedgehog.md)
- [結構修復策略](repair-strategy.md)
- [WordPress Plugin 最佳實踐](../wordpress-plugin-dev/best-practices.md)

---

**版本**: 1.0
**制定者**: Claude Code
**最後更新**: 2026-01-24
**狀態**: ⏸️ 規劃完成，待執行
**預估總工時**: 8-10 小時
