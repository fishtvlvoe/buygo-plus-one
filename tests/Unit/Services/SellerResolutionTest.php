<?php
/**
 * Seller Resolution Tests
 *
 * 測試「上架幫手建立的商品應歸屬賣家」的身份解析邏輯
 *
 * @package BuyGoPlus\Tests\Unit\Services
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\IdentityService;

class SellerResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // 清理全域 mock 狀態
        $GLOBALS['mock_user_roles'] = [];
        $GLOBALS['mock_helper_rows'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['mock_user_roles'] = [];
        $GLOBALS['mock_helper_rows'] = [];
        parent::tearDown();
    }

    /**
     * 測試：賣家的 resolveActualSellerId 應返回自己
     */
    public function testSellerResolvesToSelf(): void
    {
        $GLOBALS['mock_user_roles'][10] = ['administrator'];

        $result = IdentityService::resolveActualSellerId(10);

        $this->assertEquals(10, $result);
    }

    /**
     * 測試：小幫手的 resolveActualSellerId 應返回綁定的賣家
     */
    public function testHelperResolvesToBoundSeller(): void
    {
        $GLOBALS['mock_user_roles'][50] = ['buygo_helper'];
        $GLOBALS['mock_helper_rows'][50] = [
            'helper_id' => 50,
            'seller_id' => 10,
        ];

        $result = IdentityService::resolveActualSellerId(50);

        $this->assertEquals(10, $result, '小幫手應解析為綁定的賣家 ID');
    }

    /**
     * 測試：上架幫手（buygo_lister）的 resolveActualSellerId 應返回綁定的賣家
     */
    public function testListerResolvesToBoundSeller(): void
    {
        $GLOBALS['mock_user_roles'][60] = ['buygo_lister'];
        $GLOBALS['mock_helper_rows'][60] = [
            'helper_id' => 60,
            'seller_id' => 10,
        ];

        $result = IdentityService::resolveActualSellerId(60);

        $this->assertEquals(10, $result, '上架幫手應解析為綁定的賣家 ID');
    }

    /**
     * 測試：非賣家非小幫手（買家）的 resolveActualSellerId 應返回 null
     */
    public function testBuyerResolvesToNull(): void
    {
        $GLOBALS['mock_user_roles'][99] = ['subscriber'];

        $result = IdentityService::resolveActualSellerId(99);

        $this->assertNull($result, '買家沒有對應的賣家');
    }

    /**
     * 測試：user_id 為 0 或 null 應返回 null
     */
    public function testZeroUserIdResolvesToNull(): void
    {
        $this->assertNull(IdentityService::resolveActualSellerId(0));
    }

    /**
     * 測試：FluentCartService::resolveProductOwner 應正確解析商品擁有者
     */
    public function testResolveProductOwnerForHelper(): void
    {
        $GLOBALS['mock_user_roles'][50] = ['buygo_helper'];
        $GLOBALS['mock_helper_rows'][50] = [
            'helper_id' => 50,
            'seller_id' => 10,
        ];

        $product_data = ['user_id' => 50, 'name' => '測試商品'];
        $owner = \BuyGoPlus\Services\FluentCartService::resolveProductOwner($product_data);

        $this->assertEquals(10, $owner, '小幫手建立的商品應歸屬賣家');
    }

    /**
     * 測試：FluentCartService::resolveProductOwner 賣家自己上架
     */
    public function testResolveProductOwnerForSeller(): void
    {
        $GLOBALS['mock_user_roles'][10] = ['administrator'];

        $product_data = ['user_id' => 10, 'name' => '測試商品'];
        $owner = \BuyGoPlus\Services\FluentCartService::resolveProductOwner($product_data);

        $this->assertEquals(10, $owner, '賣家自己上架，商品歸屬自己');
    }
}
