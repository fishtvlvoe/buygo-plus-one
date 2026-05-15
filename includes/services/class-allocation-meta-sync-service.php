<?php

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

class AllocationMetaSyncService
{
    /** 視為「非活躍」的訂單狀態（取消/退款），不計入分配數量 */
    const INACTIVE_STATUSES = ['cancelled', 'canceled', 'refunded'];

    /** 視為「不可分配」的訂單狀態（包含已出貨），計算 line meta _allocated_qty 時排除 */
    const NON_ALLOCATABLE_STATUSES = ['cancelled', 'canceled', 'refunded', 'shipped'];

    public function syncForShipment(int $shipment_id): void
    {
        global $wpdb;

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT order_id, order_item_id, product_id, quantity
             FROM {$wpdb->prefix}buygo_shipment_items
             WHERE shipment_id = %d",
            $shipment_id
        ), ARRAY_A);

        $this->syncForShipmentItems($items);
    }

    public function syncForShipmentItems(array $items): void
    {
        if (empty($items)) {
            return;
        }

        global $wpdb;

        $table_items = $wpdb->prefix . 'fct_order_items';
        $table_orders = $wpdb->prefix . 'fct_orders';
        $seen_line_items = [];
        $seen_posts = [];

        foreach ($items as $item) {
            $order_id = (int)($item['order_id'] ?? 0);
            $order_item_id = (int)($item['order_item_id'] ?? 0);
            if ($order_id <= 0 || $order_item_id <= 0) {
                continue;
            }

            $shipment_item = $wpdb->get_row($wpdb->prepare(
                "SELECT id, object_id, post_id, line_meta FROM {$table_items} WHERE id = %d",
                $order_item_id
            ), ARRAY_A);
            if (empty($shipment_item)) {
                continue;
            }

            $object_id = (int)($shipment_item['object_id'] ?? 0);
            $post_id = (int)($shipment_item['post_id'] ?? ($item['product_id'] ?? 0));
            if ($object_id <= 0 || $post_id <= 0) {
                continue;
            }

            $order = $wpdb->get_row($wpdb->prepare(
                "SELECT parent_id, type FROM {$table_orders} WHERE id = %d",
                $order_id
            ), ARRAY_A);
            $order_data = (array)$order;
            $parent_order_id = (
                ($order_data['type'] ?? '') === 'split'
                && !empty($order_data['parent_id'])
            ) ? (int)$order_data['parent_id'] : $order_id;

            $parent_item = $wpdb->get_row($wpdb->prepare(
                "SELECT id, object_id, post_id, line_meta
                 FROM {$table_items}
                 WHERE order_id = %d AND object_id = %d
                 LIMIT 1",
                $parent_order_id,
                $object_id
            ), ARRAY_A);
            if (empty($parent_item)) {
                $parent_item = $shipment_item;
            }

            $line_key = $parent_order_id . ':' . $object_id;
            if (empty($seen_line_items[$line_key])) {
                $this->syncParentLineMeta($table_orders, $table_items, $parent_order_id, $object_id, $parent_item);
                $seen_line_items[$line_key] = true;
            }

            if (empty($seen_posts[$post_id])) {
                $this->syncProductAllocatedMeta($table_orders, $table_items, $post_id);
                $seen_posts[$post_id] = true;
            }
        }
    }

    /**
     * @deprecated 讀取端已遷移至 ProductStatsCalculator::calculateAllocatedToChildOrders /
     *             ::calculateAllocatedPerParentOrder。本方法的 line_meta._allocated_qty
     *             寫入暫保留供向後相容，未來獨立 change 處理移除。
     */
    private function syncParentLineMeta(string $table_orders, string $table_items, int $parent_order_id, int $object_id, array $parent_item): void
    {
        global $wpdb;

        $non_allocatable = "'" . implode("','", self::NON_ALLOCATABLE_STATUSES) . "'";
        $recalc_allocated = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(child_oi.quantity), 0)
             FROM {$table_orders} child_o
             INNER JOIN {$table_items} child_oi ON child_o.id = child_oi.order_id
             WHERE child_o.parent_id = %d
             AND child_o.type = 'split'
             AND child_o.status NOT IN ({$non_allocatable})
             AND child_oi.object_id = %d",
            $parent_order_id,
            $object_id
        ));

        $meta_data = json_decode($parent_item['line_meta'] ?? '{}', true) ?: [];
        $meta_data['_allocated_qty'] = $recalc_allocated;
        $wpdb->update(
            $table_items,
            ['line_meta' => wp_json_encode($meta_data)],
            ['id' => (int)$parent_item['id']],
            ['%s'],
            ['%d']
        );
    }

    /**
     * @deprecated 讀取端已遷移至 ProductStatsCalculator::calculateAllocatedToChildOrders /
     *             ::calculateAllocatedPerParentOrder。本方法的 _buygo_allocated post_meta
     *             寫入暫保留供向後相容，未來獨立 change 處理移除。
     */
    private function syncProductAllocatedMeta(string $table_orders, string $table_items, int $post_id): void
    {
        global $wpdb;

        $non_allocatable = "'" . implode("','", self::NON_ALLOCATABLE_STATUSES) . "'";
        $post_allocated = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(child_oi.quantity), 0)
             FROM {$table_orders} child_o
             INNER JOIN {$table_items} child_oi ON child_o.id = child_oi.order_id
             WHERE child_o.type = 'split'
             AND child_o.status NOT IN ({$non_allocatable})
             AND child_oi.post_id = %d",
            $post_id
        ));
        update_post_meta($post_id, '_buygo_allocated', $post_allocated);
    }
}
