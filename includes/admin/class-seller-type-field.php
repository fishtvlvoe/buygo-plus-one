<?php
/**
 * Seller Type Field - 賣家類型欄位管理
 *
 * 在 WordPress 使用者 profile 加入「賣家類型」欄位
 * 用於區分測試賣家（有商品數量限制）和真實賣家（無限制）
 *
 * @package BuyGoPlusOne\Admin
 * @since Phase 19
 */

namespace BuyGoPlus\Admin;

class SellerTypeField {

    /**
     * 初始化 hooks
     */
    public function __construct() {
        // 在使用者 profile 頁面顯示欄位
        add_action('show_user_profile', [$this, 'render_seller_type_field']);
        add_action('edit_user_profile', [$this, 'render_seller_type_field']);

        // 儲存欄位值
        add_action('personal_options_update', [$this, 'save_seller_type_field']);
        add_action('edit_user_profile_update', [$this, 'save_seller_type_field']);
    }

    /**
     * 顯示賣家類型欄位
     *
     * @param WP_User $user 使用者物件
     */
    public function render_seller_type_field($user) {
        // 只有管理員可以編輯
        if (!current_user_can('manage_options')) {
            return;
        }

        $seller_type = get_user_meta($user->ID, 'buygo_seller_type', true);
        if (empty($seller_type)) {
            $seller_type = 'test'; // 預設為測試賣家
        }

        ?>
        <h3>BuyGo 賣家設定</h3>
        <table class="form-table">
            <tr>
                <th><label for="buygo_seller_type">賣家類型</label></th>
                <td>
                    <select name="buygo_seller_type" id="buygo_seller_type" class="regular-text">
                        <option value="test" <?php selected($seller_type, 'test'); ?>>測試賣家（限制 2 商品 / 2 圖片）</option>
                        <option value="real" <?php selected($seller_type, 'real'); ?>>真實賣家（無限制）</option>
                    </select>
                    <p class="description">
                        測試賣家：每個賣家最多 2 個商品、每個商品最多 2 張圖片<br>
                        真實賣家：沒有商品數量和圖片數量限制
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * 儲存賣家類型欄位
     *
     * @param int $user_id 使用者 ID
     */
    public function save_seller_type_field($user_id) {
        // 權限檢查
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        // 儲存賣家類型
        if (isset($_POST['buygo_seller_type'])) {
            $seller_type = sanitize_text_field($_POST['buygo_seller_type']);
            if (in_array($seller_type, ['test', 'real'], true)) {
                update_user_meta($user_id, 'buygo_seller_type', $seller_type);
            }
        }
    }

    /**
     * 取得使用者的賣家類型
     *
     * @param int $user_id 使用者 ID
     * @return string 'test' 或 'real'
     */
    public static function get_seller_type($user_id) {
        $seller_type = get_user_meta($user_id, 'buygo_seller_type', true);
        return empty($seller_type) ? 'test' : $seller_type;
    }

    /**
     * 檢查使用者是否為測試賣家
     *
     * @param int $user_id 使用者 ID
     * @return bool
     */
    public static function is_test_seller($user_id) {
        return self::get_seller_type($user_id) === 'test';
    }
}
