<?php
namespace BuyGoPlus\Api;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reserved API - Pro 功能預留端點與已實作端點
 *
 * 預留端點（回傳 HTTP 501 Not Implemented）：
 * - POST /products/{id}/images        多圖上傳
 * - GET  /products/{id}/images        圖片列表
 *
 * 已實作端點：
 * - POST /products/batch-create       批量上架（Phase 56）
 * - GET  /products/{id}/custom-fields  自訂欄位讀取（Phase 49）
 * - PUT  /products/{id}/custom-fields  自訂欄位更新（Phase 49）
 *
 * @package BuyGoPlus\Api
 * @since 2.0.0
 */
class Reserved_API
{
    private $namespace = 'buygo-plus-one/v1';

    /**
     * 註冊 REST API 路由
     */
    public function register_routes()
    {
        // API-01: POST /products/batch-create（Phase 56 已實作）
        register_rest_route($this->namespace, '/products/batch-create', [
            'methods'             => 'POST',
            'callback'            => [$this, 'batch_create'],
            'permission_callback' => function () {
                return API::check_permission_with_scope('products');
            },
            'args'                => [
                'items' => [
                    'required' => false,
                    'type'     => 'array',
                ],
            ],
        ]);

        // API-02: POST /products/{id}/images
        register_rest_route($this->namespace, '/products/(?P<id>\d+)/images', [
            'methods'             => 'POST',
            'callback'            => [$this, 'upload_images'],
            'permission_callback' => [API::class, 'check_permission'],
            'args'                => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);

        // API-02: GET /products/{id}/images
        register_rest_route($this->namespace, '/products/(?P<id>\d+)/images', [
            'methods'             => 'GET',
            'callback'            => [$this, 'list_images'],
            'permission_callback' => [API::class, 'check_permission'],
            'args'                => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);

        // API-03: GET /products/{id}/custom-fields
        register_rest_route($this->namespace, '/products/(?P<id>\d+)/custom-fields', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_custom_fields'],
            'permission_callback' => [API::class, 'check_permission'],
            'args'                => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);

        // API-03: PUT /products/{id}/custom-fields
        register_rest_route($this->namespace, '/products/(?P<id>\d+)/custom-fields', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'update_custom_fields'],
            'permission_callback' => [API::class, 'check_permission'],
            'args'                => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    // Callback methods
    // ──────────────────────────────────────────────

    /**
     * 批量建立商品（Phase 56 實作）
     *
     * 接受 JSON body：{ "items": [...] } 或直接傳陣列 [...]
     * 每筆商品需含 title(必填)、price(必填)、quantity(選填)、description(選填)、currency(選填)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function batch_create($request)
    {
        $body = $request->get_json_params();
        $items = $body['items'] ?? $body;

        // 確保 items 是陣列
        if (!is_array($items)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '請求格式錯誤，需要 JSON 陣列',
                'code'    => 'invalid_format',
            ], 400);
        }

        $user_id = get_current_user_id();
        $service = new \BuyGoPlus\Services\BatchCreateService();
        $result = $service->batchCreate($items, $user_id);

        // 根據結果決定 HTTP 狀態碼
        if (!$result['success'] && isset($result['error'])) {
            // 整批失敗（配額不足、空陣列等）
            $status = 422;
            if (strpos($result['error'], '配額') !== false) {
                $status = 403;
            }
            return new \WP_REST_Response($result, $status);
        }

        // 部分成功或全部成功
        return new \WP_REST_Response($result, 200);
    }

    public function upload_images($request)
    {
        return $this->not_implemented('Image upload');
    }

    public function list_images($request)
    {
        return $this->not_implemented('Image listing');
    }

    /**
     * 取得商品自訂欄位
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_custom_fields($request)
    {
        $product_id = (int) $request->get_param('id');
        $fields = \BuyGoPlus\Services\ProductMetaService::get_fields($product_id);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $fields,
        ], 200);
    }

    /**
     * 更新商品自訂欄位
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function update_custom_fields($request)
    {
        $product_id = (int) $request->get_param('id');
        $body = $request->get_json_params();

        if (empty($body)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '無更新資料',
            ], 400);
        }

        \BuyGoPlus\Services\ProductMetaService::update_fields($product_id, $body);
        $fields = \BuyGoPlus\Services\ProductMetaService::get_fields($product_id);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $fields,
        ], 200);
    }

    // ──────────────────────────────────────────────
    // Helper
    // ──────────────────────────────────────────────

    /**
     * 產生 501 Not Implemented 回應
     *
     * @param string $feature 功能名稱
     * @return \WP_REST_Response
     */
    private function not_implemented(string $feature): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => false,
            'message' => "{$feature} is not yet implemented. This endpoint is reserved for future Pro features.",
            'code'    => 'not_implemented',
        ], 501);
    }
}
