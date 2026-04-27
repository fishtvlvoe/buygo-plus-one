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
        $this->debugService = DebugService::get_instance();
    }

    /**
     * 取得同一商品下所有 variation IDs
     *
     * 多樣式商品：給定任一 variation ID，找出同 post_id 的所有 variation IDs
     * 單一商品：回傳只含自己的陣列
     *
     * @param int $variation_id 任一 ProductVariation ID
     * @return array ['post_id' => int, 'variation_ids' => int[]]
     */
    public function getAllVariationIds($variation_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'fct_product_variations';

        // 先取得這個 variation 的 post_id
        $post_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$table} WHERE id = %d LIMIT 1",
            $variation_id
        ));

        if (!$post_id) {
            // 傳入值可能已經是 post_id（WordPress post ID），再試一次用 post_id 查
            $ids_by_post = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$table} WHERE post_id = %d AND item_status = 'active' ORDER BY id ASC",
                $variation_id
            ));

            if (!empty($ids_by_post)) {
                // 傳入的確實是 post_id，用查到的 variation_ids 回傳
                return ['post_id' => $variation_id, 'variation_ids' => array_map('intval', $ids_by_post)];
            }

            // 兩種方式都找不到，才走原本 fallback
            return ['post_id' => 0, 'variation_ids' => [$variation_id]];
        }

        // 查詢同 post_id 的所有 active variation IDs
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table} WHERE post_id = %d AND item_status = 'active' ORDER BY id ASC",
            $post_id
        ));

        $variation_ids = !empty($ids) ? array_map('intval', $ids) : [$variation_id];

        return ['post_id' => $post_id, 'variation_ids' => $variation_ids];
    }

    /**
     * 取得多樣式商品所有 variation 的「已採購」總量
     *
     * @param int $post_id WordPress Post ID
     * @param array $variation_ids Variation ID 陣列
     * @return int 已採購總量
     */
    private function getTotalPurchased($post_id, $variation_ids)
    {
        global $wpdb;
        $table_meta = $wpdb->prefix . 'fct_meta';

        // 嘗試從 fct_meta 讀取各 variation 的已採購數量（多樣式商品）
        $placeholders = implode(',', array_fill(0, count($variation_ids), '%d'));
        $total_from_meta = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(CAST(meta_value AS UNSIGNED)), 0)
             FROM {$table_meta}
             WHERE object_type = 'variation'
             AND object_id IN ($placeholders)
             AND meta_key = '_buygo_purchased'",
            ...$variation_ids
        ));

        // 如果 fct_meta 有值就用，否則回退到 post_meta（單一商品）
        if ($total_from_meta > 0) {
            return $total_from_meta;
        }

        return (int) get_post_meta($post_id, '_buygo_purchased', true);
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

        // 【修復】多樣式商品支援：取得同一商品的所有 variation IDs
        $varInfo = $this->getAllVariationIds($product_id);
        $variation_ids = $varInfo['variation_ids'];
        $is_multi_variant = count($variation_ids) > 1;

        $this->debugService->log('AllocationService', '多樣式商品檢查', [
            'product_id' => $product_id,
            'post_id' => $varInfo['post_id'],
            'variation_ids' => $variation_ids,
            'is_multi_variant' => $is_multi_variant
        ]);

        // 建立 IN 條件的 placeholders
        $placeholders = implode(',', array_fill(0, count($variation_ids), '%d'));

        // 查詢訂單項目，並計算已建立出貨單的數量
        // 【重要】只查詢父訂單（parent_id IS NULL），不查詢子訂單
        // 【修復】使用 IN 條件查詢所有 variant 的訂單
        $sql = $wpdb->prepare(
            "SELECT
                oi.order_id,
                oi.id as order_item_id,
                oi.object_id,
                oi.quantity,
                oi.line_meta,
                o.customer_id,
                o.parent_id,
                c.first_name,
                c.last_name,
                c.email,
                pv.variation_title,
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
             LEFT JOIN {$wpdb->prefix}fct_product_variations pv ON oi.object_id = pv.id
             WHERE oi.object_id IN ($placeholders)
             AND o.parent_id IS NULL
             AND o.status NOT IN ('cancelled', 'refunded')
             AND (o.shipping_status IS NULL OR o.shipping_status NOT IN ('shipped', 'completed'))
             ORDER BY o.created_at DESC",
            ...$variation_ids
        );

        $items = $wpdb->get_results($sql, ARRAY_A);

        $orders = [];
        foreach ($items as $item) {
            $meta_data = json_decode($item['line_meta'] ?? '{}', true) ?: [];
            $meta_allocated = (int)($meta_data['_allocated_qty'] ?? 0);
            $shipped_to_shipment = (int)($item['shipped_to_shipment'] ?? 0);
            $allocated_to_child = (int)($item['allocated_to_child'] ?? 0);

            // 【修復】已分配數量取三者最大值：子訂單數量、line_meta._allocated_qty、已出貨數量
            // 因為有兩條分配路徑（子訂單 vs 一鍵分配），且已出貨一定已分配
            $already_allocated = max($allocated_to_child, $meta_allocated, $shipped_to_shipment);

            $required = (int)$item['quantity'];
            $pending = max(0, $required - $already_allocated);

            $first_name = $item['first_name'] ?? '';
            $last_name = $item['last_name'] ?? '';
            $customer_name = trim($first_name . ' ' . $last_name);
            if (empty($customer_name)) {
                $customer_name = '未知客戶';
            }

            // 多樣式商品：顯示 variant 名稱
            $variation_title = $item['variation_title'] ?? '';

            $orders[] = [
                'order_id' => (int)$item['order_id'],
                'order_item_id' => (int)$item['order_item_id'],
                'object_id' => (int)($item['object_id'] ?? $product_id),
                'customer' => $customer_name,
                'email' => $item['email'] ?? '',
                'variation_title' => $variation_title,
                'required' => $required,
                'already_allocated' => $already_allocated,
                'allocated' => 0,
                'pending' => $pending,
                'shipped' => $shipped_to_shipment,
                'status' => $already_allocated >= $required ? '已分配' : ($already_allocated > 0 ? '部分分配' : '未分配')
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
            // 0. 先取得商品的 post_id
            $product = \FluentCart\App\Models\ProductVariation::find($product_id);
            if (!$product) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('PRODUCT_NOT_FOUND', '商品不存在');
            }
            $post_id = $product->post_id;

            // 1. 取得所有 variation IDs（多樣式商品支援）
            $varInfo = $this->getAllVariationIds($product_id);
            $variation_ids = $varInfo['variation_ids'];

            // 2. 取得商品的「已採購」數量（多樣式走 fct_meta，單一商品走 post_meta）
            $purchased = $this->getTotalPurchased($post_id, $variation_ids);

            // 3. 查詢所有相關的訂單項目
            // 【修復】多樣式商品支援：用所有 variation IDs 查詢，不只用單一 product_id
            $order_ids = array_keys($allocations);
            if (empty($order_ids)) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('NO_ORDER_IDS', '沒有提供訂單 ID');
            }

            $order_placeholders = implode(',', array_fill(0, count($order_ids), '%d'));
            $var_placeholders = implode(',', array_fill(0, count($variation_ids), '%d'));
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT oi.* FROM {$wpdb->prefix}fct_order_items oi
                 INNER JOIN {$wpdb->prefix}fct_orders o ON o.id = oi.order_id
                 WHERE oi.object_id IN ($var_placeholders) AND oi.order_id IN ($order_placeholders)
                 AND o.parent_id IS NULL",
                array_merge($variation_ids, $order_ids)
            ), ARRAY_A);

            if (empty($items)) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('NO_ORDER_ITEMS', '找不到對應的訂單項目');
            }

            // 3. 逐一驗證每個訂單的分配數量
            // 【統一數據來源】從子訂單實際查詢已分配量，不依賴 _allocated_qty meta
            foreach ($items as $item) {
                $order_id = (int)$item['order_id'];
                $new_allocation = isset($allocations[$order_id]) ? (int)$allocations[$order_id] : 0;

                if ($new_allocation <= 0) {
                    continue;
                }

                // 從子訂單查詢該訂單此商品的實際已分配數量
                $actual_child_allocated = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(child_oi.quantity), 0)
                     FROM {$wpdb->prefix}fct_orders child_o
                     INNER JOIN {$wpdb->prefix}fct_order_items child_oi ON child_o.id = child_oi.order_id
                     WHERE child_o.parent_id = %d
                     AND child_o.type = 'split'
                     AND child_oi.object_id = %d",
                    $order_id,
                    (int)$item['object_id']
                ));

                $total_item_allocated = $actual_child_allocated + $new_allocation;

                // 驗證不超過訂單需求數量
                if ($total_item_allocated > (int)$item['quantity']) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('INVALID_ALLOCATION',
                        "訂單 #{$order_id} 的總分配數量 ({$total_item_allocated}) 超過需求數量 ({$item['quantity']})");
                }
            }

            // 4. 查詢當前已分配的總數量（從子訂單計算，所有 variant 合計）
            $current_child_allocated = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(child_oi.quantity), 0)
                 FROM {$wpdb->prefix}fct_orders child_o
                 INNER JOIN {$wpdb->prefix}fct_order_items child_oi ON child_o.id = child_oi.order_id
                 WHERE child_o.type = 'split'
                 AND child_oi.object_id IN ($var_placeholders)",
                ...$variation_ids
            ));

            $new_allocation_total = \array_sum($allocations);
            $total_allocated = $current_child_allocated + $new_allocation_total;

            $this->debugService->log('AllocationService', '分配數量計算', [
                'current_child_allocated' => $current_child_allocated,
                'new_allocation_total' => $new_allocation_total,
                'total_allocated' => $total_allocated,
                'purchased' => $purchased
            ]);

            // 5. 驗證總分配數量不超過已採購數量
            if ($total_allocated > $purchased) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('INSUFFICIENT_STOCK',
                    "總分配數量 ({$total_allocated}) 超過已採購數量 ({$purchased})");
            }

            // 6. 建立子訂單（在 COMMIT 之前，確保原子性）
            $child_orders = [];
            foreach ($allocations as $order_id => $allocated_qty) {
                if ($allocated_qty > 0) {
                    $child_order = $this->create_child_order($product_id, $order_id, $allocated_qty);
                    if (is_wp_error($child_order)) {
                        $wpdb->query('ROLLBACK');
                        return new WP_Error('CHILD_ORDER_FAILED',
                            "建立訂單 #{$order_id} 的子訂單失敗：" . $child_order->get_error_message());
                    }
                    $child_orders[] = $child_order;
                }
            }

            // 7. 子訂單全部建立成功後，從子訂單重新計算 _allocated_qty 並同步
            foreach ($items as $item) {
                $order_id = (int)$item['order_id'];

                // 從子訂單查詢最新的已分配數量（包含剛剛建立的）
                $actual_allocated = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(child_oi.quantity), 0)
                     FROM {$wpdb->prefix}fct_orders child_o
                     INNER JOIN {$wpdb->prefix}fct_order_items child_oi ON child_o.id = child_oi.order_id
                     WHERE child_o.parent_id = %d
                     AND child_o.type = 'split'
                     AND child_oi.object_id = %d",
                    $order_id,
                    (int)$item['object_id']
                ));

                // 同步 _allocated_qty meta（以子訂單為準）
                $meta_data = json_decode($item['line_meta'] ?? '{}', true) ?: [];
                $meta_data['_allocated_qty'] = $actual_allocated;

                $wpdb->update(
                    $wpdb->prefix . 'fct_order_items',
                    ['line_meta' => json_encode($meta_data)],
                    ['id' => $item['id']],
                    ['%s'],
                    ['%d']
                );
            }

            // 8. 從子訂單重新計算商品的「已分配」總數（所有 variant 合計）
            $recalc_allocated = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(child_oi.quantity), 0)
                 FROM {$wpdb->prefix}fct_orders child_o
                 INNER JOIN {$wpdb->prefix}fct_order_items child_oi ON child_o.id = child_oi.order_id
                 WHERE child_o.type = 'split'
                 AND child_oi.object_id IN ($var_placeholders)",
                ...$variation_ids
            ));

            update_post_meta($post_id, '_buygo_allocated', $recalc_allocated);

            // 提交 Transaction
            $wpdb->query('COMMIT');

            $this->debugService->log('AllocationService', '更新訂單分配數量成功', [
                'product_id' => $product_id,
                'recalc_allocated' => $recalc_allocated,
                'child_orders_count' => count($child_orders)
            ]);

            return [
                'success' => true,
                'child_orders' => $child_orders,
                'total_allocated' => $recalc_allocated
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
            // 【修復】多樣式商品支援：用所有 variation IDs 查詢，避免 product_id ≠ 父項目 object_id
            $varInfo = $this->getAllVariationIds($product_id);
            $var_ids = $varInfo['variation_ids'];
            $var_ph = implode(',', array_fill(0, count($var_ids), '%d'));
            $parent_item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fct_order_items
                 WHERE order_id = %d AND object_id IN ($var_ph)",
                array_merge([$parent_order_id], $var_ids)
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
                    'payment_status' => $parent_order->payment_status ?? 'pending',  // 繼承父訂單的付款狀態
                    'shipping_status' => 'unshipped',  // 子訂單初始狀態為「未出貨」
                    'subtotal' => $child_total_cents,
                    'total_amount' => $child_total_cents,
                    'currency' => $parent_order->currency,
                    'payment_method' => $parent_order->payment_method,
                    'payment_method_title' => $parent_order->payment_method_title ?? '',
                    'invoice_no' => $child_invoice_no,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%d', '%s', '%d', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s']
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

            // 取得商品標題（從父訂單項目複製）
            $product_title = $parent_item->title ?? $parent_item->post_title ?? '';
            if (empty($product_title)) {
                // 如果父訂單項目沒有標題，從 WordPress 讀取
                $product_title = get_the_title($parent_item->post_id) ?: '';
            }

            // 【修復】子訂單 object_id 使用父訂單項目的原始值，確保與父訂單一致
            $wpdb->insert(
                $wpdb->prefix . 'fct_order_items',
                [
                    'order_id' => $child_order_id,
                    'post_id' => $parent_item->post_id,
                    'object_id' => (int)$parent_item->object_id,
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                    'subtotal' => $item_subtotal,
                    'line_total' => $item_subtotal,  // 加入 line_total
                    'title' => $product_title,       // 加入 title
                    'post_title' => $product_title,  // 加入 post_title
                    'line_meta' => $child_item_meta,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%d', '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s']
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

            // 8. 複製父訂單地址到子訂單
            $this->copy_parent_addresses_to_child($parent_order_id, $child_order_id);

            $this->debugService->log('AllocationService', '子訂單建立成功', [
                'child_order_id' => $child_order_id,
                'child_invoice_no' => $child_invoice_no,
                'parent_order_id' => $parent_order_id
            ]);

            // 9. 返回子訂單資訊
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

    /**
     * 驗證調整分配的參數（純 PHP 邏輯，可單獨測試）
     *
     * 將驗證規則提取為 public 方法，讓單元測試不需要完整 WordPress 環境。
     *
     * @param int $product_id        商品 variation ID
     * @param int $order_id          父訂單 ID
     * @param int $new_quantity      新的分配數量（0 = 全撤）
     * @param int $current_allocated 目前子訂單的分配數量
     * @param int $shipped_qty       已出貨數量（from _shipped_qty）
     * @param int $order_quantity    父訂單的原始訂購數量
     * @return true|WP_Error  驗證通過回傳 true，失敗回傳 WP_Error
     */
    public function validateAdjustment(
        int $product_id,
        int $order_id,
        int $new_quantity,
        int $current_allocated,
        int $shipped_qty,
        int $order_quantity
    ) {
        // 驗證：new_quantity 不能為負數
        if ($new_quantity < 0) {
            return new WP_Error(
                'INVALID_QUANTITY',
                "new_quantity ({$new_quantity}) 不能為負數"
            );
        }

        // 驗證：new_quantity 不能超過父訂單原始訂購數量
        if ($new_quantity > $order_quantity) {
            return new WP_Error(
                'EXCEEDS_ORDER_QUANTITY',
                "new_quantity ({$new_quantity}) 超過訂購數量 ({$order_quantity})"
            );
        }

        // 驗證：new_quantity 不能低於已出貨數量（已出貨的不能撤銷）
        if ($new_quantity < $shipped_qty) {
            return new WP_Error(
                'BELOW_SHIPPED_QTY',
                "new_quantity ({$new_quantity}) 低於已出貨數量 ({$shipped_qty})，無法調整"
            );
        }

        return true;
    }

    /**
     * 調整已分配數量（減少分配或全撤）
     *
     * 賣家分配後發現分錯，可用此方法調整數量：
     * - 減少分配數量（例如 2→1）
     * - 全撤（new_quantity=0），會刪除子訂單
     * - 不能低於已出貨數量（_shipped_qty）
     *
     * @param int $product_id  商品 variation ID
     * @param int $order_id    父訂單 ID
     * @param int $new_quantity 新的分配數量（0 = 全撤）
     * @return array|WP_Error  成功回傳結果陣列，失敗回傳 WP_Error
     */
    public function adjustAllocation(int $product_id, int $order_id, int $new_quantity)
    {
        global $wpdb;

        $this->debugService->log('AllocationService', '開始調整分配數量', [
            'product_id'   => $product_id,
            'order_id'     => $order_id,
            'new_quantity' => $new_quantity,
        ]);

        // 1. 取得同一商品的所有 variation IDs（多樣式商品支援）
        $varInfo      = $this->getAllVariationIds($product_id);
        $variation_ids = $varInfo['variation_ids'];
        $post_id       = $varInfo['post_id'];

        $var_placeholders = implode(',', array_fill(0, count($variation_ids), '%d'));

        // 2. 查詢現有子訂單（type=split, parent_id=$order_id）中對應商品的子訂單
        $child_order = $wpdb->get_row($wpdb->prepare(
            "SELECT o.* FROM {$wpdb->prefix}fct_orders o
             INNER JOIN {$wpdb->prefix}fct_order_items oi ON o.id = oi.order_id
             WHERE o.parent_id = %d
             AND o.type = 'split'
             AND oi.object_id IN ($var_placeholders)
             LIMIT 1",
            array_merge([$order_id], $variation_ids)
        ));

        if (!$child_order) {
            return new WP_Error('CHILD_ORDER_NOT_FOUND', "找不到訂單 #{$order_id} 的分配子訂單");
        }

        $child_order_id = (int) $child_order->id;

        // 3. 取得子訂單項目（含 _shipped_qty 和 _allocated_qty）
        $child_item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fct_order_items
             WHERE order_id = %d
             AND object_id IN ($var_placeholders)
             LIMIT 1",
            array_merge([$child_order_id], $variation_ids)
        ));

        if (!$child_item) {
            return new WP_Error('CHILD_ITEM_NOT_FOUND', "找不到子訂單 #{$child_order_id} 的商品項目");
        }

        // 解析 line_meta 取出 _shipped_qty 和 _allocated_qty
        $child_meta        = json_decode($child_item->line_meta ?? '{}', true) ?: [];
        $shipped_qty       = (int) ($child_meta['_shipped_qty'] ?? 0);
        $current_allocated = (int) $child_item->quantity;

        // 4. 查詢父訂單原始訂購數量
        $parent_item = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fct_order_items
             WHERE order_id = %d
             AND object_id IN ($var_placeholders)
             LIMIT 1",
            array_merge([$order_id], $variation_ids)
        ), ARRAY_A);

        $order_quantity = (int) ($parent_item[0]['quantity'] ?? 0);
        $parent_item_id = (int) ($parent_item[0]['id'] ?? 0);

        // 5. 執行驗證
        $validation = $this->validateAdjustment(
            product_id:        $product_id,
            order_id:          $order_id,
            new_quantity:      $new_quantity,
            current_allocated: $current_allocated,
            shipped_qty:       $shipped_qty,
            order_quantity:    $order_quantity
        );

        if (is_wp_error($validation)) {
            return $validation;
        }

        // 數量沒有變化，直接回傳成功（無需更新）
        if ($new_quantity === $current_allocated) {
            return [
                'success'         => true,
                'message'         => '分配數量未變更',
                'child_order_id'  => $child_order_id,
                'new_quantity'    => $new_quantity,
                'total_allocated' => $current_allocated,
            ];
        }

        // 開始 Transaction
        $wpdb->query('START TRANSACTION');

        try {
            if ($new_quantity === 0) {
                // 6a. 全撤：刪除子訂單項目，再刪除子訂單
                $wpdb->delete(
                    $wpdb->prefix . 'fct_order_items',
                    ['order_id' => $child_order_id],
                    ['%d']
                );
                $wpdb->delete(
                    $wpdb->prefix . 'fct_orders',
                    ['id' => $child_order_id],
                    ['%d']
                );

                $this->debugService->log('AllocationService', '全撤子訂單', [
                    'child_order_id' => $child_order_id,
                    'order_id'       => $order_id,
                ]);
            } else {
                // 6b. 減少數量：更新子訂單項目的 quantity 和 line_meta._allocated_qty
                $unit_price        = (float) $child_item->unit_price;
                $new_subtotal      = $unit_price * $new_quantity;
                $child_meta['_allocated_qty'] = $new_quantity;

                $wpdb->update(
                    $wpdb->prefix . 'fct_order_items',
                    [
                        'quantity'   => $new_quantity,
                        'subtotal'   => $new_subtotal,
                        'line_total' => $new_subtotal,
                        'line_meta'  => json_encode($child_meta),
                    ],
                    ['id' => (int) $child_item->id],
                    ['%d', '%f', '%f', '%s'],
                    ['%d']
                );

                // 更新子訂單的 total_amount（重算）
                $wpdb->update(
                    $wpdb->prefix . 'fct_orders',
                    [
                        'total_amount' => $new_subtotal,
                        'subtotal'     => $new_subtotal,
                    ],
                    ['id' => $child_order_id],
                    ['%f', '%f'],
                    ['%d']
                );

                $this->debugService->log('AllocationService', '減少分配數量', [
                    'child_order_id'   => $child_order_id,
                    'current_quantity' => $current_allocated,
                    'new_quantity'     => $new_quantity,
                ]);
            }

            // 7. 同步父訂單項目的 _allocated_qty（從子訂單重算）
            if ($parent_item_id > 0) {
                // 重新計算父訂單下所有子訂單的分配總量（調整後）
                $recalc_parent_allocated = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(child_oi.quantity), 0)
                     FROM {$wpdb->prefix}fct_orders child_o
                     INNER JOIN {$wpdb->prefix}fct_order_items child_oi ON child_o.id = child_oi.order_id
                     WHERE child_o.parent_id = %d
                     AND child_o.type = 'split'
                     AND child_oi.object_id IN ($var_placeholders)",
                    array_merge([$order_id], $variation_ids)
                ));

                $parent_meta = json_decode($parent_item[0]['line_meta'] ?? '{}', true) ?: [];
                $parent_meta['_allocated_qty'] = $recalc_parent_allocated;

                $wpdb->update(
                    $wpdb->prefix . 'fct_order_items',
                    ['line_meta' => json_encode($parent_meta)],
                    ['id' => $parent_item_id],
                    ['%s'],
                    ['%d']
                );
            }

            // 8. 重算商品的 _buygo_allocated（所有 variant 合計）
            $recalc_allocated = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(child_oi.quantity), 0)
                 FROM {$wpdb->prefix}fct_orders child_o
                 INNER JOIN {$wpdb->prefix}fct_order_items child_oi ON child_o.id = child_oi.order_id
                 WHERE child_o.type = 'split'
                 AND child_oi.object_id IN ($var_placeholders)",
                ...$variation_ids
            ));

            update_post_meta($post_id, '_buygo_allocated', $recalc_allocated);

            $wpdb->query('COMMIT');

            $this->debugService->log('AllocationService', '調整分配數量成功', [
                'product_id'      => $product_id,
                'order_id'        => $order_id,
                'new_quantity'    => $new_quantity,
                'total_allocated' => $recalc_allocated,
            ]);

            return [
                'success'         => true,
                'child_order_id'  => $child_order_id,
                'new_quantity'    => $new_quantity,
                'total_allocated' => $recalc_allocated,
            ];

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            $this->debugService->log('AllocationService', '調整分配數量失敗', [
                'product_id' => $product_id,
                'order_id'   => $order_id,
                'error'      => $e->getMessage(),
            ], 'error');
            return new WP_Error('ADJUSTMENT_FAILED', '調整分配數量失敗：' . $e->getMessage());
        }
    }

    /**
     * 取消子訂單並釋放庫存分配
     *
     * @param int $child_order_id 子訂單 ID
     * @return bool|WP_Error 成功回傳 true，失敗回傳 WP_Error
     */
    public function cancelChildOrder(int $child_order_id): bool|\WP_Error
    {
        global $wpdb;

        // 1. 查詢子訂單
        $order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, type, status, shipping_status FROM {$wpdb->prefix}fct_orders WHERE id = %d",
                $child_order_id
            )
        );

        if ( ! $order ) {
            return new WP_Error( 'NOT_FOUND', '找不到子訂單' );
        }

        // 2. 驗證是子訂單 (type = split)
        if ( 'split' !== $order->type ) {
            return new WP_Error( 'NOT_CHILD_ORDER', '此訂單不是子訂單' );
        }

        // 3. 已取消
        if ( 'cancelled' === $order->status ) {
            return new WP_Error( 'ALREADY_CANCELLED', '子訂單已取消' );
        }

        // 4. 只允許取消 unshipped
        if ( 'unshipped' !== $order->shipping_status ) {
            return new WP_Error( 'CANNOT_CANCEL_SHIPPED', '只有未出貨的子訂單可以取消' );
        }

        // 5. 並發守衛：compare-and-set 確保狀態未被其他請求搶先修改
        $affected = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}fct_orders SET status = 'cancelled' WHERE id = %d AND shipping_status = 'unshipped' AND status != 'cancelled'",
                $child_order_id
            )
        );

        if ( false === $affected ) {
            return new WP_Error( 'DB_ERROR', '資料庫操作失敗' );
        }

        if ( 0 === $affected ) {
            return new WP_Error( 'STATUS_CONFLICT', '子訂單狀態已變更，請重新整理後再試' );
        }

        // 6. 清除所有對應 order_items 的 _allocated_qty（顯式釋放庫存）
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, line_meta FROM {$wpdb->prefix}fct_order_items WHERE order_id = %d",
                $child_order_id
            )
        );

        foreach ( $items as $item ) {
            $meta = json_decode( $item->line_meta, true ) ?: [];
            $meta['_allocated_qty'] = 0;
            $wpdb->update(
                $wpdb->prefix . 'fct_order_items',
                [ 'line_meta' => wp_json_encode( $meta ) ],
                [ 'id' => $item->id ]
            );
        }

        return true;
    }

    /**
     * 複製父訂單地址到子訂單
     *
     * @param int $parent_order_id 父訂單 ID
     * @param int $child_order_id 子訂單 ID
     * @return void
     */
    private function copy_parent_addresses_to_child($parent_order_id, $child_order_id)
    {
        global $wpdb;
        $table_addresses = $wpdb->prefix . 'fct_order_addresses';

        // 查詢父訂單的所有地址
        $parent_addresses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_addresses} WHERE order_id = %d",
            $parent_order_id
        ), ARRAY_A);

        $this->debugService->log('AllocationService', '開始複製父訂單地址', [
            'parent_order_id' => $parent_order_id,
            'child_order_id' => $child_order_id,
            'parent_addresses_count' => count($parent_addresses)
        ]);

        if (empty($parent_addresses)) {
            $this->debugService->log('AllocationService', '父訂單沒有地址資料', [
                'parent_order_id' => $parent_order_id
            ], 'warning');
            return;
        }

        $addresses_copied = 0;
        foreach ($parent_addresses as $address) {
            // 移除 ID 欄位，建立新的地址記錄
            unset($address['id']);
            $address['order_id'] = $child_order_id;
            $address['created_at'] = current_time('mysql');
            $address['updated_at'] = current_time('mysql');

            $result = $wpdb->insert($table_addresses, $address);

            if ($result === false) {
                $this->debugService->log('AllocationService', '地址複製失敗', [
                    'address_type' => $address['type'] ?? 'unknown',
                    'error' => $wpdb->last_error
                ], 'error');
            } else {
                $addresses_copied++;
            }
        }

        $this->debugService->log('AllocationService', '複製父訂單地址完成', [
            'parent_order_id' => $parent_order_id,
            'child_order_id' => $child_order_id,
            'addresses_copied' => $addresses_copied,
            'total_addresses' => count($parent_addresses)
        ]);
    }
}
