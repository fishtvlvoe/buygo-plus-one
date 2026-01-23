<?php
/**
 * Export Service
 *
 * 處理資料匯出功能，支援 CSV 和 Excel 格式
 *
 * @package BuyGoPlus\Services
 */

namespace BuyGoPlus\Services;

class ExportService
{
    /**
     * Debug Service 實例
     *
     * @var Debug_Service
     */
    private $debug_service;

    /**
     * 建構函數
     */
    public function __construct()
    {
        $this->debug_service = Debug_Service::get_instance();
    }

    /**
     * 匯出出貨單為 CSV
     *
     * @param array $shipment_ids 出貨單 ID 陣列
     * @return string|\WP_Error CSV 檔案路徑或 WP_Error
     */
    public function export_shipments_csv($shipment_ids)
    {
        $this->debug_service->log('ExportService', '開始匯出出貨單', [
            'shipment_ids' => $shipment_ids
        ]);

        try {
            global $wpdb;

            if (empty($shipment_ids)) {
                return new \WP_Error('no_shipments', '沒有出貨單可以匯出');
            }

            // 確保是陣列
            if (!is_array($shipment_ids)) {
                $shipment_ids = [$shipment_ids];
            }

        $table_shipments = $wpdb->prefix . 'buygo_shipments';
        $table_shipment_items = $wpdb->prefix . 'buygo_shipment_items';
        $table_customers = $wpdb->prefix . 'fct_customers';
        $table_order_items = $wpdb->prefix . 'fct_order_items';

        // 準備 CSV 資料
        $csv_data = [];

        // CSV 標題列
        $csv_data[] = [
            '出貨單號',
            '客戶姓名',
            '客戶電話',
            '客戶地址',
            'Email',
            '商品名稱',
            '數量',
            '單價',
            '小計',
            '出貨日期',
            '物流方式',
            '追蹤號碼',
            '狀態'
        ];

        // 查詢每個出貨單
        foreach ($shipment_ids as $shipment_id) {
            // 取得出貨單基本資訊
            $shipment = $wpdb->get_row($wpdb->prepare(
                "SELECT s.*,
                        CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as customer_name,
                        c.phone as customer_phone,
                        c.address as customer_address,
                        c.email as customer_email
                 FROM {$table_shipments} s
                 LEFT JOIN {$table_customers} c ON s.customer_id = c.id
                 WHERE s.id = %d",
                $shipment_id
            ), ARRAY_A);

            if (!$shipment) {
                continue;
            }

            // 取得出貨單商品項目
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT si.*,
                        oi.title as product_name,
                        oi.price
                 FROM {$table_shipment_items} si
                 LEFT JOIN {$table_order_items} oi ON si.order_item_id = oi.id
                 WHERE si.shipment_id = %d",
                $shipment_id
            ), ARRAY_A);

            // 如果沒有商品，至少輸出一行出貨單資訊
            if (empty($items)) {
                $csv_data[] = [
                    $shipment['shipment_number'] ?? '',
                    trim($shipment['customer_name'] ?? ''),
                    $shipment['customer_phone'] ?? '',
                    $shipment['customer_address'] ?? '',
                    $shipment['customer_email'] ?? '',
                    '',
                    '',
                    '',
                    '',
                    $shipment['shipped_at'] ?? $shipment['created_at'] ?? '',
                    $shipment['shipping_method'] ?? '',
                    $shipment['tracking_number'] ?? '',
                    $this->get_status_label($shipment['status'] ?? 'pending')
                ];
            } else {
                // 每個商品一行
                foreach ($items as $index => $item) {
                    $price = floatval($item['price'] ?? 0) / 100; // 轉換為元
                    $quantity = intval($item['quantity'] ?? 0);
                    $subtotal = $price * $quantity;

                    // 第一個商品顯示完整出貨單資訊，後續商品只顯示商品資訊
                    if ($index === 0) {
                        $csv_data[] = [
                            $shipment['shipment_number'] ?? '',
                            trim($shipment['customer_name'] ?? ''),
                            $shipment['customer_phone'] ?? '',
                            $shipment['customer_address'] ?? '',
                            $shipment['customer_email'] ?? '',
                            $item['product_name'] ?? '未知商品',
                            $quantity,
                            $price,
                            $subtotal,
                            $shipment['shipped_at'] ?? $shipment['created_at'] ?? '',
                            $shipment['shipping_method'] ?? '',
                            $shipment['tracking_number'] ?? '',
                            $this->get_status_label($shipment['status'] ?? 'pending')
                        ];
                    } else {
                        $csv_data[] = [
                            '', // 出貨單號 (空白，避免重複)
                            '', // 客戶姓名
                            '', // 客戶電話
                            '', // 客戶地址
                            '', // Email
                            $item['product_name'] ?? '未知商品',
                            $quantity,
                            $price,
                            $subtotal,
                            '', // 出貨日期
                            '', // 物流方式
                            '', // 追蹤號碼
                            ''  // 狀態
                        ];
                    }
                }
            }

                // 每個出貨單後加一個空行
                $csv_data[] = ['', '', '', '', '', '', '', '', '', '', '', '', ''];
            }

            // 生成 CSV 檔案
            $filepath = $this->generate_csv_file($csv_data);

            if (is_wp_error($filepath)) {
                $this->debug_service->log('ExportService', '匯出失敗', [
                    'error' => $filepath->get_error_message()
                ], 'error');
                return $filepath;
            }

            $this->debug_service->log('ExportService', '匯出成功', [
                'filepath' => $filepath,
                'total_shipments' => count($shipment_ids)
            ]);

            return $filepath;

        } catch (\Exception $e) {
            $this->debug_service->log('ExportService', '匯出出貨單失敗', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'shipment_ids' => $shipment_ids
            ], 'error');

            return new \WP_Error('export_failed', $e->getMessage());
        }
    }

    /**
     * 生成 CSV 檔案
     *
     * @param array $data CSV 資料陣列
     * @return string|\WP_Error 檔案路徑或 WP_Error
     */
    private function generate_csv_file($data)
    {
        try {
            // 建立臨時檔案
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/buygo-exports';

            // 確保目錄存在
            if (!file_exists($temp_dir)) {
                if (!wp_mkdir_p($temp_dir)) {
                    $this->debug_service->log('ExportService', '無法建立匯出目錄', [
                        'temp_dir' => $temp_dir
                    ], 'error');
                    return new \WP_Error('mkdir_failed', '無法建立匯出目錄');
                }
            }

            // 生成檔案名稱
            $filename = 'shipments_' . date('Ymd_His') . '.csv';
            $filepath = $temp_dir . '/' . $filename;

            // 開啟檔案
            $fp = fopen($filepath, 'w');
            if (!$fp) {
                $this->debug_service->log('ExportService', '無法開啟檔案進行寫入', [
                    'filepath' => $filepath
                ], 'error');
                return new \WP_Error('fopen_failed', '無法開啟檔案進行寫入');
            }

            // 加入 BOM 以支援 Excel 開啟 UTF-8
            fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // 寫入每一行
            foreach ($data as $row) {
                if (fputcsv($fp, $row) === false) {
                    fclose($fp);
                    $this->debug_service->log('ExportService', '寫入 CSV 資料失敗', [
                        'filepath' => $filepath
                    ], 'error');
                    return new \WP_Error('fputcsv_failed', '寫入 CSV 資料失敗');
                }
            }

            fclose($fp);

            return $filepath;

        } catch (\Exception $e) {
            $this->debug_service->log('ExportService', '生成 CSV 檔案失敗', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 'error');

            return new \WP_Error('csv_generation_failed', $e->getMessage());
        }
    }

    /**
     * 取得狀態標籤
     *
     * @param string $status 狀態
     * @return string 狀態標籤
     */
    private function get_status_label($status)
    {
        $labels = [
            'pending' => '待出貨',
            'shipped' => '已出貨',
            'archived' => '已存檔'
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * 匯出出貨單為 Excel (使用 CSV 格式)
     *
     * 注意: 此方法實際上生成 CSV 檔案，但可被 Excel 開啟
     * 如需真正的 .xlsx 格式，需要安裝 PhpSpreadsheet 函式庫
     *
     * @param array $shipment_ids 出貨單 ID 陣列
     * @return string|false 檔案路徑或 false
     */
    public function export_shipments_excel($shipment_ids)
    {
        // 目前使用 CSV 格式 (Excel 可開啟)
        return $this->export_shipments_csv($shipment_ids);
    }

    /**
     * 下載檔案
     *
     * @param string $filepath 檔案路徑
     * @param string $filename 下載檔名
     */
    public function download_file($filepath, $filename = null)
    {
        try {
            if (!file_exists($filepath)) {
                $this->debug_service->log('ExportService', '下載失敗：檔案不存在', [
                    'filepath' => $filepath
                ], 'error');
                wp_die('檔案不存在');
            }

            if (!$filename) {
                $filename = basename($filepath);
            }

            $this->debug_service->log('ExportService', '開始下載檔案', [
                'filepath' => $filepath,
                'filename' => $filename
            ]);

            // 設定 HTTP 標頭
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            header('Pragma: public');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

            // 輸出檔案內容
            readfile($filepath);

            // 刪除臨時檔案
            @unlink($filepath);

            $this->debug_service->log('ExportService', '檔案下載成功', [
                'filename' => $filename
            ]);

            exit;

        } catch (\Exception $e) {
            $this->debug_service->log('ExportService', '下載檔案失敗', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'filepath' => $filepath
            ], 'error');

            wp_die('下載失敗：' . $e->getMessage());
        }
    }

    /**
     * 清理過期的匯出檔案
     *
     * 刪除 24 小時前的匯出檔案
     */
    public function cleanup_old_exports()
    {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/buygo-exports';

        if (!file_exists($temp_dir)) {
            return;
        }

        $files = glob($temp_dir . '/*.csv');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                // 如果檔案超過 24 小時，刪除它
                if ($now - filemtime($file) >= 24 * 3600) {
                    @unlink($file);
                }
            }
        }
    }
}
