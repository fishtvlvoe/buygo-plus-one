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
        
        // POST /settings/binding/send - 發送綁定連結
        register_rest_route($this->namespace, '/settings/binding/send', [
            'methods' => 'POST',
            'callback' => [$this, 'send_binding_link'],
            'permission_callback' => [$this, 'check_permission_for_admin'],
        ]);
        
        // POST /settings/roles/remove - 移除角色
        register_rest_route($this->namespace, '/settings/roles/remove', [
            'methods' => 'POST',
            'callback' => [$this, 'remove_role'],
            'permission_callback' => [$this, 'check_permission_for_admin'],
        ]);
        
        // POST /settings/templates/order - 更新模板順序
        register_rest_route($this->namespace, '/settings/templates/order', [
            'methods' => 'POST',
            'callback' => [$this, 'update_template_order'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        // GET /settings/templates/order - 取得模板順序
        register_rest_route($this->namespace, '/settings/templates/order', [
            'methods' => 'GET',
            'callback' => [$this, 'get_template_order'],
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
     * 更新模板設定（支援完整模板結構，包含 Flex Message）
     */
    public function update_templates($request) {
        try {
            $body = json_decode($request->get_body(), true);
            
            // 新的格式：接收完整的模板資料結構
            if (isset($body['templates']) && is_array($body['templates'])) {
                // 使用新的統一格式
                SettingsService::update_templates($body['templates']);
            } elseif (isset($body['buyer_template']) || isset($body['seller_template'])) {
                // 舊格式：向後兼容（遷移舊資料）
                $templates = [];
                
                if (isset($body['buyer_template'])) {
                    // 遷移到 order_created 模板
                    $templates['order_created'] = [
                        'line' => [
                            'message' => sanitize_textarea_field($body['buyer_template'])
                        ]
                    ];
                }
                
                if (isset($body['seller_template'])) {
                    // 遷移到 seller_order_created 模板
                    $templates['seller_order_created'] = [
                        'line' => [
                            'message' => sanitize_textarea_field($body['seller_template'])
                        ]
                    ];
                }
                
                SettingsService::update_templates($templates);
            } else {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '缺少必要參數'
                ], 400);
            }
            
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
     * 新增小幫手或管理員
     */
    public function add_helper($request) {
        try {
            $body = json_decode($request->get_body(), true);
            $user_id = (int)($body['user_id'] ?? 0);
            $role = sanitize_text_field($body['role'] ?? 'buygo_helper');
            
            if (!$user_id) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '缺少 user_id 參數'
                ], 400);
            }
            
            // 驗證角色
            if (!in_array($role, ['buygo_helper', 'buygo_admin'], true)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '無效的角色'
                ], 400);
            }
            
            $user = get_userdata($user_id);
            if (!$user) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '使用者不存在'
                ], 404);
            }
            
            SettingsService::add_helper($user_id, $role);
            
            $role_name = $role === 'buygo_admin' ? '管理員' : '小幫手';
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => "{$role_name}已新增",
                'data' => [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                ]
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '新增角色失敗：' . $e->getMessage()
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
     * 發送綁定連結
     */
    public function send_binding_link($request) {
        try {
            $body = json_decode($request->get_body(), true);
            $user_id = (int)($body['user_id'] ?? 0);
            
            if (!$user_id) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '缺少 user_id 參數'
                ], 400);
            }
            
            $result = SettingsService::send_binding_link($user_id);
            
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
                'message' => '發送綁定連結失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 移除角色
     */
    public function remove_role($request) {
        try {
            $body = json_decode($request->get_body(), true);
            $user_id = (int)($body['user_id'] ?? 0);
            $role = sanitize_text_field($body['role'] ?? '');
            
            if (!$user_id) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '缺少 user_id 參數'
                ], 400);
            }
            
            // 驗證角色
            if (!in_array($role, ['buygo_helper', 'buygo_admin'], true)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '無效的角色'
                ], 400);
            }
            
            $user = get_userdata($user_id);
            if (!$user) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '使用者不存在'
                ], 404);
            }
            
            // 防止移除 WordPress 管理員的 BuyGo 管理員角色（如果他們是 WordPress 管理員）
            if ($role === 'buygo_admin' && $user->has_cap('administrator')) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '無法移除 WordPress 管理員的 BuyGo 管理員角色'
                ], 400);
            }
            
            SettingsService::remove_role($user_id, $role);
            
            $role_name = $role === 'buygo_admin' ? '管理員' : '小幫手';
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => "{$role_name}角色已移除"
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '移除角色失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 權限檢查（僅管理員）
     * TODO: 測試完成後，統一設定權限檢查
     */
    public function check_permission_for_admin() {
        // 暫時允許所有請求（測試階段）
        // 測試完成後，改為：
        // if (!is_user_logged_in()) {
        //     return false;
        // }
        // return current_user_can('buygo_admin') || current_user_can('manage_options');
        
        return true;
    }
    
    /**
     * 更新模板順序
     */
    public function update_template_order($request) {
        try {
            $body = json_decode($request->get_body(), true);
            
            if (!isset($body['tab']) || !isset($body['order']) || !is_array($body['order'])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '缺少必要參數'
                ], 400);
            }
            
            $tab = sanitize_text_field($body['tab']);
            $order = $body['order'];
            
            // 取得現有的 metadata
            $metadata = get_option('buygo_notification_templates_metadata', []);
            
            // 更新每個模板的順序
            foreach ($order as $item) {
                $key = sanitize_key($item['key'] ?? '');
                $order_value = intval($item['order'] ?? 0);
                
                if (empty($key)) {
                    continue;
                }
                
                // 初始化 metadata（如果不存在）
                if (!isset($metadata[$key])) {
                    $metadata[$key] = [];
                }
                
                // 更新順序
                $metadata[$key]['order'] = $order_value;
                $metadata[$key]['tab'] = $tab;
            }
            
            // 儲存到資料庫
            update_option('buygo_notification_templates_metadata', $metadata);
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => '模板順序已更新'
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '更新模板順序失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 取得模板順序
     */
    public function get_template_order($request) {
        try {
            $metadata = get_option('buygo_notification_templates_metadata', []);
            
            // 按照 tab 分組
            $orderData = [
                'buyer' => [],
                'seller' => [],
                'system' => []
            ];
            
            foreach ($metadata as $key => $meta) {
                $tab = $meta['tab'] ?? 'buyer';
                $order = $meta['order'] ?? 0;
                
                if (isset($orderData[$tab])) {
                    $orderData[$tab][] = [
                        'key' => $key,
                        'order' => $order
                    ];
                }
            }
            
            // 排序每個 tab 的順序
            foreach ($orderData as &$orders) {
                usort($orders, function($a, $b) {
                    return $a['order'] - $b['order'];
                });
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $orderData
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得模板順序失敗：' . $e->getMessage()
            ], 500);
        }
    }
}
