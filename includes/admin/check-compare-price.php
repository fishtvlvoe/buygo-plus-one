<?php
/**
 * 檢查商品原價資料
 *
 * 用途：檢查 FluentCart 商品變體的 compare_price 設定
 * 使用：訪問 /wp-content/plugins/buygo-plus-one/includes/admin/check-compare-price.php
 *
 * @package BuyGoPlus\Admin
 */

// 載入 WordPress
$wp_load_path = dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once $wp_load_path;
} else {
    die('無法載入 WordPress 環境。請確認路徑正確。');
}

global $wpdb;

// 設定 header
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品原價檢查</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
            background: #f0f0f1;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 {
            color: #1d2327;
            border-bottom: 3px solid #2271b1;
            padding-bottom: 10px;
            margin-top: 0;
        }
        h2 {
            color: #1d2327;
            margin-top: 30px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 8px;
        }
        .info {
            background: #e5f5ff;
            border-left: 4px solid #2271b1;
            padding: 15px;
            margin: 20px 0;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }
        th {
            background: #f0f0f1;
            padding: 12px 8px;
            text-align: left;
            border-bottom: 2px solid #ddd;
            font-weight: 600;
        }
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .price {
            font-weight: bold;
            color: #2271b1;
        }
        .has-compare {
            color: #28a745;
        }
        .no-compare {
            color: #999;
        }
        code {
            background: #f0f0f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 12px;
        }
        .json-display {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            max-width: 400px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 商品原價檢查工具</h1>

        <div class="info">
            <strong>檢查時間：</strong><?php echo current_time('Y-m-d H:i:s'); ?><br>
            <strong>資料表：</strong><code><?php echo $wpdb->prefix; ?>fct_product_variations</code>
        </div>

        <h2>📊 最新 5 筆商品變體完整資料</h2>
        <?php
        $variations = $wpdb->get_results(
            "SELECT
                id,
                post_id,
                variation_title,
                item_price,
                compare_price,
                payment_type,
                other_info,
                created_at
            FROM {$wpdb->prefix}fct_product_variations
            ORDER BY id DESC
            LIMIT 5",
            ARRAY_A
        );

        if (!empty($variations)):
        ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th style="width: 60px;">商品ID</th>
                        <th>商品名稱</th>
                        <th style="width: 100px;">售價</th>
                        <th style="width: 100px;">原價</th>
                        <th style="width: 100px;">Payment Type</th>
                        <th style="width: 150px;">建立時間</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($variations as $var):
                        $sale_price = $var['item_price'] / 100;
                        $compare_price = $var['compare_price'] / 100;
                        $has_compare = $var['compare_price'] > 0;
                    ?>
                        <tr>
                            <td><?php echo esc_html($var['id']); ?></td>
                            <td><?php echo esc_html($var['post_id']); ?></td>
                            <td><strong><?php echo esc_html($var['variation_title']); ?></strong></td>
                            <td class="price">¥<?php echo number_format($sale_price); ?></td>
                            <td class="<?php echo $has_compare ? 'has-compare' : 'no-compare'; ?>">
                                <?php if ($has_compare): ?>
                                    <span class="status-badge badge-success">¥<?php echo number_format($compare_price); ?></span>
                                <?php else: ?>
                                    <span class="status-badge badge-warning">未設定</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge badge-info"><?php echo esc_html($var['payment_type'] ?: 'null'); ?></span>
                            </td>
                            <td><?php echo esc_html($var['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="warning">⚠️ 沒有商品變體資料</div>
        <?php endif; ?>

        <h2>📋 other_info JSON 詳細內容</h2>
        <?php
        $variations_json = $wpdb->get_results(
            "SELECT
                id,
                variation_title,
                compare_price,
                payment_type,
                other_info
            FROM {$wpdb->prefix}fct_product_variations
            ORDER BY id DESC
            LIMIT 3",
            ARRAY_A
        );

        if (!empty($variations_json)):
        ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th style="width: 200px;">商品名稱</th>
                        <th style="width: 100px;">compare_price<br>(資料庫欄位)</th>
                        <th style="width: 100px;">payment_type<br>(資料庫欄位)</th>
                        <th>other_info JSON</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($variations_json as $var):
                        $other_info = json_decode($var['other_info'], true);
                        $json_compare_price = isset($other_info['compare_price']) ? $other_info['compare_price'] / 100 : null;
                        $json_payment_type = $other_info['payment_type'] ?? null;
                    ?>
                        <tr>
                            <td><?php echo esc_html($var['id']); ?></td>
                            <td><?php echo esc_html($var['variation_title']); ?></td>
                            <td>
                                <?php if ($var['compare_price'] > 0): ?>
                                    <span class="has-compare">¥<?php echo number_format($var['compare_price'] / 100); ?></span>
                                <?php else: ?>
                                    <span class="no-compare">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code><?php echo esc_html($var['payment_type'] ?: 'null'); ?></code>
                            </td>
                            <td>
                                <div class="json-display"><?php echo esc_html(json_encode($other_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></div>
                                <?php if ($json_compare_price): ?>
                                    <div class="success" style="margin-top: 10px;">
                                        ✅ JSON 中有 compare_price: ¥<?php echo number_format($json_compare_price); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($json_payment_type): ?>
                                    <div class="info" style="margin-top: 10px;">
                                        ℹ️ JSON 中有 payment_type: <strong><?php echo esc_html($json_payment_type); ?></strong>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="warning">⚠️ 沒有商品變體資料</div>
        <?php endif; ?>

        <h2>✅ 檢查結果總結</h2>
        <?php
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_product_variations");
        $with_compare = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_product_variations WHERE compare_price > 0");
        $with_payment_type = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_product_variations WHERE payment_type IS NOT NULL AND payment_type != ''");
        ?>
        <div class="info">
            <p><strong>總商品數：</strong><?php echo $total_count; ?> 個</p>
            <p><strong>有設定原價：</strong><?php echo $with_compare; ?> 個 (<?php echo $total_count > 0 ? round($with_compare / $total_count * 100, 1) : 0; ?>%)</p>
            <p><strong>有設定 payment_type：</strong><?php echo $with_payment_type; ?> 個 (<?php echo $total_count > 0 ? round($with_payment_type / $total_count * 100, 1) : 0; ?>%)</p>
        </div>

        <?php if ($with_compare < $total_count && $total_count > 0): ?>
            <div class="warning">
                <strong>⚠️ 注意：</strong><br>
                有 <?php echo $total_count - $with_compare; ?> 個商品沒有設定原價。<br>
                如果這些商品是在代碼修改<strong>之前</strong>上架的，這是正常的。<br>
                請清除舊商品後重新上架測試。
            </div>
        <?php endif; ?>

        <?php if ($with_payment_type < $total_count && $total_count > 0): ?>
            <div class="error">
                <strong>❌ 警告：</strong><br>
                有 <?php echo $total_count - $with_payment_type; ?> 個商品沒有設定 payment_type！<br>
                這些商品可能會被 FluentCart 誤判為訂閱商品。<br>
                <strong>建議：清除所有商品後重新上架。</strong>
            </div>
        <?php endif; ?>

        <?php if ($with_payment_type === $total_count && $total_count > 0): ?>
            <div class="success">
                <strong>✅ 完美！</strong><br>
                所有商品都已正確設定 payment_type。
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
