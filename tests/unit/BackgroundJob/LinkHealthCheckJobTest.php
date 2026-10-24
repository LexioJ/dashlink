<?php
declare(strict_types=1);

namespace OCA\DashLink\Tests\Unit\BackgroundJob;

use OCA\DashLink\BackgroundJob\LinkHealthCheckJob;
use OCA\DashLink\Service\HealthCheckService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

class LinkHealthCheckJobTest extends TestCase {
    public function testRunDelegatesToService(): void {
        $service = $this->createMock(HealthCheckService::class);
        $service->expects($this->once())->method('checkAll')->willReturn(3);
        $timeFactory = $this->createMock(ITimeFactory::class);

        $job = new LinkHealthCheckJob($timeFactory, $service);
        // run() is protected on TimedJob; invoke via reflection
        $run = new \ReflectionMethod($job, 'run');
        $run->setAccessible(true);
        $run->invoke($job, []);
    }
}
