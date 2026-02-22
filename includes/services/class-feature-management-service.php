<?php

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Feature Management Service - 功能管理服務
 *
 * 提供功能管理 Tab 的商業邏輯：
 * - Free/Pro 功能列表定義
 * - 功能開關狀態 CRUD（wp_options: buygo_feature_toggles）
 * - 授權碼 CRUD（wp_options: buygo_license_key, buygo_license_status, buygo_license_expires）
 * - buygo_is_pro() 底層判斷
 *
 * @package BuyGoPlus\Services
 * @since 2.0.0
 */
class FeatureManagementService
{
    /**
     * Pro 功能 ID 列表（用於驗證 toggle keys）
     *
     * @var array
     */
    private static $pro_feature_ids = [
        'helper_system',
        'order_merge',
        'batch_operations',
        'data_management',
        'custom_fields',
        'multi_images',
        'export',
    ];

    /**
     * 取得完整功能定義列表
     *
     * 回傳 Free 和 Pro 兩組功能列表。
     * Pro 功能會合併 wp_options 中的開關狀態；若 Pro 未啟用，所有 Pro 功能 enabled = false。
     *
     * @return array ['free' => [...], 'pro' => [...]]
     */
    public static function get_features(): array
    {
        $free = [
            ['id' => 'role_management', 'name' => '角色權限管理', 'description' => '管理賣家與小幫手角色', 'category' => 'core', 'enabled' => true],
            ['id' => 'line_templates', 'name' => 'LINE 通知模板', 'description' => '自訂 LINE 通知訊息', 'category' => 'core', 'enabled' => true],
            ['id' => 'checkout_settings', 'name' => '結帳設定', 'description' => '自訂結帳頁面欄位', 'category' => 'core', 'enabled' => true],
            ['id' => 'single_product', 'name' => '單一商品管理', 'description' => '基本商品 CRUD', 'category' => 'products', 'enabled' => true],
            ['id' => 'order_view', 'name' => '訂單檢視', 'description' => '檢視和管理訂單', 'category' => 'orders', 'enabled' => true],
            ['id' => 'basic_shipment', 'name' => '基本出貨', 'description' => '出貨單建立和管理', 'category' => 'shipments', 'enabled' => true],
        ];

        $pro_definitions = [
            ['id' => 'helper_system', 'name' => '小幫手系統', 'description' => '細粒度權限控制的小幫手管理', 'category' => 'roles'],
            ['id' => 'order_merge', 'name' => '合併訂單', 'description' => '多筆訂單合併出貨', 'category' => 'orders'],
            ['id' => 'batch_operations', 'name' => '批次操作', 'description' => '批量商品上架和管理', 'category' => 'products'],
            ['id' => 'data_management', 'name' => '資料管理', 'description' => '訂單/商品/客戶批次清理', 'category' => 'data'],
            ['id' => 'custom_fields', 'name' => '自定義欄位', 'description' => '商品自定義欄位擴充', 'category' => 'products'],
            ['id' => 'multi_images', 'name' => '多圖輪播', 'description' => '商品多圖片上傳和輪播', 'category' => 'products'],
            ['id' => 'export', 'name' => '資料匯出', 'description' => '訂單和出貨資料匯出', 'category' => 'data'],
        ];

        $is_pro = self::is_pro();
        $toggles = $is_pro ? self::get_feature_toggles() : [];

        $pro = array_map(function ($feature) use ($is_pro, $toggles) {
            $feature['enabled'] = $is_pro && (!empty($toggles[$feature['id']]) ? (bool) $toggles[$feature['id']] : false);
            return $feature;
        }, $pro_definitions);

        return [
            'free' => $free,
            'pro'  => $pro,
        ];
    }

    /**
     * 取得 Pro 功能開關狀態
     *
     * 從 wp_options 讀取 buygo_feature_toggles。
     * 預設值：所有 Pro 功能啟用。
     *
     * @return array ['helper_system' => true, 'order_merge' => true, ...]
     */
    public static function get_feature_toggles(): array
    {
        $defaults = [];
        foreach (self::$pro_feature_ids as $id) {
            $defaults[$id] = true;
        }

        $toggles = get_option('buygo_feature_toggles', $defaults);

        // 確保所有已知的 Pro 功能都有值
        foreach (self::$pro_feature_ids as $id) {
            if (!isset($toggles[$id])) {
                $toggles[$id] = true;
            }
        }

        return $toggles;
    }

    /**
     * 儲存 Pro 功能開關狀態
     *
     * 驗證 keys 為已知的 Pro 功能 ID，sanitize 為 boolean，
     * 儲存至 wp_options buygo_feature_toggles。
     *
     * @param array $toggles ['helper_system' => true, 'order_merge' => false, ...]
     * @return bool 儲存成功回傳 true
     */
    public static function save_feature_toggles(array $toggles): bool
    {
        $sanitized = [];

        foreach ($toggles as $key => $value) {
            // 只接受已知的 Pro 功能 ID
            if (in_array($key, self::$pro_feature_ids, true)) {
                $sanitized[$key] = (bool) $value;
            }
        }

        return update_option('buygo_feature_toggles', $sanitized);
    }

    /**
     * 取得授權資訊
     *
     * 從 wp_options 讀取三個 key：
     * - buygo_license_key
     * - buygo_license_status（'inactive'|'active'|'expired'）
     * - buygo_license_expires（日期字串或空）
     *
     * @return array ['key' => string, 'status' => string, 'expires' => string]
     */
    public static function get_license(): array
    {
        return [
            'key'     => get_option('buygo_license_key', ''),
            'status'  => get_option('buygo_license_status', 'inactive'),
            'expires' => get_option('buygo_license_expires', ''),
        ];
    }

    /**
     * 儲存授權碼
     *
     * 儲存 license key 至 wp_options。
     * 目前無授權伺服器驗證：key 非空 → active，key 為空 → inactive。
     *
     * @param string $key 授權碼
     * @return array 更新後的授權資訊
     */
    public static function save_license(string $key): array
    {
        $key = sanitize_text_field($key);

        update_option('buygo_license_key', $key);

        if (!empty($key)) {
            update_option('buygo_license_status', 'active');
            update_option('buygo_license_expires', '');
        } else {
            update_option('buygo_license_status', 'inactive');
            update_option('buygo_license_expires', '');
        }

        return self::get_license();
    }

    /**
     * 停用授權
     *
     * 清除所有三個 wp_options（key、status、expires）。
     *
     * @return array 清除後的授權資訊
     */
    public static function deactivate_license(): array
    {
        update_option('buygo_license_key', '');
        update_option('buygo_license_status', 'inactive');
        update_option('buygo_license_expires', '');

        return self::get_license();
    }

    /**
     * 檢查是否為 Pro 版
     *
     * // TODO: 未來串接授權伺服器後改為檢查 license status 和到期日
     *
     * @return bool 目前永遠回傳 true
     */
    public static function is_pro(): bool
    {
        // TODO: 未來串接授權伺服器後改為檢查 license status 和到期日
        return true;
    }
}
