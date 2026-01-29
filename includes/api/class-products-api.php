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
            'permission_callback' => [API::class, 'check_permission'],
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
            'permission_callback' => [API::class, 'check_permission'],
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
            'permission_callback' => [API::class, 'check_permission'],
        ]);
        
        // POST /products/{id}/image - 上傳商品圖片
        register_rest_route($this->namespace, '/products/(?P<id>\\d+)/image', [
            'methods' => 'POST',
            'callback' => [$this, 'upload_image'],
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
        
        // DELETE /products/{id}/image - 刪除商品圖片
        register_rest_route($this->namespace, '/products/(?P<id>\\d+)/image', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_image'],
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
        
        // GET /products/{id}/buyers - 取得下單客戶列表
        register_rest_route($this->namespace, '/products/(?P<id>\\d+)/buyers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_buyers'],
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
        
        // GET /products/{id}/orders - 取得商品訂單列表（用於分配介面）
        register_rest_route($this->namespace, '/products/(?P<id>\\d+)/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'get_product_orders'],
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
        
        // POST /products/allocate - 分配庫存
        register_rest_route($this->namespace, '/products/allocate', [
            'methods' => 'POST',
            'callback' => [$this, 'allocate_stock'],
            'permission_callback' => [API::class, 'check_permission'],
        ]);

        // POST /products/{id}/allocate-all - 一鍵分配（將某客戶的所有訂單全部分配）
        register_rest_route($this->namespace, '/products/(?P<id>\\d+)/allocate-all', [
            'methods' => 'POST',
            'callback' => [$this, 'allocate_all_for_customer'],
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

        // GET /products/{id}/variations - 取得商品的 Variation 列表
        register_rest_route($this->namespace, '/products/(?P<id>\\d+)/variations', [
            'methods' => 'GET',
            'callback' => [$this, 'get_product_variations'],
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

        // GET /variations/{id}/stats - 取得 Variation 統計資料
        register_rest_route($this->namespace, '/variations/(?P<id>\\d+)/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_variation_stats'],
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

        // GET /products/limit-check - 檢查賣家商品數量限制 (Phase 19)
        register_rest_route($this->namespace, '/products/limit-check', [
            'methods' => 'GET',
            'callback' => [$this, 'check_seller_limit'],
            'permission_callback' => [API::class, 'check_permission'],
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
                
                // 檢查是否為多樣式商品
                $hasVariations = false;
                $variations = [];
                $defaultVariation = null;

                if (isset($product['post_id'])) {
                    $hasVariations = $productService->isVariableProduct($product['post_id']);
                    if ($hasVariations) {
                        $variations = $productService->getVariations($product['post_id']);
                        $defaultVariation = !empty($variations) ? $variations[0] : null;
                    }
                }

                $formattedProduct = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'variation_title' => $product['variation_title'] ?? null,
                    'image' => $product['image'],
                    'price' => $product['price'],
                    'currency' => $product['currency'],
                    'status' => $status,
                    'ordered' => $product['ordered'] ?? 0,
                    'purchased' => $product['purchased'] ?? 0,
                    'allocated' => $allocated,
                    'reserved' => max(0, ($product['ordered'] ?? 0) - ($product['purchased'] ?? 0) - $allocated),
                    'has_variations' => $hasVariations,
                    'variations' => $variations,
                    'default_variation' => $defaultVariation,
                    'post_id' => $product['post_id'] ?? null
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
                
                // 檢查是否為多樣式商品，並加入 variations 資料
                $hasVariations = false;
                $variations = [];
                $defaultVariation = null;

                if (isset($product['post_id'])) {
                    $hasVariations = $productService->isVariableProduct($product['post_id']);
                    if ($hasVariations) {
                        $variations = $productService->getVariations($product['post_id']);
                        $defaultVariation = !empty($variations) ? $variations[0] : null;
                    }
                }

                $formattedProducts[] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'variation_title' => $product['variation_title'] ?? null,
                    'image' => $product['image'],
                    'price' => $product['price'], // ProductService 已經轉換為元
                    'currency' => $product['currency'],
                    'status' => $status,
                    'ordered' => $product['ordered'] ?? 0,
                    'purchased' => $product['purchased'] ?? 0,
                    'allocated' => $allocated,
                    'shipped' => $product['shipped'] ?? 0,
                    'pending' => $product['pending'] ?? 0,
                    'reserved' => max(0, ($product['ordered'] ?? 0) - ($product['purchased'] ?? 0)),
                    'has_variations' => $hasVariations,
                    'variations' => $variations,
                    'default_variation' => $defaultVariation,
                    'post_id' => $product['post_id'] ?? null
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
                'product' => $result['product'] ?? null,
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
     *
     * 此方法調用 AllocationService::updateOrderAllocations()
     * 該方法會：
     * 1. 更新訂單項目的 _allocated_qty
     * 2. 更新商品的 _buygo_allocated
     * 3. 自動建立子訂單（利用 FluentCart 的 parent_id 機制）
     */
    public function allocate_stock($request) {
        try {
            $params = $request->get_json_params();

            // Debug: 記錄收到的參數
            error_log('=== 商品分配 API 開始（使用 AllocationService）===');
            error_log('收到的參數: ' . json_encode($params, JSON_UNESCAPED_UNICODE));

            // 1. 取得參數
            $product_id = (int)($params['product_id'] ?? 0);
            $raw_allocations = $params['allocations'] ?? [];

            if (empty($raw_allocations) || !is_array($raw_allocations)) {
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

            // 2. 轉換前端傳來的格式為 AllocationService 需要的格式
            // AllocationService 需要：['order_id' => quantity, ...]
            $allocations = [];
            foreach ($raw_allocations as $key => $value) {
                if (is_array($value)) {
                    // 陣列格式：[{ order_id: 124, allocated: 1 }] 或 [{ order_id: 124, quantity: 1 }]
                    $order_id = (int)($value['order_id'] ?? 0);
                    $quantity = (int)($value['allocated'] ?? $value['quantity'] ?? 0);
                } else {
                    // 物件格式：{ "124": 1, "116": 1 }
                    $order_id = (int)$key;
                    $quantity = (int)$value;
                }

                if ($order_id > 0 && $quantity > 0) {
                    $allocations[$order_id] = $quantity;
                }
            }

            if (empty($allocations)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '沒有有效的分配資料'
                ], 400);
            }

            error_log('轉換後的分配資料: ' . json_encode($allocations, JSON_UNESCAPED_UNICODE));

            // 3. 調用 AllocationService 進行分配（會自動建立子訂單）
            $allocationService = new \BuyGoPlus\Services\AllocationService();
            $result = $allocationService->updateOrderAllocations($product_id, $allocations);

            // 4. 處理結果
            if (is_wp_error($result)) {
                error_log('AllocationService 錯誤: ' . $result->get_error_message());
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $result->get_error_message()
                ], 400);
            }

            // 5. 成功返回
            $total_allocated = array_sum($allocations);
            $child_orders = $result['child_orders'] ?? [];

            error_log("=== 商品分配 API 完成 ===");
            error_log("總共分配: {$total_allocated} 個配額");
            error_log("建立的子訂單數: " . count($child_orders));

            $message = "成功分配 {$total_allocated} 個配額";
            if (!empty($child_orders)) {
                $child_order_numbers = array_map(function($co) {
                    return $co['invoice_no'] ?? $co['id'];
                }, $child_orders);
                $message .= "，已建立子訂單：" . implode(', ', $child_order_numbers);
            }

            return new \WP_REST_Response([
                'success' => true,
                'message' => $message,
                'allocated_count' => $total_allocated,
                'new_total_allocated' => $result['total_allocated'] ?? $total_allocated,
                'child_orders' => $child_orders
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
     * 一鍵分配：將某客戶購買某商品的所有訂單全部分配
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function allocate_all_for_customer($request) {
        global $wpdb;

        try {
            $product_id = (int)$request->get_param('id');
            $params = $request->get_json_params();
            $order_item_id = (int)($params['order_item_id'] ?? 0);
            $customer_id = (int)($params['customer_id'] ?? 0);

            if ($product_id <= 0) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '無效的商品 ID'
                ], 400);
            }

            $table_items = $wpdb->prefix . 'fct_order_items';
            $table_orders = $wpdb->prefix . 'fct_orders';

            // 如果有 order_item_id，只分配該單筆訂單項目
            if ($order_item_id > 0) {
                $order_items = $wpdb->get_results($wpdb->prepare(
                    "SELECT oi.id as order_item_id, oi.order_id, oi.quantity, o.invoice_no
                     FROM {$table_items} oi
                     INNER JOIN {$table_orders} o ON oi.order_id = o.id
                     WHERE oi.id = %d
                       AND oi.object_id = %d
                       AND o.parent_id IS NULL
                       AND o.status NOT IN ('cancelled', 'refunded')",
                    $order_item_id,
                    $product_id
                ));
            } elseif ($customer_id > 0) {
                // 舊邏輯：分配該客戶的所有訂單
                $order_items = $wpdb->get_results($wpdb->prepare(
                    "SELECT oi.id as order_item_id, oi.order_id, oi.quantity, o.invoice_no
                     FROM {$table_items} oi
                     INNER JOIN {$table_orders} o ON oi.order_id = o.id
                     WHERE oi.object_id = %d
                       AND o.customer_id = %d
                       AND o.parent_id IS NULL
                       AND o.status NOT IN ('cancelled', 'refunded')",
                    $product_id,
                    $customer_id
                ));
            } else {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '需要提供 order_item_id 或 customer_id'
                ], 400);
            }

            if (empty($order_items)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '找不到訂單'
                ], 404);
            }

            // 更新每個訂單項目的 line_meta 中的 _allocated_qty
            $total_allocated = 0;
            $updated_orders = [];
            $skipped_orders = [];

            foreach ($order_items as $item) {
                // 讀取現有的 line_meta
                $existing_meta = $wpdb->get_var($wpdb->prepare(
                    "SELECT line_meta FROM {$table_items} WHERE id = %d",
                    $item->order_item_id
                ));

                $meta_data = [];
                if (!empty($existing_meta)) {
                    $meta_data = json_decode($existing_meta, true) ?: [];
                }

                // 取得已出貨數量
                $shipped_qty = (int)($meta_data['_shipped_qty'] ?? 0);
                $current_allocated = (int)($meta_data['_allocated_qty'] ?? 0);
                $quantity = (int)$item->quantity;

                // 計算可分配數量 = 總數量 - 已出貨數量
                $max_allocatable = $quantity - $shipped_qty;

                // 如果已經全部出貨，跳過
                if ($max_allocatable <= 0) {
                    $skipped_orders[] = [
                        'order_id' => $item->order_id,
                        'invoice_no' => $item->invoice_no,
                        'reason' => '已全部出貨'
                    ];
                    continue;
                }

                // 如果已經分配到最大值，跳過
                if ($current_allocated >= $max_allocatable) {
                    $skipped_orders[] = [
                        'order_id' => $item->order_id,
                        'invoice_no' => $item->invoice_no,
                        'reason' => '已全部分配'
                    ];
                    continue;
                }

                // 設定 _allocated_qty 為可分配最大值（總數量 - 已出貨數量）
                $meta_data['_allocated_qty'] = $max_allocatable;

                // 更新 line_meta
                $result = $wpdb->update(
                    $table_items,
                    ['line_meta' => json_encode($meta_data)],
                    ['id' => $item->order_item_id],
                    ['%s'],
                    ['%d']
                );

                if ($result !== false) {
                    // 計算實際新增分配的數量
                    $newly_allocated = $max_allocatable - $current_allocated;
                    $total_allocated += $newly_allocated;
                    $updated_orders[] = [
                        'order_id' => $item->order_id,
                        'invoice_no' => $item->invoice_no,
                        'quantity' => $newly_allocated,
                        'total_allocated' => $max_allocatable
                    ];
                }
            }

            // 更新商品的已分配總數
            $this->updateProductAllocatedCount($product_id);

            return new \WP_REST_Response([
                'success' => true,
                'message' => "已分配 {$total_allocated} 個商品給此客戶",
                'total_allocated' => $total_allocated,
                'updated_orders' => $updated_orders
            ], 200);

        } catch (\Exception $e) {
            error_log('一鍵分配錯誤: ' . $e->getMessage());

            return new \WP_REST_Response([
                'success' => false,
                'message' => '分配時發生錯誤：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新商品的已分配總數（從 line_meta 讀取 _allocated_qty）
     */
    private function updateProductAllocatedCount($product_id) {
        global $wpdb;

        $table_items = $wpdb->prefix . 'fct_order_items';
        $table_orders = $wpdb->prefix . 'fct_orders';

        // 取得該商品所有訂單項目的 line_meta
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT oi.line_meta
             FROM {$table_items} oi
             INNER JOIN {$table_orders} o ON oi.order_id = o.id
             WHERE oi.object_id = %d
               AND o.status NOT IN ('cancelled', 'refunded')
               AND o.parent_id IS NULL",
            $product_id
        ));

        // 計算總已分配數量
        $total = 0;
        foreach ($items as $item) {
            $meta_data = [];
            if (!empty($item->line_meta)) {
                $meta_data = json_decode($item->line_meta, true) ?: [];
            }
            $total += (int)($meta_data['_allocated_qty'] ?? 0);
        }

        // 更新商品的 post meta
        $product = \FluentCart\App\Models\ProductVariation::find($product_id);
        if ($product && $product->post_id) {
            update_post_meta($product->post_id, '_buygo_allocated', (int)$total);
        }
    }

    /**
     * 取得商品的 Variation 列表
     */
    public function get_product_variations($request) {
        try {
            $product_id = (int)$request->get_param('id');

            // 檢查商品是否存在
            $product = \FluentCart\App\Models\Product::find($product_id);
            if (!$product) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '商品不存在'
                ], 404);
            }

            $productService = new ProductService();
            $variations = $productService->getVariations($product_id);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $variations
            ], 200);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取得 Variation 統計資料
     */
    public function get_variation_stats($request) {
        try {
            $variation_id = (int)$request->get_param('id');

            // 檢查 variation 是否存在
            $variation = \FluentCart\App\Models\ProductVariation::find($variation_id);
            if (!$variation) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Variation 不存在'
                ], 404);
            }

            $productService = new ProductService();
            $stats = $productService->getVariationStats($variation_id);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 檢查賣家商品數量限制 (Phase 19)
     *
     * GET /wp-json/buygo-plus-one/v1/products/limit-check
     */
    public function check_seller_limit() {
        try {
            $user_id = get_current_user_id();
            if (!$user_id) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '未登入'
                ], 401);
            }

            $product_service = new ProductService();
            $limit_status = $product_service->canAddProduct($user_id);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $limit_status
            ], 200);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
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
