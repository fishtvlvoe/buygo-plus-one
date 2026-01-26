<?php
/**
 * PHPUnit Bootstrap File - 單元測試版本
 *
 * 不依賴 WordPress 環境，只測試純邏輯
 */

// Composer autoloader
require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

// 定義外掛常數（方便測試引用）
if (!defined('BUYGO_PLUS_ONE_VERSION')) {
    define('BUYGO_PLUS_ONE_VERSION', '0.0.1');
}

if (!defined('BUYGO_PLUS_ONE_PLUGIN_DIR')) {
    define('BUYGO_PLUS_ONE_PLUGIN_DIR', dirname(dirname(__FILE__)) . '/');
}

echo "PHPUnit 單元測試環境已載入\n";
