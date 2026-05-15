<?php
/**
 * ShipmentService::get_shipment_items 單元測試（TDD 紅燈先行）
 *
 * 測試 variation JOIN 欄位回傳：variation_id、variation_title、variation_identifier
 *
 * @package BuyGoPlus\Tests\Unit\Services
 * @since 1.5.1
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\ShipmentService;

/**
 * Class ShipmentServiceGetItemsTest
 *
 * 測試 get_shipment_items 的 variation JOIN 行為：
 * - 1.1 包含 variation 三個欄位且值正確
 * - 1.2 LEFT JOIN 沒對到時 variation 欄位回 null（不拋錯）
 * - 1.3 既有欄位 name 與 type 不變
 * - 1.4 SQL 只有單一 JOIN per table，無 N+1
 */
class ShipmentServiceGetItemsTest extends TestCase
{
    /**
     * 保存原始 $GLOBALS['wpdb']，setUp 備份、tearDown 還原。
     * 避免 mock 污染全域，破壞同 process 後續執行的其他測試。
     */
    private $originalWpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->originalWpdb !== null) {
            $GLOBALS['wpdb'] = $this->originalWpdb;
        } else {
            unset($GLOBALS['wpdb']);
        }
        unset($GLOBALS['test_captured_sql']);
        parent::tearDown();
    }

    /**
     * 建立可程式化的 wpdb mock（與 AllocationServiceTest 同風格）
     *
     * 規則：
     *   [ 'contains' => '...', 'method' => 'get_results', 'return' => [...] ]
     *
     * @param array $rules
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

            public function __construct(array $rules)
            {
                $this->rules = $rules;
            }

            public function prepare($query, ...$args): string
            {
                // 展開可能的陣列參數（WordPress 兩種呼叫方式相容）
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

            public function get_var(string $sql)
            {
                $result = $this->matchRule('get_var', $sql);
                return $result ?? null;
            }

            public function get_row(string $sql, $output = OBJECT)
            {
                $result = $this->matchRule('get_row', $sql);
                return $result ?? null;
            }

            public function insert(string $table, array $data, $format = null): int
            {
                return 1;
            }

            public function update(string $table, array $data, array $where, $format = null, $where_format = null)
            {
                return 1;
            }

            public function delete(string $table, array $where, $where_format = null)
            {
                return 1;
            }
        };
    }

    // ========================================
    // 1.1 包含 variation 三個欄位且值正確
    // ========================================

    /**
     * 1.1 get_shipment_items 必須回傳含 variation_id、variation_title、variation_identifier 的資料，且值正確
     *
     * Mock 設計原則：
     * - mock 替代 DB，回傳 JOIN 後的 fixture（這是 DB 執行 JOIN SQL 後的真實結果）
     * - service 的責任是：產生正確的 JOIN SQL + 正確透傳 DB 回傳的欄位
     * - 1.4 確保 SQL 有 JOIN；1.1 確保 service 正確透傳 variation 欄位且值正確
     *
     * 紅燈情境（實作前）：
     * - service 目前 SQL 沒 JOIN → `contains` 比對的是展開後的 SQL
     * - 但 mock `contains: 'buygo_shipment_items'` 會匹配 `FROM wp_buygo_shipment_items si`
     * - 因此 mock 仍會回傳 fixture（即使 SQL 沒 JOIN）
     * - 這使得 1.1 本身現在可能是綠燈（mock 回傳有 variation 欄位的 fixture）
     * - 真正的紅燈由 1.4 承擔（SQL JOIN 計數檢查）
     * - 1.1 的角色轉為：「實作後的值驗證」+ 與 1.4 共同構成完整的契約測試
     */
    public function test_get_shipment_items_includes_variation_fields(): void
    {
        // Fixture：模擬 DB 執行 JOIN SQL 後的回傳（包含 variation_* 欄位）
        $fixture = [
            [
                'id'                  => 1,
                'shipment_id'         => 420,
                'order_id'            => 101,
                'order_item_id'       => 201,
                'product_id'          => 2560,
                'quantity'            => 1,
                'created_at'          => '2026-05-08 10:00:00',
                'variation_id'        => 976,
                'variation_title'     => '(A) 薄荷巧克力',
                'variation_identifier' => 'BUYGO-2560-A',
            ],
        ];

        $mockWpdb = $this->makeMockWpdb([
            [
                'method'   => 'get_results',
                'contains' => 'buygo_shipment_items',
                'return'   => $fixture,
            ],
        ]);

        $GLOBALS['wpdb'] = $mockWpdb;

        $service = new ShipmentService();
        $result  = $service->get_shipment_items(420);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $first = $result[0];

        // 斷言 variation 三個欄位存在且值正確（service 必須正確透傳 DB 回傳的欄位）
        $this->assertArrayHasKey('variation_id', $first, '回傳陣列必須含 variation_id 欄位');
        $this->assertSame(976, (int)$first['variation_id'], 'variation_id 必須是 976');

        $this->assertArrayHasKey('variation_title', $first, '回傳陣列必須含 variation_title 欄位');
        $this->assertSame('(A) 薄荷巧克力', $first['variation_title'], 'variation_title 必須正確');

        $this->assertArrayHasKey('variation_identifier', $first, '回傳陣列必須含 variation_identifier 欄位');
        $this->assertSame('BUYGO-2560-A', $first['variation_identifier'], 'variation_identifier 必須正確');
    }

    // ========================================
    // 1.2 LEFT JOIN 沒對到時 variation 欄位回 null
    // ========================================

    /**
     * 1.2 LEFT JOIN 對不到 variation 時，三個欄位必須是 null，不拋 PHP warning / exception
     *
     * 預期紅燈：service 目前沒 JOIN，variation 欄位不在回傳陣列中。
     * 實作 JOIN 後，LEFT JOIN 沒對到的 row 應該回傳 null 而非缺少 key。
     *
     * 此測試確認：實作 JOIN 後，即使 variation 對不到，
     * key 存在且值為 null（不用 isset 判斷，直接 assertNull）。
     */
    public function test_get_shipment_items_null_variation_for_missing_join(): void
    {
        // Fixture：模擬 DB 執行 LEFT JOIN 後，variation 對不到的 row（欄位為 null）
        // DB 的 LEFT JOIN 行為：找不到對應 variation row 時，variation_* 欄位回傳 null
        $fixture = [
            [
                'id'                  => 2,
                'shipment_id'         => 420,
                'order_id'            => 102,
                'order_item_id'       => 202,
                'product_id'          => 2560,
                'quantity'            => 1,
                'created_at'          => '2026-05-08 10:00:00',
                'variation_id'        => null,
                'variation_title'     => null,
                'variation_identifier' => null,
            ],
        ];

        $mockWpdb = $this->makeMockWpdb([
            [
                'method'   => 'get_results',
                'contains' => 'buygo_shipment_items',
                'return'   => $fixture,
            ],
        ]);

        $GLOBALS['wpdb'] = $mockWpdb;

        $service = new ShipmentService();
        $result  = $service->get_shipment_items(420);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $first = $result[0];

        // 斷言三個 variation 欄位存在（即使值為 null）
        // 預期紅燈：service 沒 JOIN，variation_id key 不存在，assertArrayHasKey 失敗
        $this->assertArrayHasKey('variation_id', $first, '即使 LEFT JOIN 沒對到，variation_id key 必須存在');
        $this->assertNull($first['variation_id'], 'LEFT JOIN 沒對到時 variation_id 必須是 null');

        $this->assertArrayHasKey('variation_title', $first, '即使 LEFT JOIN 沒對到，variation_title key 必須存在');
        $this->assertNull($first['variation_title'], 'LEFT JOIN 沒對到時 variation_title 必須是 null');

        $this->assertArrayHasKey('variation_identifier', $first, '即使 LEFT JOIN 沒對到，variation_identifier key 必須存在');
        $this->assertNull($first['variation_identifier'], 'LEFT JOIN 沒對到時 variation_identifier 必須是 null');
    }

    // ========================================
    // 1.3 既有欄位 name 與 type 不變
    // ========================================

    /**
     * 1.3 JOIN 後既有七個欄位（id, shipment_id, order_id, order_item_id, product_id, quantity, created_at）
     * 的名稱與型別必須不變
     *
     * 說明：此測試是「實作後的綠燈守護」，確認 JOIN 不破壞既有欄位。
     * 紅燈情境：若 service SQL JOIN 用了錯誤別名（如 `oi.id` 覆蓋 `si.id`），
     * 既有欄位值會被覆蓋，本測試捕捉到。
     * 目前 service 沒 JOIN，但 fixture 注入完整欄位，service 如實回傳，
     * 因此 1.3 本身現在可能是綠燈——此測試的主要功能是「實作後的迴歸守護」。
     * 紅燈由 1.1/1.2/1.4 覆蓋；1.3 作為契約守護一起寫入。
     */
    public function test_get_shipment_items_preserves_existing_fields(): void
    {
        // Fixture：完整的 JOIN 後 row，既有欄位 + variation 欄位
        $fixture = [
            [
                'id'               => 99,
                'shipment_id'      => 420,
                'order_id'         => 103,
                'order_item_id'    => 203,
                'product_id'       => 2560,
                'quantity'         => 3,
                'created_at'       => '2026-05-08 12:00:00',
                'variation_id'     => 977,
                'variation_title'  => '(B) 草莓牛奶',
                'variation_identifier' => 'BUYGO-2560-B',
            ],
        ];

        $mockWpdb = $this->makeMockWpdb([
            [
                'method'   => 'get_results',
                'contains' => 'buygo_shipment_items',
                'return'   => $fixture,
            ],
        ]);

        $GLOBALS['wpdb'] = $mockWpdb;

        $service = new ShipmentService();
        $result  = $service->get_shipment_items(420);

        $this->assertNotEmpty($result);
        $first = $result[0];

        // 既有七個欄位必須存在且值不變
        $this->assertArrayHasKey('id', $first);
        $this->assertSame(99, (int)$first['id']);

        $this->assertArrayHasKey('shipment_id', $first);
        $this->assertSame(420, (int)$first['shipment_id']);

        $this->assertArrayHasKey('order_id', $first);
        $this->assertSame(103, (int)$first['order_id']);

        $this->assertArrayHasKey('order_item_id', $first);
        $this->assertSame(203, (int)$first['order_item_id']);

        $this->assertArrayHasKey('product_id', $first);
        $this->assertSame(2560, (int)$first['product_id']);

        $this->assertArrayHasKey('quantity', $first);
        $this->assertSame(3, (int)$first['quantity']);

        $this->assertArrayHasKey('created_at', $first);
        $this->assertSame('2026-05-08 12:00:00', $first['created_at']);
    }

    // ========================================
    // 1.4 SQL 只有單一 JOIN per table，無 N+1
    // ========================================

    /**
     * 1.4 截獲 prepare 後的 SQL 字串，斷言：
     * - 含且僅含一個 LEFT JOIN.*fct_order_items
     * - 含且僅含一個 LEFT JOIN.*fct_product_variations
     * - 無 N+1 子查詢或重複 JOIN
     *
     * 預期紅燈：service 目前 SQL 是 `SELECT * FROM buygo_shipment_items WHERE shipment_id=%d`，
     * 沒有任何 LEFT JOIN，兩個 preg_match_all count 都會是 0。
     */
    public function test_get_shipment_items_sql_has_single_join_per_table(): void
    {
        // 用特製 mock 截獲 prepare 後輸出的 SQL 字串（只截獲一次，不重複計算）
        $mockWpdb = new class {
            public $prefix = 'wp_';
            public $last_error = '';

            public function prepare($query, ...$args): string
            {
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
                    $result = preg_replace('/%d/', (int)$arg, $result, 1);
                }

                // 截獲 prepare 輸出的 SQL
                $GLOBALS['test_captured_sql'] = $result;

                return $result;
            }

            public function get_results(string $sql, $output = OBJECT): array
            {
                // 不再截獲 get_results 的 sql（避免重複計算），直接回空陣列
                return [];
            }
        };

        $GLOBALS['wpdb']             = $mockWpdb;
        $GLOBALS['test_captured_sql'] = '';

        $service = new ShipmentService();
        $service->get_shipment_items(420);

        // 使用 prepare 截獲的 SQL（單一字串，不重複）
        $allSql = $GLOBALS['test_captured_sql'] ?? '';

        // 斷言含且僅含一個 LEFT JOIN .*fct_order_items
        $joinOrderItemsCount = preg_match_all(
            '/LEFT\s+JOIN\s+\S*fct_order_items/i',
            $allSql
        );
        $this->assertSame(
            1,
            $joinOrderItemsCount,
            "SQL 必須含且僅含一個 LEFT JOIN fct_order_items，實際找到 {$joinOrderItemsCount} 個。SQL: {$allSql}"
        );

        // 斷言含且僅含一個 LEFT JOIN .*fct_product_variations
        $joinVariationsCount = preg_match_all(
            '/LEFT\s+JOIN\s+\S*fct_product_variations/i',
            $allSql
        );
        $this->assertSame(
            1,
            $joinVariationsCount,
            "SQL 必須含且僅含一個 LEFT JOIN fct_product_variations，實際找到 {$joinVariationsCount} 個。SQL: {$allSql}"
        );
    }
}
