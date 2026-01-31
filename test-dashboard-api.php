#!/usr/bin/env php
<?php
/**
 * Test Dashboard API endpoints
 *
 * Usage: php test-dashboard-api.php
 */

// Test Dashboard API endpoints
$base_url = 'http://buygo.me/wp-json/buygo-plus-one/v1/dashboard';

$endpoints = [
    'stats' => '/stats',
    'revenue' => '/revenue?period=30&currency=TWD',
    'products' => '/products',
    'activities' => '/activities?limit=10'
];

$results = [];

echo "Testing Dashboard API Endpoints\n";
echo "================================\n\n";

foreach ($endpoints as $name => $path) {
    $url = $base_url . $path;
    echo "Testing: {$name}\n";
    echo "URL: {$url}\n";

    $start_time = microtime(true);

    // Use curl to make request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $error = curl_error($ch);
    curl_close($ch);

    $end_time = microtime(true);
    $duration = round(($end_time - $start_time) * 1000, 2);

    $results[$name] = [
        'http_code' => $http_code,
        'duration_ms' => $duration,
        'response_size' => strlen($response),
        'error' => $error
    ];

    echo "HTTP Code: {$http_code}\n";
    echo "Duration: {$duration}ms\n";

    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "Success: ✓\n";
            echo "Response: " . substr($response, 0, 200) . "...\n";
        } else {
            echo "Error: Invalid JSON\n";
            echo "Response: " . substr($response, 0, 200) . "\n";
        }
    } else {
        echo "Error: HTTP {$http_code}\n";
        echo "Response: " . substr($response, 0, 200) . "\n";
    }

    echo "\n";
}

echo "\nSummary\n";
echo "=======\n";
foreach ($results as $name => $result) {
    $status = $result['http_code'] === 200 ? '✓' : '✗';
    echo "{$status} {$name}: {$result['http_code']} ({$result['duration_ms']}ms)\n";
}
