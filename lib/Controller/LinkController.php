<?php
declare(strict_types=1);

namespace OCA\DashLink\Controller;

use OCA\DashLink\Service\IconService;
use OCA\DashLink\Service\LinkService;
use OCA\DashLink\Service\RateLimitService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

class LinkController extends Controller {
	private LinkService $linkService;
	private IconService $iconService;
	private IGroupManager $groupManager;
	private IURLGenerator $urlGenerator;
	private RateLimitService $rateLimitService;
	private IUserSession $userSession;

	public function __construct(
		string $appName,
		IRequest $request,
		LinkService $linkService,
		IconService $iconService,
		IGroupManager $groupManager,
		IURLGenerator $urlGenerator,
		RateLimitService $rateLimitService,
		IUserSession $userSession
	) {
		parent::__construct($appName, $request);
		$this->linkService = $linkService;
		$this->iconService = $iconService;
		$this->groupManager = $groupManager;
		$this->urlGenerator = $urlGenerator;
		$this->rateLimitService = $rateLimitService;
		$this->userSession = $userSession;
	}

	/**
	 * Get links for current user (filtered by groups)
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index(): JSONResponse {
		$links = $this->linkService->getLinksForCurrentUser();
		return new JSONResponse($links);
	}

	/**
	 * Get icon for link
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getIcon(int $id): DataDisplayResponse|JSONResponse {
		try {
			$link = $this->linkService->getLink($id);
			$iconPath = $link->getIconPath();

			if ($iconPath === null) {
				return new JSONResponse(['error' => 'No icon found'], Http::STATUS_NOT_FOUND);
			}

			$file = $this->iconService->getIconFile($iconPath);

			$response = new DataDisplayResponse(
				$file->getContent(),
				Http::STATUS_OK,
				['Content-Type' => $link->getIconMimeType() ?? 'application/octet-stream']
			);

			// Cache for 1 day
			$response->cacheFor(86400);

			return $response;
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['error' => 'Link not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get all links (admin only)
	 *
	 * @AdminRequired
	 */
	public function adminIndex(): JSONResponse {
		$links = $this->linkService->getAllLinks();
		return new JSONResponse($links);
	}

	/**
	 * Create link
	 *
	 * @AdminRequired
	 */
	public function create(
		string $title,
		string $url,
		?string $description = null,
		string $target = '_blank',
		array $groups = [],
		int $position = 0,
		int $enabled = 1,
		?string $iconPath = null
	): JSONResponse {
		try {
			$data = [
				'title' => $title,
				'url' => $url,
				'description' => $description,
				'target' => $target,
				'groups' => $groups,
				'position' => $position,
				'enabled' => $enabled,
			];
			if ($iconPath !== null) {
				$data['iconPath'] = $iconPath;
			}
			$link = $this->linkService->createLink($data);

			return new JSONResponse($link->jsonSerialize(), Http::STATUS_CREATED);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Update link
	 *
	 * @AdminRequired
	 */
	public function update(
		int $id,
		?string $title = null,
		?string $url = null,
		?string $description = null,
		?string $target = null,
		?array $groups = null,
		?int $position = null,
		?int $enabled = null
	): JSONResponse {
		try {
			$data = [];
			if ($title !== null) $data['title'] = $title;
			if ($url !== null) $data['url'] = $url;
			if ($description !== null) $data['description'] = $description;
			if ($target !== null) $data['target'] = $target;
			if ($groups !== null) $data['groups'] = $groups;
			if ($position !== null) $data['position'] = $position;
			if ($enabled !== null) $data['enabled'] = $enabled;

			$link = $this->linkService->updateLink($id, $data);

			return new JSONResponse($link->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['error' => 'Link not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete link
	 *
	 * @AdminRequired
	 */
	public function delete(int $id): JSONResponse {
		try {
			// Delete icon first if exists
			try {
				$this->iconService->deleteIcon($id);
			} catch (\Exception $e) {
				// Ignore icon deletion errors
			}

			$this->linkService->deleteLink($id);

			return new JSONResponse(['status' => 'ok']);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Upload icon for link
	 *
	 * @AdminRequired
	 */
	public function uploadIcon(int $id): JSONResponse {
		try {
			$file = $this->request->getUploadedFile('icon');

			if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
				return new JSONResponse(['error' => 'No file uploaded'], Http::STATUS_BAD_REQUEST);
			}

			$link = $this->iconService->uploadIcon(
				$id,
				$file['tmp_name'],
				$file['type']
			);

			return new JSONResponse($link->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['error' => 'Link not found'], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Duplicate a link (including icon)
	 *
	 * @AdminRequired
	 */
	public function duplicate(int $id): JSONResponse {
		try {
			$link = $this->linkService->duplicateLink($id, $this->iconService);
			$data = $link->jsonSerialize();
			if (!empty($data['iconPath'])) {
				$data['iconUrl'] = $this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->linkToRoute('dashlink.link.getIcon', ['id' => $link->getId()])
				);
			}
			return new JSONResponse($data, Http::STATUS_CREATED);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['error' => 'Link not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Copy icon from one link to another
	 *
	 * @AdminRequired
	 */
	public function copyIcon(int $id, int $sourceId): JSONResponse {
		try {
			$link = $this->iconService->copyIconToLink($sourceId, $id);
			return new JSONResponse($link->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['error' => 'Link not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete icon for link
	 *
	 * @AdminRequired
	 */
	public function deleteIcon(int $id): JSONResponse {
		try {
			$link = $this->iconService->deleteIcon($id);
			return new JSONResponse($link->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['error' => 'Link not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update link order
	 *
	 * @AdminRequired
	 */
	public function updateOrder(array $linkIds): JSONResponse {
		try {
			$this->linkService->updateOrder($linkIds);
			return new JSONResponse(['status' => 'ok']);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Get available groups
	 *
	 * @AdminRequired
	 */
	public function getGroups(): JSONResponse {
		$groups = $this->groupManager->search('');

		$groupData = array_map(function ($group) {
			return [
				'id' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
			];
		}, $groups);

		return new JSONResponse(array_values($groupData));
	}

	/**
	 * Export all links as JSON
	 *
	 * @AdminRequired
	 */
	public function exportLinks(): JSONResponse {
		try {
			$links = $this->linkService->getAllLinks();

			// Add full icon URLs for each link
			$exportData = array_map(function ($link) {
				if (!empty($link['iconPath'])) {
					$link['iconUrl'] = $this->urlGenerator->getAbsoluteURL(
						$this->urlGenerator->linkToRoute('dashlink.link.getIcon', ['id' => $link['id']])
					);
				}
				return $link;
			}, $links);

			return new JSONResponse($exportData);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Import links from JSON
	 *
	 * @AdminRequired
	 */
	public function importLinks(): JSONResponse {
		try {
			// SECURITY FIX: Rate limiting to prevent DoS attacks
			$user = $this->userSession->getUser();
			if ($user === null) {
				return new JSONResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$userId = $user->getUID();

			// Rate limit: 5 imports per hour per user
			if ($this->rateLimitService->isRateLimited('import', $userId, 5, 3600)) {
				return new JSONResponse(
					['error' => 'Rate limit exceeded. You can only import 5 times per hour. Please try again later.'],
					Http::STATUS_TOO_MANY_REQUESTS
				);
			}

			$file = $this->request->getUploadedFile('file');

			if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
				return new JSONResponse(['error' => 'No file uploaded'], Http::STATUS_BAD_REQUEST);
			}

			// SECURITY FIX: Limit file size to 1MB for import
			if ($file['size'] > 1024 * 1024) {
				return new JSONResponse(
					['error' => 'File too large. Maximum size for import is 1MB.'],
					Http::STATUS_BAD_REQUEST
				);
			}

			$content = file_get_contents($file['tmp_name']);

			// SECURITY FIX: Limit JSON depth to prevent memory exhaustion
			$linksData = json_decode($content, true, 10);

			if (!is_array($linksData)) {
				return new JSONResponse(['error' => 'Invalid JSON format'], Http::STATUS_BAD_REQUEST);
			}

			// SECURITY FIX: Limit number of links in a single import
			if (count($linksData) > 100) {
				return new JSONResponse(
					['error' => 'Too many links in import. Maximum 100 links per import.'],
					Http::STATUS_BAD_REQUEST
				);
			}

			$result = $this->linkService->importLinks($linksData, $this->iconService);

			return new JSONResponse($result);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
