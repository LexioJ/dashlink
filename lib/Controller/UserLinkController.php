<?php
declare(strict_types=1);

namespace OCA\DashLink\Controller;

use OCA\DashLink\Service\IconService;
use OCA\DashLink\Service\RateLimitService;
use OCA\DashLink\Service\UserLinkService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

/**
 * Controller for user-private links
 * All endpoints require user authentication but not admin access
 */
class UserLinkController extends Controller {
	private UserLinkService $userLinkService;
	private IconService $iconService;
	private IURLGenerator $urlGenerator;
	private RateLimitService $rateLimitService;
	private IUserSession $userSession;

	public function __construct(
		string $appName,
		IRequest $request,
		UserLinkService $userLinkService,
		IconService $iconService,
		IURLGenerator $urlGenerator,
		RateLimitService $rateLimitService,
		IUserSession $userSession
	) {
		parent::__construct($appName, $request);
		$this->userLinkService = $userLinkService;
		$this->iconService = $iconService;
		$this->urlGenerator = $urlGenerator;
		$this->rateLimitService = $rateLimitService;
		$this->userSession = $userSession;
	}

	/**
	 * Check if user links feature is enabled
	 */
	private function checkFeatureEnabled(): ?JSONResponse {
		if (!$this->userLinkService->isFeatureEnabled()) {
			return new JSONResponse(
				['error' => 'User links feature is disabled by administrator'],
				Http::STATUS_FORBIDDEN
			);
		}
		return null;
	}

	/**
	 * Get current user ID
	 */
	private function getCurrentUserId(): ?string {
		$user = $this->userSession->getUser();
		return $user?->getUID();
	}

	/**
	 * List current user's links
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index(): JSONResponse {
		$featureCheck = $this->checkFeatureEnabled();
		if ($featureCheck !== null) {
			return $featureCheck;
		}

		try {
			$links = $this->userLinkService->getUserLinks();

			// Add icon URLs
			$linksWithIcons = array_map(function ($link) {
				if (!empty($link['iconPath'])) {
					$link['iconUrl'] = $this->urlGenerator->linkToRoute(
						'dashlink.userLink.getIcon',
						['id' => $link['id']]
					);
				} else {
					$link['iconUrl'] = null;
				}
				return $link;
			}, $links);

			return new JSONResponse([
				'links' => array_values($linksWithIcons),
				'count' => count($linksWithIcons),
				'limit' => $this->userLinkService->getLinkLimit(),
			]);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new link
	 *
	 * @NoAdminRequired
	 */
	public function create(
		string $title,
		string $url,
		?string $description = null,
		string $target = '_blank',
		int $enabled = 1
	): JSONResponse {
		$featureCheck = $this->checkFeatureEnabled();
		if ($featureCheck !== null) {
			return $featureCheck;
		}

		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		// Rate limit: 20 creates per hour per user
		if ($this->rateLimitService->isRateLimited('user_link_create', $userId, 20, 3600)) {
			return new JSONResponse(
				['error' => 'Rate limit exceeded. Please try again later.'],
				Http::STATUS_TOO_MANY_REQUESTS
			);
		}

		try {
			$link = $this->userLinkService->createUserLink([
				'title' => $title,
				'url' => $url,
				'description' => $description,
				'target' => $target,
				'enabled' => $enabled,
			]);

			$result = $link->jsonSerialize();
			$result['iconUrl'] = null;

			return new JSONResponse($result, Http::STATUS_CREATED);
		} catch (\RuntimeException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Update a link
	 *
	 * @NoAdminRequired
	 */
	public function update(
		int $id,
		?string $title = null,
		?string $url = null,
		?string $description = null,
		?string $target = null,
		?int $position = null,
		?int $enabled = null
	): JSONResponse {
		$featureCheck = $this->checkFeatureEnabled();
		if ($featureCheck !== null) {
			return $featureCheck;
		}

		try {
			$data = [];
			if ($title !== null) $data['title'] = $title;
			if ($url !== null) $data['url'] = $url;
			if ($description !== null) $data['description'] = $description;
			if ($target !== null) $data['target'] = $target;
			if ($position !== null) $data['position'] = $position;
			if ($enabled !== null) $data['enabled'] = $enabled;

			$link = $this->userLinkService->updateUserLink($id, $data);

			$result = $link->jsonSerialize();
			if (!empty($result['iconPath'])) {
				$result['iconUrl'] = $this->urlGenerator->linkToRoute(
					'dashlink.userLink.getIcon',
					['id' => $result['id']]
				);
			} else {
				$result['iconUrl'] = null;
			}

			return new JSONResponse($result);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['error' => 'Link not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete a link
	 *
	 * @NoAdminRequired
	 */
	public function delete(int $id): JSONResponse {
		$featureCheck = $this->checkFeatureEnabled();
		if ($featureCheck !== null) {
			return $featureCheck;
		}

		try {
			// Delete icon first if exists
			$userId = $this->getCurrentUserId();
			if ($userId !== null) {
				try {
					$this->iconService->deleteUserIcon($id, $userId);
				} catch (\Exception $e) {
					// Ignore icon deletion errors
				}
			}

			$this->userLinkService->deleteUserLink($id);

			return new JSONResponse(['status' => 'ok']);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['error' => 'Link not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Upload icon for a link
	 *
	 * @NoAdminRequired
	 */
	public function uploadIcon(int $id): JSONResponse {
		$featureCheck = $this->checkFeatureEnabled();
		if ($featureCheck !== null) {
			return $featureCheck;
		}

		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$file = $this->request->getUploadedFile('icon');

			if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
				return new JSONResponse(['error' => 'No file uploaded'], Http::STATUS_BAD_REQUEST);
			}

			$link = $this->iconService->uploadUserIcon(
				$id,
				$userId,
				$file['tmp_name'],
				$file['type']
			);

			$result = $link->jsonSerialize();
			$result['iconUrl'] = $this->urlGenerator->linkToRoute(
				'dashlink.userLink.getIcon',
				['id' => $result['id']]
			);

			return new JSONResponse($result);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['error' => 'Link not found'], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete icon for a link
	 *
	 * @NoAdminRequired
	 */
	public function deleteIcon(int $id): JSONResponse {
		$featureCheck = $this->checkFeatureEnabled();
		if ($featureCheck !== null) {
			return $featureCheck;
		}

		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$link = $this->iconService->deleteUserIcon($id, $userId);

			$result = $link->jsonSerialize();
			$result['iconUrl'] = null;

			return new JSONResponse($result);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['error' => 'Link not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get icon for a link
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getIcon(int $id): DataDisplayResponse|JSONResponse {
		$featureCheck = $this->checkFeatureEnabled();
		if ($featureCheck !== null) {
			return $featureCheck;
		}

		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$link = $this->userLinkService->getUserLink($id);
			$iconPath = $link->getIconPath();

			if ($iconPath === null) {
				return new JSONResponse(['error' => 'No icon found'], Http::STATUS_NOT_FOUND);
			}

			$file = $this->iconService->getUserIconFile($userId, $iconPath);

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
	 * Update link order
	 *
	 * @NoAdminRequired
	 */
	public function updateOrder(array $linkIds): JSONResponse {
		$featureCheck = $this->checkFeatureEnabled();
		if ($featureCheck !== null) {
			return $featureCheck;
		}

		try {
			$this->userLinkService->updateUserOrder($linkIds);
			return new JSONResponse(['status' => 'ok']);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Export links as JSON
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function exportLinks(): JSONResponse {
		$featureCheck = $this->checkFeatureEnabled();
		if ($featureCheck !== null) {
			return $featureCheck;
		}

		try {
			$links = $this->userLinkService->exportUserLinks();

			// Add full icon URLs for each link
			$exportData = array_map(function ($link) {
				if (!empty($link['iconPath'])) {
					$link['iconUrl'] = $this->urlGenerator->getAbsoluteURL(
						$this->urlGenerator->linkToRoute('dashlink.userLink.getIcon', ['id' => $link['id']])
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
	 * @NoAdminRequired
	 */
	public function importLinks(): JSONResponse {
		$featureCheck = $this->checkFeatureEnabled();
		if ($featureCheck !== null) {
			return $featureCheck;
		}

		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		// Rate limit: 3 imports per hour per user
		if ($this->rateLimitService->isRateLimited('user_link_import', $userId, 3, 3600)) {
			return new JSONResponse(
				['error' => 'Rate limit exceeded. You can only import 3 times per hour. Please try again later.'],
				Http::STATUS_TOO_MANY_REQUESTS
			);
		}

		try {
			$file = $this->request->getUploadedFile('file');

			if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
				return new JSONResponse(['error' => 'No file uploaded'], Http::STATUS_BAD_REQUEST);
			}

			// Limit file size to 1MB for import
			if ($file['size'] > 1024 * 1024) {
				return new JSONResponse(
					['error' => 'File too large. Maximum size for import is 1MB.'],
					Http::STATUS_BAD_REQUEST
				);
			}

			$content = file_get_contents($file['tmp_name']);

			// Limit JSON depth to prevent memory exhaustion
			$linksData = json_decode($content, true, 10);

			if (!is_array($linksData)) {
				return new JSONResponse(['error' => 'Invalid JSON format'], Http::STATUS_BAD_REQUEST);
			}

			// Limit number of links in a single import
			if (count($linksData) > 50) {
				return new JSONResponse(
					['error' => 'Too many links in import. Maximum 50 links per import.'],
					Http::STATUS_BAD_REQUEST
				);
			}

			$result = $this->userLinkService->importUserLinks($linksData, $this->iconService);

			return new JSONResponse($result);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
