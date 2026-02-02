<?php
namespace BuyGoPlus\Api;

use BuyGoPlus\Services\ChildOrderService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 子訂單 API - 提供顧客前台查詢子訂單
 *
 * 這個 API 給購物者使用，不是給後台管理員使用
 * 權限驗證：is_user_logged_in() + Service 層 customer_id 驗證
 *
 * @package BuyGoPlus\Api
 * @version 1.0.0
 * @since Phase 36
 */
class ChildOrders_API
{
    private $namespace = 'buygo-plus-one/v1';
    private $childOrderService;

    /**
     * 建構子
     */
    public function __construct()
    {
        // 確保 Service 類別已載入
        require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-child-order-service.php';
        $this->childOrderService = new ChildOrderService();
    }

    /**
     * 註冊 REST API 路由
     */
    public function register_routes()
    {
        // GET /child-orders/{parent_order_id} - 取得子訂單列表
        // 支援數字 ID 或 hash 格式（FluentCart 使用 32 字元 hex hash）
        register_rest_route($this->namespace, '/child-orders/(?P<parent_order_id>[a-f0-9]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_child_orders'],
            'permission_callback' => [$this, 'check_customer_permission'],
            'args' => [
                'parent_order_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        // 支援數字 ID 或 32 字元 hex hash
                        return (is_numeric($param) && $param > 0) || preg_match('/^[a-f0-9]{32}$/', $param);
                    },
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
    }

    /**
     * 顧客權限驗證（第一層）
     *
     * 注意：這裡只驗證登入狀態
     * 不使用 API::check_permission()（那是後台 API 用的）
     * 第二層驗證（customer_id 所屬權限）在 Service 層進行
     *
     * @param \WP_REST_Request $request REST 請求
     * @return bool 是否有權限
     */
    public function check_customer_permission(\WP_REST_Request $request): bool
    {
        return is_user_logged_in();
    }

    /**
     * 取得子訂單列表
     *
     * @param \WP_REST_Request $request REST 請求
     * @return \WP_REST_Response REST 回應
     */
    public function get_child_orders(\WP_REST_Request $request): \WP_REST_Response
    {
        // 取得 parent_order_id（可能是數字或 hash）
        $parent_order_id = $request->get_param('parent_order_id');

        // 如果是數字格式，轉為整數
        if (is_numeric($parent_order_id)) {
            $parent_order_id = (int) $parent_order_id;
        }
        // hash 格式保持字串

        // 取得當前用戶的 customer_id
        $current_user_id = get_current_user_id();
        $customer_id = ChildOrderService::getCustomerIdFromUserId($current_user_id);

        // 若 customer_id 為 null，回傳 404（找不到顧客資料）
        if ($customer_id === null) {
            return new \WP_REST_Response([
                'success' => false,
                'code' => 'CUSTOMER_NOT_FOUND',
                'message' => '找不到顧客資料'
            ], 404);
        }

        try {
            // 呼叫 Service 取得子訂單
            $result = $this->childOrderService->getChildOrdersByParentId($parent_order_id, $customer_id);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();

            // 根據錯誤碼決定 HTTP 狀態碼
            if ($code === 403) {
                return new \WP_REST_Response([
                    'success' => false,
                    'code' => 'FORBIDDEN',
                    'message' => $message
                ], 403);
            } elseif ($code === 404) {
                return new \WP_REST_Response([
                    'success' => false,
                    'code' => 'NOT_FOUND',
                    'message' => $message
                ], 404);
            } else {
                return new \WP_REST_Response([
                    'success' => false,
                    'code' => 'SERVER_ERROR',
                    'message' => $message
                ], 500);
            }
        }
    }
}
