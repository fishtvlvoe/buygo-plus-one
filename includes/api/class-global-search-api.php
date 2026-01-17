<?php
namespace BuyGoPlus\Api;

if (!defined('ABSPATH')) {
    exit;
}

class GlobalSearch_API {
    public function register_routes() {
        register_rest_route('buygo-plus-one/v1', '/global-search', [
            'methods' => 'GET',
            'callback' => [$this, 'search'],
            'permission_callback' => 'BuyGoPlus\Api\API::check_permission',
            'args' => [
                'query' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit' => [
                    'default' => 10,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    public function search($request) {
        $query = $request->get_param('query');
        $limit = $request->get_param('limit');

        if (empty($query)) {
            return new \WP_Error('missing_query', 'Search query is required', ['status' => 400]);
        }

        $results = [];

        try {
            // 搜索商品
            $products = $this->search_products($query, $limit);
            $results = array_merge($results, $products);

            // 搜索訂單
            $orders = $this->search_orders($query, $limit);
            $results = array_merge($results, $orders);

            // 搜索客戶
            $customers = $this->search_customers($query, $limit);
            $results = array_merge($results, $customers);

            // 搜索出貨單
            $shipments = $this->search_shipments($query, $limit);
            $results = array_merge($results, $shipments);

            // 按相關性排序（這裡簡化為按ID排序）
            usort($results, function($a, $b) {
                return $b['relevance_score'] <=> $a['relevance_score'];
            });

            // 限制總結果數量
            $results = array_slice($results, 0, $limit);

            return [
                'success' => true,
                'data' => $results,
                'total' => count($results),
            ];

        } catch (\Exception $e) {
            return new \WP_Error('search_error', $e->getMessage(), ['status' => 500]);
        }
    }

    private function search_products($query, $limit) {
        global $wpdb;

        $sql = $wpdb->prepare("
            SELECT
                p.ID as id,
                p.post_title as name,
                pm.meta_value as price,
                'product' as type,
                '商品' as type_label,
                CONCAT('/wp-admin/admin.php?page=buygo-products&id=', p.ID) as url,
                p.post_title as display_field,
                '' as display_sub_field,
                10 as relevance_score
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_price'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND (p.post_title LIKE %s OR p.ID LIKE %s)
            LIMIT %d
        ", '%' . $wpdb->esc_like($query) . '%', '%' . $wpdb->esc_like($query) . '%', $limit);

        $products = $wpdb->get_results($sql, ARRAY_A);

        return array_map(function($product) {
            return [
                'id' => $product['id'],
                'name' => $product['name'],
                'type' => $product['type'],
                'type_label' => $product['type_label'],
                'url' => $product['url'],
                'display_field' => $product['display_field'],
                'display_sub_field' => $product['display_sub_field'],
                'relevance_score' => $product['relevance_score'],
                'price' => $product['price'],
            ];
        }, $products ?: []);
    }

    private function search_orders($query, $limit) {
        global $wpdb;

        $sql = $wpdb->prepare("
            SELECT
                o.id,
                CONCAT('訂單 #', o.invoice_no) as name,
                o.customer_name,
                o.total_amount,
                'order' as type,
                '訂單' as type_label,
                CONCAT('/wp-admin/admin.php?page=buygo-orders&id=', o.id) as url,
                CONCAT('訂單 #', o.invoice_no) as display_field,
                o.customer_name as display_sub_field,
                9 as relevance_score
            FROM {$wpdb->prefix}buygo_orders o
            WHERE o.invoice_no LIKE %s
            OR o.customer_name LIKE %s
            OR o.id LIKE %s
            LIMIT %d
        ", '%' . $wpdb->esc_like($query) . '%', '%' . $wpdb->esc_like($query) . '%', '%' . $wpdb->esc_like($query) . '%', $limit);

        $orders = $wpdb->get_results($sql, ARRAY_A);

        return array_map(function($order) {
            return [
                'id' => $order['id'],
                'name' => $order['name'],
                'type' => $order['type'],
                'type_label' => $order['type_label'],
                'url' => $order['url'],
                'display_field' => $order['display_field'],
                'display_sub_field' => $order['display_sub_field'],
                'relevance_score' => $order['relevance_score'],
                'customer_name' => $order['customer_name'],
                'total_amount' => $order['total_amount'],
            ];
        }, $orders ?: []);
    }

    private function search_customers($query, $limit) {
        global $wpdb;

        $sql = $wpdb->prepare("
            SELECT
                c.id,
                c.full_name as name,
                c.email,
                c.phone,
                'customer' as type,
                '客戶' as type_label,
                CONCAT('/wp-admin/admin.php?page=buygo-customers&id=', c.id) as url,
                c.full_name as display_field,
                c.email as display_sub_field,
                8 as relevance_score
            FROM {$wpdb->prefix}buygo_customers c
            WHERE c.full_name LIKE %s
            OR c.email LIKE %s
            OR c.phone LIKE %s
            OR c.id LIKE %s
            LIMIT %d
        ", '%' . $wpdb->esc_like($query) . '%', '%' . $wpdb->esc_like($query) . '%', '%' . $wpdb->esc_like($query) . '%', '%' . $wpdb->esc_like($query) . '%', $limit);

        $customers = $wpdb->get_results($sql, ARRAY_A);

        return array_map(function($customer) {
            return [
                'id' => $customer['id'],
                'name' => $customer['name'],
                'type' => $customer['type'],
                'type_label' => $customer['type_label'],
                'url' => $customer['url'],
                'display_field' => $customer['display_field'],
                'display_sub_field' => $customer['display_sub_field'],
                'relevance_score' => $customer['relevance_score'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
            ];
        }, $customers ?: []);
    }

    private function search_shipments($query, $limit) {
        global $wpdb;

        $sql = $wpdb->prepare("
            SELECT
                s.id,
                CONCAT('出貨單 #', s.shipment_number) as name,
                s.customer_name,
                'shipment' as type,
                '出貨單' as type_label,
                CONCAT('/wp-admin/admin.php?page=buygo-shipment-details&id=', s.id) as url,
                CONCAT('出貨單 #', s.shipment_number) as display_field,
                s.customer_name as display_sub_field,
                7 as relevance_score
            FROM {$wpdb->prefix}buygo_shipments s
            WHERE s.shipment_number LIKE %s
            OR s.customer_name LIKE %s
            OR s.id LIKE %s
            LIMIT %d
        ", '%' . $wpdb->esc_like($query) . '%', '%' . $wpdb->esc_like($query) . '%', '%' . $wpdb->esc_like($query) . '%', $limit);

        $shipments = $wpdb->get_results($sql, ARRAY_A);

        return array_map(function($shipment) {
            return [
                'id' => $shipment['id'],
                'name' => $shipment['name'],
                'type' => $shipment['type'],
                'type_label' => $shipment['type_label'],
                'url' => $shipment['url'],
                'display_field' => $shipment['display_field'],
                'display_sub_field' => $shipment['display_sub_field'],
                'relevance_score' => $shipment['relevance_score'],
                'customer_name' => $shipment['customer_name'],
            ];
        }, $shipments ?: []);
    }
}