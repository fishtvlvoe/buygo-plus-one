<?php

namespace BuyGoPlus\Tests\Unit\Services;

use BuyGoPlus\Services\AllocationQueryService;
use PHPUnit\Framework\TestCase;

class AllocationQueryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mock_wpdb_query_log'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mock_wpdb_query_log'], $GLOBALS['wpdb']);
        parent::tearDown();
    }

    private function makeMockWpdb(array $rules): object
    {
        return new class($rules) {
            public $prefix = 'wp_';
            private $rules;
            public $query_log = [];

            public function __construct(array $rules)
            {
                $this->rules = $rules;
            }

            public function prepare($query, ...$args): string
            {
                $flat = [];
                foreach ($args as $arg) {
                    foreach ((array) $arg as $value) {
                        $flat[] = $value;
                    }
                }
                $result = $query;
                foreach ($flat as $value) {
                    $posD = strpos($result, '%d');
                    $posS = strpos($result, '%s');
                    $pattern = $posD !== false && ($posS === false || $posD <= $posS) ? '/%d/' : '/%s/';
                    $replacement = $pattern === '/%d/' ? (string) (int) $value : "'" . addslashes((string) $value) . "'";
                    $result = preg_replace($pattern, $replacement, $result, 1);
                }
                return $result;
            }

            private function match(string $method, string $sql)
            {
                $entry = ['method' => $method, 'sql' => $sql];
                $this->query_log[] = $entry;
                $GLOBALS['mock_wpdb_query_log'][] = $entry;
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
                return $this->match('get_var', $sql);
            }

            public function get_col(string $sql): array
            {
                return $this->match('get_col', $sql) ?? [];
            }

            public function get_results(string $sql, $output = OBJECT): array
            {
                $rows = $this->match('get_results', $sql) ?? [];
                if ($output === ARRAY_A) {
                    return array_map(static fn($row) => is_array($row) ? $row : (array) $row, $rows);
                }
                return array_map(static fn($row) => is_object($row) ? $row : (object) $row, $rows);
            }

            public function insert($table, $data, $format = null): int
            {
                return 1;
            }
        };
    }

    public function test_get_all_variation_ids_returns_post_and_active_variations(): void
    {
        $GLOBALS['wpdb'] = $this->makeMockWpdb([
            ['method' => 'get_var', 'contains' => 'SELECT post_id', 'return' => 2529],
            ['method' => 'get_col', 'contains' => "item_status = 'active'", 'return' => [954, 955, 958]],
        ]);

        $service = new AllocationQueryService();
        $result = $service->getAllVariationIds(958);

        $this->assertSame(2529, $result['post_id']);
        $this->assertSame([954, 955, 958], $result['variation_ids']);
    }

    public function test_get_product_orders_filters_parent_orders_and_calculates_pending(): void
    {
        $GLOBALS['wpdb'] = $this->makeMockWpdb([
            ['method' => 'get_var', 'contains' => 'SELECT post_id', 'return' => 2529],
            ['method' => 'get_col', 'contains' => "item_status = 'active'", 'return' => [954, 955, 958]],
            ['method' => 'get_results', 'contains' => 'FROM wp_fct_order_items oi', 'return' => [[
                'order_id' => 1405,
                'order_item_id' => 7001,
                'object_id' => 955,
                'quantity' => 5,
                'line_meta' => json_encode(['_allocated_qty' => 1]),
                'customer_id' => 88,
                'parent_id' => null,
                'first_name' => 'Test',
                'last_name' => 'Buyer',
                'email' => 'buyer@example.com',
                'variation_title' => 'Blue',
                'shipped_to_shipment' => 1,
                'allocated_to_child' => 2,
            ]]],
        ]);

        $service = new AllocationQueryService();
        $orders = $service->getProductOrders(958);

        $this->assertCount(1, $orders);
        $this->assertSame(1405, $orders[0]['order_id']);
        $this->assertSame(2, $orders[0]['already_allocated']);
        $this->assertSame(3, $orders[0]['pending']);
        $this->assertSame('部分分配', $orders[0]['status']);
        $this->assertTrue($this->queryLogContains('o.parent_id IS NULL'));
    }

    private function queryLogContains(string $fragment): bool
    {
        foreach ($GLOBALS['mock_wpdb_query_log'] as $entry) {
            if (strpos($entry['sql'], $fragment) !== false) {
                return true;
            }
        }
        return false;
    }
}
