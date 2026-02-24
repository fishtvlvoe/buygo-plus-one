<?php
namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 客戶資料編輯服務
 *
 * 負責更新 FluentCart 客戶資料（fct_customers + fct_customer_addresses）
 * 和 BuyGo 自訂欄位（wp_usermeta）
 *
 * @package BuyGoPlus\Services
 * @since 2.5.0
 */
class CustomerEditService
{
    /**
     * 可編輯欄位白名單
     */
    private static $editable_fields = [
        'fct_customers' => ['first_name', 'last_name'],
        'fct_addresses' => ['phone', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country'],
        'usermeta'      => ['buygo_taiwan_id_number', 'buygo_custom_id'],
    ];

    /**
     * 更新客戶資料
     *
     * @param int   $customer_id FluentCart customer ID
     * @param array $data        要更新的欄位
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    public static function update(int $customer_id, array $data): array
    {
        global $wpdb;

        // 1. 取得客戶資料確認存在
        $customer_table = $wpdb->prefix . 'fct_customers';
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$customer_table} WHERE id = %d",
            $customer_id
        ));

        if (!$customer) {
            return ['success' => false, 'message' => '找不到客戶'];
        }

        $updated = [];

        // 2. 更新 fct_customers（first_name, last_name）
        $customer_updates = [];
        foreach (self::$editable_fields['fct_customers'] as $field) {
            if (isset($data[$field])) {
                $customer_updates[$field] = sanitize_text_field($data[$field]);
            }
        }
        if (!empty($customer_updates)) {
            $customer_updates['updated_at'] = current_time('mysql');
            $wpdb->update($customer_table, $customer_updates, ['id' => $customer_id]);
            $updated = array_merge($updated, $customer_updates);
        }

        // 3. 更新 fct_customer_addresses（phone, address 等）
        $address_table = $wpdb->prefix . 'fct_customer_addresses';
        $address_updates = [];
        foreach (self::$editable_fields['fct_addresses'] as $field) {
            if (isset($data[$field])) {
                $address_updates[$field] = sanitize_text_field($data[$field]);
            }
        }
        if (!empty($address_updates)) {
            $address_updates['updated_at'] = current_time('mysql');

            // 更新主要地址（is_primary = 1），如果沒有就建立一筆
            $primary_address = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$address_table} WHERE customer_id = %d AND is_primary = 1",
                $customer_id
            ));

            if ($primary_address) {
                $wpdb->update($address_table, $address_updates, ['id' => $primary_address->id]);
            } else {
                // 建立主要地址
                $address_updates['customer_id'] = $customer_id;
                $address_updates['is_primary']  = 1;
                $address_updates['created_at']  = current_time('mysql');

                // name 欄位用 first_name + last_name
                $first = $data['first_name'] ?? ($customer->first_name ?? '');
                $last  = $data['last_name'] ?? ($customer->last_name ?? '');
                $address_updates['name'] = trim($first . ' ' . $last);

                $wpdb->insert($address_table, $address_updates);
            }
            $updated = array_merge($updated, $address_updates);
        }

        // 4. 更新 wp_usermeta（自訂欄位）
        if ($customer->user_id) {
            foreach (self::$editable_fields['usermeta'] as $meta_key) {
                // 前端傳來的 key 不帶 buygo_ 前綴
                $frontend_key = str_replace('buygo_', '', $meta_key);
                if (isset($data[$frontend_key]) || isset($data[$meta_key])) {
                    $value = $data[$frontend_key] ?? $data[$meta_key];
                    update_user_meta($customer->user_id, $meta_key, sanitize_text_field($value));
                    $updated[$frontend_key] = $value;
                }
            }
        }

        return [
            'success' => true,
            'message' => '客戶資料已更新',
            'data'    => $updated,
        ];
    }

    /**
     * 檢查客戶是否屬於當前賣家
     *
     * 管理員可存取所有客戶；
     * 小幫手和賣家透過訂單記錄中的商品 post_author 確認歸屬。
     *
     * @param int $customer_id FluentCart customer ID
     * @return bool
     */
    public static function check_ownership(int $customer_id): bool
    {
        // 管理員可存取所有客戶
        if (current_user_can('manage_options') || current_user_can('buygo_admin')) {
            return true;
        }

        // 小幫手和賣家：透過訂單 → 訂單項目 → 商品 post_author 確認
        $seller_ids = SettingsService::get_accessible_seller_ids();
        if (empty($seller_ids)) {
            return false;
        }

        global $wpdb;
        $orders_table = $wpdb->prefix . 'fct_orders';
        $items_table  = $wpdb->prefix . 'fct_order_items';
        $posts_table  = $wpdb->posts;

        $seller_ids_str = implode(',', array_map('intval', $seller_ids));

        // 沿用 get_customers 列表中的賣家過濾邏輯：
        // 訂單 → 訂單項目 → 商品 post_author
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT o.id)
             FROM {$orders_table} o
             INNER JOIN {$items_table} oi ON o.id = oi.order_id
             INNER JOIN {$posts_table} p ON oi.post_id = p.ID OR oi.post_id = p.post_parent
             WHERE o.customer_id = %d AND p.post_author IN ({$seller_ids_str})",
            $customer_id
        ));

        return $count > 0;
    }
}
