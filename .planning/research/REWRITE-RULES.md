# WordPress Rewrite Rules 自動 Flush 研究報告

**專案:** BuyGo+1
**研究日期:** 2026-01-31
**信心等級:** HIGH

## 問題背景

BuyGo+1 使用自訂路由（如 `/buygo-portal/dashboard`, `/buygo-portal/orders`），這些路由透過 `add_rewrite_rule()` 註冊。目前問題是：

- 外掛啟用後，自訂路由無法立即生效
- 需要手動到「設定 → 永久連結 → 儲存」才能讓路由生效
- 這對使用者體驗不佳，容易造成「外掛壞掉」的誤解

## 現有程式碼分析

### Routes 類別 (class-routes.php)

```php
class Routes {
    public function __construct() {
        add_action('init', [$this, 'register_rewrite_rules']);
        // ...
    }

    public function register_rewrite_rules() {
        add_rewrite_rule('^buygo-portal/?$', 'index.php?buygo_page=portal_home', 'top');
        add_rewrite_rule('^buygo-portal/dashboard/?$', 'index.php?buygo_page=dashboard', 'top');
        // ... 共 7 個路由
    }
}
```

**問題:** Routes 類別在 `plugins_loaded` hook (priority 20) 時透過 `Plugin::register_hooks()` 初始化，但 activation hook 執行時這些路由尚未註冊。

### ShortLinkRoutes 類別 (class-short-link-routes.php)

ShortLinkRoutes 已經實作了正確的 flag-based 方法：

```php
// 設定 flag
public function flush_rewrite_rules() {
    set_transient('buygo_plus_one_flush_rewrite_rules', 1, 60);
}

// 在 init hook (priority 20) 檢查並執行
public function maybe_flush_rewrite_rules() {
    if (get_transient('buygo_plus_one_flush_rewrite_rules')) {
        delete_transient('buygo_plus_one_flush_rewrite_rules');
        flush_rewrite_rules();
    }
}
```

**但是:** 這個實作只處理了 ShortLinkRoutes 的路由，沒有涵蓋 Routes 類別的路由。

## WordPress Rewrite Rules 機制

### 核心概念

1. **Rewrite Rules 儲存在資料庫** - 存在 `wp_options` 表的 `rewrite_rules` option 中
2. **`add_rewrite_rule()` 不會立即寫入** - 只是將規則加入記憶體中的 `$wp_rewrite` 物件
3. **`flush_rewrite_rules()` 才會寫入** - 重新產生規則並儲存到資料庫

### 為什麼 Activation Hook 直接 flush 會失敗

```
時序問題:
1. activation hook 執行 (rewrite rules 尚未註冊)
2. plugins_loaded hook 執行
3. init hook 執行 (rewrite rules 在此註冊)
```

如果在 activation hook 中直接呼叫 `flush_rewrite_rules()`，此時 `add_rewrite_rule()` 尚未執行，flush 的結果就不會包含自訂路由。

## 解決方案比較

### 方案 A: Flag-Based 方法 (推薦)

**原理:** 在 activation hook 設定 flag，在 init hook 之後檢查 flag 並執行 flush。

```php
// activation hook
register_activation_hook(__FILE__, function() {
    set_transient('myplugin_flush_rewrite_rules', 1, 60);
});

// init hook (priority 20，確保在 add_rewrite_rule 之後)
add_action('init', function() {
    if (get_transient('myplugin_flush_rewrite_rules')) {
        delete_transient('myplugin_flush_rewrite_rules');
        flush_rewrite_rules();
    }
}, 20);
```

**優點:**
- 確保 rewrite rules 已註冊後才 flush
- 只執行一次，不影響效能
- 使用 transient 有自動過期機制

**缺點:**
- 需要等到下一次頁面載入才生效

### 方案 B: delete_option 方法

**原理:** 直接刪除 `rewrite_rules` option，讓 WordPress 在下次載入時自動重建。

```php
register_activation_hook(__FILE__, function() {
    delete_option('rewrite_rules');
});
```

**優點:**
- 程式碼最簡單
- 自動在正確的時機重建規則

**缺點:**
- 可能影響其他外掛的 rewrite rules（風險較低，因為重建時會包含所有已註冊的規則）

### 方案 C: Activation Hook 內呼叫 add_rewrite_rule

**原理:** 在 activation hook 內手動呼叫 `add_rewrite_rule()`，然後立即 flush。

```php
register_activation_hook(__FILE__, function() {
    // 在此處也註冊 rewrite rules
    add_rewrite_rule('^buygo-portal/?$', 'index.php?buygo_page=portal_home', 'top');
    // ... 其他規則
    flush_rewrite_rules();
});
```

**優點:**
- 立即生效

**缺點:**
- 程式碼重複（init hook 和 activation hook 都要維護相同的規則）
- 維護成本高，容易不同步

## 推薦方案

**採用方案 A: Flag-Based 方法**

理由：
1. BuyGo+1 的 ShortLinkRoutes 已經使用此方法，保持一致性
2. 避免程式碼重複
3. 有 transient 自動過期機制防止意外
4. 效能最佳（只執行一次）

## 實作建議

### 修改 Routes 類別

```php
class Routes {
    public function __construct() {
        add_action('init', [$this, 'register_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_buygo_pages']);

        // 新增：檢查並執行 flush
        add_action('init', [$this, 'maybe_flush_rewrite_rules'], 20);
    }

    // 新增：設定 flush flag（供 activation hook 呼叫）
    public static function schedule_flush() {
        set_transient('buygo_plus_one_flush_routes', 1, 60);
    }

    // 新增：檢查並執行 flush
    public function maybe_flush_rewrite_rules() {
        if (get_transient('buygo_plus_one_flush_routes')) {
            delete_transient('buygo_plus_one_flush_routes');
            flush_rewrite_rules();
        }
    }

    // ... 其他現有方法
}
```

### 修改 Activation Hook

```php
register_activation_hook(__FILE__, function () {
    // ... 現有程式碼 ...

    // 標記需要 flush rewrite rules（包含主要路由）
    \BuyGoPlus\Routes::schedule_flush();

    // ShortLinkRoutes 的 flush 可以合併或保留獨立
});
```

### 合併方案（進階）

考慮將所有 rewrite rules 的 flush 邏輯統一管理：

```php
// 在 activation hook 只設定一個 flag
set_transient('buygo_plus_one_flush_all_routes', 1, 60);

// 在 Plugin 類別中統一處理
add_action('init', function() {
    if (get_transient('buygo_plus_one_flush_all_routes')) {
        delete_transient('buygo_plus_one_flush_all_routes');
        flush_rewrite_rules();
    }
}, 25); // priority 25 確保在所有 add_rewrite_rule 之後
```

## 注意事項

### 效能考量

- `flush_rewrite_rules()` 是昂貴的操作
- 絕對不要在每次頁面載入時呼叫
- 只在外掛啟用/停用時使用

### Deactivation Hook

停用外掛時也應該 flush，清除無效的 rewrite rules：

```php
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});
```

**注意:** 停用時可以直接呼叫，因為此時 init hook 已經執行完畢。

### Hard Flush vs Soft Flush

```php
flush_rewrite_rules(true);  // hard flush - 更新 .htaccess
flush_rewrite_rules(false); // soft flush - 只更新資料庫 option
```

對於大多數情況，soft flush 就足夠了，且效能更好。

### Multisite 注意事項

在 Multisite 環境中，rewrite rules 是每個站點獨立的。如果外掛支援 Network Activate，需要額外處理。

## 驗證方法

安裝修改後，可透過以下方式驗證：

```bash
# 使用 WP-CLI 檢查 rewrite rules
wp rewrite list --match="/buygo-portal/dashboard"

# 重新產生 rewrite rules
wp rewrite flush
```

或在程式碼中：

```php
// 檢查規則是否已註冊
global $wp_rewrite;
var_dump($wp_rewrite->rules);
```

## 參考資料

- [WordPress Developer: flush_rewrite_rules()](https://developer.wordpress.org/reference/functions/flush_rewrite_rules/)
- [WordPress Developer: WP_Rewrite::flush_rules()](https://developer.wordpress.org/reference/classes/wp_rewrite/flush_rules/)
- [WordPress Developer: add_rewrite_rule()](https://developer.wordpress.org/reference/functions/add_rewrite_rule/)
- [How to Efficiently Flush Rewrite Rules After Plugin Activation](https://andrezrv.com/2014/08/12/efficiently-flush-rewrite-rules-plugin-activation/)
- [WP Kama: flush_rewrite_rules()](https://wp-kama.com/function/flush_rewrite_rules)

## 結論

| 項目 | 建議 |
|------|------|
| 方法 | Flag-Based (transient) |
| 觸發時機 | activation hook 設定 flag，init hook (priority 20+) 執行 flush |
| Deactivation | 直接呼叫 `flush_rewrite_rules()` |
| Hard/Soft | 使用 soft flush `flush_rewrite_rules(false)` 即可 |
| 統一管理 | 建議將 Routes 和 ShortLinkRoutes 的 flush 邏輯合併 |

**預估實作時間:** 30 分鐘
**風險等級:** 低
