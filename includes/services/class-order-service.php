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
