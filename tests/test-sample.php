<?php
/**
 * 測試範例
 *
 * 這是一個基礎測試，用來驗證測試環境是否正確設定
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Tests;

use WP_UnitTestCase;

/**
 * 測試範例類別
 */
class SampleTest extends WP_UnitTestCase {

    /**
     * 測試 WordPress 環境是否正確載入
     */
    public function test_wordpress_loaded() {
        $this->assertTrue( function_exists( 'do_action' ) );
        $this->assertTrue( function_exists( 'add_filter' ) );
    }

    /**
     * 測試外掛是否正確載入
     */
    public function test_plugin_loaded() {
        $this->assertTrue( defined( 'BUYGO_PLUS_ONE_VERSION' ) );
        $this->assertTrue( defined( 'BUYGO_PLUS_ONE_PLUGIN_DIR' ) );
    }

    /**
     * 測試外掛版本
     */
    public function test_plugin_version() {
        $this->assertEquals( '0.0.1', BUYGO_PLUS_ONE_VERSION );
    }

    /**
     * 測試基本算術（確保 PHPUnit 正常運作）
     */
    public function test_basic_math() {
        $this->assertEquals( 4, 2 + 2 );
        $this->assertTrue( 3 > 2 );
        $this->assertFalse( 1 > 2 );
    }
}
