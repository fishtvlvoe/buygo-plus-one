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
        register_rest_route($this->namespace, '/products/(?P<id>\\d+)', [
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
        
        // POST /products/{id}/image - 上傳商品圖片
        register_rest_route($this->namespace, '/products/(?P<id>\\d+)/image', [
            'methods' => 'POST',
            'callback' => [$this, 'upload_image'],
            'permission_callback' => [__CLASS__, 'check_permission'],
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
            'permission_callback' => [__CLASS__, 'check_permission'],
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
            'permission_callback' => [__CLASS__, 'check_permission'],
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
            
            // 臨時：查詢資料表結構（確認欄位名稱）
            global $wpdb;
            $table_name = $wpdb->prefix . 'fct_order_items';
            $columns = $wpdb->get_col("DESCRIBE {$table_name}", 0);
            error_log('OrderItem 資料表欄位: ' . print_r($columns, true));
            
            // 查詢訂單項目（使用正確的欄位名稱 object_id）
            error_log('查詢訂單項目...');
            $orderItems = \FluentCart\App\Models\OrderItem::where('object_id', $product_id)
                ->with(['order', 'order.customer'])
                ->get();
            
            error_log('找到訂單項目數量: ' . $orderItems->count());
            
            // 整理客戶資料
            $buyers = [];
            $buyerMap = [];
            
            foreach ($orderItems as $item) {
                error_log('處理訂單項目 ID: ' . $item->id);
                
                if (!$item->order) {
                    error_log('警告：訂單項目沒有關聯訂單');
                    continue;
                }
                
                if (!$item->order->customer) {
                    error_log('警告：訂單沒有關聯客戶');
                    continue;
                }
                
                $customer = $item->order->customer;
                $customerId = $customer->id;
                
                error_log('客戶 ID: ' . $customerId . ', 名稱: ' . ($customer->full_name ?? $customer->email));
                
                // 如果客戶已存在，累加數量
                if (isset($buyerMap[$customerId])) {
                    $buyerMap[$customerId]['quantity'] += $item->quantity;
                    $buyerMap[$customerId]['order_count']++;
                } else {
                    $buyerMap[$customerId] = [
                        'customer_id' => $customerId,
                        'customer_name' => $customer->full_name ?? $customer->email,
                        'customer_email' => $customer->email,
                        'quantity' => $item->quantity,
                        'order_count' => 1,
                        'latest_order_date' => $item->order->created_at
                    ];
                }
            }
            
            // 轉換為陣列並排序
            $buyers = array_values($buyerMap);
            usort($buyers, function($a, $b) {
                return $b['quantity'] - $a['quantity'];
            });
            
            error_log('最終客戶數量: ' . count($buyers));
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $buyers,
                'total' => count($buyers)
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
     * 權限檢查
     */
    public static function check_permission() {
        return \BuyGoPlus\Api\API::check_permission_for_api();
    }
}
