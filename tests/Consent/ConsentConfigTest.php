<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\Consent;

use JuheItSolutions\JuheConsentBundle\Consent\ConsentConfig;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ConsentConfigTest extends TestCase
{
    #[TestWith(['bar', 'bar'])]
    #[TestWith(['card', 'card'])]
    #[TestWith(['', 'card'])]
    #[TestWith([null, 'card'])]
    #[TestWith(['BAR', 'card'])]
    #[TestWith(['<script>', 'card'])]
    public function testLayoutFallsBackToTheCard(mixed $value, string $expected): void
    {
        $this->assertSame($expected, ConsentConfig::layout($value));
    }

    #[TestWith([8, false])]
    #[TestWith([9, false])]
    #[TestWith([10, true])]
    #[TestWith([0, true])]
    public function testTheLegalPagesAreNeverBlocked(int $pageId, bool $blocking): void
    {
        $banner = ['blocking' => true, 'legal_pages' => [8, 9]];

        $this->assertSame($blocking, ConsentConfig::forPage($banner, $pageId)['blocking']);
    }

    public function testANonBlockingBannerStaysNonBlocking(): void
    {
        $this->assertFalse(ConsentConfig::forPage(['blocking' => false, 'legal_pages' => [8]], 10)['blocking']);
        $this->assertFalse(ConsentConfig::forPage(['blocking' => false], 8)['blocking']);
    }

    public function testAnExcludedPageDoesNotOpenTheBannerAndNeverBlocks(): void
    {
        $banner = ['blocking' => true, 'quiet' => false, 'legal_pages' => [8], 'quiet_pages' => [12, 14]];

        $excluded = ConsentConfig::forPage($banner, 14);
        $this->assertTrue($excluded['quiet']);
        $this->assertFalse($excluded['blocking']);

        $ordinary = ConsentConfig::forPage($banner, 10);
        $this->assertFalse($ordinary['quiet']);
        $this->assertTrue($ordinary['blocking']);

        // The legal pages still ask by themselves, only without blocking
        $this->assertFalse(ConsentConfig::forPage($banner, 8)['quiet']);
    }

    #[TestWith([null, []])]
    #[TestWith(['', []])]
    #[TestWith(['a:3:{i:0;s:2:"12";i:1;i:4;i:2;s:2:"12";}', [12, 4]])]
    #[TestWith(['a:2:{i:0;s:1:"0";i:1;s:2:"-3";}', []])]
    #[TestWith(['O:8:"stdClass":0:{}', []])]
    #[TestWith([7, []])]
    public function testPageIdsKeepTheOrderAndDropJunk(mixed $value, array $expected): void
    {
        $this->assertSame($expected, ConsentConfig::pageIds($value));
    }
}
