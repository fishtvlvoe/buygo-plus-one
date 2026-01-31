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
     * FluentCart 資料表結構：
     * - fct_customers: id, user_id, email, first_name, last_name, status, ltv, purchase_count 等
     * - fct_customer_addresses: 客戶地址和電話資訊
     * - fct_orders: 訂單資料
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
            $table_addresses = $wpdb->prefix . 'fct_customer_addresses';
            
            // 先取得總數（不使用搜尋條件來確認資料存在）
            $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_customers}");
            
            // 建立搜尋條件
            $where_conditions = ['1=1'];
            $query_params = [];
            
            if (!empty($search)) {
                $where_conditions[] = "(CONCAT(c.first_name, ' ', c.last_name) LIKE %s 
                                     OR c.email LIKE %s)";
                $search_term = '%' . $wpdb->esc_like($search) . '%';
                $query_params[] = $search_term;
                $query_params[] = $search_term;
                
                // 重新計算搜尋後的總數
                $count_query = "SELECT COUNT(DISTINCT c.id)
                               FROM {$table_customers} c
                               WHERE " . implode(' AND ', $where_conditions);
                $total = $wpdb->get_var($wpdb->prepare($count_query, ...$query_params));
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            // 取得客戶資料（直接從 fct_orders 聚合計算，不使用 FluentCart 的統計欄位）
            // 注意：phone 和 address 從 fct_customer_addresses 表取得
            // 名稱優先使用 fct_customer_addresses.name（收件地址中的正式名稱）
            $query = "SELECT
                        c.id,
                        c.first_name,
                        c.last_name,
                        -- 優先使用地址表中的 name（正式收件人名稱），若無則使用 fct_customers 的名稱
                        COALESCE(
                            (SELECT a.name FROM {$table_addresses} a WHERE a.customer_id = c.id AND a.is_primary = 1 AND a.name IS NOT NULL AND a.name != '' LIMIT 1),
                            NULLIF(TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))), ''),
                            c.email
                        ) as full_name,
                        c.email,
                        c.status,
                        COUNT(o.id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_spent,
                        MAX(o.created_at) as last_order_date,
                        (SELECT a.phone FROM {$table_addresses} a WHERE a.customer_id = c.id AND a.is_primary = 1 LIMIT 1) as phone,
                        (SELECT CONCAT(COALESCE(a.city, ''), ', ', COALESCE(a.state, ''), ', ', COALESCE(a.country, ''))
                         FROM {$table_addresses} a WHERE a.customer_id = c.id AND a.is_primary = 1 LIMIT 1) as address
                      FROM {$table_customers} c
                      LEFT JOIN {$table_orders} o ON c.id = o.customer_id
                      WHERE {$where_clause}
                      GROUP BY c.id
                      ORDER BY last_order_date DESC, c.id DESC
                      LIMIT %d OFFSET %d";
            
            // 添加 LIMIT 和 OFFSET 參數
            $query_params[] = $per_page;
            $query_params[] = $offset;
            
            // 執行查詢
            $customers = $wpdb->get_results(
                $wpdb->prepare($query, ...$query_params),
                ARRAY_A
            );
            
            // 如果查詢失敗，記錄錯誤
            if ($customers === null) {
                error_log('BuyGo Customers API Error: ' . $wpdb->last_error);
                $customers = [];
            }

            // 為每個客戶添加頭像 URL、LINE 名稱，並清理姓名
            if (is_array($customers)) {
                foreach ($customers as &$customer) {
                    // 清理姓名：移除表情符號和特殊符號
                    $customer['full_name'] = $this->sanitize_customer_name($customer['full_name']);

                    // 如果清理後的名稱為空，使用 email 作為顯示名稱
                    if (empty($customer['full_name'])) {
                        $customer['full_name'] = $customer['email'];
                    }

                    // 取得 WordPress user_id（從 fct_customers 表）
                    $wp_user_id = $this->get_wp_user_id_by_customer_id($customer['id']);

                    if ($wp_user_id) {
                        // 優先使用 FluentCart 儲存的客戶照片（來自 LINE 登入）
                        $avatar_url = get_user_meta($wp_user_id, 'fc_customer_photo_url', true);
                        $customer['avatar'] = !empty($avatar_url) ? esc_url($avatar_url) : get_avatar_url($customer['email'], ['size' => 100]);

                        // 取得 LINE 名稱
                        $customer['line_display_name'] = get_user_meta($wp_user_id, 'buygo_line_display_name', true) ?: '';
                    } else {
                        // 沒有 user_id 則使用 Gravatar
                        $customer['avatar'] = get_avatar_url($customer['email'], ['size' => 100]);
                        $customer['line_display_name'] = '';
                    }
                }
                unset($customer); // 解除參考
            }

            return new \WP_REST_Response([
                'success' => true,
                'data' => $customers,
                'total' => (int)$total,
                'page' => (int)$page,
                'per_page' => (int)$per_page,
                'total_pages' => $per_page > 0 ? ceil($total / $per_page) : 1
            ], 200);
            
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
     */
    public function get_customer($request) {
        global $wpdb;
        
        try {
            $customer_id = (int)$request->get_param('id');
            
            $table_customers = $wpdb->prefix . 'fct_customers';
            $table_orders = $wpdb->prefix . 'fct_orders';
            $table_addresses = $wpdb->prefix . 'fct_customer_addresses';
            
            // 取得客戶基本資料（直接從 fct_orders 聚合計算，不使用 FluentCart 的統計欄位）
            // 名稱優先使用 fct_customer_addresses.name（收件地址中的正式名稱），
            // 因為這是客戶在結帳時填寫的正式收件人名稱，不會包含表情符號
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT
                    c.id,
                    c.user_id,
                    c.email,
                    c.first_name,
                    c.last_name,
                    -- 優先使用地址表中的 name（正式收件人名稱），若無則使用 fct_customers 的名稱
                    COALESCE(
                        (SELECT a.name FROM {$table_addresses} a WHERE a.customer_id = c.id AND a.is_primary = 1 AND a.name IS NOT NULL AND a.name != '' LIMIT 1),
                        NULLIF(TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))), ''),
                        c.email
                    ) as full_name,
                    c.status,
                    COUNT(o.id) as order_count,
                    COALESCE(SUM(o.total_amount), 0) as total_spent,
                    MAX(o.created_at) as last_purchase_date,
                    c.notes as note,
                    (SELECT a.phone FROM {$table_addresses} a WHERE a.customer_id = c.id AND a.is_primary = 1 LIMIT 1) as phone,
                    (SELECT CONCAT(
                        COALESCE(a.address_1, ''), ' ',
                        COALESCE(a.address_2, ''), ', ',
                        COALESCE(a.city, ''), ', ',
                        COALESCE(a.state, ''), ' ',
                        COALESCE(a.postcode, ''), ', ',
                        COALESCE(a.country, '')
                    ) FROM {$table_addresses} a WHERE a.customer_id = c.id AND a.is_primary = 1 LIMIT 1) as address
                 FROM {$table_customers} c
                 LEFT JOIN {$table_orders} o ON c.id = o.customer_id
                 WHERE c.id = %d
                 GROUP BY c.id",
                $customer_id
            ), ARRAY_A);
            
            if (!$customer) {
                error_log('BuyGo Customers API: Customer not found, ID = ' . $customer_id);
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '客戶不存在'
                ], 404);
            }
            
            // 取得客戶的所有訂單（不限制狀態）
            // 注意：fct_orders 表沒有 order_number，使用 id 或 receipt_number
            $orders = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    id, 
                    id as order_number,
                    total_amount, 
                    status as order_status,
                    payment_status, 
                    created_at,
                    currency
                 FROM {$table_orders}
                 WHERE customer_id = %d
                 ORDER BY created_at DESC",
                $customer_id
            ), ARRAY_A);

            // 補上 currency fallback（fct_orders 若無 currency 欄位，上列 SELECT 會 SQL 錯，需改為移除 currency 並全設 TWD）
            if (is_array($orders)) {
                foreach ($orders as &$o) {
                    if (empty($o['currency'])) {
                        $o['currency'] = 'TWD';
                    }
                }
                unset($o);
            }

            $customer['orders'] = $orders ?: [];

            // 清理姓名：移除表情符號和特殊符號
            $customer['full_name'] = $this->sanitize_customer_name($customer['full_name']);

            // 如果清理後的名稱為空，使用 email 作為顯示名稱
            if (empty($customer['full_name'])) {
                $customer['full_name'] = $customer['email'];
            }

            // 取得 LINE 名稱和身分證字號（從 wp_usermeta）
            if (!empty($customer['user_id'])) {
                $customer['line_display_name'] = get_user_meta($customer['user_id'], 'buygo_line_display_name', true) ?: '';
                $customer['taiwan_id_number'] = get_user_meta($customer['user_id'], 'buygo_taiwan_id_number', true) ?: '';
            } else {
                $customer['line_display_name'] = '';
                $customer['taiwan_id_number'] = '';
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
     * 根據 FluentCart customer_id 取得 WordPress user_id
     *
     * @param int $customer_id FluentCart 客戶 ID
     * @return int|null WordPress user_id 或 null
     */
    private function get_wp_user_id_by_customer_id($customer_id) {
        global $wpdb;
        $table_customers = $wpdb->prefix . 'fct_customers';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$table_customers} WHERE id = %d",
            $customer_id
        ));
    }

    /**
     * 清理名稱：移除表情符號和特殊符號，只保留中文、英文、數字和基本標點
     *
     * @param string $name 原始名稱
     * @return string 清理後的名稱
     */
    private function sanitize_customer_name($name) {
        if (empty($name)) {
            return '';
        }

        // 移除表情符號和特殊符號
        // 保留：中文字(CJK)、英文字母、數字、空格、基本標點（句號、逗號、破折號）
        // Unicode 範圍：
        // - 中文字：\x{4e00}-\x{9fff} (CJK Unified Ideographs)
        // - 中文標點：\x{3000}-\x{303f}
        // - 全形字符：\x{ff00}-\x{ffef}
        // - 日文假名：\x{3040}-\x{30ff}
        $cleaned = preg_replace(
            '/[^\p{Han}\p{Latin}\p{N}\s\.\,\-\_\(\)\[\]\/\·\、\。\，\：\；\！\？\「\」\『\』\（\）\【\】]/u',
            '',
            $name
        );

        // 移除多餘的空格
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = trim($cleaned);

        return $cleaned;
    }

    /**
     * 權限檢查
     */
    public static function check_permission() {
        return \BuyGoPlus\Api\API::check_permission_for_api();
    }
}
