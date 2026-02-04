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
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_buygo_test_line_connection', [$this, 'ajax_test_line_connection']);
        add_action('wp_ajax_buygo_update_seller_type', [$this, 'ajax_update_seller_type']);
        add_action('wp_ajax_buygo_update_product_limit', [$this, 'ajax_update_product_limit']);
    }

    /**
     * 添加管理選單
     */
    public function add_admin_menu(): void
    {
        // 主選單：BuyGo+1
        add_menu_page(
            'BuyGo+1',
            'BuyGo+1',
            'manage_options',
            'buygo-plus-one',
            [$this, 'render_templates_page'],  // 預設顯示 LINE 模板頁面
            'dashicons-cart',
            30
        );

        // 子選單 1：LINE 模板（第一個位置）
        add_submenu_page(
            'buygo-plus-one',
            'LINE 模板',
            'LINE 模板',
            'manage_options',
            'buygo-plus-one',  // 與主選單相同 slug，覆蓋預設子選單
            [$this, 'render_templates_page']
        );

        // 子選單 2：設定（第二個位置）
        add_submenu_page(
            'buygo-plus-one',
            '設定',
            '設定',
            'manage_options',
            'buygo-settings',
            [$this, 'render_settings_page']
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
        // 檢查是否在 BuyGo+1 的後台頁面
        if ($hook !== 'toplevel_page_buygo-plus-one' && $hook !== 'buygo1_page_buygo-settings') {
            return;
        }

        wp_enqueue_script(
            'buygo-settings-admin',
            BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/js/admin-settings.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'buygo-settings-admin',
            BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/css/admin-settings.css',
            [],
            '1.0.0'
        );

        wp_localize_script('buygo-settings-admin', 'buygoSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('buygo-plus-one/v1'),
            'nonce' => wp_create_nonce('buygo-settings'), // AJAX 和 REST API 共用
            'restNonce' => wp_create_nonce('wp_rest') // REST API 專用
        ]);

        // 阻擋 Cloudflare Beacon 以修復效能問題
        add_action('admin_print_footer_scripts', function() {
            ?>
            <script>
            // 阻擋 Cloudflare Web Analytics Beacon
            (function() {
                if (typeof window !== 'undefined') {
                    window.__cfBeacon = null;
                    window.cfjsloader = null;
                }
            })();
            </script>
            <?php
        }, 1);
    }

    /**
     * 渲染設定頁面
     */
    public function render_settings_page(): void
    {
        // 效能監控
        $start_time = microtime(true);
        error_log('[BuyGo Performance] Settings page load started');

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
            'checkout' => '結帳設定',
            'roles' => '角色權限設定',
            'test-tools' => '測試工具',
            'debug-center' => '除錯中心'
        ];

        // 取得 LINE 設定
        $settings_start = microtime(true);
        $line_settings = SettingsService::get_line_settings();
        $settings_time = microtime(true) - $settings_start;
        error_log(sprintf('[BuyGo Performance] get_line_settings took %.4f seconds', $settings_time));

        ?>
        <div class="wrap">
            <h1>設定</h1>
            
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
                    case 'checkout':
                        $this->render_checkout_tab();
                        break;
                    case 'roles':
                        $this->render_roles_tab();
                        break;
                    case 'test-tools':
                        $this->render_test_tools_tab();
                        break;
                    case 'debug-center':
                        $this->render_debug_center_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
        // 記錄總載入時間
        $total_time = microtime(true) - $start_time;
        error_log(sprintf('[BuyGo Performance] Settings page total load time: %.4f seconds', $total_time));

        // 停用 Heartbeat API 以提升效能
        wp_deregister_script('heartbeat');
    }

    /**
     * 渲染 LINE 設定 Tab
     */
    private function render_line_tab($settings): void
    {
        // Debug: 顯示解密狀態
        $debug_info = [];
        $raw_token = get_option('buygo_core_settings', [])['line_channel_access_token'] ?? null;
        if (!$raw_token) {
            $raw_token = get_option('buygo_line_channel_access_token', null);
        }

        if ($raw_token) {
            $debug_info['token_exists'] = true;
            $debug_info['token_length'] = strlen($raw_token);
            $debug_info['token_preview'] = substr($raw_token, 0, 20) . '...';
            $debug_info['decrypted_length'] = strlen($settings['channel_access_token']);
            $debug_info['encryption_key_defined'] = defined('BUYGO_ENCRYPTION_KEY');
        } else {
            $debug_info['token_exists'] = false;
        }

        ?>

        <!-- Debug Information -->
        <div class="notice notice-info" style="margin: 20px 0;">
            <h3>🔍 LINE 設定 Debug 資訊</h3>
            <table class="widefat" style="margin-top: 10px;">
                <tr>
                    <th style="width: 200px;">Token 是否存在</th>
                    <td><?php echo $debug_info['token_exists'] ? '✅ 是' : '❌ 否'; ?></td>
                </tr>
                <?php if ($debug_info['token_exists']): ?>
                <tr>
                    <th>加密資料長度</th>
                    <td><?php echo $debug_info['token_length']; ?> 字元</td>
                </tr>
                <tr>
                    <th>加密資料預覽</th>
                    <td><code><?php echo esc_html($debug_info['token_preview']); ?></code></td>
                </tr>
                <tr>
                    <th>解密後長度</th>
                    <td><?php echo $debug_info['decrypted_length']; ?> 字元</td>
                </tr>
                <tr>
                    <th>解密結果</th>
                    <td>
                        <?php if ($debug_info['decrypted_length'] > 0): ?>
                            <span style="color: green;">✅ 解密成功</span>
                        <?php else: ?>
                            <span style="color: red;">❌ 解密失敗或資料為空</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>加密金鑰已定義</th>
                    <td><?php echo $debug_info['encryption_key_defined'] ? '✅ 是' : '⚠️ 否（使用預設金鑰）'; ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

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
     * 渲染結帳設定 Tab
     */
    private function render_checkout_tab(): void
    {
        // 處理表單提交
        if (isset($_POST['buygo_checkout_submit']) && wp_verify_nonce($_POST['_wpnonce'], 'buygo_checkout_settings')) {
            \BuyGoPlus\Services\CheckoutCustomizationService::save_settings([
                'hide_shipping' => isset($_POST['buygo_checkout_hide_shipping']),
                'hide_ship_to_different' => isset($_POST['buygo_checkout_hide_ship_to_different']),
                'enable_id_number' => isset($_POST['buygo_checkout_enable_id_number']),
            ]);
            echo '<div class="notice notice-success"><p>設定已儲存！</p></div>';
        }

        $settings = \BuyGoPlus\Services\CheckoutCustomizationService::get_settings();
        ?>
        <div class="checkout-settings-wrap">
            <h2>FluentCart 結帳頁面自訂</h2>
            <p class="description">這些設定會即時生效於 FluentCart 結帳頁面，無需清除快取。</p>

            <form method="post" action="">
                <?php wp_nonce_field('buygo_checkout_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">隱藏運送方式</th>
                        <td>
                            <label>
                                <input type="checkbox" name="buygo_checkout_hide_shipping" value="1"
                                       <?php checked($settings['hide_shipping'], true); ?> />
                                隱藏運送方式選擇區塊
                            </label>
                            <p class="description">適用於代購業者自行處理出貨的情況</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">隱藏寄送到其他地址</th>
                        <td>
                            <label>
                                <input type="checkbox" name="buygo_checkout_hide_ship_to_different" value="1"
                                       <?php checked($settings['hide_ship_to_different'], true); ?> />
                                隱藏「寄送到其他地址」選項
                            </label>
                            <p class="description">簡化結帳流程，只使用帳單地址</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">身分證字號欄位</th>
                        <td>
                            <label>
                                <input type="checkbox" name="buygo_checkout_enable_id_number" value="1"
                                       <?php checked($settings['enable_id_number'], true); ?> />
                                新增身分證字號欄位
                            </label>
                            <p class="description">海運報關使用，會驗證台灣身分證格式（如 A123456789）</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="buygo_checkout_submit" class="button-primary">儲存設定</button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * 渲染流程監控 Tab
     */
    private function render_workflow_tab(): void
    {
        // 取得 Webhook 日誌
        $logger = \BuyGoPlus\Services\WebhookLogger::get_instance();
        
        // 取得篩選參數
        $event_type_filter = $_GET['event_type'] ?? '';
        $limit = 100;
        
        // 查詢參數
        $args = array(
            'limit' => $limit,
            'order_by' => 'created_at',
            'order' => 'DESC',
        );
        
        if ($event_type_filter) {
            $args['event_type'] = $event_type_filter;
        }
        
        $webhook_logs = $logger->get_logs($args);
        $stats = $logger->get_statistics('today');
        
        ?>
        <div class="webhook-monitor">
            <!-- 統計資訊 -->
            <div class="webhook-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div class="stat-card" style="background: #f0f0f0; padding: 15px; border-radius: 5px;">
                    <div style="font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo isset($stats['webhook_received']) ? $stats['webhook_received'] : 0; ?></div>
                    <div style="color: #666; font-size: 14px;">今日 Webhook 接收</div>
                </div>
                <div class="stat-card" style="background: #f0f0f0; padding: 15px; border-radius: 5px;">
                    <div style="font-size: 24px; font-weight: bold; color: #46b450;"><?php echo isset($stats['image_uploaded']) ? $stats['image_uploaded'] : 0; ?></div>
                    <div style="color: #666; font-size: 14px;">今日圖片上傳</div>
                </div>
                <div class="stat-card" style="background: #f0f0f0; padding: 15px; border-radius: 5px;">
                    <div style="font-size: 24px; font-weight: bold; color: #dc3232;"><?php echo isset($stats['error']) ? $stats['error'] : 0; ?></div>
                    <div style="color: #666; font-size: 14px;">今日錯誤</div>
                </div>
                <div class="stat-card" style="background: #f0f0f0; padding: 15px; border-radius: 5px;">
                    <div style="font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo isset($stats['product_created']) ? $stats['product_created'] : 0; ?></div>
                    <div style="color: #666; font-size: 14px;">今日商品建立</div>
                </div>
            </div>
            
            <!-- 篩選器 -->
            <div class="webhook-filters" style="margin-bottom: 20px;">
                <form method="get" style="display: inline-block;">
                    <input type="hidden" name="page" value="buygo-settings">
                    <input type="hidden" name="tab" value="workflow">
                    
                    <select name="event_type" id="filter-event-type" style="padding: 5px 10px;">
                        <option value="">所有事件類型</option>
                        <option value="webhook_received" <?php selected($event_type_filter, 'webhook_received'); ?>>Webhook 接收</option>
                        <option value="image_uploaded" <?php selected($event_type_filter, 'image_uploaded'); ?>>圖片上傳</option>
                        <option value="text_message_received" <?php selected($event_type_filter, 'text_message_received'); ?>>文字訊息</option>
                        <option value="product_created" <?php selected($event_type_filter, 'product_created'); ?>>商品建立</option>
                        <option value="product_creating" <?php selected($event_type_filter, 'product_creating'); ?>>商品建立中</option>
                        <option value="error" <?php selected($event_type_filter, 'error'); ?>>錯誤</option>
                        <option value="permission_denied" <?php selected($event_type_filter, 'permission_denied'); ?>>權限拒絕</option>
                        <option value="test_mode_active" <?php selected($event_type_filter, 'test_mode_active'); ?>>測試模式</option>
                    </select>
                    
                    <button type="submit" class="button" style="margin-left: 10px;">篩選</button>
                    <a href="?page=buygo-settings&tab=workflow" class="button" style="margin-left: 5px;">清除</a>
                </form>
            </div>
            
            <!-- 日誌列表 -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 150px;">時間</th>
                        <th style="width: 150px;">事件類型</th>
                        <th style="width: 100px;">使用者 ID</th>
                        <th style="width: 150px;">LINE UID</th>
                        <th>詳細資料</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($webhook_logs)): ?>
                        <tr>
                            <td colspan="5" class="no-logs" style="text-align: center; padding: 20px;">
                                沒有找到符合條件的記錄
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($webhook_logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log['created_at'] ?? '-'); ?></td>
                                <td>
                                    <span class="event-type-badge event-type-<?php echo esc_attr($log['event_type'] ?? ''); ?>" style="
                                        display: inline-block;
                                        padding: 3px 8px;
                                        border-radius: 3px;
                                        font-size: 12px;
                                        font-weight: 500;
                                        <?php
                                        $event_type = $log['event_type'] ?? '';
                                        if ($event_type === 'error') {
                                            echo 'background: #dc3232; color: white;';
                                        } elseif ($event_type === 'product_created') {
                                            echo 'background: #46b450; color: white;';
                                        } elseif ($event_type === 'image_uploaded') {
                                            echo 'background: #0073aa; color: white;';
                                        } else {
                                            echo 'background: #666; color: white;';
                                        }
                                        ?>
                                    ">
                                        <?php echo esc_html($log['event_type'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log['user_id'] ?? '-'); ?></td>
                                <td style="font-family: monospace; font-size: 11px;">
                                    <?php echo esc_html($log['line_user_id'] ? substr($log['line_user_id'], 0, 20) . '...' : '-'); ?>
                                </td>
                                <td>
                                    <?php
                                    $event_data = $log['event_data'] ?? array();
                                    if (is_array($event_data) && !empty($event_data)) {
                                        echo '<details style="cursor: pointer;">';
                                        echo '<summary style="color: #0073aa; text-decoration: underline;">查看詳細資料</summary>';
                                        echo '<pre style="background: #f5f5f5; padding: 10px; margin-top: 5px; border-radius: 3px; font-size: 11px; max-height: 200px; overflow: auto;">';
                                        echo esc_html(wp_json_encode($event_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                        echo '</pre>';
                                        echo '</details>';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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
            
            // 取得賣家類型
            $seller_type = get_user_meta($user->ID, 'buygo_seller_type', true);
            if (empty($seller_type)) {
                $seller_type = 'test'; // 預設為測試賣家
            }

            // 取得商品限制數量 (0 = 無限制)
            $product_limit = get_user_meta($user->ID, 'buygo_product_limit', true);
            if ($product_limit === '') {
                $product_limit = 2; // 預設為 2 個商品
            }

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
                // 賣家：查詢小幫手數量
                $helper_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$helpers_table} WHERE seller_id = %d",
                    $user->ID
                ));
                if ($helper_count > 0) {
                    $binding_info = "小幫手數量：{$helper_count} 個";
                } else {
                    $binding_info = '無小幫手';
                }
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
     * 渲染測試工具 Tab
     */
    private function render_test_tools_tab(): void
    {
        global $wpdb;

        // 處理清除請求
        if (isset($_POST['reset_test_data']) && wp_verify_nonce($_POST['_wpnonce'], 'buygo_reset_test_data')) {
            if (isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'YES') {
                $result = $this->execute_reset_test_data();

                if ($result['success']) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>請輸入 YES 確認清除操作</p></div>';
            }
        }

        // 取得當前資料統計
        $stats = $this->get_test_data_stats();

        ?>
        <div class="wrap">
            <h2>測試工具</h2>
            <p class="description">⚠️ 警告：此功能僅供測試環境使用！清除操作無法復原！</p>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h3>當前資料統計</h3>
                <table class="widefat" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th style="width: 50%;">資料類型</th>
                            <th style="width: 50%; text-align: right;">數量</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>WordPress 商品 (wp_posts)</td>
                            <td style="text-align: right;"><strong><?php echo number_format($stats['wp_products']); ?></strong></td>
                        </tr>
                        <tr>
                            <td>FluentCart 商品 (wp_fct_products)</td>
                            <td style="text-align: right;"><strong><?php echo number_format($stats['fct_products']); ?></strong></td>
                        </tr>
                        <tr>
                            <td>訂單 (wp_fct_orders)</td>
                            <td style="text-align: right;"><strong><?php echo number_format($stats['orders']); ?></strong></td>
                        </tr>
                        <tr>
                            <td style="padding-left: 30px;">└ 父訂單</td>
                            <td style="text-align: right;"><?php echo number_format($stats['parent_orders']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding-left: 30px;">└ 子訂單 (拆分)</td>
                            <td style="text-align: right;"><?php echo number_format($stats['child_orders']); ?></td>
                        </tr>
                        <tr>
                            <td>訂單項目 (wp_fct_order_items)</td>
                            <td style="text-align: right;"><strong><?php echo number_format($stats['order_items']); ?></strong></td>
                        </tr>
                        <tr>
                            <td>出貨單 (wp_buygo_shipments)</td>
                            <td style="text-align: right;"><strong><?php echo number_format($stats['shipments']); ?></strong></td>
                        </tr>
                        <tr>
                            <td>出貨單項目 (wp_buygo_shipment_items)</td>
                            <td style="text-align: right;"><strong><?php echo number_format($stats['shipment_items']); ?></strong></td>
                        </tr>
                        <tr style="background-color: #f0f0f1;">
                            <td><strong>客戶 (wp_fct_customers)</strong></td>
                            <td style="text-align: right;"><strong style="color: #46b450;"><?php echo number_format($stats['customers']); ?> (保留)</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card" style="max-width: 800px; margin-top: 20px; border-left: 4px solid #dc3232;">
                <h3 style="color: #dc3232;">🗑️ 清除測試資料</h3>
                <p class="description">
                    此操作將清除以下資料：<br>
                    • 所有 WordPress 商品 (wp_posts)<br>
                    • 所有 FluentCart 商品 (wp_fct_products)<br>
                    • 所有訂單和訂單項目 (wp_fct_orders, wp_fct_order_items)<br>
                    • 所有出貨單和出貨單項目 (wp_buygo_shipments, wp_buygo_shipment_items)<br>
                    • 商品相關的 meta 資料和分類關聯<br>
                    <br>
                    <strong style="color: #dc3232;">⚠️ 客戶資料將會保留</strong>
                </p>

                <form method="post" action="" id="reset-form" style="margin-top: 20px;">
                    <?php wp_nonce_field('buygo_reset_test_data'); ?>

                    <div style="margin-bottom: 15px;">
                        <label for="confirm_reset" style="display: block; margin-bottom: 5px;">
                            <strong>請輸入 "YES" 確認清除操作：</strong>
                        </label>
                        <input
                            type="text"
                            id="confirm_reset"
                            name="confirm_reset"
                            class="regular-text"
                            placeholder="請輸入 YES"
                            autocomplete="off"
                            style="border: 2px solid #dc3232;"
                        />
                    </div>

                    <p class="submit">
                        <button
                            type="submit"
                            name="reset_test_data"
                            id="reset-btn"
                            class="button button-primary"
                            style="background-color: #dc3232; border-color: #dc3232;"
                            disabled
                        >
                            🗑️ 確認清除所有測試資料
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // 啟用/停用按鈕
            $('#confirm_reset').on('input', function() {
                var value = $(this).val();
                if (value === 'YES') {
                    $('#reset-btn').prop('disabled', false);
                } else {
                    $('#reset-btn').prop('disabled', true);
                }
            });

            // 提交前二次確認
            $('#reset-form').on('submit', function(e) {
                var confirmText = $('#confirm_reset').val();
                if (confirmText !== 'YES') {
                    e.preventDefault();
                    alert('請輸入 YES 確認清除操作');
                    return false;
                }

                var confirm = window.confirm(
                    '⚠️ 最後確認 ⚠️\n\n' +
                    '此操作將會清除所有測試資料，包括：\n' +
                    '• 所有商品\n' +
                    '• 所有訂單\n' +
                    '• 所有出貨單\n\n' +
                    '此操作無法復原！\n\n' +
                    '確定要繼續嗎？'
                );

                if (!confirm) {
                    e.preventDefault();
                    return false;
                }
            });
        });
        </script>
        <?php
    }

    /**
     * 取得測試資料統計
     */
    private function get_test_data_stats(): array
    {
        global $wpdb;

        $stats = [
            'wp_products' => 0,
            'fct_products' => 0,
            'orders' => 0,
            'parent_orders' => 0,
            'child_orders' => 0,
            'order_items' => 0,
            'shipments' => 0,
            'shipment_items' => 0,
            'customers' => 0,
        ];

        // 輔助函數：安全查詢資料表數量（先檢查資料表是否存在）
        $safe_count = function($table_name, $where = '') use ($wpdb) {
            $full_table_name = $wpdb->prefix . $table_name;
            $table_exists = $wpdb->get_var(
                $wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name)
            );
            if (!$table_exists) {
                return 0;
            }
            $query = "SELECT COUNT(*) FROM {$full_table_name}";
            if ($where) {
                $query .= " WHERE {$where}";
            }
            return (int) $wpdb->get_var($query);
        };

        // WordPress 商品數量 (posts 資料表一定存在)
        $stats['wp_products'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'product'"
        );

        // FluentCart 商品數量 (存在 wp_posts 中，post_type = 'fluent-products')
        $stats['fct_products'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'fluent-products'"
        );

        // 訂單數量（安全查詢）
        $stats['orders'] = $safe_count('fct_orders');

        // 父訂單數量
        $stats['parent_orders'] = $safe_count('fct_orders', 'parent_id IS NULL');

        // 子訂單數量
        $stats['child_orders'] = $safe_count('fct_orders', "parent_id IS NOT NULL AND type = 'split'");

        // 訂單項目數量
        $stats['order_items'] = $safe_count('fct_order_items');

        // 出貨單數量
        $stats['shipments'] = $safe_count('buygo_shipments');

        // 出貨單項目數量
        $stats['shipment_items'] = $safe_count('buygo_shipment_items');

        // 客戶數量
        $stats['customers'] = $safe_count('fct_customers');

        return $stats;
    }

    /**
     * 執行清除測試資料
     */
    private function execute_reset_test_data(): array
    {
        global $wpdb;

        // 輔助函數：檢查資料表是否存在
        $table_exists = function($table_name) use ($wpdb) {
            $full_table_name = $wpdb->prefix . $table_name;
            return $wpdb->get_var(
                $wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name)
            ) === $full_table_name;
        };

        // 輔助函數：安全刪除資料表內容
        $safe_delete = function($table_name) use ($wpdb, $table_exists) {
            if ($table_exists($table_name)) {
                $wpdb->query("DELETE FROM {$wpdb->prefix}{$table_name}");
                return true;
            }
            return false;
        };

        try {
            // 開始交易
            $wpdb->query('START TRANSACTION');

            // 1. 清除出貨單項目
            $safe_delete('buygo_shipment_items');

            // 2. 清除出貨單
            $safe_delete('buygo_shipments');

            // 3. 清除訂單項目
            $safe_delete('fct_order_items');

            // 4. 清除訂單
            $safe_delete('fct_orders');

            // 5. 清除 FluentCart 商品變體 (如果表存在)
            $safe_delete('fct_product_variations');

            // 6. 清除 FluentCart 商品 (如果表存在)
            $safe_delete('fct_products');

            // 7. 獲取所有 WordPress 商品 ID (post_type = 'product')
            $product_ids = $wpdb->get_col(
                "SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'product'"
            );

            // 8. 清除 WordPress 商品的 meta 資料
            if (!empty($product_ids)) {
                $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}postmeta WHERE post_id IN ($placeholders)",
                        ...$product_ids
                    )
                );

                // 9. 清除商品分類關聯
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}term_relationships WHERE object_id IN ($placeholders)",
                        ...$product_ids
                    )
                );

                // 10. 清除 WordPress 商品
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}posts WHERE ID IN ($placeholders)",
                        ...$product_ids
                    )
                );
            }

            // 11. 獲取所有 FluentCart 商品 ID (post_type = 'fluent-products')
            $fluent_product_ids = $wpdb->get_col(
                "SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'fluent-products'"
            );

            // 12. 清除 FluentCart 商品的 meta 資料和商品本身
            if (!empty($fluent_product_ids)) {
                $placeholders = implode(',', array_fill(0, count($fluent_product_ids), '%d'));

                // 清除 meta
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}postmeta WHERE post_id IN ($placeholders)",
                        ...$fluent_product_ids
                    )
                );

                // 清除分類關聯
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}term_relationships WHERE object_id IN ($placeholders)",
                        ...$fluent_product_ids
                    )
                );

                // 清除 FluentCart 商品
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}posts WHERE ID IN ($placeholders)",
                        ...$fluent_product_ids
                    )
                );
            }

            // 提交交易
            $wpdb->query('COMMIT');

            return [
                'success' => true,
                'message' => '✅ 所有測試資料已成功清除！現在可以從零開始測試。'
            ];

        } catch (\Exception $e) {
            // 回滾交易
            $wpdb->query('ROLLBACK');

            return [
                'success' => false,
                'message' => '❌ 清除失敗：' . $e->getMessage()
            ];
        }
    }

    /**
     * 渲染除錯中心 Tab
     */
    private function render_debug_center_tab(): void
    {
        global $wpdb;

        // 處理 SQL 查詢
        $sql_result = null;
        $sql_error = null;
        $sql_query = '';

        if (isset($_POST['execute_sql']) && wp_verify_nonce($_POST['_wpnonce'], 'buygo_debug_sql')) {
            $sql_query = stripslashes($_POST['sql_query'] ?? '');

            if (!empty($sql_query)) {
                try {
                    // 安全檢查：只允許 SELECT 查詢
                    $sql_upper = strtoupper(trim($sql_query));
                    if (!preg_match('/^SELECT\s/i', $sql_upper)) {
                        $sql_error = '⚠️ 安全限制：只允許 SELECT 查詢';
                    } else {
                        $results = $wpdb->get_results($sql_query, ARRAY_A);

                        if ($wpdb->last_error) {
                            $sql_error = $wpdb->last_error;
                        } else {
                            $sql_result = $results;
                        }
                    }
                } catch (\Exception $e) {
                    $sql_error = $e->getMessage();
                }
            }
        }

        ?>
        <div class="wrap">
            <h2>🔍 除錯中心</h2>
            <p class="description">快速查詢資料庫，方便除錯和測試。</p>

            <!-- 常用查詢快捷按鈕 -->
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h3>📋 常用查詢</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 15px;">
                    <button type="button" class="button" onclick="setQuery('products')">
                        📦 查看最新商品
                    </button>
                    <button type="button" class="button" onclick="setQuery('orders')">
                        🛒 查看最新訂單
                    </button>
                    <button type="button" class="button" onclick="setQuery('child_orders')">
                        🔗 查看子訂單
                    </button>
                    <button type="button" class="button" onclick="setQuery('shipments')">
                        📦 查看出貨單
                    </button>
                    <button type="button" class="button" onclick="setQuery('variations')">
                        🏷️ 查看商品變體
                    </button>
                    <button type="button" class="button" onclick="setQuery('customers')">
                        👥 查看客戶
                    </button>
                    <button type="button" class="button" onclick="setQuery('tables')">
                        🗄️ 查看所有資料表
                    </button>
                </div>
            </div>

            <!-- SQL 查詢編輯器 -->
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h3>💻 SQL 查詢編輯器</h3>
                <form method="post" action="">
                    <?php wp_nonce_field('buygo_debug_sql'); ?>

                    <div style="margin-top: 15px;">
                        <textarea
                            name="sql_query"
                            id="sql_query"
                            rows="8"
                            style="width: 100%; font-family: monospace; font-size: 13px; padding: 10px;"
                            placeholder="輸入 SQL 查詢... (僅支援 SELECT)"
                        ><?php echo esc_textarea($sql_query); ?></textarea>
                    </div>

                    <div style="margin-top: 10px;">
                        <button type="submit" name="execute_sql" class="button button-primary">
                            ▶️ 執行查詢
                        </button>
                        <button type="button" class="button" onclick="clearQuery()">
                            🗑️ 清空
                        </button>
                        <button type="button" class="button" onclick="copyQuery()">
                            📋 複製結果
                        </button>
                        <span style="color: #666; margin-left: 15px;">
                            ℹ️ 提示：表名前綴為 <code><?php echo esc_html($wpdb->prefix); ?></code>
                        </span>
                    </div>
                </form>
            </div>

            <!-- 查詢結果 -->
            <?php if ($sql_error): ?>
                <div class="card" style="max-width: 100%; margin-top: 20px; border-left: 4px solid #dc3232;">
                    <h3 style="color: #dc3232;">❌ 查詢錯誤</h3>
                    <pre style="background: #f8d7da; padding: 15px; border-radius: 4px; overflow-x: auto;"><?php echo esc_html($sql_error); ?></pre>
                </div>
            <?php elseif ($sql_result !== null): ?>
                <div class="card" style="max-width: 100%; margin-top: 20px; border-left: 4px solid #46b450;">
                    <h3 style="color: #46b450;">✅ 查詢結果 (<?php echo count($sql_result); ?> 筆)</h3>

                    <?php if (empty($sql_result)): ?>
                        <p style="color: #666;">查詢結果為空</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="widefat" style="margin-top: 15px;">
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($sql_result[0]) as $column): ?>
                                            <th><?php echo esc_html($column); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sql_result as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $value): ?>
                                                <td>
                                                    <?php
                                                    if (strlen($value) > 100) {
                                                        echo '<details><summary>' . esc_html(substr($value, 0, 100)) . '...</summary><pre style="white-space: pre-wrap; word-wrap: break-word;">' . esc_html($value) . '</pre></details>';
                                                    } else {
                                                        echo esc_html($value);
                                                    }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- JSON 格式 (用於複製) -->
                        <details style="margin-top: 20px;">
                            <summary style="cursor: pointer; font-weight: 600;">📄 JSON 格式 (點擊展開)</summary>
                            <pre id="json-result" style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; margin-top: 10px;"><?php echo json_encode($sql_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- 出貨流程診斷 -->
            <div class="card" style="max-width: 100%; margin-top: 20px; border-left: 4px solid #2271b1;">
                <h3>🔍 出貨流程診斷</h3>
                <p class="description">快速檢查出貨單、備貨、訂單狀態等後端數據是否正常。</p>

                <?php
                // === 0. FluentCart 資料表檢查 ===
                $fct_tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}fct%'", ARRAY_N);
                ?>
                <details style="margin-top: 15px; background: #f0f6fc; padding: 10px; border-radius: 4px;">
                    <summary style="font-weight: 600;">🗄️ FluentCart 資料表檢查 (<?php echo count($fct_tables); ?> 個表)</summary>
                    <table class="widefat" style="margin-top: 10px;">
                        <thead><tr><th>資料表</th><th>資料筆數</th></tr></thead>
                        <tbody>
                            <?php foreach ($fct_tables as $table):
                                $table_name = $table[0];
                                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
                            ?>
                                <tr>
                                    <td><code><?php echo esc_html($table_name); ?></code></td>
                                    <td><?php echo $count; ?> <?php echo $count == 0 ? '<span style="color: #dc3232;">(空)</span>' : ''; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top: 10px; font-size: 12px; color: #666;">
                        <strong>資料庫前綴：</strong><code><?php echo esc_html($wpdb->prefix); ?></code>
                    </p>
                </details>

                <?php
                // === 1. 出貨單資料 ===
                $shipments = $wpdb->get_results(
                    "SELECT * FROM {$wpdb->prefix}buygo_shipments ORDER BY id DESC LIMIT 5",
                    ARRAY_A
                );
                ?>
                <details style="margin-top: 15px;">
                    <summary>📦 出貨單資料 (<?php echo count($shipments); ?> 筆)</summary>
                    <?php if (!empty($shipments)): ?>
                        <table class="widefat" style="margin-top: 10px;">
                            <thead><tr><th>ID</th><th>出貨單號</th><th>客戶ID</th><th>狀態</th><th>建立時間</th></tr></thead>
                            <tbody>
                                <?php foreach ($shipments as $s): ?>
                                    <tr>
                                        <td><?php echo esc_html($s['id']); ?></td>
                                        <td><code><?php echo esc_html($s['shipment_number']); ?></code></td>
                                        <td><?php echo esc_html($s['customer_id']); ?></td>
                                        <td><span style="padding: 2px 8px; border-radius: 3px; background: <?php echo $s['status'] === 'shipped' ? '#d4edda' : '#fff3cd'; ?>;"><?php echo esc_html($s['status']); ?></span></td>
                                        <td><?php echo esc_html($s['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #856404; margin-top: 10px;">⚠️ 沒有出貨單資料</p>
                    <?php endif; ?>
                </details>

                <?php
                // === 2. 出貨單項目資料 ===
                $shipment_items = $wpdb->get_results(
                    "SELECT si.*, s.shipment_number
                     FROM {$wpdb->prefix}buygo_shipment_items si
                     LEFT JOIN {$wpdb->prefix}buygo_shipments s ON si.shipment_id = s.id
                     ORDER BY si.id DESC LIMIT 10",
                    ARRAY_A
                );
                $shipment_item_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}buygo_shipment_items");
                ?>
                <details style="margin-top: 10px;">
                    <summary style="<?php echo empty($shipment_items) ? 'color: #dc3232;' : ''; ?>">📋 出貨單項目 (總數: <?php echo $shipment_item_count; ?>)</summary>
                    <?php if (!empty($shipment_items)): ?>
                        <table class="widefat" style="margin-top: 10px;">
                            <thead><tr><th>ID</th><th>出貨單號</th><th>order_id</th><th>order_item_id</th><th>product_id</th><th>數量</th></tr></thead>
                            <tbody>
                                <?php foreach ($shipment_items as $si): ?>
                                    <tr>
                                        <td><?php echo esc_html($si['id']); ?></td>
                                        <td><code><?php echo esc_html($si['shipment_number']); ?></code></td>
                                        <td><?php echo esc_html($si['order_id']); ?></td>
                                        <td><?php echo esc_html($si['order_item_id']); ?></td>
                                        <td><?php echo esc_html($si['product_id']); ?></td>
                                        <td><?php echo esc_html($si['quantity']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #dc3232; margin-top: 10px;">❌ 沒有出貨單項目資料 - 這就是 Excel 匯出空白的原因！</p>
                    <?php endif; ?>
                </details>

                <?php
                // === 3. 訂單資料 ===
                $total_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders");
                // 使用 SELECT * 避免欄位名稱不匹配
                $orders = $wpdb->get_results(
                    "SELECT * FROM {$wpdb->prefix}fct_orders ORDER BY id DESC LIMIT 10",
                    ARRAY_A
                );
                $pending_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders WHERE status = 'pending'");
                $processing_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders WHERE status = 'processing'");

                // 檢查孤兒記錄：出貨單項目引用的 order_id 是否存在
                $orphan_check = $wpdb->get_results(
                    "SELECT DISTINCT si.order_id,
                            (SELECT COUNT(*) FROM {$wpdb->prefix}fct_orders WHERE id = si.order_id) as order_exists
                     FROM {$wpdb->prefix}buygo_shipment_items si",
                    ARRAY_A
                );
                $orphan_orders = array_filter($orphan_check, function($item) {
                    return $item['order_exists'] == 0;
                });
                ?>
                <details style="margin-top: 10px;">
                    <summary style="<?php echo !empty($orphan_orders) ? 'color: #dc3232; font-weight: bold;' : ''; ?>">
                        🛒 訂單資料 (總數: <?php echo $total_orders; ?>, pending: <?php echo $pending_orders; ?>, processing: <?php echo $processing_orders; ?>)
                        <?php if (!empty($orphan_orders)): ?> ⚠️ 發現孤兒記錄<?php endif; ?>
                    </summary>

                    <?php if (!empty($orphan_orders)): ?>
                        <div style="background: #f8d7da; padding: 10px; border-radius: 4px; margin-top: 10px;">
                            <strong>⚠️ 孤兒記錄檢查：</strong>
                            <p style="margin: 5px 0;">出貨單項目引用了以下不存在的 order_id：</p>
                            <ul style="margin: 5px 0;">
                                <?php foreach ($orphan_orders as $orphan): ?>
                                    <li><code>order_id: <?php echo esc_html($orphan['order_id']); ?></code> - 在 fct_orders 表中不存在！</li>
                                <?php endforeach; ?>
                            </ul>
                            <p style="font-size: 12px; color: #721c24;">這可能是因為訂單被刪除，或是 order_id 對應錯誤。</p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($orders)): ?>
                        <p style="margin: 10px 0; font-size: 12px; color: #666;">
                            <strong>fct_orders 欄位：</strong>
                            <code><?php echo esc_html(implode(', ', array_keys($orders[0]))); ?></code>
                        </p>
                        <table class="widefat" style="margin-top: 10px;">
                            <thead><tr><th>ID</th><th>parent_id</th><th>customer_id</th><th>狀態</th><th>金額</th><th>建立時間</th></tr></thead>
                            <tbody>
                                <?php foreach ($orders as $o):
                                    $status = $o['status'] ?? $o['order_status'] ?? 'unknown';
                                    $status_color = $status === 'completed' ? '#d4edda' : ($status === 'processing' ? '#d1ecf1' : ($status === 'pending' ? '#fff3cd' : '#f8d7da'));
                                ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($o['id']); ?></strong></td>
                                        <td><?php echo esc_html($o['parent_id'] ?? '-'); ?></td>
                                        <td><?php echo esc_html($o['customer_id'] ?? '-'); ?></td>
                                        <td><span style="padding: 2px 8px; border-radius: 3px; background: <?php echo $status_color; ?>;"><?php echo esc_html($status); ?></span></td>
                                        <td>¥<?php echo number_format((($o['total_amount'] ?? $o['total'] ?? 0)) / 100); ?></td>
                                        <td style="font-size: 11px;"><?php echo esc_html($o['created_at'] ?? $o['date_created'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <details style="margin-top: 10px; background: #f9f9f9; padding: 8px; border-radius: 4px;">
                            <summary style="cursor: pointer; font-size: 12px;">查看完整資料結構 (Debug)</summary>
                            <pre style="font-size: 11px; max-height: 200px; overflow: auto; margin-top: 8px;"><?php echo esc_html(json_encode($orders[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                        </details>
                    <?php else: ?>
                        <p style="color: #856404; margin-top: 10px;">⚠️ fct_orders 表中沒有訂單資料</p>
                    <?php endif; ?>
                </details>

                <?php
                // === 4. Debug Log ===
                $log_table = $wpdb->prefix . 'buygo_debug_logs';
                $log_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$log_table}'") === $log_table;
                $logs = $log_table_exists ? $wpdb->get_results("SELECT * FROM {$log_table} ORDER BY id DESC LIMIT 10", ARRAY_A) : [];
                ?>
                <details style="margin-top: 10px;">
                    <summary>📜 Debug Log (最近 10 筆)</summary>
                    <?php if (!empty($logs)): ?>
                        <table class="widefat" style="margin-top: 10px;">
                            <thead><tr><th>ID</th><th>等級</th><th>模組</th><th>訊息</th><th>時間</th></tr></thead>
                            <tbody>
                                <?php foreach ($logs as $log):
                                    $level_color = $log['level'] === 'error' ? '#f8d7da' : ($log['level'] === 'warning' ? '#fff3cd' : '#d1ecf1');
                                ?>
                                    <tr>
                                        <td><?php echo esc_html($log['id']); ?></td>
                                        <td><span style="padding: 2px 8px; border-radius: 3px; background: <?php echo $level_color; ?>;"><?php echo esc_html($log['level']); ?></span></td>
                                        <td><code><?php echo esc_html($log['module']); ?></code></td>
                                        <td>
                                            <?php echo esc_html($log['message']); ?>
                                            <?php if (!empty($log['data'])): ?>
                                                <details style="margin-top: 5px;"><summary style="cursor: pointer; color: #2271b1; font-size: 12px;">查看資料</summary><pre style="margin-top: 5px; font-size: 11px; max-height: 150px; overflow: auto;"><?php echo esc_html($log['data']); ?></pre></details>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 11px;"><?php echo esc_html($log['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php elseif ($log_table_exists): ?>
                        <p style="color: #666; margin-top: 10px;">Log 表存在但沒有記錄</p>
                    <?php else: ?>
                        <p style="color: #856404; margin-top: 10px;">⚠️ Debug Log 表不存在，請重新啟用外掛</p>
                    <?php endif; ?>
                    <p style="margin-top: 10px; font-size: 12px;"><strong>Log 檔案：</strong><code>/wp-content/uploads/buygo-plus-one-debug.log</code></p>
                </details>

                <!-- 診斷總結 -->
                <div style="margin-top: 15px; padding: 15px; background: #f0f0f1; border-radius: 4px;">
                    <strong>📊 診斷總結</strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <li>出貨單總數：<?php echo count($shipments); ?></li>
                        <li>出貨單項目總數：<?php echo $shipment_item_count; ?> <?php echo $shipment_item_count == 0 ? '<span style="color: #dc3232;">(❌ 空的！這會導致匯出問題)</span>' : '<span style="color: #46b450;">(✅ 正常)</span>'; ?></li>
                        <li>訂單總數：<?php echo count($orders); ?> (pending: <?php echo $pending_orders; ?>, processing: <?php echo $processing_orders; ?>)</li>
                    </ul>
                </div>
            </div>

            <!-- 系統資訊 -->
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h3>ℹ️ 系統資訊</h3>
                <table class="widefat" style="margin-top: 15px;">
                    <tbody>
                        <tr>
                            <td style="width: 30%;"><strong>資料庫前綴</strong></td>
                            <td><code><?php echo esc_html($wpdb->prefix); ?></code></td>
                        </tr>
                        <tr>
                            <td><strong>WordPress 版本</strong></td>
                            <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                        </tr>
                        <tr>
                            <td><strong>PHP 版本</strong></td>
                            <td><?php echo esc_html(PHP_VERSION); ?></td>
                        </tr>
                        <tr>
                            <td><strong>MySQL 版本</strong></td>
                            <td><?php echo esc_html($wpdb->db_version()); ?></td>
                        </tr>
                        <tr>
                            <td><strong>外掛版本</strong></td>
                            <td><?php echo esc_html(BUYGO_PLUS_ONE_VERSION ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Log 檔案位置</strong></td>
                            <td><code>/wp-content/uploads/buygo-plus-one-debug.log</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        // 預設查詢模板
        const queries = {
            products: `SELECT
    id,
    post_id,
    variation_title,
    item_price / 100 AS '售價(元)',
    compare_price / 100 AS '原價(元)',
    payment_type,
    total_stock,
    available,
    created_at
FROM <?php echo $wpdb->prefix; ?>fct_product_variations
ORDER BY id DESC
LIMIT 10`,

            orders: `SELECT
    id,
    parent_id,
    invoice_no,
    type,
    status,
    total_amount / 100 AS '金額(元)',
    customer_id,
    created_at
FROM <?php echo $wpdb->prefix; ?>fct_orders
ORDER BY id DESC
LIMIT 10`,

            child_orders: `SELECT
    o.id,
    o.parent_id,
    o.invoice_no,
    o.type,
    o.status,
    o.total_amount / 100 AS '金額(元)',
    oi.quantity,
    o.created_at
FROM <?php echo $wpdb->prefix; ?>fct_orders o
LEFT JOIN <?php echo $wpdb->prefix; ?>fct_order_items oi ON o.id = oi.order_id
WHERE o.type = 'split'
ORDER BY o.created_at DESC
LIMIT 10`,

            shipments: `SELECT
    s.id,
    s.shipment_number,
    s.status,
    s.tracking_number,
    COUNT(si.id) AS item_count,
    s.created_at
FROM <?php echo $wpdb->prefix; ?>buygo_shipments s
LEFT JOIN <?php echo $wpdb->prefix; ?>buygo_shipment_items si ON s.id = si.shipment_id
GROUP BY s.id
ORDER BY s.id DESC
LIMIT 10`,

            variations: `SELECT
    v.id,
    v.post_id,
    v.variation_title,
    v.item_price / 100 AS '售價(元)',
    v.compare_price / 100 AS '原價(元)',
    v.payment_type,
    v.other_info,
    v.created_at
FROM <?php echo $wpdb->prefix; ?>fct_product_variations v
ORDER BY v.id DESC
LIMIT 5`,

            customers: `SELECT
    id,
    user_id,
    email,
    first_name,
    last_name,
    created_at
FROM <?php echo $wpdb->prefix; ?>fct_customers
ORDER BY id DESC
LIMIT 10`,

            tables: `SHOW TABLES LIKE '<?php echo $wpdb->prefix; ?>%'`
        };

        function setQuery(type) {
            document.getElementById('sql_query').value = queries[type];
        }

        function clearQuery() {
            document.getElementById('sql_query').value = '';
        }

        function copyQuery() {
            const jsonResult = document.getElementById('json-result');
            if (jsonResult) {
                const text = jsonResult.textContent;
                navigator.clipboard.writeText(text).then(() => {
                    alert('✅ 結果已複製到剪貼板！');
                }).catch(() => {
                    alert('❌ 複製失敗，請手動選取');
                });
            } else {
                alert('⚠️ 沒有可複製的結果');
            }
        }
        </script>

        <style>
        .card {
            background: white;
            padding: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        .card h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        details summary {
            font-weight: 600;
            cursor: pointer;
            padding: 10px;
            background: #f0f0f1;
            border-radius: 4px;
        }
        details summary:hover {
            background: #e0e0e1;
        }
        </style>
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
            <h1>Line 模板</h1>
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
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'buygo_settings_nonce')) {
            wp_send_json_error(['message' => '安全驗證失敗']);
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '權限不足']);
            return;
        }
        
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : null;
        $result = SettingsService::test_line_connection($token);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: 更新賣家類型
     */
    public function ajax_update_seller_type(): void
    {
        // 驗證 nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'buygo-settings')) {
            wp_send_json_error('無效的請求');
            return;
        }

        // 權限檢查
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
            return;
        }

        // 取得參數
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $seller_type = isset($_POST['seller_type']) ? sanitize_text_field($_POST['seller_type']) : '';

        // 驗證參數
        if ($user_id <= 0) {
            wp_send_json_error('無效的使用者 ID');
            return;
        }

        if (!in_array($seller_type, ['test', 'real'], true)) {
            wp_send_json_error('無效的賣家類型');
            return;
        }

        // 更新 user meta
        $result = update_user_meta($user_id, 'buygo_seller_type', $seller_type);

        if ($result !== false) {
            wp_send_json_success([
                'message' => '賣家類型已更新',
                'user_id' => $user_id,
                'seller_type' => $seller_type
            ]);
        } else {
            wp_send_json_error('更新失敗');
        }
    }

    /**
     * AJAX: 更新商品限制數量
     */
    public function ajax_update_product_limit(): void
    {
        // 驗證 nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'buygo-settings')) {
            wp_send_json_error('無效的請求');
            return;
        }

        // 權限檢查
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
            return;
        }

        // 取得參數
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $product_limit = isset($_POST['product_limit']) ? intval($_POST['product_limit']) : 0;

        // 驗證參數
        if ($user_id <= 0) {
            wp_send_json_error('無效的使用者 ID');
            return;
        }

        if ($product_limit < 0) {
            wp_send_json_error('商品限制數量不能為負數');
            return;
        }

        // 更新 user meta
        $result = update_user_meta($user_id, 'buygo_product_limit', $product_limit);

        if ($result !== false) {
            wp_send_json_success([
                'message' => '商品限制已更新',
                'user_id' => $user_id,
                'product_limit' => $product_limit
            ]);
        } else {
            wp_send_json_error('更新失敗');
        }
    }
}
