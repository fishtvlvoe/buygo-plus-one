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
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // 清理測試過程中設定的 $GLOBALS
        unset($GLOBALS['wpdb']);
    }

    // ----------------------------------------------------------------
    // 輔助方法：建立基礎 wpdb mock（GET_LOCK 永遠失敗）
    // ----------------------------------------------------------------

    private function makeWpdbLockFail(): object
    {
        return new class {
            public $prefix = 'wp_';
            public function prepare($query, ...$args) { return str_replace('%s', "'lock'", $query); }
            public function get_var($sql) { return strpos($sql, 'GET_LOCK(') !== false ? '0' : '0'; }
            public function query($sql) { return true; }
        };
    }

    // ----------------------------------------------------------------
    // 輔助方法：建立基礎 wpdb mock（GET_LOCK 永遠成功，記錄 lockQueries）
    // ----------------------------------------------------------------

    private function makeWpdbLockSucceed(): object
    {
        return new class {
            public $prefix = 'wp_';
            public array $lockQueries = [];

            public function prepare($query, ...$args): string
            {
                foreach ($args as $arg) {
                    foreach ((array) $arg as $value) {
                        $replacement = is_numeric($value) ? (string)(int)$value : "'" . $value . "'";
                        $query = preg_replace('/%[ds]/', $replacement, $query, 1);
                    }
                }
                return $query;
            }

            public function get_var($sql)
            {
                if (strpos($sql, 'GET_LOCK(') !== false) {
                    $this->lockQueries[] = $sql;
                    return '1';
                }
                if (strpos($sql, 'RELEASE_LOCK(') !== false) {
                    return '1';
                }
                return '0';
            }

            public function query($sql) { return true; }
            public function get_results($sql, $output = OBJECT): array { return []; }
        };
    }

    // ----------------------------------------------------------------
    // 測試 1：GET_LOCK 失敗時回傳 allocation_locked 錯誤
    // ----------------------------------------------------------------

    public function test_returns_allocation_locked_when_get_lock_not_acquired(): void
    {
        $GLOBALS['wpdb'] = $this->makeWpdbLockFail();

        $allocationService = new class extends AllocationService { public function __construct() {} };
        $queryService = new class extends AllocationQueryService { public function __construct() {} };
        $batchService = new class($allocationService, $queryService) extends AllocationBatchService {
            public function __construct($a, $q) { parent::__construct($a, $q); }
        };

        // Simple product：getVariationParentId 回傳自身 ID
        $service = new class($allocationService, $queryService, $batchService) extends AllocationWriteService {
            public function __construct($a, $q, $b) { parent::__construct($a, $q, $b); }
            protected function getVariationParentId(int $product_id): int {
                return $product_id; // simple product，直接用自身
            }
        };

        $result = $service->updateOrderAllocations(100, [1 => 1]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('allocation_locked', $result->get_error_code());
    }

    // ----------------------------------------------------------------
    // 測試 2：同一 parent 的不同 variations 共用同一把 lock key
    // ----------------------------------------------------------------

    public function test_variations_of_same_parent_product_share_lock_key(): void
    {
        $GLOBALS['wpdb'] = $this->makeWpdbLockSucceed();

        $allocationService = new class extends AllocationService { public function __construct() {} };
        $queryService = new class extends AllocationQueryService {
            public function __construct() {}
            public function getAllVariationIds($product_id): array
            {
                return ['variation_ids' => [101, 102]];
            }
        };
        $batchService = new class($allocationService, $queryService) extends AllocationBatchService {
            public function __construct($a, $q) { parent::__construct($a, $q); }
        };

        // 匿名子類別 override getVariationParentId，模擬 variation → parent 對應
        $service = new class($allocationService, $queryService, $batchService) extends AllocationWriteService {
            public function __construct($a, $q, $b) { parent::__construct($a, $q, $b); }
            protected function getVariationParentId(int $product_id): int {
                $map = [101 => 1000, 102 => 1000]; // 兩個 variation 都指向 parent 1000
                return $map[$product_id] ?? $product_id;
            }
        };

        $service->updateOrderAllocations(101, [1 => 1]);
        $service->updateOrderAllocations(102, [1 => 1]);

        $this->assertCount(2, $GLOBALS['wpdb']->lockQueries);
        $this->assertStringContainsString('buygo_allocate_1000', $GLOBALS['wpdb']->lockQueries[0]);
        $this->assertStringContainsString('buygo_allocate_1000', $GLOBALS['wpdb']->lockQueries[1]);
    }

    // ----------------------------------------------------------------
    // 測試 3 [12.3]：simple product lock key 使用自身 ID，不會出現 buygo_allocate_0
    // ----------------------------------------------------------------

    public function test_simple_product_uses_own_id_as_lock_key(): void
    {
        $GLOBALS['wpdb'] = $this->makeWpdbLockSucceed();

        $allocationService = new class extends AllocationService { public function __construct() {} };
        $queryService = new class extends AllocationQueryService {
            public function __construct() {}
            public function getAllVariationIds($product_id): array
            {
                return ['variation_ids' => []]; // simple product 沒有 variations
            }
        };
        $batchService = new class($allocationService, $queryService) extends AllocationBatchService {
            public function __construct($a, $q) { parent::__construct($a, $q); }
        };

        // Simple product：getVariationParentId 回傳自身 ID（無 FluentCart variation）
        $service = new class($allocationService, $queryService, $batchService) extends AllocationWriteService {
            public function __construct($a, $q, $b) { parent::__construct($a, $q, $b); }
            protected function getVariationParentId(int $product_id): int {
                return $product_id; // simple product 直接回傳自身
            }
        };

        $service->updateOrderAllocations(500, [1 => 1]);

        $this->assertNotEmpty($GLOBALS['wpdb']->lockQueries,
            '應該有 GET_LOCK 查詢');
        $this->assertStringContainsString('buygo_allocate_500',
            $GLOBALS['wpdb']->lockQueries[0],
            'simple product lock key 應為 buygo_allocate_500，不是 buygo_allocate_0');
        $this->assertStringNotContainsString('buygo_allocate_0',
            $GLOBALS['wpdb']->lockQueries[0],
            'lock key 不應出現 buygo_allocate_0');
    }
}
