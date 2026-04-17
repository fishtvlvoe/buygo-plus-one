<?php

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\ProductService;

class ProductServiceDeletePostTest extends TestCase
{
    private ProductService $service;
    private object $testWpdb;
    private object $originalWpdb;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['_test_trash_called'] = false;
        $GLOBALS['_test_trash_result'] = true;
        $GLOBALS['_test_variation_post_id_map'] = [];
        $GLOBALS['_test_variation_active_count_map'] = [];

        // Save whatever wpdb exists (may be AllocationServiceTest's custom mock)
        $this->originalWpdb = $GLOBALS['wpdb'];

        // Install a clean wpdb that responds to deleteProductPost queries
        $GLOBALS['wpdb'] = new class {
            public string $prefix = 'wp_';
            public int $insert_id = 0;
            public string $last_error = '';

            public function prepare($query, ...$args) {
                return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
            }

            public function get_var($query) {
                if (!empty($GLOBALS['_test_variation_post_id_map'])
                    && strpos($query, 'fct_product_variations') !== false
                    && strpos($query, 'SELECT post_id') !== false) {
                    if (preg_match("/WHERE id = '?(\d+)'?/i", $query, $m)) {
                        return $GLOBALS['_test_variation_post_id_map'][(int)$m[1]] ?? null;
                    }
                }
                if (!empty($GLOBALS['_test_variation_active_count_map'])
                    && strpos($query, 'fct_product_variations') !== false
                    && strpos($query, 'COUNT(*)') !== false
                    && strpos($query, 'item_status') !== false) {
                    if (preg_match("/WHERE post_id = '?(\d+)'?/i", $query, $m)) {
                        return $GLOBALS['_test_variation_active_count_map'][(int)$m[1]] ?? 0;
                    }
                }
                return null;
            }

            public function get_col($query, $column_offset = 0) { return []; }
            public function get_row($query, $output = OBJECT)    { return null; }
            public function get_results($query, $output = OBJECT) { return []; }
            public function insert($table, $data, $format = null) { $this->insert_id = 1; return 1; }
            public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
            public function delete($table, $where, $where_format = null) { return 1; }
            public function query($query) { return true; }
        };

        $this->service = new ProductService();
    }

    protected function tearDown(): void
    {
        // Restore original wpdb so subsequent tests are not affected
        $GLOBALS['wpdb'] = $this->originalWpdb;
        parent::tearDown();
    }

    public function test_returns_false_when_variation_not_found(): void
    {
        $result = $this->service->deleteProductPost(999);
        $this->assertFalse($result);
        $this->assertFalse($GLOBALS['_test_trash_called']);
    }

    public function test_returns_false_when_other_active_variations_exist(): void
    {
        $GLOBALS['_test_variation_post_id_map'][1] = 100;
        $GLOBALS['_test_variation_active_count_map'][100] = 1;
        $result = $this->service->deleteProductPost(1);
        $this->assertFalse($result);
        $this->assertFalse($GLOBALS['_test_trash_called']);
    }

    public function test_trashes_post_when_last_active_variation(): void
    {
        $GLOBALS['_test_variation_post_id_map'][1] = 100;
        $GLOBALS['_test_variation_active_count_map'][100] = 0;
        $GLOBALS['_test_trash_result'] = true;
        $result = $this->service->deleteProductPost(1);
        $this->assertTrue($result);
        $this->assertTrue($GLOBALS['_test_trash_called']);
    }

    public function test_returns_false_when_wp_trash_post_returns_false(): void
    {
        $GLOBALS['_test_variation_post_id_map'][1] = 100;
        $GLOBALS['_test_variation_active_count_map'][100] = 0;
        $GLOBALS['_test_trash_result'] = false;
        $result = $this->service->deleteProductPost(1);
        $this->assertFalse($result);
        $this->assertTrue($GLOBALS['_test_trash_called']);
    }
}
