<?php
/**
 * BuyGo+1 服務層範本
 *
 * 使用此範本建立新的服務類別。
 *
 * 使用方式：
 * 1. 複製此檔案到 includes/services/
 * 2. 重新命名為 class-{entity}-service.php（如 class-report-service.php）
 * 3. 將 {Entity} 替換為實體名稱（如 Report）
 * 4. 將 {entity} 替換為實體 slug（如 report）
 * 5. 將 {entities} 替換為實體複數形式（如 reports）
 *
 * @package BuyGoPlus\Services
 * @since 1.0.0
 */

namespace BuyGoPlus\Services;

/**
 * {Entity} Service - {實體描述}服務
 *
 * 提供 {實體描述} 相關的業務邏輯處理
 *
 * @package BuyGoPlus\Services
 * @version 1.0.0
 */
class {Entity}Service
{
    /**
     * Debug 服務實例
     *
     * @var DebugService
     */
    private $debugService;

    /**
     * 建構函式
     */
    public function __construct()
    {
        $this->debugService = new DebugService();
    }

    // ============================================
    // 查詢方法
    // ============================================

    /**
     * 取得列表（含分頁）
     *
     * @param array $params 查詢參數
     *   - page: 頁碼（預設 1）
     *   - per_page: 每頁數量（預設 20）
     *   - search: 搜尋關鍵字
     *   - status: 狀態篩選
     * @return array
     */
    public function get{Entities}(array $params = []): array
    {
        $this->debugService->log('{Entity}Service', '開始取得列表', [
            'params' => $params
        ]);

        try {
            global $wpdb;
            $table = $wpdb->prefix . 'buygo_{entities}';

            $page = (int)($params['page'] ?? 1);
            $per_page = (int)($params['per_page'] ?? 20);
            $search = $params['search'] ?? '';
            $status = $params['status'] ?? 'all';
            $offset = ($page - 1) * $per_page;

            // 建構查詢
            $where = ['1=1'];
            $values = [];

            // 搜尋條件
            if (!empty($search)) {
                $where[] = '(name LIKE %s OR description LIKE %s)';
                $values[] = '%' . $wpdb->esc_like($search) . '%';
                $values[] = '%' . $wpdb->esc_like($search) . '%';
            }

            // 狀態篩選
            if ($status !== 'all') {
                $where[] = 'status = %s';
                $values[] = $status;
            }

            $where_sql = implode(' AND ', $where);

            // 計算總數
            $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
            if (!empty($values)) {
                $count_sql = $wpdb->prepare($count_sql, ...$values);
            }
            $total = (int)$wpdb->get_var($count_sql);

            // 取得資料
            $query_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
            $query_values = array_merge($values, [$per_page, $offset]);
            $items = $wpdb->get_results(
                $wpdb->prepare($query_sql, ...$query_values),
                ARRAY_A
            );

            $this->debugService->log('{Entity}Service', '列表取得成功', [
                'total' => $total,
                'count' => count($items)
            ]);

            return [
                'items' => array_map([$this, 'format{Entity}'], $items),
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($total / $per_page)
            ];

        } catch (\Exception $e) {
            $this->debugService->log('{Entity}Service', '列表取得失敗', [
                'error' => $e->getMessage()
            ], 'error');

            return [
                'items' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $per_page,
                'total_pages' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 取得單一項目
     *
     * @param int $id 項目 ID
     * @return array|null
     */
    public function get{Entity}(int $id): ?array
    {
        $this->debugService->log('{Entity}Service', '開始取得單一項目', [
            'id' => $id
        ]);

        try {
            global $wpdb;
            $table = $wpdb->prefix . 'buygo_{entities}';

            $item = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
                ARRAY_A
            );

            if (!$item) {
                return null;
            }

            return $this->format{Entity}($item);

        } catch (\Exception $e) {
            $this->debugService->log('{Entity}Service', '取得失敗', [
                'id' => $id,
                'error' => $e->getMessage()
            ], 'error');

            return null;
        }
    }

    // ============================================
    // 寫入方法
    // ============================================

    /**
     * 建立新項目
     *
     * @param array $data 項目資料
     * @return array|WP_Error
     */
    public function create{Entity}(array $data)
    {
        $this->debugService->log('{Entity}Service', '開始建立項目', [
            'data' => $data
        ]);

        try {
            global $wpdb;
            $table = $wpdb->prefix . 'buygo_{entities}';

            // 驗證必要欄位
            if (empty($data['name'])) {
                return new \WP_Error('VALIDATION_ERROR', '名稱為必填欄位');
            }

            // 準備資料
            $insert_data = [
                'name' => sanitize_text_field($data['name']),
                'description' => sanitize_textarea_field($data['description'] ?? ''),
                'status' => $data['status'] ?? 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];

            $result = $wpdb->insert($table, $insert_data);

            if ($result === false) {
                return new \WP_Error('DB_ERROR', '資料庫寫入失敗：' . $wpdb->last_error);
            }

            $new_id = $wpdb->insert_id;

            $this->debugService->log('{Entity}Service', '建立成功', [
                'id' => $new_id
            ]);

            return $this->get{Entity}($new_id);

        } catch (\Exception $e) {
            $this->debugService->log('{Entity}Service', '建立失敗', [
                'error' => $e->getMessage()
            ], 'error');

            return new \WP_Error('CREATE_FAILED', $e->getMessage());
        }
    }

    /**
     * 更新項目
     *
     * @param int $id 項目 ID
     * @param array $data 更新資料
     * @return array|WP_Error
     */
    public function update{Entity}(int $id, array $data)
    {
        $this->debugService->log('{Entity}Service', '開始更新項目', [
            'id' => $id,
            'data' => $data
        ]);

        try {
            global $wpdb;
            $table = $wpdb->prefix . 'buygo_{entities}';

            // 確認項目存在
            $existing = $this->get{Entity}($id);
            if (!$existing) {
                return new \WP_Error('NOT_FOUND', '找不到指定的項目');
            }

            // 準備更新資料
            $update_data = ['updated_at' => current_time('mysql')];

            if (isset($data['name'])) {
                $update_data['name'] = sanitize_text_field($data['name']);
            }
            if (isset($data['description'])) {
                $update_data['description'] = sanitize_textarea_field($data['description']);
            }
            if (isset($data['status'])) {
                $update_data['status'] = sanitize_text_field($data['status']);
            }

            $result = $wpdb->update($table, $update_data, ['id' => $id]);

            if ($result === false) {
                return new \WP_Error('DB_ERROR', '資料庫更新失敗：' . $wpdb->last_error);
            }

            $this->debugService->log('{Entity}Service', '更新成功', [
                'id' => $id
            ]);

            return $this->get{Entity}($id);

        } catch (\Exception $e) {
            $this->debugService->log('{Entity}Service', '更新失敗', [
                'id' => $id,
                'error' => $e->getMessage()
            ], 'error');

            return new \WP_Error('UPDATE_FAILED', $e->getMessage());
        }
    }

    /**
     * 刪除項目
     *
     * @param int $id 項目 ID
     * @return bool|WP_Error
     */
    public function delete{Entity}(int $id)
    {
        $this->debugService->log('{Entity}Service', '開始刪除項目', [
            'id' => $id
        ]);

        try {
            global $wpdb;
            $table = $wpdb->prefix . 'buygo_{entities}';

            // 確認項目存在
            $existing = $this->get{Entity}($id);
            if (!$existing) {
                return new \WP_Error('NOT_FOUND', '找不到指定的項目');
            }

            $result = $wpdb->delete($table, ['id' => $id]);

            if ($result === false) {
                return new \WP_Error('DB_ERROR', '資料庫刪除失敗：' . $wpdb->last_error);
            }

            $this->debugService->log('{Entity}Service', '刪除成功', [
                'id' => $id
            ]);

            return true;

        } catch (\Exception $e) {
            $this->debugService->log('{Entity}Service', '刪除失敗', [
                'id' => $id,
                'error' => $e->getMessage()
            ], 'error');

            return new \WP_Error('DELETE_FAILED', $e->getMessage());
        }
    }

    // ============================================
    // 格式化方法
    // ============================================

    /**
     * 格式化項目資料
     *
     * @param array $item 原始資料
     * @return array 格式化後的資料
     */
    private function format{Entity}(array $item): array
    {
        return [
            'id' => (int)$item['id'],
            'name' => $item['name'] ?? '',
            'description' => $item['description'] ?? '',
            'status' => $item['status'] ?? 'active',
            'created_at' => $item['created_at'] ?? null,
            'updated_at' => $item['updated_at'] ?? null
        ];
    }
}
