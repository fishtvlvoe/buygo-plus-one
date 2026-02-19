<?php

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

use FluentCart\App\Models\Product;

/**
 * Product Limit Checker - 商品限制檢查
 *
 * 從 ProductService 抽出的限制檢查邏輯：
 * - 賣家商品數量上限
 * - 商品圖片數量上限
 * - 多樣式商品判斷
 *
 * @package BuyGoPlus\Services
 * @since 2.1.0
 */
class ProductLimitChecker
{
    private $debugService;

    // 測試版限制（Phase 19）
    const MAX_PRODUCTS_PER_SELLER = 2;  // 每個賣家最多 2 個商品
    const MAX_IMAGES_PER_PRODUCT = 2;   // 每個商品最多 2 張圖片

    public function __construct(DebugService $debugService)
    {
        $this->debugService = $debugService;
    }

    /**
     * 檢查賣家是否可以新增商品（Phase 19 + Phase 40）
     *
     * Phase 40: 實作小幫手共享配額驗證
     * - 計算賣家 + 所有小幫手的總商品數
     * - 與賣家的 product_limit 比較
     *
     * @param int $user_id 賣家或小幫手 ID
     * @return array ['can_add' => bool, 'current' => int, 'limit' => int, 'message' => string]
     */
    public function canAddProduct($user_id) {
        global $wpdb;

        // 檢查賣家類型
        $seller_type = \BuyGoPlus\Admin\SellerTypeField::get_seller_type($user_id);

        // 真實賣家沒有限制
        if ($seller_type === 'real') {
            return [
                'can_add' => true,
                'current' => 0,
                'limit' => 0,
                'message' => '真實賣家無商品數量限制'
            ];
        }

        // Phase 40: 判斷是賣家還是小幫手
        $seller_id = $user_id;
        $is_helper = false;

        // 檢查是否為小幫手（查詢 wp_buygo_helpers 表）
        $helper_relation = $wpdb->get_row($wpdb->prepare(
            "SELECT seller_id FROM {$wpdb->prefix}buygo_helpers WHERE helper_id = %d",
            $user_id
        ));

        if ($helper_relation) {
            $is_helper = true;
            $seller_id = $helper_relation->seller_id;
        }

        // 取得賣家的商品限制數量 (0 = 無限制)
        $product_limit = get_user_meta($seller_id, 'buygo_product_limit', true);
        if ($product_limit === '') {
            $product_limit = self::MAX_PRODUCTS_PER_SELLER; // 預設為 2
        }
        $product_limit = intval($product_limit);

        // 如果限制為 0,表示無限制
        if ($product_limit === 0) {
            return [
                'can_add' => true,
                'current' => 0,
                'limit' => 0,
                'message' => $is_helper ? '小幫手（無商品數量限制）' : '測試賣家（無商品數量限制）'
            ];
        }

        // Phase 40: 計算賣家 + 所有小幫手的總商品數

        // 1. 賣家自己的商品數
        $seller_product_count = Product::where('post_author', $seller_id)
            ->where('post_status', '!=', 'trash')
            ->count();

        // 2. 所有小幫手的商品數
        $helpers = $wpdb->get_col($wpdb->prepare(
            "SELECT helper_id FROM {$wpdb->prefix}buygo_helpers WHERE seller_id = %d",
            $seller_id
        ));

        $helpers_product_count = 0;
        if (!empty($helpers)) {
            $helpers_product_count = Product::whereIn('post_author', $helpers)
                ->where('post_status', '!=', 'trash')
                ->count();
        }

        // 總商品數
        $total_count = $seller_product_count + $helpers_product_count;
        $can_add = $total_count < $product_limit;

        $message = $can_add
            ? sprintf('還可新增 %d 個商品', $product_limit - $total_count)
            : '已達上架限制，無法新增更多產品';

        return [
            'can_add' => $can_add,
            'current' => $total_count,
            'limit' => $product_limit,
            'message' => $message,
            'is_helper' => $is_helper,
            'seller_id' => $seller_id
        ];
    }

    /**
     * 檢查商品是否可以新增圖片（Phase 19）
     *
     * @param int $product_id 商品 ID (wp_posts.ID)
     * @param int $user_id 賣家 ID
     * @return array ['can_add' => bool, 'current' => int, 'limit' => int, 'message' => string]
     */
    public function canAddImage($product_id, $user_id) {
        // 檢查賣家類型
        $seller_type = \BuyGoPlus\Admin\SellerTypeField::get_seller_type($user_id);

        // 真實賣家沒有限制
        if ($seller_type === 'real') {
            return [
                'can_add' => true,
                'current' => 0,
                'limit' => 0,
                'message' => '真實賣家無圖片數量限制'
            ];
        }

        // 測試賣家：計算現有圖片數量
        $image_count = 0;

        // 1. 縮圖
        $thumbnail_id = get_post_thumbnail_id($product_id);
        if ($thumbnail_id) {
            $image_count++;
        }

        // 2. Gallery 圖片
        $gallery = get_post_meta($product_id, '_product_image_gallery', true);
        if (!empty($gallery)) {
            $gallery_ids = explode(',', $gallery);
            $image_count += count($gallery_ids);
        }

        $can_add = $image_count < self::MAX_IMAGES_PER_PRODUCT;

        return [
            'can_add' => $can_add,
            'current' => $image_count,
            'limit' => self::MAX_IMAGES_PER_PRODUCT,
            'message' => $can_add
                ? sprintf('還可新增 %d 張圖片', self::MAX_IMAGES_PER_PRODUCT - $image_count)
                : sprintf('已達圖片數量上限（%d/%d）', $image_count, self::MAX_IMAGES_PER_PRODUCT)
        ];
    }

    /**
     * 檢查商品是否為多樣式商品
     *
     * @param int $productId WordPress Post ID
     * @return bool
     */
    public function isVariableProduct(int $productId): bool
    {
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'fct_product_variations';

            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE post_id = %d AND item_status = 'active'",
                $productId
            ));

            return (int)$count > 1;

        } catch (\Exception $e) {
            $this->debugService->log('ProductLimitChecker', '檢查 Variable Product 失敗', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ], 'error');

            return false;
        }
    }
}
