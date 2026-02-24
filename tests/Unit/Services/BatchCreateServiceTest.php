<?php
/**
 * BatchCreateService Unit Tests
 *
 * 測試批量商品建立服務的核心功能
 *
 * @package BuyGoPlus\Tests\Unit\Services
 * @since 3.1.0
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\BatchCreateService;
use BuyGoPlus\Services\FluentCartService;
use BuyGoPlus\Services\ProductLimitChecker;

/**
 * Class BatchCreateServiceTest
 *
 * 測試 BatchCreateService 的批量商品建立功能：
 * - 空陣列驗證
 * - 超過單次上限驗證
 * - 配額不足驗證
 * - 缺少必填欄位驗證
 * - 負數價格驗證
 * - 部分失敗處理
 * - 全部成功處理
 */
class BatchCreateServiceTest extends TestCase
{
    /**
     * 建立帶有 mock 依賴的 BatchCreateService
     *
     * @param FluentCartService|null $fluentCartService
     * @param ProductLimitChecker|null $productLimitChecker
     * @return BatchCreateService
     */
    private function createService($fluentCartService = null, $productLimitChecker = null): BatchCreateService
    {
        return new BatchCreateService($fluentCartService, $productLimitChecker);
    }

    /**
     * 建立 FluentCartService mock
     *
     * @param array $returnValues create_product 的回傳值序列
     * @return FluentCartService
     */
    private function mockFluentCartService(array $returnValues = []): FluentCartService
    {
        $mock = $this->createMock(FluentCartService::class);

        if (!empty($returnValues)) {
            $mock->method('create_product')
                ->willReturnOnConsecutiveCalls(...$returnValues);
        }

        return $mock;
    }

    /**
     * 建立 ProductLimitChecker mock
     *
     * @param array $canAddResult canAddProduct 的回傳值
     * @return ProductLimitChecker
     */
    private function mockProductLimitChecker(array $canAddResult): ProductLimitChecker
    {
        $mock = $this->createMock(ProductLimitChecker::class);
        $mock->method('canAddProduct')
            ->willReturn($canAddResult);

        return $mock;
    }

    // ========================================
    // Case 1: 空陣列
    // ========================================

    /**
     * 測試空陣列回傳錯誤
     */
    public function testEmptyItemsReturnsError(): void
    {
        $service = $this->createService();

        $result = $service->batchCreate([], 1);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('不可為空', $result['error']);
    }

    // ========================================
    // Case 2: 超過單次上限（50 筆）
    // ========================================

    /**
     * 測試超過 50 筆上限回傳錯誤
     */
    public function testExceedMaxItemsReturnsError(): void
    {
        $service = $this->createService();

        // 建立 51 筆資料
        $items = array_fill(0, 51, ['title' => '測試商品', 'price' => 100]);

        $result = $service->batchCreate($items, 1);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('50', $result['error']);
    }

    // ========================================
    // Case 3: 配額不足
    // ========================================

    /**
     * 測試配額不足時整批拒絕
     */
    public function testQuotaExceededReturnsError(): void
    {
        $limitChecker = $this->mockProductLimitChecker([
            'can_add' => true,
            'current' => 8,
            'limit' => 10,  // 只剩 2 個配額
            'message' => '還可新增 2 個商品',
        ]);

        $service = $this->createService(null, $limitChecker);

        // 嘗試建立 5 筆（超過剩餘配額 2）
        $items = array_fill(0, 5, ['title' => '測試商品', 'price' => 100]);

        $result = $service->batchCreate($items, 1);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('配額', $result['error']);
    }

    /**
     * 測試配額為 0（無限制）時不阻擋
     */
    public function testUnlimitedQuotaPassesCheck(): void
    {
        $limitChecker = $this->mockProductLimitChecker([
            'can_add' => true,
            'current' => 100,
            'limit' => 0,  // 無限制
            'message' => '無商品數量限制',
        ]);

        $fluentCart = $this->mockFluentCartService([101, 102]);

        $service = $this->createService($fluentCart, $limitChecker);

        $items = [
            ['title' => '商品 A', 'price' => 100],
            ['title' => '商品 B', 'price' => 200],
        ];

        $result = $service->batchCreate($items, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['created']);
    }

    // ========================================
    // Case 4: 缺少 title
    // ========================================

    /**
     * 測試缺少 title 的商品回傳驗證錯誤
     */
    public function testMissingTitleReturnsValidationError(): void
    {
        $limitChecker = $this->mockProductLimitChecker([
            'can_add' => true,
            'current' => 0,
            'limit' => 0,
            'message' => '無限制',
        ]);

        // FluentCart 不應該被呼叫（因為驗證失敗的那筆不會建立）
        // 但第二筆會成功
        $fluentCart = $this->mockFluentCartService([101]);

        $service = $this->createService($fluentCart, $limitChecker);

        $items = [
            ['price' => 100],  // 缺少 title
            ['title' => '商品 B', 'price' => 200],  // 正常
        ];

        $result = $service->batchCreate($items, 1);

        // 整體回傳 success（部分成功也算）
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['failed']);

        // 第一筆失敗
        $this->assertFalse($result['results'][0]['success']);
        $this->assertStringContainsString('名稱', $result['results'][0]['error']);

        // 第二筆成功
        $this->assertTrue($result['results'][1]['success']);
        $this->assertEquals(101, $result['results'][1]['product_id']);
    }

    // ========================================
    // Case 5: 負數價格
    // ========================================

    /**
     * 測試負數價格回傳驗證錯誤
     */
    public function testNegativePriceReturnsValidationError(): void
    {
        $limitChecker = $this->mockProductLimitChecker([
            'can_add' => true,
            'current' => 0,
            'limit' => 0,
            'message' => '無限制',
        ]);

        $fluentCart = $this->mockFluentCartService([101]);

        $service = $this->createService($fluentCart, $limitChecker);

        $items = [
            ['title' => '商品 A', 'price' => -50],  // 負數價格
            ['title' => '商品 B', 'price' => 200],   // 正常
        ];

        $result = $service->batchCreate($items, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['failed']);

        // 第一筆失敗
        $this->assertFalse($result['results'][0]['success']);
        $this->assertStringContainsString('負數', $result['results'][0]['error']);

        // 第二筆成功
        $this->assertTrue($result['results'][1]['success']);
    }

    // ========================================
    // Case 6: 部分失敗（FluentCart 建立失敗）
    // ========================================

    /**
     * 測試 FluentCart 建立失敗時該筆失敗、其他成功
     */
    public function testPartialFluentCartFailure(): void
    {
        $limitChecker = $this->mockProductLimitChecker([
            'can_add' => true,
            'current' => 0,
            'limit' => 0,
            'message' => '無限制',
        ]);

        $wpError = new \WP_Error('create_failed', '建立商品失敗');

        $fluentCart = $this->mockFluentCartService([
            101,       // 第一筆成功
            $wpError,  // 第二筆失敗
            103,       // 第三筆成功
        ]);

        $service = $this->createService($fluentCart, $limitChecker);

        $items = [
            ['title' => '商品 A', 'price' => 100],
            ['title' => '商品 B', 'price' => 200],
            ['title' => '商品 C', 'price' => 300],
        ];

        $result = $service->batchCreate($items, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(2, $result['created']);
        $this->assertEquals(1, $result['failed']);

        // 第一筆成功
        $this->assertTrue($result['results'][0]['success']);
        $this->assertEquals(101, $result['results'][0]['product_id']);

        // 第二筆失敗
        $this->assertFalse($result['results'][1]['success']);
        $this->assertStringContainsString('建立商品失敗', $result['results'][1]['error']);
        $this->assertNull($result['results'][1]['product_id']);

        // 第三筆成功
        $this->assertTrue($result['results'][2]['success']);
        $this->assertEquals(103, $result['results'][2]['product_id']);
    }

    // ========================================
    // Case 7: 全部成功
    // ========================================

    /**
     * 測試全部成功的批量建立
     */
    public function testAllItemsSucceed(): void
    {
        $limitChecker = $this->mockProductLimitChecker([
            'can_add' => true,
            'current' => 0,
            'limit' => 10,
            'message' => '還可新增 10 個商品',
        ]);

        $fluentCart = $this->mockFluentCartService([201, 202, 203]);

        $service = $this->createService($fluentCart, $limitChecker);

        $items = [
            ['title' => '商品 A', 'price' => 100, 'quantity' => 5],
            ['title' => '商品 B', 'price' => 200, 'description' => '描述'],
            ['title' => '商品 C', 'price' => 0],  // 免費商品
        ];

        $result = $service->batchCreate($items, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(3, $result['created']);
        $this->assertEquals(0, $result['failed']);

        // 每筆都成功
        foreach ($result['results'] as $i => $r) {
            $this->assertTrue($r['success'], "第 {$i} 筆應該成功");
            $this->assertNotNull($r['product_id'], "第 {$i} 筆應該有 product_id");
            $this->assertNull($r['error'], "第 {$i} 筆不應有 error");
            $this->assertEquals($i, $r['index'], "第 {$i} 筆的 index 應正確");
        }
    }

    // ========================================
    // 邊界情況
    // ========================================

    /**
     * 測試 title 為空字串也算缺少
     */
    public function testEmptyStringTitleReturnsError(): void
    {
        $limitChecker = $this->mockProductLimitChecker([
            'can_add' => true,
            'current' => 0,
            'limit' => 0,
            'message' => '無限制',
        ]);

        $service = $this->createService(null, $limitChecker);

        $items = [
            ['title' => '', 'price' => 100],
            ['title' => '  ', 'price' => 100],  // 純空白
        ];

        $result = $service->batchCreate($items, 1);

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(2, $result['failed']);
    }

    /**
     * 測試剛好 50 筆不超限
     */
    public function testExactly50ItemsIsAllowed(): void
    {
        $limitChecker = $this->mockProductLimitChecker([
            'can_add' => true,
            'current' => 0,
            'limit' => 0,
            'message' => '無限制',
        ]);

        // 建立 50 個回傳值
        $returnValues = range(1001, 1050);
        $fluentCart = $this->mockFluentCartService($returnValues);

        $service = $this->createService($fluentCart, $limitChecker);

        $items = array_fill(0, 50, ['title' => '測試商品', 'price' => 100]);

        $result = $service->batchCreate($items, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals(50, $result['total']);
        $this->assertEquals(50, $result['created']);
    }

    /**
     * 測試配額剛好足夠
     */
    public function testQuotaExactlyEnough(): void
    {
        $limitChecker = $this->mockProductLimitChecker([
            'can_add' => true,
            'current' => 7,
            'limit' => 10,  // 剩 3 個配額
            'message' => '還可新增 3 個商品',
        ]);

        $fluentCart = $this->mockFluentCartService([201, 202, 203]);

        $service = $this->createService($fluentCart, $limitChecker);

        $items = [
            ['title' => '商品 A', 'price' => 100],
            ['title' => '商品 B', 'price' => 200],
            ['title' => '商品 C', 'price' => 300],
        ];

        $result = $service->batchCreate($items, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['created']);
    }

    /**
     * 測試 canAddProduct 回傳 can_add = false（已達上限）
     */
    public function testCanAddProductReturnsFalse(): void
    {
        $limitChecker = $this->mockProductLimitChecker([
            'can_add' => false,
            'current' => 10,
            'limit' => 10,
            'message' => '已達上架限制',
        ]);

        $service = $this->createService(null, $limitChecker);

        $items = [['title' => '商品', 'price' => 100]];

        $result = $service->batchCreate($items, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('配額', $result['error']);
    }
}
