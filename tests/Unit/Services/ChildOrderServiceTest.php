<?php
/**
 * ChildOrderService Unit Tests
 *
 * 測試子訂單服務的核心格式化邏輯
 *
 * 注意：Unit 測試不依賴 WordPress 環境，也不依賴 FluentCart Model
 * 因此只測試純 PHP 邏輯（formatItems、getSellerNameFromItems、formatChildOrder）
 *
 * @package BuyGoPlus\Tests\Unit\Services
 * @since Phase 37
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\ChildOrderService;

/**
 * Class ChildOrderServiceTest
 *
 * 測試 ChildOrderService 的格式化功能：
 * - formatItems: 商品項目格式化（分 → 元）
 * - getSellerNameFromItems: 從商品集合取得賣家名稱
 * - formatChildOrder: 子訂單格式化（透過 stdClass 模擬 Order）
 */
class ChildOrderServiceTest extends TestCase
{
    /**
     * @var ChildOrderService
     */
    private $service;

    /**
     * 每個測試前初始化
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 確保 FluentCart stub 存在（讓 namespace use 不報錯）
        if (!class_exists('FluentCart\App\Models\Order')) {
            eval('namespace FluentCart\App\Models; class Order {}');
        }
        if (!class_exists('FluentCart\App\Models\Customer')) {
            eval('namespace FluentCart\App\Models; class Customer {}');
        }

        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-child-order-service.php';
        $this->service = new ChildOrderService();
    }

    // ========================================
    // formatItems 測試
    // ========================================

    /**
     * 測試 formatItems 空陣列回傳空陣列
     */
    public function testFormatItemsEmptyArrayReturnsEmpty(): void
    {
        $result = $this->service->formatItems([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * 測試 formatItems 金額從分轉換為元（陣列格式）
     */
    public function testFormatItemsConvertsAmountFromCentToYuan(): void
    {
        $items = [
            [
                'id'         => 1,
                'post_id'    => 10,
                'title'      => '測試商品',
                'quantity'   => 2,
                'unit_price' => 100000,  // 1000 元（存為分）
                'line_total' => 200000,  // 2000 元（存為分）
            ]
        ];

        $result = $this->service->formatItems($items);

        $this->assertCount(1, $result);
        $this->assertEquals(1000.0, $result[0]['unit_price']);
        $this->assertEquals(2000.0, $result[0]['line_total']);
    }

    /**
     * 測試 formatItems 物件格式也能正確格式化
     */
    public function testFormatItemsWithObjectFormat(): void
    {
        $item = new \stdClass();
        $item->id         = 5;
        $item->post_id    = 20;
        $item->title      = '測試商品物件';
        $item->quantity   = 3;
        $item->unit_price = 50000;  // 500 元
        $item->line_total = 150000; // 1500 元

        $result = $this->service->formatItems([$item]);

        $this->assertCount(1, $result);
        $this->assertEquals(5,      $result[0]['id']);
        $this->assertEquals(20,     $result[0]['product_id']);
        $this->assertEquals('測試商品物件', $result[0]['title']);
        $this->assertEquals(3,      $result[0]['quantity']);
        $this->assertEquals(500.0,  $result[0]['unit_price']);
        $this->assertEquals(1500.0, $result[0]['line_total']);
    }

    /**
     * 測試 formatItems 陣列格式欄位名稱正確映射
     */
    public function testFormatItemsArrayFieldMapping(): void
    {
        $items = [
            [
                'id'         => 99,
                'post_id'    => 55,
                'title'      => '欄位映射測試',
                'quantity'   => 1,
                'unit_price' => 30000,
                'line_total' => 30000,
            ]
        ];

        $result = $this->service->formatItems($items);

        $this->assertEquals(99,      $result[0]['id']);
        $this->assertEquals(55,      $result[0]['product_id']);
        $this->assertEquals('欄位映射測試', $result[0]['title']);
        $this->assertEquals(1,       $result[0]['quantity']);
    }

    /**
     * 測試 formatItems 缺少 title 時使用預設值
     */
    public function testFormatItemsMissingTitleFallback(): void
    {
        $items = [
            [
                'id'         => 1,
                'post_id'    => 1,
                'quantity'   => 1,
                'unit_price' => 0,
                'line_total' => 0,
            ]
        ];

        $result = $this->service->formatItems($items);

        $this->assertEquals('未知商品', $result[0]['title']);
    }

    /**
     * 測試 formatItems 多個 items 全部格式化
     */
    public function testFormatItemsMultipleItems(): void
    {
        $items = [
            ['id' => 1, 'post_id' => 1, 'title' => '商品A', 'quantity' => 1, 'unit_price' => 10000, 'line_total' => 10000],
            ['id' => 2, 'post_id' => 2, 'title' => '商品B', 'quantity' => 2, 'unit_price' => 20000, 'line_total' => 40000],
        ];

        $result = $this->service->formatItems($items);

        $this->assertCount(2, $result);
        $this->assertEquals(100.0, $result[0]['unit_price']);
        $this->assertEquals(400.0, $result[1]['line_total']);
    }

    /**
     * 測試 formatItems 金額為零不報錯
     */
    public function testFormatItemsZeroAmount(): void
    {
        $items = [
            ['id' => 1, 'post_id' => 1, 'title' => '免費商品', 'quantity' => 1, 'unit_price' => 0, 'line_total' => 0]
        ];

        $result = $this->service->formatItems($items);

        $this->assertEquals(0.0, $result[0]['unit_price']);
        $this->assertEquals(0.0, $result[0]['line_total']);
    }

    // ========================================
    // getSellerNameFromItems 測試
    // ========================================

    /**
     * 測試空陣列回傳 '未知賣家'
     */
    public function testGetSellerNameFromItemsEmptyArrayReturnsUnknown(): void
    {
        $result = $this->service->getSellerNameFromItems([]);

        $this->assertEquals('未知賣家', $result);
    }

    /**
     * 測試 null 空集合物件回傳 '未知賣家'
     *
     * 模擬 Eloquent Collection 的 isEmpty() 行為
     */
    public function testGetSellerNameFromItemsEmptyCollectionReturnsUnknown(): void
    {
        // 建立模擬 Eloquent Collection（空集合）
        $emptyCollection = new class {
            public function isEmpty() { return true; }
            public function first()   { return null; }
        };

        $result = $this->service->getSellerNameFromItems($emptyCollection);

        $this->assertEquals('未知賣家', $result);
    }

    /**
     * 測試 post_id = 0 時回傳 '未知賣家'
     *
     * get_post(0) 在 mock 環境下回傳 null
     */
    public function testGetSellerNameFromItemsZeroPostIdReturnsUnknown(): void
    {
        $items = [
            ['post_id' => 0, 'title' => '測試', 'quantity' => 1]
        ];

        $result = $this->service->getSellerNameFromItems($items);

        $this->assertEquals('未知賣家', $result);
    }

    /**
     * 測試 post_id 不存在時（get_post 回傳 null）回傳 '未知賣家'
     *
     * bootstrap-unit.php 的 get_post() mock 永遠回傳 null
     */
    public function testGetSellerNameFromItemsNonExistentPostReturnsUnknown(): void
    {
        $items = [
            ['post_id' => 99999, 'title' => '不存在的商品', 'quantity' => 1]
        ];

        $result = $this->service->getSellerNameFromItems($items);

        // get_post 在 mock 環境下回傳 null → 未知賣家
        $this->assertEquals('未知賣家', $result);
    }

    /**
     * 測試物件格式的 items 空 post_id 回傳 '未知賣家'
     */
    public function testGetSellerNameFromItemsObjectWithNullPostId(): void
    {
        $item = new \stdClass();
        $item->post_id = null;
        $item->title   = '測試';

        $result = $this->service->getSellerNameFromItems([$item]);

        $this->assertEquals('未知賣家', $result);
    }

    // ========================================
    // formatChildOrder 測試（透過 stdClass 模擬）
    // ========================================

    /**
     * 建立模擬 Order 物件（stdClass）
     *
     * @param array $attrs 屬性覆蓋
     * @return object 模擬 Order
     */
    private function mockOrder(array $attrs = []): object
    {
        $defaults = [
            'id'              => 1,
            'invoice_no'      => 'INV-001',
            'payment_status'  => 'paid',
            'shipping_status' => 'unshipped',
            'status'          => 'processing',
            'total_amount'    => 200000,  // 2000 元（分）
            'currency'        => 'TWD',
            'order_items'     => [],
            'created_at'      => '2026-01-01 00:00:00',
        ];

        $data = array_merge($defaults, $attrs);

        $order = new \stdClass();
        foreach ($data as $key => $value) {
            $order->$key = $value;
        }

        return $order;
    }

    /**
     * 測試 formatChildOrder 金額從分轉元
     */
    public function testFormatChildOrderConvertsAmountFromCentToYuan(): void
    {
        // 建立繼承 FluentCart\App\Models\Order 的 stub（讓 type hint 過關）
        if (!class_exists('BuyGoPlus\Tests\Unit\Services\StubOrder')) {
            eval('
                namespace BuyGoPlus\Tests\Unit\Services;
                class StubOrder extends \FluentCart\App\Models\Order {
                    public $id             = 1;
                    public $invoice_no     = "";
                    public $payment_status  = "paid";
                    public $shipping_status = "unshipped";
                    public $status         = "processing";
                    public $total_amount   = 300000;
                    public $currency       = "TWD";
                    public $order_items    = [];
                    public $created_at     = "2026-01-01 00:00:00";
                }
            ');
        }

        $order = new StubOrder();
        $order->total_amount = 300000;  // 3000 元（存為分）

        $result = $this->service->formatChildOrder($order);

        $this->assertEquals(3000.0, $result['total_amount']);
    }

    /**
     * 測試 formatChildOrder 回傳必要欄位
     */
    public function testFormatChildOrderReturnsRequiredFields(): void
    {
        if (!class_exists('BuyGoPlus\Tests\Unit\Services\StubOrder')) {
            eval('
                namespace BuyGoPlus\Tests\Unit\Services;
                class StubOrder extends \FluentCart\App\Models\Order {
                    public $id             = 42;
                    public $invoice_no     = "INV-042";
                    public $payment_status  = "paid";
                    public $shipping_status = "shipped";
                    public $status         = "completed";
                    public $total_amount   = 150000;
                    public $currency       = "TWD";
                    public $order_items    = [];
                    public $created_at     = "2026-02-01 00:00:00";
                }
            ');
        }

        $order = new StubOrder();
        $result = $this->service->formatChildOrder($order);

        $this->assertArrayHasKey('id',                 $result);
        $this->assertArrayHasKey('invoice_no',         $result);
        $this->assertArrayHasKey('payment_status',     $result);
        $this->assertArrayHasKey('shipping_status',    $result);
        $this->assertArrayHasKey('fulfillment_status', $result);
        $this->assertArrayHasKey('total_amount',       $result);
        $this->assertArrayHasKey('currency',           $result);
        $this->assertArrayHasKey('seller_name',        $result);
        $this->assertArrayHasKey('items',              $result);
        $this->assertArrayHasKey('created_at',         $result);
    }

    /**
     * 測試 formatChildOrder 空 order_items 時 items 為空陣列
     */
    public function testFormatChildOrderEmptyItemsReturnsEmptyArray(): void
    {
        if (!class_exists('BuyGoPlus\Tests\Unit\Services\StubOrder')) {
            eval('
                namespace BuyGoPlus\Tests\Unit\Services;
                class StubOrder extends \FluentCart\App\Models\Order {
                    public $id             = 1;
                    public $invoice_no     = "";
                    public $payment_status  = "paid";
                    public $shipping_status = "unshipped";
                    public $status         = "processing";
                    public $total_amount   = 0;
                    public $currency       = "TWD";
                    public $order_items    = [];
                    public $created_at     = "2026-01-01 00:00:00";
                }
            ');
        }

        $order = new StubOrder();
        $order->order_items = [];

        $result = $this->service->formatChildOrder($order);

        $this->assertIsArray($result['items']);
        $this->assertEmpty($result['items']);
    }

    /**
     * 測試 formatChildOrder 空 order_items 時 seller_name 為 '未知賣家'
     */
    public function testFormatChildOrderEmptyItemsSellerNameIsUnknown(): void
    {
        if (!class_exists('BuyGoPlus\Tests\Unit\Services\StubOrder')) {
            eval('
                namespace BuyGoPlus\Tests\Unit\Services;
                class StubOrder extends \FluentCart\App\Models\Order {
                    public $id             = 1;
                    public $invoice_no     = "";
                    public $payment_status  = "paid";
                    public $shipping_status = "unshipped";
                    public $status         = "processing";
                    public $total_amount   = 0;
                    public $currency       = "TWD";
                    public $order_items    = [];
                    public $created_at     = "2026-01-01 00:00:00";
                }
            ');
        }

        $order = new StubOrder();
        $order->order_items = [];

        $result = $this->service->formatChildOrder($order);

        $this->assertEquals('未知賣家', $result['seller_name']);
    }

    // ========================================
    // 邊界情況
    // ========================================

    /**
     * 測試 formatItems 處理極大金額（不溢位）
     */
    public function testFormatItemsLargeAmountNoPrecisionLoss(): void
    {
        $items = [
            ['id' => 1, 'post_id' => 1, 'title' => '高價商品', 'quantity' => 1, 'unit_price' => 999999900, 'line_total' => 999999900]
        ];

        $result = $this->service->formatItems($items);

        $this->assertEquals(9999999.0, $result[0]['unit_price']);
    }

    /**
     * 測試 formatItems 物件格式使用 post_title 作為 title fallback
     */
    public function testFormatItemsObjectUsesPostTitleAsFallback(): void
    {
        $item = new \stdClass();
        $item->id         = 1;
        $item->post_id    = 1;
        $item->post_title = '商品原始標題';  // title 不存在，用 post_title
        $item->quantity   = 1;
        $item->unit_price = 10000;
        $item->line_total = 10000;

        $result = $this->service->formatItems([$item]);

        $this->assertEquals('商品原始標題', $result[0]['title']);
    }
}
