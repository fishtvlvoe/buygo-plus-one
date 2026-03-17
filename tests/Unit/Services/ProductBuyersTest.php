<?php
/**
 * ProductService::getProductBuyers 多樣式商品 Unit Tests
 *
 * 驗證多樣式商品的「下單名單」能正確顯示所有 variant 的訂單，
 * 而非只顯示傳入的單一 variant 的訂單。
 *
 * Bug 背景：
 * getProductBuyers($variationId) 原本用 WHERE object_id = $variationId 查詢，
 * 多樣式商品有多個 variation，訂單分散在不同 variation，導致只顯示其中一個的訂單。
 *
 * 修正策略：
 * 1. 新增 getSiblingVariationIds() 方法，透過 post_id 找出所有同商品的 variation IDs
 * 2. getProductBuyers() 改用 whereIn('object_id', $allIds) 查詢
 *
 * @package BuyGoPlus\Tests\Unit\Services
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\ProductService;
use ReflectionClass;

class ProductBuyersTest extends TestCase
{
    /**
     * 設定 wpdb mock，支援 fct_product_variations 查詢
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 重置 mock 狀態
        $GLOBALS['mock_variation_rows'] = [];
        $GLOBALS['mock_variation_post_id'] = [];

        // 安裝支援 variation 查詢的 wpdb mock
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public $insert_id = 0;
            public $last_error = '';

            public function prepare($query, ...$args) {
                return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
            }

            public function get_var($query) {
                // 查詢 variation 的 post_id：SELECT post_id FROM ... WHERE id = X
                if (strpos($query, 'fct_product_variations') !== false
                    && strpos($query, 'post_id') !== false
                    && preg_match("/id\s*=\s*'?(\d+)'?/", $query, $m)) {
                    $varId = (int) $m[1];
                    return $GLOBALS['mock_variation_post_id'][$varId] ?? null;
                }

                // SHOW TABLES
                if (strpos($query, 'SHOW TABLES') !== false) {
                    return null;
                }
                return null;
            }

            public function get_col($query) {
                // 查詢同 post_id 的所有 variation IDs
                // SELECT id FROM ... WHERE post_id = X AND item_status = 'active'
                if (strpos($query, 'fct_product_variations') !== false
                    && preg_match("/post_id\s*=\s*'?(\d+)'?/", $query, $m)) {
                    $postId = (int) $m[1];
                    return $GLOBALS['mock_variation_rows'][$postId] ?? [];
                }
                return [];
            }

            public function get_row($query, $output = OBJECT) { return null; }
            public function get_results($query, $output = OBJECT) { return []; }
            public function insert($table, $data, $format = null) { $this->insert_id = 1; return 1; }
            public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
            public function delete($table, $where, $where_format = null) { return 1; }
            public function query($query) { return true; }
        };
    }

    /**
     * 測試：單樣式商品 → getSiblingVariationIds 回傳只有自己
     */
    public function test_single_variant_returns_only_self()
    {
        // 商品 post_id=100，只有一個 variation（id=958）
        $GLOBALS['mock_variation_post_id'][958] = 100;
        $GLOBALS['mock_variation_rows'][100] = [958];

        $service = new ProductService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getSiblingVariationIds');


        $result = $method->invoke($service, 958);

        $this->assertCount(1, $result);
        $this->assertEquals([958], $result);
    }

    /**
     * 測試：多樣式商品 → getSiblingVariationIds 回傳所有 variant IDs
     */
    public function test_multi_variant_returns_all_siblings()
    {
        // 商品 post_id=200，有 5 個 variations
        $allVariationIds = [955, 956, 957, 958, 959];
        $GLOBALS['mock_variation_post_id'][958] = 200;
        $GLOBALS['mock_variation_rows'][200] = $allVariationIds;

        $service = new ProductService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getSiblingVariationIds');


        $result = $method->invoke($service, 958);

        $this->assertCount(5, $result);
        $this->assertEquals($allVariationIds, $result);
    }

    /**
     * 測試：variation ID 不存在 → 回傳原始 ID（降級處理）
     */
    public function test_nonexistent_variation_returns_original_id()
    {
        // 沒有設定任何 mock 資料
        $service = new ProductService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getSiblingVariationIds');


        $result = $method->invoke($service, 999);

        $this->assertCount(1, $result);
        $this->assertEquals([999], $result);
    }

    /**
     * 測試：傳入任一 sibling variation ID，結果都相同
     */
    public function test_any_sibling_id_returns_same_result()
    {
        $allVariationIds = [955, 956, 957, 958, 959];

        // 每個 variation 都指向同一個 post_id
        foreach ($allVariationIds as $vid) {
            $GLOBALS['mock_variation_post_id'][$vid] = 200;
        }
        $GLOBALS['mock_variation_rows'][200] = $allVariationIds;

        $service = new ProductService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getSiblingVariationIds');


        // 用不同的 variation ID 呼叫，結果都一樣
        $result1 = $method->invoke($service, 955);
        $result2 = $method->invoke($service, 958);
        $result3 = $method->invoke($service, 959);

        $this->assertEquals($result1, $result2);
        $this->assertEquals($result2, $result3);
    }
}
