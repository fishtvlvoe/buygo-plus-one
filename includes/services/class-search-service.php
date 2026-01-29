<?php

namespace BuyGoPlus\Services;

/**
 * SearchService - 全域搜尋服務
 *
 * 負責跨資料來源的搜尋、過濾、相關性排序和分頁
 *
 * @package BuyGoPlus\Services
 * @version 1.0.0
 */
class SearchService
{
    private $wpdb;
    private $debugService;
    private $table_orders;
    private $table_customers;
    private $table_shipments;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->debugService = DebugService::get_instance();
        $this->table_orders = $wpdb->prefix . 'fct_orders';
        $this->table_customers = $wpdb->prefix . 'fct_customers';
        $this->table_shipments = $wpdb->prefix . 'fct_shipments';
    }

    /**
     * 主搜尋方法
     *
     * @param array $params 搜尋參數
     *   - query (string): 搜尋關鍵字 (必填)
     *   - type (string): 資料類型 (all, product, order, customer, shipment)
     *   - status (string): 狀態過濾 (依資料類型而異)
     *   - date_from (string): 開始日期 (YYYY-MM-DD)
     *   - date_to (string): 結束日期 (YYYY-MM-DD)
     *   - page (int): 頁數
     *   - per_page (int): 每頁筆數
     * @return array ['items' => [], 'total' => int]
     */
    public function search(array $params): array
    {
        $query = $params['query'] ?? '';
        $type = $params['type'] ?? 'all';
        $status = $params['status'] ?? 'all';
        $date_from = $params['date_from'] ?? '';
        $date_to = $params['date_to'] ?? '';
        $page = max(1, intval($params['page'] ?? 1));
        $per_page = max(1, min(100, intval($params['per_page'] ?? 20)));

        $this->debugService->log('SearchService', '開始搜尋', [
            'query' => $query,
            'type' => $type,
            'status' => $status,
            'page' => $page,
            'per_page' => $per_page
        ]);

        try {
            $filters = [
                'status' => $status,
                'date_from' => $date_from,
                'date_to' => $date_to
            ];

            $all_results = [];

            // 根據 type 參數決定搜尋範圍
            if ($type === 'all' || $type === 'product') {
                $products = $this->search_products($query, $filters, 1000, 0);
                $all_results = array_merge($all_results, $products);
            }

            if ($type === 'all' || $type === 'order') {
                $orders = $this->search_orders($query, $filters, 1000, 0);
                $all_results = array_merge($all_results, $orders);
            }

            if ($type === 'all' || $type === 'customer') {
                $customers = $this->search_customers($query, $filters, 1000, 0);
                $all_results = array_merge($all_results, $customers);
            }

            if ($type === 'all' || $type === 'shipment') {
                $shipments = $this->search_shipments($query, $filters, 1000, 0);
                $all_results = array_merge($all_results, $shipments);
            }

            // 計算相關性分數
            foreach ($all_results as &$item) {
                $item['relevance_score'] = $this->calculate_relevance($item, $query, $item['type']);
            }

            // 按相關性排序
            usort($all_results, function($a, $b) {
                return $b['relevance_score'] <=> $a['relevance_score'];
            });

            // 計算總數
            $total = count($all_results);

            // 應用分頁
            $offset = ($page - 1) * $per_page;
            $paginated_results = array_slice($all_results, $offset, $per_page);

            $this->debugService->log('SearchService', '搜尋完成', [
                'total' => $total,
                'returned' => count($paginated_results)
            ]);

            return [
                'items' => $paginated_results,
                'total' => $total
            ];

        } catch (\Exception $e) {
            $this->debugService->log('SearchService', '搜尋失敗', [
                'error' => $e->getMessage()
            ], 'error');

            throw new \Exception('搜尋失敗：' . $e->getMessage());
        }
    }

    /**
     * 搜尋商品
     *
     * @param string $query 搜尋關鍵字
     * @param array $filters 過濾條件
     * @param int $limit 限制筆數
     * @param int $offset 偏移量
     * @return array 商品陣列
     */
    private function search_products($query, $filters, $limit, $offset)
    {
        $where_clauses = [
            "p.post_type = 'product'",
            $this->wpdb->prepare(
                "(p.post_title LIKE %s OR p.ID = %d)",
                '%' . $this->wpdb->esc_like($query) . '%',
                intval($query)
            )
        ];

        // 狀態過濾
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where_clauses[] = $this->wpdb->prepare(
                "p.post_status = %s",
                $filters['status']
            );
        } else {
            // 預設只顯示 publish 和 draft
            $where_clauses[] = "p.post_status IN ('publish', 'draft')";
        }

        // 日期過濾
        if (!empty($filters['date_from'])) {
            $where_clauses[] = $this->wpdb->prepare(
                "p.post_date >= %s",
                $filters['date_from'] . ' 00:00:00'
            );
        }
        if (!empty($filters['date_to'])) {
            $where_clauses[] = $this->wpdb->prepare(
                "p.post_date <= %s",
                $filters['date_to'] . ' 23:59:59'
            );
        }

        $where = implode(' AND ', $where_clauses);

        $sql = "
            SELECT
                p.ID as id,
                p.post_title as name,
                p.post_status as status,
                pm.meta_value as price,
                p.post_date as created_at,
                'product' as type,
                '商品' as type_label,
                CONCAT('/wp-admin/admin.php?page=buygo-products&id=', p.ID) as url,
                p.post_title as display_field,
                '' as display_sub_field
            FROM {$this->wpdb->posts} p
            LEFT JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_price'
            WHERE {$where}
            ORDER BY p.post_date DESC
            LIMIT %d OFFSET %d
        ";

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $limit, $offset),
            ARRAY_A
        );

        return array_map(function($product) {
            return [
                'id' => $product['id'],
                'name' => $product['name'],
                'type' => $product['type'],
                'type_label' => $product['type_label'],
                'status' => $product['status'],
                'url' => $product['url'],
                'display_field' => $product['display_field'],
                'display_sub_field' => $product['display_sub_field'],
                'created_at' => $product['created_at'],
                'price' => $product['price'],
                'relevance_score' => 0 // 將由 calculate_relevance 計算
            ];
        }, $results ?: []);
    }

    /**
     * 搜尋訂單
     *
     * @param string $query 搜尋關鍵字
     * @param array $filters 過濾條件
     * @param int $limit 限制筆數
     * @param int $offset 偏移量
     * @return array 訂單陣列
     */
    private function search_orders($query, $filters, $limit, $offset)
    {
        $where_clauses = [
            $this->wpdb->prepare(
                "(o.invoice_no LIKE %s OR o.customer_name LIKE %s OR o.id = %d)",
                '%' . $this->wpdb->esc_like($query) . '%',
                '%' . $this->wpdb->esc_like($query) . '%',
                intval($query)
            )
        ];

        // 狀態過濾
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where_clauses[] = $this->wpdb->prepare(
                "o.payment_status = %s",
                $filters['status']
            );
        }

        // 日期過濾
        if (!empty($filters['date_from'])) {
            $where_clauses[] = $this->wpdb->prepare(
                "o.created_at >= %s",
                $filters['date_from'] . ' 00:00:00'
            );
        }
        if (!empty($filters['date_to'])) {
            $where_clauses[] = $this->wpdb->prepare(
                "o.created_at <= %s",
                $filters['date_to'] . ' 23:59:59'
            );
        }

        $where = implode(' AND ', $where_clauses);

        $sql = "
            SELECT
                o.id,
                o.invoice_no,
                CONCAT('訂單 #', o.invoice_no) as name,
                o.customer_name,
                o.total_amount,
                o.payment_status as status,
                o.created_at,
                'order' as type,
                '訂單' as type_label,
                CONCAT('/wp-admin/admin.php?page=buygo-orders&id=', o.id) as url,
                CONCAT('訂單 #', o.invoice_no) as display_field,
                o.customer_name as display_sub_field
            FROM {$this->table_orders} o
            WHERE {$where}
            ORDER BY o.created_at DESC
            LIMIT %d OFFSET %d
        ";

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $limit, $offset),
            ARRAY_A
        );

        return array_map(function($order) {
            return [
                'id' => $order['id'],
                'invoice_no' => $order['invoice_no'],
                'name' => $order['name'],
                'type' => $order['type'],
                'type_label' => $order['type_label'],
                'status' => $order['status'],
                'url' => $order['url'],
                'display_field' => $order['display_field'],
                'display_sub_field' => $order['display_sub_field'],
                'created_at' => $order['created_at'],
                'customer_name' => $order['customer_name'],
                'total_amount' => $order['total_amount'],
                'relevance_score' => 0 // 將由 calculate_relevance 計算
            ];
        }, $results ?: []);
    }

    /**
     * 搜尋客戶
     *
     * @param string $query 搜尋關鍵字
     * @param array $filters 過濾條件
     * @param int $limit 限制筆數
     * @param int $offset 偏移量
     * @return array 客戶陣列
     */
    private function search_customers($query, $filters, $limit, $offset)
    {
        $where_clauses = [
            $this->wpdb->prepare(
                "(c.full_name LIKE %s OR c.email LIKE %s OR c.phone LIKE %s OR c.id = %d)",
                '%' . $this->wpdb->esc_like($query) . '%',
                '%' . $this->wpdb->esc_like($query) . '%',
                '%' . $this->wpdb->esc_like($query) . '%',
                intval($query)
            )
        ];

        // 狀態過濾（客戶表沒有明確的 status 欄位，這裡預留）
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            // 如果未來有狀態欄位可以在這裡處理
        }

        // 日期過濾
        if (!empty($filters['date_from'])) {
            $where_clauses[] = $this->wpdb->prepare(
                "c.created_at >= %s",
                $filters['date_from'] . ' 00:00:00'
            );
        }
        if (!empty($filters['date_to'])) {
            $where_clauses[] = $this->wpdb->prepare(
                "c.created_at <= %s",
                $filters['date_to'] . ' 23:59:59'
            );
        }

        $where = implode(' AND ', $where_clauses);

        $sql = "
            SELECT
                c.id,
                c.full_name as name,
                c.email,
                c.phone,
                c.created_at,
                'customer' as type,
                '客戶' as type_label,
                CONCAT('/wp-admin/admin.php?page=buygo-customers&id=', c.id) as url,
                c.full_name as display_field,
                c.email as display_sub_field
            FROM {$this->table_customers} c
            WHERE {$where}
            ORDER BY c.created_at DESC
            LIMIT %d OFFSET %d
        ";

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $limit, $offset),
            ARRAY_A
        );

        return array_map(function($customer) {
            return [
                'id' => $customer['id'],
                'name' => $customer['name'],
                'type' => $customer['type'],
                'type_label' => $customer['type_label'],
                'url' => $customer['url'],
                'display_field' => $customer['display_field'],
                'display_sub_field' => $customer['display_sub_field'],
                'created_at' => $customer['created_at'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'relevance_score' => 0 // 將由 calculate_relevance 計算
            ];
        }, $results ?: []);
    }

    /**
     * 搜尋出貨單
     *
     * @param string $query 搜尋關鍵字
     * @param array $filters 過濾條件
     * @param int $limit 限制筆數
     * @param int $offset 偏移量
     * @return array 出貨單陣列
     */
    private function search_shipments($query, $filters, $limit, $offset)
    {
        $where_clauses = [
            $this->wpdb->prepare(
                "(s.shipment_number LIKE %s OR s.customer_name LIKE %s OR s.id = %d)",
                '%' . $this->wpdb->esc_like($query) . '%',
                '%' . $this->wpdb->esc_like($query) . '%',
                intval($query)
            )
        ];

        // 狀態過濾
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where_clauses[] = $this->wpdb->prepare(
                "s.status = %s",
                $filters['status']
            );
        }

        // 日期過濾
        if (!empty($filters['date_from'])) {
            $where_clauses[] = $this->wpdb->prepare(
                "s.created_at >= %s",
                $filters['date_from'] . ' 00:00:00'
            );
        }
        if (!empty($filters['date_to'])) {
            $where_clauses[] = $this->wpdb->prepare(
                "s.created_at <= %s",
                $filters['date_to'] . ' 23:59:59'
            );
        }

        $where = implode(' AND ', $where_clauses);

        $sql = "
            SELECT
                s.id,
                s.shipment_number,
                CONCAT('出貨單 #', s.shipment_number) as name,
                s.customer_name,
                s.status,
                s.created_at,
                'shipment' as type,
                '出貨單' as type_label,
                CONCAT('/wp-admin/admin.php?page=buygo-shipment-details&id=', s.id) as url,
                CONCAT('出貨單 #', s.shipment_number) as display_field,
                s.customer_name as display_sub_field
            FROM {$this->table_shipments} s
            WHERE {$where}
            ORDER BY s.created_at DESC
            LIMIT %d OFFSET %d
        ";

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $limit, $offset),
            ARRAY_A
        );

        return array_map(function($shipment) {
            return [
                'id' => $shipment['id'],
                'name' => $shipment['name'],
                'type' => $shipment['type'],
                'type_label' => $shipment['type_label'],
                'status' => $shipment['status'],
                'url' => $shipment['url'],
                'display_field' => $shipment['display_field'],
                'display_sub_field' => $shipment['display_sub_field'],
                'created_at' => $shipment['created_at'],
                'customer_name' => $shipment['customer_name'],
                'relevance_score' => 0 // 將由 calculate_relevance 計算
            ];
        }, $results ?: []);
    }

    /**
     * 計算相關性分數
     *
     * 分數計算規則：
     * 1. 類型基礎分數：order=10, product=8, customer=6, shipment=5
     * 2. 完全匹配加分：+20
     * 3. ID 精確匹配：+15
     * 4. 位置加分：(100 - position) / 10（越前面的匹配分數越高）
     *
     * @param array $item 搜尋結果項目
     * @param string $query 搜尋關鍵字
     * @param string $type 資料類型
     * @return int 相關性分數
     */
    public function calculate_relevance($item, $query, $type): int
    {
        $score = 0;

        // 1. 類型基礎分數
        $base_scores = [
            'order' => 10,
            'product' => 8,
            'customer' => 6,
            'shipment' => 5
        ];
        $score += $base_scores[$type] ?? 0;

        // 2. 完全匹配加分
        $searchable_fields = [
            $item['name'] ?? '',
            $item['display_field'] ?? '',
            $item['display_sub_field'] ?? ''
        ];

        foreach ($searchable_fields as $field) {
            if (strcasecmp(trim($field), trim($query)) === 0) {
                $score += 20;
                break;
            }
        }

        // 3. ID 精確匹配
        if (isset($item['id']) && intval($item['id']) === intval($query)) {
            $score += 15;
        }

        // 4. 位置加分（檢查關鍵字在文字中的位置）
        foreach ($searchable_fields as $field) {
            $field_lower = mb_strtolower($field, 'UTF-8');
            $query_lower = mb_strtolower($query, 'UTF-8');
            $position = mb_strpos($field_lower, $query_lower, 0, 'UTF-8');

            if ($position !== false) {
                // 越前面的匹配分數越高
                $position_score = (100 - min(100, $position)) / 10;
                $score += $position_score;
                break;
            }
        }

        return intval($score);
    }
}
