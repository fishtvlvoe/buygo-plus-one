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
            
            // 分頁處理
            $page = max(1, (int) ($request->get_param('page') ?? 1));
            $per_page_param = (int) ($request->get_param('per_page') ?? 10);
            $total = count($formattedProducts);
            
            // 如果 per_page 為 -1，顯示全部（不分頁）
            if ($per_page_param === -1) {
                $per_page = $total;
                $total_pages = 1;
                $paged_products = $formattedProducts;
            } else {
                $per_page = max(1, min(100, $per_page_param));
                $total_pages = max(1, ceil($total / $per_page));
                
                // 計算分頁範圍
                $offset = ($page - 1) * $per_page;
                $paged_products = array_slice($formattedProducts, $offset, $per_page);
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $paged_products,
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'pages' => $total_pages
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
        try {
            $params = $request->get_json_params();
            $ids = $params['ids'] ?? [];
            
            if (empty($ids)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '未選擇任何商品'
                ], 400);
            }
            
            // 驗證 ID 都是數字
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids, function($id) {
                return $id > 0;
            });
            
            if (empty($ids)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '無效的商品 ID'
                ], 400);
            }
            
            // 使用 FluentCart ProductVariation Model 刪除商品
            $deleted_count = 0;
            $failed_ids = [];
            
            foreach ($ids as $id) {
                try {
                    $variation = \FluentCart\App\Models\ProductVariation::find($id);
                    if ($variation) {
                        // 將商品狀態設為 inactive（軟刪除）
                        $variation->item_status = 'inactive';
                        $variation->save();
                        $deleted_count++;
                    } else {
                        $failed_ids[] = $id;
                    }
                } catch (\Exception $e) {
                    $failed_ids[] = $id;
                }
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => '批次刪除成功',
                'data' => [
                    'deleted_count' => $deleted_count,
                    'deleted_ids' => array_diff($ids, $failed_ids),
                    'failed_ids' => $failed_ids
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '批次刪除失敗：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 匯出 CSV
     */
    public function export_csv($request) {
        try {
            $productService = new ProductService();
            
            // 取得所有商品（不篩選）
            $filters = [
                'status' => 'all',
                'search' => ''
            ];
            
            $viewMode = 'frontend';
            $products = $productService->getProductsWithOrderCount($filters, $viewMode);
            
            // 設定 CSV 標頭
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="buygo_products_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // 輸出 BOM（讓 Excel 正確顯示中文）
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // CSV 標題列
            $headers = [
                'ID',
                '商品名稱',
                '價格',
                '幣別',
                '庫存',
                '已下單',
                '已採購',
                '預訂',
                '狀態',
                '建立時間',
                '更新時間'
            ];
            fputcsv($output, $headers);
            
            // 資料行
            foreach ($products as $product) {
                $purchased = (int) get_post_meta($product['post_id'], '_buygo_purchased', true);
                $reserved = max(0, ($product['ordered'] ?? 0) - $purchased);
                $status = $product['status'] === 'publish' ? '已上架' : '已下架';
                
                $row = [
                    $product['id'],
                    $product['name'],
                    $product['price'], // 已經是元
                    $product['currency'],
                    $product['inventory'] ?? 0,
                    $product['ordered'] ?? 0,
                    $purchased,
                    $reserved,
                    $status,
                    $product['created_at'] ?? '',
                    $product['updated_at'] ?? ''
                ];
                fputcsv($output, $row);
            }
            
            fclose($output);
            exit;
            
        } catch (\Exception $e) {
            // 如果發生錯誤，回傳 JSON 錯誤訊息
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => '匯出失敗：' . $e->getMessage()
            ]);
            exit;
        }
    }
    
    /**
     * 權限檢查
     */
    public static function check_permission() {
        return \BuyGoPlus\Api\API::check_permission_for_api();
    }
}
