<?php

namespace BuyGoPlus\Tests\Unit\Services;

use BuyGoPlus\Services\AllocationQueryService;
use BuyGoPlus\Services\AllocationBatchService;
use BuyGoPlus\Services\AllocationService;
use BuyGoPlus\Services\AllocationWriteService;
use PHPUnit\Framework\TestCase;

class AllocationWriteServiceTest extends TestCase
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

        $GLOBALS['mock_product_variation_map'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mock_product_variation_map'], $GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_update_order_allocations_returns_error_when_order_items_missing(): void
    {
        $GLOBALS['mock_product_variation_map'][958] = ['post_id' => 2529];
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public $last_error = '';

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

            public function query($query)
            {
                return true;
            }

            public function insert($table, $data, $format = null): int
            {
                return 1;
            }

            public function get_var(string $sql)
            {
                return strpos($sql, 'fct_meta') !== false ? '10' : '0';
            }

            public function get_results(string $sql, $output = OBJECT): array
            {
                return [];
            }

            public function get_row(string $sql, $output = OBJECT)
            {
                return null;
            }
        };

        $allocationService = new class extends AllocationService {
            public function __construct() {}
            public function syncAllocatedQtyBatch(array $items): void {}
        };
        $queryService = new class extends AllocationQueryService {
            public function getAllVariationIds($variation_id)
            {
                return ['post_id' => 2529, 'variation_ids' => [958]];
            }
        };
        $batchService = new class($allocationService, $queryService) extends AllocationBatchService {
            public function __construct($allocationService, $queryService)
            {
                parent::__construct($allocationService, $queryService);
            }
        };

        $service = new AllocationWriteService($allocationService, $queryService, $batchService);
        $result = $service->updateOrderAllocations(958, [1405 => 1]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('NO_ORDER_ITEMS', $result->get_error_code());
    }

    public function test_cancel_child_order_resets_allocated_qty(): void
    {
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public $updateCalls = [];

            public function prepare($query, ...$args)
            {
                return $query;
            }

            public function get_row($query, $output = OBJECT)
            {
                return (object) [
                    'id' => 1,
                    'type' => 'split',
                    'status' => 'pending',
                    'shipping_status' => 'unshipped',
                ];
            }

            public function query($query)
            {
                return 1;
            }

            public function get_results($query, $output = OBJECT)
            {
                return [(object) ['id' => 10, 'line_meta' => json_encode(['_allocated_qty' => 5])]];
            }

            public function update($table, $data, $where, $format = null, $where_format = null)
            {
                $this->updateCalls[] = compact('table', 'data', 'where');
                return 1;
            }

            public function insert($table, $data, $format = null): int
            {
                return 1;
            }
        };

        $allocationService = new class extends AllocationService {
            public function __construct() {}
        };
        $queryService = new class extends AllocationQueryService {
            public function __construct() {}
        };
        $batchService = new class($allocationService, $queryService) extends AllocationBatchService {
            public function __construct($allocationService, $queryService)
            {
                parent::__construct($allocationService, $queryService);
            }
        };

        $service = new AllocationWriteService($allocationService, $queryService, $batchService);
        $result = $service->cancelChildOrder(1);

        $this->assertTrue($result);
        $this->assertCount(1, $GLOBALS['wpdb']->updateCalls);
        $lineMeta = json_decode($GLOBALS['wpdb']->updateCalls[0]['data']['line_meta'], true);
        $this->assertSame(0, $lineMeta['_allocated_qty']);
    }

    public function test_update_order_allocations_ignores_cancelled_child_orders_in_allocated_sum(): void
    {
        $GLOBALS['mock_product_variation_map'][958] = ['post_id' => 2529];
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public $last_error = '';
            public $insert_id = 0;
            private $insertCount = 0;

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

            public function query($query)
            {
                return true;
            }

            public function insert($table, $data, $format = null): int
            {
                $this->insertCount++;
                $this->insert_id = 1 === $this->insertCount ? 9001 : 9101;
                return 1;
            }

            public function get_var(string $sql)
            {
                if (strpos($sql, 'fct_meta') !== false) {
                    return '10';
                }

                if (strpos($sql, 'COUNT(*) FROM wp_fct_orders') !== false) {
                    return '0';
                }

                if (strpos($sql, 'child_o.parent_id = 1405') !== false) {
                    return strpos($sql, "child_o.status NOT IN ('cancelled', 'refunded')") !== false ? '0' : '3';
                }

                if (strpos($sql, 'child_o.type = \'split\'') !== false) {
                    return strpos($sql, "child_o.status NOT IN ('cancelled', 'refunded')") !== false ? '0' : '3';
                }

                return '0';
            }

            public function get_results(string $sql, $output = OBJECT): array
            {
                if (strpos($sql, 'SELECT oi.* FROM wp_fct_order_items oi') !== false) {
                    return [[
                        'id' => 44,
                        'order_id' => 1405,
                        'object_id' => 958,
                        'quantity' => 5,
                        'line_meta' => '{}',
                        'unit_price' => 100,
                    ]];
                }

                return [];
            }

            public function get_row(string $sql, $output = OBJECT)
            {
                if (strpos($sql, 'FROM wp_fct_orders WHERE id = 1405') !== false) {
                    return (object) [
                        'id' => 1405,
                        'invoice_no' => 'INV-1405',
                        'customer_id' => 10,
                        'payment_status' => 'paid',
                        'currency' => 'TWD',
                        'payment_method' => 'cod',
                        'payment_method_title' => 'Cash',
                    ];
                }

                if (strpos($sql, 'FROM wp_fct_order_items WHERE order_id = 1405') !== false) {
                    return (object) [
                        'id' => 44,
                        'order_id' => 1405,
                        'post_id' => 2529,
                        'object_id' => 958,
                        'quantity' => 5,
                        'unit_price' => 100,
                        'title' => 'Test Product',
                        'post_title' => 'Test Product',
                    ];
                }

                return null;
            }

            public function update($table, $data, $where, $format = null, $where_format = null)
            {
                return 1;
            }
        };

        $allocationService = new class extends AllocationService {
            public function __construct() {}
            public function syncAllocatedQtyBatch(array $items): void {}
        };
        $queryService = new class extends AllocationQueryService {
            public function getAllVariationIds($variation_id)
            {
                return ['post_id' => 2529, 'variation_ids' => [958]];
            }
        };
        $batchService = new class($allocationService, $queryService) extends AllocationBatchService {
            public function __construct($allocationService, $queryService)
            {
                parent::__construct($allocationService, $queryService);
            }
        };

        $service = new AllocationWriteService($allocationService, $queryService, $batchService);
        $result = $service->updateOrderAllocations(958, [1405 => 3]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }
}
