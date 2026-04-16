<?php

namespace {
    // 確保不依賴 WordPress runtime：提供必要常數與最小 mock
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    if (!defined('BUYGO_PLUS_ONE_PLUGIN_DIR')) {
        define('BUYGO_PLUS_ONE_PLUGIN_DIR', dirname(__DIR__, 3) . '/');
    }

    // WP_Error mock
    if (!class_exists('WP_Error')) {
        class WP_Error
        {
            public $code;
            public $message;

            public function __construct($code = '', $message = '')
            {
                $this->code    = $code;
                $this->message = $message;
            }

            public function get_error_code()
            {
                return $this->code;
            }

            public function get_error_message()
            {
                return $this->message;
            }
        }
    }

    // WP_REST_Response mock
    if (!class_exists('WP_REST_Response')) {
        class WP_REST_Response
        {
            public $data;
            public $status;

            public function __construct($data = [], $status = 200)
            {
                $this->data   = $data;
                $this->status = $status;
            }
        }
    }

    // WP_REST_Request mock
    if (!class_exists('WP_REST_Request')) {
        class WP_REST_Request
        {
            private $params = [];

            public function set_param($k, $v)
            {
                $this->params[$k] = $v;
            }

            public function get_param($k)
            {
                return $this->params[$k] ?? null;
            }
        }
    }

    // is_wp_error mock
    if (!function_exists('is_wp_error')) {
        function is_wp_error($v)
        {
            return $v instanceof WP_Error;
        }
    }

    // absint mock
    if (!function_exists('absint')) {
        function absint($v)
        {
            return abs((int) $v);
        }
    }

    // 直接載入待測 API 類別（避免依賴 Composer autoload 與 WP runtime）
    require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-child-orders-api.php';
}

namespace BuyGoPlus\Services {
    // 若 service 類別在 unit test 環境無法載入，提供最小 stub 讓 PHPUnit 能建立 mock
    if (!class_exists('BuyGoPlus\\Services\\AllocationService')) {
        class AllocationService
        {
            public function cancelChildOrder($childOrderId)
            {
                return true;
            }
        }
    }
}

namespace BuyGoPlus\Tests\Unit\Api {

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Api\ChildOrders_API;

class TestableChildOrdersApi extends ChildOrders_API
{
    public function __construct($allocationService)
    {
        // 跳過 parent::__construct()，避免 require_once 與 new Service 造成測試環境錯誤
        $prop = new \ReflectionProperty(ChildOrders_API::class, 'allocationService');
        if (PHP_VERSION_ID < 80100) {
            $prop->setAccessible(true);
        }
        $prop->setValue($this, $allocationService);
    }
}

final class CancelChildOrderApiTest extends TestCase
{
    private $allocationService;
    private TestableChildOrdersApi $api;

    protected function setUp(): void
    {
        parent::setUp();

        $this->allocationService = $this->getMockBuilder(\BuyGoPlus\Services\AllocationService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['cancelChildOrder'])
            ->getMock();

        $this->api = new TestableChildOrdersApi($this->allocationService);
    }

    private function makeRequest(int $childOrderId): \WP_REST_Request
    {
        $request = new \WP_REST_Request();
        $request->set_param('child_order_id', $childOrderId);
        return $request;
    }

    public function test_cancel_success(): void
    {
        $this->allocationService
            ->expects($this->once())
            ->method('cancelChildOrder')
            ->with(1)
            ->willReturn(true);

        $response = $this->api->cancel_child_order($this->makeRequest(1));

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(200, $response->status);
        $this->assertIsArray($response->data);
        $this->assertTrue($response->data['success']);
    }

    public function test_cancel_not_found(): void
    {
        $this->allocationService
            ->expects($this->once())
            ->method('cancelChildOrder')
            ->with(1)
            ->willReturn(new \WP_Error('NOT_FOUND', 'not found'));

        $response = $this->api->cancel_child_order($this->makeRequest(1));

        $this->assertSame(404, $response->status);
        $this->assertFalse($response->data['success']);
        $this->assertSame('NOT_FOUND', $response->data['code']);
    }

    public function test_cancel_cannot_cancel_shipped(): void
    {
        $this->allocationService
            ->expects($this->once())
            ->method('cancelChildOrder')
            ->with(1)
            ->willReturn(new \WP_Error('CANNOT_CANCEL_SHIPPED', 'cannot cancel shipped'));

        $response = $this->api->cancel_child_order($this->makeRequest(1));

        $this->assertSame(422, $response->status);
        $this->assertFalse($response->data['success']);
        $this->assertSame('CANNOT_CANCEL_SHIPPED', $response->data['code']);
    }

    public function test_cancel_status_conflict(): void
    {
        $this->allocationService
            ->expects($this->once())
            ->method('cancelChildOrder')
            ->with(1)
            ->willReturn(new \WP_Error('STATUS_CONFLICT', 'status conflict'));

        $response = $this->api->cancel_child_order($this->makeRequest(1));

        $this->assertSame(409, $response->status);
        $this->assertFalse($response->data['success']);
        $this->assertSame('STATUS_CONFLICT', $response->data['code']);
    }
}
}
