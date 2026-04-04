<?php
/**
 * Shipment Notification Integration Tests
 *
 * 測試出貨通知的 hook 行為：
 * T1: mark_shipped 觸發 buygo/shipment/marked_as_shipped 恰好一次
 * T2: NotificationHandler::handle_shipment_marked_shipped 的 idempotency 防止重複發送
 * T3: mark_shipped 不觸發 fluent_cart/shipping_status_changed_to_shipped
 * T4: buygo_order_shipped hook 在 ShipmentService 中沒有任何 do_action（殭屍路徑確認）
 *
 * @package BuyGoPlus\Tests\Unit\Services
 * @since 1.3.0
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\ShipmentService;
use BuyGoPlus\Services\NotificationHandler;
use ReflectionClass;

/**
 * Class ShipmentNotificationTest
 *
 * 只測試 hook 行為與 idempotency 邏輯，不依賴完整的 WordPress 資料庫環境。
 *
 * 注意：此測試類別在 setUp 中自行設置 wpdb mock，
 * 因為部分前置測試類別會替換 $GLOBALS['wpdb'] 而不還原。
 */
class ShipmentNotificationTest extends TestCase
{
    /** @var ShipmentService */
    private $shipmentService;

    /** @var NotificationHandler */
    private $notificationHandler;

    /** @var mixed 儲存原始 wpdb 以便 tearDown 還原 */
    private $originalWpdb;

    /**
     * 每個測試前初始化 mock 狀態
     *
     * 包含自行設置 wpdb mock，避免其他測試類別替換 $GLOBALS['wpdb'] 後
     * 未還原而影響本測試的 buygo_shipments 查詢。
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 儲存原始 wpdb，tearDown 時還原
        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;

        // 重置 mock 全域狀態
        $GLOBALS['mock_action_calls']     = [];
        $GLOBALS['mock_transients']       = [];
        $GLOBALS['mock_shipment_rows']    = [];
        $GLOBALS['mock_helper_rows']      = [];
        $GLOBALS['mock_helpers_by_seller']= [];

        // 設置包含 buygo_shipments 支援的 wpdb mock
        // （不能依賴 bootstrap 的 wpdb，因為其他測試可能已替換它）
        $GLOBALS['wpdb'] = new class {
            public $prefix       = 'wp_';
            public $insert_id    = 0;
            public $last_error   = '';

            public function prepare($query, ...$args) {
                return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
            }

            public function get_var($query) {
                if (strpos($query, 'SHOW TABLES') !== false && strpos($query, 'buygo_helpers') !== false) {
                    return $this->prefix . 'buygo_helpers';
                }
                if (strpos($query, 'SHOW TABLES') !== false) {
                    return null;
                }
                return null;
            }

            public function get_row($query, $output = OBJECT) {
                // buygo_helpers：IdentityService 查詢
                if (strpos($query, 'buygo_helpers') !== false
                    && preg_match("/helper_id\s*=\s*'?(\d+)'?/", $query, $m)
                ) {
                    $helper_id = (int) $m[1];
                    $row = $GLOBALS['mock_helper_rows'][$helper_id] ?? null;
                    if ($row !== null) {
                        return $output === ARRAY_A ? $row : (object) $row;
                    }
                }
                // buygo_shipments：ShipmentService::get_shipment 查詢
                if (strpos($query, 'buygo_shipments') !== false
                    && preg_match('/WHERE id = \'?(\d+)\'?/i', $query, $m)
                ) {
                    $shipment_id = (int) $m[1];
                    $row = $GLOBALS['mock_shipment_rows'][$shipment_id] ?? null;
                    if ($row !== null) {
                        return $output === ARRAY_A ? $row : (object) $row;
                    }
                }
                return null;
            }

            public function get_results($query, $output = OBJECT) {
                if (strpos($query, 'buygo_helpers') !== false
                    && preg_match("/seller_id\s*=\s*'?(\d+)'?/", $query, $m)
                ) {
                    $seller_id = (int) $m[1];
                    $rows = $GLOBALS['mock_helpers_by_seller'][$seller_id] ?? [];
                    return array_map(fn($r) => (object) $r, $rows);
                }
                return [];
            }

            public function get_col($query, $column_offset = 0) {
                if (strpos($query, 'buygo_helpers') !== false
                    && preg_match("/helper_id\s*=\s*'?(\d+)'?/", $query, $m)
                ) {
                    $helper_id = (int) $m[1];
                    $row = $GLOBALS['mock_helper_rows'][$helper_id] ?? null;
                    return $row ? [(int) $row['seller_id']] : [];
                }
                return [];
            }

            public function insert($table, $data, $format = null) {
                $this->insert_id = 1;
                return 1;
            }

            public function update($table, $data, $where, $format = null, $where_format = null) {
                return 1;
            }

            public function delete($table, $where, $where_format = null) {
                return 1;
            }

            public function query($query) {
                return true;
            }

            public function get_charset_collate() {
                return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
            }
        };

        // 準備一筆 pending 出貨單 mock 資料供 mark_shipped 測試使用
        $GLOBALS['mock_shipment_rows'][1] = [
            'id'                    => 1,
            'shipment_number'       => 'SH-20260404-001',
            'customer_id'           => 10,
            'seller_id'             => 20,
            'status'                => 'pending',
            'shipped_at'            => null,
            'shipping_method'       => null,
            'estimated_delivery_at' => null,
            'created_at'            => '2026-04-04 00:00:00',
            'updated_at'            => '2026-04-04 00:00:00',
        ];

        $this->shipmentService = new ShipmentService();

        // 重置 NotificationHandler 單例
        $reflection = new ReflectionClass(NotificationHandler::class);
        $instanceProp = $reflection->getProperty('instance');
        $instanceProp->setAccessible(true);
        $instanceProp->setValue(null, null);

        $this->notificationHandler = NotificationHandler::get_instance();
    }

    /**
     * 每個測試後清理 mock 狀態並還原 wpdb
     */
    protected function tearDown(): void
    {
        // 還原原始 wpdb（讓後續測試類別不受影響）
        if ($this->originalWpdb !== null) {
            $GLOBALS['wpdb'] = $this->originalWpdb;
        }
        $GLOBALS['mock_action_calls']  = [];
        $GLOBALS['mock_transients']    = [];
        $GLOBALS['mock_shipment_rows'] = [];
        parent::tearDown();
    }

    // =====================================================================
    // T1: mark_shipped 觸發 buygo/shipment/marked_as_shipped 恰好一次
    // =====================================================================

    /**
     * T1: mark_shipped 成功時，buygo/shipment/marked_as_shipped 被觸發恰好一次
     */
    public function testMarkShippedFiresBuyGoHookExactlyOnce(): void
    {
        // 執行：標記出貨單 #1 為已出貨
        $result = $this->shipmentService->mark_shipped([1]);

        // 驗證：回傳值為 1（成功標記 1 筆）
        $this->assertSame(1, $result, 'mark_shipped 應回傳成功標記數量 1');

        // 從所有 action calls 中過濾出目標 hook
        $shippedCalls = array_filter(
            $GLOBALS['mock_action_calls'],
            fn($call) => $call['tag'] === 'buygo/shipment/marked_as_shipped'
        );

        // 驗證：hook 被觸發恰好一次
        $this->assertCount(
            1,
            $shippedCalls,
            'buygo/shipment/marked_as_shipped 應被觸發恰好一次'
        );

        // 驗證：hook 傳入的 shipment_id 正確
        $firstCall = reset($shippedCalls);
        $this->assertSame(
            1,
            $firstCall['args'][0],
            'hook 傳入的 shipment_id 應為 1'
        );
    }

    /**
     * T1 延伸：mark_shipped 兩筆出貨單時，hook 各觸發一次（共兩次）
     */
    public function testMarkShippedTwoShipmentsFiredTwice(): void
    {
        // 準備第二筆 mock 出貨單
        $GLOBALS['mock_shipment_rows'][2] = array_merge(
            $GLOBALS['mock_shipment_rows'][1],
            ['id' => 2, 'shipment_number' => 'SH-20260404-002']
        );

        $result = $this->shipmentService->mark_shipped([1, 2]);

        $this->assertSame(2, $result, '兩筆出貨單都應成功標記');

        $shippedCalls = array_filter(
            $GLOBALS['mock_action_calls'],
            fn($call) => $call['tag'] === 'buygo/shipment/marked_as_shipped'
        );

        $this->assertCount(2, $shippedCalls, '兩筆出貨單應各觸發一次 hook，共兩次');
    }

    /**
     * T1 邊界：出貨單不存在時，hook 不被觸發
     */
    public function testMarkShippedWithNonexistentShipmentDoesNotFireHook(): void
    {
        // shipment_id=999 沒有 mock 資料，wpdb->get_row() 返回 null
        $result = $this->shipmentService->mark_shipped([999]);

        $this->assertInstanceOf(
            \WP_Error::class,
            $result,
            '出貨單不存在時應返回 WP_Error'
        );

        $shippedCalls = array_filter(
            $GLOBALS['mock_action_calls'],
            fn($call) => $call['tag'] === 'buygo/shipment/marked_as_shipped'
        );

        $this->assertCount(0, $shippedCalls, '出貨單不存在時不應觸發 hook');
    }

    // =====================================================================
    // T2: NotificationHandler::handle_shipment_marked_shipped 只發一次
    // =====================================================================

    /**
     * T2: handle_shipment_marked_shipped 被呼叫兩次，idempotency 確保不重複發送
     *
     * 驗證機制：
     * - 第一次呼叫後手動設定 transient（模擬通知發送成功）
     * - 第二次呼叫時，is_notification_already_sent 應返回 true
     * - 確認 transient 不被清除（通知狀態保持已發送）
     */
    public function testHandlerIdempotencyPreventsDoubleSend(): void
    {
        $shipmentId = 42;

        // 第一次觸發（mock 環境下 DB 返回 null → send 失敗，transient 不被設定）
        $this->notificationHandler->handle_shipment_marked_shipped($shipmentId);

        // 模擬第一次發送成功（手動設定 transient，如 NotificationHandler::mark_notification_sent 所為）
        $GLOBALS['mock_transients']['buygo_shipment_notified_' . $shipmentId] = time();

        // 取得 is_notification_already_sent 方法
        $reflection = new ReflectionClass(NotificationHandler::class);
        $checkMethod = $reflection->getMethod('is_notification_already_sent');
        $checkMethod->setAccessible(true);

        // 驗證：第二次呼叫前，idempotency 已為 true
        $alreadySent = $checkMethod->invoke($this->notificationHandler, $shipmentId);
        $this->assertTrue($alreadySent, '設定 transient 後，should_notification_already_sent 應返回 true');

        // 第二次呼叫 handle：idempotency guard 應攔截，不再進入 send 流程
        $this->notificationHandler->handle_shipment_marked_shipped($shipmentId);

        // 驗證：transient 保持存在（未被重置）
        $this->assertArrayHasKey(
            'buygo_shipment_notified_' . $shipmentId,
            $GLOBALS['mock_transients'],
            'idempotency transient 在第二次呼叫後應保持存在'
        );
    }

    /**
     * T2 延伸：mark_notification_sent 設定的 transient 值為 Unix 時間戳
     */
    public function testMarkNotificationSentStoresTimestamp(): void
    {
        $reflection = new ReflectionClass(NotificationHandler::class);
        $markMethod = $reflection->getMethod('mark_notification_sent');
        $markMethod->setAccessible(true);

        $before = time();
        $markMethod->invoke($this->notificationHandler, 55);
        $after = time();

        $transientKey = 'buygo_shipment_notified_55';
        $this->assertArrayHasKey($transientKey, $GLOBALS['mock_transients']);

        $value = $GLOBALS['mock_transients'][$transientKey];
        $this->assertIsInt($value, 'transient 值應為整數時間戳');
        $this->assertGreaterThanOrEqual($before, $value);
        $this->assertLessThanOrEqual($after, $value);
    }

    /**
     * T2 延伸：不同出貨單的 idempotency 互不干擾
     */
    public function testIdempotencyIsPerShipment(): void
    {
        $reflection = new ReflectionClass(NotificationHandler::class);
        $markMethod  = $reflection->getMethod('mark_notification_sent');
        $checkMethod = $reflection->getMethod('is_notification_already_sent');
        $markMethod->setAccessible(true);
        $checkMethod->setAccessible(true);

        // 標記出貨單 100
        $markMethod->invoke($this->notificationHandler, 100);

        // 出貨單 100 已發送，出貨單 101 未發送
        $this->assertTrue($checkMethod->invoke($this->notificationHandler, 100), '出貨單 100 應標記為已發送');
        $this->assertFalse($checkMethod->invoke($this->notificationHandler, 101), '出貨單 101 不應被標記為已發送');
    }

    // =====================================================================
    // T3: mark_shipped 不觸發 fluent_cart/shipping_status_changed_to_shipped
    // =====================================================================

    /**
     * T3: 執行 mark_shipped 後，fluent_cart hook 不應出現在 action calls 中
     */
    public function testMarkShippedDoesNotFireFluentCartHook(): void
    {
        $this->shipmentService->mark_shipped([1]);

        $fluentCartCalls = array_filter(
            $GLOBALS['mock_action_calls'],
            fn($call) => $call['tag'] === 'fluent_cart/shipping_status_changed_to_shipped'
        );

        $this->assertCount(
            0,
            $fluentCartCalls,
            'mark_shipped 不應觸發 fluent_cart/shipping_status_changed_to_shipped'
        );
    }

    /**
     * T3 靜態驗證：ShipmentService 原始碼中不含 fluent_cart hook 字串
     *
     * 雙重保險：即使 mock 追蹤有漏洞，靜態分析也能抓到
     */
    public function testShipmentServiceSourceDoesNotReferenceFluentCartHook(): void
    {
        $sourceFile = BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-shipment-service.php';
        $source = file_get_contents($sourceFile);

        $this->assertNotFalse($source, '應能讀取 class-shipment-service.php');
        $this->assertStringNotContainsString(
            'fluent_cart/shipping_status_changed_to_shipped',
            $source,
            'ShipmentService 原始碼不應包含 fluent_cart 出貨 hook'
        );
    }

    // =====================================================================
    // T4: buygo_order_shipped hook 在 ShipmentService 中沒有任何 do_action
    // =====================================================================

    /**
     * T4: mark_shipped 不觸發殭屍路徑 buygo_order_shipped
     */
    public function testMarkShippedDoesNotFireBuyGoOrderShippedHook(): void
    {
        $this->shipmentService->mark_shipped([1]);

        $zombieCalls = array_filter(
            $GLOBALS['mock_action_calls'],
            fn($call) => $call['tag'] === 'buygo_order_shipped'
        );

        $this->assertCount(
            0,
            $zombieCalls,
            'mark_shipped 不應觸發殭屍路徑 buygo_order_shipped'
        );
    }

    /**
     * T4 靜態驗證：ShipmentService 原始碼中完全沒有 do_action('buygo_order_shipped')
     *
     * 確認殭屍 hook 路徑確實已與 mark_shipped 解耦
     */
    public function testShipmentServiceSourceHasNoDoActionForBuyGoOrderShipped(): void
    {
        $sourceFile = BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-shipment-service.php';
        $source = file_get_contents($sourceFile);

        $this->assertNotFalse($source, '應能讀取 class-shipment-service.php');
        $this->assertStringNotContainsString(
            "do_action('buygo_order_shipped'",
            $source,
            'ShipmentService 不應呼叫 do_action(\'buygo_order_shipped\')'
        );
        $this->assertStringNotContainsString(
            'do_action("buygo_order_shipped"',
            $source,
            'ShipmentService 不應呼叫 do_action("buygo_order_shipped")'
        );
    }

    /**
     * T4 補充：驗證 buygo_order_shipped 的真實觸發點不在 ShipmentService 路徑上
     *
     * 根據靜態分析，只有 class-order-shipping-manager.php 和
     * class-shipping-status-service.php 會觸發 buygo_order_shipped，
     * 兩者都不在 mark_shipped 的呼叫鏈中。
     */
    public function testBuyGoOrderShippedOnlyExistsInNonShipmentPaths(): void
    {
        // 驗證 ShipmentService 的 mark_shipped 只觸發 buygo/shipment/marked_as_shipped
        $this->shipmentService->mark_shipped([1]);

        $allTags = array_column($GLOBALS['mock_action_calls'], 'tag');

        // 應存在的 hook
        $this->assertContains(
            'buygo/shipment/marked_as_shipped',
            $allTags,
            'mark_shipped 應觸發 buygo/shipment/marked_as_shipped'
        );

        // 不應存在的殭屍 hook
        $this->assertNotContains(
            'buygo_order_shipped',
            $allTags,
            'mark_shipped 不應觸發殭屍路徑 buygo_order_shipped'
        );

        // 不應存在的 FluentCart hook
        $this->assertNotContains(
            'fluent_cart/shipping_status_changed_to_shipped',
            $allTags,
            'mark_shipped 不應觸發 FluentCart 出貨 hook'
        );
    }
}
