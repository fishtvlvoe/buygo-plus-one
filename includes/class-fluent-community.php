<?php
namespace BuyGoPlus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FluentCommunity 整合
 *
 * 在 FluentCommunity 側邊欄添加 BuyGo+1 管理連結
 * 只有 BuyGo 成員（管理員、小幫手）可見
 */
class FluentCommunity
{
    /**
     * 建構函數
     */
    public function __construct()
    {
        // 註冊側邊欄選單
        add_filter('fluent_community/sidebar_menu_groups_config', [$this, 'add_buygo_menu'], 10, 2);
    }

    /**
     * 在 FluentCommunity 側邊欄添加 BuyGo+1 連結
     *
     * @param array $config 現有選單配置
     * @param object $user FluentCommunity 使用者物件
     * @return array 修改後的選單配置
     */
    public function add_buygo_menu($config, $user)
    {
        // 檢查使用者是否存在
        if (!$user || !isset($user->user_id) || !$user->user_id) {
            return $config;
        }

        // 取得 WordPress 使用者
        $wp_user = get_user_by('id', $user->user_id);
        if (!$wp_user) {
            return $config;
        }

        // 檢查是否為 BuyGo 成員
        $is_buygo_member = user_can($wp_user, 'manage_options')      // WordPress 管理員
                        || user_can($wp_user, 'buygo_admin')         // BuyGo 管理員
                        || user_can($wp_user, 'buygo_helper');       // 小幫手

        if (!$is_buygo_member) {
            return $config;
        }

        // SVG Icon（購物袋圖示）
        $buygo_icon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>';

        // 確保 primaryItems 陣列存在
        if (!isset($config['primaryItems'])) {
            $config['primaryItems'] = [];
        }

        // 添加 BuyGo+1 連結到主選單
        $config['primaryItems'][] = [
            'title'     => 'BuyGo+1 管理',
            'permalink' => '/buygo-portal/dashboard',
            'slug'      => 'buygo-portal',
            'shape_svg' => $buygo_icon,
        ];

        return $config;
    }
}
