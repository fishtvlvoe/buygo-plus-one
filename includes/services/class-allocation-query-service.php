<?php

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Allocation Query Service - 庫存分配查詢服務
 *
 * 負責讀取庫存分配相關資料：variation ID 查詢、商品訂單列表查詢。
 * 純讀取，不執行任何寫入操作。
 *
 * @package BuyGoPlus\Services
 */
class AllocationQueryService
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
                     AND child_o.status NOT IN ('cancelled', 'refunded')
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
}
