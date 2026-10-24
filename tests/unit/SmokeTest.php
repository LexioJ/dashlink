<?php
declare(strict_types=1);

namespace OCA\DashLink\Tests\Unit;

use OCA\DashLink\Db\Link;
use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase {
    public function testLinkEntityInstantiates(): void {
        $link = new Link();
        $link->setTitle('Example');
        $this->assertSame('Example', $link->getTitle());
    }
}
