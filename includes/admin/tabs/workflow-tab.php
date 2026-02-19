<?php if (!defined('ABSPATH')) { exit; }

        // 取得 Webhook 日誌
        $logger = \BuyGoPlus\Services\WebhookLogger::get_instance();

        // 取得篩選參數
        $event_type_filter = $_GET['event_type'] ?? '';
        $limit = 100;

        // 查詢參數
        $args = array(
            'limit' => $limit,
            'order_by' => 'created_at',
            'order' => 'DESC',
        );

        if ($event_type_filter) {
            $args['event_type'] = $event_type_filter;
        }

        $webhook_logs = $logger->get_logs($args);
        $stats = $logger->get_statistics('today');

        ?>
        <div class="webhook-monitor">
            <!-- 統計資訊 -->
            <div class="webhook-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div class="stat-card" style="background: #f0f0f0; padding: 15px; border-radius: 5px;">
                    <div style="font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo isset($stats['webhook_received']) ? $stats['webhook_received'] : 0; ?></div>
                    <div style="color: #666; font-size: 14px;">今日 Webhook 接收</div>
                </div>
                <div class="stat-card" style="background: #f0f0f0; padding: 15px; border-radius: 5px;">
                    <div style="font-size: 24px; font-weight: bold; color: #46b450;"><?php echo isset($stats['image_uploaded']) ? $stats['image_uploaded'] : 0; ?></div>
                    <div style="color: #666; font-size: 14px;">今日圖片上傳</div>
                </div>
                <div class="stat-card" style="background: #f0f0f0; padding: 15px; border-radius: 5px;">
                    <div style="font-size: 24px; font-weight: bold; color: #dc3232;"><?php echo isset($stats['error']) ? $stats['error'] : 0; ?></div>
                    <div style="color: #666; font-size: 14px;">今日錯誤</div>
                </div>
                <div class="stat-card" style="background: #f0f0f0; padding: 15px; border-radius: 5px;">
                    <div style="font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo isset($stats['product_created']) ? $stats['product_created'] : 0; ?></div>
                    <div style="color: #666; font-size: 14px;">今日商品建立</div>
                </div>
            </div>

            <!-- 篩選器 -->
            <div class="webhook-filters" style="margin-bottom: 20px;">
                <form method="get" style="display: inline-block;">
                    <input type="hidden" name="page" value="buygo-settings">
                    <input type="hidden" name="tab" value="workflow">

                    <select name="event_type" id="filter-event-type" style="padding: 5px 10px;">
                        <option value="">所有事件類型</option>
                        <option value="webhook_received" <?php selected($event_type_filter, 'webhook_received'); ?>>Webhook 接收</option>
                        <option value="image_uploaded" <?php selected($event_type_filter, 'image_uploaded'); ?>>圖片上傳</option>
                        <option value="text_message_received" <?php selected($event_type_filter, 'text_message_received'); ?>>文字訊息</option>
                        <option value="product_created" <?php selected($event_type_filter, 'product_created'); ?>>商品建立</option>
                        <option value="product_creating" <?php selected($event_type_filter, 'product_creating'); ?>>商品建立中</option>
                        <option value="error" <?php selected($event_type_filter, 'error'); ?>>錯誤</option>
                        <option value="permission_denied" <?php selected($event_type_filter, 'permission_denied'); ?>>權限拒絕</option>
                        <option value="test_mode_active" <?php selected($event_type_filter, 'test_mode_active'); ?>>測試模式</option>
                    </select>

                    <button type="submit" class="button" style="margin-left: 10px;">篩選</button>
                    <a href="?page=buygo-settings&tab=workflow" class="button" style="margin-left: 5px;">清除</a>
                </form>
            </div>

            <!-- 日誌列表 -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 150px;">時間</th>
                        <th style="width: 150px;">事件類型</th>
                        <th style="width: 100px;">使用者 ID</th>
                        <th style="width: 150px;">LINE UID</th>
                        <th>詳細資料</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($webhook_logs)): ?>
                        <tr>
                            <td colspan="5" class="no-logs" style="text-align: center; padding: 20px;">
                                沒有找到符合條件的記錄
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($webhook_logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log['created_at'] ?? '-'); ?></td>
                                <td>
                                    <span class="event-type-badge event-type-<?php echo esc_attr($log['event_type'] ?? ''); ?>" style="
                                        display: inline-block;
                                        padding: 3px 8px;
                                        border-radius: 3px;
                                        font-size: 12px;
                                        font-weight: 500;
                                        <?php
                                        $event_type = $log['event_type'] ?? '';
                                        if ($event_type === 'error') {
                                            echo 'background: #dc3232; color: white;';
                                        } elseif ($event_type === 'product_created') {
                                            echo 'background: #46b450; color: white;';
                                        } elseif ($event_type === 'image_uploaded') {
                                            echo 'background: #0073aa; color: white;';
                                        } else {
                                            echo 'background: #666; color: white;';
                                        }
                                        ?>
                                    ">
                                        <?php echo esc_html($log['event_type'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log['user_id'] ?? '-'); ?></td>
                                <td style="font-family: monospace; font-size: 11px;">
                                    <?php echo esc_html($log['line_user_id'] ? substr($log['line_user_id'], 0, 20) . '...' : '-'); ?>
                                </td>
                                <td>
                                    <?php
                                    $event_data = $log['event_data'] ?? array();
                                    if (is_array($event_data) && !empty($event_data)) {
                                        echo '<details style="cursor: pointer;">';
                                        echo '<summary style="color: #0073aa; text-decoration: underline;">查看詳細資料</summary>';
                                        echo '<pre style="background: #f5f5f5; padding: 10px; margin-top: 5px; border-radius: 3px; font-size: 11px; max-height: 200px; overflow: auto;">';
                                        echo esc_html(wp_json_encode($event_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                        echo '</pre>';
                                        echo '</details>';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
