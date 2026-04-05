<?php
declare(strict_types=1);

namespace OCA\DashLink\Dashboard;

use OCP\Dashboard\IWidget;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IInitialStateService;
use OCA\DashLink\Service\LinkService;
use OCA\DashLink\Service\SettingsService;

class DashLinkWidget implements IWidget {
	private IL10N $l10n;
	private IURLGenerator $urlGenerator;
	private IInitialStateService $initialStateService;
	private LinkService $linkService;
	private SettingsService $settingsService;

	public function __construct(
		IL10N $l10n,
		IURLGenerator $urlGenerator,
		IInitialStateService $initialStateService,
		LinkService $linkService,
		SettingsService $settingsService
	) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->initialStateService = $initialStateService;
		$this->linkService = $linkService;
		$this->settingsService = $settingsService;
	}

	public function getId(): string {
		return 'dashlink';
	}

	public function getTitle(): string {
		return $this->settingsService->getWidgetTitle();
	}

	public function getOrder(): int {
		return 10;
	}

	public function getIconClass(): string {
		return 'app-dashlink';
	}

	public function getUrl(): ?string {
		return null;
	}

	public function load(): void {
		// Provide links for current user (admin links filtered by groups + user's private links)
		$links = $this->linkService->getLinksForCurrentUser();

		// Add icon URLs to links
		$linksWithIcons = array_map(function ($link) {
			if (!empty($link['iconPath'])) {
				// Use appropriate route based on whether it's a user link or admin link
				if (!empty($link['userId'])) {
					$link['iconUrl'] = $this->urlGenerator->linkToRoute(
						'dashlink.userLink.getIcon',
						['id' => $link['id']]
					);
				} else {
					$link['iconUrl'] = $this->urlGenerator->linkToRoute(
						'dashlink.link.getIcon',
						['id' => $link['id']]
					);
				}
			} else {
				$link['iconUrl'] = null;
			}
			return $link;
		}, $links);

		// Re-index array to ensure it's sequential (not an object in JSON)
		// Limit to maximum 10 links for widget display
		$linksWithIcons = array_values(array_slice($linksWithIcons, 0, 10));

		$this->initialStateService->provideInitialState(
			'dashlink',
			'links',
			$linksWithIcons
		);

		// Provide active hover effect
		$this->initialStateService->provideInitialState(
			'dashlink',
			'hoverEffect',
			$this->settingsService->getHoverEffect()
		);

		\OCP\Util::addScript('dashlink', 'dashlink-dashboard');
		\OCP\Util::addStyle('dashlink', 'icons');
	}
}
