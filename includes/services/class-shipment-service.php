<?php

namespace BuyGoPlus\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shipment Service - 出貨單管理服務
 * 
 * 處理出貨單的建立、查詢、合併等功能
 * 
 * @package BuyGoPlus\Services
 * @version 1.0.0
 */
class ShipmentService
{
    private $debugService;

    public function __construct()
    {
        $this->debugService = new DebugService();
    }

    /**
     * 生成出貨單號
     * 格式：SH-YYYYMMDD-XXX
     * 
     * @return string
     */
    public function generate_shipment_number()
    {
        global $wpdb;
        
        $date_prefix = date('Ymd');
        $table_name = $wpdb->prefix . 'buygo_shipments';
        
        // 查詢當日最大的序號
        $max_number = $wpdb->get_var($wpdb->prepare(
            "SELECT shipment_number 
             FROM {$table_name} 
             WHERE shipment_number LIKE %s 
             ORDER BY shipment_number DESC 
             LIMIT 1",
            "SH-{$date_prefix}-%"
        ));
        
        $sequence = 1;
        if ($max_number) {
            // 提取序號部分（最後三位數字）
            if (preg_match('/SH-\d{8}-(\d{3})$/', $max_number, $matches)) {
                $sequence = intval($matches[1]) + 1;
            }
        }
        
        // 格式化為三位數（001, 002, ...）
        $sequence_str = str_pad($sequence, 3, '0', STR_PAD_LEFT);
        
        return "SH-{$date_prefix}-{$sequence_str}";
    }

    /**
     * 建立出貨單
     * 
     * @param int $customer_id
     * @param int $seller_id
     * @param array $items 出貨單明細
     * @return int|WP_Error 出貨單 ID 或錯誤
     */
    public function create_shipment($customer_id, $seller_id, $items = [])
    {
        global $wpdb;
        
        if (empty($items)) {
            return new WP_Error('NO_ITEMS', '出貨單必須至少包含一個商品');
        }
        
        $shipment_number = $this->generate_shipment_number();
        
        // 檢查出貨單號是否唯一
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}buygo_shipments WHERE shipment_number = %s",
            $shipment_number
        ));
        
        if ($exists > 0) {
            // 如果重複，重新生成
            $shipment_number = $this->generate_shipment_number();
        }
        
        // 建立出貨單
        $result = $wpdb->insert(
            $wpdb->prefix . 'buygo_shipments',
            [
                'shipment_number' => $shipment_number,
                'customer_id' => $customer_id,
                'seller_id' => $seller_id,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%s', '%d', '%d', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            $this->debugService->log('ShipmentService', '建立出貨單失敗', [
                'error' => $wpdb->last_error,
                'customer_id' => $customer_id,
                'seller_id' => $seller_id
            ], 'error');
            return new WP_Error('DB_ERROR', '建立出貨單失敗：' . $wpdb->last_error);
        }
        
        $shipment_id = $wpdb->insert_id;
        
        // 建立出貨單明細
        foreach ($items as $item) {
            $item_result = $wpdb->insert(
                $wpdb->prefix . 'buygo_shipment_items',
                [
                    'shipment_id' => $shipment_id,
                    'order_id' => $item['order_id'],
                    'order_item_id' => $item['order_item_id'],
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%d', '%d', '%d', '%d', '%s']
            );
            
            if ($item_result === false) {
                // 如果明細建立失敗，刪除出貨單
                $wpdb->delete($wpdb->prefix . 'buygo_shipments', ['id' => $shipment_id], ['%d']);
                $this->debugService->log('ShipmentService', '建立出貨單明細失敗', [
                    'error' => $wpdb->last_error,
                    'shipment_id' => $shipment_id,
                    'item' => $item
                ], 'error');
                return new WP_Error('DB_ERROR', '建立出貨單明細失敗：' . $wpdb->last_error);
            }
        }
        
        $this->debugService->log('ShipmentService', '出貨單建立成功', [
            'shipment_id' => $shipment_id,
            'shipment_number' => $shipment_number,
            'items_count' => count($items)
        ]);
        
        return $shipment_id;
    }

    /**
     * 取得出貨單
     * 
     * @param int $shipment_id
     * @return object|null
     */
    public function get_shipment($shipment_id)
    {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}buygo_shipments WHERE id = %d",
            $shipment_id
        ));
    }

    /**
     * 取得出貨單明細
     * 
     * @param int $shipment_id
     * @return array
     */
    public function get_shipment_items($shipment_id)
    {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}buygo_shipment_items WHERE shipment_id = %d",
            $shipment_id
        ), ARRAY_A);
    }

    /**
     * 檢查出貨單是否可以合併
     * 
     * @param array $shipment_ids
     * @return WP_Error|true
     */
    public function validate_merge($shipment_ids)
    {
        global $wpdb;
        
        if (empty($shipment_ids) || count($shipment_ids) < 2) {
            return new WP_Error('INVALID_INPUT', '至少需要兩個出貨單才能合併');
        }
        
        $placeholders = implode(',', array_fill(0, count($shipment_ids), '%d'));
        $shipments = $wpdb->get_results($wpdb->prepare(
            "SELECT id, customer_id, status, shipment_number 
             FROM {$wpdb->prefix}buygo_shipments 
             WHERE id IN ($placeholders)",
            ...$shipment_ids
        ));
        
        if (count($shipments) !== count($shipment_ids)) {
            return new WP_Error('SHIPMENT_NOT_FOUND', '部分出貨單不存在');
        }
        
        // 檢查是否屬於同一買家
        $customer_ids = array_unique(array_column($shipments, 'customer_id'));
        if (count($customer_ids) > 1) {
            return new WP_Error('DIFFERENT_CUSTOMERS', '只能合併同一買家的出貨單');
        }
        
        // 檢查狀態是否為 pending
        foreach ($shipments as $shipment) {
            if ($shipment->status !== 'pending') {
                return new WP_Error('SHIPMENT_ALREADY_SHIPPED', "無法合併已出貨的出貨單：{$shipment->shipment_number}");
            }
        }
        
        return true;
    }

    /**
     * 合併出貨單
     * 
     * @param array $shipment_ids
     * @return int|WP_Error 新出貨單 ID 或錯誤
     */
    public function merge_shipments($shipment_ids)
    {
        global $wpdb;
        
        $validation = $this->validate_merge($shipment_ids);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // 取得第一個出貨單的資訊
        $first_shipment = $this->get_shipment($shipment_ids[0]);
        if (!$first_shipment) {
            return new WP_Error('SHIPMENT_NOT_FOUND', '找不到出貨單');
        }
        
        // 收集所有出貨單明細
        $all_items = [];
        foreach ($shipment_ids as $shipment_id) {
            $items = $this->get_shipment_items($shipment_id);
            $all_items = array_merge($all_items, $items);
        }
        
        // 建立新的合併出貨單
        $new_shipment_id = $this->create_shipment(
            $first_shipment->customer_id,
            $first_shipment->seller_id,
            $all_items
        );
        
        if (is_wp_error($new_shipment_id)) {
            return $new_shipment_id;
        }
        
        // 刪除舊的出貨單（會自動刪除明細，因為沒有外鍵約束，需要手動刪除）
        foreach ($shipment_ids as $shipment_id) {
            // 先刪除明細
            $wpdb->delete($wpdb->prefix . 'buygo_shipment_items', ['shipment_id' => $shipment_id], ['%d']);
            // 再刪除出貨單
            $wpdb->delete($wpdb->prefix . 'buygo_shipments', ['id' => $shipment_id], ['%d']);
        }
        
        $this->debugService->log('ShipmentService', '出貨單合併成功', [
            'new_shipment_id' => $new_shipment_id,
            'merged_shipment_ids' => $shipment_ids
        ]);
        
        return $new_shipment_id;
    }

    /**
     * 標記出貨單為已出貨
     * 
     * @param array $shipment_ids
     * @return int|WP_Error 成功標記的數量或錯誤
     */
    public function mark_shipped($shipment_ids)
    {
        global $wpdb;
        
        if (empty($shipment_ids)) {
            return new WP_Error('INVALID_INPUT', '請選擇要標記的出貨單');
        }
        
        $shipped_count = 0;
        $errors = [];
        
        foreach ($shipment_ids as $shipment_id) {
            $shipment = $this->get_shipment($shipment_id);
            
            if (!$shipment) {
                $errors[] = "出貨單 #{$shipment_id} 不存在";
                continue;
            }
            
            if ($shipment->status === 'shipped' || $shipment->status === 'delivered') {
                $errors[] = "出貨單 {$shipment->shipment_number} 已經出貨";
                continue;
            }
            
            // 更新出貨單狀態
            $result = $wpdb->update(
                $wpdb->prefix . 'buygo_shipments',
                [
                    'status' => 'shipped',
                    'shipped_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $shipment_id],
                ['%s', '%s', '%s'],
                ['%d']
            );
            
            if ($result !== false) {
                $shipped_count++;

                // 【新增】自動檢查並完成父訂單
                $this->check_parent_completion($shipment_id);
            } else {
                $errors[] = "更新出貨單 #{$shipment_id} 失敗";
            }
        }

        if ($shipped_count === 0 && !empty($errors)) {
            return new WP_Error('MARK_SHIPPED_FAILED', implode('; ', $errors));
        }

        return $shipped_count;
    }

    /**
     * 檢查並自動完成父訂單
     *
     * 當出貨單標記為已出貨時，檢查該出貨單包含的所有子訂單
     * 如果子訂單的父訂單所有子訂單都已出貨，則自動將父訂單標記為完成
     *
     * @param int $shipment_id 出貨單 ID
     * @return void
     */
    private function check_parent_completion($shipment_id)
    {
        global $wpdb;

        $this->debugService->log('ShipmentService', '檢查父訂單完成狀態', [
            'shipment_id' => $shipment_id
        ]);

        try {
            // 1. 獲取此出貨單的所有訂單
            $order_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT order_id
                 FROM {$wpdb->prefix}buygo_shipment_items
                 WHERE shipment_id = %d",
                $shipment_id
            ));

            if (empty($order_ids)) {
                $this->debugService->log('ShipmentService', '出貨單沒有訂單項目', [
                    'shipment_id' => $shipment_id
                ]);
                return;
            }

            // 2. 更新每個訂單為 shipped
            foreach ($order_ids as $order_id) {
                $wpdb->update(
                    $wpdb->prefix . 'fct_orders',
                    ['status' => 'shipped'],
                    ['id' => $order_id],
                    ['%s'],
                    ['%d']
                );

                $this->debugService->log('ShipmentService', '更新訂單狀態為 shipped', [
                    'order_id' => $order_id
                ]);

                // 3. 檢查是否為子訂單（type = 'split'）
                $order = $wpdb->get_row($wpdb->prepare(
                    "SELECT parent_id, type FROM {$wpdb->prefix}fct_orders WHERE id = %d",
                    $order_id
                ));

                if ($order && $order->parent_id && $order->type === 'split') {
                    $parent_id = $order->parent_id;

                    // 4. 檢查父訂單是否所有子訂單都已出貨
                    $pending_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$wpdb->prefix}fct_orders
                         WHERE parent_id = %d
                         AND type = 'split'
                         AND status != 'shipped'",
                        $parent_id
                    ));

                    $this->debugService->log('ShipmentService', '檢查父訂單子訂單狀態', [
                        'parent_id' => $parent_id,
                        'pending_count' => $pending_count
                    ]);

                    // 5. 如果所有子訂單都已出貨，自動完成父訂單
                    if ($pending_count == 0) {
                        $wpdb->update(
                            $wpdb->prefix . 'fct_orders',
                            ['status' => 'completed'],
                            ['id' => $parent_id],
                            ['%s'],
                            ['%d']
                        );

                        // 觸發 Hook
                        do_action('buygo/parent_order_completed', $parent_id);

                        $this->debugService->log('ShipmentService', '父訂單自動完成', [
                            'parent_id' => $parent_id,
                            'reason' => '所有子訂單都已出貨'
                        ]);
                    }
                }
            }

        } catch (\Exception $e) {
            $this->debugService->log('ShipmentService', '檢查父訂單完成狀態失敗', [
                'shipment_id' => $shipment_id,
                'error' => $e->getMessage()
            ], 'error');
        }
    }
}
