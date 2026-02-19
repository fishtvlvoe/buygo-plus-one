<?php

namespace BuyGoPlus\Services;

defined('ABSPATH') || exit;

use FluentCart\App\Models\Order;

/**
 * Order Formatter - 訂單資料格式化服務
 *
 * 從 OrderService 抽出，負責將 FluentCart Order Model 轉換為前端所需的陣列格式。
 * 包含商品名稱解析、圖片讀取、地址組合、子訂單載入等邏輯。
 *
 * @package BuyGoPlus\Services
 * @since 2.1.0
 */
class OrderFormatter
{
    private $debugService;

    public function __construct(DebugService $debugService)
    {
        $this->debugService = $debugService;
    }

    /**
     * 格式化訂單資料
     *
     * @param mixed $order 訂單物件或陣列
     * @return array
     */
    public function format($order): array
    {
        // 如果是物件，轉換為陣列
        if (is_object($order)) {
            $order = $order->toArray();
        }

        // 計算商品總數量
        $total_items = 0;
        $items = [];

        // 預先從資料庫讀取所有訂單項目的 line_meta（確保資料是最新的）
        global $wpdb;
        $table_items = $wpdb->prefix . 'fct_order_items';
        $order_id = $order['id'] ?? 0;
        $db_items = [];
        if ($order_id > 0) {
            $db_results = $wpdb->get_results($wpdb->prepare(
                "SELECT id, line_meta FROM {$table_items} WHERE order_id = %d",
                $order_id
            ), ARRAY_A);
            foreach ($db_results as $row) {
                $db_items[(int)$row['id']] = $row['line_meta'];
            }
        }

        // 【修復 2026-01-31】FluentCart Model 的 toArray() 可能返回 orderItems（駝峰命名）或 order_items（底線命名）
        $order_items = $order['order_items'] ?? $order['orderItems'] ?? [];
        if (is_array($order_items) && count($order_items) > 0) {
            foreach ($order_items as $item) {
                $formatted_item = $this->formatOrderItem($item, $order, $db_items, $wpdb);
                $total_items += $formatted_item['quantity'];
                $items[] = $formatted_item;
            }
        }

        // 取得客戶資料（含地址和電話）
        $customer_data = $this->resolveCustomerData($order, $order_id, $wpdb);

        // 取得子訂單資訊（如果是父訂單）
        $children = [];
        $parent_id = $order['parent_id'] ?? null;
        $order_type = $order['type'] ?? 'one-time';
        $is_child_order = !empty($parent_id);

        if (!$is_child_order) {
            $children = $this->loadChildOrders($order);
        }

        return [
            'id' => $order['id'] ?? 0,
            'invoice_no' => $order['invoice_no'] ?? '',
            'status' => $order['status'] ?? 'pending',
            'shipping_status' => $order['shipping_status'] ?? 'unshipped',
            'total_amount' => isset($order['total_amount']) ? ($order['total_amount'] / 100) : 0,
            'currency' => $order['currency'] ?? 'TWD',
            'payment_method' => $order['payment_method'] ?? '未提供',
            'customer_name' => $customer_data['name'],
            'customer_email' => $customer_data['email'],
            'customer_phone' => $customer_data['phone'],
            'customer_address' => $customer_data['address'],
            'total_items' => $total_items,
            'items' => $items,
            'created_at' => $order['created_at'] ?? '',
            'updated_at' => $order['updated_at'] ?? '',
            // 父子訂單關聯資訊
            'parent_id' => $parent_id,
            'type' => $order_type,
            'is_child_order' => $is_child_order,
            'children' => $children
        ];
    }

    /**
     * 格式化單一訂單項目
     *
     * @param mixed $item 訂單項目
     * @param array $order 訂單陣列
     * @param array $db_items 從 DB 讀取的 line_meta 快取
     * @param \wpdb $wpdb WordPress DB 物件
     * @return array
     */
    private function formatOrderItem($item, array $order, array $db_items, $wpdb): array
    {
        if (is_object($item)) {
            $item = (array) $item;
        }

        $quantity = $item['quantity'] ?? 0;

        // 取得商品名稱
        $product_name = $this->resolveProductName($item, $wpdb);

        // 讀取 line_meta：優先使用從 DB 直接讀取的資料（確保最新）
        $item_id = (int)($item['id'] ?? 0);
        $line_meta_value = $db_items[$item_id] ?? $item['line_meta'] ?? $item['meta_data'] ?? '{}';

        if (is_array($line_meta_value)) {
            $meta_data = $line_meta_value;
        } elseif (is_string($line_meta_value)) {
            $meta_data = json_decode($line_meta_value, true) ?: [];
        } else {
            $meta_data = [];
        }

        $allocated_quantity = (int)($meta_data['_allocated_qty'] ?? 0);
        $shipped_quantity = (int)($meta_data['_shipped_qty'] ?? 0);

        $product_id = (int)($item['post_id'] ?? $item['product_id'] ?? 0);

        // 取得商品圖片 URL
        $product_image = '';
        if ($product_id > 0) {
            $thumbnail_id = get_post_thumbnail_id($product_id);
            if ($thumbnail_id) {
                $product_image = wp_get_attachment_image_url($thumbnail_id, 'medium') ?: '';
            }
        }

        // 計算單價和總金額
        $unit_price = isset($item['unit_price']) ? ($item['unit_price'] / 100) : 0;
        $line_total = isset($item['line_total']) ? ($item['line_total'] / 100) : 0;

        // 【修復】如果 line_total 是 0 但有單價和數量，則計算正確的總金額
        if ($line_total == 0 && $unit_price > 0 && $quantity > 0) {
            $line_total = $unit_price * $quantity;
        }

        return [
            'id' => $item['id'] ?? 0,
            'order_id' => $order['id'] ?? 0,
            'product_id' => $product_id,
            'product_name' => $product_name,
            'product_image' => $product_image,
            'quantity' => $quantity,
            'price' => $unit_price,
            'total' => $line_total,
            'allocated_quantity' => $allocated_quantity,
            'shipped_quantity' => $shipped_quantity,
            'pending_quantity' => max(0, $quantity - $allocated_quantity - $shipped_quantity)
        ];
    }

    /**
     * 解析商品名稱（多層 fallback）
     *
     * @param array $item 訂單項目
     * @param \wpdb $wpdb WordPress DB 物件
     * @return string
     */
    private function resolveProductName(array $item, $wpdb): string
    {
        $product_name = $item['title'] ?? '';

        // 如果 title 是"預設"或為空，嘗試從 FluentCart variation 表讀取
        if (empty($product_name) || $product_name === '預設' || $product_name === '预设') {
            $variation_id = (int)($item['object_id'] ?? 0);
            if ($variation_id > 0) {
                $table_variations = $wpdb->prefix . 'fct_product_variations';
                $variation_title = $wpdb->get_var($wpdb->prepare(
                    "SELECT variation_title FROM {$table_variations} WHERE id = %d",
                    $variation_id
                ));
                if (!empty($variation_title)) {
                    $product_name = $variation_title;
                }
            }
        }

        // 如果仍然沒有，從 WordPress posts 表讀取
        if (empty($product_name) || $product_name === '預設' || $product_name === '预设') {
            $post_id = (int)($item['post_id'] ?? 0);
            if ($post_id > 0) {
                $product_name = get_the_title($post_id);
            }
        }

        if (empty($product_name)) {
            $product_name = '未知商品';
        }

        return $product_name;
    }

    /**
     * 解析客戶資料（地址、電話、姓名）
     *
     * @param array $order 訂單陣列
     * @param int $order_id 訂單 ID
     * @param \wpdb $wpdb WordPress DB 物件
     * @return array ['name', 'email', 'phone', 'address']
     */
    private function resolveCustomerData(array $order, int $order_id, $wpdb): array
    {
        $customer_name = '';
        $customer_email = '';
        $customer_phone = '';
        $customer_address = '';

        // 從訂單地址表讀取收件人資訊
        $table_order_addresses = $wpdb->prefix . 'fct_order_addresses';

        $order_address = $wpdb->get_row($wpdb->prepare(
            "SELECT name, meta, address_1, address_2, city, state, postcode, country
             FROM {$table_order_addresses}
             WHERE order_id = %d
             ORDER BY type = 'shipping' DESC, type = 'billing' DESC
             LIMIT 1",
            $order_id
        ), ARRAY_A);

        // 【修復】如果是子訂單且沒有地址記錄，從父訂單取得
        $parent_id = $order['parent_id'] ?? null;
        if (empty($order_address) && !empty($parent_id)) {
            $order_address = $wpdb->get_row($wpdb->prepare(
                "SELECT name, meta, address_1, address_2, city, state, postcode, country
                 FROM {$table_order_addresses}
                 WHERE order_id = %d
                 ORDER BY type = 'shipping' DESC, type = 'billing' DESC
                 LIMIT 1",
                $parent_id
            ), ARRAY_A);
        }

        if ($order_address) {
            $customer_name = $order_address['name'] ?? '';

            $address_meta = json_decode($order_address['meta'] ?? '{}', true) ?: [];
            $customer_phone = $address_meta['other_data']['phone'] ?? '';

            $address_parts = array_filter([
                $order_address['address_1'] ?? '',
                $order_address['address_2'] ?? '',
                $order_address['city'] ?? '',
                $order_address['state'] ?? '',
                $order_address['postcode'] ?? '',
                $order_address['country'] ?? ''
            ]);
            $customer_address = implode(', ', $address_parts);
        }

        // 從 customer 關聯讀取 email
        if (isset($order['customer'])) {
            $customer = $order['customer'];
            if (is_object($customer)) {
                $customer = $customer->toArray();
            }
            $customer_email = $customer['email'] ?? '';

            if (empty($customer_name)) {
                $first_name = $customer['first_name'] ?? '';
                $last_name = $customer['last_name'] ?? '';
                $customer_name = trim($first_name . ' ' . $last_name);
            }
        }

        return [
            'name' => $customer_name,
            'email' => $customer_email,
            'phone' => $customer_phone,
            'address' => $customer_address
        ];
    }

    /**
     * 載入子訂單（如果是父訂單）
     *
     * @param array $order 父訂單陣列
     * @return array 格式化後的子訂單陣列
     */
    private function loadChildOrders(array $order): array
    {
        $children = [];

        $child_orders = Order::with(['customer', 'order_items'])
            ->where('parent_id', $order['id'])
            ->orderBy('created_at', 'DESC')
            ->get();

        foreach ($child_orders as $child) {
            // 遞迴格式化子訂單
            $formatted_child = $this->format($child);
            // 只保留必要欄位，避免無限遞迴
            $children[] = [
                'id' => $formatted_child['id'],
                'invoice_no' => $formatted_child['invoice_no'],
                'status' => $formatted_child['status'],
                'shipping_status' => $formatted_child['shipping_status'],
                'total_amount' => $formatted_child['total_amount'],
                'currency' => $formatted_child['currency'],
                'created_at' => $formatted_child['created_at'],
                'items' => $formatted_child['items'],
                'total_items' => $formatted_child['total_items']
            ];
        }

        return $children;
    }
}
