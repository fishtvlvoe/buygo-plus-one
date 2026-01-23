<?php
/**
 * Plugin Name:       BuyGo+1 (開發版 - Boilerplate 重構)
 * Plugin URI:        https://buygo.me
 * Description:       BuyGo 獨立賣場後台系統 - WordPress Plugin Boilerplate 重構版本
 * Version:           0.0.1-dev
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            BuyGo Team
 * Author URI:        https://buygo.me
 * License:           GPL v2 or later
 * Text Domain:       buygo-plus-one-dev
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('BUYGO_PLUS_ONE_VERSION', '0.0.1');
define('BUYGO_PLUS_ONE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BUYGO_PLUS_ONE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BUYGO_PLUS_ONE_PLUGIN_FILE', __FILE__);

// Load plugin class
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * Activation Hook - 外掛啟用時執行
 * 
 * 建立外掛所需的資料表：
 * - buygo_debug_logs (除錯日誌)
 * - buygo_notification_logs (通知記錄)
 * - buygo_workflow_logs (流程監控)
 */
register_activation_hook(__FILE__, function () {
    require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-database.php';
    \BuyGoPlus\Database::create_tables();
    
    // 建立 Webhook Logger 資料表
    require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-webhook-logger.php';
    \BuyGoPlus\Services\WebhookLogger::create_table();
    
    // 建立 LINE Service 資料表（如果不存在）
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
    \BuyGoPlus\Plugin::instance()->init();
}, 20);
