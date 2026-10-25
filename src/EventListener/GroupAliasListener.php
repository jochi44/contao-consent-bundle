<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * The alias names a group in the cookie and the script API, so it must be
 * unique within its banner.
 */
#[AsCallback(table: 'tl_juhe_consent_group', target: 'fields.alias.save')]
final class GroupAliasListener
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(mixed $value, DataContainer $dc): mixed
    {
        $pid = (int) ($dc->getCurrentRecord()['pid'] ?? 0);

        if (false !== $this->connection->fetchOne('SELECT id FROM tl_juhe_consent_group WHERE pid = ? AND alias = ? AND id != ?', [$pid, $value, (int) $dc->id])) {
            throw new \RuntimeException(\sprintf($GLOBALS['TL_LANG']['tl_juhe_consent_group']['aliasExists'], $value));
        }

        return $value;
    }
}
