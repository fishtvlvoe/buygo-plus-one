<?php
/**
 * 測試資料庫設置腳本
 *
 * 使用 Local by Flywheel 的 MySQL socket 建立測試資料庫
 * 用法: php bin/setup-test-db.php
 */

// 獲取 Local MySQL socket 位置
$socket = getenv('LOCAL_MYSQL_SOCKET') ?:
    '/Users/fishtv/Library/Application Support/Local/run/oFa4PFqBu/mysql/mysqld.sock';

$host = 'localhost';
$user = 'root';
$password = 'root';
$testDbName = 'wordpress_test';

// 顏色輸出
$colors = [
    'success' => "\033[0;32m",
    'error' => "\033[0;31m",
    'info' => "\033[0;34m",
    'reset' => "\033[0m",
];

echo $colors['info'] . "========================================" . $colors['reset'] . "\n";
echo $colors['info'] . "BuyGo Plus One - 測試資料庫設置" . $colors['reset'] . "\n";
echo $colors['info'] . "========================================" . $colors['reset'] . "\n\n";

echo "Socket 位置: $socket\n";
echo "資料庫名稱: $testDbName\n\n";

// 檢查 socket 檔案是否存在
if (!file_exists($socket)) {
    echo $colors['error'] . "❌ 錯誤: MySQL socket 檔案不存在" . $colors['reset'] . "\n";
    echo "預期路徑: $socket\n";
    echo "請確認 Local by Flywheel 已啟動\n";
    exit(1);
}

echo $colors['success'] . "✓ 找到 MySQL socket 檔案" . $colors['reset'] . "\n\n";

// 建立 mysqli 連接
$mysqli = new mysqli(
    ini_get('mysqli.default_host') ?: $host,
    $user,
    $password,
    '',
    0,
    $socket
);

// 檢查連接
if ($mysqli->connect_error) {
    echo $colors['error'] . "❌ 連接失敗: " . $mysqli->connect_error . $colors['reset'] . "\n";
    exit(1);
}

echo $colors['success'] . "✓ 成功連接到 MySQL" . $colors['reset'] . "\n";
echo "MySQL 版本: " . $mysqli->server_info . "\n\n";

// 建立或重置測試資料庫
echo "正在設置測試資料庫...\n";

// 刪除現有的測試資料庫（清除舊資料）
if ($mysqli->query("DROP DATABASE IF EXISTS $testDbName")) {
    echo $colors['success'] . "✓ 刪除舊的測試資料庫" . $colors['reset'] . "\n";
} else {
    echo $colors['error'] . "❌ 無法刪除舊資料庫: " . $mysqli->error . $colors['reset'] . "\n";
}

// 建立新的測試資料庫
if ($mysqli->query("CREATE DATABASE $testDbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    echo $colors['success'] . "✓ 建立測試資料庫: $testDbName" . $colors['reset'] . "\n";
} else {
    echo $colors['error'] . "❌ 無法建立資料庫: " . $mysqli->error . $colors['reset'] . "\n";
    exit(1);
}

// 驗證資料庫建立
if ($mysqli->select_db($testDbName)) {
    echo $colors['success'] . "✓ 成功選擇測試資料庫" . $colors['reset'] . "\n";
} else {
    echo $colors['error'] . "❌ 無法選擇資料庫: " . $mysqli->error . $colors['reset'] . "\n";
    exit(1);
}

echo "\n" . $colors['success'] . "========================================" . $colors['reset'] . "\n";
echo $colors['success'] . "✓ 測試資料庫設置完成！" . $colors['reset'] . "\n";
echo $colors['success'] . "========================================" . $colors['reset'] . "\n";

$mysqli->close();
exit(0);
