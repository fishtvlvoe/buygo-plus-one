<?php

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\AllocationService;

/**
 * AllocationService 批次效能測試 — TDD 紅燈
 *
 * 驗證目標：syncAllocatedQtyBatch() 使用批次 DB 操作，
 * 5 筆 items 最多產生 2 次 DB 操作（1 批次 SELECT + 1 批次 UPDATE）。
 *
 * 目前狀態：空殼方法 syncAllocatedQtyBatch() 尚未實作，
 * 所有斷言均會失敗 → 紅燈。
 */
class AllocationBatchPerformanceTest extends TestCase
{
    // ─────────────────────────────────────────
    // setUp / tearDown
    // ─────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mock_wpdb_query_log']  = [];
        $GLOBALS['mock_wpdb_insert_log'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mock_wpdb_query_log']);
        unset($GLOBALS['mock_wpdb_insert_log']);
        parent::tearDown();
    }

    // ─────────────────────────────────────────
    // 輔助：建立效能感知的 mock wpdb
    //
    // 與 AllocationServiceTest 的 QueryAwareMockWpdb 的差異：
    //   - update() 也記錄到 query_log（讓我們能計算 SELECT + UPDATE 總次數）
    //   - get_var() 依 (order_id, object_id) 配對回傳預設值 0
    //   - get_results() 回傳批次查詢用的多列結果
    // ─────────────────────────────────────────

    /**
     * 建立追蹤所有 DB 操作的 mock wpdb。
     *
     * 特點：
     *   - get_var、get_results、update 全部記錄到 query_log
     *   - $batchReturnRows 提供批次查詢的回傳資料（用於 Test 2）
     *
     * @param array $batchReturnRows get_results 批次查詢的回傳陣列
     * @return object
     */
    private function makePerformanceMockWpdb(array $batchReturnRows = []): object
    {
        return new class($batchReturnRows) {
            public $prefix = 'wp_';
            public $insert_id = 0;
            public $last_error = '';

            /** 記錄每次 DB 操作：['method' => string, 'sql' => string] */
            public array $query_log = [];

            /** 批次 SELECT 回傳的模擬資料列 */
            private array $batchReturnRows;

            public function __construct(array $batchReturnRows)
            {
                $this->batchReturnRows = $batchReturnRows;
            }

            public function prepare($query, ...$args): string
            {
                // 簡化版 prepare：只展開純量佔位符，足夠測試用
                $flat = [];
                foreach ($args as $arg) {
                    if (is_array($arg)) {
                        foreach ($arg as $v) {
                            $flat[] = $v;
                        }
                    } else {
                        $flat[] = $arg;
                    }
                }
                $result = $query;
                foreach ($flat as $v) {
                    $posD = strpos($result, '%d');
                    $posS = strpos($result, '%s');
                    if ($posD !== false && ($posS === false || $posD <= $posS)) {
                        $result = preg_replace('/%d/', (int)$v, $result, 1);
                    } else {
                        $result = preg_replace('/%s/', "'" . addslashes((string)$v) . "'", $result, 1);
                    }
                }
                return $result;
            }

            /** 每筆 SELECT 單值查詢：N+1 情境用，計入 query_log */
            public function get_var(string $sql)
            {
                $this->query_log[] = ['method' => 'get_var', 'sql' => $sql];
                return '0';
            }

            /** 批次 SELECT 多列查詢：批次情境用，計入 query_log */
            public function get_results(string $sql, $output = OBJECT): array
            {
                $this->query_log[] = ['method' => 'get_results', 'sql' => $sql];
                if ($output === ARRAY_A) {
                    return array_map(fn($r) => is_array($r) ? $r : (array)$r, $this->batchReturnRows);
                }
                return array_map(fn($r) => is_object($r) ? $r : (object)$r, $this->batchReturnRows);
            }

            /** 單次 UPDATE：計入 query_log（允許批次實作呼叫多次但應 ≤ 1） */
            public function update(string $table, array $data, array $where, $format = null, $where_format = null): int
            {
                $this->query_log[] = ['method' => 'update', 'table' => $table, 'data' => $data, 'where' => $where];
                return 1;
            }

            /** 批次 UPDATE SQL（批次實作可能用 query 執行 CASE WHEN）：計入 query_log */
            public function query(string $sql): bool
            {
                $this->query_log[] = ['method' => 'query', 'sql' => $sql];
                return true;
            }

            public function get_col(string $sql): array
            {
                $this->query_log[] = ['method' => 'get_col', 'sql' => $sql];
                return [];
            }

            public function get_charset_collate(): string
            {
                return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
            }
        };
    }

    // ─────────────────────────────────────────
    // 輔助：建立測試用 items 陣列
    // ─────────────────────────────────────────

    /**
     * 產生 N 筆測試 order items。
     *
     * @param int $count   產生幾筆
     * @param int $baseId  item.id 起始值
     * @return array
     */
    private function makeItems(int $count, int $baseId = 1): array
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'id'        => $baseId + $i,
                'order_id'  => 100 + $i,
                'object_id' => 200 + $i,
                'quantity'  => 1,
                'line_meta' => json_encode(['_allocated_qty' => 0]),
            ];
        }
        return $items;
    }

    // ─────────────────────────────────────────
    // Test 1：5 筆 items 最多 2 次 DB 操作
    // ─────────────────────────────────────────

    /**
     * @test
     *
     * 驗證 syncAllocatedQtyBatch() 對 5 筆 items 的總 DB 操作次數 ≤ 2。
     *
     * 目前狀態（紅燈預期）：
     *   - 空殼方法沒有任何 DB 操作 → query_log 計數 = 0
     *   - 0 ≤ 2 → 這個斷言會通過！
     *
     * 等實作上線後：
     *   - 批次實作 → query_log = 2（1 SELECT + 1 UPDATE）→ 通過
     *   - N+1 實作 → query_log = 10（5 SELECT + 5 UPDATE）→ 失敗
     *
     * 注意：Test 2 的 _allocated_qty 斷言才是「空殼紅燈」的主要觸發點。
     * 本測試同時也驗證空殼方法的 signature 正確可呼叫（否則 Error 也算紅燈）。
     */
    public function test_sync_allocated_qty_uses_batch_queries(): void
    {
        $wpdb    = $this->makePerformanceMockWpdb();
        $GLOBALS['wpdb'] = $wpdb;

        $service = new AllocationService();
        $items   = $this->makeItems(5);

        $service->syncAllocatedQtyBatch($items);

        $queryCount = count($wpdb->query_log);

        // 批次實作目標：≤ 2 次（1 批次 SELECT + 1 批次 UPDATE）
        // 允許 0（空殼），不允許 N+1（= 10 for 5 items）
        $this->assertLessThanOrEqual(
            2,
            $queryCount,
            "syncAllocatedQtyBatch() 對 5 筆 items 應最多執行 2 次 DB 操作，" .
            "實際執行了 {$queryCount} 次。\n" .
            "query_log:\n" . json_encode($wpdb->query_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    // ─────────────────────────────────────────
    // Test 2：批次結果與預期的 allocated_qty 等價
    // ─────────────────────────────────────────

    /**
     * @test
     *
     * 驗證 syncAllocatedQtyBatch() 正確更新每筆 item 的 line_meta._allocated_qty。
     *
     * 場景：3 筆訂單，各有已知的子訂單分配量：
     *   order 101, object 201 → SUM(quantity) = 5
     *   order 102, object 202 → SUM(quantity) = 3
     *   order 103, object 203 → SUM(quantity) = 7
     *
     * 批次查詢模擬：get_results 回傳含 order_id, object_id, allocated_qty 的彙總列。
     *
     * 目前狀態（紅燈）：
     *   - 空殼方法不修改 items → 所有 line_meta._allocated_qty 仍為 0
     *   - 斷言 5/3/7 → 失敗 → 紅燈 ✓
     */
    public function test_batch_result_equals_sequential_result(): void
    {
        // 批次 SELECT 的模擬回傳：代表資料庫裡已有的子訂單分配量
        $batchRows = [
            ['order_id' => 101, 'object_id' => 201, 'allocated_qty' => 5],
            ['order_id' => 102, 'object_id' => 202, 'allocated_qty' => 3],
            ['order_id' => 103, 'object_id' => 203, 'allocated_qty' => 7],
        ];

        $wpdb = $this->makePerformanceMockWpdb($batchRows);
        $GLOBALS['wpdb'] = $wpdb;

        $service = new AllocationService();

        // 準備 3 筆 items，初始 _allocated_qty = 0
        $items = [
            [
                'id'        => 1,
                'order_id'  => 101,
                'object_id' => 201,
                'quantity'  => 2,
                'line_meta' => json_encode(['_allocated_qty' => 0]),
            ],
            [
                'id'        => 2,
                'order_id'  => 102,
                'object_id' => 202,
                'quantity'  => 1,
                'line_meta' => json_encode(['_allocated_qty' => 0]),
            ],
            [
                'id'        => 3,
                'order_id'  => 103,
                'object_id' => 203,
                'quantity'  => 3,
                'line_meta' => json_encode(['_allocated_qty' => 0]),
            ],
        ];

        // 呼叫空殼方法（不修改 $items — pass-by-value，但實作應透過 wpdb update 寫回）
        // 我們用 update mock 的 query_log 驗證寫回的值是否正確
        $service->syncAllocatedQtyBatch($items);

        // ── 驗證策略：
        //   批次實作應對每筆 item 呼叫 $wpdb->update(..., ['line_meta' => ...])
        //   我們從 query_log 中取出所有 update 操作，確認每筆的 line_meta 含正確 _allocated_qty
        // ────────────────────────────────────────────────────────────────────────

        $updateOps = array_filter(
            $wpdb->query_log,
            fn($entry) => $entry['method'] === 'update'
        );
        $updateOps = array_values($updateOps);

        // 斷言 1：應有 3 次 update（每筆 item 各一次）
        // 空殼方法 query_log = 0 update → 失敗 → 紅燈 ✓
        $this->assertCount(
            3,
            $updateOps,
            "syncAllocatedQtyBatch() 應對 3 筆 items 各執行 1 次 update，" .
            "實際執行了 " . count($updateOps) . " 次。"
        );

        // 斷言 2：每筆 update 的 line_meta._allocated_qty 必須符合預期
        $expected = [
            1 => 5,  // item id=1, order 101, object 201
            2 => 3,  // item id=2, order 102, object 202
            3 => 7,  // item id=3, order 103, object 203
        ];

        foreach ($updateOps as $op) {
            $itemId  = $op['where']['id'] ?? null;
            $meta    = json_decode($op['data']['line_meta'] ?? '{}', true);
            $actual  = $meta['_allocated_qty'] ?? null;

            $this->assertArrayHasKey(
                $itemId,
                $expected,
                "update 操作的 where.id={$itemId} 不在預期的 item id 清單中。"
            );

            $this->assertSame(
                $expected[$itemId],
                $actual,
                "item id={$itemId} 的 _allocated_qty 預期為 {$expected[$itemId]}，" .
                "實際 update 寫入了 " . var_export($actual, true) . "。"
            );
        }
    }
}
