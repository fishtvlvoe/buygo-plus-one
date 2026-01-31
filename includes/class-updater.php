<?php
/**
 * Plugin Updater
 *
 * 處理從 GitHub Releases 自動更新外掛
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus;

if (!defined('ABSPATH')) {
    exit;
}

class Updater {
    /**
     * GitHub 使用者名稱
     */
    private const GITHUB_USER = 'fishtvlvoe';

    /**
     * GitHub 倉庫名稱
     */
    private const GITHUB_REPO = 'buygo-plus-one';

    /**
     * 外掛檔案路徑
     */
    private $plugin_file;

    /**
     * 外掛 slug
     */
    private $plugin_slug;

    /**
     * 當前版本
     */
    private $current_version;

    /**
     * GitHub API URL
     */
    private $github_api_url;

    /**
     * 快取時間（秒）
     */
    private const CACHE_TIME = 43200; // 12 小時

    /**
     * Constructor
     */
    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->current_version = BUYGO_PLUS_ONE_VERSION;
        $this->github_api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            self::GITHUB_USER,
            self::GITHUB_REPO
        );

        $this->init_hooks();
    }

    /**
     * 初始化 WordPress hooks
     */
    private function init_hooks() {
        // 檢查更新
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);

        // 外掛資訊
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);

        // 更新後清除快取
        add_action('upgrader_process_complete', [$this, 'purge_cache'], 10, 2);
    }

    /**
     * 檢查更新
     */
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // 從快取或 API 獲取最新版本資訊
        $release_info = $this->get_release_info();

        if (!$release_info) {
            return $transient;
        }

        $latest_version = ltrim($release_info->tag_name, 'v');

        // 比較版本
        if (version_compare($this->current_version, $latest_version, '<')) {
            $plugin_data = [
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $latest_version,
                'url' => $release_info->html_url,
                'package' => $this->get_download_url($release_info),
                'tested' => '6.4',
                'requires_php' => '7.4',
            ];

            $transient->response[$this->plugin_slug] = (object) $plugin_data;
        }

        return $transient;
    }

    /**
     * 提供外掛資訊
     */
    public function plugin_info($false, $action, $args) {
        if ($action !== 'plugin_information') {
            return $false;
        }

        if (!isset($args->slug) || $args->slug !== dirname($this->plugin_slug)) {
            return $false;
        }

        $release_info = $this->get_release_info();

        if (!$release_info) {
            return $false;
        }

        $plugin_info = new \stdClass();
        $plugin_info->name = 'BuyGo+1';
        $plugin_info->slug = dirname($this->plugin_slug);
        $plugin_info->version = ltrim($release_info->tag_name, 'v');
        $plugin_info->author = '<a href="https://buygo.me">BuyGo Team</a>';
        $plugin_info->homepage = 'https://buygo.me';
        $plugin_info->requires = '5.8';
        $plugin_info->tested = '6.4';
        $plugin_info->requires_php = '7.4';
        $plugin_info->download_link = $this->get_download_url($release_info);
        $plugin_info->sections = [
            'description' => 'BuyGo 獨立賣場後台系統',
            'changelog' => $this->parse_changelog($release_info->body),
        ];

        return $plugin_info;
    }

    /**
     * 從 GitHub API 獲取最新版本資訊
     */
    private function get_release_info() {
        // 檢查快取
        $cache_key = 'buygo_plus_one_release_info';
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // 從 GitHub API 獲取
        $response = wp_remote_get($this->github_api_url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (!$data || !isset($data->tag_name)) {
            return false;
        }

        // 儲存到快取
        set_transient($cache_key, $data, self::CACHE_TIME);

        return $data;
    }

    /**
     * 獲取下載連結
     */
    private function get_download_url($release_info) {
        if (!isset($release_info->assets) || empty($release_info->assets)) {
            return false;
        }

        // 尋找 .zip 檔案
        foreach ($release_info->assets as $asset) {
            if (substr($asset->name, -4) === '.zip') {
                return $asset->browser_download_url;
            }
        }

        return false;
    }

    /**
     * 解析 changelog
     */
    private function parse_changelog($body) {
        if (empty($body)) {
            return '請查看 GitHub Release 頁面了解更新內容。';
        }

        return wpautop($body);
    }

    /**
     * 清除快取
     */
    public function purge_cache($upgrader, $options) {
        if ($options['action'] !== 'update' || $options['type'] !== 'plugin') {
            return;
        }

        if (!isset($options['plugins']) || !is_array($options['plugins'])) {
            return;
        }

        foreach ($options['plugins'] as $plugin) {
            if ($plugin === $this->plugin_slug) {
                delete_transient('buygo_plus_one_release_info');
                break;
            }
        }
    }
}
