<?php
namespace BuyGoPlus\Api;

use BuyGoPlus\Services\DashboardService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard_API - Dashboard REST API 端點
 *
 * 提供 5 個 REST API endpoints:
 * - GET /dashboard/stats - 總覽統計
 * - GET /dashboard/revenue - 營收趨勢
 * - GET /dashboard/products - 商品概覽
 * - GET /dashboard/activities - 最近活動
 * - GET /dashboard/profit - 利潤統計
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
     * 註冊 5 個端點，每個端點都有權限檢查和參數驗證
     *
     * @return void
     */
    public function register_routes() {
        // GET /dashboard/stats - 總覽統計（全幣別，前端做換算）
        register_rest_route($this->namespace, '/dashboard/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats'],
            'permission_callback' => [API::class, 'check_permission']
        ]);

        // GET /dashboard/revenue - 營收趨勢（全幣別，前端做換算）
        register_rest_route($this->namespace, '/dashboard/revenue', [
            'methods' => 'GET',
            'callback' => [$this, 'get_revenue'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'period' => [
                    'default' => 30,
                    'sanitize_callback' => 'absint'
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

        // GET /dashboard/profit - 利潤統計
        register_rest_route($this->namespace, '/dashboard/profit', [
            'methods' => 'GET',
            'callback' => [$this, 'get_profit'],
            'permission_callback' => [API::class, 'check_permission']
        ]);
    }

    /**
     * 取得總覽統計
     *
     * 回傳各幣別的統計數據，由前端做匯率換算:
     * - by_currency: 各幣別的營收、訂單數、客戶數
     * - total_orders: 總訂單數（不分幣別）
     * - total_customers: 總客戶數（不分幣別）
     *
     * 使用快取機制（5 分鐘）
     *
     * @param \WP_REST_Request $request REST API 請求
     * @return \WP_REST_Response
     */
    public function get_stats($request) {
        try {
            // 定義快取鍵（與 DashboardCacheManager 保持一致）
            $cache_key = "buygo_dashboard_stats";

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

            // 快取不存在，調用 DashboardService 計算統計（全幣別）
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

    /**
     * 取得營收趨勢
     *
     * 回傳各幣別的每日營收資料，由前端做匯率換算
     *
     * 使用快取機制（15 分鐘）
     *
     * @param \WP_REST_Request $request REST API 請求
     * @return \WP_REST_Response
     */
    public function get_revenue($request) {
        try {
            // 取得參數
            $period = $request->get_param('period') ?? 30;

            // 定義快取鍵（全幣別，不含幣別參數）
            $cache_key = "buygo_dashboard_revenue_{$period}_all";

            // 嘗試從 transient 讀取快取
            $cached = get_transient($cache_key);

            if ($cached !== false) {
                return new \WP_REST_Response([
                    'success' => true,
                    'data' => $cached
                ], 200);
            }

            // 快取不存在，調用 DashboardService 取得營收趨勢（全幣別）
            $revenue_data = $this->dashboardService->getRevenueTrend($period);

            // 快取 15 分鐘
            set_transient($cache_key, $revenue_data, 15 * MINUTE_IN_SECONDS);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $revenue_data
            ], 200);

        } catch (\Exception $e) {
            error_log('BuyGo Dashboard API Error (get_revenue): ' . $e->getMessage());

            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得營收趨勢失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取得商品概覽
     *
     * 回傳 Top 5 暢銷商品資料
     *
     * 使用快取機制（15 分鐘）
     *
     * @param \WP_REST_Request $request REST API 請求
     * @return \WP_REST_Response
     */
    public function get_products($request) {
        try {
            // 定義快取鍵
            $cache_key = 'buygo_dashboard_products';

            // 嘗試從 transient 讀取快取
            $cached = get_transient($cache_key);

            if ($cached !== false) {
                return new \WP_REST_Response([
                    'success' => true,
                    'data' => $cached
                ], 200);
            }

            // 快取不存在，調用 DashboardService 取得商品概覽
            $products_data = $this->dashboardService->getProductOverview();

            // 快取 15 分鐘
            set_transient($cache_key, $products_data, 15 * MINUTE_IN_SECONDS);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $products_data
            ], 200);

        } catch (\Exception $e) {
            error_log('BuyGo Dashboard API Error (get_products): ' . $e->getMessage());

            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得商品概覽失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取得最近活動
     *
     * 回傳最近的訂單和客戶活動記錄
     *
     * 使用快取機制（1 分鐘，活動需要即時性）
     *
     * @param \WP_REST_Request $request REST API 請求
     * @return \WP_REST_Response
     */
    public function get_activities($request) {
        try {
            // 取得參數
            $limit = $request->get_param('limit') ?? 10;

            // 定義快取鍵（包含參數）
            $cache_key = "buygo_dashboard_activities_{$limit}";

            // 嘗試從 transient 讀取快取
            $cached = get_transient($cache_key);

            if ($cached !== false) {
                return new \WP_REST_Response([
                    'success' => true,
                    'data' => $cached
                ], 200);
            }

            // 快取不存在，調用 DashboardService 取得最近活動
            $activities_data = $this->dashboardService->getRecentActivities($limit);

            // 快取 1 分鐘（活動需要較高即時性）
            set_transient($cache_key, $activities_data, 1 * MINUTE_IN_SECONDS);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $activities_data
            ], 200);

        } catch (\Exception $e) {
            error_log('BuyGo Dashboard API Error (get_activities): ' . $e->getMessage());

            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得最近活動失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取得利潤統計
     *
     * 回傳已完成訂單的利潤統計資料：
     * - total_profit: 總利潤（分為單位）
     * - avg_profit_margin: 平均利潤率（%）
     * - top_products: Top 5 利潤商品
     * - by_currency: 按幣別分組的利潤
     *
     * 使用快取機制（15 分鐘）
     *
     * @param \WP_REST_Request $request REST API 請求
     * @return \WP_REST_Response
     */
    public function get_profit($request) {
        try {
            // 定義快取鍵
            $cache_key = 'buygo_dashboard_profit';

            // 嘗試從 transient 讀取快取
            $cached = get_transient($cache_key);

            if ($cached !== false) {
                $cached_time = get_transient($cache_key . '_time');

                return new \WP_REST_Response([
                    'success' => true,
                    'data' => $cached,
                    'cached_at' => $cached_time ?: current_time('mysql')
                ], 200);
            }

            // 快取不存在，調用 DashboardService 計算利潤統計
            $profit_data = $this->dashboardService->calculateProfitStats();

            // 快取 15 分鐘（與營收趨勢一致）
            set_transient($cache_key, $profit_data, 15 * MINUTE_IN_SECONDS);
            set_transient($cache_key . '_time', current_time('mysql'), 15 * MINUTE_IN_SECONDS);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $profit_data,
                'cached_at' => current_time('mysql')
            ], 200);

        } catch (\Exception $e) {
            error_log('BuyGo Dashboard API Error (get_profit): ' . $e->getMessage());

            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得利潤統計失敗：' . $e->getMessage()
            ], 500);
        }
    }
}
