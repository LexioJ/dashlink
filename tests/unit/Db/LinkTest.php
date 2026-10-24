<?php
declare(strict_types=1);

namespace OCA\DashLink\Tests\Unit\Db;

use OCA\DashLink\Db\Link;
use PHPUnit\Framework\TestCase;

class LinkTest extends TestCase {
	public function testNewFieldsDefaultsAndJson(): void {
		$link = new Link();
		$this->assertSame('unknown', $link->getHealthStatus());
		$this->assertSame(0, $link->getIsSuggestion());
		$this->assertNull($link->getSuggestedFrom());

		$json = $link->jsonSerialize();
		$this->assertSame('unknown', $json['healthStatus']);
		$this->assertNull($json['healthCheckedAt']);
		$this->assertNull($json['healthDetail']);
		$this->assertFalse($json['isSuggestion']);
		$this->assertNull($json['suggestedFrom']);
	}

	public function testSettersRoundTrip(): void {
		$link = new Link();
		$link->setHealthStatus('broken');
		$link->setHealthDetail('404');
		$checked = new \DateTime('2026-10-24T10:00:00+00:00');
		$link->setHealthCheckedAt($checked);
		$link->setIsSuggestion(1);
		$link->setSuggestedFrom(42);

		$json = $link->jsonSerialize();
		$this->assertSame('broken', $json['healthStatus']);
		$this->assertSame('404', $json['healthDetail']);
		$this->assertSame($checked->format(\DateTime::ATOM), $json['healthCheckedAt']);
		$this->assertTrue($json['isSuggestion']);
		$this->assertSame(42, $json['suggestedFrom']);
	}
}
