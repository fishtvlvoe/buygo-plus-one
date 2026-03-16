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
     * 每個測試前重置 mock 狀態，並確保 wpdb 使用 bootstrap 的標準版本
     * （防止被其他 TestCase 的 setUp 覆蓋後影響本測試）
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 重置用戶角色和小幫手 mock 資料
        $GLOBALS['mock_user_roles']        = [];
        $GLOBALS['mock_helper_rows']       = [];
        $GLOBALS['mock_helpers_by_seller'] = [];

        // 重新安裝標準 wpdb mock（buygo_helpers 表永遠視為存在）
        // 確保不被其他測試的 setUp 所影響
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public $insert_id = 0;
            public $last_error = '';

            public function prepare($query, ...$args) {
                return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
            }

            public function get_var($query) {
                // buygo_helpers 表永遠視為存在，由 mock_helper_rows 控制查詢結果
                if (strpos($query, 'SHOW TABLES') !== false && strpos($query, 'buygo_helpers') !== false) {
                    return $this->prefix . 'buygo_helpers';
                }
                // 其他表（如 buygo_line_bindings）不存在，讓 getLineUid 走 usermeta 路徑
                if (strpos($query, 'SHOW TABLES') !== false) {
                    return null;
                }
                return null;
            }

            public function get_row($query, $output = OBJECT) {
                // IdentityService::getHelperInfo 查詢：WHERE helper_id = X
                if (strpos($query, 'buygo_helpers') !== false && preg_match("/helper_id\s*=\s*'?(\d+)'?/", $query, $m)) {
                    $helper_id = (int) $m[1];
                    $row = $GLOBALS['mock_helper_rows'][$helper_id] ?? null;
                    if ($row !== null) {
                        return ($output === ARRAY_A) ? $row : (object) $row;
                    }
                }
                return null;
            }

            public function get_results($query, $output = OBJECT) {
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
        };
    }

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

    // ========================================
    // 上架幫手（lister）角色識別測試
    // ========================================

    /**
     * 測試：上架幫手應被識別為 ROLE_LISTER
     *
     * 情境：用戶有 buygo_lister WP 角色，且存在於 wp_buygo_helpers 表中
     * 預期：getIdentityByUserId 回傳 role = 'lister'，seller_id 指向綁定賣家
     *
     * 這是驗證「先查綁定表 → 再查 WP 角色決定是 helper 還是 lister」邏輯的基礎測試
     */
    public function testListerIdentifiedAsLister(): void
    {
        $seller_id = 10;
        $lister_id = 80;

        // mock：上架幫手 80 有 buygo_lister WP 角色
        $GLOBALS['mock_user_roles'][$lister_id] = ['buygo_lister'];

        // mock：上架幫手 80 在 wp_buygo_helpers 表中，綁定賣家 10
        // role 欄位是實作加入後的預期資料格式
        $GLOBALS['mock_helper_rows'][$lister_id] = [
            'helper_id' => $lister_id,
            'seller_id' => $seller_id,
            'role'      => 'buygo_lister',
            'created_at' => '2026-01-01 00:00:00',
        ];

        $identity = IdentityService::getIdentityByUserId($lister_id);

        // 實作新增 ROLE_LISTER 常數後，應識別為 lister
        $this->assertEquals(IdentityService::ROLE_LISTER, $identity['role'], '上架幫手應被識別為 lister');
        $this->assertEquals($seller_id, $identity['seller_id'], '應回傳綁定賣家的 ID');
    }

    /**
     * 測試：小幫手同時擁有 administrator 角色，應優先識別為 helper（綁定表優先於 WP 角色）
     *
     * 情境：用戶有 ['administrator', 'buygo_helper'] 角色，且在綁定表中
     * 說明：isSeller 會把 administrator 識別為賣家，但若實作改為「先查綁定表」，
     *       則表中有記錄時應優先判斷為幫手身份
     *
     * 注意：此測試是 TDD 預期測試，若實作尚未修改優先級，此測試將失敗
     * 這也記錄了「綁定表優先於 WP 角色」的設計決策
     */
    public function testHelperWithAdminRoleStillIdentifiedAsHelper(): void
    {
        $seller_id = 10;
        $helper_id = 50;

        // mock：小幫手 50 同時有 administrator 和 buygo_helper 角色
        $GLOBALS['mock_user_roles'][$helper_id] = ['administrator', 'buygo_helper'];

        // mock：小幫手 50 在綁定表中，屬於賣家 10
        $GLOBALS['mock_helper_rows'][$helper_id] = [
            'helper_id' => $helper_id,
            'seller_id' => $seller_id,
            'created_at' => '2026-01-01 00:00:00',
        ];

        $identity = IdentityService::getIdentityByUserId($helper_id);

        // 綁定表有記錄時，應識別為幫手，而非賣家
        $this->assertEquals(IdentityService::ROLE_HELPER, $identity['role'], '有綁定表記錄時應識別為 helper，而非 seller');
        $this->assertEquals($seller_id, $identity['seller_id'], '應回傳綁定賣家的 ID');
    }

    /**
     * 測試：上架幫手常數 ROLE_LISTER 存在且值為 'lister'
     *
     * 確認 IdentityService 新增了 ROLE_LISTER 常數
     */
    public function testListerRoleConstantExists(): void
    {
        $this->assertEquals('lister', IdentityService::ROLE_LISTER, 'ROLE_LISTER 常數應等於 lister');
    }
}
