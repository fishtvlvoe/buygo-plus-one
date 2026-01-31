<?php

namespace BuyGoPlus\Api;

use BuyGoPlus\Services\SellerApplicationService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Seller Application API - 賣家申請 REST API
 *
 * Phase 27: 處理賣家申請、列表、升級等 API 端點
 *
 * @package BuyGoPlus\Api
 * @version 1.0.0
 */
class Seller_Application_API
{
    private $namespace = 'buygo-plus-one/v1';
    private $service;

    public function __construct()
    {
        $this->service = new SellerApplicationService();
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * 註冊 REST API 路由
     */
    public function register_routes()
    {
        // POST /seller-application - 提交申請
        register_rest_route($this->namespace, '/seller-application', [
            'methods' => 'POST',
            'callback' => [$this, 'submit_application'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);

        // GET /seller-application/status - 取得申請狀態
        register_rest_route($this->namespace, '/seller-application/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_application_status'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);

        // GET /seller-application/can-apply - 檢查是否可以申請
        register_rest_route($this->namespace, '/seller-application/can-apply', [
            'methods' => 'GET',
            'callback' => [$this, 'can_apply'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);

        // GET /admin/seller-applications - 取得所有申請列表（管理員）
        register_rest_route($this->namespace, '/admin/seller-applications', [
            'methods' => 'GET',
            'callback' => [$this, 'get_applications'],
            'permission_callback' => [$this, 'check_admin'],
        ]);

        // GET /admin/sellers - 取得所有賣家列表（管理員）
        register_rest_route($this->namespace, '/admin/sellers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_sellers'],
            'permission_callback' => [$this, 'check_admin'],
        ]);

        // POST /admin/seller-applications/{user_id}/approve - 批准申請（管理員）
        register_rest_route($this->namespace, '/admin/seller-applications/(?P<user_id>\d+)/approve', [
            'methods' => 'POST',
            'callback' => [$this, 'approve_application'],
            'permission_callback' => [$this, 'check_admin'],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);

        // POST /admin/seller-applications/{user_id}/reject - 拒絕申請（管理員）
        register_rest_route($this->namespace, '/admin/seller-applications/(?P<user_id>\d+)/reject', [
            'methods' => 'POST',
            'callback' => [$this, 'reject_application'],
            'permission_callback' => [$this, 'check_admin'],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);

        // POST /admin/sellers/{user_id}/upgrade - 升級賣家（管理員）
        register_rest_route($this->namespace, '/admin/sellers/(?P<user_id>\d+)/upgrade', [
            'methods' => 'POST',
            'callback' => [$this, 'upgrade_seller'],
            'permission_callback' => [$this, 'check_admin'],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
    }

    /**
     * 提交賣家申請
     */
    public function submit_application($request)
    {
        try {
            $body = json_decode($request->get_body(), true);

            $result = $this->service->submitApplication($body);

            if ($result['success']) {
                return new \WP_REST_Response([
                    'success' => true,
                    'message' => $result['message']
                ], 200);
            } else {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '申請失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取得申請狀態
     */
    public function get_application_status($request)
    {
        try {
            $user_id = get_current_user_id();
            $application = $this->service->getApplicationByUserId($user_id);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $application
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得狀態失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 檢查是否可以申請
     */
    public function can_apply($request)
    {
        try {
            $result = $this->service->canApply();

            return new \WP_REST_Response([
                'success' => true,
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '檢查失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取得所有申請列表（管理員）
     */
    public function get_applications($request)
    {
        try {
            $filters = [
                'status' => $request->get_param('status'),
                'seller_type' => $request->get_param('seller_type'),
            ];

            $applications = $this->service->getApplications(array_filter($filters));

            return new \WP_REST_Response([
                'success' => true,
                'data' => $applications
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得列表失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取得所有賣家列表（管理員）
     */
    public function get_sellers($request)
    {
        try {
            $filters = [
                'seller_type' => $request->get_param('seller_type'),
            ];

            $sellers = $this->service->getSellers(array_filter($filters));

            return new \WP_REST_Response([
                'success' => true,
                'data' => $sellers
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得列表失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 批准申請（管理員）
     */
    public function approve_application($request)
    {
        try {
            $user_id = (int)$request->get_param('user_id');
            $body = json_decode($request->get_body(), true);
            $seller_type = $body['seller_type'] ?? SellerApplicationService::SELLER_TYPE_TEST;

            $result = $this->service->approveApplication($user_id, $seller_type);

            if ($result['success']) {
                return new \WP_REST_Response([
                    'success' => true,
                    'message' => $result['message']
                ], 200);
            } else {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '批准失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 拒絕申請（管理員）
     */
    public function reject_application($request)
    {
        try {
            $user_id = (int)$request->get_param('user_id');
            $body = json_decode($request->get_body(), true);
            $reason = $body['reason'] ?? '';

            $result = $this->service->rejectApplication($user_id, $reason);

            if ($result['success']) {
                return new \WP_REST_Response([
                    'success' => true,
                    'message' => $result['message']
                ], 200);
            } else {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '拒絕失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 升級賣家（管理員）
     */
    public function upgrade_seller($request)
    {
        try {
            $user_id = (int)$request->get_param('user_id');

            $result = $this->service->upgradeSeller($user_id);

            if ($result['success']) {
                return new \WP_REST_Response([
                    'success' => true,
                    'message' => $result['message']
                ], 200);
            } else {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '升級失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 權限檢查：已登入
     */
    public function check_logged_in()
    {
        return is_user_logged_in();
    }

    /**
     * 權限檢查：管理員
     */
    public function check_admin()
    {
        return current_user_can('manage_options');
    }
}
