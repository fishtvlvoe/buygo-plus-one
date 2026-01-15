<?php
/**
 * Plugin Name:       BuyGo+1
 * Plugin URI:        https://buygo.me
 * Description:       BuyGo 獨立賣場後台系統
 * Version:           0.0.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            BuyGo Team
 * Author URI:        https://buygo.me
 * License:           GPL v2 or later
 * Text Domain:       buygo-plus-one
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('BUYGO_PLUS_ONE_VERSION', '0.0.1');
define('BUYGO_PLUS_ONE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BUYGO_PLUS_ONE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BUYGO_PLUS_ONE_PLUGIN_FILE', __FILE__);

// Load plugin class
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/class-plugin.php';

// Initialize plugin
add_action('plugins_loaded', function() {
    \BuyGoPlus\Plugin::instance()->init();
}, 20); // Load after BuyGo (priority 20)
