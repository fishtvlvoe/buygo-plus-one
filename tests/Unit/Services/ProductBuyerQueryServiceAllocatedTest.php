<?php

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;

/**
 * TDD 紅燈測試 — ProductBuyerQueryService::buildBuyerOrderEntry() allocated 行為
 *
 * 驗證「已分配」來源從 line_meta._allocated_qty 改為 child orders 後的正確性。
 *
 * 紅燈說明：
 *   - 當前 buildBuyerOrderEntry 讀 $metaData['_allocated_qty']（line_meta）
 *   - 紅燈測試設定 line_meta._allocated_qty=0，child orders 實際 allocated=2
 *   - 預期輸出 allocated_quantity=2，但當前實作輸出 0 → 紅燈
 *
 * 測試策略：
 *   - 用 ReflectionMethod 存取 private buildBuyerOrderEntry
 *   - 構造假 $item stdClass（模擬 OrderItem 物件屬性）
 *   - Phase 3 將 buildBuyerOrderEntry 簽名新增 $allocatedMap 後，測試不再需要修改
 *
 * 注意：此測試直接 require 相關 class，不依賴 bootstrap 中沒有的 FluentCart ORM 功能。
 */
class ProductBuyerQueryServiceAllocatedTest extends TestCase
{
    /** @var object 備份原始全域 $wpdb */
    private $originalWpdb;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // FluentCart\App\Models\OrderItem stub（若尚未定義）
        if (!class_exists('FluentCart\App\Models\OrderItem')) {
            eval('
                namespace FluentCart\App\Models;
                class OrderItem {
                    public static function whereIn($col, $vals) { return new static(); }
                    public function whereHas($rel, $cb) { return $this; }
                    public function with($rels) { return $this; }
                    public function get() { return new class { public function pluck($k) { return new class { public function toArray() { return []; } }; } public function toArray() { return []; } }; }
                }
            ');
        }

        // FluentCart\App\Models\ProductVariation stub（若尚未定義）
        if (!class_exists('FluentCart\App\Models\ProductVariation')) {
            eval('
                namespace FluentCart\App\Models;
                class ProductVariation {
                    public $post_id;
                    public $variation_title;
                    public static function with($r) { return new static(); }
                    public function find($id) { return null; }
                    public static function __callStatic($m, $a) { return new static(); }
                }
            ');
        }

        // ProductVariationService stub（若尚未定義）
        if (!class_exists('BuyGoPlus\Services\ProductVariationService')) {
            require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-product-variation-service.php';
        }

        // ProductBuyerQueryService（待測 class）
        if (!class_exists('BuyGoPlus\Services\ProductBuyerQueryService')) {
            require_once BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-product-buyer-query-service.php';
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        // 備份 $wpdb（buildBuyerOrderEntry 本身不打 DB，但 constructor 可能用到）
        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->originalWpdb !== null) {
            $GLOBALS['wpdb'] = $this->originalWpdb;
        }
        parent::tearDown();
    }

    /**
     * 建立假的 $item stdClass，模擬 FluentCart OrderItem 的資料結構
     *
     * @param int    $orderId       父訂單 ID
     * @param int    $objectId      variation ID
     * @param int    $quantity      下單數量
     * @param string $lineMeta      JSON 格式的 line_meta（內含 _allocated_qty）
     * @param int    $orderItemId   order item ID
     */
    private function makeItem(
        int $orderId,
        int $objectId,
        int $quantity,
        string $lineMeta = '{"_allocated_qty":"0"}',
        int $orderItemId = 1001
    ): object {
        $order = new \stdClass();
        $order->id = $orderId;
        $order->invoice_no = "#{$orderId}";
        $order->shipping_status = 'unshipped';
        $order->created_at = '2025-01-01 00:00:00';

        $customer = new \stdClass();
        $customer->id = 9001;
        $customer->full_name = '測試客戶';
        $customer->email = 'test@example.com';

        $order->customer = $customer;

        $item = new \stdClass();
        $item->id = $orderItemId;
        $item->object_id = $objectId;
        $item->quantity = $quantity;
        $item->line_meta = $lineMeta;
        $item->order = $order;

        return $item;
    }

    /**
     * 用 Reflection 呼叫 private buildBuyerOrderEntry
     *
     * Phase 3 改 buildBuyerOrderEntry 簽名後，此 helper 需同步更新。
     * 第四個參數 $allocatedMap 為 Phase 3 新增的參數（紅燈階段傳空 []，綠燈後傳實際值）。
     */
    private function callBuildBuyerOrderEntry(
        object $service,
        object $item,
        array $actualShippedMap,
        array $purchasedMap,
        array $variationTitles,
        array $allocatedMap = []
    ): array {
        $ref = new \ReflectionMethod($service, 'buildBuyerOrderEntry');
        $ref->setAccessible(true);

        // 偵測簽名：Phase 3 後 buildBuyerOrderEntry 多一個 $allocatedMap 參數
        $params = $ref->getParameters();
        if (count($params) >= 5) {
            // Phase 3 後的新簽名（含 $allocatedMap）
            return $ref->invoke($service, $item, $actualShippedMap, $purchasedMap, $variationTitles, $allocatedMap);
        }

        // Phase 1 紅燈期間：舊簽名，不傳 allocatedMap
        return $ref->invoke($service, $item, $actualShippedMap, $purchasedMap, $variationTitles);
    }

    // ---------------------------------------------------------------
    // 1.4 allocated_quantity 應來自 child orders，不是 line_meta
    // ---------------------------------------------------------------

    /**
     * @test
     * 當 line_meta._allocated_qty=0，但 allocatedMap 指示 child orders 實際 allocated=2，
     * 輸出的 allocated_quantity 應為 2。
     *
     * 紅燈：當前實作讀 $metaData['_allocated_qty']=0 → 輸出 0，預期 2 → FAIL
     * 綠燈：Phase 3 後讀 $allocatedMap → 輸出 2 → PASS
     */
    public function test_buildBuyerOrderEntry_uses_child_orders_not_line_meta(): void
    {
        $service = new \BuyGoPlus\Services\ProductBuyerQueryService();

        // line_meta 刻意寫 _allocated_qty=0（舊快照，不同步）
        $item = $this->makeItem(
            orderId: 1746,
            objectId: 1055,
            quantity: 3,
            lineMeta: '{"_allocated_qty":"0","_shipped_qty":"0"}',
            orderItemId: 5001
        );

        // child orders 實際 allocated=2（SSOT 真相）
        $allocatedMap = [1746 => [1055 => 2]];

        $entry = $this->callBuildBuyerOrderEntry(
            service: $service,
            item: $item,
            actualShippedMap: [],
            purchasedMap: [1055 => 10],
            variationTitles: [],
            allocatedMap: $allocatedMap
        );

        $this->assertSame(
            2,
            $entry['allocated_quantity'],
            'allocated_quantity 應來自 child orders（allocatedMap），不是 line_meta._allocated_qty=0'
        );

        $this->assertSame(
            1, // max(0, quantity=3 - allocated=2)
            $entry['pending_quantity'],
            'pending_quantity 應為 max(0, quantity - child_allocated) = 1'
        );
    }

    // ---------------------------------------------------------------
    // 1.5 pending_quantity 公式：max(0, quantity - child_allocated)
    // ---------------------------------------------------------------

    /**
     * @test
     * 驗證兩個 pending 計算邊界：
     *   Case A：quantity=5, child allocated=3 → pending=2
     *   Case B：quantity=3, child allocated=5（超分配）→ pending=0
     */
    public function test_buildBuyerOrderEntry_pending_equals_quantity_minus_child_allocated(): void
    {
        $service = new \BuyGoPlus\Services\ProductBuyerQueryService();

        // Case A：正常分配
        $itemA = $this->makeItem(
            orderId: 1746,
            objectId: 1055,
            quantity: 5,
            lineMeta: '{"_allocated_qty":"0"}',
            orderItemId: 5001
        );

        $entryA = $this->callBuildBuyerOrderEntry(
            service: $service,
            item: $itemA,
            actualShippedMap: [],
            purchasedMap: [1055 => 10],
            variationTitles: [],
            allocatedMap: [1746 => [1055 => 3]]
        );

        $this->assertSame(3, $entryA['allocated_quantity'], 'Case A: allocated_quantity=3');
        $this->assertSame(2, $entryA['pending_quantity'], 'Case A: pending=max(0,5-3)=2');

        // Case B：超分配（子訂單分配超過下單量）
        $itemB = $this->makeItem(
            orderId: 1747,
            objectId: 1055,
            quantity: 3,
            lineMeta: '{"_allocated_qty":"0"}',
            orderItemId: 5002
        );

        $entryB = $this->callBuildBuyerOrderEntry(
            service: $service,
            item: $itemB,
            actualShippedMap: [],
            purchasedMap: [1055 => 10],
            variationTitles: [],
            allocatedMap: [1747 => [1055 => 5]]
        );

        $this->assertSame(5, $entryB['allocated_quantity'], 'Case B: allocated_quantity=5（超分配）');
        $this->assertSame(0, $entryB['pending_quantity'], 'Case B: pending=max(0,3-5)=0');
    }
}
