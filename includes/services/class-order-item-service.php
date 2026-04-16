<?php

namespace BuyGoPlus\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Order Item Service — 訂單項目操作服務
 *
 * 負責訂單項目的新增、移除與金額重新計算。
 * 純 PHP 邏輯層，不依賴 WordPress hooks，便於單元測試。
 *
 * @package BuyGoPlus\Services
 * @since 2.5.0
 */
final class OrderItemService
{
    /**
     * 移除訂單項目並重新計算訂單總金額
     *
     * @param int $order_id 父訂單 ID
     * @param int $item_id  要移除的訂單項目 ID
     * @return true
     * @throws \Exception 訂單不存在、已完成／已取消、項目不屬於此訂單
     */
    public function removeItem( int $order_id, int $item_id ): bool
    {
        global $wpdb;

        $orders_table = $wpdb->prefix . 'fct_orders';
        $items_table  = $wpdb->prefix . 'fct_order_items';

        // Guard 1: 確認訂單存在且狀態可修改
        $order = $wpdb->get_row(
            $wpdb->prepare( "SELECT id, status FROM {$orders_table} WHERE id = %d", $order_id )
        );

        if ( ! $order ) {
            throw new \Exception( 'Order not found' );
        }

        if ( in_array( $order->status, [ 'completed', 'cancelled' ], true ) ) {
            throw new \Exception( 'Order is completed and cannot be modified' );
        }

        // Guard 2: 確認 item 屬於此訂單
        $item_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$items_table} WHERE id = %d AND order_id = %d",
                $item_id,
                $order_id
            )
        );

        if ( $item_count === 0 ) {
            throw new \Exception( 'Order item not found' );
        }

        // 執行刪除
        $wpdb->delete( $items_table, [ 'id' => $item_id ] );

        // 重新計算訂單總金額
        $new_total = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(line_total), 0) FROM {$items_table} WHERE order_id = %d",
                $order_id
            )
        );

        $wpdb->update(
            $orders_table,
            [
                'subtotal' => $new_total,
                'total'    => $new_total,
            ],
            [ 'id' => $order_id ]
        );

        return true;
    }
}
