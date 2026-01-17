<?php
namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Service - 設定管理服務
 * 
 * 負責管理外掛的各種設定，使用 WordPress Options API 儲存
 */
class SettingsService
{
    /**
     * 初始化角色權限
     * 
     * @return void
     */
    public static function init_roles(): void
    {
        // 建立 BuyGo 管理員角色
        if (!get_role('buygo_admin')) {
            add_role('buygo_admin', 'BuyGo 管理員', [
                'read' => true,
                'buygo_manage_all' => true,
                'buygo_add_helper' => true,
            ]);
        }
        
        // 建立 BuyGo 小幫手角色
        if (!get_role('buygo_helper')) {
            add_role('buygo_helper', 'BuyGo 小幫手', [
                'read' => true,
                'buygo_manage_all' => true,
                'buygo_add_helper' => false,
            ]);
        }
    }
    /**
     * 取得模板設定
     * 
     * @return array
     */
    public static function get_templates(): array
    {
        $default_buyer = "親愛的 {{客戶名稱}}，\n您的訂單 {{訂單編號}} 已出貨！\n商品：{{商品名稱}}\n感謝您的購買！";
        $default_seller = "新訂單通知！\n客戶：{{客戶名稱}}\n訂單金額：{{訂單金額}}\n請盡快處理！";
        
        return [
            'buyer_template' => get_option('buygo_buyer_template', $default_buyer),
            'seller_template' => get_option('buygo_seller_template', $default_seller),
        ];
    }
    
    /**
     * 更新模板設定
     * 
     * @param array $templates
     * @return bool
     */
    public static function update_templates(array $templates): bool
    {
        $buyer_template = sanitize_textarea_field($templates['buyer_template'] ?? '');
        $seller_template = sanitize_textarea_field($templates['seller_template'] ?? '');
        
        update_option('buygo_buyer_template', $buyer_template);
        update_option('buygo_seller_template', $seller_template);
        
        return true;
    }
    
    /**
     * 取得小幫手列表
     * 
     * @return array
     */
    public static function get_helpers(): array
    {
        $helper_ids = get_option('buygo_helpers', []);
        
        if (empty($helper_ids) || !is_array($helper_ids)) {
            return [];
        }
        
        $helpers = [];
        foreach ($helper_ids as $user_id) {
            $user = get_userdata($user_id);
            if ($user) {
                $helpers[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                ];
            }
        }
        
        return $helpers;
    }
    
    /**
     * 新增小幫手
     * 
     * @param int $user_id
     * @return bool
     */
    public static function add_helper(int $user_id): bool
    {
        $helper_ids = get_option('buygo_helpers', []);
        
        if (!is_array($helper_ids)) {
            $helper_ids = [];
        }
        
        if (!in_array($user_id, $helper_ids)) {
            $helper_ids[] = $user_id;
            update_option('buygo_helpers', $helper_ids);
            
            // 賦予小幫手角色
            $user = get_userdata($user_id);
            if ($user) {
                $user->add_role('buygo_helper');
            }
        }
        
        return true;
    }
    
    /**
     * 移除小幫手
     * 
     * @param int $user_id
     * @return bool
     */
    public static function remove_helper(int $user_id): bool
    {
        $helper_ids = get_option('buygo_helpers', []);
        
        if (!is_array($helper_ids)) {
            return false;
        }
        
        $key = array_search($user_id, $helper_ids);
        if ($key !== false) {
            unset($helper_ids[$key]);
            $helper_ids = array_values($helper_ids); // 重新索引
            update_option('buygo_helpers', $helper_ids);
            
            // 移除小幫手角色
            $user = get_userdata($user_id);
            if ($user) {
                $user->remove_role('buygo_helper');
            }
        }
        
        return true;
    }
    
    /**
     * 取得 LINE 設定
     * 
     * @return array
     */
    public static function get_line_settings(): array
    {
        return [
            'channel_access_token' => get_option('buygo_line_channel_access_token', ''),
            'channel_secret' => get_option('buygo_line_channel_secret', ''),
            'liff_id' => get_option('buygo_line_liff_id', ''),
            'webhook_url' => rest_url('buygo-plus-one/v1/line/webhook'),
        ];
    }
    
    /**
     * 更新 LINE 設定
     * 
     * @param array $settings
     * @return bool
     */
    public static function update_line_settings(array $settings): bool
    {
        if (isset($settings['channel_access_token'])) {
            update_option('buygo_line_channel_access_token', sanitize_text_field($settings['channel_access_token']));
        }
        
        if (isset($settings['channel_secret'])) {
            update_option('buygo_line_channel_secret', sanitize_text_field($settings['channel_secret']));
        }
        
        if (isset($settings['liff_id'])) {
            update_option('buygo_line_liff_id', sanitize_text_field($settings['liff_id']));
        }
        
        return true;
    }
    
    /**
     * 測試 LINE 連線
     * 
     * @return array
     */
    public static function test_line_connection(): array
    {
        $token = get_option('buygo_line_channel_access_token', '');
        
        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'Channel Access Token 未設定'
            ];
        }
        
        // 測試 API 呼叫
        $response = wp_remote_get('https://api.line.me/v2/bot/info', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => '連線失敗：' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return [
                'success' => true,
                'message' => '連線成功',
                'data' => $body
            ];
        } else {
            return [
                'success' => false,
                'message' => '連線失敗：HTTP ' . $status_code
            ];
        }
    }
}
