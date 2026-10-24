<?php
declare(strict_types=1);

namespace OCA\DashLink\Tests\Unit\Service;

use OCA\DashLink\Service\HealthCheckService;
use PHPUnit\Framework\TestCase;

class HealthCheckServiceTest extends TestCase {
    public function testMapping2xxIsOk(): void {
        $this->assertSame(['ok', '200'], HealthCheckService::mapOutcome(200, false, null));
        $this->assertSame(['ok', '204'], HealthCheckService::mapOutcome(204, false, null));
    }

    public function testRedirectedSuccessIsRedirect(): void {
        $this->assertSame(['redirect', '200'], HealthCheckService::mapOutcome(200, true, null));
    }

    public function test4xx5xxIsBroken(): void {
        $this->assertSame(['broken', '404'], HealthCheckService::mapOutcome(404, false, null));
        $this->assertSame(['broken', '503'], HealthCheckService::mapOutcome(503, false, null));
    }

    public function testNetworkErrorIsUnknown(): void {
        $this->assertSame(['unknown', 'ConnectException'], HealthCheckService::mapOutcome(null, false, 'ConnectException'));
    }
}
