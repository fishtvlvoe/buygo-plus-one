<?php
namespace BuyGoPlus\Api;

use BuyGoPlus\Services\FeatureManagementService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Feature Management API - 功能管理 REST API
 *
 * 提供功能管理 Tab 的 REST API 端點：
 * - GET    /features          取得完整功能列表（Free/Pro + 開關狀態）
 * - GET    /features/toggles  取得 Pro 功能開關狀態
 * - POST   /features/toggles  儲存 Pro 功能開關狀態
 * - GET    /features/license  取得授權狀態
 * - POST   /features/license  儲存/啟用授權碼
 * - DELETE /features/license  停用/清除授權
 *
 * 所有端點僅限管理員（buygo_admin 或 manage_options）存取。
 *
 * @package BuyGoPlus\Api
 * @since 2.0.0
 */
class FeatureManagement_API
{
    private $namespace = 'buygo-plus-one/v1';

    /**
     * 註冊 REST API 路由
     */
    public function register_routes()
    {
        // GET /features - 取得完整功能列表
        register_rest_route($this->namespace, '/features', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_features'],
            'permission_callback' => [$this, 'check_permission_for_admin'],
        ]);

        // GET /features/toggles - 取得 Pro 功能開關狀態
        register_rest_route($this->namespace, '/features/toggles', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_toggles'],
            'permission_callback' => [$this, 'check_permission_for_admin'],
        ]);

        // POST /features/toggles - 儲存 Pro 功能開關狀態
        register_rest_route($this->namespace, '/features/toggles', [
            'methods'             => 'POST',
            'callback'            => [$this, 'save_toggles'],
            'permission_callback' => [$this, 'check_permission_for_admin'],
            'args'                => [
                'toggles' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_array($param);
                    },
                ],
            ],
        ]);

        // GET /features/license - 取得授權狀態
        register_rest_route($this->namespace, '/features/license', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_license'],
            'permission_callback' => [$this, 'check_permission_for_admin'],
        ]);

        // POST /features/license - 儲存/啟用授權碼
        register_rest_route($this->namespace, '/features/license', [
            'methods'             => 'POST',
            'callback'            => [$this, 'save_license'],
            'permission_callback' => [$this, 'check_permission_for_admin'],
            'args'                => [
                'key' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // DELETE /features/license - 停用/清除授權
        register_rest_route($this->namespace, '/features/license', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'deactivate_license'],
            'permission_callback' => [$this, 'check_permission_for_admin'],
        ]);
    }

    /**
     * 取得完整功能列表（Free/Pro + 開關狀態）
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_features($request)
    {
        try {
            $features = FeatureManagementService::get_features();

            return new \WP_REST_Response([
                'success' => true,
                'data'    => $features,
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得功能列表失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 取得 Pro 功能開關狀態
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_toggles($request)
    {
        try {
            $toggles = FeatureManagementService::get_feature_toggles();

            return new \WP_REST_Response([
                'success' => true,
                'data'    => $toggles,
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得功能開關失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 儲存 Pro 功能開關狀態
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function save_toggles($request)
    {
        try {
            $body    = json_decode($request->get_body(), true);
            $toggles = $body['toggles'] ?? null;

            if (!is_array($toggles)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'toggles 必須為陣列',
                ], 400);
            }

            FeatureManagementService::save_feature_toggles($toggles);

            return new \WP_REST_Response([
                'success' => true,
                'data'    => FeatureManagementService::get_feature_toggles(),
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '儲存功能開關失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 取得授權狀態
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_license($request)
    {
        try {
            $license = FeatureManagementService::get_license();

            return new \WP_REST_Response([
                'success' => true,
                'data'    => $license,
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得授權狀態失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 儲存/啟用授權碼
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function save_license($request)
    {
        try {
            $body = json_decode($request->get_body(), true);
            $key  = $body['key'] ?? '';

            $result = FeatureManagementService::save_license($key);

            return new \WP_REST_Response([
                'success' => true,
                'data'    => $result,
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '儲存授權碼失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 停用/清除授權
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function deactivate_license($request)
    {
        try {
            $result = FeatureManagementService::deactivate_license();

            return new \WP_REST_Response([
                'success' => true,
                'data'    => $result,
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '停用授權失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 權限檢查（僅管理員）
     *
     * @return bool
     */
    public function check_permission_for_admin()
    {
        if (!is_user_logged_in()) {
            return false;
        }
        return current_user_can('buygo_admin') || current_user_can('manage_options');
    }
}
