<?php
namespace BuyGoPlus\Api;

if (!defined('ABSPATH')) {
    exit;
}

class API {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes() {
        // 載入所有 API 控制器
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-products-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-orders-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-shipments-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-customers-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-global-search-api.php';
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-dashboard-api.php';

        // 註冊商品 API
        $products_api = new Products_API();
        $products_api->register_routes();

        // 註冊訂單 API
        $orders_api = new Orders_API();
        $orders_api->register_routes();

        // 註冊出貨單 API
        $shipments_api = new Shipments_API();
        $shipments_api->register_routes();

        // 註冊客戶 API
        $customers_api = new Customers_API();
        $customers_api->register_routes();

        // 註冊全局搜索 API
        $global_search_api = new GlobalSearch_API();
        $global_search_api->register_routes();

        // 註冊 Dashboard API
        $dashboard_api = new Dashboard_API();
        $dashboard_api->register_routes();
    }
    
    /**
     * 統一權限檢查方法（所有 BuyGo 使用者）
     *
     * 允許以下角色存取 BuyGo+1 功能：
     * - WordPress 管理員
     * - BuyGo 管理員
     * - 小幫手
     *
     * @return bool
     */
    public static function check_permission(): bool
    {
        // 除錯日誌：同時寫入 WordPress error_log 和 BuyGo 日誌
        error_log('[BuyGo API] check_permission() called at ' . date('Y-m-d H:i:s'));

        $user_id = get_current_user_id();
        $is_logged_in = is_user_logged_in();
        $log_file = WP_CONTENT_DIR . '/buygo-plus-one.log';

        // 也寫入 WordPress debug.log
        error_log("[BuyGo API] User ID: $user_id, Logged In: " . ($is_logged_in ? 'YES' : 'NO'));

        $log_message = sprintf(
            "[%s] [PERMISSION] User ID: %d, Logged In: %s",
            date('Y-m-d H:i:s'),
            $user_id,
            $is_logged_in ? 'YES' : 'NO'
        );
        file_put_contents($log_file, $log_message . "\n", FILE_APPEND);

        if (!$is_logged_in) {
            file_put_contents($log_file, sprintf("[%s] [PERMISSION] DENIED - User not logged in\n", date('Y-m-d H:i:s')), FILE_APPEND);
            return false;
        }

        $can_manage = current_user_can('manage_options');
        $can_buygo_admin = current_user_can('buygo_admin');
        $can_buygo_helper = current_user_can('buygo_helper');

        $log_message = sprintf(
            "[%s] [PERMISSION] Capabilities - manage_options: %s, buygo_admin: %s, buygo_helper: %s",
            date('Y-m-d H:i:s'),
            $can_manage ? 'YES' : 'NO',
            $can_buygo_admin ? 'YES' : 'NO',
            $can_buygo_helper ? 'YES' : 'NO'
        );
        file_put_contents($log_file, $log_message . "\n", FILE_APPEND);

        $has_permission = $can_manage || $can_buygo_admin || $can_buygo_helper;
        file_put_contents($log_file, sprintf("[%s] [PERMISSION] %s\n", date('Y-m-d H:i:s'), $has_permission ? 'GRANTED' : 'DENIED'), FILE_APPEND);

        return $has_permission;
    }

    /**
     * 權限檢查（僅管理員可新增/刪除小幫手）
     *
     * @return bool
     */
    public static function check_admin_permission(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        return current_user_can('manage_options')      // WordPress 管理員
            || current_user_can('buygo_add_helper');   // 有新增小幫手權限的人
    }

    /**
     * 權限檢查（給 Products_API 使用）
     */
    public static function check_permission_for_api(): bool
    {
        return self::check_permission();
    }
}
