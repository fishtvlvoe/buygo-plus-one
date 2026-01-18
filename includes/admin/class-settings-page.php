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
        
        // 獨立的通知模板管理頁面
        add_submenu_page(
            'buygo-settings',
            '通知模板管理',
            '通知模板管理',
            'manage_options',
            'buygo-templates',
            [$this, 'render_templates_page'],
            1
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
        if ($hook !== 'toplevel_page_buygo-settings' && $hook !== 'buygo-settings_page_buygo-templates') {
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
     * 渲染獨立的通知模板管理頁面
     */
    public function render_templates_page(): void
    {
        // 處理表單提交
        if (isset($_POST['submit_templates']) && isset($_POST['templates']) && wp_verify_nonce($_POST['_wpnonce'], 'buygo_settings')) {
            $this->handle_templates_submit();
        }
        
        ?>
        <div class="wrap">
            <h1>通知模板管理</h1>
            <?php settings_errors('buygo_settings'); ?>
            <?php $this->render_templates_tab(); ?>
        </div>
        <?php
    }
    
    /**
     * 渲染訂單通知模板 Tab（內部方法）
     */
    private function render_templates_tab(): void
    {
        // 取得所有模板
        $all_templates = NotificationTemplates::get_all_templates();
        
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
            'missing_fields' => '缺少欄位'
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
                    'variables' => ['product_name', 'price', 'quantity', 'product_url', 'currency_symbol', 'original_price_section', 'category_section', 'arrival_date_section', 'preorder_date_section', 'community_url_section']
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
                ]
            ]
        ];
        
        ?>
        <div id="buygo-templates-page">
            <form method="post" action="">
                <?php wp_nonce_field('buygo_settings'); ?>
                
                <h2>通知模板管理</h2>
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
            <div id="system-templates" class="template-tab-content" style="margin-top: 20px; display: none;">
                <h3>系統通知</h3>
                
                <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
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
                <div class="postbox closed" style="margin-bottom: 20px; max-width: 800px;">
                    <button type="button" class="handlediv" aria-expanded="false" onclick="jQuery(this).parent().toggleClass('closed'); jQuery(this).attr('aria-expanded', jQuery(this).parent().hasClass('closed') ? 'false' : 'true'); jQuery(this).siblings('.inside').toggle();">
                        <span class="toggle-indicator" aria-hidden="true"></span>
                    </button>
                    <h3 class="hndle" style="padding: 12px 15px; margin: 0; cursor: pointer;">
                        <span>關鍵字訊息</span>
                    </h3>
                    <div class="inside" style="padding: 15px; display: none;">
                        <p class="description">關鍵字管理功能開發中...</p>
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
    }
    
    /**
     * 處理模板提交（獨立方法）
     */
    private function handle_templates_submit(): void
    {
        if (isset($_POST['submit_templates']) && isset($_POST['templates']) && wp_verify_nonce($_POST['_wpnonce'], 'buygo_settings')) {
            $templates = $_POST['templates'];
            
            // 取得所有現有自訂模板
            $all_custom = get_option('buygo_notification_templates', []);
            
            // 取得所有模板（包含預設和自訂）
            $all_templates = NotificationTemplates::get_all_templates();
            
            // 處理每個提交的模板
            foreach ($templates as $key => $template_data) {
                $template_type = sanitize_text_field($template_data['type'] ?? 'text');
                
                if ($template_type === 'flex') {
                    // Flex Message 模板
                    $flex_template = $template_data['line']['flex_template'] ?? [];
                    
                    if (!empty($flex_template)) {
                        // 取得當前模板（可能是預設或自訂）
                        $current_template = $all_templates[$key] ?? null;
                        
                        if ($current_template) {
                            // 建立自訂 Flex Message 模板結構
                            $all_custom[$key] = [
                                'type' => 'flex',
                                'line' => [
                                    'flex_template' => [
                                        'logo_url' => sanitize_text_field($flex_template['logo_url'] ?? ''),
                                        'title' => sanitize_text_field($flex_template['title'] ?? ''),
                                        'description' => sanitize_textarea_field($flex_template['description'] ?? ''),
                                        'buttons' => []
                                    ]
                                ]
                            ];
                            
                            // 處理按鈕
                            if (isset($flex_template['buttons']) && is_array($flex_template['buttons'])) {
                                foreach ($flex_template['buttons'] as $button) {
                                    if (!empty($button['label']) || !empty($button['action'])) {
                                        $all_custom[$key]['line']['flex_template']['buttons'][] = [
                                            'label' => sanitize_text_field($button['label'] ?? ''),
                                            'action' => sanitize_text_field($button['action'] ?? '')
                                        ];
                                    }
                                }
                            }
                        }
                    }
                } elseif (isset($template_data['line']['message'])) {
                    // 文字模板
                    // 取得當前模板（可能是預設或自訂）
                    $current_template = $all_templates[$key] ?? null;
                    
                    if ($current_template) {
                        // 建立自訂模板結構（移除 email 結構）
                        $all_custom[$key] = [
                            'line' => [
                                'message' => sanitize_textarea_field($template_data['line']['message'])
                            ]
                        ];
                    }
                }
            }
            
            // #region agent log
            error_log('DEBUG: handle_templates_submit() - all_custom keys: ' . implode(', ', array_keys($all_custom)));
            if (isset($all_custom['order_created'])) {
                error_log('DEBUG: handle_templates_submit() - order_created data: ' . print_r($all_custom['order_created'], true));
            }
            // #endregion
            
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
