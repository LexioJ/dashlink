<?php
declare(strict_types=1);

namespace OCA\DashLink\Service;

/**
 * Centralized security service for input validation and sanitization
 */
class SecurityService {
	/**
	 * Sanitize text input (titles, descriptions)
	 * Removes HTML tags and encodes special characters
	 *
	 * @param string $text Input text to sanitize
	 * @param int $maxLength Maximum allowed length
	 * @return string Sanitized text
	 */
	public function sanitizeText(string $text, int $maxLength = 255): string {
		// Trim whitespace
		$text = trim($text);

		// Strip all HTML tags
		$text = strip_tags($text);

		// Encode special characters to prevent XSS
		$text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		// Enforce maximum length
		if (mb_strlen($text) > $maxLength) {
			$text = mb_substr($text, 0, $maxLength);
		}

		return $text;
	}

	/**
	 * Validate and sanitize URL - only HTTP/HTTPS allowed
	 * Blocks javascript:, data:, file:, and other dangerous protocols
	 *
	 * @param string $url URL to validate
	 * @throws \InvalidArgumentException If URL is invalid or uses disallowed protocol
	 */
	public function validateUrl(string $url): void {
		// Trim and check if empty
		$url = trim($url);
		if (empty($url)) {
			throw new \InvalidArgumentException('URL cannot be empty');
		}

		// Validate URL format
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			throw new \InvalidArgumentException('Invalid URL format');
		}

		// Parse URL
		$parsed = parse_url($url);
		if (!$parsed || !isset($parsed['scheme'])) {
			throw new \InvalidArgumentException('URL must include a protocol (http:// or https://)');
		}

		// ONLY allow HTTP and HTTPS protocols - blocks javascript:, data:, file:, etc.
		$allowedSchemes = ['http', 'https'];
		if (!in_array(strtolower($parsed['scheme']), $allowedSchemes, true)) {
			throw new \InvalidArgumentException('Only HTTP and HTTPS URLs are allowed. Blocked protocol: ' . $parsed['scheme']);
		}

		// Must have a valid host
		if (!isset($parsed['host']) || empty($parsed['host'])) {
			throw new \InvalidArgumentException('URL must include a valid hostname');
		}
	}

	/**
	 * Validate URL for download (SSRF protection)
	 * Blocks access to private IPs, localhost, and cloud metadata endpoints
	 *
	 * @param string $url URL to validate for download
	 * @throws \InvalidArgumentException If URL points to blocked resource
	 */
	public function validateDownloadUrl(string $url): void {
		// First do basic URL validation
		$this->validateUrl($url);

		// Parse URL
		$parsed = parse_url($url);
		$host = $parsed['host'] ?? '';

		// Resolve hostname to IP address
		$ip = gethostbyname($host);

		// Block private IP ranges (RFC 1918) and reserved ranges
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
			throw new \InvalidArgumentException('Cannot access private or internal IP addresses');
		}

		// Block cloud metadata endpoints
		$blockedHosts = [
			'metadata.google.internal',
			'169.254.169.254',
			'instance-data',
			'metadata.azure.com',
			'metadata.packet.net',
		];

		if (in_array(strtolower($host), $blockedHosts, true)) {
			throw new \InvalidArgumentException('Access to this endpoint is blocked');
		}

		// Block localhost variations
		$localhostPatterns = [
			'localhost',
			'127.',
			'0.0.0.0',
			'::1',
			'[::1]',
		];

		foreach ($localhostPatterns as $pattern) {
			if (stripos($host, $pattern) === 0 || stripos($ip, $pattern) === 0) {
				throw new \InvalidArgumentException('Cannot access localhost or loopback addresses');
			}
		}

		// Block link-local addresses (169.254.0.0/16)
		if (strpos($ip, '169.254.') === 0) {
			throw new \InvalidArgumentException('Cannot access link-local addresses');
		}
	}

	/**
	 * Validate integer within allowed range
	 *
	 * @param int $value Value to validate
	 * @param int $min Minimum allowed value
	 * @param int $max Maximum allowed value
	 * @return int Validated value
	 * @throws \InvalidArgumentException If value is out of range
	 */
	public function validateInteger(int $value, int $min, int $max): int {
		if ($value < $min || $value > $max) {
			throw new \InvalidArgumentException("Value must be between {$min} and {$max}");
		}
		return $value;
	}

	/**
	 * Validate filename - prevents path traversal
	 * Only allows alphanumeric characters, dash, underscore, and dot
	 *
	 * @param string $filename Filename to validate
	 * @throws \InvalidArgumentException If filename is invalid
	 */
	public function validateFilename(string $filename): void {
		// Only allow alphanumeric, dash, underscore, and dot
		if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
			throw new \InvalidArgumentException('Invalid filename - only alphanumeric, dash, underscore, and dot allowed');
		}

		// Prevent path traversal
		if (strpos($filename, '..') !== false) {
			throw new \InvalidArgumentException('Path traversal detected in filename');
		}

		// Prevent directory separators
		if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
			throw new \InvalidArgumentException('Directory separators not allowed in filename');
		}

		// Prevent null bytes
		if (strpos($filename, "\0") !== false) {
			throw new \InvalidArgumentException('Null bytes not allowed in filename');
		}
	}

	/**
	 * Validate icon filename - must start with 'icon_'
	 *
	 * @param string $filename Filename to validate
	 * @throws \InvalidArgumentException If filename is invalid
	 */
	public function validateIconFilename(string $filename): void {
		// First run general filename validation
		$this->validateFilename($filename);

		// Must start with 'icon_' prefix
		if (strpos($filename, 'icon_') !== 0) {
			throw new \InvalidArgumentException('Icon filename must start with "icon_"');
		}
	}

	/**
	 * Validate target attribute value
	 *
	 * @param string $target Target value (_blank or _self)
	 * @return string Validated target value
	 * @throws \InvalidArgumentException If target is invalid
	 */
	public function validateTarget(string $target): string {
		$allowedTargets = ['_blank', '_self'];
		if (!in_array($target, $allowedTargets, true)) {
			throw new \InvalidArgumentException('Invalid target value. Must be _blank or _self');
		}
		return $target;
	}

	/**
	 * Validate array of group IDs
	 *
	 * @param array $groups Array of group IDs
	 * @return array Validated groups
	 */
	public function validateGroups(array $groups): array {
		// Ensure all group IDs are strings
		foreach ($groups as $groupId) {
			if (!is_string($groupId)) {
				throw new \InvalidArgumentException('Group IDs must be strings');
			}
			// Sanitize group IDs
			if (!preg_match('/^[a-zA-Z0-9_\-\. @äöüÄÖÜß]+$/u', $groupId)) {
				throw new \InvalidArgumentException('Invalid group ID format');
			}
		}
		return $groups;
	}
}