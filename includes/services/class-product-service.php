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

    // 測試版限制（Phase 19）
    const MAX_PRODUCTS_PER_SELLER = 2;  // 每個賣家最多 2 個商品
    const MAX_IMAGES_PER_PRODUCT = 2;   // 每個商品最多 2 張圖片

    public function __construct()
    {
        $this->debugService = DebugService::get_instance();
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
            $isWpAdmin = in_array('administrator', (array)$user->roles, true);

            // 建立查詢
            $query = ProductVariation::query()
                ->with(['product', 'product_detail'])
                ->where('item_status', 'active');

            // 權限篩選：多賣家隔離（Phase 24）
            // - WordPress 管理員可看到所有商品
            // - BuyGo 賣家只能看到自己的商品
            // - BuyGo 小幫手可以看到被授權賣場的商品
            if ($viewMode === 'frontend') {
                if (!$isWpAdmin) {
                    // 取得使用者可存取的 seller_ids
                    $accessible_seller_ids = SettingsService::get_accessible_seller_ids($user->ID);

                    if (!empty($accessible_seller_ids)) {
                        // 使用原生 SQL 查詢（與 OrderService 一致）
                        // 這比 whereHas 更穩定，因為直接查詢 wp_posts.post_author
                        $table_posts = $wpdb->posts;
                        $seller_ids_placeholder = implode(',', array_map('intval', $accessible_seller_ids));

                        // 取得這些賣家的商品 post_id
                        $post_ids = $wpdb->get_col(
                            "SELECT DISTINCT p.ID
                             FROM {$table_posts} p
                             WHERE p.post_author IN ({$seller_ids_placeholder})
                             AND p.post_type = 'fluent-products'
                             AND p.post_status != 'trash'"
                        );

                        if (!empty($post_ids)) {
                            $query->whereIn('post_id', $post_ids);
                        } else {
                            // 沒有符合的商品
                            $query->whereRaw('1 = 0');
                        }
                    } else {
                        // 沒有授權的賣場，返回空結果
                        $query->whereRaw('1 = 0');
                    }
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

            $products = $query->orderBy('id', 'desc')->get();

            // 計算下單數量
            $productIds = $products->pluck('id')->toArray();
            $orderCounts = $this->calculateOrderCounts($productIds);

            // 計算已出貨數量
            $shippedCounts = $this->calculateShippedCounts($productIds);

            // 計算已分配到子訂單的數量（取代不可靠的 post_meta）
            $allocatedToChildCounts = $this->calculateAllocatedToChildOrders($productIds);

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

                // 取得幣別資訊
                // 優先順序: 1. 商品自訂幣別 (_mygo_currency) → 2. FluentCart 系統幣別 → 3. TWD (後備)
                $currency = get_post_meta($product->post_id, '_mygo_currency', true);

                if (empty($currency)) {
                    // 從 FluentCart 系統設定讀取預設幣別
                    $currency = \FluentCart\Api\CurrencySettings::get('currency');
                }

                if (empty($currency)) {
                    // 最終後備：台幣
                    $currency = 'TWD';
                }

                // 取得已採購數量（從 post_meta）
                $purchased = (int) get_post_meta($product->post_id, '_buygo_purchased', true);

                // 計算已下單數量（只計算父訂單）
                $ordered = $orderCounts[$product->id] ?? 0;

                // 計算已分配數量（從子訂單計算，而不是 post_meta）
                // 這樣更準確，因為 post_meta 可能沒有正確同步
                $allocated = $allocatedToChildCounts[$product->id] ?? 0;

                // 計算已出貨數量（從出貨單計算）
                $shipped = $shippedCounts[$product->id] ?? 0;

                // 計算待出貨數量（已分配但還沒出貨的）
                // 待出貨 = 已分配 - 已出貨
                $pending = max(0, $allocated - $shipped);

                // 計算預訂數量（客戶下單了但還沒採購到的）
                $reserved = max(0, $ordered - $purchased);

                // 取得商品名稱：直接從 wp_posts 讀取 post_title（最可靠的方式）
                $post = get_post($product->post_id);
                $productName = ($post && !empty($post->post_title)) ? $post->post_title : '';

                // 如果 post_title 為空，使用 variation_title（但排除「預設」）
                if (empty($productName)) {
                    $productName = (!empty($product->variation_title) && $product->variation_title !== '預設')
                        ? $product->variation_title
                        : '未命名商品';
                }

                $productData = [
                    'id' => $product->id,
                    'post_id' => $product->post_id,
                    'name' => $productName,
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
                    'ordered' => $ordered,
                    'purchased' => $purchased,
                    'allocated' => $allocated,
                    'shipped' => $shipped,
                    'pending' => $pending,
                    'reserved' => $reserved,
                    'order_count' => $ordered,
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
     * 取得商品的下單客戶列表（每筆訂單獨立顯示）
     *
     * @param int $productId 商品 ID（product_variation_id）
     * @return array
     */
    public function getProductBuyers(int $productId): array
    {
        try {
            // 取得商品資訊（名稱和圖片）
            $productVariation = \FluentCart\App\Models\ProductVariation::with(['product'])->find($productId);
            $productName = '未知商品';
            $productImage = '';

            if ($productVariation) {
                $productName = $productVariation->variation_title ?? '';
                if (empty($productName) && $productVariation->product) {
                    $productName = $productVariation->product->post_title ?? '未知商品';
                }

                // 取得商品圖片
                if ($productVariation->post_id) {
                    $thumbnailId = get_post_thumbnail_id($productVariation->post_id);
                    if ($thumbnailId) {
                        $productImage = wp_get_attachment_image_url($thumbnailId, 'medium') ?: '';
                    }
                }
            }

            // 查詢訂單項目（只計算父訂單，排除子訂單、已取消和已退款的訂單）
            $orderItems = OrderItem::where('object_id', $productId)
                ->whereHas('order', function($query) {
                    $query->whereNotIn('status', ['cancelled', 'refunded'])
                          ->whereNull('parent_id');  // 只查詢父訂單
                })
                ->with(['order', 'order.customer'])
                ->get();

            // 每筆訂單獨立顯示（不再整合）
            $orders = [];

            foreach ($orderItems as $item) {
                if (!$item->order || !$item->order->customer) {
                    continue;
                }

                $customer = $item->order->customer;
                $order = $item->order;

                // 從 line_meta 讀取 _allocated_qty 和 _shipped_qty
                $lineMeta = $item->line_meta ?? '{}';
                if (is_array($lineMeta)) {
                    $metaData = $lineMeta;
                } elseif (is_string($lineMeta)) {
                    $metaData = json_decode($lineMeta, true) ?: [];
                } else {
                    $metaData = [];
                }

                $quantity = (int)$item->quantity;
                $allocatedQty = (int)($metaData['_allocated_qty'] ?? 0);
                $shippedQty = (int)($metaData['_shipped_qty'] ?? 0);

                // 計算待處理數量
                $pendingQty = max(0, $quantity - $allocatedQty - $shippedQty);

                // 判斷狀態
                // - 已全部出貨：shipped_qty >= quantity
                // - 已全部分配：allocated_qty + shipped_qty >= quantity
                // - 待分配：有待處理數量
                $isFullyShipped = $shippedQty >= $quantity;
                $isFullyAllocated = ($allocatedQty + $shippedQty) >= $quantity;

                // 決定顯示狀態
                $status = 'pending';  // 待分配
                if ($isFullyShipped) {
                    $status = 'shipped';  // 已出貨
                } elseif ($isFullyAllocated) {
                    $status = 'allocated';  // 已分配
                } elseif ($shippedQty > 0 || $allocatedQty > 0) {
                    $status = 'partial';  // 部分處理
                }

                // 格式化日期
                $orderDate = '';
                if ($order->created_at) {
                    if (is_object($order->created_at) && method_exists($order->created_at, 'format')) {
                        $orderDate = $order->created_at->format('Y/m/d');
                    } elseif (is_string($order->created_at)) {
                        $orderDate = date('Y/m/d', strtotime($order->created_at));
                    }
                }

                $orders[] = [
                    'order_item_id' => $item->id,
                    'order_id' => $order->id,
                    'invoice_no' => $order->invoice_no ?? "#{$order->id}",
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->full_name ?? $customer->email,
                    'customer_email' => $customer->email,
                    'quantity' => $quantity,
                    'allocated_quantity' => $allocatedQty,
                    'shipped_quantity' => $shippedQty,
                    'pending_quantity' => $pendingQty,
                    'status' => $status,
                    'is_allocated' => $isFullyAllocated,  // 保持向後相容
                    'order_date' => $orderDate,
                    'shipping_status' => $order->shipping_status ?? 'unshipped'
                ];
            }

            // 排序：按狀態優先級 pending > partial > allocated > shipped
            $statusOrder = ['pending' => 0, 'partial' => 1, 'allocated' => 2, 'shipped' => 3];
            usort($orders, function($a, $b) use ($statusOrder) {
                $aOrder = $statusOrder[$a['status']] ?? 99;
                $bOrder = $statusOrder[$b['status']] ?? 99;
                if ($aOrder !== $bOrder) {
                    return $aOrder - $bOrder;
                }
                // 同樣狀態按待處理數量降序
                return $b['pending_quantity'] - $a['pending_quantity'];
            });

            return [
                'success' => true,
                'data' => $orders,
                'product' => [
                    'id' => $productId,
                    'name' => $productName,
                    'image' => $productImage
                ]
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
                AND o.status NOT IN ('cancelled', 'refunded')
                AND o.parent_id IS NULL
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
     * 計算已出貨數量
     * 
     * @param array $productVariationIds 商品變體 ID 陣列
     * @return array 商品 ID => 已出貨數量
     */
    private function calculateShippedCounts(array $productVariationIds): array
    {
        if (empty($productVariationIds)) {
            return [];
        }

        try {
            global $wpdb;

            $table_shipment_items = $wpdb->prefix . 'buygo_shipment_items';
            $table_shipments = $wpdb->prefix . 'buygo_shipments';
            $table_variations = $wpdb->prefix . 'fct_product_variations';

            $placeholders = implode(',', array_fill(0, count($productVariationIds), '%d'));

            // 【重要】buygo_shipment_items.product_id 存的是 WordPress post_id
            // 而傳入的 productVariationIds 是 FluentCart ProductVariation ID
            // 需要透過 fct_product_variations 表做轉換
            $sql = $wpdb->prepare("
                SELECT
                    pv.id as product_variation_id,
                    SUM(si.quantity) as shipped_count
                FROM {$table_shipment_items} si
                INNER JOIN {$table_shipments} s ON si.shipment_id = s.id
                INNER JOIN {$table_variations} pv ON si.product_id = pv.post_id
                WHERE pv.id IN ({$placeholders})
                AND s.status IN ('shipped', 'archived')
                GROUP BY pv.id
            ", ...$productVariationIds);

            $results = $wpdb->get_results($sql, ARRAY_A);

            $shippedCounts = [];
            foreach ($results as $result) {
                $shippedCounts[$result['product_variation_id']] = (int)$result['shipped_count'];
            }

            return $shippedCounts;

        } catch (\Exception $e) {
            $this->debugService->log('ProductService', '計算已出貨數量失敗', [
                'error' => $e->getMessage(),
                'product_variation_ids' => $productVariationIds
            ], 'error');

            return [];
        }
    }

    /**
     * 計算已分配到子訂單的數量
     *
     * 【重要】這個方法計算的是實際已建立子訂單的商品數量
     * 用於取代從 post_meta 讀取的 _buygo_allocated（因為 post_meta 可能不同步）
     *
     * @param array $productVariationIds 商品變體 ID 陣列
     * @return array 商品 ID => 已分配到子訂單的數量
     */
    private function calculateAllocatedToChildOrders(array $productVariationIds): array
    {
        if (empty($productVariationIds)) {
            return [];
        }

        try {
            global $wpdb;

            $table_items = $wpdb->prefix . 'fct_order_items';
            $table_orders = $wpdb->prefix . 'fct_orders';

            $placeholders = implode(',', array_fill(0, count($productVariationIds), '%d'));

            // 計算每個商品在子訂單中的總數量
            // 子訂單的特徵：parent_id IS NOT NULL 且 type = 'split'
            $sql = $wpdb->prepare("
                SELECT
                    oi.object_id as product_variation_id,
                    SUM(oi.quantity) as allocated_count
                FROM {$table_items} oi
                INNER JOIN {$table_orders} o ON oi.order_id = o.id
                WHERE oi.object_id IN ({$placeholders})
                AND o.parent_id IS NOT NULL
                AND o.type = 'split'
                AND o.status NOT IN ('cancelled', 'refunded')
                GROUP BY oi.object_id
            ", ...$productVariationIds);

            $results = $wpdb->get_results($sql, ARRAY_A);

            $allocatedCounts = [];
            foreach ($results as $result) {
                $allocatedCounts[$result['product_variation_id']] = (int)$result['allocated_count'];
            }

            return $allocatedCounts;

        } catch (\Exception $e) {
            $this->debugService->log('ProductService', '計算已分配到子訂單數量失敗', [
                'error' => $e->getMessage(),
                'product_variation_ids' => $productVariationIds
            ], 'error');

            return [];
        }
    }

    /**
     * 取得幣別符號
     *
     * @param string $currency 幣別代碼
     * @return string 幣別符號
     */
    private function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'JPY' => '¥',
            'TWD' => 'NT$',
            'USD' => '$',
            'THB' => '฿',
            'CNY' => '¥',
            'EUR' => '€',
            'GBP' => '£'
        ];

        return $symbols[$currency] ?? 'NT$';
    }

    /**
     * 格式化價格顯示
     */
    private function formatPrice(int $priceInCents): string
    {
        // 從 FluentCart 系統讀取幣別設定
        $currency = \FluentCart\Api\CurrencySettings::get('currency') ?: 'TWD';
        $symbol = $this->getCurrencySymbol($currency);

        return $symbol . ' ' . number_format($priceInCents / 100, 2);
    }

    /**
     * 取得單一商品完整資料
     * 
     * @param int $productId 商品 ID 
     * @return array|null
     */
    public function getProductById(int $productId): ?array
    {
        $this->debugService->log('ProductService', '取得單品資料', [
            'product_id' => $productId
        ]);

        try {
            $product = ProductVariation::query()
                ->with(['product', 'product_detail'])
                ->where('id', $productId)
                ->where('item_status', 'active')
                ->first();

            if (!$product) {
                return null;
            }

            // 取得商品圖片
            $imageUrl = null;
            if ($product->product) {
                $thumbnailId = get_post_thumbnail_id($product->post_id);
                if ($thumbnailId) {
                    $imageUrl = wp_get_attachment_image_url($thumbnailId, 'medium');
                }
            }

            // 取得幣別資訊
            // 優先順序: 1. 商品自訂幣別 (_mygo_currency) → 2. FluentCart 系統幣別 → 3. TWD (後備)
            $currency = get_post_meta($product->post_id, '_mygo_currency', true);

            if (empty($currency)) {
                // 從 FluentCart 系統設定讀取預設幣別
                $currency = \FluentCart\Api\CurrencySettings::get('currency');
            }

            if (empty($currency)) {
                // 最終後備：台幣
                $currency = 'TWD';
            }

            // 取得已採購數量（從 post_meta）
            $purchased = (int) get_post_meta($product->post_id, '_buygo_purchased', true);

            // 計算下單數量
            $productIds = [$product->id];
            $orderCounts = $this->calculateOrderCounts($productIds);

            // 取得商品名稱：直接從 wp_posts 讀取 post_title（最可靠的方式）
            $post = get_post($product->post_id);
            $productName = ($post && !empty($post->post_title)) ? $post->post_title : '';

            // 如果 post_title 為空，使用 variation_title（但排除「預設」）
            if (empty($productName)) {
                $productName = (!empty($product->variation_title) && $product->variation_title !== '預設')
                    ? $product->variation_title
                    : '未命名商品';
            }

            return [
                'id' => $product->id,
                'post_id' => $product->post_id,
                'name' => $productName,
                'variation_title' => $product->variation_title,
                'price' => (int) ($product->item_price / 100), // 轉換為元
                'currency' => $currency,
                'image' => $imageUrl,
                'inventory' => $product->available ?? 0,
                'stock' => $product->available ?? 0,
                'ordered' => $orderCounts[$product->id] ?? 0,
                'purchased' => $purchased,
                'status' => $product->product->post_status ?? 'draft',
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at
            ];

        } catch (\Exception $e) {
            $this->debugService->log('ProductService', '取得單品資料失敗', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ], 'error');
            return null;
        }
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

    /**
     * 取得商品的 Variation 列表
     *
     * @param int $productId WordPress Post ID (不是 variation ID)
     * @return array Variation 列表
     */
    public function getVariations(int $productId): array
    {
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'fct_product_variations';

            $variations = $wpdb->get_results($wpdb->prepare(
                "SELECT id, variation_title, item_price, total_stock, available, stock_status, media_id
                 FROM {$table}
                 WHERE post_id = %d AND item_status = 'active'
                 ORDER BY id ASC",
                $productId
            ), ARRAY_A);

            // 轉換價格單位（分 → 元）並取得圖片 URL
            foreach ($variations as &$v) {
                $v['price'] = $v['item_price'] / 100;
                unset($v['item_price']);

                // 取得 variation 的圖片 URL
                $v['image'] = null;
                if (!empty($v['media_id'])) {
                    $imageUrl = wp_get_attachment_image_url((int)$v['media_id'], 'medium');
                    $v['image'] = $imageUrl ?: null;
                }
                unset($v['media_id']); // 移除 media_id，只保留 image URL
            }

            return $variations;

        } catch (\Exception $e) {
            $this->debugService->log('ProductService', '取得 Variations 失敗', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ], 'error');

            return [];
        }
    }

    /**
     * 取得 Variation 的統計資料
     *
     * @param int $variationId ProductVariation ID
     * @return array 統計資料
     */
    public function getVariationStats(int $variationId): array
    {
        try {
            // 計算下單數量（只計算父訂單）
            $orderCounts = $this->calculateOrderCounts([$variationId]);
            $ordered = $orderCounts[$variationId] ?? 0;

            // 計算已分配數量（從子訂單計算）
            $allocatedCounts = $this->calculateAllocatedToChildOrders([$variationId]);
            $allocated = $allocatedCounts[$variationId] ?? 0;

            // 計算已出貨數量
            $shippedCounts = $this->calculateShippedCounts([$variationId]);
            $shipped = $shippedCounts[$variationId] ?? 0;

            // 取得已採購數量（從 variation meta）
            $variation = ProductVariation::find($variationId);
            $purchased = 0;
            if ($variation) {
                // 從 variation meta 讀取（而不是 post meta）
                $purchased = (int) get_metadata('fluent_cart_variation', $variationId, '_buygo_purchased', true);
            }

            return [
                'ordered' => $ordered,
                'allocated' => $allocated,
                'shipped' => $shipped,
                'purchased' => $purchased,
                'pending' => max(0, $allocated - $shipped),
                'reserved' => max(0, $ordered - $purchased)
            ];

        } catch (\Exception $e) {
            $this->debugService->log('ProductService', '取得 Variation 統計失敗', [
                'variation_id' => $variationId,
                'error' => $e->getMessage()
            ], 'error');

            return [
                'ordered' => 0,
                'allocated' => 0,
                'shipped' => 0,
                'purchased' => 0,
                'pending' => 0,
                'reserved' => 0
            ];
        }
    }

    /**
     * 檢查商品是否為多樣式商品
     *
     * @param int $productId WordPress Post ID
     * @return bool
     */
    public function isVariableProduct(int $productId): bool
    {
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'fct_product_variations';

            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE post_id = %d AND item_status = 'active'",
                $productId
            ));

            return (int)$count > 1;

        } catch (\Exception $e) {
            $this->debugService->log('ProductService', '檢查 Variable Product 失敗', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ], 'error');

            return false;
        }
    }

    /**
     * 檢查賣家是否可以新增商品（Phase 19 + Phase 40）
     *
     * Phase 40: 實作小幫手共享配額驗證
     * - 計算賣家 + 所有小幫手的總商品數
     * - 與賣家的 product_limit 比較
     *
     * @param int $user_id 賣家或小幫手 ID
     * @return array ['can_add' => bool, 'current' => int, 'limit' => int, 'message' => string]
     */
    public function canAddProduct($user_id) {
        global $wpdb;

        // 檢查賣家類型
        $seller_type = \BuyGoPlus\Admin\SellerTypeField::get_seller_type($user_id);

        // 真實賣家沒有限制
        if ($seller_type === 'real') {
            return [
                'can_add' => true,
                'current' => 0,
                'limit' => 0,
                'message' => '真實賣家無商品數量限制'
            ];
        }

        // Phase 40: 判斷是賣家還是小幫手
        $seller_id = $user_id;
        $is_helper = false;

        // 檢查是否為小幫手（查詢 wp_buygo_helpers 表）
        $helper_relation = $wpdb->get_row($wpdb->prepare(
            "SELECT seller_id FROM {$wpdb->prefix}buygo_helpers WHERE helper_id = %d",
            $user_id
        ));

        if ($helper_relation) {
            $is_helper = true;
            $seller_id = $helper_relation->seller_id;
        }

        // 取得賣家的商品限制數量 (0 = 無限制)
        $product_limit = get_user_meta($seller_id, 'buygo_product_limit', true);
        if ($product_limit === '') {
            $product_limit = self::MAX_PRODUCTS_PER_SELLER; // 預設為 2
        }
        $product_limit = intval($product_limit);

        // 如果限制為 0,表示無限制
        if ($product_limit === 0) {
            return [
                'can_add' => true,
                'current' => 0,
                'limit' => 0,
                'message' => $is_helper ? '小幫手（無商品數量限制）' : '測試賣家（無商品數量限制）'
            ];
        }

        // Phase 40: 計算賣家 + 所有小幫手的總商品數

        // 1. 賣家自己的商品數
        $seller_product_count = Product::where('post_author', $seller_id)
            ->where('post_status', '!=', 'trash')
            ->count();

        // 2. 所有小幫手的商品數
        $helpers = $wpdb->get_col($wpdb->prepare(
            "SELECT helper_id FROM {$wpdb->prefix}buygo_helpers WHERE seller_id = %d",
            $seller_id
        ));

        $helpers_product_count = 0;
        if (!empty($helpers)) {
            $helpers_product_count = Product::whereIn('post_author', $helpers)
                ->where('post_status', '!=', 'trash')
                ->count();
        }

        // 總商品數
        $total_count = $seller_product_count + $helpers_product_count;
        $can_add = $total_count < $product_limit;

        $message = $can_add
            ? sprintf('還可新增 %d 個商品', $product_limit - $total_count)
            : '已達上架限制，無法新增更多產品';

        return [
            'can_add' => $can_add,
            'current' => $total_count,
            'limit' => $product_limit,
            'message' => $message,
            'is_helper' => $is_helper,
            'seller_id' => $seller_id
        ];
    }

    /**
     * 檢查商品是否可以新增圖片（Phase 19）
     *
     * @param int $product_id 商品 ID (wp_posts.ID)
     * @param int $user_id 賣家 ID
     * @return array ['can_add' => bool, 'current' => int, 'limit' => int, 'message' => string]
     */
    public function canAddImage($product_id, $user_id) {
        // 檢查賣家類型
        $seller_type = \BuyGoPlus\Admin\SellerTypeField::get_seller_type($user_id);

        // 真實賣家沒有限制
        if ($seller_type === 'real') {
            return [
                'can_add' => true,
                'current' => 0,
                'limit' => 0,
                'message' => '真實賣家無圖片數量限制'
            ];
        }

        // 測試賣家：計算現有圖片數量
        $image_count = 0;

        // 1. 縮圖
        $thumbnail_id = get_post_thumbnail_id($product_id);
        if ($thumbnail_id) {
            $image_count++;
        }

        // 2. Gallery 圖片
        $gallery = get_post_meta($product_id, '_product_image_gallery', true);
        if (!empty($gallery)) {
            $gallery_ids = explode(',', $gallery);
            $image_count += count($gallery_ids);
        }

        $can_add = $image_count < self::MAX_IMAGES_PER_PRODUCT;

        return [
            'can_add' => $can_add,
            'current' => $image_count,
            'limit' => self::MAX_IMAGES_PER_PRODUCT,
            'message' => $can_add
                ? sprintf('還可新增 %d 張圖片', self::MAX_IMAGES_PER_PRODUCT - $image_count)
                : sprintf('已達圖片數量上限（%d/%d）', $image_count, self::MAX_IMAGES_PER_PRODUCT)
        ];
    }
}
