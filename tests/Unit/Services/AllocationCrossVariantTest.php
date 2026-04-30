<?php

namespace BuyGoPlus\Tests\Unit\Services;

use BuyGoPlus\Services\AllocationQueryService;
use BuyGoPlus\Services\AllocationBatchService;
use BuyGoPlus\Services\AllocationService;
use BuyGoPlus\Services\AllocationWriteService;
use PHPUnit\Framework\TestCase;

/**
 * 多變體商品跨變體分配污染回歸測試
 *
 * 場景：商品 post_id=2650「日本皮克敏髮夾四款」
 * - 父訂單 #1687 含 ABCD 四變體 order_items：A=3, B=3, C=3, D=2
 * - variation_ids: A=1038, B=1039, C=1040, D=1041
 * - 採購 meta: A=7, B=4, C=4, D=0（總計=11）
 */
class AllocationCrossVariantTest extends TestCase
{
    const POST_ID = 2650;
    const PRODUCT_ID = 2650;
    const PARENT_ORDER_ID = 1687;
    const VAR_A = 1038;
    const VAR_B = 1039;
    const VAR_C = 1040;
    const VAR_D = 1041;

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

        $GLOBALS['mock_product_variation_map'] = [
            self::PRODUCT_ID => ['post_id' => self::POST_ID],
            900 => ['post_id' => 500],
        ];
        $GLOBALS['mock_get_post_meta_map'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['mock_product_variation_map'],
            $GLOBALS['mock_get_post_meta_map'],
            $GLOBALS['wpdb']
        );
        parent::tearDown();
    }

    /**
     * Helper: 建立可追蹤 insert 呼叫的 mock wpdb
     */
    private function createTrackableWpdb(array $config = []): object
    {
        return new class($config) {
            public $prefix = 'wp_';
            public $last_error = '';
            public $insert_id = 0;
            public $insertLog = [];
            public $queryLog = [];
            public $config;

            public function __construct(array $config)
            {
                $this->config = $config;
            }

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
                $result = preg_replace('/%s/', "'" . addslashes((string) ($flat[count($flat) - 1] ?? '')) . "'", $result, 1);
                return $result;
            }

            public function query($query)
            {
                $this->queryLog[] = $query;
                return true;
            }

            public function insert($table, $data, $format = null): int
            {
                $this->insert_id++;
                $this->insertLog[] = ['table' => $table, 'data' => $data];
                return 1;
            }

            public function update($table, $data, $where, $format = null, $where_format = null)
            {
                return 1;
            }

            public function delete($table, $where, $where_format = null)
            {
                return 1;
            }

            public function get_var(string $sql)
            {
                // fct_meta purchased total
                if (strpos($sql, 'fct_meta') !== false && strpos($sql, '_buygo_purchased') !== false) {
                    return (string) ($this->config['purchased_total'] ?? 11);
                }
                // post_meta fallback
                if (strpos($sql, 'wp_postmeta') !== false && strpos($sql, '_buygo_purchased') !== false) {
                    return (string) ($this->config['purchased_total'] ?? 11);
                }
                // child allocated sum
                if (strpos($sql, 'child_o.type = \'split\'') !== false) {
                    $excludeCancelled = strpos($sql, "child_o.status NOT IN ('cancelled', 'refunded')") !== false;
                    $allocated = $this->config['existing_child_allocated'] ?? 0;
                    return (string) $allocated;
                }
                // COUNT split orders for invoice_no suffix
                if (strpos($sql, 'COUNT(*) FROM wp_fct_orders') !== false && strpos($sql, "type = 'split'") !== false) {
                    return '0';
                }
                // shipment items sum
                if (strpos($sql, 'buygo_shipment_items') !== false) {
                    return '0';
                }
                return '0';
            }

            public function get_results(string $sql, $output = OBJECT): array
            {
                // Parent order items query (used by updateOrderAllocations)
                if (strpos($sql, 'SELECT oi.* FROM wp_fct_order_items oi') !== false) {
                    return $this->config['parent_items'] ?? [];
                }
                return [];
            }

            public function get_row(string $sql, $output = OBJECT)
            {
                // Parent order query
                if (strpos($sql, 'FROM wp_fct_orders WHERE id = ' . AllocationCrossVariantTest::PARENT_ORDER_ID) !== false) {
                    return (object) [
                        'id' => AllocationCrossVariantTest::PARENT_ORDER_ID,
                        'invoice_no' => 'INV-1687',
                        'customer_id' => 10,
                        'payment_status' => 'paid',
                        'currency' => 'TWD',
                        'payment_method' => 'cod',
                        'payment_method_title' => 'Cash',
                    ];
                }

                // Parent item query in createChildOrder for order #500 (legacy compat test)
                if (strpos($sql, 'FROM wp_fct_orders WHERE id = 500') !== false) {
                    return (object) [
                        'id' => 500,
                        'invoice_no' => 'INV-500',
                        'customer_id' => 20,
                        'payment_status' => 'paid',
                        'currency' => 'TWD',
                        'payment_method' => 'cod',
                        'payment_method_title' => 'Cash',
                    ];
                }

                // For legacy compat test (order #500, object_id=900)
                if (strpos($sql, 'FROM wp_fct_order_items WHERE order_id = 500') !== false) {
                    return (object) [
                        'id' => 200,
                        'order_id' => 500,
                        'post_id' => 500,
                        'object_id' => 900,
                        'quantity' => 5,
                        'unit_price' => 100,
                        'title' => 'Single Variant Product',
                        'post_title' => 'Single Variant Product',
                    ];
                }

                // Parent item query in createChildOrder (IN clause — current buggy behavior returns first match)
                if (strpos($sql, 'FROM wp_fct_order_items WHERE order_id = ' . AllocationCrossVariantTest::PARENT_ORDER_ID) !== false) {
                    if (strpos($sql, 'object_id IN') !== false) {
                        // Current buggy code: IN clause returns first match (lowest variation_id = A)
                        return (object) [
                            'id' => 100,
                            'order_id' => AllocationCrossVariantTest::PARENT_ORDER_ID,
                            'post_id' => AllocationCrossVariantTest::POST_ID,
                            'object_id' => AllocationCrossVariantTest::VAR_A, // Bug: always returns A
                            'quantity' => 3,
                            'unit_price' => 100,
                            'title' => 'Test Product A',
                            'post_title' => 'Test Product',
                        ];
                    }
                    // After fix: exact match with = clause
                    if (strpos($sql, 'object_id = ' . AllocationCrossVariantTest::VAR_D) !== false) {
                        return (object) [
                            'id' => 103,
                            'order_id' => AllocationCrossVariantTest::PARENT_ORDER_ID,
                            'post_id' => AllocationCrossVariantTest::POST_ID,
                            'object_id' => AllocationCrossVariantTest::VAR_D,
                            'quantity' => 2,
                            'unit_price' => 100,
                            'title' => 'Test Product D',
                            'post_title' => 'Test Product',
                        ];
                    }
                    if (strpos($sql, 'object_id = ' . AllocationCrossVariantTest::VAR_C) !== false) {
                        return (object) [
                            'id' => 102,
                            'order_id' => AllocationCrossVariantTest::PARENT_ORDER_ID,
                            'post_id' => AllocationCrossVariantTest::POST_ID,
                            'object_id' => AllocationCrossVariantTest::VAR_C,
                            'quantity' => 3,
                            'unit_price' => 100,
                            'title' => 'Test Product C',
                            'post_title' => 'Test Product',
                        ];
                    }
                }

                return null;
            }
        };
    }

    /**
     * Helper: 建立四變體 parent_items fixture
     */
    private function fourVariantParentItems(): array
    {
        return [
            ['id' => 100, 'order_id' => self::PARENT_ORDER_ID, 'object_id' => self::VAR_A, 'quantity' => 3, 'line_meta' => '{}', 'unit_price' => 100],
            ['id' => 101, 'order_id' => self::PARENT_ORDER_ID, 'object_id' => self::VAR_B, 'quantity' => 3, 'line_meta' => '{}', 'unit_price' => 100],
            ['id' => 102, 'order_id' => self::PARENT_ORDER_ID, 'object_id' => self::VAR_C, 'quantity' => 3, 'line_meta' => '{}', 'unit_price' => 100],
            ['id' => 103, 'order_id' => self::PARENT_ORDER_ID, 'object_id' => self::VAR_D, 'quantity' => 2, 'line_meta' => '{}', 'unit_price' => 100],
        ];
    }

    /**
     * Helper: 建立 service 實例
     */
    private function createWriteService(object $wpdb, array $extraConfig = []): AllocationWriteService
    {
        $GLOBALS['wpdb'] = $wpdb;

        $queryService = new class extends AllocationQueryService {
            public function getAllVariationIds($variation_id)
            {
                return [
                    'post_id' => AllocationCrossVariantTest::POST_ID,
                    'variation_ids' => [
                        AllocationCrossVariantTest::VAR_A,
                        AllocationCrossVariantTest::VAR_B,
                        AllocationCrossVariantTest::VAR_C,
                        AllocationCrossVariantTest::VAR_D,
                    ],
                ];
            }
        };

        $allocationService = new class extends AllocationService {
            public function __construct() {}
            public function syncAllocatedQtyBatch(array $items): void {}
        };

        $batchService = new class($allocationService, $queryService) extends AllocationBatchService {
            public function __construct($allocationService, $queryService)
            {
                parent::__construct($allocationService, $queryService);
            }
        };

        return new AllocationWriteService($allocationService, $queryService, $batchService);
    }

    /**
     * 1.1 Fixture 驗證：確認測試環境能正確建立四變體父訂單
     */
    public function test_fixture_parent_order_has_four_variants(): void
    {
        $items = $this->fourVariantParentItems();
        $this->assertCount(4, $items);
        $this->assertSame(self::VAR_A, $items[0]['object_id']);
        $this->assertSame(self::VAR_B, $items[1]['object_id']);
        $this->assertSame(self::VAR_C, $items[2]['object_id']);
        $this->assertSame(self::VAR_D, $items[3]['object_id']);
    }

    /**
     * 1.2 Decision 1 — 直接呼叫 createChildOrder 分配 D（variation_id=1041）qty=2
     * 斷言新建子訂單 order_item 的 object_id=1041 而非 1038
     */
    public function test_create_child_order_uses_correct_variation_id(): void
    {
        $wpdb = $this->createTrackableWpdb();
        $service = $this->createWriteService($wpdb);

        $method = new \ReflectionMethod(AllocationWriteService::class, 'createChildOrder');

        // New signature: createChildOrder($parent_order_id, $variation_id, $quantity)
        // We allocate D (variation_id=1041) qty=2
        $result = $method->invoke($service, self::PARENT_ORDER_ID, self::VAR_D, 2);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);

        // Find the fct_order_items insert call
        $orderItemInsert = null;
        foreach ($wpdb->insertLog as $log) {
            if ($log['table'] === 'wp_fct_order_items') {
                $orderItemInsert = $log['data'];
                break;
            }
        }

        $this->assertNotNull($orderItemInsert, '應建立子訂單項目');
        // DESIRED: object_id should be VAR_D (1041)
        // CURRENT BUG: object_id is VAR_A (1038) because IN clause returns first match
        $this->assertSame(self::VAR_D, (int) $orderItemInsert['object_id'], '子訂單 object_id 必須等於目標變體 D (1041)，而非錯誤的 A (1038)');
    }

    /**
     * 1.3 Decision 2 — 一鍵分配 #1687 全部待配
     * 斷言建出 4 個子訂單，object_id 分別為 1038/1039/1040/1041
     */
    public function test_allocate_all_for_customer_with_multiple_variants(): void
    {
        $callLog = [];

        $wpdb = new class {
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

            public function query($query) { return true; }
            public function insert($table, $data, $format = null): int { return 1; }
            public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }

            public function get_var(string $sql)
            {
                if (strpos($sql, 'buygo_shipment_items') !== false) return '0';
                if (strpos($sql, 'fct_meta') !== false && strpos($sql, '_buygo_purchased') !== false) return '11';
                if (strpos($sql, 'child_o.parent_id') !== false && strpos($sql, "child_o.status NOT IN ('cancelled', 'refunded')") !== false) return '0';
                return '0';
            }

            public function get_results(string $sql, $output = OBJECT): array
            {
                if (strpos($sql, 'fct_order_items oi') !== false && strpos($sql, 'customer_id') !== false) {
                    return [
                        (object) ['order_item_id' => 100, 'order_id' => 1687, 'object_id' => 1038, 'quantity' => 3, 'line_meta' => '{}'],
                        (object) ['order_item_id' => 101, 'order_id' => 1687, 'object_id' => 1039, 'quantity' => 3, 'line_meta' => '{}'],
                        (object) ['order_item_id' => 102, 'order_id' => 1687, 'object_id' => 1040, 'quantity' => 3, 'line_meta' => '{}'],
                        (object) ['order_item_id' => 103, 'order_id' => 1687, 'object_id' => 1041, 'quantity' => 2, 'line_meta' => '{}'],
                    ];
                }
                return [];
            }
        };
        $GLOBALS['wpdb'] = $wpdb;

        $queryService = new class extends AllocationQueryService {
            public function getAllVariationIds($variation_id)
            {
                return [
                    'post_id' => 2650,
                    'variation_ids' => [1038, 1039, 1040, 1041],
                ];
            }
        };

        $allocationService = new class($callLog) extends AllocationService {
            private $log;
            public function __construct(&$log) { $this->log = &$log; }
            public function syncAllocatedQtyBatch(array $items): void {}
            public function updateOrderAllocations($product_id, $allocations)
            {
                $this->log[] = ['method' => 'updateOrderAllocations', 'product_id' => $product_id, 'allocations' => $allocations];
                return ['success' => true, 'child_orders' => [], 'total_allocated' => 0];
            }
        };

        $batchService = new class($allocationService, $queryService) extends AllocationBatchService {
            public function __construct($allocationService, $queryService)
            {
                parent::__construct($allocationService, $queryService);
            }
        };

        $service = new AllocationWriteService($allocationService, $queryService, $batchService);
        $result = $service->allocateAllForCustomer(self::PRODUCT_ID, 0, 10);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_allocated', $result);

        // Verify updateOrderAllocations was called with per-item format
        $this->assertNotEmpty($callLog, '應呼叫 updateOrderAllocations');
        $call = $callLog[0];
        $this->assertSame(self::PRODUCT_ID, $call['product_id']);

        $allocations = $call['allocations'];
        // After fix: should be per-item format with 4 entries
        // Current bug: [1687 => 11] (all collapsed into single order_id, overwritten)
        $this->assertIsArray($allocations);

        // Check that we have 4 distinct allocation entries (one per variation)
        $objectIds = [];
        if (isset($allocations[0]) && is_array($allocations[0])) {
            // New per-item format
            $this->assertCount(4, $allocations, '應有 4 筆獨立分配（ABCD 各一筆）');
            foreach ($allocations as $alloc) {
                $objectIds[] = $alloc['object_id'];
            }
        } else {
            // Legacy format — this is the bug
            $this->fail('allocations 應使用 per-item 格式，而非 legacy [order_id => qty]');
        }

        $this->assertContains(self::VAR_A, $objectIds);
        $this->assertContains(self::VAR_B, $objectIds);
        $this->assertContains(self::VAR_C, $objectIds);
        $this->assertContains(self::VAR_D, $objectIds);
        $this->assertSame(11, array_sum(array_column($allocations, 'quantity')), '總分配量應為 11');
    }

    /**
     * 1.4 Decision 3 — 用新格式 [{order_id, object_id, quantity}] 呼叫 updateOrderAllocations
     * 斷言 C+D 各建立獨立子訂單 object_id 正確
     */
    public function test_update_order_allocations_per_item_format(): void
    {
        $wpdb = $this->createTrackableWpdb([
            'parent_items' => $this->fourVariantParentItems(),
            'purchased_total' => 11,
            'existing_child_allocated' => 0,
        ]);
        $service = $this->createWriteService($wpdb);

        // New per-item format
        $allocations = [
            ['order_id' => self::PARENT_ORDER_ID, 'object_id' => self::VAR_C, 'quantity' => 3],
            ['order_id' => self::PARENT_ORDER_ID, 'object_id' => self::VAR_D, 'quantity' => 2],
        ];

        $result = $service->updateOrderAllocations(self::PRODUCT_ID, $allocations);

        $this->assertIsArray($result);
        $this->assertTrue($result['success'], '新格式分配應成功');
        $this->assertCount(2, $result['child_orders'], '應建立 2 個子訂單（C 和 D）');

        // Verify both child orders have correct object_ids via insert log
        $orderItemInserts = array_values(array_filter($wpdb->insertLog, function ($log) {
            return $log['table'] === 'wp_fct_order_items';
        }));

        $this->assertCount(2, $orderItemInserts, '應插入 2 筆子訂單項目');

        $insertedObjectIds = array_map(function ($log) {
            return (int) $log['data']['object_id'];
        }, $orderItemInserts);

        $this->assertContains(self::VAR_C, $insertedObjectIds, '其中一筆應為 C (1040)');
        $this->assertContains(self::VAR_D, $insertedObjectIds, '其中一筆應為 D (1041)');
    }

    /**
     * 1.5 Decision 3 — 舊格式 [order_id => qty] 單變體商品仍正常運作
     */
    public function test_update_order_allocations_legacy_format_compat(): void
    {
        $singleVarItem = [
            ['id' => 200, 'order_id' => 500, 'object_id' => 900, 'quantity' => 5, 'line_meta' => '{}', 'unit_price' => 100],
        ];

        $wpdb = $this->createTrackableWpdb([
            'parent_items' => $singleVarItem,
            'purchased_total' => 10,
            'existing_child_allocated' => 0,
        ]);

        // Override queryService for single variation
        $GLOBALS['wpdb'] = $wpdb;
        $queryService = new class extends AllocationQueryService {
            public function getAllVariationIds($variation_id)
            {
                return ['post_id' => 500, 'variation_ids' => [900]];
            }
        };

        $allocationService = new class extends AllocationService {
            public function __construct() {}
            public function syncAllocatedQtyBatch(array $items): void {}
        };
        $batchService = new class($allocationService, $queryService) extends AllocationBatchService {
            public function __construct($allocationService, $queryService)
            {
                parent::__construct($allocationService, $queryService);
            }
        };
        $service = new AllocationWriteService($allocationService, $queryService, $batchService);

        // Legacy format
        $result = $service->updateOrderAllocations(900, [500 => 5]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success'], '舊格式分配應成功');

        $orderItemInsert = null;
        foreach ($wpdb->insertLog as $log) {
            if ($log['table'] === 'wp_fct_order_items') {
                $orderItemInsert = $log['data'];
                break;
            }
        }

        $this->assertNotNull($orderItemInsert);
        // object_id should be automatically resolved from parent item
        $this->assertSame(900, (int) $orderItemInsert['object_id'], '舊格式應自動解析父訂單項目的 object_id');
    }

    /**
     * 1.6 Decision 1 採購池檢核 — 跨變體採購總量=11、已分配 9、嘗試再分 3 應回傳 INSUFFICIENT_STOCK
     */
    public function test_purchased_pool_shared_across_variants(): void
    {
        // Use a single large-quantity item so legacy format doesn't trigger per-item overflow first
        $largeQtyItems = [
            ['id' => 100, 'order_id' => self::PARENT_ORDER_ID, 'object_id' => self::VAR_A, 'quantity' => 100, 'line_meta' => '{}', 'unit_price' => 100],
        ];

        $wpdb = $this->createTrackableWpdb([
            'parent_items' => $largeQtyItems,
            'purchased_total' => 11,
            'existing_child_allocated' => 9,
        ]);
        $service = $this->createWriteService($wpdb);

        // Try to allocate 3 more units (would make total = 12 > purchased = 11)
        // Use legacy format so current code can process it and reach the stock check
        $allocations = [self::PARENT_ORDER_ID => 3];

        $result = $service->updateOrderAllocations(self::PRODUCT_ID, $allocations);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('INSUFFICIENT_STOCK', $result->get_error_code(), '超過採購池應回傳 INSUFFICIENT_STOCK');
    }
}
