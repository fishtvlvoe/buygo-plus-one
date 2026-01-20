<?php

namespace BuyGoPlus\Api;

use BuyGoPlus\Services\ShipmentService;
use BuyGoPlus\Services\ExportService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shipments API - 出貨單 API
 * 
 * 處理出貨單的查詢、建立、更新等功能
 * 
 * @package BuyGoPlus\Api
 */
class Shipments_API
{
    private $shipmentService;
    private $exportService;

    public function __construct()
    {
        $this->shipmentService = new ShipmentService();
        $this->exportService = new ExportService();
    }

    /**
     * 註冊 REST API 路由
     */
    public function register_routes()
    {
        register_rest_route('buygo-plus-one/v1', '/shipments', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_shipments'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_shipment'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('buygo-plus-one/v1', '/shipments/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_shipment'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_shipment'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route('buygo-plus-one/v1', '/shipments/batch-mark-shipped', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'batch_mark_shipped'],
                'permission_callback' => '__return_true',
            ],
        ]);

        // 移至存檔區
        register_rest_route('buygo-plus-one/v1', '/shipments/(?P<id>\d+)/archive', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'archive_shipment'],
                'permission_callback' => '__return_true',
            ],
        ]);

        // 合併出貨單
        register_rest_route('buygo-plus-one/v1', '/shipments/merge', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'merge_shipments'],
                'permission_callback' => '__return_true',
            ],
        ]);

        // 批次移至存檔
        register_rest_route('buygo-plus-one/v1', '/shipments/batch-archive', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'batch_archive_shipments'],
                'permission_callback' => '__return_true',
            ],
        ]);

        // 取得出貨單詳情
        register_rest_route('buygo-plus-one/v1', '/shipments/(?P<id>\d+)/detail', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_shipment_detail'],
                'permission_callback' => '__return_true',
            ],
        ]);

        // 匯出出貨單為 Excel/CSV（改用 GET 方法，參考舊外掛實作）
        register_rest_route('buygo-plus-one/v1', '/shipments/export', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'export_shipments'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    /**
     * 取得出貨單列表
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_shipments(WP_REST_Request $request)
    {
        global $wpdb;

        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 10;
        $status = $request->get_param('status'); // 'all', 'pending', 'shipped'
        $id = $request->get_param('id'); // 單一出貨單 ID

        $table_shipments = $wpdb->prefix . 'buygo_shipments';
        $table_shipment_items = $wpdb->prefix . 'buygo_shipment_items';
        $table_order_items = $wpdb->prefix . 'fct_order_items';
        $table_orders = $wpdb->prefix . 'fct_orders';
        $table_customers = $wpdb->prefix . 'fct_customers';

        // 建立 WHERE 條件
        $where_conditions = ['1=1'];

        if ($id) {
            $where_conditions[] = $wpdb->prepare('s.id = %d', $id);
        }

        if ($status && $status !== 'all') {
            $where_conditions[] = $wpdb->prepare('s.status = %s', $status);
        }

        $where_clause = implode(' AND ', $where_conditions);

        // 計算總數
        $total_query = "
            SELECT COUNT(DISTINCT s.id)
            FROM {$table_shipments} s
            WHERE {$where_clause}
        ";
        $total = $wpdb->get_var($total_query);

        // 計算偏移量
        $offset = ($page - 1) * $per_page;
        $limit = $per_page === -1 ? '' : "LIMIT {$per_page} OFFSET {$offset}";

        // 查詢出貨單列表（不 JOIN orders 表，避免笛卡兒積）
        $query = "
            SELECT 
                s.id,
                s.shipment_number,
                s.customer_id,
                s.seller_id,
                s.status,
                s.created_at,
                s.shipped_at,
                s.updated_at
            FROM {$table_shipments} s
            WHERE {$where_clause}
            ORDER BY s.created_at DESC
            {$limit}
        ";

        $shipments = $wpdb->get_results($query, ARRAY_A);

        // 為每個出貨單載入明細和客戶資料
        foreach ($shipments as &$shipment) {
            $shipment_id = $shipment['id'];
            $customer_id = $shipment['customer_id'];

            // 查詢客戶資料（直接從 fct_customers 表查詢）
            $customer_query = "
                SELECT first_name, last_name, email
                FROM {$table_customers}
                WHERE id = %d
                LIMIT 1
            ";
            $customer = $wpdb->get_row($wpdb->prepare($customer_query, $customer_id), ARRAY_A);
            
            if ($customer) {
                $first_name = $customer['first_name'] ?? '';
                $last_name = $customer['last_name'] ?? '';
                $shipment['customer_name'] = trim($first_name . ' ' . $last_name) ?: '未知客戶';
                $shipment['customer_email'] = $customer['email'] ?? null;
            } else {
                $shipment['customer_name'] = '未知客戶';
                $shipment['customer_email'] = null;
            }

            // 查詢出貨單明細
            $items_query = "
                SELECT
                    si.id,
                    si.order_id,
                    si.order_item_id,
                    si.product_id,
                    si.quantity,
                    oi.title,
                    oi.post_title,
                    oi.line_meta,
                    oi.unit_price,
                    o.invoice_no as order_invoice_no,
                    o.currency
                FROM {$table_shipment_items} si
                LEFT JOIN {$table_order_items} oi ON si.order_item_id = oi.id
                LEFT JOIN {$table_orders} o ON si.order_id = o.id
                WHERE si.shipment_id = %d
            ";

            $items = $wpdb->get_results($wpdb->prepare($items_query, $shipment_id), ARRAY_A);

            // 處理商品名稱、圖片和價格
            foreach ($items as &$item) {
                // 取得商品名稱（優先使用 title，其次使用 post_title）
                $product_name = $item['title'] ?? $item['post_title'] ?? null;

                // 如果從 order_items 沒有取得名稱，嘗試從 product_id 獲取
                if (empty($product_name) && !empty($item['product_id'])) {
                    $product = get_post($item['product_id']);
                    if ($product) {
                        $product_name = $product->post_title;
                    }
                }

                $item['product_name'] = $product_name ?: '未知商品';

                // 從 line_meta 解析商品圖片和其他 meta 資料
                $line_meta = $item['line_meta'] ?? '{}';
                $meta_data = is_string($line_meta) ? json_decode($line_meta, true) : ($line_meta ?: []);
                $product_image = $meta_data['product_image'] ?? $meta_data['image'] ?? null;

                // 如果沒有圖片，從 product_id 獲取特色圖片
                if (empty($product_image) && !empty($item['product_id'])) {
                    $thumbnail_id = get_post_thumbnail_id($item['product_id']);
                    if ($thumbnail_id) {
                        $product_image = wp_get_attachment_image_url($thumbnail_id, 'thumbnail');
                    }
                }

                $item['product_image'] = $product_image;

                // 處理價格（unit_price 是數據庫中的欄位名稱）
                $unit_price_value = floatval($item['unit_price'] ?? 0);
                $item['unit_price'] = $unit_price_value;
                $item['price'] = $unit_price_value;

                // 移除不需要的欄位
                unset($item['title'], $item['post_title'], $item['line_meta']);
            }
            unset($item);

            // 計算總數量
            $total_quantity = array_sum(array_column($items, 'quantity'));

            $shipment['items'] = $items;
            $shipment['total_quantity'] = $total_quantity;
            $shipment['items_count'] = count($items);
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => $shipments,
            'total' => (int) $total,
            'page' => (int) $page,
            'per_page' => $per_page === -1 ? $total : (int) $per_page,
        ]);
    }

    /**
     * 取得單一出貨單
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_shipment(WP_REST_Request $request)
    {
        $shipment_id = $request->get_param('id');
        $shipment = $this->shipmentService->get_shipment($shipment_id);

        if (!$shipment) {
            return new WP_Error('shipment_not_found', '出貨單不存在', ['status' => 404]);
        }

        $items = $this->shipmentService->get_shipment_items($shipment_id);

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'shipment' => $shipment,
                'items' => $items,
            ],
        ]);
    }

    /**
     * 建立出貨單
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_shipment(WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        $customer_id = $params['customer_id'] ?? null;
        $seller_id = get_current_user_id() ?: 1; // 預設為當前使用者
        $items = $params['items'] ?? [];

        if (!$customer_id) {
            return new WP_Error('missing_customer_id', '缺少客戶 ID', ['status' => 400]);
        }

        if (empty($items)) {
            return new WP_Error('missing_items', '出貨單必須包含至少一個商品', ['status' => 400]);
        }

        $shipment_id = $this->shipmentService->create_shipment($customer_id, $seller_id, $items);

        if (is_wp_error($shipment_id)) {
            return $shipment_id;
        }

        $shipment = $this->shipmentService->get_shipment($shipment_id);

        return new WP_REST_Response([
            'success' => true,
            'data' => $shipment,
            'shipment_id' => $shipment_id,
        ], 201);
    }

    /**
     * 更新出貨單
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_shipment(WP_REST_Request $request)
    {
        $shipment_id = $request->get_param('id');
        $params = $request->get_json_params();

        $shipment = $this->shipmentService->get_shipment($shipment_id);

        if (!$shipment) {
            return new WP_Error('shipment_not_found', '出貨單不存在', ['status' => 404]);
        }

        // 目前只支援更新狀態
        if (isset($params['status'])) {
            global $wpdb;

            $result = $wpdb->update(
                $wpdb->prefix . 'buygo_shipments',
                [
                    'status' => $params['status'],
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $shipment_id],
                ['%s', '%s'],
                ['%d']
            );

            if ($result === false) {
                return new WP_Error('update_failed', '更新失敗', ['status' => 500]);
            }

            $shipment = $this->shipmentService->get_shipment($shipment_id);
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => $shipment,
        ]);
    }

    /**
     * 批次標記為已出貨
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function batch_mark_shipped(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $shipment_ids = $params['shipment_ids'] ?? [];

        if (empty($shipment_ids)) {
            return new WP_Error('missing_shipment_ids', '請選擇要標記的出貨單', ['status' => 400]);
        }

        $result = $this->shipmentService->mark_shipped($shipment_ids);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => "成功標記 {$result} 個出貨單為已出貨",
            'count' => $result,
        ]);
    }

    /**
     * 移至存檔區
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function archive_shipment(WP_REST_Request $request)
    {
        global $wpdb;
        
        try {
            $table_shipments = $wpdb->prefix . 'buygo_shipments';
            $shipment_id = (int)$request->get_param('id');
            
            // 檢查出貨單是否存在
            $shipment = $wpdb->get_row($wpdb->prepare(
                "SELECT id, status FROM {$table_shipments} WHERE id = %d",
                $shipment_id
            ));
            
            if (!$shipment) {
                return new WP_Error('shipment_not_found', '出貨單不存在', ['status' => 404]);
            }
            
            // 更新狀態為 archived
            $result = $wpdb->update(
                $table_shipments,
                [
                    'status' => 'archived',
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $shipment_id],
                ['%s', '%s'],
                ['%d']
            );
            
            if ($result === false) {
                return new WP_Error('update_failed', '移至存檔失敗：' . $wpdb->last_error, ['status' => 500]);
            }
            
            return new WP_REST_Response([
                'success' => true,
                'message' => '已移至存檔區'
            ], 200);
            
        } catch (\Exception $e) {
            return new WP_Error('archive_failed', '移至存檔失敗：' . $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * 合併出貨單
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function merge_shipments(WP_REST_Request $request)
    {
        global $wpdb;
        
        try {
            $shipment_ids = $request->get_param('shipment_ids');
            
            // 驗證輸入
            if (empty($shipment_ids) || !is_array($shipment_ids)) {
                return new WP_Error('invalid_input', '請提供有效的出貨單 ID 陣列', ['status' => 400]);
            }
            
            if (count($shipment_ids) < 2) {
                return new WP_Error('invalid_input', '至少需要選擇 2 個出貨單才能合併', ['status' => 400]);
            }
            
            $table_shipments = $wpdb->prefix . 'buygo_shipments';
            $table_shipment_items = $wpdb->prefix . 'buygo_shipment_items';
            
            // 確保所有 ID 都是整數
            $shipment_ids = array_map('intval', $shipment_ids);
            $shipment_ids = array_filter($shipment_ids, function($id) { return $id > 0; });
            
            if (count($shipment_ids) < 2) {
                return new WP_Error('invalid_input', '至少需要選擇 2 個有效的出貨單才能合併', ['status' => 400]);
            }
            
            // 檢查所有出貨單是否屬於同一個客戶
            $ids_placeholder = implode(',', array_fill(0, count($shipment_ids), '%d'));
            $query = $wpdb->prepare(
                "SELECT DISTINCT customer_id FROM {$table_shipments} 
                 WHERE id IN ({$ids_placeholder})",
                ...$shipment_ids
            );
            $customer_ids = $wpdb->get_col($query);
            
            if (count($customer_ids) > 1) {
                return new WP_Error('different_customers', '只能合併相同客戶的出貨單', ['status' => 400]);
            }
            
            // 開始資料庫事務
            $wpdb->query('START TRANSACTION');
            
            try {
                // 保留第一個出貨單作為主出貨單
                $main_shipment_id = $shipment_ids[0];
                $merge_shipment_ids = array_slice($shipment_ids, 1);
                
                // 將其他出貨單的商品項目移到主出貨單
                foreach ($merge_shipment_ids as $merge_id) {
                    $wpdb->update(
                        $table_shipment_items,
                        ['shipment_id' => $main_shipment_id],
                        ['shipment_id' => $merge_id],
                        ['%d'],
                        ['%d']
                    );
                }
                
                // 刪除被合併的出貨單
                $merge_ids_placeholder = implode(',', array_fill(0, count($merge_shipment_ids), '%d'));
                $delete_query = $wpdb->prepare(
                    "DELETE FROM {$table_shipments} 
                     WHERE id IN ({$merge_ids_placeholder})",
                    ...$merge_shipment_ids
                );
                $wpdb->query($delete_query);
                
                // 更新主出貨單的 updated_at
                $wpdb->update(
                    $table_shipments,
                    ['updated_at' => current_time('mysql')],
                    ['id' => $main_shipment_id],
                    ['%s'],
                    ['%d']
                );
                
                $wpdb->query('COMMIT');
                
                return new WP_REST_Response([
                    'success' => true,
                    'message' => '合併成功',
                    'main_shipment_id' => $main_shipment_id
                ], 200);
                
            } catch (\Exception $e) {
                $wpdb->query('ROLLBACK');
                throw $e;
            }
            
        } catch (\Exception $e) {
            return new WP_Error('merge_failed', '合併失敗：' . $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * 批次移至存檔
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function batch_archive_shipments(WP_REST_Request $request)
    {
        global $wpdb;
        
        try {
            $shipment_ids = $request->get_param('shipment_ids');
            
            if (empty($shipment_ids) || !is_array($shipment_ids)) {
                return new WP_Error('invalid_input', '請提供有效的出貨單 ID 陣列', ['status' => 400]);
            }
            
            // 確保所有 ID 都是整數
            $shipment_ids = array_map('intval', $shipment_ids);
            $shipment_ids = array_filter($shipment_ids, function($id) { return $id > 0; });
            
            if (empty($shipment_ids)) {
                return new WP_Error('invalid_input', '請提供有效的出貨單 ID', ['status' => 400]);
            }
            
            $table_shipments = $wpdb->prefix . 'buygo_shipments';
            
            // 批次更新狀態
            $ids_placeholder = implode(',', array_fill(0, count($shipment_ids), '%d'));
            $update_query = $wpdb->prepare(
                "UPDATE {$table_shipments} 
                 SET status = 'archived', updated_at = %s 
                 WHERE id IN ({$ids_placeholder})",
                current_time('mysql'),
                ...$shipment_ids
            );
            $result = $wpdb->query($update_query);
            
            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }
            
            return new WP_REST_Response([
                'success' => true,
                'message' => "已將 {$result} 個出貨單移至存檔區",
                'count' => $result
            ], 200);
            
        } catch (\Exception $e) {
            return new WP_Error('batch_archive_failed', '批次移至存檔失敗：' . $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * 取得出貨單詳情
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_shipment_detail(WP_REST_Request $request)
    {
        global $wpdb;
        
        try {
            $shipment_id = (int)$request->get_param('id');
            
            $table_shipments = $wpdb->prefix . 'buygo_shipments';
            $table_shipment_items = $wpdb->prefix . 'buygo_shipment_items';
            $table_customers = $wpdb->prefix . 'fct_customers';
            $table_order_items = $wpdb->prefix . 'fct_order_items';
            
            // 取得出貨單基本資訊
            $shipment = $wpdb->get_row($wpdb->prepare(
                "SELECT s.*, 
                        CONCAT(c.first_name, ' ', c.last_name) as customer_name, 
                        c.phone as customer_phone, 
                        c.address as customer_address
                 FROM {$table_shipments} s
                 LEFT JOIN {$table_customers} c ON s.customer_id = c.id
                 WHERE s.id = %d",
                $shipment_id
            ), ARRAY_A);
            
            if (!$shipment) {
                return new WP_Error('shipment_not_found', '出貨單不存在', ['status' => 404]);
            }
            
            // 取得出貨單商品項目
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT si.*, 
                        COALESCE(oi.title, oi.post_title, '未知商品') as product_name,
                        oi.price
                 FROM {$table_shipment_items} si
                 LEFT JOIN {$table_order_items} oi ON si.order_item_id = oi.id
                 WHERE si.shipment_id = %d",
                $shipment_id
            ), ARRAY_A);
            
            // 處理價格（如果沒有價格，預設為 0）
            foreach ($items as &$item) {
                $item['price'] = floatval($item['price'] ?? 0);
            }
            unset($item);
            
            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'shipment' => $shipment,
                    'items' => $items
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return new WP_Error('get_detail_failed', '取得詳情失敗：' . $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * 匯出出貨單為 Excel/CSV
     *
     * 參考舊外掛的 ShipmentController::export_csv() 實作
     * 使用直接輸出模式（php://output + exit）而不是 WP_REST_Response
     *
     * @param WP_REST_Request $request
     * @return void 直接輸出檔案，呼叫 exit 結束
     */
    public function export_shipments(WP_REST_Request $request)
    {
        global $wpdb;

        try {
            // 從 GET 參數取得出貨單 IDs
            $shipment_ids = $request->get_param('shipment_ids');

            if (empty($shipment_ids)) {
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(['success' => false, 'message' => '請至少選擇一個出貨單']);
                exit;
            }

            // 確保是陣列（可能是逗號分隔的字串）
            if (!is_array($shipment_ids)) {
                $shipment_ids = explode(',', $shipment_ids);
            }
            $shipment_ids = array_map('intval', $shipment_ids);

            // 資料表名稱
            $table_shipments = $wpdb->prefix . 'buygo_shipments';
            $table_shipment_items = $wpdb->prefix . 'buygo_shipment_items';
            $table_customers = $wpdb->prefix . 'fct_customers';
            $table_order_items = $wpdb->prefix . 'fct_order_items';

            // 生成檔名
            $filename = 'shipments_' . date('Ymd_His') . '.csv';

            // 設定 HTTP Headers（直接輸出，不使用 WP_REST_Response）
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            // 開啟輸出流（直接輸出到瀏覽器）
            $output = fopen('php://output', 'w');

            // 寫入 UTF-8 BOM（讓 Excel 正確識別 UTF-8）
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // 寫入標題行
            fputcsv($output, [
                '出貨單號',
                '客戶姓名',
                '客戶電話',
                '客戶地址',
                'Email',
                '商品名稱',
                '數量',
                '單價',
                '小計',
                '出貨日期',
                '物流方式',
                '追蹤號碼',
                '狀態'
            ]);

            // 查詢每個出貨單
            foreach ($shipment_ids as $shipment_id) {
                // 取得出貨單基本資訊
                $shipment = $wpdb->get_row($wpdb->prepare(
                    "SELECT s.*,
                            CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as customer_name,
                            c.phone as customer_phone,
                            c.address as customer_address,
                            c.email as customer_email
                     FROM {$table_shipments} s
                     LEFT JOIN {$table_customers} c ON s.customer_id = c.id
                     WHERE s.id = %d",
                    $shipment_id
                ), ARRAY_A);

                if (!$shipment) {
                    continue;
                }

                // 取得出貨單商品項目
                $items = $wpdb->get_results($wpdb->prepare(
                    "SELECT si.*,
                            oi.title as product_name,
                            oi.price
                     FROM {$table_shipment_items} si
                     LEFT JOIN {$table_order_items} oi ON si.order_item_id = oi.id
                     WHERE si.shipment_id = %d",
                    $shipment_id
                ), ARRAY_A);

                // 如果沒有商品，至少輸出一行出貨單資訊
                if (empty($items)) {
                    fputcsv($output, [
                        $shipment['shipment_number'] ?? '',
                        trim($shipment['customer_name'] ?? ''),
                        $shipment['customer_phone'] ?? '',
                        $shipment['customer_address'] ?? '',
                        $shipment['customer_email'] ?? '',
                        '',
                        '',
                        '',
                        '',
                        $shipment['shipped_at'] ?? $shipment['created_at'] ?? '',
                        $shipment['shipping_method'] ?? '',
                        $shipment['tracking_number'] ?? '',
                        $this->get_status_label($shipment['status'] ?? 'pending')
                    ]);
                } else {
                    // 每個商品一行
                    foreach ($items as $index => $item) {
                        $price = floatval($item['price'] ?? 0) / 100; // 轉換為元
                        $quantity = intval($item['quantity'] ?? 0);
                        $subtotal = $price * $quantity;

                        // 第一個商品顯示完整出貨單資訊，後續商品只顯示商品資訊
                        if ($index === 0) {
                            fputcsv($output, [
                                $shipment['shipment_number'] ?? '',
                                trim($shipment['customer_name'] ?? ''),
                                $shipment['customer_phone'] ?? '',
                                $shipment['customer_address'] ?? '',
                                $shipment['customer_email'] ?? '',
                                $item['product_name'] ?? '未知商品',
                                $quantity,
                                $price,
                                $subtotal,
                                $shipment['shipped_at'] ?? $shipment['created_at'] ?? '',
                                $shipment['shipping_method'] ?? '',
                                $shipment['tracking_number'] ?? '',
                                $this->get_status_label($shipment['status'] ?? 'pending')
                            ]);
                        } else {
                            fputcsv($output, [
                                '', // 出貨單號 (空白，避免重複)
                                '', // 客戶姓名
                                '', // 客戶電話
                                '', // 客戶地址
                                '', // Email
                                $item['product_name'] ?? '未知商品',
                                $quantity,
                                $price,
                                $subtotal,
                                '', // 出貨日期
                                '', // 物流方式
                                '', // 追蹤號碼
                                ''  // 狀態
                            ]);
                        }
                    }
                }

                // 每個出貨單後加一個空行
                fputcsv($output, ['', '', '', '', '', '', '', '', '', '', '', '', '']);
            }

            fclose($output);
            exit; // 直接結束，不返回 WP_REST_Response

        } catch (\Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['success' => false, 'message' => '匯出失敗：' . $e->getMessage()]);
            exit;
        }
    }

    /**
     * 取得狀態標籤
     *
     * @param string $status 狀態
     * @return string 狀態標籤
     */
    private function get_status_label($status)
    {
        $labels = [
            'pending' => '待出貨',
            'shipped' => '已出貨',
            'archived' => '已存檔'
        ];

        return $labels[$status] ?? $status;
    }
}
