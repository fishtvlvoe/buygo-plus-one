<?php

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Product Stats Calculator - 商品統計計算
 *
 * 從 ProductService 抽出的統計計算邏輯：
 * - 下單數量（父訂單）
 * - 已出貨數量
 * - 已分配到子訂單的數量
 *
 * @package BuyGoPlus\Services
 * @since 2.1.0
 */
class ProductStatsCalculator
{
    private $debugService;

    public function __construct(DebugService $debugService)
    {
        $this->debugService = $debugService;
    }

    /**
     * 計算商品的下單數量
     *
     * @param array $productVariationIds 商品變化 ID 陣列
     * @return array
     */
    public function calculateOrderCounts(array $productVariationIds): array
    {
        if (empty($productVariationIds)) {
            return [];
        }

        try {
            global $wpdb;

            $table_items = $wpdb->prefix . 'fct_order_items';
            $table_orders = $wpdb->prefix . 'fct_orders';

            $placeholders = implode(',', array_fill(0, count($productVariationIds), '%d'));

            $sql = $wpdb->prepare("
                SELECT oi.object_id as product_variation_id, SUM(oi.quantity) as order_count
                FROM {$table_items} oi
                INNER JOIN {$table_orders} o ON oi.order_id = o.id
                WHERE oi.object_id IN ({$placeholders})
                AND o.status NOT IN ('cancelled', 'refunded')
                AND o.parent_id IS NULL
                GROUP BY oi.object_id
            ", ...$productVariationIds);

            $results = $wpdb->get_results($sql, ARRAY_A);

            $orderCounts = [];
            foreach ($results as $result) {
                $orderCounts[$result['product_variation_id']] = (int)$result['order_count'];
            }

            return $orderCounts;

        } catch (\Exception $e) {
            $this->debugService->log('ProductStatsCalculator', '計算下單數量失敗', [
                'error' => $e->getMessage(),
                'product_variation_ids' => $productVariationIds
            ], 'error');

            return [];
        }
    }

    /**
     * 計算已出貨數量
     *
     * @param array $productVariationIds 商品變體 ID 陣列
     * @return array 商品 ID => 已出貨數量
     */
    public function calculateShippedCounts(array $productVariationIds): array
    {
        if (empty($productVariationIds)) {
            return [];
        }

        try {
            global $wpdb;

            $table_shipment_items = $wpdb->prefix . 'buygo_shipment_items';
            $table_shipments = $wpdb->prefix . 'buygo_shipments';
            $table_variations = $wpdb->prefix . 'fct_product_variations';

            $placeholders = implode(',', array_fill(0, count($productVariationIds), '%d'));

            // 【重要】buygo_shipment_items.product_id 存的是 WordPress post_id
            // 而傳入的 productVariationIds 是 FluentCart ProductVariation ID
            // 需要透過 fct_product_variations 表做轉換
            $sql = $wpdb->prepare("
                SELECT
                    pv.id as product_variation_id,
                    SUM(si.quantity) as shipped_count
                FROM {$table_shipment_items} si
                INNER JOIN {$table_shipments} s ON si.shipment_id = s.id
                INNER JOIN {$table_variations} pv ON si.product_id = pv.post_id
                WHERE pv.id IN ({$placeholders})
                AND s.status IN ('shipped', 'archived')
                GROUP BY pv.id
            ", ...$productVariationIds);

            $results = $wpdb->get_results($sql, ARRAY_A);

            $shippedCounts = [];
            foreach ($results as $result) {
                $shippedCounts[$result['product_variation_id']] = (int)$result['shipped_count'];
            }

            return $shippedCounts;

        } catch (\Exception $e) {
            $this->debugService->log('ProductStatsCalculator', '計算已出貨數量失敗', [
                'error' => $e->getMessage(),
                'product_variation_ids' => $productVariationIds
            ], 'error');

            return [];
        }
    }

    /**
     * 計算已分配到子訂單的數量
     *
     * 【重要】這個方法計算的是實際已建立子訂單的商品數量
     * 用於取代從 post_meta 讀取的 _buygo_allocated（因為 post_meta 可能不同步）
     *
     * @param array $productVariationIds 商品變體 ID 陣列
     * @return array 商品 ID => 已分配到子訂單的數量
     */
    public function calculateAllocatedToChildOrders(array $productVariationIds): array
    {
        if (empty($productVariationIds)) {
            return [];
        }

        try {
            global $wpdb;

            $table_items = $wpdb->prefix . 'fct_order_items';
            $table_orders = $wpdb->prefix . 'fct_orders';

            $placeholders = implode(',', array_fill(0, count($productVariationIds), '%d'));

            // 計算每個商品在子訂單中的總數量
            // 子訂單的特徵：parent_id IS NOT NULL 且 type = 'split'
            $sql = $wpdb->prepare("
                SELECT
                    oi.object_id as product_variation_id,
                    SUM(oi.quantity) as allocated_count
                FROM {$table_items} oi
                INNER JOIN {$table_orders} o ON oi.order_id = o.id
                WHERE oi.object_id IN ({$placeholders})
                AND o.parent_id IS NOT NULL
                AND o.type = 'split'
                AND o.status NOT IN ('cancelled', 'refunded')
                GROUP BY oi.object_id
            ", ...$productVariationIds);

            $results = $wpdb->get_results($sql, ARRAY_A);

            $allocatedCounts = [];
            foreach ($results as $result) {
                $allocatedCounts[$result['product_variation_id']] = (int)$result['allocated_count'];
            }

            return $allocatedCounts;

        } catch (\Exception $e) {
            $this->debugService->log('ProductStatsCalculator', '計算已分配到子訂單數量失敗', [
                'error' => $e->getMessage(),
                'product_variation_ids' => $productVariationIds
            ], 'error');

            return [];
        }
    }
}
