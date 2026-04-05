<?php
declare(strict_types=1);

namespace OCA\DashLink\Service;

use OCA\DashLink\Db\Link;
use OCA\DashLink\Db\LinkMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use OCP\IUserSession;

class LinkService {
	private LinkMapper $mapper;
	private IUserSession $userSession;
	private IGroupManager $groupManager;
	private SecurityService $securityService;
	private SettingsService $settingsService;

	public function __construct(
		LinkMapper $mapper,
		IUserSession $userSession,
		IGroupManager $groupManager,
		SecurityService $securityService,
		SettingsService $settingsService
	) {
		$this->mapper = $mapper;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
		$this->securityService = $securityService;
		$this->settingsService = $settingsService;
	}

	/**
	 * Get links for the current user (admin links filtered by group membership + user's private links)
	 *
	 * @return array
	 */
	public function getLinksForCurrentUser(): array {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return [];
		}

		$userId = $user->getUID();

		// Get user's groups
		$userGroups = $this->groupManager->getUserGroupIds($user);

		// Find admin links filtered by user's groups
		$adminLinks = $this->mapper->findAdminLinksForUser($userGroups);

		// If user links are enabled, get user's private links
		$userLinks = [];
		if ($this->settingsService->isUserLinksEnabled()) {
			$userLinks = $this->mapper->findEnabledByUser($userId);
		}

		// Merge: admin links first, then user links
		$allLinks = array_merge(
			array_map(fn(Link $link) => $link->jsonSerialize(), $adminLinks),
			array_map(fn(Link $link) => $link->jsonSerialize(), $userLinks)
		);

		return $allLinks;
	}

	/**
	 * Get all links (admin only)
	 *
	 * @return array
	 */
	public function getAllLinks(): array {
		$links = $this->mapper->findAll();
		return array_map(fn(Link $link) => $link->jsonSerialize(), $links);
	}

	/**
	 * Get link by ID
	 *
	 * @throws DoesNotExistException
	 */
	public function getLink(int $id): Link {
		return $this->mapper->findById($id);
	}

	/**
	 * Create new link
	 */
	public function createLink(array $data): Link {
		// SECURITY FIX: Validate and sanitize all inputs
		$url = $data['url'] ?? '';
		$this->securityService->validateUrl($url);

		$title = $this->securityService->sanitizeText($data['title'] ?? '', 255);
		$description = isset($data['description']) && $data['description'] !== null
			? $this->securityService->sanitizeText($data['description'], 1000)
			: null;

		$target = $this->securityService->validateTarget($data['target'] ?? '_blank');
		$groups = $this->securityService->validateGroups($data['groups'] ?? []);

		$link = new Link();
		$link->setTitle($title);
		$link->setUrl($url);
		$link->setDescription($description);
		$link->setTarget($target);
		$link->setGroups($groups);
		$link->setPosition($data['position'] ?? 0);
		$link->setEnabled($data['enabled'] ?? 1);
		$link->setCreatedAt(new \DateTime());
		$link->setUpdatedAt(new \DateTime());

		return $this->mapper->insert($link);
	}

	/**
	 * Update link
	 *
	 * @throws DoesNotExistException
	 */
	public function updateLink(int $id, array $data): Link {
		$link = $this->mapper->findById($id);

		// SECURITY FIX: Validate and sanitize all inputs
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
		if (isset($data['groups'])) {
			$link->setGroups($this->securityService->validateGroups($data['groups']));
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
	 * Delete link
	 */
	public function deleteLink(int $id): void {
		$this->mapper->deleteById($id);
	}

	/**
	 * Update link order
	 *
	 * @param array $linkIds Array of link IDs in desired order
	 */
	public function updateOrder(array $linkIds): void {
		$this->mapper->updatePositions($linkIds);
	}

	/**
	 * Import links from array
	 *
	 * @param array $linksData Array of link data to import
	 * @param IconService $iconService IconService for downloading icons
	 * @return array Import result with counts
	 */
	public function importLinks(array $linksData, IconService $iconService): array {
		$imported = 0;
		$skipped = 0;
		$errors = [];

		// Get maximum position to append new links after existing ones
		$existingLinks = $this->mapper->findAll();
		$maxPosition = 0;
		foreach ($existingLinks as $existingLink) {
			if ($existingLink->getPosition() > $maxPosition) {
				$maxPosition = $existingLink->getPosition();
			}
		}

		foreach ($linksData as $linkData) {
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

				// Increment position for new link (append after last existing)
				$maxPosition++;

				// Create new link
				$link = $this->createLink([
					'title' => $linkData['title'] ?? '',
					'url' => $linkData['url'] ?? '',
					'description' => $linkData['description'] ?? null,
					'target' => $linkData['target'] ?? '_blank',
					'groups' => $linkData['groups'] ?? [],
					'position' => $maxPosition,
					'enabled' => $linkData['enabled'] ?? 1,
				]);

				// Download and attach icon if iconUrl is provided
				if (!empty($linkData['iconUrl'])) {
					try {
						$iconService->downloadAndSaveIcon($link->getId(), $linkData['iconUrl']);
					} catch (\Exception $e) {
						// Icon download failed, but link was created
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
}
