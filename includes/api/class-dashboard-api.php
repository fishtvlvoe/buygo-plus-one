<?php
namespace BuyGoPlus\Api;

use BuyGoPlus\Services\DashboardService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard_API - Dashboard REST API 端點
 *
 * 提供 4 個 REST API endpoints:
 * - GET /dashboard/stats - 總覽統計
 * - GET /dashboard/revenue - 營收趨勢
 * - GET /dashboard/products - 商品概覽
 * - GET /dashboard/activities - 最近活動
 *
 * @package BuyGoPlus\Api
 * @since 0.0.1
 */
class Dashboard_API {

    /**
     * REST API 命名空間
     *
     * @var string
     */
    private $namespace = 'buygo-plus-one/v1';

    /**
     * DashboardService 實例
     *
     * @var DashboardService
     */
    private $dashboardService;

    /**
     * 建構函數
     *
     * 初始化 DashboardService
     */
    public function __construct() {
        $this->dashboardService = new DashboardService();
    }

    /**
     * 註冊 REST API 路由
     *
     * 註冊 4 個端點，每個端點都有權限檢查和參數驗證
     *
     * @return void
     */
    public function register_routes() {
        // GET /dashboard/stats - 總覽統計
        register_rest_route($this->namespace, '/dashboard/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats'],
            'permission_callback' => [API::class, 'check_permission']
        ]);

        // GET /dashboard/revenue - 營收趨勢
        register_rest_route($this->namespace, '/dashboard/revenue', [
            'methods' => 'GET',
            'callback' => [$this, 'get_revenue'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'period' => [
                    'default' => 30,
                    'sanitize_callback' => 'absint'
                ],
                'currency' => [
                    'default' => 'TWD',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        // GET /dashboard/products - 商品概覽
        register_rest_route($this->namespace, '/dashboard/products', [
            'methods' => 'GET',
            'callback' => [$this, 'get_products'],
            'permission_callback' => [API::class, 'check_permission']
        ]);

        // GET /dashboard/activities - 最近活動
        register_rest_route($this->namespace, '/dashboard/activities', [
            'methods' => 'GET',
            'callback' => [$this, 'get_activities'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'limit' => [
                    'default' => 10,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
    }

    /**
     * 取得總覽統計
     *
     * 回傳 4 個統計數據:
     * - total_revenue: 總營收
     * - total_orders: 訂單數
     * - total_customers: 客戶數
     * - avg_order_value: 平均訂單金額
     *
     * 使用快取機制（5 分鐘）
     *
     * @param \WP_REST_Request $request REST API 請求
     * @return \WP_REST_Response
     */
    public function get_stats($request) {
        try {
            // 定義快取鍵
            $cache_key = 'buygo_dashboard_stats';

            // 嘗試從 transient 讀取快取
            $cached = get_transient($cache_key);

            if ($cached !== false) {
                // 快取存在，回傳快取資料
                $cached_time = get_transient($cache_key . '_time');

                return new \WP_REST_Response([
                    'success' => true,
                    'data' => $cached,
                    'cached_at' => $cached_time ?: current_time('mysql')
                ], 200);
            }

            // 快取不存在，調用 DashboardService 計算統計
            $stats = $this->dashboardService->calculateStats();

            // 快取 5 分鐘
            set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);
            set_transient($cache_key . '_time', current_time('mysql'), 5 * MINUTE_IN_SECONDS);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $stats,
                'cached_at' => current_time('mysql')
            ], 200);

        } catch (\Exception $e) {
            error_log('BuyGo Dashboard API Error (get_stats): ' . $e->getMessage());

            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得統計資料失敗：' . $e->getMessage()
            ], 500);
        }
    }
}
