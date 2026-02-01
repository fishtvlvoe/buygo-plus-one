<?php

namespace BuyGoPlus\Services;

use FluentCart\App\Models\Order;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order Sync Service - 訂單同步服務
 *
 * 處理父訂單與子訂單之間的狀態同步
 *
 * @package BuyGoPlus\Services
 * @version 1.0.0
 */
class OrderSyncService
{
    private $debugService;

    public function __construct()
    {
        $this->debugService = DebugService::get_instance();
    }

    /**
     * 註冊 WordPress Hooks
     */
    public function register_hooks()
    {
        // 監聽 FluentCart 訂單更新事件
        add_action('fluentcart/order_updated', [$this, 'sync_order_status'], 10, 2);

        // 監聽自訂的訂單付款狀態變更事件
        add_action('buygo/order_payment_status_changed', [$this, 'sync_payment_status_to_children'], 10, 3);
    }

    /**
     * 同步訂單狀態（FluentCart 訂單更新時觸發）
     *
     * @param int $order_id 訂單 ID
     * @param Order $order 訂單物件
     */
    public function sync_order_status($order_id, $order)
    {
        // 只處理父訂單（有子訂單的訂單）
        if (!empty($order->parent_id)) {
            return; // 這是子訂單，不處理
        }

        $this->debugService->log('OrderSyncService', 'FluentCart 訂單更新', [
            'order_id' => $order_id,
            'payment_status' => $order->payment_status ?? '',
            'status' => $order->status ?? ''
        ]);

        // 檢查是否有子訂單
        $children = Order::where('parent_id', $order_id)->get();

        if ($children->isEmpty()) {
            return; // 沒有子訂單，不需要同步
        }

        // 同步付款狀態
        if (!empty($order->payment_status)) {
            $this->sync_payment_status_to_children($order_id, '', $order->payment_status);
        }

        // 同步訂單狀態
        if (!empty($order->status)) {
            $this->sync_order_status_to_children($order_id, $order->status);
        }
    }

    /**
     * 同步付款狀態到所有子訂單
     *
     * @param int $parent_order_id 父訂單 ID
     * @param string $old_status 舊狀態（可選）
     * @param string $new_status 新狀態
     */
    public function sync_payment_status_to_children($parent_order_id, $old_status = '', $new_status = '')
    {
        global $wpdb;

        $this->debugService->log('OrderSyncService', '開始同步付款狀態到子訂單', [
            'parent_order_id' => $parent_order_id,
            'old_status' => $old_status,
            'new_status' => $new_status
        ]);

        // 取得所有子訂單
        $child_orders = Order::where('parent_id', $parent_order_id)->get();

        if ($child_orders->isEmpty()) {
            $this->debugService->log('OrderSyncService', '沒有子訂單需要同步', [
                'parent_order_id' => $parent_order_id
            ]);
            return;
        }

        $updated_count = 0;
        foreach ($child_orders as $child) {
            // 只更新狀態不同的子訂單
            if ($child->payment_status !== $new_status) {
                $child->payment_status = $new_status;

                // 如果付款完成，也更新 paid_at 時間
                if ($new_status === 'paid' && empty($child->paid_at)) {
                    $child->paid_at = current_time('mysql');
                }

                $child->save();
                $updated_count++;

                $this->debugService->log('OrderSyncService', '子訂單付款狀態已更新', [
                    'child_order_id' => $child->id,
                    'invoice_no' => $child->invoice_no,
                    'old_status' => $child->payment_status,
                    'new_status' => $new_status
                ]);
            }
        }

        $this->debugService->log('OrderSyncService', '付款狀態同步完成', [
            'parent_order_id' => $parent_order_id,
            'total_children' => count($child_orders),
            'updated_count' => $updated_count,
            'new_status' => $new_status
        ]);
    }

    /**
     * 同步訂單狀態到所有子訂單
     *
     * @param int $parent_order_id 父訂單 ID
     * @param string $new_status 新狀態
     */
    public function sync_order_status_to_children($parent_order_id, $new_status)
    {
        $this->debugService->log('OrderSyncService', '開始同步訂單狀態到子訂單', [
            'parent_order_id' => $parent_order_id,
            'new_status' => $new_status
        ]);

        // 取得所有子訂單
        $child_orders = Order::where('parent_id', $parent_order_id)->get();

        if ($child_orders->isEmpty()) {
            return;
        }

        $updated_count = 0;
        foreach ($child_orders as $child) {
            // 某些狀態不應該同步（例如已取消、已退款的子訂單）
            if (in_array($child->status, ['cancelled', 'refunded'])) {
                continue;
            }

            if ($child->status !== $new_status) {
                $child->status = $new_status;
                $child->save();
                $updated_count++;
            }
        }

        $this->debugService->log('OrderSyncService', '訂單狀態同步完成', [
            'parent_order_id' => $parent_order_id,
            'total_children' => count($child_orders),
            'updated_count' => $updated_count,
            'new_status' => $new_status
        ]);
    }

    /**
     * 手動觸發付款狀態同步（給 API 或其他服務使用）
     *
     * @param int $parent_order_id 父訂單 ID
     * @param string $payment_status 付款狀態
     * @return bool
     */
    public function update_payment_status($parent_order_id, $payment_status)
    {
        try {
            // 更新父訂單
            $parent_order = Order::find($parent_order_id);
            if (!$parent_order) {
                return false;
            }

            $old_status = $parent_order->payment_status;
            $parent_order->payment_status = $payment_status;

            if ($payment_status === 'paid' && empty($parent_order->paid_at)) {
                $parent_order->paid_at = current_time('mysql');
            }

            $parent_order->save();

            // 觸發同步到子訂單
            $this->sync_payment_status_to_children($parent_order_id, $old_status, $payment_status);

            // 觸發 WordPress action
            do_action('buygo/order_payment_status_changed', $parent_order_id, $old_status, $payment_status);

            return true;

        } catch (\Exception $e) {
            $this->debugService->log('OrderSyncService', '更新付款狀態失敗', [
                'order_id' => $parent_order_id,
                'error' => $e->getMessage()
            ], 'error');

            return false;
        }
    }
}
