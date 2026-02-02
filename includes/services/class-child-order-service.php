<?php

namespace BuyGoPlus\Services;

use FluentCart\App\Models\Order;
use FluentCart\App\Models\Customer;

/**
 * Child Order Service - 子訂單查詢服務
 *
 * 提供子訂單查詢功能，供購物者前台顯示子訂單資料
 *
 * @package BuyGoPlus\Services
 * @version 1.0.0
 * @since Phase 36
 */
class ChildOrderService
{
    private $debugService;

    /**
     * 建構子
     */
    public function __construct()
    {
        $this->debugService = DebugService::get_instance();
    }

    /**
     * 從 WordPress user_id 取得 FluentCart customer_id
     *
     * 解決 customer_id 與 user_id 混淆的問題：
     * - FluentCart 的 customer_id 是 wp_fct_customers.id
     * - WordPress 的 user_id 是 wp_users.ID
     * - 需要透過 wp_fct_customers.user_id 欄位進行轉換
     *
     * @param int $user_id WordPress 使用者 ID
     * @return int|null FluentCart customer_id，若找不到返回 null
     */
    public static function getCustomerIdFromUserId(int $user_id): ?int
    {
        $customer = Customer::where('user_id', $user_id)->first();
        return $customer ? $customer->id : null;
    }

    /**
     * 取得指定父訂單的所有子訂單
     *
     * @param int $parent_order_id 父訂單 ID
     * @param int $customer_id FluentCart customer_id（非 WordPress user_id）
     * @return array 格式化的子訂單資料
     * @throws \Exception 訂單不存在（code: 404）或無權限（code: 403）
     */
    public function getChildOrdersByParentId(int $parent_order_id, int $customer_id): array
    {
        $this->debugService->log('ChildOrderService', '開始取得子訂單', [
            'parent_order_id' => $parent_order_id,
            'customer_id' => $customer_id
        ]);

        try {
            // 1. 驗證父訂單存在
            $parent_order = Order::find($parent_order_id);
            if (!$parent_order) {
                $this->debugService->log('ChildOrderService', '父訂單不存在', [
                    'parent_order_id' => $parent_order_id
                ], 'warning');
                throw new \Exception('訂單不存在', 404);
            }

            // 2. 驗證父訂單屬於此 customer_id（權限驗證第二層）
            if ((int)$parent_order->customer_id !== $customer_id) {
                $this->debugService->log('ChildOrderService', '無權限存取訂單', [
                    'parent_order_id' => $parent_order_id,
                    'order_customer_id' => $parent_order->customer_id,
                    'requested_customer_id' => $customer_id
                ], 'warning');
                throw new \Exception('無權限存取此訂單', 403);
            }

            // 3. 使用 Eager Loading 查詢子訂單和商品
            $child_orders = Order::where('parent_id', $parent_order_id)
                ->with(['order_items'])
                ->orderBy('created_at', 'desc')
                ->get();

            // 4. 格式化回傳資料
            $formatted = [];
            foreach ($child_orders as $order) {
                $formatted[] = $this->formatChildOrder($order);
            }

            $this->debugService->log('ChildOrderService', '成功取得子訂單', [
                'parent_order_id' => $parent_order_id,
                'child_count' => count($formatted)
            ]);

            return [
                'child_orders' => $formatted,
                'count' => count($formatted),
                'currency' => $parent_order->currency ?? 'TWD'
            ];

        } catch (\Exception $e) {
            // 如果是已知的錯誤碼（404, 403），直接拋出
            if (in_array($e->getCode(), [403, 404])) {
                throw $e;
            }

            $this->debugService->log('ChildOrderService', '取得子訂單失敗', [
                'parent_order_id' => $parent_order_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 'error');

            throw new \Exception('無法取得子訂單列表：' . $e->getMessage(), 500);
        }
    }

    /**
     * 格式化單一子訂單
     *
     * @param Order $order 子訂單 Model
     * @return array 格式化的子訂單資料
     */
    public function formatChildOrder(Order $order): array
    {
        $items = $order->order_items;
        $seller_name = $this->getSellerNameFromItems($items);

        return [
            'id' => $order->id,
            'invoice_no' => $order->invoice_no ?? '',
            'payment_status' => $order->payment_status ?? 'pending',
            'shipping_status' => $order->shipping_status ?? 'unshipped',
            'fulfillment_status' => $order->status ?? 'pending',
            'total_amount' => ($order->total_amount ?? 0) / 100,  // 分 → 元
            'currency' => $order->currency ?? 'TWD',
            'seller_name' => $seller_name,
            'items' => $this->formatItems($items),
            'created_at' => $order->created_at
        ];
    }

    /**
     * 從商品項目取得賣家名稱
     *
     * 注意：子訂單沒有直接的 seller_id 欄位，需要從商品的 post_author 取得
     * 如果商品是 variation，需要取得 parent 的 post_author
     *
     * @param \Illuminate\Database\Eloquent\Collection|array $items 商品項目集合
     * @return string 賣家名稱，若無法取得則返回 '未知賣家'
     */
    public function getSellerNameFromItems($items): string
    {
        // 處理空集合
        if (empty($items) || (is_object($items) && $items->isEmpty())) {
            return '未知賣家';
        }

        // 取得第一個商品
        $first_item = is_array($items) ? reset($items) : $items->first();

        // 處理物件或陣列格式
        if (is_object($first_item)) {
            $post_id = $first_item->post_id ?? null;
        } else {
            $post_id = $first_item['post_id'] ?? null;
        }

        if (!$post_id) {
            return '未知賣家';
        }

        // 取得商品
        $product = get_post($post_id);
        if (!$product) {
            return '未知賣家';
        }

        // 如果是 variation，取得 parent 的 author
        if ($product->post_type === 'product_variation' && $product->post_parent > 0) {
            $parent_product = get_post($product->post_parent);
            $seller_id = $parent_product ? $parent_product->post_author : null;
        } else {
            $seller_id = $product->post_author;
        }

        return $seller_id ? get_the_author_meta('display_name', $seller_id) : '未知賣家';
    }

    /**
     * 格式化商品項目清單
     *
     * @param \Illuminate\Database\Eloquent\Collection|array $items 商品項目集合
     * @return array 格式化的商品項目陣列
     */
    public function formatItems($items): array
    {
        $formatted = [];

        // 處理空集合
        if (empty($items)) {
            return $formatted;
        }

        foreach ($items as $item) {
            // 處理物件或陣列格式
            if (is_object($item)) {
                $formatted[] = [
                    'id' => $item->id ?? 0,
                    'product_id' => $item->post_id ?? $item->product_id ?? 0,
                    'title' => $item->title ?? $item->post_title ?? '未知商品',
                    'quantity' => $item->quantity ?? 1,
                    'unit_price' => ($item->unit_price ?? 0) / 100,  // 分 → 元
                    'line_total' => ($item->line_total ?? 0) / 100  // 分 → 元
                ];
            } else {
                $formatted[] = [
                    'id' => $item['id'] ?? 0,
                    'product_id' => $item['post_id'] ?? $item['product_id'] ?? 0,
                    'title' => $item['title'] ?? $item['post_title'] ?? '未知商品',
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => ($item['unit_price'] ?? 0) / 100,  // 分 → 元
                    'line_total' => ($item['line_total'] ?? 0) / 100  // 分 → 元
                ];
            }
        }

        return $formatted;
    }
}
