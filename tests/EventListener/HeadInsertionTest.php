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
 * The stylesheet goes to the end of the real head, not into a script or text
 * that happens to contain "</head>".
 */
final class HeadInsertionTest extends TestCase
{
    #[TestWith(['<html><head><title>T</title></head><body><p>x</p></body></html>', '<html><head><title>T</title>[S]</head><body><p>x</p></body></html>'])]
    #[TestWith(['<head></head><body><script>w.document.write("<html><head></head><body>");</script></body>', '<head>[S]</head><body><script>w.document.write("<html><head></head><body>");</script></body>'])]
    #[TestWith(['<head><script>var s = "</head><body>";</script><!-- </head> --></HEAD ><body>', '<head><script>var s = "</head><body>";</script><!-- </head> -->[S]</HEAD ><body>'])]
    #[TestWith(['<title>a </head> b</title><body>x</body>', '<title>a </head> b</title>[S]<body>x</body>'])]
    #[TestWith(['<p>no head</p>', '<p>no head</p>'])]
    public function testInsertsAtTheEndOfTheRealHead(string $html, string $expected): void
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

        $this->assertSame($expected, (new \ReflectionMethod($listener, 'insertInHead'))->invoke($listener, $html, '[S]'));
    }
}
