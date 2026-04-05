<?php
declare(strict_types=1);

namespace OCA\DashLink\Controller;

use OCA\DashLink\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class SettingsController extends Controller {
	private SettingsService $settingsService;

	public function __construct(
		string $appName,
		IRequest $request,
		SettingsService $settingsService
	) {
		parent::__construct($appName, $request);
		$this->settingsService = $settingsService;
	}

	/**
	 * Get settings
	 *
	 * @AdminRequired
	 */
	public function index(): JSONResponse {
		return new JSONResponse($this->settingsService->getSettings());
	}

	/**
	 * Update settings
	 *
	 * @AdminRequired
	 */
	public function update(
		?string $hoverEffect = null,
		?string $widgetTitle = null,
		?bool $userLinksEnabled = null,
		?int $userLinkLimit = null
	): JSONResponse {
		try {
			if ($hoverEffect !== null) {
				$this->settingsService->setHoverEffect($hoverEffect);
			}

			if ($widgetTitle !== null) {
				$this->settingsService->setWidgetTitle($widgetTitle);
			}

			if ($userLinksEnabled !== null) {
				$this->settingsService->setUserLinksEnabled($userLinksEnabled);
			}

			if ($userLinkLimit !== null) {
				$this->settingsService->setUserLinkLimit($userLinkLimit);
			}

			return new JSONResponse(['status' => 'ok']);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], 400);
		}
	}
}
