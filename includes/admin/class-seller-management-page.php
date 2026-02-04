<?php

namespace BuyGoPlus\Admin;

use BuyGoPlus\Services\SellerApplicationService;
use BuyGoPlus\Services\SettingsService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Seller Management Page - 賣家管理後台頁面
 *
 * Phase 27: 提供 WP 後台的賣家申請管理和賣家列表功能
 *
 * ⚠️ 已棄用 (2026-02-04)：
 * 此頁面功能已統一整合到「角色權限設定」頁面 (buygo-settings&tab=roles)。
 * 新流程：用戶從 FluentCart 購買「0 元賣家商品」後自動賦予 buygo_admin 角色。
 *
 * 保留此檔案僅供參考，但已從 Plugin::register_hooks() 中移除註冊。
 *
 * @package BuyGoPlus\Admin
 * @version 1.0.0
 * @deprecated 2026-02-04
 */
class SellerManagementPage
{
    private $service;

    public function __construct()
    {
        $this->service = new SellerApplicationService();
        add_action('admin_menu', [$this, 'add_admin_menu'], 25);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

        // AJAX handlers
        add_action('wp_ajax_buygo_approve_seller_application', [$this, 'ajax_approve_application']);
        add_action('wp_ajax_buygo_reject_seller_application', [$this, 'ajax_reject_application']);
        add_action('wp_ajax_buygo_upgrade_seller', [$this, 'ajax_upgrade_seller']);
    }

    /**
     * 添加管理選單
     */
    public function add_admin_menu(): void
    {
        // 子選單：賣家管理
        add_submenu_page(
            'buygo-plus-one',
            '賣家管理',
            '賣家管理',
            'manage_options',
            'buygo-sellers',
            [$this, 'render_page']
        );
    }

    /**
     * 載入腳本和樣式
     */
    public function enqueue_scripts($hook): void
    {
        if ($hook !== 'buygo1_page_buygo-sellers') {
            return;
        }

        wp_enqueue_style(
            'buygo-seller-management',
            BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/css/seller-management.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'buygo-seller-management',
            BUYGO_PLUS_ONE_PLUGIN_URL . 'admin/js/seller-management.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('buygo-seller-management', 'buygoSellerAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('buygo_seller_admin'),
        ]);
    }

    /**
     * 渲染頁面
     */
    public function render_page(): void
    {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'applications';

        ?>
        <div class="wrap">
            <h1>賣家管理</h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=buygo-sellers&tab=applications" class="nav-tab <?php echo $tab === 'applications' ? 'nav-tab-active' : ''; ?>">
                    賣家申請
                    <?php
                    $pending_count = count($this->service->getApplications(['status' => SellerApplicationService::STATUS_PENDING]));
                    if ($pending_count > 0) {
                        echo '<span class="awaiting-mod">' . $pending_count . '</span>';
                    }
                    ?>
                </a>
                <a href="?page=buygo-sellers&tab=sellers" class="nav-tab <?php echo $tab === 'sellers' ? 'nav-tab-active' : ''; ?>">
                    賣家列表
                </a>
            </nav>

            <div class="tab-content" style="margin-top: 20px;">
                <?php
                if ($tab === 'applications') {
                    $this->render_applications_tab();
                } else {
                    $this->render_sellers_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * 渲染申請列表分頁
     */
    private function render_applications_tab(): void
    {
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $filters = $status_filter ? ['status' => $status_filter] : [];
        $applications = $this->service->getApplications($filters);

        ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="status_filter" id="status-filter">
                    <option value="">全部狀態</option>
                    <option value="<?php echo SellerApplicationService::STATUS_PENDING; ?>" <?php selected($status_filter, SellerApplicationService::STATUS_PENDING); ?>>待審核</option>
                    <option value="<?php echo SellerApplicationService::STATUS_APPROVED; ?>" <?php selected($status_filter, SellerApplicationService::STATUS_APPROVED); ?>>已批准</option>
                    <option value="<?php echo SellerApplicationService::STATUS_REJECTED; ?>" <?php selected($status_filter, SellerApplicationService::STATUS_REJECTED); ?>>已拒絕</option>
                </select>
                <input type="button" class="button" value="篩選" onclick="filterApplications()">
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="column-user">用戶</th>
                    <th scope="col" class="column-shop">商店資料</th>
                    <th scope="col" class="column-status">狀態</th>
                    <th scope="col" class="column-date">申請日期</th>
                    <th scope="col" class="column-actions">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($applications)): ?>
                    <tr>
                        <td colspan="5">目前沒有申請記錄。</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <?php
                        $user = get_user_by('id', $app['user_id']);
                        $user_name = $user ? $user->display_name : '未知用戶';
                        $user_email = $user ? $user->user_email : '';
                        ?>
                        <tr data-user-id="<?php echo esc_attr($app['user_id']); ?>">
                            <td class="column-user">
                                <strong><?php echo esc_html($user_name); ?></strong>
                                <br>
                                <span class="description"><?php echo esc_html($user_email); ?></span>
                            </td>
                            <td class="column-shop">
                                <strong><?php echo esc_html($app['shop_name']); ?></strong>
                                <?php if (!empty($app['shop_description'])): ?>
                                    <br>
                                    <span class="description"><?php echo esc_html(wp_trim_words($app['shop_description'], 20)); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($app['contact_phone'])): ?>
                                    <br>
                                    <span class="description">電話：<?php echo esc_html($app['contact_phone']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-status">
                                <?php $this->render_status_badge($app['status']); ?>
                            </td>
                            <td class="column-date">
                                <?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($app['applied_at']))); ?>
                            </td>
                            <td class="column-actions">
                                <?php if ($app['status'] === SellerApplicationService::STATUS_PENDING): ?>
                                    <button type="button" class="button button-primary approve-btn" data-user-id="<?php echo esc_attr($app['user_id']); ?>">
                                        批准
                                    </button>
                                    <button type="button" class="button reject-btn" data-user-id="<?php echo esc_attr($app['user_id']); ?>">
                                        拒絕
                                    </button>
                                <?php elseif ($app['status'] === SellerApplicationService::STATUS_REJECTED): ?>
                                    <span class="description">
                                        <?php if (!empty($app['rejected_reason'])): ?>
                                            原因：<?php echo esc_html($app['rejected_reason']); ?>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="description">已處理</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- 拒絕原因對話框 -->
        <div id="reject-dialog" style="display:none;">
            <p>請輸入拒絕原因（選填）：</p>
            <textarea id="reject-reason" rows="3" style="width:100%;"></textarea>
        </div>

        <script>
        function filterApplications() {
            const status = document.getElementById('status-filter').value;
            const url = new URL(window.location.href);
            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }
            window.location.href = url.toString();
        }
        </script>
        <?php
    }

    /**
     * 渲染賣家列表分頁
     */
    private function render_sellers_tab(): void
    {
        $type_filter = isset($_GET['seller_type']) ? sanitize_text_field($_GET['seller_type']) : '';
        $filters = $type_filter ? ['seller_type' => $type_filter] : [];
        $sellers = $this->service->getSellers($filters);

        ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="type_filter" id="type-filter">
                    <option value="">全部類型</option>
                    <option value="<?php echo SellerApplicationService::SELLER_TYPE_TEST; ?>" <?php selected($type_filter, SellerApplicationService::SELLER_TYPE_TEST); ?>>測試賣家</option>
                    <option value="<?php echo SellerApplicationService::SELLER_TYPE_REAL; ?>" <?php selected($type_filter, SellerApplicationService::SELLER_TYPE_REAL); ?>>正式賣家</option>
                </select>
                <input type="button" class="button" value="篩選" onclick="filterSellers()">
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="column-user">用戶</th>
                    <th scope="col" class="column-shop">商店名稱</th>
                    <th scope="col" class="column-type">賣家類型</th>
                    <th scope="col" class="column-products">商品數量</th>
                    <th scope="col" class="column-date">成為賣家日期</th>
                    <th scope="col" class="column-actions">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sellers)): ?>
                    <tr>
                        <td colspan="6">目前沒有賣家。</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sellers as $seller): ?>
                        <?php
                        $user = get_user_by('id', $seller['user_id']);
                        $user_name = $user ? $user->display_name : '未知用戶';
                        $user_email = $user ? $user->user_email : '';
                        ?>
                        <tr data-user-id="<?php echo esc_attr($seller['user_id']); ?>">
                            <td class="column-user">
                                <strong><?php echo esc_html($user_name); ?></strong>
                                <br>
                                <span class="description"><?php echo esc_html($user_email); ?></span>
                            </td>
                            <td class="column-shop">
                                <?php echo esc_html($seller['shop_name'] ?: '未設定'); ?>
                            </td>
                            <td class="column-type">
                                <?php $this->render_seller_type_badge($seller['seller_type']); ?>
                            </td>
                            <td class="column-products">
                                <?php echo intval($seller['product_count']); ?>
                                <?php if ($seller['seller_type'] === SellerApplicationService::SELLER_TYPE_TEST): ?>
                                    / <?php echo SellerApplicationService::TEST_SELLER_PRODUCT_LIMIT; ?>
                                <?php else: ?>
                                    <span class="description">（無限制）</span>
                                <?php endif; ?>
                            </td>
                            <td class="column-date">
                                <?php echo esc_html($seller['approved_at'] ? date_i18n('Y-m-d', strtotime($seller['approved_at'])) : '-'); ?>
                            </td>
                            <td class="column-actions">
                                <?php if ($seller['seller_type'] === SellerApplicationService::SELLER_TYPE_TEST): ?>
                                    <button type="button" class="button button-primary upgrade-btn" data-user-id="<?php echo esc_attr($seller['user_id']); ?>">
                                        升級為正式賣家
                                    </button>
                                <?php else: ?>
                                    <span class="description">已是正式賣家</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <script>
        function filterSellers() {
            const type = document.getElementById('type-filter').value;
            const url = new URL(window.location.href);
            if (type) {
                url.searchParams.set('seller_type', type);
            } else {
                url.searchParams.delete('seller_type');
            }
            window.location.href = url.toString();
        }
        </script>
        <?php
    }

    /**
     * 渲染狀態標籤
     */
    private function render_status_badge(string $status): void
    {
        $badges = [
            SellerApplicationService::STATUS_PENDING => ['待審核', 'background:#ffc107;color:#000;'],
            SellerApplicationService::STATUS_APPROVED => ['已批准', 'background:#28a745;color:#fff;'],
            SellerApplicationService::STATUS_REJECTED => ['已拒絕', 'background:#dc3545;color:#fff;'],
        ];

        $badge = $badges[$status] ?? ['未知', 'background:#6c757d;color:#fff;'];
        echo '<span style="padding:3px 8px;border-radius:3px;font-size:12px;' . $badge[1] . '">' . esc_html($badge[0]) . '</span>';
    }

    /**
     * 渲染賣家類型標籤
     */
    private function render_seller_type_badge(string $type): void
    {
        $badges = [
            SellerApplicationService::SELLER_TYPE_TEST => ['測試賣家', 'background:#17a2b8;color:#fff;'],
            SellerApplicationService::SELLER_TYPE_REAL => ['正式賣家', 'background:#28a745;color:#fff;'],
        ];

        $badge = $badges[$type] ?? ['未知', 'background:#6c757d;color:#fff;'];
        echo '<span style="padding:3px 8px;border-radius:3px;font-size:12px;' . $badge[1] . '">' . esc_html($badge[0]) . '</span>';
    }

    /**
     * AJAX: 批准申請
     */
    public function ajax_approve_application(): void
    {
        check_ajax_referer('buygo_seller_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '權限不足']);
        }

        $user_id = intval($_POST['user_id'] ?? 0);
        $seller_type = sanitize_text_field($_POST['seller_type'] ?? SellerApplicationService::SELLER_TYPE_TEST);

        if (!$user_id) {
            wp_send_json_error(['message' => '無效的用戶 ID']);
        }

        $result = $this->service->approveApplication($user_id, $seller_type);

        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: 拒絕申請
     */
    public function ajax_reject_application(): void
    {
        check_ajax_referer('buygo_seller_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '權限不足']);
        }

        $user_id = intval($_POST['user_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        if (!$user_id) {
            wp_send_json_error(['message' => '無效的用戶 ID']);
        }

        $result = $this->service->rejectApplication($user_id, $reason);

        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: 升級賣家
     */
    public function ajax_upgrade_seller(): void
    {
        check_ajax_referer('buygo_seller_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '權限不足']);
        }

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(['message' => '無效的用戶 ID']);
        }

        $result = $this->service->upgradeSeller($user_id);

        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }
}
