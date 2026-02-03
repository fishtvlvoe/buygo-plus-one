<?php
/**
 * NotificationTemplates Unit Tests
 *
 * 測試 v1.3 通知模板引擎的核心功能
 *
 * @package BuyGoPlus\Tests\Unit\Services
 * @since 1.3.0
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\NotificationTemplates;

/**
 * Class NotificationTemplatesTest
 *
 * 測試 NotificationTemplates 的模板引擎功能：
 * - format_product_list: 商品清單格式化
 * - format_estimated_delivery: 預計送達時間格式化
 * - format_shipping_method: 物流方式格式化
 * - 狀態翻譯方法
 * - 模板變數替換
 */
class NotificationTemplatesTest extends TestCase
{
    // ========================================
    // format_product_list 測試
    // ========================================

    /**
     * 測試 format_product_list 基本功能
     */
    public function testFormatProductListWithValidItems(): void
    {
        $items = [
            ['product_name' => '商品A', 'quantity' => 2],
            ['product_name' => '商品B', 'quantity' => 1],
        ];

        $result = NotificationTemplates::format_product_list($items);

        $this->assertStringContainsString('商品A', $result);
        $this->assertStringContainsString('x 2', $result);
        $this->assertStringContainsString('商品B', $result);
        $this->assertStringContainsString('x 1', $result);
    }

    /**
     * 測試 format_product_list 格式正確（每行一個商品）
     */
    public function testFormatProductListLineFormat(): void
    {
        $items = [
            ['product_name' => '商品A', 'quantity' => 2],
            ['product_name' => '商品B', 'quantity' => 1],
        ];

        $result = NotificationTemplates::format_product_list($items);
        $lines = explode("\n", $result);

        $this->assertCount(2, $lines);
        $this->assertEquals('- 商品A x 2', $lines[0]);
        $this->assertEquals('- 商品B x 1', $lines[1]);
    }

    /**
     * 測試 format_product_list 空陣列
     */
    public function testFormatProductListWithEmptyArray(): void
    {
        $result = NotificationTemplates::format_product_list([]);

        $this->assertEquals('（無商品資訊）', $result);
    }

    /**
     * 測試 format_product_list 缺少 product_name
     */
    public function testFormatProductListWithMissingProductName(): void
    {
        $items = [
            ['quantity' => 2],
        ];

        $result = NotificationTemplates::format_product_list($items);

        $this->assertStringContainsString('未知商品', $result);
        $this->assertStringContainsString('x 2', $result);
    }

    /**
     * 測試 format_product_list 缺少 quantity（預設為 1）
     */
    public function testFormatProductListWithMissingQuantity(): void
    {
        $items = [
            ['product_name' => '商品A'],
        ];

        $result = NotificationTemplates::format_product_list($items);

        $this->assertStringContainsString('商品A', $result);
        $this->assertStringContainsString('x 1', $result);
    }

    /**
     * 測試 format_product_list XSS 防護
     */
    public function testFormatProductListEscapesHtml(): void
    {
        $items = [
            ['product_name' => '<script>alert("xss")</script>', 'quantity' => 1],
        ];

        $result = NotificationTemplates::format_product_list($items);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    /**
     * 測試 format_product_list 單一商品
     */
    public function testFormatProductListWithSingleItem(): void
    {
        $items = [
            ['product_name' => '限量商品', 'quantity' => 5],
        ];

        $result = NotificationTemplates::format_product_list($items);

        $this->assertEquals('- 限量商品 x 5', $result);
    }

    /**
     * 測試 format_product_list 大量商品
     */
    public function testFormatProductListWithManyItems(): void
    {
        $items = [];
        for ($i = 1; $i <= 10; $i++) {
            $items[] = ['product_name' => "商品{$i}", 'quantity' => $i];
        }

        $result = NotificationTemplates::format_product_list($items);
        $lines = explode("\n", $result);

        $this->assertCount(10, $lines);
    }

    // ========================================
    // format_estimated_delivery 測試
    // ========================================

    /**
     * 測試 format_estimated_delivery 正常日期
     */
    public function testFormatEstimatedDeliveryWithValidDate(): void
    {
        $result = NotificationTemplates::format_estimated_delivery('2026-02-05 00:00:00');

        $this->assertEquals('2026/02/05', $result);
    }

    /**
     * 測試 format_estimated_delivery 只有日期部分
     */
    public function testFormatEstimatedDeliveryWithDateOnly(): void
    {
        $result = NotificationTemplates::format_estimated_delivery('2026-12-25 00:00:00');

        $this->assertEquals('2026/12/25', $result);
    }

    /**
     * 測試 format_estimated_delivery 空值
     */
    public function testFormatEstimatedDeliveryWithNull(): void
    {
        $result = NotificationTemplates::format_estimated_delivery(null);

        $this->assertEquals('配送中', $result);
    }

    /**
     * 測試 format_estimated_delivery 空字串
     */
    public function testFormatEstimatedDeliveryWithEmptyString(): void
    {
        $result = NotificationTemplates::format_estimated_delivery('');

        $this->assertEquals('配送中', $result);
    }

    /**
     * 測試 format_estimated_delivery 無效日期格式
     */
    public function testFormatEstimatedDeliveryWithInvalidDate(): void
    {
        $result = NotificationTemplates::format_estimated_delivery('invalid-date');

        $this->assertEquals('配送中', $result);
    }

    /**
     * 測試 format_estimated_delivery 不同時間部分
     */
    public function testFormatEstimatedDeliveryIgnoresTime(): void
    {
        $result1 = NotificationTemplates::format_estimated_delivery('2026-02-05 00:00:00');
        $result2 = NotificationTemplates::format_estimated_delivery('2026-02-05 23:59:59');

        $this->assertEquals($result1, $result2);
        $this->assertEquals('2026/02/05', $result1);
    }

    // ========================================
    // format_shipping_method 測試
    // ========================================

    /**
     * 測試 format_shipping_method 標準配送
     */
    public function testFormatShippingMethodStandard(): void
    {
        $result = NotificationTemplates::format_shipping_method('standard');

        $this->assertEquals('標準配送', $result);
    }

    /**
     * 測試 format_shipping_method 快速配送
     */
    public function testFormatShippingMethodExpress(): void
    {
        $result = NotificationTemplates::format_shipping_method('express');

        $this->assertEquals('快速配送', $result);
    }

    /**
     * 測試 format_shipping_method 自取
     */
    public function testFormatShippingMethodPickup(): void
    {
        $result = NotificationTemplates::format_shipping_method('pickup');

        $this->assertEquals('自取', $result);
    }

    /**
     * 測試 format_shipping_method 超商取貨
     */
    public function testFormatShippingMethodConvenienceStore(): void
    {
        $result = NotificationTemplates::format_shipping_method('convenience_store');

        $this->assertEquals('超商取貨', $result);
    }

    /**
     * 測試 format_shipping_method 7-11 取貨
     */
    public function testFormatShippingMethod711(): void
    {
        $result = NotificationTemplates::format_shipping_method('7-11');

        $this->assertEquals('7-ELEVEN 取貨', $result);
    }

    /**
     * 測試 format_shipping_method 全家取貨
     */
    public function testFormatShippingMethodFamily(): void
    {
        $result = NotificationTemplates::format_shipping_method('family');

        $this->assertEquals('全家取貨', $result);
    }

    /**
     * 測試 format_shipping_method 空值
     */
    public function testFormatShippingMethodWithNull(): void
    {
        $result = NotificationTemplates::format_shipping_method(null);

        $this->assertEquals('標準配送', $result);
    }

    /**
     * 測試 format_shipping_method 空字串
     */
    public function testFormatShippingMethodWithEmptyString(): void
    {
        $result = NotificationTemplates::format_shipping_method('');

        $this->assertEquals('標準配送', $result);
    }

    /**
     * 測試 format_shipping_method 未知物流方式（原樣返回）
     */
    public function testFormatShippingMethodUnknown(): void
    {
        $result = NotificationTemplates::format_shipping_method('custom_delivery');

        $this->assertEquals('custom_delivery', $result);
    }

    /**
     * 測試 format_shipping_method 大小寫不敏感
     */
    public function testFormatShippingMethodCaseInsensitive(): void
    {
        $result1 = NotificationTemplates::format_shipping_method('STANDARD');
        $result2 = NotificationTemplates::format_shipping_method('Standard');
        $result3 = NotificationTemplates::format_shipping_method('standard');

        $this->assertEquals('標準配送', $result1);
        $this->assertEquals('標準配送', $result2);
        $this->assertEquals('標準配送', $result3);
    }

    /**
     * 測試 format_shipping_method XSS 防護
     */
    public function testFormatShippingMethodEscapesHtml(): void
    {
        $result = NotificationTemplates::format_shipping_method('<script>alert("xss")</script>');

        $this->assertStringNotContainsString('<script>', $result);
    }

    // ========================================
    // get_payment_status_text 測試
    // ========================================

    /**
     * 測試 get_payment_status_text 未付款
     */
    public function testGetPaymentStatusTextUnpaid(): void
    {
        $result = NotificationTemplates::get_payment_status_text('unpaid');

        $this->assertEquals('未付款', $result);
    }

    /**
     * 測試 get_payment_status_text 已付款
     */
    public function testGetPaymentStatusTextPaid(): void
    {
        $result = NotificationTemplates::get_payment_status_text('paid');

        $this->assertEquals('已付款', $result);
    }

    /**
     * 測試 get_payment_status_text 已退款
     */
    public function testGetPaymentStatusTextRefunded(): void
    {
        $result = NotificationTemplates::get_payment_status_text('refunded');

        $this->assertEquals('已退款', $result);
    }

    /**
     * 測試 get_payment_status_text 未知狀態（原樣返回）
     */
    public function testGetPaymentStatusTextUnknown(): void
    {
        $result = NotificationTemplates::get_payment_status_text('unknown_status');

        $this->assertEquals('unknown_status', $result);
    }

    // ========================================
    // get_procurement_status_text 測試
    // ========================================

    /**
     * 測試 get_procurement_status_text 未處理
     */
    public function testGetProcurementStatusTextPending(): void
    {
        $result = NotificationTemplates::get_procurement_status_text('pending');

        $this->assertEquals('未處理', $result);
    }

    /**
     * 測試 get_procurement_status_text 處理中
     */
    public function testGetProcurementStatusTextProcessing(): void
    {
        $result = NotificationTemplates::get_procurement_status_text('processing');

        $this->assertEquals('處理中', $result);
    }

    /**
     * 測試 get_procurement_status_text 已採購
     */
    public function testGetProcurementStatusTextPurchased(): void
    {
        $result = NotificationTemplates::get_procurement_status_text('purchased');

        $this->assertEquals('已採購', $result);
    }

    /**
     * 測試 get_procurement_status_text 已到貨
     */
    public function testGetProcurementStatusTextCompleted(): void
    {
        $result = NotificationTemplates::get_procurement_status_text('completed');

        $this->assertEquals('已到貨', $result);
    }

    /**
     * 測試 get_procurement_status_text 斷貨
     */
    public function testGetProcurementStatusTextCancelled(): void
    {
        $result = NotificationTemplates::get_procurement_status_text('cancelled');

        $this->assertEquals('斷貨', $result);
    }

    // ========================================
    // get_order_status_text 測試
    // ========================================

    /**
     * 測試 get_order_status_text 進行中
     */
    public function testGetOrderStatusTextActive(): void
    {
        $result = NotificationTemplates::get_order_status_text('active');

        $this->assertEquals('進行中', $result);
    }

    /**
     * 測試 get_order_status_text 已完成
     */
    public function testGetOrderStatusTextCompleted(): void
    {
        $result = NotificationTemplates::get_order_status_text('completed');

        $this->assertEquals('已完成', $result);
    }

    /**
     * 測試 get_order_status_text 已取消
     */
    public function testGetOrderStatusTextCancelled(): void
    {
        $result = NotificationTemplates::get_order_status_text('cancelled');

        $this->assertEquals('已取消', $result);
    }

    // ========================================
    // get 方法測試（模板變數替換）
    // ========================================

    /**
     * 測試 get 方法取得預設模板
     */
    public function testGetReturnsDefaultTemplate(): void
    {
        $result = NotificationTemplates::get('shipment_shipped', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('line', $result);
    }

    /**
     * 測試 get 方法變數替換
     */
    public function testGetReplacesVariables(): void
    {
        $args = [
            'product_list' => "- 商品A x 2\n- 商品B x 1",
            'shipping_method' => '宅配',
            'estimated_delivery' => '2026/02/05'
        ];

        $result = NotificationTemplates::get('shipment_shipped', $args);

        $this->assertArrayHasKey('line', $result);
        $message = $result['line']['text'] ?? '';

        $this->assertStringContainsString('商品A', $message);
        $this->assertStringContainsString('宅配', $message);
        $this->assertStringContainsString('2026/02/05', $message);
    }

    /**
     * 測試 get 方法不存在的模板
     */
    public function testGetReturnsNullForNonExistentTemplate(): void
    {
        $result = NotificationTemplates::get('non_existent_template', []);

        $this->assertNull($result);
    }

    /**
     * 測試 get 方法返回正確的類型
     */
    public function testGetReturnsCorrectType(): void
    {
        $result = NotificationTemplates::get('shipment_shipped', []);

        $this->assertEquals('text', $result['type']);
    }

    // ========================================
    // clear_cache 測試
    // ========================================

    /**
     * 測試 clear_cache 不會拋出異常
     */
    public function testClearCacheDoesNotThrow(): void
    {
        // clear_cache 應該能正常執行不拋出異常
        NotificationTemplates::clear_cache();

        $this->assertTrue(true);
    }

    // ========================================
    // 整合測試：完整出貨通知流程
    // ========================================

    /**
     * 測試完整出貨通知流程（format 方法組合）
     */
    public function testCompleteShipmentNotificationFlow(): void
    {
        // 1. 準備商品資料
        $items = [
            ['product_name' => '限量版 T-shirt', 'quantity' => 2],
            ['product_name' => '經典帆布包', 'quantity' => 1],
        ];

        // 2. 格式化各項資料
        $productList = NotificationTemplates::format_product_list($items);
        $shippingMethod = NotificationTemplates::format_shipping_method('7-11');
        $estimatedDelivery = NotificationTemplates::format_estimated_delivery('2026-02-10 00:00:00');

        // 3. 驗證格式化結果
        $this->assertStringContainsString('限量版 T-shirt x 2', $productList);
        $this->assertStringContainsString('經典帆布包 x 1', $productList);
        $this->assertEquals('7-ELEVEN 取貨', $shippingMethod);
        $this->assertEquals('2026/02/10', $estimatedDelivery);

        // 4. 取得模板並替換變數
        $args = [
            'product_list' => $productList,
            'shipping_method' => $shippingMethod,
            'estimated_delivery' => $estimatedDelivery
        ];

        $result = NotificationTemplates::get('shipment_shipped', $args);

        // 5. 驗證最終結果
        $this->assertIsArray($result);
        $this->assertEquals('text', $result['type']);

        $message = $result['line']['text'] ?? '';
        $this->assertStringContainsString('限量版 T-shirt', $message);
        $this->assertStringContainsString('7-ELEVEN 取貨', $message);
        $this->assertStringContainsString('2026/02/10', $message);
    }

    /**
     * 測試無預計送達時間的出貨通知
     */
    public function testShipmentNotificationWithoutEstimatedDelivery(): void
    {
        $items = [
            ['product_name' => '商品A', 'quantity' => 1],
        ];

        $args = [
            'product_list' => NotificationTemplates::format_product_list($items),
            'shipping_method' => NotificationTemplates::format_shipping_method('standard'),
            'estimated_delivery' => NotificationTemplates::format_estimated_delivery(null)
        ];

        $result = NotificationTemplates::get('shipment_shipped', $args);

        $message = $result['line']['text'] ?? '';
        $this->assertStringContainsString('配送中', $message);
    }
}
