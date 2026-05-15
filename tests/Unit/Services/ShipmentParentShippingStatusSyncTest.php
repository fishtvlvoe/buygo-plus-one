<?php

namespace BuyGoPlus\Tests\Unit\Services;

use BuyGoPlus\Services\OrderShippingManager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ShipmentParentShippingStatusSyncTest extends TestCase
{
    public function testMarkShippedCallsSyncParentShippingStatusAfterParentCompletion(): void
    {
        $source = file_get_contents(BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-shipment-service.php');
        $this->assertIsString($source);

        $parentCompletionPos = strpos($source, '$this->check_parent_completion($shipment_id);');
        $syncCallPos = strpos($source, 'syncParentShippingStatus(');

        $this->assertNotFalse($parentCompletionPos, 'mark_shipped() 應包含 check_parent_completion() 呼叫');
        $this->assertNotFalse($syncCallPos, 'mark_shipped() 應同步呼叫 syncParentShippingStatus()');
        $this->assertGreaterThan(
            $parentCompletionPos,
            $syncCallPos,
            'syncParentShippingStatus() 應在 check_parent_completion() 之後呼叫'
        );
    }

    public function testMarkShippedWrapsParentSyncInTryCatch(): void
    {
        $source = file_get_contents(BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-shipment-service.php');
        $this->assertIsString($source);

        $this->assertMatchesRegularExpression(
            '/try\s*\{[\s\S]*syncParentShippingStatus\([\s\S]*\}[\s\S]*catch\s*\(\\\\Exception \$e\)/',
            $source,
            '父訂單同步應在獨立 try-catch 區塊內，失敗不阻斷出貨流程'
        );
    }

    public function testSyncParentShippingStatusMethodIsPublic(): void
    {
        $reflection = new ReflectionClass(OrderShippingManager::class);
        $method = $reflection->getMethod('syncParentShippingStatus');

        $this->assertTrue($method->isPublic());
    }

    public function testSyncParentShippingStatusKeepsPartialShipmentPreparingRule(): void
    {
        $source = file_get_contents(BUYGO_PLUS_ONE_PLUGIN_DIR . 'includes/services/class-order-shipping-manager.php');
        $this->assertIsString($source);

        $this->assertStringContainsString(
            "elseif ((\$statusCounts['preparing'] + \$statusCounts['processing'] + \$statusCounts['shipped'] + \$statusCounts['completed']) > 0)",
            $source
        );
        $this->assertStringContainsString("\$newParentStatus = 'preparing';", $source);
        $this->assertStringContainsString("\$newParentStatus = 'unshipped';", $source);
    }
}
