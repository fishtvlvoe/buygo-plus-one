<?php

namespace BuyGoPlus\Services;

use FluentCart\App\Models\Order;
use FluentCart\App\Models\Customer;
use FluentCart\App\Models\OrderItem;

/**
 * Order Service - 訂單管理服務
 * 
 * 整合 FluentCart Order Model 並提供訂單管理功能
 * 
 * @package BuyGoPlus\Services
 * @version 1.0.0
 */
class OrderService
{
    private $debugService;

    public function __construct()
    {
        $this->debugService = new DebugService();
    }

    /**
     * 取得訂單列表（含分頁）
     * 
     * @param array $params 查詢參數
     * @return array
     */
    public function getOrders(array $params = []): array
    {
        $this->debugService->log('OrderService', '開始取得訂單列表', [
            'params' => $params
        ]);

        try {
            $page = $params['page'] ?? 1;
            $per_page = $params['per_page'] ?? 10;
            $search = $params['search'] ?? '';
            $status = $params['status'] ?? 'all';
            $id = $params['id'] ?? null;

            $query = Order::with(['customer', 'order_items']);

            // 如果有 ID 參數，只取得單一訂單
            if ($id) {
                $order = $query->find($id);
                if (!$order) {
                    return [
                        'orders' => [],
                        'total' => 0,
                        'page' => 1,
                        'per_page' => 1,
                        'pages' => 0
                    ];
                }

                return [
                    'orders' => [$this->formatOrder($order)],
                    'total' => 1,
                    'page' => 1,
                    'per_page' => 1,
                    'pages' => 1
                ];
            }

            // 搜尋：訂單編號或客戶名稱
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('invoice_no', 'LIKE', "%{$search}%")
                      ->orWhereHas('customer', function($customerQuery) use ($search) {
                          $customerQuery->where('first_name', 'LIKE', "%{$search}%")
                                       ->orWhere('last_name', 'LIKE', "%{$search}%")
                                       ->orWhere('email', 'LIKE', "%{$search}%");
                      });
                });
            }

            // 狀態篩選
            if ($status !== 'all') {
                $query->where('status', $status);
            }

            // 總數
            $total = $query->count();

            // 分頁
            if ($per_page !== -1) {
                $query->skip(($page - 1) * $per_page)->take($per_page);
            }

            // 排序
            $query->orderBy('created_at', 'DESC');

            $orders = $query->get();

            // 格式化訂單資料
            $formatted_orders = array_map(function($order) {
                return $this->formatOrder($order);
            }, $orders->toArray());

            $this->debugService->log('OrderService', '成功取得訂單列表', [
                'count' => count($formatted_orders),
                'total' => $total
            ]);

            return [
                'orders' => $formatted_orders,
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'pages' => $per_page === -1 ? 1 : ceil($total / $per_page)
            ];

        } catch (\Exception $e) {
            $this->debugService->log('OrderService', '取得訂單列表失敗', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 'error');

            throw new \Exception('無法取得訂單列表：' . $e->getMessage());
        }
    }

    /**
     * 取得單一訂單
     * 
     * @param int $orderId 訂單 ID
     * @return array|null
     */
    public function getOrderById(int $orderId): ?array
    {
        $this->debugService->log('OrderService', '取得單一訂單', [
            'order_id' => $orderId
        ]);

        try {
            $order = Order::with(['customer', 'order_items'])
                ->find($orderId);

            if (!$order) {
                return null;
            }

            return $this->formatOrder($order);

        } catch (\Exception $e) {
            $this->debugService->log('OrderService', '取得單一訂單失敗', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ], 'error');

            return null;
        }
    }

    /**
     * 更新訂單狀態
     * 
     * @param int $orderId 訂單 ID
     * @param string $status 新狀態
     * @return bool
     */
    public function updateOrderStatus(int $orderId, string $status): bool
    {
        $this->debugService->log('OrderService', '更新訂單狀態', [
            'order_id' => $orderId,
            'status' => $status
        ]);

        try {
            $order = Order::find($orderId);

            if (!$order) {
                return false;
            }

            $order->status = $status;
            $result = $order->save();

            $this->debugService->log('OrderService', '訂單狀態更新成功', [
                'order_id' => $orderId,
                'status' => $status
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->debugService->log('OrderService', '更新訂單狀態失敗', [
                'order_id' => $orderId,
                'status' => $status,
                'error' => $e->getMessage()
            ], 'error');

            return false;
        }
    }

    /**
     * 執行訂單出貨（使用已分配的配額）
     * 
     * @param int $order_id 訂單 ID
     * @param array $items 要出貨的商品項目
     *   格式：[
     *     ['order_item_id' => 123, 'quantity' => 10, 'product_id' => 456],
     *     ['order_item_id' => 124, 'quantity' => 5, 'product_id' => 789]
     *   ]
     * @return int|WP_Error 出貨單 ID 或錯誤
     */
    public function shipOrder($order_id, $items = [])
    {
        global $wpdb;
        
        $this->debugService->log('OrderService', '開始執行訂單出貨', [
            'order_id' => $order_id,
            'items' => $items
        ]);
        
        // 1. 驗證訂單存在
        $order = Order::find($order_id);
        if (!$order) {
            $this->debugService->log('OrderService', '訂單不存在', [
                'order_id' => $order_id
            ], 'error');
            return new \WP_Error('ORDER_NOT_FOUND', '訂單不存在');
        }
        
        if (empty($items)) {
            return new \WP_Error('NO_ITEMS', '請選擇要出貨的商品');
        }
        
        // 2. 驗證每個商品的 allocated_quantity 足夠
        $table_items = $wpdb->prefix . 'fct_order_items';
        foreach ($items as $item) {
            $order_item_id = (int)($item['order_item_id'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 0);
            
            if ($order_item_id <= 0 || $quantity <= 0) {
                return new \WP_Error('INVALID_ITEM', '訂單項目 ID 或數量無效');
            }
            
            // 取得訂單項目
            $order_item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_items} WHERE id = %d AND order_id = %d",
                $order_item_id,
                $order_id
            ), ARRAY_A);
            
            if (!$order_item) {
                return new \WP_Error('ORDER_ITEM_NOT_FOUND', "訂單項目 #{$order_item_id} 不存在");
            }
            
            // 檢查 meta_data 中的 _allocated_qty
            $meta_data = json_decode($order_item['meta_data'] ?? '{}', true) ?: [];
            $allocated_qty = (int)($meta_data['_allocated_qty'] ?? 0);
            
            if ($quantity > $allocated_qty) {
                return new \WP_Error('INSUFFICIENT_ALLOCATION', 
                    "商品 #{$order_item['post_id']} 的分配數量不足。需要: {$quantity}, 已分配: {$allocated_qty}");
            }
        }
        
        // 3. 取得賣家 ID
        $seller_id = $this->getSellerId($items, $order_id);
        if ($seller_id === 0) {
            return new \WP_Error('SELLER_NOT_FOUND', '無法取得賣家資訊');
        }
        
        // 4. 準備出貨單明細
        $shipment_items = [];
        foreach ($items as $item) {
            $order_item_id = (int)($item['order_item_id'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 0);
            $product_id = (int)($item['product_id'] ?? 0);
            
            // 如果沒有提供 product_id，從訂單項目中取得
            if ($product_id === 0) {
                $order_item = $wpdb->get_row($wpdb->prepare(
                    "SELECT post_id, product_id FROM {$table_items} WHERE id = %d",
                    $order_item_id
                ), ARRAY_A);
                
                if ($order_item) {
                    $product_id = (int)($order_item['post_id'] ?? $order_item['product_id'] ?? 0);
                }
            }
            
            $shipment_items[] = [
                'order_id' => $order_id,
                'order_item_id' => $order_item_id,
                'product_id' => $product_id,
                'quantity' => $quantity
            ];
        }
        
        // 5. 建立出貨單（呼叫 ShipmentService）
        $shipmentService = new ShipmentService();
        $shipment_id = $shipmentService->create_shipment(
            (int)$order->customer_id,
            $seller_id,
            $shipment_items
        );
        
        if (is_wp_error($shipment_id)) {
            return $shipment_id;
        }
        
        // 6. 更新 allocated_quantity（扣除已出貨數量）
        foreach ($items as $item) {
            $order_item_id = (int)($item['order_item_id'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 0);
            
            // 取得訂單項目
            $order_item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_items} WHERE id = %d",
                $order_item_id
            ), ARRAY_A);
            
            if ($order_item) {
                // 讀取現有的 meta_data
                $meta_data = json_decode($order_item['meta_data'] ?? '{}', true) ?: [];
                $current_allocated = (int)($meta_data['_allocated_qty'] ?? 0);
                
                // 扣除已出貨數量
                $new_allocated = max(0, $current_allocated - $quantity);
                $meta_data['_allocated_qty'] = $new_allocated;
                
                // 更新已出貨數量
                $current_shipped = (int)($meta_data['_shipped_qty'] ?? 0);
                $meta_data['_shipped_qty'] = $current_shipped + $quantity;
                
                // 更新資料庫
                $wpdb->update(
                    $table_items,
                    ['meta_data' => json_encode($meta_data)],
                    ['id' => $order_item_id],
                    ['%s'],
                    ['%d']
                );
            }
        }
        
        $this->debugService->log('OrderService', '訂單出貨成功', [
            'order_id' => $order_id,
            'shipment_id' => $shipment_id,
            'items_count' => count($items)
        ]);
        
        return $shipment_id;
    }
    
    /**
     * 取得賣家 ID
     * 
     * @param array $items 出貨項目
     * @param int $order_id 訂單 ID
     * @return int 賣家 ID，如果找不到則回傳 0
     */
    private function getSellerId($items, $order_id)
    {
        // 方法 1：優先使用當前使用者（如果是賣家、管理員或 buygo_admin）
        $current_user_id = get_current_user_id();
        if ($current_user_id > 0) {
            $user = get_userdata($current_user_id);
            if ($user && (
                in_array('buygo_seller', $user->roles) || 
                in_array('administrator', $user->roles) ||
                in_array('buygo_admin', $user->roles)
            )) {
                return $current_user_id;
            }
        }
        
        // 方法 2：從出貨商品中取得賣家 ID
        foreach ($items as $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            if ($product_id > 0) {
                $product = get_post($product_id);
                if ($product) {
                    // 如果是 variation，取得 parent 的 author
                    if ($product->post_type === 'product_variation' && $product->post_parent > 0) {
                        $parent_product = get_post($product->post_parent);
                        if ($parent_product && !empty($parent_product->post_author)) {
                            return (int)$parent_product->post_author;
                        }
                    } elseif (!empty($product->post_author)) {
                        return (int)$product->post_author;
                    }
                }
            }
        }
        
        // 方法 3：從訂單項目中取得
        global $wpdb;
        $table_items = $wpdb->prefix . 'fct_order_items';
        $order_items = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, product_id FROM {$table_items} WHERE order_id = %d LIMIT 1",
            $order_id
        ), ARRAY_A);
        
        foreach ($order_items as $order_item) {
            $product_id = (int)($order_item['post_id'] ?? $order_item['product_id'] ?? 0);
            if ($product_id > 0) {
                $product = get_post($product_id);
                if ($product) {
                    if ($product->post_type === 'product_variation' && $product->post_parent > 0) {
                        $parent_product = get_post($product->post_parent);
                        if ($parent_product && !empty($parent_product->post_author)) {
                            return (int)$parent_product->post_author;
                        }
                    } elseif (!empty($product->post_author)) {
                        return (int)$product->post_author;
                    }
                }
            }
        }
        
        return 0;
    }

    /**
     * 格式化訂單資料
     * 
     * @param mixed $order 訂單物件或陣列
     * @return array
     */
    private function formatOrder($order): array
    {
        // 如果是物件，轉換為陣列
        if (is_object($order)) {
            $order = $order->toArray();
        }

        // 計算商品總數量
        $total_items = 0;
        $items = [];

        if (isset($order['order_items']) && is_array($order['order_items'])) {
            foreach ($order['order_items'] as $item) {
                $quantity = $item['quantity'] ?? 0;
                $total_items += $quantity;

                // 取得商品名稱（優先使用 title，其次使用 post_title）
                $product_name = $item['title'] ?? $item['post_title'] ?? '未知商品';

                $items[] = [
                    'id' => $item['id'] ?? 0,
                    'product_name' => $product_name,
                    'quantity' => $quantity,
                    'price' => isset($item['unit_price']) ? ($item['unit_price'] / 100) : 0, // 轉換為元
                    'total' => isset($item['line_total']) ? ($item['line_total'] / 100) : 0
                ];
            }
        }

        // 取得客戶名稱
        $customer_name = '';
        $customer_email = '';
        if (isset($order['customer'])) {
            $customer = $order['customer'];
            if (is_object($customer)) {
                $customer = $customer->toArray();
            }
            $first_name = $customer['first_name'] ?? '';
            $last_name = $customer['last_name'] ?? '';
            $customer_name = trim($first_name . ' ' . $last_name);
            $customer_email = $customer['email'] ?? '';
        }

        return [
            'id' => $order['id'] ?? 0,
            'invoice_no' => $order['invoice_no'] ?? '',
            'status' => $order['status'] ?? 'pending',
            'total_amount' => isset($order['total_amount']) ? ($order['total_amount'] / 100) : 0, // 轉換為元
            'currency' => $order['currency'] ?? 'TWD',
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'total_items' => $total_items,
            'items' => $items,
            'created_at' => $order['created_at'] ?? '',
            'updated_at' => $order['updated_at'] ?? ''
        ];
    }
}
