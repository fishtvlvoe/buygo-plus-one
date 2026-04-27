<?php

namespace BuyGoPlus\Services;

use FluentCart\App\Models\OrderItem;
use FluentCart\App\Models\ProductVariation;

if (!defined('ABSPATH')) {
    exit;
}

class ProductBuyerQueryService
{
    private $debugService;
    private $variationService;

    public function __construct()
    {
        $this->debugService = DebugService::get_instance();
        $this->variationService = new ProductVariationService();
    }

    public function getProductBuyers(int $productId): array
    {
        try {
            [$productName, $productImage] = $this->resolveProductSummary($productId);
            [$postId, $variationIds] = $this->fetchVariationIds($productId);
            $orderItems = OrderItem::whereIn('object_id', $variationIds)
                ->whereHas('order', static function($query) {
                    $query->whereNotIn('status', ['cancelled', 'refunded'])->whereNull('parent_id');
                })
                ->with(['order', 'order.customer'])
                ->get();

            $variationTitles = $this->fetchVariationTitles($postId, $variationIds);
            $purchasedMap = $this->buildPurchasedMap($postId, $variationIds);
            $actualShippedMap = $this->fetchActualShippedMap($orderItems->pluck('id')->toArray());

            $orders = [];
            foreach ($orderItems as $item) {
                if (!$item->order || !$item->order->customer) {
                    continue;
                }
                $orders[] = $this->buildBuyerOrderEntry($item, $actualShippedMap, $purchasedMap, $variationTitles);
            }

            usort($orders, static function($a, $b) {
                $statusOrder = ['pending' => 0, 'partial' => 1, 'allocated' => 2, 'shipped' => 3];
                return ($statusOrder[$a['status']] ?? 99) <=> ($statusOrder[$b['status']] ?? 99)
                    ?: ($b['pending_quantity'] <=> $a['pending_quantity']);
            });

            $response = ['success' => true, 'data' => $orders, 'product' => ['id' => $productId, 'name' => $productName, 'image' => $productImage]];
            if (count($variationIds) > 1 && !empty($variationTitles)) {
                $counts = [];
                foreach ($orders as $order) {
                    $counts[$order['object_id']] = ($counts[$order['object_id']] ?? 0) + 1;
                }
                $variants = [];
                foreach ($variationTitles as $variationId => $title) {
                    $variants[] = ['id' => $variationId, 'title' => $title, 'order_count' => $counts[$variationId] ?? 0];
                }
                usort($variants, static fn($a, $b) => $a['id'] <=> $b['id']);
                $response['variants'] = $variants;
            }

            return $response;
        } catch (\Exception $e) {
            $this->debugService->log('ProductService', '取得下單客戶列表失敗', ['product_id' => $productId, 'error' => $e->getMessage()], 'error');
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function resolveProductSummary(int $productId): array
    {
        $productVariation = ProductVariation::with(['product'])->find($productId);
        if (!$productVariation) {
            return ['未知商品', ''];
        }

        $name = $productVariation->post_id
            ? html_entity_decode(get_the_title($productVariation->post_id) ?: ($productVariation->variation_title ?? '未知商品'))
            : ($productVariation->variation_title ?? '未知商品');
        $image = '';
        if ($productVariation->post_id) {
            $gallery = get_post_meta($productVariation->post_id, 'fluent-products-gallery-image', true);
            if (is_array($gallery) && !empty($gallery[0]['url'])) {
                $image = $gallery[0]['url'];
            }
            if (empty($image)) {
                $thumbnailId = get_post_thumbnail_id($productVariation->post_id);
                $image = $thumbnailId ? (wp_get_attachment_image_url($thumbnailId, 'medium') ?: '') : '';
            }
        }

        return [$name, $image];
    }

    private function fetchVariationIds(int $productId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'fct_product_variations';
        $postId = (int) $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$table} WHERE id = %d LIMIT 1", $productId));
        $variationIds = [$productId];
        if ($postId) {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$table} WHERE post_id = %d AND item_status = 'active'", $postId));
            if (!empty($ids)) {
                $variationIds = array_map('intval', $ids);
            }
        }

        return [$postId, $variationIds];
    }

    private function fetchVariationTitles(int $postId, array $variationIds): array
    {
        if (count($variationIds) <= 1 || !$postId) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'fct_product_variations';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, variation_title FROM {$table} WHERE post_id = %d AND item_status = 'active'", $postId));
        $titles = [];
        foreach ($rows as $row) {
            $titles[(int) $row->id] = $row->variation_title;
        }
        return $titles;
    }

    private function buildPurchasedMap(int $postId, array $variationIds): array
    {
        $map = [];
        if (count($variationIds) > 1) {
            foreach ($variationIds as $variationId) {
                $map[$variationId] = (int) $this->variationService->getVariationMeta($variationId, '_buygo_purchased', 0);
            }
            return $map;
        }

        $purchased = $postId ? (int) get_post_meta($postId, '_buygo_purchased', true) : 0;
        foreach ($variationIds as $variationId) {
            $map[$variationId] = $purchased;
        }
        return $map;
    }

    private function fetchActualShippedMap(array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }

        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT order_item_id, SUM(quantity) as shipped_qty
             FROM {$wpdb->prefix}buygo_shipment_items
             WHERE order_item_id IN (" . implode(',', array_fill(0, count($itemIds), '%d')) . ")
             GROUP BY order_item_id",
            ...$itemIds
        ));
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->order_item_id] = (int) $row->shipped_qty;
        }
        return $map;
    }

    private function buildBuyerOrderEntry(object $item, array $actualShippedMap, array $purchasedMap, array $variationTitles): array
    {
        $metaData = is_array($item->line_meta) ? $item->line_meta : (is_string($item->line_meta) ? (json_decode($item->line_meta, true) ?: []) : []);
        $quantity = (int) $item->quantity;
        $shippedQty = max((int) ($metaData['_shipped_qty'] ?? 0), $actualShippedMap[(int) $item->id] ?? 0);
        $allocatedQty = max((int) ($metaData['_allocated_qty'] ?? 0), $shippedQty);
        $pendingQty = max(0, $quantity - $allocatedQty);
        $status = $shippedQty >= $quantity ? 'shipped' : ($allocatedQty >= $quantity ? 'allocated' : (($shippedQty > 0 || $allocatedQty > 0) ? 'partial' : 'pending'));
        $createdAt = $item->order->created_at ?? null;
        $orderDate = is_object($createdAt) && method_exists($createdAt, 'format')
            ? $createdAt->format('Y/m/d')
            : (is_string($createdAt) ? date('Y/m/d', strtotime($createdAt)) : '');

        return [
            'order_item_id' => $item->id,
            'order_id' => $item->order->id,
            'invoice_no' => $item->order->invoice_no ?? "#{$item->order->id}",
            'customer_id' => $item->order->customer->id,
            'customer_name' => $item->order->customer->full_name ?? $item->order->customer->email,
            'customer_email' => $item->order->customer->email,
            'object_id' => (int) $item->object_id,
            'variation_title' => $variationTitles[(int) $item->object_id] ?? '',
            'quantity' => $quantity,
            'purchased' => $purchasedMap[(int) $item->object_id] ?? 0,
            'allocated_quantity' => $allocatedQty,
            'shipped_quantity' => $shippedQty,
            'pending_quantity' => $pendingQty,
            'status' => $status,
            'is_allocated' => $allocatedQty >= $quantity,
            'order_date' => $orderDate,
            'shipping_status' => $item->order->shipping_status ?? 'unshipped',
        ];
    }
}
