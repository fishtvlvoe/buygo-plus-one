<?php

namespace BuyGoPlus\Tests\Unit\Services;

use BuyGoPlus\Services\OrderService;
use PHPUnit\Framework\TestCase;

class SplitOrderTransactionTest extends TestCase
{
    public function test_split_order_rolls_back_when_item_insert_fails(): void
    {
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public array $queries = [];
            public int $insert_id = 1000;
            public string $last_error = 'mock insert fail';

            public function prepare($query, ...$args): string
            {
                foreach ($args as $arg) {
                    foreach ((array) $arg as $v) {
                        $query = preg_replace('/%d/', (string) (int) $v, $query, 1);
                    }
                }
                return $query;
            }
            public function query($sql) { $this->queries[] = $sql; return true; }
            public function get_row($sql, $output = OBJECT)
            {
                if (strpos($sql, 'fct_orders WHERE id = 5001') !== false) {
                    return (object) ['id' => 5001, 'customer_id' => 1, 'invoice_no' => 'INV-5001'];
                }
                return null;
            }
            public function get_results($sql, $output = OBJECT): array
            {
                if (strpos($sql, 'fct_order_items WHERE order_id = 5001') !== false) {
                    return [[
                        'id' => 7001, 'order_id' => 5001, 'post_id' => 1001, 'object_id' => 2001,
                        'quantity' => 2, 'unit_price' => 100, 'line_meta' => '{}'
                    ]];
                }
                return [];
            }
            public function get_var($sql)
            {
                if (strpos($sql, 'COUNT(*) FROM wp_fct_orders') !== false) { return '0'; }
                return '0';
            }
            public function insert($table, $data, $format = null)
            {
                if (strpos($table, 'fct_orders') !== false) {
                    $this->insert_id = 2001;
                    return 1;
                }
                return false;
            }
            public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
        };

        $service = new OrderService();
        $result = $service->splitOrder(5001, ['split_items' => [['order_item_id' => 7001, 'quantity' => 1]]]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('CREATE_ORDER_ITEMS_FAILED', $result->get_error_code());
        $sql = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString('START TRANSACTION', $sql);
    }

    public function test_split_order_rolls_back_when_any_item_insert_fails_after_partial_success(): void
    {
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public array $queries = [];
            public int $insert_id = 1000;
            public string $last_error = 'mock second item insert fail';
            private int $itemInsertCount = 0;

            public function prepare($query, ...$args): string
            {
                foreach ($args as $arg) {
                    foreach ((array) $arg as $v) {
                        $query = preg_replace('/%d/', (string) (int) $v, $query, 1);
                    }
                }
                return $query;
            }

            public function query($sql) { $this->queries[] = $sql; return true; }

            public function get_row($sql, $output = OBJECT)
            {
                if (strpos($sql, 'fct_orders WHERE id = 5002') !== false) {
                    return (object) ['id' => 5002, 'customer_id' => 1, 'invoice_no' => 'INV-5002'];
                }
                return null;
            }

            public function get_results($sql, $output = OBJECT): array
            {
                if (strpos($sql, 'fct_order_items WHERE order_id = 5002') !== false) {
                    return [
                        [
                            'id' => 7001, 'order_id' => 5002, 'post_id' => 1001, 'object_id' => 2001,
                            'quantity' => 2, 'unit_price' => 100, 'line_meta' => '{}'
                        ],
                        [
                            'id' => 7002, 'order_id' => 5002, 'post_id' => 1002, 'object_id' => 2002,
                            'quantity' => 2, 'unit_price' => 200, 'line_meta' => '{}'
                        ],
                    ];
                }
                return [];
            }

            public function get_var($sql)
            {
                if (strpos($sql, 'COUNT(*) FROM wp_fct_orders') !== false) { return '0'; }
                return '0';
            }

            public function insert($table, $data, $format = null)
            {
                if (strpos($table, 'fct_orders') !== false) {
                    $this->insert_id = 2002;
                    return 1;
                }
                if (strpos($table, 'fct_order_items') !== false) {
                    $this->itemInsertCount++;
                    return $this->itemInsertCount === 1 ? 1 : false;
                }
                return 1;
            }

            public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
        };

        $service = new OrderService();
        $result = $service->splitOrder(5002, [
            'split_items' => [
                ['order_item_id' => 7001, 'quantity' => 1],
                ['order_item_id' => 7002, 'quantity' => 1],
            ],
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('CREATE_ORDER_ITEMS_FAILED', $result->get_error_code());
        $sql = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString('ROLLBACK', $sql);
        $this->assertStringNotContainsString('COMMIT', $sql);
    }
}
