<?php
/**
 * ShipmentService Unit Tests
 *
 * 測試 v1.3 出貨單服務的核心功能
 *
 * @package BuyGoPlus\Tests\Unit\Services
 * @since 1.3.0
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\ShipmentService;
use ReflectionClass;

/**
 * Class ShipmentServiceTest
 *
 * 測試 ShipmentService 的出貨單管理功能：
 * - generate_shipment_number: 出貨單號生成
 * - create_shipment: 出貨單建立
 * - validate_merge: 合併驗證
 * - mark_shipped: 標記出貨（v1.3 重點：estimated_delivery_at 參數）
 */
class ShipmentServiceTest extends TestCase
{
    /**
     * @var ShipmentService
     */
    private $service;

    /**
     * 每個測試前初始化
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ShipmentService();
    }

    // ========================================
    // generate_shipment_number 測試
    // ========================================

    /**
     * 測試 generate_shipment_number 返回字串
     */
    public function testGenerateShipmentNumberReturnsString(): void
    {
        $result = $this->service->generate_shipment_number();

        $this->assertIsString($result);
    }

    /**
     * 測試 generate_shipment_number 格式正確
     */
    public function testGenerateShipmentNumberFormat(): void
    {
        $result = $this->service->generate_shipment_number();

        // 格式：SH-YYYYMMDD-XXX
        $this->assertMatchesRegularExpression('/^SH-\d{8}-\d{3}$/', $result);
    }

    /**
     * 測試 generate_shipment_number 包含今天日期
     */
    public function testGenerateShipmentNumberContainsToday(): void
    {
        $result = $this->service->generate_shipment_number();
        $today = date('Ymd');

        $this->assertStringContainsString($today, $result);
    }

    /**
     * 測試 generate_shipment_number 前綴正確
     */
    public function testGenerateShipmentNumberPrefix(): void
    {
        $result = $this->service->generate_shipment_number();

        $this->assertStringStartsWith('SH-', $result);
    }

    /**
     * 測試 generate_shipment_number 序號部分為三位數
     */
    public function testGenerateShipmentNumberSequencePadding(): void
    {
        $result = $this->service->generate_shipment_number();

        // 取得序號部分
        $parts = explode('-', $result);
        $sequence = end($parts);

        $this->assertEquals(3, strlen($sequence));
    }

    // ========================================
    // create_shipment 測試
    // ========================================

    /**
     * 測試 create_shipment 空項目返回錯誤
     */
    public function testCreateShipmentWithEmptyItemsReturnsError(): void
    {
        $result = $this->service->create_shipment(1, 1, []);

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('NO_ITEMS', $result->get_error_code());
    }

    /**
     * 測試 create_shipment 錯誤訊息正確
     */
    public function testCreateShipmentEmptyItemsErrorMessage(): void
    {
        $result = $this->service->create_shipment(1, 1, []);

        $this->assertStringContainsString('至少包含一個商品', $result->get_error_message());
    }

    /**
     * 測試 create_shipment 有效項目返回 ID
     */
    public function testCreateShipmentWithValidItemsReturnsId(): void
    {
        $items = [
            [
                'order_id' => 1,
                'order_item_id' => 1,
                'product_id' => 1,
                'quantity' => 2
            ]
        ];

        $result = $this->service->create_shipment(1, 1, $items);

        // 在 mock 環境下，wpdb->insert_id 返回 1
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    /**
     * 測試 create_shipment 多個項目
     */
    public function testCreateShipmentWithMultipleItems(): void
    {
        $items = [
            ['order_id' => 1, 'order_item_id' => 1, 'product_id' => 1, 'quantity' => 2],
            ['order_id' => 1, 'order_item_id' => 2, 'product_id' => 2, 'quantity' => 1],
            ['order_id' => 2, 'order_item_id' => 3, 'product_id' => 3, 'quantity' => 5],
        ];

        $result = $this->service->create_shipment(1, 1, $items);

        $this->assertIsInt($result);
    }

    // ========================================
    // validate_merge 測試
    // ========================================

    /**
     * 測試 validate_merge 空陣列返回錯誤
     */
    public function testValidateMergeWithEmptyArray(): void
    {
        $result = $this->service->validate_merge([]);

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('INVALID_INPUT', $result->get_error_code());
    }

    /**
     * 測試 validate_merge 單一出貨單返回錯誤
     */
    public function testValidateMergeWithSingleShipment(): void
    {
        $result = $this->service->validate_merge([1]);

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('INVALID_INPUT', $result->get_error_code());
    }

    /**
     * 測試 validate_merge 錯誤訊息包含「至少兩個」
     */
    public function testValidateMergeErrorMessage(): void
    {
        $result = $this->service->validate_merge([1]);

        $this->assertStringContainsString('至少需要兩個', $result->get_error_message());
    }

    /**
     * 測試 validate_merge 找不到出貨單返回錯誤
     */
    public function testValidateMergeWithNonExistentShipments(): void
    {
        // 在 mock 環境下，wpdb->get_results 返回空陣列
        $result = $this->service->validate_merge([999, 1000]);

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('SHIPMENT_NOT_FOUND', $result->get_error_code());
    }

    // ========================================
    // mark_shipped 測試（v1.3 重點）
    // ========================================

    /**
     * 測試 mark_shipped 空陣列返回錯誤
     */
    public function testMarkShippedWithEmptyArray(): void
    {
        $result = $this->service->mark_shipped([]);

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('INVALID_INPUT', $result->get_error_code());
    }

    /**
     * 測試 mark_shipped 錯誤訊息正確
     */
    public function testMarkShippedEmptyArrayErrorMessage(): void
    {
        $result = $this->service->mark_shipped([]);

        $this->assertStringContainsString('請選擇要標記的出貨單', $result->get_error_message());
    }

    /**
     * 測試 mark_shipped 接受 null estimated_delivery_at
     */
    public function testMarkShippedWithNullEstimatedDelivery(): void
    {
        // 在 mock 環境下，出貨單不存在會跳過
        $result = $this->service->mark_shipped([1], null);

        // 由於 mock 環境下找不到出貨單，返回錯誤
        $this->assertTrue(is_wp_error($result));
    }

    /**
     * 測試 mark_shipped 接受 estimated_delivery_at 參數
     */
    public function testMarkShippedWithEstimatedDelivery(): void
    {
        $estimatedDelivery = '2026-02-10 00:00:00';

        // 在 mock 環境下，出貨單不存在會跳過
        $result = $this->service->mark_shipped([1], $estimatedDelivery);

        // 驗證方法接受參數不拋出異常
        $this->assertTrue(is_wp_error($result) || is_int($result));
    }

    /**
     * 測試 mark_shipped 方法簽名正確
     */
    public function testMarkShippedMethodSignature(): void
    {
        $reflection = new ReflectionClass(ShipmentService::class);
        $method = $reflection->getMethod('mark_shipped');
        $parameters = $method->getParameters();

        // 應該有兩個參數
        $this->assertCount(2, $parameters);

        // 第一個參數：shipment_ids
        $this->assertEquals('shipment_ids', $parameters[0]->getName());

        // 第二個參數：estimated_delivery_at（有預設值 null）
        $this->assertEquals('estimated_delivery_at', $parameters[1]->getName());
        $this->assertTrue($parameters[1]->isOptional());
        $this->assertNull($parameters[1]->getDefaultValue());
    }

    /**
     * 測試 mark_shipped 不存在的出貨單
     */
    public function testMarkShippedWithNonExistentShipment(): void
    {
        $result = $this->service->mark_shipped([999999]);

        // 應該返回錯誤
        $this->assertTrue(is_wp_error($result));
    }

    /**
     * 測試 mark_shipped 多個出貨單
     */
    public function testMarkShippedWithMultipleShipments(): void
    {
        $result = $this->service->mark_shipped([1, 2, 3]);

        // 在 mock 環境下，全部找不到會返回錯誤
        $this->assertTrue(is_wp_error($result));
    }

    /**
     * 測試 mark_shipped 日期格式驗證
     */
    public function testMarkShippedDateFormat(): void
    {
        // 各種日期格式都應該被接受（不在此處驗證，由 API 層處理）
        $dates = [
            '2026-02-10 00:00:00',  // MySQL datetime
            '2026-02-10',            // 只有日期
            '2026/02/10 00:00:00',  // 斜線格式
        ];

        foreach ($dates as $date) {
            $result = $this->service->mark_shipped([1], $date);
            // 不應該因為日期格式而拋出異常
            $this->assertTrue(is_wp_error($result) || is_int($result));
        }
    }

    // ========================================
    // get_shipment 測試
    // ========================================

    /**
     * 測試 get_shipment 返回 null（mock 環境）
     */
    public function testGetShipmentReturnsNullInMock(): void
    {
        $result = $this->service->get_shipment(1);

        // 在 mock 環境下，wpdb->get_row 返回 null
        $this->assertNull($result);
    }

    /**
     * 測試 get_shipment 接受整數 ID
     */
    public function testGetShipmentAcceptsIntegerId(): void
    {
        $result = $this->service->get_shipment(123);

        // 不應該拋出異常
        $this->assertNull($result);
    }

    // ========================================
    // get_shipment_items 測試
    // ========================================

    /**
     * 測試 get_shipment_items 返回空陣列（mock 環境）
     */
    public function testGetShipmentItemsReturnsEmptyArray(): void
    {
        $result = $this->service->get_shipment_items(1);

        // 在 mock 環境下，wpdb->get_results 返回空陣列
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ========================================
    // merge_shipments 測試
    // ========================================

    /**
     * 測試 merge_shipments 驗證失敗返回錯誤
     */
    public function testMergeShipmentsValidationFails(): void
    {
        $result = $this->service->merge_shipments([1]);

        $this->assertTrue(is_wp_error($result));
    }

    /**
     * 測試 merge_shipments 找不到出貨單返回錯誤
     */
    public function testMergeShipmentsNotFound(): void
    {
        $result = $this->service->merge_shipments([1, 2]);

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('SHIPMENT_NOT_FOUND', $result->get_error_code());
    }

    // ========================================
    // 邊界情況測試
    // ========================================

    /**
     * 測試處理極大 ID
     */
    public function testHandleLargeIds(): void
    {
        $largeId = PHP_INT_MAX;

        // get_shipment
        $this->assertNull($this->service->get_shipment($largeId));

        // get_shipment_items
        $this->assertIsArray($this->service->get_shipment_items($largeId));
    }

    /**
     * 測試處理零 ID
     */
    public function testHandleZeroId(): void
    {
        $this->assertNull($this->service->get_shipment(0));
        $this->assertIsArray($this->service->get_shipment_items(0));
    }

    /**
     * 測試 estimated_delivery_at 為空字串
     */
    public function testMarkShippedWithEmptyStringEstimatedDelivery(): void
    {
        $result = $this->service->mark_shipped([1], '');

        // 空字串應該被視為有效輸入（由 API 層處理）
        $this->assertTrue(is_wp_error($result) || is_int($result));
    }

    /**
     * 測試 estimated_delivery_at 為未來日期
     */
    public function testMarkShippedWithFutureDate(): void
    {
        $futureDate = date('Y-m-d H:i:s', strtotime('+30 days'));

        $result = $this->service->mark_shipped([1], $futureDate);

        // 不應該因為日期而拋出異常
        $this->assertTrue(is_wp_error($result) || is_int($result));
    }

    /**
     * 測試 estimated_delivery_at 為過去日期
     */
    public function testMarkShippedWithPastDate(): void
    {
        $pastDate = date('Y-m-d H:i:s', strtotime('-30 days'));

        $result = $this->service->mark_shipped([1], $pastDate);

        // 過去日期也應該被接受（由 API 層處理驗證）
        $this->assertTrue(is_wp_error($result) || is_int($result));
    }

    // ========================================
    // 整合測試
    // ========================================

    /**
     * 測試完整出貨流程（mock 環境）
     */
    public function testCompleteShipmentFlow(): void
    {
        // 1. 建立出貨單
        $items = [
            ['order_id' => 1, 'order_item_id' => 1, 'product_id' => 1, 'quantity' => 2],
        ];

        $shipmentId = $this->service->create_shipment(1, 1, $items);
        $this->assertIsInt($shipmentId);

        // 2. 生成出貨單號
        $shipmentNumber = $this->service->generate_shipment_number();
        $this->assertMatchesRegularExpression('/^SH-\d{8}-\d{3}$/', $shipmentNumber);

        // 3. 嘗試標記出貨（mock 環境下會因為找不到資料而返回錯誤）
        $result = $this->service->mark_shipped([$shipmentId], '2026-02-10 00:00:00');

        // 驗證完成
        $this->assertTrue(is_wp_error($result) || is_int($result));
    }

    /**
     * 測試 DebugService 整合（不拋出異常）
     */
    public function testDebugServiceIntegration(): void
    {
        // 各種操作都會調用 DebugService
        $this->service->create_shipment(1, 1, [['order_id' => 1, 'order_item_id' => 1, 'product_id' => 1, 'quantity' => 1]]);
        $this->service->mark_shipped([1]);
        $this->service->get_shipment(1);

        $this->assertTrue(true);
    }
}
