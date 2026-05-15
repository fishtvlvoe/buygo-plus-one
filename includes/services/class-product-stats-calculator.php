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

    /**
     * 計算「待分配」數量 — 列表端點與單品端點共用此公式。
     *
     * reserved = max(0, ordered - purchased - allocated)
     *
     * @param int $ordered   下單量
     * @param int $purchased 已採購量
     * @param int $allocated 已分配量
     * @return int 待分配量（不低於 0）
     */
    public static function reserved(int $ordered, int $purchased, int $allocated): int
    {
        return max(0, $ordered - $purchased - $allocated);
    }

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
                AND o.status NOT IN ('cancelled', 'canceled', 'refunded')
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
     * 計算每個父訂單在各商品變體的已分配子訂單數量
     *
     * 供 ProductBuyerQueryService::buildBuyerOrderEntry() 使用，
     * 取代讀取 parent order_items.line_meta._allocated_qty 的舊方式。
     *
     * @param array $parentOrderIds 父訂單 ID 陣列
     * @param array $variationIds   商品變體 ID 陣列
     * @return array [parentOrderId => [variationId => int]]，未出現的組合視為 0
     */
    public function calculateAllocatedPerParentOrder(array $parentOrderIds, array $variationIds): array
    {
        // 空輸入直接回 []，不打 SQL
        if (empty($parentOrderIds) || empty($variationIds)) {
            return [];
        }

        try {
            global $wpdb;

            $table_items  = $wpdb->prefix . 'fct_order_items';
            $table_orders = $wpdb->prefix . 'fct_orders';

            $parentPlaceholders    = implode(',', array_fill(0, count($parentOrderIds), '%d'));
            $variationPlaceholders = implode(',', array_fill(0, count($variationIds), '%d'));

            // SQL：以父訂單 + 商品變體分組，加總子訂單數量
            // 子訂單特徵：parent_id IS NOT NULL AND type = 'split'
            // 排除已取消 / 退款的子訂單
            $sql = $wpdb->prepare("
                SELECT
                    parent.id AS parent_id,
                    child_item.object_id AS variation_id,
                    SUM(child_item.quantity) AS allocated_qty
                FROM {$table_items} child_item
                INNER JOIN {$table_orders} child  ON child.id  = child_item.order_id
                INNER JOIN {$table_orders} parent ON parent.id = child.parent_id
                WHERE parent.id IN ({$parentPlaceholders})
                  AND child_item.object_id IN ({$variationPlaceholders})
                  AND child.parent_id IS NOT NULL
                  AND child.type = 'split'
                  AND child.status NOT IN ('cancelled', 'refunded')
                GROUP BY parent.id, child_item.object_id
            ", ...[...$parentOrderIds, ...$variationIds]);

            $results = $wpdb->get_results($sql, ARRAY_A);

            // 組 nested map：[parentOrderId][variationId] => int
            $map = [];
            foreach ($results as $row) {
                $pid = (int) $row['parent_id'];
                $vid = (int) $row['variation_id'];
                $map[$pid][$vid] = (int) $row['allocated_qty'];
            }

            return $map;

        } catch (\Exception $e) {
            $this->debugService->log('ProductStatsCalculator', '計算每父訂單已分配子訂單數量失敗', [
                'error'            => $e->getMessage(),
                'parent_order_ids' => $parentOrderIds,
                'variation_ids'    => $variationIds,
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
                AND o.status NOT IN ('cancelled', 'canceled', 'refunded')
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
