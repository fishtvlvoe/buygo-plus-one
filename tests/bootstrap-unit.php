<?php
/**
 * PHPUnit Bootstrap for Unit Tests
 *
 * 這個 bootstrap 用於單元測試，不需要 WordPress 環境
 * 只測試純 PHP 邏輯
 */

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// 定義測試常數
define('ABSPATH', dirname(__DIR__) . '/');
define('WP_CONTENT_DIR', dirname(__DIR__) . '/');
define('BUYGO_PLUS_ONE_PLUGIN_DIR', dirname(__DIR__) . '/');
define('BUYGO_PLUS_ONE_PLUGIN_FILE', dirname(__DIR__) . '/buygo-plus-one.php');

// Mock WordPress functions that are commonly used
if (!function_exists('get_userdata')) {
    function get_userdata($user_id) {
        return null;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key = '', $single = false) {
        return $single ? '' : [];
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        if (file_exists($target)) {
            return @is_dir($target);
        }
        return @mkdir($target, 0755, true);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action($tag, ...$args) {
        // No-op in tests
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability, ...$args) {
        return false;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 0;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url, $protocols = null, $_context = 'display') {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return htmlspecialchars(strip_tags($str), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '', $scheme = null) {
        return 'http://test.local' . $path;
    }
}

if (!function_exists('site_url')) {
    function site_url($path = '', $scheme = null) {
        return 'http://test.local' . $path;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = 'admin.php', $scheme = 'admin') {
        return 'http://test.local/wp-admin/' . $path;
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir($time = null, $create_dir = true, $refresh_cache = false) {
        $base = WP_CONTENT_DIR . '/uploads';
        return [
            'path' => $base,
            'url' => 'http://test.local/wp-content/uploads',
            'subdir' => '',
            'basedir' => $base,
            'baseurl' => 'http://test.local/wp-content/uploads',
            'error' => false,
        ];
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        if ($type === 'mysql') {
            return gmdate('Y-m-d H:i:s');
        } elseif ($type === 'timestamp') {
            return time();
        }
        return gmdate($type);
    }
}

if (!function_exists('wp_date')) {
    function wp_date($format, $timestamp = null, $timezone = null) {
        if ($timestamp === null) {
            $timestamp = time();
        }
        return date($format, $timestamp);
    }
}

if (!function_exists('get_post')) {
    function get_post($post = null, $output = OBJECT, $filter = 'raw') {
        return null;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        return $single ? '' : [];
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '') {
        return true;
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $meta_key, $meta_value, $prev_value = '') {
        return true;
    }
}

if (!function_exists('delete_user_meta')) {
    function delete_user_meta($user_id, $meta_key, $meta_value = '') {
        return true;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        return true;
    }
}

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '', $force = false, &$found = null) {
        $found = false;
        return false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0) {
        return true;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') {
        return true;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        private $data;

        public function __construct($code = '', $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message() {
            return $this->message;
        }
    }
}

// Mock global $wpdb
if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public $prefix = 'wp_';
        public $insert_id = 0;
        public $last_error = '';

        public function prepare($query, ...$args) {
            return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
        }

        public function get_var($query) {
            return null;
        }

        public function get_row($query, $output = OBJECT) {
            return null;
        }

        public function get_results($query, $output = OBJECT) {
            return [];
        }

        public function insert($table, $data, $format = null) {
            $this->insert_id = 1;
            return 1;
        }

        public function update($table, $data, $where, $format = null, $where_format = null) {
            return 1;
        }

        public function delete($table, $where, $where_format = null) {
            return 1;
        }

        public function query($query) {
            return true;
        }
    };
}

// Define OBJECT constant for wpdb
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}

// 載入需要測試的類別
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-debug-service.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-settings-service.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-line-service.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-notification-templates.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-identity-service.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-notification-service.php';
