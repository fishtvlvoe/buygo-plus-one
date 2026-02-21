<?php if (!defined('ABSPATH')) { exit; }

        // 處理表單提交
        if (isset($_POST['buygo_checkout_submit']) && wp_verify_nonce($_POST['_wpnonce'], 'buygo_checkout_settings')) {
            \BuyGoPlus\Services\CheckoutCustomizationService::save_settings([
                'hide_shipping' => isset($_POST['buygo_checkout_hide_shipping']),
                'hide_ship_to_different' => isset($_POST['buygo_checkout_hide_ship_to_different']),
                'enable_id_number' => isset($_POST['buygo_checkout_enable_id_number']),
            ]);
            $saved = true;
        }

        $settings = \BuyGoPlus\Services\CheckoutCustomizationService::get_settings();
        ?>
        <style>
        .bgo-checkout-card { background: #fff; border-radius: 8px; padding: 24px 28px; max-width: 640px; }
        .bgo-checkout-title { margin: 0 0 4px; font-size: 16px; font-weight: 600; color: #1d2327; }
        .bgo-checkout-desc { margin: 0 0 20px; font-size: 13px; color: #666; }
        .bgo-checkout-fields { display: flex; flex-direction: column; gap: 6px; margin-bottom: 20px; }
        .bgo-checkout-label { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 4px; cursor: pointer; transition: background 0.15s; }
        .bgo-checkout-label:nth-child(odd) { background: #f9fafb; }
        .bgo-checkout-label:nth-child(even) { background: #fff; }
        .bgo-checkout-label:hover { background: #f0f7ff; }
        .bgo-checkout-label input[type="checkbox"] { margin: 0; flex-shrink: 0; }
        .bgo-checkout-label span { font-size: 13px; font-weight: 500; color: #1d2327; white-space: nowrap; }
        .bgo-checkout-label small { color: #888; font-size: 12px; margin-left: auto; }
        .bgo-checkout-notice { padding: 10px 14px; background: #f0f7ff; border-left: 3px solid #3b82f6; border-radius: 0 4px 4px 0; margin-bottom: 20px; font-size: 13px; color: #1e40af; }
        </style>

        <div class="bgo-checkout-card">
            <?php if (!empty($saved)): ?>
                <div class="bgo-checkout-notice">設定已儲存！</div>
            <?php endif; ?>

            <h2 class="bgo-checkout-title">結帳設定</h2>
            <p class="bgo-checkout-desc">自訂 FluentCart 結帳頁面的顯示項目，變更即時生效。</p>

            <form method="post" action="<?php echo esc_url(add_query_arg(['page' => 'buygo-plus-one', 'tab' => 'checkout'], admin_url('admin.php'))); ?>">
                <?php wp_nonce_field('buygo_checkout_settings'); ?>

                <div class="bgo-checkout-fields">
                    <label class="bgo-checkout-label">
                        <input type="checkbox" name="buygo_checkout_hide_shipping" value="1"
                               <?php checked($settings['hide_shipping'], true); ?> />
                        <span>隱藏運送方式</span>
                        <small>代購業者自行處理出貨時使用</small>
                    </label>
                    <label class="bgo-checkout-label">
                        <input type="checkbox" name="buygo_checkout_hide_ship_to_different" value="1"
                               <?php checked($settings['hide_ship_to_different'], true); ?> />
                        <span>隱藏寄送到其他地址</span>
                        <small>簡化流程，只用帳單地址</small>
                    </label>
                    <label class="bgo-checkout-label">
                        <input type="checkbox" name="buygo_checkout_enable_id_number" value="1"
                               <?php checked($settings['enable_id_number'], true); ?> />
                        <span>身分證字號欄位</span>
                        <small>海運報關用，驗證台灣身分證格式</small>
                    </label>
                </div>

                <button type="submit" name="buygo_checkout_submit" class="button-primary">儲存設定</button>
            </form>
        </div>
        <?php
