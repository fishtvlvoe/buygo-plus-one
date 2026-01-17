<?php
namespace BuyGoPlus\Api;

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
            'permission_callback' => '__return_true',
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
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
        
        // PUT /customers/{id}/note - 更新客戶備註
        register_rest_route($this->namespace, '/customers/(?P<id>\d+)/note', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_note'],
            'permission_callback' => '__return_true',
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
     */
    public function get_customers($request) {
        global $wpdb;
        
        try {
            $params = $request->get_params();
            $page = $params['page'] ?? 1;
            $per_page = $params['per_page'] ?? 20;
            $search = $params['search'] ?? '';
            
            $offset = ($page - 1) * $per_page;
            
            $table_customers = $wpdb->prefix . 'fct_customers';
            $table_orders = $wpdb->prefix . 'fct_orders';
            
            // 建立搜尋條件
            $where_conditions = ['1=1'];
            $where_values = [];
            
            if (!empty($search)) {
                $where_conditions[] = "(CONCAT(c.first_name, ' ', c.last_name) LIKE %s 
                                     OR c.phone LIKE %s 
                                     OR c.email LIKE %s)";
                $search_term = '%' . $wpdb->esc_like($search) . '%';
                $where_values[] = $search_term;
                $where_values[] = $search_term;
                $where_values[] = $search_term;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            // 取得客戶資料（包含訂單統計）
            $query = "SELECT 
                        c.id,
                        c.first_name,
                        c.last_name,
                        CONCAT(c.first_name, ' ', c.last_name) as full_name,
                        c.phone,
                        c.email,
                        c.address,
                        COUNT(o.id) as order_count,
                        COALESCE(SUM(o.total), 0) as total_spent,
                        MAX(o.created_at) as last_order_date
                      FROM {$table_customers} c
                      LEFT JOIN {$table_orders} o ON c.id = o.customer_id
                      WHERE {$where_clause}
                      GROUP BY c.id
                      ORDER BY last_order_date DESC
                      LIMIT %d OFFSET %d";
            
            $where_values[] = $per_page;
            $where_values[] = $offset;
            
            $customers = $wpdb->get_results(
                $wpdb->prepare($query, ...$where_values),
                ARRAY_A
            );
            
            // 取得總數
            $count_query = "SELECT COUNT(DISTINCT c.id)
                           FROM {$table_customers} c
                           WHERE {$where_clause}";
            
            if (empty($where_values)) {
                $total = $wpdb->get_var($count_query);
            } else {
                // 移除 LIMIT 和 OFFSET 的參數（最後兩個）
                $count_values = array_slice($where_values, 0, -2);
                $total = $wpdb->get_var($wpdb->prepare($count_query, ...$count_values));
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $customers,
                'total' => (int)$total,
                'page' => (int)$page,
                'per_page' => (int)$per_page,
                'total_pages' => ceil($total / $per_page)
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得客戶列表失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 取得單一客戶詳情（包含所有訂單）
     */
    public function get_customer($request) {
        global $wpdb;
        
        try {
            $customer_id = (int)$request->get_param('id');
            
            $table_customers = $wpdb->prefix . 'fct_customers';
            $table_orders = $wpdb->prefix . 'fct_orders';
            
            // 取得客戶基本資料
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    c.*,
                    CONCAT(c.first_name, ' ', c.last_name) as full_name,
                    COUNT(o.id) as order_count,
                    COALESCE(SUM(o.total), 0) as total_spent
                 FROM {$table_customers} c
                 LEFT JOIN {$table_orders} o ON c.id = o.customer_id
                 WHERE c.id = %d
                 GROUP BY c.id",
                $customer_id
            ), ARRAY_A);
            
            if (!$customer) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '客戶不存在'
                ], 404);
            }
            
            // 取得客戶的所有訂單
            $orders = $wpdb->get_results($wpdb->prepare(
                "SELECT id, order_serial, total, order_status, payment_status, created_at
                 FROM {$table_orders}
                 WHERE customer_id = %d
                 ORDER BY created_at DESC",
                $customer_id
            ), ARRAY_A);
            
            $customer['orders'] = $orders;
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $customer
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '取得客戶詳情失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 更新客戶備註
     */
    public function update_note($request) {
        global $wpdb;
        
        try {
            $customer_id = (int)$request->get_param('id');
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
            
            // 檢查 note 欄位是否存在，如果不存在則先加入
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SELECT COLUMN_NAME 
                 FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s 
                 AND TABLE_NAME = %s 
                 AND COLUMN_NAME = 'note'",
                DB_NAME,
                $table_customers
            ));
            
            if (empty($column_exists)) {
                // 加入 note 欄位
                $wpdb->query("ALTER TABLE {$table_customers} ADD COLUMN note TEXT NULL");
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
        return \BuyGoPlus\Api\API::check_permission_for_api();
    }
}
