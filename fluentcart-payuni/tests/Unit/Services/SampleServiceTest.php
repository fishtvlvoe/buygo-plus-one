<?php

namespace BuyGoFluentCart\PayUNi\Tests\Unit\Services;

use BuyGoFluentCart\PayUNi\Services\PayUNiCryptoService;

use BuyGoFluentCart\PayUNi\Gateway\PayUNiSettingsBase;

use PHPUnit\Framework\TestCase;

final class SampleServiceTest extends TestCase
{
    public function testBuildStubPayload(): void
    {
        $settings = $this->createMock(PayUNiSettingsBase::class);

        $settings->method('getMode')->willReturn('test');
        $settings->method('getMerId')->willReturn('MER123');

        $svc = new PayUNiCryptoService($settings);

        $payload = $svc->buildStubPayload('T123');

        $this->assertEquals('test', $payload['mode']);
        $this->assertEquals('MER123', $payload['mer_id']);
        $this->assertEquals('T123', $payload['merchant_trade_no']);
    }
}

