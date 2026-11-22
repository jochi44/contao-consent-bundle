<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\EventListener;

use Contao\CoreBundle\DataContainer\RecordLabel;
use Doctrine\DBAL\Connection;
use JuheItSolutions\JuheConsentBundle\EventListener\BannerLabelListener;
use JuheItSolutions\JuheConsentBundle\Tests\BundleTwig;
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

        $label = (new BannerLabelListener($connection, BundleTwig::create()))(['id' => '7'], 'Main');

        // Contao 6: the HTML label, not a string Contao would escape again
        $this->assertSame('Main <span class="label-info">Used by: Website DE (de), Sito &lt;IT&gt; (it)</span>', $label instanceof RecordLabel ? $label->htmlLabel : $label);
        $this->assertSame(class_exists(RecordLabel::class), $label instanceof RecordLabel);
    }

    public function testSaysWhenNoWebsiteUsesTheBanner(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $label = (new BannerLabelListener($connection, BundleTwig::create()))(['id' => 3], 'Spare');

        $this->assertSame('Spare <span class="label-info">Not assigned to a website</span>', $label instanceof RecordLabel ? $label->htmlLabel : $label);
    }
}
