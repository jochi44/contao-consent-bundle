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
}
