<?php
declare(strict_types=1);

namespace OCA\DashLink\Tests\Unit\Search;

use OCA\DashLink\Search\LinkSearchProvider;
use PHPUnit\Framework\TestCase;

class LinkSearchProviderTest extends TestCase {
    private function link(string $title, string $url, ?string $description = null): array {
        return ['id' => 1, 'title' => $title, 'url' => $url, 'description' => $description,
                'iconPath' => null, 'userId' => null, 'target' => '_blank'];
    }

    public function testMatchesTitleCaseInsensitive(): void {
        $links = [$this->link('Company Wiki', 'https://wiki.example.com')];
        $this->assertCount(1, LinkSearchProvider::filterLinks($links, 'wiki'));
        $this->assertCount(1, LinkSearchProvider::filterLinks($links, 'COMPANY'));
    }

    public function testMatchesUrlAndDescription(): void {
        $links = [$this->link('Intranet', 'https://portal.example.com', 'HR portal')];
        $this->assertCount(1, LinkSearchProvider::filterLinks($links, 'portal.example'));
        $this->assertCount(1, LinkSearchProvider::filterLinks($links, 'hr port'));
    }

    public function testNoMatchReturnsEmpty(): void {
        $links = [$this->link('Intranet', 'https://portal.example.com')];
        $this->assertCount(0, LinkSearchProvider::filterLinks($links, 'zzz'));
    }
}
