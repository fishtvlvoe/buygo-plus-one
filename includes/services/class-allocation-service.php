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
            // 1. 取得商品的「已採購」數量
            $purchased = (int)get_post_meta($product_id, '_buygo_purchased', true);
            
            // 2. 取得商品的「已分配」數量
            $allocated = (int)get_post_meta($product_id, '_buygo_allocated', true);
            
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
                 WHERE post_id = %d AND order_id IN ($placeholders)",
                array_merge([$product_id], $order_ids)
            ), ARRAY_A);
            
            if (empty($items)) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('NO_ORDER_ITEMS', '找不到對應的訂單項目');
            }
            
            // 5. 計算需要分配的總數量
            $total_needed = 0;
            foreach ($items as $item) {
                $meta_data = json_decode($item['meta_data'] ?? '{}', true) ?: [];
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
                $meta_data = json_decode($item['meta_data'] ?? '{}', true) ?: [];
                $already_allocated = (int)($meta_data['_allocated_qty'] ?? 0);
                $still_needed = max(0, (int)$item['quantity'] - $already_allocated);
                
                if ($still_needed > 0 && $available > 0) {
                    $to_allocate = min($still_needed, $available);
                    $meta_data['_allocated_qty'] = $already_allocated + $to_allocate;
                    
                    $result = $wpdb->update(
                        $wpdb->prefix . 'fct_order_items',
                        ['meta_data' => json_encode($meta_data)],
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
            $result = update_post_meta($product_id, '_buygo_allocated', $new_allocated);
            
            if (!$result) {
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
                oi.meta_data,
                o.customer_id,
                c.first_name,
                c.last_name
             FROM {$wpdb->prefix}fct_order_items oi
             LEFT JOIN {$wpdb->prefix}fct_orders o ON oi.order_id = o.id
             LEFT JOIN {$wpdb->prefix}fct_customers c ON o.customer_id = c.id
             WHERE oi.post_id = %d
             ORDER BY o.created_at DESC",
            $product_id
        ), ARRAY_A);
        
        $orders = [];
        foreach ($items as $item) {
            $meta_data = json_decode($item['meta_data'] ?? '{}', true) ?: [];
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
                'required' => (int)$item['quantity'],
                'allocated' => $allocated,
                'shipped' => $shipped,
                'status' => $allocated >= (int)$item['quantity'] ? '已分配' : '未分配'
            ];
        }
        
        return $orders;
    }
}
