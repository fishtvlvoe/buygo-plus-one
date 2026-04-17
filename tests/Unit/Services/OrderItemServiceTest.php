<?php

namespace BuyGoPlus\Tests\Unit\Services {

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\OrderItemService;

class OrderItemServiceTest extends TestCase
{
    private $originalWpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;
    }

    protected function tearDown(): void
    {
        if ( $this->originalWpdb !== null ) {
            $GLOBALS['wpdb'] = $this->originalWpdb;
        } else {
            unset( $GLOBALS['wpdb'] );
        }
        parent::tearDown();
    }

    /**
     * 建立 mock wpdb，支援 get_row() 和 get_var() 依序回傳
     *
     * @param mixed $getRowReturn  get_row() 回傳值
     * @param mixed $getVarReturn  get_var() 回傳值
     */
    private function makeMockWpdb( $getRowReturn, $getVarReturn = null ): object
    {
        return new class( $getRowReturn, $getVarReturn ) {
            public string $prefix = 'wp_';

            /** @var mixed */
            private $getRowReturn;

            /** @var mixed */
            private $getVarReturn;

            /** @var array<int, array{table:string,data:array,where:array}> */
            public array $updateCalls = [];

            /** @var array<int, string> */
            public array $deleteCalls = [];

            public function __construct( $getRowReturn, $getVarReturn )
            {
                $this->getRowReturn = $getRowReturn;
                $this->getVarReturn = $getVarReturn;
            }

            public function prepare( $query, ...$args )
            {
                return $query;
            }

            public function get_row( $query, $output = OBJECT )
            {
                return $this->getRowReturn;
            }

            public function get_var( $query )
            {
                return $this->getVarReturn;
            }

            public function update( $table, $data, $where, $format = null, $where_format = null )
            {
                $this->updateCalls[] = [
                    'table' => $table,
                    'data'  => $data,
                    'where' => $where,
                ];
                return 1;
            }

            public function delete( $table, $where, $where_format = null )
            {
                $this->deleteCalls[] = $table;
                return 1;
            }
        };
    }

    /**
     * Test 1: 成功移除 — order status = 'on-hold'，item 屬於該 order
     */
    public function test_remove_item_success(): void
    {
        $orderRow = (object) [
            'id'     => 1,
            'status' => 'on-hold',
        ];

        // get_var() 回傳 item count = 1，表示 item 屬於此 order
        $wpdb            = $this->makeMockWpdb( $orderRow, 1 );
        $GLOBALS['wpdb'] = $wpdb;

        $service = new OrderItemService();
        $result  = $service->removeItem( 1, 10 );

        $this->assertTrue( $result );
    }

    /**
     * Test 2: completed 訂單拒絕移除，應拋出 Exception 且 message 含 'completed'
     */
    public function test_remove_item_rejects_completed_order(): void
    {
        $orderRow = (object) [
            'id'     => 2,
            'status' => 'completed',
        ];

        $wpdb            = $this->makeMockWpdb( $orderRow, null );
        $GLOBALS['wpdb'] = $wpdb;

        $this->expectException( \Exception::class );
        $this->expectExceptionMessageMatches( '/completed/i' );

        $service = new OrderItemService();
        $service->removeItem( 2, 20 );
    }

    /**
     * Test 3: item 不屬於該 order，應拋出 Exception 且 message 含 'not found'
     */
    public function test_remove_item_rejects_foreign_item(): void
    {
        $orderRow = (object) [
            'id'     => 3,
            'status' => 'on-hold',
        ];

        // get_var() 回傳 0，表示 item 不屬於此 order
        $wpdb            = $this->makeMockWpdb( $orderRow, 0 );
        $GLOBALS['wpdb'] = $wpdb;

        $this->expectException( \Exception::class );
        $this->expectExceptionMessageMatches( '/not found/i' );

        $service = new OrderItemService();
        $service->removeItem( 3, 99 );
    }
}
}
