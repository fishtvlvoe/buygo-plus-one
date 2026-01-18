<?php

namespace BuyGoPlus\Admin;

use BuyGoPlus\Services\SettingsService;
use BuyGoPlus\Services\NotificationTemplates;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Page - 管理後台設定頁面
 * 
 * 提供完整的系統設定功能
 */
class SettingsPage
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_buygo_test_line_connection', [$this, 'ajax_test_line_connection']);
    }

    /**
     * 添加管理選單
     */
    public function add_admin_menu(): void
    {
        add_menu_page(
            'BuyGo 設定',
            'BuyGo 設定',
            'manage_options',
            'buygo-settings',
            [$this, 'render_settings_page'],
            'dashicons-admin-generic',
            30
        );
    }

    /**
     * 註冊設定
     */
    public function register_settings(): void
    {
        register_setting('buygo_settings', 'buygo_line_channel_access_token');
        register_setting('buygo_settings', 'buygo_line_channel_secret');
        register_setting('buygo_settings', 'buygo_line_liff_id');
    }

    /**
     * 載入腳本和樣式
     */
    public function enqueue_scripts($hook): void
    {
        if ($hook !== 'toplevel_page_buygo-settings') {
            return;
        }

        wp_enqueue_script(
            'buygo-settings-admin',
            plugin_dir_url(__FILE__) . '../../assets/js/admin-settings.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'buygo-settings-admin',
            plugin_dir_url(__FILE__) . '../../assets/css/admin-settings.css',
            [],
            '1.0.0'
        );

        wp_localize_script('buygo-settings-admin', 'buygoSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('buygo-plus-one/v1'),
            'nonce' => wp_create_nonce('wp_rest')
        ]);
    }

    /**
     * 渲染設定頁面
     */
    public function render_settings_page(): void
    {
        // 處理表單提交
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'buygo_settings')) {
            $this->handle_form_submit();
        }

        // 取得當前 Tab
        $current_tab = $_GET['tab'] ?? 'line';
        $tabs = [
            'line' => 'LINE 設定',
            'templates' => '訂單通知模板',
            'notifications' => '通知記錄',
            'workflow' => '流程監控',
            'roles' => '角色權限設定'
        ];

        // 取得 LINE 設定
        $line_settings = SettingsService::get_line_settings();

        ?>
        <div class="wrap">
            <h1>BuyGo 設定</h1>
            
            <!-- Tab 導航 -->
            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_key => $tab_label): ?>
                    <a href="?page=buygo-settings&tab=<?php echo esc_attr($tab_key); ?>" 
                       class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            
            <!-- Tab 內容 -->
            <div class="tab-content" style="margin-top: 20px;">
                <?php
                switch ($current_tab) {
                    case 'line':
                        $this->render_line_tab($line_settings);
                        break;
                    case 'templates':
                        $this->render_templates_tab();
                        break;
                    case 'notifications':
                        $this->render_notifications_tab();
                        break;
                    case 'workflow':
                        $this->render_workflow_tab();
                        break;
                    case 'roles':
                        $this->render_roles_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * 渲染 LINE 設定 Tab
     */
    private function render_line_tab($settings): void
    {
        ?>
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
        <?php
    }

    /**
     * 渲染通知記錄 Tab
     */
    private function render_notifications_tab(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_notification_logs';
        
        // 檢查資料表是否存在
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        // 取得篩選參數
        $status_filter = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';
        
        // 查詢日誌
        $where = ['1=1'];
        $query_params = [];
        
        if ($status_filter) {
            $where[] = "status = %s";
            $query_params[] = $status_filter;
        }
        
        if ($search) {
            $where[] = "(receiver LIKE %s OR content LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = $search_term;
            $query_params[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where);
        
        if ($table_exists) {
            $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT 100";
            if (!empty($query_params)) {
                $query = $wpdb->prepare($query, $query_params);
            }
            $logs = $wpdb->get_results($query, ARRAY_A);
        } else {
            $logs = [];
        }
        
        ?>
        <div class="tablenav top">
            <form method="get" style="display: inline-block;">
                <input type="hidden" name="page" value="buygo-settings">
                <input type="hidden" name="tab" value="notifications">
                
                <select name="status" id="filter-status">
                    <option value="">全部狀態</option>
                    <option value="success" <?php selected($status_filter, 'success'); ?>>成功</option>
                    <option value="failed" <?php selected($status_filter, 'failed'); ?>>失敗</option>
                </select>
                
                <input type="search" name="search" placeholder="搜尋..." value="<?php echo esc_attr($search); ?>" />
                
                <button type="submit" class="button">篩選</button>
                <a href="?page=buygo-settings&tab=notifications" class="button">清除</a>
            </form>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>接收者</th>
                    <th>管道</th>
                    <th>狀態</th>
                    <th>內容</th>
                    <th>時間</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="no-logs">
                            <?php echo $table_exists ? '沒有找到符合條件的記錄' : '資料表尚未建立，請啟用外掛以建立資料表'; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log['receiver'] ?? '-'); ?></td>
                            <td><?php echo esc_html($log['channel'] ?? '-'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($log['status'] ?? ''); ?>">
                                    <?php echo esc_html($log['status'] === 'success' ? '成功' : '失敗'); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(wp_trim_words($log['content'] ?? '', 30)); ?></td>
                            <td><?php echo esc_html($log['created_at'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * 渲染流程監控 Tab
     */
    private function render_workflow_tab(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_workflow_logs';
        
        // 檢查資料表是否存在
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if ($table_exists) {
            $logs = $wpdb->get_results(
                "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 100",
                ARRAY_A
            );
        } else {
            $logs = [];
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>流程名稱</th>
                    <th>狀態</th>
                    <th>步數</th>
                    <th>成功率</th>
                    <th>錯誤訊息</th>
                    <th>時間</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="no-logs">
                            <?php echo $table_exists ? '沒有找到符合條件的記錄' : '資料表尚未建立，請啟用外掛以建立資料表'; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log['workflow_name'] ?? '-'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($log['status'] ?? ''); ?>">
                                    <?php echo esc_html($log['status'] ?? '-'); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log['steps'] ?? '-'); ?></td>
                            <td><?php echo esc_html($log['success_rate'] ?? '-'); ?>%</td>
                            <td><?php echo esc_html(wp_trim_words($log['error_message'] ?? '', 30)); ?></td>
                            <td><?php echo esc_html($log['created_at'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * 渲染角色權限設定 Tab
     */
    private function render_roles_tab(): void
    {
        // 取得所有小幫手（從選項中）
        $helpers = SettingsService::get_helpers();
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
            $line_id = SettingsService::get_user_line_id($user->ID);
            
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
                'is_in_helpers_list' => $is_in_helpers_list
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
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_users as $user): ?>
                            <tr>
                                <td><?php echo esc_html($user['name']); ?></td>
                                <td><?php echo esc_html($user['email']); ?></td>
                                <td>
                                    <?php if ($user['is_bound']): ?>
                                        <span style="color: #00a32a;">✅ 已綁定</span>
                                        <br>
                                        <code style="font-size: 11px; color: #666;"><?php echo esc_html($user['line_id']); ?></code>
                                    <?php else: ?>
                                        <span style="color: #d63638;">❌ 未綁定</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($user['role']); ?></td>
                                <td>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                                        <?php if (!$user['is_bound']): ?>
                                            <button type="button" class="button button-secondary send-binding-link" data-user-id="<?php echo esc_attr($user['id']); ?>" style="font-size: 12px; padding: 6px 12px; height: auto; line-height: 1.4;">
                                                📧 發送綁定連結
                                            </button>
                                        <?php endif; ?>
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
        
        <!-- 新增角色 Modal（使用 WordPress 內建的樣式） -->
        <div id="add-role-modal" style="display:none;">
            <div class="modal-content" style="background: white; padding: 20px; border: 1px solid #ccc; border-radius: 4px; max-width: 500px; margin: 20px auto;">
                <h3>新增角色</h3>
                <form id="add-role-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="add-role-user">選擇使用者</label>
                            </th>
                            <td>
                                <select name="user_id" id="add-role-user" class="regular-text">
                                    <option value="">請選擇使用者</option>
                                    <?php
                                    $users = get_users(['number' => 100]);
                                    foreach ($users as $user) {
                                        echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="add-role-type">選擇角色</label>
                            </th>
                            <td>
                                <select name="role" id="add-role-type" class="regular-text">
                                    <option value="buygo_helper">BuyGo 小幫手</option>
                                    <option value="buygo_admin">BuyGo 管理員</option>
                                </select>
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
        <?php
    }

    /**
     * 渲染訂單通知模板 Tab
     */
    private function render_templates_tab(): void
    {
        // 取得所有模板
        $all_templates = NotificationTemplates::get_all_templates();
        
        // 定義可編輯的模板（買家版和賣家版）
        $editable_templates = [
            'buyer' => [
                'order_created' => [
                    'name' => '訂單已建立',
                    'description' => '買家下單後收到的通知',
                    'variables' => ['order_id', 'total', '客戶名稱', '訂單編號', '訂單金額', '下單時間']
                ],
                'order_shipped' => [
                    'name' => '訂單已出貨',
                    'description' => '商品出貨後通知買家',
                    'variables' => ['order_id', 'note', '訂單編號', '商品名稱']
                ],
                'order_cancelled' => [
                    'name' => '訂單已取消',
                    'description' => '訂單取消或缺貨時通知買家',
                    'variables' => ['order_id', 'note', '訂單編號', '說明']
                ]
            ],
            'seller' => [
                'seller_order_created' => [
                    'name' => '新訂單通知',
                    'description' => '賣家收到新訂單時的通知',
                    'variables' => ['order_id', 'buyer_name', 'order_total', '訂單編號', '客戶名稱', '訂單金額', '下單時間']
                ],
                'seller_order_paid' => [
                    'name' => '訂單已付款',
                    'description' => '訂單付款後通知賣家',
                    'variables' => ['order_id', 'buyer_name', 'order_total', '訂單編號', '客戶名稱', '訂單金額']
                ],
                'seller_order_refunded' => [
                    'name' => '訂單已退款',
                    'description' => '訂單退款後通知賣家',
                    'variables' => ['order_id', 'customer_name', 'total', '訂單編號', '客戶名稱', '退款金額']
                ]
            ]
        ];
        
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('buygo_settings'); ?>
            
            <h2>訂單通知模板管理</h2>
            <p class="description">
                編輯買家和賣家收到的 LINE 通知模板。可使用變數：<code>{變數名稱}</code>
            </p>
            
            <div style="margin-top: 20px;">
                <h3>買家版模板（客戶收到的通知）</h3>
                
                <?php foreach ($editable_templates['buyer'] as $template_key => $template_info): ?>
                    <?php
                    $template = $all_templates[$template_key] ?? null;
                    $line_message = $template['line']['message'] ?? '';
                    ?>
                    <div style="margin-bottom: 30px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <h4 style="margin-top: 0;">
                            <?php echo esc_html($template_info['name']); ?>
                            <span style="font-size: 12px; font-weight: normal; color: #666;">
                                （<?php echo esc_html($template_info['description']); ?>）
                            </span>
                        </h4>
                        
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
                        
                        <p class="description" style="margin-top: 5px;">
                            可用變數：<code><?php echo esc_html(implode('</code>、<code>', $template_info['variables'])); ?></code>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="margin-top: 30px;">
                <h3>賣家版模板（賣家/小幫手收到的通知）</h3>
                
                <?php foreach ($editable_templates['seller'] as $template_key => $template_info): ?>
                    <?php
                    $template = $all_templates[$template_key] ?? null;
                    $line_message = $template['line']['message'] ?? '';
                    ?>
                    <div style="margin-bottom: 30px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <h4 style="margin-top: 0;">
                            <?php echo esc_html($template_info['name']); ?>
                            <span style="font-size: 12px; font-weight: normal; color: #666;">
                                （<?php echo esc_html($template_info['description']); ?>）
                            </span>
                        </h4>
                        
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
                        
                        <p class="description" style="margin-top: 5px;">
                            可用變數：<code><?php echo esc_html(implode('</code>、<code>', $template_info['variables'])); ?></code>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <p class="submit">
                <input type="submit" name="submit_templates" class="button-primary" value="儲存模板" />
            </p>
        </form>
        <?php
    }

    /**
     * 處理表單提交
     */
    private function handle_form_submit(): void
    {
        if (isset($_POST['line_channel_access_token'])) {
            SettingsService::update_line_settings([
                'channel_access_token' => sanitize_text_field($_POST['line_channel_access_token'] ?? ''),
                'channel_secret' => sanitize_text_field($_POST['line_channel_secret'] ?? ''),
                'liff_id' => sanitize_text_field($_POST['line_liff_id'] ?? ''),
            ]);
            
            add_settings_error(
                'buygo_settings',
                'settings_saved',
                '設定已儲存',
                'updated'
            );
        }
        
        // 處理模板儲存
        if (isset($_POST['submit_templates']) && isset($_POST['templates']) && wp_verify_nonce($_POST['_wpnonce'], 'buygo_settings')) {
            $templates = $_POST['templates'];
            
            // 取得所有現有自訂模板
            $all_custom = get_option('buygo_notification_templates', []);
            
            // 取得所有模板（包含預設和自訂）
            $all_templates = NotificationTemplates::get_all_templates();
            
            // 處理每個提交的模板
            foreach ($templates as $key => $template_data) {
                if (isset($template_data['line']['message'])) {
                    // 取得當前模板（可能是預設或自訂）
                    $current_template = $all_templates[$key] ?? null;
                    
                    if ($current_template) {
                        // 建立自訂模板結構
                        $all_custom[$key] = [
                            'email' => $current_template['email'] ?? ['subject' => '', 'message' => ''],
                            'line' => [
                                'message' => sanitize_textarea_field($template_data['line']['message'])
                            ]
                        ];
                    }
                }
            }
            
            // 儲存所有自訂模板
            NotificationTemplates::save_custom_templates($all_custom);
            
            add_settings_error(
                'buygo_settings',
                'templates_saved',
                '模板已儲存',
                'updated'
            );
        }
    }

    /**
     * AJAX: 測試 LINE 連線
     */
    public function ajax_test_line_connection(): void
    {
        check_ajax_referer('buygo_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '權限不足']);
        }
        
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : null;
        $result = SettingsService::test_line_connection($token);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
