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
     * 匯出出貨單為 CSV
     *
     * @param array $shipment_ids 出貨單 ID 陣列
     * @return string|false CSV 檔案路徑或 false
     */
    public function export_shipments_csv($shipment_ids)
    {
        global $wpdb;

        if (empty($shipment_ids)) {
            return false;
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
        return $this->generate_csv_file($csv_data);
    }

    /**
     * 生成 CSV 檔案
     *
     * @param array $data CSV 資料陣列
     * @return string|false 檔案路徑或 false
     */
    private function generate_csv_file($data)
    {
        // 建立臨時檔案
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/buygo-exports';

        // 確保目錄存在
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        // 生成檔案名稱
        $filename = 'shipments_' . date('Ymd_His') . '.csv';
        $filepath = $temp_dir . '/' . $filename;

        // 開啟檔案
        $fp = fopen($filepath, 'w');
        if (!$fp) {
            return false;
        }

        // 加入 BOM 以支援 Excel 開啟 UTF-8
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // 寫入每一行
        foreach ($data as $row) {
            fputcsv($fp, $row);
        }

        fclose($fp);

        return $filepath;
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
        if (!file_exists($filepath)) {
            wp_die('檔案不存在');
        }

        if (!$filename) {
            $filename = basename($filepath);
        }

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

        exit;
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
