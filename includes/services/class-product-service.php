<?php

namespace BuyGoPlus\Services;

use FluentCart\App\Models\ProductVariation;
use FluentCart\App\Models\Product;
use FluentCart\App\Models\OrderItem;

/**
 * Product Service - 商品管理服務
 * 
 * 整合 FluentCart 商品功能並添加 BuyGo 特有的業務邏輯
 * 
 * @package BuyGoPlusOne\Services
 * @version 1.0.0
 */
class ProductService
{
    private $debugService;

    public function __construct()
    {
        $this->debugService = new DebugService();
    }

    /**
     * 取得商品列表（包含下單數量）
     * 
     * @param array $filters 篩選條件
     * @param string $viewMode 顯示模式 frontend|backend
     * @return array
     */
    public function getProductsWithOrderCount(array $filters = [], string $viewMode = 'frontend'): array
    {
        $this->debugService->log('ProductService', '開始取得商品列表', [
            'filters' => $filters,
            'viewMode' => $viewMode
        ]);

        try {
            global $wpdb;
            
            $user = wp_get_current_user();
            $isAdmin = in_array('administrator', (array)$user->roles, true) || 
                      in_array('buygo_admin', (array)$user->roles, true);

            // 建立查詢
            $query = ProductVariation::query()
                ->with(['product', 'product_detail'])
                ->where('item_status', 'active');

            // 權限篩選
            if ($viewMode === 'frontend') {
                if (!$isAdmin) {
                    // 一般賣家：只顯示自己的商品
                    $query->whereHas('product', function($q) use ($user) {
                        $q->where('post_author', $user->ID);
                    });
                }
            }

            // 狀態篩選
            if (isset($filters['status']) && $filters['status'] !== 'all') {
                $query->whereHas('product', function($q) use ($filters) {
                    $q->where('post_status', $filters['status']);
                });
            }

            // 搜尋篩選
            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->where(function($q) use ($search) {
                    $q->where('variation_title', 'LIKE', "%{$search}%")
                      ->orWhereHas('product', function($productQuery) use ($search) {
                          $productQuery->where('post_title', 'LIKE', "%{$search}%");
                      });
                });
            }

            $products = $query->get();

            // 計算下單數量
            $productIds = $products->pluck('id')->toArray();
            $orderCounts = $this->calculateOrderCounts($productIds);

            // 格式化資料
            $formattedProducts = [];
            foreach ($products as $product) {
                // 取得商品圖片
                $imageUrl = null;
                if ($product->product) {
                    $thumbnailId = get_post_thumbnail_id($product->post_id);
                    if ($thumbnailId) {
                        $imageUrl = wp_get_attachment_image_url($thumbnailId, 'medium');
                    }
                }

                // 取得幣別資訊 (從 product_detail 或預設為 TWD)
                $currency = 'TWD';
                if ($product->product_detail && isset($product->product_detail->currency)) {
                    $currency = $product->product_detail->currency;
                }

                // 取得已採購數量（從 post_meta）
                $purchased = (int) get_post_meta($product->post_id, '_buygo_purchased', true);

                $productData = [
                    'id' => $product->id,
                    'post_id' => $product->post_id,
                    'name' => $product->product->post_title ?? '',
                    'variation_title' => $product->variation_title,
                    'price' => (int) ($product->item_price / 100), // 轉換為元
                    'currency' => $currency,
                    'formatted_price' => $this->formatPrice($product->item_price),
                    'image' => $imageUrl,
                    'inventory' => $product->available ?? 0,
                    'stock' => $product->available ?? 0,
                    'total_stock' => $product->total_stock ?? 0,
                    'committed' => $product->committed ?? 0,
                    'on_hold' => $product->on_hold ?? 0,
                    'ordered' => $orderCounts[$product->id] ?? 0,
                    'purchased' => $purchased,
                    'order_count' => $orderCounts[$product->id] ?? 0,
                    'stock_status' => $product->stock_status,
                    'status' => $product->product->post_status ?? 'draft',
                    'procurement_status' => get_post_meta($product->post_id, '_procurement_status', true) ?: 'pending',
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                    'seller_id' => $product->product->post_author ?? 0,
                    'seller_name' => $this->getSellerName($product->product->post_author ?? 0)
                ];

                // 後台額外欄位
                if ($viewMode === 'backend') {
                    $productData['reserved_count'] = ($product->committed ?? 0) + ($product->on_hold ?? 0);
                }

                $formattedProducts[] = $productData;
            }

            $this->debugService->log('ProductService', '成功取得商品列表', [
                'count' => count($formattedProducts),
                'viewMode' => $viewMode
            ]);

            return $formattedProducts;

        } catch (\Exception $e) {
            $this->debugService->log('ProductService', '取得商品列表失敗', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'filters' => $filters
            ], 'error');

            throw new \Exception('無法取得商品列表：' . $e->getMessage());
        }
    }

    /**
     * 更新商品資料
     * 
     * @param int $productId 商品 ID
     * @param array $updateData 更新資料
     * @return bool
     */
    public function updateProduct(int $productId, array $updateData): bool
    {
        error_log('===== ProductService::updateProduct =====');
        error_log('ID: ' . $productId);
        error_log('Data: ' . print_r($updateData, true));
        
        $this->debugService->log('ProductService', '更新商品資料', [
            'product_id' => $productId,
            'update_data' => $updateData
        ]);

        try {
            error_log('嘗試取得商品: ProductVariation::find(' . $productId . ')');
            $product = ProductVariation::find($productId);
            
            if (!$product) {
                error_log('錯誤：商品不存在，ID: ' . $productId);
                return false;
            }
            
            error_log('找到商品: post_id=' . $product->post_id . ', item_title=' . ($product->variation_title ?? 'N/A'));

            // 更新商品名稱（WordPress Post Title）
            if (isset($updateData['name'])) {
                error_log('更新商品名稱: ' . $updateData['name']);
                $result = wp_update_post([
                    'ID' => $product->post_id,
                    'post_title' => $updateData['name']
                ]);
                if (is_wp_error($result)) {
                    error_log('wp_update_post 失敗: ' . $result->get_error_message());
                } else {
                    error_log('wp_update_post 成功: ' . $result);
                }
            }

            // 更新價格（轉換為分）
            if (isset($updateData['price'])) {
                $priceInCents = (int) ($updateData['price'] * 100);
                error_log('更新價格: ' . $updateData['price'] . ' 元 = ' . $priceInCents . ' 分');
                $product->item_price = $priceInCents;
            }

            // 更新已採購數量（儲存到 post_meta）
            if (isset($updateData['purchased'])) {
                error_log('更新已採購數量: ' . $updateData['purchased']);
                update_post_meta($product->post_id, '_buygo_purchased', (int) $updateData['purchased']);
            }

            // 更新狀態
            if (isset($updateData['status'])) {
                error_log('更新狀態: ' . $updateData['status']);
                $result = wp_update_post([
                    'ID' => $product->post_id,
                    'post_status' => $updateData['status']
                ]);
                if (is_wp_error($result)) {
                    error_log('wp_update_post (status) 失敗: ' . $result->get_error_message());
                } else {
                    error_log('wp_update_post (status) 成功: ' . $result);
                }
            }

            // 儲存 ProductVariation 變更
            error_log('嘗試儲存 ProductVariation...');
            $result = $product->save();
            error_log('儲存結果: ' . ($result ? '成功' : '失敗'));
            
            if (!$result) {
                error_log('ProductVariation::save() 返回 false');
            }

            $this->debugService->log('ProductService', '商品更新成功', [
                'product_id' => $productId
            ]);

            return true;

        } catch (\Exception $e) {
            error_log('ProductService::updateProduct 錯誤: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            $this->debugService->log('ProductService', '商品更新失敗', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ], 'error');
            
            throw $e;
        }
    }

    /**
     * 取得商品的下單客戶列表
     * 
     * @param int $productId 商品 ID
     * @return array
     */
    public function getProductBuyers(int $productId): array
    {
        try {
            // 查詢訂單項目
            $orderItems = OrderItem::where('object_id', $productId)
                ->with(['order', 'order.customer'])
                ->get();
            
            // 整理客戶資料
            $buyerMap = [];
            
            foreach ($orderItems as $item) {
                if (!$item->order || !$item->order->customer) {
                    continue;
                }
                
                $customer = $item->order->customer;
                $customerId = $customer->id;
                
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

            return [
                'success' => true,
                'data' => $buyers
            ];

        } catch (\Exception $e) {
            $this->debugService->log('ProductService', '取得下單客戶列表失敗', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ], 'error');

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 計算商品的下單數量
     * 
     * @param array $productVariationIds 商品變化 ID 陣列
     * @return array
     */
    private function calculateOrderCounts(array $productVariationIds): array
    {
        if (empty($productVariationIds)) {
            return [];
        }

        try {
            global $wpdb;
            
            $table_items = $wpdb->prefix . 'fct_order_items';
            $table_orders = $wpdb->prefix . 'fct_orders';
            
            $placeholders = implode(',', array_fill(0, count($productVariationIds), '%d'));
            
            $sql = $wpdb->prepare("
                SELECT oi.object_id as product_variation_id, SUM(oi.quantity) as order_count
                FROM {$table_items} oi
                INNER JOIN {$table_orders} o ON oi.order_id = o.id
                WHERE oi.object_id IN ({$placeholders})
                AND o.status NOT IN ('cancelled', 'refunded', 'completed')
                GROUP BY oi.object_id
            ", ...$productVariationIds);

            $results = $wpdb->get_results($sql, ARRAY_A);
            
            $orderCounts = [];
            foreach ($results as $result) {
                $orderCounts[$result['product_variation_id']] = (int)$result['order_count'];
            }

            return $orderCounts;

        } catch (\Exception $e) {
            $this->debugService->log('ProductService', '計算下單數量失敗', [
                'error' => $e->getMessage(),
                'product_variation_ids' => $productVariationIds
            ], 'error');

            return [];
        }
    }

    /**
     * 格式化價格顯示
     */
    private function formatPrice(int $priceInCents): string
    {
        return 'NT$ ' . number_format($priceInCents / 100, 2);
    }

    /**
     * 取得賣家名稱
     */
    private function getSellerName(int $userId): string
    {
        if (!$userId) {
            return '';
        }

        $user = get_userdata($userId);
        return $user ? ($user->display_name ?: $user->user_login) : '';
    }
}
