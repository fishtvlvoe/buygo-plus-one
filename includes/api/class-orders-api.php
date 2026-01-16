<?php
namespace BuyGoPlus\Api;

use BuyGoPlus\Services\OrderService;

if (!defined('ABSPATH')) {
    exit;
}

class Orders_API {
    
    private $namespace = 'buygo-plus-one/v1';
    private $orderService;
    
    public function __construct() {
        $this->orderService = new OrderService();
    }
    
    /**
     * 註冊 REST API 路由
     */
    public function register_routes() {
        // GET /orders - 取得訂單列表
        register_rest_route($this->namespace, '/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'get_orders'],
            'permission_callback' => [__CLASS__, 'check_permission'],
            'args' => [
                'page' => [
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'per_page' => [
                    'default' => 10,
                    'sanitize_callback' => 'absint',
                ],
                'search' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'status' => [
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'id' => [
                    'default' => null,
                    'sanitize_callback' => 'absint',
                ],
            ]
        ]);
        
        // GET /orders/{id} - 取得單一訂單
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_order'],
            'permission_callback' => [__CLASS__, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
        
        // PUT /orders/{id}/status - 更新訂單狀態
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)/status', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_order_status'],
            'permission_callback' => [__CLASS__, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
    }
    
    /**
     * 取得訂單列表
     */
    public function get_orders($request) {
        try {
            $params = $request->get_params();
            
            // 如果有 ID 參數，只取得單一訂單
            if (!empty($params['id'])) {
                $order = $this->orderService->getOrderById($params['id']);
                
                if (!$order) {
                    return new \WP_REST_Response([
                        'success' => false,
                        'message' => '訂單不存在'
                    ], 404);
                }
                
                return new \WP_REST_Response([
                    'success' => true,
                    'data' => [$order],
                    'total' => 1,
                    'page' => 1,
                    'per_page' => 1,
                    'pages' => 1
                ], 200);
            }
            
            // 取得訂單列表
            $result = $this->orderService->getOrders($params);
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $result['orders'],
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'pages' => $result['pages']
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 取得單一訂單
     */
    public function get_order($request) {
        try {
            $order_id = $request['id'];
            $order = $this->orderService->getOrderById($order_id);
            
            if (!$order) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '訂單不存在'
                ], 404);
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $order
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 更新訂單狀態
     */
    public function update_order_status($request) {
        try {
            $order_id = $request['id'];
            $body = json_decode($request->get_body(), true);
            $status = $body['status'] ?? '';
            
            if (empty($status)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '狀態不能為空'
                ], 400);
            }
            
            $result = $this->orderService->updateOrderStatus($order_id, $status);
            
            if (!$result) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '更新失敗'
                ], 500);
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => '訂單狀態已更新'
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 權限檢查
     */
    public static function check_permission() {
        return \BuyGoPlus\Api\API::check_permission_for_api();
    }
}
