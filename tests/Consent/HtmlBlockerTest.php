<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\Consent;

use JuheItSolutions\JuheConsentBundle\Consent\HtmlBlocker;
use PHPUnit\Framework\Attributes\TestWith;
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
        $this->assertSame([[['group' => 'media', 'key' => 's7', 'title' => 'YouTube', 'provider' => 'Google Ireland Limited', 'privacy_url' => 'https://policies.google.com/privacy', 'notice' => '', 'notice_template' => ''], $iframe]], $calls);
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

    public function testBlocksUnquotedUrlsAndExternalScriptsWithFallbackText(): void
    {
        $html = '<iframe src=https://www.youtube-nocookie.com/embed/abc></iframe><script src=https://www.googletagmanager.com/x.js>/* fallback */</script>';
        $result = (new HtmlBlocker())->block($html, $this->banner(), static fn (): string => 'P');

        $this->assertSame('P<script type="text/plain" data-juhe-consent-group="stats" data-juhe-consent-service="s8" data-juhe-consent-src="https://www.googletagmanager.com/x.js">/* fallback */</script>', $result);
    }

    public function testQuotedGreaterThanDoesNotEndTheOpeningTag(): void
    {
        $html = '<iframe title="a > b" src="https://www.youtube-nocookie.com/embed/abc"></iframe>';
        $this->assertSame('P', (new HtmlBlocker())->block($html, $this->banner(), static fn (): string => 'P'));
    }

    public function testMatchesBrowserNormalizedProviderUrls(): void
    {
        foreach ([
            "https://www.you\ntube-nocookie.com/embed/abc",
            'https://www.%79outube-nocookie.com/embed/abc',
            'https:\\\\www.youtube-nocookie.com\\embed\\abc',
        ] as $url) {
            $this->assertSame('P', (new HtmlBlocker())->block('<iframe src="'.$url.'"></iframe>', $this->banner(), static fn (): string => 'P'), $url);
        }
    }

    public function testDoesNotRewriteCommentsOrRawTextAsLiveElements(): void
    {
        $iframe = '<iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe>';
        $script = '<script src="https://www.googletagmanager.com/x.js"></script>';
        foreach (['<!--'.$iframe.$script.'-->', '<script>const markup = `'.$iframe.'`;</script>', '<textarea>'.$iframe.$script.'</textarea>'] as $html) {
            $this->assertSame($html, (new HtmlBlocker())->block($html, $this->banner(), static fn (): string => 'P'));
        }
    }

    public function testNeverBlocksEssentialServices(): void
    {
        $html = '<script src="https://cdn.essential.example/x.js"></script>';

        $this->assertSame($html, (new HtmlBlocker())->block($html, $this->banner(), static fn (): string => 'P'));
    }

    #[TestWith(['preconnect'])]
    #[TestWith(['dns-prefetch'])]
    #[TestWith(['preload'])]
    #[TestWith(['prefetch'])]
    #[TestWith(['modulepreload'])]
    #[TestWith(['stylesheet'])]
    #[TestWith(['Stylesheet Preload'])]
    public function testHoldsBackResourceLinksToABlockedHost(string $rel): void
    {
        $html = (new HtmlBlocker())->block('<link rel="'.$rel.'" href="https://www.googletagmanager.com/x?a=1&amp;b=2" crossorigin as="script">', $this->banner(), static fn (): string => 'P');

        $this->assertSame('<link data-juhe-consent-group="stats" data-juhe-consent-service="s8" data-juhe-consent-rel="'.strtolower($rel).'" data-juhe-consent-href="https://www.googletagmanager.com/x?a=1&amp;b=2" crossorigin as="script">', $html);
    }

    public function testKeepsPlainLinksOtherLinkTypesAndOtherHosts(): void
    {
        $html = '<a href="https://www.googletagmanager.com/">GTM</a><link rel="icon" href="https://www.googletagmanager.com/i.png"><link rel="canonical" href="https://googletagmanager.com/"><link rel="alternate" hreflang="en" href="https://googletagmanager.com/en"><link rel="stylesheet" href="https://example.org/a.css"><link rel="preconnect" href="https://cdn.essential.example"><link rel="stylesheet" href="/local.css">';

        $this->assertSame($html, (new HtmlBlocker())->block($html, $this->banner(), static fn (): string => 'P'));
    }

    public function testHoldsBackSelfClosingAndUnquotedLinksButNotInRawText(): void
    {
        $html = (new HtmlBlocker())->block('<link rel=preconnect href=//youtube-nocookie.com/ /><link rel="dns-prefetch" href="https://youtube-nocookie.com" media="print"/><!-- <link rel="preload" href="https://youtube-nocookie.com/x"> --><script>var l = \'<link rel="preload" href="https://youtube-nocookie.com/y">\';</script>', $this->banner(), static fn (): string => 'P');

        $this->assertSame('<link data-juhe-consent-group="media" data-juhe-consent-service="s7" data-juhe-consent-rel="preconnect" data-juhe-consent-href="//youtube-nocookie.com/"><link data-juhe-consent-group="media" data-juhe-consent-service="s7" data-juhe-consent-rel="dns-prefetch" data-juhe-consent-href="https://youtube-nocookie.com" media="print"><!-- <link rel="preload" href="https://youtube-nocookie.com/x"> --><script>var l = \'<link rel="preload" href="https://youtube-nocookie.com/y">\';</script>', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function banner(): array
    {
        return ['groups' => [
            ['alias' => 'essential', 'essential' => true, 'services' => [['key' => 's1', 'title' => 'CDN', 'hosts' => ['cdn.essential.example']]]],
            ['alias' => 'media', 'essential' => false, 'services' => [['key' => 's7', 'title' => 'YouTube', 'provider' => 'Google Ireland Limited', 'privacy_url' => 'https://policies.google.com/privacy', 'hosts' => ['youtube-nocookie.com']]]],
            ['alias' => 'stats', 'essential' => false, 'services' => [['key' => 's8', 'title' => 'Analytics', 'hosts' => ['googletagmanager.com']], ['key' => 's9', 'title' => 'Manual', 'hosts' => []]]],
        ]];
    }
}
