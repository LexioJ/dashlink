<?php
declare(strict_types=1);

namespace OCA\DashLink\Service;

use OCA\DashLink\AppInfo\Application;
use OCA\DashLink\Db\Link;
use OCA\DashLink\Db\LinkMapper;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

class HealthCheckService {
	private const TIMEOUT_SECONDS = 5;
	private const MAX_REDIRECTS = 5;

	public function __construct(
		private LinkMapper $mapper,
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Map an HTTP outcome to [health_status, health_detail].
	 * Network-level failures map to 'unknown', not 'broken': the Nextcloud
	 * server may simply be unable to reach intranet-only URLs.
	 *
	 * @return array{0:string,1:string}
	 */
	public static function mapOutcome(?int $statusCode, bool $redirected, ?string $errorClass): array {
		if ($statusCode === null) {
			return ['unknown', (string)($errorClass ?? 'error')];
		}
		if ($statusCode >= 200 && $statusCode < 300) {
			return [$redirected ? 'redirect' : 'ok', (string)$statusCode];
		}
		if ($statusCode >= 300 && $statusCode < 400) {
			return ['redirect', (string)$statusCode];
		}
		return ['broken', (string)$statusCode];
	}

	public function checkLink(Link $link): void {
		$statusCode = null;
		$redirected = false;
		$errorClass = null;

		try {
			$client = $this->clientService->newClient();
			$options = [
				'timeout' => self::TIMEOUT_SECONDS,
				'allow_redirects' => ['max' => self::MAX_REDIRECTS, 'track_redirects' => true],
			];
			try {
				$response = $client->head($link->getUrl(), $options);
			} catch (\Throwable $headError) {
				// Some servers reject HEAD (405 or outright close) - retry with GET
				$response = $client->get($link->getUrl(), $options);
			}
			$statusCode = $response->getStatusCode();
			$redirected = $response->getHeader('X-Guzzle-Redirect-History') !== '';
		} catch (\Throwable $e) {
			$parts = explode('\\', get_class($e));
			$errorClass = mb_substr(end($parts), 0, 64);
			$this->logger->debug(
				'DashLink health check failed for link ' . $link->getId() . ': ' . $e->getMessage(),
				['app' => Application::APP_ID]
			);
		}

		[$status, $detail] = self::mapOutcome($statusCode, $redirected, $errorClass);
		$link->setHealthStatus($status);
		$link->setHealthDetail(mb_substr($detail, 0, 64));
		$link->setHealthCheckedAt(new \DateTime());
		$this->mapper->update($link);
	}

	public function checkAll(): int {
		$links = $this->mapper->findAllEnabledForHealthCheck();
		foreach ($links as $link) {
			$this->checkLink($link);
		}
		return count($links);
	}
}
