<?php
/**
 * 模板管理 Tab
 *
 * 包含客戶、賣家、系統通知模板 + 關鍵字訊息的編輯介面
 * A1: 只保留模板名稱欄（移除說明和操作欄）
 * A2: 點擊整行展開/收合
 * A3: 儲存按鈕在每個模板編輯區域內
 * A4: 未儲存提醒
 * A5: 關鍵字獨立為第四個子 Tab
 */
if (!defined('ABSPATH')) {
    exit;
}

// 取得所有模板
$all_templates = \BuyGoPlus\Services\NotificationTemplates::get_all_templates();

// 變數說明對應表
$variable_descriptions = [
    'order_id'                  => '訂單編號',
    'total'                     => '訂單總金額',
    'note'                      => '備註說明',
    'product_name'              => '商品名稱',
    'quantity'                  => '數量',
    'buyer_name'                => '買家名稱',
    'order_total'               => '訂單總額',
    'order_url'                 => '訂單連結',
    'error_message'             => '錯誤訊息',
    'product_url'               => '商品連結',
    'price'                     => '價格',
    'currency_symbol'           => '貨幣符號',
    'original_price_section'    => '原價區塊',
    'category_section'          => '分類區塊',
    'arrival_date_section'      => '到貨日期區塊',
    'preorder_date_section'     => '預購日期區塊',
    'community_url_section'     => '社群連結區塊',
    'missing_fields'            => '缺少欄位',
    'display_name'              => '使用者名稱',
    'purchase_url'              => '購買連結',
    'product_limit'             => '商品配額數量',
    'dashboard_url'             => '後台管理連結',
    'order_count'               => '進行中訂單筆數',
    'order_details'             => '訂單明細',
    'account_url'               => '會員中心連結',
];

// 定義可編輯的模板
$editable_templates = [
    'buyer' => [
        'order_created' => [
            'name'        => '訂單已建立',
            'description' => '訂單建立時（完整或拆分）發送給客戶',
            'variables'   => ['order_id', 'total'],
        ],
        'order_cancelled' => [
            'name'        => '訂單已取消',
            'description' => '訂單取消時（僅客戶自行取消）發送給客戶',
            'variables'   => ['order_id', 'note'],
        ],
        'plusone_order_confirmation' => [
            'name'        => '訂單確認',
            'description' => '訂單確認（留言回覆）發送給買家',
            'variables'   => ['product_name', 'quantity', 'total'],
        ],
        'order_query' => [
            'name'        => '訂單查詢',
            'description' => '/訂單 指令的回覆模板',
            'variables'   => ['order_count', 'order_details', 'total', 'account_url'],
        ],
    ],
    'seller' => [
        'seller_order_created' => [
            'name'        => '新訂單通知',
            'description' => '有人下訂單時發送給賣家',
            'variables'   => ['order_id', 'buyer_name', 'order_total', 'order_url'],
        ],
        'seller_order_cancelled' => [
            'name'        => '訂單已取消',
            'description' => '訂單取消時發送給賣家',
            'variables'   => ['order_id', 'buyer_name', 'note', 'order_url'],
        ],
        'helper_product_created' => [
            'name'        => '小幫手上架通知',
            'description' => '商品上架時發送給非上架者（賣家上架→通知小幫手，小幫手上架→通知賣家）',
            'variables'   => ['product_name', 'price', 'quantity', 'product_url', 'currency_symbol', 'original_price_section', 'category_section', 'arrival_date_section', 'preorder_date_section'],
        ],
    ],
    'system' => [
        'system_line_follow' => [
            'name'        => '加入好友通知',
            'description' => '加入好友時發送（含第一則通知）',
            'variables'   => [],
        ],
        'flex_image_upload_menu' => [
            'name'        => '圖片上傳成功（卡片式訊息）',
            'description' => '圖片上傳成功後發送的卡片式訊息',
            'type'        => 'flex',
            'variables'   => [],
        ],
        'system_image_upload_failed' => [
            'name'        => '圖片上傳失敗',
            'description' => '圖片上傳失敗時發送',
            'variables'   => ['error_message'],
        ],
        'system_product_published' => [
            'name'        => '商品上架成功',
            'description' => '商品上架成功時發送',
            'variables'   => ['product_name', 'price', 'quantity', 'product_url', 'currency_symbol', 'original_price_section', 'category_section', 'arrival_date_section', 'preorder_date_section'],
        ],
        'system_product_publish_failed' => [
            'name'        => '商品上架失敗',
            'description' => '商品上架失敗時發送',
            'variables'   => ['error_message'],
        ],
        'system_product_data_incomplete' => [
            'name'        => '商品資料不完整',
            'description' => '商品資料不完整時發送',
            'variables'   => ['missing_fields'],
        ],
        'system_permission_denied' => [
            'name'        => '權限不足通知',
            'description' => '非賣家用戶嘗試上架商品時發送',
            'variables'   => ['display_name', 'purchase_url'],
        ],
        'system_seller_grant_line' => [
            'name'        => '成為賣家 LINE 通知',
            'description' => '購買賣家資格後發送的 LINE 恭喜訊息',
            'variables'   => ['display_name', 'product_limit', 'dashboard_url'],
        ],
        'system_seller_grant_email' => [
            'name'        => '成為賣家 Email 通知',
            'description' => '購買賣家資格後發送的 Email 恭喜訊息',
            'variables'   => ['display_name', 'product_limit', 'dashboard_url'],
        ],
    ],
];

// 取得關鍵字列表
$keywords = get_option('buygo_line_keywords', []);
if (empty($keywords)) {
    $keywords = [
        [
            'id'       => 'help',
            'keyword'  => '/help',
            'aliases'  => ['/幫助', '?help', '幫助'],
            'message'  => "📱 商品上架說明\n\n【步驟】\n1️⃣ 發送商品圖片\n2️⃣ 發送商品資訊\n\n【必填欄位】\n商品名稱\n價格：350\n數量：20\n\n【選填欄位】\n原價：500\n分類：服飾\n到貨：01/25\n預購：01/20\n描述：商品描述\n\n【範例】\n冬季外套\n價格：1200\n原價：1800\n數量：15\n分類：服飾\n到貨：01/15\n\n💡 輸入 /分類 查看可用分類",
            'order'    => 0,
        ],
    ];
}
usort($keywords, function ($a, $b) {
    return ($a['order'] ?? 0) - ($b['order'] ?? 0);
});
?>
<style>
/* ── 子 Tab 導航 ── */
.bgo-templates-subtabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid #e0e0e0;
    margin: 16px 0;
}
.bgo-templates-subtab {
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 500;
    color: #666;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
    text-decoration: none;
}
.bgo-templates-subtab:hover { color: #1d2327; }
.bgo-templates-subtab.active { color: #3b82f6; border-bottom-color: #3b82f6; }

/* ── 模板列表表格（單欄：只有名稱）── */
.bgo-templates-table {
    border-collapse: collapse;
    width: 100%;
    max-width: 900px;
}
.bgo-templates-table th {
    text-align: left;
    padding: 10px 14px;
    font-size: 12px;
    color: #666;
    border-bottom: 2px solid #e0e0e0;
    font-weight: 600;
}
/* 整行可點擊 */
.bgo-templates-table tbody tr.bgo-template-row {
    cursor: pointer;
}
.bgo-templates-table tbody tr.bgo-template-row td {
    padding: 12px 14px;
    font-size: 13px;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.15s;
    vertical-align: middle;
}
.bgo-templates-table tbody tr.bgo-template-row:nth-child(odd) td { background: #fff; }
.bgo-templates-table tbody tr.bgo-template-row:nth-child(even) td { background: #f9fafb; }
.bgo-templates-table tbody tr.bgo-template-row:hover td { background: #f0f7ff; }
.bgo-templates-table tbody tr.bgo-template-row.is-open td { background: #e8f0fe !important; }

/* 展開箭頭 */
.bgo-row-arrow {
    float: right;
    display: inline-block;
    transition: transform 0.2s;
    color: #999;
    font-size: 11px;
}
.bgo-template-row.is-open .bgo-row-arrow { transform: rotate(180deg); }

/* 展開的編輯區 */
.bgo-templates-table tr.template-edit-row td {
    padding: 20px 24px;
    background: #f9f9f9 !important;
    border-bottom: 1px solid #e0e0e0;
}

/* 變數按鈕 */
.bgo-var-btn {
    cursor: pointer;
    font-family: monospace;
    font-size: 12px;
    padding: 4px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
    color: #1d2327;
    transition: all 0.2s;
}
.bgo-var-btn:hover { border-color: #3b82f6; background: #f0f7ff; }

/* ── 變數 + 儲存按鈕同行容器 ── */
.bgo-vars-save-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-top: 14px;
    gap: 16px;
}
.bgo-vars-save-row .bgo-vars-left {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
}

/* ── 模板內儲存按鈕 ── */
.bgo-save-in-template {
    flex-shrink: 0;
    align-self: flex-end;
    background: #3b82f6;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 10px 28px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(59,130,246,0.25);
    transition: background 0.2s, box-shadow 0.2s;
}
.bgo-save-in-template:hover { background: #2563eb; box-shadow: 0 4px 12px rgba(59,130,246,0.35); }
.bgo-save-in-template.is-dirty::before {
    content: '● ';
    font-size: 10px;
    color: #fbbf24;
}

/* ── 關鍵字 Tab ── */
.bgo-keywords-info {
    margin-bottom: 12px;
    font-size: 13px;
    color: #666;
}
.bgo-keywords-portal-link {
    display: inline-block;
    margin-top: 4px;
    padding: 6px 14px;
    background: #3b82f6;
    color: #fff;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: background 0.2s;
}
.bgo-keywords-portal-link:hover { background: #2563eb; color: #fff; }
.bgo-keyword-preview {
    color: #888;
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 500px;
}
.bgo-keyword-edit-area {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 16px;
    max-width: 820px;
    white-space: pre-wrap;
    font-size: 13px;
    line-height: 1.6;
    color: #333;
    margin-bottom: 12px;
}
</style>

<div id="buygo-templates-page">
    <form method="post" action="" id="bgo-templates-form">
        <?php wp_nonce_field('buygo_settings'); ?>

        <h2 style="margin: 0 0 4px;">LINE 模板</h2>
        <p style="margin: 0 0 12px; font-size: 13px; color: #666;">
            編輯買家、賣家和系統通知的 LINE 模板。可使用變數：<code>{變數名稱}</code>
        </p>

        <!-- 子 Tab 導航 -->
        <div class="bgo-templates-subtabs">
            <a href="#buyer-templates"  class="bgo-templates-subtab active" data-tab="buyer">客戶</a>
            <a href="#seller-templates" class="bgo-templates-subtab"        data-tab="seller">賣家</a>
            <a href="#system-templates" class="bgo-templates-subtab"        data-tab="system">系統</a>
            <a href="#keyword-templates" class="bgo-templates-subtab"       data-tab="keyword">關鍵字</a>
        </div>

        <!-- ════════ 客戶 Tab ════════ -->
        <div id="buyer-templates" class="template-tab-content">
            <table class="bgo-templates-table">
                <thead>
                    <tr><th>模板名稱</th></tr>
                </thead>
                <tbody>
                <?php foreach ($editable_templates['buyer'] as $template_key => $template_info):
                    $template     = $all_templates[$template_key] ?? null;
                    $line_message = $template['line']['message'] ?? '';
                ?>
                    <tr class="bgo-template-row" data-template-key="<?php echo esc_attr($template_key); ?>">
                        <td>
                            <strong><?php echo esc_html($template_info['name']); ?></strong>
                            <span class="bgo-row-arrow">▼</span>
                        </td>
                    </tr>
                    <tr class="template-edit-row" id="template-<?php echo esc_attr($template_key); ?>" style="display:none;">
                        <td>
                            <div style="max-width:820px;">
                                <p style="margin:0 0 10px; color:#666; font-size:13px;"><?php echo esc_html($template_info['description']); ?></p>
                                <label style="display:block; margin-bottom:5px; font-weight:600;">LINE 訊息模板：</label>
                                <textarea
                                    name="templates[<?php echo esc_attr($template_key); ?>][line][message]"
                                    rows="8"
                                    class="large-text code bgo-template-textarea"
                                    style="width:100%; font-family:monospace;"
                                ><?php echo esc_textarea($line_message); ?></textarea>
                                <div class="bgo-vars-save-row">
                                    <?php if (!empty($template_info['variables'])): ?>
                                    <div class="bgo-vars-left">
                                        <label style="font-weight:600;">可用變數（點擊插入）：</label>
                                        <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                            <?php foreach ($template_info['variables'] as $variable): ?>
                                            <div style="display:flex; flex-direction:column; gap:4px; align-items:center;">
                                                <button type="button" onclick="bgoInsertVar(this, '{<?php echo esc_js($variable); ?>}')" class="bgo-var-btn">
                                                    {<?php echo esc_html($variable); ?>}
                                                </button>
                                                <span style="font-size:11px; color:#666;"><?php echo esc_html($variable_descriptions[$variable] ?? $variable); ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <button type="button" class="bgo-save-in-template">儲存模板</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ════════ 賣家 Tab ════════ -->
        <div id="seller-templates" class="template-tab-content" style="display:none;">
            <table class="bgo-templates-table">
                <thead>
                    <tr><th>模板名稱</th></tr>
                </thead>
                <tbody>
                <?php foreach ($editable_templates['seller'] as $template_key => $template_info):
                    $template     = $all_templates[$template_key] ?? null;
                    $line_message = $template['line']['message'] ?? '';
                ?>
                    <tr class="bgo-template-row" data-template-key="<?php echo esc_attr($template_key); ?>">
                        <td>
                            <strong><?php echo esc_html($template_info['name']); ?></strong>
                            <span class="bgo-row-arrow">▼</span>
                        </td>
                    </tr>
                    <tr class="template-edit-row" id="template-<?php echo esc_attr($template_key); ?>" style="display:none;">
                        <td>
                            <div style="max-width:820px;">
                                <p style="margin:0 0 10px; color:#666; font-size:13px;"><?php echo esc_html($template_info['description']); ?></p>
                                <label style="display:block; margin-bottom:5px; font-weight:600;">LINE 訊息模板：</label>
                                <textarea
                                    name="templates[<?php echo esc_attr($template_key); ?>][line][message]"
                                    rows="8"
                                    class="large-text code bgo-template-textarea"
                                    style="width:100%; font-family:monospace;"
                                ><?php echo esc_textarea($line_message); ?></textarea>
                                <div class="bgo-vars-save-row">
                                    <?php if (!empty($template_info['variables'])): ?>
                                    <div class="bgo-vars-left">
                                        <label style="font-weight:600;">可用變數（點擊插入）：</label>
                                        <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                            <?php foreach ($template_info['variables'] as $variable): ?>
                                            <div style="display:flex; flex-direction:column; gap:4px; align-items:center;">
                                                <button type="button" onclick="bgoInsertVar(this, '{<?php echo esc_js($variable); ?>}')" class="bgo-var-btn">
                                                    {<?php echo esc_html($variable); ?>}
                                                </button>
                                                <span style="font-size:11px; color:#666;"><?php echo esc_html($variable_descriptions[$variable] ?? $variable); ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <button type="button" class="bgo-save-in-template">儲存模板</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ════════ 系統 Tab ════════ -->
        <div id="system-templates" class="template-tab-content" style="display:none;">
            <table class="bgo-templates-table">
                <thead>
                    <tr><th>模板名稱</th></tr>
                </thead>
                <tbody>
                <?php foreach ($editable_templates['system'] as $template_key => $template_info):
                    $template      = $all_templates[$template_key] ?? null;
                    $template_type = $template['type'] ?? ($template_info['type'] ?? 'text');
                ?>
                    <tr class="bgo-template-row" data-template-key="<?php echo esc_attr($template_key); ?>">
                        <td>
                            <strong><?php echo esc_html($template_info['name']); ?></strong>
                            <span class="bgo-row-arrow">▼</span>
                        </td>
                    </tr>
                    <tr class="template-edit-row" id="template-<?php echo esc_attr($template_key); ?>" style="display:none;">
                        <td>
                            <div style="max-width:820px;">
                                <p style="margin:0 0 10px; color:#666; font-size:13px;"><?php echo esc_html($template_info['description']); ?></p>
                                <?php if ($template_type === 'flex'): ?>
                                    <?php
                                    $flex_template = $template['line']['flex_template'] ?? [
                                        'logo_url'    => '',
                                        'title'       => '',
                                        'description' => '',
                                        'buttons'     => [
                                            ['label' => '', 'action' => ''],
                                            ['label' => '', 'action' => ''],
                                            ['label' => '', 'action' => ''],
                                        ],
                                    ];
                                    ?>
                                    <input type="hidden" name="templates[<?php echo esc_attr($template_key); ?>][type]" value="flex">

                                    <label style="display:block; margin-bottom:5px; font-weight:600;">Logo URL：</label>
                                    <input type="text"
                                        name="templates[<?php echo esc_attr($template_key); ?>][line][flex_template][logo_url]"
                                        value="<?php echo esc_attr($flex_template['logo_url'] ?? ''); ?>"
                                        class="large-text bgo-template-textarea"
                                        style="width:100%;"
                                        placeholder="https://example.com/logo.png" />

                                    <label style="display:block; margin-top:14px; margin-bottom:5px; font-weight:600;">標題文字：</label>
                                    <input type="text"
                                        name="templates[<?php echo esc_attr($template_key); ?>][line][flex_template][title]"
                                        value="<?php echo esc_attr($flex_template['title'] ?? ''); ?>"
                                        class="large-text bgo-template-textarea"
                                        style="width:100%;" />

                                    <label style="display:block; margin-top:14px; margin-bottom:5px; font-weight:600;">說明文字：</label>
                                    <textarea
                                        name="templates[<?php echo esc_attr($template_key); ?>][line][flex_template][description]"
                                        rows="3"
                                        class="large-text bgo-template-textarea"
                                        style="width:100%;"
                                    ><?php echo esc_textarea($flex_template['description'] ?? ''); ?></textarea>

                                    <h5 style="margin-top:18px; margin-bottom:10px;">按鈕設定：</h5>
                                    <?php
                                    $buttons = $flex_template['buttons'] ?? [
                                        ['label' => '', 'action' => ''],
                                        ['label' => '', 'action' => ''],
                                        ['label' => '', 'action' => ''],
                                    ];
                                    for ($i = 0; $i < 3; $i++):
                                        $btn = $buttons[$i] ?? ['label' => '', 'action' => ''];
                                    ?>
                                    <div style="margin-bottom:12px; padding:10px; background:#fff; border:1px solid #ccc; border-radius:4px;">
                                        <strong>按鈕 <?php echo $i + 1; ?>：</strong>
                                        <label style="display:block; margin-top:6px;">
                                            文字：
                                            <input type="text"
                                                name="templates[<?php echo esc_attr($template_key); ?>][line][flex_template][buttons][<?php echo $i; ?>][label]"
                                                value="<?php echo esc_attr($btn['label'] ?? ''); ?>"
                                                class="bgo-template-textarea"
                                                style="width:200px; margin-left:5px;" />
                                        </label>
                                        <label style="display:block; margin-top:5px;">
                                            關鍵字：
                                            <input type="text"
                                                name="templates[<?php echo esc_attr($template_key); ?>][line][flex_template][buttons][<?php echo $i; ?>][action]"
                                                value="<?php echo esc_attr($btn['action'] ?? ''); ?>"
                                                placeholder="/one"
                                                class="bgo-template-textarea"
                                                style="width:200px; margin-left:5px;" />
                                        </label>
                                    </div>
                                    <?php endfor; ?>
                                    <div class="bgo-vars-save-row" style="justify-content:flex-end;">
                                        <button type="button" class="bgo-save-in-template">儲存模板</button>
                                    </div>

                                <?php else:
                                    $line_message = $template['line']['message'] ?? '';
                                ?>
                                    <label style="display:block; margin-bottom:5px; font-weight:600;">LINE 訊息模板：</label>
                                    <textarea
                                        name="templates[<?php echo esc_attr($template_key); ?>][line][message]"
                                        rows="8"
                                        class="large-text code bgo-template-textarea"
                                        style="width:100%; font-family:monospace;"
                                    ><?php echo esc_textarea($line_message); ?></textarea>
                                    <div class="bgo-vars-save-row">
                                        <?php if (!empty($template_info['variables'])): ?>
                                        <div class="bgo-vars-left">
                                            <label style="font-weight:600;">可用變數（點擊插入）：</label>
                                            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                                <?php foreach ($template_info['variables'] as $variable): ?>
                                                <div style="display:flex; flex-direction:column; gap:4px; align-items:center;">
                                                    <button type="button" onclick="bgoInsertVar(this, '{<?php echo esc_js($variable); ?>}')" class="bgo-var-btn">
                                                        {<?php echo esc_html($variable); ?>}
                                                    </button>
                                                    <span style="font-size:11px; color:#666;"><?php echo esc_html($variable_descriptions[$variable] ?? $variable); ?></span>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <button type="button" class="bgo-save-in-template">儲存模板</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ════════ 關鍵字 Tab ════════ -->
        <div id="keyword-templates" class="template-tab-content" style="display:none;">
            <p class="bgo-keywords-info">
                關鍵字回覆訊息的新增、編輯、刪除請至前端 Portal 操作。
                <br>
                <a href="<?php echo esc_url(home_url('/buygo-portal/settings')); ?>" target="_blank" class="bgo-keywords-portal-link">
                    前往前端編輯 →
                </a>
            </p>

            <?php if (empty($keywords)): ?>
                <p style="color:#888; font-size:13px;">尚無關鍵字。</p>
            <?php else: ?>
                <table class="bgo-templates-table">
                    <thead>
                        <tr><th>關鍵字</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($keywords as $kw):
                        $kw_id      = esc_attr($kw['id'] ?? uniqid());
                        $kw_keyword = $kw['keyword'] ?? '';
                        $kw_aliases = implode(', ', $kw['aliases'] ?? []);
                        $kw_message = $kw['message'] ?? '';
                        $preview    = mb_substr($kw_message, 0, 60) . (mb_strlen($kw_message) > 60 ? '…' : '');
                    ?>
                        <tr class="bgo-template-row" data-template-key="kw-<?php echo $kw_id; ?>">
                            <td>
                                <strong><?php echo esc_html($kw_keyword); ?></strong>
                                <?php if ($kw_aliases): ?>
                                    <span style="color:#aaa; font-size:12px; margin-left:8px;"><?php echo esc_html($kw_aliases); ?></span>
                                <?php endif; ?>
                                <span class="bgo-row-arrow">▼</span>
                            </td>
                        </tr>
                        <tr class="template-edit-row" id="template-kw-<?php echo $kw_id; ?>" style="display:none;">
                            <td>
                                <div style="max-width:820px;">
                                    <p style="margin:0 0 10px; color:#666; font-size:13px;">
                                        關鍵字：<strong><?php echo esc_html($kw_keyword); ?></strong>
                                        <?php if ($kw_aliases): ?>
                                            ／別名：<?php echo esc_html($kw_aliases); ?>
                                        <?php endif; ?>
                                    </p>
                                    <label style="display:block; margin-bottom:6px; font-weight:600;">回覆訊息內容：</label>
                                    <div class="bgo-keyword-edit-area"><?php echo esc_html($kw_message); ?></div>
                                    <a href="<?php echo esc_url(home_url('/buygo-portal/settings')); ?>" target="_blank" class="bgo-keywords-portal-link">
                                        前往前端編輯 →
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </form><!-- /form -->
</div><!-- /#buygo-templates-page -->

<script>
(function($) {
    'use strict';

    /* ── 子 Tab 切換 ── */
    var $subtabs = $('#buygo-templates-page .bgo-templates-subtab');
    var $contents = $('#buygo-templates-page .template-tab-content');

    $subtabs.on('click', function(e) {
        e.preventDefault();

        // 未儲存警告
        if (bgoIsDirty && !confirm('模板還未存檔，確定要切換嗎？')) {
            return;
        }

        var tab = $(this).data('tab');
        $subtabs.removeClass('active');
        $(this).addClass('active');
        $contents.hide();
        $('#' + tab + '-templates').show();
    });

    /* ── 點擊整行展開/收合 ── */
    $(document).on('click', '#buygo-templates-page .bgo-template-row', function() {
        var key       = $(this).data('template-key');
        var $editRow  = $('#template-' + key);
        var isOpen    = $editRow.is(':visible');

        // 關閉其他展開的列（同一個 Tab 內只開一個）
        $(this).closest('table').find('.template-edit-row:visible').slideUp(150);
        $(this).closest('table').find('.bgo-template-row.is-open').removeClass('is-open');

        if (!isOpen) {
            $(this).addClass('is-open');
            $editRow.slideDown(200);
        }
    });

    // 點擊編輯區外部收合
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.bgo-template-row, .template-edit-row').length) {
            $('#buygo-templates-page .template-edit-row:visible').slideUp(150);
            $('#buygo-templates-page .bgo-template-row.is-open').removeClass('is-open');
        }
    });

    /* ── 未儲存偵測 ── */
    var bgoIsDirty = false;

    $(document).on('input change', '#bgo-templates-form .bgo-template-textarea', function() {
        bgoIsDirty = true;
        $('.bgo-save-in-template').addClass('is-dirty');
    });

    // 離開頁面警告
    $(window).on('beforeunload', function() {
        if (bgoIsDirty) {
            return '模板還未存檔，確定要離開嗎？';
        }
    });

    /* ── 模板內儲存按鈕 ── */
    $(document).on('click', '.bgo-save-in-template', function() {
        bgoIsDirty = false;
        $('.bgo-save-in-template').removeClass('is-dirty');
        $(window).off('beforeunload');
        $('<input type="hidden" name="submit_templates" value="1">').appendTo('#bgo-templates-form');
        $('#bgo-templates-form').submit();
    });

})(jQuery);

/* ── 插入變數到最近的 textarea ── */
function bgoInsertVar(btn, text) {
    var textarea = btn.closest('tr.template-edit-row').querySelector('textarea.bgo-template-textarea');
    if (!textarea) return;
    var start = textarea.selectionStart;
    var end   = textarea.selectionEnd;
    textarea.value = textarea.value.substring(0, start) + text + textarea.value.substring(end);
    var pos = start + text.length;
    textarea.setSelectionRange(pos, pos);
    textarea.focus();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}
</script>
