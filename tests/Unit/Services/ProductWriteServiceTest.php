<?php

namespace BuyGoPlus\Tests\Unit\Services;

use BuyGoPlus\Services\ProductWriteService;
use PHPUnit\Framework\TestCase;

class ProductWriteServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('FluentCart\App\Models\ProductVariation')) {
            eval('
                namespace FluentCart\App\Models;
                class ProductVariation {
                    public $post_id;
                    public $item_price = 0;
                    public $available = 0;
                    public $saved = false;
                    public static function find($id) {
                        return $GLOBALS["mock_product_variation_objects"][$id] ?? null;
                    }
                    public function save() {
                        $this->saved = true;
                        return true;
                    }
                }
            ');
        }

        $GLOBALS['mock_product_variation_objects'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mock_product_variation_objects']);
        parent::tearDown();
    }

    public function test_update_product_returns_false_when_variation_missing(): void
    {
        $service = new ProductWriteService();

        $this->assertFalse($service->updateProduct(999, ['name' => 'Missing']));
    }
}
