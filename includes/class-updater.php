<?php

namespace BuyGoPlus;

/**
 * 外掛自動更新器
 *
 * 透過 GitHub Releases API 檢查並安裝外掛更新。
 */
final class Updater
{
    /**
     * 更新伺服器 URL（GitHub Releases API）
     */
    private const UPDATE_SERVER_URL = 'https://api.github.com/repos/fishtvlvoe/buygo-plus-one/releases/latest';

    /**
     * 外掛 slug（用於識別外掛）
     */
    private const PLUGIN_SLUG = 'buygo-plus-one';

    /**
     * 外掛檔案路徑（相對於 wp-content/plugins/）
     */
    private string $plugin_file;

    /**
     * 目前版本
     */
    private string $current_version;

    /**
     * 初始化更新器
     */
    public function __construct(string $plugin_file, string $current_version)
    {
        $this->plugin_file = $plugin_file;
        $this->current_version = $current_version;
    }

    /**
     * 註冊更新檢查 hooks
     */
    public function init(): void
    {
        // 檢查更新資訊
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update'], 10, 1);

        // 提供外掛資訊（更新說明、下載 URL 等）
        add_filter('plugins_api', [$this, 'get_plugin_info'], 20, 3);

        // 在更新前驗證下載 URL（可選，增加安全性）
        add_filter('upgrader_pre_download', [$this, 'validate_download_url'], 10, 3);

        // 啟用自動更新按鈕（WordPress 5.5+）
        add_filter('auto_update_plugin', [$this, 'enable_auto_update'], 10, 2);
    }

    /**
     * 啟用外掛自動更新功能
     *
     * 讓 WordPress 在外掛列表頁面顯示「啟用自動更新」按鈕
     *
     * @param bool|null $update 是否啟用自動更新（null 表示由使用者決定）
     * @param object $item 外掛資訊物件
     * @return bool|null
     */
    public function enable_auto_update($update, $item)
    {
        $plugin_basename = plugin_basename($this->plugin_file);

        // 只處理我們自己的外掛
        if (!isset($item->plugin) || $item->plugin !== $plugin_basename) {
            return $update;
        }

        // 如果使用者已經設定過自動更新，尊重使用者的選擇
        // 如果為 null，表示允許使用者透過 UI 控制
        return $update;
    }

    /**
     * 檢查是否有更新
     *
     * @param object $transient WordPress 的更新 transient
     * @return object
     */
    public function check_update($transient)
    {
        // 只在檢查外掛更新時執行
        if (empty($transient->checked)) {
            return $transient;
        }

        $update_info = $this->fetch_update_info();

        if (!$update_info || !$this->is_newer_version($update_info['version'])) {
            return $transient;
        }

        // 建立更新物件
        $plugin_basename = plugin_basename($this->plugin_file);

        $transient->response[$plugin_basename] = (object) [
            'slug' => self::PLUGIN_SLUG,
            'plugin' => $plugin_basename,
            'new_version' => $update_info['version'],
            'url' => $update_info['homepage'] ?? '',
            'package' => $update_info['download_url'],
            'tested' => $update_info['tested'] ?? '',
            'requires_php' => $update_info['requires_php'] ?? '',
        ];

        return $transient;
    }

    /**
     * 提供外掛詳細資訊（點擊「查看版本資訊」時顯示）
     *
     * @param false|object|array $result 預設結果
     * @param string $action 動作（plugin_information）
     * @param object $args 查詢參數
     * @return false|object|array
     */
    public function get_plugin_info($result, $action, $args)
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== self::PLUGIN_SLUG) {
            return $result;
        }

        $update_info = $this->fetch_update_info();

        if (!$update_info) {
            return $result;
        }

        return (object) [
            'name' => $update_info['name'] ?? 'BuyGo+1',
            'slug' => self::PLUGIN_SLUG,
            'version' => $update_info['version'],
            'author' => $update_info['author'] ?? 'BuyGo Team',
            'author_profile' => $update_info['author_url'] ?? '',
            'requires' => $update_info['requires'] ?? '5.8',
            'tested' => $update_info['tested'] ?? '',
            'requires_php' => $update_info['requires_php'] ?? '7.4',
            'download_link' => $update_info['download_url'],
            'trunk' => $update_info['download_url'],
            'last_updated' => $update_info['last_updated'] ?? '',
            'sections' => [
                'description' => $update_info['description'] ?? '',
                'changelog' => $update_info['changelog'] ?? '',
            ],
        ];
    }

    /**
     * 驗證下載 URL（可選，增加安全性）
     *
     * @param bool|WP_Error $reply 是否允許下載
     * @param string $package 下載 URL
     * @param WP_Upgrader $upgrader 更新器物件
     * @return bool|WP_Error
     */
    public function validate_download_url($reply, $package, $upgrader)
    {
        // 只驗證我們自己的外掛
        if (!isset($upgrader->skin->plugin) || strpos($upgrader->skin->plugin, self::PLUGIN_SLUG) === false) {
            return $reply;
        }

        // 檢查 URL 是否來自允許的網域
        $allowed_domains = [
            'github.com',
            'githubusercontent.com',
            // 你可以在這裡加入其他允許的網域
        ];

        $parsed = wp_parse_url($package);
        $host = $parsed['host'] ?? '';

        foreach ($allowed_domains as $domain) {
            if (strpos($host, $domain) !== false) {
                return $reply;
            }
        }

        // 如果不在允許清單中，可以選擇拒絕或允許
        // 這裡選擇允許（因為可能是自訂伺服器）
        return $reply;
    }

    /**
     * 從更新伺服器取得更新資訊
     *
     * @return array|null 更新資訊，失敗時返回 null
     */
    private function fetch_update_info(): ?array
    {
        // 使用 transient 快取，避免每次頁面載入都請求 API
        $cache_key = 'buygo_plus_one_update_info';
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // 使用 GitHub API
        $info = $this->fetch_from_github();

        if ($info) {
            // 快取 12 小時
            set_transient($cache_key, $info, 12 * HOUR_IN_SECONDS);
        }

        return $info;
    }

    /**
     * 從 GitHub Releases 取得更新資訊
     *
     * @return array|null
     */
    private function fetch_from_github(): ?array
    {
        $response = wp_remote_get(self::UPDATE_SERVER_URL, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('[buygo-plus-one] Update check failed: ' . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['tag_name'])) {
            return null;
        }

        // 移除版本號前面的 'v'（如果有的話）
        $version = ltrim($data['tag_name'], 'v');

        // 尋找 zip 下載連結
        $download_url = '';
        if (isset($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (isset($asset['browser_download_url']) && strpos($asset['name'], '.zip') !== false) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }

        // 如果沒有找到 zip，嘗試從 tag 建立下載 URL
        if (!$download_url) {
            // GitHub 的 zip 下載格式：https://github.com/{owner}/{repo}/archive/refs/tags/{tag}.zip
            $repo_url = $data['html_url'] ?? '';
            if (preg_match('#github\.com/([^/]+)/([^/]+)#', $repo_url, $matches)) {
                $owner = $matches[1];
                $repo = $matches[2];
                $tag = $data['tag_name'];
                $download_url = "https://github.com/{$owner}/{$repo}/archive/refs/tags/{$tag}.zip";
            }
        }

        return [
            'version' => $version,
            'download_url' => $download_url,
            'homepage' => $data['html_url'] ?? '',
            'name' => $data['name'] ?? 'BuyGo+1',
            'description' => $this->extract_description($data['body'] ?? ''),
            'changelog' => $this->format_changelog($data['body'] ?? ''),
            'author' => 'BuyGo Team',
            'last_updated' => $data['published_at'] ?? '',
            'tested' => '6.5', // 可以從 README 或 API 取得
            'requires' => '5.8',
            'requires_php' => '7.4',
        ];
    }

    /**
     * 檢查新版本是否比目前版本新
     *
     * @param string $new_version 新版本號
     * @return bool
     */
    private function is_newer_version(string $new_version): bool
    {
        return version_compare($new_version, $this->current_version, '>');
    }

    /**
     * 從 GitHub release body 提取描述
     *
     * @param string $body Release body
     * @return string
     */
    private function extract_description(string $body): string
    {
        // 取前 500 字元作為描述
        $desc = wp_strip_all_tags($body);
        return mb_substr($desc, 0, 500);
    }

    /**
     * 格式化 changelog
     *
     * @param string $body Release body
     * @return string
     */
    private function format_changelog(string $body): string
    {
        // 如果 body 是 Markdown，轉換成 HTML
        if (function_exists('wpautop')) {
            return wpautop($body);
        }

        return nl2br(esc_html($body));
    }
}
