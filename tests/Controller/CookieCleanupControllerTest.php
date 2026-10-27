<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\Controller;

use Doctrine\DBAL\Connection;
use JuheItSolutions\JuheConsentBundle\Controller\CookieCleanupController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class CookieCleanupControllerTest extends TestCase
{
    private const COOKIES = '_ga=GA1; _ga_ABC=GS1; _fbp=fb; PHPSESSID=s; _pk_id=1; other=x';

    public function testExpiresOnlyConfiguredNamesTheRequestCarries(): void
    {
        $response = $this->call(['services' => ['s7']]);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame(['_ga', '_ga_ABC'], $this->names($response));
        // Root path only, host-only and the parent domain
        $this->assertSame([['/', null], ['/', 'www.example.org'], ['/', 'example.org']], $this->places($response, '_ga'));

        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertTrue($cookie->isCleared());
            $this->assertTrue($cookie->isHttpOnly());
            $this->assertSame('', (string) $cookie->getValue());
        }
    }

    public function testWithoutServicesAllOptionalServicesAreCleaned(): void
    {
        $this->assertSame(['_ga', '_ga_ABC', '_fbp', '_pk_id'], $this->names($this->call([])));
    }

    public function testCookiesOfEssentialServicesStay(): void
    {
        // s8 lists "_pk*" and "PHPSESSID"; the essential service lists PHPSESSID too
        $this->assertSame(['_pk_id'], $this->names($this->call(['services' => ['s8']])));
    }

    public function testThePagePathAndItsParents(): void
    {
        $paths = array_unique(array_column($this->places($this->call(['services' => ['s9'], 'path' => '/shop/cart']), '_fbp'), 0));

        $this->assertSame(['/', '/shop', '/shop/', '/shop/cart', '/shop/cart/'], array_values($paths));
    }

    public function testSecureOnHttps(): void
    {
        $response = $this->call(['services' => ['s9']], 'https://www.example.org/_juhe_consent/cookies');

        $this->assertTrue($response->headers->getCookies()[0]->isSecure());
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidProvider')]
    public function testRejectsInvalidRequests(array $data, int $status): void
    {
        $response = $this->call($data);

        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame([], $response->headers->getCookies());
    }

    public static function invalidProvider(): iterable
    {
        yield 'unknown banner' => [['banner' => 99], 400];
        yield 'banner as string' => [['banner' => '1'], 400];
        yield 'essential service' => [['services' => ['s1']], 400];
        yield 'unknown service' => [['services' => ['s99']], 400];
        yield 'service list as object' => [['services' => ['a' => 's7']], 400];
        yield 'cookie name instead of a key' => [['services' => ['_ga']], 400];
        yield 'relative path' => [['path' => 'shop'], 400];
        yield 'protocol-relative path' => [['path' => '//evil.example'], 400];
        yield 'path with a semicolon' => [['path' => '/a;domain=x'], 400];
        yield 'path too long' => [['path' => '/'.str_repeat('a', 255)], 400];
    }

    public function testRejectsOtherContentTypes(): void
    {
        $request = Request::create('http://www.example.org/_juhe_consent/cookies', 'POST', [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_COOKIE' => self::COOKIES], '{"banner":1}');

        $this->assertSame(415, $this->controller()($request)->getStatusCode());
    }

    public function testRejectsLargeBodiesAndJunk(): void
    {
        $this->assertSame(413, $this->controller()($this->request(str_repeat(' ', 4097)))->getStatusCode());
        $this->assertSame(400, $this->controller()($this->request('{"banner":'))->getStatusCode());
    }

    public function testRejectsWhenTheRateLimitIsReached(): void
    {
        $this->assertSame(429, $this->controller(false)($this->request('{"banner":1}'))->getStatusCode());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function call(array $data, string $url = 'http://www.example.org/_juhe_consent/cookies'): Response
    {
        return $this->controller()($this->request(json_encode($data + ['banner' => 1], JSON_THROW_ON_ERROR), $url));
    }

    private function request(string $body, string $url = 'http://www.example.org/_juhe_consent/cookies'): Request
    {
        return Request::create($url, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_COOKIE' => self::COOKIES], $body);
    }

    private function controller(bool $accepted = true): CookieCleanupController
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(static fn (string $sql, array $params): array => 1 === $params[0] ? [
            ['id' => 1, 'cookies' => "PHPSESSID: Session\njuhe_consent_1: 1 year", 'essential' => 1],
            ['id' => 7, 'cookies' => "_ga*: 2 years\n_gid: 1 day", 'essential' => 0],
            ['id' => 8, 'cookies' => "_pk*: 13 months\nPHPSESSID", 'essential' => 0],
            ['id' => 9, 'cookies' => '_fbp: 3 months', 'essential' => 0],
        ] : []);

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn(new RateLimit($accepted ? 9 : 0, new \DateTimeImmutable(), $accepted, 10));

        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        return new CookieCleanupController($connection, $factory);
    }

    /**
     * @return list<string>
     */
    private function names(Response $response): array
    {
        return array_values(array_unique(array_map(static fn (Cookie $cookie): string => $cookie->getName(), $response->headers->getCookies())));
    }

    /**
     * @return list<array{0: string, 1: string|null}>
     */
    private function places(Response $response, string $name): array
    {
        $cookies = array_filter($response->headers->getCookies(), static fn (Cookie $cookie): bool => $cookie->getName() === $name);

        return array_values(array_map(static fn (Cookie $cookie): array => [$cookie->getPath(), $cookie->getDomain()], $cookies));
    }
}
