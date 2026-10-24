<?php
declare(strict_types=1);

namespace OCA\DashLink\Tests\Unit\Service;

use OCA\DashLink\Db\Link;
use OCA\DashLink\Db\LinkMapper;
use OCA\DashLink\Service\IconService;
use OCA\DashLink\Service\LinkService;
use OCA\DashLink\Service\SecurityService;
use OCA\DashLink\Service\SettingsService;
use OCP\IGroupManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class LinkServiceSuggestionTest extends TestCase {
	private function makeService(LinkMapper $mapper): LinkService {
		return new LinkService(
			$mapper,
			$this->createMock(IUserSession::class),
			$this->createMock(IGroupManager::class),
			new SecurityService(),
			$this->createMock(SettingsService::class)
		);
	}

	public function testCreateLinkSetsIsSuggestionFlag(): void {
		$mapper = $this->createMock(LinkMapper::class);
		$mapper->method('insert')->willReturnArgument(0);

		$service = $this->makeService($mapper);

		$link = $service->createLink([
			'title' => 'Wiki',
			'url' => 'https://example.com',
			'isSuggestion' => true,
		]);

		$this->assertSame(1, $link->getIsSuggestion());
	}

	public function testCreateLinkDefaultsIsSuggestionToZero(): void {
		$mapper = $this->createMock(LinkMapper::class);
		$mapper->method('insert')->willReturnArgument(0);

		$service = $this->makeService($mapper);

		$link = $service->createLink([
			'title' => 'Wiki',
			'url' => 'https://example.com',
		]);

		$this->assertSame(0, $link->getIsSuggestion());
	}

	public function testUpdateLinkTogglesIsSuggestionFlag(): void {
		$existing = new Link();
		$existing->setId(5);
		$existing->setIsSuggestion(0);

		$mapper = $this->createMock(LinkMapper::class);
		$mapper->method('findById')->willReturn($existing);
		$mapper->method('update')->willReturnArgument(0);

		$service = $this->makeService($mapper);

		$updated = $service->updateLink(5, ['isSuggestion' => true]);

		$this->assertSame(1, $updated->getIsSuggestion());
	}

	public function testDuplicateLinkCarriesOverIsSuggestionFlag(): void {
		$source = new Link();
		$source->setId(7);
		$source->setTitle('Portal');
		$source->setUrl('https://example.com');
		$source->setGroups([]);
		$source->setPosition(0);
		$source->setEnabled(1);
		$source->setIsSuggestion(1);

		$mapper = $this->createMock(LinkMapper::class);
		$mapper->method('findById')->willReturn($source);
		$mapper->method('insert')->willReturnArgument(0);

		$service = $this->makeService($mapper);
		$iconService = $this->createMock(IconService::class);

		$duplicate = $service->duplicateLink(7, $iconService);

		$this->assertSame(1, $duplicate->getIsSuggestion());
	}
}
