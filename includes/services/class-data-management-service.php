<?php

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data Management Service - 資料管理服務
 *
 * 提供資料管理 Tab 的商業邏輯：
 * - 查詢訂單/商品/客戶（日期範圍 + 關鍵字）
 * - 刪除訂單（含串聯清理出貨單）
 * - 軟刪除商品（設為 inactive）
 * - 刪除客戶（FluentCart 資料，不刪 WP 帳號）
 * - 編輯客戶資料
 *
 * @package BuyGoPlus\Services
 * @version 1.0.0
 */
class DataManagementService
{
    /**
     * 查詢訂單（依日期範圍和關鍵字）
     *
     * @param array $params 查詢參數：date_from, date_to, keyword, page, per_page
     * @return array 分頁結果 ['data' => [...], 'total' => int, 'page' => int, 'per_page' => int]
     */
    public function queryOrders(array $params): array
    {
        global $wpdb;

        $date_from = sanitize_text_field($params['date_from'] ?? '');
        $date_to   = sanitize_text_field($params['date_to'] ?? '');
        $keyword   = sanitize_text_field($params['keyword'] ?? '');
        $page      = max(1, (int) ($params['page'] ?? 1));
        $per_page  = max(1, min(100, (int) ($params['per_page'] ?? 20)));
        $offset    = ($page - 1) * $per_page;

        $table_orders    = $wpdb->prefix . 'fct_orders';
        $table_items     = $wpdb->prefix . 'fct_order_items';
        $table_customers = $wpdb->prefix . 'fct_customers';

        // 建立 WHERE 條件
        $where_conditions = ['1=1'];
        $query_params = [];

        // 排除子訂單
        $where_conditions[] = 'o.parent_id IS NULL';

        // 日期篩選
        if (!empty($date_from)) {
            $where_conditions[] = 'o.created_at >= %s';
            $query_params[] = $date_from . ' 00:00:00';
        }
        if (!empty($date_to)) {
            $where_conditions[] = 'o.created_at <= %s';
            $query_params[] = $date_to . ' 23:59:59';
        }

        // 關鍵字搜尋（訂單編號或客戶名稱/Email）
        if (!empty($keyword)) {
            $like_keyword = '%' . $wpdb->esc_like($keyword) . '%';
            $where_conditions[] = '(o.invoice_no LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s)';
            $query_params[] = $like_keyword;
            $query_params[] = $like_keyword;
            $query_params[] = $like_keyword;
            $query_params[] = $like_keyword;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // 計算總數
        $count_sql = "SELECT COUNT(DISTINCT o.id)
                      FROM {$table_orders} o
                      LEFT JOIN {$table_customers} c ON o.customer_id = c.id
                      WHERE {$where_clause}";

        if (!empty($query_params)) {
            $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$query_params));
        } else {
            $total = (int) $wpdb->get_var($count_sql);
        }

        // 查詢訂單資料
        $data_sql = "SELECT
                        o.id,
                        o.invoice_no,
                        TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))) AS customer_name,
                        c.email AS customer_email,
                        o.total_amount,
                        o.currency,
                        o.status,
                        o.payment_status,
                        (SELECT COUNT(*) FROM {$table_items} oi WHERE oi.order_id = o.id) AS item_count,
                        o.created_at
                     FROM {$table_orders} o
                     LEFT JOIN {$table_customers} c ON o.customer_id = c.id
                     WHERE {$where_clause}
                     ORDER BY o.created_at DESC
                     LIMIT %d OFFSET %d";

        $data_params = array_merge($query_params, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($data_sql, ...$data_params), ARRAY_A);

        return [
            'data'     => is_array($rows) ? $rows : [],
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
        ];
    }

    /**
     * 查詢商品（依日期範圍和關鍵字）
     *
     * 查詢 FluentCart 商品：wp_posts (fluent-products) JOIN fct_product_variations
     *
     * @param array $params 查詢參數：date_from, date_to, keyword, page, per_page
     * @return array 分頁結果
     */
    public function queryProducts(array $params): array
    {
        global $wpdb;

        $date_from = sanitize_text_field($params['date_from'] ?? '');
        $date_to   = sanitize_text_field($params['date_to'] ?? '');
        $keyword   = sanitize_text_field($params['keyword'] ?? '');
        $page      = max(1, (int) ($params['page'] ?? 1));
        $per_page  = max(1, min(100, (int) ($params['per_page'] ?? 20)));
        $offset    = ($page - 1) * $per_page;

        $table_variations = $wpdb->prefix . 'fct_product_variations';
        $table_posts      = $wpdb->posts;

        // 建立 WHERE 條件（FluentCart 使用 fluent-products 作為 post_type）
        $where_conditions = ["p.post_type = 'fluent-products'"];
        $query_params = [];

        // 只顯示非 inactive 的商品
        $where_conditions[] = "pv.item_status != 'inactive'";

        // 日期篩選
        if (!empty($date_from)) {
            $where_conditions[] = 'p.post_date >= %s';
            $query_params[] = $date_from . ' 00:00:00';
        }
        if (!empty($date_to)) {
            $where_conditions[] = 'p.post_date <= %s';
            $query_params[] = $date_to . ' 23:59:59';
        }

        // 關鍵字搜尋（商品名稱）
        if (!empty($keyword)) {
            $like_keyword = '%' . $wpdb->esc_like($keyword) . '%';
            $where_conditions[] = 'p.post_title LIKE %s';
            $query_params[] = $like_keyword;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // 計算總數
        $count_sql = "SELECT COUNT(DISTINCT pv.id)
                      FROM {$table_variations} pv
                      INNER JOIN {$table_posts} p ON pv.post_id = p.ID
                      WHERE {$where_clause}";

        if (!empty($query_params)) {
            $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$query_params));
        } else {
            $total = (int) $wpdb->get_var($count_sql);
        }

        // 查詢商品資料
        $data_sql = "SELECT
                        pv.id,
                        pv.post_id,
                        p.post_title AS name,
                        pv.item_price AS price,
                        p.post_status AS status,
                        pv.item_status,
                        p.post_date AS created_at
                     FROM {$table_variations} pv
                     INNER JOIN {$table_posts} p ON pv.post_id = p.ID
                     WHERE {$where_clause}
                     ORDER BY p.post_date DESC
                     LIMIT %d OFFSET %d";

        $data_params = array_merge($query_params, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($data_sql, ...$data_params), ARRAY_A);

        return [
            'data'     => is_array($rows) ? $rows : [],
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
        ];
    }

    /**
     * 查詢客戶（依日期範圍和關鍵字）
     *
     * @param array $params 查詢參數：date_from, date_to, keyword, page, per_page
     * @return array 分頁結果
     */
    public function queryCustomers(array $params): array
    {
        global $wpdb;

        $date_from = sanitize_text_field($params['date_from'] ?? '');
        $date_to   = sanitize_text_field($params['date_to'] ?? '');
        $keyword   = sanitize_text_field($params['keyword'] ?? '');
        $page      = max(1, (int) ($params['page'] ?? 1));
        $per_page  = max(1, min(100, (int) ($params['per_page'] ?? 20)));
        $offset    = ($page - 1) * $per_page;

        $table_customers = $wpdb->prefix . 'fct_customers';
        $table_addresses = $wpdb->prefix . 'fct_customer_addresses';
        $table_orders    = $wpdb->prefix . 'fct_orders';

        // 建立 WHERE 條件
        $where_conditions = ['1=1'];
        $query_params = [];

        // 日期篩選
        if (!empty($date_from)) {
            $where_conditions[] = 'c.created_at >= %s';
            $query_params[] = $date_from . ' 00:00:00';
        }
        if (!empty($date_to)) {
            $where_conditions[] = 'c.created_at <= %s';
            $query_params[] = $date_to . ' 23:59:59';
        }

        // 關鍵字搜尋（姓名或 Email）
        if (!empty($keyword)) {
            $like_keyword = '%' . $wpdb->esc_like($keyword) . '%';
            $where_conditions[] = '(c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s)';
            $query_params[] = $like_keyword;
            $query_params[] = $like_keyword;
            $query_params[] = $like_keyword;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // 計算總數
        $count_sql = "SELECT COUNT(DISTINCT c.id)
                      FROM {$table_customers} c
                      WHERE {$where_clause}";

        if (!empty($query_params)) {
            $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$query_params));
        } else {
            $total = (int) $wpdb->get_var($count_sql);
        }

        // 查詢客戶資料
        $data_sql = "SELECT
                        c.id,
                        c.user_id,
                        c.email,
                        TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))) AS full_name,
                        (SELECT a.phone FROM {$table_addresses} a WHERE a.customer_id = c.id AND a.is_primary = 1 LIMIT 1) AS phone,
                        (SELECT COUNT(*) FROM {$table_orders} o WHERE o.customer_id = c.id) AS order_count,
                        (SELECT COALESCE(SUM(o.total_amount), 0) FROM {$table_orders} o WHERE o.customer_id = c.id) AS total_spent,
                        c.created_at
                     FROM {$table_customers} c
                     WHERE {$where_clause}
                     ORDER BY c.created_at DESC
                     LIMIT %d OFFSET %d";

        $data_params = array_merge($query_params, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($data_sql, ...$data_params), ARRAY_A);

        return [
            'data'     => is_array($rows) ? $rows : [],
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
        ];
    }

    // =========================================================================
    // 刪除方法
    // =========================================================================

    /**
     * 刪除訂單（硬刪除，含串聯清理出貨單）
     *
     * 刪除順序（外鍵完整性）：
     * 1. buygo_shipment_items（依 order_id）
     * 2. buygo_shipments（無剩餘 items 的出貨單）
     * 3. fct_order_items
     * 4. fct_order_addresses
     * 5. 子訂單的 items/addresses
     * 6. fct_orders（含子訂單）
     *
     * @param array $order_ids 訂單 ID 陣列
     * @return array 結果
     */
    public function deleteOrders(array $order_ids): array
    {
        global $wpdb;

        // 驗證所有 ID 為正整數
        $order_ids = array_filter(array_map('intval', $order_ids), function ($id) {
            return $id > 0;
        });

        if (empty($order_ids)) {
            return [
                'success'       => false,
                'message'       => '無有效的訂單 ID',
                'deleted_count' => 0,
                'deleted_ids'   => [],
                'cascade'       => ['shipment_items' => 0, 'shipments' => 0, 'order_items' => 0, 'child_orders' => 0],
            ];
        }

        $table_orders           = $wpdb->prefix . 'fct_orders';
        $table_order_items      = $wpdb->prefix . 'fct_order_items';
        $table_order_addresses  = $wpdb->prefix . 'fct_order_addresses';
        $table_shipments        = $wpdb->prefix . 'buygo_shipments';
        $table_shipment_items   = $wpdb->prefix . 'buygo_shipment_items';

        $cascade = [
            'shipment_items' => 0,
            'shipments'      => 0,
            'order_items'    => 0,
            'child_orders'   => 0,
        ];

        $wpdb->query('START TRANSACTION');

        try {
            foreach ($order_ids as $order_id) {
                // 1. 找出子訂單 ID
                $child_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM {$table_orders} WHERE parent_id = %d",
                    $order_id
                ));
                $child_ids = is_array($child_ids) ? array_map('intval', $child_ids) : [];
                $cascade['child_orders'] += count($child_ids);

                // 所有要處理的訂單 ID（父 + 子）
                $all_ids = array_merge([$order_id], $child_ids);

                foreach ($all_ids as $id) {
                    // 2. 刪除 buygo_shipment_items
                    $deleted_si = $wpdb->query($wpdb->prepare(
                        "DELETE FROM {$table_shipment_items} WHERE order_id = %d",
                        $id
                    ));
                    if ($deleted_si === false) {
                        throw new \Exception('刪除 shipment_items 失敗：' . $wpdb->last_error);
                    }
                    $cascade['shipment_items'] += (int) $deleted_si;

                    // 3. 刪除沒有剩餘 items 的 buygo_shipments
                    $empty_shipments = $wpdb->get_col(
                        "SELECT s.id FROM {$table_shipments} s
                         WHERE NOT EXISTS (
                             SELECT 1 FROM {$table_shipment_items} si WHERE si.shipment_id = s.id
                         )"
                    );
                    if (!empty($empty_shipments)) {
                        $placeholders = implode(',', array_fill(0, count($empty_shipments), '%d'));
                        $deleted_s = $wpdb->query($wpdb->prepare(
                            "DELETE FROM {$table_shipments} WHERE id IN ({$placeholders})",
                            ...array_map('intval', $empty_shipments)
                        ));
                        if ($deleted_s === false) {
                            throw new \Exception('刪除 shipments 失敗：' . $wpdb->last_error);
                        }
                        $cascade['shipments'] += (int) $deleted_s;
                    }

                    // 4. 刪除 fct_order_items
                    $deleted_oi = $wpdb->query($wpdb->prepare(
                        "DELETE FROM {$table_order_items} WHERE order_id = %d",
                        $id
                    ));
                    if ($deleted_oi === false) {
                        throw new \Exception('刪除 order_items 失敗：' . $wpdb->last_error);
                    }
                    $cascade['order_items'] += (int) $deleted_oi;

                    // 5. 刪除 fct_order_addresses
                    $wpdb->query($wpdb->prepare(
                        "DELETE FROM {$table_order_addresses} WHERE order_id = %d",
                        $id
                    ));
                }

                // 6. 刪除 fct_orders（父訂單 + 子訂單）
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$table_orders} WHERE id = %d OR parent_id = %d",
                    $order_id,
                    $order_id
                ));
            }

            $wpdb->query('COMMIT');

            return [
                'success'       => true,
                'deleted_count' => count($order_ids),
                'deleted_ids'   => $order_ids,
                'cascade'       => $cascade,
            ];
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');

            return [
                'success'       => false,
                'message'       => '刪除訂單失敗：' . $e->getMessage(),
                'deleted_count' => 0,
                'deleted_ids'   => [],
                'cascade'       => $cascade,
            ];
        }
    }

    /**
     * 軟刪除商品（設為 inactive，沿用既有 FluentCart 慣例）
     *
     * @param array $product_ids ProductVariation ID 陣列
     * @return array 結果
     */
    public function deleteProducts(array $product_ids): array
    {
        $product_ids = array_filter(array_map('intval', $product_ids), function ($id) {
            return $id > 0;
        });

        if (empty($product_ids)) {
            return ['success' => false, 'deleted_count' => 0, 'failed_ids' => []];
        }

        $deleted_count = 0;
        $failed_ids = [];

        foreach ($product_ids as $id) {
            try {
                $variation = \FluentCart\App\Models\ProductVariation::find($id);
                if ($variation) {
                    $variation->item_status = 'inactive';
                    $variation->save();
                    $deleted_count++;
                } else {
                    $failed_ids[] = $id;
                }
            } catch (\Exception $e) {
                $failed_ids[] = $id;
            }
        }

        return [
            'success'       => true,
            'deleted_count' => $deleted_count,
            'failed_ids'    => $failed_ids,
        ];
    }

    /**
     * 刪除客戶（硬刪除 FluentCart 客戶資料，不刪 WP 帳號）
     *
     * @param array $customer_ids FluentCart customer ID 陣列
     * @return array 結果
     */
    public function deleteCustomers(array $customer_ids): array
    {
        global $wpdb;

        $customer_ids = array_filter(array_map('intval', $customer_ids), function ($id) {
            return $id > 0;
        });

        if (empty($customer_ids)) {
            return ['success' => false, 'deleted_count' => 0, 'deleted_ids' => []];
        }

        $table_customers = $wpdb->prefix . 'fct_customers';
        $table_addresses = $wpdb->prefix . 'fct_customer_addresses';

        $wpdb->query('START TRANSACTION');

        try {
            foreach ($customer_ids as $customer_id) {
                // 1. 刪除客戶地址
                $result = $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$table_addresses} WHERE customer_id = %d",
                    $customer_id
                ));
                if ($result === false) {
                    throw new \Exception('刪除客戶地址失敗：' . $wpdb->last_error);
                }

                // 2. 刪除客戶記錄
                $result = $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$table_customers} WHERE id = %d",
                    $customer_id
                ));
                if ($result === false) {
                    throw new \Exception('刪除客戶記錄失敗：' . $wpdb->last_error);
                }
            }

            $wpdb->query('COMMIT');

            return [
                'success'       => true,
                'deleted_count' => count($customer_ids),
                'deleted_ids'   => $customer_ids,
            ];
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');

            return [
                'success'       => false,
                'message'       => '刪除客戶失敗：' . $e->getMessage(),
                'deleted_count' => 0,
                'deleted_ids'   => [],
            ];
        }
    }

    // =========================================================================
    // 編輯方法
    // =========================================================================

    /**
     * 更新客戶資料
     *
     * 更新 fct_customers（姓名）+ fct_customer_addresses（地址/電話）
     * 可選：若客戶有 user_id 且提供 taiwan_id_number，更新 wp_usermeta
     *
     * @param int   $customer_id FluentCart 客戶 ID
     * @param array $data        可接受欄位：first_name, last_name, phone, address_1, address_2, city, state, postcode, country, taiwan_id_number
     * @return array 結果
     */
    public function updateCustomer(int $customer_id, array $data): array
    {
        global $wpdb;

        if ($customer_id <= 0) {
            return ['success' => false, 'message' => '無效的客戶 ID'];
        }

        $table_customers = $wpdb->prefix . 'fct_customers';
        $table_addresses = $wpdb->prefix . 'fct_customer_addresses';

        // 確認客戶存在
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id FROM {$table_customers} WHERE id = %d",
            $customer_id
        ));

        if (!$customer) {
            return ['success' => false, 'message' => '客戶不存在'];
        }

        // 允許的客戶表欄位
        $customer_fields = [];
        if (isset($data['first_name'])) {
            $customer_fields['first_name'] = sanitize_text_field($data['first_name']);
        }
        if (isset($data['last_name'])) {
            $customer_fields['last_name'] = sanitize_text_field($data['last_name']);
        }

        // 更新 fct_customers
        if (!empty($customer_fields)) {
            $customer_fields['updated_at'] = current_time('mysql');
            $result = $wpdb->update(
                $table_customers,
                $customer_fields,
                ['id' => $customer_id],
                array_fill(0, count($customer_fields), '%s'),
                ['%d']
            );
            if ($result === false) {
                return ['success' => false, 'message' => '更新客戶資料失敗：' . $wpdb->last_error];
            }
        }

        // 允許的地址表欄位
        $address_fields = [];
        $address_keys = ['phone', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country'];
        foreach ($address_keys as $key) {
            if (isset($data[$key])) {
                $address_fields[$key] = sanitize_text_field($data[$key]);
            }
        }

        // 更新 fct_customer_addresses（主要地址）
        if (!empty($address_fields)) {
            $address_fields['updated_at'] = current_time('mysql');

            // 檢查是否有主要地址
            $has_primary = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_addresses} WHERE customer_id = %d AND is_primary = 1 LIMIT 1",
                $customer_id
            ));

            if ($has_primary) {
                $result = $wpdb->update(
                    $table_addresses,
                    $address_fields,
                    ['customer_id' => $customer_id, 'is_primary' => 1],
                    array_fill(0, count($address_fields), '%s'),
                    ['%d', '%d']
                );
                if ($result === false) {
                    return ['success' => false, 'message' => '更新客戶地址失敗：' . $wpdb->last_error];
                }
            } else {
                // 若無主要地址，新建一筆
                $address_fields['customer_id'] = $customer_id;
                $address_fields['is_primary'] = 1;
                $address_fields['created_at'] = current_time('mysql');
                $wpdb->insert($table_addresses, $address_fields);
            }
        }

        // 更新身分證字號（wp_usermeta）
        if (isset($data['taiwan_id_number']) && !empty($customer->user_id)) {
            $taiwan_id = sanitize_text_field($data['taiwan_id_number']);
            update_user_meta((int) $customer->user_id, 'buygo_taiwan_id_number', $taiwan_id);
        }

        return ['success' => true, 'customer_id' => $customer_id];
    }
}
