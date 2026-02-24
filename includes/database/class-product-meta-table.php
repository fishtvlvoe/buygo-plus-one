<?php
namespace BuyGoPlus\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ProductMetaTable - 商品自訂欄位資料表
 *
 * 建立 buygo_product_meta 表，儲存商品的成本、來源等自訂欄位。
 * 使用 EAV 結構（product_id + meta_key + meta_value），
 * 搭配 ProductMetaService 白名單限制 key。
 *
 * @package BuyGoPlus\Database
 * @since 2.1.0
 */
class ProductMetaTable {

    /**
     * 建立資料表
     *
     * @return void
     */
    public static function create_table(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'buygo_product_meta';

        // 已存在則跳過
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            meta_key VARCHAR(255) NOT NULL,
            meta_value LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_meta (product_id, meta_key),
            KEY product_id (product_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
