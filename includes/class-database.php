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
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 建立除錯日誌表
        self::create_debug_logs_table($wpdb, $charset_collate);
        
        // 建立通知記錄表
        self::create_notification_logs_table($wpdb, $charset_collate);
        
        // 建立流程監控表
        self::create_workflow_logs_table($wpdb, $charset_collate);
    }
    
    /**
     * 建立除錯日誌表
     */
    private static function create_debug_logs_table($wpdb, $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'buygo_debug_logs';
        
        // 檢查表格是否已存在
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
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
        
        dbDelta($sql);
    }
    
    /**
     * 建立通知記錄表
     */
    private static function create_notification_logs_table($wpdb, $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'buygo_notification_logs';
        
        // 檢查表格是否已存在
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
        }
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            receiver varchar(255) NOT NULL,
            channel varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            content text NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY receiver (receiver),
            KEY channel (channel),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * 建立流程監控表
     */
    private static function create_workflow_logs_table($wpdb, $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'buygo_workflow_logs';
        
        // 檢查表格是否已存在
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
        }
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            workflow_name varchar(100) NOT NULL,
            status varchar(20) NOT NULL,
            steps int(11) DEFAULT 0,
            success_rate decimal(5,2) DEFAULT 0.00,
            error_message text,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY workflow_name (workflow_name),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
}
