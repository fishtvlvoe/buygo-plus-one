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
                    'default' => 10,
                    'sanitize_callback' => 'absint',
                ],
                'search' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'id' => [
                    'default' => null,
                    'sanitize_callback' => 'absint',
                ]
            ]
        ]);
        
        // PUT /products/{id} - 更新商品
        register_rest_route($this->namespace, '/products/(?P<id>\\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_product'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'name' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'price' => [
                    'sanitize_callback' => function($value) {
                        return floatval($value);
                    },
                    'validate_callback' => function($param) {
                        return $param === null || (is_numeric($param) && $param >= 0);
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
            'permission_callback' => '__return_true',
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
            'permission_callback' => '__return_true',
        ]);
        
        // POST /products/{id}/image - 上傳商品圖片
        register_rest_route($this->namespace, '/products/(?P<id>\\d+)/image', [
            'methods' => 'POST',
            'callback' => [$this, 'upload_image'],
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
        
        // DELETE /products/{id}/image - 刪除商品圖片
        register_rest_route($this->namespace, '/products/(?P<id>\\d+)/image', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_image'],
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
        
        // GET /products/{id}/buyers - 取得下單客戶列表
        register_rest_route($this->namespace, '/products/(?P<id>\\d+)/buyers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_buyers'],
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
        
        // GET /products/{id}/orders - 取得商品訂單列表（用於分配介面）
        register_rest_route($this->namespace, '/products/(?P<id>\\d+)/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'get_product_orders'],
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
        
        // POST /products/allocate - 分配庫存
        register_rest_route($this->namespace, '/products/allocate', [
            'methods' => 'POST',
            'callback' => [$this, 'allocate_stock'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    /**
     * 取得商品列表
     */
    public function get_products($request) {
        try {
            $productService = new ProductService();
            
            // 如果有 ID 參數，只取得單一商品
            $productId = $request->get_param('id');
            if (!empty($productId)) {
                $product = $productService->getProductById((int) $productId);
                
                if (!$product) {
                    return new \WP_REST_Response([
                        'success' => false,
                        'message' => '商品不存在'
                    ], 404);
                }
                
                // 轉換 status：WordPress 使用 'publish'，前端需要 'published'
                $status = 'private';
                if ($product['status'] === 'publish') {
                    $status = 'published';
                } elseif ($product['status'] === 'private' || $product['status'] === 'draft') {
                    $status = 'private';
                }
                
                // 取得已分配數量（ProductService 已經讀取並回傳，如果沒有則從 post meta 讀取）
                $allocated = $product['allocated'] ?? 0;
                if ($allocated === 0 && isset($product['post_id'])) {
                    // 如果 ProductService 沒有回傳 allocated，則從 post meta 讀取
                    $allocated = (int)get_post_meta($product['post_id'], '_buygo_allocated', true);
                }
                
                $formattedProduct = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'image' => $product['image'],
                    'price' => $product['price'],
                    'currency' => $product['currency'],
                    'status' => $status,
                    'ordered' => $product['ordered'] ?? 0,
                    'purchased' => $product['purchased'] ?? 0,
                    'allocated' => $allocated,
                    'reserved' => max(0, ($product['ordered'] ?? 0) - ($product['purchased'] ?? 0) - $allocated)
                ];
                
                return new \WP_REST_Response([
                    'success' => true,
                    'data' => [$formattedProduct],
                    'total' => 1,
                    'page' => 1,
                    'per_page' => 1,
                    'pages' => 1
                ], 200);
            }
            
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
                
                // 取得已分配數量（ProductService 已經讀取並回傳，如果沒有則從 post meta 讀取）
                $allocated = $product['allocated'] ?? 0;
                if ($allocated === 0 && isset($product['post_id'])) {
                    // 如果 ProductService 沒有回傳 allocated，則從 post meta 讀取
                    $allocated = (int)get_post_meta($product['post_id'], '_buygo_allocated', true);
                }
                
                $formattedProducts[] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'image' => $product['image'],
                    'price' => $product['price'], // ProductService 已經轉換為元
                    'currency' => $product['currency'],
                    'status' => $status,
                    'ordered' => $product['ordered'] ?? 0,
                    'purchased' => $product['purchased'] ?? 0,
                    'allocated' => $allocated,
                    'reserved' => max(0, ($product['ordered'] ?? 0) - ($product['purchased'] ?? 0) - $allocated)
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
        error_log('===== API update_product =====');
        error_log('ID: ' . $request->get_param('id'));
        
        try {
            $id = $request->get_param('id');
            $params = $request->get_json_params();
            
            error_log('參數: ' . print_r($params, true));
            
            $productService = new ProductService();
            
            $updateData = [];
            
            // 商品名稱
            if (isset($params['name'])) {
                $updateData['name'] = sanitize_text_field($params['name']);
                error_log('準備更新名稱: ' . $updateData['name']);
            }
            
            // 商品價格
            if (isset($params['price'])) {
                $updateData['price'] = (float) $params['price'];
                error_log('準備更新價格: ' . $updateData['price']);
            }
            
            // 已採購數量
            if (isset($params['purchased'])) {
                $updateData['purchased'] = (int) $params['purchased'];
                error_log('準備更新已採購: ' . $updateData['purchased']);
            }
            
            // 商品狀態
            if (isset($params['status'])) {
                $updateData['status'] = $params['status'] === 'published' ? 'publish' : 'private';
                error_log('準備更新狀態: ' . $params['status'] . ' -> ' . $updateData['status']);
            }
            
            error_log('更新資料: ' . print_r($updateData, true));
            
            $result = $productService->updateProduct($id, $updateData);
            
            error_log('updateProduct 返回: ' . ($result ? 'true' : 'false'));
            
            if ($result) {
                return new \WP_REST_Response([
                    'success' => true,
                    'message' => '商品更新成功',
                    'data' => [
                        'id' => $id,
                        'name' => $params['name'] ?? null,
                        'price' => $params['price'] ?? null,
                        'purchased' => $params['purchased'] ?? null,
                        'status' => $params['status'] ?? null
                    ]
                ], 200);
            } else {
                error_log('商品更新失敗：updateProduct 返回 false');
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '商品更新失敗'
                ], 400);
            }
            
        } catch (\Exception $e) {
            error_log('API update_product 錯誤: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
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
     * 上傳商品圖片
     */
    public function upload_image($request) {
        try {
            $id = $request->get_param('id');
            
            // 檢查是否有上傳檔案
            if (empty($_FILES['image'])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '沒有上傳檔案'
                ], 400);
            }
            
            $file = $_FILES['image'];
            
            // 檢查檔案大小（最大 5MB）
            $max_size = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $max_size) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '檔案大小超過 5MB'
                ], 400);
            }
            
            // 檢查檔案類型
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
            $file_type = wp_check_filetype($file['name']);
            
            if (empty($file_type['ext']) || !in_array(strtolower($file_type['ext']), $allowed_extensions)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '不支援的檔案格式，僅支援 JPG、PNG、WebP'
                ], 400);
            }
            
            // 也檢查 MIME 類型
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!empty($file['type']) && !in_array($file['type'], $allowed_mimes)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '不支援的檔案格式，僅支援 JPG、PNG、WebP'
                ], 400);
            }
            
            // 載入 WordPress 檔案處理函數
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            // 上傳檔案到 WordPress Media Library
            $attachment_id = media_handle_upload('image', 0);
            
            if (is_wp_error($attachment_id)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '上傳失敗：' . $attachment_id->get_error_message()
                ], 500);
            }
            
            // 取得商品的 post_id
            $product = \FluentCart\App\Models\ProductVariation::find($id);
            if (!$product) {
                // 刪除剛上傳的圖片
                wp_delete_attachment($attachment_id, true);
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '商品不存在'
                ], 404);
            }
            
            // 設定為商品的特色圖片
            set_post_thumbnail($product->post_id, $attachment_id);
            
            // 取得圖片 URL
            $image_url = wp_get_attachment_image_url($attachment_id, 'medium');
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => '圖片上傳成功',
                'data' => [
                    'image_url' => $image_url,
                    'attachment_id' => $attachment_id
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 刪除商品圖片
     */
    public function delete_image($request) {
        try {
            $id = $request->get_param('id');
            
            // 取得商品
            $product = \FluentCart\App\Models\ProductVariation::find($id);
            if (!$product) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '商品不存在'
                ], 404);
            }
            
            // 取得特色圖片 ID
            $thumbnail_id = get_post_thumbnail_id($product->post_id);
            
            if (!$thumbnail_id) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '商品沒有圖片'
                ], 400);
            }
            
            // 刪除特色圖片設定
            delete_post_thumbnail($product->post_id);
            
            // 刪除圖片檔案
            wp_delete_attachment($thumbnail_id, true);
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => '圖片刪除成功'
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 取得商品的下單客戶列表
     */
    public function get_buyers($request) {
        error_log('===== get_buyers API =====');
        
        try {
            $product_id = $request->get_param('id');
            error_log('Product ID: ' . $product_id);
            
            // 檢查商品是否存在
            error_log('檢查商品是否存在...');
            $product = \FluentCart\App\Models\ProductVariation::find($product_id);
            
            if (!$product) {
                error_log('錯誤：商品不存在');
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '商品不存在'
                ], 404);
            }
            
            error_log('找到商品: ' . $product->variation_title);
            
            // 建立 ProductService 實例
            $productService = new ProductService();
            $result = $productService->getProductBuyers($product_id);
            
            if (!$result['success']) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $result['message']
                ], 500);
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $result['data'],
                'total' => count($result['data'])
            ], 200);
            
        } catch (\Exception $e) {
            error_log('get_buyers 錯誤: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 取得商品訂單列表（用於分配介面）
     */
    public function get_product_orders($request) {
        try {
            $product_id = (int)$request->get_param('id');
            
            // 檢查商品是否存在
            $product = \FluentCart\App\Models\ProductVariation::find($product_id);
            if (!$product) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '商品不存在'
                ], 404);
            }
            
            $allocationService = new \BuyGoPlus\Services\AllocationService();
            $orders = $allocationService->getProductOrders($product_id);
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $orders
            ], 200);
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 分配庫存給訂單
     */
    public function allocate_stock($request) {
        try {
            $params = $request->get_json_params();
            
            // Debug: 記錄收到的參數
            error_log('=== 商品分配 API 開始 ===');
            error_log('收到的參數: ' . json_encode($params, JSON_UNESCAPED_UNICODE));
            
            // 1. 取得參數
            $product_id = (int)($params['product_id'] ?? 0);
            $allocations = $params['allocations'] ?? []; // 格式：[{ order_id: 123, allocated: 5 }, ...] 或 { "124": 1, "116": 1 }
            
            if (empty($allocations) || !is_array($allocations)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '缺少分配資料'
                ], 400);
            }
            
            if ($product_id <= 0) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '無效的商品 ID'
                ], 400);
            }
            
            // 2. 取得商品的 post_id
            $product = \FluentCart\App\Models\ProductVariation::find($product_id);
            if (!$product) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '商品不存在'
                ], 404);
            }
            
            $post_id = $product->post_id;
            
            // 3. 檢查配額是否足夠
            $purchased = (int)get_post_meta($post_id, '_buygo_purchased', true);
            $current_allocated = (int)get_post_meta($post_id, '_buygo_allocated', true);
            
            $total_new_allocated = 0;
            // 支援兩種格式：物件格式 { "124": 1 } 或陣列格式 [{ order_id: 124, allocated: 1 }]
            foreach ($allocations as $key => $value) {
                if (is_array($value)) {
                    // 陣列格式
                    $total_new_allocated += (int)($value['allocated'] ?? 0);
                } else {
                    // 物件格式（key 是 order_id，value 是 allocated_qty）
                    $total_new_allocated += (int)$value;
                }
            }
            
            $available = $purchased - $current_allocated;
            if ($total_new_allocated > $available) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => "配額不足！可分配數量：{$available}，需求數量：{$total_new_allocated}"
                ], 400);
            }
            
            // 4. 更新每筆訂單的 allocated_qty
            global $wpdb;
            $table_order_items = $wpdb->prefix . 'fct_order_items';
            
            $total_allocated_count = 0;
            
            // 支援三種格式：
            // 1. 物件格式：{ "124": 1, "116": 1 }
            // 2. 陣列格式：[{ order_id: 124, allocated: 1 }]
            // 3. 陣列格式：[{ order_id: 124, quantity: 1 }]（前端目前使用）
            foreach ($allocations as $key => $value) {
                // 判斷格式
                if (is_array($value)) {
                    // 陣列格式（同時支援 allocated 和 quantity 欄位）
                    $order_id = (int)($value['order_id'] ?? 0);
                    $allocated_qty = (int)($value['allocated'] ?? $value['quantity'] ?? 0);
                } else {
                    // 物件格式（key 是 order_id，value 是 allocated_qty）
                    $order_id = (int)$key;
                    $allocated_qty = (int)$value;
                }
                
                if ($allocated_qty <= 0 || $order_id <= 0) continue;
                
                // 找到該訂單中對應的 order_item（使用 object_id，這是 FluentCart 的標準欄位）
                $order_item = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_order_items} WHERE order_id = %d AND object_id = %d LIMIT 1",
                    $order_id,
                    $product_id
                ), ARRAY_A);
                
                if (!$order_item) {
                    error_log("找不到訂單項目: order_id={$order_id}, product_id={$product_id}");
                    continue;
                }
                
                // 更新 line_meta 中的 _allocated_qty（FluentCart 使用 line_meta 欄位）
                $meta_data = json_decode($order_item['line_meta'] ?? '{}', true) ?: [];
                $meta_data['_allocated_qty'] = $allocated_qty;
                
                $result = $wpdb->update(
                    $table_order_items,
                    ['line_meta' => json_encode($meta_data)],
                    ['id' => $order_item['id']],
                    ['%s'],
                    ['%d']
                );
                
                if ($result === false) {
                    error_log("更新訂單項目失敗: order_item_id={$order_item['id']}, error={$wpdb->last_error}");
                    continue;
                }
                
                // 記錄成功
                error_log("成功更新訂單項目: order_item_id={$order_item['id']}, order_id={$order_id}, allocated_qty={$allocated_qty}");
                
                $total_allocated_count += $allocated_qty;
            }
            
            // 5. 更新商品的 _buygo_allocated meta
            $new_allocated = $current_allocated + $total_allocated_count;
            update_post_meta($post_id, '_buygo_allocated', $new_allocated);
            
            error_log("=== 商品分配 API 完成 ===");
            error_log("總共分配: {$total_allocated_count} 個配額");
            error_log("更新的 post meta: post_id={$post_id}, _buygo_allocated={$new_allocated}");
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => "成功分配 {$total_allocated_count} 個配額",
                'allocated_count' => $total_allocated_count,
                'new_total_allocated' => $new_allocated  // 新增：回傳更新後的總分配數
            ], 200);
            
        } catch (\Exception $e) {
            error_log('商品分配錯誤: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            return new \WP_REST_Response([
                'success' => false,
                'message' => '分配時發生錯誤：' . $e->getMessage()
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
