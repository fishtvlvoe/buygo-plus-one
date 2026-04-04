<?php

namespace BuyGoPlus\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Api\API;

/**
 * 測試 API::is_platform_admin() 統一管理員判斷
 *
 * 驗證目標：
 * - manage_options capability → 視為管理員
 * - buygo_admin capability   → 視為管理員
 * - 兩者皆無                 → 不是管理員
 * - 單純 'administrator' 角色（但無 capability）→ 不視為管理員
 */
class IsPlatformAdminTest extends TestCase
{
    protected function setUp(): void
    {
        // 每個測試前重置 capability mock
        $GLOBALS['mock_current_user_can'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['mock_current_user_can'] = [];
    }

    // ----------------------------------------------------------------
    // 管理員情境（應回傳 true）
    // ----------------------------------------------------------------

    public function testManageOptionsIsAdmin(): void
    {
        // WordPress 管理員有 manage_options capability
        $GLOBALS['mock_current_user_can']['manage_options'] = true;

        $this->assertTrue(
            API::is_platform_admin(),
            'manage_options capability 應視為平台管理員'
        );
    }

    public function testBuyGoAdminIsAdmin(): void
    {
        // BuyGo 主帳號有 buygo_admin capability
        $GLOBALS['mock_current_user_can']['buygo_admin'] = true;

        $this->assertTrue(
            API::is_platform_admin(),
            'buygo_admin capability 應視為平台管理員'
        );
    }

    public function testBothCapabilitiesIsAdmin(): void
    {
        // 同時有兩個 capability（超級管理員）
        $GLOBALS['mock_current_user_can']['manage_options'] = true;
        $GLOBALS['mock_current_user_can']['buygo_admin']    = true;

        $this->assertTrue(
            API::is_platform_admin(),
            '同時有兩個 capability 應視為平台管理員'
        );
    }

    // ----------------------------------------------------------------
    // 非管理員情境（應回傳 false）
    // ----------------------------------------------------------------

    public function testNoCapabilityIsNotAdmin(): void
    {
        // 無任何 capability（一般用戶）
        $this->assertFalse(
            API::is_platform_admin(),
            '沒有任何 capability 不應視為平台管理員'
        );
    }

    public function testHelperIsNotAdmin(): void
    {
        // buygo_helper 有操作權限，但不是管理員
        $GLOBALS['mock_current_user_can']['buygo_helper'] = true;

        $this->assertFalse(
            API::is_platform_admin(),
            'buygo_helper 不應視為平台管理員'
        );
    }

    public function testListerIsNotAdmin(): void
    {
        // buygo_lister 有上架權限，但不是管理員
        $GLOBALS['mock_current_user_can']['buygo_lister'] = true;

        $this->assertFalse(
            API::is_platform_admin(),
            'buygo_lister 不應視為平台管理員'
        );
    }

    public function testAdministratorRoleWithoutCapabilityIsNotAdmin(): void
    {
        // 角色字串 'administrator' 不是 capability，不應被認定
        // （這正是修復前的不一致根源）
        $GLOBALS['mock_current_user_can']['administrator'] = true;

        $this->assertFalse(
            API::is_platform_admin(),
            "'administrator' 角色字串不是 capability，不應直接視為管理員"
        );
    }
}
