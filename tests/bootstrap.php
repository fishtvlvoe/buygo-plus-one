<?php
/**
 * PHPUnit Bootstrap File
 *
 * 載入 WordPress 測試環境和外掛
 */

// Composer autoloader
require_once dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php';

// WordPress 測試環境路徑
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// 如果找不到測試環境，嘗試常見位置
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    echo "找不到 WordPress 測試環境\n";
    echo "請確認已執行以下命令:\n";
    echo "  bash bin/install-wp-tests.sh wordpress_test root root localhost latest true\n";
    echo "  svn export --ignore-externals https://develop.svn.wordpress.org/tags/6.9/tests/phpunit/includes/ /tmp/wordpress-tests-lib/includes\n";
    echo "  svn export --ignore-externals https://develop.svn.wordpress.org/tags/6.9/tests/phpunit/data/ /tmp/wordpress-tests-lib/data\n";
    echo "\n當前搜尋路徑: $_tests_dir\n";
    exit( 1 );
}

// 載入 WordPress 測試環境
require_once $_tests_dir . '/includes/functions.php';

/**
 * 手動載入外掛
 */
function _manually_load_plugin() {
    require dirname( dirname( __FILE__ ) ) . '/buygo-plus-one.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// 啟動 WordPress 測試環境
require $_tests_dir . '/includes/bootstrap.php';

// 啟動外掛
\BuyGoPlus\Plugin::instance()->init();
