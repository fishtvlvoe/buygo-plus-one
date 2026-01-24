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

        // 建立出貨單資料表
        self::create_shipments_table($wpdb, $charset_collate);

        // 建立出貨單項目資料表
        self::create_shipment_items_table($wpdb, $charset_collate);

        // 建立 Webhook 日誌表
        self::create_webhook_logs_table($wpdb, $charset_collate);

        // 建立訂單狀態歷史表
        self::create_order_status_history_table($wpdb, $charset_collate);
    }

    /**
     * 升級資料表結構（修復缺失的欄位）
     * 這個函數會在外掛啟動時檢查並修復資料表結構
     */
    public static function upgrade_tables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // 修復 buygo_shipments 資料表
        self::upgrade_shipments_table($wpdb, $charset_collate);

        // 修復 buygo_shipment_items 資料表
        self::upgrade_shipment_items_table($wpdb, $charset_collate);
    }

    /**
     * 升級 shipments 資料表
     */
    private static function upgrade_shipments_table($wpdb, $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'buygo_shipments';

        // 檢查表格是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            // 表不存在，建立新表
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
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY idx_shipment_number (shipment_number),
                KEY idx_customer_id (customer_id),
                KEY idx_seller_id (seller_id),
                KEY idx_status (status)
            ) {$charset_collate};";

            dbDelta($sql);
            return;
        }

        // 表存在，檢查並添加缺失的欄位
        $columns = $wpdb->get_col("DESCRIBE {$table_name}", 0);

        // 添加 shipment_number 欄位
        if (!in_array('shipment_number', $columns)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN shipment_number varchar(50) NOT NULL AFTER id");
            $wpdb->query("ALTER TABLE {$table_name} ADD UNIQUE KEY idx_shipment_number (shipment_number)");
        }

        // 添加 customer_id 欄位
        if (!in_array('customer_id', $columns)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN customer_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER shipment_number");
            $wpdb->query("ALTER TABLE {$table_name} ADD KEY idx_customer_id (customer_id)");
        }

        // 添加 seller_id 欄位
        if (!in_array('seller_id', $columns)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN seller_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER customer_id");
            $wpdb->query("ALTER TABLE {$table_name} ADD KEY idx_seller_id (seller_id)");
        }
    }

    /**
     * 升級 shipment_items 資料表
     */
    private static function upgrade_shipment_items_table($wpdb, $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'buygo_shipment_items';

        // 檢查表格是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            // 表不存在，建立新表
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            $sql = "CREATE TABLE {$table_name} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                shipment_id bigint(20) UNSIGNED NOT NULL,
                order_id bigint(20) UNSIGNED NOT NULL,
                order_item_id bigint(20) UNSIGNED NOT NULL,
                product_id bigint(20) UNSIGNED NOT NULL,
                quantity int(11) UNSIGNED NOT NULL DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_shipment_id (shipment_id),
                KEY idx_order_id (order_id),
                KEY idx_order_item_id (order_item_id),
                KEY idx_product_id (product_id)
            ) {$charset_collate};";

            dbDelta($sql);
            return;
        }

        // 表存在，檢查並添加缺失的欄位
        $columns = $wpdb->get_col("DESCRIBE {$table_name}", 0);

        // 添加 order_id 欄位
        if (!in_array('order_id', $columns)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN order_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER shipment_id");
            $wpdb->query("ALTER TABLE {$table_name} ADD KEY idx_order_id (order_id)");
        }
    }

    /**
     * 建立出貨單資料表
     */
    private static function create_shipments_table($wpdb, $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'buygo_shipments';

        // 檢查表格是否已存在
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
        }

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            shipment_number varchar(50) NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            seller_id bigint(20) UNSIGNED NOT NULL,
            status varchar(50) DEFAULT 'pending',
            shipping_method varchar(100),
            tracking_number varchar(100),
            shipped_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_shipment_number (shipment_number),
            KEY idx_customer_id (customer_id),
            KEY idx_seller_id (seller_id),
            KEY idx_status (status)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * 建立出貨單項目資料表
     */
    private static function create_shipment_items_table($wpdb, $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'buygo_shipment_items';

        // 檢查表格是否已存在
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
        }

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            shipment_id bigint(20) UNSIGNED NOT NULL,
            order_id bigint(20) UNSIGNED NOT NULL,
            order_item_id bigint(20) UNSIGNED NOT NULL,
            product_id bigint(20) UNSIGNED NOT NULL,
            quantity int(11) UNSIGNED NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_shipment_id (shipment_id),
            KEY idx_order_id (order_id),
            KEY idx_order_item_id (order_item_id),
            KEY idx_product_id (product_id)
        ) {$charset_collate};";

        dbDelta($sql);
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

    /**
     * 建立 Webhook 日誌表
     */
    private static function create_webhook_logs_table($wpdb, $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'buygo_webhook_logs';

        // 檢查表格是否已存在
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
        }

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data text,
            user_id bigint(20) UNSIGNED,
            line_user_id varchar(100),
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_created_at (created_at),
            KEY idx_line_user_id (line_user_id)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * 建立訂單狀態歷史表
     */
    private static function create_order_status_history_table($wpdb, $charset_collate): void
    {
        $table_name = $wpdb->prefix . 'buygo_order_status_history';

        // 檢查表格是否已存在
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
        }

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id varchar(50) NOT NULL,
            old_status varchar(50) NOT NULL,
            new_status varchar(50) NOT NULL,
            reason varchar(255),
            operator_id bigint(20) UNSIGNED,
            is_abnormal tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_order_id (order_id),
            KEY idx_created_at (created_at),
            KEY idx_is_abnormal (is_abnormal)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}
