<?php

namespace BuyGoPlus\Tests\Unit\Services;

use BuyGoPlus\Services\AllocationBatchService;
use BuyGoPlus\Services\AllocationQueryService;
use BuyGoPlus\Services\AllocationService;
use BuyGoPlus\Services\AllocationWriteService;
use PHPUnit\Framework\TestCase;

class AllocationLockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists('FluentCart\App\Models\ProductVariation')) {
            eval('
                namespace FluentCart\App\Models;
                class ProductVariation {
                    public $post_id;
                    private function __construct($post_id) { $this->post_id = $post_id; }
                    public static function find($id) {
                        $map = $GLOBALS["mock_product_variation_map"] ?? [];
                        return isset($map[$id]) ? new self($map[$id]["post_id"]) : null;
                    }
                }
            ');
        }
        $GLOBALS['mock_product_variation_map'] = [100 => ['post_id' => 100]];
    }

    public function test_returns_allocation_locked_when_get_lock_not_acquired(): void
    {
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public function prepare($query, ...$args) { return str_replace('%s', "'lock'", $query); }
            public function get_var($sql) { return strpos($sql, 'GET_LOCK(') !== false ? '0' : '0'; }
            public function query($sql) { return true; }
        };

        $allocationService = new class extends AllocationService { public function __construct() {} };
        $queryService = new class extends AllocationQueryService { public function __construct() {} };
        $batchService = new class($allocationService, $queryService) extends AllocationBatchService {
            public function __construct($a, $q) { parent::__construct($a, $q); }
        };

        $service = new AllocationWriteService($allocationService, $queryService, $batchService);
        $result = $service->updateOrderAllocations(100, [1 => 1]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('allocation_locked', $result->get_error_code());
    }
}
