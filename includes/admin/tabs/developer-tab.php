<?php if (!defined('ABSPATH')) { exit; }

// === AJAX Handlers (registered early so they work on any page load) ===
add_action('wp_ajax_buygo_dev_reset_data', function () {
    check_ajax_referer('buygo_dev_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }

    global $wpdb;
    try {
        $wpdb->query('START TRANSACTION');

        $results = [];
        $results['shipment_items'] = $wpdb->query("DELETE FROM {$wpdb->prefix}buygo_shipment_items");
        $results['shipments'] = $wpdb->query("DELETE FROM {$wpdb->prefix}buygo_shipments");
        $results['order_items'] = $wpdb->query("DELETE FROM {$wpdb->prefix}fct_order_items");
        $results['orders'] = $wpdb->query("DELETE FROM {$wpdb->prefix}fct_orders");

        $product_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'product'");
        if (!empty($product_ids)) {
            $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}postmeta WHERE post_id IN ($placeholders)",
                ...$product_ids
            ));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}term_relationships WHERE object_id IN ($placeholders)",
                ...$product_ids
            ));
        }
        $results['products'] = $wpdb->query("DELETE FROM {$wpdb->prefix}posts WHERE post_type = 'product'");
        $results['fct_variations'] = $wpdb->query("DELETE FROM {$wpdb->prefix}fct_product_variations");
        $results['fct_products'] = $wpdb->query("DELETE FROM {$wpdb->prefix}fct_products");

        $wpdb->query('COMMIT');
        wp_send_json_success($results);
    } catch (\Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => $e->getMessage()]);
    }
});

add_action('wp_ajax_buygo_dev_sql_query', function () {
    check_ajax_referer('buygo_dev_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }

    $sql = trim(wp_unslash($_POST['sql'] ?? ''));
    if (empty($sql)) {
        wp_send_json_error(['message' => 'SQL query is empty']);
    }

    // Security: Only allow SELECT
    $forbidden = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'REPLACE', 'GRANT', 'REVOKE'];
    $first_word = strtoupper(strtok($sql, " \t\n\r"));
    if ($first_word !== 'SELECT') {
        wp_send_json_error(['message' => 'Only SELECT queries are allowed']);
    }
    foreach ($forbidden as $keyword) {
        if ($first_word === $keyword) {
            wp_send_json_error(['message' => "Forbidden keyword: {$keyword}"]);
        }
    }

    global $wpdb;
    $start = microtime(true);
    $results = $wpdb->get_results($sql, ARRAY_A);
    $duration = round((microtime(true) - $start) * 1000, 2);

    if ($wpdb->last_error) {
        wp_send_json_error(['message' => $wpdb->last_error]);
    }

    $limited = array_slice($results ?: [], 0, 100);
    wp_send_json_success([
        'rows'     => $limited,
        'count'    => count($results ?: []),
        'limited'  => count($results ?: []) > 100,
        'duration' => $duration,
    ]);
});

// 事件分頁 AJAX handler — 取得指定 event_type 的分頁事件
add_action('wp_ajax_buygo_dev_event_page', function () {
    check_ajax_referer('buygo_dev_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }

    $event_type = sanitize_text_field($_POST['event_type'] ?? '');
    $page = max(1, intval($_POST['page'] ?? 1));
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    if (empty($event_type)) {
        wp_send_json_error(['message' => 'event_type is required']);
    }

    $logger = \BuyGoPlus\Services\WebhookLogger::get_instance();

    // 取得該類型的總數
    global $wpdb;
    $table_name = $wpdb->prefix . 'buygo_webhook_logs';
    $total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE event_type = %s",
        $event_type
    ));

    // 取得該頁事件
    $rows = $logger->get_logs([
        'event_type' => $event_type,
        'limit'      => $per_page,
        'offset'     => $offset,
        'order_by'   => 'created_at',
        'order'      => 'DESC',
    ]);

    $pages = max(1, ceil($total / $per_page));

    wp_send_json_success([
        'rows'  => $rows,
        'total' => $total,
        'page'  => $page,
        'pages' => $pages,
    ]);
});

// === Data for rendering ===

// Workflow logs — 取所有事件並按 event_type 分組
$logger = \BuyGoPlus\Services\WebhookLogger::get_instance();
$webhook_logs = $logger->get_logs(['limit' => 500, 'order_by' => 'created_at', 'order' => 'DESC']);
$stats = $logger->get_statistics('today');

// 按 event_type 分組，計算每組統計
$event_groups = [];
foreach ($webhook_logs as $log) {
    $type = $log['event_type'] ?? 'unknown';
    if (!isset($event_groups[$type])) {
        $event_groups[$type] = [
            'type'     => $type,
            'count'    => 0,
            'earliest' => $log['created_at'] ?? '',
            'latest'   => $log['created_at'] ?? '',
            'rows'     => [],
        ];
    }
    $event_groups[$type]['count']++;
    $created = $log['created_at'] ?? '';
    if ($created && ($created < $event_groups[$type]['earliest'] || empty($event_groups[$type]['earliest']))) {
        $event_groups[$type]['earliest'] = $created;
    }
    if ($created && ($created > $event_groups[$type]['latest'] || empty($event_groups[$type]['latest']))) {
        $event_groups[$type]['latest'] = $created;
    }
    // 每組只保留前 20 筆用於初始渲染
    if (count($event_groups[$type]['rows']) < 20) {
        $event_groups[$type]['rows'][] = $log;
    }
}

// 排序：error > permission_denied > 其他依計數降序
$priority_types = ['error', 'permission_denied'];
usort($event_groups, function ($a, $b) use ($priority_types) {
    $a_priority = array_search($a['type'], $priority_types);
    $b_priority = array_search($b['type'], $priority_types);
    $a_priority = $a_priority === false ? 999 : $a_priority;
    $b_priority = $b_priority === false ? 999 : $b_priority;
    if ($a_priority !== $b_priority) {
        return $a_priority - $b_priority;
    }
    return $b['count'] - $a['count'];
});

// badge 類別對應
$badge_map = [
    'error'              => 'bgo-badge-error',
    'permission_denied'  => 'bgo-badge-error',
    'product_created'    => 'bgo-badge-success',
    'image_uploaded'     => 'bgo-badge-info',
    'webhook_received'   => 'bgo-badge-info',
    'product_creating'   => 'bgo-badge-warning',
    'test_mode_active'   => 'bgo-badge-warning',
];

// 預設展開的事件類型
$default_open_types = ['error', 'permission_denied'];

// Data stats
global $wpdb;
$data_stats = [
    'products'     => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'product'"),
    'fct_products' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_products"),
    'orders'       => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders"),
    'order_items'  => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_order_items"),
    'shipments'    => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}buygo_shipments"),
    'customers'    => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_customers"),
];
?>
<style>
/* === BGO Developer Tab === */

/* Card wrapper */
.bgo-card {
    background: #fff;
    padding: 20px 24px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    border: 1px solid #f0f0f0;
    margin-bottom: 24px;
}
.bgo-card h3 {
    margin: 0 0 16px;
    font-size: 16px;
    font-weight: 600;
}

/* Stats grid (shared) */
.bgo-dev-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}
.bgo-dev-stat-card {
    background: #f9fafb;
    padding: 14px 16px;
    border-radius: 6px;
    border: 1px solid #f0f0f0;
}
.bgo-dev-stat-number {
    font-size: 24px;
    font-weight: 700;
    line-height: 1.2;
}
.bgo-dev-stat-label {
    font-size: 12px;
    color: #666;
    margin-top: 4px;
}

/* 事件分組摺疊 */
.bgo-event-group { margin-bottom: 12px; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; }
.bgo-event-group:last-child { margin-bottom: 0; }
.bgo-event-group-header {
    padding: 10px 16px;
    background: #f9fafb;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    user-select: none;
    -webkit-user-select: none;
}
.bgo-event-group-header:hover { background: #f0f7ff; }
.bgo-event-group-arrow {
    display: inline-block;
    transition: transform 0.2s ease;
    font-size: 10px;
    color: #666;
    width: 12px;
    text-align: center;
    flex-shrink: 0;
}
.bgo-event-group-arrow.open { transform: rotate(90deg); }
.bgo-event-group-count { font-size: 12px; color: #666; }
.bgo-event-group-time { font-size: 11px; color: #999; margin-left: auto; }
.bgo-event-group-body { padding: 0 16px 16px; }
.bgo-event-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    margin-top: 12px;
}
.bgo-event-pagination button {
    padding: 4px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
    cursor: pointer;
    font-size: 12px;
}
.bgo-event-pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
.bgo-event-pagination button:not(:disabled):hover { background: #f0f7ff; border-color: #3b82f6; }
.bgo-event-pagination span { font-size: 12px; color: #666; }

/* Log table */
.bgo-dev-table {
    border-collapse: collapse;
    width: 100%;
}
.bgo-dev-table th {
    text-align: left;
    padding: 10px 12px;
    font-size: 12px;
    color: #666;
    border-bottom: 2px solid #e0e0e0;
    font-weight: 600;
}
.bgo-dev-table td {
    padding: 10px 12px;
    vertical-align: middle;
    font-size: 13px;
}
.bgo-dev-table tbody tr:nth-child(odd) td { background: #fff; }
.bgo-dev-table tbody tr:nth-child(even) td { background: #f9fafb; }
.bgo-dev-table tbody tr:hover td { background: #f0f7ff; }

/* Badges */
.bgo-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 500; }
.bgo-badge-error { background: #fee2e2; color: #b91c1c; }
.bgo-badge-success { background: #dcfce7; color: #15803d; }
.bgo-badge-info { background: #dbeafe; color: #1e40af; }
.bgo-badge-warning { background: #fef3c7; color: #92400e; }
.bgo-badge-default { background: #f3f4f6; color: #4b5563; }

.bgo-dev-mono { font-family: monospace; font-size: 11px; color: #666; }

.bgo-dev-table details { cursor: pointer; }
.bgo-dev-table details summary { color: #2271b1; font-size: 12px; font-weight: 500; }
.bgo-dev-table details summary:hover { text-decoration: underline; }
.bgo-dev-table details pre {
    background: #f9fafb; padding: 10px 12px; margin-top: 6px; border-radius: 4px;
    font-size: 11px; max-height: 200px; overflow: auto; border: 1px solid #e5e7eb; line-height: 1.5;
}

.bgo-dev-empty { text-align: center; padding: 32px 20px; color: #999; font-size: 13px; }

/* SQL Console */
.bgo-dev-sql-input {
    width: 100%;
    min-height: 100px;
    font-family: 'SF Mono', 'Consolas', 'Monaco', monospace;
    font-size: 13px;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    resize: vertical;
    box-sizing: border-box;
}
.bgo-dev-sql-input:focus {
    border-color: #3b82f6;
    outline: none;
    box-shadow: 0 0 0 2px rgba(59,130,246,0.15);
}
.bgo-dev-sql-meta {
    margin-top: 8px;
    font-size: 12px;
    color: #666;
}
.bgo-dev-sql-results {
    margin-top: 16px;
    overflow-x: auto;
}
.bgo-dev-sql-results table {
    border-collapse: collapse;
    width: 100%;
    font-size: 12px;
}
.bgo-dev-sql-results th {
    text-align: left;
    padding: 6px 10px;
    background: #f0f0f0;
    border: 1px solid #ddd;
    font-weight: 600;
    white-space: nowrap;
}
.bgo-dev-sql-results td {
    padding: 6px 10px;
    border: 1px solid #eee;
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Message toast */
.bgo-dev-message {
    padding: 10px 16px;
    border-radius: 4px;
    margin-bottom: 16px;
    font-size: 13px;
    display: none;
}
.bgo-dev-message.success { background: #dcfce7; color: #15803d; border: 1px solid #86efac; display: block; }
.bgo-dev-message.error { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; display: block; }

/* Action buttons */
.bgo-dev-actions { display: flex; gap: 8px; margin-top: 16px; }
</style>

<script>
var buygoDevNonce = '<?php echo wp_create_nonce("buygo_dev_nonce"); ?>';
</script>

<h2 style="margin: 0 0 16px;">開發者</h2>

<!-- ===================== Section 1: Webhook / Flow Logs ===================== -->
<div class="bgo-card">
    <h3>Webhook / Flow Logs</h3>

    <div class="bgo-dev-stats">
        <div class="bgo-dev-stat-card">
            <div class="bgo-dev-stat-number" style="color: #2271b1;"><?php echo (int)($stats['webhook_received'] ?? 0); ?></div>
            <div class="bgo-dev-stat-label">今日 Webhook 接收</div>
        </div>
        <div class="bgo-dev-stat-card">
            <div class="bgo-dev-stat-number" style="color: #00a32a;"><?php echo (int)($stats['image_uploaded'] ?? 0); ?></div>
            <div class="bgo-dev-stat-label">今日圖片上傳</div>
        </div>
        <div class="bgo-dev-stat-card">
            <div class="bgo-dev-stat-number" style="color: #d63638;"><?php echo (int)($stats['error'] ?? 0); ?></div>
            <div class="bgo-dev-stat-label">今日錯誤</div>
        </div>
        <div class="bgo-dev-stat-card">
            <div class="bgo-dev-stat-number" style="color: #2271b1;"><?php echo (int)($stats['product_created'] ?? 0); ?></div>
            <div class="bgo-dev-stat-label">今日商品建立</div>
        </div>
    </div>

    <?php if (empty($event_groups)): ?>
        <div class="bgo-dev-empty">目前沒有事件記錄</div>
    <?php else: ?>
        <?php foreach ($event_groups as $group):
            $type = $group['type'];
            $badge_class = $badge_map[$type] ?? 'bgo-badge-default';
            $is_open = in_array($type, $default_open_types, true);
            $total_pages = max(1, ceil($group['count'] / 20));
        ?>
        <div class="bgo-event-group" data-type="<?php echo esc_attr($type); ?>" data-total="<?php echo (int)$group['count']; ?>" data-pages="<?php echo $total_pages; ?>">
            <div class="bgo-event-group-header" onclick="bgoToggleGroup(this)">
                <span class="bgo-event-group-arrow<?php echo $is_open ? ' open' : ''; ?>">&#9654;</span>
                <span class="bgo-badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($type); ?></span>
                <span class="bgo-event-group-count"><?php echo (int)$group['count']; ?> 筆</span>
                <span class="bgo-event-group-time">
                    <?php
                    $earliest = $group['earliest'] ? substr($group['earliest'], 0, 16) : '';
                    $latest = $group['latest'] ? substr($group['latest'], 0, 16) : '';
                    if ($earliest && $latest && $earliest !== $latest) {
                        echo esc_html($earliest . ' ~ ' . $latest);
                    } elseif ($earliest) {
                        echo esc_html($earliest);
                    }
                    ?>
                </span>
            </div>
            <div class="bgo-event-group-body" style="display:<?php echo $is_open ? 'block' : 'none'; ?>;" data-page="1">
                <table class="bgo-dev-table">
                    <thead>
                        <tr>
                            <th style="width: 140px;">時間</th>
                            <th style="width: 80px;">使用者 ID</th>
                            <th style="width: 130px;">LINE UID</th>
                            <th>詳細資料</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($group['rows'] as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log['created_at'] ?? '-'); ?></td>
                            <td><?php echo esc_html($log['user_id'] ?? '-'); ?></td>
                            <td>
                                <span class="bgo-dev-mono"><?php
                                    echo esc_html(!empty($log['line_user_id'])
                                        ? substr($log['line_user_id'], 0, 20) . '...'
                                        : '-');
                                ?></span>
                            </td>
                            <td>
                                <?php
                                $event_data = $log['event_data'] ?? [];
                                if (is_array($event_data) && !empty($event_data)) {
                                    echo '<details>';
                                    echo '<summary>查看詳細資料</summary>';
                                    echo '<pre>' . esc_html(wp_json_encode($event_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                                    echo '</details>';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($group['count'] > 20): ?>
                <div class="bgo-event-pagination">
                    <button disabled>上一頁</button>
                    <span>1 / <?php echo $total_pages; ?></span>
                    <button onclick="bgoLoadEventPage('<?php echo esc_attr($type); ?>', 2)">下一頁</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ===================== Section 2: Data Cleanup ===================== -->
<div class="bgo-card">
    <h3>Data Cleanup</h3>

    <div id="bgo-dev-cleanup-message" class="bgo-dev-message"></div>

    <div class="bgo-dev-stats" id="bgo-dev-data-stats">
        <div class="bgo-dev-stat-card">
            <div class="bgo-dev-stat-number" style="color: #2271b1;" id="stat-products"><?php echo (int)$data_stats['products']; ?></div>
            <div class="bgo-dev-stat-label">WP 商品</div>
        </div>
        <div class="bgo-dev-stat-card">
            <div class="bgo-dev-stat-number" style="color: #2271b1;" id="stat-fct-products"><?php echo (int)$data_stats['fct_products']; ?></div>
            <div class="bgo-dev-stat-label">FC 商品</div>
        </div>
        <div class="bgo-dev-stat-card">
            <div class="bgo-dev-stat-number" style="color: #00a32a;" id="stat-orders"><?php echo (int)$data_stats['orders']; ?></div>
            <div class="bgo-dev-stat-label">訂單</div>
        </div>
        <div class="bgo-dev-stat-card">
            <div class="bgo-dev-stat-number" style="color: #00a32a;" id="stat-order-items"><?php echo (int)$data_stats['order_items']; ?></div>
            <div class="bgo-dev-stat-label">訂單項目</div>
        </div>
        <div class="bgo-dev-stat-card">
            <div class="bgo-dev-stat-number" style="color: #d63638;" id="stat-shipments"><?php echo (int)$data_stats['shipments']; ?></div>
            <div class="bgo-dev-stat-label">出貨單</div>
        </div>
        <div class="bgo-dev-stat-card">
            <div class="bgo-dev-stat-number" style="color: #666;" id="stat-customers"><?php echo (int)$data_stats['customers']; ?></div>
            <div class="bgo-dev-stat-label">客戶（保留）</div>
        </div>
    </div>

    <div class="bgo-dev-actions">
        <button type="button" class="button button-primary" id="bgo-dev-cleanup-btn" style="background: #d63638; border-color: #d63638;">
            清除所有測試資料
        </button>
    </div>
</div>

<!-- ===================== Section 3: SQL Console ===================== -->
<div class="bgo-card">
    <h3>SQL Console (SELECT Only)</h3>

    <textarea class="bgo-dev-sql-input" id="bgo-dev-sql-input" placeholder="SELECT * FROM wp_posts LIMIT 10"></textarea>

    <div class="bgo-dev-actions">
        <button type="button" class="button button-primary" id="bgo-dev-sql-run">Execute</button>
    </div>

    <div class="bgo-dev-sql-meta" id="bgo-dev-sql-meta" style="display: none;"></div>
    <div class="bgo-dev-sql-results" id="bgo-dev-sql-results"></div>
</div>

<script>
// === 事件分組展開/收合 ===
function bgoToggleGroup(headerEl) {
    var body = headerEl.nextElementSibling;
    var arrow = headerEl.querySelector('.bgo-event-group-arrow');
    if (body.style.display === 'none') {
        body.style.display = 'block';
        arrow.classList.add('open');
    } else {
        body.style.display = 'none';
        arrow.classList.remove('open');
    }
}

// === 事件分頁 AJAX 載入 ===
function bgoLoadEventPage(eventType, page) {
    var ajaxUrl = typeof buygoSettings !== 'undefined' ? buygoSettings.ajaxUrl : '<?php echo admin_url("admin-ajax.php"); ?>';
    var group = document.querySelector('.bgo-event-group[data-type="' + eventType + '"]');
    if (!group) return;

    var body = group.querySelector('.bgo-event-group-body');
    var totalPages = parseInt(group.dataset.pages) || 1;

    // 防止重複請求
    if (body.dataset.loading === '1') return;
    body.dataset.loading = '1';

    var formData = new FormData();
    formData.append('action', 'buygo_dev_event_page');
    formData.append('nonce', buygoDevNonce);
    formData.append('event_type', eventType);
    formData.append('page', page);

    fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            if (!resp.success) return;
            var data = resp.data;
            var rows = data.rows || [];
            var currentPage = data.page;
            var pages = data.pages;

            // 重建表格 tbody
            var tbody = body.querySelector('.bgo-dev-table tbody');
            if (tbody) {
                var html = '';
                if (rows.length === 0) {
                    html = '<tr><td colspan="4" class="bgo-dev-empty">沒有記錄</td></tr>';
                } else {
                    rows.forEach(function(log) {
                        var lineUid = log.line_user_id
                            ? (log.line_user_id.length > 20 ? log.line_user_id.substring(0, 20) + '...' : log.line_user_id)
                            : '-';
                        var detailHtml = '-';
                        if (log.event_data && typeof log.event_data === 'object' && Object.keys(log.event_data).length > 0) {
                            var jsonStr = JSON.stringify(log.event_data, null, 2);
                            detailHtml = '<details><summary>查看詳細資料</summary><pre>' + bgoEscHtml(jsonStr) + '</pre></details>';
                        }
                        html += '<tr>';
                        html += '<td>' + bgoEscHtml(log.created_at || '-') + '</td>';
                        html += '<td>' + bgoEscHtml(log.user_id || '-') + '</td>';
                        html += '<td><span class="bgo-dev-mono">' + bgoEscHtml(lineUid) + '</span></td>';
                        html += '<td>' + detailHtml + '</td>';
                        html += '</tr>';
                    });
                }
                tbody.innerHTML = html;
            }

            // 更新分頁按鈕
            var pagination = body.querySelector('.bgo-event-pagination');
            if (pagination) {
                var prevDisabled = currentPage <= 1 ? ' disabled' : '';
                var nextDisabled = currentPage >= pages ? ' disabled' : '';
                var prevPage = currentPage - 1;
                var nextPage = currentPage + 1;
                pagination.innerHTML =
                    '<button' + prevDisabled + ' onclick="bgoLoadEventPage(\'' + eventType + '\', ' + prevPage + ')">上一頁</button>' +
                    '<span>' + currentPage + ' / ' + pages + '</span>' +
                    '<button' + nextDisabled + ' onclick="bgoLoadEventPage(\'' + eventType + '\', ' + nextPage + ')">下一頁</button>';
            }

            body.dataset.page = currentPage;
        })
        .finally(function() {
            body.dataset.loading = '0';
        });
}

function bgoEscHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
}

(function() {
    var ajaxUrl = typeof buygoSettings !== 'undefined' ? buygoSettings.ajaxUrl : '<?php echo admin_url("admin-ajax.php"); ?>';

    // === Data Cleanup ===
    var cleanupBtn = document.getElementById('bgo-dev-cleanup-btn');
    var cleanupMsg = document.getElementById('bgo-dev-cleanup-message');

    cleanupBtn.addEventListener('click', function() {
        var confirm1 = prompt('This will DELETE all products, orders, and shipments.\nType "DELETE" to confirm:');
        if (confirm1 !== 'DELETE') {
            return;
        }

        cleanupBtn.disabled = true;
        cleanupBtn.textContent = 'Processing...';
        cleanupMsg.className = 'bgo-dev-message';
        cleanupMsg.style.display = 'none';

        var formData = new FormData();
        formData.append('action', 'buygo_dev_reset_data');
        formData.append('nonce', buygoDevNonce);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                if (resp.success) {
                    cleanupMsg.className = 'bgo-dev-message success';
                    cleanupMsg.textContent = 'Data cleared successfully.';
                    // Reset stat numbers to 0 (except customers)
                    ['stat-products','stat-fct-products','stat-orders','stat-order-items','stat-shipments'].forEach(function(id) {
                        var el = document.getElementById(id);
                        if (el) el.textContent = '0';
                    });
                } else {
                    cleanupMsg.className = 'bgo-dev-message error';
                    cleanupMsg.textContent = 'Error: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error');
                }
            })
            .catch(function(err) {
                cleanupMsg.className = 'bgo-dev-message error';
                cleanupMsg.textContent = 'Request failed: ' + err.message;
            })
            .finally(function() {
                cleanupBtn.disabled = false;
                cleanupBtn.textContent = '清除所有測試資料';
            });
    });

    // === SQL Console ===
    var sqlInput = document.getElementById('bgo-dev-sql-input');
    var sqlRunBtn = document.getElementById('bgo-dev-sql-run');
    var sqlMeta = document.getElementById('bgo-dev-sql-meta');
    var sqlResults = document.getElementById('bgo-dev-sql-results');

    sqlRunBtn.addEventListener('click', function() {
        var sql = sqlInput.value.trim();
        if (!sql) return;

        sqlRunBtn.disabled = true;
        sqlRunBtn.textContent = 'Running...';
        sqlMeta.style.display = 'none';
        sqlResults.innerHTML = '';

        var formData = new FormData();
        formData.append('action', 'buygo_dev_sql_query');
        formData.append('nonce', buygoDevNonce);
        formData.append('sql', sql);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                if (!resp.success) {
                    sqlMeta.style.display = 'block';
                    sqlMeta.textContent = 'Error: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error');
                    sqlMeta.style.color = '#b91c1c';
                    return;
                }

                var data = resp.data;
                sqlMeta.style.display = 'block';
                sqlMeta.style.color = '#666';
                sqlMeta.textContent = data.count + ' rows' + (data.limited ? ' (showing first 100)' : '') + ' — ' + data.duration + 'ms';

                if (!data.rows || data.rows.length === 0) {
                    sqlResults.innerHTML = '<p style="color:#999;font-size:13px;">No results</p>';
                    return;
                }

                var cols = Object.keys(data.rows[0]);
                var html = '<table><thead><tr>';
                cols.forEach(function(c) { html += '<th>' + escHtml(c) + '</th>'; });
                html += '</tr></thead><tbody>';
                data.rows.forEach(function(row) {
                    html += '<tr>';
                    cols.forEach(function(c) {
                        var val = row[c];
                        html += '<td title="' + escHtml(String(val !== null ? val : '')) + '">' + escHtml(String(val !== null ? val : 'NULL')) + '</td>';
                    });
                    html += '</tr>';
                });
                html += '</tbody></table>';
                sqlResults.innerHTML = html;
            })
            .catch(function(err) {
                sqlMeta.style.display = 'block';
                sqlMeta.style.color = '#b91c1c';
                sqlMeta.textContent = 'Request failed: ' + err.message;
            })
            .finally(function() {
                sqlRunBtn.disabled = false;
                sqlRunBtn.textContent = 'Execute';
            });
    });

    // Ctrl+Enter to run SQL
    sqlInput.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            sqlRunBtn.click();
        }
    });

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
})();
</script>
<?php
