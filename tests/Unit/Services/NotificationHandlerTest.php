<?php
/**
 * NotificationHandler Unit Tests
 *
 * 測試 v1.3 出貨通知處理器的核心功能
 *
 * @package BuyGoPlus\Tests\Unit\Services
 * @since 1.3.0
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\NotificationHandler;
use ReflectionClass;
use ReflectionMethod;

/**
 * Class NotificationHandlerTest
 *
 * 測試 NotificationHandler 的通知處理功能：
 * - 單例模式
 * - Idempotency 機制（防重複發送）
 * - 事件處理
 */
class NotificationHandlerTest extends TestCase
{
    /**
     * @var NotificationHandler
     */
    private $handler;

    /**
     * 每個測試前重置 mock transients
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 重置 mock transients
        $GLOBALS['mock_transients'] = [];

        // 重置單例實例（使用反射）
        $this->resetSingleton();

        // 取得新實例
        $this->handler = NotificationHandler::get_instance();
    }

    /**
     * 重置 NotificationHandler 單例實例
     */
    private function resetSingleton(): void
    {
        $reflection = new ReflectionClass(NotificationHandler::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, null);
    }

    /**
     * 取得 private 方法的反射
     *
     * @param string $methodName
     * @return ReflectionMethod
     */
    private function getPrivateMethod(string $methodName): ReflectionMethod
    {
        $reflection = new ReflectionClass(NotificationHandler::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }

    // ========================================
    // 單例模式測試
    // ========================================

    /**
     * 測試 get_instance 返回 NotificationHandler 實例
     */
    public function testGetInstanceReturnsNotificationHandler(): void
    {
        $instance = NotificationHandler::get_instance();

        $this->assertInstanceOf(NotificationHandler::class, $instance);
    }

    /**
     * 測試 get_instance 返回相同實例（單例）
     */
    public function testGetInstanceReturnsSameInstance(): void
    {
        $instance1 = NotificationHandler::get_instance();
        $instance2 = NotificationHandler::get_instance();

        $this->assertSame($instance1, $instance2);
    }

    // ========================================
    // register_hooks 測試
    // ========================================

    /**
     * 測試 register_hooks 不拋出異常
     */
    public function testRegisterHooksDoesNotThrow(): void
    {
        // register_hooks 應該能正常執行不拋出異常
        $this->handler->register_hooks();

        $this->assertTrue(true);
    }

    // ========================================
    // Idempotency 機制測試
    // ========================================

    /**
     * 測試 is_notification_already_sent 初始狀態返回 false
     */
    public function testIsNotificationAlreadySentReturnsFalseInitially(): void
    {
        $method = $this->getPrivateMethod('is_notification_already_sent');

        $result = $method->invoke($this->handler, 123);

        $this->assertFalse($result);
    }

    /**
     * 測試 mark_notification_sent 設置 transient
     */
    public function testMarkNotificationSentSetsTransient(): void
    {
        $markMethod = $this->getPrivateMethod('mark_notification_sent');
        $checkMethod = $this->getPrivateMethod('is_notification_already_sent');

        // 標記通知已發送
        $markMethod->invoke($this->handler, 456);

        // 檢查是否已發送
        $result = $checkMethod->invoke($this->handler, 456);

        $this->assertTrue($result);
    }

    /**
     * 測試不同出貨單 ID 獨立追蹤
     */
    public function testDifferentShipmentsTrackedIndependently(): void
    {
        $markMethod = $this->getPrivateMethod('mark_notification_sent');
        $checkMethod = $this->getPrivateMethod('is_notification_already_sent');

        // 標記出貨單 100 已發送
        $markMethod->invoke($this->handler, 100);

        // 出貨單 100 應該返回 true
        $this->assertTrue($checkMethod->invoke($this->handler, 100));

        // 出貨單 200 應該返回 false（未發送）
        $this->assertFalse($checkMethod->invoke($this->handler, 200));
    }

    /**
     * 測試 transient key 格式正確
     */
    public function testTransientKeyFormat(): void
    {
        $markMethod = $this->getPrivateMethod('mark_notification_sent');
        $markMethod->invoke($this->handler, 789);

        // 驗證 transient key 格式
        $expectedKey = 'buygo_shipment_notified_789';
        $this->assertArrayHasKey($expectedKey, $GLOBALS['mock_transients']);
    }

    /**
     * 測試 transient 值為時間戳
     */
    public function testTransientValueIsTimestamp(): void
    {
        $markMethod = $this->getPrivateMethod('mark_notification_sent');
        $markMethod->invoke($this->handler, 999);

        $transientKey = 'buygo_shipment_notified_999';
        $value = $GLOBALS['mock_transients'][$transientKey];

        // 值應該是時間戳（整數且合理範圍）
        $this->assertIsInt($value);
        $this->assertGreaterThan(time() - 60, $value);
        $this->assertLessThanOrEqual(time(), $value);
    }

    // ========================================
    // handle_shipment_marked_shipped 測試
    // ========================================

    /**
     * 測試 handle_shipment_marked_shipped 不拋出異常
     */
    public function testHandleShipmentMarkedShippedDoesNotThrow(): void
    {
        // 在 mock 環境下，資料庫查詢返回 null，應該優雅處理
        $this->handler->handle_shipment_marked_shipped(999);

        $this->assertTrue(true);
    }

    /**
     * 測試 handle_shipment_marked_shipped 處理無效 ID
     */
    public function testHandleShipmentMarkedShippedWithInvalidId(): void
    {
        // 負數 ID
        $this->handler->handle_shipment_marked_shipped(-1);
        $this->assertTrue(true);

        // 零 ID
        $this->handler->handle_shipment_marked_shipped(0);
        $this->assertTrue(true);
    }

    // ========================================
    // collect_shipment_data 測試
    // ========================================

    /**
     * 測試 collect_shipment_data 返回 null（mock 環境下無資料）
     */
    public function testCollectShipmentDataReturnsNullWhenNoData(): void
    {
        $method = $this->getPrivateMethod('collect_shipment_data');

        $result = $method->invoke($this->handler, 123);

        // 在 mock 環境下，資料庫查詢返回 null
        $this->assertNull($result);
    }

    // ========================================
    // send_shipment_notification 測試
    // ========================================

    /**
     * 測試 send_shipment_notification Idempotency 檢查
     */
    public function testSendShipmentNotificationIdempotency(): void
    {
        $markMethod = $this->getPrivateMethod('mark_notification_sent');
        $sendMethod = $this->getPrivateMethod('send_shipment_notification');

        // 先標記為已發送
        $markMethod->invoke($this->handler, 555);

        // 再次發送應該被跳過（不拋出異常）
        $sendMethod->invoke($this->handler, 555);

        $this->assertTrue(true);
    }

    /**
     * 測試 send_shipment_notification 不拋出異常
     */
    public function testSendShipmentNotificationDoesNotThrow(): void
    {
        $method = $this->getPrivateMethod('send_shipment_notification');

        // 在 mock 環境下應該優雅處理
        $method->invoke($this->handler, 123);

        $this->assertTrue(true);
    }

    // ========================================
    // 邊界情況測試
    // ========================================

    /**
     * 測試處理極大 shipment_id
     */
    public function testHandleLargeShipmentId(): void
    {
        $largeId = PHP_INT_MAX;

        $this->handler->handle_shipment_marked_shipped($largeId);

        $this->assertTrue(true);
    }

    /**
     * 測試 Idempotency 使用極大 ID
     */
    public function testIdempotencyWithLargeId(): void
    {
        $largeId = PHP_INT_MAX;

        $markMethod = $this->getPrivateMethod('mark_notification_sent');
        $checkMethod = $this->getPrivateMethod('is_notification_already_sent');

        $markMethod->invoke($this->handler, $largeId);

        $this->assertTrue($checkMethod->invoke($this->handler, $largeId));
    }

    /**
     * 測試連續多次標記同一出貨單
     */
    public function testMultipleMarksForSameShipment(): void
    {
        $markMethod = $this->getPrivateMethod('mark_notification_sent');
        $checkMethod = $this->getPrivateMethod('is_notification_already_sent');

        $shipmentId = 777;

        // 多次標記
        $markMethod->invoke($this->handler, $shipmentId);
        $markMethod->invoke($this->handler, $shipmentId);
        $markMethod->invoke($this->handler, $shipmentId);

        // 應該仍然返回 true
        $this->assertTrue($checkMethod->invoke($this->handler, $shipmentId));
    }

    /**
     * 測試批量出貨單獨立處理
     */
    public function testBatchShipmentsIndependent(): void
    {
        $markMethod = $this->getPrivateMethod('mark_notification_sent');
        $checkMethod = $this->getPrivateMethod('is_notification_already_sent');

        $shipmentIds = [101, 102, 103, 104, 105];

        // 標記部分出貨單
        $markMethod->invoke($this->handler, 101);
        $markMethod->invoke($this->handler, 103);
        $markMethod->invoke($this->handler, 105);

        // 驗證標記狀態
        $this->assertTrue($checkMethod->invoke($this->handler, 101));
        $this->assertFalse($checkMethod->invoke($this->handler, 102));
        $this->assertTrue($checkMethod->invoke($this->handler, 103));
        $this->assertFalse($checkMethod->invoke($this->handler, 104));
        $this->assertTrue($checkMethod->invoke($this->handler, 105));
    }

    // ========================================
    // 整合測試
    // ========================================

    /**
     * 測試完整通知流程（mock 環境）
     */
    public function testCompleteNotificationFlow(): void
    {
        $checkMethod = $this->getPrivateMethod('is_notification_already_sent');
        $markMethod = $this->getPrivateMethod('mark_notification_sent');

        $shipmentId = 888;

        // 1. 初始狀態：未發送
        $this->assertFalse($checkMethod->invoke($this->handler, $shipmentId));

        // 2. 處理出貨事件（mock 環境下會因為資料庫返回 null 而提前返回）
        $this->handler->handle_shipment_marked_shipped($shipmentId);

        // 3. 手動標記為已發送（模擬成功發送）
        $markMethod->invoke($this->handler, $shipmentId);

        // 4. 現在應該返回已發送
        $this->assertTrue($checkMethod->invoke($this->handler, $shipmentId));

        // 5. 再次處理應該被 Idempotency 檢查攔截
        $this->handler->handle_shipment_marked_shipped($shipmentId);

        // 6. 狀態保持為已發送
        $this->assertTrue($checkMethod->invoke($this->handler, $shipmentId));
    }

    /**
     * 測試 DebugService 整合（不拋出異常）
     */
    public function testDebugServiceIntegration(): void
    {
        // 處理事件時會調用 DebugService
        $this->handler->handle_shipment_marked_shipped(123);

        $this->assertTrue(true);
    }
}
