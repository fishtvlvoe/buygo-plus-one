<?php
/**
 * Notification Handler
 *
 * 出貨通知處理器 - 監聽出貨事件並觸發通知
 *
 * @package BuyGoPlus\Services
 * @since 1.3.0
 */

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NotificationHandler
 *
 * 事件驅動架構：監聽 ShipmentService 觸發的 Action Hook，
 * 收集出貨單完整資訊並準備通知內容。
 *
 * 設計原則：
 * - 使用 try-catch 確保通知失敗不影響出貨流程
 * - 使用 DebugService 記錄所有事件和錯誤
 * - 單例模式確保只有一個實例
 */
class NotificationHandler
{
    /**
     * 單例實例
     *
     * @var NotificationHandler|null
     */
    private static $instance = null;

    /**
     * Debug Service 實例
     *
     * @var DebugService|null
     */
    private $debugService;

    /**
     * 私有建構函數（Singleton 模式）
     */
    private function __construct()
    {
        $this->debugService = DebugService::get_instance();
    }

    /**
     * 取得單例實例
     *
     * @return NotificationHandler
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 註冊 WordPress Action Hooks
     *
     * 監聽 ShipmentService 觸發的出貨事件
     *
     * @return void
     */
    public function register_hooks()
    {
        add_action('buygo/shipment/marked_as_shipped', [$this, 'handle_shipment_marked_shipped'], 10, 1);

        $this->debugService->log('NotificationHandler', 'Hooks registered', [
            'hook' => 'buygo/shipment/marked_as_shipped',
        ]);
    }

    /**
     * 處理出貨單標記為已出貨事件
     *
     * 當 ShipmentService::mark_shipped() 觸發 Hook 時執行
     * 收集出貨單資訊並準備發送通知
     *
     * @param int $shipment_id 出貨單 ID
     * @return void
     */
    public function handle_shipment_marked_shipped($shipment_id)
    {
        try {
            $this->debugService->log('NotificationHandler', '收到出貨事件', [
                'shipment_id' => $shipment_id,
            ]);

            // 收集出貨單完整資訊
            $shipment_data = $this->collect_shipment_data($shipment_id);

            if (!$shipment_data) {
                $this->debugService->log('NotificationHandler', '收集出貨單資訊失敗', [
                    'shipment_id' => $shipment_id,
                ], 'warning');
                return;
            }

            $this->debugService->log('NotificationHandler', '出貨單資訊收集成功', [
                'shipment_id' => $shipment_id,
                'shipment_number' => $shipment_data['shipment_number'],
                'customer_id' => $shipment_data['customer_id'],
                'seller_id' => $shipment_data['seller_id'],
                'items_count' => count($shipment_data['items']),
            ]);

            // TODO: Phase 33-02 - 使用模板引擎生成通知內容
            // TODO: Phase 33-03 - 調用 NotificationService 發送通知

        } catch (\Exception $e) {
            // 通知失敗不影響出貨流程，僅記錄錯誤
            $this->debugService->log('NotificationHandler', '處理出貨通知失敗', [
                'shipment_id' => $shipment_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 'error');
        }
    }

    /**
     * 收集出貨單完整資訊
     *
     * 查詢出貨單基本資訊、商品清單、物流方式、預計送達時間等
     *
     * @param int $shipment_id 出貨單 ID
     * @return array|null 出貨單資訊或 null（查詢失敗時）
     */
    private function collect_shipment_data($shipment_id)
    {
        global $wpdb;

        try {
            // 1. 查詢出貨單基本資訊
            $shipment = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}buygo_shipments WHERE id = %d",
                $shipment_id
            ));

            if (!$shipment) {
                $this->debugService->log('NotificationHandler', '出貨單不存在', [
                    'shipment_id' => $shipment_id,
                ], 'error');
                return null;
            }

            // 2. 查詢出貨單商品清單（JOIN 產品資料）
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT
                    si.id,
                    si.order_id,
                    si.order_item_id,
                    si.product_id,
                    si.quantity,
                    p.title as product_title,
                    p.price as product_price,
                    p.image_url as product_image
                FROM {$wpdb->prefix}buygo_shipment_items si
                LEFT JOIN {$wpdb->prefix}buygo_products p ON si.product_id = p.id
                WHERE si.shipment_id = %d",
                $shipment_id
            ), ARRAY_A);

            // 3. 查詢物流方式（從訂單或出貨單設定）
            // 目前從第一個訂單項目取得物流方式
            $shipping_method = null;
            $estimated_delivery_at = null;

            if (!empty($items)) {
                $first_order_id = $items[0]['order_id'];
                $order_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT shipping_method, estimated_delivery_at
                     FROM {$wpdb->prefix}fct_orders
                     WHERE id = %d",
                    $first_order_id
                ));

                if ($order_data) {
                    $shipping_method = $order_data->shipping_method;
                    $estimated_delivery_at = $order_data->estimated_delivery_at;
                }
            }

            // 4. 如果訂單沒有 estimated_delivery_at，從出貨單取得（Phase 32 新增的欄位）
            if (!$estimated_delivery_at && isset($shipment->estimated_delivery_at)) {
                $estimated_delivery_at = $shipment->estimated_delivery_at;
            }

            // 5. 組合完整資訊
            return [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'customer_id' => $shipment->customer_id,
                'seller_id' => $shipment->seller_id,
                'status' => $shipment->status,
                'shipped_at' => $shipment->shipped_at,
                'estimated_delivery_at' => $estimated_delivery_at,
                'shipping_method' => $shipping_method,
                'items' => $items,
            ];

        } catch (\Exception $e) {
            $this->debugService->log('NotificationHandler', '收集出貨單資訊異常', [
                'shipment_id' => $shipment_id,
                'error' => $e->getMessage(),
            ], 'error');
            return null;
        }
    }
}
