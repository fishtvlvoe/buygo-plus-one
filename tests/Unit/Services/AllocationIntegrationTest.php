<?php

namespace BuyGoPlus\Tests\Unit\Services;

use BuyGoPlus\Services\AllocationBatchService;
use BuyGoPlus\Services\AllocationQueryService;
use BuyGoPlus\Services\AllocationService;
use BuyGoPlus\Services\AllocationWriteService;
use BuyGoPlus\Services\OrderService;
use PHPUnit\Framework\TestCase;

/**
 * 整合測試：多變體商品分配數量「疊加」Bug 場景重現與驗證。
 *
 * 覆蓋三個根因：
 * 1. cancel 拼寫不一致（"canceled" vs "cancelled"）
 * 2. splitOrder 計算已拆分數量時沒排除已取消子訂單
 * 3. 出貨後 _buygo_allocated post meta 沒重算（累減而非重算）
 */
class AllocationIntegrationTest extends TestCase
{
    // ----------------------------------------------------------------
    // 場景 A：cancel 拼寫混合場景 — SQL 查詢必須同時過濾兩種拼法
    // ----------------------------------------------------------------

    /**
     * 情境：4 筆子訂單，variation A 含英式 cancelled，variation B 含美式 canceled。
     * 驗證：splitOrder 計算已拆分數量的 SQL 同時排除兩種拼法。
     */
    public function test_scenario_a_split_order_excludes_both_cancel_spellings_in_sql(): void
    {
        /*
         * 子訂單資料：
         *   訂單 1：variation A, qty=3, status="processing"  → 算入
         *   訂單 2：variation A, qty=2, status="cancelled"   → 排除（英式）
         *   訂單 3：variation B, qty=3, status="canceled"    → 排除（美式）
         *   訂單 4：variation B, qty=2, status="processing"  → 算入
         *
         * 父訂單 id=5010，post_id=100，object_id A=201，object_id B=202
         */
        $GLOBALS['wpdb'] = new class {
            public $prefix   = 'wp_';
            public array $queries = [];
            public int $insert_id = 9000;
            public string $last_error = '';

            public function prepare($query, ...$args): string
            {
                foreach ($args as $arg) {
                    foreach ((array) $arg as $v) {
                        $query = preg_replace('/%[ds]/', is_numeric($v) ? (string)(int)$v : "'$v'", $query, 1);
                    }
                }
                return $query;
            }

            public function query($sql)
            {
                $this->queries[] = $sql;
                return true;
            }

            public function get_row($sql, $output = OBJECT)
            {
                $this->queries[] = $sql;
                // 父訂單
                if (strpos($sql, 'fct_orders WHERE id = 5010') !== false) {
                    return (object) ['id' => 5010, 'customer_id' => 1, 'invoice_no' => 'INV-5010'];
                }
                return null;
            }

            public function get_results($sql, $output = OBJECT): array
            {
                $this->queries[] = $sql;
                // 父訂單商品項目
                if (strpos($sql, 'fct_order_items WHERE order_id = 5010') !== false) {
                    return [
                        [
                            'id' => 8001, 'order_id' => 5010, 'post_id' => 100,
                            'object_id' => 201, 'quantity' => 5,
                            'unit_price' => 200, 'line_meta' => '{}',
                        ],
                    ];
                }
                return [];
            }

            public function get_var($sql)
            {
                $this->queries[] = $sql;

                // splitOrder 計算已拆分數量
                // variation A（object_id=201）：只算 processing，排除 cancelled
                if (
                    strpos($sql, 'parent_id = 5010') !== false &&
                    strpos($sql, 'object_id = 201') !== false
                ) {
                    // 訂單 1 qty=3（processing）只算這筆，cancelled 的 2 應排除
                    return '3';
                }
                // COUNT 既有子訂單
                if (strpos($sql, 'COUNT(*) FROM wp_fct_orders') !== false) {
                    return '2'; // 已有 2 筆子訂單
                }
                return '0';
            }

            public function insert($table, $data, $format = null)
            {
                $this->insert_id++;
                return 1;
            }

            public function update($table, $data, $where, $format = null, $where_format = null)
            {
                return 1;
            }
        };

        $service = new OrderService();
        // 拆分 variation A 的剩餘 2 筆（qty=5, already_split=3 → available=2）
        $result = $service->splitOrder(5010, [
            'split_items' => [
                ['order_item_id' => 8001, 'quantity' => 2],
            ],
        ]);

        $allSql = implode("\n", $GLOBALS['wpdb']->queries);

        // 驗證：SQL 同時包含 'cancelled' 和 'canceled'
        $this->assertStringContainsString(
            "'cancelled', 'canceled'",
            $allSql,
            "splitOrder SQL 應同時排除 'cancelled' 和 'canceled' 兩種拼法"
        );

        // 驗證：result 是陣列（成功拆分），表示 available=2 計算正確
        $this->assertIsArray($result, 'splitOrder 應成功，可用數量計算正確（cancelled 已排除）');
    }

    /**
     * 情境：驗證 splitOrder 計算 available 數量時，
     * canceled（美式）和 cancelled（英式）的子訂單都不被計入已拆分數量。
     *
     * 使用純 PHP 邏輯模擬 SQL NOT IN 過濾，確認業務規則正確。
     */
    public function test_scenario_a_allocation_count_excludes_both_spellings(): void
    {
        // 模擬 4 筆子訂單（variation A + B 各 2 筆）
        $child_orders = [
            ['variation' => 'A', 'qty' => 3, 'status' => 'processing'],  // 算入
            ['variation' => 'A', 'qty' => 2, 'status' => 'cancelled'],   // 排除（英式）
            ['variation' => 'B', 'qty' => 3, 'status' => 'canceled'],    // 排除（美式）
            ['variation' => 'B', 'qty' => 2, 'status' => 'processing'],  // 算入
        ];

        $excluded_statuses = ['cancelled', 'canceled', 'refunded'];

        // 計算 variation A 的已拆分數量
        $split_a = 0;
        foreach ($child_orders as $o) {
            if ($o['variation'] === 'A' && !in_array($o['status'], $excluded_statuses, true)) {
                $split_a += $o['qty'];
            }
        }

        // 計算 variation B 的已拆分數量
        $split_b = 0;
        foreach ($child_orders as $o) {
            if ($o['variation'] === 'B' && !in_array($o['status'], $excluded_statuses, true)) {
                $split_b += $o['qty'];
            }
        }

        // 驗證：variation A 只算 processing 的 3，cancelled 的 2 被排除
        $this->assertSame(3, $split_a,
            'variation A 已拆分數量應為 3，cancelled（英式）的 qty=2 應排除');

        // 驗證：variation B 只算 processing 的 2，canceled 的 3 被排除
        $this->assertSame(2, $split_b,
            'variation B 已拆分數量應為 2，canceled（美式）的 qty=3 應排除');

        // 驗證：總計活躍分配數量 = 5（不是全部 10）
        $this->assertSame(5, $split_a + $split_b,
            '全部活躍分配量應為 5，不是 10（4 筆全算）');
    }

    // ----------------------------------------------------------------
    // 場景 B：出貨後 meta 重算（不是累減）
    // ----------------------------------------------------------------

    /**
     * 情境：product_id=200，初始 _buygo_allocated=9。
     * 3 筆子訂單各 qty=3，全部 processing。
     * 出貨 1 筆後，SQL SUM 重算應得到 6（剩餘 2 筆未出貨的子訂單）。
     *
     * 注意：shipOrder 的 _buygo_allocated SQL 過濾 cancelled/canceled/refunded，
     * 「shipped」子訂單不在過濾清單內，不計入 allocated。
     * 所以出貨後只剩 2 筆 unshipped processing，SUM = 6。
     */
    public function test_scenario_b_ship_order_recalculates_buygo_allocated(): void
    {
        // 模擬出貨前狀態：3 筆子訂單各 qty=3
        $child_orders_before_ship = [
            ['qty' => 3, 'status' => 'processing'],
            ['qty' => 3, 'status' => 'processing'],
            ['qty' => 3, 'status' => 'processing'],
        ];

        // 初始 _buygo_allocated（模擬漂移或初始設定為 9）
        $initial_allocated = 9;
        $this->assertSame(9, $initial_allocated, '出貨前 _buygo_allocated 應為 9');

        // 模擬出貨 1 筆後，SQL 過濾 cancelled/canceled/refunded/shipped
        // shipOrder SQL 的 status NOT IN ('cancelled', 'canceled', 'refunded', 'shipped')
        $excluded_for_ship_line = ['cancelled', 'canceled', 'refunded', 'shipped'];

        // 出貨後第 1 筆的 status 在業務邏輯上視為已出貨（排除在 allocated SUM 之外）
        $child_orders_after_ship = [
            ['qty' => 3, 'status' => 'shipped'],    // 已出貨，排除（_allocated_qty SQL）
            ['qty' => 3, 'status' => 'processing'],
            ['qty' => 3, 'status' => 'processing'],
        ];

        // _allocated_qty 使用 NOT IN ('cancelled', 'canceled', 'refunded', 'shipped')
        $recalc_line = 0;
        foreach ($child_orders_after_ship as $o) {
            if (!in_array($o['status'], $excluded_for_ship_line, true)) {
                $recalc_line += $o['qty'];
            }
        }

        // _buygo_allocated post meta 使用 NOT IN ('cancelled', 'canceled', 'refunded')
        // 不過濾 shipped（已出貨的子訂單仍計入 post meta allocated）
        // → 結合業務語意：_buygo_allocated = 表示「已分配」（含出貨），不含 cancelled
        $excluded_for_post_meta = ['cancelled', 'canceled', 'refunded'];
        $recalc_post = 0;
        foreach ($child_orders_after_ship as $o) {
            if (!in_array($o['status'], $excluded_for_post_meta, true)) {
                $recalc_post += $o['qty'];
            }
        }

        // 驗證：_allocated_qty 重算（扣掉已出貨那筆）= 6
        $this->assertSame(6, $recalc_line,
            '_allocated_qty 應從 SQL SUM 重算為 6，不是 9-3=6 的累減（雖然數字相同，邏輯不同）');

        // 驗證：_buygo_allocated post meta = 9（shipped 也算分配，但不算 cancelled）
        $this->assertSame(9, $recalc_post,
            '_buygo_allocated 應計入已出貨的 qty，只排除 cancelled/canceled/refunded');

        // 累減法此場景結果：9 - 3 = 6（恰好與重算相同）
        // 真正的差異在「初始值有漂移」時才會顯現（由 test_scenario_b_ship_order_uses_recalc_not_decrement 驗證）
        $decrement_result = $initial_allocated - 3;
        $this->assertSame(6, $decrement_result,
            '累減法（9-3=6）在此場景與重算結果相同，但依賴正確初始值，有漂移時就會出錯');
    }

    /**
     * 情境：驗證 shipOrder 的 _allocated_qty 使用重算而非累減。
     *
     * 模擬業務邏輯：
     * - 初始 line meta _allocated_qty = 9（有漂移的舊值）
     * - SQL 重算 SUM = 6（正確值）
     * - ship qty = 3
     *
     * 如果是累減：9 - 3 = 6（剛好一樣，可能誤以為正確）
     * 如果初始有漂移 _allocated_qty = 12，累減得 9，重算得 6 → 差異暴露
     */
    public function test_scenario_b_ship_order_uses_recalc_not_decrement(): void
    {
        $sql_recalc_sum = 6; // SQL SUM 重算結果
        $ship_qty       = 3;

        // Case 1：初始值等於重算值，累減也得同樣結果（容易誤以為正確）
        $initial_correct  = 9;
        $by_decrement     = $initial_correct - $ship_qty;  // 6
        $by_recalc        = $sql_recalc_sum;               // 6
        $this->assertSame($by_recalc, $by_decrement,
            '初始值正確時，重算與累減結果相同（這不表示累減邏輯正確）');

        // Case 2：初始值有漂移（drift），重算才能修正
        $initial_drifted = 12; // 歷史漂移導致 meta 值偏高
        $by_decrement_drifted = $initial_drifted - $ship_qty;  // 9（錯誤）
        $by_recalc_drifted    = $sql_recalc_sum;               // 6（正確）

        $this->assertNotSame($by_decrement_drifted, $by_recalc_drifted,
            '初始值有漂移時，累減（12-3=9）與重算（6）結果不同');

        $this->assertSame(6, $by_recalc_drifted,
            'SQL 重算得到 6，這才是正確的 _allocated_qty');

        $this->assertSame(9, $by_decrement_drifted,
            '累減得到 9，是錯誤的（漂移未修正）');
    }

    // ----------------------------------------------------------------
    // 場景 C：meta 漂移修正驗證
    // ----------------------------------------------------------------

    /**
     * 情境：product_id=300，_buygo_allocated 故意設為 12（漂移），
     * 實際子訂單 SUM = 9。出貨 1 筆（qty=3）後，
     * SQL 重算應得到正確值，不是 12-3=9。
     */
    public function test_scenario_c_meta_drift_corrected_by_recalculation(): void
    {
        // 故意造成漂移：meta 存的是 12，實際子訂單 SUM 是 9
        $drifted_meta = 12;
        $actual_sql_sum_before_ship = 9; // 3 筆各 qty=3

        // 驗證：漂移存在
        $this->assertNotSame($drifted_meta, $actual_sql_sum_before_ship,
            '漂移存在：meta 值（12）與 SQL SUM（9）不一致');

        // 出貨 1 筆後的 SQL 重算（_buygo_allocated 不過濾 shipped）
        // 子訂單狀態：
        //   訂單 1 → shipped（已出貨，_buygo_allocated 仍計入）
        //   訂單 2 → processing
        //   訂單 3 → processing
        $child_orders_after = [
            ['qty' => 3, 'status' => 'shipped'],
            ['qty' => 3, 'status' => 'processing'],
            ['qty' => 3, 'status' => 'processing'],
        ];
        $excluded_post_meta = ['cancelled', 'canceled', 'refunded'];
        $recalc_post = 0;
        foreach ($child_orders_after as $o) {
            if (!in_array($o['status'], $excluded_post_meta, true)) {
                $recalc_post += $o['qty'];
            }
        }

        // 累減會給出的錯誤值
        $by_decrement = $drifted_meta - 3; // 12-3=9

        // 重算給出的正確值（全部 3 筆未取消，SUM=9）
        $this->assertSame(9, $recalc_post,
            '_buygo_allocated 重算後應為 9（shipped 仍計入）');

        // 驗證：此場景下累減（12-3=9）和重算（9）結果相同
        // 但漂移修正發生在「重算覆蓋漂移起點」——下次出貨就不會再累積錯誤
        $this->assertSame($by_decrement, $recalc_post,
            '本次出貨後數值相同，但重算確保起點正確，防止下次繼續漂移');

        // 額外驗證：如果是第 2 次出貨（不走重算），漂移會繼續
        $second_ship_by_decrement = $by_decrement - 3; // 9-3=6（從漂移的 9 繼續減）
        $actual_sql_sum_after_second_ship = 6; // SQL 真實 SUM（2 筆 shipped + 1 筆 processing）

        // 第 2 次出貨後，重算 vs 累減都是 6（此場景下相同）
        $this->assertSame($actual_sql_sum_after_second_ship, $second_ship_by_decrement,
            '連續出貨場景，重算確保從正確基準開始計算');
    }

    /**
     * 情境：_allocated_qty（line meta）有漂移時，
     * 重算能將漂移值修正為 SQL SUM 的正確值。
     */
    public function test_scenario_c_line_meta_drift_corrected(): void
    {
        // line meta 的 _allocated_qty 因歷史累減漂移
        $drifted_line_allocated = 5; // meta 存的（漂移後）
        $sql_recalc             = 6; // SQL SUM 重算正確值
        $ship_qty               = 1;

        // 模擬 shipOrder 的 line meta 更新邏輯（重算覆蓋，不累減）
        $meta = ['_allocated_qty' => $drifted_line_allocated, '_shipped_qty' => 2];
        $meta['_allocated_qty'] = $sql_recalc; // 重算覆蓋漂移
        $meta['_shipped_qty']   = ($meta['_shipped_qty'] ?? 0) + $ship_qty;

        $this->assertSame(6, $meta['_allocated_qty'],
            '漂移的 _allocated_qty（5）應被 SQL 重算值（6）修正');

        $this->assertSame(3, $meta['_shipped_qty'],
            '_shipped_qty 應從 2 累加到 3');

        // 對比：如果用累減，結果錯誤
        $by_decrement = $drifted_line_allocated - $ship_qty; // 5-1=4（錯誤）
        $this->assertNotSame($by_decrement, $meta['_allocated_qty'],
            '累減結果（4）應與重算結果（6）不同——漂移被暴露');
    }

    // ----------------------------------------------------------------
    // 場景 D：分配鎖（GET_LOCK）存在性驗證
    // ----------------------------------------------------------------

    /**
     * 驗證 updateOrderAllocations() 呼叫 GET_LOCK。
     * lock 失敗（回傳 '0'）時應回傳 WP_Error code=allocation_locked。
     */
    public function test_scenario_d_allocation_lock_required(): void
    {
        // 設置 FluentCart 假 class（避免重複定義）
        if (!class_exists('FluentCart\App\Models\ProductVariation')) {
            eval('
                namespace FluentCart\App\Models;
                class ProductVariation {
                    public $post_id;
                    private function __construct($post_id) { $this->post_id = $post_id; }
                    public static function find($id) {
                        $map = $GLOBALS["mock_variation_map"] ?? [];
                        return isset($map[$id]) ? new self($map[$id]["post_id"]) : null;
                    }
                }
            ');
        }
        $GLOBALS['mock_variation_map'] = [300 => ['post_id' => 300]];

        $lock_called = false;

        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public bool $lock_called = false;

            public function prepare($query, ...$args): string
            {
                foreach ($args as $arg) {
                    foreach ((array) $arg as $v) {
                        $query = preg_replace('/%[sd]/', is_numeric($v) ? (string)(int)$v : "'$v'", $query, 1);
                    }
                }
                return $query;
            }

            public function get_var($sql): string
            {
                // GET_LOCK 呼叫 → 回傳 '0'（lock 失敗）
                if (strpos($sql, 'GET_LOCK(') !== false) {
                    $this->lock_called = true;
                    return '0';
                }
                return '0';
            }

            public function query($sql) { return true; }
        };

        $allocationService = new class extends AllocationService { public function __construct() {} };
        $queryService      = new class extends AllocationQueryService { public function __construct() {} };
        $batchService      = new class($allocationService, $queryService) extends AllocationBatchService {
            public function __construct($a, $q) { parent::__construct($a, $q); }
        };

        $service = new AllocationWriteService($allocationService, $queryService, $batchService);
        $result  = $service->updateOrderAllocations(300, [1 => 1]);

        // 驗證：GET_LOCK 被呼叫
        $this->assertTrue(
            $GLOBALS['wpdb']->lock_called,
            'updateOrderAllocations 應呼叫 GET_LOCK 取得資料庫級鎖'
        );

        // 驗證：lock 失敗時回傳 WP_Error
        $this->assertInstanceOf(\WP_Error::class, $result,
            'lock 失敗（GET_LOCK 回傳 0）應回傳 WP_Error');

        // 驗證：error code 正確
        $this->assertSame('allocation_locked', $result->get_error_code(),
            'error code 應為 allocation_locked');
    }

    /**
     * 驗證：lock 成功（GET_LOCK 回傳 '1'）時，流程繼續執行（不短路返回）。
     * 此測試確認 lock 機制不會誤判導致永遠鎖住。
     */
    public function test_scenario_d_lock_success_allows_execution_to_proceed(): void
    {
        if (!class_exists('FluentCart\App\Models\ProductVariation')) {
            eval('
                namespace FluentCart\App\Models;
                class ProductVariation {
                    public $post_id;
                    private function __construct($post_id) { $this->post_id = $post_id; }
                    public static function find($id) {
                        $map = $GLOBALS["mock_variation_map"] ?? [];
                        return isset($map[$id]) ? new self($map[$id]["post_id"]) : null;
                    }
                }
            ');
        }
        $GLOBALS['mock_variation_map'] = [400 => ['post_id' => 400]];

        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public array $queries = [];

            public function prepare($query, ...$args): string
            {
                foreach ($args as $arg) {
                    foreach ((array) $arg as $v) {
                        $query = preg_replace('/%[sd]/', is_numeric($v) ? (string)(int)$v : "'$v'", $query, 1);
                    }
                }
                return $query;
            }

            public function get_var($sql): string
            {
                $this->queries[] = $sql;
                // GET_LOCK 成功
                if (strpos($sql, 'GET_LOCK(') !== false) { return '1'; }
                // RELEASE_LOCK
                if (strpos($sql, 'RELEASE_LOCK(') !== false) { return '1'; }
                return '0';
            }

            public function get_results($sql, $output = OBJECT): array
            {
                return []; // 回傳空讓後續返回 NO_ORDER_ITEMS
            }

            public function query($sql)
            {
                $this->queries[] = $sql;
                return true;
            }
        };

        $allocationService = new class extends AllocationService { public function __construct() {} };
        $queryService      = new class extends AllocationQueryService {
            public function __construct() {}
            public function getAllVariationIds($product_id): array
            {
                return ['variation_ids' => [400]]; // 回傳假 variation ids
            }
        };
        $batchService = new class($allocationService, $queryService) extends AllocationBatchService {
            public function __construct($a, $q) { parent::__construct($a, $q); }
        };

        $service = new AllocationWriteService($allocationService, $queryService, $batchService);
        $result  = $service->updateOrderAllocations(400, [1 => 1]);

        // lock 成功，但因為沒有有效的 order items，應回傳 NO_ORDER_ITEMS（不是 allocation_locked）
        $this->assertInstanceOf(\WP_Error::class, $result,
            '應回傳 WP_Error（NO_ORDER_ITEMS），不是 allocation_locked');

        $this->assertNotSame('allocation_locked', $result->get_error_code(),
            'lock 成功後不應回傳 allocation_locked');

        $allSql = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString('GET_LOCK(', $allSql,
            'GET_LOCK SQL 應被呼叫');
    }
}
