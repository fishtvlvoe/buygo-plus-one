<?php

namespace BuyGoPlus\Services;

use FluentCart\App\Models\ProductVariation;

if (!defined('ABSPATH')) {
    exit;
}

class ProductCatalogQueryService
{
    private $debugService;
    private $statsCalculator;
    private $variationService;

    public function __construct()
    {
        $this->debugService = DebugService::get_instance();
        $this->statsCalculator = new ProductStatsCalculator($this->debugService);
        $this->variationService = new ProductVariationService();
    }

    public function getProductsWithOrderCount(array $filters = [], string $viewMode = 'frontend'): array
    {
        $this->debugService->log('ProductService', '開始取得商品列表', ['filters' => $filters, 'viewMode' => $viewMode]);

        try {
            global $wpdb;

            $user = wp_get_current_user();
            $query = ProductVariation::query()->with(['product', 'product_detail'])->where('item_status', 'active');

            if ('frontend' === $viewMode && !in_array('administrator', (array) $user->roles, true)) {
                $sellerIds = SettingsService::get_accessible_seller_ids($user->ID);
                if (empty($sellerIds)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $postIds = $wpdb->get_col(
                        "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
                         WHERE p.post_author IN (" . implode(',', array_map('intval', $sellerIds)) . ")
                         AND p.post_type = 'fluent-products' AND p.post_status != 'trash'"
                    );
                    !empty($postIds) ? $query->whereIn('post_id', $postIds) : $query->whereRaw('1 = 0');
                }
            }

            if (!empty($filters['status']) && 'all' !== $filters['status']) {
                $query->whereHas('product', static function($builder) use ($filters) {
                    $builder->where('post_status', $filters['status']);
                });
            }
            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->where(static function($builder) use ($search) {
                    $builder->where('variation_title', 'LIKE', "%{$search}%")
                        ->orWhereHas('product', static function($productQuery) use ($search) {
                            $productQuery->where('post_title', 'LIKE', "%{$search}%");
                        });
                });
            }

            $products = $query->orderBy('id', 'desc')->get();
            $productIds = $products->pluck('id')->toArray();
            $orderCounts = $this->statsCalculator->calculateOrderCounts($productIds);
            $shippedCounts = $this->statsCalculator->calculateShippedCounts($productIds);
            $allocatedCounts = $this->statsCalculator->calculateAllocatedToChildOrders($productIds);
            $postIdToVarIds = [];
            foreach ($products as $product) {
                $postIdToVarIds[$product->post_id][] = $product->id;
            }

            $formatted = [];
            foreach ($products as $product) {
                $formatted[] = $this->formatProductListItem($product, $postIdToVarIds, $orderCounts, $shippedCounts, $allocatedCounts, $viewMode);
            }

            $this->debugService->log('ProductService', '成功取得商品列表', ['count' => count($formatted), 'viewMode' => $viewMode]);
            return $formatted;
        } catch (\Exception $e) {
            $this->debugService->log('ProductService', '取得商品列表失敗', ['error' => $e->getMessage(), 'filters' => $filters], 'error');
            throw new \Exception('無法取得商品列表：' . $e->getMessage());
        }
    }

    public function getProductById(int $productId): ?array
    {
        $this->debugService->log('ProductService', '取得單品資料', ['product_id' => $productId]);

        try {
            $product = ProductVariation::query()->with(['product', 'product_detail'])->where('id', $productId)->where('item_status', 'active')->first();
            if (!$product) {
                return null;
            }

            $imageUrl = $this->resolveImageUrl($product->post_id, true);
            $orderCounts = $this->statsCalculator->calculateOrderCounts([$product->id]);

            return [
                'id' => $product->id,
                'post_id' => $product->post_id,
                'name' => $this->resolveProductName($product->post_id, $product->variation_title ?? ''),
                'variation_title' => $product->variation_title,
                'price' => (int) ($product->item_price / 100),
                'currency' => $this->resolveCurrency($product->post_id),
                'image' => $imageUrl,
                'inventory' => $product->available ?? 0,
                'stock' => $product->available ?? 0,
                'ordered' => $orderCounts[$product->id] ?? 0,
                'purchased' => (int) get_post_meta($product->post_id, '_buygo_purchased', true),
                'status' => $product->product->post_status ?? 'draft',
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ];
        } catch (\Exception $e) {
            $this->debugService->log('ProductService', '取得單品資料失敗', ['product_id' => $productId, 'error' => $e->getMessage()], 'error');
            return null;
        }
    }

    private function formatProductListItem(object $product, array $postIdToVarIds, array $orderCounts, array $shippedCounts, array $allocatedCounts, string $viewMode): array
    {
        $siblingIds = $postIdToVarIds[$product->post_id] ?? [$product->id];
        $ordered = $allocated = $shipped = $purchased = 0;
        foreach ($siblingIds as $variationId) {
            $ordered += $orderCounts[$variationId] ?? 0;
            $allocated += $allocatedCounts[$variationId] ?? 0;
            $shipped += $shippedCounts[$variationId] ?? 0;
            $purchased += count($siblingIds) > 1
                ? (int) $this->variationService->getVariationMeta($variationId, '_buygo_purchased', 0)
                : 0;
        }
        if (1 === count($siblingIds)) {
            $purchased = (int) get_post_meta($product->post_id, '_buygo_purchased', true);
        }

        $data = [
            'id' => $product->id,
            'post_id' => $product->post_id,
            'name' => $this->resolveProductName($product->post_id, $product->variation_title ?? ''),
            'variation_title' => $product->variation_title,
            'price' => (int) ($product->item_price / 100),
            'currency' => $this->resolveCurrency($product->post_id),
            'formatted_price' => $this->formatPrice($product->item_price),
            'image' => $this->resolveImageUrl($product->post_id, false),
            'inventory' => $product->available ?? 0,
            'stock' => $product->available ?? 0,
            'total_stock' => $product->total_stock ?? 0,
            'committed' => $product->committed ?? 0,
            'on_hold' => $product->on_hold ?? 0,
            'ordered' => $ordered,
            'purchased' => $purchased,
            'allocated' => $allocated,
            'shipped' => $shipped,
            'pending' => max(0, $allocated - $shipped),
            'reserved' => max(0, $ordered - $purchased),
            'order_count' => $ordered,
            'stock_status' => $product->stock_status,
            'status' => $product->product->post_status ?? 'draft',
            'procurement_status' => get_post_meta($product->post_id, '_procurement_status', true) ?: 'pending',
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
            'seller_id' => $product->product->post_author ?? 0,
            'seller_name' => $this->getSellerName($product->product->post_author ?? 0),
        ];

        if ('backend' === $viewMode) {
            $data['reserved_count'] = ($product->committed ?? 0) + ($product->on_hold ?? 0);
        }

        return $data;
    }

    private function resolveProductName(int $postId, string $variationTitle): string
    {
        $post = get_post($postId);
        if ($post && !empty($post->post_title)) {
            return $post->post_title;
        }
        return (!empty($variationTitle) && '預設' !== $variationTitle) ? $variationTitle : '未命名商品';
    }

    private function resolveImageUrl(int $postId, bool $preferThumbnail): ?string
    {
        $thumbnailId = get_post_thumbnail_id($postId);
        $gallery = get_post_meta($postId, 'fluent-products-gallery-image', true);
        if ($preferThumbnail && $thumbnailId) {
            return wp_get_attachment_image_url($thumbnailId, 'medium');
        }
        if (is_array($gallery) && !empty($gallery[0]['url'])) {
            return $gallery[0]['url'];
        }
        return $thumbnailId ? wp_get_attachment_image_url($thumbnailId, 'medium') : null;
    }

    private function resolveCurrency(int $postId): string
    {
        $currency = get_post_meta($postId, '_mygo_currency', true);
        if (empty($currency)) {
            $currency = \FluentCart\Api\CurrencySettings::get('currency');
        }
        return !empty($currency) ? $currency : 'TWD';
    }

    private function formatPrice(int $priceInCents): string
    {
        $currency = \FluentCart\Api\CurrencySettings::get('currency') ?: 'TWD';
        return $this->getCurrencySymbol($currency) . ' ' . number_format($priceInCents / 100, 2);
    }

    private function getCurrencySymbol(string $currency): string
    {
        return [
            'JPY' => '¥',
            'TWD' => 'NT$',
            'USD' => '$',
            'THB' => '฿',
            'CNY' => '¥',
            'EUR' => '€',
            'GBP' => '£',
        ][$currency] ?? 'NT$';
    }

    private function getSellerName(int $userId): string
    {
        if (!$userId) {
            return '';
        }
        $user = get_userdata($userId);
        return $user ? ($user->display_name ?: $user->user_login) : '';
    }
}
