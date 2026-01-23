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
     */
    public function get_orders($request) {
        // 設置 no-cache 標頭，確保瀏覽器不會快取 API 回應
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

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
     * 更新運送狀態
     */
    public function update_shipping_status($request) {
        try {
            $order_id = (string)$request['id'];
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
        $params = $request->get_json_params();
        
        $debugService = new \BuyGoPlus\Services\DebugService();
        $debugService->log('Orders_API', '開始拆分訂單', [
            'order_id' => $order_id,
            'params' => $params
        ]);
        
        try {
            global $wpdb;
            $table_orders = $wpdb->prefix . 'fct_orders';
            $table_items = $wpdb->prefix . 'fct_order_items';
            
            // 檢查訂單是否存在
            $order = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_orders} WHERE id = %d",
                $order_id
            ));
            
            if (!$order) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '找不到此訂單',
                    'code' => 'ORDER_NOT_FOUND'
                ], 404);
            }
            
            // 檢查是否有要拆分的商品
            if (empty($params['split_items']) || !is_array($params['split_items'])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '請選擇要拆分的商品',
                    'code' => 'NO_ITEMS_SELECTED'
                ], 400);
            }
            
            $split_items = $params['split_items'];
            
            // 取得訂單項目
            $order_items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_items} WHERE order_id = %d",
                $order_id
            ), ARRAY_A);
            
            if (empty($order_items)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '訂單沒有商品',
                    'code' => 'NO_ORDER_ITEMS'
                ], 400);
            }
            
            // 驗證拆分數量
            $order_items_map = [];
            foreach ($order_items as $item) {
                $order_items_map[$item['id']] = $item;
            }
            
            $shipment_items = [];
            foreach ($split_items as $split_item) {
                $order_item_id = (int)($split_item['order_item_id'] ?? 0);
                $quantity = (int)($split_item['quantity'] ?? 0);
                
                if (!isset($order_items_map[$order_item_id])) {
                    return new \WP_REST_Response([
                        'success' => false,
                        'message' => "訂單項目 #{$order_item_id} 不存在",
                        'code' => 'ORDER_ITEM_NOT_FOUND'
                    ], 400);
                }
                
                $order_item = $order_items_map[$order_item_id];
                
                // 檢查已拆分數量（從拆分訂單的商品項目中計算）
                $split_quantity = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(oi.quantity), 0) 
                     FROM {$wpdb->prefix}fct_order_items oi
                     INNER JOIN {$wpdb->prefix}fct_orders o ON oi.order_id = o.id
                     WHERE o.parent_id = %d 
                     AND oi.post_id = %d 
                     AND oi.object_id = %d",
                    $order_id,
                    (int)($order_item['post_id'] ?? 0),
                    (int)($order_item['object_id'] ?? 0)
                ));
                
                $available_quantity = $order_item['quantity'] - $split_quantity;
                
                if ($quantity > $available_quantity) {
                    return new \WP_REST_Response([
                        'success' => false,
                        'message' => "拆分數量 ({$quantity}) 不能超過可用數量 ({$available_quantity})",
                        'code' => 'QUANTITY_EXCEEDED'
                    ], 400);
                }
                
                if ($quantity <= 0) {
                    return new \WP_REST_Response([
                        'success' => false,
                        'message' => '拆分數量必須大於 0',
                        'code' => 'INVALID_QUANTITY'
                    ], 400);
                }
                
                $shipment_items[] = [
                    'order_id' => $order_id,
                    'order_item_id' => $order_item_id,
                    'product_id' => (int)($order_item['post_id'] ?? $order_item['product_id'] ?? 0),
                    'quantity' => $quantity
                ];
            }
            
            // 計算新訂單的編號後綴
            $split_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_orders} WHERE parent_id = %d",
                $order_id
            ));
            $split_suffix = (int)$split_count + 1;
            
            // 計算新訂單的總金額
            $new_order_total = 0;
            foreach ($shipment_items as $item) {
                $order_item = $order_items_map[$item['order_item_id']];
                $unit_price = (int)($order_item['unit_price'] ?? $order_item['item_price'] ?? 0);
                $new_order_total += $unit_price * $item['quantity'];
            }
            
            // 建立新訂單（拆分訂單/子訂單）
            $new_order_data = [
                'parent_id' => $order_id,  // 記錄父訂單 ID
                'customer_id' => (int)$order->customer_id,
                'status' => 'pending',  // 預設為「待處理」，可以進行分配
                'payment_status' => $order->payment_status ?? 'pending',
                'shipping_status' => 'unshipped',
                'payment_method' => $order->payment_method ?? '',
                'payment_method_title' => $order->payment_method_title ?? '',
                'currency' => $order->currency ?? 'TWD',
                'subtotal' => $new_order_total,
                'total_amount' => $new_order_total,
                'tax_total' => 0,
                'discount_tax' => 0,
                'manual_discount_total' => 0,
                'coupon_discount_total' => 0,
                'shipping_tax' => 0,
                'shipping_total' => 0,
                'total_paid' => 0,
                'total_refund' => 0,
                'invoice_no' => (!empty($order->invoice_no) ? $order->invoice_no : $order_id) . '-' . $split_suffix,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            
            $new_order_inserted = $wpdb->insert(
                $table_orders,
                $new_order_data,
                [
                    '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d',
                    '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s'
                ]
            );
            
            if ($new_order_inserted === false) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '建立拆分訂單失敗：' . $wpdb->last_error,
                    'code' => 'CREATE_ORDER_FAILED'
                ], 500);
            }
            
            $new_order_id = $wpdb->insert_id;
            
            // 建立新訂單的商品項目
            $items_inserted = 0;
            foreach ($shipment_items as $item) {
                $order_item = $order_items_map[$item['order_item_id']];
                $unit_price = (int)($order_item['unit_price'] ?? $order_item['item_price'] ?? 0);
                $line_total = $unit_price * $item['quantity'];
                $subtotal = $line_total;
                
                $new_item_data = [
                    'order_id' => $new_order_id,
                    'post_id' => (int)($order_item['post_id'] ?? 0),
                    'object_id' => (int)($order_item['object_id'] ?? 0),
                    'quantity' => $item['quantity'],
                    'unit_price' => $unit_price,
                    'subtotal' => $subtotal,
                    'line_total' => $line_total,
                    'post_title' => $order_item['post_title'] ?? $order_item['title'] ?? '',
                    'title' => $order_item['title'] ?? '',
                    'fulfillment_type' => $order_item['fulfillment_type'] ?? 'physical',
                    'payment_type' => $order_item['payment_type'] ?? 'onetime',
                    'cart_index' => $order_item['cart_index'] ?? 0,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ];
                
                $insert_result = $wpdb->insert(
                    $table_items,
                    $new_item_data,
                    [
                        '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s'
                    ]
                );
                
                if ($insert_result !== false) {
                    $items_inserted++;
                }
            }
            
            if ($items_inserted === 0 && !empty($shipment_items)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '建立拆分訂單的商品項目失敗',
                    'code' => 'CREATE_ORDER_ITEMS_FAILED'
                ], 500);
            }

            // 同步更新父訂單項目的 _allocated_qty（重新計算而非遞增，確保與實際子訂單同步）
            foreach ($shipment_items as $item) {
                $parent_order_item_id = $item['order_item_id'];
                $parent_order_item = $order_items_map[$parent_order_item_id] ?? null;

                if (!$parent_order_item) {
                    continue;
                }

                // 計算該訂單項目現在的實際已分配數量（查詢所有子訂單項目）
                $current_allocated = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(oi.quantity), 0)
                     FROM {$wpdb->prefix}fct_order_items oi
                     INNER JOIN {$wpdb->prefix}fct_orders o ON oi.order_id = o.id
                     WHERE o.parent_id = %d
                     AND oi.object_id = %d
                     AND o.type = 'split'",
                    $order_id,
                    (int)($parent_order_item['object_id'] ?? 0)
                ));

                // 更新父訂單項目的 line_meta
                $parent_meta = json_decode($parent_order_item['line_meta'] ?? '{}', true) ?: [];
                $parent_meta['_allocated_qty'] = (int)$current_allocated;

                $wpdb->update(
                    $table_items,
                    ['line_meta' => json_encode($parent_meta)],
                    ['id' => $parent_order_item_id],
                    ['%s'],
                    ['%d']
                );
            }

            $debugService->log('Orders_API', '訂單拆分成功', [
                'order_id' => $order_id,
                'new_order_id' => $new_order_id,
                'split_suffix' => $split_suffix
            ]);
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => '訂單拆分成功',
                'data' => [
                    'original_order_id' => $order_id,
                    'new_order_id' => $new_order_id,
                    'order_number' => (!empty($order->invoice_no) ? $order->invoice_no : $order_id) . '-' . $split_suffix
                ]
            ], 201);
            
        } catch (\Exception $e) {
            $debugService->log('Orders_API', '拆分訂單失敗', [
                'error' => $e->getMessage(),
                'order_id' => $order_id
            ], 'error');
            
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'SPLIT_ORDER_FAILED'
            ], 500);
        }
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
        return \BuyGoPlus\Api\API::check_permission_for_api();
    }
}
