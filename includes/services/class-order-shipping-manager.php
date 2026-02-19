<?php

namespace BuyGoPlus\Services;

defined('ABSPATH') || exit;

use FluentCart\App\Models\Order;

/**
 * Order Shipping Manager - 訂單運送狀態管理服務
 *
 * 從 OrderService 抽出，負責：
 * - 更新訂單運送狀態（含驗證和事件觸發）
 * - 同步父訂單的 shipping_status（子訂單狀態變更時）
 * - 計算出貨進度
 *
 * @package BuyGoPlus\Services
 * @since 2.1.0
 */
class OrderShippingManager
{
    private $debugService;
    private $shippingStatusService;

    public function __construct(DebugService $debugService, ShippingStatusService $shippingStatusService)
    {
        $this->debugService = $debugService;
        $this->shippingStatusService = $shippingStatusService;
    }

    /**
     * 更新運送狀態（使用 ShippingStatusService）
     *
     * @param string $orderId 訂單 ID
     * @param string $status 新狀態
     * @param string $reason 變更原因
     * @return bool
     */
    public function updateShippingStatus(string $orderId, string $status, string $reason = ''): bool
    {
        $this->debugService->log('OrderShippingManager', '開始更新運送狀態', [
            'order_id' => $orderId,
            'new_status' => $status,
            'reason' => $reason
        ]);

        try {
            // 驗證狀態有效性
            if (!$this->shippingStatusService->isValidStatus($status)) {
                throw new \Exception("無效的運送狀態：{$status}");
            }

            $order = Order::find($orderId);
            if (!$order) {
                throw new \Exception("訂單不存在：ID {$orderId}");
            }

            $oldStatus = $order->shipping_status ?? 'pending';

            // 檢查異常狀態變更
            if ($this->shippingStatusService->isAbnormalStatusChange($oldStatus, $status)) {
                $this->debugService->log('OrderShippingManager', '異常狀態變更警告', [
                    'order_id' => $orderId,
                    'old_status' => $oldStatus,
                    'new_status' => $status
                ], 'warning');
            }

            // 更新狀態
            $order->shipping_status = $status;
            if ($status === 'completed' && !$order->completed_at) {
                $order->completed_at = current_time('mysql');
            }
            $order->save();

            // 記錄狀態變更歷史
            $this->shippingStatusService->logStatusChange($orderId, $oldStatus, $status, $reason);

            $this->debugService->log('OrderShippingManager', '運送狀態更新成功', [
                'order_id' => $orderId,
                'old_status' => $oldStatus,
                'new_status' => $status
            ]);

            // 觸發狀態變更通知
            \do_action('buygo_shipping_status_changed', $orderId, $oldStatus, $status);

            // 特定狀態事件
            if ($status === 'shipped') {
                \do_action('buygo_order_shipped', $orderId);
            } elseif ($status === 'completed') {
                \do_action('buygo_order_completed', $orderId);
            } elseif ($status === 'out_of_stock') {
                \do_action('buygo_order_out_of_stock', $orderId);
            }

            // 如果是子訂單，同步更新父訂單的 shipping_status
            if (!empty($order->parent_id)) {
                $this->syncParentShippingStatus($order->parent_id);
            }

            return true;

        } catch (\Exception $e) {
            $this->debugService->log('OrderShippingManager', '運送狀態更新失敗', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'status' => $status
            ], 'error');

            throw new \Exception('運送狀態更新失敗：' . $e->getMessage());
        }
    }

    /**
     * 同步父訂單的 shipping_status
     *
     * 根據所有子訂單的狀態，計算父訂單應該顯示的狀態。
     *
     * 邏輯：
     * - 所有子訂單都 completed → 父 completed
     * - 所有子訂單都 shipped 或 completed → 父 shipped
     * - 所有子訂單都至少 processing → 父 processing
     * - 有任何子訂單 preparing 以上 → 父 preparing
     * - 其餘 → 父 unshipped
     *
     * @param int $parentId 父訂單 ID
     */
    private function syncParentShippingStatus(int $parentId): void
    {
        try {
            $parentOrder = Order::find($parentId);
            if (!$parentOrder) {
                return;
            }

            $childOrders = Order::where('parent_id', $parentId)->get();

            if ($childOrders->isEmpty()) {
                return;
            }

            // 統計子訂單狀態
            $statusCounts = [
                'unshipped' => 0,
                'preparing' => 0,
                'processing' => 0,
                'shipped' => 0,
                'completed' => 0,
                'out_of_stock' => 0
            ];

            foreach ($childOrders as $child) {
                $status = $child->shipping_status ?? 'unshipped';
                if (isset($statusCounts[$status])) {
                    $statusCounts[$status]++;
                }
            }

            $totalChildren = count($childOrders);
            $newParentStatus = 'unshipped';

            if ($statusCounts['completed'] === $totalChildren) {
                $newParentStatus = 'completed';
            } elseif (($statusCounts['shipped'] + $statusCounts['completed']) === $totalChildren) {
                $newParentStatus = 'shipped';
            } elseif (($statusCounts['processing'] + $statusCounts['shipped'] + $statusCounts['completed']) === $totalChildren) {
                $newParentStatus = 'processing';
            } elseif (($statusCounts['preparing'] + $statusCounts['processing'] + $statusCounts['shipped'] + $statusCounts['completed']) > 0) {
                $newParentStatus = 'preparing';
            }

            // 只有當父訂單狀態需要更新時才更新
            $currentParentStatus = $parentOrder->shipping_status ?? 'unshipped';
            if ($currentParentStatus !== $newParentStatus) {
                $parentOrder->shipping_status = $newParentStatus;
                $parentOrder->save();

                $this->debugService->log('OrderShippingManager', '同步父訂單 shipping_status', [
                    'parent_id' => $parentId,
                    'old_status' => $currentParentStatus,
                    'new_status' => $newParentStatus,
                    'child_status_counts' => $statusCounts
                ]);

                $this->shippingStatusService->logStatusChange(
                    (string)$parentId,
                    $currentParentStatus,
                    $newParentStatus,
                    '子訂單狀態同步'
                );
            }

        } catch (\Exception $e) {
            $this->debugService->log('OrderShippingManager', '同步父訂單狀態失敗', [
                'parent_id' => $parentId,
                'error' => $e->getMessage()
            ], 'error');
        }
    }

    /**
     * 計算出貨進度
     *
     * @param \Illuminate\Database\Eloquent\Collection $orderItems 訂單商品集合
     * @return array ['total_quantity', 'shipped_quantity']
     */
    public function calculateShipmentProgress($orderItems): array
    {
        global $wpdb;
        $table_shipment_items = $wpdb->prefix . 'buygo_shipment_items';

        $total_quantity = 0;
        $shipped_quantity = 0;

        foreach ($orderItems as $item) {
            $item_quantity = (int)($item->quantity ?? 0);
            $total_quantity += $item_quantity;

            $shipped = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(quantity)
                 FROM {$table_shipment_items}
                 WHERE order_item_id = %d",
                $item->id
            ));

            $shipped_quantity += (int)($shipped ?? 0);
        }

        return [
            'total_quantity' => $total_quantity,
            'shipped_quantity' => $shipped_quantity
        ];
    }
}
