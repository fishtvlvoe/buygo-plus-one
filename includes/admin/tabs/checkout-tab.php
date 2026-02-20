<?php if (!defined('ABSPATH')) { exit; }

        // 處理表單提交
        if (isset($_POST['buygo_checkout_submit']) && wp_verify_nonce($_POST['_wpnonce'], 'buygo_checkout_settings')) {
            \BuyGoPlus\Services\CheckoutCustomizationService::save_settings([
                'hide_shipping' => isset($_POST['buygo_checkout_hide_shipping']),
                'hide_ship_to_different' => isset($_POST['buygo_checkout_hide_ship_to_different']),
                'enable_id_number' => isset($_POST['buygo_checkout_enable_id_number']),
            ]);
            echo '<div class="notice notice-success"><p>設定已儲存！</p></div>';
        }

        $settings = \BuyGoPlus\Services\CheckoutCustomizationService::get_settings();
        ?>
        <div class="checkout-settings-wrap">
            <h2>FluentCart 結帳頁面自訂</h2>
            <p class="description">這些設定會即時生效於 FluentCart 結帳頁面，無需清除快取。</p>

            <form method="post" action="<?php echo esc_url(add_query_arg(['page' => 'buygo-plus-one', 'tab' => 'checkout'], admin_url('admin.php'))); ?>">
                <?php wp_nonce_field('buygo_checkout_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">隱藏運送方式</th>
                        <td>
                            <label>
                                <input type="checkbox" name="buygo_checkout_hide_shipping" value="1"
                                       <?php checked($settings['hide_shipping'], true); ?> />
                                隱藏運送方式選擇區塊
                            </label>
                            <p class="description">適用於代購業者自行處理出貨的情況</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">隱藏寄送到其他地址</th>
                        <td>
                            <label>
                                <input type="checkbox" name="buygo_checkout_hide_ship_to_different" value="1"
                                       <?php checked($settings['hide_ship_to_different'], true); ?> />
                                隱藏「寄送到其他地址」選項
                            </label>
                            <p class="description">簡化結帳流程，只使用帳單地址</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">身分證字號欄位</th>
                        <td>
                            <label>
                                <input type="checkbox" name="buygo_checkout_enable_id_number" value="1"
                                       <?php checked($settings['enable_id_number'], true); ?> />
                                新增身分證字號欄位
                            </label>
                            <p class="description">海運報關使用，會驗證台灣身分證格式（如 A123456789）</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="buygo_checkout_submit" class="button-primary">儲存設定</button>
                </p>
            </form>
        </div>
        <?php
