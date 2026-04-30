<?php

namespace BuyGoPlus\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class AllocationWriteService
{
    private $allocationService;
    private $batchService;
    private $debugService;
    private $queryService;

    public function __construct(AllocationService $allocationService, AllocationQueryService $queryService, AllocationBatchService $batchService)
    {
        $this->allocationService = $allocationService;
        $this->batchService = $batchService;
        $this->debugService = DebugService::get_instance();
        $this->queryService = $queryService;
    }

    public function updateOrderAllocations($product_id, $allocations)
    {
        global $wpdb;

        $is_per_item = !empty($allocations) && is_array(reset($allocations));

        $this->debugService->log('AllocationService', '開始更新訂單分配數量', [
            'product_id' => $product_id,
            'allocations' => $allocations,
            'format' => $is_per_item ? 'per-item' : 'legacy',
        ]);

        $wpdb->query('START TRANSACTION');

        try {
            $product = \FluentCart\App\Models\ProductVariation::find($product_id);
            if (!$product) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('PRODUCT_NOT_FOUND', '商品不存在');
            }

            $variation_ids = $this->queryService->getAllVariationIds($product_id)['variation_ids'];
            $purchased = $this->getTotalPurchased((int) $product->post_id, $variation_ids);

            // Normalize allocations to per-item format
            $normalized = [];
            if ($is_per_item) {
                foreach ($allocations as $alloc) {
                    $order_id = (int) ($alloc['order_id'] ?? 0);
                    $object_id = (int) ($alloc['object_id'] ?? 0);
                    $quantity = (int) ($alloc['quantity'] ?? 0);
                    if ($order_id > 0 && $object_id > 0 && $quantity > 0) {
                        $normalized[] = ['order_id' => $order_id, 'object_id' => $object_id, 'quantity' => $quantity];
                    }
                }
            } else {
                foreach ($allocations as $order_id => $quantity) {
                    $normalized[] = ['order_id' => (int) $order_id, 'object_id' => 0, 'quantity' => (int) $quantity];
                }
            }

            if (empty($normalized)) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('NO_ORDER_ITEMS', $is_per_item ? '沒有提供有效的分配項目' : '沒有提供訂單 ID');
            }

            $order_ids = array_unique(array_column($normalized, 'order_id'));

            $order_placeholders = implode(',', array_fill(0, count($order_ids), '%d'));
            $var_placeholders = implode(',', array_fill(0, count($variation_ids), '%d'));
            $db_items = $wpdb->get_results($wpdb->prepare(
                "SELECT oi.* FROM {$wpdb->prefix}fct_order_items oi
                 INNER JOIN {$wpdb->prefix}fct_orders o ON o.id = oi.order_id
                 WHERE oi.object_id IN ($var_placeholders) AND oi.order_id IN ($order_placeholders)
                 AND o.parent_id IS NULL",
                array_merge($variation_ids, $order_ids)
            ), ARRAY_A);

            if (empty($db_items)) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('NO_ORDER_ITEMS', '找不到對應的訂單項目');
            }

            // Build lookup maps
            $db_items_by_order = [];
            $db_item_map = [];
            foreach ($db_items as $db_item) {
                $oid = (int) $db_item['order_id'];
                $obj_id = (int) $db_item['object_id'];
                $db_items_by_order[$oid][] = $db_item;
                $db_item_map[$oid . ':' . $obj_id] = $db_item;
            }

            // Resolve object_ids for legacy format
            foreach ($normalized as &$norm) {
                if ($norm['object_id'] === 0) {
                    $matches = $db_items_by_order[$norm['order_id']] ?? [];
                    if (empty($matches)) {
                        $wpdb->query('ROLLBACK');
                        return new WP_Error('NO_ORDER_ITEMS', "找不到訂單 #{$norm['order_id']} 的項目");
                    }
                    $norm['object_id'] = (int) $matches[0]['object_id'];
                }
            }
            unset($norm);

            // Per-item validation
            foreach ($normalized as $norm) {
                $order_id = $norm['order_id'];
                $object_id = $norm['object_id'];
                $quantity = $norm['quantity'];

                $db_item = $db_item_map[$order_id . ':' . $object_id] ?? null;
                if (!$db_item) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('NO_ORDER_ITEMS', "找不到訂單 #{$order_id} 的項目 object_id={$object_id}");
                }

                $actual_child_allocated = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(child_oi.quantity), 0)
                     FROM {$wpdb->prefix}fct_orders child_o
                     INNER JOIN {$wpdb->prefix}fct_order_items child_oi ON child_o.id = child_oi.order_id
                     WHERE child_o.parent_id = %d
                     AND child_o.type = 'split'
                     AND child_o.status NOT IN ('cancelled', 'refunded')
                     AND child_oi.object_id = %d",
                    $order_id,
                    $object_id
                ));
                $total_item_allocated = $actual_child_allocated + $quantity;
                if ($total_item_allocated > (int) $db_item['quantity']) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('INVALID_ALLOCATION', "訂單 #{$order_id} 的總分配數量 ({$total_item_allocated}) 超過需求數量 ({$db_item['quantity']})");
                }
            }

            $current_child_allocated = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(child_oi.quantity), 0)
                 FROM {$wpdb->prefix}fct_orders child_o
                 INNER JOIN {$wpdb->prefix}fct_order_items child_oi ON child_o.id = child_oi.order_id
                 WHERE child_o.type = 'split'
                 AND child_o.status NOT IN ('cancelled', 'refunded')
                 AND child_oi.object_id IN ($var_placeholders)",
                ...$variation_ids
            ));
            $new_allocation_total = array_sum(array_column($normalized, 'quantity'));
            $total_allocated = $current_child_allocated + $new_allocation_total;

            $this->debugService->log('AllocationService', '分配數量計算', [
                'current_child_allocated' => $current_child_allocated,
                'new_allocation_total' => $new_allocation_total,
                'total_allocated' => $total_allocated,
                'purchased' => $purchased,
            ]);

            if ($total_allocated > $purchased) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('INSUFFICIENT_STOCK', "總分配數量 ({$total_allocated}) 超過已採購數量 ({$purchased})");
            }

            $child_orders = [];
            foreach ($normalized as $norm) {
                $child_order = $this->createChildOrder($norm['order_id'], $norm['object_id'], $norm['quantity']);
                if (is_wp_error($child_order)) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('CHILD_ORDER_FAILED', "建立訂單 #{$norm['order_id']} 的子訂單失敗：" . $child_order->get_error_message());
                }
                $child_orders[] = $child_order;
            }

            $this->allocationService->syncAllocatedQtyBatch($db_items);
            $recalc_allocated = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(child_oi.quantity), 0)
                 FROM {$wpdb->prefix}fct_orders child_o
                 INNER JOIN {$wpdb->prefix}fct_order_items child_oi ON child_o.id = child_oi.order_id
                 WHERE child_o.type = 'split'
                 AND child_o.status NOT IN ('cancelled', 'refunded')
                 AND child_oi.object_id IN ($var_placeholders)",
                ...$variation_ids
            ));
            update_post_meta((int) $product->post_id, '_buygo_allocated', $recalc_allocated);
            $wpdb->query('COMMIT');

            return ['success' => true, 'child_orders' => $child_orders, 'total_allocated' => $recalc_allocated];
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            $this->debugService->log('AllocationService', '更新訂單分配數量失敗', ['product_id' => $product_id, 'error' => $e->getMessage()], 'error');
            return new WP_Error('ALLOCATION_UPDATE_FAILED', '更新分配數量失敗：' . $e->getMessage());
        }
    }

    public function cancelChildOrder(int $child_order_id): bool|\WP_Error
    {
        global $wpdb;

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT id, type, status, shipping_status FROM {$wpdb->prefix}fct_orders WHERE id = %d",
            $child_order_id
        ));
        if (!$order) {
            return new WP_Error('NOT_FOUND', '找不到子訂單');
        }
        if ('split' !== $order->type) {
            return new WP_Error('NOT_CHILD_ORDER', '此訂單不是子訂單');
        }
        if ('cancelled' === $order->status) {
            return new WP_Error('ALREADY_CANCELLED', '子訂單已取消');
        }
        if ('unshipped' !== $order->shipping_status) {
            return new WP_Error('CANNOT_CANCEL_SHIPPED', '只有未出貨的子訂單可以取消');
        }

        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}fct_orders SET status = 'cancelled' WHERE id = %d AND shipping_status = 'unshipped' AND status != 'cancelled'",
            $child_order_id
        ));
        if (false === $affected) {
            return new WP_Error('DB_ERROR', '資料庫操作失敗');
        }
        if (0 === $affected) {
            return new WP_Error('STATUS_CONFLICT', '子訂單狀態已變更，請重新整理後再試');
        }

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT id, line_meta FROM {$wpdb->prefix}fct_order_items WHERE order_id = %d",
            $child_order_id
        ));
        foreach ($items as $item) {
            $meta = json_decode($item->line_meta, true) ?: [];
            $meta['_allocated_qty'] = 0;
            $wpdb->update($wpdb->prefix . 'fct_order_items', ['line_meta' => wp_json_encode($meta)], ['id' => $item->id]);
        }

        return true;
    }

    public function syncAllocatedQtyBatch(array $items): void
    {
        $this->batchService->syncAllocatedQtyBatch($items);
    }

    public function allocateAllForCustomer(int $product_id, int $order_item_id, int $customer_id)
    {
        return $this->batchService->allocateAllForCustomer($product_id, $order_item_id, $customer_id);
    }

    private function getTotalPurchased(int $post_id, array $variation_ids): int
    {
        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($variation_ids), '%d'));
        $total_from_meta = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(CAST(meta_value AS UNSIGNED)), 0)
             FROM {$wpdb->prefix}fct_meta
             WHERE object_type = 'variation' AND object_id IN ($placeholders) AND meta_key = '_buygo_purchased'",
            ...$variation_ids
        ));

        return $total_from_meta > 0 ? $total_from_meta : (int) get_post_meta($post_id, '_buygo_purchased', true);
    }

    private function createChildOrder(int $parent_order_id, int $variation_id, int $quantity)
    {
        global $wpdb;

        try {
            $parent_order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}fct_orders WHERE id = %d", $parent_order_id));
            if (!$parent_order) {
                return new WP_Error('PARENT_ORDER_NOT_FOUND', '父訂單不存在');
            }
            $parent_item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fct_order_items WHERE order_id = %d AND object_id = %d",
                $parent_order_id,
                $variation_id
            ));
            if (!$parent_item) {
                return new WP_Error('PARENT_ITEM_NOT_FOUND', '父訂單中找不到此商品項目');
            }
            $unit_price = (float) $parent_item->unit_price;
            $child_total_cents = $unit_price * $quantity;
            $split_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders WHERE parent_id = %d AND type = 'split'",
                $parent_order_id
            )) + 1;
            $child_invoice_no = (!empty($parent_order->invoice_no) ? $parent_order->invoice_no : "#{$parent_order_id}") . '-' . $split_count;
            $result = $wpdb->insert($wpdb->prefix . 'fct_orders', [
                'parent_id' => $parent_order_id, 'type' => 'split', 'customer_id' => $parent_order->customer_id, 'status' => 'pending',
                'payment_status' => $parent_order->payment_status ?? 'pending', 'shipping_status' => 'unshipped', 'subtotal' => $child_total_cents,
                'total_amount' => $child_total_cents, 'currency' => $parent_order->currency, 'payment_method' => $parent_order->payment_method,
                'payment_method_title' => $parent_order->payment_method_title ?? '', 'invoice_no' => $child_invoice_no,
                'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql'),
            ], ['%d', '%s', '%d', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s']);
            if ($result === false || $wpdb->insert_id === 0) {
                return new WP_Error('DB_ERROR', '建立子訂單失敗：' . $wpdb->last_error);
            }
            $child_order_id = $wpdb->insert_id;
            $product_title = $parent_item->title ?? $parent_item->post_title ?? '';
            if (empty($product_title)) {
                $product_title = get_the_title($parent_item->post_id) ?: '';
            }
            $wpdb->insert($wpdb->prefix . 'fct_order_items', [
                'order_id' => $child_order_id, 'post_id' => $parent_item->post_id, 'object_id' => (int) $parent_item->object_id, 'quantity' => $quantity,
                'unit_price' => $unit_price, 'subtotal' => $child_total_cents, 'line_total' => $child_total_cents, 'title' => $product_title,
                'post_title' => $product_title, 'line_meta' => json_encode(['_allocated_qty' => $quantity, '_shipped_qty' => 0]),
                'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql'),
            ], ['%d', '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s']);
            if ($wpdb->insert_id === 0) {
                $wpdb->delete($wpdb->prefix . 'fct_orders', ['id' => $child_order_id], ['%d']);
                return new WP_Error('DB_ERROR', '建立子訂單項目失敗：' . $wpdb->last_error);
            }
            do_action('buygo/child_order_created', $child_order_id, $parent_order_id);
            $this->copyParentAddressesToChild($parent_order_id, $child_order_id);
            return ['id' => $child_order_id, 'invoice_no' => $child_invoice_no, 'parent_id' => $parent_order_id, 'quantity' => $quantity, 'total_amount' => $child_total_cents];
        } catch (\Exception $e) {
            return new WP_Error('CHILD_ORDER_CREATION_FAILED', '建立子訂單失敗：' . $e->getMessage());
        }
    }

    private function copyParentAddressesToChild(int $parent_order_id, int $child_order_id): void
    {
        global $wpdb;

        $parent_addresses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fct_order_addresses WHERE order_id = %d",
            $parent_order_id
        ), ARRAY_A);
        foreach ($parent_addresses as $address) {
            unset($address['id']);
            $address['order_id'] = $child_order_id;
            $address['created_at'] = current_time('mysql');
            $address['updated_at'] = current_time('mysql');
            $wpdb->insert($wpdb->prefix . 'fct_order_addresses', $address);
        }
    }
}
