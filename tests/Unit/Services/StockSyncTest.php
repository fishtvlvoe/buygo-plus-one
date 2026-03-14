<?php
/**
 * StockSync Unit Tests
 *
 * 測試庫存同步邏輯：BuyGo 更新庫存時正確同步到 FluentCart 欄位
 *
 * @package BuyGoPlus\Tests\Unit\Services
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;

class StockSyncTest extends TestCase
{
    /**
     * 呼叫靜態方法 FluentCartService::calculateStockFields
     */
    private function calc(?int $newStock, array $current): array
    {
        return \BuyGoPlus\Services\FluentCartService::calculateStockFields($newStock, $current);
    }

    /**
     * 測試：設定庫存 50，無訂單保留，available 應為 50
     */
    public function testNoHolds(): void
    {
        $result = $this->calc(50, [
            'total_stock' => 30, 'available' => 30,
            'on_hold' => 0, 'committed' => 0, 'manage_stock' => 1,
        ]);

        $this->assertEquals(50, $result['total_stock']);
        $this->assertEquals(50, $result['available']);
        $this->assertEquals(0, $result['on_hold']);
        $this->assertEquals(0, $result['committed']);
        $this->assertEquals(1, $result['manage_stock']);
    }

    /**
     * 測試：有 on_hold=3 和 committed=2，available = 50 - 3 - 2 = 45
     */
    public function testWithHoldsAndCommitted(): void
    {
        $result = $this->calc(50, [
            'total_stock' => 30, 'available' => 25,
            'on_hold' => 3, 'committed' => 2, 'manage_stock' => 1,
        ]);

        $this->assertEquals(50, $result['total_stock']);
        $this->assertEquals(45, $result['available']);
        $this->assertEquals(3, $result['on_hold']);
        $this->assertEquals(2, $result['committed']);
    }

    /**
     * 測試：庫存 2 但 on_hold=3，available 不應為負數
     */
    public function testAvailableNeverNegative(): void
    {
        $result = $this->calc(2, [
            'total_stock' => 10, 'available' => 7,
            'on_hold' => 3, 'committed' => 0, 'manage_stock' => 1,
        ]);

        $this->assertEquals(2, $result['total_stock']);
        $this->assertEquals(0, $result['available']);
    }

    /**
     * 測試：庫存 0 = 缺貨（manage_stock 仍為 1，不是無限量）
     */
    public function testZeroMeansOutOfStock(): void
    {
        $result = $this->calc(0, [
            'total_stock' => 10, 'available' => 10,
            'on_hold' => 0, 'committed' => 0, 'manage_stock' => 1,
        ]);

        $this->assertEquals(0, $result['total_stock']);
        $this->assertEquals(0, $result['available']);
        $this->assertEquals(1, $result['manage_stock']);
        $this->assertEquals('out-of-stock', $result['stock_status']);
    }

    /**
     * 測試：原本無限量(manage_stock=0)，設定庫存後應啟用追蹤
     */
    public function testEnablesStockTracking(): void
    {
        $result = $this->calc(10, [
            'total_stock' => 0, 'available' => 0,
            'on_hold' => 0, 'committed' => 0, 'manage_stock' => 0,
        ]);

        $this->assertEquals(10, $result['total_stock']);
        $this->assertEquals(10, $result['available']);
        $this->assertEquals(1, $result['manage_stock']);
        $this->assertEquals('in-stock', $result['stock_status']);
    }

    /**
     * 測試：stock_status 正確反映 available
     */
    public function testStockStatus(): void
    {
        // 有庫存 → in-stock
        $result = $this->calc(10, [
            'total_stock' => 0, 'available' => 0,
            'on_hold' => 0, 'committed' => 0, 'manage_stock' => 1,
        ]);
        $this->assertEquals('in-stock', $result['stock_status']);

        // 庫存 0 → out-of-stock
        $result = $this->calc(0, [
            'total_stock' => 10, 'available' => 10,
            'on_hold' => 0, 'committed' => 0, 'manage_stock' => 1,
        ]);
        $this->assertEquals('out-of-stock', $result['stock_status']);
    }

    /**
     * 測試：null 代表無限量模式
     */
    public function testNullMeansUnlimited(): void
    {
        $result = $this->calc(null, [
            'total_stock' => 10, 'available' => 10,
            'on_hold' => 0, 'committed' => 0, 'manage_stock' => 1,
        ]);

        $this->assertEquals(0, $result['manage_stock']);
        $this->assertEquals(0, $result['total_stock']);
        $this->assertEquals(0, $result['available']);
        $this->assertEquals('in-stock', $result['stock_status']);
    }
}
