<?php
namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ProductMetaService - 商品自訂欄位 CRUD
 *
 * 管理 buygo_product_meta 表的讀取與更新。
 * 使用白名單限制允許的 meta_key，防止任意欄位寫入。
 *
 * @package BuyGoPlus\Services
 * @since 2.1.0
 */
class ProductMetaService {

    /**
     * 允許的 meta_key 白名單
     *
     * @var array
     */
    private static $allowed_keys = [
        'cost_price',
        'original_price',
        'purchase_location',
        'supplier',
        'barcode',
        'manufacturing_notes',
    ];

    /**
     * 取得商品的所有自訂欄位
     *
     * @param int $product_id 商品 ID
     * @return array 欄位名稱 => 值（未設定的欄位回傳空字串）
     */
    public static function get_fields(int $product_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'buygo_product_meta';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$table} WHERE product_id = %d",
            $product_id
        ), ARRAY_A);

        // 初始化所有欄位為空字串
        $fields = [];
        foreach (self::$allowed_keys as $key) {
            $fields[$key] = '';
        }

        // 填入資料庫中的值
        if ($results) {
            foreach ($results as $row) {
                if (in_array($row['meta_key'], self::$allowed_keys, true)) {
                    $fields[$row['meta_key']] = $row['meta_value'];
                }
            }
        }

        return $fields;
    }

    /**
     * 更新商品的自訂欄位
     *
     * 使用 REPLACE INTO 實現 upsert，只更新白名單內的欄位。
     *
     * @param int   $product_id 商品 ID
     * @param array $data       欄位名稱 => 值
     * @return bool 是否成功
     */
    public static function update_fields(int $product_id, array $data): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'buygo_product_meta';

        foreach ($data as $key => $value) {
            // 只允許白名單內的 key
            if (!in_array($key, self::$allowed_keys, true)) {
                continue;
            }

            $wpdb->replace($table, [
                'product_id' => $product_id,
                'meta_key'   => $key,
                'meta_value' => sanitize_text_field($value),
            ], ['%d', '%s', '%s']);
        }

        return true;
    }
}
