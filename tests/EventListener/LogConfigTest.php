<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\EventListener;

use Contao\CoreBundle\Cache\CacheTagManager;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use JuheItSolutions\JuheConsentBundle\Consent\ConsentConfig;
use JuheItSolutions\JuheConsentBundle\Consent\HtmlBlocker;
use JuheItSolutions\JuheConsentBundle\EventListener\InjectConsentListener;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Without logging the script gets no log address, so the browser posts nothing.
 */
final class LogConfigTest extends TestCase
{
    #[TestWith([true, 'path', '/_juhe_consent/log'])]
    #[TestWith([true, 'none', '/_juhe_consent/log'])]
    #[TestWith([false, 'path', null])]
    public function testTheScriptGetsTheLogAddressOnlyWithLogging(bool $log, string $page, string|null $url): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/_juhe_consent/log');

        $listener = new InjectConsentListener(
            $this->createStub(ScopeMatcher::class),
            (new \ReflectionClass(ConsentConfig::class))->newInstanceWithoutConstructor(),
            (new \ReflectionClass(HtmlBlocker::class))->newInstanceWithoutConstructor(),
            $this->createStub(Environment::class),
            $this->createStub(TranslatorInterface::class),
            $this->createStub(CacheTagManager::class),
            $urlGenerator,
            $this->createStub(TokenChecker::class),
        );

        $banner = ['id' => 1, 'version' => 1, 'ask' => true, 'layout' => 'card', 'blocking' => false, 'groups' => [], 'log' => $log, 'log_page' => $page];
        $config = (new \ReflectionMethod($listener, 'scriptConfig'))->invoke($listener, $banner);

        $this->assertSame($url, $config['logUrl']);
        $this->assertSame($page, $config['logPage']);
    }
}
