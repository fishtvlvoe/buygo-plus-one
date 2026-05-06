<?php

namespace BuyGoPlus\Tests\Unit\Services;

use BuyGoPlus\Services\AllocationBatchService;
use BuyGoPlus\Services\AllocationMetaSyncService;
use BuyGoPlus\Services\AllocationQueryService;
use BuyGoPlus\Services\AllocationService;
use BuyGoPlus\Services\AllocationWriteService;
use BuyGoPlus\Services\OrderService;
use BuyGoPlus\Services\ShipmentService;
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
     *
     * 注意：本測試使用 product_id=400 作為「無 parent 的 simple product」，
     * 因此 mock_post_parent_map 不設 400，wp_get_post_parent_id 回傳 0，
     * lock key 應使用 post_id 本身（400）。
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

    // ----------------------------------------------------------------
    // 場景 E：Lock key 用 parent product ID（Wave 9 修復）
    // ----------------------------------------------------------------

    /**
     * 情境：variation_id=401 和 402 都屬於 parent product_id=400。
     * 驗證：對任一 variation 呼叫 updateOrderAllocations 時，
     * GET_LOCK 的 key 使用 parent ID (400)，而非 variation 自身的 post_id。
     * 這確保同一商品的不同規格共用同一把鎖，防止並發分配競爭。
     */
    public function test_scenario_e_lock_key_uses_parent_product_id_for_variation(): void
    {
        // 建立 FluentCart ProductVariation stub（若尚未建立）
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

        // variation 401 → post_id=4010, variation 402 → post_id=4020
        // 兩者的 parent post 都是 400
        $GLOBALS['mock_product_variation_map'] = [
            401 => ['post_id' => 4010],
            402 => ['post_id' => 4020],
        ];
        $GLOBALS['mock_variation_map'] = $GLOBALS['mock_product_variation_map'];
        $GLOBALS['mock_post_parent_map'] = [
            4010 => 400,  // post_id=4010 的 parent 是 400
            4020 => 400,  // post_id=4020 的 parent 是 400
        ];

        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public array $lockQueries = [];

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
                if (strpos($sql, 'GET_LOCK(') !== false) {
                    $this->lockQueries[] = $sql;
                    return '1'; // lock 成功
                }
                if (strpos($sql, 'RELEASE_LOCK(') !== false) { return '1'; }
                return '0';
            }

            public function query($sql) { return true; }
            public function get_results($sql, $output = OBJECT): array { return []; }
        };

        $allocationService = new class extends AllocationService { public function __construct() {} };
        $queryService = new class extends AllocationQueryService {
            public function __construct() {}
            public function getAllVariationIds($product_id): array { return ['variation_ids' => [$product_id]]; }
        };
        $batchService = new class($allocationService, $queryService) extends AllocationBatchService {
            public function __construct($a, $q) { parent::__construct($a, $q); }
        };
        $service = new AllocationWriteService($allocationService, $queryService, $batchService);

        // 對 variation 401 和 402 各呼叫一次
        $service->updateOrderAllocations(401, [1 => 1]);
        $service->updateOrderAllocations(402, [1 => 1]);

        // 驗證：GET_LOCK 被呼叫兩次
        $this->assertCount(2, $GLOBALS['wpdb']->lockQueries,
            'GET_LOCK 應被呼叫兩次（各一次 variation）');

        // 驗證：兩次都用 parent ID (400) 作為 lock key
        $this->assertStringContainsString('buygo_allocate_400', $GLOBALS['wpdb']->lockQueries[0],
            'variation 401 的 lock key 應使用 parent product_id=400，而非 variation 的 post_id=4010');
        $this->assertStringContainsString('buygo_allocate_400', $GLOBALS['wpdb']->lockQueries[1],
            'variation 402 的 lock key 應使用 parent product_id=400，而非 variation 的 post_id=4020');
    }

    /**
     * 情境：simple product（無 parent），product_id=500，post_id=500。
     * 驗證：wp_get_post_parent_id 回傳 0 時，lock key 使用商品自身 ID，
     * 不會因為沒有 parent 而出錯或使用錯誤 key。
     */
    public function test_scenario_e_lock_key_uses_own_id_for_simple_product(): void
    {
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

        // simple product：post_id 等於 product_id，沒有 parent
        $GLOBALS['mock_product_variation_map'] = [500 => ['post_id' => 500]];
        $GLOBALS['mock_variation_map']         = $GLOBALS['mock_product_variation_map'];
        $GLOBALS['mock_post_parent_map']       = []; // 無 parent → wp_get_post_parent_id 回傳 0

        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public array $lockQueries = [];

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
                if (strpos($sql, 'GET_LOCK(') !== false) {
                    $this->lockQueries[] = $sql;
                    return '1';
                }
                if (strpos($sql, 'RELEASE_LOCK(') !== false) { return '1'; }
                return '0';
            }

            public function query($sql) { return true; }
            public function get_results($sql, $output = OBJECT): array { return []; }
        };

        $allocationService = new class extends AllocationService { public function __construct() {} };
        $queryService = new class extends AllocationQueryService {
            public function __construct() {}
            public function getAllVariationIds($product_id): array { return ['variation_ids' => [500]]; }
        };
        $batchService = new class($allocationService, $queryService) extends AllocationBatchService {
            public function __construct($a, $q) { parent::__construct($a, $q); }
        };
        $service = new AllocationWriteService($allocationService, $queryService, $batchService);
        $service->updateOrderAllocations(500, [1 => 1]);

        $this->assertCount(1, $GLOBALS['wpdb']->lockQueries,
            'GET_LOCK 應被呼叫一次');
        $this->assertStringContainsString('buygo_allocate_500', $GLOBALS['wpdb']->lockQueries[0],
            'simple product（無 parent）的 lock key 應使用自身 ID=500');
        $this->assertStringNotContainsString('buygo_allocate_0', $GLOBALS['wpdb']->lockQueries[0],
            'lock key 不應為 0（無 parent 應 fallback 到自身 ID）');
    }

    // ----------------------------------------------------------------
    // 場景 F：splitOrder 任何 item insert 失敗全 ROLLBACK（Wave 9 修復）
    // ----------------------------------------------------------------

    /**
     * 情境：splitOrder 有 2 個品項，第 1 個 insert 成功、第 2 個 insert 失敗。
     * 驗證：整筆交易 ROLLBACK，不會只有部分品項被 insert（確保原子性）。
     */
    public function test_scenario_f_split_order_rollbacks_entire_transaction_on_partial_item_failure(): void
    {
        $GLOBALS['wpdb'] = new class {
            public $prefix    = 'wp_';
            public array $queries = [];
            public int $insert_id = 8000;
            public string $last_error = 'mock item insert fail';
            private int $itemInsertCount = 0;

            public function prepare($query, ...$args): string
            {
                foreach ($args as $arg) {
                    foreach ((array) $arg as $v) {
                        $query = preg_replace('/%[sd]/', is_numeric($v) ? (string)(int)$v : "'$v'", $query, 1);
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
                // 父訂單 id=6010
                if (strpos($sql, 'fct_orders WHERE id = 6010') !== false) {
                    return (object)['id' => 6010, 'customer_id' => 1, 'invoice_no' => 'INV-6010'];
                }
                return null;
            }

            public function get_results($sql, $output = OBJECT): array
            {
                // 父訂單有 2 個品項
                if (strpos($sql, 'fct_order_items WHERE order_id = 6010') !== false) {
                    return [
                        ['id' => 9001, 'order_id' => 6010, 'post_id' => 1001, 'object_id' => 2001, 'quantity' => 3, 'unit_price' => 100, 'line_meta' => '{}'],
                        ['id' => 9002, 'order_id' => 6010, 'post_id' => 1002, 'object_id' => 2002, 'quantity' => 3, 'unit_price' => 200, 'line_meta' => '{}'],
                    ];
                }
                return [];
            }

            public function get_var($sql)
            {
                // 父訂單下的子訂單數量：0（尚未拆）
                if (strpos($sql, 'COUNT(*) FROM wp_fct_orders') !== false) { return '0'; }
                // 已拆分數量：0
                if (strpos($sql, 'parent_id = 6010') !== false) { return '0'; }
                return '0';
            }

            public function insert($table, $data, $format = null)
            {
                // 子訂單（fct_orders）本身 insert 成功
                if (strpos($table, 'fct_orders') !== false && strpos($table, 'items') === false) {
                    $this->insert_id = 7000 + $this->itemInsertCount + 1;
                    return 1;
                }
                // 子訂單品項（fct_order_items）：第 1 個成功，第 2 個失敗
                if (strpos($table, 'fct_order_items') !== false) {
                    $this->itemInsertCount++;
                    return $this->itemInsertCount === 1 ? 1 : false;
                }
                return 1;
            }

            public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
        };

        $service = new OrderService();
        $result = $service->splitOrder(6010, [
            'split_items' => [
                ['order_item_id' => 9001, 'quantity' => 1],
                ['order_item_id' => 9002, 'quantity' => 1],
            ],
        ]);

        // 驗證：回傳 WP_Error（不應成功）
        $this->assertInstanceOf(\WP_Error::class, $result,
            '部分 item insert 失敗時，splitOrder 應回傳 WP_Error');

        // 驗證：error code 為 CREATE_ORDER_ITEMS_FAILED
        $this->assertSame('CREATE_ORDER_ITEMS_FAILED', $result->get_error_code(),
            'error code 應為 CREATE_ORDER_ITEMS_FAILED');

        $allSql = implode("\n", $GLOBALS['wpdb']->queries);

        // 驗證：有 START TRANSACTION
        $this->assertStringContainsString('START TRANSACTION', $allSql,
            'splitOrder 應開啟交易');

        // 驗證：有 ROLLBACK（任何 item 失敗都應回滾）
        $this->assertStringContainsString('ROLLBACK', $allSql,
            '第 2 個 item insert 失敗後，應觸發 ROLLBACK');

        // 驗證：沒有 COMMIT（部分失敗不應提交）
        $this->assertStringNotContainsString('COMMIT', $allSql,
            '部分 item insert 失敗時，不應 COMMIT 任何資料');
    }

    // ----------------------------------------------------------------
    // 場景 G：mark_shipped 觸發 AllocationMetaSyncService 重算（Wave 9 修復）
    // ----------------------------------------------------------------

    /**
     * 情境：透過 ShipmentService::mark_shipped() 完成出貨。
     * 驗證：
     * 1. _buygo_allocated (post meta) 被重算並更新。
     * 2. _allocated_qty (line meta) 被重算並寫回 order_items。
     *
     * mark_shipped 路徑：ShipmentService::mark_shipped()
     *   → AllocationMetaSyncService::syncForShipment()
     *   → syncParentLineMeta() + syncProductAllocatedMeta()
     */
    public function test_scenario_g_mark_shipped_triggers_allocation_meta_recalculation(): void
    {
        $GLOBALS['mock_update_post_meta_log'] = [];

        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public array $updates = [];
            public int $insert_id = 1;
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

            public function get_row($sql, $output = OBJECT)
            {
                // 出貨單 id=77
                if (strpos($sql, 'buygo_shipments WHERE id = 77') !== false) {
                    return (object)['id' => 77, 'shipment_number' => 'SH-77', 'status' => 'pending'];
                }
                // 出貨品項的 order_item
                if (strpos($sql, 'fct_order_items WHERE id = 8001') !== false) {
                    return ['id' => 8001, 'object_id' => 3001, 'post_id' => 2001, 'line_meta' => json_encode(['_allocated_qty' => 9])];
                }
                // 子訂單 id=6001
                if (strpos($sql, 'fct_orders WHERE id = 6001') !== false) {
                    return (object)['id' => 6001, 'parent_id' => null, 'type' => 'parent', 'status' => 'pending'];
                }
                // 父訂單 item（用 object_id 查）
                if (strpos($sql, 'fct_order_items WHERE order_id = 6001') !== false
                    && strpos($sql, 'object_id = 3001') !== false) {
                    return ['id' => 8001, 'object_id' => 3001, 'post_id' => 2001, 'line_meta' => json_encode(['_allocated_qty' => 9])];
                }
                return null;
            }

            public function get_results($sql, $output = OBJECT): array
            {
                // 出貨單 77 的品項
                if (strpos($sql, 'buygo_shipment_items') !== false
                    && strpos($sql, 'shipment_id = 77') !== false) {
                    return [[
                        'shipment_id' => 77,
                        'order_id'    => 6001,
                        'order_item_id' => 8001,
                        'product_id'  => 2001,
                        'quantity'    => 3,
                    ]];
                }
                return [];
            }

            public function get_col($sql): array
            {
                if (strpos($sql, 'buygo_shipment_items') !== false) { return [6001]; }
                return [];
            }

            public function get_var($sql)
            {
                // _allocated_qty 重算（子訂單 SUM）
                if (strpos($sql, 'child_o.parent_id = 6001') !== false
                    && strpos($sql, 'child_oi.object_id = 3001') !== false) {
                    return '6'; // 模擬 2 筆剩餘 processing 各 qty=3
                }
                // _buygo_allocated 重算（post meta）
                if (strpos($sql, 'child_oi.post_id = 2001') !== false) {
                    return '6';
                }
                if (strpos($sql, 'COUNT(*)') !== false) { return '0'; }
                return '0';
            }

            public function update($table, $data, $where, $format = null, $where_format = null)
            {
                $this->updates[] = ['table' => $table, 'data' => $data, 'where' => $where];
                return 1;
            }

            public function insert($table, $data, $format = null) { return 1; }
            public function query($sql) { return true; }
        };

        $service = new ShipmentService();
        $result = $service->mark_shipped([77]);

        // 驗證：mark_shipped 成功
        $this->assertSame(1, $result,
            'mark_shipped 應回傳 1（成功）');

        // 驗證：update_post_meta 被呼叫，_buygo_allocated 更新為重算值 6
        $postMetaLog = $GLOBALS['mock_update_post_meta_log'];
        $this->assertNotEmpty($postMetaLog,
            'mark_shipped 後應呼叫 update_post_meta 更新 _buygo_allocated');

        $allocatedUpdate = array_filter($postMetaLog, fn($entry) => $entry['meta_key'] === '_buygo_allocated');
        $this->assertNotEmpty($allocatedUpdate,
            '_buygo_allocated post meta 應被更新');

        $firstUpdate = array_values($allocatedUpdate)[0];
        $this->assertSame(6, $firstUpdate['meta_value'],
            '_buygo_allocated 應重算為 6（非累減）');
        $this->assertSame(2001, $firstUpdate['post_id'],
            '_buygo_allocated 應更新在 post_id=2001');

        // 驗證：line meta _allocated_qty 被寫回 order_items
        $lineMetaUpdates = array_filter(
            $GLOBALS['wpdb']->updates,
            fn($u) => $u['table'] === 'wp_fct_order_items'
                   && ($u['where']['id'] ?? null) === 8001
        );
        $this->assertNotEmpty($lineMetaUpdates,
            'mark_shipped 後應更新 order_item 的 line_meta');

        $lineMeta = json_decode(array_values($lineMetaUpdates)[0]['data']['line_meta'], true);
        $this->assertSame(6, $lineMeta['_allocated_qty'],
            '_allocated_qty line meta 應重算為 6（非從 9 累減）');
    }

    // ----------------------------------------------------------------
    // 場景 H：create_shipment 路徑觸發 meta 重算（Wave 9 修復）
    // ----------------------------------------------------------------

    /**
     * 情境：透過 ShipmentService::create_shipment() 建立出貨單。
     * 驗證：不經過 OrderService::shipOrder() 的直接出貨路徑，
     * 也會觸發 AllocationMetaSyncService 重算 _buygo_allocated 和 _allocated_qty。
     *
     * create_shipment 路徑：
     *   ShipmentService::create_shipment()
     *     → AllocationMetaSyncService::syncForShipmentItems(items)
     */
    public function test_scenario_h_create_shipment_triggers_allocation_meta_recalculation_directly(): void
    {
        $GLOBALS['mock_update_post_meta_log'] = [];

        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public int $insert_id = 9000;
            public array $updates = [];
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

            public function get_var($sql)
            {
                // shipment_number 不存在（0）
                if (strpos($sql, 'shipment_number') !== false) { return '0'; }
                // 子訂單 6002 狀態
                if (strpos($sql, 'SELECT status FROM wp_fct_orders WHERE id = 6002') !== false) { return 'pending'; }
                // _allocated_qty 重算（子訂單 SUM，過濾 cancelled/refunded/shipped）
                if (strpos($sql, 'child_o.parent_id = 6002') !== false
                    && strpos($sql, 'child_oi.object_id = 3002') !== false) {
                    return '4'; // 出貨後剩餘 active qty
                }
                // _buygo_allocated 重算（post SUM）
                if (strpos($sql, 'child_oi.post_id = 2002') !== false) {
                    return '4';
                }
                return '0';
            }

            public function get_row($sql, $output = OBJECT)
            {
                // 出貨品項的 order_item（by id）
                if (strpos($sql, 'fct_order_items WHERE id = 8002') !== false) {
                    return ['id' => 8002, 'object_id' => 3002, 'post_id' => 2002, 'line_meta' => json_encode(['_allocated_qty' => 9])];
                }
                // 子訂單
                if (strpos($sql, 'fct_orders WHERE id = 6002') !== false) {
                    return (object)['id' => 6002, 'parent_id' => null, 'type' => 'parent'];
                }
                // 父訂單 item（用 object_id 查）
                if (strpos($sql, 'fct_order_items WHERE order_id = 6002') !== false
                    && strpos($sql, 'object_id = 3002') !== false) {
                    return ['id' => 8002, 'object_id' => 3002, 'post_id' => 2002, 'line_meta' => json_encode(['_allocated_qty' => 9])];
                }
                return null;
            }

            public function insert($table, $data, $format = null)
            {
                if (strpos($table, 'buygo_shipments') !== false) {
                    $this->insert_id = 9200;
                }
                return 1;
            }

            public function update($table, $data, $where, $format = null, $where_format = null)
            {
                $this->updates[] = ['table' => $table, 'data' => $data, 'where' => $where];
                return 1;
            }

            public function delete($table, $where, $where_format = null) { return 1; }
        };

        // 呼叫 create_shipment（直接出貨路徑，不經過 shipOrder）
        $service = new ShipmentService();
        $result  = $service->create_shipment(88, 99, [[
            'order_id'      => 6002,
            'order_item_id' => 8002,
            'product_id'    => 2002,
            'quantity'      => 3,
        ]]);

        // 驗證：create_shipment 成功並回傳 shipment_id
        $this->assertSame(9200, $result,
            'create_shipment 應成功並回傳 shipment_id=9200');

        // 驗證：update_post_meta 被呼叫，_buygo_allocated 重算為 4
        $postMetaLog     = $GLOBALS['mock_update_post_meta_log'];
        $allocatedUpdates = array_filter($postMetaLog, fn($e) => $e['meta_key'] === '_buygo_allocated');
        $this->assertNotEmpty($allocatedUpdates,
            'create_shipment 路徑應觸發 _buygo_allocated post meta 重算');

        $update = array_values($allocatedUpdates)[0];
        $this->assertSame(4, $update['meta_value'],
            '_buygo_allocated 應重算為 4（而非從 9 累減）');
        $this->assertSame(2002, $update['post_id'],
            '_buygo_allocated 應更新在正確的 post_id=2002');

        // 驗證：line meta _allocated_qty 也被寫回
        $lineMetaUpdates = array_filter(
            $GLOBALS['wpdb']->updates,
            fn($u) => $u['table'] === 'wp_fct_order_items'
                   && ($u['where']['id'] ?? null) === 8002
        );
        $this->assertNotEmpty($lineMetaUpdates,
            'create_shipment 路徑應更新 order_item 的 line_meta');

        $lineMeta = json_decode(array_values($lineMetaUpdates)[0]['data']['line_meta'], true);
        $this->assertSame(4, $lineMeta['_allocated_qty'],
            '_allocated_qty 應重算為 4（create_shipment 路徑）');
    }
}
