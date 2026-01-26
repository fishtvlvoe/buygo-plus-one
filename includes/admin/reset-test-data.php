<?php
/**
 * 測試資料清除工具
 *
 * ⚠️ 警告：此工具僅供測試環境使用！
 * 這會清除所有商品、訂單、出貨單資料
 *
 * @package BuyGoPlus\Admin
 */

// 安全檢查
if (!defined('ABSPATH')) {
    die('Direct access not permitted.');
}

// 只在管理後台顯示
if (!is_admin()) {
    die('Only accessible from admin panel.');
}

// 檢查權限
if (!current_user_can('manage_options')) {
    die('Permission denied.');
}

/**
 * 清除測試資料
 */
function buygo_reset_test_data() {
    global $wpdb;

    $results = [];

    try {
        // 開始 Transaction
        $wpdb->query('START TRANSACTION');

        // 1. 清除出貨單
        $results['shipment_items_deleted'] = $wpdb->query("DELETE FROM {$wpdb->prefix}buygo_shipment_items");
        $results['shipments_deleted'] = $wpdb->query("DELETE FROM {$wpdb->prefix}buygo_shipments");

        // 2. 清除訂單
        $results['order_items_deleted'] = $wpdb->query("DELETE FROM {$wpdb->prefix}fct_order_items");
        $results['orders_deleted'] = $wpdb->query("DELETE FROM {$wpdb->prefix}fct_orders");

        // 3. 清除商品 meta
        $product_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'product'");
        if (!empty($product_ids)) {
            $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
            $results['postmeta_deleted'] = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}postmeta WHERE post_id IN ($placeholders)",
                ...$product_ids
            ));

            // 清除商品分類關聯
            $results['term_relationships_deleted'] = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}term_relationships WHERE object_id IN ($placeholders)",
                ...$product_ids
            ));
        }

        // 4. 清除商品
        $results['products_deleted'] = $wpdb->query("DELETE FROM {$wpdb->prefix}posts WHERE post_type = 'product'");

        // 5. 清除 FluentCart 商品
        $results['fct_product_variations_deleted'] = $wpdb->query("DELETE FROM {$wpdb->prefix}fct_product_variations");
        $results['fct_products_deleted'] = $wpdb->query("DELETE FROM {$wpdb->prefix}fct_products");

        // 提交 Transaction
        $wpdb->query('COMMIT');

        $results['success'] = true;
        $results['message'] = '✅ 測試資料清除成功！';

    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        $results['success'] = false;
        $results['message'] = '❌ 清除失敗：' . $e->getMessage();
    }

    return $results;
}

/**
 * 獲取資料統計
 */
function buygo_get_data_stats() {
    global $wpdb;

    return [
        'products' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'product'"),
        'fct_products' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_products"),
        'orders' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders"),
        'order_items' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_order_items"),
        'shipments' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}buygo_shipments"),
        'customers' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_customers"),
    ];
}

// 處理表單提交
$action_result = null;
if (isset($_POST['buygo_reset_confirm']) && $_POST['buygo_reset_confirm'] === 'YES') {
    check_admin_referer('buygo_reset_test_data');
    $action_result = buygo_reset_test_data();
}

// 獲取當前統計
$stats = buygo_get_data_stats();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>BuyGo Plus One - 測試資料清除工具</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #d32f2f;
            border-bottom: 3px solid #d32f2f;
            padding-bottom: 10px;
        }
        .warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .warning h2 {
            color: #856404;
            margin-top: 0;
        }
        .stats {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .stats table {
            width: 100%;
            border-collapse: collapse;
        }
        .stats td {
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        .stats td:first-child {
            font-weight: 600;
        }
        .stats td:last-child {
            text-align: right;
            color: #0066cc;
            font-weight: 600;
        }
        .form-group {
            margin: 20px 0;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-danger {
            background: #d32f2f;
            color: white;
        }
        .btn-danger:hover {
            background: #c62828;
        }
        .btn-danger:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .result {
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .result.success {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
        }
        .result.error {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
        }
        .details {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚠️ 測試資料清除工具</h1>

        <?php if ($action_result): ?>
            <div class="result <?php echo $action_result['success'] ? 'success' : 'error'; ?>">
                <h2><?php echo esc_html($action_result['message']); ?></h2>
                <?php if ($action_result['success']): ?>
                    <div class="details">
                        <strong>清除統計：</strong><br>
                        - 出貨單項目：<?php echo $action_result['shipment_items_deleted']; ?> 筆<br>
                        - 出貨單：<?php echo $action_result['shipments_deleted']; ?> 筆<br>
                        - 訂單項目：<?php echo $action_result['order_items_deleted']; ?> 筆<br>
                        - 訂單：<?php echo $action_result['orders_deleted']; ?> 筆<br>
                        - 商品：<?php echo $action_result['products_deleted']; ?> 筆<br>
                        - FluentCart 商品：<?php echo $action_result['fct_products_deleted']; ?> 筆
                    </div>
                    <p><strong>✅ 現在可以開始測試父子訂單自動化功能了！</strong></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="warning">
            <h2>⚠️ 警告</h2>
            <p><strong>此工具會清除以下所有資料：</strong></p>
            <ul>
                <li>所有商品（WordPress 商品 + FluentCart 商品）</li>
                <li>所有訂單（父訂單 + 子訂單）</li>
                <li>所有出貨單</li>
                <li>所有分配記錄</li>
            </ul>
            <p><strong>✅ 保留：</strong>客戶資料</p>
            <p><strong>⚠️ 此操作無法復原！僅限測試環境使用！</strong></p>
        </div>

        <div class="stats">
            <h3>📊 目前資料統計</h3>
            <table>
                <tr>
                    <td>WordPress 商品：</td>
                    <td><?php echo number_format($stats['products']); ?> 筆</td>
                </tr>
                <tr>
                    <td>FluentCart 商品：</td>
                    <td><?php echo number_format($stats['fct_products']); ?> 筆</td>
                </tr>
                <tr>
                    <td>訂單：</td>
                    <td><?php echo number_format($stats['orders']); ?> 筆</td>
                </tr>
                <tr>
                    <td>訂單項目：</td>
                    <td><?php echo number_format($stats['order_items']); ?> 筆</td>
                </tr>
                <tr>
                    <td>出貨單：</td>
                    <td><?php echo number_format($stats['shipments']); ?> 筆</td>
                </tr>
                <tr>
                    <td>客戶：</td>
                    <td><?php echo number_format($stats['customers']); ?> 筆（保留）</td>
                </tr>
            </table>
        </div>

        <form method="post" id="resetForm">
            <?php wp_nonce_field('buygo_reset_test_data'); ?>

            <div class="form-group">
                <label for="buygo_reset_confirm">
                    請輸入 <strong style="color: #d32f2f;">YES</strong> 確認清除（大寫）：
                </label>
                <input
                    type="text"
                    id="buygo_reset_confirm"
                    name="buygo_reset_confirm"
                    placeholder="輸入 YES 確認"
                    autocomplete="off"
                >
            </div>

            <p>
                <button type="submit" class="btn btn-danger" id="submitBtn" disabled>
                    🗑️ 清除所有測試資料
                </button>
                <a href="<?php echo admin_url('admin.php?page=buygo-plus-one-orders'); ?>" class="btn btn-secondary">
                    ← 返回訂單管理
                </a>
            </p>
        </form>
    </div>

    <script>
        // 只有輸入 YES 才能點擊按鈕
        document.getElementById('buygo_reset_confirm').addEventListener('input', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = e.target.value !== 'YES';
        });

        // 提交前再次確認
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            if (!confirm('⚠️ 最後確認：確定要清除所有測試資料嗎？此操作無法復原！')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
