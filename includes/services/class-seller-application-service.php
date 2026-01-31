<?php

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Seller Application Service - 賣家申請管理服務
 *
 * Phase 27: 處理賣家申請、審核、升級等功能
 *
 * @package BuyGoPlus\Services
 * @version 1.0.0
 */
class SellerApplicationService
{
    /**
     * 申請狀態常數
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * 賣家類型常數
     */
    const SELLER_TYPE_TEST = 'test';
    const SELLER_TYPE_REAL = 'real';

    /**
     * 測試賣家預設商品限制
     */
    const DEFAULT_TEST_PRODUCT_LIMIT = 10;

    /**
     * Debug Service
     *
     * @var DebugService
     */
    private $debugService;

    public function __construct()
    {
        $this->debugService = DebugService::get_instance();
    }

    /**
     * 提交賣家申請
     *
     * APPLY-01, APPLY-02, APPLY-05, APPLY-06
     *
     * @param array $data 申請資料
     * @return array 包含 success 和 message
     */
    public function submitApplication(array $data): array
    {
        $this->debugService->log('SellerApplicationService', '提交賣家申請', $data);

        try {
            $user_id = get_current_user_id();
            if (!$user_id) {
                return [
                    'success' => false,
                    'message' => '請先登入'
                ];
            }

            // 檢查是否已經是賣家
            if (SettingsService::is_seller($user_id)) {
                return [
                    'success' => false,
                    'message' => '您已經是賣家，無需重複申請'
                ];
            }

            // 檢查是否已經有待審核的申請
            $existing = $this->getApplicationByUserId($user_id);
            if ($existing && $existing['status'] === self::STATUS_PENDING) {
                return [
                    'success' => false,
                    'message' => '您已有待審核的申請，請耐心等候'
                ];
            }

            // 準備申請資料
            $application_data = [
                'name' => sanitize_text_field($data['name'] ?? ''),
                'email' => sanitize_email($data['email'] ?? ''),
                'line_id' => sanitize_text_field($data['line_id'] ?? ''),
                'reason' => sanitize_textarea_field($data['reason'] ?? ''),
            ];

            // 驗證必填欄位
            if (empty($application_data['name']) || empty($application_data['email'])) {
                return [
                    'success' => false,
                    'message' => '姓名和 Email 為必填欄位'
                ];
            }

            // APPLY-06: 記錄申請
            update_user_meta($user_id, 'buygo_seller_application', wp_json_encode($application_data));
            update_user_meta($user_id, 'buygo_seller_application_date', current_time('mysql'));
            update_user_meta($user_id, 'buygo_seller_application_status', self::STATUS_PENDING);

            // APPLY-02: 自動批准為測試賣家
            $approve_result = $this->approveApplication($user_id, self::SELLER_TYPE_TEST);

            if (!$approve_result['success']) {
                return $approve_result;
            }

            // APPLY-05: 發送 Email 通知
            $this->sendApprovalEmail($user_id, $application_data);

            $this->debugService->log('SellerApplicationService', '賣家申請成功', [
                'user_id' => $user_id,
                'type' => self::SELLER_TYPE_TEST
            ]);

            return [
                'success' => true,
                'message' => '恭喜！您已成為測試賣家，可以開始上架商品了。'
            ];

        } catch (\Exception $e) {
            $this->debugService->log('SellerApplicationService', '賣家申請失敗', [
                'error' => $e->getMessage()
            ], 'error');

            return [
                'success' => false,
                'message' => '申請失敗：' . $e->getMessage()
            ];
        }
    }

    /**
     * 批准申請
     *
     * @param int $user_id 使用者 ID
     * @param string $seller_type 賣家類型 (test|real)
     * @return array
     */
    public function approveApplication(int $user_id, string $seller_type = self::SELLER_TYPE_TEST): array
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return [
                'success' => false,
                'message' => '使用者不存在'
            ];
        }

        // 賦予 buygo_admin 角色
        $user->add_role('buygo_admin');

        // 設定賣家類型
        update_user_meta($user_id, 'buygo_seller_type', $seller_type);

        // 設定商品限制（測試賣家有限制，真實賣家無限制）
        if ($seller_type === self::SELLER_TYPE_TEST) {
            update_user_meta($user_id, 'buygo_product_limit', self::DEFAULT_TEST_PRODUCT_LIMIT);
        } else {
            update_user_meta($user_id, 'buygo_product_limit', 0); // 0 = 無限制
        }

        // 更新申請狀態
        update_user_meta($user_id, 'buygo_seller_application_status', self::STATUS_APPROVED);
        update_user_meta($user_id, 'buygo_seller_approved_date', current_time('mysql'));

        return [
            'success' => true,
            'message' => '已批准為' . ($seller_type === self::SELLER_TYPE_TEST ? '測試' : '正式') . '賣家'
        ];
    }

    /**
     * 拒絕申請
     *
     * @param int $user_id 使用者 ID
     * @param string $reason 拒絕原因
     * @return array
     */
    public function rejectApplication(int $user_id, string $reason = ''): array
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return [
                'success' => false,
                'message' => '使用者不存在'
            ];
        }

        update_user_meta($user_id, 'buygo_seller_application_status', self::STATUS_REJECTED);
        update_user_meta($user_id, 'buygo_seller_rejected_reason', $reason);
        update_user_meta($user_id, 'buygo_seller_rejected_date', current_time('mysql'));

        return [
            'success' => true,
            'message' => '已拒絕申請'
        ];
    }

    /**
     * 升級賣家（測試 → 正式）
     *
     * ADMIN-03
     *
     * @param int $user_id 使用者 ID
     * @return array
     */
    public function upgradeSeller(int $user_id): array
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return [
                'success' => false,
                'message' => '使用者不存在'
            ];
        }

        // 檢查是否為測試賣家
        $current_type = get_user_meta($user_id, 'buygo_seller_type', true);
        if ($current_type !== self::SELLER_TYPE_TEST) {
            return [
                'success' => false,
                'message' => '此用戶不是測試賣家'
            ];
        }

        // 升級為正式賣家
        update_user_meta($user_id, 'buygo_seller_type', self::SELLER_TYPE_REAL);
        update_user_meta($user_id, 'buygo_product_limit', 0); // 解除限制
        update_user_meta($user_id, 'buygo_seller_upgraded_date', current_time('mysql'));

        $this->debugService->log('SellerApplicationService', '賣家升級', [
            'user_id' => $user_id,
            'from' => self::SELLER_TYPE_TEST,
            'to' => self::SELLER_TYPE_REAL
        ]);

        return [
            'success' => true,
            'message' => '已升級為正式賣家'
        ];
    }

    /**
     * 取得使用者的申請資料
     *
     * @param int $user_id 使用者 ID
     * @return array|null
     */
    public function getApplicationByUserId(int $user_id): ?array
    {
        $application_json = get_user_meta($user_id, 'buygo_seller_application', true);
        if (empty($application_json)) {
            return null;
        }

        $application = json_decode($application_json, true);
        $application['user_id'] = $user_id;
        $application['status'] = get_user_meta($user_id, 'buygo_seller_application_status', true);
        $application['applied_date'] = get_user_meta($user_id, 'buygo_seller_application_date', true);

        return $application;
    }

    /**
     * 取得所有申請列表
     *
     * ADMIN-02
     *
     * @param array $filters 篩選條件
     * @return array
     */
    public function getApplications(array $filters = []): array
    {
        $args = [
            'meta_query' => [
                [
                    'key' => 'buygo_seller_application_status',
                    'compare' => 'EXISTS'
                ]
            ],
            'orderby' => 'meta_value',
            'meta_key' => 'buygo_seller_application_date',
            'order' => 'DESC'
        ];

        // 篩選狀態
        if (!empty($filters['status'])) {
            $args['meta_query'][] = [
                'key' => 'buygo_seller_application_status',
                'value' => $filters['status']
            ];
        }

        // 篩選賣家類型
        if (!empty($filters['seller_type'])) {
            $args['meta_query'][] = [
                'key' => 'buygo_seller_type',
                'value' => $filters['seller_type']
            ];
        }

        $users = get_users($args);

        $applications = [];
        foreach ($users as $user) {
            $application = $this->getApplicationByUserId($user->ID);
            if ($application) {
                $application['user_name'] = $user->display_name;
                $application['user_email'] = $user->user_email;
                $application['seller_type'] = get_user_meta($user->ID, 'buygo_seller_type', true);
                $application['product_limit'] = get_user_meta($user->ID, 'buygo_product_limit', true);
                $applications[] = $application;
            }
        }

        return $applications;
    }

    /**
     * 取得所有賣家列表
     *
     * @param array $filters 篩選條件
     * @return array
     */
    public function getSellers(array $filters = []): array
    {
        $args = [
            'role' => 'buygo_admin'
        ];

        // 篩選賣家類型
        if (!empty($filters['seller_type'])) {
            $args['meta_query'] = [
                [
                    'key' => 'buygo_seller_type',
                    'value' => $filters['seller_type']
                ]
            ];
        }

        $users = get_users($args);

        $sellers = [];
        foreach ($users as $user) {
            $sellers[] = [
                'user_id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'seller_type' => get_user_meta($user->ID, 'buygo_seller_type', true) ?: self::SELLER_TYPE_REAL,
                'product_limit' => get_user_meta($user->ID, 'buygo_product_limit', true) ?: 0,
                'applied_date' => get_user_meta($user->ID, 'buygo_seller_application_date', true),
                'approved_date' => get_user_meta($user->ID, 'buygo_seller_approved_date', true),
            ];
        }

        return $sellers;
    }

    /**
     * 發送批准通知 Email
     *
     * APPLY-05
     *
     * @param int $user_id 使用者 ID
     * @param array $application_data 申請資料
     * @return bool
     */
    private function sendApprovalEmail(int $user_id, array $application_data): bool
    {
        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email)) {
            return false;
        }

        $to = $user->user_email;
        $subject = '恭喜！您的 BuyGo 賣家申請已通過';

        $portal_url = home_url('/buygo-portal/');

        $message = "親愛的 {$application_data['name']}，\n\n";
        $message .= "恭喜！您的賣家申請已通過，您現在是 BuyGo 測試賣家。\n\n";
        $message .= "【測試賣家說明】\n";
        $message .= "• 您可以上架最多 " . self::DEFAULT_TEST_PRODUCT_LIMIT . " 件商品\n";
        $message .= "• 熟悉系統後，可聯繫客服升級為正式賣家\n\n";
        $message .= "【開始使用】\n";
        $message .= "請前往 BuyGo Portal 開始管理您的商品：\n";
        $message .= $portal_url . "\n\n";
        $message .= "如有任何問題，歡迎聯繫客服。\n\n";
        $message .= "BuyGo 團隊";

        return wp_mail($to, $subject, $message);
    }

    /**
     * 檢查使用者是否可以申請成為賣家
     *
     * @param int|null $user_id 使用者 ID
     * @return array
     */
    public function canApply(?int $user_id = null): array
    {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return [
                'can_apply' => false,
                'reason' => '請先登入'
            ];
        }

        // 已經是賣家
        if (SettingsService::is_seller($user_id)) {
            return [
                'can_apply' => false,
                'reason' => '您已經是賣家'
            ];
        }

        // 有待審核的申請
        $existing = $this->getApplicationByUserId($user_id);
        if ($existing && $existing['status'] === self::STATUS_PENDING) {
            return [
                'can_apply' => false,
                'reason' => '您已有待審核的申請'
            ];
        }

        return [
            'can_apply' => true,
            'reason' => ''
        ];
    }
}
