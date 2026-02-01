<?php
/**
 * Notification Service
 *
 * 通知服務 - 整合 buygo-line-notify 的 MessagingService
 *
 * @package BuyGoPlus
 * @since 1.2.0
 */

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NotificationService
 *
 * 提供統一的通知發送介面，整合 buygo-line-notify 外掛
 * 支援 soft dependency：buygo-line-notify 未啟用時優雅降級
 */
class NotificationService
{
    /**
     * Debug Service 實例
     *
     * @var DebugService|null
     */
    private static $debug_service = null;

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
     * 檢查 buygo-line-notify 是否可用
     *
     * @return bool
     */
    public static function isLineNotifyAvailable(): bool
    {
        return class_exists('\\BuygoLineNotify\\Services\\MessagingService');
    }

    /**
     * 發送文字通知
     *
     * @param int $user_id WordPress User ID
     * @param string $template_key 模板 key
     * @param array $args 模板變數
     * @return bool 發送是否成功
     */
    public static function sendText(int $user_id, string $template_key, array $args = []): bool
    {
        self::init_debug_service();

        // 檢查 buygo-line-notify 是否可用
        if (!self::isLineNotifyAvailable()) {
            self::$debug_service->log('NotificationService', 'buygo-line-notify 未啟用', [
                'user_id' => $user_id,
                'template_key' => $template_key,
            ], 'warning');
            return false;
        }

        // 檢查用戶是否有 LINE 綁定
        if (!IdentityService::hasLineBinding($user_id)) {
            self::$debug_service->log('NotificationService', '用戶未綁定 LINE', [
                'user_id' => $user_id,
            ], 'warning');
            return false;
        }

        // 取得模板內容
        $template = NotificationTemplates::get($template_key, $args);
        if (!$template) {
            self::$debug_service->log('NotificationService', '模板不存在', [
                'template_key' => $template_key,
            ], 'error');
            return false;
        }

        // 取得訊息內容（支援 'text' 或 'message' 鍵名）
        $message = $template['line']['text'] ?? $template['line']['message'] ?? '';
        if (empty($message)) {
            self::$debug_service->log('NotificationService', '模板訊息為空', [
                'template_key' => $template_key,
            ], 'warning');
            return false;
        }

        // 發送訊息
        try {
            $result = \BuygoLineNotify\Services\MessagingService::pushText($user_id, $message);

            if (is_wp_error($result)) {
                self::$debug_service->log('NotificationService', '發送失敗', [
                    'user_id' => $user_id,
                    'error' => $result->get_error_message(),
                ], 'error');
                return false;
            }

            self::$debug_service->log('NotificationService', '發送成功', [
                'user_id' => $user_id,
                'template_key' => $template_key,
            ]);

            return true;
        } catch (\Exception $e) {
            self::$debug_service->log('NotificationService', '發送異常', [
                'user_id' => $user_id,
                'error' => $e->getMessage(),
            ], 'error');
            return false;
        }
    }

    /**
     * 發送 Flex Message 通知
     *
     * @param int $user_id WordPress User ID
     * @param string $template_key 模板 key
     * @param array $args 模板變數
     * @return bool 發送是否成功
     */
    public static function sendFlex(int $user_id, string $template_key, array $args = []): bool
    {
        self::init_debug_service();

        // 檢查 buygo-line-notify 是否可用
        if (!self::isLineNotifyAvailable()) {
            self::$debug_service->log('NotificationService', 'buygo-line-notify 未啟用', [
                'user_id' => $user_id,
                'template_key' => $template_key,
            ], 'warning');
            return false;
        }

        // 檢查用戶是否有 LINE 綁定
        if (!IdentityService::hasLineBinding($user_id)) {
            self::$debug_service->log('NotificationService', '用戶未綁定 LINE', [
                'user_id' => $user_id,
            ], 'warning');
            return false;
        }

        // 取得模板內容
        $template = NotificationTemplates::get($template_key, $args);
        if (!$template || ($template['type'] ?? '') !== 'flex') {
            self::$debug_service->log('NotificationService', 'Flex 模板不存在或類型錯誤', [
                'template_key' => $template_key,
            ], 'error');
            return false;
        }

        // 取得 Flex 內容
        $flex_template = $template['line']['flex_template'] ?? [];
        if (empty($flex_template)) {
            self::$debug_service->log('NotificationService', 'Flex 模板內容為空', [
                'template_key' => $template_key,
            ], 'warning');
            return false;
        }

        // 構建 Flex Message
        $flex_message = self::buildFlexMessage($flex_template);

        // 發送訊息
        try {
            $result = \BuygoLineNotify\Services\MessagingService::pushFlex($user_id, $flex_message);

            if (is_wp_error($result)) {
                self::$debug_service->log('NotificationService', 'Flex 發送失敗', [
                    'user_id' => $user_id,
                    'error' => $result->get_error_message(),
                ], 'error');
                return false;
            }

            self::$debug_service->log('NotificationService', 'Flex 發送成功', [
                'user_id' => $user_id,
                'template_key' => $template_key,
            ]);

            return true;
        } catch (\Exception $e) {
            self::$debug_service->log('NotificationService', 'Flex 發送異常', [
                'user_id' => $user_id,
                'error' => $e->getMessage(),
            ], 'error');
            return false;
        }
    }

    /**
     * 發送通知（自動判斷類型）
     *
     * @param int $user_id WordPress User ID
     * @param string $template_key 模板 key
     * @param array $args 模板變數
     * @return bool 發送是否成功
     */
    public static function send(int $user_id, string $template_key, array $args = []): bool
    {
        // 取得模板判斷類型
        $template = NotificationTemplates::get($template_key, $args);
        if (!$template) {
            return false;
        }

        $type = $template['type'] ?? 'text';

        if ($type === 'flex') {
            return self::sendFlex($user_id, $template_key, $args);
        }

        return self::sendText($user_id, $template_key, $args);
    }

    /**
     * 發送通知給多個用戶
     *
     * @param array $user_ids WordPress User IDs
     * @param string $template_key 模板 key
     * @param array $args 模板變數
     * @return array ['success' => int, 'failed' => int, 'skipped' => int]
     */
    public static function sendToMultiple(array $user_ids, string $template_key, array $args = []): array
    {
        $result = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($user_ids as $user_id) {
            // 檢查是否有 LINE 綁定
            if (!IdentityService::hasLineBinding($user_id)) {
                $result['skipped']++;
                continue;
            }

            if (self::send($user_id, $template_key, $args)) {
                $result['success']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * 發送通知給賣家和所有小幫手
     *
     * @param int $seller_id 賣家 ID
     * @param string $template_key 模板 key
     * @param array $args 模板變數
     * @return array ['success' => int, 'failed' => int, 'skipped' => int]
     */
    public static function sendToSellerAndHelpers(int $seller_id, string $template_key, array $args = []): array
    {
        self::init_debug_service();

        // 收集所有需要通知的用戶 ID
        $user_ids = [$seller_id];

        // 取得小幫手列表
        $helpers = SettingsService::get_helpers($seller_id);
        foreach ($helpers as $helper) {
            $user_ids[] = $helper['id'];
        }

        // 去重
        $user_ids = array_unique($user_ids);

        self::$debug_service->log('NotificationService', '發送給賣家和小幫手', [
            'seller_id' => $seller_id,
            'user_count' => count($user_ids),
            'template_key' => $template_key,
        ]);

        return self::sendToMultiple($user_ids, $template_key, $args);
    }

    /**
     * 構建 Flex Message
     *
     * @param array $flex_template 模板資料
     * @return array Flex Message 結構
     */
    private static function buildFlexMessage(array $flex_template): array
    {
        $title = $flex_template['title'] ?? '';
        $description = $flex_template['description'] ?? '';
        $buttons = $flex_template['buttons'] ?? [];

        // 基本的 Bubble 結構
        $body_contents = [];

        if (!empty($title)) {
            $body_contents[] = [
                'type' => 'text',
                'text' => $title,
                'weight' => 'bold',
                'size' => 'xl',
            ];
        }

        if (!empty($description)) {
            $body_contents[] = [
                'type' => 'text',
                'text' => $description,
                'wrap' => true,
                'margin' => 'md',
            ];
        }

        $bubble = [
            'type' => 'bubble',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => $body_contents,
            ],
        ];

        // 添加按鈕
        if (!empty($buttons)) {
            $footer_contents = [];
            foreach ($buttons as $button) {
                $footer_contents[] = [
                    'type' => 'button',
                    'style' => 'primary',
                    'action' => [
                        'type' => 'uri',
                        'label' => $button['label'] ?? '查看',
                        'uri' => $button['action'] ?? '#',
                    ],
                ];
            }

            $bubble['footer'] = [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => $footer_contents,
            ];
        }

        return [
            'type' => 'flex',
            'altText' => $title ?: '通知',
            'contents' => $bubble,
        ];
    }

    /**
     * 發送原始訊息（不使用模板）
     *
     * @param int $user_id WordPress User ID
     * @param string $message 訊息內容
     * @return bool 發送是否成功
     */
    public static function sendRawText(int $user_id, string $message): bool
    {
        self::init_debug_service();

        if (!self::isLineNotifyAvailable()) {
            return false;
        }

        if (!IdentityService::hasLineBinding($user_id)) {
            return false;
        }

        try {
            $result = \BuygoLineNotify\Services\MessagingService::pushText($user_id, $message);
            return !is_wp_error($result);
        } catch (\Exception $e) {
            self::$debug_service->log('NotificationService', '發送原始訊息失敗', [
                'error' => $e->getMessage(),
            ], 'error');
            return false;
        }
    }
}
