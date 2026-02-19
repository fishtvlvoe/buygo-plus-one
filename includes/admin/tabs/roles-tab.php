<?php if (!defined('ABSPATH')) { exit; }

        global $wpdb;

        // 取得所有小幫手（從選項中）
        $helpers = \BuyGoPlus\Services\SettingsService::get_helpers();
        $helper_ids = array_map(function($h) { return $h['id']; }, $helpers);

        // 取得所有管理員（WordPress 管理員 + BuyGo 管理員）
        $wp_admins = get_users(['role' => 'administrator']);
        $buygo_admins = get_users(['role' => 'buygo_admin']);
        $all_admins = array_merge($wp_admins, $buygo_admins);
        $wp_admin_ids = array_map(function($admin) { return $admin->ID; }, $wp_admins);

        // 取得所有有 buygo_helper 角色的使用者
        $buygo_helpers = get_users(['role' => 'buygo_helper']);

        // 合併所有相關使用者（管理員 + 小幫手）
        $all_related_users = array_merge($all_admins, $buygo_helpers);

        // 也加入從選項中取得的小幫手（可能沒有角色但有記錄）
        foreach ($helpers as $helper) {
            $found = false;
            foreach ($all_related_users as $user) {
                if ($user->ID === $helper['id']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $user_obj = get_userdata($helper['id']);
                if ($user_obj) {
                    $all_related_users[] = $user_obj;
                }
            }
        }

        // 去重（使用 user_id 作為 key）
        $unique_users = [];
        foreach ($all_related_users as $user) {
            if (!isset($unique_users[$user->ID])) {
                $unique_users[$user->ID] = $user;
            }
        }

        // 建立所有使用者的列表
        $all_users = [];

        foreach ($unique_users as $user) {
            $line_id = \BuyGoPlus\Services\SettingsService::get_user_line_id($user->ID);

            // 判斷角色
            $is_wp_admin = in_array($user->ID, $wp_admin_ids);
            $has_buygo_admin_role = in_array('buygo_admin', $user->roles);
            $has_buygo_helper_role = in_array('buygo_helper', $user->roles);
            $is_in_helpers_list = in_array($user->ID, $helper_ids);

            if ($is_wp_admin || $has_buygo_admin_role) {
                $role = 'BuyGo 管理員';
            } elseif ($has_buygo_helper_role || $is_in_helpers_list) {
                $role = 'BuyGo 小幫手';
            } else {
                // 這種情況不應該發生，但為了安全起見
                continue;
            }

            // 取得賣家類型
            $seller_type = get_user_meta($user->ID, 'buygo_seller_type', true);
            if (empty($seller_type)) {
                $seller_type = 'test'; // 預設為測試賣家
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

            if ($has_buygo_helper_role || $is_in_helpers_list) {
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
            } elseif ($has_buygo_admin_role || $is_wp_admin) {
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

            $all_users[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'role' => $role,
                'line_id' => $line_id,
                'is_bound' => !empty($line_id),
                'is_wp_admin' => $is_wp_admin,
                'has_buygo_admin_role' => $has_buygo_admin_role,
                'has_buygo_helper_role' => $has_buygo_helper_role,
                'is_in_helpers_list' => $is_in_helpers_list,
                'seller_type' => $seller_type,
                'product_limit' => intval($product_limit),
                'binding_info' => $binding_info,
                'buygo_id' => $buygo_id
            ];
        }

        ?>
        <div class="wrap">
            <h2>
                角色權限設定
                <button type="button" class="button" id="add-role-btn" style="margin-left: 10px;">
                    新增角色
                </button>
            </h2>

            <?php if (empty($all_users)): ?>
                <p class="no-logs">尚無管理員或小幫手</p>
            <?php else: ?>
                <p class="description" style="margin-bottom: 15px;">
                    ⚠️ 提示：未綁定 LINE 的使用者無法從 LINE 上架商品
                </p>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>使用者</th>
                            <th>Email</th>
                            <th>LINE ID</th>
                            <th>角色</th>
                            <th>綁定關係</th>
                            <th>商品限制</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_users as $user): ?>
                            <tr>
                                <td>
                                    <?php echo esc_html($user['name']); ?><br>
                                    <small style="color: #666;">WP-<?php echo esc_html($user['id']); ?></small>
                                </td>
                                <td><?php echo esc_html($user['email']); ?></td>
                                <td>
                                    <?php if ($user['is_bound']): ?>
                                        <div style="display: flex; align-items: flex-start; gap: 4px; max-width: 140px;">
                                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" title="已綁定" style="flex-shrink: 0; margin-top: 2px;">
                                                <circle cx="8" cy="8" r="8" fill="#00a32a"/>
                                                <path d="M5 8L7 10L11 6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <code style="font-size: 9px; color: #666; line-height: 1.3; word-break: break-all; display: block;"><?php echo esc_html($user['line_id']); ?></code>
                                        </div>
                                    <?php else: ?>
                                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" title="未綁定">
                                            <circle cx="8" cy="8" r="8" fill="#d63638"/>
                                            <path d="M5 5L11 11M11 5L5 11" stroke="white" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html($user['role']); ?><br>
                                    <small style="color: #666;">
                                        <?php
                                        if ($user['buygo_id']) {
                                            echo 'BuyGo-' . esc_html($user['buygo_id']);
                                        } else {
                                            echo '（無 BuyGo ID）';
                                        }
                                        ?>
                                    </small>
                                </td>
                                <td style="font-size: 12px; color: #2271b1;">
                                    <?php echo $user['binding_info']; ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <input
                                            type="number"
                                            class="product-limit-input"
                                            data-user-id="<?php echo esc_attr($user['id']); ?>"
                                            value="<?php echo esc_attr($user['product_limit']); ?>"
                                            min="0"
                                            step="1"
                                            style="width: 60px; font-size: 12px;"
                                            placeholder="3"
                                            title="0 = 無限制，預設 = 3"
                                        />
                                        <span style="font-size: 11px; color: #666;">
                                            <?php echo ($user['product_limit'] == 0) ? '(無限制)' : '個商品'; ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                                        <?php if (!$user['is_wp_admin']): ?>
                                            <?php
                                            // 判斷應該移除哪個角色
                                            $role_to_remove = null;
                                            if ($user['has_buygo_admin_role'] || ($user['role'] === 'BuyGo 管理員')) {
                                                $role_to_remove = 'buygo_admin';
                                            } elseif ($user['has_buygo_helper_role'] || $user['role'] === 'BuyGo 小幫手' || ($user['is_in_helpers_list'] ?? false)) {
                                                $role_to_remove = 'buygo_helper';
                                            }
                                            ?>
                                            <?php if ($role_to_remove): ?>
                                                <button type="button" class="button remove-role" data-user-id="<?php echo esc_attr($user['id']); ?>" data-role="<?php echo esc_attr($role_to_remove); ?>" style="font-size: 12px; padding: 6px 12px; height: auto; line-height: 1.4; background: #dc3232; color: white; border-color: #dc3232; cursor: pointer;">
                                                    🗑️ 移除<?php echo $role_to_remove === 'buygo_admin' ? '管理員' : '小幫手'; ?>角色
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="description" style="font-size: 11px; color: #666; padding: 4px 8px; background: #f0f0f1; border-radius: 3px;">
                                                WordPress 管理員無法移除
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- 新增角色 Modal -->
        <div id="add-role-modal" style="display:none;">
            <div class="modal-content">
                <h3>新增角色</h3>
                <form id="add-role-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label>搜尋使用者</label>
                            </th>
                            <td>
                                <div class="user-search-wrap">
                                    <input type="text" id="add-role-user-search" class="regular-text" placeholder="輸入姓名或 Email 搜尋..." autocomplete="off" />
                                    <input type="hidden" id="add-role-user" name="user_id" value="" />
                                    <div id="add-role-user-selected" class="user-selected" style="display:none;">
                                        <span class="user-selected-name"></span>
                                        <button type="button" class="user-selected-clear" title="清除">&times;</button>
                                    </div>
                                    <div id="add-role-user-results" class="user-search-results" style="display:none;"></div>
                                    <p class="description">至少輸入 2 個字元開始搜尋</p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="add-role-type">選擇角色</label>
                            </th>
                            <td>
                                <select name="role" id="add-role-type" class="regular-text">
                                    <option value="buygo_admin">BuyGo 管理員（賣家）</option>
                                    <option value="buygo_helper">BuyGo 小幫手</option>
                                </select>
                            </td>
                        </tr>
                        <tr id="add-role-seller-row" style="display:none;">
                            <th scope="row">
                                <label>歸屬賣家</label>
                            </th>
                            <td>
                                <div class="user-search-wrap">
                                    <input type="text" id="add-role-seller-search" class="regular-text" placeholder="輸入賣家姓名或 Email 搜尋..." autocomplete="off" />
                                    <input type="hidden" id="add-role-seller-id" name="seller_id" value="" />
                                    <div id="add-role-seller-selected" class="user-selected" style="display:none;">
                                        <span class="user-selected-name"></span>
                                        <button type="button" class="user-selected-clear" title="清除">&times;</button>
                                    </div>
                                    <div id="add-role-seller-results" class="user-search-results" style="display:none;"></div>
                                    <p class="description">選擇此小幫手歸屬的賣家（僅顯示管理員）</p>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="button" class="button-primary" id="confirm-add-role">確認</button>
                        <button type="button" class="button" id="cancel-add-role">取消</button>
                    </p>
                </form>
            </div>
        </div>

        <!-- 新增小幫手 Modal（從綁定關係欄位觸發） -->
        <div id="add-helper-modal" style="display:none;">
            <div class="modal-content">
                <h3>管理小幫手 — <span id="add-helper-seller-label"></span></h3>
                <input type="hidden" id="add-helper-seller-id" value="" />

                <div id="add-helper-current-list" style="margin-bottom: 15px;"></div>

                <div style="border-top: 1px solid #eee; padding-top: 15px;">
                    <p style="font-weight: 500; margin-bottom: 8px;">新增小幫手</p>
                    <div class="user-search-wrap">
                        <input type="text" id="add-helper-search" class="regular-text" placeholder="輸入姓名或 Email 搜尋..." autocomplete="off" />
                        <div id="add-helper-results" class="user-search-results" style="display:none;"></div>
                    </div>
                </div>

                <p class="submit" style="margin-top: 15px;">
                    <button type="button" class="button" id="close-add-helper">關閉</button>
                </p>
            </div>
        </div>
