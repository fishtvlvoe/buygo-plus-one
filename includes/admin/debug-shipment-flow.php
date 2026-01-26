<?php
/**
 * 出貨流程診斷工具
 *
 * 用途：診斷出貨單、備貨、訂單狀態等問題
 *
 * @package BuyGoPlus\Admin
 */

// 載入 WordPress
$wp_load_path = dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once $wp_load_path;
} else {
    die('無法載入 WordPress 環境。');
}

// 檢查管理員權限
if (!current_user_can('manage_options')) {
    die('權限不足');
}

global $wpdb;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>出貨流程診斷</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1400px; margin: 20px auto; padding: 0 20px; background: #f0f0f1; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h1 { color: #1d2327; border-bottom: 3px solid #2271b1; padding-bottom: 10px; margin-top: 0; }
        h2 { color: #1d2327; margin-top: 30px; border-bottom: 2px solid #ddd; padding-bottom: 8px; }
        .info { background: #e5f5ff; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; }
        th { background: #f0f0f1; padding: 10px 8px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600; }
        td { padding: 8px; border-bottom: 1px solid #ddd; vertical-align: top; }
        tr:hover { background: #f9f9f9; }
        code { background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-family: monospace; font-size: 12px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; overflow-x: auto; white-space: pre-wrap; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 出貨流程診斷工具</h1>

        <div class="info">
            <strong>診斷時間：</strong><?php echo current_time('Y-m-d H:i:s'); ?><br>
            <strong>用途：</strong>檢查出貨單、備貨頁面、訂單狀態等後端數據
        </div>

        <!-- 1. 出貨單資料 -->
        <h2>📦 1. 出貨單資料 (wp_buygo_shipments)</h2>
        <?php
        $shipments = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}buygo_shipments ORDER BY id DESC LIMIT 5",
            ARRAY_A
        );

        if (!empty($shipments)):
        ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>出貨單號</th>
                        <th>客戶ID</th>
                        <th>狀態</th>
                        <th>建立時間</th>
                        <th>出貨時間</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shipments as $s): ?>
                        <tr>
                            <td><?php echo esc_html($s['id']); ?></td>
                            <td><code><?php echo esc_html($s['shipment_number']); ?></code></td>
                            <td><?php echo esc_html($s['customer_id']); ?></td>
                            <td>
                                <span class="badge <?php echo $s['status'] === 'shipped' ? 'badge-success' : ($s['status'] === 'pending' ? 'badge-warning' : 'badge-info'); ?>">
                                    <?php echo esc_html($s['status']); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($s['created_at']); ?></td>
                            <td><?php echo esc_html($s['shipped_at'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="warning">⚠️ 沒有出貨單資料</div>
        <?php endif; ?>

        <!-- 2. 出貨單項目資料 -->
        <h2>📋 2. 出貨單項目 (wp_buygo_shipment_items)</h2>
        <?php
        $shipment_items = $wpdb->get_results(
            "SELECT si.*, s.shipment_number
             FROM {$wpdb->prefix}buygo_shipment_items si
             LEFT JOIN {$wpdb->prefix}buygo_shipments s ON si.shipment_id = s.id
             ORDER BY si.id DESC LIMIT 10",
            ARRAY_A
        );

        if (!empty($shipment_items)):
        ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>出貨單號</th>
                        <th>order_id</th>
                        <th>order_item_id</th>
                        <th>product_id</th>
                        <th>數量</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shipment_items as $si): ?>
                        <tr>
                            <td><?php echo esc_html($si['id']); ?></td>
                            <td><code><?php echo esc_html($si['shipment_number']); ?></code></td>
                            <td><?php echo esc_html($si['order_id']); ?></td>
                            <td><?php echo esc_html($si['order_item_id']); ?></td>
                            <td><?php echo esc_html($si['product_id']); ?></td>
                            <td><?php echo esc_html($si['quantity']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="error">❌ 沒有出貨單項目資料 - 這就是 Excel 匯出空白的原因！</div>
        <?php endif; ?>

        <!-- 3. 訂單資料 -->
        <h2>🛒 3. 訂單資料 (wp_fct_orders)</h2>
        <?php
        $orders = $wpdb->get_results(
            "SELECT id, parent_id, type, status, billing_first_name, billing_last_name, total_amount, created_at
             FROM {$wpdb->prefix}fct_orders
             ORDER BY id DESC LIMIT 5",
            ARRAY_A
        );

        if (!empty($orders)):
        ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>parent_id</th>
                        <th>type</th>
                        <th>狀態</th>
                        <th>客戶</th>
                        <th>金額</th>
                        <th>建立時間</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><?php echo esc_html($o['id']); ?></td>
                            <td><?php echo esc_html($o['parent_id'] ?? '-'); ?></td>
                            <td><?php echo esc_html($o['type'] ?? 'one-time'); ?></td>
                            <td>
                                <span class="badge <?php
                                    echo $o['status'] === 'completed' ? 'badge-success' :
                                        ($o['status'] === 'processing' ? 'badge-info' :
                                        ($o['status'] === 'pending' ? 'badge-warning' : 'badge-danger'));
                                ?>">
                                    <?php echo esc_html($o['status']); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(trim(($o['billing_first_name'] ?? '') . ' ' . ($o['billing_last_name'] ?? ''))); ?></td>
                            <td>¥<?php echo number_format(($o['total_amount'] ?? 0) / 100); ?></td>
                            <td><?php echo esc_html($o['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="warning">⚠️ 沒有訂單資料</div>
        <?php endif; ?>

        <!-- 4. 訂單項目資料 -->
        <h2>📝 4. 訂單項目 (wp_fct_order_items)</h2>
        <?php
        $order_items = $wpdb->get_results(
            "SELECT oi.id, oi.order_id, oi.object_id as product_id, oi.title, oi.quantity, oi.line_meta
             FROM {$wpdb->prefix}fct_order_items oi
             ORDER BY oi.id DESC LIMIT 5",
            ARRAY_A
        );

        if (!empty($order_items)):
        ?>
            <table>
                <thead>
                    <tr>
                        <th>order_item_id</th>
                        <th>order_id</th>
                        <th>product_id</th>
                        <th>商品名稱</th>
                        <th>數量</th>
                        <th>line_meta (已分配)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $oi):
                        $meta = json_decode($oi['line_meta'] ?? '{}', true);
                        $allocated = $meta['_allocated_qty'] ?? 0;
                    ?>
                        <tr>
                            <td><?php echo esc_html($oi['id']); ?></td>
                            <td><?php echo esc_html($oi['order_id']); ?></td>
                            <td><?php echo esc_html($oi['product_id']); ?></td>
                            <td><?php echo esc_html($oi['title']); ?></td>
                            <td><?php echo esc_html($oi['quantity']); ?></td>
                            <td>
                                <?php if ($allocated > 0): ?>
                                    <span class="badge badge-success"><?php echo esc_html($allocated); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-warning">0</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="warning">⚠️ 沒有訂單項目資料</div>
        <?php endif; ?>

        <!-- 5. 關鍵診斷：檢查 order_item_id 對應 -->
        <h2>🔗 5. 關鍵診斷：出貨單項目與訂單項目對應</h2>
        <?php
        // 檢查出貨單項目的 order_item_id 是否在訂單項目表中存在
        $check_query = $wpdb->get_results(
            "SELECT
                si.id as shipment_item_id,
                si.shipment_id,
                si.order_item_id,
                si.quantity as shipment_qty,
                oi.id as found_order_item_id,
                oi.quantity as order_qty,
                oi.title as product_name
             FROM {$wpdb->prefix}buygo_shipment_items si
             LEFT JOIN {$wpdb->prefix}fct_order_items oi ON si.order_item_id = oi.id
             ORDER BY si.id DESC
             LIMIT 10",
            ARRAY_A
        );

        if (!empty($check_query)):
        ?>
            <table>
                <thead>
                    <tr>
                        <th>出貨項目ID</th>
                        <th>出貨單ID</th>
                        <th>order_item_id</th>
                        <th>出貨數量</th>
                        <th>對應訂單項目</th>
                        <th>訂單數量</th>
                        <th>商品名稱</th>
                        <th>狀態</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($check_query as $c): ?>
                        <tr>
                            <td><?php echo esc_html($c['shipment_item_id']); ?></td>
                            <td><?php echo esc_html($c['shipment_id']); ?></td>
                            <td><?php echo esc_html($c['order_item_id']); ?></td>
                            <td><?php echo esc_html($c['shipment_qty']); ?></td>
                            <td><?php echo esc_html($c['found_order_item_id'] ?? '❌ 找不到'); ?></td>
                            <td><?php echo esc_html($c['order_qty'] ?? '-'); ?></td>
                            <td><?php echo esc_html($c['product_name'] ?? '-'); ?></td>
                            <td>
                                <?php if ($c['found_order_item_id']): ?>
                                    <span class="badge badge-success">✓ 正常</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">✗ 對應失敗</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="warning">⚠️ 沒有出貨單項目可檢查</div>
        <?php endif; ?>

        <!-- 6. 客戶資料檢查 -->
        <h2>👤 6. 客戶資料 (wp_fct_customers)</h2>
        <?php
        $customers = $wpdb->get_results(
            "SELECT id, first_name, last_name, email, phone, address
             FROM {$wpdb->prefix}fct_customers
             ORDER BY id DESC LIMIT 5",
            ARRAY_A
        );

        if (!empty($customers)):
        ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>姓名</th>
                        <th>Email</th>
                        <th>電話</th>
                        <th>地址</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td><?php echo esc_html($c['id']); ?></td>
                            <td><?php echo esc_html(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))); ?></td>
                            <td><?php echo esc_html($c['email'] ?? '-'); ?></td>
                            <td><?php echo esc_html($c['phone'] ?? '-'); ?></td>
                            <td><?php echo esc_html($c['address'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="warning">⚠️ 沒有客戶資料</div>
        <?php endif; ?>

        <!-- 7. 問題診斷總結 -->
        <h2>📊 7. 問題診斷總結</h2>
        <?php
        // 統計
        $shipment_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}buygo_shipments");
        $shipment_item_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}buygo_shipment_items");
        $order_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders");
        $order_item_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_order_items");
        $pending_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders WHERE status = 'pending'");
        $processing_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders WHERE status = 'processing'");
        ?>
        <div class="info">
            <p><strong>出貨單總數：</strong><?php echo $shipment_count; ?></p>
            <p><strong>出貨單項目總數：</strong><?php echo $shipment_item_count; ?></p>
            <p><strong>訂單總數：</strong><?php echo $order_count; ?>（pending: <?php echo $pending_orders; ?>，processing: <?php echo $processing_orders; ?>）</p>
            <p><strong>訂單項目總數：</strong><?php echo $order_item_count; ?></p>
        </div>

        <?php if ($shipment_item_count == 0): ?>
            <div class="error">
                <strong>❌ 問題發現：出貨單項目表是空的！</strong><br>
                這就是 Excel 匯出空白的原因。<br>
                <strong>可能原因：</strong>
                <ul>
                    <li>建立出貨單時，項目沒有正確寫入資料庫</li>
                    <li>出貨單是透過舊版程式碼建立的，沒有寫入項目</li>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($pending_orders > 0 && $shipment_count > 0): ?>
            <div class="warning">
                <strong>⚠️ 注意：</strong>有 <?php echo $pending_orders; ?> 筆訂單仍為 pending 狀態<br>
                建立出貨單後，訂單狀態應該自動變為 processing
            </div>
        <?php endif; ?>

        <!-- 8. Debug Log -->
        <h2>📜 8. BuyGo Debug Log（最近 20 筆）</h2>
        <?php
        $log_table = $wpdb->prefix . 'buygo_debug_logs';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$log_table}'") === $log_table;

        if ($table_exists):
            $logs = $wpdb->get_results(
                "SELECT * FROM {$log_table} ORDER BY id DESC LIMIT 20",
                ARRAY_A
            );

            if (!empty($logs)):
        ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th style="width: 70px;">等級</th>
                        <th style="width: 130px;">模組</th>
                        <th>訊息</th>
                        <th style="width: 160px;">時間</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log):
                        $level_class = '';
                        if ($log['level'] === 'error') $level_class = 'badge-danger';
                        elseif ($log['level'] === 'warning') $level_class = 'badge-warning';
                        elseif ($log['level'] === 'info') $level_class = 'badge-info';
                        else $level_class = 'badge-success';
                    ?>
                        <tr>
                            <td><?php echo esc_html($log['id']); ?></td>
                            <td><span class="badge <?php echo $level_class; ?>"><?php echo esc_html($log['level'] ?? 'info'); ?></span></td>
                            <td><code><?php echo esc_html($log['module'] ?? '-'); ?></code></td>
                            <td>
                                <?php echo esc_html($log['message'] ?? '-'); ?>
                                <?php if (!empty($log['data'])): ?>
                                    <details style="margin-top: 5px;">
                                        <summary style="cursor: pointer; color: #2271b1;">查看詳細資料</summary>
                                        <pre style="margin-top: 5px; max-height: 200px; overflow: auto;"><?php
                                            $data = json_decode($log['data'], true);
                                            echo esc_html(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                        ?></pre>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 11px;"><?php echo esc_html($log['created_at'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top: 15px;">
                <strong>Log 檔案位置：</strong><code>/wp-content/uploads/buygo-plus-one-debug.log</code>
            </p>
            <?php else: ?>
                <div class="info">
                    Log 表存在但沒有記錄。<br>
                    這是正常的，表示系統還沒有產生任何 debug log。<br>
                    <strong>Log 檔案位置：</strong><code>/wp-content/uploads/buygo-plus-one-debug.log</code>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="warning">
                <strong>⚠️ Debug Log 表不存在</strong><br>
                請重新啟用外掛以建立資料庫表。<br>
                <strong>Log 檔案位置：</strong><code>/wp-content/uploads/buygo-plus-one-debug.log</code>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>
