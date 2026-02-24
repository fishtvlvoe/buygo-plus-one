<?php if (!defined('ABSPATH')) { exit; }

// 注意：此檔案在 class-settings-page.php 的 render_page() 中被 require，
// 此時 wp_head() 已執行，所以用直接 <link>/<script> 標籤載入。
?>
<link rel="stylesheet" href="<?php echo esc_url(BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/css/data-management.css'); ?>?ver=<?php echo BUYGO_PLUS_ONE_VERSION; ?>">

<script>
window.bgoDataManagement = {
    restUrl: '<?php echo esc_url(rest_url('buygo-plus-one/v1')); ?>',
    restNonce: '<?php echo wp_create_nonce('wp_rest'); ?>'
};
</script>

<h2 style="margin: 0 0 16px;">資料管理</h2>

<!-- 子 Tab 導航 -->
<div class="bgo-dm-sub-tabs">
    <button class="bgo-dm-sub-tab active" data-type="orders">訂單</button>
    <button class="bgo-dm-sub-tab" data-type="products">商品</button>
    <button class="bgo-dm-sub-tab" data-type="customers">客戶</button>
</div>

<!-- 查詢篩選 -->
<div class="bgo-card" style="margin-top: 16px;">
    <div class="bgo-dm-filters">
        <label>日期範圍</label>
        <input type="date" id="bgo-dm-date-from">
        <span>~</span>
        <input type="date" id="bgo-dm-date-to">
        <input type="text" id="bgo-dm-keyword" placeholder="關鍵字搜尋...">
        <button class="button button-primary" id="bgo-dm-query-btn">查詢</button>
    </div>
</div>

<!-- 結果表格 -->
<div class="bgo-card" style="margin-top: 16px;">
    <div class="bgo-dm-toolbar">
        <label><input type="checkbox" id="bgo-dm-select-all"> 全選</label>
        <span id="bgo-dm-selected-info" class="bgo-dm-selected-info"></span>
        <div class="bgo-dm-toolbar-right">
            <select id="bgo-dm-per-page" class="bgo-dm-per-page-select">
                <option value="20">20 筆</option>
                <option value="50">50 筆</option>
                <option value="-1">全部</option>
            </select>
            <button class="bgo-dm-delete-btn" id="bgo-dm-delete-btn" disabled>刪除選取</button>
        </div>
    </div>
    <table class="bgo-dev-table" id="bgo-dm-table">
        <thead id="bgo-dm-thead"></thead>
        <tbody id="bgo-dm-tbody">
            <tr><td colspan="8" class="bgo-dev-empty">載入中...</td></tr>
        </tbody>
    </table>
    <div class="bgo-dm-pagination" id="bgo-dm-pagination"></div>
</div>

<!-- 刪除確認 Modal -->
<div id="bgo-dm-delete-modal" class="bgo-modal" style="display:none;">
    <div class="bgo-modal-overlay"></div>
    <div class="bgo-modal-content">
        <h3>確認刪除</h3>
        <p>即將刪除 <strong id="bgo-dm-delete-count">0</strong> 筆<span id="bgo-dm-delete-type">資料</span>，此操作不可復原。</p>
        <div style="margin: 16px 0;">
            <input type="text" id="bgo-dm-delete-confirm-input" placeholder="請輸入「確認刪除」" style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px;">
        </div>
        <div style="display:flex; gap:8px; justify-content:flex-end;">
            <button class="button" id="bgo-dm-delete-cancel">取消</button>
            <button class="button" id="bgo-dm-delete-confirm" disabled style="background:#d63638; color:#fff; border-color:#d63638;">確認刪除</button>
        </div>
    </div>
</div>

<!-- 客戶編輯 Modal -->
<div id="bgo-dm-edit-modal" class="bgo-modal" style="display:none;">
    <div class="bgo-modal-overlay"></div>
    <div class="bgo-modal-content" style="max-width: 500px;">
        <h3>編輯客戶</h3>
        <form id="bgo-dm-edit-form" onsubmit="return false;">
            <input type="hidden" id="bgo-dm-edit-id">
            <div class="bgo-dm-form-row">
                <label>姓</label>
                <input type="text" id="bgo-dm-edit-last-name">
            </div>
            <div class="bgo-dm-form-row">
                <label>名</label>
                <input type="text" id="bgo-dm-edit-first-name">
            </div>
            <div class="bgo-dm-form-row">
                <label>電話</label>
                <input type="text" id="bgo-dm-edit-phone">
            </div>
            <div class="bgo-dm-form-row">
                <label>地址</label>
                <input type="text" id="bgo-dm-edit-address">
            </div>
            <div class="bgo-dm-form-row">
                <label>縣市</label>
                <input type="text" id="bgo-dm-edit-city">
            </div>
            <div class="bgo-dm-form-row">
                <label>郵遞區號</label>
                <input type="text" id="bgo-dm-edit-postcode">
            </div>
            <div class="bgo-dm-form-row">
                <label>身分證</label>
                <input type="text" id="bgo-dm-edit-taiwan-id">
            </div>
        </form>
        <div id="bgo-dm-edit-message" style="display:none; margin-top:8px;"></div>
        <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
            <button class="button" id="bgo-dm-edit-cancel">取消</button>
            <button class="button button-primary" id="bgo-dm-edit-save">儲存</button>
        </div>
    </div>
</div>

<script src="<?php echo esc_url(BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/js/data-management.js'); ?>?ver=<?php echo BUYGO_PLUS_ONE_VERSION; ?>"></script>
