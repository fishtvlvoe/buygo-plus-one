<?php if (!defined('ABSPATH')) { exit; }

        global $wpdb;

        // 處理清除請求
        if (isset($_POST['reset_test_data']) && wp_verify_nonce($_POST['_wpnonce'], 'buygo_reset_test_data')) {
            if (isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'YES') {
                $result = $this->execute_reset_test_data();

                if ($result['success']) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>請輸入 YES 確認清除操作</p></div>';
            }
        }

        // 取得當前資料統計
        $stats = $this->get_test_data_stats();

        ?>
        <div class="wrap">
            <h2>測試工具</h2>
            <p class="description">⚠️ 警告：此功能僅供測試環境使用！清除操作無法復原！</p>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h3>當前資料統計</h3>
                <table class="widefat" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th style="width: 50%;">資料類型</th>
                            <th style="width: 50%; text-align: right;">數量</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>WordPress 商品 (wp_posts)</td>
                            <td style="text-align: right;"><strong><?php echo number_format($stats['wp_products']); ?></strong></td>
                        </tr>
                        <tr>
                            <td>FluentCart 商品 (wp_fct_products)</td>
                            <td style="text-align: right;"><strong><?php echo number_format($stats['fct_products']); ?></strong></td>
                        </tr>
                        <tr>
                            <td>訂單 (wp_fct_orders)</td>
                            <td style="text-align: right;"><strong><?php echo number_format($stats['orders']); ?></strong></td>
                        </tr>
                        <tr>
                            <td style="padding-left: 30px;">└ 父訂單</td>
                            <td style="text-align: right;"><?php echo number_format($stats['parent_orders']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding-left: 30px;">└ 子訂單 (拆分)</td>
                            <td style="text-align: right;"><?php echo number_format($stats['child_orders']); ?></td>
                        </tr>
                        <tr>
                            <td>訂單項目 (wp_fct_order_items)</td>
                            <td style="text-align: right;"><strong><?php echo number_format($stats['order_items']); ?></strong></td>
                        </tr>
                        <tr>
                            <td>出貨單 (wp_buygo_shipments)</td>
                            <td style="text-align: right;"><strong><?php echo number_format($stats['shipments']); ?></strong></td>
                        </tr>
                        <tr>
                            <td>出貨單項目 (wp_buygo_shipment_items)</td>
                            <td style="text-align: right;"><strong><?php echo number_format($stats['shipment_items']); ?></strong></td>
                        </tr>
                        <tr style="background-color: #f0f0f1;">
                            <td><strong>客戶 (wp_fct_customers)</strong></td>
                            <td style="text-align: right;"><strong style="color: #46b450;"><?php echo number_format($stats['customers']); ?> (保留)</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card" style="max-width: 800px; margin-top: 20px; border-left: 4px solid #dc3232;">
                <h3 style="color: #dc3232;">🗑️ 清除測試資料</h3>
                <p class="description">
                    此操作將清除以下資料：<br>
                    • 所有 WordPress 商品 (wp_posts)<br>
                    • 所有 FluentCart 商品 (wp_fct_products)<br>
                    • 所有訂單和訂單項目 (wp_fct_orders, wp_fct_order_items)<br>
                    • 所有出貨單和出貨單項目 (wp_buygo_shipments, wp_buygo_shipment_items)<br>
                    • 商品相關的 meta 資料和分類關聯<br>
                    <br>
                    <strong style="color: #dc3232;">⚠️ 客戶資料將會保留</strong>
                </p>

                <form method="post" action="" id="reset-form" style="margin-top: 20px;">
                    <?php wp_nonce_field('buygo_reset_test_data'); ?>

                    <div style="margin-bottom: 15px;">
                        <label for="confirm_reset" style="display: block; margin-bottom: 5px;">
                            <strong>請輸入 "YES" 確認清除操作：</strong>
                        </label>
                        <input
                            type="text"
                            id="confirm_reset"
                            name="confirm_reset"
                            class="regular-text"
                            placeholder="請輸入 YES"
                            autocomplete="off"
                            style="border: 2px solid #dc3232;"
                        />
                    </div>

                    <p class="submit">
                        <button
                            type="submit"
                            name="reset_test_data"
                            id="reset-btn"
                            class="button button-primary"
                            style="background-color: #dc3232; border-color: #dc3232;"
                            disabled
                        >
                            🗑️ 確認清除所有測試資料
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // 啟用/停用按鈕
            $('#confirm_reset').on('input', function() {
                var value = $(this).val();
                if (value === 'YES') {
                    $('#reset-btn').prop('disabled', false);
                } else {
                    $('#reset-btn').prop('disabled', true);
                }
            });

            // 提交前二次確認
            $('#reset-form').on('submit', function(e) {
                var confirmText = $('#confirm_reset').val();
                if (confirmText !== 'YES') {
                    e.preventDefault();
                    alert('請輸入 YES 確認清除操作');
                    return false;
                }

                var confirm = window.confirm(
                    '⚠️ 最後確認 ⚠️\n\n' +
                    '此操作將會清除所有測試資料，包括：\n' +
                    '• 所有商品\n' +
                    '• 所有訂單\n' +
                    '• 所有出貨單\n\n' +
                    '此操作無法復原！\n\n' +
                    '確定要繼續嗎？'
                );

                if (!confirm) {
                    e.preventDefault();
                    return false;
                }
            });
        });
        </script>
