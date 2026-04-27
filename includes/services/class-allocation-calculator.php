<?php

namespace BuyGoPlus\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Allocation Calculator - 庫存分配計算服務
 *
 * 負責純計算與調整邏輯：驗證分配調整請求，以及執行分配數量更新。
 * 從 AllocationService 拆分，不含查詢與寫入訂單的其他職責。
 *
 * @package BuyGoPlus\Services
 */
class AllocationCalculator
{
    private $debugService;
    private $allocationService;

    public function __construct(AllocationService $allocationService)
    {
        $this->debugService      = DebugService::get_instance();
        $this->allocationService = $allocationService;
    }

    /**
     * 驗證調整分配數量的合法性
     *
     * @param int $product_id
     * @param int $order_id
     * @param int $new_quantity
     * @param int $current_allocated
     * @param int $shipped_qty
     * @param int $order_quantity
     * @return true|WP_Error
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

        $this->debugService->log('AllocationCalculator', '開始調整分配數量', [
            'product_id'   => $product_id,
            'order_id'     => $order_id,
            'new_quantity' => $new_quantity,
        ]);

        // 1. 取得同一商品的所有 variation IDs（多樣式商品支援）
        $varInfo       = $this->allocationService->getAllVariationIds($product_id);
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

                $this->debugService->log('AllocationCalculator', '全撤子訂單', [
                    'child_order_id' => $child_order_id,
                    'order_id'       => $order_id,
                ]);
            } else {
                // 6b. 減少數量：更新子訂單項目的 quantity 和 line_meta._allocated_qty
                $unit_price                   = (float) $child_item->unit_price;
                $new_subtotal                 = $unit_price * $new_quantity;
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

                $this->debugService->log('AllocationCalculator', '減少分配數量', [
                    'child_order_id'   => $child_order_id,
                    'current_quantity' => $current_allocated,
                    'new_quantity'     => $new_quantity,
                ]);
            }

            // 7. 同步父訂單項目的 _allocated_qty（從子訂單重算）
            if ($parent_item_id > 0) {
                $recalc_parent_allocated = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(child_oi.quantity), 0)
                     FROM {$wpdb->prefix}fct_orders child_o
                     INNER JOIN {$wpdb->prefix}fct_order_items child_oi ON child_o.id = child_oi.order_id
                     WHERE child_o.parent_id = %d
                     AND child_o.type = 'split'
                     AND child_o.status NOT IN ('cancelled', 'refunded')
                     AND child_oi.object_id IN ($var_placeholders)",
                    array_merge([$order_id], $variation_ids)
                ));

                $parent_meta                   = json_decode($parent_item[0]['line_meta'] ?? '{}', true) ?: [];
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
                 AND child_o.status NOT IN ('cancelled', 'refunded')
                 AND child_oi.object_id IN ($var_placeholders)",
                ...$variation_ids
            ));

            update_post_meta($post_id, '_buygo_allocated', $recalc_allocated);

            $wpdb->query('COMMIT');

            $this->debugService->log('AllocationCalculator', '調整分配數量成功', [
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
            $this->debugService->log('AllocationCalculator', '調整分配數量失敗', [
                'product_id' => $product_id,
                'order_id'   => $order_id,
                'error'      => $e->getMessage(),
            ], 'error');
            return new WP_Error('ADJUSTMENT_FAILED', '調整分配數量失敗：' . $e->getMessage());
        }
    }
}
