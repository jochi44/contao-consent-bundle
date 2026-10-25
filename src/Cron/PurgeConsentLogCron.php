<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Doctrine\DBAL\Connection;

/**
 * Log entries are kept three years (the usual limitation period for claims),
 * then deleted.
 */
#[AsCronJob('daily')]
final class PurgeConsentLogCron
{
    private const KEEP_SECONDS = 3 * 365 * 86400;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(): void
    {
        $this->connection->executeStatement('DELETE FROM tl_juhe_consent_log WHERE tstamp < ?', [time() - self::KEEP_SECONDS]);
    }
}
