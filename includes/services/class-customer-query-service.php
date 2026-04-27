<?php

namespace BuyGoPlus\Services;

/**
 * CustomerQueryService — 客戶查詢服務
 *
 * 負責封裝所有客戶相關的資料庫查詢邏輯，
 * 原本散落在 CustomersAPI 的 $wpdb 直接查詢已遷移至此。
 */
class CustomerQueryService
{
    /**
     * 取得客戶列表（分頁）
     *
     * 包含：分頁、搜尋、多賣家權限過濾、頭像、LINE 名稱、姓名清理。
     * 快取邏輯保留在 API handler（屬於 API 層職責）。
     *
     * @param array $params   查詢參數，支援 page、per_page、search
     * @param int   $user_id  當前使用者 ID
     * @param bool  $is_admin 是否為平台管理員（管理員可看所有客戶）
     * @return array{customers: array, total: int, page: int, per_page: int, total_pages: int}
     */
    public function getListCustomers(array $params, int $user_id, bool $is_admin): array
    {
        global $wpdb;

        $page     = $params['page'] ?? 1;
        $per_page = $params['per_page'] ?? 20;
        $search   = $params['search'] ?? '';
        $offset   = ($page - 1) * $per_page;

        $table_customers = $wpdb->prefix . 'fct_customers';
        $table_orders    = $wpdb->prefix . 'fct_orders';
        $table_addresses = $wpdb->prefix . 'fct_customer_addresses';
        // WordPress 原生 $wpdb->usermeta 等同 prefix . 'usermeta'，用拼接確保 mock 相容
        $table_usermeta  = $wpdb->prefix . 'usermeta';

        // 建立基礎過濾條件
        $where_conditions = ['1=1'];
        $query_params     = [];

        // 多賣家權限過濾：非管理員只能看到購買自己商品的客戶
        if (!$is_admin && $user_id > 0) {
            $accessible_seller_ids = SettingsService::get_accessible_seller_ids($user_id);

            if (!empty($accessible_seller_ids)) {
                $table_items    = $wpdb->prefix . 'fct_order_items';
                $table_posts    = $wpdb->posts;
                $seller_ids_str = implode(',', array_map('intval', $accessible_seller_ids));

                // 透過 訂單 → 訂單項目 → 商品 → post_author 關聯到賣家
                $where_conditions[] = "c.id IN (
                    SELECT DISTINCT o2.customer_id
                    FROM {$table_orders} o2
                    INNER JOIN {$table_items} oi ON o2.id = oi.order_id
                    INNER JOIN {$table_posts} p ON oi.post_id = p.ID OR oi.post_id = p.post_parent
                    WHERE p.post_author IN ({$seller_ids_str})
                )";
            } else {
                // 沒有可存取的賣家 ID，直接回傳空結果
                $where_conditions[] = '1 = 0';
            }
        }

        // 計算總數（含賣家過濾，不含搜尋條件）
        $base_where = implode(' AND ', $where_conditions);
        $total      = $wpdb->get_var(
            "SELECT COUNT(DISTINCT c.id) FROM {$table_customers} c WHERE {$base_where}"
        );

        if (!empty($search)) {
            // 搜尋條件：姓名、Email、自訂編號（buygo_custom_id）
            $search_term        = '%' . $wpdb->esc_like($search) . '%';
            $where_conditions[] = "(CONCAT(c.first_name, ' ', c.last_name) LIKE %s
                                 OR c.email LIKE %s
                                 OR um_custom_id.meta_value LIKE %s)";
            $query_params[]     = $search_term;
            $query_params[]     = $search_term;
            $query_params[]     = $search_term;

            // 重新計算搜尋後的總數（含 JOIN usermeta）
            $count_query = "SELECT COUNT(DISTINCT c.id)
                           FROM {$table_customers} c
                           LEFT JOIN {$table_usermeta} um_custom_id
                               ON c.user_id = um_custom_id.user_id
                               AND um_custom_id.meta_key = 'buygo_custom_id'
                           WHERE " . implode(' AND ', $where_conditions);
            $total       = $wpdb->get_var($wpdb->prepare($count_query, ...$query_params));
        }

        $where_clause = implode(' AND ', $where_conditions);

        // 取得客戶資料（直接從 fct_orders 聚合計算，不使用 FluentCart 的統計欄位）
        // 名稱優先使用 fct_customer_addresses.name（收件地址中的正式名稱），
        // phone 和 address 從 fct_customer_addresses 表取得，
        // custom_id 從 wp_usermeta（buygo_custom_id）JOIN 取得
        $query = "SELECT
                    c.id,
                    c.first_name,
                    c.last_name,
                    -- 優先使用地址表中的 name（正式收件人名稱），若無則使用 fct_customers 的名稱
                    COALESCE(
                        (SELECT a.name FROM {$table_addresses} a WHERE a.customer_id = c.id AND a.is_primary = 1 AND a.name IS NOT NULL AND a.name != '' LIMIT 1),
                        NULLIF(TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))), ''),
                        c.email
                    ) as full_name,
                    c.email,
                    c.status,
                    COUNT(o.id) as order_count,
                    COALESCE(SUM(o.total_amount), 0) as total_spent,
                    MAX(o.created_at) as last_order_date,
                    (SELECT a.phone FROM {$table_addresses} a WHERE a.customer_id = c.id AND a.is_primary = 1 LIMIT 1) as phone,
                    (SELECT CONCAT(COALESCE(a.city, ''), ', ', COALESCE(a.state, ''), ', ', COALESCE(a.country, ''))
                     FROM {$table_addresses} a WHERE a.customer_id = c.id AND a.is_primary = 1 LIMIT 1) as address,
                    um_custom_id.meta_value as custom_id
                  FROM {$table_customers} c
                  LEFT JOIN {$table_orders} o ON c.id = o.customer_id
                  LEFT JOIN {$table_usermeta} um_custom_id
                      ON c.user_id = um_custom_id.user_id
                      AND um_custom_id.meta_key = 'buygo_custom_id'
                  WHERE {$where_clause}
                  GROUP BY c.id
                  ORDER BY last_order_date DESC, c.id DESC
                  LIMIT %d OFFSET %d";

        // 添加 LIMIT 和 OFFSET 參數
        $query_params[] = (int) $per_page;
        $query_params[] = (int) $offset;

        // 執行查詢
        $customers = $wpdb->get_results(
            $wpdb->prepare($query, ...$query_params),
            ARRAY_A
        );

        // 如果查詢失敗，記錄錯誤
        if ($customers === null) {
            error_log('BuyGo CustomerQueryService Error: ' . $wpdb->last_error);
            $customers = [];
        }

        // 為每個客戶添加頭像 URL、LINE 名稱，並清理姓名
        if (is_array($customers)) {
            foreach ($customers as &$customer) {
                // 清理姓名：移除表情符號和特殊符號
                $customer['full_name'] = $this->sanitize_customer_name($customer['full_name']);

                // 如果清理後的名稱為空，使用 email 作為顯示名稱
                if (empty($customer['full_name'])) {
                    $customer['full_name'] = $customer['email'];
                }

                // 取得 WordPress user_id（從 fct_customers 表）
                $wp_user_id = $this->get_wp_user_id_by_customer_id($customer['id']);

                if ($wp_user_id) {
                    // 優先使用 FluentCart 儲存的客戶照片（來自 LINE 登入）
                    $avatar_url         = get_user_meta($wp_user_id, 'fc_customer_photo_url', true);
                    $customer['avatar'] = !empty($avatar_url) ? esc_url($avatar_url) : get_avatar_url($customer['email'], ['size' => 100]);

                    // 取得 LINE 名稱
                    $customer['line_display_name'] = get_user_meta($wp_user_id, 'buygo_line_display_name', true) ?: '';
                } else {
                    // 沒有 user_id 則使用 Gravatar
                    $customer['avatar']            = get_avatar_url($customer['email'], ['size' => 100]);
                    $customer['line_display_name'] = '';
                }
            }
            unset($customer); // 解除參考
        }

        return [
            'customers'   => $customers,
            'total'       => (int) $total,
            'page'        => (int) $page,
            'per_page'    => (int) $per_page,
            'total_pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 1,
        ];
    }

    /**
     * 取得單一客戶詳情
     *
     * 包含：基本資料、所有訂單、地址子欄位、LINE 名稱、身分證、自訂編號。
     *
     * @param int $customer_id 客戶 ID
     * @return array{id: int, email: string, full_name: string, ...}|null
     *         找不到時回傳 null
     */
    public function getCustomerDetail(int $customer_id): ?array
    {
        global $wpdb;

        $table_customers = $wpdb->prefix . 'fct_customers';
        $table_orders    = $wpdb->prefix . 'fct_orders';
        $table_addresses = $wpdb->prefix . 'fct_customer_addresses';

        // 取得客戶基本資料（直接從 fct_orders 聚合計算，不使用 FluentCart 的統計欄位）
        // 名稱優先使用 fct_customer_addresses.name（收件地址中的正式名稱），
        // 因為這是客戶在結帳時填寫的正式收件人名稱，不會包含表情符號
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT
                c.id,
                c.user_id,
                c.email,
                c.first_name,
                c.last_name,
                -- 優先使用地址表中的 name（正式收件人名稱），若無則使用 fct_customers 的名稱
                COALESCE(
                    (SELECT a.name FROM {$table_addresses} a WHERE a.customer_id = c.id AND a.is_primary = 1 AND a.name IS NOT NULL AND a.name != '' LIMIT 1),
                    NULLIF(TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))), ''),
                    c.email
                ) as full_name,
                c.status,
                COUNT(o.id) as order_count,
                COALESCE(SUM(o.total_amount), 0) as total_spent,
                MAX(o.created_at) as last_purchase_date,
                c.notes as note,
                (SELECT a.phone FROM {$table_addresses} a WHERE a.customer_id = c.id AND a.is_primary = 1 LIMIT 1) as phone,
                (SELECT CONCAT(
                    COALESCE(a.address_1, ''), ' ',
                    COALESCE(a.address_2, ''), ', ',
                    COALESCE(a.city, ''), ', ',
                    COALESCE(a.state, ''), ' ',
                    COALESCE(a.postcode, ''), ', ',
                    COALESCE(a.country, '')
                ) FROM {$table_addresses} a WHERE a.customer_id = c.id AND a.is_primary = 1 LIMIT 1) as address
             FROM {$table_customers} c
             LEFT JOIN {$table_orders} o ON c.id = o.customer_id
             WHERE c.id = %d
             GROUP BY c.id",
            $customer_id
        ), ARRAY_A);

        if (!$customer) {
            return null;
        }

        // 取得客戶的所有訂單（不限制狀態）
        // 注意：fct_orders 表沒有 order_number，使用 id 或 receipt_number
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT
                id,
                id as order_number,
                total_amount,
                status as order_status,
                payment_status,
                created_at,
                currency
             FROM {$table_orders}
             WHERE customer_id = %d
             ORDER BY created_at DESC",
            $customer_id
        ), ARRAY_A);

        // 補上 currency fallback
        if (is_array($orders)) {
            foreach ($orders as &$o) {
                if (empty($o['currency'])) {
                    $o['currency'] = 'TWD';
                }
            }
            unset($o);
        }

        $customer['orders'] = $orders ?: [];

        // 清理姓名：移除表情符號和特殊符號
        $customer['full_name'] = $this->sanitize_customer_name($customer['full_name']);

        // 如果清理後的名稱為空，使用 email 作為顯示名稱
        if (empty($customer['full_name'])) {
            $customer['full_name'] = $customer['email'];
        }

        // 取得 LINE 名稱、身分證字號、自訂編號（從 wp_usermeta）
        if (!empty($customer['user_id'])) {
            $customer['line_display_name'] = get_user_meta($customer['user_id'], 'buygo_line_display_name', true) ?: '';
            $customer['taiwan_id_number']  = get_user_meta($customer['user_id'], 'buygo_taiwan_id_number', true) ?: '';
            $customer['custom_id']         = get_user_meta($customer['user_id'], 'buygo_custom_id', true) ?: '';
        } else {
            $customer['line_display_name'] = '';
            $customer['taiwan_id_number']  = '';
            $customer['custom_id']         = '';
        }

        // 取得地址子欄位（供 inline 編輯使用）
        $address_detail = $wpdb->get_row($wpdb->prepare(
            "SELECT address_1, address_2, city, state, postcode, country
             FROM {$table_addresses}
             WHERE customer_id = %d AND is_primary = 1
             LIMIT 1",
            $customer_id
        ), ARRAY_A);

        if ($address_detail) {
            $customer['address_1'] = $address_detail['address_1'] ?? '';
            $customer['address_2'] = $address_detail['address_2'] ?? '';
            $customer['city']      = $address_detail['city'] ?? '';
            $customer['state']     = $address_detail['state'] ?? '';
            $customer['postcode']  = $address_detail['postcode'] ?? '';
            $customer['country']   = $address_detail['country'] ?? '';
        } else {
            $customer['address_1'] = '';
            $customer['address_2'] = '';
            $customer['city']      = '';
            $customer['state']     = '';
            $customer['postcode']  = '';
            $customer['country']   = '';
        }

        return $customer;
    }

    /**
     * 根據 FluentCart customer_id 取得 WordPress user_id
     *
     * @param int $customer_id FluentCart 客戶 ID
     * @return int|null WordPress user_id 或 null
     */
    private function get_wp_user_id_by_customer_id($customer_id)
    {
        global $wpdb;
        $table_customers = $wpdb->prefix . 'fct_customers';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$table_customers} WHERE id = %d",
            $customer_id
        ));
    }

    /**
     * 清理名稱：移除表情符號和特殊符號，只保留中文、英文、數字和基本標點
     *
     * @param string $name 原始名稱
     * @return string 清理後的名稱
     */
    private function sanitize_customer_name($name)
    {
        if (empty($name)) {
            return '';
        }

        // 移除表情符號和特殊符號
        // 保留：中文字(CJK)、英文字母、數字、空格、基本標點（句號、逗號、破折號等）
        // Unicode 範圍：
        // - 中文字：\x{4e00}-\x{9fff} (CJK Unified Ideographs)
        // - 中文標點：\x{3000}-\x{303f}
        // - 全形字符：\x{ff00}-\x{ffef}
        // - 日文假名：\x{3040}-\x{30ff}
        $cleaned = preg_replace(
            '/[^\p{Han}\p{Latin}\p{N}\s\.\,\-\_\(\)\[\]\/\·\、\。\，\：\；\！\？\「\」\『\』\（\）\【\】]/u',
            '',
            $name
        );

        // 移除多餘的空格
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = trim($cleaned);

        return $cleaned;
    }
}
