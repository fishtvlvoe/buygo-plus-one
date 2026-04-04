<?php
namespace BuyGoPlus\Api;

use BuyGoPlus\Services\ProductService;
use BuyGoPlus\Services\FluentCartService;

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

        // POST /products/adjust-allocation - 調整/撤銷已分配數量
        register_rest_route($this->namespace, '/products/adjust-allocation', [
            'methods'             => 'POST',
            'callback'            => [$this, 'adjust_allocation'],
            'permission_callback' => [API::class, 'check_permission'],
            'args'                => [
                'product_id' => [
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (int) $param > 0;
                    },
                ],
                'order_id' => [
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (int) $param > 0;
                    },
                ],
                'new_quantity' => [
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (int) $param >= 0;
                    },
                ],
            ],
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

        // PUT /variations/{id} - 更新 Variation（用於更新採購數量）
        register_rest_route($this->namespace, '/variations/(?P<id>\\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_variation'],
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
    /**
     * 取得商品列表
     *
     * 使用 per-user Transient 快取（TTL 30 秒），降低頻繁 SWR 輪詢對資料庫的壓力。
     * 快取 key 包含 user_id 與查詢參數 hash，確保不同使用者、不同篩選條件各自獨立。
     * 單一商品查詢（帶 id 參數）跳過快取，直接查詢以確保即時性。
     */
    public function get_products($request) {
        try {
            $productService = new ProductService();

            // 如果有 ID 參數，只取得單一商品（不走快取，確保即時）
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

                // 多樣式商品：purchased 應從預設 variation 的 meta 讀取
                $purchased = $product['purchased'] ?? 0;
                if ($hasVariations && $defaultVariation) {
                    $varPurchased = (int) $productService->getVariationMeta($defaultVariation['id'], '_buygo_purchased', 0);
                    $purchased = $varPurchased;
                }

                $formattedProduct = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'variation_title' => $product['variation_title'] ?? null,
                    'image' => $product['image'],
                    'price' => $product['price'],
                    'currency' => $product['currency'],
                    'status' => $status,
                    'stock' => (int) ($product['stock'] ?? 0),
                    'ordered' => $product['ordered'] ?? 0,
                    'purchased' => $purchased,
                    'allocated' => $allocated,
                    'reserved' => max(0, ($product['ordered'] ?? 0) - $purchased - $allocated),
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
            
            // 建立 per-user 快取 key（包含查詢參數 hash，不同分頁/篩選各自快取）
            $user_id   = get_current_user_id();
            $cache_key = 'buygo_products_' . $user_id . '_' . md5(serialize($request->get_params()));

            // 嘗試從 transient 讀取快取
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return new \WP_REST_Response($cached, 200);
            }

            $filters = [
                'status' => $request->get_param('status') ?? 'all',
                'search' => $request->get_param('search') ?? ''
            ];

            $viewMode = 'frontend'; // BuyGo+1 固定使用 frontend 模式

            $products = $productService->getProductsWithOrderCount($filters, $viewMode);

            // 去重：同一個商品（post_id）只保留一個，避免多樣式商品重複顯示
            $uniqueProducts = [];
            $seenPostIds = [];
            foreach ($products as $product) {
                $postId = $product['post_id'] ?? null;
                if ($postId && !isset($seenPostIds[$postId])) {
                    $uniqueProducts[] = $product;
                    $seenPostIds[$postId] = true;
                } elseif (!$postId) {
                    // 沒有 post_id 的商品直接加入（不應該發生，但保險起見）
                    $uniqueProducts[] = $product;
                }
            }

            // 轉換資料格式以符合前端需求
            $formattedProducts = [];
            foreach ($uniqueProducts as $product) {
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

                // 多樣式商品：purchased 應從預設 variation 的 meta 讀取
                $purchased = $product['purchased'] ?? 0;
                if ($hasVariations && $defaultVariation) {
                    $varPurchased = (int) $productService->getVariationMeta($defaultVariation['id'], '_buygo_purchased', 0);
                    $purchased = $varPurchased;
                }

                $formattedProducts[] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'variation_title' => $product['variation_title'] ?? null,
                    'image' => $product['image'],
                    'price' => $product['price'], // ProductService 已經轉換為元
                    'currency' => $product['currency'],
                    'status' => $status,
                    'stock' => (int) ($product['stock'] ?? 0),
                    'ordered' => $product['ordered'] ?? 0,
                    'purchased' => $purchased,
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
            
            $response_data = [
                'success'  => true,
                'data'     => $paged_products,
                'total'    => $total,
                'page'     => $page,
                'per_page' => $per_page,
                'pages'    => $total_pages
            ];

            // 快取 30 秒（與前端 BuyGoCache.TTL 一致）
            set_transient($cache_key, $response_data, 30);

            return new \WP_REST_Response($response_data, 200);

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

            // 驗證商品所有權
            $check = API::verify_product_ownership((int) $id);
            if (is_wp_error($check)) {
                return $check;
            }

            $params = $request->get_json_params();

            $productService = new ProductService();

            $updateData = [];

            // 商品名稱
            if (isset($params['name'])) {
                $updateData['name'] = sanitize_text_field($params['name']);
            }

            // 商品價格
            if (isset($params['price'])) {
                $updateData['price'] = (float) $params['price'];
            }

            // 已採購數量
            if (isset($params['purchased'])) {
                $updateData['purchased'] = (int) $params['purchased'];
            }

            // 庫存數量（同步到 FluentCart）
            if (isset($params['stock'])) {
                $updateData['stock'] = (int) $params['stock'];
            }

            // 商品狀態
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
                        'name' => $params['name'] ?? null,
                        'price' => $params['price'] ?? null,
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
            $debugService = \BuyGoPlus\Services\DebugService::get_instance();
            $debugService->log('Products_API', 'update_product 錯誤', ['error' => $e->getMessage()], 'error');

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
            
            // 驗證所有商品所有權（批次操作前先全部驗證）
            foreach ($ids as $product_id) {
                $check = API::verify_product_ownership((int) $product_id);
                if (is_wp_error($check)) {
                    return $check;
                }
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

            // 驗證商品所有權
            $check = API::verify_product_ownership((int) $id);
            if (is_wp_error($check)) {
                return $check;
            }

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

            // 驗證商品所有權
            $check = API::verify_product_ownership((int) $id);
            if (is_wp_error($check)) {
                return $check;
            }

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
        try {
            $product_id = $request->get_param('id');

            // 驗證商品所有權
            $check = API::verify_product_ownership((int) $product_id);
            if (is_wp_error($check)) {
                return $check;
            }

            // 檢查商品是否存在
            $product = \FluentCart\App\Models\ProductVariation::find($product_id);

            if (!$product) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '商品不存在'
                ], 404);
            }

            // 建立 ProductService 實例
            $productService = new ProductService();
            $result = $productService->getProductBuyers($product_id);

            if (!$result['success']) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $result['message']
                ], 500);
            }

            $response = [
                'success' => true,
                'data' => $result['data'],
                'product' => $result['product'] ?? null,
                'total' => count($result['data'])
            ];
            // 多樣式商品：傳遞 variants 供前端篩選
            if (!empty($result['variants'])) {
                $response['variants'] = $result['variants'];
            }
            return new \WP_REST_Response($response, 200);

        } catch (\Exception $e) {
            $debugService = \BuyGoPlus\Services\DebugService::get_instance();
            $debugService->log('Products_API', 'get_buyers 錯誤', ['error' => $e->getMessage()], 'error');

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

            // 驗證商品所有權
            $check = API::verify_product_ownership($product_id);
            if (is_wp_error($check)) {
                return $check;
            }

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

            // 1. 取得參數
            $product_id = (int)($params['product_id'] ?? 0);
            $raw_allocations = $params['allocations'] ?? [];

            // 驗證商品所有權
            if ($product_id > 0) {
                $check = API::verify_product_ownership($product_id);
                if (is_wp_error($check)) {
                    return $check;
                }
            }

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

            // 3. 調用 AllocationService 進行分配（會自動建立子訂單）
            $allocationService = new \BuyGoPlus\Services\AllocationService();
            $result = $allocationService->updateOrderAllocations($product_id, $allocations);

            // 4. 處理結果
            if (is_wp_error($result)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $result->get_error_message()
                ], 400);
            }

            // 5. 成功返回
            $total_allocated = array_sum($allocations);
            $child_orders = $result['child_orders'] ?? [];

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
            $debugService = \BuyGoPlus\Services\DebugService::get_instance();
            $debugService->log('Products_API', 'allocate_stock 錯誤', ['error' => $e->getMessage()], 'error');

            return new \WP_REST_Response([
                'success' => false,
                'message' => '分配時發生錯誤：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 一鍵分配：將某客戶購買某商品的訂單全部分配
     * 統一走 AllocationService::updateOrderAllocations（建立子訂單）
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

            // 驗證商品所有權
            $check = API::verify_product_ownership($product_id);
            if (is_wp_error($check)) {
                return $check;
            }

            if ($product_id <= 0) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '無效的商品 ID'
                ], 400);
            }

            if ($order_item_id <= 0 && $customer_id <= 0) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '需要提供 order_item_id 或 customer_id'
                ], 400);
            }

            $table_items = $wpdb->prefix . 'fct_order_items';
            $table_orders = $wpdb->prefix . 'fct_orders';

            // 取得同一商品所有 variation IDs
            $allocationService = new \BuyGoPlus\Services\AllocationService();
            $varTable = $wpdb->prefix . 'fct_product_variations';
            $post_id_for_alloc = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$varTable} WHERE id = %d LIMIT 1", $product_id
            ));
            $allVarIds = [$product_id];
            if ($post_id_for_alloc) {
                $ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM {$varTable} WHERE post_id = %d AND item_status = 'active'", $post_id_for_alloc
                ));
                if (!empty($ids)) {
                    $allVarIds = array_map('intval', $ids);
                }
            }
            $varPlaceholders = implode(',', array_fill(0, count($allVarIds), '%d'));

            // 查詢需要分配的訂單項目
            if ($order_item_id > 0) {
                $order_items = $wpdb->get_results($wpdb->prepare(
                    "SELECT oi.id as order_item_id, oi.order_id, oi.object_id, oi.quantity, oi.line_meta
                     FROM {$table_items} oi
                     INNER JOIN {$table_orders} o ON oi.order_id = o.id
                     WHERE oi.id = %d
                       AND oi.object_id IN ($varPlaceholders)
                       AND o.parent_id IS NULL
                       AND o.status NOT IN ('cancelled', 'refunded')",
                    $order_item_id,
                    ...$allVarIds
                ));
            } else {
                $order_items = $wpdb->get_results($wpdb->prepare(
                    "SELECT oi.id as order_item_id, oi.order_id, oi.object_id, oi.quantity, oi.line_meta
                     FROM {$table_items} oi
                     INNER JOIN {$table_orders} o ON oi.order_id = o.id
                     WHERE oi.object_id IN ($varPlaceholders)
                       AND o.customer_id = %d
                       AND o.parent_id IS NULL
                       AND o.status NOT IN ('cancelled', 'refunded')",
                    ...array_merge($allVarIds, [$customer_id])
                ));
            }

            if (empty($order_items)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '找不到訂單'
                ], 404);
            }

            // 計算每筆訂單的待分配數量，組成 allocations 陣列
            $allocations = [];
            $skipped_orders = [];
            foreach ($order_items as $item) {
                $meta_data = json_decode($item->line_meta ?? '{}', true) ?: [];
                $quantity = (int)$item->quantity;

                // 查詢實際已出貨和已分配（子訂單）數量
                $actual_shipped = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(quantity), 0) FROM {$wpdb->prefix}buygo_shipment_items WHERE order_item_id = %d",
                    $item->order_item_id
                ));
                $child_allocated = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(child_oi.quantity), 0)
                     FROM {$wpdb->prefix}fct_orders child_o
                     INNER JOIN {$wpdb->prefix}fct_order_items child_oi ON child_o.id = child_oi.order_id
                     WHERE child_o.parent_id = %d AND child_o.type = 'split' AND child_oi.object_id = %d",
                    $item->order_id, (int)$item->object_id
                ));

                $already = max($child_allocated, (int)($meta_data['_allocated_qty'] ?? 0), $actual_shipped);
                $needed = $quantity - $already;

                if ($needed <= 0) {
                    $skipped_orders[] = ['order_id' => $item->order_id, 'reason' => '已全部分配'];
                    continue;
                }

                // 用訂單的實際 object_id 呼叫（確保多樣式商品正確）
                $allocations[(int)$item->order_id] = $needed;
            }

            if (empty($allocations)) {
                return new \WP_REST_Response([
                    'success' => true,
                    'message' => '所有訂單已全部分配',
                    'total_allocated' => 0,
                    'updated_orders' => []
                ], 200);
            }

            // 統一走 updateOrderAllocations（建立子訂單 + 同步 meta）
            $result = $allocationService->updateOrderAllocations($product_id, $allocations);

            if (is_wp_error($result)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $result->get_error_message()
                ], 400);
            }

            $total_allocated = array_sum($allocations);
            $child_orders = $result['child_orders'] ?? [];

            return new \WP_REST_Response([
                'success' => true,
                'message' => "已分配 {$total_allocated} 個商品",
                'total_allocated' => $total_allocated,
                'updated_orders' => $child_orders
            ], 200);

        } catch (\Exception $e) {
            $debugService = \BuyGoPlus\Services\DebugService::get_instance();
            $debugService->log('Products_API', 'allocate_all_for_customer 錯誤', ['error' => $e->getMessage()], 'error');

            return new \WP_REST_Response([
                'success' => false,
                'message' => '分配時發生錯誤：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取得商品的 Variation 列表
     */
    public function get_product_variations($request) {
        try {
            $product_id = (int)$request->get_param('id');

            // 驗證商品所有權
            $check = API::verify_product_ownership($product_id);
            if (is_wp_error($check)) {
                return $check;
            }

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

            // 驗證 variation 所有權
            $check = API::verify_variation_ownership($variation_id);
            if (is_wp_error($check)) {
                return $check;
            }

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
     * 更新 Variation（用於更新採購數量）
     *
     * PUT /wp-json/buygo-plus-one/v1/variations/{id}
     */
    public function update_variation($request) {
        try {
            $variation_id = (int)$request->get_param('id');
            $data = $request->get_json_params();

            // 驗證 variation 所有權
            $check = API::verify_variation_ownership($variation_id);
            if (is_wp_error($check)) {
                return $check;
            }

            // 檢查 variation 是否存在
            $variation = \FluentCart\App\Models\ProductVariation::find($variation_id);
            if (!$variation) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Variation 不存在'
                ], 404);
            }

            // 更新採購數量（儲存到 fct_meta 表）
            if (isset($data['purchased'])) {
                $purchased = (int)$data['purchased'];
                $productService = new ProductService();
                $productService->updateVariationMeta($variation_id, '_buygo_purchased', $purchased);
            }

            // 更新庫存（正確同步 FluentCart 的 total_stock、available、manage_stock、stock_status）
            if (isset($data['stock'])) {
                $stock = ($data['stock'] === '' || $data['stock'] === null) ? null : (int) $data['stock'];
                $fields = FluentCartService::calculateStockFields($stock, [
                    'total_stock'  => $variation->total_stock ?? 0,
                    'available'    => $variation->available ?? 0,
                    'on_hold'      => $variation->on_hold ?? 0,
                    'committed'    => $variation->committed ?? 0,
                    'manage_stock' => $variation->manage_stock ?? 0,
                ]);
                $variation->total_stock  = $fields['total_stock'];
                $variation->available    = $fields['available'];
                $variation->manage_stock = $fields['manage_stock'];
                $variation->stock_status = $fields['stock_status'];
            }

            // 更新樣式名稱
            if (isset($data['variation_title'])) {
                $title = sanitize_text_field($data['variation_title']);
                $variation->variation_title = $title;
            }

            // 有任何 variation 欄位變更時統一儲存
            if (isset($data['stock']) || isset($data['variation_title'])) {
                $variation->save();
            }

            return new \WP_REST_Response([
                'success' => true,
                'message' => '已更新 Variation'
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
     * 調整/撤銷已分配數量
     *
     * 賣家分配後發現分錯，可呼叫此端點調整：
     * - 減少分配數量（例如 2→1）
     * - 全撤（new_quantity=0），會刪除子訂單
     * - 不能低於已出貨數量
     *
     * Request Body: { product_id, order_id, new_quantity }
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adjust_allocation($request) {
        try {
            $params = $request->get_json_params();

            // 1. 取得並驗證參數
            $product_id   = (int) ($params['product_id']   ?? 0);
            $order_id     = (int) ($params['order_id']     ?? 0);
            $new_quantity = isset($params['new_quantity']) ? (int) $params['new_quantity'] : -1;

            // 驗證商品所有權
            if ($product_id > 0) {
                $check = API::verify_product_ownership($product_id);
                if (is_wp_error($check)) {
                    return $check;
                }
            }

            if ($product_id <= 0) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '無效的商品 ID',
                ], 400);
            }

            if ($order_id <= 0) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '無效的訂單 ID',
                ], 400);
            }

            if ($new_quantity < 0) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'new_quantity 不能為負數',
                ], 400);
            }

            // 2. 呼叫 AllocationService 執行調整
            $allocationService = new \BuyGoPlus\Services\AllocationService();
            $result = $allocationService->adjustAllocation($product_id, $order_id, $new_quantity);

            // 3. 處理錯誤
            if (is_wp_error($result)) {
                $error_code = $result->get_error_code();

                // 判斷適合的 HTTP 狀態碼
                $status = 400;
                if ($error_code === 'CHILD_ORDER_NOT_FOUND') {
                    $status = 404;
                }

                return new \WP_REST_Response([
                    'success' => false,
                    'code'    => $error_code,
                    'message' => $result->get_error_message(),
                ], $status);
            }

            // 4. 成功回應
            $action  = $new_quantity === 0 ? '已全撤分配' : "已調整分配數量為 {$new_quantity}";
            $message = "{$action}（訂單 #{$order_id}）";

            return new \WP_REST_Response([
                'success'         => true,
                'message'         => $message,
                'child_order_id'  => $result['child_order_id'] ?? null,
                'new_quantity'    => $result['new_quantity']    ?? $new_quantity,
                'total_allocated' => $result['total_allocated'] ?? 0,
            ], 200);

        } catch (\Exception $e) {
            $debugService = \BuyGoPlus\Services\DebugService::get_instance();
            $debugService->log('Products_API', 'adjust_allocation 錯誤', ['error' => $e->getMessage()], 'error');

            return new \WP_REST_Response([
                'success' => false,
                'message' => '調整分配時發生錯誤：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 權限檢查
     */
    public static function check_permission() {
        return \BuyGoPlus\Api\API::check_permission_with_scope('products');
    }
}
