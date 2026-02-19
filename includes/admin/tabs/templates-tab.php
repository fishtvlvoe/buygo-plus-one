<?php
/**
 * 模板管理 Tab
 *
 * 從 class-settings-page.php 拆分出來的模板編輯頁面
 * 包含買家、賣家、系統通知模板的編輯介面
 */
if (!defined('ABSPATH')) {
    exit;
}

// 取得所有模板
$all_templates = \BuyGoPlus\Services\NotificationTemplates::get_all_templates();

// 變數說明對應表
$variable_descriptions = [
    'order_id' => '訂單編號',
    'total' => '訂單總金額',
    'note' => '備註說明',
    'product_name' => '商品名稱',
    'quantity' => '數量',
    'buyer_name' => '買家名稱',
    'order_total' => '訂單總額',
    'order_url' => '訂單連結',
    'error_message' => '錯誤訊息',
    'product_url' => '商品連結',
    'price' => '價格',
    'currency_symbol' => '貨幣符號',
    'original_price_section' => '原價區塊',
    'category_section' => '分類區塊',
    'arrival_date_section' => '到貨日期區塊',
    'preorder_date_section' => '預購日期區塊',
    'community_url_section' => '社群連結區塊',
    'missing_fields' => '缺少欄位',
    'display_name' => '使用者名稱',
    'purchase_url' => '購買連結',
    'product_limit' => '商品配額數量',
    'dashboard_url' => '後台管理連結'
];

// 定義可編輯的模板（按照新的分類）
$editable_templates = [
    'buyer' => [
        'order_created' => [
            'name' => '訂單已建立',
            'description' => '訂單建立時（完整或拆分）發送給客戶',
            'variables' => ['order_id', 'total']
        ],
        'order_cancelled' => [
            'name' => '訂單已取消',
            'description' => '訂單取消時（僅客戶自行取消）發送給客戶',
            'variables' => ['order_id', 'note']
        ],
        'plusone_order_confirmation' => [
            'name' => '訂單確認',
            'description' => '訂單確認（留言回覆）發送給買家',
            'variables' => ['product_name', 'quantity', 'total']
        ]
    ],
    'seller' => [
        'seller_order_created' => [
            'name' => '新訂單通知',
            'description' => '有人下訂單時發送給賣家',
            'variables' => ['order_id', 'buyer_name', 'order_total', 'order_url']
        ],
        'seller_order_cancelled' => [
            'name' => '訂單已取消',
            'description' => '訂單取消時發送給賣家',
            'variables' => ['order_id', 'buyer_name', 'note', 'order_url']
        ],
        'helper_product_created' => [
            'name' => '小幫手上架通知',
            'description' => '商品上架時發送給非上架者（賣家上架→通知小幫手，小幫手上架→通知賣家）',
            'variables' => ['product_name', 'price', 'quantity', 'product_url', 'currency_symbol', 'original_price_section', 'category_section', 'arrival_date_section', 'preorder_date_section']
        ]
    ],
    'system' => [
        'system_line_follow' => [
            'name' => '加入好友通知',
            'description' => '加入好友時發送（含第一則通知）',
            'variables' => []
        ],
        'flex_image_upload_menu' => [
            'name' => '圖片上傳成功（卡片式訊息）',
            'description' => '圖片上傳成功後發送的卡片式訊息',
            'type' => 'flex',
            'variables' => []
        ],
        'system_image_upload_failed' => [
            'name' => '圖片上傳失敗',
            'description' => '圖片上傳失敗時發送',
            'variables' => ['error_message']
        ],
        'system_product_published' => [
            'name' => '商品上架成功',
            'description' => '商品上架成功時發送',
            'variables' => ['product_name', 'price', 'quantity', 'product_url', 'currency_symbol', 'original_price_section', 'category_section', 'arrival_date_section', 'preorder_date_section']
        ],
        'system_product_publish_failed' => [
            'name' => '商品上架失敗',
            'description' => '商品上架失敗時發送',
            'variables' => ['error_message']
        ],
        'system_product_data_incomplete' => [
            'name' => '商品資料不完整',
            'description' => '商品資料不完整時發送',
            'variables' => ['missing_fields']
        ],
        'system_keyword_reply' => [
            'name' => '關鍵字回覆訊息',
            'description' => '關鍵字回覆訊息',
            'variables' => []
        ],
        'system_permission_denied' => [
            'name' => '權限不足通知',
            'description' => '非賣家用戶嘗試上架商品時發送',
            'variables' => ['display_name', 'purchase_url']
        ],
        'system_seller_grant_line' => [
            'name' => '成為賣家 LINE 通知',
            'description' => '購買賣家資格後發送的 LINE 恭喜訊息',
            'variables' => ['display_name', 'product_limit', 'dashboard_url']
        ],
        'system_seller_grant_email' => [
            'name' => '成為賣家 Email 通知',
            'description' => '購買賣家資格後發送的 Email 恭喜訊息',
            'variables' => ['display_name', 'product_limit', 'dashboard_url']
        ]
    ]
];

?>
<div id="buygo-templates-page">
    <form method="post" action="">
        <?php wp_nonce_field('buygo_settings'); ?>

        <h2>Line 模板</h2>
        <p class="description">
            編輯買家、賣家和系統通知的 LINE 模板。可使用變數：<code>{變數名稱}</code>
        </p>

        <!-- Tab 切換 -->
        <div class="nav-tab-wrapper" style="margin-top: 20px; border-bottom: 1px solid #ccc;">
            <a href="#buyer-templates" class="nav-tab nav-tab-active" onclick="return false;" data-tab="buyer" style="cursor: pointer;">客戶</a>
            <a href="#seller-templates" class="nav-tab" onclick="return false;" data-tab="seller" style="cursor: pointer;">賣家</a>
            <a href="#system-templates" class="nav-tab" onclick="return false;" data-tab="system" style="cursor: pointer;">系統</a>
        </div>

    <!-- 買家通知 -->
    <div id="buyer-templates" class="template-tab-content" style="margin-top: 20px;">
        <h3>客戶通知</h3>

        <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
            <thead>
                <tr>
                    <th style="width: 30%;">模板名稱</th>
                    <th style="width: 50%;">說明</th>
                    <th style="width: 20%;">操作</th>
                </tr>
            </thead>
            <tbody>
        <?php foreach ($editable_templates['buyer'] as $template_key => $template_info): ?>
            <?php
            $template = $all_templates[$template_key] ?? null;
            $line_message = $template['line']['message'] ?? '';
            ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($template_info['name']); ?></strong>
                </td>
                <td>
                    <span class="description"><?php echo esc_html($template_info['description']); ?></span>
                </td>
                <td>
                    <button type="button" class="button button-small toggle-template-btn" data-template-key="<?php echo esc_attr($template_key); ?>" style="width: 100%;">
                        <span class="toggle-arrow" style="display: inline-block; transition: transform 0.2s;">▼</span>
                        <span class="toggle-text">展開</span>
                    </button>
                </td>
            </tr>
            <tr class="template-edit-row" id="template-<?php echo esc_attr($template_key); ?>" style="display: none;">
                <td colspan="3" style="padding: 20px; background: #f9f9f9;">
                    <div style="max-width: 800px; margin: 0 auto;">
                    <p class="description" style="margin-top: 0; margin-bottom: 15px; color: #666;">
                        <?php echo esc_html($template_info['description']); ?>
                    </p>

                    <label for="template_<?php echo esc_attr($template_key); ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                        LINE 訊息模板：
                    </label>
                    <textarea
                        id="template_<?php echo esc_attr($template_key); ?>"
                        name="templates[<?php echo esc_attr($template_key); ?>][line][message]"
                        rows="8"
                        class="large-text code"
                        style="width: 100%; font-family: monospace;"
                    ><?php echo esc_textarea($line_message); ?></textarea>

                    <?php if (!empty($template_info['variables'])): ?>
                    <div style="margin-top: 15px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">可用變數（點擊複製）：</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <?php foreach ($template_info['variables'] as $variable): ?>
                            <div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">
                                <button
                                    type="button"
                                    onclick="copyToClipboard('{<?php echo esc_js($variable); ?>}')"
                                    class="button button-small"
                                    style="cursor: pointer; font-family: monospace; font-size: 12px; padding: 6px 12px;">
                                    { <?php echo esc_html($variable); ?> }
                                </button>
                                <span class="description" style="font-size: 11px; color: #666;">
                                    <?php echo esc_html($variable_descriptions[$variable] ?? $variable); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 賣家通知 -->
    <div id="seller-templates" class="template-tab-content" style="margin-top: 20px; display: none;">
        <h3>賣家通知</h3>

        <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
            <thead>
                <tr>
                    <th style="width: 30%;">模板名稱</th>
                    <th style="width: 50%;">說明</th>
                    <th style="width: 20%;">操作</th>
                </tr>
            </thead>
            <tbody>
        <?php foreach ($editable_templates['seller'] as $template_key => $template_info): ?>
            <?php
            $template = $all_templates[$template_key] ?? null;
            $line_message = $template['line']['message'] ?? '';
            ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($template_info['name']); ?></strong>
                </td>
                <td>
                    <span class="description"><?php echo esc_html($template_info['description']); ?></span>
                </td>
                <td>
                    <button type="button" class="button button-small toggle-template-btn" data-template-key="<?php echo esc_attr($template_key); ?>" style="width: 100%;">
                        <span class="toggle-arrow" style="display: inline-block; transition: transform 0.2s;">▼</span>
                        <span class="toggle-text">展開</span>
                    </button>
                </td>
            </tr>
            <tr class="template-edit-row" id="template-<?php echo esc_attr($template_key); ?>" style="display: none;">
                <td colspan="3" style="padding: 20px; background: #f9f9f9;">
                    <div style="max-width: 800px; margin: 0 auto;">
                    <p class="description" style="margin-top: 0; margin-bottom: 15px; color: #666;">
                        <?php echo esc_html($template_info['description']); ?>
                    </p>

                        <label for="template_<?php echo esc_attr($template_key); ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            LINE 訊息模板：
                        </label>
                        <textarea
                            id="template_<?php echo esc_attr($template_key); ?>"
                            name="templates[<?php echo esc_attr($template_key); ?>][line][message]"
                            rows="8"
                            class="large-text code"
                            style="width: 100%; font-family: monospace;"
                        ><?php echo esc_textarea($line_message); ?></textarea>

                        <?php if (!empty($template_info['variables'])): ?>
                        <div style="margin-top: 15px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">可用變數（點擊複製）：</label>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                <?php foreach ($template_info['variables'] as $variable): ?>
                                <div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">
                                    <button
                                        type="button"
                                        onclick="copyToClipboard('{<?php echo esc_js($variable); ?>}')"
                                        class="button button-small"
                                        style="cursor: pointer; font-family: monospace; font-size: 12px; padding: 6px 12px;">
                                        { <?php echo esc_html($variable); ?> }
                                    </button>
                                    <span class="description" style="font-size: 11px; color: #666;">
                                        <?php echo esc_html($variable_descriptions[$variable] ?? $variable); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 系統通知 -->
    <div id="system-templates" class="template-tab-content" style="margin-top: 20px; display: none; max-width: 1000px; margin-left: auto; margin-right: auto;">
        <h3>系統通知</h3>

        <table class="wp-list-table widefat fixed striped" style="margin-top: 10px; max-width: 100%;">
            <thead>
                <tr>
                    <th style="width: 30%;">模板名稱</th>
                    <th style="width: 50%;">說明</th>
                    <th style="width: 20%;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // 過濾掉關鍵字回覆
                $system_notification_templates = array_filter($editable_templates['system'], function($key) {
                    return $key !== 'system_keyword_reply';
                }, ARRAY_FILTER_USE_KEY);

                foreach ($system_notification_templates as $template_key => $template_info):
                    $template = $all_templates[$template_key] ?? null;
                    $template_type = $template['type'] ?? 'text';
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($template_info['name']); ?></strong>
                        </td>
                        <td>
                            <span class="description"><?php echo esc_html($template_info['description']); ?></span>
                        </td>
                        <td>
                            <button type="button" class="button button-small toggle-template-btn" data-template-key="<?php echo esc_attr($template_key); ?>" style="width: 100%;">
                                <span class="toggle-arrow" style="display: inline-block; transition: transform 0.2s;">▼</span>
                                <span class="toggle-text">展開</span>
                            </button>
                        </td>
                    </tr>
                    <tr class="template-edit-row" id="template-<?php echo esc_attr($template_key); ?>" style="display: none;">
                        <td colspan="3" style="padding: 20px; background: #f9f9f9;">
                            <div style="max-width: 800px; margin: 0 auto;">
                    <?php
                    // 檢查是否為卡片式訊息
                    if (($template_info['type'] ?? 'text') === 'flex' || $template_type === 'flex') {
                        $flex_template = $template['line']['flex_template'] ?? [
                            'logo_url' => '',
                            'title' => '',
                            'description' => '',
                            'buttons' => [
                                ['label' => '', 'action' => ''],
                                ['label' => '', 'action' => ''],
                                ['label' => '', 'action' => '']
                            ]
                        ];
                        ?>
                        <!-- 卡片式訊息編輯器 -->
                                <p class="description" style="margin-top: 0; margin-bottom: 15px; color: #666;">
                                    <?php echo esc_html($template_info['description']); ?>
                                </p>

                                <input type="hidden" name="templates[<?php echo esc_attr($template_key); ?>][type]" value="flex">

                                <label for="flex_logo_<?php echo esc_attr($template_key); ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                    Logo URL：
                                </label>
                                <input
                                    type="text"
                                    id="flex_logo_<?php echo esc_attr($template_key); ?>"
                                    name="templates[<?php echo esc_attr($template_key); ?>][line][flex_template][logo_url]"
                                    value="<?php echo esc_attr($flex_template['logo_url'] ?? ''); ?>"
                                    class="large-text"
                                    style="width: 100%;"
                                    placeholder="https://example.com/logo.png"
                                />

                                <label for="flex_title_<?php echo esc_attr($template_key); ?>" style="display: block; margin-top: 15px; margin-bottom: 5px; font-weight: 600;">
                                    標題文字：
                                </label>
                                <input
                                    type="text"
                                    id="flex_title_<?php echo esc_attr($template_key); ?>"
                                    name="templates[<?php echo esc_attr($template_key); ?>][line][flex_template][title]"
                                    value="<?php echo esc_attr($flex_template['title'] ?? ''); ?>"
                                    class="large-text"
                                    style="width: 100%;"
                                />

                                <label for="flex_description_<?php echo esc_attr($template_key); ?>" style="display: block; margin-top: 15px; margin-bottom: 5px; font-weight: 600;">
                                    說明文字：
                                </label>
                                <textarea
                                    id="flex_description_<?php echo esc_attr($template_key); ?>"
                                    name="templates[<?php echo esc_attr($template_key); ?>][line][flex_template][description]"
                                    rows="3"
                                    class="large-text"
                                    style="width: 100%;"
                                ><?php echo esc_textarea($flex_template['description'] ?? ''); ?></textarea>

                                <h5 style="margin-top: 20px; margin-bottom: 10px;">按鈕設定：</h5>
                                <?php
                                $buttons = $flex_template['buttons'] ?? [
                                    ['label' => '', 'action' => ''],
                                    ['label' => '', 'action' => ''],
                                    ['label' => '', 'action' => '']
                                ];
                                for ($i = 0; $i < 3; $i++):
                                    $button = $buttons[$i] ?? ['label' => '', 'action' => ''];
                                ?>
                                <div style="margin-bottom: 15px; padding: 10px; background: #fff; border: 1px solid #ccc; border-radius: 4px;">
                                    <strong>按鈕 <?php echo $i + 1; ?>：</strong>
                                    <label style="display: block; margin-top: 5px;">
                                        文字：
                                        <input
                                            type="text"
                                            name="templates[<?php echo esc_attr($template_key); ?>][line][flex_template][buttons][<?php echo $i; ?>][label]"
                                            value="<?php echo esc_attr($button['label'] ?? ''); ?>"
                                            style="width: 200px; margin-left: 5px;"
                                        />
                                    </label>
                                    <label style="display: block; margin-top: 5px;">
                                        關鍵字：
                                        <input
                                            type="text"
                                            name="templates[<?php echo esc_attr($template_key); ?>][line][flex_template][buttons][<?php echo $i; ?>][action]"
                                            value="<?php echo esc_attr($button['action'] ?? ''); ?>"
                                            style="width: 200px; margin-left: 5px;"
                                            placeholder="/one"
                                        />
                                    </label>
                                </div>
                                <?php endfor; ?>
                    <?php
                    } else {
                        // 一般文字模板
                        $line_message = $template['line']['message'] ?? '';
                        ?>
                        <p class="description" style="margin-top: 0; margin-bottom: 15px; color: #666;">
                            <?php echo esc_html($template_info['description']); ?>
                        </p>

                        <label for="template_<?php echo esc_attr($template_key); ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            LINE 訊息模板：
                        </label>
                        <textarea
                            id="template_<?php echo esc_attr($template_key); ?>"
                            name="templates[<?php echo esc_attr($template_key); ?>][line][message]"
                            rows="8"
                            class="large-text code"
                            style="width: 100%; font-family: monospace;"
                        ><?php echo esc_textarea($line_message); ?></textarea>

                        <?php if (!empty($template_info['variables'])): ?>
                        <div style="margin-top: 15px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">可用變數（點擊複製）：</label>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                <?php foreach ($template_info['variables'] as $variable): ?>
                                <div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">
                                    <button
                                        type="button"
                                        onclick="copyToClipboard('{<?php echo esc_js($variable); ?>}')"
                                        class="button button-small"
                                        style="cursor: pointer; font-family: monospace; font-size: 12px; padding: 6px 12px;">
                                        { <?php echo esc_html($variable); ?> }
                                    </button>
                                    <span class="description" style="font-size: 11px; color: #666;">
                                        <?php echo esc_html($variable_descriptions[$variable] ?? $variable); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php
                    }
                    ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- 關鍵字訊息區塊 -->
        <h3 style="margin-top: 30px;">關鍵字訊息</h3>
        <?php
        // 取得關鍵字列表
        $keywords = get_option('buygo_line_keywords', []);

        // 如果沒有關鍵字，提供預設的 /help 關鍵字
        if (empty($keywords)) {
            $keywords = [
                [
                    'id' => 'help',
                    'keyword' => '/help',
                    'aliases' => ['/幫助', '?help', '幫助'],
                    'message' => "📱 商品上架說明\n\n【步驟】\n1️⃣ 發送商品圖片\n2️⃣ 發送商品資訊\n\n【必填欄位】\n商品名稱\n價格：350\n數量：20\n\n【選填欄位】\n原價：500\n分類：服飾\n到貨：01/25\n預購：01/20\n描述：商品描述\n\n【範例】\n冬季外套\n價格：1200\n原價：1800\n數量：15\n分類：服飾\n到貨：01/15\n\n💡 輸入 /分類 查看可用分類",
                    'order' => 0
                ]
            ];
        }

        // 按照 order 排序
        usort($keywords, function($a, $b) {
            return ($a['order'] ?? 0) - ($b['order'] ?? 0);
        });
        ?>
        <div class="postbox closed" style="margin-bottom: 20px; max-width: 1000px; margin-left: auto; margin-right: auto;">
            <button type="button" class="handlediv" aria-expanded="false" onclick="jQuery(this).parent().toggleClass('closed'); jQuery(this).attr('aria-expanded', jQuery(this).parent().hasClass('closed') ? 'false' : 'true'); jQuery(this).siblings('.inside').toggle();">
                <span class="toggle-indicator" aria-hidden="true"></span>
            </button>
            <h3 class="hndle" style="padding: 12px 15px; margin: 0; cursor: pointer;">
                <span>關鍵字訊息</span>
            </h3>
            <div class="inside" style="padding: 15px; display: none;">
                <?php if (empty($keywords)): ?>
                    <p class="description">尚無關鍵字，請使用前端 Portal 新增關鍵字。</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped" style="margin-top: 10px; max-width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 15%;">關鍵字</th>
                                <th style="width: 25%;">別名</th>
                                <th style="width: 45%;">回覆訊息預覽</th>
                                <th style="width: 15%;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($keywords as $keyword): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($keyword['keyword'] ?? ''); ?></strong>
                                </td>
                                <td>
                                    <span class="description">
                                        <?php echo esc_html(implode(', ', $keyword['aliases'] ?? [])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="description" style="display: block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo esc_html(mb_substr($keyword['message'] ?? '', 0, 50)); ?>...
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo home_url('/buygo-portal/settings'); ?>" target="_blank" class="button button-small">
                                        前往前端編輯
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="description" style="margin-top: 15px;">
                        💡 提示：關鍵字的新增、編輯、刪除功能請使用前端 Portal 的「Line 模板」頁面進行管理。
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php
        // 舊的系統通知循環（需要刪除）
        // foreach ($editable_templates['system'] as $template_key => $template_info): ?>
    </div>

    <p class="submit">
        <input type="submit" name="submit_templates" class="button-primary" value="儲存模板" />
    </p>
</form>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab 切換功能（僅限於模板管理頁面）
    $('#buygo-templates-page .nav-tab').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');

        // 更新 Tab 樣式
        $('#buygo-templates-page .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // 顯示對應的內容
        $('#buygo-templates-page .template-tab-content').hide();
        var $targetTab = $('#buygo-templates-page #' + tab + '-templates');

        if ($targetTab.length) {
            $targetTab.show();
        } else {
            console.error('找不到 Tab 內容: #' + tab + '-templates');
        }
    });

    // 表格展開/收合功能
    $('#buygo-templates-page .toggle-template-btn').on('click', function() {
        var $btn = $(this);
        var templateKey = $btn.data('template-key');
        var $row = $('#template-' + templateKey);
        var $arrow = $btn.find('.toggle-arrow');
        var $text = $btn.find('.toggle-text');

        if ($row.is(':visible')) {
            // 收合
            $row.slideUp(200);
            $arrow.css('transform', 'rotate(0deg)');
            $text.text('展開');
        } else {
            // 展開
            $row.slideDown(200);
            $arrow.css('transform', 'rotate(180deg)');
            $text.text('收合');
        }
    });

    // WordPress 內建的 postbox 折疊功能（僅限於模板管理頁面的系統通知區塊）
    $('#buygo-templates-page .postbox .handlediv').on('click', function() {
        $(this).parent().toggleClass('closed');
        var isClosed = $(this).parent().hasClass('closed');
        $(this).attr('aria-expanded', isClosed ? 'false' : 'true');
        $(this).siblings('.inside').toggle();
    });

    // 讓 h3.hndle 也可以點擊展開/收合（僅限於模板管理頁面的系統通知區塊）
    $('#buygo-templates-page .postbox .hndle').on('click', function() {
        var $postbox = $(this).closest('.postbox');
        var $handlediv = $postbox.find('.handlediv');
        $postbox.toggleClass('closed');
        var isClosed = $postbox.hasClass('closed');
        $handlediv.attr('aria-expanded', isClosed ? 'false' : 'true');
        $postbox.find('.inside').toggle();
    });
});

// 插入變數到 textarea（直接插入，不複製到剪貼簿）
function copyToClipboard(text) {
    // 找到當前焦點的 textarea（應該是在同一個模板編輯區域內）
    const activeElement = document.activeElement;
    let targetTextarea = null;

    // 如果當前焦點是 textarea，直接使用
    if (activeElement && activeElement.tagName === 'TEXTAREA' && activeElement.name && activeElement.name.includes('[line][message]')) {
        targetTextarea = activeElement;
    } else {
        // 否則，找到最近的 textarea（在同一個模板編輯區域內）
        const templateRow = activeElement?.closest('tr.template-edit-row');
        if (templateRow) {
            targetTextarea = templateRow.querySelector('textarea[name*="[line][message]"]');
        }
    }

    // 如果找到 textarea，直接插入
    if (targetTextarea) {
        const start = targetTextarea.selectionStart || targetTextarea.value.length;
        const end = targetTextarea.selectionEnd || targetTextarea.value.length;
        const currentValue = targetTextarea.value;
        const textBefore = currentValue.substring(0, start);
        const textAfter = currentValue.substring(end);

        targetTextarea.value = textBefore + text + textAfter;

        // 設定游標位置
        const newPos = start + text.length;
        targetTextarea.setSelectionRange(newPos, newPos);
        targetTextarea.focus();

        // 觸發 input 事件，確保表單驗證等機制能正常工作
        targetTextarea.dispatchEvent(new Event('input', { bubbles: true }));

        // 顯示提示（可選）
        if (typeof showToast === 'function') {
            showToast('已插入：' + text);
        }
        return;
    }

    // 備用方案：如果找不到 textarea，則複製到剪貼簿
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            alert('已複製：' + text);
        }).catch(function(err) {
            console.error('複製失敗:', err);
            fallbackCopyToClipboard(text);
        });
    } else {
        fallbackCopyToClipboard(text);
    }
}

// 備用複製方法（舊瀏覽器）
function fallbackCopyToClipboard(text) {
    var textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        document.execCommand('copy');
        alert('已複製：' + text);
    } catch (err) {
        console.error('複製失敗:', err);
        alert('複製失敗，請手動複製：' + text);
    }
    document.body.removeChild(textArea);
}
</script>
