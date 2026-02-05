<?php
/**
 * Plugin Name:       BuyGo 開發版
 * Plugin URI:        https://buygo.me
 * Description:       BuyGo 獨立賣場後台系統
 * Version:           0.2.6
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            BuyGo Team
 * Author URI:        https://buygo.me
 * License:           GPL v2 or later
 * Text Domain:       buygo.me
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// ========================================
// 🛡️ 防止開發版和正式版同時啟用
// ========================================
// 如果已經定義了 BUYGO_PLUS_ONE_VERSION 常數,
// 表示正式版已經載入,開發版必須停止運作
if (defined('BUYGO_PLUS_ONE_VERSION')) {
    // 顯示管理員通知
    add_action('admin_notices', function() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error is-dismissible">';
        echo '<h3>⚠️ BuyGo+1 開發版已自動停用</h3>';
        echo '<p>系統偵測到 <strong>BuyGo+1 正式版</strong> 已啟用，為避免衝突，開發版將不會載入。</p>';
        echo '<p><strong>目前狀態：</strong>只有正式版在運作中 ✅</p>';
        echo '<p><strong>建議：</strong>在本地開發環境請停用正式版，保留開發版即可。</p>';
        echo '</div>';
    });

    // 停止載入開發版的其餘程式碼
    return;
}

// Define plugin constants
define('BUYGO_PLUS_ONE_VERSION', '0.2.6-dev');

// 新版專用的常數（舊版不會定義這些）
define('BUYGO_PLUS_ONE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BUYGO_PLUS_ONE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BUYGO_PLUS_ONE_PLUGIN_FILE', __FILE__);

// Load plugin class
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-plugin.php';

// Load updater
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-updater.php';

/**
 * Activation Hook - 外掛啟用時執行
 *
 * 執行順序：
 * 1. 檢查舊外掛兼容性（若舊外掛啟用中則阻止啟用）
 * 2. 建立外掛所需的資料表
 * 3. 執行資料表升級
 */
register_activation_hook(__FILE__, function () {
    // 標記需要 flush rewrite rules
    require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-routes.php';
    \BuyGoPlus\Routes::schedule_flush();

    // 1. 兼容性檢查 - 確保舊外掛未啟用
    require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-plugin-compatibility.php';
    \BuyGoPlus\PluginCompatibility::on_activation();

    // 2. 建立資料表
    require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-database.php';
    \BuyGoPlus\Database::create_tables();

    // 3. 升級現有資料表結構
    \BuyGoPlus\Database::upgrade_tables();
    
    // 建立 Webhook Logger 資料表
    require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-webhook-logger.php';
    \BuyGoPlus\Services\WebhookLogger::create_table();
    
    // 建立 LINE Service 資料表（如果不存在）
    // Note: LineService requires DebugService in its constructor
    require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-debug-service.php';
    require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-line-service.php';
    \BuyGoPlus\Services\LineService::create_table();
    
    // 載入並初始化短連結路由，然後刷新 rewrite rules
    require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-short-link-routes.php';
    \BuyGoPlus\ShortLinkRoutes::instance()->flush_rewrite_rules();
});

/**
 * Deactivation Hook - 外掛停用時執行
 * 
 * 清理暫存資料和快取：
 * - 清除 WordPress Object Cache
 * - 清除 Transients（暫存資料）
 * 
 * 注意：不會刪除資料表和設定，以便使用者重新啟用時可以保留資料
 */
register_deactivation_hook(__FILE__, function () {
    // 清理 rewrite rules
    flush_rewrite_rules(false);

    // 清除快取
    wp_cache_flush();

    // 清除所有 BuyGo 相關的 Transients
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_buygo_%'
         OR option_name LIKE '_transient_timeout_buygo_%'"
    );

    // 清除 NotificationTemplates 快取
    delete_option('buygo_notification_templates_cache');
});

/**
 * Initialize Plugin - 載入外掛
 *
 * 優先級設為 20，確保在其他外掛（如 FluentCRM）載入後才初始化
 */
add_action('plugins_loaded', function () {
    // 開發版不需要 runtime_check，因為已在檔案開頭處理衝突檢查
    // runtime_check 是給正式版用的，用來偵測舊外掛
    // 開發版呼叫 runtime_check 會誤判自己為衝突

    \BuyGoPlus\Plugin::instance()->init();

    // 初始化自動更新器
    new \BuyGoPlus\Updater(BUYGO_PLUS_ONE_PLUGIN_FILE);
}, 20);
