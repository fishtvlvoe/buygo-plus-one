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
 * - POST /products/upload-temp-image  暫存圖片上傳（Phase 60）
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

        // API: POST /products/upload-temp-image（Phase 60 — 批量上架暫存圖片）
        register_rest_route($this->namespace, '/products/upload-temp-image', [
            'methods'             => 'POST',
            'callback'            => [$this, 'upload_temp_image'],
            'permission_callback' => function () {
                return API::check_permission_with_scope('products');
            },
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

    /**
     * 上傳暫存圖片（不關聯商品）
     * 用於批量上架時先上傳圖片，取得 attachment_id
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function upload_temp_image($request)
    {
        try {
            $files = $request->get_file_params();

            if (empty($files['image'])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '沒有上傳檔案'
                ], 400);
            }

            $file = $files['image'];

            // 檔案大小上限 5MB
            if ($file['size'] > 5 * 1024 * 1024) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '檔案大小超過 5MB'
                ], 400);
            }

            // 副檔名驗證
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
            $file_type = wp_check_filetype($file['name']);

            if (empty($file_type['ext']) || !in_array(strtolower($file_type['ext']), $allowed_extensions)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '僅支援 JPG、PNG、WebP 格式'
                ], 400);
            }

            // 實際 MIME 內容驗證（防止偽造副檔名）
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $real_mime = $finfo->file($file['tmp_name']);

            if (!in_array($real_mime, $allowed_mimes)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '檔案內容不是有效的圖片格式'
                ], 400);
            }

            // 載入 WordPress 檔案處理函數
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            // 上傳到 Media Library（不關聯任何 post）
            $attachment_id = media_handle_upload('image', 0);

            if (is_wp_error($attachment_id)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '上傳失敗：' . $attachment_id->get_error_message()
                ], 500);
            }

            // 標記為暫存上傳，供定期清理
            update_post_meta($attachment_id, '_buygo_temp_upload', time());

            $image_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');

            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'attachment_id' => $attachment_id,
                    'image_url' => $image_url
                ]
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
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

    /**
     * 清理超過 24 小時的暫存圖片附件
     * 由 WP-Cron 每日執行一次
     */
    public static function cleanup_temp_uploads()
    {
        $cutoff = time() - DAY_IN_SECONDS;

        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'meta_key'       => '_buygo_temp_upload',
            'meta_value'     => $cutoff,
            'meta_compare'   => '<',
            'meta_type'      => 'NUMERIC',
            'posts_per_page' => 50,
            'fields'         => 'ids',
        ]);

        foreach ($attachments as $att_id) {
            wp_delete_attachment($att_id, true);
        }
    }

    /**
     * 註冊暫存圖片清理 cron
     */
    public static function schedule_cleanup()
    {
        add_action('buygo_cleanup_temp_uploads', [self::class, 'cleanup_temp_uploads']);

        if (!wp_next_scheduled('buygo_cleanup_temp_uploads')) {
            wp_schedule_event(time(), 'daily', 'buygo_cleanup_temp_uploads');
        }
    }
}
