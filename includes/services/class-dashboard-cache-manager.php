<?php

namespace BuyGoPlus\Services;

/**
 * Dashboard Cache Manager - Dashboard 快取管理服務
 *
 * 提供 Dashboard 快取的主動失效機制：
 * - 訂單建立/狀態變更時清除相關快取
 * - 確保統計數據即時性
 *
 * @package BuyGoPlus\Services
 * @version 1.0.0
 * @since B21-05 技術債修復
 */
class DashboardCacheManager
{
    /**
     * Dashboard 快取鍵前綴清單
     *
     * @var array
     */
    private static $cache_keys = [
        'buygo_dashboard_stats',
        'buygo_dashboard_revenue',
        'buygo_dashboard_products',
        'buygo_dashboard_activities'
    ];

    /**
     * 初始化服務並註冊 hooks
     *
     * @return void
     */
    public static function init(): void
    {
        // FluentCart 訂單建立
        add_action('fluent_cart/order_created', [self::class, 'on_order_change'], 5);

        // FluentCart 訂單狀態變更
        add_action('fluent_cart/order_status_changed', [self::class, 'on_order_change'], 5);

        // FluentCart 付款狀態變更
        add_action('fluent_cart/payment_status_changed', [self::class, 'on_order_change'], 5);

        // FluentCart 出貨狀態變更
        add_action('fluent_cart/shipping_status_changed', [self::class, 'on_order_change'], 5);

        // BuyGo 內部出貨狀態變更
        add_action('buygo_shipping_status_changed', [self::class, 'on_order_change'], 5);

        // BuyGo 訂單出貨
        add_action('buygo_order_shipped', [self::class, 'on_order_change'], 5);

        // BuyGo 訂單完成
        add_action('buygo_order_completed', [self::class, 'on_order_change'], 5);
    }

    /**
     * 訂單變更時清除快取
     *
     * @param mixed $order_data 訂單資料（可能是 ID 或陣列）
     * @return void
     */
    public static function on_order_change($order_data = null): void
    {
        self::clear_dashboard_cache();
    }

    /**
     * 清除所有 Dashboard 快取
     *
     * @return int 清除的快取數量
     */
    public static function clear_dashboard_cache(): int
    {
        $cleared = 0;

        foreach (self::$cache_keys as $key) {
            // 直接清除
            if (delete_transient($key)) {
                $cleared++;
            }
            // 清除時間戳記
            delete_transient($key . '_time');

            // 清除帶參數的變體（營收趨勢和活動）
            self::clear_parameterized_cache($key);
        }

        // 記錄清除動作
        if (defined('WP_DEBUG') && WP_DEBUG && $cleared > 0) {
            error_log("[BuyGo DashboardCacheManager] Cleared {$cleared} cache entries");
        }

        return $cleared;
    }

    /**
     * 清除帶參數的快取變體
     *
     * @param string $base_key 基礎快取鍵
     * @return void
     */
    private static function clear_parameterized_cache(string $base_key): void
    {
        global $wpdb;

        // 刪除所有以此 key 開頭的 transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s
             OR option_name LIKE %s",
            '_transient_' . $base_key . '%',
            '_transient_timeout_' . $base_key . '%'
        ));
    }

    /**
     * 手動清除指定快取
     *
     * @param string $cache_type 快取類型 (stats, revenue, products, activities)
     * @return bool 是否成功
     */
    public static function clear_specific_cache(string $cache_type): bool
    {
        $key = 'buygo_dashboard_' . $cache_type;

        if (!in_array($key, self::$cache_keys)) {
            return false;
        }

        delete_transient($key);
        delete_transient($key . '_time');
        self::clear_parameterized_cache($key);

        return true;
    }

    /**
     * 取得快取狀態
     *
     * @return array 各快取鍵的狀態
     */
    public static function get_cache_status(): array
    {
        $status = [];

        foreach (self::$cache_keys as $key) {
            $cached = get_transient($key);
            $cached_time = get_transient($key . '_time');

            $status[$key] = [
                'exists' => $cached !== false,
                'cached_at' => $cached_time ?: null,
                'age_seconds' => $cached_time ? (time() - strtotime($cached_time)) : null
            ];
        }

        return $status;
    }
}
