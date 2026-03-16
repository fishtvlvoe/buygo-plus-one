<?php
/**
 * ProductNotificationHandler Unit Tests
 *
 * 透過 TDD 驗證批次上架（非 LINE 途徑）也能正確發送通知給綁定賣家的 bug 修正
 *
 * @package BuyGoPlus\Tests\Unit\Services
 * @since 1.4.0
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\ProductNotificationHandler;
use ReflectionClass;

/**
 * Class ProductNotificationHandlerTest
 *
 * 測試 resolveNotificationTargets 核心邏輯與 onProductCreated 分支行為：
 *
 * Bug 背景：
 * 1. 原本 onProductCreated 在 line_uid 為空時直接 return，批次上架無法觸發通知
 * 2. 原本 seller_id 從 product_data['user_id'] 取得，但批次上架時該值是小幫手 ID，非賣家 ID
 *
 * 修正策略：
 * - 先嘗試從 LINE UID 取得上架者，再 fallback 到 product_data['user_id']
 * - 提取 resolveNotificationTargets() 封裝身份判斷與通知目標邏輯，便於測試
 */
class ProductNotificationHandlerTest extends TestCase
{
    /**
     * 每個測試前重置全域 mock 狀態，並重新安裝支援 buygo_helpers 查詢的 wpdb mock
     *
     * FluentCartServiceTest::tearDown() 會把 $GLOBALS['wpdb'] 替換成簡化版，
     * 這裡在每個測試前重新安裝完整版，確保測試隔離。
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 重置所有 mock 狀態
        $GLOBALS['mock_user_roles']        = [];
        $GLOBALS['mock_helper_rows']       = [];
        $GLOBALS['mock_helpers_by_seller'] = [];
        $GLOBALS['mock_transients']        = [];

        // 重新安裝支援 buygo_helpers 查詢的 wpdb mock
        // （防止被 FluentCartServiceTest::tearDown 覆蓋後影響本測試）
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public $insert_id = 0;
            public $last_error = '';

            public function prepare($query, ...$args) {
                return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
            }

            public function get_var($query) {
                // SHOW TABLES LIKE 'wp_buygo_helpers' → 若有任一 mock 資料則假設表存在
                if (strpos($query, 'SHOW TABLES') !== false && strpos($query, 'buygo_helpers') !== false) {
                    if (!empty($GLOBALS['mock_helper_rows']) || !empty($GLOBALS['mock_helpers_by_seller'])) {
                        return $this->prefix . 'buygo_helpers';
                    }
                    return null;
                }
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
                // RolePermissionService::get_helpers 查詢：WHERE seller_id = X
                if (strpos($query, 'buygo_helpers') !== false && preg_match("/seller_id\s*=\s*'?(\d+)'?/", $query, $m)) {
                    $seller_id = (int) $m[1];
                    $rows = $GLOBALS['mock_helpers_by_seller'][$seller_id] ?? [];
                    return array_map(function($r) { return (object) $r; }, $rows);
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
        };
    }

    /**
     * 建立 ProductNotificationHandler 實例（不呼叫 constructor 的 add_action，已被 bootstrap mock）
     */
    private function makeHandler(): ProductNotificationHandler
    {
        return new ProductNotificationHandler();
    }

    // ========================================
    // resolveNotificationTargets 核心邏輯測試
    // ========================================

    /**
     * 測試 1：小幫手上架 → 通知目標包含賣家，且不含小幫手本人
     *
     * Bug 修正前：seller_id 從 product_data['user_id'] 取得，會是小幫手 ID，
     * 導致通知目標錯誤（通知小幫手自己，而非賣家）
     */
    public function testHelperUploadCorrectlyIdentifiesSeller(): void
    {
        // 設定：賣家 ID=10，小幫手 ID=50
        $seller_id = 10;
        $helper_id = 50;

        // mock：讓 IdentityService::isSeller(10) 返回 true（buygo_admin 角色）
        $GLOBALS['mock_user_roles'][$seller_id] = ['buygo_admin'];
        // mock：讓 IdentityService::isSeller(50) 返回 false
        $GLOBALS['mock_user_roles'][$helper_id] = ['buygo_helper'];

        // mock：getHelperInfo(50) → 小幫手 50 屬於賣家 10
        $GLOBALS['mock_helper_rows'][$helper_id] = [
            'helper_id' => $helper_id,
            'seller_id' => $seller_id,
            'created_at' => '2026-01-01 00:00:00',
        ];

        // mock：get_helpers(10) → 賣家 10 的小幫手列表
        $GLOBALS['mock_helpers_by_seller'][$seller_id] = [
            ['helper_id' => $helper_id, 'seller_id' => $seller_id, 'created_at' => '2026-01-01 00:00:00'],
        ];

        $handler = $this->makeHandler();
        $result  = $handler->resolveNotificationTargets($helper_id);

        // 賣家 ID 應正確識別為 10，而非小幫手 ID 50
        $this->assertNotNull($result, '應返回通知目標，不應為 null');
        $this->assertSame($seller_id, $result['seller_id'], '賣家 ID 應為 10');

        // 通知列表應包含賣家（10），不應包含小幫手本人（50）
        $this->assertContains($seller_id, $result['notify_user_ids'], '通知列表應包含賣家');
        $this->assertNotContains($helper_id, $result['notify_user_ids'], '通知列表不應包含上架者本人');
    }

    /**
     * 測試 2：賣家本人上架（無 LINE UID）→ 通知目標為所有小幫手
     */
    public function testSellerUploadTargetsHelpers(): void
    {
        $seller_id  = 10;
        $helper1_id = 50;
        $helper2_id = 60;

        $GLOBALS['mock_user_roles'][$seller_id]  = ['buygo_admin'];
        $GLOBALS['mock_user_roles'][$helper1_id] = ['buygo_helper'];
        $GLOBALS['mock_user_roles'][$helper2_id] = ['buygo_helper'];

        // 賣家本人沒有小幫手資料（不在 buygo_helpers 表中）
        $GLOBALS['mock_helper_rows'] = [];

        // 賣家 10 有兩個小幫手
        $GLOBALS['mock_helpers_by_seller'][$seller_id] = [
            ['helper_id' => $helper1_id, 'seller_id' => $seller_id, 'created_at' => '2026-01-01 00:00:00'],
            ['helper_id' => $helper2_id, 'seller_id' => $seller_id, 'created_at' => '2026-01-01 00:00:00'],
        ];

        $handler = $this->makeHandler();
        $result  = $handler->resolveNotificationTargets($seller_id);

        $this->assertNotNull($result, '賣家上架應返回通知目標');
        $this->assertSame($seller_id, $result['seller_id']);

        // 通知對象：所有小幫手（賣家本人不在列表中）
        $this->assertContains($helper1_id, $result['notify_user_ids'], '應通知小幫手 50');
        $this->assertContains($helper2_id, $result['notify_user_ids'], '應通知小幫手 60');
        $this->assertNotContains($seller_id, $result['notify_user_ids'], '不應通知賣家本人');
    }

    /**
     * 測試 3：多個小幫手時，上架的小幫手本人不在通知列表，其他小幫手和賣家都在
     */
    public function testHelperUploadExcludesUploaderButIncludesOtherHelpers(): void
    {
        $seller_id  = 10;
        $helper1_id = 50; // 上架者
        $helper2_id = 60; // 另一個小幫手

        $GLOBALS['mock_user_roles'][$seller_id]  = ['buygo_admin'];
        $GLOBALS['mock_user_roles'][$helper1_id] = ['buygo_helper'];
        $GLOBALS['mock_user_roles'][$helper2_id] = ['buygo_helper'];

        // 小幫手 50 屬於賣家 10
        $GLOBALS['mock_helper_rows'][$helper1_id] = [
            'helper_id' => $helper1_id,
            'seller_id' => $seller_id,
            'created_at' => '2026-01-01 00:00:00',
        ];

        // 賣家 10 有兩個小幫手
        $GLOBALS['mock_helpers_by_seller'][$seller_id] = [
            ['helper_id' => $helper1_id, 'seller_id' => $seller_id, 'created_at' => '2026-01-01 00:00:00'],
            ['helper_id' => $helper2_id, 'seller_id' => $seller_id, 'created_at' => '2026-01-01 00:00:00'],
        ];

        $handler = $this->makeHandler();
        $result  = $handler->resolveNotificationTargets($helper1_id);

        $this->assertNotNull($result);

        // 賣家和另一個小幫手都要被通知
        $this->assertContains($seller_id,  $result['notify_user_ids'], '應通知賣家');
        $this->assertContains($helper2_id, $result['notify_user_ids'], '應通知另一個小幫手');
        // 上架者本人不應被通知
        $this->assertNotContains($helper1_id, $result['notify_user_ids'], '不應通知上架者本人');
    }

    /**
     * 測試 4：無法識別身份（user_id 不存在或非賣家/小幫手）→ 返回 null，不發通知
     */
    public function testUnknownUploaderReturnsNull(): void
    {
        // user_id=999 在 mock 中沒有角色，get_userdata 返回 null
        // IdentityService::isSeller(999) → false，getHelperInfo(999) → null
        // 身份 = 'unbound'，resolveNotificationTargets 應返回 null

        $handler = $this->makeHandler();
        $result  = $handler->resolveNotificationTargets(999);

        $this->assertNull($result, '無法識別身份時應返回 null');
    }

    // ========================================
    // onProductCreated 分支測試
    // ========================================

    /**
     * 測試 5：批次上架（無 line_uid，有 user_id=小幫手）→ 不應跳過（不提早 return）
     *
     * 驗證 Bug 1 修正：onProductCreated 不應在 line_uid 為空時直接 return
     * 這裡透過驗證 resolveNotificationTargets 能被正確呼叫（間接驗證）
     * 直接測試：uploader_id 應 fallback 到 product_data['user_id']
     */
    public function testHelperUploadWithoutLineUidStillResolvesTargets(): void
    {
        $seller_id = 10;
        $helper_id = 50;

        $GLOBALS['mock_user_roles'][$seller_id] = ['buygo_admin'];
        $GLOBALS['mock_user_roles'][$helper_id] = ['buygo_helper'];

        $GLOBALS['mock_helper_rows'][$helper_id] = [
            'helper_id' => $helper_id,
            'seller_id' => $seller_id,
            'created_at' => '2026-01-01 00:00:00',
        ];

        $GLOBALS['mock_helpers_by_seller'][$seller_id] = [
            ['helper_id' => $helper_id, 'seller_id' => $seller_id, 'created_at' => '2026-01-01 00:00:00'],
        ];

        // 直接測試 resolveNotificationTargets：傳入小幫手 ID，驗證能正確返回賣家
        // 這等同於 onProductCreated 走「fallback 到 product_data['user_id']」後的邏輯
        $handler = $this->makeHandler();
        $result  = $handler->resolveNotificationTargets($helper_id);

        $this->assertNotNull(
            $result,
            '批次上架的小幫手（無 LINE UID）也應能解析通知目標'
        );
        $this->assertSame($seller_id, $result['seller_id'], '應識別綁定的賣家 ID');
        $this->assertContains($seller_id, $result['notify_user_ids'], '賣家應在通知列表中');
    }

    /**
     * 測試 6：賣家透過 API 上架（無 line_uid）→ 解析目標為小幫手列表
     */
    public function testSellerUploadWithoutLineUidNotifiesHelpers(): void
    {
        $seller_id = 10;
        $helper_id = 50;

        $GLOBALS['mock_user_roles'][$seller_id] = ['buygo_admin'];
        $GLOBALS['mock_user_roles'][$helper_id] = ['buygo_helper'];

        // 賣家不在 helper_rows 中
        $GLOBALS['mock_helper_rows'] = [];

        $GLOBALS['mock_helpers_by_seller'][$seller_id] = [
            ['helper_id' => $helper_id, 'seller_id' => $seller_id, 'created_at' => '2026-01-01 00:00:00'],
        ];

        $handler = $this->makeHandler();
        $result  = $handler->resolveNotificationTargets($seller_id);

        $this->assertNotNull($result, '賣家無 LINE 上架也應能解析通知目標');
        $this->assertContains($helper_id, $result['notify_user_ids'], '小幫手應在通知列表中');
        $this->assertNotContains($seller_id, $result['notify_user_ids'], '賣家不應通知自己');
    }

    /**
     * 測試 7：賣家沒有任何小幫手時，通知列表為空（不應出錯）
     */
    public function testSellerWithNoHelpersReturnsEmptyNotifyList(): void
    {
        $seller_id = 10;

        $GLOBALS['mock_user_roles'][$seller_id] = ['buygo_admin'];
        $GLOBALS['mock_helper_rows']            = [];
        $GLOBALS['mock_helpers_by_seller']      = []; // 沒有小幫手

        $handler = $this->makeHandler();
        $result  = $handler->resolveNotificationTargets($seller_id);

        $this->assertNotNull($result, '應返回結果（即使沒有小幫手）');
        $this->assertSame($seller_id, $result['seller_id']);
        $this->assertEmpty($result['notify_user_ids'], '沒有小幫手時通知列表應為空');
    }

    // ========================================
    // 上架幫手（lister）通知邏輯測試
    // ========================================

    /**
     * 測試 9：上架幫手上架商品 → 通知賣家和小幫手，不通知上架幫手本人
     *
     * 情境：賣家 ID=10，小幫手 ID=50，上架幫手 ID=80
     * - 上架幫手 80 上架商品
     * - 系統應通知：賣家（10）和小幫手（50）
     * - 不應通知：上架幫手本人（80），因為他是上架者
     *
     * 注意：此為 TDD 測試，resolveNotificationTargets 需要新增對 ROLE_LISTER 的處理
     */
    public function testListerUploadNotifiesSellerAndHelpers(): void
    {
        $seller_id = 10;
        $helper_id = 50;
        $lister_id = 80;

        // mock：各角色設定
        $GLOBALS['mock_user_roles'][$seller_id] = ['buygo_admin'];
        $GLOBALS['mock_user_roles'][$helper_id] = ['buygo_helper'];
        $GLOBALS['mock_user_roles'][$lister_id] = ['buygo_lister'];

        // mock：上架幫手 80 在綁定表中，屬於賣家 10
        $GLOBALS['mock_helper_rows'][$lister_id] = [
            'helper_id' => $lister_id,
            'seller_id' => $seller_id,
            'role'      => 'buygo_lister',
            'created_at' => '2026-01-01 00:00:00',
        ];

        // mock：賣家 10 旗下的所有幫手（小幫手 50 + 上架幫手 80）
        // get_helpers 會依 mock_user_roles 設定 role 欄位
        $GLOBALS['mock_helpers_by_seller'][$seller_id] = [
            ['helper_id' => $helper_id, 'seller_id' => $seller_id, 'created_at' => '2026-01-01 00:00:00'],
            ['helper_id' => $lister_id, 'seller_id' => $seller_id, 'created_at' => '2026-01-01 00:00:00'],
        ];

        $handler = $this->makeHandler();
        $result  = $handler->resolveNotificationTargets($lister_id);

        $this->assertNotNull($result, '上架幫手上架應返回通知目標，不應為 null');
        $this->assertSame($seller_id, $result['seller_id'], '賣家 ID 應為 10');

        // 賣家和小幫手都應在通知列表
        $this->assertContains($seller_id, $result['notify_user_ids'], '通知列表應包含賣家（10）');
        $this->assertContains($helper_id, $result['notify_user_ids'], '通知列表應包含小幫手（50）');

        // 上架幫手本人不應在通知列表
        $this->assertNotContains($lister_id, $result['notify_user_ids'], '通知列表不應包含上架幫手本人（80）');
    }

    /**
     * 測試 10：小幫手上架時，上架幫手不應收到通知
     *
     * 情境：賣家 ID=10，小幫手 ID=50（上架者），上架幫手 ID=80
     * - 小幫手 50 上架商品，呼叫 resolveNotificationTargets(50)
     * - 系統應通知：賣家（10）
     * - 不應通知：小幫手本人（50），也不應通知上架幫手（80）
     *
     * 設計決策：上架幫手只負責上架，不參與上架通知的接收
     */
    public function testHelperUploadDoesNotNotifyLister(): void
    {
        $seller_id = 10;
        $helper_id = 50; // 上架者
        $lister_id = 80;

        // mock：各角色設定
        $GLOBALS['mock_user_roles'][$seller_id] = ['buygo_admin'];
        $GLOBALS['mock_user_roles'][$helper_id] = ['buygo_helper'];
        $GLOBALS['mock_user_roles'][$lister_id] = ['buygo_lister'];

        // mock：小幫手 50 在綁定表中，屬於賣家 10
        $GLOBALS['mock_helper_rows'][$helper_id] = [
            'helper_id' => $helper_id,
            'seller_id' => $seller_id,
            'created_at' => '2026-01-01 00:00:00',
        ];

        // mock：賣家 10 旗下有小幫手 50 和上架幫手 80
        $GLOBALS['mock_helpers_by_seller'][$seller_id] = [
            ['helper_id' => $helper_id, 'seller_id' => $seller_id, 'created_at' => '2026-01-01 00:00:00'],
            ['helper_id' => $lister_id, 'seller_id' => $seller_id, 'created_at' => '2026-01-01 00:00:00'],
        ];

        $handler = $this->makeHandler();
        // 由小幫手 50 上架
        $result  = $handler->resolveNotificationTargets($helper_id);

        $this->assertNotNull($result, '小幫手上架應返回通知目標');

        // 賣家應被通知
        $this->assertContains($seller_id, $result['notify_user_ids'], '通知列表應包含賣家（10）');

        // 上架者本人不應在通知列表
        $this->assertNotContains($helper_id, $result['notify_user_ids'], '通知列表不應包含上架者小幫手（50）');

        // 上架幫手不應收到別人上架的通知
        $this->assertNotContains($lister_id, $result['notify_user_ids'], '通知列表不應包含上架幫手（80）');
    }

    /**
     * 測試 11：賣家上架時，上架幫手不應收到通知
     *
     * 情境：賣家 ID=10（上架者），小幫手 ID=50，上架幫手 ID=80
     * - 賣家 10 上架商品
     * - 應通知：小幫手（50）
     * - 不應通知：上架幫手（80）（上架幫手不接收上架通知）
     *
     * 設計決策：上架幫手是「執行者」不是「接收者」，上架通知只發給 buygo_helper 角色
     */
    public function testSellerUploadNotifiesHelpersButNotListers(): void
    {
        $seller_id = 10; // 上架者
        $helper_id = 50;
        $lister_id = 80;

        // mock：各角色設定
        $GLOBALS['mock_user_roles'][$seller_id] = ['buygo_admin'];
        $GLOBALS['mock_user_roles'][$helper_id] = ['buygo_helper'];
        $GLOBALS['mock_user_roles'][$lister_id] = ['buygo_lister'];

        // 賣家不在綁定表中
        $GLOBALS['mock_helper_rows'] = [];

        // mock：賣家 10 旗下有小幫手 50 和上架幫手 80
        $GLOBALS['mock_helpers_by_seller'][$seller_id] = [
            ['helper_id' => $helper_id, 'seller_id' => $seller_id, 'created_at' => '2026-01-01 00:00:00'],
            ['helper_id' => $lister_id, 'seller_id' => $seller_id, 'created_at' => '2026-01-01 00:00:00'],
        ];

        $handler = $this->makeHandler();
        // 由賣家 10 上架
        $result  = $handler->resolveNotificationTargets($seller_id);

        $this->assertNotNull($result, '賣家上架應返回通知目標');
        $this->assertSame($seller_id, $result['seller_id']);

        // 小幫手應被通知
        $this->assertContains($helper_id, $result['notify_user_ids'], '通知列表應包含小幫手（50）');

        // 賣家本人和上架幫手都不應在通知列表
        $this->assertNotContains($seller_id, $result['notify_user_ids'], '不應通知賣家本人（10）');
        $this->assertNotContains($lister_id, $result['notify_user_ids'], '通知列表不應包含上架幫手（80）');
    }

    /**
     * 測試 12：上架幫手透過批次上架（無 LINE UID）也能正確觸發通知
     *
     * 情境：上架幫手 80 透過 API 批次上架（沒有 LINE UID），
     *       fallback 到 product_data['user_id'] 後，resolveNotificationTargets 應能正確處理
     * 預期：通知賣家（10）和小幫手（50）
     *
     * 這是現有 testHelperUploadWithoutLineUidStillResolvesTargets（test 5）的上架幫手版本
     */
    public function testListerUploadWithNoLineUidStillWorks(): void
    {
        $seller_id = 10;
        $helper_id = 50;
        $lister_id = 80;

        // mock：各角色設定（上架幫手透過批次，無 LINE UID 但有 user_id）
        $GLOBALS['mock_user_roles'][$seller_id] = ['buygo_admin'];
        $GLOBALS['mock_user_roles'][$helper_id] = ['buygo_helper'];
        $GLOBALS['mock_user_roles'][$lister_id] = ['buygo_lister'];

        // mock：上架幫手 80 在綁定表中，屬於賣家 10
        $GLOBALS['mock_helper_rows'][$lister_id] = [
            'helper_id' => $lister_id,
            'seller_id' => $seller_id,
            'role'      => 'buygo_lister',
            'created_at' => '2026-01-01 00:00:00',
        ];

        // mock：賣家 10 旗下有小幫手 50 和上架幫手 80
        $GLOBALS['mock_helpers_by_seller'][$seller_id] = [
            ['helper_id' => $helper_id, 'seller_id' => $seller_id, 'created_at' => '2026-01-01 00:00:00'],
            ['helper_id' => $lister_id, 'seller_id' => $seller_id, 'created_at' => '2026-01-01 00:00:00'],
        ];

        // 直接傳入上架幫手 ID（等同 onProductCreated fallback 到 product_data['user_id'] 的結果）
        $handler = $this->makeHandler();
        $result  = $handler->resolveNotificationTargets($lister_id);

        $this->assertNotNull($result, '批次上架的上架幫手（無 LINE UID）也應能解析通知目標');
        $this->assertSame($seller_id, $result['seller_id'], '應識別綁定的賣家 ID 為 10');
        $this->assertContains($seller_id, $result['notify_user_ids'], '賣家應在通知列表中');
        $this->assertContains($helper_id, $result['notify_user_ids'], '小幫手應在通知列表中');
        $this->assertNotContains($lister_id, $result['notify_user_ids'], '上架幫手本人不應在通知列表中');
    }

    /**
     * 測試 8：resolveNotificationTargets 返回結構驗證
     */
    public function testResolveNotificationTargetsReturnStructure(): void
    {
        $seller_id = 10;
        $helper_id = 50;

        $GLOBALS['mock_user_roles'][$seller_id] = ['buygo_admin'];
        $GLOBALS['mock_user_roles'][$helper_id] = ['buygo_helper'];
        $GLOBALS['mock_helper_rows'][$helper_id] = [
            'helper_id' => $helper_id,
            'seller_id' => $seller_id,
            'created_at' => '2026-01-01 00:00:00',
        ];
        $GLOBALS['mock_helpers_by_seller'][$seller_id] = [
            ['helper_id' => $helper_id, 'seller_id' => $seller_id, 'created_at' => '2026-01-01 00:00:00'],
        ];

        $handler = $this->makeHandler();
        $result  = $handler->resolveNotificationTargets($helper_id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('seller_id', $result, '應有 seller_id 鍵');
        $this->assertArrayHasKey('notify_user_ids', $result, '應有 notify_user_ids 鍵');
        $this->assertIsInt($result['seller_id'], 'seller_id 應為整數');
        $this->assertIsArray($result['notify_user_ids'], 'notify_user_ids 應為陣列');
    }
}
