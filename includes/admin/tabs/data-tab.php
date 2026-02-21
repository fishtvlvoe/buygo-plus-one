<?php if (!defined('ABSPATH')) { exit; }
?>
<style>
.bgo-data-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 32px; max-width: 640px; text-align: center; }
.bgo-data-card h3 { margin: 0 0 8px; font-size: 16px; font-weight: 600; color: #1d2327; }
.bgo-data-card p { margin: 0; font-size: 13px; color: #888; }
.bgo-data-items { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; text-align: left; }
.bgo-data-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #f9fafb; border-radius: 4px; font-size: 13px; color: #666; }
.bgo-data-item span { color: #1d2327; font-weight: 500; }
</style>

<h2 style="margin: 0 0 16px;">資料管理</h2>

<div class="bgo-data-card">
    <h3>即將推出</h3>
    <p>資料管理功能正在開發中，預計包含以下功能：</p>
    <div class="bgo-data-items">
        <div class="bgo-data-item"><span>訂單資料</span> — 按日期範圍查詢、匯出、刪除</div>
        <div class="bgo-data-item"><span>商品資料</span> — 批次管理、清理無效商品</div>
        <div class="bgo-data-item"><span>客戶資料</span> — 查詢、匯出、GDPR 合規刪除</div>
        <div class="bgo-data-item"><span>日誌清理</span> — 自動清理過期的 Webhook 和系統日誌</div>
    </div>
</div>
