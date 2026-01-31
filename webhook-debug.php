<?php
/**
 * Webhook Debug - 臨時診斷工具
 *
 * 訪問 https://test.buygo.me/webhook-debug.php
 * 查看所有接收到的 headers
 */

header('Content-Type: application/json');

$debug_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'all_headers' => [],
    'specific_headers' => [
        'X-Line-Signature' => $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? 'NOT FOUND',
        'Content-Type' => $_SERVER['CONTENT_TYPE'] ?? 'NOT FOUND',
        'User-Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'NOT FOUND',
    ],
    'body' => file_get_contents('php://input'),
    'server_vars' => []
];

// 收集所有 HTTP headers
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $header_name = str_replace('_', '-', substr($key, 5));
        $debug_info['all_headers'][$header_name] = $value;
    }
}

// 收集重要的 SERVER 變數
$important_vars = ['REMOTE_ADDR', 'REQUEST_URI', 'QUERY_STRING', 'CONTENT_LENGTH'];
foreach ($important_vars as $var) {
    if (isset($_SERVER[$var])) {
        $debug_info['server_vars'][$var] = $_SERVER[$var];
    }
}

// 記錄到檔案
$log_file = __DIR__ . '/webhook-debug.log';
file_put_contents($log_file, json_encode($debug_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n" . str_repeat('=', 80) . "\n\n", FILE_APPEND);

// 返回 JSON 響應
echo json_encode([
    'success' => true,
    'message' => 'Debug info logged',
    'debug' => $debug_info
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
