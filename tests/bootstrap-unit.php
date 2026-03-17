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
// ABSPATH 指向 tests/stubs/，讓 require ABSPATH . 'wp-admin/includes/upgrade.php' 載入 stub
define('ABSPATH', __DIR__ . '/stubs/');
define('WP_CONTENT_DIR', dirname(__DIR__) . '/');
define('BUYGO_PLUS_ONE_PLUGIN_DIR', dirname(__DIR__) . '/');
define('BUYGO_PLUS_ONE_PLUGIN_FILE', dirname(__DIR__) . '/buygo-plus-one.php');

// Mock WordPress functions that are commonly used
// 支援透過 $GLOBALS['mock_user_roles'] 控制 get_userdata 返回的角色
// 格式：[ user_id => ['role1', 'role2', ...], ... ]
$GLOBALS['mock_user_roles'] = [];

if (!function_exists('get_userdata')) {
    function get_userdata($user_id) {
        if (isset($GLOBALS['mock_user_roles'][$user_id])) {
            $obj = new stdClass();
            $obj->ID = $user_id;
            $obj->roles = $GLOBALS['mock_user_roles'][$user_id];
            $obj->display_name = 'User ' . $user_id;
            $obj->user_email = 'user' . $user_id . '@test.local';
            return $obj;
        }
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
// 支援透過 $GLOBALS['mock_helper_rows'] 控制小幫手查詢結果
// 格式：[ helper_user_id => ['helper_id' => X, 'seller_id' => Y, ...], ... ]
$GLOBALS['mock_helper_rows'] = [];

// 支援透過 $GLOBALS['mock_helpers_by_seller'] 控制 get_helpers 查詢結果
// 格式：[ seller_id => [ ['id' => X], ['id' => Y], ... ], ... ]
$GLOBALS['mock_helpers_by_seller'] = [];

if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public $prefix = 'wp_';
        public $insert_id = 0;
        public $last_error = '';

        public function prepare($query, ...$args) {
            return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
        }

        public function get_var($query) {
            // SHOW TABLES LIKE 'wp_buygo_helpers' → 始終假設表存在
            // 這讓 IdentityService::getHelperInfo 和 RolePermissionService::get_helpers
            // 都走資料表查詢路徑，由 mock_helper_rows / mock_helpers_by_seller 控制結果
            if (strpos($query, 'SHOW TABLES') !== false && strpos($query, 'buygo_helpers') !== false) {
                return $this->prefix . 'buygo_helpers';
            }
            // 其他 SHOW TABLES → 假設表不存在
            if (strpos($query, 'SHOW TABLES') !== false) {
                return null;
            }
            return null;
        }

        public function get_row($query, $output = OBJECT) {
            // IdentityService::getHelperInfo 查詢：SELECT * FROM wp_buygo_helpers WHERE helper_id = X
            if (strpos($query, 'buygo_helpers') !== false && preg_match("/helper_id\s*=\s*'?(\d+)'?/", $query, $m)) {
                $helper_id = (int) $m[1];
                $row = $GLOBALS['mock_helper_rows'][$helper_id] ?? null;
                if ($row !== null) {
                    if ($output === ARRAY_A) {
                        return $row;
                    }
                    return (object) $row;
                }
            }
            return null;
        }

        public function get_results($query, $output = OBJECT) {
            // RolePermissionService::get_helpers 查詢：SELECT ... FROM wp_buygo_helpers WHERE seller_id = X
            if (strpos($query, 'buygo_helpers') !== false && preg_match("/seller_id\s*=\s*'?(\d+)'?/", $query, $m)) {
                $seller_id = (int) $m[1];
                $rows = $GLOBALS['mock_helpers_by_seller'][$seller_id] ?? [];
                return array_map(function($r) { return (object) $r; }, $rows);
            }
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

        public function get_charset_collate() {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
    };
}

// Mock dbDelta（僅在測試環境下）
if (!function_exists('dbDelta')) {
    function dbDelta($queries = '', $execute = true) {
        return [];
    }
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

// Mock get_transient and set_transient for NotificationHandler tests
if (!function_exists('get_transient')) {
    // 全域快取儲存
    $GLOBALS['mock_transients'] = [];

    function get_transient($transient) {
        return isset($GLOBALS['mock_transients'][$transient]) ? $GLOBALS['mock_transients'][$transient] : false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        $GLOBALS['mock_transients'][$transient] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        unset($GLOBALS['mock_transients'][$transient]);
        return true;
    }
}

// Mock MINUTE_IN_SECONDS constant
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return htmlspecialchars(strip_tags($str), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post($postarr, $wp_error = false, $fire_after_hooks = true) {
        // 測試用：預設回傳假的 post ID
        return $GLOBALS['mock_wp_insert_post_return'] ?? 999;
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post($postarr = array(), $wp_error = false, $fire_after_hooks = true) {
        return $postarr['ID'] ?? 0;
    }
}

if (!function_exists('wp_delete_post')) {
    function wp_delete_post($postid = 0, $force_delete = false) {
        return true;
    }
}

if (!function_exists('set_post_thumbnail')) {
    function set_post_thumbnail($post, $thumbnail_id) {
        return true;
    }
}

if (!function_exists('wp_attachment_is_image')) {
    function wp_attachment_is_image($post = 0) {
        return false;
    }
}

if (!function_exists('delete_post_meta')) {
    function delete_post_meta($post_id, $meta_key, $meta_value = '') {
        return true;
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title($post = 0) {
        return '';
    }
}

if (!function_exists('get_post_thumbnail_id')) {
    function get_post_thumbnail_id($post = null) {
        return 0;
    }
}

if (!function_exists('wp_get_attachment_image_url')) {
    function wp_get_attachment_image_url($attachment_id, $size = 'thumbnail', $icon = false) {
        return '';
    }
}

if (!function_exists('get_avatar_url')) {
    function get_avatar_url($id_or_email, $args = null) {
        return 'https://www.gravatar.com/avatar/test';
    }
}

if (!function_exists('wp_get_current_user')) {
    // 返回一個空角色的假用戶，使 RolePermissionService::get_helpers 走 seller 路徑
    function wp_get_current_user() {
        $user = new stdClass();
        $user->ID = 0;
        $user->roles = [];
        return $user;
    }
}

// 載入需要測試的類別
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-debug-service.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-encryption-service.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-role-permission-service.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-line-settings-service.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-settings-service.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-line-service.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-notification-definitions.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-flex-message-builder.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-notification-templates.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-identity-service.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-notification-service.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-notification-handler.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-shipment-service.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-webhook-logger.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-fluentcart-service.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-product-limit-checker.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-product-stats-calculator.php';

// Mock FluentCart Eloquent Models（讓 ProductService 可被載入）
if (!class_exists('FluentCart\App\Models\ProductVariation')) {
    eval('namespace FluentCart\App\Models; class ProductVariation { public static function with($relations) { return new self(); } public static function find($id) { return null; } public function where($col, $val) { return $this; } public function whereIn($col, $vals) { return $this; } public function whereHas($rel, $cb = null) { return $this; } public function get() { return collect([]); } public $post_id; public $variation_title; public $product; }');
}
if (!class_exists('FluentCart\App\Models\Product')) {
    eval('namespace FluentCart\App\Models; class Product {}');
}
if (!class_exists('FluentCart\App\Models\OrderItem')) {
    eval('namespace FluentCart\App\Models; class OrderItem { public static function where($col, $val) { $i = new self(); $i->_wheres[] = [$col, $val]; return $i; } public static function whereIn($col, $vals) { $i = new self(); $i->_wheres[] = [$col, $vals]; return $i; } public function whereHas($rel, $cb = null) { return $this; } public function with($rels) { return $this; } public function get() { return collect([]); } public $_wheres = []; }');
}

// Mock Laravel collect() 函數
if (!function_exists('collect')) {
    function collect($items = []) {
        return new class($items) implements \IteratorAggregate, \Countable {
            private $items;
            public function __construct($items) { $this->items = $items; }
            public function getIterator(): \Traversable { return new \ArrayIterator($this->items); }
            public function count(): int { return count($this->items); }
            public function toArray() { return $this->items; }
        };
    }
}

require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-product-service.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-batch-create-service.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-line-product-creator.php';
require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-product-notification-handler.php';
