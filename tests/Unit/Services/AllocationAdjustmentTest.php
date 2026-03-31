<?php

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\AllocationService;

/**
 * AllocationService::adjustAllocation() 單元測試
 *
 * 測試「調整/撤銷分配」功能的驗證邏輯。
 * 驗證邏輯已提取成純 PHP public 方法（validateAdjustment），
 * 因此不需要完整 WordPress 環境也能測試。
 *
 * 測試場景：
 *   - 父訂單 #2000，原始訂購數量 2
 *   - 子訂單已存在，分配數量 2，已出貨數量 1
 */
class AllocationAdjustmentTest extends TestCase
{
    // ─────────────────────────────────────────
    // 共用測試常數
    // ─────────────────────────────────────────

    /** 測試用商品 variation ID */
    const PRODUCT_ID = 100;

    /** 測試用父訂單 ID */
    const ORDER_ID = 2000;

    /** 子訂單 ID */
    const CHILD_ORDER_ID = 3000;

    /** 父訂單原始訂購數量 */
    const ORDER_QUANTITY = 2;

    /** 子訂單已出貨數量 */
    const SHIPPED_QTY = 1;

    /** 子訂單目前分配數量 */
    const CURRENT_ALLOCATED = 2;

    // ─────────────────────────────────────────
    // setUp / tearDown
    // ─────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        // 確保 FluentCart ProductVariation mock 存在（AllocationService 建構子不需要，但 class 載入時需要）
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

        // 重置 GLOBALS mock
        $GLOBALS['mock_product_variation_map'] = [];
        $GLOBALS['mock_get_post_meta_map']      = [];
        $GLOBALS['mock_wpdb_insert_id_sequence'] = [];
        $GLOBALS['mock_wpdb_query_log']          = [];
        $GLOBALS['mock_wpdb_insert_log']         = [];
        $GLOBALS['mock_wpdb_update_log']         = [];
        $GLOBALS['mock_wpdb_delete_log']         = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['mock_product_variation_map'],
            $GLOBALS['mock_get_post_meta_map'],
            $GLOBALS['mock_wpdb_insert_id_sequence'],
            $GLOBALS['mock_wpdb_query_log'],
            $GLOBALS['mock_wpdb_insert_log'],
            $GLOBALS['mock_wpdb_update_log'],
            $GLOBALS['mock_wpdb_delete_log']
        );
        parent::tearDown();
    }

    // ─────────────────────────────────────────
    // 輔助：建立可追蹤 update/delete 的 Mock wpdb
    // ─────────────────────────────────────────

    /**
     * 建立 adjustAllocation 專用的 mock wpdb。
     *
     * 規則系統與 AllocationServiceTest 相同，額外加上 update/delete 操作追蹤。
     *
     * @param array $rules  查詢規則 [['method'=>'get_var','contains'=>'...','return'=>...], ...]
     * @return object
     */
    private function makeMockWpdb(array $rules = []): object
    {
        return new class($rules) {
            public $prefix     = 'wp_';
            public $insert_id  = 0;
            public $last_error = '';

            private array $rules;
            public array  $query_log  = [];
            public array  $insert_log = [];
            public array  $update_log = [];
            public array  $delete_log = [];

            private array $insert_id_sequence;
            private int   $insert_id_cursor = 0;

            public function __construct(array $rules)
            {
                $this->rules              = $rules;
                $this->insert_id_sequence = $GLOBALS['mock_wpdb_insert_id_sequence'] ?? [];
            }

            public function prepare($query, ...$args): string
            {
                // 支援 prepare($q, array) 與 prepare($q, v1, v2, ...) 兩種呼叫方式
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
                $this->insert_log[] = ['table' => $table, 'data' => $data];
                $GLOBALS['mock_wpdb_insert_log'][] = ['table' => $table, 'data' => $data];

                if (!empty($this->insert_id_sequence) && isset($this->insert_id_sequence[$this->insert_id_cursor])) {
                    $this->insert_id = $this->insert_id_sequence[$this->insert_id_cursor];
                    $this->insert_id_cursor++;
                } else {
                    $this->insert_id = 9999;
                }
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
    // 輔助：建立標準測試場景的 wpdb 規則
    // ─────────────────────────────────────────

    /**
     * 建立 adjustAllocation 標準場景的 mock wpdb 規則。
     *
     * 場景：
     *   - product_id = 100（單一商品，variation_ids = [100]）
     *   - 父訂單 #2000，order item quantity = 2
     *   - 子訂單 #3000（type=split, parent_id=2000），已分配 2，已出貨 $shipped_qty
     *
     * @param int $shipped_qty  子訂單 _shipped_qty（預設 1）
     * @param int $current_allocated  子訂單目前分配數量（預設 2）
     * @return array
     */
    private function buildStandardRules(int $shipped_qty = self::SHIPPED_QTY, int $current_allocated = self::CURRENT_ALLOCATED): array
    {
        $childLineMeta = json_encode([
            '_allocated_qty' => $current_allocated,
            '_shipped_qty'   => $shipped_qty,
        ]);

        // 子訂單項目
        $childItem = [
            'id'         => 5001,
            'order_id'   => self::CHILD_ORDER_ID,
            'post_id'    => 200,
            'object_id'  => self::PRODUCT_ID,
            'quantity'   => $current_allocated,
            'unit_price' => 10000.0,
            'subtotal'   => $current_allocated * 10000.0,
            'line_total' => $current_allocated * 10000.0,
            'line_meta'  => $childLineMeta,
        ];

        // 子訂單
        $childOrder = (object)[
            'id'             => self::CHILD_ORDER_ID,
            'parent_id'      => self::ORDER_ID,
            'type'           => 'split',
            'customer_id'    => 42,
            'status'         => 'pending',
            'payment_status' => 'paid',
            'total_amount'   => $current_allocated * 10000.0,
            'currency'       => 'TWD',
        ];

        // 父訂單項目
        $parentItem = [
            'id'        => 4001,
            'order_id'  => self::ORDER_ID,
            'object_id' => self::PRODUCT_ID,
            'quantity'  => self::ORDER_QUANTITY,
            'unit_price' => 10000.0,
            'line_meta' => json_encode(['_allocated_qty' => $current_allocated]),
        ];

        return [
            // getAllVariationIds：單一商品 post_id = 200
            [
                'method'   => 'get_var',
                'contains' => 'fct_product_variations WHERE id',
                'return'   => '200',
            ],
            [
                'method'   => 'get_col',
                'contains' => 'fct_product_variations WHERE post_id',
                'return'   => [(string) self::PRODUCT_ID],
            ],
            // 查詢子訂單（type=split, parent_id=$order_id）
            [
                'method'   => 'get_row',
                'contains' => "parent_id = " . self::ORDER_ID . " AND type = 'split'",
                'return'   => $childOrder,
            ],
            // 查詢子訂單項目（找 object_id = product_id）
            [
                'method'   => 'get_row',
                'contains' => 'fct_order_items',
                'return'   => (object) $childItem,
            ],
            // 查詢父訂單項目（驗證原始訂購數量用）
            [
                'method'   => 'get_results',
                'contains' => 'fct_order_items',
                'return'   => [$parentItem],
            ],
            // 重算 _buygo_allocated（子訂單 SUM）
            [
                'method'   => 'get_var',
                'contains' => 'COALESCE(SUM',
                'return'   => '0', // 刪除後為 0，調整後由 test 覆寫
            ],
        ];
    }

    // ─────────────────────────────────────────
    // 測試 1：validateAdjustment — 負數拒絕
    // ─────────────────────────────────────────

    /**
     * @test
     * 傳入負數 new_quantity 應被拒絕
     */
    public function test_adjust_rejects_negative_quantity(): void
    {
        $service = new AllocationService();

        $result = $service->validateAdjustment(
            product_id:        self::PRODUCT_ID,
            order_id:          self::ORDER_ID,
            new_quantity:      -1,
            current_allocated: self::CURRENT_ALLOCATED,
            shipped_qty:       self::SHIPPED_QTY,
            order_quantity:    self::ORDER_QUANTITY
        );

        $this->assertInstanceOf(
            \WP_Error::class,
            $result,
            'new_quantity = -1 應回傳 WP_Error'
        );
        $this->assertSame('INVALID_QUANTITY', $result->get_error_code());
    }

    // ─────────────────────────────────────────
    // 測試 2：validateAdjustment — 超過訂購數量拒絕
    // ─────────────────────────────────────────

    /**
     * @test
     * new_quantity 超過父訂單原始訂購數量應被拒絕
     */
    public function test_adjust_rejects_exceeding_order_quantity(): void
    {
        $service = new AllocationService();

        $result = $service->validateAdjustment(
            product_id:        self::PRODUCT_ID,
            order_id:          self::ORDER_ID,
            new_quantity:      self::ORDER_QUANTITY + 1, // 3 > 2
            current_allocated: self::CURRENT_ALLOCATED,
            shipped_qty:       self::SHIPPED_QTY,
            order_quantity:    self::ORDER_QUANTITY
        );

        $this->assertInstanceOf(
            \WP_Error::class,
            $result,
            'new_quantity 超過訂購數量應回傳 WP_Error'
        );
        $this->assertSame('EXCEEDS_ORDER_QUANTITY', $result->get_error_code());
    }

    // ─────────────────────────────────────────
    // 測試 3：validateAdjustment — 低於已出貨數量拒絕
    // ─────────────────────────────────────────

    /**
     * @test
     * 已出貨 1 個，不能將 new_quantity 調到 0
     */
    public function test_cannot_adjust_below_shipped_qty(): void
    {
        $service = new AllocationService();

        $result = $service->validateAdjustment(
            product_id:        self::PRODUCT_ID,
            order_id:          self::ORDER_ID,
            new_quantity:      0,                   // 嘗試全撤
            current_allocated: self::CURRENT_ALLOCATED,
            shipped_qty:       self::SHIPPED_QTY,   // 已出貨 1，不能撤到 0
            order_quantity:    self::ORDER_QUANTITY
        );

        $this->assertInstanceOf(
            \WP_Error::class,
            $result,
            '已出貨 1 個，全撤應回傳 WP_Error'
        );
        $this->assertSame('BELOW_SHIPPED_QTY', $result->get_error_code());
    }

    // ─────────────────────────────────────────
    // 測試 4：validateAdjustment — 合法減量通過
    // ─────────────────────────────────────────

    /**
     * @test
     * 合法調整（2→1，已出貨 1）應通過驗證，回傳 true
     */
    public function test_validate_passes_for_valid_reduction(): void
    {
        $service = new AllocationService();

        $result = $service->validateAdjustment(
            product_id:        self::PRODUCT_ID,
            order_id:          self::ORDER_ID,
            new_quantity:      1,                   // 2→1，合法
            current_allocated: self::CURRENT_ALLOCATED,
            shipped_qty:       self::SHIPPED_QTY,   // 已出貨 1，1 >= 1 OK
            order_quantity:    self::ORDER_QUANTITY
        );

        $this->assertTrue($result, '合法調整應通過驗證（回傳 true）');
    }

    // ─────────────────────────────────────────
    // 測試 5：validateAdjustment — 全撤（shipped=0）通過
    // ─────────────────────────────────────────

    /**
     * @test
     * 已出貨 0 個，全撤（new_quantity = 0）應通過驗證
     */
    public function test_validate_passes_for_full_undo_when_not_shipped(): void
    {
        $service = new AllocationService();

        $result = $service->validateAdjustment(
            product_id:        self::PRODUCT_ID,
            order_id:          self::ORDER_ID,
            new_quantity:      0,
            current_allocated: self::CURRENT_ALLOCATED,
            shipped_qty:       0, // 未出貨，可全撤
            order_quantity:    self::ORDER_QUANTITY
        );

        $this->assertTrue($result, '未出貨時全撤應通過驗證');
    }

    // ─────────────────────────────────────────
    // 測試 6：adjustAllocation — 數量 2→1，子訂單 qty 變 1
    // ─────────────────────────────────────────

    /**
     * @test
     * 分配數量從 2 調整為 1，子訂單項目 quantity 應更新為 1
     */
    public function test_adjust_reduces_child_order_quantity(): void
    {
        // shipped_qty = 1，current_allocated = 2，調整為 1
        $rules = $this->buildStandardRules(shipped_qty: 1, current_allocated: 2);
        // 覆寫 COALESCE(SUM 規則，讓重算回傳 new_quantity = 1
        foreach ($rules as &$rule) {
            if ($rule['method'] === 'get_var' && strpos($rule['contains'], 'COALESCE(SUM') !== false) {
                $rule['return'] = '1';
            }
        }

        $mockWpdb = $this->makeMockWpdb($rules);
        $GLOBALS['wpdb'] = $mockWpdb;

        $service = new AllocationService();
        $result  = $service->adjustAllocation(
            product_id:   self::PRODUCT_ID,
            order_id:     self::ORDER_ID,
            new_quantity: 1
        );

        // 不應回傳錯誤
        $this->assertNotInstanceOf(\WP_Error::class, $result, '合法調整不應回傳 WP_Error');
        $this->assertIsArray($result);
        $this->assertTrue($result['success'] ?? false, '調整應成功');

        // 確認有執行 UPDATE（更新子訂單項目的 quantity）
        $updateLog = $GLOBALS['mock_wpdb_update_log'] ?? [];
        $hasItemUpdate = false;
        foreach ($updateLog as $entry) {
            if (strpos($entry['table'], 'fct_order_items') !== false) {
                $hasItemUpdate = true;
                $this->assertSame(1, (int) ($entry['data']['quantity'] ?? -1), '子訂單項目 quantity 應更新為 1');
                break;
            }
        }
        $this->assertTrue($hasItemUpdate, '應有對 fct_order_items 執行 UPDATE');
    }

    // ─────────────────────────────────────────
    // 測試 7：adjustAllocation — 數量→0，子訂單被刪除
    // ─────────────────────────────────────────

    /**
     * @test
     * 調整為 0（全撤），子訂單和子訂單項目應被刪除
     */
    public function test_adjust_to_zero_deletes_child_order(): void
    {
        // shipped_qty = 0，可以全撤
        $rules = $this->buildStandardRules(shipped_qty: 0, current_allocated: 2);

        $mockWpdb = $this->makeMockWpdb($rules);
        $GLOBALS['wpdb'] = $mockWpdb;

        $service = new AllocationService();
        $result  = $service->adjustAllocation(
            product_id:   self::PRODUCT_ID,
            order_id:     self::ORDER_ID,
            new_quantity: 0
        );

        $this->assertNotInstanceOf(\WP_Error::class, $result, '合法全撤不應回傳 WP_Error');
        $this->assertTrue($result['success'] ?? false, '全撤應成功');

        // 確認有刪除子訂單
        $deleteLog = $GLOBALS['mock_wpdb_delete_log'] ?? [];
        $deletedOrders = array_filter($deleteLog, fn($e) => strpos($e['table'], 'fct_orders') !== false);
        $this->assertNotEmpty($deletedOrders, '應有刪除 fct_orders（子訂單）');

        // 確認有刪除子訂單項目
        $deletedItems = array_filter($deleteLog, fn($e) => strpos($e['table'], 'fct_order_items') !== false);
        $this->assertNotEmpty($deletedItems, '應有刪除 fct_order_items（子訂單項目）');
    }

    // ─────────────────────────────────────────
    // 測試 8：adjustAllocation — 同步父訂單 _allocated_qty
    // ─────────────────────────────────────────

    /**
     * @test
     * 調整後，父訂單項目的 line_meta._allocated_qty 應同步為新數量
     */
    public function test_adjust_syncs_parent_allocated_qty(): void
    {
        // shipped_qty = 1，current = 2，調整為 1
        $rules = $this->buildStandardRules(shipped_qty: 1, current_allocated: 2);
        foreach ($rules as &$rule) {
            if ($rule['method'] === 'get_var' && strpos($rule['contains'] ?? '', 'COALESCE(SUM') !== false) {
                $rule['return'] = '1';
            }
        }

        $mockWpdb = $this->makeMockWpdb($rules);
        $GLOBALS['wpdb'] = $mockWpdb;

        $service = new AllocationService();
        $service->adjustAllocation(
            product_id:   self::PRODUCT_ID,
            order_id:     self::ORDER_ID,
            new_quantity: 1
        );

        // 確認有更新父訂單項目（fct_order_items, id=4001）
        $updateLog = $GLOBALS['mock_wpdb_update_log'] ?? [];
        $parentItemUpdate = null;
        foreach ($updateLog as $entry) {
            if (
                strpos($entry['table'], 'fct_order_items') !== false &&
                isset($entry['where']['id']) &&
                (int) $entry['where']['id'] === 4001
            ) {
                $parentItemUpdate = $entry;
                break;
            }
        }

        $this->assertNotNull($parentItemUpdate, '應有更新父訂單項目（id=4001）');

        // 確認 line_meta._allocated_qty 同步為 1
        $lineMeta = json_decode($parentItemUpdate['data']['line_meta'] ?? '{}', true);
        $this->assertSame(1, (int) ($lineMeta['_allocated_qty'] ?? -1), '父訂單項目 _allocated_qty 應同步為 1');
    }

    // ─────────────────────────────────────────
    // 測試 9：adjustAllocation — 重算 _buygo_allocated
    // ─────────────────────────────────────────

    /**
     * @test
     * 調整後，商品的 _buygo_allocated post_meta 應被重算（update_post_meta 被呼叫）
     */
    public function test_adjust_recalculates_buygo_allocated(): void
    {
        // 追蹤 update_post_meta 呼叫
        $GLOBALS['mock_update_post_meta_calls'] = [];

        $rules = $this->buildStandardRules(shipped_qty: 1, current_allocated: 2);
        foreach ($rules as &$rule) {
            if ($rule['method'] === 'get_var' && strpos($rule['contains'] ?? '', 'COALESCE(SUM') !== false) {
                $rule['return'] = '1'; // 調整後總分配 = 1
            }
        }

        $mockWpdb = $this->makeMockWpdb($rules);
        $GLOBALS['wpdb'] = $mockWpdb;

        $service = new AllocationService();
        $result  = $service->adjustAllocation(
            product_id:   self::PRODUCT_ID,
            order_id:     self::ORDER_ID,
            new_quantity: 1
        );

        $this->assertNotInstanceOf(\WP_Error::class, $result, '應成功');
        $this->assertTrue($result['success'] ?? false);

        // 確認 result 中有回傳 total_allocated
        $this->assertArrayHasKey('total_allocated', $result, '結果應包含 total_allocated');
    }

    // ─────────────────────────────────────────
    // 測試 10：adjustAllocation — 找不到子訂單回傳錯誤
    // ─────────────────────────────────────────

    /**
     * @test
     * 找不到對應子訂單時，應回傳 WP_Error（CHILD_ORDER_NOT_FOUND）
     */
    public function test_adjust_returns_error_when_no_child_order(): void
    {
        $rules = [
            // getAllVariationIds
            ['method' => 'get_var', 'contains' => 'fct_product_variations WHERE id', 'return' => '200'],
            ['method' => 'get_col', 'contains' => 'fct_product_variations WHERE post_id', 'return' => [(string) self::PRODUCT_ID]],
            // 子訂單查不到
            ['method' => 'get_row', 'contains' => "type = 'split'", 'return' => null],
        ];

        $mockWpdb = $this->makeMockWpdb($rules);
        $GLOBALS['wpdb'] = $mockWpdb;

        $service = new AllocationService();
        $result  = $service->adjustAllocation(
            product_id:   self::PRODUCT_ID,
            order_id:     self::ORDER_ID,
            new_quantity: 1
        );

        $this->assertInstanceOf(\WP_Error::class, $result, '找不到子訂單應回傳 WP_Error');
        $this->assertSame('CHILD_ORDER_NOT_FOUND', $result->get_error_code());
    }
}
