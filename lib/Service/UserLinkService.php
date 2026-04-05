<?php
declare(strict_types=1);

namespace OCA\DashLink\Service;

use OCA\DashLink\Db\Link;
use OCA\DashLink\Db\LinkMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserSession;

/**
 * Service for managing user-private links
 */
class UserLinkService {
	private LinkMapper $mapper;
	private IUserSession $userSession;
	private SecurityService $securityService;
	private SettingsService $settingsService;

	public function __construct(
		LinkMapper $mapper,
		IUserSession $userSession,
		SecurityService $securityService,
		SettingsService $settingsService
	) {
		$this->mapper = $mapper;
		$this->userSession = $userSession;
		$this->securityService = $securityService;
		$this->settingsService = $settingsService;
	}

	/**
	 * Get current user's ID
	 *
	 * @throws \RuntimeException If no user is logged in
	 */
	private function getCurrentUserId(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new \RuntimeException('No user logged in');
		}
		return $user->getUID();
	}

	/**
	 * Check if user links feature is enabled
	 */
	public function isFeatureEnabled(): bool {
		return $this->settingsService->isUserLinksEnabled();
	}

	/**
	 * Get current user's link limit
	 */
	public function getLinkLimit(): int {
		return $this->settingsService->getUserLinkLimit();
	}

	/**
	 * Get current user's links
	 *
	 * @return array
	 */
	public function getUserLinks(): array {
		$userId = $this->getCurrentUserId();
		$links = $this->mapper->findByUser($userId);
		return array_map(fn(Link $link) => $link->jsonSerialize(), $links);
	}

	/**
	 * Get count of current user's links
	 */
	public function getUserLinkCount(): int {
		$userId = $this->getCurrentUserId();
		return $this->mapper->countUserLinks($userId);
	}

	/**
	 * Create a new link for current user
	 *
	 * @param array $data Link data
	 * @return Link
	 * @throws \RuntimeException If link limit exceeded
	 */
	public function createUserLink(array $data): Link {
		$userId = $this->getCurrentUserId();

		// Check limit
		$currentCount = $this->mapper->countUserLinks($userId);
		$limit = $this->settingsService->getUserLinkLimit();
		if ($currentCount >= $limit) {
			throw new \RuntimeException("Link limit reached. Maximum {$limit} links allowed.");
		}

		// Validate and sanitize inputs
		$url = $data['url'] ?? '';
		$this->securityService->validateUrl($url);

		$title = $this->securityService->sanitizeText($data['title'] ?? '', 255);
		$description = isset($data['description']) && $data['description'] !== null
			? $this->securityService->sanitizeText($data['description'], 1000)
			: null;

		$target = $this->securityService->validateTarget($data['target'] ?? '_blank');

		// Get next position
		$existingLinks = $this->mapper->findByUser($userId);
		$maxPosition = 0;
		foreach ($existingLinks as $existingLink) {
			if ($existingLink->getPosition() > $maxPosition) {
				$maxPosition = $existingLink->getPosition();
			}
		}

		$link = new Link();
		$link->setTitle($title);
		$link->setUrl($url);
		$link->setDescription($description);
		$link->setTarget($target);
		$link->setUserId($userId);
		$link->setGroups([]); // User links don't have group restrictions
		$link->setPosition($maxPosition + 1);
		$link->setEnabled($data['enabled'] ?? 1);
		$link->setCreatedAt(new \DateTime());
		$link->setUpdatedAt(new \DateTime());

		return $this->mapper->insert($link);
	}

	/**
	 * Update a user's link
	 *
	 * @param int $id Link ID
	 * @param array $data Updated data
	 * @return Link
	 * @throws DoesNotExistException If link not found or doesn't belong to user
	 */
	public function updateUserLink(int $id, array $data): Link {
		$userId = $this->getCurrentUserId();

		// Find link with ownership check
		$link = $this->mapper->findByIdForUser($id, $userId);

		// Validate and sanitize inputs
		if (isset($data['title'])) {
			$link->setTitle($this->securityService->sanitizeText($data['title'], 255));
		}
		if (isset($data['url'])) {
			$this->securityService->validateUrl($data['url']);
			$link->setUrl($data['url']);
		}
		if (array_key_exists('description', $data)) {
			$description = $data['description'] !== null
				? $this->securityService->sanitizeText($data['description'], 1000)
				: null;
			$link->setDescription($description);
		}
		if (isset($data['target'])) {
			$link->setTarget($this->securityService->validateTarget($data['target']));
		}
		if (isset($data['position'])) {
			$link->setPosition($data['position']);
		}
		if (isset($data['enabled'])) {
			$link->setEnabled($data['enabled']);
		}

		$link->setUpdatedAt(new \DateTime());

		return $this->mapper->update($link);
	}

	/**
	 * Delete a user's link
	 *
	 * @param int $id Link ID
	 * @throws DoesNotExistException If link not found or doesn't belong to user
	 */
	public function deleteUserLink(int $id): void {
		$userId = $this->getCurrentUserId();

		// Verify ownership before deleting
		$this->mapper->findByIdForUser($id, $userId);

		$this->mapper->deleteById($id);
	}

	/**
	 * Update order of user's links
	 *
	 * @param array $linkIds Array of link IDs in desired order
	 */
	public function updateUserOrder(array $linkIds): void {
		$userId = $this->getCurrentUserId();
		$this->mapper->updateUserPositions($userId, $linkIds);
	}

	/**
	 * Export user's links
	 *
	 * @return array
	 */
	public function exportUserLinks(): array {
		return $this->getUserLinks();
	}

	/**
	 * Import links for current user
	 *
	 * @param array $linksData Array of link data to import
	 * @param IconService $iconService IconService for downloading icons
	 * @return array Import result with counts
	 */
	public function importUserLinks(array $linksData, IconService $iconService): array {
		$userId = $this->getCurrentUserId();
		$imported = 0;
		$skipped = 0;
		$errors = [];

		// Check how many links user can still add
		$currentCount = $this->mapper->countUserLinks($userId);
		$limit = $this->settingsService->getUserLinkLimit();
		$available = $limit - $currentCount;

		if ($available <= 0) {
			return [
				'imported' => 0,
				'skipped' => count($linksData),
				'errors' => ['Link limit reached. No more links can be imported.'],
			];
		}

		// Get existing links for duplicate check
		$existingLinks = $this->mapper->findByUser($userId);

		// Get maximum position to append new links
		$maxPosition = 0;
		foreach ($existingLinks as $existingLink) {
			if ($existingLink->getPosition() > $maxPosition) {
				$maxPosition = $existingLink->getPosition();
			}
		}

		foreach ($linksData as $linkData) {
			// Check limit
			if ($imported >= $available) {
				$skipped += count($linksData) - $imported - $skipped;
				$errors[] = "Link limit reached. Remaining links were skipped.";
				break;
			}

			try {
				// Check if link already exists by title or URL
				$exists = false;
				foreach ($existingLinks as $existingLink) {
					if ($existingLink->getTitle() === ($linkData['title'] ?? '') ||
						$existingLink->getUrl() === ($linkData['url'] ?? '')) {
						$exists = true;
						break;
					}
				}

				if ($exists) {
					$skipped++;
					continue;
				}

				// Increment position
				$maxPosition++;

				// Create new link
				$link = $this->createUserLinkInternal([
					'title' => $linkData['title'] ?? '',
					'url' => $linkData['url'] ?? '',
					'description' => $linkData['description'] ?? null,
					'target' => $linkData['target'] ?? '_blank',
					'position' => $maxPosition,
					'enabled' => $linkData['enabled'] ?? 1,
				], $userId);

				// Download and attach icon if iconUrl is provided
				if (!empty($linkData['iconUrl'])) {
					try {
						$iconService->downloadAndSaveUserIcon($link->getId(), $userId, $linkData['iconUrl']);
					} catch (\Exception $e) {
						$errors[] = "Link '{$linkData['title']}' created but icon download failed: {$e->getMessage()}";
					}
				}

				$imported++;
			} catch (\Exception $e) {
				$errors[] = "Failed to import link '{$linkData['title']}': {$e->getMessage()}";
			}
		}

		return [
			'imported' => $imported,
			'skipped' => $skipped,
			'errors' => $errors,
		];
	}

	/**
	 * Internal method to create link without limit check (used during import)
	 */
	private function createUserLinkInternal(array $data, string $userId): Link {
		// Validate and sanitize inputs
		$url = $data['url'] ?? '';
		$this->securityService->validateUrl($url);

		$title = $this->securityService->sanitizeText($data['title'] ?? '', 255);
		$description = isset($data['description']) && $data['description'] !== null
			? $this->securityService->sanitizeText($data['description'], 1000)
			: null;

		$target = $this->securityService->validateTarget($data['target'] ?? '_blank');

		$link = new Link();
		$link->setTitle($title);
		$link->setUrl($url);
		$link->setDescription($description);
		$link->setTarget($target);
		$link->setUserId($userId);
		$link->setGroups([]);
		$link->setPosition($data['position'] ?? 0);
		$link->setEnabled($data['enabled'] ?? 1);
		$link->setCreatedAt(new \DateTime());
		$link->setUpdatedAt(new \DateTime());

		return $this->mapper->insert($link);
	}

	/**
	 * Get link by ID with ownership verification
	 *
	 * @param int $id Link ID
	 * @return Link
	 * @throws DoesNotExistException If link not found or doesn't belong to user
	 */
	public function getUserLink(int $id): Link {
		$userId = $this->getCurrentUserId();
		return $this->mapper->findByIdForUser($id, $userId);
	}
}
