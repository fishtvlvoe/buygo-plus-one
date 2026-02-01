<?php
/**
 * LINE Response Provider
 *
 * 監聽 buygo-line-notify 的 filter，提供回覆模板內容
 *
 * 架構說明：
 * - buygo-line-notify 處理 LINE webhook 接收和訊息發送
 * - buygo-plus-one-dev 透過此類別提供「要回覆什麼內容」
 * - 此類別不直接發送訊息，只返回模板內容給 buygo-line-notify
 *
 * @package BuyGoPlus
 * @since 1.2.0
 */

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LineResponseProvider
 *
 * 負責：
 * 1. 監聽 buygo_line_notify/get_response filter
 * 2. 根據 action_type 和用戶身份決定回覆內容
 * 3. 使用 NotificationTemplates 產生格式化訊息
 */
class LineResponseProvider
{
    /**
     * Debug Service 實例
     *
     * @var DebugService|null
     */
    private static $debug_service = null;

    /**
     * 初始化
     */
    public static function init(): void
    {
        // 檢查 buygo-line-notify 是否啟用
        if (!class_exists('\\BuygoLineNotify\\BuygoLineNotify')) {
            return; // 未啟用則靜默返回
        }

        // 註冊 filter 監聽器
        add_filter('buygo_line_notify/get_response', [__CLASS__, 'provide_response'], 10, 5);
    }

    /**
     * 初始化 Debug Service
     */
    private static function init_debug_service(): void
    {
        if (self::$debug_service === null) {
            self::$debug_service = DebugService::get_instance();
        }
    }

    /**
     * 提供回覆內容
     *
     * 這是與 buygo-line-notify 的主要橋接點。
     * buygo-line-notify 透過 apply_filters 詢問「這個事件要回覆什麼？」
     * 我們根據 action_type 和用戶身份決定回覆內容。
     *
     * @param mixed    $response    預設回覆（null 表示不回覆）
     * @param string   $action_type 事件類型（message_text, message_image, follow, postback...）
     * @param array    $event       LINE Webhook 事件資料
     * @param string   $line_uid    LINE User ID
     * @param int|null $user_id     WordPress User ID (null if not linked)
     * @return mixed 回覆內容（null=不回覆, string=純文字, array=LINE Message 格式）
     */
    public static function provide_response($response, string $action_type, array $event, string $line_uid, ?int $user_id)
    {
        self::init_debug_service();

        // 如果已有其他外掛提供回覆，不覆蓋
        if ($response !== null) {
            return $response;
        }

        // 取得用戶身份
        $identity = IdentityService::getIdentityByLineUid($line_uid);
        $role = $identity['role'];

        self::$debug_service->log('LineResponseProvider', '處理回覆請求', [
            'action_type' => $action_type,
            'line_uid' => $line_uid,
            'user_id' => $user_id,
            'role' => $role,
        ]);

        // Phase 29: Bot 回應邏輯
        // 只有賣家和小幫手可以與 bot 互動，買家和未綁定用戶不回覆
        if (!in_array($role, [IdentityService::ROLE_SELLER, IdentityService::ROLE_HELPER])) {
            self::$debug_service->log('LineResponseProvider', '非賣家/小幫手，不回覆', [
                'role' => $role,
            ]);
            return null; // 返回 null 表示不回覆（靜默）
        }

        // 根據 action_type 決定回覆內容
        switch ($action_type) {
            case 'follow':
                return self::get_follow_response($event, $identity);

            case 'message_image':
                return self::get_image_upload_response($event, $identity);

            case 'message_text':
                return self::get_text_response($event, $identity);

            case 'postback':
                return self::get_postback_response($event, $identity);

            default:
                // 未處理的事件類型，不回覆
                return null;
        }
    }

    /**
     * 取得 follow 事件的回覆
     *
     * @param array $event    LINE 事件資料
     * @param array $identity 用戶身份資訊
     * @return array|null LINE Message 格式或 null
     */
    private static function get_follow_response(array $event, array $identity): ?array
    {
        // 使用模板系統取得歡迎訊息
        $template = NotificationTemplates::get('system_line_follow');

        if (!$template || empty($template['line']['text'])) {
            return null;
        }

        return $template['line'];
    }

    /**
     * 取得圖片上傳事件的回覆
     *
     * @param array $event    LINE 事件資料
     * @param array $identity 用戶身份資訊
     * @return array|null LINE Message 格式或 null
     */
    private static function get_image_upload_response(array $event, array $identity): ?array
    {
        // 使用 get_by_trigger_condition 取得圖片上傳選單模板
        // 這會返回 Flex Message 格式
        $templates = NotificationTemplates::get_by_trigger_condition('flex_image_upload_menu');

        if (empty($templates)) {
            // 如果沒有設定模板，使用預設的 get 方法
            $template = NotificationTemplates::get('flex_image_upload_menu');
            if (!$template) {
                return null;
            }

            return self::build_line_message($template);
        }

        // 取得第一個模板（通常只有一個）
        $template = $templates[0];
        return self::build_line_message($template);
    }

    /**
     * 取得文字訊息事件的回覆
     *
     * 這裡處理命令和關鍵字回覆
     *
     * @param array $event    LINE 事件資料
     * @param array $identity 用戶身份資訊
     * @return array|null LINE Message 格式或 null
     */
    private static function get_text_response(array $event, array $identity): ?array
    {
        $text = $event['message']['text'] ?? '';
        $text = trim($text);

        // 處理命令
        if (strpos($text, '/') === 0) {
            return self::handle_command($text, $identity);
        }

        // 這裡可以擴展關鍵字回覆等功能
        // 目前不做任何處理，讓其他系統（如商品上架流程）處理
        return null;
    }

    /**
     * 處理命令
     *
     * @param string $command  命令文字（如 /one, /many, /help）
     * @param array  $identity 用戶身份資訊
     * @return array|null LINE Message 格式或 null
     */
    private static function handle_command(string $command, array $identity): ?array
    {
        $command = strtolower(trim($command));

        switch ($command) {
            case '/one':
                $template = NotificationTemplates::get('system_command_one_template');
                break;

            case '/many':
                $template = NotificationTemplates::get('system_command_many_template');
                break;

            case '/help':
                // help 命令使用歡迎訊息模板
                $template = NotificationTemplates::get('system_line_follow');
                break;

            default:
                return null;
        }

        if (!$template || empty($template['line']['text'])) {
            return null;
        }

        return $template['line'];
    }

    /**
     * 取得 postback 事件的回覆
     *
     * @param array $event    LINE 事件資料
     * @param array $identity 用戶身份資訊
     * @return array|null LINE Message 格式或 null
     */
    private static function get_postback_response(array $event, array $identity): ?array
    {
        // Postback 的 data 欄位包含按鈕動作
        $data = $event['postback']['data'] ?? '';

        // 目前 postback 主要用於 Flex Message 按鈕
        // 按鈕的 action type 是 'message'，所以實際上會觸發 message_text
        // 這裡預留給未來的 postback 類型按鈕使用

        return null;
    }

    /**
     * 將模板轉換為 LINE Message 格式
     *
     * @param array $template NotificationTemplates 返回的模板
     * @return array|null LINE Message 格式或 null
     */
    private static function build_line_message(array $template): ?array
    {
        $type = $template['type'] ?? 'text';

        if ($type === 'flex') {
            // Flex Message 模板
            $flex_template = $template['line']['flex_template'] ?? [];
            if (empty($flex_template)) {
                return null;
            }

            // 使用 NotificationTemplates 的 build_flex_message 方法
            return NotificationTemplates::build_flex_message($flex_template);
        }

        // 文字模板
        $text = $template['line']['text'] ?? '';
        if (empty($text)) {
            return null;
        }

        return [
            'type' => 'text',
            'text' => $text,
        ];
    }
}
