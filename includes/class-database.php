<?php
namespace BuyGoPlus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database - 資料庫管理
 * 
 * 負責建立外掛所需的資料表
 */
class Database
{
    /**
     * 建立資料表
     */
    public static function create_tables(): void
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'buygo_debug_logs';
        
        // 檢查表格是否已存在（舊外掛可能已建立）
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return; // 表格已存在，不重複建立
        }
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL,
            module varchar(100) NOT NULL,
            message text NOT NULL,
            data longtext,
            user_id bigint(20),
            ip_address varchar(45),
            user_agent text,
            request_uri text,
            request_method varchar(10),
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY module (module),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
