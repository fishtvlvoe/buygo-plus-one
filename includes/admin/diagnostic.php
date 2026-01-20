<?php
/**
 * 資料庫診斷工具
 *
 * 用途：直接查詢資料庫，檢查各資料表的狀態
 * 使用：訪問 /wp-content/plugins/buygo-plus-one/includes/admin/diagnostic.php
 *
 * @package BuyGoPlus\Admin
 */

// 載入 WordPress
require_once '../../../../../../wp-load.php';

// 安全檢查
if (!is_admin() || !current_user_can('manage_options')) {
    die('Access denied');
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
    <title>BuyGo Plus One - 資料庫診斷</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            background: #f0f0f1;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1d2327;
            border-bottom: 3px solid #2271b1;
            padding-bottom: 10px;
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
        }
        th {
            background: #f0f0f1;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #ddd;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .count {
            font-weight: bold;
            color: #2271b1;
            font-size: 18px;
        }
        .zero {
            color: #999;
        }
        .query-box {
            background: #f0f0f1;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            margin: 10px 0;
            overflow-x: auto;
        }
        code {
            background: #f0f0f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #2271b1;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #135e96;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 資料庫診斷工具</h1>

        <div class="info">
            <strong>診斷時間：</strong><?php echo current_time('Y-m-d H:i:s'); ?><br>
            <strong>資料表前綴：</strong><code><?php echo $wpdb->prefix; ?></code><br>
            <strong>WordPress 版本：</strong><?php echo get_bloginfo('version'); ?><br>
            <strong>PHP 版本：</strong><?php echo PHP_VERSION; ?>
        </div>

        <h2>📊 資料表統計</h2>

        <?php
        // 定義要檢查的資料表
        $tables_to_check = [
            'WordPress 商品' => [
                'query' => "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'product'",
                'description' => 'WordPress 商品記錄'
            ],
            'FluentCart 商品' => [
                'query' => "SELECT COUNT(*) FROM {$wpdb->prefix}fct_products",
                'description' => 'FluentCart 商品表'
            ],
            'FluentCart 商品變體' => [
                'query' => "SELECT COUNT(*) FROM {$wpdb->prefix}fct_product_variations",
                'description' => 'FluentCart 商品變體'
            ],
            '訂單總數' => [
                'query' => "SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders",
                'description' => 'FluentCart 訂單'
            ],
            '父訂單' => [
                'query' => "SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders WHERE parent_id IS NULL",
                'description' => '沒有 parent_id 的訂單'
            ],
            '子訂單 (拆分)' => [
                'query' => "SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders WHERE parent_id IS NOT NULL AND type = 'split'",
                'description' => '有 parent_id 且 type=split'
            ],
            '訂單項目' => [
                'query' => "SELECT COUNT(*) FROM {$wpdb->prefix}fct_order_items",
                'description' => 'FluentCart 訂單項目'
            ],
            '出貨單' => [
                'query' => "SELECT COUNT(*) FROM {$wpdb->prefix}buygo_shipments",
                'description' => 'BuyGo 出貨單'
            ],
            '出貨單項目' => [
                'query' => "SELECT COUNT(*) FROM {$wpdb->prefix}buygo_shipment_items",
                'description' => 'BuyGo 出貨單項目'
            ],
            '客戶' => [
                'query' => "SELECT COUNT(*) FROM {$wpdb->prefix}fct_customers",
                'description' => 'FluentCart 客戶'
            ],
        ];
        ?>

        <table>
            <thead>
                <tr>
                    <th>資料表</th>
                    <th>說明</th>
                    <th style="text-align: right; width: 120px;">數量</th>
                    <th style="text-align: center; width: 80px;">狀態</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tables_to_check as $name => $info): ?>
                    <?php
                    $count = $wpdb->get_var($info['query']);
                    $error = $wpdb->last_error;
                    $has_data = $count > 0;
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($name); ?></strong></td>
                        <td><?php echo esc_html($info['description']); ?></td>
                        <td style="text-align: right;">
                            <?php if ($error): ?>
                                <span style="color: red;">錯誤</span>
                            <?php else: ?>
                                <span class="count <?php echo $has_data ? '' : 'zero'; ?>">
                                    <?php echo number_format((int)$count); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($error): ?>
                                ❌
                            <?php elseif ($has_data): ?>
                                ✅
                            <?php else: ?>
                                ⚪
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($error): ?>
                        <tr>
                            <td colspan="4">
                                <div class="error">
                                    <strong>錯誤訊息：</strong><?php echo esc_html($error); ?><br>
                                    <strong>SQL：</strong><code><?php echo esc_html($info['query']); ?></code>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>🔍 詳細資料表檢查</h2>

        <?php
        // 檢查資料表是否存在
        $required_tables = [
            "{$wpdb->prefix}fct_products" => 'FluentCart 商品表',
            "{$wpdb->prefix}fct_orders" => 'FluentCart 訂單表',
            "{$wpdb->prefix}fct_order_items" => 'FluentCart 訂單項目表',
            "{$wpdb->prefix}fct_customers" => 'FluentCart 客戶表',
            "{$wpdb->prefix}buygo_shipments" => 'BuyGo 出貨單表',
            "{$wpdb->prefix}buygo_shipment_items" => 'BuyGo 出貨單項目表',
        ];

        echo '<table>';
        echo '<thead><tr><th>資料表名稱</th><th>說明</th><th style="text-align: center;">存在狀態</th></tr></thead>';
        echo '<tbody>';

        foreach ($required_tables as $table_name => $description) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            echo '<tr>';
            echo '<td><code>' . esc_html($table_name) . '</code></td>';
            echo '<td>' . esc_html($description) . '</td>';
            echo '<td style="text-align: center;">' . ($exists ? '✅ 存在' : '❌ 不存在') . '</td>';
            echo '</tr>';

            // 如果不存在，顯示錯誤
            if (!$exists) {
                echo '<tr><td colspan="3"><div class="error">⚠️ 此資料表不存在！請檢查資料庫結構。</div></td></tr>';
            }
        }

        echo '</tbody></table>';
        ?>

        <h2>📋 最近 5 筆訂單</h2>
        <?php
        $recent_orders = $wpdb->get_results(
            "SELECT id, invoice_no, parent_id, type, status, total_amount, created_at
             FROM {$wpdb->prefix}fct_orders
             ORDER BY created_at DESC
             LIMIT 5",
            ARRAY_A
        );

        if (!empty($recent_orders)):
        ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>單號</th>
                        <th>父訂單ID</th>
                        <th>類型</th>
                        <th>狀態</th>
                        <th>金額</th>
                        <th>建立時間</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $order): ?>
                        <tr>
                            <td><?php echo esc_html($order['id']); ?></td>
                            <td><?php echo esc_html($order['invoice_no']); ?></td>
                            <td><?php echo esc_html($order['parent_id'] ?: '-'); ?></td>
                            <td><?php echo esc_html($order['type']); ?></td>
                            <td><?php echo esc_html($order['status']); ?></td>
                            <td><?php echo esc_html($order['total_amount']); ?></td>
                            <td><?php echo esc_html($order['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="info">⚠️ 沒有訂單記錄</div>
        <?php endif; ?>

        <h2>📦 最近 5 筆商品</h2>
        <?php
        $recent_products = $wpdb->get_results(
            "SELECT ID, post_title, post_status, post_date
             FROM {$wpdb->prefix}posts
             WHERE post_type = 'product'
             ORDER BY post_date DESC
             LIMIT 5",
            ARRAY_A
        );

        if (!empty($recent_products)):
        ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>商品名稱</th>
                        <th>狀態</th>
                        <th>建立時間</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_products as $product): ?>
                        <tr>
                            <td><?php echo esc_html($product['ID']); ?></td>
                            <td><?php echo esc_html($product['post_title']); ?></td>
                            <td><?php echo esc_html($product['post_status']); ?></td>
                            <td><?php echo esc_html($product['post_date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="info">⚠️ 沒有商品記錄</div>
        <?php endif; ?>

        <div class="success">
            <strong>✅ 診斷完成！</strong><br>
            如果所有資料表都存在但數量為 0，這是正常的（表示資料庫是空的，還沒有測試資料）。<br>
            如果有資料表不存在，請檢查資料庫結構或重新執行 migration。
        </div>

        <a href="<?php echo admin_url('admin.php?page=buygo-plus-one-settings&tab=test-tools'); ?>" class="btn">
            ← 返回測試工具頁面
        </a>
    </div>
</body>
</html>
