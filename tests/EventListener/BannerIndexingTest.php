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
 * The banner is the same on every page, so the search index skips it without
 * changing whether the page's own end is indexed.
 */
final class BannerIndexingTest extends TestCase
{
    #[TestWith(['<body><p>x</p></body>', '<body><p>x</p><!-- indexer::stop -->[B]<!-- indexer::continue --></body>'])]
    #[TestWith(['<body><!-- indexer::stop --><p>x</p><!-- indexer::continue --></body>', '<body><!-- indexer::stop --><p>x</p><!-- indexer::continue --><!-- indexer::stop -->[B]<!-- indexer::continue --></body>'])]
    #[TestWith(['<body><p>x</p><!-- indexer::stop --><nav>n</nav></body>', '<body><p>x</p><!-- indexer::stop --><nav>n</nav><!-- indexer::stop -->[B]</body>'])]
    #[TestWith(['<p>no body</p>', '<p>no body</p>'])]
    public function testTheBannerIsKeptOutOfTheSearchIndex(string $html, string $expected): void
    {
        $listener = new InjectConsentListener(
            $this->createStub(ScopeMatcher::class),
            (new \ReflectionClass(ConsentConfig::class))->newInstanceWithoutConstructor(),
            (new \ReflectionClass(HtmlBlocker::class))->newInstanceWithoutConstructor(),
            $this->createStub(Environment::class),
            $this->createStub(TranslatorInterface::class),
            $this->createStub(CacheTagManager::class),
            $this->createStub(UrlGeneratorInterface::class),
            $this->createStub(TokenChecker::class),
        );

        $this->assertSame($expected, (new \ReflectionMethod($listener, 'insertBanner'))->invoke($listener, $html, '[B]'));
    }
}
