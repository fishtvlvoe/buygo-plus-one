<?php

namespace BuyGoPlus\Admin;

use BuyGoPlus\Services\SettingsService;

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
            'nonce' => wp_create_nonce('buygo_settings_nonce')
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
        // 取得所有小幫手
        $helpers = SettingsService::get_helpers();
        
        // 取得所有管理員（有 buygo_admin 權限的使用者）
        $admins = get_users([
            'role' => 'administrator',
            'meta_key' => 'buygo_admin',
            'meta_value' => '1'
        ]);
        
        ?>
        <div class="wrap">
            <h2>
                角色權限設定
                <button type="button" class="button" id="add-role-btn" style="margin-left: 10px;">
                    新增角色
                </button>
            </h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>使用者</th>
                        <th>Email</th>
                        <th>角色</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($helpers as $helper): ?>
                        <tr>
                            <td><?php echo esc_html($helper['name']); ?></td>
                            <td><?php echo esc_html($helper['email']); ?></td>
                            <td>BuyGo 小幫手</td>
                            <td>
                                <button type="button" class="button-link delete-helper" data-user-id="<?php echo esc_attr($helper['id']); ?>">
                                    移除
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($helpers)): ?>
                        <tr>
                            <td colspan="4" class="no-logs">尚無小幫手</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
     * 處理表單提交
     */
    private function handle_form_submit(): void
    {
        if (isset($_POST['line_channel_access_token'])) {
            SettingsService::update_line_settings([
                'channel_access_token' => $_POST['line_channel_access_token'],
                'channel_secret' => $_POST['line_channel_secret'] ?? '',
                'liff_id' => $_POST['line_liff_id'] ?? '',
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
     * AJAX: 測試 LINE 連線
     */
    public function ajax_test_line_connection(): void
    {
        check_ajax_referer('buygo_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '權限不足']);
        }
        
        $result = SettingsService::test_line_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
