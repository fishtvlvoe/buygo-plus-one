<?php

namespace BuyGoPlus\Services;

/**
 * Product Variation Service - 商品 Variation 管理服務
 *
 * 負責 Variation 的查詢、刪除、統計與 meta 資料讀寫。
 * 從 ProductService 拆分（task 2.2），ProductService facade 將在 task 2.4 委派至此。
 *
 * @package BuyGoPlus\Services
 */
class ProductVariationService
{
    private $debugService;
    private $statsCalculator;

    public function __construct()
    {
        $this->debugService = DebugService::get_instance();
        $this->statsCalculator = new ProductStatsCalculator($this->debugService);
    }

    /**
     * 取得商品的所有 Variation 列表
     *
     * @param int $productId FluentCart post_id
     * @return array
     */
    public function getVariations(int $productId): array
    {
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'fct_product_variations';

            $variations = $wpdb->get_results($wpdb->prepare(
                "SELECT id, variation_title, item_price, total_stock, available, stock_status, media_id
                 FROM {$table}
                 WHERE post_id = %d AND item_status = 'active'
                 ORDER BY id ASC",
                $productId
            ), ARRAY_A);

            // 轉換價格單位（分 → 元）並取得圖片 URL
            foreach ($variations as &$v) {
                $v['price'] = $v['item_price'] / 100;
                $v['available'] = (int) ($v['available'] ?? 0);
                unset($v['item_price']);

                // 取得 variation 的圖片 URL
                $v['image'] = null;
                if (!empty($v['media_id'])) {
                    $imageUrl = wp_get_attachment_image_url((int)$v['media_id'], 'medium');
                    $v['image'] = $imageUrl ?: null;
                }
                unset($v['media_id']); // 移除 media_id，只保留 image URL
            }

            return $variations;

        } catch (\Exception $e) {
            $this->debugService->log('ProductVariationService', '取得 Variations 失敗', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ], 'error');

            return [];
        }
    }

    /**
     * 刪除 Variation 對應的 WordPress Post（移至回收桶）
     *
     * 若同一個 post_id 下還有其他 active variation，不執行刪除。
     *
     * @param int $variationId FluentCart variation ID
     * @return bool
     */
    public function deleteProductPost(int $variationId): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'fct_product_variations';
        $post_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$table} WHERE id = %d LIMIT 1",
            $variationId
        ));
        if (!$post_id) {
            return false;
        }
        $active_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE post_id = %d AND item_status = 'active' AND id != %d",
            $post_id,
            $variationId
        ));
        if ($active_count > 0) {
            return false;
        }
        try {
            $result = wp_trash_post($post_id);
            return (bool) $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 取得 Variation 的統計資料
     *
     * @param int $variationId ProductVariation ID
     * @return array 統計資料
     */
    public function getVariationStats(int $variationId): array
    {
        try {
            // 計算下單數量（只計算父訂單）
            $orderCounts = $this->statsCalculator->calculateOrderCounts([$variationId]);
            $ordered = $orderCounts[$variationId] ?? 0;

            // 計算已分配數量（從子訂單計算）
            $allocatedCounts = $this->statsCalculator->calculateAllocatedToChildOrders([$variationId]);
            $allocated = $allocatedCounts[$variationId] ?? 0;

            // 計算已出貨數量
            $shippedCounts = $this->statsCalculator->calculateShippedCounts([$variationId]);
            $shipped = $shippedCounts[$variationId] ?? 0;

            // 取得已採購數量（從 fct_meta 表讀取）
            $purchased = (int) $this->getVariationMeta($variationId, '_buygo_purchased', 0);

            return [
                'ordered' => $ordered,
                'allocated' => $allocated,
                'shipped' => $shipped,
                'purchased' => $purchased,
                'pending' => max(0, $allocated - $shipped),
                'reserved' => max(0, $ordered - $purchased)
            ];

        } catch (\Exception $e) {
            $this->debugService->log('ProductVariationService', '取得 Variation 統計失敗', [
                'variation_id' => $variationId,
                'error' => $e->getMessage()
            ], 'error');

            return [
                'ordered' => 0,
                'allocated' => 0,
                'shipped' => 0,
                'purchased' => 0,
                'pending' => 0,
                'reserved' => 0
            ];
        }
    }

    /**
     * 從 fct_meta 表讀取 variation 的 meta 值
     *
     * @param int $variationId
     * @param string $metaKey
     * @param mixed $default
     * @return mixed
     */
    public function getVariationMeta(int $variationId, string $metaKey, $default = null)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'fct_meta';
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$table} WHERE object_type = 'variation' AND object_id = %d AND meta_key = %s LIMIT 1",
            $variationId,
            $metaKey
        ));
        return $value !== null ? $value : $default;
    }

    /**
     * 更新 fct_meta 表中 variation 的 meta 值
     *
     * @param int $variationId
     * @param string $metaKey
     * @param mixed $metaValue
     * @return bool
     */
    public function updateVariationMeta(int $variationId, string $metaKey, $metaValue): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'fct_meta';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE object_type = 'variation' AND object_id = %d AND meta_key = %s LIMIT 1",
            $variationId,
            $metaKey
        ));

        if ($existing) {
            $wpdb->update($table, ['meta_value' => $metaValue], [
                'object_type' => 'variation',
                'object_id' => $variationId,
                'meta_key' => $metaKey
            ]);
        } else {
            $wpdb->insert($table, [
                'object_type' => 'variation',
                'object_id' => $variationId,
                'meta_key' => $metaKey,
                'meta_value' => $metaValue
            ]);
        }

        return true;
    }
}
