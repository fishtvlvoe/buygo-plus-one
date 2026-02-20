<?php if (!defined('ABSPATH')) { exit; }

        global $wpdb;

        // v2.0: 只查詢有 BGO 角色的使用者（不含純 WP Admin）
        $buygo_admins = get_users(['role' => 'buygo_admin']);
        $buygo_helpers = get_users(['role' => 'buygo_helper']);

        // 去重合併
        $unique_users = [];
        foreach (array_merge($buygo_admins, $buygo_helpers) as $user) {
            if (!isset($unique_users[$user->ID])) {
                $unique_users[$user->ID] = $user;
            }
        }

        // 建立所有使用者的列表
        $all_users = [];

        foreach ($unique_users as $user) {
            $line_id = \BuyGoPlus\Services\SettingsService::get_user_line_id($user->ID);

            // 判斷角色
            $is_wp_admin = in_array('administrator', (array) $user->roles);
            $has_buygo_admin_role = in_array('buygo_admin', (array) $user->roles);
            $has_buygo_helper_role = in_array('buygo_helper', (array) $user->roles);

            if ($has_buygo_admin_role) {
                $role = '賣家';
            } elseif ($has_buygo_helper_role) {
                $role = '小幫手';
            } else {
                continue;
            }

            // 商品限制邏輯：
            // - 預設值：3 個商品
            // - 0 = 無限制
            // - 所有賣家都可以編輯此欄位
            $product_limit = get_user_meta($user->ID, 'buygo_product_limit', true);
            if ($product_limit === '' || $product_limit === false) {
                $product_limit = 3; // 預設為 3 個商品（根據用戶反饋調整）
            }
            // 注意：0 值不會被視為空值，代表無限制

            // 取得綁定關係和 BuyGo ID
            global $wpdb;
            $helpers_table = $wpdb->prefix . 'buygo_helpers';
            $binding_info = '';
            $buygo_id = null;

            if ($has_buygo_helper_role) {
                // 小幫手：查詢綁定的賣家和 BuyGo ID
                $helper_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT h.id as buygo_id, s.ID as seller_wp_id, s.display_name as seller_name
                     FROM {$helpers_table} h
                     JOIN {$wpdb->users} s ON h.seller_id = s.ID
                     WHERE h.helper_id = %d
                     LIMIT 1",
                    $user->ID
                ));
                if ($helper_data) {
                    $buygo_id = $helper_data->buygo_id;
                    $binding_info = '綁定賣家：' . $helper_data->seller_name;
                } else {
                    $binding_info = '<span style="color: #d63638;">未綁定賣家</span>';
                }
            } elseif ($has_buygo_admin_role) {
                // 賣家：查詢小幫手數量和列表
                $helper_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$helpers_table} WHERE seller_id = %d",
                    $user->ID
                ));
                $helper_list_html = '';
                if ($helper_count > 0) {
                    $helpers = $wpdb->get_results($wpdb->prepare(
                        "SELECT h.helper_id, u.display_name FROM {$helpers_table} h JOIN {$wpdb->users} u ON h.helper_id = u.ID WHERE h.seller_id = %d",
                        $user->ID
                    ));
                    $names = array_map(function($h) { return esc_html($h->display_name); }, $helpers);
                    $helper_list_html = '<br><small style="color:#666;">' . implode('、', $names) . '</small>';
                }
                $binding_info = '<a href="#" class="add-helper-link" data-seller-id="' . esc_attr($user->ID) . '" data-seller-name="' . esc_attr($user->display_name) . '" style="text-decoration:none;">'
                    . ($helper_count > 0 ? "小幫手數量：{$helper_count} 個" : '無小幫手')
                    . ' <span style="font-size:11px;">✏️</span></a>'
                    . $helper_list_html;
            }

            // 取得頭像
            $avatar_url = get_user_meta($user->ID, 'fc_customer_photo_url', true);
            if (empty($avatar_url)) {
                $avatar_url = get_avatar_url($user->user_email, ['size' => 64]);
            }

            $all_users[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'role' => $role,
                'line_id' => $line_id,
                'is_bound' => !empty($line_id),
                'has_buygo_admin_role' => $has_buygo_admin_role,
                'has_buygo_helper_role' => $has_buygo_helper_role,
                'product_limit' => intval($product_limit),
                'binding_info' => $binding_info,
                'avatar_url' => $avatar_url,
            ];
        }

        ?>
        <style>
        /* 表格 */
        .bgo-roles-table { border-collapse: collapse; width: 100%; max-width: 900px; }
        .bgo-roles-table th { text-align: left; padding: 10px 12px; font-size: 12px; color: #666; border-bottom: 2px solid #e0e0e0; font-weight: 600; }
        .bgo-roles-table td { padding: 10px 12px; vertical-align: middle; font-size: 13px; }
        .bgo-roles-table tbody tr:nth-child(odd) td { background: #fff; }
        .bgo-roles-table tbody tr:nth-child(even) td { background: #f9fafb; }
        .bgo-roles-table tbody tr:hover td { background: #f0f7ff; }
        /* 使用者 */
        .bgo-user-cell { display: flex; align-items: center; gap: 10px; }
        .bgo-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
        .bgo-user-info { line-height: 1.4; }
        .bgo-user-name { font-weight: 500; color: #1d2327; }
        .bgo-user-email { font-size: 11px; color: #888; }
        /* Badge */
        .bgo-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 500; }
        .bgo-badge-seller { background: #dbeafe; color: #1e40af; }
        .bgo-badge-helper { background: #f3f4f6; color: #4b5563; }
        /* LINE */
        .bgo-line-status { display: flex; align-items: center; gap: 4px; font-size: 11px; }
        .bgo-line-uid { color: #999; font-family: monospace; font-size: 10px; }
        /* 綁定 */
        .bgo-binding { font-size: 12px; }
        .bgo-binding a { text-decoration: none; color: #2271b1; }
        .bgo-binding a:hover { text-decoration: underline; }
        /* 商品限制 */
        .bgo-limit-input { width: 48px; font-size: 12px; padding: 3px 6px; text-align: center; border: 1px solid #ddd; border-radius: 3px; }
        /* 操作按鈕 */
        .bgo-btn-icon { background: none; border: none; cursor: pointer; padding: 4px; border-radius: 4px; font-size: 16px; line-height: 1; opacity: 0.4; transition: opacity 0.2s; }
        .bgo-btn-icon:hover { opacity: 1; background: #fee2e2; }
        /* Modal 統一風格 */
        .bgo-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 100000; display: flex; align-items: center; justify-content: center; }
        .bgo-modal { background: #fff; border-radius: 8px; padding: 24px; max-width: 480px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 8px 30px rgba(0,0,0,0.15); }
        .bgo-modal h3 { margin: 0 0 16px; font-size: 16px; font-weight: 600; color: #1d2327; }
        .bgo-modal-actions { display: flex; gap: 8px; margin-top: 16px; }
        /* 搜尋 dropdown */
        .bgo-search-wrap { position: relative; }
        .bgo-search-wrap input[type="text"] { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
        .bgo-search-wrap input[type="text"]:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 2px rgba(59,130,246,0.15); }
        .bgo-search-results { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ddd; border-top: none; border-radius: 0 0 6px 6px; max-height: 240px; overflow-y: auto; z-index: 100001; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .bgo-search-results .search-result-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f5f5f5; display: flex; justify-content: space-between; align-items: center; font-size: 13px; }
        .bgo-search-results .search-result-item:last-child { border-bottom: none; }
        .bgo-search-results .search-result-item:hover { background: #f0f7ff; }
        .bgo-search-results .search-result-item .user-name { font-weight: 500; }
        .bgo-search-results .search-result-item .user-email { color: #888; font-size: 11px; margin-left: 6px; }
        .bgo-search-results .search-result-item .user-id { color: #bbb; font-size: 11px; }
        .bgo-search-results .search-no-result,
        .bgo-search-results .search-loading { padding: 12px; color: #999; text-align: center; font-size: 13px; }
        /* 已選取 */
        .bgo-selected { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #f0f7ff; border: 1px solid #3b82f6; border-radius: 4px; margin-top: 6px; }
        .bgo-selected-name { flex: 1; font-weight: 500; color: #1e40af; font-size: 13px; }
        .bgo-selected-clear { background: none; border: none; color: #999; font-size: 18px; cursor: pointer; padding: 0 4px; line-height: 1; }
        .bgo-selected-clear:hover { color: #dc3232; }
        </style>

        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
            <h2 style="margin: 0;">角色權限</h2>
            <button type="button" class="button" id="add-role-btn">+ 新增賣家</button>
        </div>

        <?php if (empty($all_users)): ?>
            <p class="no-logs">尚無賣家或小幫手</p>
        <?php else: ?>
            <table class="bgo-roles-table">
                <thead>
                    <tr>
                        <th>使用者</th>
                        <th>角色</th>
                        <th>LINE</th>
                        <th>綁定</th>
                        <th>商品</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_users as $user): ?>
                        <tr>
                            <td>
                                <div class="bgo-user-cell">
                                    <img class="bgo-avatar" src="<?php echo esc_url($user['avatar_url']); ?>" alt="" />
                                    <div class="bgo-user-info">
                                        <div class="bgo-user-name"><?php echo esc_html($user['name']); ?></div>
                                        <div class="bgo-user-email"><?php echo esc_html($user['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($user['has_buygo_admin_role']): ?>
                                    <span class="bgo-badge bgo-badge-seller">賣家</span>
                                <?php else: ?>
                                    <span class="bgo-badge bgo-badge-helper">小幫手</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['is_bound']): ?>
                                    <div class="bgo-line-status" title="<?php echo esc_attr($user['line_id']); ?>">
                                        <svg width="12" height="12" viewBox="0 0 16 16"><circle cx="8" cy="8" r="8" fill="#00a32a"/><path d="M5 8L7 10L11 6" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        <span class="bgo-line-uid"><?php echo esc_html(substr($user['line_id'], 0, 8)); ?>…</span>
                                    </div>
                                <?php else: ?>
                                    <div class="bgo-line-status">
                                        <svg width="12" height="12" viewBox="0 0 16 16"><circle cx="8" cy="8" r="8" fill="#d63638"/><path d="M5 5L11 11M11 5L5 11" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
                                        <span style="color:#999;">未綁定</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="bgo-binding">
                                <?php echo $user['binding_info']; ?>
                            </td>
                            <td>
                                <input type="number" class="bgo-limit-input product-limit-input"
                                    data-user-id="<?php echo esc_attr($user['id']); ?>"
                                    value="<?php echo esc_attr($user['product_limit']); ?>"
                                    min="0" step="1" title="0 = 無限制" />
                            </td>
                            <td>
                                <?php
                                $role_to_remove = $user['has_buygo_admin_role'] ? 'buygo_admin' : ($user['has_buygo_helper_role'] ? 'buygo_helper' : null);
                                if ($role_to_remove): ?>
                                    <button type="button" class="bgo-btn-icon remove-role"
                                        data-user-id="<?php echo esc_attr($user['id']); ?>"
                                        data-role="<?php echo esc_attr($role_to_remove); ?>"
                                        title="移除<?php echo $role_to_remove === 'buygo_admin' ? '賣家' : '小幫手'; ?>角色">🗑️</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- 新增賣家 Modal -->
        <div id="add-role-modal" class="bgo-modal-overlay" style="display:none;">
            <div class="bgo-modal">
                <h3>新增賣家</h3>
                <p style="margin:0 0 12px; font-size:13px; color:#666;">搜尋用戶並賦予賣家角色</p>
                <div class="bgo-search-wrap">
                    <input type="text" id="add-role-user-search" placeholder="輸入姓名或 Email..." autocomplete="off" />
                    <input type="hidden" id="add-role-user" name="user_id" value="" />
                    <div id="add-role-user-selected" class="bgo-selected" style="display:none;">
                        <span class="bgo-selected-name"></span>
                        <button type="button" class="bgo-selected-clear" title="清除">&times;</button>
                    </div>
                    <div id="add-role-user-results" class="bgo-search-results" style="display:none;"></div>
                </div>
                <div class="bgo-modal-actions">
                    <button type="button" class="button-primary" id="confirm-add-role">確認新增</button>
                    <button type="button" class="button" id="cancel-add-role">取消</button>
                </div>
            </div>
        </div>

        <!-- 管理小幫手 Modal -->
        <div id="add-helper-modal" class="bgo-modal-overlay" style="display:none;">
            <div class="bgo-modal">
                <h3>管理小幫手 — <span id="add-helper-seller-label"></span></h3>
                <input type="hidden" id="add-helper-seller-id" value="" />
                <div id="add-helper-current-list" style="margin-bottom: 12px;"></div>
                <div style="border-top: 1px solid #eee; padding-top: 12px;">
                    <p style="font-weight: 500; margin: 0 0 8px; font-size: 13px;">新增小幫手</p>
                    <div class="bgo-search-wrap">
                        <input type="text" id="add-helper-search" placeholder="輸入姓名或 Email..." autocomplete="off" />
                        <div id="add-helper-results" class="bgo-search-results" style="display:none;"></div>
                    </div>
                </div>
                <div class="bgo-modal-actions">
                    <button type="button" class="button" id="close-add-helper">關閉</button>
                </div>
            </div>
        </div>
