<?php

namespace BuyGoPlus\Services;

use FluentCart\App\Models\ProductVariation;

if (!defined('ABSPATH')) {
    exit;
}

class ProductWriteService
{
    private $debugService;

    public function __construct()
    {
        $this->debugService = DebugService::get_instance();
    }

    public function updateProduct(int $productId, array $updateData): bool
    {
        $this->debugService->log('ProductService', '更新商品資料', ['product_id' => $productId, 'update_data' => $updateData]);

        try {
            $product = ProductVariation::find($productId);
            if (!$product) {
                return false;
            }
            if (isset($updateData['name'])) {
                wp_update_post(['ID' => $product->post_id, 'post_title' => $updateData['name']]);
            }
            if (isset($updateData['price'])) {
                $product->item_price = (int) ($updateData['price'] * 100);
            }
            if (isset($updateData['purchased'])) {
                update_post_meta($product->post_id, '_buygo_purchased', (int) $updateData['purchased']);
            }
            if (isset($updateData['stock'])) {
                $product->available = (int) $updateData['stock'];
            }
            if (isset($updateData['status'])) {
                wp_update_post(['ID' => $product->post_id, 'post_status' => $updateData['status']]);
            }
            if (!$product->save()) {
                $this->debugService->log('ProductService', 'ProductVariation::save() 返回 false', ['product_id' => $productId], 'warning');
            }
            $this->debugService->log('ProductService', '商品更新成功', ['product_id' => $productId]);
            return true;
        } catch (\Exception $e) {
            $this->debugService->log('ProductService', '商品更新失敗', ['product_id' => $productId, 'error' => $e->getMessage()], 'error');
            throw $e;
        }
    }
}
