<?php

namespace {
    // WP_Error mock（單元測試環境不載入完整 WordPress）
    if ( ! class_exists( 'WP_Error' ) ) {
        class WP_Error
        {
            private string $code;
            private string $message;
            private $data;

            public function __construct( string $code = '', string $message = '', $data = null )
            {
                $this->code    = $code;
                $this->message = $message;
                $this->data    = $data;
            }

            public function get_error_code(): string
            {
                return $this->code;
            }

            public function get_error_message(): string
            {
                return $this->message;
            }

            public function get_error_data()
            {
                return $this->data;
            }
        }
    }

    // wp_json_encode mock
    if ( ! function_exists( 'wp_json_encode' ) ) {
        function wp_json_encode( $data, $options = 0, $depth = 512 ) {
            return json_encode( $data, $options, $depth );
        }
    }
}

namespace BuyGoPlus\Tests\Unit\Services {

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\AllocationService;

class CancelChildOrderTest extends TestCase
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

    private function makeMockWpdb( array $getRowReturns, int $queryReturn = 0 ): object
    {
        return new class( $getRowReturns, $queryReturn ) {
            public string $prefix = 'wp_';

            /** @var array<int, mixed> */
            private array $getRowReturns;

            private int $queryReturn;

            public int $getRowCallCount = 0;

            /** @var array<int, array{table:string,data:array,where:array}> */
            public array $updateCalls = [];

            public function __construct( array $getRowReturns, int $queryReturn )
            {
                $this->getRowReturns = array_values( $getRowReturns );
                $this->queryReturn   = $queryReturn;
            }

            public function prepare( $query, ...$args )
            {
                // 依需求：直接回傳 SQL string（不做格式化）
                return $query;
            }

            public function get_row( $query, $output = OBJECT )
            {
                $index = $this->getRowCallCount;
                $this->getRowCallCount++;

                return $this->getRowReturns[ $index ] ?? null;
            }

            public function get_results( $query, $output = OBJECT )
            {
                // Return the next getRow value wrapped in an array (for order_items loop)
                $index = $this->getRowCallCount;
                $this->getRowCallCount++;
                $row = $this->getRowReturns[ $index ] ?? null;
                return $row ? [ $row ] : [];
            }

            public function query( $query )
            {
                return $this->queryReturn;
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
        };
    }

    public function test_cancel_success(): void
    {
        $orderRow = (object) [
            'id'              => 1,
            'type'            => 'split',
            'status'          => 'pending',
            'shipping_status' => 'unshipped',
        ];

        $itemRow = (object) [
            'id'        => 10,
            'line_meta' => json_encode( [ '_allocated_qty' => 5 ] ),
        ];

        $wpdb           = $this->makeMockWpdb( [ $orderRow, $itemRow ], 1 );
        $GLOBALS['wpdb'] = $wpdb;

        $service = new AllocationService();
        $result  = $service->cancelChildOrder( 1 );

        $this->assertTrue( $result );

        $this->assertCount( 1, $wpdb->updateCalls );
        $this->assertSame( 'wp_fct_order_items', $wpdb->updateCalls[0]['table'] );

        $lineMeta = $wpdb->updateCalls[0]['data']['line_meta'] ?? '';
        $decoded  = json_decode( $lineMeta, true );

        $this->assertIsArray( $decoded );
        $this->assertArrayHasKey( '_allocated_qty', $decoded );
        $this->assertSame( 0, $decoded['_allocated_qty'] );
    }

    public function test_cancel_shipped_rejected(): void
    {
        $orderRow = (object) [
            'id'              => 1,
            'type'            => 'split',
            'status'          => 'pending',
            'shipping_status' => 'shipped',
        ];

        $wpdb           = $this->makeMockWpdb( [ $orderRow ] );
        $GLOBALS['wpdb'] = $wpdb;

        $service = new AllocationService();
        $result  = $service->cancelChildOrder( 1 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'CANNOT_CANCEL_SHIPPED', $result->get_error_code() );
    }

    public function test_cancel_already_cancelled(): void
    {
        $orderRow = (object) [
            'id'              => 1,
            'type'            => 'split',
            'status'          => 'cancelled',
            'shipping_status' => 'unshipped',
        ];

        $wpdb           = $this->makeMockWpdb( [ $orderRow ] );
        $GLOBALS['wpdb'] = $wpdb;

        $service = new AllocationService();
        $result  = $service->cancelChildOrder( 1 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'ALREADY_CANCELLED', $result->get_error_code() );
    }

    public function test_cancel_not_found(): void
    {
        $wpdb           = $this->makeMockWpdb( [ null ] );
        $GLOBALS['wpdb'] = $wpdb;

        $service = new AllocationService();
        $result  = $service->cancelChildOrder( 999 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'NOT_FOUND', $result->get_error_code() );
    }

    public function test_cancel_status_conflict(): void
    {
        $orderRow = (object) [
            'id'              => 1,
            'type'            => 'split',
            'status'          => 'pending',
            'shipping_status' => 'unshipped',
        ];

        $wpdb           = $this->makeMockWpdb( [ $orderRow ], 0 );
        $GLOBALS['wpdb'] = $wpdb;

        $service = new AllocationService();
        $result  = $service->cancelChildOrder( 1 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'STATUS_CONFLICT', $result->get_error_code() );

        // query 影響 0 行會直接 return，不應該再查詢 item
        $this->assertSame( 1, $wpdb->getRowCallCount );
        $this->assertCount( 0, $wpdb->updateCalls );
    }
}
}
