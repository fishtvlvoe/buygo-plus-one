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
            // 變更：使用 i18n 字串與穩定錯誤碼，避免 API 依賴訊息內容（WPCS）。
            throw new \RuntimeException( __( 'Order not found', 'buygo-plus-one' ), 4041 );
        }

        if ( in_array( $order->status, [ 'completed', 'cancelled' ], true ) ) {
            // 變更：使用 i18n 字串（WPCS）。
            throw new \RuntimeException( __( 'Order is completed and cannot be modified', 'buygo-plus-one' ), 4221 );
        }

        // Guard 2: 確認 item 屬於此訂單
        $item_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$items_table} WHERE id = %d AND order_id = %d",
                $item_id,
                $order_id
            )
        );

        if ( 0 === $item_count ) {
            // 變更：使用 i18n 字串與穩定錯誤碼（WPCS）。
            throw new \RuntimeException( __( 'Order item not found', 'buygo-plus-one' ), 4042 );
        }

        // 執行刪除
        // 變更：補上 formats 並檢查 false 回傳，避免靜默 DB 失敗（WPCS）。
        $deleted = $wpdb->delete( $items_table, [ 'id' => $item_id ], [ '%d' ] );
        if ( false === $deleted ) {
            $db_error = (string) $wpdb->last_error;
            $message  = __( 'Database error occurred while removing the order item.', 'buygo-plus-one' );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && '' !== $db_error ) {
                $message = sprintf( __( 'Database error: %s', 'buygo-plus-one' ), $db_error );
            }

            throw new \RuntimeException( $message, 5001 );
        }

        // 重新計算訂單總金額
        $new_total = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(line_total), 0) FROM {$items_table} WHERE order_id = %d",
                $order_id
            )
        );

        // 變更：補上 formats 並檢查 false 回傳，避免靜默 DB 失敗（WPCS）。
        $updated = $wpdb->update(
            $orders_table,
            [
                'subtotal' => $new_total,
                'total'    => $new_total,
            ],
            [ 'id' => $order_id ],
            [ '%f', '%f' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            $db_error = (string) $wpdb->last_error;
            $message  = __( 'Database error occurred while updating the order totals.', 'buygo-plus-one' );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && '' !== $db_error ) {
                $message = sprintf( __( 'Database error: %s', 'buygo-plus-one' ), $db_error );
            }

            throw new \RuntimeException( $message, 5002 );
        }

        return true;
    }
}
