<?php

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\CustomerQueryService;

/**
 * CustomerQueryService 單元測試（TDD 紅燈）
 *
 * 驗證 CustomerQueryService 完成實作後的行為契約：
 *   - getListCustomers() 回傳分頁資料，且實際執行 DB 查詢
 *   - getCustomerDetail() 回傳客戶陣列（含 id/email/full_name），找不到時回傳 null
 *
 * 目前骨架只回傳預設值，以下測試中有 3 個應為紅燈：
 *   - test_get_list_customers_returns_paginated_result（customers 數量 0 ≠ 3）
 *   - test_get_list_customers_uses_wpdb_queries（query_log 為空）
 *   - test_get_customer_detail_returns_customer_data（回傳 null）
 *
 * 綠燈：
 *   - test_get_customer_detail_returns_null_for_nonexistent（骨架本就回 null）
 */
class CustomerQueryServiceTest extends TestCase
{
    // ─────────────────────────────────────────
    // setUp / tearDown
    // ─────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        // 重置 GLOBALS mock 狀態，避免跨測試汙染
        $GLOBALS['mock_wpdb_query_log'] = [];
        $GLOBALS['mock_wpdb_insert_log'] = [];
        $GLOBALS['mock_wpdb_insert_id_sequence'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        unset($GLOBALS['mock_wpdb_query_log']);
        unset($GLOBALS['mock_wpdb_insert_log']);
        unset($GLOBALS['mock_wpdb_insert_id_sequence']);
        parent::tearDown();
    }

    // ─────────────────────────────────────────
    // 輔助：建立 MockWpdb 實例
    // ─────────────────────────────────────────

    /**
     * 建立輕量 wpdb mock，支援規則比對與 query_log 追蹤。
     *
     * 規則格式：
     *   ['contains' => 'sql片段', 'method' => 'get_results|get_row|get_var', 'return' => <值>]
     *
     * @param array $rules 查詢規則陣列
     * @return object
     */
    private function makeMockWpdb(array $rules = []): object
    {
        return new class($rules) {
            public string $prefix = 'wp_';
            public int $insert_id = 0;
            public string $last_error = '';

            private array $rules;
            public array $query_log = [];

            public function __construct(array $rules)
            {
                $this->rules = $rules;
            }

            public function prepare(string $query, ...$args): string
            {
                // 展開可能傳入的陣列參數
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

            /** 比對 SQL 規則，記錄至 query_log */
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

            public function get_row(string $sql, int $output = OBJECT): mixed
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

            public function get_results(string $sql, int $output = OBJECT): array
            {
                $result = $this->matchRule('get_results', $sql);
                if (empty($result)) {
                    return [];
                }
                if ($output === ARRAY_A) {
                    return array_map(fn($r) => is_array($r) ? $r : (array) $r, $result);
                }
                return array_map(fn($r) => is_object($r) ? $r : (object) $r, $result);
            }
        };
    }

    // ─────────────────────────────────────────
    // Test 1：getListCustomers 回傳分頁結果（紅燈）
    // ─────────────────────────────────────────

    /**
     * 預期：getListCustomers 回傳含有 3 筆客戶資料的分頁陣列
     *
     * 紅燈原因：骨架永遠回傳 ['customers' => [], 'total' => 0]，
     *           assertCount(3, ...) 會失敗。
     */
    public function test_get_list_customers_returns_paginated_result(): void
    {
        // 準備三筆假客戶資料
        $fakeCustomers = [
            ['id' => 1, 'email' => 'alice@example.com', 'full_name' => 'Alice'],
            ['id' => 2, 'email' => 'bob@example.com',   'full_name' => 'Bob'],
            ['id' => 3, 'email' => 'carol@example.com', 'full_name' => 'Carol'],
        ];

        // mock wpdb：get_results 回傳客戶列表；get_var 回傳總筆數
        $GLOBALS['wpdb'] = $this->makeMockWpdb([
            [
                'method'   => 'get_results',
                'contains' => 'SELECT',
                'return'   => $fakeCustomers,
            ],
            [
                'method'   => 'get_var',
                'contains' => 'COUNT',
                'return'   => '3',
            ],
        ]);

        $service = new CustomerQueryService();
        $result = $service->getListCustomers(
            ['page' => 1, 'per_page' => 20, 'search' => ''],
            1,
            true
        );

        // 回傳值必須有 customers 和 total 兩個 key
        $this->assertArrayHasKey('customers', $result, '回傳陣列應包含 customers key');
        $this->assertArrayHasKey('total', $result, '回傳陣列應包含 total key');

        // 紅燈：骨架回傳空陣列，assertCount 3 會失敗
        $this->assertCount(3, $result['customers'], 'customers 應有 3 筆資料');
    }

    // ─────────────────────────────────────────
    // Test 2：getListCustomers 必須執行 DB 查詢（紅燈）
    // ─────────────────────────────────────────

    /**
     * 預期：getListCustomers 執行期間至少觸發一次 SELECT 查詢
     *
     * 紅燈原因：骨架完全不碰 wpdb，query_log 為空。
     */
    public function test_get_list_customers_uses_wpdb_queries(): void
    {
        $GLOBALS['wpdb'] = $this->makeMockWpdb([
            [
                'method'   => 'get_results',
                'contains' => 'SELECT',
                'return'   => [],
            ],
            [
                'method'   => 'get_var',
                'contains' => 'COUNT',
                'return'   => '0',
            ],
        ]);

        $service = new CustomerQueryService();
        $service->getListCustomers(['page' => 1, 'per_page' => 20, 'search' => ''], 1, true);

        $queryLog = $GLOBALS['mock_wpdb_query_log'] ?? [];

        // 紅燈：骨架不做任何 DB 操作，query_log 是空的
        $this->assertNotEmpty($queryLog, 'getListCustomers 應至少執行一次 DB 查詢');

        // 確認有 SELECT 查詢
        $hasSql = array_filter($queryLog, fn($entry) => str_contains(strtoupper($entry['sql'] ?? ''), 'SELECT'));
        $this->assertNotEmpty($hasSql, 'query_log 中應有 SELECT 語句');
    }

    // ─────────────────────────────────────────
    // Test 3：getCustomerDetail 回傳客戶資料（紅燈）
    // ─────────────────────────────────────────

    /**
     * 預期：getCustomerDetail 找到客戶時回傳含 id/email/full_name 的陣列
     *
     * 紅燈原因：骨架永遠回傳 null。
     */
    public function test_get_customer_detail_returns_customer_data(): void
    {
        $fakeCustomer = [
            'id'        => 1,
            'email'     => 'alice@example.com',
            'full_name' => 'Alice Chen',
        ];

        // mock wpdb：get_row 回傳單筆客戶
        $GLOBALS['wpdb'] = $this->makeMockWpdb([
            [
                'method'   => 'get_row',
                'contains' => 'SELECT',
                'return'   => $fakeCustomer,
            ],
        ]);

        $service = new CustomerQueryService();
        $result = $service->getCustomerDetail(1);

        // 紅燈：骨架回傳 null，以下斷言全部失敗
        $this->assertNotNull($result, 'getCustomerDetail 找到客戶時不應回傳 null');
        $this->assertArrayHasKey('id', $result, '回傳資料應有 id key');
        $this->assertArrayHasKey('email', $result, '回傳資料應有 email key');
        $this->assertArrayHasKey('full_name', $result, '回傳資料應有 full_name key');
    }

    // ─────────────────────────────────────────
    // Test 4：getCustomerDetail 找不到時回傳 null（綠燈）
    // ─────────────────────────────────────────

    /**
     * 預期：getCustomerDetail 查無此客戶時回傳 null
     *
     * 綠燈：骨架本就直接回傳 null，此測試應通過。
     */
    public function test_get_customer_detail_returns_null_for_nonexistent(): void
    {
        // mock wpdb：get_row 回傳 null（找不到）
        $GLOBALS['wpdb'] = $this->makeMockWpdb([
            [
                'method'   => 'get_row',
                'contains' => 'SELECT',
                'return'   => null,
            ],
        ]);

        $service = new CustomerQueryService();
        $result = $service->getCustomerDetail(999);

        $this->assertNull($result, '找不到客戶時應回傳 null');
    }
}
