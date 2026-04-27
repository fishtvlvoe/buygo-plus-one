<?php

namespace BuyGoPlus\Tests\Unit\Services;

use BuyGoPlus\Services\ProductVariationService;
use PHPUnit\Framework\TestCase;

class ProductVariationServiceTest extends TestCase
{
    private object $originalWpdb;
    private ProductVariationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['_test_trash_called'] = false;
        $GLOBALS['_test_trash_result'] = true;
        $GLOBALS['_test_variation_post_id_map'] = [];
        $GLOBALS['_test_variation_active_count_map'] = [];

        $this->originalWpdb = $GLOBALS['wpdb'];
        $GLOBALS['wpdb'] = new class {
            public string $prefix = 'wp_';

            public function prepare($query, ...$args)
            {
                return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
            }

            public function get_var($query)
            {
                if (strpos($query, 'SELECT post_id') !== false && preg_match("/WHERE id = '?(\d+)'?/i", $query, $m)) {
                    return $GLOBALS['_test_variation_post_id_map'][(int) $m[1]] ?? null;
                }
                if (strpos($query, 'COUNT(*)') !== false && preg_match("/WHERE post_id = '?(\d+)'?/i", $query, $m)) {
                    return $GLOBALS['_test_variation_active_count_map'][(int) $m[1]] ?? 0;
                }
                return null;
            }

            public function insert($table, $data, $format = null) { return 1; }
        };

        $this->service = new ProductVariationService();
    }

    protected function tearDown(): void
    {
        $GLOBALS['wpdb'] = $this->originalWpdb;
        parent::tearDown();
    }

    public function test_delete_product_post_returns_false_when_other_active_variations_exist(): void
    {
        $GLOBALS['_test_variation_post_id_map'][1] = 100;
        $GLOBALS['_test_variation_active_count_map'][100] = 1;

        $this->assertFalse($this->service->deleteProductPost(1));
        $this->assertFalse($GLOBALS['_test_trash_called']);
    }

    public function test_delete_product_post_trashes_last_active_variation(): void
    {
        $GLOBALS['_test_variation_post_id_map'][1] = 100;
        $GLOBALS['_test_variation_active_count_map'][100] = 0;

        $this->assertTrue($this->service->deleteProductPost(1));
        $this->assertTrue($GLOBALS['_test_trash_called']);
    }
}
