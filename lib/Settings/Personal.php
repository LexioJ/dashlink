<?php
declare(strict_types=1);

namespace OCA\DashLink\Settings;

use OCA\DashLink\Service\SettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class Personal implements ISettings {
	private SettingsService $settingsService;

	public function __construct(SettingsService $settingsService) {
		$this->settingsService = $settingsService;
	}

	public function getForm(): TemplateResponse {
		// If user links are disabled, return an empty/hidden page
		if (!$this->settingsService->isUserLinksEnabled()) {
			return new TemplateResponse('dashlink', 'personal-disabled');
		}

		return new TemplateResponse('dashlink', 'personal');
	}

	public function getSection(): ?string {
		// Only show in settings if user links are enabled
		if (!$this->settingsService->isUserLinksEnabled()) {
			return null;
		}
		return 'dashlink';
	}

	public function getPriority(): int {
		return 50;
	}
}
