<?php

/**
 * OrderService 單元測試 — splitOrder() 場景覆蓋
 *
 * 測試案例：
 *   1. 正常拆單 → 回傳含 new_order_id / order_number 的陣列
 *   2. 訂單不存在 → 回傳 WP_Error('ORDER_NOT_FOUND')
 *   3. 空的 split_data（無 split_items 鍵）→ 回傳 WP_Error('NO_ITEMS_SELECTED')
 *   4. 空陣列 split_items → 同上
 *   5. 訂單沒有商品 → 回傳 WP_Error('NO_ORDER_ITEMS')
 *   6. 拆分數量超過可用量 → 回傳 WP_Error('QUANTITY_EXCEEDED')
 *   7. 拆分數量為 0 → 回傳 WP_Error('INVALID_QUANTITY')
 *   8. order_item_id 不屬於該訂單 → 回傳 WP_Error('ORDER_ITEM_NOT_FOUND')
 *
 * Mock 策略：
 *   使用 QueryAwareMockWpdb（規則陣列模式），與 AllocationServiceTest 相同風格。
 *   OrderService 建構子自動 new 相依物件（DebugService / OrderFormatter / OrderShippingManager），
 *   無需額外注入。
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\OrderService;

class OrderServiceTest extends TestCase
{
    // ─────────────────────────────────────────
    // 測試常數
    // ─────────────────────────────────────────

    /** 測試用父訂單 ID */
    const ORDER_ID = 5001;

    /** 測試用訂單項目 ID */
    const ORDER_ITEM_ID = 7001;

    /** 測試用子訂單 ID（insert 後回傳） */
    const NEW_ORDER_ID = 9999;

    // ─────────────────────────────────────────
    // setUp / tearDown
    // ─────────────────────────────────────────

    /** 儲存原始 $wpdb，測試結束後還原 */
    private $originalWpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalWpdb    = $GLOBALS['wpdb'] ?? null;

        // 初始化 mock global 狀態
        $GLOBALS['mock_wpdb_insert_id_sequence'] = [];
        $GLOBALS['mock_wpdb_query_log']          = [];
        $GLOBALS['mock_wpdb_insert_log']         = [];
    }

    protected function tearDown(): void
    {
        // 還原全域 $wpdb，避免汙染其他測試
        if ($this->originalWpdb !== null) {
            $GLOBALS['wpdb'] = $this->originalWpdb;
        } else {
            unset($GLOBALS['wpdb']);
        }

        unset($GLOBALS['mock_wpdb_insert_id_sequence']);
        unset($GLOBALS['mock_wpdb_query_log']);
        unset($GLOBALS['mock_wpdb_insert_log']);

        parent::tearDown();
    }

    // ─────────────────────────────────────────
    // 輔助：建立 QueryAwareMockWpdb
    // ─────────────────────────────────────────

    /**
     * 建立可程式化的 wpdb mock。
     *
     * 規則格式（陣列，按順序比對，第一個命中的生效）：
     *   [
     *     'method'   => 'get_row' | 'get_results' | 'get_var',
     *     'contains' => 'SQL 片段',   // 用 strpos 比對
     *     'return'   => mixed,         // 比對成功時回傳的值
     *   ]
     *
     * insert() 永遠回傳 1（成功），insert_id 依 $insertIds 序列遞增。
     *
     * @param array $rules     查詢規則陣列
     * @param array $insertIds insert 後依序設定的 insert_id 值
     */
    private function makeMockWpdb(array $rules = [], array $insertIds = []): object
    {
        return new class($rules, $insertIds) {
            public string $prefix    = 'wp_';
            public int    $insert_id = 0;
            public string $last_error = '';

            /** @var array */
            private array $rules;

            /** @var array insert_id 回傳序列 */
            private array $insertIds;
            private int   $insertCursor = 0;

            /** @var array SQL 查詢記錄 */
            public array $queryLog  = [];

            /** @var array insert 呼叫記錄 */
            public array $insertLog = [];

            public function __construct(array $rules, array $insertIds)
            {
                $this->rules     = $rules;
                $this->insertIds = $insertIds;
            }

            /**
             * 模擬 wpdb->prepare()：把 %d / %s 佔位符替換成實際值。
             */
            public function prepare(string $query, ...$args): string
            {
                // 支援 prepare($sql, $arg1, $arg2) 和 prepare($sql, [$arg1, $arg2]) 兩種用法
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
                    $posd = strpos($result, '%d');
                    $poss = strpos($result, '%s');
                    if ($posd !== false && ($poss === false || $posd <= $poss)) {
                        $result = preg_replace('/%d/', (int) $arg, $result, 1);
                    } else {
                        $result = preg_replace('/%s/', "'" . addslashes((string) $arg) . "'", $result, 1);
                    }
                }
                return $result;
            }

            /**
             * 依規則比對 SQL，找到第一條命中規則就回傳 return 值。
             * 同時記錄查詢到 queryLog / $GLOBALS['mock_wpdb_query_log']。
             */
            private function matchRule(string $method, string $sql): mixed
            {
                $entry = ['method' => $method, 'sql' => $sql];
                $this->queryLog[]                      = $entry;
                $GLOBALS['mock_wpdb_query_log'][]      = $entry;

                foreach ($this->rules as $rule) {
                    if (($rule['method'] ?? '') !== $method) {
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

            public function get_var(string $sql): mixed
            {
                return $this->matchRule('get_var', $sql);
            }

            public function get_row(string $sql, $output = OBJECT): mixed
            {
                $result = $this->matchRule('get_row', $sql);
                if ($result === null) {
                    return null;
                }
                if ($output === ARRAY_A) {
                    return is_array($result) ? $result : (array) $result;
                }
                return is_object($result) ? $result : (object) $result;
            }

            public function get_results(string $sql, $output = OBJECT): array
            {
                $result = $this->matchRule('get_results', $sql);
                if (empty($result)) {
                    return [];
                }
                if ($output === ARRAY_A) {
                    return array_map(
                        fn ($r) => is_array($r) ? $r : (array) $r,
                        (array) $result
                    );
                }
                return array_map(
                    fn ($r) => is_object($r) ? $r : (object) $r,
                    (array) $result
                );
            }

            public function insert(string $table, array $data, $format = null): int
            {
                $entry = ['table' => $table, 'data' => $data];
                $this->insertLog[]                     = $entry;
                $GLOBALS['mock_wpdb_insert_log'][]     = $entry;

                // 依序設定 insert_id
                if (isset($this->insertIds[$this->insertCursor])) {
                    $this->insert_id = $this->insertIds[$this->insertCursor];
                    $this->insertCursor++;
                } else {
                    $this->insert_id++;
                }
                return 1; // 1 = 成功
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
                $this->queryLog[] = ['method' => 'query', 'sql' => $sql];
                return true;
            }

            public function get_charset_collate(): string
            {
                return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
            }
        };
    }

    // ─────────────────────────────────────────
    // 輔助：建立標準父訂單物件
    // ─────────────────────────────────────────

    /**
     * 回傳一個標準父訂單 stdClass 物件。
     */
    private function buildParentOrder(): object
    {
        return (object) [
            'id'                   => self::ORDER_ID,
            'parent_id'            => null,
            'customer_id'          => 42,
            'status'               => 'processing',
            'payment_status'       => 'paid',
            'shipping_status'      => 'unshipped',
            'payment_method'       => 'cash',
            'payment_method_title' => '現金',
            'currency'             => 'TWD',
            'invoice_no'           => 'INV-5001',
        ];
    }

    /**
     * 回傳一個標準父訂單商品項目（ARRAY_A 格式）。
     *
     * @param int $quantity 商品數量
     */
    private function buildOrderItem(int $quantity = 3): array
    {
        return [
            'id'               => self::ORDER_ITEM_ID,
            'order_id'         => self::ORDER_ID,
            'post_id'          => 1001,
            'object_id'        => 2001,
            'quantity'         => $quantity,
            'unit_price'       => 30000,  // 300 TWD（以分計）
            'subtotal'         => 30000 * $quantity,
            'line_total'       => 30000 * $quantity,
            'title'            => '測試商品',
            'post_title'       => '測試商品',
            'fulfillment_type' => 'physical',
            'payment_type'     => 'onetime',
            'cart_index'       => 0,
            'line_meta'        => '{}',
        ];
    }

    // ─────────────────────────────────────────
    // 輔助：建立正常拆單的 wpdb 規則組
    // ─────────────────────────────────────────

    /**
     * 回傳正常拆單場景所需的 wpdb 規則：
     *   - 父訂單存在
     *   - 訂單商品存在（quantity = 3）
     *   - 已拆分數量 = 0（可用 3）
     *   - 現有子訂單數量 = 0（第一次拆，suffix = 1）
     *   - _allocated_qty 查詢 = 0
     *
     * @param int $alreadySplitQty 已拆出的數量（模擬部分已拆）
     * @param int $existingChildCount 現有子訂單數量
     */
    private function buildNormalSplitRules(int $alreadySplitQty = 0, int $existingChildCount = 0): array
    {
        return [
            // 查詢父訂單：SELECT * FROM fct_orders WHERE id = %d
            [
                'method'   => 'get_row',
                'contains' => 'fct_orders WHERE id',
                'return'   => $this->buildParentOrder(),
            ],
            // 查詢訂單商品：SELECT * FROM fct_order_items WHERE order_id = %d
            [
                'method'   => 'get_results',
                'contains' => 'fct_order_items WHERE order_id',
                'return'   => [$this->buildOrderItem(3)],
            ],
            // 查詢已拆分數量（COALESCE SUM 子查詢）
            [
                'method'   => 'get_var',
                'contains' => 'COALESCE(SUM(oi.quantity)',
                'return'   => (string) $alreadySplitQty,
            ],
            // 查詢現有子訂單數量：SELECT COUNT(*) FROM fct_orders WHERE parent_id = %d
            [
                'method'   => 'get_var',
                'contains' => 'COUNT(*)',
                'return'   => (string) $existingChildCount,
            ],
            // 複製地址：SELECT * FROM fct_order_addresses WHERE order_id = %d（回傳空，跳過地址複製）
            [
                'method'   => 'get_results',
                'contains' => 'fct_order_addresses',
                'return'   => [],
            ],
            // 同步 _allocated_qty：重新計算子訂單已分配數量
            [
                'method'   => 'get_var',
                'contains' => 'WHERE o.parent_id',
                'return'   => '1',
            ],
        ];
    }

    // ─────────────────────────────────────────
    // Test 1：正常拆單 → 回傳成功陣列
    // ─────────────────────────────────────────

    /**
     * 正常場景：父訂單存在、商品 quantity = 3、拆 1 件。
     * 預期：回傳陣列含 original_order_id、new_order_id、order_number。
     */
    public function test_split_order_creates_child_orders(): void
    {
        // 設定 insert 後第一次 insert_id = NEW_ORDER_ID（即子訂單的 ID）
        $wpdb            = $this->makeMockWpdb(
            $this->buildNormalSplitRules(),
            [self::NEW_ORDER_ID]  // 第一次 insert（建子訂單）回傳此 ID
        );
        $GLOBALS['wpdb'] = $wpdb;

        $service    = new OrderService();
        $split_data = [
            'split_items' => [
                ['order_item_id' => self::ORDER_ITEM_ID, 'quantity' => 1],
            ],
        ];

        $result = $service->splitOrder(self::ORDER_ID, $split_data);

        // 確認沒有回傳 WP_Error
        $this->assertIsArray($result, '正常拆單應回傳 array，不是 WP_Error');

        // 確認三個必要欄位都存在
        $this->assertArrayHasKey('original_order_id', $result);
        $this->assertArrayHasKey('new_order_id', $result);
        $this->assertArrayHasKey('order_number', $result);

        // 確認 original_order_id 等於傳入的父訂單 ID
        $this->assertSame(self::ORDER_ID, $result['original_order_id']);

        // 確認 new_order_id 等於 mock insert_id
        $this->assertSame(self::NEW_ORDER_ID, $result['new_order_id']);

        // 確認 order_number 格式：{invoice_no}-{split_suffix}，suffix = 現有子訂單數 + 1 = 1
        $this->assertStringContainsString('INV-5001-1', $result['order_number']);

        // 確認至少有一次 insert（建子訂單記錄）
        $this->assertNotEmpty($wpdb->insertLog, '應有至少一筆 insert（子訂單記錄）');

        // 確認第一筆 insert 是寫入訂單表（fct_orders）
        $firstInsert = $wpdb->insertLog[0];
        $this->assertStringContainsString('fct_orders', $firstInsert['table']);

        // 確認子訂單的 parent_id 正確指向父訂單
        $this->assertSame(self::ORDER_ID, $firstInsert['data']['parent_id']);

        // 確認子訂單狀態為 pending
        $this->assertSame('pending', $firstInsert['data']['status']);
    }

    // ─────────────────────────────────────────
    // Test 2：訂單不存在 → WP_Error('ORDER_NOT_FOUND')
    // ─────────────────────────────────────────

    /**
     * 傳入不存在的 order_id。
     * 預期：get_row 回傳 null → splitOrder 回傳 WP_Error，code = ORDER_NOT_FOUND。
     */
    public function test_split_order_returns_error_for_nonexistent_order(): void
    {
        // get_row 對所有 fct_orders 查詢回傳 null（訂單不存在）
        $wpdb = $this->makeMockWpdb([
            [
                'method'   => 'get_row',
                'contains' => 'fct_orders WHERE id',
                'return'   => null,
            ],
        ]);
        $GLOBALS['wpdb'] = $wpdb;

        $service = new OrderService();
        $result  = $service->splitOrder(99999, [
            'split_items' => [
                ['order_item_id' => 1, 'quantity' => 1],
            ],
        ]);

        // 確認回傳 WP_Error
        $this->assertInstanceOf(\WP_Error::class, $result, '不存在的訂單應回傳 WP_Error');
        $this->assertSame('ORDER_NOT_FOUND', $result->get_error_code());
    }

    // ─────────────────────────────────────────
    // Test 3：完全空的 split_data → WP_Error('NO_ITEMS_SELECTED')
    // ─────────────────────────────────────────

    /**
     * 傳入空陣列作為 split_data（無 split_items 鍵）。
     * 預期：splitOrder 在驗證階段就回傳 WP_Error('NO_ITEMS_SELECTED')。
     */
    public function test_split_order_with_empty_split_data(): void
    {
        // 父訂單存在（驗證 split_items 之前先查訂單）
        $wpdb = $this->makeMockWpdb([
            [
                'method'   => 'get_row',
                'contains' => 'fct_orders WHERE id',
                'return'   => $this->buildParentOrder(),
            ],
        ]);
        $GLOBALS['wpdb'] = $wpdb;

        $service = new OrderService();

        // 測試 3a：完全空的陣列
        $result = $service->splitOrder(self::ORDER_ID, []);

        $this->assertInstanceOf(\WP_Error::class, $result, '空的 split_data 應回傳 WP_Error');
        $this->assertSame('NO_ITEMS_SELECTED', $result->get_error_code());
    }

    // ─────────────────────────────────────────
    // Test 4：split_items 為空陣列 → WP_Error('NO_ITEMS_SELECTED')
    // ─────────────────────────────────────────

    /**
     * split_items 鍵存在但值為空陣列。
     * 預期：同樣回傳 WP_Error('NO_ITEMS_SELECTED')。
     */
    public function test_split_order_with_empty_split_items_array(): void
    {
        $wpdb = $this->makeMockWpdb([
            [
                'method'   => 'get_row',
                'contains' => 'fct_orders WHERE id',
                'return'   => $this->buildParentOrder(),
            ],
        ]);
        $GLOBALS['wpdb'] = $wpdb;

        $service = new OrderService();
        $result  = $service->splitOrder(self::ORDER_ID, ['split_items' => []]);

        $this->assertInstanceOf(\WP_Error::class, $result, '空陣列 split_items 應回傳 WP_Error');
        $this->assertSame('NO_ITEMS_SELECTED', $result->get_error_code());
    }

    // ─────────────────────────────────────────
    // Test 5：訂單沒有商品 → WP_Error('NO_ORDER_ITEMS')
    // ─────────────────────────────────────────

    /**
     * 父訂單存在，但查詢商品項目回傳空陣列。
     * 預期：splitOrder 回傳 WP_Error('NO_ORDER_ITEMS')。
     */
    public function test_split_order_returns_error_when_order_has_no_items(): void
    {
        $wpdb = $this->makeMockWpdb([
            [
                'method'   => 'get_row',
                'contains' => 'fct_orders WHERE id',
                'return'   => $this->buildParentOrder(),
            ],
            [
                'method'   => 'get_results',
                'contains' => 'fct_order_items WHERE order_id',
                'return'   => [],  // 空商品列表
            ],
        ]);
        $GLOBALS['wpdb'] = $wpdb;

        $service = new OrderService();
        $result  = $service->splitOrder(self::ORDER_ID, [
            'split_items' => [
                ['order_item_id' => self::ORDER_ITEM_ID, 'quantity' => 1],
            ],
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result, '無商品的訂單應回傳 WP_Error');
        $this->assertSame('NO_ORDER_ITEMS', $result->get_error_code());
    }

    // ─────────────────────────────────────────
    // Test 6：拆分數量超過可用量 → WP_Error('QUANTITY_EXCEEDED')
    // ─────────────────────────────────────────

    /**
     * 商品 quantity = 3，已拆出 2，可用 1，但要求拆 2。
     * 預期：splitOrder 回傳 WP_Error('QUANTITY_EXCEEDED')。
     */
    public function test_split_order_returns_error_when_quantity_exceeds_available(): void
    {
        $wpdb = $this->makeMockWpdb([
            [
                'method'   => 'get_row',
                'contains' => 'fct_orders WHERE id',
                'return'   => $this->buildParentOrder(),
            ],
            // 商品 quantity = 3
            [
                'method'   => 'get_results',
                'contains' => 'fct_order_items WHERE order_id',
                'return'   => [$this->buildOrderItem(3)],
            ],
            // 已拆出 2（可用 = 3 - 2 = 1）
            [
                'method'   => 'get_var',
                'contains' => 'COALESCE(SUM(oi.quantity)',
                'return'   => '2',
            ],
        ]);
        $GLOBALS['wpdb'] = $wpdb;

        $service = new OrderService();
        $result  = $service->splitOrder(self::ORDER_ID, [
            'split_items' => [
                // 要求拆 2，但只剩 1 可用 → 超過
                ['order_item_id' => self::ORDER_ITEM_ID, 'quantity' => 2],
            ],
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result, '超過可用數量應回傳 WP_Error');
        $this->assertSame('QUANTITY_EXCEEDED', $result->get_error_code());
        // 確認錯誤訊息包含數量資訊
        $this->assertStringContainsString('2', $result->get_error_message());
    }

    // ─────────────────────────────────────────
    // Test 7：拆分數量為 0 → WP_Error('INVALID_QUANTITY')
    // ─────────────────────────────────────────

    /**
     * quantity = 0 為無效。
     * 預期：splitOrder 回傳 WP_Error('INVALID_QUANTITY')。
     */
    public function test_split_order_returns_error_for_zero_quantity(): void
    {
        $wpdb = $this->makeMockWpdb([
            [
                'method'   => 'get_row',
                'contains' => 'fct_orders WHERE id',
                'return'   => $this->buildParentOrder(),
            ],
            [
                'method'   => 'get_results',
                'contains' => 'fct_order_items WHERE order_id',
                'return'   => [$this->buildOrderItem(3)],
            ],
            [
                'method'   => 'get_var',
                'contains' => 'COALESCE(SUM(oi.quantity)',
                'return'   => '0',
            ],
        ]);
        $GLOBALS['wpdb'] = $wpdb;

        $service = new OrderService();
        $result  = $service->splitOrder(self::ORDER_ID, [
            'split_items' => [
                ['order_item_id' => self::ORDER_ITEM_ID, 'quantity' => 0],
            ],
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result, 'quantity = 0 應回傳 WP_Error');
        $this->assertSame('INVALID_QUANTITY', $result->get_error_code());
    }

    // ─────────────────────────────────────────
    // Test 8：order_item_id 不屬於該訂單 → WP_Error('ORDER_ITEM_NOT_FOUND')
    // ─────────────────────────────────────────

    /**
     * 傳入的 order_item_id 在訂單商品列表中找不到。
     * 預期：splitOrder 回傳 WP_Error('ORDER_ITEM_NOT_FOUND')。
     */
    public function test_split_order_returns_error_for_invalid_order_item_id(): void
    {
        $wpdb = $this->makeMockWpdb([
            [
                'method'   => 'get_row',
                'contains' => 'fct_orders WHERE id',
                'return'   => $this->buildParentOrder(),
            ],
            // 訂單只有 ORDER_ITEM_ID = 7001 這筆商品
            [
                'method'   => 'get_results',
                'contains' => 'fct_order_items WHERE order_id',
                'return'   => [$this->buildOrderItem(3)],
            ],
            // 已拆分數量（不影響，ORDER_ITEM_NOT_FOUND 在此之後才查）
            // 注意：COALESCE 查詢只有在 order_item_id 存在時才會被呼叫
            // 這裡傳入一個不存在的 id（99999），會直接在 isset($order_items_map[99999]) 失敗
        ]);
        $GLOBALS['wpdb'] = $wpdb;

        $service = new OrderService();
        $result  = $service->splitOrder(self::ORDER_ID, [
            'split_items' => [
                // 99999 不在訂單商品列表裡
                ['order_item_id' => 99999, 'quantity' => 1],
            ],
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result, '不存在的訂單項目應回傳 WP_Error');
        $this->assertSame('ORDER_ITEM_NOT_FOUND', $result->get_error_code());
        // 確認錯誤訊息提到缺少的 item ID
        $this->assertStringContainsString('99999', $result->get_error_message());
    }
}
