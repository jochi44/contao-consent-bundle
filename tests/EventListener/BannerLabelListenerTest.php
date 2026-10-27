<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\EventListener;

use Doctrine\DBAL\Connection;
use JuheItSolutions\JuheConsentBundle\EventListener\BannerLabelListener;
use PHPUnit\Framework\TestCase;

final class BannerLabelListenerTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['TL_LANG']['tl_juhe_consent_banner'] = ['usedBy' => 'Used by:', 'usedByNone' => 'Not assigned to a website'];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_LANG']['tl_juhe_consent_banner']);
    }

    public function testNamesTheRootsAndTheirLanguage(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains("type = 'root' AND juheConsentBanner = ?"), [7])
            ->willReturn([['title' => 'Website DE', 'language' => 'de'], ['title' => 'Sito <IT>', 'language' => 'it']])
        ;

        $label = (new BannerLabelListener($connection))(['id' => '7'], 'Main');

        $this->assertSame('Main <span class="label-info">Used by: Website DE (de), Sito &lt;IT&gt; (it)</span>', $label);
    }

    public function testSaysWhenNoWebsiteUsesTheBanner(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $this->assertSame('Spare <span class="label-info">Not assigned to a website</span>', (new BannerLabelListener($connection))(['id' => 3], 'Spare'));
    }
}
