<?php
/**
 * Plugin Updater
 *
 * 使用 yahnis-elsts/plugin-update-checker 從 GitHub Releases 自動更新外掛
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if (!defined('ABSPATH')) {
    exit;
}

class Updater {
    /**
     * GitHub 倉庫 URL
     */
    private const GITHUB_REPO = 'https://github.com/fishtvlvoe/buygo-plus-one';

    /**
     * 更新檢查器實例
     */
    private $update_checker;

    /**
     * 外掛檔案路徑
     */
    private $plugin_file;

    /**
     * Constructor
     *
     * @param string $plugin_file 外掛主檔案的完整路徑
     */
    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->init_update_checker();
    }

    /**
     * 初始化更新檢查器
     */
    private function init_update_checker() {
        // 確保 Composer autoloader 已載入
        $autoload_path = dirname($this->plugin_file) . '/vendor/autoload.php';
        if (!file_exists($autoload_path)) {
            return; // 如果 vendor 目錄不存在，靜默失敗（不中斷外掛載入）
        }

        require_once $autoload_path;

        try {
            // 建立更新檢查器
            $this->update_checker = PucFactory::buildUpdateChecker(
                self::GITHUB_REPO,
                $this->plugin_file,
                'buygo-plus-one'
            );

            // 設定分支（從 main 分支檢查更新）
            $this->update_checker->setBranch('main');

            // 啟用 GitHub Releases
            $this->update_checker->getVcsApi()->enableReleaseAssets();

            // 設定檢查頻率（24 小時）
            $this->update_checker->checkForUpdates();

        } catch (\Exception $e) {
            // 錯誤處理：記錄錯誤但不中斷外掛載入
            if (function_exists('error_log')) {
                error_log('BuyGo+1 更新檢查器初始化失敗: ' . $e->getMessage());
            }
        }
    }

    /**
     * 手動觸發更新檢查（用於測試）
     */
    public function check_for_updates() {
        if ($this->update_checker) {
            return $this->update_checker->checkForUpdates();
        }
        return null;
    }
}
