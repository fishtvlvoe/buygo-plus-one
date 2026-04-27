<?php

namespace BuyGoPlus\Tests\Unit\Services;

use BuyGoPlus\Services\AllocationCalculator;
use BuyGoPlus\Services\AllocationService;
use PHPUnit\Framework\TestCase;

class AllocationCalculatorTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_validate_adjustment_rejects_below_shipped_quantity(): void
    {
        $calculator = new AllocationCalculator(new class extends AllocationService {
            public function __construct() {}
        });

        $result = $calculator->validateAdjustment(100, 2000, 0, 2, 1, 2);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('BELOW_SHIPPED_QTY', $result->get_error_code());
    }

    public function test_adjust_allocation_returns_success_when_quantity_unchanged(): void
    {
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';

            public function prepare($query, ...$args): string
            {
                $flat = [];
                foreach ($args as $arg) {
                    foreach ((array) $arg as $value) {
                        $flat[] = $value;
                    }
                }
                $result = $query;
                foreach ($flat as $value) {
                    $result = preg_replace('/%d/', (string) (int) $value, $result, 1);
                }
                return $result;
            }

            public function get_row(string $sql, $output = OBJECT)
            {
                if (strpos($sql, 'FROM wp_fct_orders o') !== false) {
                    return (object) ['id' => 3000];
                }

                return (object) [
                    'id' => 11,
                    'order_id' => 3000,
                    'object_id' => 100,
                    'quantity' => 2,
                    'unit_price' => 100,
                    'line_meta' => json_encode(['_shipped_qty' => 1, '_allocated_qty' => 2]),
                ];
            }

            public function get_results(string $sql, $output = OBJECT): array
            {
                return [[
                    'id' => 21,
                    'quantity' => 2,
                    'line_meta' => json_encode(['_allocated_qty' => 2]),
                ]];
            }

            public function insert($table, $data, $format = null): int
            {
                return 1;
            }
        };

        $allocationService = new class extends AllocationService {
            public function __construct() {}

            public function getAllVariationIds($variation_id)
            {
                return ['post_id' => 2529, 'variation_ids' => [100]];
            }
        };

        $calculator = new AllocationCalculator($allocationService);
        $result = $calculator->adjustAllocation(100, 2000, 2);

        $this->assertTrue($result['success']);
        $this->assertSame('分配數量未變更', $result['message']);
        $this->assertSame(3000, $result['child_order_id']);
        $this->assertSame(2, $result['total_allocated']);
    }
}
