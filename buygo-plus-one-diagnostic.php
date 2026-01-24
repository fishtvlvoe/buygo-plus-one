<?php
/**
 * BuyGo+1 外掛診斷腳本
 *
 * 使用方法：
 * 1. 將此檔案放在 WordPress 根目錄
 * 2. 在瀏覽器訪問：https://test.buygo.me/buygo-plus-one-diagnostic.php
 * 3. 查看診斷結果
 */

// 載入 WordPress
require_once __DIR__ . '/wp-load.php';

// 開始診斷
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>BuyGo+1 外掛診斷</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .success { color: #008000; }
        .error { color: #ff0000; }
        .warning { color: #ff8800; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 5px; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 BuyGo+1 外掛診斷報告</h1>

    <div class="section">
        <h2>1. 環境檢查</h2>
        <?php
        echo "<p>PHP 版本: " . PHP_VERSION . "</p>";
        echo "<p>WordPress 版本: " . get_bloginfo('version') . "</p>";
        echo "<p>WordPress ABSPATH: " . ABSPATH . "</p>";
        ?>
    </div>

    <div class="section">
        <h2>2. 外掛檔案檢查</h2>
        <?php
        $plugin_path = WP_PLUGIN_DIR . '/buygo-plus-one/buygo-plus-one.php';
        if (file_exists($plugin_path)) {
            echo "<p class='success'>✅ 外掛檔案存在: $plugin_path</p>";

            // 檢查檔案可讀性
            if (is_readable($plugin_path)) {
                echo "<p class='success'>✅ 外掛檔案可讀</p>";
            } else {
                echo "<p class='error'>❌ 外掛檔案不可讀</p>";
            }

            // 檢查語法
            $syntax_check = shell_exec("php -l " . escapeshellarg($plugin_path) . " 2>&1");
            if (strpos($syntax_check, 'No syntax errors') !== false) {
                echo "<p class='success'>✅ 外掛主檔案語法正確</p>";
            } else {
                echo "<p class='error'>❌ 外掛主檔案語法錯誤:</p>";
                echo "<pre>" . htmlspecialchars($syntax_check) . "</pre>";
            }
        } else {
            echo "<p class='error'>❌ 外掛檔案不存在: $plugin_path</p>";
        }

        // 檢查必要的依賴檔案
        $required_files = [
            'includes/class-plugin.php',
            'includes/class-plugin-compatibility.php',
            'includes/class-database.php',
            'includes/class-loader.php',
            'admin/class-admin.php',
            'public/class-public.php',
        ];

        echo "<h3>依賴檔案檢查:</h3>";
        $missing_files = [];
        foreach ($required_files as $file) {
            $full_path = WP_PLUGIN_DIR . '/buygo-plus-one/' . $file;
            if (file_exists($full_path)) {
                echo "<p class='success'>✅ $file</p>";
            } else {
                echo "<p class='error'>❌ $file (缺失)</p>";
                $missing_files[] = $file;
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>3. 常數衝突檢查</h2>
        <?php
        if (defined('BUYGO_PLUS_ONE_VERSION')) {
            echo "<p class='warning'>⚠️ BUYGO_PLUS_ONE_VERSION 已定義: " . BUYGO_PLUS_ONE_VERSION . "</p>";
            echo "<p>這表示舊版 buygo 外掛可能已經載入了這個常數</p>";
        } else {
            echo "<p class='success'>✅ BUYGO_PLUS_ONE_VERSION 未定義（無衝突）</p>";
        }

        // 檢查其他可能衝突的常數
        $constants_to_check = [
            'BUYGO_PLUS_ONE_PATH',
            'BUYGO_PLUS_ONE_URL',
            'BUYGO_PLUS_ONE_PLUGIN_DIR',
            'BUYGO_PLUS_ONE_PLUGIN_URL',
            'BUYGO_PLUS_ONE_PLUGIN_FILE',
        ];

        foreach ($constants_to_check as $const) {
            if (defined($const)) {
                echo "<p class='warning'>⚠️ $const 已定義: " . constant($const) . "</p>";
            } else {
                echo "<p class='success'>✅ $const 未定義</p>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>4. 外掛狀態檢查</h2>
        <?php
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins_to_check = [
            'buygo/buygo.php' => '舊版 BuyGo',
            'buygo-plus-one/buygo-plus-one.php' => 'BuyGo+1 正式版',
            'buygo-plus-one-dev/buygo-plus-one.php' => 'BuyGo+1 開發版',
        ];

        foreach ($plugins_to_check as $plugin => $name) {
            $is_active = is_plugin_active($plugin);
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false);

            if ($is_active) {
                echo "<p class='warning'>⚠️ $name: <strong>已啟用</strong></p>";
                if (!empty($plugin_data['Version'])) {
                    echo "<p>　　版本: {$plugin_data['Version']}</p>";
                }
            } else {
                if (file_exists(WP_PLUGIN_DIR . '/' . $plugin)) {
                    echo "<p>ℹ️ $name: 已安裝但未啟用</p>";
                } else {
                    echo "<p>❌ $name: 未安裝</p>";
                }
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>5. 嘗試模擬啟用</h2>
        <?php
        echo "<p>嘗試載入外掛主檔案（不執行啟用 hook）...</p>";

        // 臨時關閉錯誤顯示，捕捉錯誤
        $old_error_reporting = error_reporting(0);
        $old_display_errors = ini_get('display_errors');
        ini_set('display_errors', '0');

        ob_start();
        try {
            // 只載入主檔案，不執行 hook
            $plugin_file = WP_PLUGIN_DIR . '/buygo-plus-one/buygo-plus-one.php';

            // 檢查檔案內容
            $content = file_get_contents($plugin_file);

            // 檢查是否有 syntax error
            $temp_file = tempnam(sys_get_temp_dir(), 'buygo-test-');
            file_put_contents($temp_file, $content);
            $syntax_output = shell_exec("php -l " . escapeshellarg($temp_file) . " 2>&1");
            unlink($temp_file);

            if (strpos($syntax_output, 'No syntax errors') !== false) {
                echo "<p class='success'>✅ 外掛檔案可以被 PHP 解析</p>";

                // 嘗試實際 include（危險，可能會有副作用）
                // 這裡我們只是檢查，不實際執行
                echo "<p class='success'>✅ 外掛結構檢查完成</p>";
            } else {
                echo "<p class='error'>❌ PHP 語法錯誤:</p>";
                echo "<pre>" . htmlspecialchars($syntax_output) . "</pre>";
            }

        } catch (Throwable $e) {
            echo "<p class='error'>❌ 錯誤: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p>檔案: " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }
        $output = ob_get_clean();
        echo $output;

        // 恢復錯誤設定
        error_reporting($old_error_reporting);
        ini_set('display_errors', $old_display_errors);
        ?>
    </div>

    <div class="section">
        <h2>6. 最近的錯誤日誌</h2>
        <?php
        $debug_log = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($debug_log)) {
            echo "<p>讀取最後 50 行錯誤日誌:</p>";
            $lines = file($debug_log);
            $last_lines = array_slice($lines, -50);

            // 過濾出與 buygo 相關的錯誤
            $buygo_errors = array_filter($last_lines, function($line) {
                return stripos($line, 'buygo') !== false ||
                       stripos($line, 'fatal') !== false ||
                       stripos($line, 'error') !== false;
            });

            if (!empty($buygo_errors)) {
                echo "<pre>" . htmlspecialchars(implode('', $buygo_errors)) . "</pre>";
            } else {
                echo "<p class='success'>✅ 沒有發現相關錯誤</p>";
            }
        } else {
            echo "<p class='warning'>⚠️ debug.log 不存在</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>7. 建議操作</h2>
        <?php
        $suggestions = [];

        // 檢查是否有舊版啟用
        if (is_plugin_active('buygo/buygo.php')) {
            $suggestions[] = "✅ 舊版 buygo 已啟用，新版應該可以共存";
        }

        // 檢查是否有開發版啟用
        if (is_plugin_active('buygo-plus-one-dev/buygo-plus-one.php')) {
            $suggestions[] = "❌ 開發版已啟用，請先停用開發版再啟用正式版";
        }

        // 檢查檔案完整性
        if (!empty($missing_files)) {
            $suggestions[] = "❌ 缺少必要檔案，請重新安裝外掛";
        }

        if (empty($suggestions)) {
            echo "<p class='success'>✅ 初步檢查未發現明顯問題</p>";
            echo "<p>請嘗試:</p>";
            echo "<ol>";
            echo "<li>清除 PHP OPcache: 在 WordPress 後台 → 工具 → 網站健康</li>";
            echo "<li>停用所有其他外掛，只保留 buygo 和 buygo-plus-one</li>";
            echo "<li>檢查 PHP error_log</li>";
            echo "</ol>";
        } else {
            foreach ($suggestions as $suggestion) {
                echo "<p>$suggestion</p>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>8. 系統資訊</h2>
        <pre><?php
        echo "PHP Memory Limit: " . ini_get('memory_limit') . "\n";
        echo "Max Execution Time: " . ini_get('max_execution_time') . "\n";
        echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";
        echo "Post Max Size: " . ini_get('post_max_size') . "\n";
        echo "Display Errors: " . ini_get('display_errors') . "\n";
        echo "Error Reporting: " . error_reporting() . "\n";
        ?></pre>
    </div>

</body>
</html>
