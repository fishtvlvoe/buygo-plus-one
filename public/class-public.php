<?php
namespace BuyGoPlus\PublicSide;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 前台功能類別
 *
 * 統一管理前台樣式、腳本、功能註冊
 * 目前預留為空，未來可擴充前台功能
 *
 * @package    BuyGoPlus
 * @subpackage BuyGoPlus/public
 */
class PublicSide {
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
     * 初始化 Public 類別
     *
     * @param string $plugin_name 外掛名稱
     * @param string $version     外掛版本
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * 載入前台樣式（預留）
     */
    public function enqueue_styles() {
        // 未來可在此載入前台樣式
    }

    /**
     * 載入前台腳本（預留）
     */
    public function enqueue_scripts() {
        // 未來可在此載入前台腳本
    }
}
