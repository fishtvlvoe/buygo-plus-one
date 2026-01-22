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
        
        // 建立 LINE 綁定表（遷移自舊外掛，讓新外掛可以獨立執行）
        self::create_line_bindings_table($wpdb, $charset_collate);

        // 建立小幫手資料表
        self::create_helpers_table($wpdb, $charset_collate);
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
    
    /**
     * 建立 LINE 綁定表
     */
    private static function create_line_bindings_table($wpdb, $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'buygo_line_bindings';
        
        // 檢查表格是否已存在
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
        }
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            line_uid varchar(100) NOT NULL,
            binding_code varchar(20),
            binding_code_expires_at datetime,
            status varchar(20) DEFAULT 'unbound',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at datetime,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY line_uid (line_uid),
            KEY binding_code (binding_code)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }

    /**
     * 建立小幫手資料表
     *
     * 用於記錄 BuyGo 管理員與小幫手的關聯關係
     * seller_id: 管理員的 WordPress user ID
     * user_id: 小幫手的 WordPress user ID
     */
    private static function create_helpers_table($wpdb, $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'buygo_helpers';

        // 檢查表格是否已存在
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
        }

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            seller_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_helper (user_id, seller_id),
            KEY idx_seller (seller_id),
            KEY idx_user (user_id)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * 遷移舊的小幫手資料
     *
     * 將舊的 Option API 資料遷移到新的資料表
     */
    public static function migrate_helpers_data(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_helpers';

        // 檢查是否已遷移
        if (get_option('buygo_helpers_migrated', false)) {
            return;
        }

        // 取得舊資料
        $old_helpers = get_option('buygo_helpers', []);
        if (empty($old_helpers) || !is_array($old_helpers)) {
            update_option('buygo_helpers_migrated', true);
            return;
        }

        // 找出所有 buygo_admin 角色的使用者作為預設 seller
        $admins = get_users(['role' => 'buygo_admin']);
        $default_seller_id = !empty($admins) ? $admins[0]->ID : get_current_user_id();

        // 如果沒有管理員，使用 WordPress 管理員
        if (!$default_seller_id) {
            $wp_admins = get_users(['role' => 'administrator']);
            $default_seller_id = !empty($wp_admins) ? $wp_admins[0]->ID : 1;
        }

        foreach ($old_helpers as $user_id) {
            // 檢查是否已存在
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND seller_id = %d",
                $user_id,
                $default_seller_id
            ));

            if (!$exists) {
                $wpdb->insert(
                    $table_name,
                    [
                        'user_id' => $user_id,
                        'seller_id' => $default_seller_id,
                    ],
                    ['%d', '%d']
                );
            }
        }

        // 標記遷移完成
        update_option('buygo_helpers_migrated', true);
    }
}
