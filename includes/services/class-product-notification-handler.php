<?php
/**
 * Product Notification Handler
 *
 * 處理商品上架通知
 *
 * @package BuyGoPlus
 * @since 1.2.0
 */

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ProductNotificationHandler
 *
 * 監聽商品建立事件並發送通知給賣家和小幫手
 */
class ProductNotificationHandler
{
    /**
     * Debug Service 實例
     *
     * @var DebugService
     */
    private $debug_service;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->debug_service = DebugService::get_instance();

        // 監聽商品建立事件
        add_action('buygo/product/created', [$this, 'onProductCreated'], 10, 3);
    }

    /**
     * 處理商品建立事件
     *
     * @param int    $product_id   商品 ID
     * @param array  $product_data 商品資料
     * @param string $line_uid     LINE UID（透過 LINE 建立時才有）
     */
    public function onProductCreated($product_id, $product_data, $line_uid)
    {
        // 只處理透過 LINE 建立的商品（有 line_uid）
        if (empty($line_uid)) {
            $this->debug_service->log('ProductNotificationHandler', '跳過通知：非 LINE 建立的商品', [
                'product_id' => $product_id,
            ]);
            return;
        }

        // 取得賣家 ID
        $seller_id = $product_data['user_id'] ?? null;
        if (!$seller_id) {
            $this->debug_service->log('ProductNotificationHandler', '跳過通知：無賣家 ID', [
                'product_id' => $product_id,
            ], 'warning');
            return;
        }

        // 準備通知參數
        $product_name = $product_data['name'] ?? '';
        $product_url = home_url("/item/{$product_id}");

        $this->debug_service->log('ProductNotificationHandler', '發送商品上架通知', [
            'product_id' => $product_id,
            'product_name' => $product_name,
            'seller_id' => $seller_id,
        ]);

        // 發送通知給賣家和小幫手
        $result = NotificationService::sendToSellerAndHelpers($seller_id, 'product_created', [
            'product_name' => $product_name,
            'product_url' => $product_url,
            'product_id' => $product_id,
        ]);

        $this->debug_service->log('ProductNotificationHandler', '通知發送結果', [
            'product_id' => $product_id,
            'success' => $result['success'] ?? 0,
            'failed' => $result['failed'] ?? 0,
            'skipped' => $result['skipped'] ?? 0,
        ]);
    }
}
