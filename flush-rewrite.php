<?php
/**
 * Flush Rewrite Rules Script
 *
 * 將此檔案上傳到 WordPress 根目錄，然後訪問一次即可重新整理 rewrite rules
 * 使用後請立即刪除此檔案（安全考量）
 */

// 載入 WordPress
require_once __DIR__ . '/../../../wp-load.php';

// 檢查權限
if (!current_user_can('manage_options')) {
    wp_die('權限不足');
}

// 重新整理 rewrite rules
flush_rewrite_rules(true);

echo '<h1>Rewrite Rules 已重新整理</h1>';
echo '<p>Dashboard 路由已註冊。</p>';
echo '<p><a href="' . home_url('/buygo-portal/dashboard/') . '">前往 Dashboard</a></p>';
echo '<p><strong>請立即刪除此檔案！</strong></p>';
