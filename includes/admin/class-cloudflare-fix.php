<?php
/**
 * Cloudflare Performance Fix
 *
 * 移除 Cloudflare Web Analytics Beacon 以提升頁面載入速度
 *
 * @package BuyGoPlus
 */

namespace BuyGoPlus\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CloudflareFix
 *
 * 修復 Cloudflare Beacon 導致的效能問題
 */
class CloudflareFix
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // 移除 Cloudflare Beacon 腳本
        add_action('wp_head', [$this, 'block_cloudflare_beacon'], 1);
        add_action('admin_head', [$this, 'block_cloudflare_beacon'], 1);

        // 移除 Cloudflare 注入的腳本標籤
        add_filter('script_loader_tag', [$this, 'filter_cloudflare_scripts'], 10, 3);
    }

    /**
     * 在 head 中注入腳本以阻擋 Cloudflare Beacon
     */
    public function block_cloudflare_beacon(): void
    {
        ?>
        <script>
        // 阻擋 Cloudflare Web Analytics Beacon
        (function() {
            // 停用 Cloudflare Beacon
            if (typeof window !== 'undefined') {
                window.__cfBeacon = null;
                window.cfjsloader = null;
            }

            // 攔截對 cloudflareinsights.com 的請求
            if (window.PerformanceObserver) {
                const observer = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (entry.name && entry.name.includes('cloudflareinsights.com')) {
                            console.log('[BuyGo] Blocked Cloudflare Beacon:', entry.name);
                        }
                    }
                });

                try {
                    observer.observe({ entryTypes: ['resource'] });
                } catch (e) {
                    // Ignore errors
                }
            }
        })();
        </script>
        <?php
    }

    /**
     * 過濾腳本標籤，移除 Cloudflare 相關腳本
     *
     * @param string $tag    腳本標籤
     * @param string $handle 腳本句柄
     * @param string $src    腳本來源
     * @return string
     */
    public function filter_cloudflare_scripts(string $tag, string $handle, string $src): string
    {
        // 檢查是否為 Cloudflare Insights 腳本
        if (strpos($src, 'cloudflareinsights.com') !== false) {
            error_log('[BuyGo CloudflareFix] Blocked Cloudflare script: ' . $src);
            return '<!-- Cloudflare Beacon blocked by BuyGo+ -->';
        }

        return $tag;
    }
}
