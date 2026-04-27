<?php

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

class ProductService
{
    private $queryService;
    private $writeService;
    private $variationService;

    const MAX_PRODUCTS_PER_SELLER = ProductLimitChecker::MAX_PRODUCTS_PER_SELLER;
    const MAX_IMAGES_PER_PRODUCT = ProductLimitChecker::MAX_IMAGES_PER_PRODUCT;

    public function __construct()
    {
        $this->queryService = new ProductQueryService();
        $this->writeService = new ProductWriteService();
        $this->variationService = new ProductVariationService();
    }

    public function getProductsWithOrderCount(array $filters = [], string $viewMode = 'frontend'): array
    {
        return $this->queryService->getProductsWithOrderCount($filters, $viewMode);
    }

    public function updateProduct(int $productId, array $updateData): bool
    {
        return $this->writeService->updateProduct($productId, $updateData);
    }

    public function getProductBuyers(int $productId): array
    {
        return $this->queryService->getProductBuyers($productId);
    }

    public function getProductById(int $productId): ?array
    {
        return $this->queryService->getProductById($productId);
    }

    public function getVariations(int $productId): array
    {
        return $this->variationService->getVariations($productId);
    }

    public function deleteProductPost(int $variationId): bool
    {
        return $this->variationService->deleteProductPost($variationId);
    }

    public function getVariationStats(int $variationId): array
    {
        return $this->variationService->getVariationStats($variationId);
    }

    public function getVariationMeta(int $variationId, string $metaKey, $default = null)
    {
        return $this->variationService->getVariationMeta($variationId, $metaKey, $default);
    }

    public function updateVariationMeta(int $variationId, string $metaKey, $metaValue): bool
    {
        return $this->variationService->updateVariationMeta($variationId, $metaKey, $metaValue);
    }

    public function isVariableProduct(int $productId): bool
    {
        return $this->queryService->isVariableProduct($productId);
    }

    public function canAddProduct($user_id)
    {
        return $this->queryService->canAddProduct($user_id);
    }

    public function canAddImage($product_id, $user_id)
    {
        return $this->queryService->canAddImage($product_id, $user_id);
    }
}
