<?php
namespace BuyGoPlus\Api;

use BuyGoPlus\Services\OrderItemService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 訂單商品 API - 提供訂單商品的管理操作
 *
 * 權限驗證：使用 API::check_permission()（後台管理員）
 *
 * @package BuyGoPlus\Api
 */
class OrderItems_API
{
    private $namespace = 'buygo-plus-one/v1';

    /**
     * 註冊 REST API 路由
     */
    public function register_routes()
    {
        // DELETE /orders/{order_id}/items/{item_id} - 移除訂單商品
        register_rest_route($this->namespace, '/orders/(?P<order_id>\d+)/items/(?P<item_id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'remove_item'],
            'permission_callback' => [API::class, 'check_permission'],
            'args'                => [
                'order_id' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint',
                ],
                'item_id' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * 移除訂單商品
     *
     * @param \WP_REST_Request $request REST 請求
     * @return \WP_REST_Response REST 回應
     */
    public function remove_item(\WP_REST_Request $request): \WP_REST_Response
    {
        $order_id = (int) $request->get_param('order_id');
        $item_id  = (int) $request->get_param('item_id');

        try {
            ( new OrderItemService() )->removeItem($order_id, $item_id);
            return new \WP_REST_Response(['success' => true], 200);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            if (stripos($message, 'not found') !== false) {
                return new \WP_REST_Response(['success' => false, 'message' => $message], 404);
            }

            if (stripos($message, 'completed') !== false || stripos($message, 'cancelled') !== false) {
                return new \WP_REST_Response(['success' => false, 'message' => $message], 422);
            }

            return new \WP_REST_Response(['success' => false, 'message' => $message], 500);
        }
    }
}
