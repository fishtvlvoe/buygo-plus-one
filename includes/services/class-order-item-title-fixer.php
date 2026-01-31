<?php
namespace BuyGoPlus\Services;

/**
 * Order Item Title Fixer - 修復 FluentCart 訂單項目標題為空的問題
 *
 * 問題：FluentCart 在建立訂單時，fct_order_items.item_title 欄位為空
 * 解決方案：Hook into order creation 並自動填入商品標題
 *
 * @package BuyGoPlus\Services
 * @since 0.2.2
 */
class OrderItemTitleFixer
{
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        // Hook into FluentCart order creation
        add_action('fluent_cart/order_created', [$this, 'fix_order_item_titles'], 20, 1);
    }

    /**
     * 修復訂單項目標題
     *
     * @param array $order_data FluentCart 訂單資料
     * @return void
     */
    public function fix_order_item_titles($order_data)
    {
        if (empty($order_data['id'])) {
            return;
        }

        global $wpdb;
        $order_id = $order_data['id'];

        // 取得該訂單的所有項目
        $table_order_items = $wpdb->prefix . 'fct_order_items';

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT id, product_id, item_title, variation_title
             FROM {$table_order_items}
             WHERE order_id = %d",
            $order_id
        ), ARRAY_A);

        if (empty($items)) {
            return;
        }

        // 逐一檢查並修復空白的 item_title
        foreach ($items as $item) {
            // 如果 item_title 已經有值，跳過
            if (!empty($item['item_title'])) {
                continue;
            }

            // 從 wp_posts 讀取商品標題
            $post = get_post($item['product_id']);

            if (!$post || empty($post->post_title)) {
                // 如果找不到商品，使用 variation_title
                $title = !empty($item['variation_title']) ? $item['variation_title'] : '未命名商品';
            } else {
                $title = $post->post_title;
            }

            // 更新 item_title
            $wpdb->update(
                $table_order_items,
                ['item_title' => $title],
                ['id' => $item['id']],
                ['%s'],
                ['%d']
            );

            error_log("[BuyGo] 修復訂單 #{$order_id} 項目 #{$item['id']} 的標題: {$title}");
        }
    }
}
