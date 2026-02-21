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
     * 查詢 FluentCart 商品：wp_posts (fc_product) JOIN fct_product_variations
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

        // 建立 WHERE 條件
        $where_conditions = ["p.post_type = 'fc_product'"];
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
                        pv.price,
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
}
