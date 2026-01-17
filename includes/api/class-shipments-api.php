<?php

namespace BuyGoPlus\Api;

use BuyGoPlus\Services\ShipmentService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shipments API - 出貨單 API
 * 
 * 處理出貨單的查詢、建立、更新等功能
 * 
 * @package BuyGoPlus\Api
 */
class Shipments_API
{
    private $shipmentService;

    public function __construct()
    {
        $this->shipmentService = new ShipmentService();
    }

    /**
     * 註冊 REST API 路由
     */
    public function register_routes()
    {
        register_rest_route('buygo-plus-one/v1', '/shipments', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_shipments'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_shipment'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('buygo-plus-one/v1', '/shipments/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_shipment'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_shipment'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('buygo-plus-one/v1', '/shipments/batch-mark-shipped', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'batch_mark_shipped'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    /**
     * 取得出貨單列表
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_shipments(WP_REST_Request $request)
    {
        global $wpdb;

        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 10;
        $status = $request->get_param('status'); // 'all', 'pending', 'shipped'
        $id = $request->get_param('id'); // 單一出貨單 ID

        $table_shipments = $wpdb->prefix . 'buygo_shipments';
        $table_shipment_items = $wpdb->prefix . 'buygo_shipment_items';
        $table_order_items = $wpdb->prefix . 'fct_order_items';
        $table_orders = $wpdb->prefix . 'fct_orders';
        $table_customers = $wpdb->prefix . 'fct_customers';

        // 建立 WHERE 條件
        $where_conditions = ['1=1'];

        if ($id) {
            $where_conditions[] = $wpdb->prepare('s.id = %d', $id);
        }

        if ($status && $status !== 'all') {
            $where_conditions[] = $wpdb->prepare('s.status = %s', $status);
        }

        $where_clause = implode(' AND ', $where_conditions);

        // 計算總數
        $total_query = "
            SELECT COUNT(DISTINCT s.id)
            FROM {$table_shipments} s
            WHERE {$where_clause}
        ";
        $total = $wpdb->get_var($total_query);

        // 計算偏移量
        $offset = ($page - 1) * $per_page;
        $limit = $per_page === -1 ? '' : "LIMIT {$per_page} OFFSET {$offset}";

        // 查詢出貨單列表（不 JOIN orders 表，避免笛卡兒積）
        $query = "
            SELECT 
                s.id,
                s.shipment_number,
                s.customer_id,
                s.seller_id,
                s.status,
                s.created_at,
                s.shipped_at,
                s.updated_at
            FROM {$table_shipments} s
            WHERE {$where_clause}
            ORDER BY s.created_at DESC
            {$limit}
        ";

        $shipments = $wpdb->get_results($query, ARRAY_A);

        // 為每個出貨單載入明細和客戶資料
        foreach ($shipments as &$shipment) {
            $shipment_id = $shipment['id'];
            $customer_id = $shipment['customer_id'];

            // 查詢客戶資料（直接從 fct_customers 表查詢）
            $customer_query = "
                SELECT first_name, last_name, email
                FROM {$table_customers}
                WHERE id = %d
                LIMIT 1
            ";
            $customer = $wpdb->get_row($wpdb->prepare($customer_query, $customer_id), ARRAY_A);
            
            if ($customer) {
                $first_name = $customer['first_name'] ?? '';
                $last_name = $customer['last_name'] ?? '';
                $shipment['customer_name'] = trim($first_name . ' ' . $last_name) ?: '未知客戶';
                $shipment['customer_email'] = $customer['email'] ?? null;
            } else {
                $shipment['customer_name'] = '未知客戶';
                $shipment['customer_email'] = null;
            }

            // 查詢出貨單明細
            $items_query = "
                SELECT 
                    si.id,
                    si.order_id,
                    si.order_item_id,
                    si.product_id,
                    si.quantity,
                    oi.title,
                    oi.post_title,
                    oi.line_meta,
                    o.invoice_no as order_invoice_no
                FROM {$table_shipment_items} si
                LEFT JOIN {$table_order_items} oi ON si.order_item_id = oi.id
                LEFT JOIN {$table_orders} o ON si.order_id = o.id
                WHERE si.shipment_id = %d
            ";

            $items = $wpdb->get_results($wpdb->prepare($items_query, $shipment_id), ARRAY_A);
            
            // 處理商品名稱和圖片
            foreach ($items as &$item) {
                // 取得商品名稱（優先使用 title，其次使用 post_title）
                $item['product_name'] = $item['title'] ?? $item['post_title'] ?? '未知商品';
                
                // 從 line_meta 解析商品圖片（如果有的話）
                $line_meta = $item['line_meta'] ?? '{}';
                $meta_data = is_string($line_meta) ? json_decode($line_meta, true) : ($line_meta ?: []);
                $item['product_image'] = $meta_data['product_image'] ?? $meta_data['image'] ?? null;
                
                // 移除不需要的欄位
                unset($item['title'], $item['post_title'], $item['line_meta']);
            }
            unset($item);

            // 計算總數量
            $total_quantity = array_sum(array_column($items, 'quantity'));

            $shipment['items'] = $items;
            $shipment['total_quantity'] = $total_quantity;
            $shipment['items_count'] = count($items);
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => $shipments,
            'total' => (int) $total,
            'page' => (int) $page,
            'per_page' => $per_page === -1 ? $total : (int) $per_page,
        ]);
    }

    /**
     * 取得單一出貨單
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_shipment(WP_REST_Request $request)
    {
        $shipment_id = $request->get_param('id');
        $shipment = $this->shipmentService->get_shipment($shipment_id);

        if (!$shipment) {
            return new WP_Error('shipment_not_found', '出貨單不存在', ['status' => 404]);
        }

        $items = $this->shipmentService->get_shipment_items($shipment_id);

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'shipment' => $shipment,
                'items' => $items,
            ],
        ]);
    }

    /**
     * 建立出貨單
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_shipment(WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        $customer_id = $params['customer_id'] ?? null;
        $seller_id = get_current_user_id() ?: 1; // 預設為當前使用者
        $items = $params['items'] ?? [];

        if (!$customer_id) {
            return new WP_Error('missing_customer_id', '缺少客戶 ID', ['status' => 400]);
        }

        if (empty($items)) {
            return new WP_Error('missing_items', '出貨單必須包含至少一個商品', ['status' => 400]);
        }

        $shipment_id = $this->shipmentService->create_shipment($customer_id, $seller_id, $items);

        if (is_wp_error($shipment_id)) {
            return $shipment_id;
        }

        $shipment = $this->shipmentService->get_shipment($shipment_id);

        return new WP_REST_Response([
            'success' => true,
            'data' => $shipment,
            'shipment_id' => $shipment_id,
        ], 201);
    }

    /**
     * 更新出貨單
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_shipment(WP_REST_Request $request)
    {
        $shipment_id = $request->get_param('id');
        $params = $request->get_json_params();

        $shipment = $this->shipmentService->get_shipment($shipment_id);

        if (!$shipment) {
            return new WP_Error('shipment_not_found', '出貨單不存在', ['status' => 404]);
        }

        // 目前只支援更新狀態
        if (isset($params['status'])) {
            global $wpdb;

            $result = $wpdb->update(
                $wpdb->prefix . 'buygo_shipments',
                [
                    'status' => $params['status'],
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $shipment_id],
                ['%s', '%s'],
                ['%d']
            );

            if ($result === false) {
                return new WP_Error('update_failed', '更新失敗', ['status' => 500]);
            }

            $shipment = $this->shipmentService->get_shipment($shipment_id);
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => $shipment,
        ]);
    }

    /**
     * 批次標記為已出貨
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function batch_mark_shipped(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $shipment_ids = $params['shipment_ids'] ?? [];

        if (empty($shipment_ids)) {
            return new WP_Error('missing_shipment_ids', '請選擇要標記的出貨單', ['status' => 400]);
        }

        $result = $this->shipmentService->mark_shipped($shipment_ids);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => "成功標記 {$result} 個出貨單為已出貨",
            'count' => $result,
        ]);
    }
}
