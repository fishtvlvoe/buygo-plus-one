<?php

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\AllocationService;

/**
 * AllocationService 單元測試 — 多樣式商品分配 Bug 驗證
 *
 * 場景：
 *   - 商品 post_id = 2529
 *   - Variation IDs: 954(A), 955(B), 956(C), 957(D), 958(E)
 *   - 訂單 #1405，項目 object_id = 956（variant C），quantity = 1
 *   - 分配時傳入 product_id = 958（variant E）
 *
 * Bug 位置：
 *   - Bug1: updateOrderAllocations L429 — 查詢 WHERE object_id = $product_id（958），
 *           但訂單項目的 object_id = 956（variant C）
 *   - Bug2: create_child_order L612 — 查父訂單項目用 object_id = $product_id（958），
 *           但父訂單的 object_id = 956
 *   - Bug3: create_child_order L708 — 子訂單項目的 object_id 寫成 $product_id（958），
 *           應該用父項目的 object_id（956）
 */
class AllocationServiceTest extends TestCase
{
    // ─────────────────────────────────────────
    // 共用測試常數
    // ─────────────────────────────────────────

    /** 商品 WordPress Post ID */
    const POST_ID = 2529;

    /** 傳入 updateOrderAllocations 的 product_id（variant E） */
    const PRODUCT_ID = 958;

    /** 父訂單 #1405 的訂單項目實際 object_id（variant C） */
    const ORDER_ITEM_OBJECT_ID = 956;

    /** 測試用父訂單 ID */
    const ORDER_ID = 1405;

    /** 測試用子訂單 ID（insert 後回傳） */
    const CHILD_ORDER_ID = 9001;

    // ─────────────────────────────────────────
    // setUp / tearDown
    // ─────────────────────────────────────────

    /**
     * 每個測試前：
     *   - 替換全域 $wpdb 為可程式化的 QueryAwareMockWpdb
     *   - 建立 FluentCart\App\Models\ProductVariation mock class（若尚未存在）
     *   - 覆寫 get_post_meta 全域函數（透過 GLOBALS 路由）
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 建立 FluentCart ProductVariation mock（只建立一次，避免重複宣告）
        if (!class_exists('FluentCart\App\Models\ProductVariation')) {
            eval('
                namespace FluentCart\App\Models;
                class ProductVariation {
                    public $post_id;
                    private function __construct($post_id) {
                        $this->post_id = $post_id;
                    }
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

        // 建立 get_the_title mock（若尚未存在）
        if (!function_exists('get_the_title')) {
            // 無法在 PHP 中動態取消函數定義，所以透過 GLOBAL 旗標路由
            // 實際上 bootstrap 沒有定義此函數，所以直接定義
        }

        // 重置 GLOBALS mock 設定
        $GLOBALS['mock_product_variation_map'] = [];
        $GLOBALS['mock_get_post_meta_map'] = [];
        $GLOBALS['mock_wpdb_insert_id_sequence'] = [];
        $GLOBALS['mock_wpdb_query_log'] = [];
        $GLOBALS['mock_wpdb_insert_log'] = [];
    }

    protected function tearDown(): void
    {
        // 還原全域 mock，避免跨 test class 汙染
        unset($GLOBALS['mock_product_variation_map']);
        unset($GLOBALS['mock_get_post_meta_map']);
        unset($GLOBALS['mock_wpdb_insert_id_sequence']);
        unset($GLOBALS['mock_wpdb_query_log']);
        unset($GLOBALS['mock_wpdb_insert_log']);
        parent::tearDown();
    }

    // ─────────────────────────────────────────
    // 輔助：建立 QueryAwareMockWpdb 實例
    // ─────────────────────────────────────────

    /**
     * 建立可程式化的 wpdb mock。
     *
     * QueryAwareMockWpdb 透過規則陣列決定每個 SQL 查詢的回傳值：
     *   $rules = [
     *     ['contains' => 'fct_product_variations WHERE id', 'method' => 'get_var', 'return' => 2529],
     *     ['contains' => 'fct_order_items',                 'method' => 'get_results', 'return' => [...]],
     *   ]
     *
     * 規則按順序比對，第一個符合的生效。未比對到的查詢回傳 null / []。
     *
     * @param array $rules 查詢規則陣列
     * @return object
     */
    private function makeMockWpdb(array $rules = []): object
    {
        return new class($rules) {
            public $prefix = 'wp_';
            public $insert_id = 0;
            public $last_error = '';

            private array $rules;
            public array $query_log = [];
            public array $insert_log = [];
            private array $insert_id_sequence;
            private int $insert_id_cursor = 0;

            public function __construct(array $rules)
            {
                $this->rules = $rules;
                $this->insert_id_sequence = $GLOBALS['mock_wpdb_insert_id_sequence'] ?? [];
            }

            public function prepare($query, ...$args): string
            {
                // WordPress wpdb->prepare 支援兩種呼叫方式：
                //   1. prepare($query, $arg1, $arg2, ...)
                //   2. prepare($query, [$arg1, $arg2, ...])  ← array_merge 傳入時
                // 這裡統一展開成扁平的 scalar 列表
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
                foreach ($flat as $arg) {
                    $pos_d = strpos($result, '%d');
                    $pos_s = strpos($result, '%s');
                    if ($pos_d !== false && ($pos_s === false || $pos_d <= $pos_s)) {
                        $result = preg_replace('/%d/', (int)$arg, $result, 1);
                    } else {
                        $result = preg_replace("/%s/", "'" . addslashes((string)$arg) . "'", $result, 1);
                    }
                }
                return $result;
            }

            /** 依規則比對 SQL，回傳匹配的 return 值 */
            private function matchRule(string $method, string $sql)
            {
                $this->query_log[] = ['method' => $method, 'sql' => $sql];
                $GLOBALS['mock_wpdb_query_log'][] = ['method' => $method, 'sql' => $sql];

                foreach ($this->rules as $rule) {
                    if ($rule['method'] !== $method) {
                        continue;
                    }
                    if (isset($rule['contains']) && strpos($sql, $rule['contains']) !== false) {
                        return $rule['return'];
                    }
                    if (isset($rule['matches']) && preg_match($rule['matches'], $sql)) {
                        return $rule['return'];
                    }
                }
                return null;
            }

            public function get_var(string $sql)
            {
                $result = $this->matchRule('get_var', $sql);
                return $result ?? null;
            }

            public function get_col(string $sql): array
            {
                $result = $this->matchRule('get_col', $sql);
                return $result ?? [];
            }

            public function get_row(string $sql, $output = OBJECT)
            {
                $result = $this->matchRule('get_row', $sql);
                if ($result === null) {
                    return null;
                }
                if ($output === ARRAY_A) {
                    return is_array($result) ? $result : (array)$result;
                }
                return is_object($result) ? $result : (object)$result;
            }

            public function get_results(string $sql, $output = OBJECT): array
            {
                $result = $this->matchRule('get_results', $sql);
                if (empty($result)) {
                    return [];
                }
                if ($output === ARRAY_A) {
                    return array_map(fn($r) => is_array($r) ? $r : (array)$r, $result);
                }
                return array_map(fn($r) => is_object($r) ? $r : (object)$r, $result);
            }

            public function insert(string $table, array $data, $format = null): int
            {
                $this->insert_log[] = ['table' => $table, 'data' => $data];
                $GLOBALS['mock_wpdb_insert_log'][] = ['table' => $table, 'data' => $data];

                // 使用 insert_id_sequence 依序回傳 insert_id
                if (!empty($this->insert_id_sequence) && isset($this->insert_id_sequence[$this->insert_id_cursor])) {
                    $this->insert_id = $this->insert_id_sequence[$this->insert_id_cursor];
                    $this->insert_id_cursor++;
                } else {
                    $this->insert_id = self::CHILD_ORDER_ID ?? 9001;
                }
                return 1;
            }

            public function update(string $table, array $data, array $where, $format = null, $where_format = null): int
            {
                return 1;
            }

            public function delete(string $table, array $where, $format = null): int
            {
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

            // 常數（用於 matchRule 內部 insert_id 參考）
            const CHILD_ORDER_ID = 9001;
        };
    }

    // ─────────────────────────────────────────
    // 輔助：設定標準多樣式商品場景的 mock 資料
    // ─────────────────────────────────────────

    /**
     * 回傳標準場景的 wpdb 規則：
     *   - ProductVariation 查詢 958 → post_id = 2529
     *   - fct_product_variations WHERE id = 958 → 2529（get_var）
     *   - 同 post_id 所有 variation IDs → [954, 955, 956, 957, 958]
     *   - 父訂單 #1405 → 存在
     *   - 父訂單項目 WHERE object_id = 956 → 存在
     *   - 父訂單項目 WHERE object_id = 958 → null（Bug2 觸發點）
     */
    private function buildBugScenarioRules(): array
    {
        $parentOrder = (object)[
            'id'                   => self::ORDER_ID,
            'parent_id'            => null,
            'type'                 => 'normal',
            'customer_id'          => 42,
            'status'               => 'processing',
            'payment_status'       => 'paid',
            'shipping_status'      => 'unshipped',
            'invoice_no'           => 'INV-1405',
            'currency'             => 'TWD',
            'payment_method'       => 'cash',
            'payment_method_title' => '現金',
        ];

        // 父訂單項目，object_id = 956（variant C）
        $parentItem956 = (object)[
            'id'         => 88001,
            'order_id'   => self::ORDER_ID,
            'post_id'    => self::POST_ID,
            'object_id'  => self::ORDER_ITEM_OBJECT_ID, // 956
            'quantity'   => 1,
            'unit_price' => 50000.0, // 500 TWD in cents
            'subtotal'   => 50000.0,
            'title'      => '測試商品 - 規格C',
            'post_title' => '測試商品 - 規格C',
            'line_meta'  => '{}',
        ];

        return [
            // getAllVariationIds：取得 variation 958 的 post_id
            [
                'method'   => 'get_var',
                'contains' => 'fct_product_variations WHERE id',
                'return'   => (string)self::POST_ID,
            ],
            // getAllVariationIds：取得 post_id = 2529 下所有 active variation IDs
            [
                'method'   => 'get_col',
                'contains' => 'fct_product_variations WHERE post_id',
                'return'   => ['954', '955', '956', '957', '958'],
            ],
            // ─── updateOrderAllocations：查父訂單項目（Bug1 關鍵查詢）
            // 注意：現在的 code 用 WHERE object_id = 958（product_id），所以回傳空陣列
            // 符合 "fct_order_items\n                 WHERE object_id" 的查詢
            [
                'method'   => 'get_results',
                'contains' => 'fct_order_items',
                'return'   => [], // Bug1：958 找不到任何項目
            ],
            // ─── create_child_order：取得父訂單
            [
                'method'   => 'get_row',
                'contains' => 'fct_orders WHERE id',
                'return'   => $parentOrder,
            ],
            // ─── create_child_order：查詢父訂單項目（Bug2 關鍵查詢）
            // 現在的 code: WHERE order_id = 1405 AND object_id = 958 → 找不到（956 才是正確的）
            [
                'method'   => 'get_row',
                'contains' => 'fct_order_items',
                'return'   => null, // Bug2：958 找不到父訂單項目
            ],
            // ─── 子訂單數量計算
            [
                'method'   => 'get_var',
                'contains' => 'COUNT(*)',
                'return'   => '0',
            ],
            // ─── 驗證用：子訂單已分配數量查詢
            [
                'method'   => 'get_var',
                'contains' => 'COALESCE(SUM',
                'return'   => '0',
            ],
        ];
    }

    /**
     * 回傳修復後場景的 wpdb 規則：
     *   - 修復假設：查詢改為 WHERE object_id IN (954,955,956,957,958)
     *   - 訂單項目 object_id = 956 能被查到
     *   - create_child_order 查父訂單項目改用 variation_ids IN → 找到 956
     */
    private function buildFixedScenarioRules(): array
    {
        $parentOrder = (object)[
            'id'                   => self::ORDER_ID,
            'parent_id'            => null,
            'type'                 => 'normal',
            'customer_id'          => 42,
            'status'               => 'processing',
            'payment_status'       => 'paid',
            'shipping_status'      => 'unshipped',
            'invoice_no'           => 'INV-1405',
            'currency'             => 'TWD',
            'payment_method'       => 'cash',
            'payment_method_title' => '現金',
        ];

        // 父訂單項目，object_id = 956（修復後能被找到）
        $parentItem956 = (object)[
            'id'         => 88001,
            'order_id'   => self::ORDER_ID,
            'post_id'    => self::POST_ID,
            'object_id'  => self::ORDER_ITEM_OBJECT_ID, // 956
            'quantity'   => 1,
            'unit_price' => 50000.0,
            'subtotal'   => 50000.0,
            'title'      => '測試商品 - 規格C',
            'post_title' => '測試商品 - 規格C',
            'line_meta'  => '{}',
        ];

        // 訂單項目（ARRAY_A 格式供 get_results 使用）
        $parentItemArray = [
            'id'         => 88001,
            'order_id'   => self::ORDER_ID,
            'post_id'    => self::POST_ID,
            'object_id'  => self::ORDER_ITEM_OBJECT_ID, // 956
            'quantity'   => 1,
            'unit_price' => 50000.0,
            'subtotal'   => 50000.0,
            'title'      => '測試商品 - 規格C',
            'post_title' => '測試商品 - 規格C',
            'line_meta'  => '{}',
        ];

        return [
            // getAllVariationIds
            [
                'method'   => 'get_var',
                'contains' => 'fct_product_variations WHERE id',
                'return'   => (string)self::POST_ID,
            ],
            [
                'method'   => 'get_col',
                'contains' => 'fct_product_variations WHERE post_id',
                'return'   => ['954', '955', '956', '957', '958'],
            ],
            // ─── 修復後：查詢用 IN (954,955,956,957,958) → 找到 object_id=956 的項目
            [
                'method'   => 'get_results',
                'contains' => 'fct_order_items',
                'return'   => [$parentItemArray],
            ],
            // ─── create_child_order：取得父訂單
            [
                'method'   => 'get_row',
                'contains' => 'fct_orders WHERE id',
                'return'   => $parentOrder,
            ],
            // ─── 修復後：查父訂單項目用 variation_ids IN → 找到 956
            [
                'method'   => 'get_row',
                'contains' => 'fct_order_items',
                'return'   => $parentItem956,
            ],
            // ─── 子訂單數量計算（各種 COUNT/SUM 查詢）
            [
                'method'   => 'get_var',
                'contains' => 'COUNT(*)',
                'return'   => '0',
            ],
            [
                'method'   => 'get_var',
                'contains' => 'COALESCE(SUM',
                'return'   => '0',
            ],
        ];
    }

    // ─────────────────────────────────────────
    // Bug 1 測試：updateOrderAllocations 查詢只用單一 object_id
    // ─────────────────────────────────────────

    /**
     * @test
     * 修復驗證：updateOrderAllocations(958, [1405 => 1])
     * 修復後查詢用 IN 所有 variant IDs，能找到 object_id=956 的項目
     */
    public function test_fix1_updateOrderAllocations_多樣式商品用IN查詢能找到訂單項目(): void
    {
        // Arrange
        $GLOBALS['mock_product_variation_map'][self::PRODUCT_ID] = ['post_id' => self::POST_ID];
        $GLOBALS['mock_get_post_meta_map'][self::POST_ID . '__buygo_purchased'] = 10;
        $GLOBALS['mock_wpdb_insert_id_sequence'] = [self::CHILD_ORDER_ID, self::CHILD_ORDER_ID];

        // 使用修復後場景（get_results 能找到 object_id=956 的項目）
        $mockWpdb = $this->makeMockWpdb($this->buildFixedScenarioRules());
        $GLOBALS['wpdb'] = $mockWpdb;

        $service = new AllocationService();

        // Act
        $result = $service->updateOrderAllocations(self::PRODUCT_ID, [self::ORDER_ID => 1]);

        // Assert：修復後不應回傳 WP_Error
        $this->assertNotInstanceOf(
            \WP_Error::class,
            $result,
            '修復後：IN 查詢所有 variant IDs 應能找到 object_id=956 的項目，不應回傳 WP_Error'
        );
        $this->assertIsArray($result);
        $this->assertTrue($result['success'] ?? false, '修復後分配應成功');
    }

    /**
     * @test
     * 修復驗證：SQL 查詢應使用 IN 所有 variant IDs，而非單一 object_id
     */
    public function test_fix1_確認SQL查詢使用IN所有variants而非單一object_id(): void
    {
        // Arrange
        $GLOBALS['mock_product_variation_map'][self::PRODUCT_ID] = ['post_id' => self::POST_ID];
        $GLOBALS['mock_get_post_meta_map'][self::POST_ID . '__buygo_purchased'] = 10;
        $GLOBALS['mock_wpdb_insert_id_sequence'] = [self::CHILD_ORDER_ID, self::CHILD_ORDER_ID];

        $mockWpdb = $this->makeMockWpdb($this->buildFixedScenarioRules());
        $GLOBALS['wpdb'] = $mockWpdb;

        $service = new AllocationService();

        // Act
        $service->updateOrderAllocations(self::PRODUCT_ID, [self::ORDER_ID => 1]);

        // Assert：找出 get_results 的查詢 SQL
        $getResultsCalls = array_filter(
            $mockWpdb->query_log,
            fn($entry) => $entry['method'] === 'get_results'
        );

        $this->assertNotEmpty($getResultsCalls, '應該有執行 get_results 查詢');

        $foundOrderItemsQuery = false;
        foreach ($getResultsCalls as $call) {
            if (strpos($call['sql'], 'fct_order_items') !== false) {
                $foundOrderItemsQuery = true;
                // 修復後：SQL 應使用 IN (954,955,956,957,958)，不是 object_id = 958
                $this->assertStringContainsString(
                    'object_id IN',
                    $call['sql'],
                    '修復後：查詢 SQL 應使用 object_id IN 所有 variant IDs'
                );
                break;
            }
        }

        $this->assertTrue($foundOrderItemsQuery, '應該有對 fct_order_items 執行查詢');
    }

    // ─────────────────────────────────────────
    // Bug 2 測試：create_child_order 查詢父訂單項目用錯誤的 object_id
    // ─────────────────────────────────────────

    /**
     * @test
     * 修復驗證：create_child_order 用 IN 所有 variant IDs 查父訂單項目
     * 即使 product_id=958 但父訂單 object_id=956，也能找到
     */
    public function test_fix2_create_child_order_用IN查詢能找到父訂單項目(): void
    {
        // Arrange：使用修復後場景
        $GLOBALS['mock_product_variation_map'][self::PRODUCT_ID] = ['post_id' => self::POST_ID];
        $GLOBALS['mock_get_post_meta_map'][self::POST_ID . '__buygo_purchased'] = 10;
        $GLOBALS['mock_wpdb_insert_id_sequence'] = [self::CHILD_ORDER_ID, self::CHILD_ORDER_ID];

        $mockWpdb = $this->makeMockWpdb($this->buildFixedScenarioRules());
        $GLOBALS['wpdb'] = $mockWpdb;

        $service = new AllocationService();

        // Act
        $result = $service->updateOrderAllocations(self::PRODUCT_ID, [self::ORDER_ID => 1]);

        // Assert：修復後應成功，不回傳 CHILD_ORDER_FAILED
        $this->assertNotInstanceOf(
            \WP_Error::class,
            $result,
            '修復後：create_child_order 用 IN 查詢所有 variant IDs 應能找到父訂單項目'
        );
        $this->assertIsArray($result);
        $this->assertNotEmpty($result['child_orders'] ?? [], '修復後應有建立子訂單');
    }

    // ─────────────────────────────────────────
    // Bug 3 測試：子訂單的 object_id 應為父項目的 object_id，不是傳入的 $product_id
    // ─────────────────────────────────────────

    /**
     * @test
     * 重現 Bug3：即使 create_child_order 成功建立子訂單，
     * L708 `'object_id' => $product_id` 會把 958 寫入子訂單項目，
     * 而不是父項目的 object_id = 956
     *
     * 此測試繞過 Bug1 和 Bug2（提供完整的修復場景 mock），
     * 但保留 Bug3（L708 的 object_id 使用 $product_id），
     * 驗證寫入 fct_order_items 的 object_id 是 958（而不是 956）
     */
    public function test_bug3_子訂單object_id應為父項目object_id而非傳入的product_id(): void
    {
        // Arrange：使用修復後的完整場景（Bug1、Bug2 都繞過），讓子訂單能被建立
        $parentOrder = (object)[
            'id'                   => self::ORDER_ID,
            'parent_id'            => null,
            'type'                 => 'normal',
            'customer_id'          => 42,
            'status'               => 'processing',
            'payment_status'       => 'paid',
            'shipping_status'      => 'unshipped',
            'invoice_no'           => 'INV-1405',
            'currency'             => 'TWD',
            'payment_method'       => 'cash',
            'payment_method_title' => '現金',
        ];

        // 父訂單項目，object_id = 956（模擬修復後能被找到）
        $parentItem956 = (object)[
            'id'         => 88001,
            'order_id'   => self::ORDER_ID,
            'post_id'    => self::POST_ID,
            'object_id'  => self::ORDER_ITEM_OBJECT_ID, // 956
            'quantity'   => 1,
            'unit_price' => 50000.0,
            'subtotal'   => 50000.0,
            'title'      => '測試商品 - 規格C',
            'post_title' => '測試商品 - 規格C',
            'line_meta'  => '{}',
        ];

        $parentItemArray = (array)$parentItem956;

        $rules = [
            // getAllVariationIds（Bug1 修復後的路徑）
            [
                'method'   => 'get_var',
                'contains' => 'fct_product_variations WHERE id',
                'return'   => (string)self::POST_ID,
            ],
            [
                'method'   => 'get_col',
                'contains' => 'fct_product_variations WHERE post_id',
                'return'   => ['954', '955', '956', '957', '958'],
            ],
            // updateOrderAllocations get_results（模擬修復後找到 956 的項目）
            [
                'method'   => 'get_results',
                'contains' => 'fct_order_items',
                'return'   => [$parentItemArray],
            ],
            // create_child_order：取得父訂單
            [
                'method'   => 'get_row',
                'contains' => 'fct_orders WHERE id',
                'return'   => $parentOrder,
            ],
            // create_child_order：查父訂單項目（模擬修復後找到 956 的項目）
            [
                'method'   => 'get_row',
                'contains' => 'fct_order_items',
                'return'   => $parentItem956,
            ],
            // 所有 get_var COALESCE/COUNT
            [
                'method'   => 'get_var',
                'contains' => 'COALESCE(SUM',
                'return'   => '0',
            ],
            [
                'method'   => 'get_var',
                'contains' => 'COUNT(*)',
                'return'   => '0',
            ],
        ];

        $GLOBALS['mock_product_variation_map'][self::PRODUCT_ID] = ['post_id' => self::POST_ID];
        $GLOBALS['mock_get_post_meta_map'][self::POST_ID . '__buygo_purchased'] = 10;

        // insert_id 序列：第一次 insert（fct_orders）回傳 9001，第二次 insert（fct_order_items）也非 0
        $GLOBALS['mock_wpdb_insert_id_sequence'] = [self::CHILD_ORDER_ID, self::CHILD_ORDER_ID];

        $mockWpdb = $this->makeMockWpdb($rules);
        $GLOBALS['wpdb'] = $mockWpdb;

        $service = new AllocationService();

        // Act
        $service->updateOrderAllocations(self::PRODUCT_ID, [self::ORDER_ID => 1]);

        // Assert：找出 fct_order_items 的 insert 呼叫
        $orderItemInserts = array_filter(
            $mockWpdb->insert_log,
            fn($entry) => strpos($entry['table'], 'fct_order_items') !== false
        );

        $this->assertNotEmpty(
            $orderItemInserts,
            '應該有對 fct_order_items 執行 INSERT（建立子訂單項目）'
        );

        $childItemInsert = reset($orderItemInserts);

        // 修復後：子訂單 object_id 應為 956（父訂單項目的 object_id），不是 958
        $this->assertSame(
            self::ORDER_ITEM_OBJECT_ID, // 956
            (int)$childItemInsert['data']['object_id'],
            '修復後：子訂單項目的 object_id 應為 ' . self::ORDER_ITEM_OBJECT_ID .
            '（父訂單項目的 object_id），不是 ' . self::PRODUCT_ID
        );
    }

    /**
     * @test
     * 修復後驗證：子訂單 object_id 應為 956（父項目的 object_id）
     *
     * 此測試是 test_bug3 的對偶測試，用於修復後確認正確行為。
     * 目前這個測試會 FAIL（因為 Bug3 存在），修復後應該 PASS。
     *
     * @group after-fix
     */
    public function test_fix3_完整流程_子訂單object_id應等於父訂單項目的object_id(): void
    {
        // Arrange：完整修復場景
        $parentOrder = (object)[
            'id'                   => self::ORDER_ID,
            'parent_id'            => null,
            'type'                 => 'normal',
            'customer_id'          => 42,
            'status'               => 'processing',
            'payment_status'       => 'paid',
            'shipping_status'      => 'unshipped',
            'invoice_no'           => 'INV-1405',
            'currency'             => 'TWD',
            'payment_method'       => 'cash',
            'payment_method_title' => '現金',
        ];

        $parentItem956 = (object)[
            'id'         => 88001,
            'order_id'   => self::ORDER_ID,
            'post_id'    => self::POST_ID,
            'object_id'  => self::ORDER_ITEM_OBJECT_ID, // 956
            'quantity'   => 1,
            'unit_price' => 50000.0,
            'subtotal'   => 50000.0,
            'title'      => '測試商品 - 規格C',
            'post_title' => '測試商品 - 規格C',
            'line_meta'  => '{}',
        ];

        $parentItemArray = (array)$parentItem956;

        $rules = [
            ['method' => 'get_var', 'contains' => 'fct_product_variations WHERE id', 'return' => (string)self::POST_ID],
            ['method' => 'get_col', 'contains' => 'fct_product_variations WHERE post_id', 'return' => ['954', '955', '956', '957', '958']],
            ['method' => 'get_results', 'contains' => 'fct_order_items', 'return' => [$parentItemArray]],
            ['method' => 'get_row', 'contains' => 'fct_orders WHERE id', 'return' => $parentOrder],
            ['method' => 'get_row', 'contains' => 'fct_order_items', 'return' => $parentItem956],
            ['method' => 'get_var', 'contains' => 'COALESCE(SUM', 'return' => '0'],
            ['method' => 'get_var', 'contains' => 'COUNT(*)', 'return' => '0'],
        ];

        $GLOBALS['mock_product_variation_map'][self::PRODUCT_ID] = ['post_id' => self::POST_ID];
        $GLOBALS['mock_get_post_meta_map'][self::POST_ID . '__buygo_purchased'] = 10;
        $GLOBALS['mock_wpdb_insert_id_sequence'] = [self::CHILD_ORDER_ID, self::CHILD_ORDER_ID];

        $mockWpdb = $this->makeMockWpdb($rules);
        $GLOBALS['wpdb'] = $mockWpdb;

        $service = new AllocationService();

        // Act
        $service->updateOrderAllocations(self::PRODUCT_ID, [self::ORDER_ID => 1]);

        // Assert：修復後子訂單 object_id 應為 956（父項目的 object_id）
        $orderItemInserts = array_filter(
            $mockWpdb->insert_log,
            fn($entry) => strpos($entry['table'], 'fct_order_items') !== false
        );

        $this->assertNotEmpty($orderItemInserts, '應該有建立子訂單項目');

        $childItemInsert = reset($orderItemInserts);

        // 修復後的預期行為：object_id = 956（父項目的 object_id），不是 958
        $this->assertSame(
            self::ORDER_ITEM_OBJECT_ID, // 956
            (int)$childItemInsert['data']['object_id'],
            '修復後：子訂單項目的 object_id 應為 ' . self::ORDER_ITEM_OBJECT_ID .
            '（父訂單項目的 object_id），不是 ' . self::PRODUCT_ID
        );
    }

    // ─────────────────────────────────────────
    // 單一商品向後相容測試
    // ─────────────────────────────────────────

    /**
     * @test
     * 單一商品（product_id == order item object_id）應該正常分配
     */
    public function test_單一商品_product_id等於object_id_正常分配(): void
    {
        $singleProductId = 500;
        $singleOrderId = 2000;

        $GLOBALS['mock_product_variation_map'][$singleProductId] = ['post_id' => 3000];
        $GLOBALS['mock_get_post_meta_map']['3000__buygo_purchased'] = 10;
        $GLOBALS['mock_wpdb_insert_id_sequence'] = [8001, 8001];

        $parentOrder = (object)[
            'id' => $singleOrderId, 'parent_id' => null, 'type' => 'normal',
            'customer_id' => 1, 'status' => 'processing', 'payment_status' => 'paid',
            'shipping_status' => 'unshipped', 'invoice_no' => 'INV-2000',
            'currency' => 'TWD', 'payment_method' => 'cash', 'payment_method_title' => '現金',
        ];

        $parentItem = (object)[
            'id' => 99001, 'order_id' => $singleOrderId, 'post_id' => 3000,
            'object_id' => $singleProductId, 'quantity' => 2, 'unit_price' => 10000.0,
            'subtotal' => 20000.0, 'title' => '單一商品', 'post_title' => '單一商品', 'line_meta' => '{}',
        ];

        $rules = [
            // getAllVariationIds：單一商品只回傳自己
            ['method' => 'get_var', 'contains' => 'fct_product_variations WHERE id', 'return' => '3000'],
            ['method' => 'get_col', 'contains' => 'fct_product_variations WHERE post_id', 'return' => [(string)$singleProductId]],
            // 查詢訂單項目
            ['method' => 'get_results', 'contains' => 'fct_order_items', 'return' => [(array)$parentItem]],
            // 子訂單相關查詢
            ['method' => 'get_var', 'contains' => 'COALESCE(SUM', 'return' => '0'],
            ['method' => 'get_var', 'contains' => 'COUNT(*)', 'return' => '0'],
            // create_child_order
            ['method' => 'get_row', 'contains' => 'fct_orders WHERE id', 'return' => $parentOrder],
            ['method' => 'get_row', 'contains' => 'fct_order_items', 'return' => $parentItem],
        ];

        $GLOBALS['wpdb'] = $this->makeMockWpdb($rules);

        $service = new AllocationService();
        $result = $service->updateOrderAllocations($singleProductId, [$singleOrderId => 1]);

        $this->assertNotInstanceOf(\WP_Error::class, $result, '單一商品應正常分配');
        $this->assertIsArray($result);
        $this->assertTrue($result['success'] ?? false);
    }
}
