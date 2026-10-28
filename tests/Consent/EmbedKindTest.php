<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\Consent;

use JuheItSolutions\JuheConsentBundle\Consent\EmbedKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmbedKindTest extends TestCase
{
    #[DataProvider('iframes')]
    public function testTakesTheKindFromTheIframeHost(string $iframe, string $kind): void
    {
        $this->assertSame($kind, EmbedKind::fromIframe($iframe));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function iframes(): iterable
    {
        yield 'youtube-nocookie' => ['<iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe>', EmbedKind::VIDEO];
        yield 'vimeo player, single quotes' => ["<iframe title='x' src='https://player.vimeo.com/video/1'></iframe>", EmbedKind::VIDEO];
        yield 'protocol relative' => ['<iframe src="//www.dailymotion.com/embed/video/x"></iframe>', EmbedKind::VIDEO];
        yield 'google maps embed' => ['<iframe src="https://www.google.com/maps/embed?pb=1&amp;q=a"></iframe>', EmbedKind::MAP];
        yield 'google.at maps' => ['<iframe src="https://www.google.at/maps?q=Lienz&amp;output=embed"></iframe>', EmbedKind::MAP];
        yield 'openstreetmap' => ['<iframe src="https://www.openstreetmap.org/export/embed.html?bbox=1"></iframe>', EmbedKind::MAP];
        yield 'bing maps' => ['<iframe src="https://www.bing.com/maps/embed?h=400"></iframe>', EmbedKind::MAP];
        yield 'spotify' => ['<iframe src="https://open.spotify.com/embed/episode/1"></iframe>', EmbedKind::AUDIO];
        yield 'soundcloud' => ['<iframe src="https://w.soundcloud.com/player/?url=x"></iframe>', EmbedKind::AUDIO];
        yield 'google, not maps' => ['<iframe src="https://calendar.google.com/calendar/embed"></iframe>', EmbedKind::EMBED];
        yield 'bing, not maps' => ['<iframe src="https://www.bing.com/search?q=a"></iframe>', EmbedKind::EMBED];
        yield 'look-alike host' => ['<iframe src="https://notyoutube.com/embed/abc"></iframe>', EmbedKind::EMBED];
        yield 'unknown host' => ['<iframe src="https://forms.example.org/f/1"></iframe>', EmbedKind::EMBED];
        yield 'no src' => ['<iframe srcdoc="<p>a</p>"></iframe>', EmbedKind::EMBED];
        yield 'extra slashes after https:' => ['<iframe src="https:///www.youtube.com/embed/abc"></iframe>', EmbedKind::VIDEO];
        yield 'backslashes, google maps' => ['<iframe src="https:\\\\www.google.com\\maps/embed?pb=1"></iframe>', EmbedKind::MAP];
        yield 'upper case' => ['<iframe src="HTTPS://Maps.Google.com/maps?q=a"></iframe>', EmbedKind::MAP];
        yield 'data-src only' => ['<iframe data-src="https://www.youtube.com/embed/a"></iframe>', EmbedKind::EMBED];
    }
}
