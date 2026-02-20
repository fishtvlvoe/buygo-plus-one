<?php

namespace BuyGoPlus\Admin;

use BuyGoPlus\Services\SettingsService;
use BuyGoPlus\Services\NotificationTemplates;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Page - 管理後台設定頁面（路由/載入器）
 *
 * 熵減重構：此類別只負責 hooks 註冊和路由分派
 * Tab 渲染、AJAX 處理、表單處理全部拆分到獨立檔案
 *
 * Tab 檔案：includes/admin/tabs/
 * AJAX 檔案：includes/admin/ajax/
 */
class SettingsPage
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_buygo_test_line_connection', [$this, 'ajax_test_line_connection']);
        add_action('wp_ajax_buygo_update_seller_type', [$this, 'ajax_update_seller_type']);
        add_action('wp_ajax_buygo_update_product_limit', [$this, 'ajax_update_product_limit']);
    }

    /**
     * 添加管理選單
     *
     * v2.0: 合併為單一「BGO」選單，無子選單，6-Tab 單頁設計
     */
    public function add_admin_menu(): void
    {
        add_menu_page(
            'BGO',
            'BGO',
            'manage_options',
            'buygo-plus-one',
            [$this, 'render_page'],
            'dashicons-cart',
            30
        );
    }

    /**
     * 註冊設定
     */
    public function register_settings(): void
    {
        register_setting('buygo_settings', 'buygo_line_channel_access_token');
        register_setting('buygo_settings', 'buygo_line_channel_secret');
        register_setting('buygo_settings', 'buygo_line_liff_id');
    }

    /**
     * 載入腳本和樣式
     */
    public function enqueue_scripts($hook): void
    {
        if (strpos($hook, 'buygo-plus-one') === false) {
            return;
        }

        wp_enqueue_script(
            'buygo-settings-admin',
            BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/js/admin-settings.js',
            ['jquery'],
            '2.0.0',
            true
        );

        wp_enqueue_style(
            'buygo-settings-admin',
            BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/css/admin-settings.css',
            [],
            '2.0.0'
        );

        wp_enqueue_style(
            'bgo-admin-tabs',
            BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/css/admin-tabs.css',
            [],
            '2.0.0'
        );

        wp_localize_script('buygo-settings-admin', 'buygoSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('buygo-plus-one/v1'),
            'nonce' => wp_create_nonce('buygo-settings'),
            'restNonce' => wp_create_nonce('wp_rest')
        ]);

        // 阻擋 Cloudflare Beacon 以修復效能問題
        add_action('admin_print_footer_scripts', function() {
            ?>
            <script>
            // 阻擋 Cloudflare Web Analytics Beacon
            (function() {
                if (typeof window !== 'undefined') {
                    window.__cfBeacon = null;
                    window.cfjsloader = null;
                }
            })();
            </script>
            <?php
        }, 1);
    }

    // ──────────────────────────────────────────────
    // v2.0 統一頁面渲染（6-Tab 單頁設計）
    // ──────────────────────────────────────────────

    /**
     * 渲染 BGO 後台統一頁面
     *
     * v2.0: 合併原本的 render_settings_page() 和 render_templates_page()
     */
    public function render_page(): void
    {
        // 處理模板表單提交
        if (isset($_POST['submit_templates']) && isset($_POST['templates']) && wp_verify_nonce($_POST['_wpnonce'], 'buygo_settings')) {
            $this->handle_templates_submit();
        }

        // 處理設定表單提交
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'buygo_settings')) {
            $this->handle_form_submit();
        }

        // Tab 路由（POST 提交後保持在同一 Tab）
        $current_tab = sanitize_key($_GET['tab'] ?? 'roles');
        $tabs = [
            'roles'     => '角色權限',
            'templates' => 'LINE 模板',
            'checkout'  => '結帳設定',
            'data'      => '資料管理',
            'features'  => '功能管理',
            'developer' => '開發者',
        ];

        // 驗證 Tab 有效性
        if (!isset($tabs[$current_tab])) {
            $current_tab = 'roles';
        }

        ?>
        <div class="wrap">
            <h1>BGO</h1>
            <?php settings_errors('buygo_settings'); ?>

            <div class="bgo-tabs">
                <ul class="bgo-tabs-wrapper">
                    <?php foreach ($tabs as $tab_key => $tab_label): ?>
                        <li class="bgo-tab <?php echo $current_tab === $tab_key ? 'active' : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(['page' => 'buygo-plus-one', 'tab' => $tab_key], admin_url('admin.php'))); ?>">
                                <?php echo esc_html($tab_label); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="bgo-tab-content">
                <?php
                switch ($current_tab) {
                    case 'roles':
                        $this->render_roles_tab();
                        break;
                    case 'templates':
                        $this->render_templates_tab();
                        break;
                    case 'checkout':
                        $this->render_checkout_tab();
                        break;
                    case 'data':
                        echo '<div class="bgo-card"><p>資料管理功能將在後續版本中實作。</p></div>';
                        break;
                    case 'features':
                        echo '<div class="bgo-card"><p>功能管理將在後續版本中實作。</p></div>';
                        break;
                    case 'developer':
                        $this->render_workflow_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    // ──────────────────────────────────────────────
    // 舊頁面渲染（@deprecated v2.0，保留供相容）
    // ──────────────────────────────────────────────

    /**
     * @deprecated v2.0 — 已由 render_page() 取代
     */
    public function render_settings_page(): void
    {
        // 效能監控
        $start_time = microtime(true);
        error_log('[BuyGo Performance] Settings page load started');

        // 處理表單提交
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'buygo_settings')) {
            $this->handle_form_submit();
        }

        // 取得當前 Tab
        $current_tab = $_GET['tab'] ?? 'notifications';
        $tabs = [
            'notifications' => '通知記錄',
            'workflow' => '流程監控',
            'checkout' => '結帳設定',
            'roles' => '角色權限設定',
            'test-tools' => '測試工具',
            'debug-center' => '除錯中心'
        ];

        // 取得 LINE 設定
        $settings_start = microtime(true);
        $line_settings = SettingsService::get_line_settings();
        $settings_time = microtime(true) - $settings_start;
        error_log(sprintf('[BuyGo Performance] get_line_settings took %.4f seconds', $settings_time));

        ?>
        <div class="wrap">
            <h1>設定</h1>

            <!-- Tab 導航 -->
            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_key => $tab_label): ?>
                    <a href="?page=buygo-settings&tab=<?php echo esc_attr($tab_key); ?>"
                       class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Tab 內容 -->
            <div class="tab-content" style="margin-top: 20px;">
                <?php
                switch ($current_tab) {
                    case 'notifications':
                        $this->render_notifications_tab();
                        break;
                    case 'workflow':
                        $this->render_workflow_tab();
                        break;
                    case 'checkout':
                        $this->render_checkout_tab();
                        break;
                    case 'roles':
                        $this->render_roles_tab();
                        break;
                    case 'test-tools':
                        $this->render_test_tools_tab();
                        break;
                    case 'debug-center':
                        $this->render_debug_center_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
        // 記錄總載入時間
        $total_time = microtime(true) - $start_time;
        error_log(sprintf('[BuyGo Performance] Settings page total load time: %.4f seconds', $total_time));

        // 停用 Heartbeat API 以提升效能
        wp_deregister_script('heartbeat');
    }

    /**
     * 渲染 LINE 設定 Tab
     */
    private function render_line_tab($settings): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/tabs/line-tab.php';
    }

    /**
     * 渲染通知記錄 Tab
     */
    private function render_notifications_tab(): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/tabs/notifications-tab.php';
    }

    /**
     * 渲染結帳設定 Tab
     */
    private function render_checkout_tab(): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/tabs/checkout-tab.php';
    }

    /**
     * 渲染流程監控 Tab
     */
    private function render_workflow_tab(): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/tabs/workflow-tab.php';
    }

    /**
     * 渲染角色權限設定 Tab
     */
    private function render_roles_tab(): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/tabs/roles-tab.php';
    }

    /**
     * 渲染測試工具 Tab
     */
    private function render_test_tools_tab(): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/tabs/test-tools-tab.php';
    }

    /**
     * 渲染除錯中心 Tab
     */
    private function render_debug_center_tab(): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/tabs/debug-center-tab.php';
    }

    // ──────────────────────────────────────────────
    // 模板管理頁面
    // ──────────────────────────────────────────────

    /**
     * 渲染模板管理頁面（主選單頁面）
     */
    public function render_templates_page(): void
    {
        // 處理表單提交
        if (isset($_POST['submit_templates']) && isset($_POST['templates']) && wp_verify_nonce($_POST['_wpnonce'], 'buygo_settings')) {
            $this->handle_templates_submit();
        }

        ?>
        <div class="wrap">
            <h1>Line 模板</h1>
            <?php settings_errors('buygo_settings'); ?>
            <?php $this->render_templates_tab(); ?>
        </div>
        <?php
    }

    /**
     * 渲染訂單通知模板 Tab
     */
    private function render_templates_tab(): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/tabs/templates-tab.php';
    }

    // ──────────────────────────────────────────────
    // 表單處理（委派到 ajax/form-handlers.php）
    // ──────────────────────────────────────────────

    /**
     * 處理 LINE 設定表單提交
     */
    private function handle_form_submit(): void
    {
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/ajax/form-handlers.php';
        buygo_handle_form_submit();
    }

    /**
     * 處理模板編輯表單提交
     */
    private function handle_templates_submit(): void
    {
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/ajax/form-handlers.php';
        buygo_handle_templates_submit();
    }

    // ──────────────────────────────────────────────
    // AJAX 處理（委派到 ajax/ 目錄下的獨立檔案）
    // ──────────────────────────────────────────────

    /**
     * AJAX: 測試 LINE 連線
     */
    public function ajax_test_line_connection(): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/ajax/line-connection.php';
    }

    /**
     * AJAX: 更新賣家類型
     */
    public function ajax_update_seller_type(): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/ajax/seller-type.php';
    }

    /**
     * AJAX: 更新商品限制數量
     */
    public function ajax_update_product_limit(): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/ajax/product-limit.php';
    }

    /**
     * AJAX: 驗證賣家商品
     */
    public function ajax_validate_seller_product(): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/ajax/validate-seller-product.php';
    }

    /**
     * AJAX: 搜尋虛擬商品
     */
    public function ajax_search_virtual_products(): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/ajax/search-virtual-products.php';
    }

    // ──────────────────────────────────────────────
    // 測試工具輔助方法（由 test-tools-tab.php 透過 $this 呼叫）
    // ──────────────────────────────────────────────

    /**
     * 取得測試資料統計
     */
    private function get_test_data_stats(): array
    {
        global $wpdb;

        $stats = [
            'wp_products' => 0,
            'fct_products' => 0,
            'orders' => 0,
            'parent_orders' => 0,
            'child_orders' => 0,
            'order_items' => 0,
            'shipments' => 0,
            'shipment_items' => 0,
            'customers' => 0,
        ];

        $safe_count = function($table_name, $where = '') use ($wpdb) {
            $full_table_name = $wpdb->prefix . $table_name;
            $table_exists = $wpdb->get_var(
                $wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name)
            );
            if (!$table_exists) {
                return 0;
            }
            $query = "SELECT COUNT(*) FROM {$full_table_name}";
            if ($where) {
                $query .= " WHERE {$where}";
            }
            return (int) $wpdb->get_var($query);
        };

        $stats['wp_products'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'product'"
        );
        $stats['fct_products'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'fluent-products'"
        );
        $stats['orders'] = $safe_count('fct_orders');
        $stats['parent_orders'] = $safe_count('fct_orders', 'parent_id IS NULL');
        $stats['child_orders'] = $safe_count('fct_orders', "parent_id IS NOT NULL AND type = 'split'");
        $stats['order_items'] = $safe_count('fct_order_items');
        $stats['shipments'] = $safe_count('buygo_shipments');
        $stats['shipment_items'] = $safe_count('buygo_shipment_items');
        $stats['customers'] = $safe_count('fct_customers');

        return $stats;
    }

    /**
     * 執行清除測試資料
     */
    private function execute_reset_test_data(): array
    {
        global $wpdb;

        $table_exists = function($table_name) use ($wpdb) {
            $full_table_name = $wpdb->prefix . $table_name;
            return $wpdb->get_var(
                $wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name)
            ) === $full_table_name;
        };

        $safe_delete = function($table_name) use ($wpdb, $table_exists) {
            if ($table_exists($table_name)) {
                $wpdb->query("DELETE FROM {$wpdb->prefix}{$table_name}");
                return true;
            }
            return false;
        };

        try {
            $wpdb->query('START TRANSACTION');

            $safe_delete('buygo_shipment_items');
            $safe_delete('buygo_shipments');
            $safe_delete('fct_order_items');
            $safe_delete('fct_orders');
            $safe_delete('fct_product_variations');
            $safe_delete('fct_products');

            $product_ids = $wpdb->get_col(
                "SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'product'"
            );

            if (!empty($product_ids)) {
                $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}postmeta WHERE post_id IN ($placeholders)",
                        ...$product_ids
                    )
                );
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}term_relationships WHERE object_id IN ($placeholders)",
                        ...$product_ids
                    )
                );
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}posts WHERE ID IN ($placeholders)",
                        ...$product_ids
                    )
                );
            }

            $fluent_product_ids = $wpdb->get_col(
                "SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'fluent-products'"
            );

            if (!empty($fluent_product_ids)) {
                $placeholders = implode(',', array_fill(0, count($fluent_product_ids), '%d'));
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}postmeta WHERE post_id IN ($placeholders)",
                        ...$fluent_product_ids
                    )
                );
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}term_relationships WHERE object_id IN ($placeholders)",
                        ...$fluent_product_ids
                    )
                );
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}posts WHERE ID IN ($placeholders)",
                        ...$fluent_product_ids
                    )
                );
            }

            $wpdb->query('COMMIT');

            return [
                'success' => true,
                'message' => '所有測試資料已成功清除！現在可以從零開始測試。'
            ];

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');

            return [
                'success' => false,
                'message' => '清除失敗：' . $e->getMessage()
            ];
        }
    }
}
