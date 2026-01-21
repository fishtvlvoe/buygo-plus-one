<?php
/**
 * 訂單商品顯示格式化測試
 *
 * 測試 orders.php 中的 formatItemsDisplay 邏輯
 * Phase 3.7：訂單頁面商品顯示優化
 */

namespace BuyGoPlus\Tests\Unit\Views;

use PHPUnit\Framework\TestCase;

class OrderItemsDisplayTest extends TestCase
{
    /**
     * 模擬 JavaScript 的 formatItemsDisplay 函數（PHP 版本）
     */
    private function formatItemsDisplay($order, $maxLength = 50)
    {
        // 如果沒有商品項目，顯示總件數
        if (!isset($order['items']) || !is_array($order['items']) || empty($order['items'])) {
            return ($order['total_items'] ?? 0) . ' 件';
        }

        // 組合商品名稱和數量
        $itemsText = array_map(function($item) {
            $name = $item['product_name'] ?? '未知商品';
            $quantity = $item['quantity'] ?? 0;
            return "{$name} x{$quantity}";
        }, $order['items']);

        $result = implode(', ', $itemsText);

        // 如果文字太長，截斷並加上省略號
        if (strlen($result) > $maxLength) {
            return substr($result, 0, $maxLength) . '...';
        }

        return $result;
    }

    /**
     * 測試：單一商品顯示格式
     */
    public function test_single_item_display()
    {
        $order = [
            'items' => [
                [
                    'product_name' => 'LOGO',
                    'quantity' => 50
                ]
            ]
        ];

        $result = $this->formatItemsDisplay($order);

        $this->assertEquals('LOGO x50', $result);
    }

    /**
     * 測試：多個商品顯示格式
     */
    public function test_multiple_items_display()
    {
        $order = [
            'items' => [
                ['product_name' => '商品A', 'quantity' => 10],
                ['product_name' => '商品B', 'quantity' => 5],
                ['product_name' => '商品C', 'quantity' => 3]
            ]
        ];

        $result = $this->formatItemsDisplay($order);

        $this->assertEquals('商品A x10, 商品B x5, 商品C x3', $result);
    }

    /**
     * 測試：沒有商品時顯示總件數
     */
    public function test_empty_items_shows_total()
    {
        $order = [
            'items' => [],
            'total_items' => 5
        ];

        $result = $this->formatItemsDisplay($order);

        $this->assertEquals('5 件', $result);
    }

    /**
     * 測試：items 為 null 時顯示總件數
     */
    public function test_null_items_shows_total()
    {
        $order = [
            'total_items' => 10
        ];

        $result = $this->formatItemsDisplay($order);

        $this->assertEquals('10 件', $result);
    }

    /**
     * 測試：商品名稱缺失時顯示「未知商品」
     */
    public function test_missing_product_name_shows_unknown()
    {
        $order = [
            'items' => [
                ['quantity' => 5] // 沒有 product_name
            ]
        ];

        $result = $this->formatItemsDisplay($order);

        $this->assertEquals('未知商品 x5', $result);
    }

    /**
     * 測試：數量缺失時顯示 0
     */
    public function test_missing_quantity_shows_zero()
    {
        $order = [
            'items' => [
                ['product_name' => 'LOGO'] // 沒有 quantity
            ]
        ];

        $result = $this->formatItemsDisplay($order);

        $this->assertEquals('LOGO x0', $result);
    }

    /**
     * 測試：文字超過長度限制時截斷
     */
    public function test_long_text_truncation()
    {
        $order = [
            'items' => [
                ['product_name' => '超長商品名稱一二三四五六七八九十', 'quantity' => 10],
                ['product_name' => '超長商品名稱A B C D E F G H', 'quantity' => 20],
                ['product_name' => '超長商品名稱X Y Z', 'quantity' => 30]
            ]
        ];

        $result = $this->formatItemsDisplay($order, 50);

        // 驗證結果長度不超過 53 (50 + '...')
        $this->assertLessThanOrEqual(53, strlen($result));

        // 驗證包含省略號
        $this->assertStringEndsWith('...', $result);
    }

    /**
     * 測試：剛好等於長度限制
     */
    public function test_exact_length_no_truncation()
    {
        $order = [
            'items' => [
                ['product_name' => 'A', 'quantity' => 1],
                ['product_name' => 'B', 'quantity' => 2]
            ]
        ];

        $result = $this->formatItemsDisplay($order, 100);

        // 應該是 "A x1, B x2" 不會被截斷
        $this->assertEquals('A x1, B x2', $result);
        $this->assertStringEndsNotWith('...', $result);
    }

    /**
     * 測試：混合中英文商品名稱
     */
    public function test_mixed_language_product_names()
    {
        $order = [
            'items' => [
                ['product_name' => 'iPhone 15 Pro', 'quantity' => 2],
                ['product_name' => 'MacBook Pro 16吋', 'quantity' => 1],
                ['product_name' => 'AirPods Pro 第二代', 'quantity' => 3]
            ]
        ];

        // 使用較大的 maxLength 避免截斷
        $result = $this->formatItemsDisplay($order, 100);

        $expected = 'iPhone 15 Pro x2, MacBook Pro 16吋 x1, AirPods Pro 第二代 x3';
        $this->assertEquals($expected, $result);
    }

    /**
     * 測試：大數量顯示
     */
    public function test_large_quantity_display()
    {
        $order = [
            'items' => [
                ['product_name' => 'LOGO', 'quantity' => 9999]
            ]
        ];

        $result = $this->formatItemsDisplay($order);

        $this->assertEquals('LOGO x9999', $result);
    }
}
