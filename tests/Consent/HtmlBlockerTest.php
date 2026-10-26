<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\Consent;

use JuheItSolutions\JuheConsentBundle\Consent\HtmlBlocker;
use PHPUnit\Framework\TestCase;

final class HtmlBlockerTest extends TestCase
{
    public function testReplacesAnIframeWithThePlaceholder(): void
    {
        $iframe = '<iframe src="https://www.youtube-nocookie.com/embed/abc" title="Video"></iframe>';
        $calls = [];

        $html = (new HtmlBlocker())->block('<p>a</p>'.$iframe.'<p>b</p>', $this->banner(), static function (array $service, string $original) use (&$calls): string {
            $calls[] = [$service, $original];

            return '<div class="placeholder"></div>';
        });

        $this->assertSame('<p>a</p><div class="placeholder"></div><p>b</p>', $html);
        $this->assertSame([[['group' => 'media', 'key' => 's7', 'title' => 'YouTube'], $iframe]], $calls);
    }

    public function testMatchesSubdomainsAndProtocolRelativeUrls(): void
    {
        $html = (new HtmlBlocker())->block('<iframe src="//player.youtube-nocookie.com/x"></iframe><iframe src="https://notyoutube-nocookie.com/x"></iframe>', $this->banner(), static fn (): string => 'P');

        $this->assertSame('P<iframe src="https://notyoutube-nocookie.com/x"></iframe>', $html);
    }

    public function testHoldsBackScriptsAndKeepsTheirType(): void
    {
        $html = (new HtmlBlocker())->block('<script src="https://www.googletagmanager.com/gtag.js" type="module" async></script>', $this->banner(), static fn (): string => 'P');

        $this->assertSame('<script type="text/plain" data-juhe-consent-group="stats" data-juhe-consent-service="s8" data-juhe-consent-src="https://www.googletagmanager.com/gtag.js" data-juhe-consent-type="module" async></script>', $html);
    }

    public function testLeavesInlineScriptsAndOtherHostsAlone(): void
    {
        $html = '<script>var a = 1;</script><script src="/files/app.js"></script><iframe src="https://example.org/"></iframe>';

        $this->assertSame($html, (new HtmlBlocker())->block($html, $this->banner(), static fn (): string => 'P'));
    }

    public function testNeverBlocksEssentialServices(): void
    {
        $html = '<script src="https://cdn.essential.example/x.js"></script>';

        $this->assertSame($html, (new HtmlBlocker())->block($html, $this->banner(), static fn (): string => 'P'));
    }

    /**
     * @return array<string, mixed>
     */
    private function banner(): array
    {
        return ['groups' => [
            ['alias' => 'essential', 'essential' => true, 'services' => [['key' => 's1', 'title' => 'CDN', 'hosts' => ['cdn.essential.example']]]],
            ['alias' => 'media', 'essential' => false, 'services' => [['key' => 's7', 'title' => 'YouTube', 'hosts' => ['youtube-nocookie.com']]]],
            ['alias' => 'stats', 'essential' => false, 'services' => [['key' => 's8', 'title' => 'Analytics', 'hosts' => ['googletagmanager.com']], ['key' => 's9', 'title' => 'Manual', 'hosts' => []]]],
        ]];
    }
}
