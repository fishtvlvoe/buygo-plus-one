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

            // 注意：update_post_meta 在新值與舊值相同時會回傳 false，這不是錯誤
            // 需要用 get_post_meta 確認實際值是否正確
            if ($result === false) {
                $actual_value = get_post_meta($post_id, '_buygo_allocated', true);
                if (intval($actual_value) !== intval($new_allocated)) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('DB_ERROR', '更新商品已分配數量失敗');
                }
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
     * 取得購買該商品的所有訂單（用於備貨分配頁面）
     *
     * 只顯示「尚未完全建立出貨單」的訂單項目
     * 已經建立出貨單且數量已滿足的項目不會顯示
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

        // 查詢訂單項目，並計算已建立出貨單的數量
        // 【重要】只查詢父訂單（parent_id IS NULL），不查詢子訂單
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT
                oi.order_id,
                oi.id as order_item_id,
                oi.quantity,
                oi.line_meta,
                o.customer_id,
                o.parent_id,
                c.first_name,
                c.last_name,
                c.email,
                COALESCE(
                    (SELECT SUM(si.quantity)
                     FROM {$wpdb->prefix}buygo_shipment_items si
                     WHERE si.order_item_id = oi.id),
                    0
                ) as shipped_to_shipment,
                COALESCE(
                    (SELECT SUM(child_oi.quantity)
                     FROM {$wpdb->prefix}fct_orders child_o
                     INNER JOIN {$wpdb->prefix}fct_order_items child_oi ON child_o.id = child_oi.order_id
                     WHERE child_o.parent_id = o.id
                     AND child_o.type = 'split'
                     AND child_oi.object_id = oi.object_id),
                    0
                ) as allocated_to_child
             FROM {$wpdb->prefix}fct_order_items oi
             LEFT JOIN {$wpdb->prefix}fct_orders o ON oi.order_id = o.id
             LEFT JOIN {$wpdb->prefix}fct_customers c ON o.customer_id = c.id
             WHERE oi.object_id = %d
             AND o.parent_id IS NULL
             AND o.status NOT IN ('cancelled', 'refunded')
             ORDER BY o.created_at DESC",
            $product_id
        ), ARRAY_A);

        $orders = [];
        foreach ($items as $item) {
            $meta_data = json_decode($item['line_meta'] ?? '{}', true) ?: [];
            $shipped = (int)($meta_data['_shipped_qty'] ?? 0);
            $shipped_to_shipment = (int)($item['shipped_to_shipment'] ?? 0);
            $allocated_to_child = (int)($item['allocated_to_child'] ?? 0);

            // 計算剩餘待分配數量（訂單數量 - 已建立子訂單的數量）
            // 【重要】使用子訂單數量而非出貨單數量
            $required = (int)$item['quantity'];
            $pending = $required - $allocated_to_child;

            // 如果已經全部建立子訂單，跳過此訂單項目
            if ($pending <= 0) {
                $this->debugService->log('AllocationService', '跳過已完全分配的訂單項目', [
                    'order_item_id' => $item['order_item_id'],
                    'required' => $required,
                    'allocated_to_child' => $allocated_to_child
                ]);
                continue;
            }

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
                'required' => $required,                            // 下單量 (訂單總需求)
                'already_allocated' => $allocated_to_child,         // 已建立子訂單的數量
                'allocated' => 0,                                   // 本次分配 (前端輸入)
                'pending' => $pending,                              // 待分配 (剩餘需求)
                'shipped' => $shipped_to_shipment,                  // 已出貨數量
                'status' => $allocated_to_child >= $required ? '已分配' : ($allocated_to_child > 0 ? '部分分配' : '未分配')
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

            // 注意：update_post_meta 在新值與舊值相同時會回傳 false，這不是錯誤
            // 需要用 get_post_meta 確認實際值是否正確
            if ($result === false) {
                $actual_value = get_post_meta($post_id, '_buygo_allocated', true);
                if (intval($actual_value) !== intval($new_product_allocated)) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('DB_ERROR', '更新商品已分配數量失敗');
                }
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

            // 3. 計算金額（FluentCart 使用 unit_price 欄位，已經是分為單位）
            $unit_price = (float)$parent_item->unit_price;
            $child_total_cents = $unit_price * $quantity;  // unit_price 已經是分單位，直接相乘即可

            $this->debugService->log('AllocationService', '取得父訂單項目價格', [
                'parent_item_id' => $parent_item->id,
                'unit_price' => $unit_price,
                'quantity' => $quantity,
                'child_total_cents' => $child_total_cents
            ]);

            // 4. 生成子訂單編號
            $split_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders
                 WHERE parent_id = %d AND type = 'split'",
                $parent_order_id
            )) + 1;

            // 如果父訂單沒有 invoice_no，使用訂單 ID
            $parent_invoice = !empty($parent_order->invoice_no) ? $parent_order->invoice_no : "#{$parent_order_id}";
            $child_invoice_no = $parent_invoice . '-' . $split_count;

            // 5. 建立子訂單
            $this->debugService->log('AllocationService', '準備建立子訂單', [
                'parent_id' => $parent_order_id,
                'customer_id' => $parent_order->customer_id,
                'invoice_no' => $child_invoice_no,
                'total_amount' => $child_total_cents,
                'currency' => $parent_order->currency
            ]);

            $result = $wpdb->insert(
                $wpdb->prefix . 'fct_orders',
                [
                    'parent_id' => $parent_order_id,
                    'type' => 'split',
                    'customer_id' => $parent_order->customer_id,
                    'status' => 'pending',
                    'shipping_status' => 'unshipped',  // 子訂單初始狀態為「未出貨」
                    'total_amount' => $child_total_cents,
                    'currency' => $parent_order->currency,
                    'payment_method' => $parent_order->payment_method,
                    'invoice_no' => $child_invoice_no,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s']
            );

            if ($result === false || $wpdb->insert_id === 0) {
                $this->debugService->log('AllocationService', '建立子訂單失敗 - DB Error', [
                    'wpdb_last_error' => $wpdb->last_error,
                    'wpdb_insert_id' => $wpdb->insert_id,
                    'result' => $result
                ], 'error');
                return new WP_Error('DB_ERROR', '建立子訂單失敗：' . $wpdb->last_error);
            }

            $child_order_id = $wpdb->insert_id;

            $this->debugService->log('AllocationService', '子訂單 INSERT 成功', [
                'child_order_id' => $child_order_id
            ]);

            // 6. 複製訂單項目（FluentCart 使用 unit_price 欄位）
            $item_subtotal = $unit_price * $quantity;

            // 準備子訂單項目的 meta 資料（重要：設定分配數量）
            $child_item_meta = json_encode([
                '_allocated_qty' => $quantity,  // 設定分配數量 = 子訂單數量（出貨驗證需要）
                '_shipped_qty' => 0              // 已出貨數量初始為 0
            ]);

            $wpdb->insert(
                $wpdb->prefix . 'fct_order_items',
                [
                    'order_id' => $child_order_id,
                    'post_id' => $parent_item->post_id,
                    'object_id' => $product_id,
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                    'subtotal' => $item_subtotal,
                    'line_meta' => $child_item_meta,  // 使用包含 _allocated_qty 的 meta
                ],
                ['%d', '%d', '%d', '%d', '%f', '%f', '%s']
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
                'total_amount' => $child_total_cents
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
