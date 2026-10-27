<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

/**
 * The banner list names the websites (root pages) that use each banner, with
 * their language: the root decides the language of the visitor texts, so this
 * shows at a glance which banner speaks which language and which one no site uses.
 */
#[AsCallback(table: 'tl_juhe_consent_banner', target: 'list.label.label')]
final class BannerLabelListener
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function __invoke(array $row, string $label, DataContainer|null $dc = null): string
    {
        $roots = $this->connection->fetchAllAssociative(
            "SELECT title, language FROM tl_page WHERE type = 'root' AND juheConsentBanner = ? ORDER BY sorting",
            [(int) $row['id']],
        );

        $lang = $GLOBALS['TL_LANG']['tl_juhe_consent_banner'] ?? [];

        if ([] === $roots) {
            return $label.' <span class="label-info">'.($lang['usedByNone'] ?? '').'</span>';
        }

        $sites = array_map(
            static fn (array $root): string => StringUtil::specialchars((string) $root['title']).' ('.StringUtil::specialchars((string) $root['language']).')',
            $roots,
        );

        return $label.' <span class="label-info">'.($lang['usedBy'] ?? '').' '.implode(', ', $sites).'</span>';
    }
}
