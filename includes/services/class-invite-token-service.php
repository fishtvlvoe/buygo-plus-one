<?php
namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Invite Token Service - 邀請 Token 管理
 *
 * 負責上架幫手邀請連結的建立、驗證、接受、撤銷。
 * 每位賣家同時只能有一個有效邀請，新建會自動撤銷舊的。
 *
 * @package BuyGoPlus\Services
 * @since 0.3.0
 */
class InviteTokenService
{
    /**
     * Token 有效期（秒）— 24 小時
     */
    const TOKEN_EXPIRY_SECONDS = 86400;

    /**
     * Token 長度（hex 字元數）
     */
    const TOKEN_LENGTH = 48;

    /**
     * 取得資料表名稱
     */
    private static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'buygo_invite_tokens';
    }

    /**
     * 建立邀請 Token
     *
     * 自動撤銷同賣家同角色的舊 token，確保同時只有一個有效邀請。
     *
     * @param int    $seller_id 賣家 user ID
     * @param string $role      角色（預設 buygo_lister）
     * @return array{token: string, expires_at: string, invite_url: string}
     */
    public static function create(int $seller_id, string $role = 'buygo_lister'): array
    {
        global $wpdb;
        $table = self::table_name();

        // 撤銷同賣家同角色的舊 token
        $wpdb->update(
            $table,
            ['status' => 'revoked'],
            [
                'seller_id' => $seller_id,
                'role'      => $role,
                'status'    => 'pending',
            ],
            ['%s'],
            ['%d', '%s', '%s']
        );

        // 產生新 token
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH / 2));
        $expires_at = gmdate('Y-m-d H:i:s', time() + self::TOKEN_EXPIRY_SECONDS);

        $wpdb->insert(
            $table,
            [
                'token'      => $token,
                'seller_id'  => $seller_id,
                'role'       => $role,
                'status'     => 'pending',
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql', true),
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s']
        );

        $invite_url = home_url('/buygo-invite/' . $token);

        return [
            'token'      => $token,
            'expires_at' => $expires_at,
            'invite_url' => $invite_url,
        ];
    }

    /**
     * 驗證 Token
     *
     * @param string $token
     * @return array|null 有效時回傳 token 資料，無效回傳 null
     */
    public static function verify(string $token): ?array
    {
        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE token = %s LIMIT 1",
            $token
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        // 狀態檢查
        if ($row['status'] !== 'pending') {
            return array_merge($row, ['error' => $row['status'] === 'used' ? 'already_used' : 'revoked']);
        }

        // 過期檢查
        if (strtotime($row['expires_at']) < time()) {
            return array_merge($row, ['error' => 'expired']);
        }

        // 附加賣家資訊
        $seller = get_userdata($row['seller_id']);
        $row['seller_name'] = $seller ? $seller->display_name : '未知';
        $row['role_label'] = $row['role'] === 'buygo_lister' ? '上架幫手' : '小幫手';
        $row['error'] = null;

        return $row;
    }

    /**
     * 接受邀請
     *
     * 1. 驗證 token
     * 2. 用 RolePermissionService::add_helper() 賦角色 + 寫 helpers 表
     * 3. 標記 token 為已使用
     * 4. 觸發通知 hook
     *
     * @param string $token
     * @param int    $user_id 接受邀請的使用者 ID
     * @return array{success: bool, message: string}
     */
    public static function accept(string $token, int $user_id): array
    {
        $data = self::verify($token);

        if (!$data) {
            return ['success' => false, 'message' => '邀請連結無效'];
        }

        if (!empty($data['error'])) {
            $messages = [
                'already_used' => '此邀請連結已被使用',
                'revoked'      => '此邀請連結已被撤銷',
                'expired'      => '此邀請連結已過期',
            ];
            return ['success' => false, 'message' => $messages[$data['error']] ?? '邀請連結無效'];
        }

        $seller_id = (int) $data['seller_id'];
        $role = $data['role'];

        // 檢查是否已經是該賣家的幫手
        global $wpdb;
        $helpers_table = $wpdb->prefix . 'buygo_helpers';
        $already_bound = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$helpers_table} WHERE helper_id = %d AND seller_id = %d",
            $user_id,
            $seller_id
        ));

        if ($already_bound) {
            return ['success' => false, 'message' => '你已經是這位賣家的幫手了'];
        }

        // 賦角色 + 寫 helpers 表
        $added = RolePermissionService::add_helper($user_id, $role, $seller_id);
        if (!$added) {
            return ['success' => false, 'message' => '指派角色失敗，請稍後再試'];
        }

        // 標記 token 為已使用
        $table = self::table_name();
        $wpdb->update(
            $table,
            [
                'status'  => 'used',
                'used_by' => $user_id,
                'used_at' => current_time('mysql', true),
            ],
            ['token' => $token],
            ['%s', '%d', '%s'],
            ['%s']
        );

        // 觸發通知 — 通知賣家有新幫手加入
        $new_user = get_userdata($user_id);
        $display_name = $new_user ? $new_user->display_name : '新用戶';

        do_action('buygo_lister_joined', [
            'seller_id'    => $seller_id,
            'user_id'      => $user_id,
            'display_name' => $display_name,
            'role'         => $role,
        ]);

        return ['success' => true, 'message' => '成功加入！你現在可以透過 LINE 幫賣家上架商品了。'];
    }

    /**
     * 撤銷邀請
     *
     * @param string $token
     * @param int    $seller_id 操作者（驗證權限）
     * @return bool
     */
    public static function revoke(string $token, int $seller_id): bool
    {
        global $wpdb;
        $table = self::table_name();

        $affected = $wpdb->update(
            $table,
            ['status' => 'revoked'],
            [
                'token'     => $token,
                'seller_id' => $seller_id,
                'status'    => 'pending',
            ],
            ['%s'],
            ['%s', '%d', '%s']
        );

        return $affected > 0;
    }

    /**
     * 查詢賣家的當前有效邀請
     *
     * @param int    $seller_id
     * @param string $role
     * @return array|null
     */
    public static function get_active(int $seller_id, string $role = 'buygo_lister'): ?array
    {
        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE seller_id = %d AND role = %s AND status = 'pending' AND expires_at > %s
             ORDER BY created_at DESC LIMIT 1",
            $seller_id,
            $role,
            gmdate('Y-m-d H:i:s')
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        $row['invite_url'] = home_url('/buygo-invite/' . $row['token']);

        return $row;
    }
}
