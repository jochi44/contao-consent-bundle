<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\Consent;

use JuheItSolutions\JuheConsentBundle\Consent\EssentialStarter;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class EssentialStarterTest extends TestCase
{
    #[TestWith(["juhe_consent_4: 1 Jahr", 4, true])]
    #[TestWith(["_ga: 2 years\njuhe_consent_*: 1 year", 4, true])]
    #[TestWith(["juhe_*", 4, true])]
    #[TestWith(["juhe_consent_40: 1 year", 4, false])]
    #[TestWith(["juhe_consent_4x", 4, false])]
    #[TestWith(["*", 4, false])]
    #[TestWith(["", 4, false])]
    public function testRecognisesTheStoredChoice(string $cookies, int $banner, bool $expected): void
    {
        $this->assertSame($expected, EssentialStarter::covers($cookies, $banner));
    }

    public function testFindsAFreeAlias(): void
    {
        $this->assertSame('essential', EssentialStarter::alias('essential', ['notwendig']));
        $this->assertSame('essential-3', EssentialStarter::alias('essential', ['essential', 'essential-2']));
    }
}
