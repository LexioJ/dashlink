<?php
declare(strict_types=1);

namespace OCA\DashLink\AppInfo;

use OCA\DashLink\Dashboard\DashLinkWidget;
use OCA\DashLink\Service\RateLimitService;
use OCA\DashLink\Service\SecurityService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\ICacheFactory;

class Application extends App implements IBootstrap {
	public const APP_ID = 'dashlink';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
		$vendorAutoload = __DIR__ . "/../../vendor/autoload.php";
		if (file_exists($vendorAutoload)) { require_once $vendorAutoload; }
	}

	public function register(IRegistrationContext $context): void {
		// Register dashboard widget
		$context->registerDashboardWidget(DashLinkWidget::class);

		// Register security services
		$context->registerService(SecurityService::class, function ($c) {
			return new SecurityService();
		});

		$context->registerService(RateLimitService::class, function ($c) {
			return new RateLimitService(
				$c->get(ICacheFactory::class)
			);
		});
	}

	public function boot(IBootContext $context): void {
		// Boot logic if needed
	}
}
