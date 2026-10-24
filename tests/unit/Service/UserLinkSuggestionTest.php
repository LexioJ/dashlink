<?php
declare(strict_types=1);

namespace OCA\DashLink\Tests\Unit\Service;

use OCA\DashLink\Db\Link;
use OCA\DashLink\Db\LinkMapper;
use OCA\DashLink\Service\UserLinkService;
use PHPUnit\Framework\TestCase;

class UserLinkSuggestionTest extends TestCase {
    public function testMarkAdoptedMergesFlag(): void {
        $s1 = new Link(); $s1->setId(10); $s1->setTitle('Wiki');
        $s2 = new Link(); $s2->setId(11); $s2->setTitle('Portal');

        $result = UserLinkService::markAdopted([$s1, $s2], [11]);

        $this->assertFalse($result[0]['adopted']);
        $this->assertTrue($result[1]['adopted']);
        $this->assertSame('Wiki', $result[0]['title']);
    }
}
