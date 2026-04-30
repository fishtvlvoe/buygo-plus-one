<?php

namespace BuyGoPlus\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class AllocationBatchService
{
    private $allocationService;
    private $queryService;

    public function __construct(AllocationService $allocationService, AllocationQueryService $queryService)
    {
        $this->allocationService = $allocationService;
        $this->queryService = $queryService;
    }

    public function syncAllocatedQtyBatch(array $items): void
    {
        if (empty($items)) {
            return;
        }

        global $wpdb;

        $conditions = [];
        $values = [];
        foreach ($items as $item) {
            $conditions[] = '(child_o.parent_id = %d AND child_oi.object_id = %d)';
            $values[] = (int) $item['order_id'];
            $values[] = (int) $item['object_id'];
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT child_o.parent_id AS order_id, child_oi.object_id, COALESCE(SUM(child_oi.quantity), 0) AS allocated_qty
             FROM {$wpdb->prefix}fct_orders child_o
             INNER JOIN {$wpdb->prefix}fct_order_items child_oi ON child_o.id = child_oi.order_id
             WHERE child_o.type = 'split' AND child_o.status NOT IN ('cancelled', 'refunded')
             AND (" . implode(' OR ', $conditions) . ")
             GROUP BY child_o.parent_id, child_oi.object_id",
            ...$values
        ), ARRAY_A);

        $alloc_map = [];
        foreach ($results as $row) {
            $alloc_map[$row['order_id'] . ':' . $row['object_id']] = (int) ($row['allocated_qty'] ?? 0);
        }

        foreach ($items as $item) {
            $key = (int) $item['order_id'] . ':' . (int) $item['object_id'];
            $actual_allocated = $alloc_map[$key] ?? 0;
            $meta_data = json_decode($item['line_meta'] ?? '{}', true) ?: [];
            if (isset($meta_data['_allocated_qty']) && (int) $meta_data['_allocated_qty'] === $actual_allocated) {
                continue;
            }
            $meta_data['_allocated_qty'] = $actual_allocated;
            $wpdb->update($wpdb->prefix . 'fct_order_items', ['line_meta' => json_encode($meta_data)], ['id' => (int) $item['id']], ['%s'], ['%d']);
        }
    }

    public function allocateAllForCustomer(int $product_id, int $order_item_id, int $customer_id)
    {
        global $wpdb;

        $variation_ids = $this->queryService->getAllVariationIds($product_id)['variation_ids'];
        $var_placeholders = implode(',', array_fill(0, count($variation_ids), '%d'));
        $query = $order_item_id > 0
            ? [
                "SELECT oi.id as order_item_id, oi.order_id, oi.object_id, oi.quantity, oi.line_meta
                 FROM {$wpdb->prefix}fct_order_items oi
                 INNER JOIN {$wpdb->prefix}fct_orders o ON oi.order_id = o.id
                 WHERE oi.id = %d AND oi.object_id IN ($var_placeholders)
                 AND o.parent_id IS NULL AND o.status NOT IN ('cancelled', 'refunded')",
                array_merge([$order_item_id], $variation_ids),
            ]
            : [
                "SELECT oi.id as order_item_id, oi.order_id, oi.object_id, oi.quantity, oi.line_meta
                 FROM {$wpdb->prefix}fct_order_items oi
                 INNER JOIN {$wpdb->prefix}fct_orders o ON oi.order_id = o.id
                 WHERE oi.object_id IN ($var_placeholders) AND o.customer_id = %d
                 AND o.parent_id IS NULL AND o.status NOT IN ('cancelled', 'refunded')",
                array_merge($variation_ids, [$customer_id]),
            ];
        $order_items = $wpdb->get_results($wpdb->prepare($query[0], ...$query[1]));

        if (empty($order_items)) {
            return new WP_Error('order_not_found', '找不到訂單');
        }

        $allocations = [];
        $skipped_orders = [];
        foreach ($order_items as $item) {
            $meta_data = json_decode($item->line_meta ?? '{}', true) ?: [];
            $actual_shipped = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(quantity), 0) FROM {$wpdb->prefix}buygo_shipment_items WHERE order_item_id = %d",
                $item->order_item_id
            ));
            $child_allocated = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(child_oi.quantity), 0)
                 FROM {$wpdb->prefix}fct_orders child_o
                 INNER JOIN {$wpdb->prefix}fct_order_items child_oi ON child_o.id = child_oi.order_id
                 WHERE child_o.parent_id = %d AND child_o.type = 'split'
                 AND child_o.status NOT IN ('cancelled', 'refunded')
                 AND child_oi.object_id = %d",
                $item->order_id,
                (int) $item->object_id
            ));
            $already = max($child_allocated, (int) ($meta_data['_allocated_qty'] ?? 0), $actual_shipped);
            $needed = (int) $item->quantity - $already;
            if ($needed <= 0) {
                $skipped_orders[] = ['order_id' => $item->order_id, 'reason' => '已全部分配'];
                continue;
            }
            $allocations[] = [
                'order_id' => (int) $item->order_id,
                'object_id' => (int) $item->object_id,
                'quantity' => $needed,
            ];
        }

        if (empty($allocations)) {
            return ['total_allocated' => 0, 'child_orders' => [], 'skipped_orders' => $skipped_orders];
        }

        $result = $this->allocationService->updateOrderAllocations($product_id, $allocations);
        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'total_allocated' => array_sum(array_column($allocations, 'quantity')),
            'child_orders' => $result['child_orders'] ?? [],
            'skipped_orders' => $skipped_orders,
        ];
    }
}
