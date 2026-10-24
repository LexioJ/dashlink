<?php
declare(strict_types=1);

namespace OCA\DashLink\Search;

use OCA\DashLink\AppInfo\Application;
use OCA\DashLink\Service\LinkService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;
use Psr\Log\LoggerInterface;

class LinkSearchProvider implements IProvider {
	public function __construct(
		private LinkService $linkService,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10n->t('DashLink');
	}

	public function getOrder(string $route, array $routeParameters): int {
		return 50;
	}

	/**
	 * Case-insensitive substring match of the query against title, description, and URL.
	 *
	 * @param array[] $links jsonSerialize()-shaped link arrays
	 * @return array[]
	 */
	public static function filterLinks(array $links, string $term): array {
		$term = mb_strtolower(trim($term));
		if ($term === '') {
			return [];
		}
		return array_values(array_filter($links, function (array $link) use ($term) {
			$haystacks = [
				mb_strtolower($link['title'] ?? ''),
				mb_strtolower($link['description'] ?? ''),
				mb_strtolower($link['url'] ?? ''),
			];
			foreach ($haystacks as $haystack) {
				if ($haystack !== '' && str_contains($haystack, $term)) {
					return true;
				}
			}
			return false;
		}));
	}

	public function search(IUser $user, ISearchQuery $query): SearchResult {
		try {
			$links = $this->linkService->getLinksForCurrentUser();
			$matches = self::filterLinks($links, $query->getTerm());
		} catch (\Throwable $e) {
			$this->logger->debug('DashLink search failed: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			$matches = [];
		}

		$entries = array_map(function (array $link) {
			$iconUrl = $this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg');
			if (!empty($link['iconPath'])) {
				$route = $link['userId'] === null ? 'dashlink.link.getIcon' : 'dashlink.userLink.getIcon';
				$iconUrl = $this->urlGenerator->linkToRoute($route, ['id' => $link['id']]);
			}
			return new SearchResultEntry(
				$iconUrl,
				$link['title'],
				$link['description'] ?? $link['url'],
				$link['url'],
				'',
				false
			);
		}, $matches);

		return SearchResult::complete($this->getName(), $entries);
	}
}
