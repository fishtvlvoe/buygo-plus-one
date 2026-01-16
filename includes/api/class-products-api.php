<?php
namespace BuyGoPlus\Api;

use BuyGoPlus\Services\ProductService;

if (!defined('ABSPATH')) {
    exit;
}

class Products_API {
    private $namespace = 'buygo-plus-one/v1';
    
    public function register_routes() {
        // GET /products - 取得商品列表
        register_rest_route($this->namespace, '/products', [
            'methods' => 'GET',
            'callback' => [$this, 'get_products'],
            'permission_callback' => [__CLASS__, 'check_permission'],
            'args' => [
                'page' => [
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'per_page' => [
                    'default' => 10,
                    'sanitize_callback' => 'absint',
                ],
                'search' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ]
            ]
        ]);
        
        // PUT /products/{id} - 更新商品
        register_rest_route($this->namespace, '/products/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_product'],
            'permission_callback' => [__CLASS__, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'purchased' => [
                    'sanitize_callback' => 'absint',
                ],
                'status' => [
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return in_array($param, ['published', 'private']);
                    }
                ]
            ]
        ]);
        
        // POST /products/batch-delete - 批次刪除
        register_rest_route($this->namespace, '/products/batch-delete', [
            'methods' => 'POST',
            'callback' => [$this, 'batch_delete'],
            'permission_callback' => [__CLASS__, 'check_permission'],
            'args' => [
                'ids' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_array($param) && !empty($param);
                    }
                ]
            ]
        ]);
        
        // GET /products/export - 匯出 CSV
        register_rest_route($this->namespace, '/products/export', [
            'methods' => 'GET',
            'callback' => [$this, 'export_csv'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);
    }
    
    /**
     * 取得商品列表
     */
    public function get_products($request) {
        try {
            $productService = new ProductService();
            
            $filters = [
                'status' => $request->get_param('status') ?? 'all',
                'search' => $request->get_param('search') ?? ''
            ];
            
            $viewMode = 'frontend'; // BuyGo+1 固定使用 frontend 模式
            
            $products = $productService->getProductsWithOrderCount($filters, $viewMode);
            
            // 轉換資料格式以符合前端需求
            $formattedProducts = [];
            foreach ($products as $product) {
                // 轉換 status：WordPress 使用 'publish'，前端需要 'published'
                $status = 'private';
                if ($product['status'] === 'publish') {
                    $status = 'published';
                } elseif ($product['status'] === 'private' || $product['status'] === 'draft') {
                    $status = 'private';
                }
                
                $formattedProducts[] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'image' => $product['image'],
                    'price' => $product['price'], // ProductService 已經轉換為元
                    'currency' => $product['currency'],
                    'status' => $status,
                    'ordered' => $product['ordered'] ?? 0,
                    'purchased' => $product['purchased'] ?? 0
                ];
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $formattedProducts,
                'total' => count($formattedProducts),
                'page' => $request->get_param('page'),
                'per_page' => $request->get_param('per_page'),
                'pages' => 1 // TODO: 後續加入分頁
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 更新商品
     */
    public function update_product($request) {
        try {
            $id = $request->get_param('id');
            $params = $request->get_json_params();
            
            $productService = new ProductService();
            
            $updateData = [];
            if (isset($params['purchased'])) {
                $updateData['purchased'] = (int) $params['purchased'];
            }
            if (isset($params['status'])) {
                $updateData['status'] = $params['status'] === 'published' ? 'publish' : 'private';
            }
            
            $result = $productService->updateProduct($id, $updateData);
            
            if ($result) {
                return new \WP_REST_Response([
                    'success' => true,
                    'message' => '商品更新成功',
                    'data' => [
                        'id' => $id,
                        'purchased' => $params['purchased'] ?? null,
                        'status' => $params['status'] ?? null
                    ]
                ], 200);
            } else {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '商品更新失敗'
                ], 400);
            }
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 批次刪除
     */
    public function batch_delete($request) {
        $params = $request->get_json_params();
        $ids = $params['ids'] ?? [];
        
        // TODO: 從資料庫或 FluentCart API 刪除商品
        // 目前只回傳成功訊息
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => '批次刪除成功',
            'data' => [
                'deleted_ids' => $ids
            ]
        ], 200);
    }
    
    /**
     * 匯出 CSV
     */
    public function export_csv($request) {
        // TODO: 從資料庫或 FluentCart API 取得商品並匯出 CSV
        // 目前先回傳假資料
        
        $mock_products = [
            ['商品名稱', 'ID', '價格', '已下單', '已採購', '預訂'],
            ['測試商品 A', 1, 1000, 10, 5, 5],
            ['測試商品 B', 2, 2000, 20, 15, 5],
            ['測試商品 C', 3, 3000, 5, 3, 2]
        ];
        
        // 設定 CSV 標頭
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="buygo_products_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // 輸出 BOM（讓 Excel 正確顯示中文）
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        foreach ($mock_products as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * 權限檢查
     */
    public static function check_permission() {
        return \BuyGoPlus\Api\API::check_permission_for_api();
    }
}
