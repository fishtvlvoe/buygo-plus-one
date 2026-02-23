<?php
namespace BuyGoPlus\Api;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Invite API - 邀請連結 REST 端點
 *
 * @package BuyGoPlus\Api
 * @since 0.3.0
 */
class Invite_API
{
    const NAMESPACE = 'buygo-plus-one/v1';

    public function register_routes(): void
    {
        // POST /invite/create — 賣家建立邀請 token
        register_rest_route(self::NAMESPACE, '/invite/create', [
            'methods'             => 'POST',
            'callback'            => [$this, 'create_invite'],
            'permission_callback' => [$this, 'check_seller_permission'],
        ]);

        // GET /invite/status — 查詢賣家目前有效邀請
        register_rest_route(self::NAMESPACE, '/invite/status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_invite_status'],
            'permission_callback' => [$this, 'check_seller_permission'],
        ]);

        // POST /invite/revoke — 撤銷邀請
        register_rest_route(self::NAMESPACE, '/invite/revoke', [
            'methods'             => 'POST',
            'callback'            => [$this, 'revoke_invite'],
            'permission_callback' => [$this, 'check_seller_permission'],
        ]);

        // GET /invite/verify/(?P<token>[a-f0-9]{48}) — 公開驗證 token
        register_rest_route(self::NAMESPACE, '/invite/verify/(?P<token>[a-f0-9]{48})', [
            'methods'             => 'GET',
            'callback'            => [$this, 'verify_invite'],
            'permission_callback' => '__return_true',
        ]);

        // POST /invite/accept — 已登入使用者接受邀請
        register_rest_route(self::NAMESPACE, '/invite/accept', [
            'methods'             => 'POST',
            'callback'            => [$this, 'accept_invite'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);
    }

    /**
     * 權限：只有賣家（buygo_admin）或 WP 管理員
     */
    public function check_seller_permission(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }
        return current_user_can('manage_options')
            || current_user_can('buygo_admin');
    }

    /**
     * POST /invite/create
     */
    public function create_invite(\WP_REST_Request $request): \WP_REST_Response
    {
        $seller_id = get_current_user_id();
        $role = $request->get_param('role') ?: 'buygo_lister';

        // 只允許建立 lister 邀請
        if (!in_array($role, ['buygo_lister'], true)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '目前只支援上架幫手邀請',
            ], 400);
        }

        $result = \BuyGoPlus\Services\InviteTokenService::create($seller_id, $role);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /**
     * GET /invite/status
     */
    public function get_invite_status(\WP_REST_Request $request): \WP_REST_Response
    {
        $seller_id = get_current_user_id();
        $role = $request->get_param('role') ?: 'buygo_lister';

        $active = \BuyGoPlus\Services\InviteTokenService::get_active($seller_id, $role);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $active,
        ]);
    }

    /**
     * POST /invite/revoke
     */
    public function revoke_invite(\WP_REST_Request $request): \WP_REST_Response
    {
        $seller_id = get_current_user_id();
        $token = $request->get_param('token');

        if (empty($token)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '缺少 token 參數',
            ], 400);
        }

        $revoked = \BuyGoPlus\Services\InviteTokenService::revoke($token, $seller_id);

        return new \WP_REST_Response([
            'success' => $revoked,
            'message' => $revoked ? '已撤銷邀請連結' : '撤銷失敗（可能已過期或不存在）',
        ]);
    }

    /**
     * GET /invite/verify/{token}
     */
    public function verify_invite(\WP_REST_Request $request): \WP_REST_Response
    {
        $token = $request->get_param('token');
        $data = \BuyGoPlus\Services\InviteTokenService::verify($token);

        if (!$data) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '邀請連結無效',
            ], 404);
        }

        if (!empty($data['error'])) {
            $status_code = $data['error'] === 'expired' ? 410 : 409;
            return new \WP_REST_Response([
                'success' => false,
                'error'   => $data['error'],
                'message' => $data['error'] === 'expired' ? '邀請已過期' : '邀請已' . ($data['error'] === 'already_used' ? '使用' : '撤銷'),
            ], $status_code);
        }

        // 公開端點只回傳必要資訊
        return new \WP_REST_Response([
            'success'     => true,
            'seller_name' => $data['seller_name'],
            'role_label'  => $data['role_label'],
            'expires_at'  => $data['expires_at'],
        ]);
    }

    /**
     * POST /invite/accept
     */
    public function accept_invite(\WP_REST_Request $request): \WP_REST_Response
    {
        $token = $request->get_param('token');
        $user_id = get_current_user_id();

        if (empty($token)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '缺少 token 參數',
            ], 400);
        }

        $result = \BuyGoPlus\Services\InviteTokenService::accept($token, $user_id);

        $status_code = $result['success'] ? 200 : 400;
        return new \WP_REST_Response($result, $status_code);
    }
}
