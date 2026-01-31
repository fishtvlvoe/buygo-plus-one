<?php
/**
 * LINE Product Upload Bridge
 *
 * 整合 buygo-line-notify 和 buygo-plus-one-dev 的橋接器
 *
 * 隔離設計原則:
 * 1. 此檔案完全獨立,不修改任何現有檔案
 * 2. 只在 buygo-line-notify 啟用時才載入
 * 3. 使用 Hook 機制,不直接耦合程式碼
 * 4. 失敗時優雅降級,不影響現有功能
 *
 * @package BuyGoPlus\Integrations
 */

namespace BuyGoPlus\Integrations;

use BuyGoPlus\Services\SettingsService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LINE Product Upload Bridge
 *
 * 監聽 buygo-line-notify 的 Webhook 事件,處理賣家圖片上傳流程
 */
class LineProductUploadBridge
{
    /**
     * 初始化
     */
    public static function init(): void
    {
        // 檢查 buygo-line-notify 是否啟用
        if (!class_exists('\\BuygoLineNotify\\BuygoLineNotify')) {
            return; // 未啟用則靜默返回
        }

        // 註冊 Webhook 事件監聽器
        add_action('buygo_line_notify/webhook_message', [__CLASS__, 'handle_line_message'], 10, 3);
        add_action('buygo_line_notify/webhook_follow', [__CLASS__, 'handle_follow'], 10, 3);
        add_action('buygo_line_notify/webhook_postback', [__CLASS__, 'handle_postback'], 10, 3);
    }

    /**
     * 處理 LINE 訊息事件 (圖片/文字)
     *
     * @param array    $event    Webhook 事件資料
     * @param string   $line_uid LINE User ID
     * @param int|null $user_id  WordPress User ID (如果已綁定)
     * @return void
     */
    public static function handle_line_message(array $event, string $line_uid, ?int $user_id): void
    {
        // 未綁定 WordPress 帳號,忽略
        if (!$user_id) {
            return;
        }

        $message_type = $event['message']['type'] ?? '';

        if ($message_type === 'image') {
            self::handle_image_upload($event, $line_uid, $user_id);
        } elseif ($message_type === 'text') {
            self::handle_product_info($event, $line_uid, $user_id);
        }
    }

    /**
     * 處理圖片上傳
     *
     * @param array  $event    Webhook 事件
     * @param string $line_uid LINE User ID
     * @param int    $user_id  WordPress User ID
     * @return void
     */
    private static function handle_image_upload(array $event, string $line_uid, int $user_id): void
    {
        // 檢查權限
        if (!self::can_upload_product($user_id)) {
            $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
            $messaging->replyText($event['replyToken'], '❌ 您沒有上架商品的權限');
            return;
        }

        // 下載圖片到 Media Library
        $message_id = $event['message']['id'] ?? '';
        if (empty($message_id)) {
            return;
        }

        $image_service = new \BuygoLineNotify\Services\ImageService();
        $attachment_id = $image_service->downloadToMediaLibrary($message_id, $user_id);

        if (is_wp_error($attachment_id)) {
            $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
            $messaging->replyText(
                $event['replyToken'],
                '❌ 圖片下載失敗，請稍後再試'
            );

            // 記錄錯誤日誌
            error_log('[LINE Product Upload] 圖片下載失敗: ' . $attachment_id->get_error_message());
            return;
        }

        // 儲存上傳狀態
        update_user_meta($user_id, 'buygo_pending_product_image', $attachment_id);
        update_user_meta($user_id, 'buygo_pending_product_timestamp', time());

        // 發送模板文字
        $template = "📸 收到您的商品圖片！\n\n";
        $template .= "請提供以下商品資訊：\n";
        $template .= "1️⃣ 商品名稱\n";
        $template .= "2️⃣ 商品價格（元）\n";
        $template .= "3️⃣ 商品描述\n\n";
        $template .= "範例：\n";
        $template .= "MacBook Pro 13\n";
        $template .= "45000\n";
        $template .= "全新未拆封，原廠保固";

        $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
        $messaging->replyText($event['replyToken'], $template);
    }

    /**
     * 處理商品資訊輸入
     *
     * @param array  $event    Webhook 事件
     * @param string $line_uid LINE User ID
     * @param int    $user_id  WordPress User ID
     * @return void
     */
    private static function handle_product_info(array $event, string $line_uid, int $user_id): void
    {
        // 檢查是否有待處理的商品圖片
        $attachment_id = get_user_meta($user_id, 'buygo_pending_product_image', true);
        $timestamp = get_user_meta($user_id, 'buygo_pending_product_timestamp', true);

        if (empty($attachment_id)) {
            // 沒有待處理的商品,忽略此訊息
            return;
        }

        // 檢查是否超時 (30 分鐘)
        if (time() - $timestamp > 1800) {
            delete_user_meta($user_id, 'buygo_pending_product_image');
            delete_user_meta($user_id, 'buygo_pending_product_timestamp');

            $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
            $messaging->replyText($event['replyToken'], '⏰ 商品上架已超時，請重新上傳圖片');
            return;
        }

        // 解析商品資訊
        $product_info = self::parse_product_info($event['message']['text']);

        if (is_wp_error($product_info)) {
            $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
            $messaging->replyText(
                $event['replyToken'],
                '❌ 商品資訊格式錯誤：' . $product_info->get_error_message()
            );
            return;
        }

        // 建立 FluentCart 產品
        $product = self::create_fluentcart_product([
            'title' => $product_info['title'],
            'price' => $product_info['price'],
            'description' => $product_info['description'],
            'featured_image' => $attachment_id,
            'status' => 'publish',
        ]);

        if (is_wp_error($product)) {
            $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
            $messaging->replyText(
                $event['replyToken'],
                '❌ 商品建立失敗：' . $product->get_error_message()
            );

            // 記錄錯誤日誌
            error_log('[LINE Product Upload] 建立商品失敗: ' . $product->get_error_message());
            return;
        }

        // 清除待處理狀態
        delete_user_meta($user_id, 'buygo_pending_product_image');
        delete_user_meta($user_id, 'buygo_pending_product_timestamp');

        // 發送確認訊息
        $product_url = get_permalink($product);
        $confirmation = "✅ 商品已成功上架！\n\n";
        $confirmation .= "商品名稱：{$product_info['title']}\n";
        $confirmation .= "商品價格：NT$ " . number_format($product_info['price']) . "\n";
        $confirmation .= "商品連結：{$product_url}\n\n";
        $confirmation .= "您可以透過以上連結查看商品詳情。";

        $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
        $messaging->replyText($event['replyToken'], $confirmation);
    }

    /**
     * 處理關注事件
     *
     * @param array  $event    Webhook 事件
     * @param string $line_uid LINE User ID
     * @param int    $user_id  WordPress User ID
     * @return void
     */
    public static function handle_follow(array $event, string $line_uid, ?int $user_id): void
    {
        if (!$user_id) {
            return;
        }

        // 檢查是否有上架權限
        if (!self::can_upload_product($user_id)) {
            return;
        }

        $welcome_message = "歡迎使用 BuyGo 商品上架系統！\n\n";
        $welcome_message .= "直接上傳商品圖片即可開始上架。";

        $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
        $messaging->replyText($event['replyToken'], $welcome_message);
    }

    /**
     * 處理 Postback 事件 (未來可擴展按鈕互動)
     *
     * @param array  $event    Webhook 事件
     * @param string $line_uid LINE User ID
     * @param int    $user_id  WordPress User ID
     * @return void
     */
    public static function handle_postback(array $event, string $line_uid, ?int $user_id): void
    {
        // 預留給未來的按鈕互動功能
    }

    /**
     * 檢查使用者是否有上架商品權限
     *
     * @param int $user_id WordPress User ID
     * @return bool
     */
    private static function can_upload_product(int $user_id): bool
    {
        if (!$user_id) {
            return false;
        }

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }

        // 檢查角色
        if ($user->has_cap('administrator') ||
            $user->has_cap('buygo_admin') ||
            $user->has_cap('buygo_helper')) {
            return true;
        }

        // 檢查 wp_buygo_helpers 表 (舊版相容)
        global $wpdb;
        $table = $wpdb->prefix . 'buygo_helpers';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
            $user_id
        ));

        return $exists > 0;
    }

    /**
     * 解析商品資訊
     *
     * @param string $text 使用者輸入的文字
     * @return array|\WP_Error
     */
    private static function parse_product_info(string $text)
    {
        $lines = array_map('trim', explode("\n", trim($text)));
        $lines = array_filter($lines); // 移除空行

        if (count($lines) < 3) {
            return new \WP_Error(
                'invalid_format',
                '商品資訊格式錯誤（需要至少 3 行：名稱、價格、描述）'
            );
        }

        $title = sanitize_text_field($lines[0]);
        $price = intval($lines[1]);
        $description = sanitize_textarea_field(implode("\n", array_slice($lines, 2)));

        if (empty($title)) {
            return new \WP_Error('empty_title', '商品名稱不能為空');
        }

        if ($price <= 0) {
            return new \WP_Error('invalid_price', '商品價格必須大於 0');
        }

        return [
            'title' => $title,
            'price' => $price,
            'description' => $description,
        ];
    }

    /**
     * 建立 FluentCart 產品
     *
     * @param array $product_data 產品資料
     * @return int|\WP_Error 成功返回 Post ID,失敗返回 WP_Error
     */
    private static function create_fluentcart_product(array $product_data)
    {
        // 建立 FluentCart 產品 (post_type: fluent-products)
        $post_id = wp_insert_post([
            'post_title' => $product_data['title'],
            'post_content' => $product_data['description'],
            'post_status' => $product_data['status'] ?? 'publish',
            'post_type' => 'fluent-products',
        ]);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // 設定特色圖片
        if (!empty($product_data['featured_image'])) {
            set_post_thumbnail($post_id, $product_data['featured_image']);
        }

        // 設定價格 (FluentCart 使用 post meta)
        update_post_meta($post_id, '_regular_price', $product_data['price']);
        update_post_meta($post_id, '_price', $product_data['price']);

        // 設定庫存 (預設有庫存)
        update_post_meta($post_id, '_stock_status', 'instock');

        return $post_id;
    }
}
