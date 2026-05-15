<?php

namespace BuyGoPlus\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\ProductStatsCalculator;

/**
 * 測試 ProductsApi 統計欄位行為
 *
 * 驗證目標：
 * - reserved 公式正確（ordered - purchased - allocated，下限 0）
 * - class-products-api.php 不含 transient 快取字串
 * - 列表端點與單品端點使用相同公式
 *
 * 這是 TDD 紅燈測試：
 * - test_reserved_* 紅燈因 ProductStatsCalculator::reserved() 尚未存在
 * - test_no_transient_calls_in_products_api 紅燈因 transient 字串仍存在
 * - test_list_and_single_use_same_formula 紅燈因列表端點公式未對齊
 */
class ProductsApiStatsTest extends TestCase
{
    /**
     * 確保 BUYGO_PLUS_ONE_PLUGIN_DIR 在純單元測試環境中有定義
     */
    public static function setUpBeforeClass(): void
    {
        if (!defined('BUYGO_PLUS_ONE_PLUGIN_DIR')) {
            define('BUYGO_PLUS_ONE_PLUGIN_DIR', dirname(__DIR__, 3) . '/');
        }
    }

    // ----------------------------------------------------------------
    // 1. reserved 基礎公式測試
    // ----------------------------------------------------------------

    /**
     * 基本案例：reserved = max(0, ordered - purchased - allocated)
     *
     * ordered=10, purchased=5, allocated=2 → reserved = max(0, 10-5-2) = 3
     *
     * 注意：spec example table 中 [10,10,4,6] 是文件筆誤，
     * 公式 max(0, 10-10-4) = 0，不是 6。此測試以正確公式為準。
     */
    public function test_reserved_subtracts_allocated(): void
    {
        $result = ProductStatsCalculator::reserved(10, 5, 2);

        $this->assertSame(
            3,
            $result,
            'reserved 應為 max(0, ordered(10) - purchased(5) - allocated(2)) = 3'
        );
    }

    /**
     * 邊界案例：結果不可為負，floor at 0
     */
    public function test_reserved_floor_at_zero(): void
    {
        // allocated 超過 ordered - purchased
        $this->assertSame(
            0,
            ProductStatsCalculator::reserved(10, 10, 12),
            'reserved 不可為負，allocated 超出時應回傳 0'
        );

        // 全部為 0
        $this->assertSame(
            0,
            ProductStatsCalculator::reserved(0, 0, 0),
            '全部為 0 時 reserved 應回傳 0'
        );
    }

    /**
     * 表格測試：多組輸入驗證公式 reserved = max(0, ordered - purchased - allocated)
     *
     * 欄位：[ordered, purchased, allocated, expected_reserved]
     *
     * 注意：spec example table 中 [10,10,4] expected=6 為文件錯誤，
     * max(0, 10-10-4) = 0。本測試以正確數學為準。
     */
    public function test_reserved_table_cases(): void
    {
        $cases = [
            [0,  0,  0,  0],
            [10, 5,  0,  5],
            [10, 10, 0,  0],
            [10, 10, 4,  0],  // spec typo: expected 6，實際公式 max(0,10-10-4)=0
            [10, 10, 12, 0],
            [2,  2,  2,  0],
        ];

        foreach ($cases as [$ordered, $purchased, $allocated, $expected]) {
            $actual = ProductStatsCalculator::reserved($ordered, $purchased, $allocated);
            $this->assertSame(
                $expected,
                $actual,
                "reserved({$ordered}, {$purchased}, {$allocated}) 應為 {$expected}，實際為 {$actual}"
            );
        }
    }

    // ----------------------------------------------------------------
    // 2. 快取字串掃描測試
    // ----------------------------------------------------------------

    /**
     * class-products-api.php 不應含 transient 快取讀寫字串
     * 當前應紅燈（get_transient / set_transient 及 cache_key 字串仍存在）
     *
     * 掃描三個特徵字串：
     * - cache_key = 'buygo_products_'   （快取 key 建構）
     * - get_transient($cache_key)        （快取讀取）
     * - set_transient($cache_key,        （快取寫入）
     */
    public function test_no_transient_calls_in_products_api(): void
    {
        $apiFile = BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-products-api.php';
        $src = file_get_contents($apiFile);

        $this->assertNotFalse(
            $src,
            'class-products-api.php 必須可讀取'
        );

        // cache_key 建構字串
        $this->assertFalse(
            strpos($src, "\$cache_key = 'buygo_products_'"),
            "class-products-api.php 不應含 cache key 建構字串 — 快取機制應已移除"
        );

        // 快取讀取
        $this->assertFalse(
            strpos($src, 'get_transient($cache_key)'),
            'class-products-api.php 不應含 get_transient($cache_key) — 快取讀取應已移除'
        );

        // 快取寫入
        $this->assertFalse(
            strpos($src, 'set_transient($cache_key,'),
            'class-products-api.php 不應含 set_transient($cache_key, — 快取寫入應已移除'
        );
    }

    // ----------------------------------------------------------------
    // 3. 列表端點與單品端點公式一致性測試
    // ----------------------------------------------------------------

    /**
     * 列表端點（L440）與單品端點（L338）都應透過 ProductStatsCalculator::reserved() 計算
     * 且不應殘留裸寫公式 'reserved' => max(0, ($product['ordered']
     */
    public function test_list_and_single_use_same_formula(): void
    {
        $apiFile = BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-products-api.php';
        $src = file_get_contents($apiFile);

        $this->assertNotFalse($src, 'class-products-api.php 必須可讀取');

        // 計算透過 ProductStatsCalculator::reserved( 呼叫的次數（全限定或 use 後短名皆可）
        $helperCallCount = substr_count($src, 'ProductStatsCalculator::reserved(');

        $this->assertGreaterThanOrEqual(
            2,
            $helperCallCount,
            "class-products-api.php 應至少有 2 次 ProductStatsCalculator::reserved( 呼叫（列表端點 + 單品端點），實際只有 {$helperCallCount} 次"
        );

        // 不應殘留裸寫公式（列表端點舊公式：只有 ordered - purchased，沒扣 allocated）
        $this->assertFalse(
            strpos($src, "'reserved' => max(0, (\$product['ordered']"),
            "class-products-api.php 不應殘留裸寫的 reserved 計算公式，應統一走 ProductStatsCalculator::reserved()"
        );
    }
}
