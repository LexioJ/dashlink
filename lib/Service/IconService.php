<?php
declare(strict_types=1);

namespace OCA\DashLink\Service;

use enshrined\svgSanitize\Sanitizer;
use OCA\DashLink\Db\Link;
use OCA\DashLink\Db\LinkMapper;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;

class IconService {
	private const FOLDER_NAME = 'icons';
	private const USER_ICONS_FOLDER_NAME = 'user_icons';
	private const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
	private const ALLOWED_MIME_TYPES = [
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/svg+xml',
		'image/webp',
	];

	private IAppData $appData;
	private LinkMapper $mapper;
	private SecurityService $securityService;

	public function __construct(
		IAppData $appData,
		LinkMapper $mapper,
		SecurityService $securityService
	) {
		$this->appData = $appData;
		$this->mapper = $mapper;
		$this->securityService = $securityService;
	}

	/**
	 * Get admin icons folder (create if not exists)
	 */
	private function getIconsFolder(): ISimpleFolder {
		try {
			return $this->appData->getFolder(self::FOLDER_NAME);
		} catch (NotFoundException $e) {
			return $this->appData->newFolder(self::FOLDER_NAME);
		}
	}

	/**
	 * Get user icons base folder (create if not exists)
	 */
	private function getUserIconsBaseFolder(): ISimpleFolder {
		try {
			return $this->appData->getFolder(self::USER_ICONS_FOLDER_NAME);
		} catch (NotFoundException $e) {
			return $this->appData->newFolder(self::USER_ICONS_FOLDER_NAME);
		}
	}

	/**
	 * Get icons folder for a specific user (create if not exists)
	 */
	public function getUserIconsFolder(string $userId): ISimpleFolder {
		// Sanitize userId to prevent path traversal
		$safeUserId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $userId);

		$baseFolder = $this->getUserIconsBaseFolder();
		try {
			return $baseFolder->getFolder($safeUserId);
		} catch (NotFoundException $e) {
			return $baseFolder->newFolder($safeUserId);
		}
	}

	/**
	 * Upload icon for link
	 *
	 * @param int $linkId
	 * @param string $tmpPath Temporary file path
	 * @param string $mimeType
	 * @throws \InvalidArgumentException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function uploadIcon(int $linkId, string $tmpPath, string $mimeType): Link {
		// Validate mime type
		if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
			throw new \InvalidArgumentException('Invalid file type. Only images are allowed.');
		}

		// Read file content
		$content = file_get_contents($tmpPath);
		if ($content === false) {
			throw new \RuntimeException('Failed to read uploaded file');
		}

		// SECURITY FIX: Sanitize SVG files to prevent XSS attacks
		if ($mimeType === 'image/svg+xml') {
			$sanitizer = new Sanitizer();
			$sanitizedContent = $sanitizer->sanitize($content);

			if ($sanitizedContent === false || empty($sanitizedContent)) {
				throw new \InvalidArgumentException('SVG file contains malicious content and cannot be uploaded');
			}

			$content = $sanitizedContent;
		}

		// Validate file size after sanitization
		$fileSize = strlen($content);
		if ($fileSize > self::MAX_FILE_SIZE) {
			throw new \InvalidArgumentException('File too large. Maximum size is 2MB.');
		}

		// SECURITY FIX: Validate actual image content to prevent mime-type spoofing
		$this->validateImageContent($tmpPath, $mimeType);

		// Get link
		$link = $this->mapper->findById($linkId);

		// Delete old icon if exists
		if ($link->getIconPath() !== null) {
			$this->deleteIconFile($link->getIconPath());
		}

		// Generate filename
		$extension = $this->getExtensionFromMimeType($mimeType);
		$filename = 'icon_' . $linkId . '_' . time() . '.' . $extension;

		// Save file
		$folder = $this->getIconsFolder();
		$file = $folder->newFile($filename);
		$file->putContent($content);

		// Update link
		$link->setIconPath($filename);
		$link->setIconMimeType($mimeType);
		$link->setUpdatedAt(new \DateTime());

		return $this->mapper->update($link);
	}

	/**
	 * Get icon file
	 *
	 * @throws NotFoundException
	 */
	public function getIconFile(string $filename): ISimpleFile {
		// SECURITY FIX: Validate filename to prevent path traversal
		$this->securityService->validateIconFilename($filename);

		$folder = $this->getIconsFolder();
		return $folder->getFile($filename);
	}

	/**
	 * Delete icon for link
	 *
	 * @throws NotFoundException
	 */
	public function deleteIcon(int $linkId): Link {
		$link = $this->mapper->findById($linkId);

		if ($link->getIconPath() !== null) {
			$this->deleteIconFile($link->getIconPath());

			$link->setIconPath(null);
			$link->setIconMimeType(null);
			$link->setUpdatedAt(new \DateTime());

			return $this->mapper->update($link);
		}

		return $link;
	}

	/**
	 * Delete icon file from storage
	 */
	private function deleteIconFile(string $filename): void {
		try {
			$folder = $this->getIconsFolder();
			$file = $folder->getFile($filename);
			$file->delete();
		} catch (NotFoundException $e) {
			// File doesn't exist, nothing to delete
		}
	}

	/**
	 * Get file extension from mime type
	 */
	private function getExtensionFromMimeType(string $mimeType): string {
		$extensions = [
			'image/jpeg' => 'jpg',
			'image/png' => 'png',
			'image/gif' => 'gif',
			'image/svg+xml' => 'svg',
			'image/webp' => 'webp',
		];

		return $extensions[$mimeType] ?? 'bin';
	}

	/**
	 * Validate image content to prevent mime-type spoofing
	 *
	 * @param string $tmpPath Temporary file path
	 * @param string $expectedMimeType Expected mime type
	 * @throws \InvalidArgumentException If validation fails
	 */
	private function validateImageContent(string $tmpPath, string $expectedMimeType): void {
		// Use finfo to verify actual file type
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$actualMimeType = $finfo->file($tmpPath);

		if ($actualMimeType !== $expectedMimeType) {
			throw new \InvalidArgumentException('File type mismatch - possible upload attack. Expected ' . $expectedMimeType . ' but got ' . $actualMimeType);
		}

		// Additional validation for non-SVG images using getimagesize
		if ($expectedMimeType !== 'image/svg+xml') {
			$imageInfo = @getimagesize($tmpPath);
			if ($imageInfo === false) {
				throw new \InvalidArgumentException('Invalid or corrupted image file');
			}

			// Verify mime type from getimagesize matches expected
			if (isset($imageInfo['mime']) && $imageInfo['mime'] !== $expectedMimeType) {
				throw new \InvalidArgumentException('Image validation failed - mime type mismatch');
			}
		}
	}

	/**
	 * Download icon from URL and save it
	 *
	 * @param int $linkId
	 * @param string $iconUrl URL to download icon from
	 * @throws \InvalidArgumentException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function downloadAndSaveIcon(int $linkId, string $iconUrl): Link {
		// SECURITY FIX: Validate URL to prevent SSRF attacks
		$this->securityService->validateDownloadUrl($iconUrl);

		// Create a temporary file
		$tmpFile = tempnam(sys_get_temp_dir(), 'dashlink_icon_');
		if ($tmpFile === false) {
			throw new \RuntimeException('Failed to create temporary file');
		}

		try {
			// Download the file with security restrictions
			$context = stream_context_create([
				'http' => [
					'timeout' => 10, // 10 second timeout
					'user_agent' => 'DashLink/1.0',
					'follow_location' => 1,
					'max_redirects' => 3,
					'ignore_errors' => false,
				],
				'ssl' => [
					'verify_peer' => true,
					'verify_peer_name' => true,
				],
			]);

			$content = @file_get_contents($iconUrl, false, $context);
			if ($content === false) {
				throw new \RuntimeException('Failed to download icon from URL');
			}

			// Validate file size before saving
			if (strlen($content) > self::MAX_FILE_SIZE) {
				throw new \InvalidArgumentException('Downloaded file is too large. Maximum size is 2MB.');
			}

			file_put_contents($tmpFile, $content);

			// Detect mime type
			$finfo = new \finfo(FILEINFO_MIME_TYPE);
			$mimeType = $finfo->file($tmpFile);

			if ($mimeType === false || !in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
				throw new \InvalidArgumentException('Downloaded file is not a valid image');
			}

			// Upload the icon
			return $this->uploadIcon($linkId, $tmpFile, $mimeType);
		} finally {
			// Clean up temporary file
			if (file_exists($tmpFile)) {
				unlink($tmpFile);
			}
		}
	}

	/**
	 * Upload icon for a user's link
	 *
	 * @param int $linkId
	 * @param string $userId User's UID
	 * @param string $tmpPath Temporary file path
	 * @param string $mimeType
	 * @throws \InvalidArgumentException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function uploadUserIcon(int $linkId, string $userId, string $tmpPath, string $mimeType): Link {
		// Validate mime type
		if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
			throw new \InvalidArgumentException('Invalid file type. Only images are allowed.');
		}

		// Read file content
		$content = file_get_contents($tmpPath);
		if ($content === false) {
			throw new \RuntimeException('Failed to read uploaded file');
		}

		// SECURITY FIX: Sanitize SVG files to prevent XSS attacks
		if ($mimeType === 'image/svg+xml') {
			$sanitizer = new Sanitizer();
			$sanitizedContent = $sanitizer->sanitize($content);

			if ($sanitizedContent === false || empty($sanitizedContent)) {
				throw new \InvalidArgumentException('SVG file contains malicious content and cannot be uploaded');
			}

			$content = $sanitizedContent;
		}

		// Validate file size after sanitization
		$fileSize = strlen($content);
		if ($fileSize > self::MAX_FILE_SIZE) {
			throw new \InvalidArgumentException('File too large. Maximum size is 2MB.');
		}

		// SECURITY FIX: Validate actual image content to prevent mime-type spoofing
		$this->validateImageContent($tmpPath, $mimeType);

		// Get link (verify ownership)
		$link = $this->mapper->findByIdForUser($linkId, $userId);

		// Delete old icon if exists
		if ($link->getIconPath() !== null) {
			$this->deleteUserIconFile($userId, $link->getIconPath());
		}

		// Generate filename
		$extension = $this->getExtensionFromMimeType($mimeType);
		$filename = 'icon_' . $linkId . '_' . time() . '.' . $extension;

		// Save file
		$folder = $this->getUserIconsFolder($userId);
		$file = $folder->newFile($filename);
		$file->putContent($content);

		// Update link
		$link->setIconPath($filename);
		$link->setIconMimeType($mimeType);
		$link->setUpdatedAt(new \DateTime());

		return $this->mapper->update($link);
	}

	/**
	 * Get user icon file
	 *
	 * @param string $userId User's UID
	 * @param string $filename Icon filename
	 * @throws NotFoundException
	 */
	public function getUserIconFile(string $userId, string $filename): ISimpleFile {
		// SECURITY FIX: Validate filename to prevent path traversal
		$this->securityService->validateIconFilename($filename);

		$folder = $this->getUserIconsFolder($userId);
		return $folder->getFile($filename);
	}

	/**
	 * Delete icon for a user's link
	 *
	 * @param int $linkId
	 * @param string $userId User's UID
	 * @throws NotFoundException
	 */
	public function deleteUserIcon(int $linkId, string $userId): Link {
		$link = $this->mapper->findByIdForUser($linkId, $userId);

		if ($link->getIconPath() !== null) {
			$this->deleteUserIconFile($userId, $link->getIconPath());

			$link->setIconPath(null);
			$link->setIconMimeType(null);
			$link->setUpdatedAt(new \DateTime());

			return $this->mapper->update($link);
		}

		return $link;
	}

	/**
	 * Delete user icon file from storage
	 */
	private function deleteUserIconFile(string $userId, string $filename): void {
		try {
			$folder = $this->getUserIconsFolder($userId);
			$file = $folder->getFile($filename);
			$file->delete();
		} catch (NotFoundException $e) {
			// File doesn't exist, nothing to delete
		}
	}

	/**
	 * Download icon from URL and save it for a user's link
	 *
	 * @param int $linkId
	 * @param string $userId User's UID
	 * @param string $iconUrl URL to download icon from
	 * @throws \InvalidArgumentException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function downloadAndSaveUserIcon(int $linkId, string $userId, string $iconUrl): Link {
		// SECURITY FIX: Validate URL to prevent SSRF attacks
		$this->securityService->validateDownloadUrl($iconUrl);

		// Create a temporary file
		$tmpFile = tempnam(sys_get_temp_dir(), 'dashlink_user_icon_');
		if ($tmpFile === false) {
			throw new \RuntimeException('Failed to create temporary file');
		}

		try {
			// Download the file with security restrictions
			$context = stream_context_create([
				'http' => [
					'timeout' => 10, // 10 second timeout
					'user_agent' => 'DashLink/1.0',
					'follow_location' => 1,
					'max_redirects' => 3,
					'ignore_errors' => false,
				],
				'ssl' => [
					'verify_peer' => true,
					'verify_peer_name' => true,
				],
			]);

			$content = @file_get_contents($iconUrl, false, $context);
			if ($content === false) {
				throw new \RuntimeException('Failed to download icon from URL');
			}

			// Validate file size before saving
			if (strlen($content) > self::MAX_FILE_SIZE) {
				throw new \InvalidArgumentException('Downloaded file is too large. Maximum size is 2MB.');
			}

			file_put_contents($tmpFile, $content);

			// Detect mime type
			$finfo = new \finfo(FILEINFO_MIME_TYPE);
			$mimeType = $finfo->file($tmpFile);

			if ($mimeType === false || !in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
				throw new \InvalidArgumentException('Downloaded file is not a valid image');
			}

			// Upload the icon
			return $this->uploadUserIcon($linkId, $userId, $tmpFile, $mimeType);
		} finally {
			// Clean up temporary file
			if (file_exists($tmpFile)) {
				unlink($tmpFile);
			}
		}
	}
}
