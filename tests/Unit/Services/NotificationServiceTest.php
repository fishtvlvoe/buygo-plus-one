<?php
/**
 * NotificationService Unit Tests
 *
 * @package BuyGoPlus\Tests\Unit\Services
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\NotificationService;

/**
 * Class NotificationServiceTest
 *
 * 測試 NotificationService 的通知發送邏輯
 */
class NotificationServiceTest extends TestCase
{
    /**
     * 測試 isLineNotifyAvailable 方法
     *
     * 當 buygo-line-notify 未載入時應返回 false
     */
    public function testIsLineNotifyAvailableReturnsFalseWhenNotLoaded(): void
    {
        // buygo-line-notify 的 MessagingService 類別不存在
        $this->assertFalse(NotificationService::isLineNotifyAvailable());
    }

    /**
     * 測試 sendText 方法在 buygo-line-notify 未啟用時返回 false
     */
    public function testSendTextReturnsFalseWhenLineNotifyNotAvailable(): void
    {
        $result = NotificationService::sendText(1, 'test_template', []);
        $this->assertFalse($result);
    }

    /**
     * 測試 sendFlex 方法在 buygo-line-notify 未啟用時返回 false
     */
    public function testSendFlexReturnsFalseWhenLineNotifyNotAvailable(): void
    {
        $result = NotificationService::sendFlex(1, 'test_template', []);
        $this->assertFalse($result);
    }

    /**
     * 測試 send 方法在 buygo-line-notify 未啟用時返回 false
     */
    public function testSendReturnsFalseWhenLineNotifyNotAvailable(): void
    {
        $result = NotificationService::send(1, 'test_template', []);
        $this->assertFalse($result);
    }

    /**
     * 測試 sendToMultiple 方法結構
     */
    public function testSendToMultipleReturnsCorrectStructure(): void
    {
        $result = NotificationService::sendToMultiple([1, 2, 3], 'test_template', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('skipped', $result);
    }

    /**
     * 測試 sendToMultiple 方法在用戶未綁定時全部跳過
     */
    public function testSendToMultipleSkipsUnboundUsers(): void
    {
        // Mock 環境下所有用戶都未綁定
        $result = NotificationService::sendToMultiple([1, 2, 3], 'test_template', []);

        // 全部應該被跳過（因為沒有 LINE 綁定）
        $this->assertEquals(3, $result['skipped']);
        $this->assertEquals(0, $result['success']);
        $this->assertEquals(0, $result['failed']);
    }

    /**
     * 測試 sendToSellerAndHelpers 方法結構
     */
    public function testSendToSellerAndHelpersReturnsCorrectStructure(): void
    {
        $result = NotificationService::sendToSellerAndHelpers(1, 'test_template', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('skipped', $result);
    }

    /**
     * 測試 sendRawText 方法在 buygo-line-notify 未啟用時返回 false
     */
    public function testSendRawTextReturnsFalseWhenLineNotifyNotAvailable(): void
    {
        $result = NotificationService::sendRawText(1, 'Test message');
        $this->assertFalse($result);
    }

    /**
     * 測試空用戶列表
     */
    public function testSendToMultipleWithEmptyArray(): void
    {
        $result = NotificationService::sendToMultiple([], 'test_template', []);

        $this->assertEquals(0, $result['success']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEquals(0, $result['skipped']);
    }
}
