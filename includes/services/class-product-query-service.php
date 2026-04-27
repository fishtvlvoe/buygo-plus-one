<?php

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

class ProductQueryService
{
    private $catalogQuery;
    private $buyerQuery;
    private $limitChecker;

    public function __construct()
    {
        $this->catalogQuery = new ProductCatalogQueryService();
        $this->buyerQuery = new ProductBuyerQueryService();
        $this->limitChecker = new ProductLimitChecker(DebugService::get_instance());
    }

    public function getProductsWithOrderCount(array $filters = [], string $viewMode = 'frontend'): array
    {
        return $this->catalogQuery->getProductsWithOrderCount($filters, $viewMode);
    }

    public function getProductById(int $productId): ?array
    {
        return $this->catalogQuery->getProductById($productId);
    }

    public function getProductBuyers(int $productId): array
    {
        return $this->buyerQuery->getProductBuyers($productId);
    }

    public function canAddProduct($user_id)
    {
        return $this->limitChecker->canAddProduct($user_id);
    }

    public function canAddImage($product_id, $user_id)
    {
        return $this->limitChecker->canAddImage($product_id, $user_id);
    }

    public function isVariableProduct(int $productId): bool
    {
        return $this->limitChecker->isVariableProduct($productId);
    }
}
