<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\Cron;

use Doctrine\DBAL\Connection;
use JuheItSolutions\JuheConsentBundle\Cron\PurgeConsentLogCron;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class PurgeConsentLogCronTest extends TestCase
{
    // 2026-10-27 12:00:00 UTC
    private const NOW = 1793102400;

    #[TestWith([6, '2026-04-27 12:00:00'])]
    #[TestWith([12, '2025-10-27 12:00:00'])]
    #[TestWith([24, '2024-10-27 12:00:00'])]
    #[TestWith([36, '2023-10-27 12:00:00'])]
    public function testTheCutoffIsTheSameMomentMonthsBack(int $months, string $expected): void
    {
        $this->assertSame($expected, gmdate('Y-m-d H:i:s', PurgeConsentLogCron::cutoff(self::NOW, $months)));
    }

    public function testDeletesPerBannerRetentionWithTheDefaultForTheRest(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, 'LEFT JOIN tl_juhe_consent_banner b ON b.id = l.pid')
                    && str_contains($sql, 'l.tstamp < CASE b.logRetention WHEN 6 THEN ? WHEN 12 THEN ? WHEN 24 THEN ? WHEN 36 THEN ? ELSE ? END')),
                [
                    PurgeConsentLogCron::cutoff(self::NOW, 6),
                    PurgeConsentLogCron::cutoff(self::NOW, 12),
                    PurgeConsentLogCron::cutoff(self::NOW, 24),
                    PurgeConsentLogCron::cutoff(self::NOW, 36),
                    PurgeConsentLogCron::cutoff(self::NOW, 36),
                ],
            )
        ;

        (new PurgeConsentLogCron($connection))->purge(self::NOW);
    }
}
