<?php
/**
 * LINE 設定 Tab
 *
 * 從 class-settings-page.php 拆分出來的 Tab 渲染模板
 * 變數由父層 SettingsPage::render_line_tab() 傳入
 *
 * @var array $settings LINE 設定陣列
 */
if (!defined('ABSPATH')) {
    exit;
}

// Debug: 顯示解密狀態
$debug_info = [];
$raw_token = get_option('buygo_core_settings', [])['line_channel_access_token'] ?? null;
if (!$raw_token) {
    $raw_token = get_option('buygo_line_channel_access_token', null);
}

if ($raw_token) {
    $debug_info['token_exists'] = true;
    $debug_info['token_length'] = strlen($raw_token);
    $debug_info['token_preview'] = substr($raw_token, 0, 20) . '...';
    $debug_info['decrypted_length'] = strlen($settings['channel_access_token']);
    $debug_info['encryption_key_defined'] = defined('BUYGO_ENCRYPTION_KEY');
} else {
    $debug_info['token_exists'] = false;
}
?>

<!-- 遷移提示 -->
<div class="notice notice-warning" style="margin: 20px 0; padding: 15px; border-left: 4px solid #f0ad4e;">
    <h3 style="margin-top: 0;">⚠️ 重要通知：LINE 設定已遷移</h3>
    <p style="font-size: 14px; margin-bottom: 10px;">
        從 LINE Hub v1.0 開始，所有 LINE 相關設定（Channel ID、Secret、Access Token）已統一管理至 <strong>LINE Hub 外掛</strong>。
    </p>
    <p style="font-size: 14px; margin-bottom: 10px;">
        • <strong>此處設定</strong>：僅作為相容性備援，將在未來版本中移除<br>
        • <strong>建議操作</strong>：請前往 LINE Hub 設定頁面進行設定
    </p>
    <p style="margin-bottom: 0;">
        <a href="<?php echo admin_url('admin.php?page=line-hub-settings'); ?>" class="button button-primary">
            前往 LINE Hub 設定 →
        </a>
    </p>
</div>

<!-- Debug Information -->
<div class="notice notice-info" style="margin: 20px 0;">
    <h3>🔍 LINE 設定 Debug 資訊（舊版相容模式）</h3>
    <table class="widefat" style="margin-top: 10px;">
        <tr>
            <th style="width: 200px;">Token 是否存在</th>
            <td><?php echo $debug_info['token_exists'] ? '✅ 是' : '❌ 否'; ?></td>
        </tr>
        <?php if ($debug_info['token_exists']): ?>
        <tr>
            <th>加密資料長度</th>
            <td><?php echo $debug_info['token_length']; ?> 字元</td>
        </tr>
        <tr>
            <th>加密資料預覽</th>
            <td><code><?php echo esc_html($debug_info['token_preview']); ?></code></td>
        </tr>
        <tr>
            <th>解密後長度</th>
            <td><?php echo $debug_info['decrypted_length']; ?> 字元</td>
        </tr>
        <tr>
            <th>解密結果</th>
            <td>
                <?php if ($debug_info['decrypted_length'] > 0): ?>
                    <span style="color: green;">✅ 解密成功</span>
                <?php else: ?>
                    <span style="color: red;">❌ 解密失敗或資料為空</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th>加密金鑰已定義</th>
            <td><?php echo $debug_info['encryption_key_defined'] ? '✅ 是' : '⚠️ 否（使用預設金鑰）'; ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<form method="post" action="">
    <?php wp_nonce_field('buygo_settings'); ?>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="line_channel_access_token">Channel Access Token</label>
            </th>
            <td>
                <input type="text"
                       id="line_channel_access_token"
                       name="line_channel_access_token"
                       class="regular-text"
                       value="<?php echo esc_attr($settings['channel_access_token']); ?>" />
                <p class="description">LINE Bot 的 Channel Access Token</p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="line_channel_secret">Channel Secret</label>
            </th>
            <td>
                <input type="password"
                       id="line_channel_secret"
                       name="line_channel_secret"
                       class="regular-text"
                       value="<?php echo esc_attr($settings['channel_secret']); ?>" />
                <p class="description">LINE Bot 的 Channel Secret</p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="line_liff_id">LIFF ID</label>
            </th>
            <td>
                <input type="text"
                       id="line_liff_id"
                       name="line_liff_id"
                       class="regular-text"
                       value="<?php echo esc_attr($settings['liff_id']); ?>" />
                <p class="description">LINE LIFF 應用程式 ID</p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label>Webhook URL</label>
            </th>
            <td>
                <input type="text"
                       class="regular-text"
                       value="<?php echo esc_attr($settings['webhook_url']); ?>"
                       readonly />
                <p class="description">自動生成，無需修改。請將此 URL 設定到 LINE Developers Console</p>
            </td>
        </tr>
    </table>

    <p class="submit">
        <button type="button" class="button" id="test-line-connection">
            測試連線
        </button>
        <input type="submit" name="submit" class="button-primary" value="儲存設定" />
    </p>
</form>

<div id="line-test-result" style="margin-top: 20px;"></div>
