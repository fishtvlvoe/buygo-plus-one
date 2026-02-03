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
     * MVP 策略：採用「1個出貨單 = 1則通知」的簡化邏輯
     * 理由：避免客戶收到重複或混淆的通知，降低 LINE 推播成本
     *
     * @return void
     */
    public function register_hooks()
    {
        // 監聽出貨單標記為已出貨事件（唯一啟用的通知）
        add_action('buygo/shipment/marked_as_shipped', [$this, 'handle_shipment_marked_shipped'], 10, 1);

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // 以下通知暫時停用，保留程式碼以備未來需求
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //
        // 未來如果客戶需要「訂單完成統計」功能，可取消以下註解：
        //
        // 父訂單完成通知（當所有子訂單都已出貨時觸發）
        // add_action('buygo/parent_order_completed', [$this, 'handle_parent_order_completed'], 10, 1);
        //
        // 單一訂單出貨通知（當不拆單的訂單出貨時觸發）
        // add_action('buygo_order_shipped', [$this, 'handle_order_shipped'], 10, 1);

        $this->debugService->log('NotificationHandler', 'Hooks registered', [
            'active_hooks' => [
                'buygo/shipment/marked_as_shipped'
            ],
            'disabled_hooks' => [
                'buygo/parent_order_completed',
                'buygo_order_shipped'
            ],
            'strategy' => '1 shipment = 1 notification'
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

            // Phase 33-03: 調用通知發送邏輯
            $this->send_shipment_notification($shipment_id);

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
     * 檢查通知是否已發送（Idempotency 機制）
     *
     * 使用 WordPress transient 記錄短期內已處理的出貨單
     * 防止同一張出貨單重複發送通知
     *
     * @param int $shipment_id 出貨單 ID
     * @return bool 是否已發送通知
     */
    private function is_notification_already_sent($shipment_id)
    {
        $transient_key = 'buygo_shipment_notified_' . $shipment_id;
        return get_transient($transient_key) !== false;
    }

    /**
     * 標記通知已發送
     *
     * 設置 transient 記錄，有效期 5 分鐘
     * 足以防止短期內的重複觸發，過期後自動清理
     *
     * @param int $shipment_id 出貨單 ID
     * @return void
     */
    private function mark_notification_sent($shipment_id)
    {
        $transient_key = 'buygo_shipment_notified_' . $shipment_id;
        set_transient($transient_key, time(), 5 * MINUTE_IN_SECONDS);
    }

    /**
     * 發送出貨通知
     *
     * 完整的通知發送流程：
     * 1. Idempotency 檢查
     * 2. 收集出貨單資料
     * 3. 檢查買家 LINE 綁定
     * 4. 準備模板變數
     * 5. 發送通知給買家（不發給賣家和小幫手）
     * 6. 標記通知已發送
     *
     * @param int $shipment_id 出貨單 ID
     * @return void
     */
    private function send_shipment_notification($shipment_id)
    {
        // 1. Idempotency 檢查
        if ($this->is_notification_already_sent($shipment_id)) {
            $this->debugService->log('NotificationHandler', '通知已發送，跳過', [
                'shipment_id' => $shipment_id
            ]);
            return;
        }

        try {
            // 2. 收集出貨單資料
            $shipment_data = $this->collect_shipment_data($shipment_id);
            if (!$shipment_data) {
                return;
            }

            // 3. 取得買家 WordPress User ID
            $customer_id = $shipment_data['customer_id'];

            // 4. 檢查買家是否有 LINE 綁定
            if (!IdentityService::hasLineBinding($customer_id)) {
                $this->debugService->log('NotificationHandler', '買家未綁定 LINE，跳過通知', [
                    'shipment_id' => $shipment_id,
                    'customer_id' => $customer_id
                ]);
                return;
            }

            // 5. 準備模板變數
            $template_args = [
                'product_list' => NotificationTemplates::format_product_list($shipment_data['items']),
                'shipping_method' => NotificationTemplates::format_shipping_method($shipment_data['shipping_method']),
                'estimated_delivery' => NotificationTemplates::format_estimated_delivery($shipment_data['estimated_delivery_at'])
            ];

            // 6. 發送通知（僅發給買家，不發給賣家和小幫手）
            $result = NotificationService::sendText($customer_id, 'shipment_shipped', $template_args);

            if ($result) {
                // 7. 標記通知已發送
                $this->mark_notification_sent($shipment_id);
                $this->debugService->log('NotificationHandler', '出貨通知發送成功', [
                    'shipment_id' => $shipment_id,
                    'customer_id' => $customer_id
                ]);
            } else {
                $this->debugService->log('NotificationHandler', '出貨通知發送失敗', [
                    'shipment_id' => $shipment_id,
                    'customer_id' => $customer_id
                ], 'error');
            }

        } catch (\Exception $e) {
            // 確保通知失敗不影響出貨流程
            $this->debugService->log('NotificationHandler', '出貨通知異常', [
                'shipment_id' => $shipment_id,
                'error' => $e->getMessage()
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
            // 1. 查詢出貨單基本資訊（僅 shipped 狀態）
            $shipment = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}buygo_shipments WHERE id = %d AND status = 'shipped'",
                $shipment_id
            ));

            if (!$shipment) {
                $this->debugService->log('NotificationHandler', '出貨單不存在或狀態不是 shipped', [
                    'shipment_id' => $shipment_id,
                ], 'warning');
                return null;
            }

            // 2. 將 FluentCart customer_id 轉換為 WordPress user_id
            $fct_customer = $wpdb->get_row($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}fct_customers WHERE id = %d",
                $shipment->customer_id
            ));

            if (!$fct_customer || !$fct_customer->user_id) {
                $this->debugService->log('NotificationHandler', 'FluentCart 客戶未連結 WordPress 帳號', [
                    'shipment_id' => $shipment_id,
                    'fct_customer_id' => $shipment->customer_id,
                ], 'warning');
                return null;
            }

            $wp_user_id = $fct_customer->user_id;

            // 3. 查詢出貨單商品清單（JOIN FluentCart 訂單項目取得商品名稱）
            // 欄位名稱使用 product_name 以對應 NotificationTemplates::format_product_list()
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT
                    si.id,
                    si.order_id,
                    si.order_item_id,
                    si.product_id,
                    si.quantity,
                    COALESCE(oi.title, '未知商品') as product_name
                FROM {$wpdb->prefix}buygo_shipment_items si
                LEFT JOIN {$wpdb->prefix}fct_order_items oi ON si.order_item_id = oi.id
                WHERE si.shipment_id = %d",
                $shipment_id
            ), ARRAY_A);

            // 4. 從出貨單取得物流方式和預計送達時間
            $shipping_method = $shipment->shipping_method ?? null;
            $estimated_delivery_at = $shipment->estimated_delivery_at ?? null;

            // 5. 組合完整資訊
            return [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'customer_id' => $wp_user_id, // 修正：使用 WordPress user ID
                'fct_customer_id' => $shipment->customer_id, // 保留原始 FluentCart customer ID 供參考
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

    /**
     * 處理父訂單完成事件
     *
     * 當所有子訂單都已出貨時，父訂單會被標記為完成
     * 此時發送通知給客戶
     *
     * @param int $parent_order_id 父訂單 ID
     * @return void
     */
    public function handle_parent_order_completed($parent_order_id)
    {
        $this->debugService->log('NotificationHandler', '收到父訂單完成事件', [
            'order_id' => $parent_order_id,
        ]);

        $message = "您的訂單已全部出貨完成！\n\n" .
                   "訂單編號：#{order_id}\n" .
                   "訂單金額：NT$ {order_total}\n\n" .
                   "感謝您的購買，期待您再次光臨！";

        $this->send_order_notification($parent_order_id, $message, '父訂單完成');
    }

    /**
     * 處理訂單出貨事件
     *
     * 當單一訂單出貨時發送通知（非子訂單）
     *
     * @param string $order_id 訂單 ID
     * @return void
     */
    public function handle_order_shipped($order_id)
    {
        $this->debugService->log('NotificationHandler', '收到訂單出貨事件', [
            'order_id' => $order_id,
        ]);

        $message = "您的訂單已出貨！\n\n" .
                   "訂單編號：#{order_id}\n" .
                   "訂單金額：NT$ {order_total}\n\n" .
                   "感謝您的購買！";

        $this->send_order_notification($order_id, $message, '訂單出貨', true);
    }

    /**
     * 發送訂單相關的 LINE 通知（共用方法）
     *
     * 統一處理：
     * 1. 查詢訂單資訊
     * 2. 驗證用戶和 LINE 綁定
     * 3. 發送通知
     * 4. 記錄日誌
     *
     * @param int|string $order_id 訂單 ID
     * @param string $message_template 訊息模板（使用 {order_id}, {order_total} 作為佔位符）
     * @param string $event_name 事件名稱（用於日誌）
     * @param bool $skip_child_orders 是否跳過子訂單（預設 false）
     * @return void
     */
    private function send_order_notification($order_id, $message_template, $event_name, $skip_child_orders = false)
    {
        try {
            global $wpdb;

            // 取得訂單資訊（支援數字和字串格式的 order_id）
            $order = $wpdb->get_row($wpdb->prepare(
                "SELECT o.*, c.user_id as wp_user_id, c.first_name, c.last_name, c.email
                 FROM {$wpdb->prefix}fct_orders o
                 LEFT JOIN {$wpdb->prefix}fct_customers c ON o.customer_id = c.id
                 WHERE o.id = %s",
                $order_id
            ));

            if (!$order) {
                $this->debugService->log('NotificationHandler', '找不到訂單', [
                    'order_id' => $order_id,
                    'event' => $event_name,
                ], 'warning');
                return;
            }

            // 如果需要跳過子訂單
            if ($skip_child_orders && !empty($order->parent_id) && $order->parent_id > 0) {
                $this->debugService->log('NotificationHandler', '子訂單出貨，等待父訂單完成時發送通知', [
                    'order_id' => $order_id,
                    'parent_id' => $order->parent_id,
                ], 'info');
                return;
            }

            // 取得 WordPress User ID
            $wp_user_id = $order->wp_user_id;
            if (!$wp_user_id) {
                $this->debugService->log('NotificationHandler', '訂單無對應 WordPress 用戶', [
                    'order_id' => $order_id,
                    'customer_id' => $order->customer_id,
                    'event' => $event_name,
                ], 'warning');
                return;
            }

            // 檢查用戶是否有 LINE 綁定
            if (!IdentityService::hasLineBinding($wp_user_id)) {
                $this->debugService->log('NotificationHandler', '用戶未綁定 LINE，跳過通知', [
                    'wp_user_id' => $wp_user_id,
                    'event' => $event_name,
                ], 'info');
                return;
            }

            // 替換訊息模板中的佔位符
            $order_total = number_format($order->total ?? 0, 0);
            $message = str_replace(
                ['{order_id}', '{order_total}'],
                [$order_id, $order_total],
                $message_template
            );

            // 發送 LINE 通知
            $result = NotificationService::sendRawText($wp_user_id, $message);

            if ($result) {
                $this->debugService->log('NotificationHandler', "{$event_name}通知發送成功", [
                    'wp_user_id' => $wp_user_id,
                    'order_id' => $order_id,
                ]);
            } else {
                $this->debugService->log('NotificationHandler', "{$event_name}通知發送失敗", [
                    'wp_user_id' => $wp_user_id,
                    'order_id' => $order_id,
                ], 'error');
            }

        } catch (\Exception $e) {
            $this->debugService->log('NotificationHandler', "處理{$event_name}事件時發生錯誤", [
                'order_id' => $order_id,
                'error' => $e->getMessage(),
            ], 'error');
        }
    }
}
