<?php

namespace BuyGoPlus\Tests\Unit\Services;

use BuyGoPlus\Services\ProductService;
use PHPUnit\Framework\TestCase;

/**
 * ProductServiceBasicTest
 *
 * Basic tests for the ProductService class
 * Tests the core functionality of product price calculation and validation
 */
class ProductServiceBasicTest extends TestCase
{
    /**
     * @var ProductService
     */
    private $productService;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void
    {
        $this->productService = new ProductService();
    }

    /**
     * Test 1: Basic price calculation without discount
     */
    public function testCalculatePriceBasic(): void
    {
        $items = [
            ['price' => 100, 'quantity' => 1],
            ['price' => 50, 'quantity' => 2],
        ];

        $result = $this->productService->calculatePrice($items);
        $this->assertEquals(200, $result);
    }

    /**
     * Test 2: Price calculation with discount
     */
    public function testCalculatePriceWithDiscount(): void
    {
        $items = [
            ['price' => 100, 'quantity' => 1],
        ];

        $result = $this->productService->calculatePrice($items, 0.1);
        $this->assertEquals(90, $result);
    }

    /**
     * Test 3: Discount validation
     */
    public function testDiscountValidation(): void
    {
        $this->assertTrue($this->productService->isValidDiscount(0.1));
        $this->assertFalse($this->productService->isValidDiscount(1.5));
        $this->assertFalse($this->productService->isValidDiscount(-0.1));
    }

    /**
     * Test 4: Price formatting
     */
    public function testFormatPrice(): void
    {
        $formatted = $this->productService->formatPrice(99.5);
        $this->assertStringContainsString('99.5', $formatted);
    }

    /**
     * Test 5: Rating calculation
     */
    public function testCalculateRating(): void
    {
        $ratings = [4, 5, 3];
        $average = $this->productService->calculateAverageRating($ratings);
        $this->assertEquals(4.0, $average);
    }

    /**
     * Test 6: Empty items handling
     */
    public function testEmptyItemsHandling(): void
    {
        $result = $this->productService->calculatePrice([]);
        $this->assertEquals(0, $result);
    }

    /**
     * Test 7: Large quantity handling
     */
    public function testLargeQuantityHandling(): void
    {
        $items = [
            ['price' => 10, 'quantity' => 1000],
        ];

        $result = $this->productService->calculatePrice($items);
        $this->assertEquals(10000, $result);
    }
}
