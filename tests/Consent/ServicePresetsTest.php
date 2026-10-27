<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\Consent;

use Contao\CoreBundle\Twig\Finder\FinderFactory;
use JuheItSolutions\JuheConsentBundle\Consent\ServicePresets;
use JuheItSolutions\JuheConsentBundle\Consent\ServiceTemplates;
use JuheItSolutions\JuheConsentBundle\EventListener\ServicePresetListener;
use JuheItSolutions\JuheConsentBundle\Tests\BundleTwig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ServicePresetsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, list<string>}>
     */
    public static function presets(): iterable
    {
        yield 'Google Analytics 4' => ['juhe_consent/service/google_analytics', 'G-AB12CD34', '', ['https://www.googletagmanager.com/gtag/js?id=G-AB12CD34', "gtag('config', 'G\\u002DAB12CD34');"]];
        yield 'Google Tag Manager' => ['juhe_consent/service/google_tag_manager', 'GTM-AB12CD', '', ['https://www.googletagmanager.com/gtm.js?id=GTM-AB12CD', "'gtm.start'"]];
        yield 'Matomo' => ['juhe_consent/service/matomo', '3', 'https://stats.example.org/matomo', ['src="https://stats.example.org/matomo/matomo.js"', "'setSiteId', '3'", "matomo.php"]];
        yield 'Matomo Tag Manager' => ['juhe_consent/service/matomo_tag_manager', 'aBcD1234', 'https://stats.example.org/', ['src="https://stats.example.org/js/container_aBcD1234.js"', "'mtm.Start'"]];
        yield 'Meta Pixel' => ['juhe_consent/service/meta_pixel', '123456789012345', '', ['https://connect.facebook.net/en_US/fbevents.js', "fbq('init', '123456789012345')"]];
        yield 'etracker' => ['juhe_consent/service/etracker', 'aBc123', '', ['https://code.etracker.com/code/e.js', 'data-secure-code="aBc123"']];
    }

    #[DataProvider('presets')]
    public function testEachPresetRendersItsSnippet(string $template, string $id, string $url, array $expected): void
    {
        $this->assertTrue(ServicePresets::valid($template, $id, $url));

        $templates = new ServiceTemplates($this->createStub(FinderFactory::class), BundleTwig::create());
        $html = $templates->render($template, ['service' => ['key' => 's1', 'title' => 'T', 'id' => $id, 'url' => ServicePresets::baseUrl($url) ?? '']]);

        foreach ($expected as $part) {
            $this->assertStringContainsString($part, $html);
        }

        // Nothing that would load without script (and so without consent), no comment left over
        $this->assertStringNotContainsStringIgnoringCase('<noscript', $html);
        $this->assertStringNotContainsString('{#', $html);
        $this->assertGreaterThan(0, preg_match_all('/<script\b/', $html));
    }

    #[TestWith(['juhe_consent/service/google_analytics', 'UA-1234-1', ''])]
    #[TestWith(['juhe_consent/service/google_analytics', "G-ABC');alert(1)//", ''])]
    #[TestWith(['juhe_consent/service/google_analytics', '', ''])]
    #[TestWith(['juhe_consent/service/google_tag_manager', 'GTM-<x>', ''])]
    #[TestWith(['juhe_consent/service/matomo', '1', ''])]
    #[TestWith(['juhe_consent/service/matomo', '1', 'javascript:alert(1)'])]
    #[TestWith(['juhe_consent/service/matomo', '1', 'https://stats.example.org/?a=1'])]
    #[TestWith(['juhe_consent/service/matomo', '1', 'https://user@stats.example.org/'])]
    #[TestWith(['juhe_consent/service/matomo', '1', 'https://stats.example.org/"onload="x'])]
    #[TestWith(['juhe_consent/service/matomo', 'x', 'https://stats.example.org/'])]
    #[TestWith(['juhe_consent/service/matomo_tag_manager', 'a/b', 'https://stats.example.org/'])]
    #[TestWith(['juhe_consent/service/meta_pixel', '12ab', ''])]
    #[TestWith(['juhe_consent/service/etracker', 'a b', ''])]
    #[TestWith(['juhe_consent/service/agency', '<x>', ''])]
    public function testRefusesIdentifiersAndAddressesThatDoNotFit(string $template, string $id, string $url): void
    {
        $this->assertFalse(ServicePresets::valid($template, $id, $url));
    }

    public function testAWebsitesOwnTemplateMayGoWithoutIdentifier(): void
    {
        $this->assertTrue(ServicePresets::valid('juhe_consent/service/agency', '', ''));
        $this->assertTrue(ServicePresets::valid('juhe_consent/service/agency', 'abc-1.2', ''));
    }

    public function testEveryPresetHasItsTemplate(): void
    {
        foreach (array_keys(ServicePresets::PRESETS) as $template) {
            $this->assertFileExists(__DIR__.'/../../contao/templates/'.$template.'.html.twig');
        }
    }

    public function testFillsOnlyEmptyFields(): void
    {
        $durations = ['years2' => '2 Jahre'];
        $row = ['serviceTemplate' => 'juhe_consent/service/google_analytics', 'serviceUrl' => '', 'hosts' => '', 'provider' => 'Own text', 'privacyUrl' => '', 'cookies' => ''];

        $this->assertSame([
            'hosts' => "googletagmanager.com\ngoogle-analytics.com\nanalytics.google.com",
            'privacyUrl' => 'https://policies.google.com/privacy',
            'cookies' => "_ga: 2 Jahre\n_ga_*: 2 Jahre",
        ], ServicePresetListener::prefilled($row, $durations));

        $this->assertSame([], ServicePresetListener::prefilled(['serviceTemplate' => 'juhe_consent/service/agency'] + $row, $durations));
    }

    public function testASelfHostedMatomoBlocksItsHostUnlessItIsTheWebsite(): void
    {
        $row = ['serviceTemplate' => 'juhe_consent/service/matomo', 'serviceUrl' => 'https://www.stats.example.org/matomo/', 'hosts' => '', 'provider' => '', 'privacyUrl' => '', 'cookies' => 'x'];

        $this->assertSame(['hosts' => 'stats.example.org'], ServicePresetListener::prefilled($row, [], 'www.example.org'));
        $this->assertSame([], ServicePresetListener::prefilled($row, [], 'stats.example.org'));
    }

    #[TestWith(['https://stats.example.org', 'https://stats.example.org/'])]
    #[TestWith(['https://stats.example.org/matomo//', 'https://stats.example.org/matomo/'])]
    #[TestWith(['ftp://stats.example.org/', null])]
    #[TestWith(['//stats.example.org/', null])]
    #[TestWith(['https://stats.example.org/#x', null])]
    public function testBaseUrl(string $url, string|null $expected): void
    {
        $this->assertSame($expected, ServicePresets::baseUrl($url));
    }
}
