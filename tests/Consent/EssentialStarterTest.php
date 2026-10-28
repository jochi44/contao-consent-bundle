<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\Consent;

use Contao\CoreBundle\Cache\CacheTagManager;
use Doctrine\DBAL\Connection;
use JuheItSolutions\JuheConsentBundle\Consent\EssentialStarter;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class EssentialStarterTest extends TestCase
{
    #[TestWith(["juhe_consent_4: 1 Jahr", 4, true])]
    #[TestWith(["_ga: 2 years\njuhe_consent_*: 1 year", 4, true])]
    #[TestWith(["juhe_*", 4, true])]
    #[TestWith(["juhe_consent_40: 1 year", 4, false])]
    #[TestWith(["juhe_consent_4x", 4, false])]
    #[TestWith(["*", 4, false])]
    #[TestWith(["", 4, false])]
    public function testRecognisesTheStoredChoice(string $cookies, int $banner, bool $expected): void
    {
        $this->assertSame($expected, EssentialStarter::covers($cookies, $banner));
    }

    public function testClearsTheCachedPagesAfterCreatingRecords(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['id' => 4, 'lifetime' => 365]);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $callback): mixed => $callback($connection));
        $connection->method('fetchAllAssociative')->willReturn([]);
        $connection->method('fetchFirstColumn')->willReturn([]);
        $connection->method('fetchOne')->willReturn(0);
        $connection->method('lastInsertId')->willReturnOnConsecutiveCalls(10, 11);
        $connection->expects($this->exactly(2))->method('insert');

        $cache = $this->createMock(CacheTagManager::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['contao.db.tl_juhe_consent_group', 'contao.db.tl_juhe_consent_service']);

        $this->assertSame(['group' => 10, 'service' => 11], (new EssentialStarter($connection, $cache))->run(4, $this->texts()));
    }

    public function testClearsNothingWhenNothingIsMissing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['id' => 4, 'lifetime' => 365]);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $callback): mixed => $callback($connection));
        $connection->method('fetchAllAssociative')->willReturn([['id' => 2, 'alias' => 'essential', 'essential' => 1, 'sorting' => 128]]);
        $connection->method('fetchFirstColumn')->willReturn(['juhe_consent_4: 1 year']);
        $connection->expects($this->never())->method('insert');

        $cache = $this->createMock(CacheTagManager::class);
        $cache->expects($this->never())->method('invalidateTags');

        $this->assertSame(['group' => null, 'service' => null], (new EssentialStarter($connection, $cache))->run(4, $this->texts()));
    }

    public function testFindsAFreeAlias(): void
    {
        $this->assertSame('essential', EssentialStarter::alias('essential', ['notwendig']));
        $this->assertSame('essential-3', EssentialStarter::alias('essential', ['essential', 'essential-2']));
    }

    /**
     * @return array{group_title: string, group_description: string, service_title: string, service_provider: string, service_description: string, duration: string}
     */
    private function texts(): array
    {
        return ['group_title' => 'Essential', 'group_description' => '', 'service_title' => 'Choice', 'service_provider' => 'Site', 'service_description' => '', 'duration' => '%d days'];
    }
}
