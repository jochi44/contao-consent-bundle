<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\Controller;

use Doctrine\DBAL\Connection;
use JuheItSolutions\JuheConsentBundle\Controller\ConsentLogController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class ConsentLogControllerTest extends TestCase
{
    private const ID = '0f8e7d6c-5b4a-4321-8abc-0123456789ab';

    public function testStoresAValidChoice(): void
    {
        $connection = $this->connection();
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with('tl_juhe_consent_log', $this->callback(static fn (array $row): bool => 1 === $row['pid'] && 3 === $row['version'] && self::ID === $row['consentId'] && 'essential,media' === $row['groups'] && 's7' === $row['services'] && '/kontakt' === $row['url'] && \is_int($row['tstamp'])))
        ;

        $response = $this->controller($connection)($this->request(['services' => ['s7'], 'url' => '/kontakt']));

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testStoresInsertTagLikeUrlsVerbatim(): void
    {
        $connection = $this->connection();
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with('tl_juhe_consent_log', $this->callback(static fn (array $row): bool => '/{{date}}' === $row['url']))
        ;

        $this->assertSame(204, $this->controller($connection)($this->request(['url' => '/{{date}}']))->getStatusCode());
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidProvider')]
    public function testRejectsInvalidChoices(array $override): void
    {
        $connection = $this->connection();
        $connection->expects($this->never())->method('insert');

        $this->assertSame(400, $this->controller($connection)($this->request($override))->getStatusCode());
    }

    public static function invalidProvider(): iterable
    {
        yield 'old version' => [['version' => 2]];
        yield 'unknown banner' => [['banner' => 9]];
        yield 'banner as string' => [['banner' => '1']];
        yield 'unknown group' => [['groups' => ['essential', 'evil']]];
        yield 'unknown service' => [['services' => ['s99']]];
        yield 'groups as map' => [['groups' => ['a' => 'essential']]];
        yield 'id not a v4 UUID' => [['id' => 'not-a-uuid']];
        yield 'id uppercase' => [['id' => strtoupper(self::ID)]];
        yield 'url to another host' => [['url' => '//evil.example/']];
        yield 'url absolute' => [['url' => 'https://evil.example/']];
        yield 'url too long' => [['url' => '/'.str_repeat('a', 255)]];
    }

    public function testStoresALargeChoiceThatFitsTheBodyLimit(): void
    {
        // More than 255 bytes of group keys: the text columns take them
        $groups = array_map(static fn (int $i): string => str_repeat('a', 30).$i, range(1, 40));
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['version' => 3, 'logConsent' => '1', 'logPage' => 'path']);
        $connection->method('fetchAllKeyValue')->willReturn(array_combine($groups, range(1, 40)));
        $connection->method('fetchFirstColumn')->willReturn([]);
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with('tl_juhe_consent_log', $this->callback(static fn (array $row): bool => implode(',', $groups) === $row['groups'] && \strlen($row['groups']) > 255))
        ;

        $this->assertSame(204, $this->controller($connection)($this->request(['groups' => $groups]))->getStatusCode());
    }

    public function testRefusesAChoiceForABannerWithoutLogging(): void
    {
        $connection = $this->connection(['logConsent' => '0']);
        $connection->expects($this->never())->method('insert');

        $this->assertSame(403, $this->controller($connection)($this->request([]))->getStatusCode());
    }

    #[TestWith(['/kontakt?token=secret', '/kontakt'])]
    #[TestWith(['/kontakt#top', '/kontakt'])]
    #[TestWith(['/a?b#c', '/a'])]
    #[TestWith(['/', '/'])]
    public function testStoresThePathWithoutQueryAndFragment(string $url, string $stored): void
    {
        $connection = $this->connection();
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with('tl_juhe_consent_log', $this->callback(static fn (array $row): bool => $stored === $row['url']))
        ;

        $this->assertSame(204, $this->controller($connection)($this->request(['url' => $url]))->getStatusCode());
    }

    /**
     * @param array<string, mixed> $override
     */
    #[TestWith([['url' => '/konto/12345']])]
    #[TestWith([['url' => 'https://evil.example/']])]
    #[TestWith([['url' => null]])]
    public function testStoresNoPageIfTheBannerKeepsNone(array $override): void
    {
        $connection = $this->connection(['logPage' => 'none']);
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with('tl_juhe_consent_log', $this->callback(static fn (array $row): bool => '' === $row['url'] && self::ID === $row['consentId']))
        ;

        $request = $this->request($override);

        if (null === $override['url']) {
            $data = json_decode($request->getContent(), true);
            unset($data['url']);
            $request = Request::create('/_juhe_consent/log', 'POST', [], [], [], [], json_encode($data, JSON_THROW_ON_ERROR));
        }

        $this->assertSame(204, $this->controller($connection)($request)->getStatusCode());
    }

    public function testRejectsBodiesThatAreNotJson(): void
    {
        $connection = $this->connection();
        $connection->expects($this->never())->method('insert');

        $request = Request::create('/_juhe_consent/log', 'POST', [], [], [], [], '{"banner":');

        $this->assertSame(400, $this->controller($connection)($request)->getStatusCode());
    }

    public function testRejectsLargeBodies(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('insert');

        $request = Request::create('/_juhe_consent/log', 'POST', [], [], [], [], str_repeat(' ', 4097));

        $this->assertSame(413, $this->controller($connection)($request)->getStatusCode());
    }

    public function testRejectsWhenTheRateLimitIsReached(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('insert');

        $this->assertSame(429, $this->controller($connection, false)($this->request([]))->getStatusCode());
    }

    /**
     * @param array<string, string> $banner
     */
    private function connection(array $banner = []): Connection&\PHPUnit\Framework\MockObject\MockObject
    {
        $banner += ['version' => '3', 'logConsent' => '1', 'logPage' => 'path'];
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturnCallback(static fn (string $sql, array $params): array|false => 1 === $params[0] ? $banner : false);
        $connection->method('fetchAllKeyValue')->willReturn(['essential' => 1, 'media' => 2]);
        $connection->method('fetchFirstColumn')->willReturn([7, 8]);

        return $connection;
    }

    private function controller(Connection $connection, bool $accepted = true): ConsentLogController
    {
        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn(new RateLimit($accepted ? 9 : 0, new \DateTimeImmutable(), $accepted, 10));

        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        return new ConsentLogController($connection, $factory);
    }

    /**
     * @param array<string, mixed> $override
     */
    private function request(array $override): Request
    {
        $data = $override + ['banner' => 1, 'version' => 3, 'id' => self::ID, 'groups' => ['essential', 'media'], 'services' => [], 'url' => '/'];

        return Request::create('/_juhe_consent/log', 'POST', [], [], [], [], json_encode($data, JSON_THROW_ON_ERROR));
    }
}
