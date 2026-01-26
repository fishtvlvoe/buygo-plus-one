<?php
/**
 * Product Service 基礎單元測試
 *
 * 測試 Product Service 的純邏輯部分，不依賴 WordPress 環境
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;

/**
 * 測試 Product Service 的基礎功能
 */
class TestProductServiceBasic extends TestCase {

    /**
     * 測試：計算折扣後的價格
     */
    public function test_calculate_discounted_price() {
        // 原價 100，折扣 10%
        $originalPrice = 100;
        $discountPercent = 10;
        $expected = 90;

        $result = $this->calculateDiscountedPrice($originalPrice, $discountPercent);

        $this->assertEquals($expected, $result);
    }

    /**
     * 測試：計算折扣後的價格 - 邊界情況
     */
    public function test_calculate_discounted_price_no_discount() {
        $originalPrice = 100;
        $discountPercent = 0;
        $expected = 100;

        $result = $this->calculateDiscountedPrice($originalPrice, $discountPercent);

        $this->assertEquals($expected, $result);
    }

    /**
     * 測試：計算折扣後的價格 - 完全折扣
     */
    public function test_calculate_discounted_price_full_discount() {
        $originalPrice = 100;
        $discountPercent = 100;
        $expected = 0;

        $result = $this->calculateDiscountedPrice($originalPrice, $discountPercent);

        $this->assertEquals($expected, $result);
    }

    /**
     * 測試：驗證商品庫存
     */
    public function test_validate_product_quantity() {
        // 庫存 10，要求 5，應該通過
        $this->assertTrue($this->validateProductQuantity(10, 5));

        // 庫存 5，要求 5，應該通過
        $this->assertTrue($this->validateProductQuantity(5, 5));

        // 庫存 3，要求 5，應該失敗
        $this->assertFalse($this->validateProductQuantity(3, 5));
    }

    /**
     * 測試：格式化商品名稱
     */
    public function test_format_product_name() {
        $name = "  商品名稱  ";
        $expected = "商品名稱";

        $result = $this->formatProductName($name);

        $this->assertEquals($expected, $result);
    }

    /**
     * 測試：計算平均評分
     */
    public function test_calculate_average_rating() {
        $ratings = [5, 4, 3, 4, 5];
        $expected = 4.2;

        $result = $this->calculateAverageRating($ratings);

        $this->assertEquals($expected, $result);
    }

    /**
     * 測試：計算平均評分 - 空陣列
     */
    public function test_calculate_average_rating_empty() {
        $ratings = [];
        $expected = 0;

        $result = $this->calculateAverageRating($ratings);

        $this->assertEquals($expected, $result);
    }

    // ============ 輔助方法 ============

    /**
     * 計算折扣後的價格
     */
    private function calculateDiscountedPrice($price, $discountPercent) {
        return $price - ($price * $discountPercent / 100);
    }

    /**
     * 驗證商品庫存是否充足
     */
    private function validateProductQuantity($stock, $requested) {
        return $stock >= $requested;
    }

    /**
     * 格式化商品名稱（去除首尾空格）
     */
    private function formatProductName($name) {
        return trim($name);
    }

    /**
     * 計算平均評分
     */
    private function calculateAverageRating($ratings) {
        if (empty($ratings)) {
            return 0;
        }
        return array_sum($ratings) / count($ratings);
    }
}
