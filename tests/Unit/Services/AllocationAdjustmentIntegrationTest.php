<?php

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\AllocationService;

/**
 * AllocationService 整合測試 — 分配→調整→全撤完整流程
 *
 * 以假資料模擬真實業務流程，驗證每一步的資料狀態：
 *   - 減量：子訂單 quantity 更新、父訂單 _allocated_qty 同步、_buygo_allocated 重算
 *   - 全撤：子訂單與子訂單項目被刪除
 *   - 防呆：已出貨時不允許調整到低於出貨數量
 *
 * Mock 策略：
 *   以 anonymous class 實作 wpdb，追蹤所有 update/delete 操作，
 *   使用 contains 規則按 SQL 關鍵字路由各種查詢的回傳值。
 *
 * 場景設定：
 *   - 父訂單  order_id = 100，商品 product_id = 50，訂購 qty = 3
 *   - 子訂單  child_id = 200（type=split），目前分配 qty = 2
 *   - 子訂單項目 id = 5001，unit_price = 500.0
 *   - 父訂單項目 id = 4001
 */
class AllocationAdjustmentIntegrationTest extends TestCase
{
    // ─────────────────────────────────────────
    // 共用測試常數
    // ─────────────────────────────────────────

    /** 測試用商品 variation ID */
    const PRODUCT_ID = 50;

    /** 測試用父訂單 ID */
    const ORDER_ID = 100;

    /** 子訂單 ID */
    const CHILD_ORDER_ID = 200;

    /** 父訂單原始訂購數量 */
    const ORDER_QUANTITY = 3;

    /** 目前分配數量 */
    const CURRENT_ALLOCATED = 2;

    /** 子訂單項目 ID */
    const CHILD_ITEM_ID = 5001;

    /** 父訂單項目 ID */
    const PARENT_ITEM_ID = 4001;

    /** 單價 */
    const UNIT_PRICE = 500.0;

    // ─────────────────────────────────────────
    // setUp / tearDown
    // ─────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        // 確保 FluentCart ProductVariation mock 存在（AllocationService class 載入時需要）
        if (!class_exists('FluentCart\App\Models\ProductVariation')) {
            eval('
                namespace FluentCart\App\Models;
                class ProductVariation {
                    public $post_id;
                    private function __construct($post_id) { $this->post_id = $post_id; }
                    public static function find($id) {
                        $map = $GLOBALS["mock_product_variation_map"] ?? [];
                        if (isset($map[$id])) {
                            $obj = new self($map[$id]["post_id"]);
                            return $obj;
                        }
                        return null;
                    }
                }
            ');
        }

        // 重置所有 GLOBALS mock 狀態
        $GLOBALS['mock_product_variation_map']    = [];
        $GLOBALS['mock_get_post_meta_map']        = [];
        $GLOBALS['mock_wpdb_insert_id_sequence']  = [];
        $GLOBALS['mock_wpdb_query_log']           = [];
        $GLOBALS['mock_wpdb_insert_log']          = [];
        $GLOBALS['mock_wpdb_update_log']          = [];
        $GLOBALS['mock_wpdb_delete_log']          = [];
        $GLOBALS['mock_update_post_meta_calls']   = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['wpdb'],
            $GLOBALS['mock_product_variation_map'],
            $GLOBALS['mock_get_post_meta_map'],
            $GLOBALS['mock_wpdb_insert_id_sequence'],
            $GLOBALS['mock_wpdb_query_log'],
            $GLOBALS['mock_wpdb_insert_log'],
            $GLOBALS['mock_wpdb_update_log'],
            $GLOBALS['mock_wpdb_delete_log'],
            $GLOBALS['mock_update_post_meta_calls']
        );
        parent::tearDown();
    }

    // ─────────────────────────────────────────
    // 輔助：建立可追蹤操作的 Mock wpdb
    // ─────────────────────────────────────────

    /**
     * 建立整合測試用的 mock wpdb。
     *
     * 支援 get_var / get_col / get_row / get_results / update / delete / query。
     * 每個 update / delete 都會寫入 GLOBALS['mock_wpdb_update_log'] /
     * GLOBALS['mock_wpdb_delete_log']，方便斷言。
     *
     * @param array $rules  [['method'=>'get_var','contains'=>'...','return'=>...], ...]
     * @return object
     */
    private function makeMockWpdb(array $rules = []): object
    {
        return new class($rules) {
            public string $prefix     = 'wp_';
            public int    $insert_id  = 0;
            public string $last_error = '';

            private array $rules;
            public array  $query_log  = [];
            public array  $update_log = [];
            public array  $delete_log = [];

            public function __construct(array $rules)
            {
                $this->rules = $rules;
            }

            public function prepare($query, ...$args): string
            {
                // 支援 prepare($q, array) 和 prepare($q, v1, v2, ...) 兩種形式
                $flat = [];
                foreach ($args as $arg) {
                    if (is_array($arg)) {
                        foreach ($arg as $v) { $flat[] = $v; }
                    } else {
                        $flat[] = $arg;
                    }
                }

                $result = $query;
                foreach ($flat as $arg) {
                    $pos_d = strpos($result, '%d');
                    $pos_s = strpos($result, '%s');
                    if ($pos_d !== false && ($pos_s === false || $pos_d <= $pos_s)) {
                        $result = preg_replace('/%d/', (int) $arg, $result, 1);
                    } else {
                        $result = preg_replace("/%s/", "'" . addslashes((string) $arg) . "'", $result, 1);
                    }
                }
                return $result;
            }

            private function matchRule(string $method, string $sql)
            {
                $this->query_log[] = ['method' => $method, 'sql' => $sql];
                $GLOBALS['mock_wpdb_query_log'][] = ['method' => $method, 'sql' => $sql];

                foreach ($this->rules as $rule) {
                    if ($rule['method'] !== $method) { continue; }
                    if (isset($rule['contains']) && strpos($sql, $rule['contains']) !== false) {
                        return $rule['return'];
                    }
                    if (isset($rule['matches']) && preg_match($rule['matches'], $sql)) {
                        return $rule['return'];
                    }
                }
                return null;
            }

            public function get_var(string $sql) { return $this->matchRule('get_var', $sql); }

            public function get_col(string $sql): array
            {
                $result = $this->matchRule('get_col', $sql);
                return $result ?? [];
            }

            public function get_row(string $sql, $output = OBJECT)
            {
                $result = $this->matchRule('get_row', $sql);
                if ($result === null) { return null; }
                if ($output === ARRAY_A) {
                    return is_array($result) ? $result : (array) $result;
                }
                return is_object($result) ? $result : (object) $result;
            }

            public function get_results(string $sql, $output = OBJECT): array
            {
                $result = $this->matchRule('get_results', $sql);
                if (empty($result)) { return []; }
                if ($output === ARRAY_A) {
                    return array_map(fn($r) => is_array($r) ? $r : (array) $r, $result);
                }
                return array_map(fn($r) => is_object($r) ? $r : (object) $r, $result);
            }

            public function insert(string $table, array $data, $format = null): int
            {
                $this->insert_id = 9999;
                return 1;
            }

            public function update(string $table, array $data, array $where, $format = null, $where_format = null): int
            {
                $entry = ['table' => $table, 'data' => $data, 'where' => $where];
                $this->update_log[] = $entry;
                $GLOBALS['mock_wpdb_update_log'][] = $entry;
                return 1;
            }

            public function delete(string $table, array $where, $format = null): int
            {
                $entry = ['table' => $table, 'where' => $where];
                $this->delete_log[] = $entry;
                $GLOBALS['mock_wpdb_delete_log'][] = $entry;
                return 1;
            }

            public function query(string $sql): bool
            {
                $this->query_log[] = ['method' => 'query', 'sql' => $sql];
                return true;
            }

            public function get_charset_collate(): string
            {
                return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
            }
        };
    }

    // ─────────────────────────────────────────
    // 輔助：建立場景用的 wpdb 規則陣列
    // ─────────────────────────────────────────

    /**
     * 建立標準場景的 mock wpdb 規則。
     *
     * 場景：product_id=50，父訂單 #100（qty=3），子訂單 #200（qty=$currentAllocated，shipped=$shippedQty）
     *
     * 查詢路由說明（按 contains 關鍵字匹配）：
     *   - step 1a: get_var  "fct_product_variations WHERE id"       → post_id = 300
     *   - step 7:  get_var  "child_o.parent_id"（父訂單 scope SUM） → $sumAfterAdjust
     *   - step 8:  get_var  "COALESCE(SUM"（全商品 SUM）            → $totalAfterAdjust
     *   - step 1b: get_col  "fct_product_variations WHERE post_id"  → [50]
     *   - step 2:  get_row  "parent_id = 100 AND"                   → 子訂單物件
     *   - step 3:  get_row  "fct_order_items"                       → 子訂單項目
     *   - step 4:  get_results "fct_order_items"                    → 父訂單項目陣列
     *
     * 注意：step 7 的 contains 使用 "child_o.parent_id"，比 step 8 的 "COALESCE(SUM" 更精確，
     * 排在前面確保優先匹配（matchRule 按陣列順序）。
     *
     * @param int $shippedQty        子訂單已出貨數量
     * @param int $currentAllocated  子訂單目前分配數量
     * @param int $sumAfterAdjust    step 7 重算值（父訂單 scope）
     * @param int $totalAfterAdjust  step 8 重算值（全商品 scope）
     */
    private function buildRules(
        int $shippedQty,
        int $currentAllocated,
        int $sumAfterAdjust,
        int $totalAfterAdjust
    ): array {
        $childLineMeta = json_encode([
            '_allocated_qty' => $currentAllocated,
            '_shipped_qty'   => $shippedQty,
        ]);

        $childItem = (object)[
            'id'         => self::CHILD_ITEM_ID,
            'order_id'   => self::CHILD_ORDER_ID,
            'object_id'  => self::PRODUCT_ID,
            'quantity'   => $currentAllocated,
            'unit_price' => self::UNIT_PRICE,
            'subtotal'   => $currentAllocated * self::UNIT_PRICE,
            'line_total' => $currentAllocated * self::UNIT_PRICE,
            'line_meta'  => $childLineMeta,
        ];

        $childOrder = (object)[
            'id'             => self::CHILD_ORDER_ID,
            'parent_id'      => self::ORDER_ID,
            'type'           => 'split',
            'customer_id'    => 42,
            'status'         => 'pending',
            'payment_status' => 'paid',
            'total_amount'   => $currentAllocated * self::UNIT_PRICE,
            'currency'       => 'TWD',
        ];

        $parentItem = [
            'id'         => self::PARENT_ITEM_ID,
            'order_id'   => self::ORDER_ID,
            'object_id'  => self::PRODUCT_ID,
            'quantity'   => self::ORDER_QUANTITY,
            'unit_price' => self::UNIT_PRICE,
            'line_meta'  => json_encode(['_allocated_qty' => $currentAllocated]),
        ];

        return [
            // step 1a：取得 variation 的 post_id（最精確，放最前）
            [
                'method'   => 'get_var',
                'contains' => 'fct_product_variations WHERE id',
                'return'   => '300',
            ],
            // step 7：父訂單 scope 重算（含 child_o.parent_id，比 COALESCE 更精確）
            [
                'method'   => 'get_var',
                'contains' => 'child_o.parent_id',
                'return'   => (string) $sumAfterAdjust,
            ],
            // step 8：全商品 _buygo_allocated 重算（不含 parent_id 條件）
            [
                'method'   => 'get_var',
                'contains' => 'COALESCE(SUM',
                'return'   => (string) $totalAfterAdjust,
            ],
            // step 1b：取得同 post_id 的所有 variation IDs
            [
                'method'   => 'get_col',
                'contains' => 'fct_product_variations WHERE post_id',
                'return'   => [(string) self::PRODUCT_ID],
            ],
            // step 2：查詢子訂單（parent_id=100, type=split）
            [
                'method'   => 'get_row',
                'contains' => "o.type = 'split'",
                'return'   => $childOrder,
            ],
            // step 3：查詢子訂單項目（第二個 get_row，order_id=CHILD_ORDER_ID）
            [
                'method'   => 'get_row',
                'contains' => 'fct_order_items',
                'return'   => $childItem,
            ],
            // step 4：查詢父訂單項目（get_results）
            [
                'method'   => 'get_results',
                'contains' => 'fct_order_items',
                'return'   => [$parentItem],
            ],
        ];
    }

    // ─────────────────────────────────────────
    // 輔助：從 GLOBALS log 篩選特定 table 操作
    // ─────────────────────────────────────────

    private function findUpdates(string $tableKeyword): array
    {
        return array_values(array_filter(
            $GLOBALS['mock_wpdb_update_log'] ?? [],
            fn($e) => strpos($e['table'], $tableKeyword) !== false
        ));
    }

    private function findDeletes(string $tableKeyword): array
    {
        return array_values(array_filter(
            $GLOBALS['mock_wpdb_delete_log'] ?? [],
            fn($e) => strpos($e['table'], $tableKeyword) !== false
        ));
    }

    // ─────────────────────────────────────────
    // 測試 1：分配→減量（2→1）完整流程
    // ─────────────────────────────────────────

    /**
     * @test
     * 已分配 2 個（已出貨 1），調整為 1：
     *   - 子訂單項目 quantity 變為 1
     *   - 子訂單項目 line_meta._allocated_qty 變為 1
     *   - 父訂單項目 line_meta._allocated_qty 同步為 1
     *   - 回傳 total_allocated = 1
     */
    public function test_full_flow_allocate_then_reduce(): void
    {
        $rules           = $this->buildRules(shippedQty: 1, currentAllocated: 2, sumAfterAdjust: 1, totalAfterAdjust: 1);
        $GLOBALS['wpdb'] = $this->makeMockWpdb($rules);

        $service = new AllocationService();
        $result  = $service->adjustAllocation(
            product_id:   self::PRODUCT_ID,
            order_id:     self::ORDER_ID,
            new_quantity: 1
        );

        // 不應回傳錯誤
        $this->assertNotInstanceOf(\WP_Error::class, $result, '合法減量不應回傳 WP_Error');
        $this->assertTrue($result['success'] ?? false, '調整應成功');

        // 驗證子訂單項目 quantity = 1，且 line_meta._allocated_qty = 1
        $childItemUpdates = $this->findUpdates('fct_order_items');
        $childItemUpdate  = null;
        foreach ($childItemUpdates as $entry) {
            if (isset($entry['where']['id']) && (int) $entry['where']['id'] === self::CHILD_ITEM_ID) {
                $childItemUpdate = $entry;
                break;
            }
        }
        $this->assertNotNull($childItemUpdate, '應有對子訂單項目（id=' . self::CHILD_ITEM_ID . '）執行 UPDATE');
        $this->assertSame(1, (int) ($childItemUpdate['data']['quantity'] ?? -1), '子訂單項目 quantity 應更新為 1');

        $childLineMeta = json_decode($childItemUpdate['data']['line_meta'] ?? '{}', true);
        $this->assertSame(1, (int) ($childLineMeta['_allocated_qty'] ?? -1), '子訂單項目 line_meta._allocated_qty 應更新為 1');

        // 驗證父訂單項目 line_meta._allocated_qty 同步為 1
        $parentItemUpdate = null;
        foreach ($childItemUpdates as $entry) {
            if (isset($entry['where']['id']) && (int) $entry['where']['id'] === self::PARENT_ITEM_ID) {
                $parentItemUpdate = $entry;
                break;
            }
        }
        $this->assertNotNull($parentItemUpdate, '應有對父訂單項目（id=' . self::PARENT_ITEM_ID . '）執行 UPDATE');
        $parentLineMeta = json_decode($parentItemUpdate['data']['line_meta'] ?? '{}', true);
        $this->assertSame(1, (int) ($parentLineMeta['_allocated_qty'] ?? -1), '父訂單項目 _allocated_qty 應同步為 1');

        // 驗證回傳的 total_allocated = 1
        $this->assertArrayHasKey('total_allocated', $result, '結果應包含 total_allocated');
        $this->assertSame(1, (int) $result['total_allocated'], 'total_allocated 應為 1');
    }

    // ─────────────────────────────────────────
    // 測試 2：分配→全撤（new_qty=0，無出貨）
    // ─────────────────────────────────────────

    /**
     * @test
     * 已分配 2 個、已出貨 0，調整為 0（全撤）：
     *   - 子訂單項目被刪除（fct_order_items，以 order_id 為條件）
     *   - 子訂單被刪除（fct_orders，以 id 為條件）
     *   - 父訂單項目 line_meta._allocated_qty 同步為 0
     *   - 回傳 total_allocated = 0
     */
    public function test_full_flow_allocate_then_fully_revoke(): void
    {
        $rules           = $this->buildRules(shippedQty: 0, currentAllocated: 2, sumAfterAdjust: 0, totalAfterAdjust: 0);
        $GLOBALS['wpdb'] = $this->makeMockWpdb($rules);

        $service = new AllocationService();
        $result  = $service->adjustAllocation(
            product_id:   self::PRODUCT_ID,
            order_id:     self::ORDER_ID,
            new_quantity: 0
        );

        // 不應回傳錯誤
        $this->assertNotInstanceOf(\WP_Error::class, $result, '未出貨時全撤不應回傳 WP_Error');
        $this->assertTrue($result['success'] ?? false, '全撤應成功');

        // 驗證子訂單項目被刪除（order_id = CHILD_ORDER_ID）
        $deletedItems    = $this->findDeletes('fct_order_items');
        $this->assertNotEmpty($deletedItems, '應有刪除 fct_order_items（子訂單項目）');
        $deletedByOrderId = array_filter(
            $deletedItems,
            fn($e) => isset($e['where']['order_id']) && (int) $e['where']['order_id'] === self::CHILD_ORDER_ID
        );
        $this->assertNotEmpty($deletedByOrderId, '子訂單項目應以 order_id=' . self::CHILD_ORDER_ID . ' 條件刪除');

        // 驗證子訂單被刪除（id = CHILD_ORDER_ID）
        $deletedOrders = $this->findDeletes('fct_orders');
        $this->assertNotEmpty($deletedOrders, '應有刪除 fct_orders（子訂單）');
        $deletedById = array_filter(
            $deletedOrders,
            fn($e) => isset($e['where']['id']) && (int) $e['where']['id'] === self::CHILD_ORDER_ID
        );
        $this->assertNotEmpty($deletedById, '子訂單應以 id=' . self::CHILD_ORDER_ID . ' 條件刪除');

        // 驗證父訂單項目 _allocated_qty 同步為 0
        $parentItemUpdates = $this->findUpdates('fct_order_items');
        $parentItemUpdate  = null;
        foreach ($parentItemUpdates as $entry) {
            if (isset($entry['where']['id']) && (int) $entry['where']['id'] === self::PARENT_ITEM_ID) {
                $parentItemUpdate = $entry;
                break;
            }
        }
        $this->assertNotNull($parentItemUpdate, '全撤後應更新父訂單項目（id=' . self::PARENT_ITEM_ID . '）');
        $parentLineMeta = json_decode($parentItemUpdate['data']['line_meta'] ?? '{}', true);
        $this->assertSame(0, (int) ($parentLineMeta['_allocated_qty'] ?? -1), '全撤後父訂單項目 _allocated_qty 應為 0');

        // 驗證回傳的 total_allocated = 0
        $this->assertArrayHasKey('total_allocated', $result, '結果應包含 total_allocated');
        $this->assertSame(0, (int) $result['total_allocated'], 'total_allocated 應為 0');
    }

    // ─────────────────────────────────────────
    // 測試 3：已部分出貨時，全撤被阻止；調整至出貨量則允許
    // ─────────────────────────────────────────

    /**
     * @test
     * 已分配 2 個、已出貨 1 個：
     *   步驟 A：嘗試全撤（new_qty=0） → WP_Error（BELOW_SHIPPED_QTY），無 DELETE
     *   步驟 B：調整為 1（= 出貨量） → 成功，有 UPDATE，無 DELETE
     */
    public function test_full_flow_cannot_revoke_when_partially_shipped(): void
    {
        // ── 步驟 A：全撤被拒 ────────────────────────────
        $rules           = $this->buildRules(shippedQty: 1, currentAllocated: 2, sumAfterAdjust: 1, totalAfterAdjust: 1);
        $GLOBALS['wpdb'] = $this->makeMockWpdb($rules);

        $service     = new AllocationService();
        $errorResult = $service->adjustAllocation(
            product_id:   self::PRODUCT_ID,
            order_id:     self::ORDER_ID,
            new_quantity: 0
        );

        $this->assertInstanceOf(\WP_Error::class, $errorResult, '已出貨時全撤應回傳 WP_Error');
        $this->assertSame('BELOW_SHIPPED_QTY', $errorResult->get_error_code(), 'error code 應為 BELOW_SHIPPED_QTY');

        // 全撤被拒後不應有任何 DELETE
        $this->assertEmpty($GLOBALS['mock_wpdb_delete_log'], '全撤被阻止時不應有任何 DELETE 操作');

        // ── 步驟 B：調整至出貨量成功 ────────────────────
        // 重置 mock log（保留測試狀態乾淨）
        $GLOBALS['mock_wpdb_update_log'] = [];
        $GLOBALS['mock_wpdb_delete_log'] = [];
        $GLOBALS['mock_wpdb_query_log']  = [];

        $rules2          = $this->buildRules(shippedQty: 1, currentAllocated: 2, sumAfterAdjust: 1, totalAfterAdjust: 1);
        $GLOBALS['wpdb'] = $this->makeMockWpdb($rules2);

        $successResult = $service->adjustAllocation(
            product_id:   self::PRODUCT_ID,
            order_id:     self::ORDER_ID,
            new_quantity: 1
        );

        $this->assertNotInstanceOf(\WP_Error::class, $successResult, '調整為出貨量（1）應成功');
        $this->assertTrue($successResult['success'] ?? false, '調整為 1 應成功');

        // 應有 UPDATE，不應有 DELETE
        $this->assertNotEmpty($GLOBALS['mock_wpdb_update_log'], '調整為 1 應有 UPDATE 操作');
        $this->assertEmpty($GLOBALS['mock_wpdb_delete_log'],    '調整為 1 不應有 DELETE 操作');
    }
}
