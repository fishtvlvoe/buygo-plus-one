<?php
/**
 * OwnershipGuard Unit Tests
 *
 * 測試 API::verify_*_ownership() 多租戶隔離 guard 方法
 *
 * @package BuyGoPlus\Tests\Unit\Services
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Api\API;

/**
 * Class OwnershipGuardTest
 *
 * 覆蓋以下場景：
 * - 管理員（manage_options）永遠放行
 * - buygo_admin 永遠放行
 * - helper 只能存取授權賣家的資源
 * - 跨 seller 存取應回傳 403 WP_Error
 * - 資源不存在應回傳 WP_Error
 */
class OwnershipGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // 重置所有 mock 狀態
        $GLOBALS['mock_current_user_can']   = [];
        $GLOBALS['mock_current_user_id']    = 0;
        $GLOBALS['mock_helper_rows']        = [];
        $GLOBALS['mock_helpers_by_seller']  = [];
        $GLOBALS['mock_user_roles']         = [];
        $GLOBALS['mock_ownership_queries']  = [];

        // 安裝可程式化的 wpdb mock
        $GLOBALS['wpdb'] = new class {
            public $prefix  = 'wp_';
            public $posts   = 'wp_posts';
            public $last_error = '';

            public function prepare($query, ...$args)
            {
                // 簡易替換 %d / %s
                $i = 0;
                return preg_replace_callback('/%[ds]/', function () use ($args, &$i) {
                    $val = $args[$i++] ?? 0;
                    return is_numeric($val) ? (int) $val : "'" . addslashes($val) . "'";
                }, $query);
            }

            public function get_var($query)
            {
                // buygo_helpers 表始終視為存在
                if (strpos($query, 'SHOW TABLES') !== false && strpos($query, 'buygo_helpers') !== false) {
                    return $this->prefix . 'buygo_helpers';
                }
                if (strpos($query, 'SHOW TABLES') !== false) {
                    return null;
                }

                // 商品所有權查詢：SELECT post_author FROM wp_posts WHERE ID = X
                if (preg_match('/SELECT\s+post_author\s+FROM\s+wp_posts\s+WHERE\s+ID\s*=\s*(\d+)/i', $query, $m)) {
                    $product_id = (int) $m[1];
                    return $GLOBALS['mock_ownership_queries']['product_author'][$product_id] ?? null;
                }

                // variation 所有權查詢：SELECT p.post_author FROM wp_posts p JOIN wp_fct_product_variations v
                if (preg_match('/SELECT\s+p\.post_author.*wp_fct_product_variations.*v\.id\s*=\s*(\d+)/is', $query, $m)) {
                    $variation_id = (int) $m[1];
                    return $GLOBALS['mock_ownership_queries']['variation_author'][$variation_id] ?? null;
                }

                // 訂單所有權查詢：COUNT 查詢含 fct_orders
                if (preg_match('/SELECT\s+COUNT.*fct_orders.*o\.id\s*=\s*(\d+)/is', $query, $m)) {
                    $order_id = (int) $m[1];
                    return $GLOBALS['mock_ownership_queries']['order_count'][$order_id] ?? 0;
                }

                // 客戶所有權查詢：COUNT 查詢含 fct_orders 和 customer_id
                if (preg_match('/SELECT\s+COUNT.*fct_orders.*o\.customer_id\s*=\s*(\d+)/is', $query, $m)) {
                    $customer_id = (int) $m[1];
                    return $GLOBALS['mock_ownership_queries']['customer_count'][$customer_id] ?? 0;
                }

                // 出貨單所有權查詢：SELECT seller_id FROM wp_buygo_shipments WHERE id = X
                if (preg_match('/SELECT\s+seller_id\s+FROM\s+wp_buygo_shipments\s+WHERE\s+id\s*=\s*(\d+)/i', $query, $m)) {
                    $shipment_id = (int) $m[1];
                    return $GLOBALS['mock_ownership_queries']['shipment_seller'][$shipment_id] ?? null;
                }

                // helper 查詢：SELECT seller_ids FROM wp_buygo_helpers WHERE helper_id = X
                if (strpos($query, 'buygo_helpers') !== false && preg_match("/helper_id\s*=\s*'?(\d+)'?/", $query, $m)) {
                    $helper_id = (int) $m[1];
                    $row = $GLOBALS['mock_helper_rows'][$helper_id] ?? null;
                    return $row ? $row['seller_id'] : null;
                }

                return null;
            }

            public function get_row($query, $output = OBJECT)
            {
                if (strpos($query, 'buygo_helpers') !== false && preg_match("/helper_id\s*=\s*'?(\d+)'?/", $query, $m)) {
                    $helper_id = (int) $m[1];
                    $row = $GLOBALS['mock_helper_rows'][$helper_id] ?? null;
                    if ($row !== null) {
                        return ($output === ARRAY_A) ? $row : (object) $row;
                    }
                }
                return null;
            }

            public function get_results($query, $output = OBJECT)
            {
                if (strpos($query, 'buygo_helpers') !== false && preg_match("/seller_id\s*=\s*'?(\d+)'?/", $query, $m)) {
                    $seller_id = (int) $m[1];
                    $rows = $GLOBALS['mock_helpers_by_seller'][$seller_id] ?? [];
                    return array_map(function ($r) { return (object) $r; }, $rows);
                }
                return [];
            }

            public function get_col($query, $column_offset = 0)
            {
                // get_accessible_seller_ids 查詢：SELECT seller_id FROM wp_buygo_helpers WHERE helper_id = X
                if (strpos($query, 'buygo_helpers') !== false && preg_match("/helper_id\s*=\s*'?(\d+)'?/", $query, $m)) {
                    $helper_id = (int) $m[1];
                    $row = $GLOBALS['mock_helper_rows'][$helper_id] ?? null;
                    return $row ? [(int) $row['seller_id']] : [];
                }
                return [];
            }

            public function insert($table, $data, $format = null) { return 1; }
            public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
            public function delete($table, $where, $where_format = null) { return 1; }
            public function query($query) { return true; }
            public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
        };
    }

    // =========================================================
    // 輔助方法：設定當前使用者能力
    // =========================================================

    private function setCurrentUser(int $user_id, array $caps): void
    {
        $GLOBALS['mock_current_user_id'] = $user_id;
        $GLOBALS['mock_current_user_can'] = $caps;
    }

    private function setAsAdmin(): void
    {
        $this->setCurrentUser(1, ['manage_options' => true]);
    }

    private function setAsBuygoAdmin(int $user_id = 10): void
    {
        $this->setCurrentUser($user_id, ['buygo_admin' => true]);
        $GLOBALS['mock_user_roles'][$user_id] = ['buygo_admin'];
    }

    private function setAsHelper(int $helper_id, int $seller_id): void
    {
        $this->setCurrentUser($helper_id, ['buygo_helper' => true]);
        $GLOBALS['mock_user_roles'][$helper_id] = ['buygo_helper'];
        $GLOBALS['mock_helper_rows'][$helper_id] = [
            'helper_id' => $helper_id,
            'seller_id' => $seller_id,
        ];
    }

    // =========================================================
    // verify_product_ownership 測試
    // =========================================================

    public function testProductOwnership_AdminAlwaysPasses(): void
    {
        $this->setAsAdmin();
        $result = API::verify_product_ownership(999);
        $this->assertTrue($result, '管理員應直接放行');
    }

    public function testProductOwnership_BuygoAdminAlwaysPasses(): void
    {
        $this->setAsBuygoAdmin(10);
        // 商品作者設為另一個 seller（不同人），管理員仍應放行
        $GLOBALS['mock_ownership_queries']['product_author'][100] = 99;
        $result = API::verify_product_ownership(100);
        $this->assertTrue($result, 'buygo_admin 應直接放行');
    }

    public function testProductOwnership_SellerOwnsProduct(): void
    {
        $seller_id = 10;
        $product_id = 100;
        $this->setCurrentUser($seller_id, ['buygo_admin' => true]);
        $GLOBALS['mock_user_roles'][$seller_id] = ['buygo_admin'];
        $GLOBALS['mock_ownership_queries']['product_author'][$product_id] = $seller_id;

        $result = API::verify_product_ownership($product_id);
        $this->assertTrue($result, '賣家擁有商品，應放行');
    }

    public function testProductOwnership_CrossSellerAccessDenied(): void
    {
        $helper_id = 50;
        $seller_id = 10;
        $other_seller_id = 20;
        $product_id = 200;

        $this->setAsHelper($helper_id, $seller_id);
        // 商品屬於另一個賣家
        $GLOBALS['mock_ownership_queries']['product_author'][$product_id] = $other_seller_id;

        $result = API::verify_product_ownership($product_id);
        $this->assertInstanceOf(\WP_Error::class, $result, '跨賣家存取應回傳 WP_Error');
        $this->assertEquals('access_denied', $result->get_error_code());
    }

    public function testProductOwnership_ProductNotFound(): void
    {
        $this->setAsHelper(50, 10);
        // 商品不存在（get_var 回傳 null）
        $result = API::verify_product_ownership(9999);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('product_not_found', $result->get_error_code());
    }

    // =========================================================
    // verify_order_ownership 測試
    // =========================================================

    public function testOrderOwnership_AdminAlwaysPasses(): void
    {
        $this->setAsAdmin();
        $result = API::verify_order_ownership(999);
        $this->assertTrue($result);
    }

    public function testOrderOwnership_HelperOwnsOrder(): void
    {
        $helper_id = 50;
        $seller_id = 10;
        $order_id  = 300;

        $this->setAsHelper($helper_id, $seller_id);
        $GLOBALS['mock_ownership_queries']['order_count'][$order_id] = 1;

        $result = API::verify_order_ownership($order_id);
        $this->assertTrue($result, '小幫手授權賣家的訂單應放行');
    }

    public function testOrderOwnership_CrossSellerAccessDenied(): void
    {
        $this->setAsHelper(50, 10);
        // COUNT = 0 表示沒有任何訂單項目屬於這個 seller
        $GLOBALS['mock_ownership_queries']['order_count'][400] = 0;

        $result = API::verify_order_ownership(400);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('access_denied', $result->get_error_code());
    }

    // =========================================================
    // verify_customer_ownership 測試
    // =========================================================

    public function testCustomerOwnership_AdminAlwaysPasses(): void
    {
        $this->setAsAdmin();
        $result = API::verify_customer_ownership(999);
        $this->assertTrue($result);
    }

    public function testCustomerOwnership_HelperOwnsCustomer(): void
    {
        $this->setAsHelper(50, 10);
        $GLOBALS['mock_ownership_queries']['customer_count'][500] = 2;

        $result = API::verify_customer_ownership(500);
        $this->assertTrue($result);
    }

    public function testCustomerOwnership_CrossSellerAccessDenied(): void
    {
        $this->setAsHelper(50, 10);
        $GLOBALS['mock_ownership_queries']['customer_count'][600] = 0;

        $result = API::verify_customer_ownership(600);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('access_denied', $result->get_error_code());
    }

    // =========================================================
    // verify_shipment_ownership 測試
    // =========================================================

    public function testShipmentOwnership_AdminAlwaysPasses(): void
    {
        $this->setAsAdmin();
        $result = API::verify_shipment_ownership(999);
        $this->assertTrue($result);
    }

    public function testShipmentOwnership_SellerOwnsShipment(): void
    {
        $seller_id   = 10;
        $shipment_id = 700;

        $this->setAsBuygoAdmin($seller_id);
        $GLOBALS['mock_ownership_queries']['shipment_seller'][$shipment_id] = $seller_id;

        $result = API::verify_shipment_ownership($shipment_id);
        $this->assertTrue($result);
    }

    public function testShipmentOwnership_CrossSellerAccessDenied(): void
    {
        $helper_id   = 50;
        $seller_id   = 10;
        $shipment_id = 800;

        $this->setAsHelper($helper_id, $seller_id);
        $GLOBALS['mock_ownership_queries']['shipment_seller'][$shipment_id] = 20; // 其他賣家

        $result = API::verify_shipment_ownership($shipment_id);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('access_denied', $result->get_error_code());
    }

    public function testShipmentOwnership_ShipmentNotFound(): void
    {
        $this->setAsHelper(50, 10);
        $result = API::verify_shipment_ownership(9999);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('shipment_not_found', $result->get_error_code());
    }

    // =========================================================
    // verify_variation_ownership 測試
    // =========================================================

    public function testVariationOwnership_AdminAlwaysPasses(): void
    {
        $this->setAsAdmin();
        $result = API::verify_variation_ownership(999);
        $this->assertTrue($result);
    }

    public function testVariationOwnership_CrossSellerAccessDenied(): void
    {
        $this->setAsHelper(50, 10);
        $GLOBALS['mock_ownership_queries']['variation_author'][900] = 20; // 其他賣家

        $result = API::verify_variation_ownership(900);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('access_denied', $result->get_error_code());
    }

    public function testVariationOwnership_VariationNotFound(): void
    {
        $this->setAsHelper(50, 10);
        $result = API::verify_variation_ownership(9999);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('product_not_found', $result->get_error_code());
    }
}
