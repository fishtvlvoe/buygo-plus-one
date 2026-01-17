<?php
namespace BuyGoPlus\Api;

use BuyGoPlus\Services\SettingsService;

if (!defined('ABSPATH')) {
    exit;
}

class Settings_API {
    
    private $namespace = 'buygo-plus-one/v1';
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    /**
     * 註冊 REST API 路由
     */
    public function register_routes() {
        // GET /settings/templates - 取得模板設定
        register_rest_route($this->namespace, '/settings/templates', [
            'methods' => 'GET',
            'callback' => [$this, 'get_templates'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        // POST /settings/templates - 更新模板設定
        register_rest_route($this->namespace, '/settings/templates', [
            'methods' => 'POST',
            'callback' => [$this, 'update_templates'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        // GET /settings/helpers - 取得小幫手列表
        register_rest_route($this->namespace, '/settings/helpers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_helpers'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        // POST /settings/helpers - 新增小幫手
        register_rest_route($this->namespace, '/settings/helpers', [
            'methods' => 'POST',
            'callback' => [$this, 'add_helper'],
            'permission_callback' => [$this, 'check_permission_for_admin'],
        ]);
        
        // DELETE /settings/helpers/{user_id} - 移除小幫手
        register_rest_route($this->namespace, '/settings/helpers/(?P<user_id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'remove_helper'],
            'permission_callback' => [$this, 'check_permission_for_admin'],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
        
        // GET /settings/users/search - 搜尋使用者（用於新增小幫手）
        register_rest_route($this->namespace, '/settings/users/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search_users'],
            'permission_callback' => [$this, 'check_permission_for_admin'],
            'args' => [
                'query' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ]
            ]
        ]);
        
        // GET /settings/user/permissions - 取得當前使用者權限
        register_rest_route($this->namespace, '/settings/user/permissions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_permissions'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }
    
    /**
     * 取得模板設定
     */
    public function get_templates($request) {
        try {
            $templates = SettingsService::get_templates();
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $templates
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得模板設定失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 更新模板設定
     */
    public function update_templates($request) {
        try {
            $body = json_decode($request->get_body(), true);
            
            if (!isset($body['buyer_template']) || !isset($body['seller_template'])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '缺少必要參數'
                ], 400);
            }
            
            SettingsService::update_templates([
                'buyer_template' => $body['buyer_template'],
                'seller_template' => $body['seller_template'],
            ]);
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => '模板設定已更新'
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '更新模板設定失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 取得小幫手列表
     */
    public function get_helpers($request) {
        try {
            $helpers = SettingsService::get_helpers();
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $helpers
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得小幫手列表失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 新增小幫手
     */
    public function add_helper($request) {
        try {
            $body = json_decode($request->get_body(), true);
            $user_id = (int)($body['user_id'] ?? 0);
            
            if (!$user_id) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '缺少 user_id 參數'
                ], 400);
            }
            
            $user = get_userdata($user_id);
            if (!$user) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '使用者不存在'
                ], 404);
            }
            
            SettingsService::add_helper($user_id);
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => '小幫手已新增',
                'data' => [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                ]
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '新增小幫手失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 移除小幫手
     */
    public function remove_helper($request) {
        try {
            $user_id = (int)$request->get_param('user_id');
            
            if (!$user_id) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '缺少 user_id 參數'
                ], 400);
            }
            
            SettingsService::remove_helper($user_id);
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => '小幫手已移除'
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '移除小幫手失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 搜尋使用者
     */
    public function search_users($request) {
        try {
            $query = $request->get_param('query');
            
            if (empty($query)) {
                return new \WP_REST_Response([
                    'success' => true,
                    'data' => []
                ], 200);
            }
            
            $users = get_users([
                'search' => '*' . $query . '*',
                'search_columns' => ['user_login', 'user_nicename', 'user_email', 'display_name'],
                'number' => 20,
            ]);
            
            $results = [];
            foreach ($users as $user) {
                $results[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                ];
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $results
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '搜尋使用者失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 權限檢查（一般使用者）
     */
    public function check_permission() {
        return \BuyGoPlus\Api\API::check_permission();
    }
    
    /**
     * 取得當前使用者權限
     */
    public function get_user_permissions($request) {
        try {
            $is_admin = current_user_can('buygo_admin') || current_user_can('manage_options');
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'is_admin' => $is_admin,
                    'can_add_helper' => $is_admin,
                ]
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得權限失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 權限檢查（僅管理員）
     */
    public function check_permission_for_admin() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        // 檢查是否為外掛管理員
        return current_user_can('buygo_admin') || current_user_can('manage_options');
    }
}
