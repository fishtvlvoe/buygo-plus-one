<?php

namespace BuyGoPlus\Services;

use FluentCart\App\Models\Order;
use FluentCart\App\Models\Customer;
use FluentCart\App\Models\OrderItem;

/**
 * Order Service - 訂單管理服務（Facade）
 *
 * 整合 FluentCart Order Model 並提供訂單管理功能。
 * 實際邏輯委派到子服務：
 * - OrderFormatter：訂單資料格式化
 * - OrderShippingManager：運送狀態管理
 *
 * @package BuyGoPlus\Services
 * @version 2.1.0
 */
class OrderService
{
    private $debugService;
    private $shippingStatusService;
    private $formatter;
    private $shippingManager;

    public function __construct()
    {
        $this->debugService = DebugService::get_instance();
        $this->shippingStatusService = new ShippingStatusService();
        $this->formatter = new OrderFormatter($this->debugService);
        $this->shippingManager = new OrderShippingManager($this->debugService, $this->shippingStatusService);
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
            $page = (int)($params['page'] ?? 1);
            $per_page = (int)($params['per_page'] ?? 10);
            $search = $params['search'] ?? '';
            $status = $params['status'] ?? 'all';
            $id = $params['id'] ?? null;

            $query = Order::with(['customer', 'order_items']);

            // 如果有 ID 參數，只取得單一訂單（包含子訂單）
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

            // 根據參數決定是否顯示子訂單
            $include_children = $params['include_children'] ?? '';

            if ($include_children === 'all') {
                // 顯示所有訂單（父訂單和子訂單），不加任何條件
            } elseif ($include_children === 'children_only') {
                $query->whereNotNull('parent_id');
            } else {
                // 預設：只顯示父訂單
                $query->whereNull('parent_id');
            }

            // 多賣家權限過濾（Phase 24）
            $current_user = wp_get_current_user();
            $isWpAdmin = in_array('administrator', (array)$current_user->roles, true);

            if (!$isWpAdmin && $current_user->ID > 0) {
                $accessible_seller_ids = SettingsService::get_accessible_seller_ids($current_user->ID);

                if (!empty($accessible_seller_ids)) {
                    global $wpdb;
                    $table_items = $wpdb->prefix . 'fct_order_items';
                    $table_posts = $wpdb->posts;

                    $seller_ids_placeholder = implode(',', array_map('intval', $accessible_seller_ids));

                    $order_ids = $wpdb->get_col(
                        "SELECT DISTINCT oi.order_id
                         FROM {$table_items} oi
                         INNER JOIN {$table_posts} p ON oi.post_id = p.ID OR oi.post_id = p.post_parent
                         WHERE p.post_author IN ({$seller_ids_placeholder})"
                    );

                    if (!empty($order_ids)) {
                        $query->whereIn('id', $order_ids);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                } else {
                    $query->whereRaw('1 = 0');
                }
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
     * 取得訂單狀態統計（全域統計，不受分頁影響）
     *
     * @return array
     */
    public function getOrderStats(): array
    {
        try {
            $parentOrders = Order::whereNull('parent_id')
                ->with(['children'])
                ->get();

            $total = $parentOrders->count();

            $stats = [
                'total' => $total,
                'unshipped' => 0,
                'preparing' => 0,
                'shipped' => 0
            ];

            foreach ($parentOrders as $parentOrder) {
                $children = $parentOrder->children ?? [];

                if (count($children) > 0) {
                    foreach ($children as $child) {
                        $status = $child->shipping_status ?? 'unshipped';
                        $this->incrementStatsByStatus($stats, $status);
                    }
                } else {
                    $status = $parentOrder->shipping_status ?? 'unshipped';
                    $this->incrementStatsByStatus($stats, $status);
                }
            }

            $this->debugService->log('OrderService', '取得訂單統計成功', $stats);

            return $stats;

        } catch (\Exception $e) {
            $this->debugService->log('OrderService', '取得訂單統計失敗', [
                'error' => $e->getMessage()
            ], 'error');

            return [
                'total' => 0,
                'unshipped' => 0,
                'preparing' => 0,
                'shipped' => 0
            ];
        }
    }

    /**
     * 根據狀態增加統計數字
     *
     * @param array &$stats 統計陣列
     * @param string $status 訂單狀態
     */
    private function incrementStatsByStatus(array &$stats, string $status): void
    {
        if (empty($status) || $status === 'unshipped' || $status === 'pending') {
            $stats['unshipped']++;
        } elseif ($status === 'preparing') {
            $stats['preparing']++;
        } elseif (in_array($status, ['shipped', 'completed', 'processing', 'ready_to_ship'])) {
            $stats['shipped']++;
        } else {
            $stats['unshipped']++;
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
     * 更新付款狀態
     *
     * @param int $orderId 訂單 ID
     * @param string $payment_status 付款狀態 (pending, paid, failed, refunded)
     * @return bool
     */
    public function updatePaymentStatus(int $orderId, string $payment_status): bool
    {
        $this->debugService->log('OrderService', '更新付款狀態', [
            'order_id' => $orderId,
            'payment_status' => $payment_status
        ]);

        try {
            $order = Order::find($orderId);

            if (!$order) {
                return false;
            }

            $old_status = $order->payment_status;
            $order->payment_status = $payment_status;

            if ($payment_status === 'paid' && empty($order->paid_at)) {
                $order->paid_at = current_time('mysql');
            }

            $result = $order->save();

            $this->debugService->log('OrderService', '付款狀態更新成功', [
                'order_id' => $orderId,
                'old_status' => $old_status,
                'new_status' => $payment_status
            ]);

            do_action('buygo/order_payment_status_changed', $orderId, $old_status, $payment_status);

            return $result;

        } catch (\Exception $e) {
            $this->debugService->log('OrderService', '更新付款狀態失敗', [
                'order_id' => $orderId,
                'payment_status' => $payment_status,
                'error' => $e->getMessage()
            ], 'error');

            return false;
        }
    }

    /**
     * 更新運送狀態（委派到 OrderShippingManager）
     *
     * @param string $orderId 訂單 ID
     * @param string $status 新狀態
     * @param string $reason 變更原因
     * @return bool
     */
    public function updateShippingStatus(string $orderId, string $status, string $reason = ''): bool
    {
        return $this->shippingManager->updateShippingStatus($orderId, $status, $reason);
    }

    /**
     * 執行訂單出貨（使用已分配的配額）
     *
     * @param int $order_id 訂單 ID
     * @param array $items 要出貨的商品項目
     * @return int|\WP_Error 出貨單 ID 或錯誤
     */
    public function shipOrder($order_id, $items = [])
    {
        global $wpdb;

        $this->debugService->log('OrderService', '開始執行訂單出貨', [
            'order_id' => $order_id,
            'items' => $items
        ]);

        // 開始 Transaction
        $wpdb->query('START TRANSACTION');

        try {
            // 1. 驗證訂單存在且狀態正確
            $order = Order::find($order_id);
            if (!$order) {
                $wpdb->query('ROLLBACK');
                $this->debugService->log('OrderService', '訂單不存在', [
                    'order_id' => $order_id
                ], 'error');
                return new \WP_Error('ORDER_NOT_FOUND', '訂單不存在');
            }

            if (in_array($order->status, ['cancelled', 'refunded', 'completed'])) {
                $wpdb->query('ROLLBACK');
                return new \WP_Error('INVALID_ORDER_STATUS', '訂單狀態不允許出貨');
            }

            if (empty($items)) {
                $wpdb->query('ROLLBACK');
                return new \WP_Error('NO_ITEMS', '請選擇要出貨的商品');
            }

            // 2. 驗證每個商品的 allocated_quantity 足夠
            $table_items = $wpdb->prefix . 'fct_order_items';
            foreach ($items as $item) {
                $order_item_id = (int)($item['order_item_id'] ?? 0);
                $quantity = (int)($item['quantity'] ?? 0);

                if ($order_item_id <= 0 || $quantity <= 0) {
                    $wpdb->query('ROLLBACK');
                    return new \WP_Error('INVALID_ITEM', '訂單項目 ID 或數量無效');
                }

                $order_item = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_items} WHERE id = %d AND order_id = %d",
                    $order_item_id,
                    $order_id
                ), ARRAY_A);

                if (!$order_item) {
                    $wpdb->query('ROLLBACK');
                    return new \WP_Error('ORDER_ITEM_NOT_FOUND', "訂單項目 #{$order_item_id} 不存在");
                }

                $meta_data = json_decode($order_item['line_meta'] ?? $order_item['meta_data'] ?? '{}', true) ?: [];
                $allocated_qty = (int)($meta_data['_allocated_qty'] ?? 0);

                if ($quantity > $allocated_qty) {
                    $wpdb->query('ROLLBACK');
                    return new \WP_Error('INSUFFICIENT_ALLOCATION',
                        "商品 #{$order_item['post_id']} 的分配數量不足。需要: {$quantity}, 已分配: {$allocated_qty}");
                }
            }

            // 3. 取得賣家 ID
            $seller_id = $this->getSellerId($items, $order_id);
            if ($seller_id === 0) {
                $wpdb->query('ROLLBACK');
                return new \WP_Error('SELLER_NOT_FOUND', '無法取得賣家資訊');
            }

            // 4. 準備出貨單明細
            $shipment_items = [];
            foreach ($items as $item) {
                $order_item_id = (int)($item['order_item_id'] ?? 0);
                $quantity = (int)($item['quantity'] ?? 0);
                $product_id = (int)($item['product_id'] ?? 0);

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
                $wpdb->query('ROLLBACK');
                return $shipment_id;
            }

            // 6. 更新 allocated_quantity（扣除已出貨數量）
            foreach ($items as $item) {
                $order_item_id = (int)($item['order_item_id'] ?? 0);
                $quantity = (int)($item['quantity'] ?? 0);

                $order_item = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_items} WHERE id = %d",
                    $order_item_id
                ), ARRAY_A);

                if ($order_item) {
                    $meta_data = json_decode($order_item['line_meta'] ?? $order_item['meta_data'] ?? '{}', true) ?: [];
                    $current_allocated = (int)($meta_data['_allocated_qty'] ?? 0);

                    $new_allocated = max(0, $current_allocated - $quantity);
                    $meta_data['_allocated_qty'] = $new_allocated;

                    $current_shipped = (int)($meta_data['_shipped_qty'] ?? 0);
                    $meta_data['_shipped_qty'] = $current_shipped + $quantity;

                    $wpdb->update(
                        $table_items,
                        ['line_meta' => json_encode($meta_data)],
                        ['id' => $order_item_id],
                        ['%s'],
                        ['%d']
                    );
                }
            }

            // 7. 更新訂單狀態（如果訂單中所有商品都已出貨）
            $allOrderItems = OrderItem::where('order_id', $order_id)->get();
            $totalOrdered = 0;
            $totalShipped = 0;

            foreach ($allOrderItems as $orderItem) {
                $totalOrdered += (int)$orderItem->quantity;

                $shipped = $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(quantity)
                     FROM {$wpdb->prefix}buygo_shipment_items
                     WHERE order_item_id = %d",
                    $orderItem->id
                ));
                $totalShipped += (int)($shipped ?? 0);
            }

            if ($totalShipped >= $totalOrdered) {
                $this->updateShippingStatus((string)$order_id, 'shipped', '所有商品已出貨');
            }

            // 提交 Transaction
            $wpdb->query('COMMIT');

            $this->debugService->log('OrderService', '訂單出貨成功', [
                'order_id' => $order_id,
                'shipment_id' => $shipment_id,
                'items_count' => count($items)
            ]);

            return $shipment_id;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            $this->debugService->log('OrderService', '訂單出貨失敗', [
                'order_id' => $order_id,
                'error' => $e->getMessage()
            ], 'error');
            return new \WP_Error('SHIP_ORDER_FAILED', '出貨失敗：' . $e->getMessage());
        }
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

        // 方法 4：Fallback 到網站管理員
        $this->debugService->log('OrderService', '找不到賣家，使用 Fallback', [
            'order_id' => $order_id,
            'current_user_id' => get_current_user_id()
        ], 'warning');

        $admins = get_users(['role' => 'administrator', 'number' => 1]);
        if (!empty($admins)) {
            return (int)$admins[0]->ID;
        }

        return 0;
    }

    /**
     * 拆分訂單：建立子訂單並複製地址資料
     *
     * 從父訂單指定的商品項目中拆分出新子訂單，
     * 包含商品項目建立、地址複製、父訂單 _allocated_qty 同步。
     *
     * @param int   $order_id   父訂單 ID
     * @param array $split_data 拆分參數，須包含 split_items 陣列
     * @return array|\WP_Error  成功回傳包含新訂單資訊的陣列，失敗回傳 WP_Error
     */
    public function splitOrder(int $order_id, array $split_data): array|\WP_Error
    {
        global $wpdb;
        $table_orders   = $wpdb->prefix . 'fct_orders';
        $table_items    = $wpdb->prefix . 'fct_order_items';
        $table_addresses = $wpdb->prefix . 'fct_order_addresses';

        // 檢查訂單是否存在
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_orders} WHERE id = %d",
            $order_id
        ));

        if (!$order) {
            return new \WP_Error('ORDER_NOT_FOUND', '找不到此訂單', ['status' => 404]);
        }

        // 驗證 split_items 必填
        if (empty($split_data['split_items']) || !is_array($split_data['split_items'])) {
            return new \WP_Error('NO_ITEMS_SELECTED', '請選擇要拆分的商品', ['status' => 400]);
        }

        $split_items = $split_data['split_items'];

        // 取得父訂單所有商品項目
        $order_items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_items} WHERE order_id = %d",
            $order_id
        ), ARRAY_A);

        if (empty($order_items)) {
            return new \WP_Error('NO_ORDER_ITEMS', '訂單沒有商品', ['status' => 400]);
        }

        // 建立商品項目索引（以 id 為鍵）
        $order_items_map = [];
        foreach ($order_items as $item) {
            $order_items_map[$item['id']] = $item;
        }

        // 驗證每個拆分項目的數量合法性
        $shipment_items = [];
        foreach ($split_items as $split_item) {
            $order_item_id = (int)($split_item['order_item_id'] ?? 0);
            $quantity      = (int)($split_item['quantity'] ?? 0);

            if (!isset($order_items_map[$order_item_id])) {
                return new \WP_Error(
                    'ORDER_ITEM_NOT_FOUND',
                    "訂單項目 #{$order_item_id} 不存在",
                    ['status' => 400]
                );
            }

            $order_item = $order_items_map[$order_item_id];

            // 計算該商品在所有子訂單中已拆分的數量
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

            if ($quantity <= 0) {
                return new \WP_Error('INVALID_QUANTITY', '拆分數量必須大於 0', ['status' => 400]);
            }

            if ($quantity > $available_quantity) {
                return new \WP_Error(
                    'QUANTITY_EXCEEDED',
                    "拆分數量 ({$quantity}) 不能超過可用數量 ({$available_quantity})",
                    ['status' => 400]
                );
            }

            $shipment_items[] = [
                'order_id'      => $order_id,
                'order_item_id' => $order_item_id,
                'product_id'    => (int)($order_item['post_id'] ?? $order_item['product_id'] ?? 0),
                'quantity'      => $quantity,
            ];
        }

        // 計算新訂單編號後綴（現有子訂單數量 + 1）
        $split_count  = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_orders} WHERE parent_id = %d",
            $order_id
        ));
        $split_suffix = (int)$split_count + 1;

        // 計算新子訂單的總金額
        $new_order_total = 0;
        foreach ($shipment_items as $item) {
            $order_item       = $order_items_map[$item['order_item_id']];
            $unit_price       = (int)($order_item['unit_price'] ?? $order_item['item_price'] ?? 0);
            $new_order_total += $unit_price * $item['quantity'];
        }

        // 建立子訂單記錄
        $new_order_data = [
            'parent_id'            => $order_id,
            'customer_id'          => (int)$order->customer_id,
            'status'               => 'pending',
            'payment_status'       => $order->payment_status ?? 'pending',
            'shipping_status'      => 'unshipped',
            'payment_method'       => $order->payment_method ?? '',
            'payment_method_title' => $order->payment_method_title ?? '',
            'currency'             => $order->currency ?? 'TWD',
            'subtotal'             => $new_order_total,
            'total_amount'         => $new_order_total,
            'tax_total'            => 0,
            'discount_tax'         => 0,
            'manual_discount_total'=> 0,
            'coupon_discount_total'=> 0,
            'shipping_tax'         => 0,
            'shipping_total'       => 0,
            'total_paid'           => 0,
            'total_refund'         => 0,
            'invoice_no'           => (!empty($order->invoice_no) ? $order->invoice_no : $order_id) . '-' . $split_suffix,
            'created_at'           => current_time('mysql'),
            'updated_at'           => current_time('mysql'),
        ];

        $new_order_inserted = $wpdb->insert(
            $table_orders,
            $new_order_data,
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d',
             '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s']
        );

        if ($new_order_inserted === false) {
            return new \WP_Error(
                'CREATE_ORDER_FAILED',
                '建立拆分訂單失敗：' . $wpdb->last_error,
                ['status' => 500]
            );
        }

        $new_order_id = $wpdb->insert_id;

        // 建立子訂單的商品項目
        $items_inserted = 0;
        foreach ($shipment_items as $item) {
            $order_item = $order_items_map[$item['order_item_id']];
            $unit_price = (int)($order_item['unit_price'] ?? $order_item['item_price'] ?? 0);
            $line_total = $unit_price * $item['quantity'];

            $new_item_data = [
                'order_id'         => $new_order_id,
                'post_id'          => (int)($order_item['post_id'] ?? 0),
                'object_id'        => (int)($order_item['object_id'] ?? 0),
                'quantity'         => $item['quantity'],
                'unit_price'       => $unit_price,
                'subtotal'         => $line_total,
                'line_total'       => $line_total,
                'post_title'       => $order_item['post_title'] ?? $order_item['title'] ?? '',
                'title'            => $order_item['title'] ?? '',
                'fulfillment_type' => $order_item['fulfillment_type'] ?? 'physical',
                'payment_type'     => $order_item['payment_type'] ?? 'onetime',
                'cart_index'       => $order_item['cart_index'] ?? 0,
                'created_at'       => current_time('mysql'),
                'updated_at'       => current_time('mysql'),
            ];

            $insert_result = $wpdb->insert(
                $table_items,
                $new_item_data,
                ['%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
            );

            if ($insert_result !== false) {
                $items_inserted++;
            }
        }

        if ($items_inserted === 0 && !empty($shipment_items)) {
            return new \WP_Error(
                'CREATE_ORDER_ITEMS_FAILED',
                '建立拆分訂單的商品項目失敗',
                ['status' => 500]
            );
        }

        // 複製父訂單的地址資料到子訂單
        $parent_addresses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_addresses} WHERE order_id = %d",
            $order_id
        ), ARRAY_A);

        $this->debugService->log('OrderService', '開始複製父訂單地址', [
            'parent_order_id'       => $order_id,
            'child_order_id'        => $new_order_id,
            'parent_addresses_count' => count($parent_addresses),
        ]);

        if (!empty($parent_addresses)) {
            $addresses_copied = 0;
            foreach ($parent_addresses as $address) {
                unset($address['id']);
                $address['order_id']   = $new_order_id;
                $address['created_at'] = current_time('mysql');
                $address['updated_at'] = current_time('mysql');

                $result = $wpdb->insert($table_addresses, $address);

                if ($result === false) {
                    $this->debugService->log('OrderService', '地址複製失敗', [
                        'address_type' => $address['type'] ?? 'unknown',
                        'error'        => $wpdb->last_error,
                    ], 'error');
                } else {
                    $addresses_copied++;
                }
            }

            $this->debugService->log('OrderService', '複製父訂單地址完成', [
                'parent_order_id'  => $order_id,
                'child_order_id'   => $new_order_id,
                'addresses_copied' => $addresses_copied,
                'total_addresses'  => count($parent_addresses),
            ]);
        } else {
            $this->debugService->log('OrderService', '父訂單沒有地址資料', [
                'parent_order_id' => $order_id,
            ], 'warning');
        }

        // 同步更新父訂單項目的 _allocated_qty（重新計算，確保與實際子訂單同步）
        foreach ($shipment_items as $item) {
            $parent_order_item_id = $item['order_item_id'];
            $parent_order_item    = $order_items_map[$parent_order_item_id] ?? null;

            if (!$parent_order_item) {
                continue;
            }

            // 查詢所有子訂單中該 object_id 的實際已分配數量
            $current_allocated = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(oi.quantity), 0)
                 FROM {$wpdb->prefix}fct_order_items oi
                 INNER JOIN {$wpdb->prefix}fct_orders o ON oi.order_id = o.id
                 WHERE o.parent_id = %d
                 AND oi.object_id = %d",
                $order_id,
                (int)($parent_order_item['object_id'] ?? 0)
            ));

            // 更新父訂單項目的 line_meta._allocated_qty
            $parent_meta                    = json_decode($parent_order_item['line_meta'] ?? '{}', true) ?: [];
            $parent_meta['_allocated_qty']  = (int)$current_allocated;

            $wpdb->update(
                $table_items,
                ['line_meta' => json_encode($parent_meta)],
                ['id' => $parent_order_item_id],
                ['%s'],
                ['%d']
            );
        }

        $this->debugService->log('OrderService', '訂單拆分成功', [
            'order_id'     => $order_id,
            'new_order_id' => $new_order_id,
            'split_suffix' => $split_suffix,
        ]);

        return [
            'original_order_id' => $order_id,
            'new_order_id'      => $new_order_id,
            'order_number'      => (!empty($order->invoice_no) ? $order->invoice_no : $order_id) . '-' . $split_suffix,
        ];
    }

    /**
     * 格式化訂單資料（委派到 OrderFormatter）
     *
     * @param mixed $order 訂單物件或陣列
     * @return array
     */
    private function formatOrder($order): array
    {
        return $this->formatter->format($order);
    }
}
