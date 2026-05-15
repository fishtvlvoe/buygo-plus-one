<?php

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\ProductStatsCalculator;
use BuyGoPlus\Services\DebugService;

/**
 * TDD 紅燈測試 — ProductStatsCalculator::calculateAllocatedPerParentOrder()
 *
 * 這批測試在方法尚未實作前應全部 FAIL（紅燈）。
 * 驗證「已分配讀取端 SSOT」的三個核心行為：
 *   1. 按 parent order + variation 分組正確聚合
 *   2. cancelled / refunded 子訂單被排除
 *   3. 空輸入直接回 []，不打 SQL
 *
 * 設計原則：
 *   - 純 PHP + PHPUnit，無 WP runtime
 *   - 替換全域 $wpdb 為可程式化的 FakeWpdb stub
 *   - setUp 前 backup、tearDown 後 restore，避免污染其他測試
 */
class ProductStatsCalculatorAllocatedTest extends TestCase
{
    /** @var object 備份原始全域 $wpdb */
    private $originalWpdb;

    /** @var object 本測試用的 FakeWpdb */
    private $fakeWpdb;

    /** @var ProductStatsCalculator */
    private $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        // 備份原始 $wpdb
        $this->originalWpdb = $GLOBALS['wpdb'];

        // 建立 FakeWpdb（只建立一次 class，避免重複宣告）
        if (!class_exists('BuyGoPlus\Tests\Unit\Services\FakeWpdbForCalculator')) {
            // 用 eval 在 namespace 外建立，避免 class_exists 跨 namespace 問題
        }

        $this->fakeWpdb = new class {
            public string $prefix = 'wp_';
            public string $last_error = '';

            /** 記錄所有被呼叫的 SQL（含 prepare 後的版本） */
            public array $preparedQueries = [];

            /** 下次 get_results 要回傳的 rows */
            public array $nextResults = [];

            /** 記錄 get_results 被呼叫幾次 */
            public int $getResultsCallCount = 0;

            public function prepare(string $query, ...$args): string
            {
                // 簡單替換 %d → 實際值（僅用於測試斷言 SQL 結構）
                $formatted = $query;
                foreach ($args as $arg) {
                    $formatted = preg_replace('/%d/', (string)(int)$arg, $formatted, 1);
                }
                $this->preparedQueries[] = $formatted;
                return $formatted;
            }

            public function get_results(string $query, $output = OBJECT): array
            {
                $this->getResultsCallCount++;
                $rows = $this->nextResults;
                // 每次呼叫後清空（防止跨呼叫污染）
                $this->nextResults = [];

                // 如果呼叫端要 ARRAY_A，把 object 轉成 assoc array
                if (defined('ARRAY_A') && $output === ARRAY_A) {
                    return array_map(function($row) {
                        return is_object($row) ? (array) $row : $row;
                    }, $rows);
                }

                return $rows;
            }

            public function get_var(string $query): ?string { return null; }
            public function get_row(string $query, $output = OBJECT): ?object { return null; }
            public function get_col(string $query, int $col = 0): array { return []; }
        };

        $GLOBALS['wpdb'] = $this->fakeWpdb;

        // 建立 calculator（注入 DebugService stub）
        $debugStub = $this->createStub(DebugService::class);
        $this->calculator = new ProductStatsCalculator($debugStub);
    }

    protected function tearDown(): void
    {
        // 還原原始 $wpdb
        $GLOBALS['wpdb'] = $this->originalWpdb;
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // 1.1 按 parent + variation 正確分組聚合
    // ---------------------------------------------------------------

    /**
     * @test
     * 給定 2 筆 DB rows（parent 1746 → var 1055 qty 1; parent 1747 → var 1055 qty 1），
     * 方法應回傳 [1746 => [1055 => 1], 1747 => [1055 => 1]]。
     */
    public function test_calculateAllocatedPerParentOrder_groups_by_parent_and_variation(): void
    {
        $this->fakeWpdb->nextResults = [
            (object)['parent_id' => '1746', 'variation_id' => '1055', 'allocated_qty' => '1'],
            (object)['parent_id' => '1747', 'variation_id' => '1055', 'allocated_qty' => '1'],
        ];

        $result = $this->calculator->calculateAllocatedPerParentOrder([1746, 1747], [1055]);

        $this->assertSame(
            [1746 => [1055 => 1], 1747 => [1055 => 1]],
            $result,
            'calculateAllocatedPerParentOrder 應依 parent_id + variation_id 建立 nested map'
        );
    }

    // ---------------------------------------------------------------
    // 1.2 cancelled / refunded 被排除（驗 SQL 結構）
    // ---------------------------------------------------------------

    /**
     * @test
     * SQL 必須含 parent_id IS NOT NULL、type = 'split'、
     * status NOT IN ('cancelled', 'refunded') 三個過濾條件。
     */
    public function test_calculateAllocatedPerParentOrder_excludes_cancelled_and_refunded(): void
    {
        // 不需要 DB 結果，只需要方法被呼叫並產生 SQL
        $this->fakeWpdb->nextResults = [];

        $this->calculator->calculateAllocatedPerParentOrder([1746], [1055]);

        // 至少應有一個 prepare 呼叫
        $this->assertNotEmpty(
            $this->fakeWpdb->preparedQueries,
            '給定非空輸入時應打 SQL query'
        );

        $sql = implode(' ', $this->fakeWpdb->preparedQueries);

        $this->assertStringContainsString(
            'parent_id IS NOT NULL',
            $sql,
            'SQL 應過濾 parent_id IS NOT NULL（排除父訂單本身）'
        );

        $this->assertStringContainsStringIgnoringCase(
            "type = 'split'",
            $sql,
            "SQL 應過濾 type = 'split'（只算真正的子訂單）"
        );

        $this->assertStringContainsStringIgnoringCase(
            "cancelled",
            $sql,
            "SQL 應排除 cancelled 狀態"
        );

        $this->assertStringContainsStringIgnoringCase(
            "refunded",
            $sql,
            "SQL 應排除 refunded 狀態"
        );
    }

    // ---------------------------------------------------------------
    // 1.3 空輸入直接回 []，不打 SQL
    // ---------------------------------------------------------------

    /**
     * @test
     * 任一輸入陣列為空時，應立即回 []，不呼叫 $wpdb->get_results。
     */
    public function test_calculateAllocatedPerParentOrder_empty_inputs_return_empty_array(): void
    {
        // Case 1：空 parentOrderIds
        $result1 = $this->calculator->calculateAllocatedPerParentOrder([], [1055]);
        $this->assertSame([], $result1, '空 parentOrderIds 應回 []');
        $this->assertSame(0, $this->fakeWpdb->getResultsCallCount, '空輸入不應打 SQL');

        // Case 2：空 variationIds
        $result2 = $this->calculator->calculateAllocatedPerParentOrder([1746], []);
        $this->assertSame([], $result2, '空 variationIds 應回 []');
        $this->assertSame(0, $this->fakeWpdb->getResultsCallCount, '空輸入不應打 SQL');
    }
}
