<?php
/**
 * SellerGrantService 單元測試（紅燈測試）
 *
 * 目的：在 SellerGrantService 實作之前先定義成功條件。
 * 所有測試在骨架階段預期失敗（紅燈），遷移商業邏輯後才轉綠。
 *
 * 測試對象：
 *   - is_order_processed()   — 去重查詢
 *   - get_seller_product_id() — 從 options 讀取設定
 *   - process_order()         — 完整流程（含 DB 寫入）
 *
 * Mock 策略：
 *   - 透過 $GLOBALS['wpdb'] 替換全域 wpdb（與 AllocationServiceTest 相同模式）
 *   - 使用規則陣列（contains + method）控制查詢回傳值
 *   - wpdb::insert 呼叫記錄於 insert_log 供 assert 驗證
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\SellerGrantService;

class SellerGrantServiceTest extends TestCase
{
    // ────────────────────────────────────────
    // 測試常數
    // ────────────────────────────────────────

    /** 測試用訂單 ID */
    const ORDER_ID = 123;

    /** 測試用用戶 ID */
    const USER_ID = 456;

    /** 測試用賣家商品 ID */
    const SELLER_PRODUCT_ID = 789;

    // ────────────────────────────────────────
    // setUp / tearDown
    // ────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        // 重置 GLOBALS mock 狀態，避免跨 test 汙染
        $GLOBALS['mock_wpdb_query_log']  = [];
        $GLOBALS['mock_wpdb_insert_log'] = [];
        $GLOBALS['mock_get_option_map']  = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mock_wpdb_query_log']);
        unset($GLOBALS['mock_wpdb_insert_log']);
        unset($GLOBALS['mock_get_option_map']);
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    // ────────────────────────────────────────
    // 輔助：建立 QueryAwareMockWpdb
    // ────────────────────────────────────────

    /**
     * 建立可程式化的 wpdb mock。
     *
     * 規則格式（同 AllocationServiceTest）：
     *   ['method' => 'get_var', 'contains' => '片段', 'return' => 回傳值]
     *
     * @param array $rules 查詢規則陣列
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
            public array  $insert_log = [];

            public function __construct(array $rules)
            {
                $this->rules = $rules;
            }

            /** 模擬 wpdb->prepare：把 %d/%s 替換為實際值 */
            public function prepare(string $query, ...$args): string
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
                    $pos_s = strpos($result, '%s');
                    if ($pos_d !== false && ($pos_s === false || $pos_d <= $pos_s)) {
                        $result = preg_replace('/%d/', (int) $arg, $result, 1);
                    } else {
                        $result = preg_replace('/%s/', "'" . addslashes((string) $arg) . "'", $result, 1);
                    }
                }
                return $result;
            }

            /** 依規則比對 SQL，回傳匹配的 return 值；順帶記錄查詢日誌 */
            private function matchRule(string $method, string $sql): mixed
            {
                $this->query_log[] = ['method' => $method, 'sql' => $sql];
                $GLOBALS['mock_wpdb_query_log'][] = ['method' => $method, 'sql' => $sql];

                foreach ($this->rules as $rule) {
                    if ($rule['method'] !== $method) {
                        continue;
                    }
                    if (isset($rule['contains']) && str_contains($sql, $rule['contains'])) {
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
                return is_object($result) ? $result : (object) $result;
            }

            public function get_results(string $sql, $output = OBJECT): array
            {
                $result = $this->matchRule('get_results', $sql);
                return is_array($result) ? array_map(
                    fn($r) => is_object($r) ? $r : (object) $r,
                    $result
                ) : [];
            }

            public function insert(string $table, array $data, $format = null): int
            {
                $this->insert_log[]                    = ['table' => $table, 'data' => $data];
                $GLOBALS['mock_wpdb_insert_log'][]     = ['table' => $table, 'data' => $data];
                $this->insert_id                       = 999;
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
        };
    }

    // ════════════════════════════════════════
    // Test 1 — is_order_processed 對新訂單應回傳 false
    // ════════════════════════════════════════

    /**
     * @test
     *
     * 情境：wp_buygo_seller_grants 無此 order_id 的記錄
     * 預期：is_order_processed(123) 回傳 false
     *
     * 紅燈原因：骨架直接 return false（恰好通過），
     * 但我們同時驗證「有發出 SELECT 查詢」——骨架沒做查詢，
     * 因此 assert query_log 為空，以此確認骨架沒有真實實作。
     *
     * 注意：此測試分兩段 assert：
     *   (A) 回傳值必須是 false ← 骨架可以通過
     *   (B) 必須有對 buygo_seller_grants 發出查詢 ← 骨架會在這裡失敗（紅燈）
     */
    public function test_is_order_processed_returns_false_for_new_order(): void
    {
        // Arrange：wpdb 查無記錄（COUNT = null → 骨架不該查詢）
        $mockWpdb = $this->makeMockWpdb([
            [
                'method'   => 'get_var',
                'contains' => 'buygo_seller_grants',
                'return'   => null, // 查無記錄
            ],
        ]);
        $GLOBALS['wpdb'] = $mockWpdb;

        $service = new SellerGrantService();

        // Act
        $result = $service->is_order_processed(self::ORDER_ID);

        // Assert A：回傳 false（新訂單尚未處理）
        $this->assertFalse(
            $result,
            'is_order_processed 對新訂單應回傳 false（查無記錄）'
        );

        // Assert B（紅燈核心）：應該有對 buygo_seller_grants 發出 get_var 查詢
        $grantQueries = array_filter(
            $mockWpdb->query_log,
            fn($entry) => $entry['method'] === 'get_var'
                && str_contains($entry['sql'], 'buygo_seller_grants')
        );

        $this->assertNotEmpty(
            $grantQueries,
            '骨架沒有對 buygo_seller_grants 發出查詢（紅燈：未實作 DB 查詢）'
        );
    }

    // ════════════════════════════════════════
    // Test 2 — process_order 應授予角色並寫入 grants 記錄
    // ════════════════════════════════════════

    /**
     * @test
     *
     * 情境：
     *   - 訂單 #123 尚未處理（grants 表查無記錄）
     *   - 訂單包含設定的賣家商品（order_items 有對應 post_id）
     *   - 顧客已綁定 WordPress 用戶
     *
     * 預期：
     *   - process_order(123) 回傳 true
     *   - wpdb insert_log 有寫入 buygo_seller_grants
     *
     * 紅燈原因：骨架 return false，且無任何 DB 寫入
     */
    public function test_process_order_grants_seller_role(): void
    {
        // Arrange：模擬完整的「應授予」場景
        $mockCustomer = (object)[
            'id'      => 10,
            'user_id' => self::USER_ID,
        ];

        $mockWpdb = $this->makeMockWpdb([
            // is_order_processed：查無記錄 → 尚未處理
            [
                'method'   => 'get_var',
                'contains' => 'buygo_seller_grants',
                'return'   => null,
            ],
            // order_contains_product：fct_order_items 有對應項目
            [
                'method'   => 'get_results',
                'contains' => 'fct_order_items',
                'return'   => [
                    (object)['post_id' => self::SELLER_PRODUCT_ID],
                ],
            ],
            // grant_seller_role：查顧客資料
            [
                'method'   => 'get_row',
                'contains' => 'fct_customers',
                'return'   => $mockCustomer,
            ],
        ]);
        $GLOBALS['wpdb'] = $mockWpdb;

        // mock get_option 回傳賣家商品 ID
        $GLOBALS['mock_get_option_map']['buygo_seller_product_id'] = (string) self::SELLER_PRODUCT_ID;

        $service = new SellerGrantService();

        // Act
        $result = $service->process_order(self::ORDER_ID);

        // Assert A（紅燈）：應回傳 true 表示流程執行成功
        $this->assertTrue(
            $result,
            '骨架 return false，process_order 應在授予後回傳 true（紅燈）'
        );

        // Assert B（紅燈）：應有寫入 buygo_seller_grants 的 INSERT
        $grantInserts = array_filter(
            $mockWpdb->insert_log,
            fn($entry) => str_contains($entry['table'], 'buygo_seller_grants')
        );

        $this->assertNotEmpty(
            $grantInserts,
            '骨架沒有寫入 buygo_seller_grants（紅燈：未實作 DB INSERT）'
        );
    }

    // ════════════════════════════════════════
    // Test 3 — get_seller_product_id 應回傳 options 中設定的 ID
    // ════════════════════════════════════════

    /**
     * @test
     *
     * 情境：WordPress options 表中 buygo_seller_product_id = 789
     * 預期：get_seller_product_id() 回傳 int 789
     *
     * 紅燈原因：骨架直接 return null
     *
     * 說明：
     *   get_option() 是全域函數，測試環境透過 tests/bootstrap.php 中的
     *   mock 版本，從 $GLOBALS['mock_get_option_map'] 讀取。
     *   若 bootstrap 無此 mock，測試會失敗並提示需補充 bootstrap mock。
     */
    public function test_get_seller_product_id_returns_configured_id(): void
    {
        // Arrange：設定 mock options
        $GLOBALS['mock_get_option_map']['buygo_seller_product_id'] = (string) self::SELLER_PRODUCT_ID;

        $service = new SellerGrantService();

        // Act
        $result = $service->get_seller_product_id();

        // Assert（紅燈）：應回傳設定的商品 ID（int 789）
        $this->assertSame(
            self::SELLER_PRODUCT_ID,
            $result,
            '骨架 return null，get_seller_product_id 應回傳 options 設定的 ID（紅燈）'
        );
    }

    // ════════════════════════════════════════
    // Test 4 — is_order_processed 對已處理訂單應回傳 true
    // ════════════════════════════════════════

    /**
     * @test
     *
     * 情境：wp_buygo_seller_grants 已有此 order_id 的記錄（COUNT = 1）
     * 預期：is_order_processed(123) 回傳 true
     *
     * 紅燈原因：骨架 return false，無論 DB 回傳什麼都是 false
     */
    public function test_is_order_processed_returns_true_for_existing_order(): void
    {
        // Arrange：wpdb 查到 1 筆記錄
        $mockWpdb = $this->makeMockWpdb([
            [
                'method'   => 'get_var',
                'contains' => 'buygo_seller_grants',
                'return'   => '1', // 已有記錄
            ],
        ]);
        $GLOBALS['wpdb'] = $mockWpdb;

        $service = new SellerGrantService();

        // Act
        $result = $service->is_order_processed(self::ORDER_ID);

        // Assert（紅燈）：已處理的訂單應回傳 true
        $this->assertTrue(
            $result,
            '骨架 return false，is_order_processed 對已處理訂單應回傳 true（紅燈）'
        );
    }
}
