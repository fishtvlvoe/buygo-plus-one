<?php
/**
 * FluentCartService Unit Tests
 *
 * 測試 FluentCartService 在不同 quantity 情境下，
 * 傳給 wpdb->insert 的庫存欄位是否正確。
 *
 * @package BuyGoPlus\Tests\Unit\Services
 * @since 3.2.0
 */

namespace BuyGoPlus\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuyGoPlus\Services\FluentCartService;

/**
 * Class FluentCartServiceTest
 *
 * 透過攔截 $wpdb->insert 的呼叫，驗證 FluentCartService
 * 在以下情境下傳入正確的庫存設定：
 * - quantity=6（有庫存）→ manage_stock=1、available=6
 * - quantity=0（無限量）→ manage_stock=0、available=0
 * - quantity 未傳入（null）→ manage_stock=0（無限量）
 */
class FluentCartServiceTest extends TestCase
{
    /**
     * 每個測試前重置全域狀態
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 建立假的 FluentCart class，讓 class_exists() 檢查通過
        if (!class_exists('FluentCart\App\App')) {
            eval('namespace FluentCart\App; class App {}');
        }

        // 重置 wpdb mock 的攔截記錄
        $GLOBALS['mock_wpdb_inserts'] = [];
        $GLOBALS['mock_wp_insert_post_return'] = 999;

        // 安裝可攔截 insert 的 wpdb mock
        $GLOBALS['wpdb'] = $this->createWpdbMock();
    }

    /**
     * 每個測試後還原 wpdb
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        // 還原為原始 wpdb mock（bootstrap 中的版本）
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public $insert_id = 1;
            public $last_error = '';

            public function prepare($query, ...$args) {
                // 讓 SHOW TABLES LIKE 查詢模擬表存在
                return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
            }

            public function get_var($query) {
                // 讓表存在檢查通過：回傳表名本身
                if (strpos($query, 'fct_product_details') !== false) {
                    return 'wp_fct_product_details';
                }
                if (strpos($query, 'fct_product_variations') !== false) {
                    return 'wp_fct_product_variations';
                }
                return null;
            }

            public function get_row($query, $output = OBJECT) { return null; }
            public function get_results($query, $output = OBJECT) { return []; }

            public function insert($table, $data, $format = null) {
                $this->insert_id = 1;
                return 1;
            }

            public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
            public function delete($table, $where, $where_format = null) { return 1; }
            public function query($query) { return true; }
        };
    }

    /**
     * 建立可攔截 insert 呼叫的 wpdb mock
     *
     * 每次 insert 被呼叫時，會將 $table 和 $data 記錄到
     * $GLOBALS['mock_wpdb_inserts'] 中，方便測試驗證。
     *
     * @return object
     */
    private function createWpdbMock(): object
    {
        return new class {
            public $prefix = 'wp_';
            public $insert_id = 1;
            public $last_error = '';

            public function prepare($query, ...$args) {
                return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
            }

            public function get_var($query) {
                // 模擬表存在：讓所有 SHOW TABLES LIKE 查詢成功
                if (strpos($query, 'fct_product_details') !== false) {
                    return 'wp_fct_product_details';
                }
                if (strpos($query, 'fct_product_variations') !== false) {
                    return 'wp_fct_product_variations';
                }
                return null;
            }

            public function get_row($query, $output = OBJECT) { return null; }
            public function get_results($query, $output = OBJECT) { return []; }

            public function insert($table, $data, $format = null) {
                // 攔截並記錄每次 insert 的資料
                $GLOBALS['mock_wpdb_inserts'][] = [
                    'table' => $table,
                    'data'  => $data,
                ];
                $this->insert_id = count($GLOBALS['mock_wpdb_inserts']);
                return 1;
            }

            public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
            public function delete($table, $where, $where_format = null) { return 1; }
            public function query($query) { return true; }

            public function get_charset_collate() {
                return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
            }
        };
    }

    /**
     * 從攔截記錄中取出 fct_product_details 的 insert 資料
     *
     * @return array|null
     */
    private function getProductDetailsInsert(): ?array
    {
        foreach ($GLOBALS['mock_wpdb_inserts'] as $insert) {
            if (strpos($insert['table'], 'fct_product_details') !== false) {
                return $insert['data'];
            }
        }
        return null;
    }

    /**
     * 從攔截記錄中取出 fct_product_variations 的 insert 資料
     *
     * @return array|null
     */
    private function getVariationInsert(): ?array
    {
        foreach ($GLOBALS['mock_wpdb_inserts'] as $insert) {
            if (strpos($insert['table'], 'fct_product_variations') !== false) {
                return $insert['data'];
            }
        }
        return null;
    }

    /**
     * 建立 FluentCartService 並呼叫 create_product
     *
     * @param array $productData
     * @return mixed
     */
    private function callCreateProduct(array $productData)
    {
        $service = new FluentCartService();
        return $service->create_product($productData);
    }

    // ========================================
    // Case 1: quantity=6（有庫存）
    // ========================================

    /**
     * 測試：quantity=6 時，product_details 的 manage_stock=1、stock_availability='in-stock'
     *
     * 這是 Bug 修正的核心測試：確認有庫存商品不被誤判為無限量
     */
    public function testCreateProductDetailsWithQuantitySetsManagedStock(): void
    {
        $this->callCreateProduct([
            'name'     => '測試商品',
            'price'    => 100,
            'quantity' => 6,
            'user_id'  => 1,
        ]);

        $detailsData = $this->getProductDetailsInsert();

        $this->assertNotNull($detailsData, 'product_details insert 應該被呼叫');
        $this->assertEquals(1, $detailsData['manage_stock'], 'quantity=6 時 manage_stock 應為 1（追蹤庫存）');
        $this->assertEquals('in-stock', $detailsData['stock_availability'], 'stock_availability 應為 in-stock');
    }

    /**
     * 測試：quantity=6 時，variation 的 manage_stock=1、available=6、total_stock=6
     *
     * 確認 variation 層級的庫存值也正確設定
     */
    public function testCreateDefaultVariationWithQuantitySetsCorrectAvailable(): void
    {
        $this->callCreateProduct([
            'name'     => '測試商品',
            'price'    => 200,
            'quantity' => 6,
            'user_id'  => 1,
        ]);

        $variationData = $this->getVariationInsert();

        $this->assertNotNull($variationData, 'fct_product_variations insert 應該被呼叫');
        $this->assertEquals(1, $variationData['manage_stock'], 'quantity=6 時 variation manage_stock 應為 1');
        $this->assertEquals(6, $variationData['available'], 'quantity=6 時 available 應為 6');
        $this->assertEquals(6, $variationData['total_stock'], 'quantity=6 時 total_stock 應為 6');
    }

    // ========================================
    // Case 2: quantity=0（無限量）
    // ========================================

    /**
     * 測試：quantity=0（明確傳入 0）時，product_details 的 manage_stock=1（追蹤庫存，庫存為 0 即缺貨）
     *
     * 修正後語意：quantity=0 → 庫存為 0（缺貨），仍追蹤庫存
     * 只有 quantity=null（未傳入）才代表無限量
     */
    public function testCreateProductDetailsWithZeroQuantitySetsUnlimited(): void
    {
        $this->callCreateProduct([
            'name'     => '缺貨商品',
            'price'    => 100,
            'quantity' => 0,
            'user_id'  => 1,
        ]);

        $detailsData = $this->getProductDetailsInsert();

        $this->assertNotNull($detailsData, 'product_details insert 應該被呼叫');
        // 修正後：quantity=0 表示「庫存為 0（缺貨）」，manage_stock=1（追蹤庫存）
        $this->assertEquals(1, $detailsData['manage_stock'], 'quantity=0 時 manage_stock 應為 1（追蹤庫存，庫存 0 表示缺貨）');
    }

    // ========================================
    // Case 3: quantity 未傳入（null）
    // ========================================

    /**
     * 測試：quantity 未傳入時，product_details 的 manage_stock=0（無限量）
     *
     * 批次上架若沒有 quantity 欄位，應視為無限量商品
     */
    public function testCreateProductDetailsWithNullQuantitySetsUnlimited(): void
    {
        $this->callCreateProduct([
            'name'    => '無限量商品（未設庫存）',
            'price'   => 100,
            // 故意不傳 quantity
            'user_id' => 1,
        ]);

        $detailsData = $this->getProductDetailsInsert();

        $this->assertNotNull($detailsData, 'product_details insert 應該被呼叫');
        $this->assertEquals(0, $detailsData['manage_stock'], 'quantity 未傳入時 manage_stock 應為 0（無限量）');
    }

    // ========================================
    // Case 4: quantity=0 時 variation 的 available=0
    // ========================================

    /**
     * 測試：quantity=0（缺貨）時，variation 的 manage_stock=1、available=0、total_stock=0
     *
     * 修正後語意：quantity=0 → 庫存為 0（缺貨，追蹤庫存）
     * FluentCart 以 available=0 + manage_stock=0 表示無限量
     * FluentCart 以 available=0 + manage_stock=1 表示缺貨（追蹤庫存且庫存為 0）
     */
    public function testCreateDefaultVariationWithZeroQuantityHasZeroAvailable(): void
    {
        $this->callCreateProduct([
            'name'     => '缺貨商品',
            'price'    => 100,
            'quantity' => 0,
            'user_id'  => 1,
        ]);

        $variationData = $this->getVariationInsert();

        $this->assertNotNull($variationData, 'fct_product_variations insert 應該被呼叫');
        // 修正後：quantity=0 → 追蹤庫存（manage_stock=1）且庫存為 0（缺貨）
        $this->assertEquals(1, $variationData['manage_stock'], 'quantity=0 時 variation manage_stock 應為 1（追蹤庫存）');
        $this->assertEquals(0, $variationData['available'], 'quantity=0 時 available 應為 0（缺貨）');
        $this->assertEquals(0, $variationData['total_stock'], 'quantity=0 時 total_stock 應為 0（缺貨）');
    }
}
