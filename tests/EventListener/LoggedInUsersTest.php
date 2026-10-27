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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * "No optional services for logged-in users" applies to front end members and
 * back end users in the preview, and only to a response that it makes private:
 * the restricted page never ends up in a shared cache.
 */
final class LoggedInUsersTest extends TestCase
{
    #[TestWith([true, false, false, true])]
    #[TestWith([false, true, true, true])]
    #[TestWith([false, true, false, false])]
    #[TestWith([false, false, true, false])]
    #[TestWith([false, false, false, false])]
    public function testRestrictsMembersAndBackendUsersInThePreview(bool $member, bool $backendUser, bool $preview, bool $expected): void
    {
        $request = new Request();
        $request->attributes->set('_preview', $preview);
        $response = $this->publicResponse();

        $restricted = $this->restricted($this->tokenChecker($member, $backendUser), $request, $response, ['restrict_users' => true]);

        $this->assertSame($expected, $restricted);
        $this->assertSame($expected, $response->headers->hasCacheControlDirective('private'));
        $this->assertSame(!$expected, $response->isCacheable());
    }

    public function testLeavesEverybodyAloneWhenTheBannerDoesNotAsk(): void
    {
        $tokenChecker = $this->createMock(TokenChecker::class);
        $tokenChecker->expects($this->never())->method($this->anything());
        $response = $this->publicResponse();

        $this->assertFalse($this->restricted($tokenChecker, new Request(), $response, ['restrict_users' => false]));
        $this->assertTrue($response->isCacheable());
    }

    private function publicResponse(): Response
    {
        $response = new Response('<html><body></body></html>');
        $response->setSharedMaxAge(300);

        return $response;
    }

    private function tokenChecker(bool $member, bool $backendUser): TokenChecker
    {
        $tokenChecker = $this->createStub(TokenChecker::class);
        $tokenChecker->method('hasFrontendUser')->willReturn($member);
        $tokenChecker->method('hasBackendUser')->willReturn($backendUser);

        return $tokenChecker;
    }

    /**
     * @param array<string, mixed> $banner
     */
    private function restricted(TokenChecker $tokenChecker, Request $request, Response $response, array $banner): bool
    {
        $listener = new InjectConsentListener(
            $this->createStub(ScopeMatcher::class),
            (new \ReflectionClass(ConsentConfig::class))->newInstanceWithoutConstructor(),
            (new \ReflectionClass(HtmlBlocker::class))->newInstanceWithoutConstructor(),
            $this->createStub(Environment::class),
            $this->createStub(TranslatorInterface::class),
            $this->createStub(CacheTagManager::class),
            $this->createStub(UrlGeneratorInterface::class),
            $tokenChecker,
        );

        return (new \ReflectionMethod($listener, 'restricted'))->invoke($listener, $request, $response, $banner);
    }
}
