<?php
namespace BuyGoPlus\Api;

use BuyGoPlus\Services\DataManagementService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data Management API - 資料管理 REST API
 *
 * 提供資料管理 Tab 的 REST API 端點：
 * - GET  /data-management/query         查詢訂單/商品/客戶
 * - POST /data-management/delete-orders  刪除訂單（含串聯清理）
 * - POST /data-management/delete-products 軟刪除商品
 * - POST /data-management/delete-customers 刪除客戶
 * - PUT  /data-management/customers/{id}  編輯客戶資料
 *
 * 所有端點僅限管理員（buygo_admin 或 manage_options）存取。
 * 刪除端點需要 confirmation_token = 'DELETE' 作為二次確認（DATA-05）。
 *
 * @package BuyGoPlus\Api
 * @version 1.0.0
 */
class DataManagement_API
{
    private $namespace = 'buygo-plus-one/v1';

    /**
     * 註冊 REST API 路由
     */
    public function register_routes()
    {
        // GET /data-management/query - 查詢訂單/商品/客戶
        register_rest_route($this->namespace, '/data-management/query', [
            'methods'             => 'GET',
            'callback'            => [$this, 'query_data'],
            'permission_callback' => [$this, 'check_permission_for_admin'],
            'args'                => [
                'type' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($param) {
                        return in_array($param, ['orders', 'products', 'customers'], true);
                    },
                ],
                'date_from' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($param) {
                        if (empty($param)) {
                            return true;
                        }
                        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    },
                ],
                'date_to' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($param) {
                        if (empty($param)) {
                            return true;
                        }
                        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    },
                ],
                'keyword' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'page' => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // POST /data-management/delete-orders - 刪除訂單（含串聯清理）
        register_rest_route($this->namespace, '/data-management/delete-orders', [
            'methods'             => 'POST',
            'callback'            => [$this, 'delete_orders'],
            'permission_callback' => [$this, 'check_permission_for_admin'],
            'args'                => [
                'ids' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_array($param) && !empty($param);
                    },
                ],
                'confirmation_token' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // POST /data-management/delete-products - 軟刪除商品
        register_rest_route($this->namespace, '/data-management/delete-products', [
            'methods'             => 'POST',
            'callback'            => [$this, 'delete_products'],
            'permission_callback' => [$this, 'check_permission_for_admin'],
            'args'                => [
                'ids' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_array($param) && !empty($param);
                    },
                ],
                'confirmation_token' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // POST /data-management/delete-customers - 刪除客戶
        register_rest_route($this->namespace, '/data-management/delete-customers', [
            'methods'             => 'POST',
            'callback'            => [$this, 'delete_customers'],
            'permission_callback' => [$this, 'check_permission_for_admin'],
            'args'                => [
                'ids' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_array($param) && !empty($param);
                    },
                ],
                'confirmation_token' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // PUT /data-management/customers/{id} - 編輯客戶資料
        register_rest_route($this->namespace, '/data-management/customers/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'update_customer'],
            'permission_callback' => [$this, 'check_permission_for_admin'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
                'first_name' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'last_name' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'phone' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'address_1' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'address_2' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'city' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'state' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'postcode' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'country' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'taiwan_id_number' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * 查詢資料（訂單/商品/客戶）
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function query_data($request)
    {
        try {
            $service = new DataManagementService();
            $type    = $request->get_param('type');
            $params  = [
                'date_from' => $request->get_param('date_from'),
                'date_to'   => $request->get_param('date_to'),
                'keyword'   => $request->get_param('keyword'),
                'page'      => $request->get_param('page'),
                'per_page'  => $request->get_param('per_page'),
            ];

            switch ($type) {
                case 'orders':
                    $result = $service->queryOrders($params);
                    break;
                case 'products':
                    $result = $service->queryProducts($params);
                    break;
                case 'customers':
                    $result = $service->queryCustomers($params);
                    break;
                default:
                    return new \WP_REST_Response([
                        'success' => false,
                        'message' => '不支援的查詢類型：' . $type,
                    ], 400);
            }

            return new \WP_REST_Response([
                'success' => true,
                'data'    => $result,
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '查詢失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 刪除訂單（含串聯清理出貨單、子訂單）
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function delete_orders($request)
    {
        try {
            $body = json_decode($request->get_body(), true);
            $ids  = $body['ids'] ?? [];
            $confirmation_token = $body['confirmation_token'] ?? '';

            // DATA-05: 伺服器端二次確認驗證
            if ($confirmation_token !== 'DELETE') {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '需要輸入 DELETE 確認刪除',
                ], 400);
            }

            $service = new DataManagementService();
            $result  = $service->deleteOrders(array_map('intval', $ids));

            return new \WP_REST_Response([
                'success' => true,
                'data'    => $result,
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '刪除訂單失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 軟刪除商品（設為 inactive）
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function delete_products($request)
    {
        try {
            $body = json_decode($request->get_body(), true);
            $ids  = $body['ids'] ?? [];
            $confirmation_token = $body['confirmation_token'] ?? '';

            // DATA-05: 伺服器端二次確認驗證
            if ($confirmation_token !== 'DELETE') {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '需要輸入 DELETE 確認刪除',
                ], 400);
            }

            $service = new DataManagementService();
            $result  = $service->deleteProducts(array_map('intval', $ids));

            return new \WP_REST_Response([
                'success' => true,
                'data'    => $result,
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '刪除商品失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 刪除客戶（FluentCart 資料，保留 WP 帳號）
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function delete_customers($request)
    {
        try {
            $body = json_decode($request->get_body(), true);
            $ids  = $body['ids'] ?? [];
            $confirmation_token = $body['confirmation_token'] ?? '';

            // DATA-05: 伺服器端二次確認驗證
            if ($confirmation_token !== 'DELETE') {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '需要輸入 DELETE 確認刪除',
                ], 400);
            }

            $service = new DataManagementService();
            $result  = $service->deleteCustomers(array_map('intval', $ids));

            return new \WP_REST_Response([
                'success' => true,
                'data'    => $result,
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '刪除客戶失敗：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 編輯客戶資料
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function update_customer($request)
    {
        try {
            $customer_id = (int) $request->get_param('id');
            $data = [];

            // 收集可編輯欄位
            $editable_fields = [
                'first_name', 'last_name', 'phone',
                'address_1', 'address_2', 'city',
                'state', 'postcode', 'country',
                'taiwan_id_number',
            ];

            foreach ($editable_fields as $field) {
                $value = $request->get_param($field);
                if ($value !== null) {
                    $data[$field] = $value;
                }
            }

            if (empty($data)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '未提供任何要更新的欄位',
                ], 400);
            }

            $service = new DataManagementService();
            $result  = $service->updateCustomer($customer_id, $data);

            if (!$result['success']) {
                $status_code = ($result['message'] ?? '') === '找不到指定的客戶' ? 404 : 400;
                return new \WP_REST_Response($result, $status_code);
            }

            return new \WP_REST_Response($result, 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '更新客戶失敗：' . $e->getMessage(),
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
