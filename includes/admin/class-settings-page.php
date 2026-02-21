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
                        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/tabs/data-tab.php';
                        break;
                    case 'features':
                        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/tabs/features-tab.php';
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
    // Tab 渲染方法
    // ──────────────────────────────────────────────

    private function render_roles_tab(): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/tabs/roles-tab.php';
    }

    private function render_templates_tab(): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/tabs/templates-tab.php';
    }

    private function render_checkout_tab(): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/tabs/checkout-tab.php';
    }

    private function render_workflow_tab(): void
    {
        require BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/admin/tabs/workflow-tab.php';
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

}
