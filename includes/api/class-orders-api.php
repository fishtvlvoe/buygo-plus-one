<?php
namespace BuyGoPlus\Api;

use BuyGoPlus\Services\OrderService;
use BuyGoPlus\Services\ShipmentService;

if (!defined('ABSPATH')) {
    exit;
}

class Orders_API {

    private $namespace = 'buygo-plus-one/v1';
    private $orderService;
    private $shipmentService;

    public function __construct() {
        $this->orderService = new OrderService();
        $this->shipmentService = new ShipmentService();
    }
    
    /**
     * 註冊 REST API 路由
     */
    public function register_routes() {
        // GET /orders - 取得訂單列表
        register_rest_route($this->namespace, '/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'get_orders'],
            'permission_callback' => [API::class, 'check_permission'],
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
            'permission_callback' => [API::class, 'check_permission'],
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
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
        
        // PUT /orders/{id}/shipping-status - 更新運送狀態
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)/shipping-status', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_shipping_status'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
        
        // POST /orders/{id}/ship - 執行出貨
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)/ship', [
            'methods' => 'POST',
            'callback' => [$this, 'ship_order'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
        
        // POST /orders/{id}/split - 拆分訂單
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)/split', [
            'methods' => 'POST',
            'callback' => [$this, 'split_order'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);

        // POST /orders/{id}/prepare - 轉備貨（更新狀態為 preparing）
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)/prepare', [
            'methods' => 'POST',
            'callback' => [$this, 'prepare_order'],
            'permission_callback' => [API::class, 'check_permission'],
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
     *
     * 使用 per-user Transient 快取（TTL 30 秒），降低頻繁 SWR 輪詢對資料庫的壓力。
     * 快取 key 包含 user_id 與查詢參數 hash，確保不同使用者、不同篩選條件各自獨立。
     * 單一訂單查詢（帶 id 參數）跳過快取，直接查詢以確保即時性。
     */
    public function get_orders($request) {
        try {
            $params = $request->get_params();

            // 如果有 ID 參數，只取得單一訂單（不走快取，確保即時）
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

            // 建立 per-user 快取 key（包含查詢參數 hash，不同分頁/篩選各自快取）
            $user_id   = get_current_user_id();
            $cache_key = 'buygo_orders_' . $user_id . '_' . md5(serialize($params));

            // 嘗試從 transient 讀取快取
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return new \WP_REST_Response($cached, 200);
            }

            // 快取未命中，查詢資料庫
            $result = $this->orderService->getOrders($params);

            // 取得全域統計（不受分頁影響）
            $stats = $this->orderService->getOrderStats();

            $response_data = [
                'success'  => true,
                'data'     => $result['orders'],
                'total'    => $result['total'],
                'page'     => $result['page'],
                'per_page' => $result['per_page'],
                'pages'    => $result['pages'],
                'stats'    => $stats
            ];

            // 快取 30 秒（與前端 BuyGoCache.TTL 一致）
            set_transient($cache_key, $response_data, 30);

            return new \WP_REST_Response($response_data, 200);

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

            // 驗證訂單所有權
            $check = API::verify_order_ownership((int) $order_id);
            if (is_wp_error($check)) {
                return $check;
            }

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

            // 驗證訂單所有權
            $check = API::verify_order_ownership((int) $order_id);
            if (is_wp_error($check)) {
                return $check;
            }

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
     * 更新運送狀態
     */
    public function update_shipping_status($request) {
        try {
            $order_id = (string)$request['id'];

            // 驗證訂單所有權
            $check = API::verify_order_ownership((int) $order_id);
            if (is_wp_error($check)) {
                return $check;
            }

            $body = json_decode($request->get_body(), true);
            $status = $body['status'] ?? '';
            $reason = $body['reason'] ?? '';
            
            if (empty($status)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '運送狀態不能為空'
                ], 400);
            }
            
            $result = $this->orderService->updateShippingStatus($order_id, $status, $reason);
            
            if (!$result) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '運送狀態更新失敗'
                ], 500);
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => '運送狀態已更新'
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 執行訂單出貨
     */
    public function ship_order($request) {
        try {
            $order_id = (int)$request->get_param('id');

            // 驗證訂單所有權
            $check = API::verify_order_ownership($order_id);
            if (is_wp_error($check)) {
                return $check;
            }

            $params = $request->get_json_params();
            
            if (empty($params['items']) || !is_array($params['items'])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '請選擇要出貨的商品'
                ], 400);
            }
            
            // 驗證 items 格式
            $items = [];
            foreach ($params['items'] as $item) {
                if (empty($item['order_item_id']) || empty($item['quantity'])) {
                    return new \WP_REST_Response([
                        'success' => false,
                        'message' => '訂單項目 ID 或數量無效'
                    ], 400);
                }
                
                $items[] = [
                    'order_item_id' => (int)$item['order_item_id'],
                    'quantity' => (int)$item['quantity'],
                    'product_id' => isset($item['product_id']) ? (int)$item['product_id'] : 0
                ];
            }
            
            $shipment_id = $this->orderService->shipOrder($order_id, $items);
            
            if (is_wp_error($shipment_id)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $shipment_id->get_error_message()
                ], 400);
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'shipment_id' => $shipment_id,
                'message' => '出貨成功'
            ], 201);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 拆分訂單
     * POST /wp-json/buygo-plus-one/v1/orders/{id}/split
     */
    public function split_order($request) {
        $order_id = (int)$request->get_param('id');

        // 驗證訂單所有權（API 層職責）
        $check = API::verify_order_ownership($order_id);
        if (is_wp_error($check)) {
            return $check;
        }

        $params = $request->get_json_params();

        // 委派商業邏輯給 Service
        $service = new \BuyGoPlus\Services\OrderService();
        $result  = $service->splitOrder($order_id, $params ?? []);

        if (is_wp_error($result)) {
            $status = (int)($result->get_error_data()['status'] ?? 400);
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ], $status);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => '訂單拆分成功',
            'data'    => $result,
        ], 201);
    }

    /**
     * 轉備貨（更新訂單狀態為 preparing 並建立出貨單）
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function prepare_order($request) {
        global $wpdb;

        try {
            $order_id = (string)$request['id'];

            // 驗證訂單所有權
            $check = API::verify_order_ownership((int) $order_id);
            if (is_wp_error($check)) {
                return $check;
            }

            // 取得訂單資訊
            $order = \FluentCart\App\Models\Order::with(['customer', 'order_items'])->find($order_id);

            if (!$order) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '訂單不存在'
                ], 404);
            }

            // 更新 shipping_status 為 'preparing'
            $result = $this->orderService->updateShippingStatus($order_id, 'preparing', '轉備貨');

            if (!$result) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '轉備貨失敗'
                ], 500);
            }

            // 建立出貨單
            $shipment_items = [];
            foreach ($order->order_items as $item) {
                // FluentCart 使用 post_id 作為商品 ID，不是 product_id
                $product_id = $item->post_id ?? $item->product_id ?? null;
                $shipment_items[] = [
                    'order_id' => $order_id,
                    'order_item_id' => $item->id,
                    'product_id' => $product_id,
                    'quantity' => $item->quantity
                ];
            }

            // 取得 seller_id（從設定或使用預設值）
            $seller_id = get_current_user_id() ?: 1;

            $shipment_id = $this->shipmentService->create_shipment(
                (int)$order->customer_id,
                $seller_id,
                $shipment_items
            );

            if (is_wp_error($shipment_id)) {
                // 即使出貨單建立失敗，訂單狀態已更新成功
                return new \WP_REST_Response([
                    'success' => true,
                    'message' => '已轉為備貨狀態（出貨單建立失敗：' . $shipment_id->get_error_message() . '）',
                    'shipment_id' => null
                ], 200);
            }

            return new \WP_REST_Response([
                'success' => true,
                'message' => '已轉為備貨狀態',
                'shipment_id' => $shipment_id
            ], 200);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'PREPARE_ORDER_FAILED'
            ], 500);
        }
    }

    /**
     * 權限檢查
     */
    public static function check_permission() {
        return \BuyGoPlus\Api\API::check_permission_with_scope('orders');
    }
}
