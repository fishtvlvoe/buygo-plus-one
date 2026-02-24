<?php if (!defined('ABSPATH')) { exit; } ?>
<link rel="stylesheet" href="<?php echo esc_url(BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/css/feature-management.css'); ?>?ver=<?php echo BUYGO_PLUS_ONE_VERSION; ?>">

<script>
window.bgoFeatureManagement = {
    restUrl: '<?php echo esc_url(rest_url('buygo-plus-one/v1')); ?>',
    restNonce: '<?php echo wp_create_nonce('wp_rest'); ?>'
};
</script>

<h2 style="margin: 0 0 16px;">功能管理</h2>

<div id="bgo-fm-message" class="bgo-fm-message" style="display:none;"></div>

<!-- 授權狀態卡片 -->
<div class="bgo-card bgo-fm-license-card">
    <div class="bgo-fm-license-status">
        <span id="bgo-fm-license-badge" class="bgo-badge bgo-badge-default">Free</span>
        <span id="bgo-fm-license-expires" style="display:none;font-size:12px;color:#666;"></span>
    </div>
    <div class="bgo-fm-license-input">
        <input type="text" id="bgo-fm-license-key" placeholder="輸入授權碼">
        <button class="button button-primary" id="bgo-fm-activate-btn">驗證</button>
        <button class="button bgo-fm-deactivate" id="bgo-fm-deactivate-btn" style="display:none;">停用授權</button>
    </div>
</div>

<!-- Free 功能 -->
<div class="bgo-card" style="margin-top: 16px;">
    <h3 style="margin: 0 0 16px; font-size: 16px; font-weight: 600;">基礎功能（Free）</h3>
    <div class="bgo-fm-feature-list" id="bgo-fm-free-list">
        <div style="text-align:center;color:#999;padding:20px;">載入中...</div>
    </div>
</div>

<!-- Pro 功能 -->
<div class="bgo-card" style="margin-top: 16px;">
    <h3 style="margin: 0 0 16px; font-size: 16px; font-weight: 600;">進階功能（Pro）</h3>
    <div class="bgo-fm-feature-list" id="bgo-fm-pro-list">
        <div style="text-align:center;color:#999;padding:20px;">載入中...</div>
    </div>
</div>

<script src="<?php echo esc_url(BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/js/feature-management.js'); ?>?ver=<?php echo BUYGO_PLUS_ONE_VERSION; ?>"></script>
