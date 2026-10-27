<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Doctrine\DBAL\Connection;
use JuheItSolutions\JuheConsentBundle\Consent\ConsentConfig;

/**
 * Deletes log entries once they are older than their banner keeps them (6, 12,
 * 24 or 36 months, counted in calendar months). Entries of a deleted banner or
 * with an unknown setting follow the default of 36 months. An entry exactly at
 * the limit stays until the next run.
 */
#[AsCronJob('daily')]
final class PurgeConsentLogCron
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(): void
    {
        $this->purge(time());
    }

    public function purge(int $now): void
    {
        $cases = '';
        $cutoffs = [];

        foreach (ConsentConfig::LOG_RETENTIONS as $months) {
            $cases .= ' WHEN '.$months.' THEN ?';
            $cutoffs[] = self::cutoff($now, $months);
        }

        $cutoffs[] = self::cutoff($now, ConsentConfig::DEFAULT_LOG_RETENTION);

        $this->connection->executeStatement(
            'DELETE l FROM tl_juhe_consent_log l LEFT JOIN tl_juhe_consent_banner b ON b.id = l.pid WHERE l.tstamp < CASE b.logRetention'.$cases.' ELSE ? END',
            $cutoffs,
        );
    }

    /**
     * The same moment, the given number of calendar months back (UTC).
     */
    public static function cutoff(int $now, int $months): int
    {
        return (new \DateTimeImmutable('@'.$now))->modify(\sprintf('-%d months', $months))->getTimestamp();
    }
}
