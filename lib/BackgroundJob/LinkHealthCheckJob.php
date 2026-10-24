<?php
declare(strict_types=1);

namespace OCA\DashLink\BackgroundJob;

use OCA\DashLink\Service\HealthCheckService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class LinkHealthCheckJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private HealthCheckService $healthCheckService,
	) {
		parent::__construct($time);
		$this->setInterval(24 * 60 * 60);
		// Reachability probing is not urgent; run it in low-load windows
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		$this->healthCheckService->checkAll();
	}
}
