<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\Controller;

use Doctrine\DBAL\Connection;
use JuheItSolutions\JuheConsentBundle\Consent\ConsentLogExport;
use JuheItSolutions\JuheConsentBundle\Controller\ConsentLogExportController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ConsentLogExportControllerTest extends TestCase
{
    public function testIsABackendRouteForGetRequests(): void
    {
        $route = (new \ReflectionClass(ConsentLogExportController::class))->getAttributes(Route::class)[0]->newInstance();

        $this->assertSame(['GET'], $route->methods);
        $this->assertSame('backend', $route->defaults['_scope']);
        $this->assertArrayNotHasKey('_token_check', $route->defaults);
        $this->assertStringStartsWith('/contao/', $route->path);
    }

    public function testDeniesUsersWithoutAccessToTheLogModule(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->once())->method('isGranted')->with('contao_user.modules', 'juhe_consent_log')->willReturn(false);
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method($this->anything());

        $this->expectException(AccessDeniedException::class);

        (new ConsentLogExportController($authorizationChecker, new ConsentLogExport($connection)))($this->request([]));
    }

    public function testStreamsACsvDownloadWithTheListFilter(): void
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('WHERE l.pid = ? AND l.id < ?'), ['3', PHP_INT_MAX])
            ->willReturn([])
        ;

        $response = (new ConsentLogExportController($authorizationChecker, new ConsentLogExport($connection)))($this->request(['filter' => ['tl_juhe_consent_log' => ['pid' => '3']]]));

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertMatchesRegularExpression('/^attachment; filename=consent-log-\d{4}-\d{2}-\d{2}\.csv$/', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));

        ob_start();
        $response->sendContent();
        $this->assertStringStartsWith("\u{FEFF}id,time,", (string) ob_get_clean());
    }

    /**
     * @param array<mixed> $backendSession
     */
    private function request(array $backendSession): Request
    {
        $bag = new AttributeBag('_contao_be_attributes');
        $bag->setName('contao_backend');
        $session = new Session(new MockArraySessionStorage());
        $session->registerBag($bag);
        $session->start();
        $bag->replace($backendSession);

        $request = Request::create('/contao/juhe-consent/log-export');
        $request->setSession($session);

        return $request;
    }
}
