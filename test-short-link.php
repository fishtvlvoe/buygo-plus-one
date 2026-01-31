<?php
/**
 * 診斷短連結問題
 */
require_once __DIR__ . '/../../../wp-load.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== 短連結診斷 ===\n\n";

// 1. 檢查商品 816 是否存在
$post_id = 816;
$post = get_post($post_id);

if ($post) {
    echo "✅ 商品存在\n";
    echo "   - ID: {$post->ID}\n";
    echo "   - 標題: {$post->post_title}\n";
    echo "   - 類型: {$post->post_type}\n";
    echo "   - 狀態: {$post->post_status}\n";
    echo "   - Permalink: " . get_permalink($post_id) . "\n";
} else {
    echo "❌ 商品不存在 (ID: {$post_id})\n";
}

echo "\n";

// 2. 檢查 rewrite rules 是否包含 item
global $wp_rewrite;
$rules = $wp_rewrite->wp_rewrite_rules();

echo "=== Rewrite Rules (item 相關) ===\n";
$found_item_rule = false;
if (is_array($rules)) {
    foreach ($rules as $pattern => $rewrite) {
        if (strpos($pattern, 'item') !== false) {
            echo "✅ 找到規則: {$pattern} => {$rewrite}\n";
            $found_item_rule = true;
        }
    }
}

if (!$found_item_rule) {
    echo "❌ 沒有找到 item 相關的 rewrite rule\n";
    echo "   請執行：到 WordPress 後台 → 設定 → 永久連結 → 點「儲存設定」\n";
}

echo "\n";

// 3. 測試 query var
echo "=== Query Vars ===\n";
global $wp;
$public_query_vars = $wp->public_query_vars;
if (in_array('item_id', $public_query_vars)) {
    echo "✅ item_id query var 已註冊\n";
} else {
    echo "❌ item_id query var 未註冊\n";
}

echo "\n";

// 4. 強制刷新 rewrite rules（可選）
if (isset($_GET['flush']) && $_GET['flush'] === '1') {
    echo "=== 強制刷新 Rewrite Rules ===\n";
    flush_rewrite_rules();
    echo "✅ 已刷新 rewrite rules\n";
    echo "請重新載入此頁面（不帶 ?flush=1）確認結果\n";
}

echo "\n";
echo "=== 操作說明 ===\n";
echo "1. 如果沒有找到 item 規則，請訪問：\n";
echo "   https://test.buygo.me/wp-content/plugins/buygo-plus-one-dev/test-short-link.php?flush=1\n";
echo "2. 或者到 WordPress 後台 → 設定 → 永久連結 → 點「儲存設定」\n";
