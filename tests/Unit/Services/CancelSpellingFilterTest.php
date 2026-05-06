<?php

namespace BuyGoPlus\Tests\Unit\Services;

use BuyGoPlus\Services\OrderService;
use PHPUnit\Framework\TestCase;

class CancelSpellingFilterTest extends TestCase
{
    public function test_split_order_query_excludes_cancelled_and_canceled(): void
    {
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public array $queries = [];
            public int $insert_id = 100;
            public string $last_error = '';

            public function prepare($query, ...$args): string
            {
                foreach ($args as $arg) {
                    foreach ((array) $arg as $value) {
                        $query = preg_replace('/%d/', (string) (int) $value, $query, 1);
                    }
                }
                return $query;
            }
            public function query($sql) { $this->queries[] = $sql; return true; }
            public function insert($table, $data, $format = null) { $this->insert_id++; return 1; }
            public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
            public function get_row($sql, $output = OBJECT)
            {
                if (strpos($sql, 'fct_orders WHERE id = 5001') !== false) {
                    return (object) ['id' => 5001, 'customer_id' => 9, 'invoice_no' => 'INV-5001'];
                }
                return null;
            }
            public function get_results($sql, $output = OBJECT): array
            {
                if (strpos($sql, 'fct_order_items WHERE order_id = 5001') !== false) {
                    return [[
                        'id' => 7001, 'order_id' => 5001, 'post_id' => 1001, 'object_id' => 2001, 'quantity' => 3,
                        'unit_price' => 100, 'line_meta' => '{}'
                    ]];
                }
                return [];
            }
            public function get_var($sql)
            {
                $this->queries[] = $sql;
                if (strpos($sql, 'COUNT(*) FROM wp_fct_orders') !== false) {
                    return '0';
                }
                return '0';
            }
        };

        $service = new OrderService();
        $result = $service->splitOrder(5001, ['split_items' => [['order_item_id' => 7001, 'quantity' => 1]]]);

        $this->assertIsArray($result);
        $sql = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString("o.status NOT IN ('cancelled', 'canceled', 'refunded')", $sql);
    }
}
