<?php
/**
 * Bootstrap for unit tests
 *
 * This bootstrap file is used by PHPUnit to set up the test environment
 * without requiring the full WordPress installation.
 */

// Prevent errors from different WordPress versions
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Get the path to vendor/autoload.php (in the plugin's own directory)
$composer_autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
} else {
    die('Unable to find Composer autoloader: ' . $composer_autoload);
}

// Define WordPress constants if not already defined (for unit tests)
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/../../');
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

// Define plugin constants
if (!defined('BUYGO_PLUGIN_DIR')) {
    define('BUYGO_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('BUYGO_PLUGIN_URL')) {
    define('BUYGO_PLUGIN_URL', 'http://example.local/wp-content/plugins/buygo-plus-one/');
}
