<?php
/**
 * BuyGo Plus One - Webhook 功能測試頁面
 * 
 * 用途：測試階段 2 開發的 Webhook 相關功能
 * 訪問方式：http://localhost:10004/wp-content/plugins/buygo-plus-one/tests/test-webhook.php
 */

// 載入 WordPress
require_once dirname(__DIR__, 4) . '/wp-load.php';

// 檢查是否為管理員
if (!current_user_can('manage_options')) {
    wp_die('此頁面僅限管理員訪問');
}

// 測試結果陣列
$tests = [];
$passed = 0;
$failed = 0;

/**
 * 執行測試並記錄結果
 */
function run_test($name, $callback) {
    global $tests, $passed, $failed;
    
    try {
        $result = $callback();
        $tests[] = [
            'name' => $name,
            'status' => $result ? 'pass' : 'fail',
            'message' => $result ? '通過' : '失敗'
        ];
        
        if ($result) {
            $passed++;
        } else {
            $failed++;
        }
    } catch (Exception $e) {
        $tests[] = [
            'name' => $name,
            'status' => 'error',
            'message' => '錯誤：' . $e->getMessage()
        ];
        $failed++;
    }
}

// ========================================
// 測試項目
// ========================================

// 測試 1：檢查 LineWebhookHandler 類別是否存在
run_test('LineWebhookHandler 類別存在', function() {
    return class_exists('BuyGoPlus\\Services\\LineWebhookHandler');
});

// 測試 2：檢查 WebhookLogger 類別是否存在
run_test('WebhookLogger 類別存在', function() {
    return class_exists('BuyGoPlus\\Services\\WebhookLogger');
});

// 測試 3：檢查資料庫表是否存在
run_test('wp_buygo_webhook_logs 資料表存在', function() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'buygo_webhook_logs';
    $result = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    return $result === $table_name;
});

// 測試 4：檢查 Webhook API 端點是否註冊
run_test('Webhook API 端點已註冊', function() {
    $routes = rest_get_server()->get_routes();
    return isset($routes['/buygo-plus-one/v1/line/webhook']);
});

// 測試 5：檢查 SettingsService::get_user_line_id 方法是否存在
run_test('SettingsService::get_user_line_id 方法存在', function() {
    return method_exists('BuyGoPlus\\Services\\SettingsService', 'get_user_line_id');
});

// 測試 6：檢查 FluentCartService 類別是否存在
run_test('FluentCartService 類別存在', function() {
    return class_exists('BuyGoPlus\\Services\\FluentCartService');
});

// 測試 7：檢查 ProductDataParser 類別是否存在
run_test('ProductDataParser 類別存在', function() {
    return class_exists('BuyGoPlus\\Services\\ProductDataParser');
});

// 測試 8：檢查 NotificationTemplates 類別是否存在（已改用此類別取代 LineFlexTemplates）
run_test('NotificationTemplates 類別存在', function() {
    return class_exists('BuyGoPlus\\Services\\NotificationTemplates');
});

// 測試 9：測試 WebhookLogger 寫入功能
run_test('WebhookLogger 可以寫入日誌', function() {
    $logger = BuyGoPlus\\Services\\WebhookLogger::get_instance();
    
    // 寫入測試日誌
    $webhook_id = $logger->log('test_event', [
        'message' => 'Test log entry'
    ], null, null);
    
    return $webhook_id > 0;
});

// 測試 10：測試 NotificationTemplates 功能
run_test('NotificationTemplates 可以取得模板', function() {
    $template = BuyGoPlus\\Services\\NotificationTemplates::get('flex_image_upload_menu', []);
    return !empty($template);
});

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BuyGo Plus One - Webhook 功能測試</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            padding: 40px;
            background: #f8f9fa;
        }
        
        .summary-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .summary-card h3 {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .summary-card .number {
            font-size: 48px;
            font-weight: bold;
            line-height: 1;
        }
        
        .summary-card.total .number {
            color: #667eea;
        }
        
        .summary-card.passed .number {
            color: #10b981;
        }
        
        .summary-card.failed .number {
            color: #ef4444;
        }
        
        .tests {
            padding: 40px;
        }
        
        .test-item {
            display: flex;
            align-items: center;
            padding: 20px;
            margin-bottom: 12px;
            border-radius: 12px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .test-item:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .test-item.pass {
            border-left: 4px solid #10b981;
        }
        
        .test-item.fail {
            border-left: 4px solid #ef4444;
        }
        
        .test-item.error {
            border-left: 4px solid #f59e0b;
        }
        
        .test-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-right: 20px;
            flex-shrink: 0;
        }
        
        .test-item.pass .test-icon {
            background: #d1fae5;
            color: #10b981;
        }
        
        .test-item.fail .test-icon {
            background: #fee2e2;
            color: #ef4444;
        }
        
        .test-item.error .test-icon {
            background: #fef3c7;
            color: #f59e0b;
        }
        
        .test-content {
            flex: 1;
        }
        
        .test-name {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }
        
        .test-message {
            font-size: 14px;
            color: #6c757d;
        }
        
        .footer {
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 BuyGo Plus One</h1>
            <p>階段 2：Webhook 功能測試報告</p>
        </div>
        
        <div class="summary">
            <div class="summary-card total">
                <h3>總測試數</h3>
                <div class="number"><?php echo count($tests); ?></div>
            </div>
            <div class="summary-card passed">
                <h3>通過</h3>
                <div class="number"><?php echo $passed; ?></div>
            </div>
            <div class="summary-card failed">
                <h3>失敗</h3>
                <div class="number"><?php echo $failed; ?></div>
            </div>
        </div>
        
        <div class="tests">
            <?php foreach ($tests as $test): ?>
                <div class="test-item <?php echo $test['status']; ?>">
                    <div class="test-icon">
                        <?php if ($test['status'] === 'pass'): ?>
                            ✓
                        <?php elseif ($test['status'] === 'fail'): ?>
                            ✗
                        <?php else: ?>
                            ⚠
                        <?php endif; ?>
                    </div>
                    <div class="test-content">
                        <div class="test-name"><?php echo esc_html($test['name']); ?></div>
                        <div class="test-message"><?php echo esc_html($test['message']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="footer">
            <p>測試完成時間：<?php echo current_time('Y-m-d H:i:s'); ?></p>
            <p>WordPress 版本：<?php echo get_bloginfo('version'); ?></p>
            <p>PHP 版本：<?php echo PHP_VERSION; ?></p>
        </div>
    </div>
</body>
</html>
