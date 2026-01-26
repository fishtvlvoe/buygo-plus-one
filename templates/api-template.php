<?php
/**
 * BuyGo+1 API 範本
 *
 * 使用此範本建立新的 REST API 端點。
 *
 * 使用方式：
 * 1. 複製此檔案到 includes/api/
 * 2. 重新命名為 class-{entities}-api.php（如 class-reports-api.php）
 * 3. 將 {Entity} 替換為實體名稱（如 Report）
 * 4. 將 {Entities} 替換為實體複數名稱（如 Reports）
 * 5. 將 {entities} 替換為 API 路徑（如 reports）
 * 6. 在 class-api.php 中註冊此 API
 *
 * @package BuyGoPlus\Api
 * @since 1.0.0
 */

namespace BuyGoPlus\Api;

use BuyGoPlus\Services\{Entity}Service;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * {Entities} API - {實體描述} API 端點
 *
 * 提供 {實體描述} 相關的 REST API
 *
 * @package BuyGoPlus\Api
 * @version 1.0.0
 */
class {Entities}_API
{
    /**
     * API 命名空間
     *
     * @var string
     */
    private $namespace = 'buygo-plus-one/v1';

    /**
     * 服務實例
     *
     * @var {Entity}Service
     */
    private ${entity}Service;

    /**
     * 建構函式
     */
    public function __construct()
    {
        $this->{entity}Service = new {Entity}Service();
    }

    /**
     * 註冊 API 路由
     *
     * 【重要】所有路由都使用 API::check_permission 做權限檢查
     */
    public function register_routes()
    {
        // ============================================
        // GET /{entities} - 取得列表
        // ============================================
        register_rest_route($this->namespace, '/{entities}', [
            'methods' => 'GET',
            'callback' => [$this, 'get_items'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'page' => [
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'per_page' => [
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                ],
                'search' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'status' => [
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ]
            ]
        ]);

        // ============================================
        // GET /{entities}/{id} - 取得單一項目
        // ============================================
        register_rest_route($this->namespace, '/{entities}/(?P<id>\\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_item'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ]
            ]
        ]);

        // ============================================
        // POST /{entities} - 建立新項目
        // ============================================
        register_rest_route($this->namespace, '/{entities}', [
            'methods' => 'POST',
            'callback' => [$this, 'create_item'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'name' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'description' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'status' => [
                    'default' => 'active',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return in_array($param, ['active', 'inactive']);
                    }
                ]
            ]
        ]);

        // ============================================
        // PUT /{entities}/{id} - 更新項目
        // ============================================
        register_rest_route($this->namespace, '/{entities}/(?P<id>\\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_item'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'name' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'description' => [
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'status' => [
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return $param === null || in_array($param, ['active', 'inactive']);
                    }
                ]
            ]
        ]);

        // ============================================
        // DELETE /{entities}/{id} - 刪除項目
        // ============================================
        register_rest_route($this->namespace, '/{entities}/(?P<id>\\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_item'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ]
            ]
        ]);

        // ============================================
        // POST /{entities}/batch-delete - 批次刪除
        // ============================================
        register_rest_route($this->namespace, '/{entities}/batch-delete', [
            'methods' => 'POST',
            'callback' => [$this, 'batch_delete'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'ids' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_array($param) && !empty($param);
                    }
                ]
            ]
        ]);

        // ============================================
        // GET /{entities}/export - 匯出（如需要）
        // ============================================
        register_rest_route($this->namespace, '/{entities}/export', [
            'methods' => 'GET',
            'callback' => [$this, 'export'],
            'permission_callback' => [API::class, 'check_permission'],
            'args' => [
                'format' => [
                    'default' => 'csv',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return in_array($param, ['csv', 'json']);
                    }
                ]
            ]
        ]);
    }

    // ============================================
    // API 回調方法
    // ============================================

    /**
     * 取得列表
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_items(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = [
            'page' => $request->get_param('page'),
            'per_page' => $request->get_param('per_page'),
            'search' => $request->get_param('search'),
            'status' => $request->get_param('status'),
        ];

        $result = $this->{entity}Service->get{Entities}($params);

        return new \WP_REST_Response($result, 200);
    }

    /**
     * 取得單一項目
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_item(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int)$request->get_param('id');
        $item = $this->{entity}Service->get{Entity}($id);

        if (!$item) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '找不到指定的項目'
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $item
        ], 200);
    }

    /**
     * 建立新項目
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function create_item(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = [
            'name' => $request->get_param('name'),
            'description' => $request->get_param('description'),
            'status' => $request->get_param('status'),
        ];

        $result = $this->{entity}Service->create{Entity}($data);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message()
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $result
        ], 201);
    }

    /**
     * 更新項目
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function update_item(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int)$request->get_param('id');
        $data = [];

        // 只取得有提供的欄位
        if ($request->get_param('name') !== null) {
            $data['name'] = $request->get_param('name');
        }
        if ($request->get_param('description') !== null) {
            $data['description'] = $request->get_param('description');
        }
        if ($request->get_param('status') !== null) {
            $data['status'] = $request->get_param('status');
        }

        $result = $this->{entity}Service->update{Entity}($id, $data);

        if (is_wp_error($result)) {
            $status_code = $result->get_error_code() === 'NOT_FOUND' ? 404 : 400;
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message()
            ], $status_code);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $result
        ], 200);
    }

    /**
     * 刪除項目
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function delete_item(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int)$request->get_param('id');
        $result = $this->{entity}Service->delete{Entity}($id);

        if (is_wp_error($result)) {
            $status_code = $result->get_error_code() === 'NOT_FOUND' ? 404 : 400;
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message()
            ], $status_code);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => '刪除成功'
        ], 200);
    }

    /**
     * 批次刪除
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function batch_delete(\WP_REST_Request $request): \WP_REST_Response
    {
        $ids = $request->get_param('ids');
        $deleted = 0;
        $errors = [];

        foreach ($ids as $id) {
            $result = $this->{entity}Service->delete{Entity}((int)$id);
            if (is_wp_error($result)) {
                $errors[] = "ID {$id}: " . $result->get_error_message();
            } else {
                $deleted++;
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'deleted' => $deleted,
            'errors' => $errors
        ], 200);
    }

    /**
     * 匯出
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|void
     */
    public function export(\WP_REST_Request $request)
    {
        $format = $request->get_param('format');

        // 取得所有資料（不分頁）
        $result = $this->{entity}Service->get{Entities}([
            'page' => 1,
            'per_page' => 10000
        ]);

        $items = $result['items'] ?? [];

        if ($format === 'json') {
            return new \WP_REST_Response($items, 200);
        }

        // CSV 匯出
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="{entities}_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // BOM for Excel UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // 標題列
        fputcsv($output, ['ID', '名稱', '描述', '狀態', '建立時間']);

        // 資料列
        foreach ($items as $item) {
            fputcsv($output, [
                $item['id'],
                $item['name'],
                $item['description'],
                $item['status'],
                $item['created_at']
            ]);
        }

        fclose($output);
        exit;
    }
}
