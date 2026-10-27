<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\Consent;

use Contao\CoreBundle\Twig\Finder\FinderFactory;
use JuheItSolutions\JuheConsentBundle\Consent\ConsentNotices;
use JuheItSolutions\JuheConsentBundle\Consent\ServiceTemplates;
use JuheItSolutions\JuheConsentBundle\Tests\BundleTwig;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ServiceTemplatesTest extends TestCase
{
    public function testRendersAListedTemplateAndEscapesOrdinaryValues(): void
    {
        $html = $this->templates()->render('juhe_consent/service/echo', ['service' => ['title' => 'A <b>"x"</b>']]);

        $this->assertStringContainsString('<p data-title="A &lt;b&gt;&quot;x&quot;&lt;/b&gt;">A &lt;b&gt;&quot;x&quot;&lt;/b&gt;</p>', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    #[TestWith(['juhe_consent/service/missing'])]
    #[TestWith(['juhe_consent/service/_partial'])]
    #[TestWith(['juhe_consent/service/../placeholder'])]
    #[TestWith(['juhe_consent/service/./echo'])]
    #[TestWith(['juhe_consent/placeholder'])]
    #[TestWith(['juhe_consent/service'])]
    #[TestWith(['/etc/passwd'])]
    #[TestWith(['@Contao/juhe_consent/service/echo'])]
    #[TestWith(['juhe_consent/service/echo.html.twig'])]
    #[TestWith([''])]
    public function testRefusesEverythingElse(string $name): void
    {
        $this->assertSame('', $this->templates()->render($name, ['service' => ['title' => 'x']]));
    }

    #[TestWith(['juhe_consent/service/google_analytics', true])]
    #[TestWith(['juhe_consent/service/agency/custom-1', true])]
    #[TestWith(['juhe_consent/service/', false])]
    #[TestWith(['juhe_consent/service//x', false])]
    #[TestWith(['juhe_consent/service/x/../y', false])]
    public function testNames(string $name, bool $valid): void
    {
        $this->assertSame($valid, ServiceTemplates::isName($name));
    }

    #[TestWith(['juhe_consent/placeholder/compact', '@Contao/juhe_consent/placeholder/compact.html.twig'])]
    #[TestWith(['juhe_consent/placeholder/missing', '@Contao/juhe_consent/placeholder.html.twig'])]
    #[TestWith(['juhe_consent/placeholder/../banner', '@Contao/juhe_consent/placeholder.html.twig'])]
    #[TestWith(['juhe_consent/service/echo', '@Contao/juhe_consent/placeholder.html.twig'])]
    #[TestWith(['', '@Contao/juhe_consent/placeholder.html.twig'])]
    public function testANoticeTemplateIsAPlaceholderVariantOrTheDefault(string $name, string $expected): void
    {
        $this->assertSame($expected, ConsentNotices::template($name, BundleTwig::create(__DIR__.'/../Fixtures/templates')->getLoader()));
    }

    private function templates(): ServiceTemplates
    {
        return new ServiceTemplates($this->createStub(FinderFactory::class), BundleTwig::create(__DIR__.'/../Fixtures/templates'));
    }
}
