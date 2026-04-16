<?php

namespace {
    // 測試環境用：避免 OrderFormatter 內部呼叫到未定義的 WordPress function
    if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
        function get_post_thumbnail_id( $post_id ) {
            return 0;
        }
    }

    if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
        function wp_get_attachment_image_url( $attachment_id, $size = 'thumbnail' ) {
            return '';
        }
    }

    if ( ! function_exists( 'get_the_title' ) ) {
        function get_the_title( $post_id ) {
            return '';
        }
    }
}

namespace BuyGoPlus\Tests\Unit\Services {

use BuyGoPlus\Services\DebugService;
use BuyGoPlus\Services\OrderFormatter;
use PHPUnit\Framework\TestCase;

class OrderFormatterChildOrderIdTest extends TestCase
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

    private function makeMockWpdb( array $getRowReturns ): object
    {
        return new class( $getRowReturns ) {
            public string $prefix = 'wp_';

            /** @var array<int, mixed> */
            private array $getRowReturns;

            public int $getRowCallCount = 0;

            public function __construct( array $getRowReturns )
            {
                $this->getRowReturns = array_values( $getRowReturns );
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
                return [];
            }

            public function get_var( $query )
            {
                return null;
            }
        };
    }

    private function makeFormatter(): OrderFormatter
    {
        $debug = $this->getMockBuilder( DebugService::class )
            ->disableOriginalConstructor()
            ->onlyMethods( [ 'log' ] )
            ->getMock();

        return new OrderFormatter( $debug );
    }

    public function test_format_item_has_child_order_id_when_child_exists(): void
    {
        $itemRow = (object) [
            'id'        => 1,
            'line_meta' => json_encode( [ '_allocated_qty' => 1 ] ),
        ];

        $childOrderRow = (object) [
            'id' => 99,
        ];

        $GLOBALS['wpdb'] = $this->makeMockWpdb( [ $itemRow, $childOrderRow ] );

        $formatter = $this->makeFormatter();

        $order = [
            'id'            => 1,
            'type'          => 'one-time',
            'customer_name' => 'Tester',
            'customer'       => [
                'email'      => 'tester@test.local',
                'first_name' => 'Test',
                'last_name'  => 'User',
            ],
            'order_items'   => [
                [
                    'id'         => 1,
                    'object_id'  => 777,
                    'title'      => 'Test Product',
                    'quantity'   => 1,
                    'unit_price' => 100,
                    'line_total' => 100,
                ],
            ],
        ];

        $formatted = $formatter->format( $order );

        $this->assertIsArray( $formatted );
        $this->assertArrayHasKey( 'items', $formatted );
        $this->assertIsArray( $formatted['items'] );
        $this->assertSame( 99, $formatted['items'][0]['child_order_id'] );
    }

    public function test_format_item_has_null_child_order_id_when_no_child(): void
    {
        $itemRow = (object) [
            'id'        => 1,
            'line_meta' => json_encode( [ '_allocated_qty' => 1 ] ),
        ];

        $GLOBALS['wpdb'] = $this->makeMockWpdb( [ $itemRow, null ] );

        $formatter = $this->makeFormatter();

        $order = [
            'id'            => 1,
            'type'          => 'one-time',
            'customer_name' => 'Tester',
            'customer'       => [
                'email'      => 'tester@test.local',
                'first_name' => 'Test',
                'last_name'  => 'User',
            ],
            'order_items'   => [
                [
                    'id'         => 1,
                    'object_id'  => 777,
                    'title'      => 'Test Product',
                    'quantity'   => 1,
                    'unit_price' => 100,
                    'line_total' => 100,
                ],
            ],
        ];

        $formatted = $formatter->format( $order );

        $this->assertIsArray( $formatted );
        $this->assertArrayHasKey( 'items', $formatted );
        $this->assertIsArray( $formatted['items'] );
        $this->assertNull( $formatted['items'][0]['child_order_id'] );
    }
}
}
