<?php
/**
 * IdentityService Unit Tests
 *
 * @package BuyGoPlus\Tests\Unit\Services
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\IdentityService;

/**
 * Class IdentityServiceTest
 *
 * 測試 IdentityService 的身份識別邏輯
 */
class IdentityServiceTest extends TestCase
{
    /**
     * 測試角色常數定義
     */
    public function testRoleConstants(): void
    {
        $this->assertEquals('seller', IdentityService::ROLE_SELLER);
        $this->assertEquals('helper', IdentityService::ROLE_HELPER);
        $this->assertEquals('buyer', IdentityService::ROLE_BUYER);
        $this->assertEquals('unbound', IdentityService::ROLE_UNBOUND);
    }

    /**
     * 測試空 LINE UID 返回未綁定身份
     */
    public function testGetIdentityByLineUidWithEmptyUid(): void
    {
        $identity = IdentityService::getIdentityByLineUid('');

        $this->assertIsArray($identity);
        $this->assertNull($identity['user_id']);
        $this->assertEquals('', $identity['line_uid']);
        $this->assertEquals(IdentityService::ROLE_UNBOUND, $identity['role']);
        $this->assertFalse($identity['is_bound']);
        $this->assertNull($identity['seller_id']);
    }

    /**
     * 測試身份結構完整性
     */
    public function testIdentityStructure(): void
    {
        $identity = IdentityService::getIdentityByLineUid('test_uid');

        // 確認結構包含所有必要欄位
        $this->assertArrayHasKey('user_id', $identity);
        $this->assertArrayHasKey('line_uid', $identity);
        $this->assertArrayHasKey('role', $identity);
        $this->assertArrayHasKey('is_bound', $identity);
        $this->assertArrayHasKey('seller_id', $identity);
    }

    /**
     * 測試 isSeller 方法
     *
     * 注意：由於依賴 WordPress 函數，這個測試在 mock 環境下會返回 false
     */
    public function testIsSellerReturnsFalseForInvalidUser(): void
    {
        // Mock 環境下 get_userdata 返回 null
        $this->assertFalse(IdentityService::isSeller(0));
        $this->assertFalse(IdentityService::isSeller(999999));
    }

    /**
     * 測試 isHelper 方法
     *
     * 注意：由於依賴資料庫，這個測試在 mock 環境下會返回 false
     */
    public function testIsHelperReturnsFalseForInvalidUser(): void
    {
        $this->assertFalse(IdentityService::isHelper(0));
        $this->assertFalse(IdentityService::isHelper(999999));
    }

    /**
     * 測試 hasLineBinding 方法
     */
    public function testHasLineBindingReturnsFalseForUnboundUser(): void
    {
        // Mock 環境下沒有綁定資料
        $this->assertFalse(IdentityService::hasLineBinding(999999));
    }

    /**
     * 測試 getLineUid 返回 null
     */
    public function testGetLineUidReturnsNullForUnboundUser(): void
    {
        $this->assertNull(IdentityService::getLineUid(999999));
    }

    /**
     * 測試 canInteractWithBot 方法
     */
    public function testCanInteractWithBotReturnsFalseForNonSellerHelper(): void
    {
        // Mock 環境下既不是賣家也不是小幫手
        $this->assertFalse(IdentityService::canInteractWithBot(999999));
    }

    /**
     * 測試 canInteractWithBotByLineUid 方法
     */
    public function testCanInteractWithBotByLineUidReturnsFalseForUnbound(): void
    {
        // 未綁定用戶不能與 bot 互動
        $this->assertFalse(IdentityService::canInteractWithBotByLineUid('unknown_uid'));
    }

    /**
     * 測試 getHelperInfo 返回 null
     */
    public function testGetHelperInfoReturnsNullForNonHelper(): void
    {
        $this->assertNull(IdentityService::getHelperInfo(999999));
    }
}
