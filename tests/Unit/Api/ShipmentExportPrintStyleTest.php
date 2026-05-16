<?php

namespace BuyGoPlus\Tests\Unit\Api;

use BuyGoPlus\Api\Shipments_API;
use PHPUnit\Framework\TestCase;

final class ShipmentExportPrintStyleTest extends TestCase
{
    public function test_export_query_uses_variation_join_and_no_product_only_grouping(): void
    {
        $apiFile = BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/api/class-shipments-api.php';
        $src = file_get_contents($apiFile);

        $this->assertNotFalse($src, 'class-shipments-api.php 必須可讀取');

        preg_match('/public function export_shipments\(WP_REST_Request \$request\)(.*?)public function transfer_to_shipment/s', $src, $matches);
        $exportBody = $matches[1] ?? '';

        $this->assertNotSame('', $exportBody, '應可定位 export_shipments 方法內容');
        $this->assertStringContainsString("\$table_product_variations = \$wpdb->prefix . 'fct_product_variations';", $exportBody);
        $this->assertStringContainsString('LEFT JOIN {$table_product_variations} pv ON pv.id = oi.object_id', $exportBody);
        $this->assertMatchesRegularExpression('/GROUP BY\\s+si\\.product_id,\\s*oi\\.object_id/s', $exportBody);
        $this->assertStringNotContainsString('GROUP BY si.product_id"', $exportBody);
    }

    public function test_format_export_product_name_with_and_without_variation(): void
    {
        $api = new class extends Shipments_API {
            public function __construct()
            {
                // Skip parent constructor in unit tests.
            }
        };

        $method = new \ReflectionMethod(Shipments_API::class, 'format_export_product_name');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        $withVariation = $method->invoke($api, [
            'product_name' => '產品測試',
            'variation_id' => '16',
            'variation_identifier' => 'BUYGO-2683-A',
            'variation_title' => '(A) 漢頓',
        ]);

        $withoutVariation = $method->invoke($api, [
            'product_name' => '一般商品',
            'variation_id' => null,
            'variation_identifier' => '',
            'variation_title' => '',
        ]);

        $this->assertSame('產品測試 - (A) 漢頓', $withVariation);
        $this->assertSame('一般商品', $withoutVariation);
    }

    public function test_shipment_details_has_print_media_rules(): void
    {
        $templateFile = BUYGO_PLUS_ONE_PLUGIN_DIR . 'admin/partials/shipment-details.php';
        $src = file_get_contents($templateFile);

        $this->assertNotFalse($src, 'shipment-details.php 必須可讀取');
        $this->assertStringContainsString('@media print', $src);
        $this->assertStringContainsString('.shipment-print-hide', $src);
        $this->assertStringContainsString('.shipment-print-table', $src);
        $this->assertStringContainsString('font-size: 10pt', $src);
    }
}
