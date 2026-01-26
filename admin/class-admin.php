<?php
namespace BuyGoPlus\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 後台管理功能類別
 *
 * 統一管理後台樣式、腳本、功能註冊
 * 遵循 WordPress Plugin Boilerplate 標準
 *
 * @package    BuyGoPlus
 * @subpackage BuyGoPlus/admin
 */
class Admin {
    /**
     * 外掛名稱
     *
     * @var string
     */
    private $plugin_name;

    /**
     * 外掛版本
     *
     * @var string
     */
    private $version;

    /**
     * 初始化 Admin 類別
     *
     * @param string $plugin_name 外掛名稱
     * @param string $version     外掛版本
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * 載入後台樣式
     */
    public function enqueue_styles() {
        // 只在外掛頁面載入樣式
        if (!$this->is_plugin_page()) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name,
            BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/css/admin-settings.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * 載入後台腳本
     */
    public function enqueue_scripts() {
        // 只在外掛頁面載入腳本
        if (!$this->is_plugin_page()) {
            return;
        }

        // 載入 DesignSystem.js（設計系統）
        wp_enqueue_script(
            $this->plugin_name . '-design-system',
            BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/js/DesignSystem.js',
            array('jquery'),
            $this->version,
            false
        );

        // 載入 RouterMixin.js（路由混入）
        wp_enqueue_script(
            $this->plugin_name . '-router-mixin',
            BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/js/RouterMixin.js',
            array('jquery', $this->plugin_name . '-design-system'),
            $this->version,
            false
        );

        // 載入 admin-settings.js（後台設定主腳本）
        wp_enqueue_script(
            $this->plugin_name . '-admin',
            BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/js/admin-settings.js',
            array('jquery', $this->plugin_name . '-design-system', $this->plugin_name . '-router-mixin'),
            $this->version,
            false
        );
    }

    /**
     * 檢查是否為外掛頁面
     *
     * @return bool
     */
    private function is_plugin_page() {
        // 檢查是否在 BuyGo Portal 頁面
        global $wp_query;
        $buygo_page = get_query_var('buygo_page');

        return !empty($buygo_page);
    }
}
