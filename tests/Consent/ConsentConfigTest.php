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
}
