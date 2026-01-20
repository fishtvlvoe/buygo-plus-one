<?php

namespace BuyGoPlus\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Allocation Service - 庫存分配服務
 * 
 * 處理商品庫存分配給訂單的邏輯
 * 
 * @package BuyGoPlus\Services
 * @version 1.0.0
 */
class AllocationService
{
    private $debugService;

    public function __construct()
    {
        $this->debugService = new DebugService();
    }

    /**
     * 分配庫存給訂單
     * 
     * @param int $product_id 商品 ID
     * @param array $order_ids 要分配的訂單 ID 陣列
     * @return bool|WP_Error 成功或錯誤
     */
    public function allocateStock($product_id, $order_ids)
    {
        global $wpdb;
        
        $this->debugService->log('AllocationService', '開始分配庫存', [
            'product_id' => $product_id,
            'order_ids' => $order_ids
        ]);
        
        // 開始 Transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // 0. 先取得商品的 post_id（因為 product_id 是 FluentCart variation ID，而 meta 儲存在 WordPress Post 上）
            $product = \FluentCart\App\Models\ProductVariation::find($product_id);
            if (!$product) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('PRODUCT_NOT_FOUND', '商品不存在');
            }
            $post_id = $product->post_id;
            
            // 1. 取得商品的「已採購」數量
            $purchased = (int)get_post_meta($post_id, '_buygo_purchased', true);
            
            // 2. 取得商品的「已分配」數量
            $allocated = (int)get_post_meta($post_id, '_buygo_allocated', true);
            
            // 3. 計算剩餘可分配數量
            $available = $purchased - $allocated;
            
            if ($available <= 0) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('NO_STOCK', '沒有可分配的庫存');
            }
            
            // 4. 查詢訂單項目
            $placeholders = implode(',', array_fill(0, count($order_ids), '%d'));
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fct_order_items 
                 WHERE object_id = %d AND order_id IN ($placeholders)",
                array_merge([$product_id], $order_ids)
            ), ARRAY_A);
            
            if (empty($items)) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('NO_ORDER_ITEMS', '找不到對應的訂單項目');
            }
            
            // 5. 計算需要分配的總數量
            $total_needed = 0;
            foreach ($items as $item) {
                $meta_data = json_decode($item['line_meta'] ?? '{}', true) ?: [];
                $already_allocated = (int)($meta_data['_allocated_qty'] ?? 0);
                $still_needed = max(0, (int)$item['quantity'] - $already_allocated);
                $total_needed += $still_needed;
            }
            
            if ($total_needed > $available) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('INSUFFICIENT_STOCK', 
                    "庫存不足。需要: {$total_needed}, 可用: {$available}");
            }
            
            // 6. 執行分配（更新 order_item meta）
            $allocated_count = 0;
            foreach ($items as $item) {
                $meta_data = json_decode($item['line_meta'] ?? '{}', true) ?: [];
                $already_allocated = (int)($meta_data['_allocated_qty'] ?? 0);
                $still_needed = max(0, (int)$item['quantity'] - $already_allocated);
                
                if ($still_needed > 0 && $available > 0) {
                    $to_allocate = min($still_needed, $available);
                    $meta_data['_allocated_qty'] = $already_allocated + $to_allocate;
                    
                    $result = $wpdb->update(
                        $wpdb->prefix . 'fct_order_items',
                        ['line_meta' => json_encode($meta_data)],
                        ['id' => $item['id']],
                        ['%s'],
                        ['%d']
                    );
                    
                    if ($result === false) {
                        $wpdb->query('ROLLBACK');
                        return new WP_Error('DB_ERROR', '更新訂單項目失敗：' . $wpdb->last_error);
                    }
                    
                    $available -= $to_allocate;
                    $allocated_count += $to_allocate;
                }
            }
            
            // 7. 更新商品的「已分配」總數
            $new_allocated = $allocated + $allocated_count;
            $result = update_post_meta($post_id, '_buygo_allocated', $new_allocated);
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('DB_ERROR', '更新商品已分配數量失敗');
            }
            
            // 提交 Transaction
            $wpdb->query('COMMIT');
            
            $this->debugService->log('AllocationService', '庫存分配成功', [
                'product_id' => $product_id,
                'allocated_count' => $allocated_count,
                'new_allocated' => $new_allocated
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            $this->debugService->log('AllocationService', '庫存分配失敗', [
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ], 'error');
            return new WP_Error('ALLOCATION_FAILED', '分配失敗：' . $e->getMessage());
        }
    }

    /**
     * 取得購買該商品的所有訂單
     * 
     * @param int $product_id 商品 ID
     * @return array 訂單列表
     */
    public function getProductOrders($product_id)
    {
        global $wpdb;
        
        $this->debugService->log('AllocationService', '取得商品訂單列表', [
            'product_id' => $product_id
        ]);
        
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                oi.order_id,
                oi.id as order_item_id,
                oi.quantity,
                oi.line_meta,
                o.customer_id,
                c.first_name,
                c.last_name,
                c.email
             FROM {$wpdb->prefix}fct_order_items oi
             LEFT JOIN {$wpdb->prefix}fct_orders o ON oi.order_id = o.id
             LEFT JOIN {$wpdb->prefix}fct_customers c ON o.customer_id = c.id
             WHERE oi.object_id = %d
             AND o.status NOT IN ('cancelled', 'refunded', 'completed')
             ORDER BY o.created_at DESC",
            $product_id
        ), ARRAY_A);
        
        $orders = [];
        foreach ($items as $item) {
            $meta_data = json_decode($item['line_meta'] ?? '{}', true) ?: [];
            $allocated = (int)($meta_data['_allocated_qty'] ?? 0);
            $shipped = (int)($meta_data['_shipped_qty'] ?? 0);
            
            $first_name = $item['first_name'] ?? '';
            $last_name = $item['last_name'] ?? '';
            $customer_name = trim($first_name . ' ' . $last_name);
            if (empty($customer_name)) {
                $customer_name = '未知客戶';
            }
            
            $orders[] = [
                'order_id' => (int)$item['order_id'],
                'order_item_id' => (int)$item['order_item_id'],
                'customer' => $customer_name,
                'email' => $item['email'] ?? '',
                'required' => (int)$item['quantity'],
                'allocated' => $allocated,
                'pending' => (int)$item['quantity'] - $allocated,
                'shipped' => $shipped,
                'status' => $allocated >= (int)$item['quantity'] ? '已分配' : ($allocated > 0 ? '部分分配' : '未分配')
            ];
        }
        
        return $orders;
    }

    /**
     * 更新訂單的分配數量（支援指定每個訂單的分配數量）
     * 
     * @param int $product_id 商品 ID
     * @param array $allocations 訂單分配數量陣列，格式：['order_id' => allocated_quantity]
     * @return bool|WP_Error 成功或錯誤
     */
    public function updateOrderAllocations($product_id, $allocations)
    {
        global $wpdb;
        
        // 將 allocations 的 key 轉換為整數（因為 JSON 物件的 key 是字串）
        $normalized_allocations = [];
        foreach ($allocations as $order_id => $quantity) {
            $normalized_allocations[(int)$order_id] = (int)$quantity;
        }
        $allocations = $normalized_allocations;
        
        $this->debugService->log('AllocationService', '開始更新訂單分配數量', [
            'product_id' => $product_id,
            'allocations' => $allocations
        ]);
        
        // 開始 Transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // 0. 先取得商品的 post_id（因為 product_id 是 FluentCart variation ID，而 meta 儲存在 WordPress Post 上）
            $product = \FluentCart\App\Models\ProductVariation::find($product_id);
            if (!$product) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('PRODUCT_NOT_FOUND', '商品不存在');
            }
            $post_id = $product->post_id;
            
            // 1. 取得商品的「已採購」數量
            $purchased = (int)get_post_meta($post_id, '_buygo_purchased', true);
            
            // 2. 取得商品的「已分配」數量
            $current_allocated = (int)get_post_meta($post_id, '_buygo_allocated', true);
            
            // 3. 查詢所有相關的訂單項目
            $order_ids = array_keys($allocations);
            if (empty($order_ids)) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('NO_ORDER_IDS', '沒有提供訂單 ID');
            }
            
            $placeholders = implode(',', array_fill(0, count($order_ids), '%d'));
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fct_order_items 
                 WHERE object_id = %d AND order_id IN ($placeholders)",
                array_merge([$product_id], $order_ids)
            ), ARRAY_A);
            
            if (empty($items)) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('NO_ORDER_ITEMS', '找不到對應的訂單項目');
            }
            
            // 4. 先計算所有訂單的總分配數量（包括未在此次更新的訂單）
            // 取得所有訂單項目的當前分配數量
            $all_items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fct_order_items 
                 WHERE object_id = %d",
                $product_id
            ), ARRAY_A);
            
            $total_allocated = 0;
            $items_to_update = [];
            
            // 建立要更新的訂單項目索引
            foreach ($items as $item) {
                $items_to_update[(int)$item['order_id']] = $item;
            }
            
            // 計算總分配數量
            foreach ($all_items as $item) {
                $order_id = (int)$item['order_id'];
                if (isset($items_to_update[$order_id])) {
                    // 使用新的分配數量
                    $total_allocated += isset($allocations[$order_id]) ? (int)$allocations[$order_id] : 0;
                } else {
                    // 使用現有的分配數量
                    $meta_data = json_decode($item['line_meta'] ?? '{}', true) ?: [];
                    $total_allocated += (int)($meta_data['_allocated_qty'] ?? 0);
                }
            }
            
            // 5. 驗證總分配數量不超過已採購數量
            if ($total_allocated > $purchased) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('INSUFFICIENT_STOCK', 
                    "總分配數量 ({$total_allocated}) 超過已採購數量 ({$purchased})");
            }
            
            // 6. 更新訂單項目的分配數量
            foreach ($items as $item) {
                $order_id = (int)$item['order_id'];
                $target_allocated = isset($allocations[$order_id]) ? (int)$allocations[$order_id] : 0;
                
                // 驗證分配數量不超過需求數量
                if ($target_allocated > (int)$item['quantity']) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('INVALID_ALLOCATION', 
                        "訂單 #{$order_id} 的分配數量 ({$target_allocated}) 超過需求數量 ({$item['quantity']})");
                }
                
                // 更新分配數量
                $meta_data = json_decode($item['line_meta'] ?? '{}', true) ?: [];
                $meta_data['_allocated_qty'] = $target_allocated;
                
                $result = $wpdb->update(
                    $wpdb->prefix . 'fct_order_items',
                    ['line_meta' => json_encode($meta_data)],
                    ['id' => $item['id']],
                    ['%s'],
                    ['%d']
                );
                
                if ($result === false) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('DB_ERROR', '更新訂單項目失敗：' . $wpdb->last_error);
                }
            }
            
            // 7. 更新商品的「已分配」總數
            $new_product_allocated = $total_allocated;
            
            $result = update_post_meta($post_id, '_buygo_allocated', $new_product_allocated);
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('DB_ERROR', '更新商品已分配數量失敗');
            }
            
            // 提交 Transaction
            $wpdb->query('COMMIT');

            $this->debugService->log('AllocationService', '更新訂單分配數量成功', [
                'product_id' => $product_id,
                'new_total_allocated' => $total_allocated,
                'new_product_allocated' => $new_product_allocated
            ]);

            // 8. 【新增】自動建立子訂單
            $child_orders = [];
            foreach ($allocations as $order_id => $allocated_qty) {
                if ($allocated_qty > 0) {
                    $child_order = $this->create_child_order($product_id, $order_id, $allocated_qty);
                    if (!is_wp_error($child_order)) {
                        $child_orders[] = $child_order;
                    }
                }
            }

            return [
                'success' => true,
                'child_orders' => $child_orders,
                'total_allocated' => $total_allocated
            ];

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            $this->debugService->log('AllocationService', '更新訂單分配數量失敗', [
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ], 'error');
            return new WP_Error('ALLOCATION_UPDATE_FAILED', '更新分配數量失敗：' . $e->getMessage());
        }
    }

    /**
     * 建立子訂單
     *
     * 當分配庫存給父訂單時，自動建立子訂單
     * 利用 FluentCart 原生的 parent_id 機制
     *
     * @param int $product_id 商品 ID (FluentCart variation ID)
     * @param int $parent_order_id 父訂單 ID
     * @param int $quantity 分配數量
     * @return array|WP_Error 子訂單資訊或錯誤
     */
    private function create_child_order($product_id, $parent_order_id, $quantity)
    {
        global $wpdb;

        $this->debugService->log('AllocationService', '開始建立子訂單', [
            'product_id' => $product_id,
            'parent_order_id' => $parent_order_id,
            'quantity' => $quantity
        ]);

        try {
            // 1. 獲取父訂單
            $parent_order = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fct_orders WHERE id = %d",
                $parent_order_id
            ));

            if (!$parent_order) {
                return new WP_Error('PARENT_ORDER_NOT_FOUND', '父訂單不存在');
            }

            // 2. 獲取父訂單中的商品項目
            $parent_item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fct_order_items
                 WHERE order_id = %d AND object_id = %d",
                $parent_order_id, $product_id
            ));

            if (!$parent_item) {
                return new WP_Error('PARENT_ITEM_NOT_FOUND', '父訂單中找不到此商品項目');
            }

            // 3. 計算金額
            $unit_price = (float)$parent_item->price;
            $child_total = $unit_price * $quantity;

            // 4. 生成子訂單編號
            $split_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders
                 WHERE parent_id = %d AND type = 'split'",
                $parent_order_id
            )) + 1;

            $child_invoice_no = $parent_order->invoice_no . '-' . $split_count;

            // 5. 建立子訂單
            $wpdb->insert(
                $wpdb->prefix . 'fct_orders',
                [
                    'parent_id' => $parent_order_id,
                    'type' => 'split',
                    'customer_id' => $parent_order->customer_id,
                    'status' => 'pending',
                    'total_amount' => $child_total,
                    'currency' => $parent_order->currency,
                    'payment_method' => $parent_order->payment_method,
                    'invoice_no' => $child_invoice_no,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s']
            );

            if ($wpdb->insert_id === 0) {
                return new WP_Error('DB_ERROR', '建立子訂單失敗：' . $wpdb->last_error);
            }

            $child_order_id = $wpdb->insert_id;

            // 6. 複製訂單項目
            $wpdb->insert(
                $wpdb->prefix . 'fct_order_items',
                [
                    'order_id' => $child_order_id,
                    'post_id' => $parent_item->post_id,
                    'object_id' => $product_id,
                    'quantity' => $quantity,
                    'price' => $unit_price,
                    'line_meta' => '{}',
                ],
                ['%d', '%d', '%d', '%d', '%f', '%s']
            );

            if ($wpdb->insert_id === 0) {
                // 回滾：刪除剛建立的子訂單
                $wpdb->delete(
                    $wpdb->prefix . 'fct_orders',
                    ['id' => $child_order_id],
                    ['%d']
                );
                return new WP_Error('DB_ERROR', '建立子訂單項目失敗：' . $wpdb->last_error);
            }

            // 7. 觸發 Hook
            do_action('buygo/child_order_created', $child_order_id, $parent_order_id);

            $this->debugService->log('AllocationService', '子訂單建立成功', [
                'child_order_id' => $child_order_id,
                'child_invoice_no' => $child_invoice_no,
                'parent_order_id' => $parent_order_id
            ]);

            // 8. 返回子訂單資訊
            return [
                'id' => $child_order_id,
                'invoice_no' => $child_invoice_no,
                'parent_id' => $parent_order_id,
                'quantity' => $quantity,
                'total_amount' => $child_total
            ];

        } catch (\Exception $e) {
            $this->debugService->log('AllocationService', '建立子訂單失敗', [
                'product_id' => $product_id,
                'parent_order_id' => $parent_order_id,
                'error' => $e->getMessage()
            ], 'error');
            return new WP_Error('CHILD_ORDER_CREATION_FAILED', '建立子訂單失敗：' . $e->getMessage());
        }
    }
}
