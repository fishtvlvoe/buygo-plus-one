<?php

namespace BuyGoPlus\Tests\Unit\Services;

use BuyGoPlus\Services\ProductQueryService;
use PHPUnit\Framework\TestCase;

class ProductQueryServiceTest extends TestCase
{
    public function test_get_products_with_order_count_delegates_to_catalog_query(): void
    {
        $service = new ProductQueryService();
        $catalog = new class {
            public function getProductsWithOrderCount(array $filters = [], string $viewMode = 'frontend'): array
            {
                return [['filters' => $filters, 'view_mode' => $viewMode]];
            }

            public function getProductById(int $productId): ?array
            {
                return ['id' => $productId];
            }
        };
        $buyer = new class {
            public function getProductBuyers(int $productId): array
            {
                return ['buyers_for' => $productId];
            }
        };
        $limit = new class {
            public function canAddProduct($userId) { return ['user' => $userId]; }
            public function canAddImage($productId, $userId) { return ['product' => $productId, 'user' => $userId]; }
            public function isVariableProduct(int $productId): bool { return 5 === $productId; }
        };

        $this->setProperty($service, 'catalogQuery', $catalog);
        $this->setProperty($service, 'buyerQuery', $buyer);
        $this->setProperty($service, 'limitChecker', $limit);

        $result = $service->getProductsWithOrderCount(['status' => 'draft'], 'backend');

        $this->assertSame([['filters' => ['status' => 'draft'], 'view_mode' => 'backend']], $result);
        $this->assertSame(['id' => 9], $service->getProductById(9));
        $this->assertSame(['buyers_for' => 7], $service->getProductBuyers(7));
        $this->assertSame(['user' => 3], $service->canAddProduct(3));
        $this->assertSame(['product' => 1, 'user' => 3], $service->canAddImage(1, 3));
        $this->assertTrue($service->isVariableProduct(5));
    }

    private function setProperty(object $object, string $property, $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setValue($object, $value);
    }
}
