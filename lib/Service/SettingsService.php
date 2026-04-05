<?php
declare(strict_types=1);

namespace OCA\DashLink\Service;

use OCP\IConfig;

class SettingsService {
	private IConfig $config;
	private SecurityService $securityService;
	private string $appName = 'dashlink';

	// Available effects - must match frontend registry
	private const AVAILABLE_EFFECTS = [
		'blur' => [
			'id' => 'blur',
			'name' => 'Blur Overlay',
			'description' => 'Description appears over a blurred logo background'
		],
		'flip' => [
			'id' => 'flip',
			'name' => '3D Card Flip',
			'description' => 'Card flips to reveal description on the back'
		],
		'slide' => [
			'id' => 'slide',
			'name' => 'Slide Panel',
			'description' => 'Description panel slides up from the bottom'
		],
	];

	private const DEFAULT_EFFECT = 'blur';
	private const DEFAULT_WIDGET_TITLE = 'DashLink';
	private const MAX_TITLE_LENGTH = 100;
	private const DEFAULT_USER_LINKS_ENABLED = false;
	private const DEFAULT_USER_LINK_LIMIT = 10;
	private const MIN_USER_LINK_LIMIT = 1;
	private const MAX_USER_LINK_LIMIT = 50;

	public function __construct(IConfig $config, SecurityService $securityService) {
		$this->config = $config;
		$this->securityService = $securityService;
	}

	/**
	 * Get current hover effect
	 */
	public function getHoverEffect(): string {
		$effect = $this->config->getAppValue($this->appName, 'hover_effect', self::DEFAULT_EFFECT);

		// Validate effect exists
		if (!isset(self::AVAILABLE_EFFECTS[$effect])) {
			return self::DEFAULT_EFFECT;
		}

		return $effect;
	}

	/**
	 * Set hover effect
	 */
	public function setHoverEffect(string $effect): void {
		if (!isset(self::AVAILABLE_EFFECTS[$effect])) {
			throw new \InvalidArgumentException('Invalid effect: ' . $effect);
		}

		$this->config->setAppValue($this->appName, 'hover_effect', $effect);
	}

	/**
	 * Get all available effects
	 */
	public function getAvailableEffects(): array {
		return array_values(self::AVAILABLE_EFFECTS);
	}

	/**
	 * Get widget title
	 */
	public function getWidgetTitle(): string {
		return $this->config->getAppValue($this->appName, 'widget_title', self::DEFAULT_WIDGET_TITLE);
	}

	/**
	 * Set widget title
	 */
	public function setWidgetTitle(string $title): void {
		// SECURITY FIX: Sanitize title to prevent XSS attacks
		$title = $this->securityService->sanitizeText($title, self::MAX_TITLE_LENGTH);

		// Use default if empty after sanitization
		if (empty($title)) {
			$title = self::DEFAULT_WIDGET_TITLE;
		}

		$this->config->setAppValue($this->appName, 'widget_title', $title);
	}

	/**
	 * Check if user links feature is enabled
	 */
	public function isUserLinksEnabled(): bool {
		return $this->config->getAppValue($this->appName, 'user_links_enabled', self::DEFAULT_USER_LINKS_ENABLED ? '1' : '0') === '1';
	}

	/**
	 * Enable or disable user links feature
	 */
	public function setUserLinksEnabled(bool $enabled): void {
		$this->config->setAppValue($this->appName, 'user_links_enabled', $enabled ? '1' : '0');
	}

	/**
	 * Get maximum number of links a user can create
	 */
	public function getUserLinkLimit(): int {
		$limit = (int) $this->config->getAppValue($this->appName, 'user_link_limit', (string) self::DEFAULT_USER_LINK_LIMIT);

		// Ensure limit is within bounds
		if ($limit < self::MIN_USER_LINK_LIMIT) {
			return self::MIN_USER_LINK_LIMIT;
		}
		if ($limit > self::MAX_USER_LINK_LIMIT) {
			return self::MAX_USER_LINK_LIMIT;
		}

		return $limit;
	}

	/**
	 * Set maximum number of links a user can create
	 */
	public function setUserLinkLimit(int $limit): void {
		// Validate limit is within bounds
		if ($limit < self::MIN_USER_LINK_LIMIT || $limit > self::MAX_USER_LINK_LIMIT) {
			throw new \InvalidArgumentException(
				"User link limit must be between " . self::MIN_USER_LINK_LIMIT . " and " . self::MAX_USER_LINK_LIMIT
			);
		}

		$this->config->setAppValue($this->appName, 'user_link_limit', (string) $limit);
	}

	/**
	 * Get all settings
	 */
	public function getSettings(): array {
		return [
			'hoverEffect' => $this->getHoverEffect(),
			'availableEffects' => $this->getAvailableEffects(),
			'widgetTitle' => $this->getWidgetTitle(),
			'userLinksEnabled' => $this->isUserLinksEnabled(),
			'userLinkLimit' => $this->getUserLinkLimit(),
		];
	}
}
