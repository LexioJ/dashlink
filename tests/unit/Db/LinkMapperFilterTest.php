<?php
declare(strict_types=1);

namespace OCA\DashLink\Tests\Unit\Db;

use OCA\DashLink\Db\Link;
use OCA\DashLink\Db\LinkMapper;
use PHPUnit\Framework\TestCase;

class LinkMapperFilterTest extends TestCase {
	private function makeLink(array $groups): Link {
		$link = new Link();
		$link->setGroups($groups);
		return $link;
	}

	public function testUnrestrictedLinkVisibleToAll(): void {
		$links = [$this->makeLink([])];
		$this->assertCount(1, LinkMapper::filterByGroups($links, []));
		$this->assertCount(1, LinkMapper::filterByGroups($links, ['staff']));
	}

	public function testRestrictedLinkOnlyVisibleToMembers(): void {
		$links = [$this->makeLink(['staff', 'it.team'])];
		$this->assertCount(0, LinkMapper::filterByGroups($links, []));
		$this->assertCount(0, LinkMapper::filterByGroups($links, ['sales']));
		$this->assertCount(1, LinkMapper::filterByGroups($links, ['it.team']));
	}
}
