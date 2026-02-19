<?php
/**
 * 表單處理：LINE 設定和模板提交
 *
 * 從 class-settings-page.php 拆分的表單處理邏輯
 *
 * 包含：
 * - handle_form_submit(): LINE 設定表單
 * - handle_templates_submit(): 模板編輯表單
 */
if (!defined('ABSPATH')) {
    exit;
}

use BuyGoPlus\Services\SettingsService;
use BuyGoPlus\Services\NotificationTemplates;

/**
 * 處理 LINE 設定表單提交
 */
function buygo_handle_form_submit(): void
{
    if (isset($_POST['line_channel_access_token'])) {
        SettingsService::update_line_settings([
            'channel_access_token' => sanitize_text_field($_POST['line_channel_access_token'] ?? ''),
            'channel_secret' => sanitize_text_field($_POST['line_channel_secret'] ?? ''),
            'liff_id' => sanitize_text_field($_POST['line_liff_id'] ?? ''),
        ]);

        add_settings_error(
            'buygo_settings',
            'settings_saved',
            '設定已儲存',
            'updated'
        );
    }
}

/**
 * 處理模板編輯表單提交
 */
function buygo_handle_templates_submit(): void
{
    if (isset($_POST['submit_templates']) && isset($_POST['templates']) && wp_verify_nonce($_POST['_wpnonce'], 'buygo_settings')) {
        $templates = $_POST['templates'];

        // 取得所有現有自訂模板
        $all_custom = get_option('buygo_notification_templates', []);

        // 取得所有模板（包含預設和自訂）
        $all_templates = NotificationTemplates::get_all_templates();

        // 處理每個提交的模板
        foreach ($templates as $key => $template_data) {
            $template_type = sanitize_text_field($template_data['type'] ?? 'text');

            if ($template_type === 'flex') {
                // Flex Message 模板
                $flex_template = $template_data['line']['flex_template'] ?? [];

                if (!empty($flex_template)) {
                    // 取得當前模板（可能是預設或自訂）
                    $current_template = $all_templates[$key] ?? null;

                    if ($current_template) {
                        // 建立自訂 Flex Message 模板結構
                        $all_custom[$key] = [
                            'type' => 'flex',
                            'line' => [
                                'flex_template' => [
                                    'logo_url' => sanitize_text_field($flex_template['logo_url'] ?? ''),
                                    'title' => sanitize_text_field($flex_template['title'] ?? ''),
                                    'description' => sanitize_textarea_field($flex_template['description'] ?? ''),
                                    'buttons' => []
                                ]
                            ]
                        ];

                        // 處理按鈕
                        if (isset($flex_template['buttons']) && is_array($flex_template['buttons'])) {
                            foreach ($flex_template['buttons'] as $button) {
                                if (!empty($button['label']) || !empty($button['action'])) {
                                    $all_custom[$key]['line']['flex_template']['buttons'][] = [
                                        'label' => sanitize_text_field($button['label'] ?? ''),
                                        'action' => sanitize_text_field($button['action'] ?? '')
                                    ];
                                }
                            }
                        }
                    }
                }
            } elseif (isset($template_data['line']['message'])) {
                // 文字模板
                // 取得當前模板（可能是預設或自訂）
                $current_template = $all_templates[$key] ?? null;

                if ($current_template) {
                    // 建立自訂模板結構（移除 email 結構）
                    $all_custom[$key] = [
                        'line' => [
                            'message' => sanitize_textarea_field($template_data['line']['message'])
                        ]
                    ];
                }
            }
        }

        // 儲存所有自訂模板
        NotificationTemplates::save_custom_templates($all_custom);

        add_settings_error(
            'buygo_settings',
            'templates_saved',
            '模板已儲存',
            'updated'
        );
    }
}
