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
        <style>
        /* === BGO Workflow Tab === */

        /* 統計卡片 */
        .bgo-workflow-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }
        .bgo-workflow-stat-card {
            background: #fff;
            padding: 16px 18px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            border: 1px solid #f0f0f0;
        }
        .bgo-workflow-stat-number {
            font-size: 26px;
            font-weight: 700;
            line-height: 1.2;
        }
        .bgo-workflow-stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }

        /* 篩選器 */
        .bgo-workflow-filters {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .bgo-workflow-filters select {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            background: #fff;
            color: #1d2327;
        }
        .bgo-workflow-filters select:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 2px rgba(59,130,246,0.15);
        }
        .bgo-workflow-filters .button {
            font-size: 13px;
        }

        /* 表格 */
        .bgo-workflow-table {
            border-collapse: collapse;
            width: 100%;
            max-width: 900px;
        }
        .bgo-workflow-table th {
            text-align: left;
            padding: 10px 12px;
            font-size: 12px;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
            font-weight: 600;
        }
        .bgo-workflow-table td {
            padding: 10px 12px;
            vertical-align: middle;
            font-size: 13px;
        }
        .bgo-workflow-table tbody tr:nth-child(odd) td {
            background: #fff;
        }
        .bgo-workflow-table tbody tr:nth-child(even) td {
            background: #f9fafb;
        }
        .bgo-workflow-table tbody tr:hover td {
            background: #f0f7ff;
        }

        /* Badge */
        .bgo-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 500;
        }
        .bgo-badge-error {
            background: #fee2e2;
            color: #b91c1c;
        }
        .bgo-badge-success {
            background: #dcfce7;
            color: #15803d;
        }
        .bgo-badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        .bgo-badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .bgo-badge-default {
            background: #f3f4f6;
            color: #4b5563;
        }

        /* LINE UID 等長文字 */
        .bgo-workflow-mono {
            font-family: monospace;
            font-size: 11px;
            color: #666;
        }

        /* 詳細資料展開 */
        .bgo-workflow-table details {
            cursor: pointer;
        }
        .bgo-workflow-table details summary {
            color: #2271b1;
            font-size: 12px;
            font-weight: 500;
        }
        .bgo-workflow-table details summary:hover {
            text-decoration: underline;
        }
        .bgo-workflow-table details pre {
            background: #f9fafb;
            padding: 10px 12px;
            margin-top: 6px;
            border-radius: 4px;
            font-size: 11px;
            max-height: 200px;
            overflow: auto;
            border: 1px solid #e5e7eb;
            line-height: 1.5;
        }

        /* 空狀態 */
        .bgo-workflow-empty {
            text-align: center;
            padding: 32px 20px;
            color: #999;
            font-size: 13px;
        }
        </style>

        <h2 style="margin: 0 0 16px;">開發者</h2>

        <div class="bgo-workflow-monitor">
            <!-- 統計資訊 -->
            <div class="bgo-workflow-stats">
                <div class="bgo-workflow-stat-card">
                    <div class="bgo-workflow-stat-number" style="color: #2271b1;"><?php echo isset($stats['webhook_received']) ? $stats['webhook_received'] : 0; ?></div>
                    <div class="bgo-workflow-stat-label">今日 Webhook 接收</div>
                </div>
                <div class="bgo-workflow-stat-card">
                    <div class="bgo-workflow-stat-number" style="color: #00a32a;"><?php echo isset($stats['image_uploaded']) ? $stats['image_uploaded'] : 0; ?></div>
                    <div class="bgo-workflow-stat-label">今日圖片上傳</div>
                </div>
                <div class="bgo-workflow-stat-card">
                    <div class="bgo-workflow-stat-number" style="color: #d63638;"><?php echo isset($stats['error']) ? $stats['error'] : 0; ?></div>
                    <div class="bgo-workflow-stat-label">今日錯誤</div>
                </div>
                <div class="bgo-workflow-stat-card">
                    <div class="bgo-workflow-stat-number" style="color: #2271b1;"><?php echo isset($stats['product_created']) ? $stats['product_created'] : 0; ?></div>
                    <div class="bgo-workflow-stat-label">今日商品建立</div>
                </div>
            </div>

            <!-- 篩選器 -->
            <div class="bgo-workflow-filters">
                <form method="get" style="display: flex; align-items: center; gap: 8px;">
                    <input type="hidden" name="page" value="buygo-plus-one">
                    <input type="hidden" name="tab" value="developer">

                    <select name="event_type" id="filter-event-type">
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

                    <button type="submit" class="button">篩選</button>
                    <a href="<?php echo admin_url('admin.php?page=buygo-plus-one&tab=developer'); ?>" class="button">清除</a>
                </form>
            </div>

            <!-- 日誌列表 -->
            <table class="bgo-workflow-table">
                <thead>
                    <tr>
                        <th style="width: 140px;">時間</th>
                        <th style="width: 130px;">事件類型</th>
                        <th style="width: 80px;">使用者 ID</th>
                        <th style="width: 130px;">LINE UID</th>
                        <th>詳細資料</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($webhook_logs)): ?>
                        <tr>
                            <td colspan="5" class="bgo-workflow-empty">
                                沒有找到符合條件的記錄
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($webhook_logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log['created_at'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    $event_type = $log['event_type'] ?? '';
                                    $badge_class = 'bgo-badge-default';
                                    if ($event_type === 'error' || $event_type === 'permission_denied') {
                                        $badge_class = 'bgo-badge-error';
                                    } elseif ($event_type === 'product_created') {
                                        $badge_class = 'bgo-badge-success';
                                    } elseif ($event_type === 'image_uploaded' || $event_type === 'webhook_received') {
                                        $badge_class = 'bgo-badge-info';
                                    } elseif ($event_type === 'product_creating' || $event_type === 'test_mode_active') {
                                        $badge_class = 'bgo-badge-warning';
                                    }
                                    ?>
                                    <span class="bgo-badge <?php echo esc_attr($badge_class); ?>">
                                        <?php echo esc_html($event_type ?: '-'); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log['user_id'] ?? '-'); ?></td>
                                <td>
                                    <span class="bgo-workflow-mono">
                                        <?php echo esc_html($log['line_user_id'] ? substr($log['line_user_id'], 0, 20) . '...' : '-'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $event_data = $log['event_data'] ?? array();
                                    if (is_array($event_data) && !empty($event_data)) {
                                        echo '<details>';
                                        echo '<summary>查看詳細資料</summary>';
                                        echo '<pre>';
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
