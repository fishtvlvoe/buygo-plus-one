<?php
namespace BuyGoPlus\Api;

use BuyGoPlus\Services\SettingsService;
use BuyGoPlus\Services\CustomerEditService;

if (!defined('ABSPATH')) {
    exit;
}

class Customers_API {

    private $namespace = 'buygo-plus-one/v1';

    public function __construct() {
        // 建構函數
    }

    /**
     * 註冊 REST API 路由
     */
    public function register_routes() {
        // GET /customers - 取得客戶列表
        register_rest_route($this->namespace, '/customers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_customers'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'page' => [
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'per_page' => [
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                ],
                'search' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ]
        ]);

        // GET /customers/{id} - 取得單一客戶詳情
        register_rest_route($this->namespace, '/customers/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_customer'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);

        // PUT /customers/{id} - 更新客戶資料
        register_rest_route($this->namespace, '/customers/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_customer'],
            'permission_callback' => function () {
                return API::check_permission_with_scope('customers');
            },
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);

        // PUT /customers/{id}/note - 更新客戶備註
        register_rest_route($this->namespace, '/customers/(?P<id>\d+)/note', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_note'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
    }

    /**
     * 取得客戶列表
     *
     * 快取邏輯保留在此（API 層職責）；查詢邏輯已遷移至 CustomerQueryService::getListCustomers()。
     *
     * 使用 per-user Transient 快取（TTL 30 秒），降低頻繁 SWR 輪詢對資料庫的壓力。
     * 快取 key 包含 user_id 與查詢參數 hash，確保不同使用者、不同篩選條件各自獨立。
     */
    public function get_customers($request) {
        try {
            $params = $request->get_params();

            // 建立 per-user 快取 key（包含查詢參數 hash，不同分頁/搜尋各自快取）
            $user_id   = get_current_user_id();
            $cache_key = 'buygo_customers_' . $user_id . '_' . md5(serialize($params));

            // 嘗試從 transient 讀取快取
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return new \WP_REST_Response($cached, 200);
            }

            $is_admin = API::is_platform_admin();
            $service  = new \BuyGoPlus\Services\CustomerQueryService();
            $result   = $service->getListCustomers($params, $user_id, $is_admin);

            $response_data = [
                'success'     => true,
                'data'        => $result['customers'],
                'total'       => $result['total'],
                'page'        => $result['page'],
                'per_page'    => $result['per_page'],
                'total_pages' => $result['total_pages'],
            ];

            // 快取 30 秒（與前端 BuyGoCache.TTL 一致）
            set_transient($cache_key, $response_data, 30);

            return new \WP_REST_Response($response_data, 200);

        } catch (\Exception $e) {
            error_log('BuyGo Customers API Exception: ' . $e->getMessage());
            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得客戶列表失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取得單一客戶詳情（包含所有訂單）
     *
     * 查詢邏輯已遷移至 CustomerQueryService::getCustomerDetail()。
     */
    public function get_customer($request) {
        try {
            $customer_id = (int)$request->get_param('id');

            // 驗證客戶所有權
            $check = API::verify_customer_ownership($customer_id);
            if (is_wp_error($check)) {
                return $check;
            }

            $service  = new \BuyGoPlus\Services\CustomerQueryService();
            $customer = $service->getCustomerDetail($customer_id);

            if (!$customer) {
                error_log('BuyGo Customers API: Customer not found, ID = ' . $customer_id);
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '客戶不存在'
                ], 404);
            }

            return new \WP_REST_Response([
                'success' => true,
                'data' => $customer
            ], 200);

        } catch (\Exception $e) {
            error_log('BuyGo Customers API Exception: ' . $e->getMessage());
            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得客戶詳情失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新客戶資料（inline 編輯）
     */
    public function update_customer($request) {
        $customer_id = (int) $request->get_param('id');
        $body = $request->get_json_params();

        if (empty($body)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '無更新資料'
            ], 400);
        }

        // 跨賣場檢查
        if (!CustomerEditService::check_ownership($customer_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '無權限編輯此客戶'
            ], 403);
        }

        $result = CustomerEditService::update($customer_id, $body);
        $status = $result['success'] ? 200 : 400;

        return new \WP_REST_Response($result, $status);
    }

    /**
     * 更新客戶備註
     */
    public function update_note($request) {
        global $wpdb;

        try {
            $customer_id = (int)$request->get_param('id');

            // 驗證客戶所有權
            $check = API::verify_customer_ownership($customer_id);
            if (is_wp_error($check)) {
                return $check;
            }

            $body = json_decode($request->get_body(), true);
            $note = $body['note'] ?? '';

            $table_customers = $wpdb->prefix . 'fct_customers';

            // 檢查客戶是否存在
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$table_customers} WHERE id = %d",
                $customer_id
            ));

            if (!$customer) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '客戶不存在'
                ], 404);
            }

            // 更新備註
            $result = $wpdb->update(
                $table_customers,
                ['note' => $note, 'updated_at' => current_time('mysql')],
                ['id' => $customer_id],
                ['%s', '%s'],
                ['%d']
            );

            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }

            return new \WP_REST_Response([
                'success' => true,
                'message' => '備註已更新'
            ], 200);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '更新備註失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 權限檢查
     */
    public static function check_permission() {
        return \BuyGoPlus\Api\API::check_permission_with_scope('customers');
    }
}
