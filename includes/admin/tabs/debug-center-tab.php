<?php if (!defined('ABSPATH')) { exit; }

        global $wpdb;

        // 處理 SQL 查詢
        $sql_result = null;
        $sql_error = null;
        $sql_query = '';

        if (isset($_POST['execute_sql']) && wp_verify_nonce($_POST['_wpnonce'], 'buygo_debug_sql')) {
            $sql_query = stripslashes($_POST['sql_query'] ?? '');

            if (!empty($sql_query)) {
                try {
                    // 安全檢查：只允許 SELECT 查詢
                    $sql_upper = strtoupper(trim($sql_query));
                    if (!preg_match('/^SELECT\s/i', $sql_upper)) {
                        $sql_error = '⚠️ 安全限制：只允許 SELECT 查詢';
                    } else {
                        $results = $wpdb->get_results($sql_query, ARRAY_A);

                        if ($wpdb->last_error) {
                            $sql_error = $wpdb->last_error;
                        } else {
                            $sql_result = $results;
                        }
                    }
                } catch (\Exception $e) {
                    $sql_error = $e->getMessage();
                }
            }
        }

        ?>
        <div class="wrap">
            <h2>🔍 除錯中心</h2>
            <p class="description">快速查詢資料庫，方便除錯和測試。</p>

            <!-- 常用查詢快捷按鈕 -->
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h3>📋 常用查詢</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 15px;">
                    <button type="button" class="button" onclick="setQuery('products')">
                        📦 查看最新商品
                    </button>
                    <button type="button" class="button" onclick="setQuery('orders')">
                        🛒 查看最新訂單
                    </button>
                    <button type="button" class="button" onclick="setQuery('child_orders')">
                        🔗 查看子訂單
                    </button>
                    <button type="button" class="button" onclick="setQuery('shipments')">
                        📦 查看出貨單
                    </button>
                    <button type="button" class="button" onclick="setQuery('variations')">
                        🏷️ 查看商品變體
                    </button>
                    <button type="button" class="button" onclick="setQuery('customers')">
                        👥 查看客戶
                    </button>
                    <button type="button" class="button" onclick="setQuery('tables')">
                        🗄️ 查看所有資料表
                    </button>
                </div>
            </div>

            <!-- SQL 查詢編輯器 -->
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h3>💻 SQL 查詢編輯器</h3>
                <form method="post" action="">
                    <?php wp_nonce_field('buygo_debug_sql'); ?>

                    <div style="margin-top: 15px;">
                        <textarea
                            name="sql_query"
                            id="sql_query"
                            rows="8"
                            style="width: 100%; font-family: monospace; font-size: 13px; padding: 10px;"
                            placeholder="輸入 SQL 查詢... (僅支援 SELECT)"
                        ><?php echo esc_textarea($sql_query); ?></textarea>
                    </div>

                    <div style="margin-top: 10px;">
                        <button type="submit" name="execute_sql" class="button button-primary">
                            ▶️ 執行查詢
                        </button>
                        <button type="button" class="button" onclick="clearQuery()">
                            🗑️ 清空
                        </button>
                        <button type="button" class="button" onclick="copyQuery()">
                            📋 複製結果
                        </button>
                        <span style="color: #666; margin-left: 15px;">
                            ℹ️ 提示：表名前綴為 <code><?php echo esc_html($wpdb->prefix); ?></code>
                        </span>
                    </div>
                </form>
            </div>

            <!-- 查詢結果 -->
            <?php if ($sql_error): ?>
                <div class="card" style="max-width: 100%; margin-top: 20px; border-left: 4px solid #dc3232;">
                    <h3 style="color: #dc3232;">❌ 查詢錯誤</h3>
                    <pre style="background: #f8d7da; padding: 15px; border-radius: 4px; overflow-x: auto;"><?php echo esc_html($sql_error); ?></pre>
                </div>
            <?php elseif ($sql_result !== null): ?>
                <div class="card" style="max-width: 100%; margin-top: 20px; border-left: 4px solid #46b450;">
                    <h3 style="color: #46b450;">✅ 查詢結果 (<?php echo count($sql_result); ?> 筆)</h3>

                    <?php if (empty($sql_result)): ?>
                        <p style="color: #666;">查詢結果為空</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="widefat" style="margin-top: 15px;">
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($sql_result[0]) as $column): ?>
                                            <th><?php echo esc_html($column); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sql_result as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $value): ?>
                                                <td>
                                                    <?php
                                                    if (strlen($value) > 100) {
                                                        echo '<details><summary>' . esc_html(substr($value, 0, 100)) . '...</summary><pre style="white-space: pre-wrap; word-wrap: break-word;">' . esc_html($value) . '</pre></details>';
                                                    } else {
                                                        echo esc_html($value);
                                                    }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- JSON 格式 (用於複製) -->
                        <details style="margin-top: 20px;">
                            <summary style="cursor: pointer; font-weight: 600;">📄 JSON 格式 (點擊展開)</summary>
                            <pre id="json-result" style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; margin-top: 10px;"><?php echo json_encode($sql_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- 出貨流程診斷 -->
            <div class="card" style="max-width: 100%; margin-top: 20px; border-left: 4px solid #2271b1;">
                <h3>🔍 出貨流程診斷</h3>
                <p class="description">快速檢查出貨單、備貨、訂單狀態等後端數據是否正常。</p>

                <?php
                // === 0. FluentCart 資料表檢查 ===
                $fct_tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}fct%'", ARRAY_N);
                ?>
                <details style="margin-top: 15px; background: #f0f6fc; padding: 10px; border-radius: 4px;">
                    <summary style="font-weight: 600;">🗄️ FluentCart 資料表檢查 (<?php echo count($fct_tables); ?> 個表)</summary>
                    <table class="widefat" style="margin-top: 10px;">
                        <thead><tr><th>資料表</th><th>資料筆數</th></tr></thead>
                        <tbody>
                            <?php foreach ($fct_tables as $table):
                                $table_name = $table[0];
                                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
                            ?>
                                <tr>
                                    <td><code><?php echo esc_html($table_name); ?></code></td>
                                    <td><?php echo $count; ?> <?php echo $count == 0 ? '<span style="color: #dc3232;">(空)</span>' : ''; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top: 10px; font-size: 12px; color: #666;">
                        <strong>資料庫前綴：</strong><code><?php echo esc_html($wpdb->prefix); ?></code>
                    </p>
                </details>

                <?php
                // === 1. 出貨單資料 ===
                $shipments = $wpdb->get_results(
                    "SELECT * FROM {$wpdb->prefix}buygo_shipments ORDER BY id DESC LIMIT 5",
                    ARRAY_A
                );
                ?>
                <details style="margin-top: 15px;">
                    <summary>📦 出貨單資料 (<?php echo count($shipments); ?> 筆)</summary>
                    <?php if (!empty($shipments)): ?>
                        <table class="widefat" style="margin-top: 10px;">
                            <thead><tr><th>ID</th><th>出貨單號</th><th>客戶ID</th><th>狀態</th><th>建立時間</th></tr></thead>
                            <tbody>
                                <?php foreach ($shipments as $s): ?>
                                    <tr>
                                        <td><?php echo esc_html($s['id']); ?></td>
                                        <td><code><?php echo esc_html($s['shipment_number']); ?></code></td>
                                        <td><?php echo esc_html($s['customer_id']); ?></td>
                                        <td><span style="padding: 2px 8px; border-radius: 3px; background: <?php echo $s['status'] === 'shipped' ? '#d4edda' : '#fff3cd'; ?>;"><?php echo esc_html($s['status']); ?></span></td>
                                        <td><?php echo esc_html($s['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #856404; margin-top: 10px;">⚠️ 沒有出貨單資料</p>
                    <?php endif; ?>
                </details>

                <?php
                // === 2. 出貨單項目資料 ===
                $shipment_items = $wpdb->get_results(
                    "SELECT si.*, s.shipment_number
                     FROM {$wpdb->prefix}buygo_shipment_items si
                     LEFT JOIN {$wpdb->prefix}buygo_shipments s ON si.shipment_id = s.id
                     ORDER BY si.id DESC LIMIT 10",
                    ARRAY_A
                );
                $shipment_item_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}buygo_shipment_items");
                ?>
                <details style="margin-top: 10px;">
                    <summary style="<?php echo empty($shipment_items) ? 'color: #dc3232;' : ''; ?>">📋 出貨單項目 (總數: <?php echo $shipment_item_count; ?>)</summary>
                    <?php if (!empty($shipment_items)): ?>
                        <table class="widefat" style="margin-top: 10px;">
                            <thead><tr><th>ID</th><th>出貨單號</th><th>order_id</th><th>order_item_id</th><th>product_id</th><th>數量</th></tr></thead>
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
                        <p style="color: #dc3232; margin-top: 10px;">❌ 沒有出貨單項目資料 - 這就是 Excel 匯出空白的原因！</p>
                    <?php endif; ?>
                </details>

                <?php
                // === 3. 訂單資料 ===
                $total_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders");
                // 使用 SELECT * 避免欄位名稱不匹配
                $orders = $wpdb->get_results(
                    "SELECT * FROM {$wpdb->prefix}fct_orders ORDER BY id DESC LIMIT 10",
                    ARRAY_A
                );
                $pending_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders WHERE status = 'pending'");
                $processing_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders WHERE status = 'processing'");

                // 檢查孤兒記錄：出貨單項目引用的 order_id 是否存在
                $orphan_check = $wpdb->get_results(
                    "SELECT DISTINCT si.order_id,
                            (SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders WHERE id = si.order_id) as order_exists
                     FROM {$wpdb->prefix}buygo_shipment_items si",
                    ARRAY_A
                );
                $orphan_orders = array_filter($orphan_check, function($item) {
                    return $item['order_exists'] == 0;
                });
                ?>
                <details style="margin-top: 10px;">
                    <summary style="<?php echo !empty($orphan_orders) ? 'color: #dc3232; font-weight: bold;' : ''; ?>">
                        🛒 訂單資料 (總數: <?php echo $total_orders; ?>, pending: <?php echo $pending_orders; ?>, processing: <?php echo $processing_orders; ?>)
                        <?php if (!empty($orphan_orders)): ?> ⚠️ 發現孤兒記錄<?php endif; ?>
                    </summary>

                    <?php if (!empty($orphan_orders)): ?>
                        <div style="background: #f8d7da; padding: 10px; border-radius: 4px; margin-top: 10px;">
                            <strong>⚠️ 孤兒記錄檢查：</strong>
                            <p style="margin: 5px 0;">出貨單項目引用了以下不存在的 order_id：</p>
                            <ul style="margin: 5px 0;">
                                <?php foreach ($orphan_orders as $orphan): ?>
                                    <li><code>order_id: <?php echo esc_html($orphan['order_id']); ?></code> - 在 fct_orders 表中不存在！</li>
                                <?php endforeach; ?>
                            </ul>
                            <p style="font-size: 12px; color: #721c24;">這可能是因為訂單被刪除，或是 order_id 對應錯誤。</p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($orders)): ?>
                        <p style="margin: 10px 0; font-size: 12px; color: #666;">
                            <strong>fct_orders 欄位：</strong>
                            <code><?php echo esc_html(implode(', ', array_keys($orders[0]))); ?></code>
                        </p>
                        <table class="widefat" style="margin-top: 10px;">
                            <thead><tr><th>ID</th><th>parent_id</th><th>customer_id</th><th>狀態</th><th>金額</th><th>建立時間</th></tr></thead>
                            <tbody>
                                <?php foreach ($orders as $o):
                                    $status = $o['status'] ?? $o['order_status'] ?? 'unknown';
                                    $status_color = $status === 'completed' ? '#d4edda' : ($status === 'processing' ? '#d1ecf1' : ($status === 'pending' ? '#fff3cd' : '#f8d7da'));
                                ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($o['id']); ?></strong></td>
                                        <td><?php echo esc_html($o['parent_id'] ?? '-'); ?></td>
                                        <td><?php echo esc_html($o['customer_id'] ?? '-'); ?></td>
                                        <td><span style="padding: 2px 8px; border-radius: 3px; background: <?php echo $status_color; ?>;"><?php echo esc_html($status); ?></span></td>
                                        <td>¥<?php echo number_format((($o['total_amount'] ?? $o['total'] ?? 0)) / 100); ?></td>
                                        <td style="font-size: 11px;"><?php echo esc_html($o['created_at'] ?? $o['date_created'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <details style="margin-top: 10px; background: #f9f9f9; padding: 8px; border-radius: 4px;">
                            <summary style="cursor: pointer; font-size: 12px;">查看完整資料結構 (Debug)</summary>
                            <pre style="font-size: 11px; max-height: 200px; overflow: auto; margin-top: 8px;"><?php echo esc_html(json_encode($orders[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                        </details>
                    <?php else: ?>
                        <p style="color: #856404; margin-top: 10px;">⚠️ fct_orders 表中沒有訂單資料</p>
                    <?php endif; ?>
                </details>

                <?php
                // === 4. Debug Log ===
                $log_table = $wpdb->prefix . 'buygo_debug_logs';
                $log_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$log_table}'") === $log_table;
                $logs = $log_table_exists ? $wpdb->get_results("SELECT * FROM {$log_table} ORDER BY id DESC LIMIT 10", ARRAY_A) : [];
                ?>
                <details style="margin-top: 10px;">
                    <summary>📜 Debug Log (最近 10 筆)</summary>
                    <?php if (!empty($logs)): ?>
                        <table class="widefat" style="margin-top: 10px;">
                            <thead><tr><th>ID</th><th>等級</th><th>模組</th><th>訊息</th><th>時間</th></tr></thead>
                            <tbody>
                                <?php foreach ($logs as $log):
                                    $level_color = $log['level'] === 'error' ? '#f8d7da' : ($log['level'] === 'warning' ? '#fff3cd' : '#d1ecf1');
                                ?>
                                    <tr>
                                        <td><?php echo esc_html($log['id']); ?></td>
                                        <td><span style="padding: 2px 8px; border-radius: 3px; background: <?php echo $level_color; ?>;"><?php echo esc_html($log['level']); ?></span></td>
                                        <td><code><?php echo esc_html($log['module']); ?></code></td>
                                        <td>
                                            <?php echo esc_html($log['message']); ?>
                                            <?php if (!empty($log['data'])): ?>
                                                <details style="margin-top: 5px;"><summary style="cursor: pointer; color: #2271b1; font-size: 12px;">查看資料</summary><pre style="margin-top: 5px; font-size: 11px; max-height: 150px; overflow: auto;"><?php echo esc_html($log['data']); ?></pre></details>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 11px;"><?php echo esc_html($log['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php elseif ($log_table_exists): ?>
                        <p style="color: #666; margin-top: 10px;">Log 表存在但沒有記錄</p>
                    <?php else: ?>
                        <p style="color: #856404; margin-top: 10px;">⚠️ Debug Log 表不存在，請重新啟用外掛</p>
                    <?php endif; ?>
                    <p style="margin-top: 10px; font-size: 12px;"><strong>Log 檔案：</strong><code>/wp-content/uploads/buygo-plus-one-debug.log</code></p>
                </details>

                <!-- 診斷總結 -->
                <div style="margin-top: 15px; padding: 15px; background: #f0f0f1; border-radius: 4px;">
                    <strong>📊 診斷總結</strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <li>出貨單總數：<?php echo count($shipments); ?></li>
                        <li>出貨單項目總數：<?php echo $shipment_item_count; ?> <?php echo $shipment_item_count == 0 ? '<span style="color: #dc3232;">(❌ 空的！這會導致匯出問題)</span>' : '<span style="color: #46b450;">(✅ 正常)</span>'; ?></li>
                        <li>訂單總數：<?php echo count($orders); ?> (pending: <?php echo $pending_orders; ?>, processing: <?php echo $processing_orders; ?>)</li>
                    </ul>
                </div>
            </div>

            <!-- 系統資訊 -->
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h3>ℹ️ 系統資訊</h3>
                <table class="widefat" style="margin-top: 15px;">
                    <tbody>
                        <tr>
                            <td style="width: 30%;"><strong>資料庫前綴</strong></td>
                            <td><code><?php echo esc_html($wpdb->prefix); ?></code></td>
                        </tr>
                        <tr>
                            <td><strong>WordPress 版本</strong></td>
                            <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                        </tr>
                        <tr>
                            <td><strong>PHP 版本</strong></td>
                            <td><?php echo esc_html(PHP_VERSION); ?></td>
                        </tr>
                        <tr>
                            <td><strong>MySQL 版本</strong></td>
                            <td><?php echo esc_html($wpdb->db_version()); ?></td>
                        </tr>
                        <tr>
                            <td><strong>外掛版本</strong></td>
                            <td><?php echo esc_html(BUYGO_PLUS_ONE_VERSION ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Log 檔案位置</strong></td>
                            <td><code>/wp-content/uploads/buygo-plus-one-debug.log</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        // 預設查詢模板
        const queries = {
            products: `SELECT
    id,
    post_id,
    variation_title,
    item_price / 100 AS '售價(元)',
    compare_price / 100 AS '原價(元)',
    payment_type,
    total_stock,
    available,
    created_at
FROM <?php echo $wpdb->prefix; ?>fct_product_variations
ORDER BY id DESC
LIMIT 10`,

            orders: `SELECT
    id,
    parent_id,
    invoice_no,
    type,
    status,
    total_amount / 100 AS '金額(元)',
    customer_id,
    created_at
FROM <?php echo $wpdb->prefix; ?>fct_orders
ORDER BY id DESC
LIMIT 10`,

            child_orders: `SELECT
    o.id,
    o.parent_id,
    o.invoice_no,
    o.type,
    o.status,
    o.total_amount / 100 AS '金額(元)',
    oi.quantity,
    o.created_at
FROM <?php echo $wpdb->prefix; ?>fct_orders o
LEFT JOIN <?php echo $wpdb->prefix; ?>fct_order_items oi ON o.id = oi.order_id
WHERE o.type = 'split'
ORDER BY o.created_at DESC
LIMIT 10`,

            shipments: `SELECT
    s.id,
    s.shipment_number,
    s.status,
    s.tracking_number,
    COUNT(si.id) AS item_count,
    s.created_at
FROM <?php echo $wpdb->prefix; ?>buygo_shipments s
LEFT JOIN <?php echo $wpdb->prefix; ?>buygo_shipment_items si ON s.id = si.shipment_id
GROUP BY s.id
ORDER BY s.id DESC
LIMIT 10`,

            variations: `SELECT
    v.id,
    v.post_id,
    v.variation_title,
    v.item_price / 100 AS '售價(元)',
    v.compare_price / 100 AS '原價(元)',
    v.payment_type,
    v.other_info,
    v.created_at
FROM <?php echo $wpdb->prefix; ?>fct_product_variations v
ORDER BY v.id DESC
LIMIT 5`,

            customers: `SELECT
    id,
    user_id,
    email,
    first_name,
    last_name,
    created_at
FROM <?php echo $wpdb->prefix; ?>fct_customers
ORDER BY id DESC
LIMIT 10`,

            tables: `SHOW TABLES LIKE '<?php echo $wpdb->prefix; ?>%'`
        };

        function setQuery(type) {
            document.getElementById('sql_query').value = queries[type];
        }

        function clearQuery() {
            document.getElementById('sql_query').value = '';
        }

        function copyQuery() {
            const jsonResult = document.getElementById('json-result');
            if (jsonResult) {
                const text = jsonResult.textContent;
                navigator.clipboard.writeText(text).then(() => {
                    alert('✅ 結果已複製到剪貼板！');
                }).catch(() => {
                    alert('❌ 複製失敗，請手動選取');
                });
            } else {
                alert('⚠️ 沒有可複製的結果');
            }
        }
        </script>

        <style>
        .card {
            background: white;
            padding: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        .card h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        details summary {
            font-weight: 600;
            cursor: pointer;
            padding: 10px;
            background: #f0f0f1;
            border-radius: 4px;
        }
        details summary:hover {
            background: #e0e0e1;
        }
        </style>
