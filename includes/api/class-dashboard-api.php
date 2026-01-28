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
}
