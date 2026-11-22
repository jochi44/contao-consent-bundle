<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\EventListener;

use Contao\CoreBundle\DataContainer\RecordLabel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Twig\Environment;

/**
 * The banner list names the websites (root pages) that use each banner, with
 * their language: the root decides the language of the visitor texts, so this
 * shows at a glance which banner speaks which language and which one no site uses.
 */
#[AsCallback(table: 'tl_juhe_consent_banner', target: 'list.label.label')]
final class BannerLabelListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Environment $twig,
    ) {
    }

    /**
     * Contao 6 escapes a plain string from a label callback, so the HTML goes
     * back as a RecordLabel there; Contao 5.7 has no such class and takes the
     * string as HTML.
     *
     * @param array<string, mixed> $row
     */
    public function __invoke(array $row, string $label, DataContainer|null $dc = null): RecordLabel|string
    {
        $roots = $this->connection->fetchAllAssociative(
            "SELECT title, language FROM tl_page WHERE type = 'root' AND juheConsentBanner = ? ORDER BY sorting",
            [(int) $row['id']],
        );

        $lang = $GLOBALS['TL_LANG']['tl_juhe_consent_banner'] ?? [];

        $html = trim($this->twig->render('@Contao/juhe_consent/backend/banner_label.html.twig', [
            'label' => $label,
            'sites' => array_map(static fn (array $root): array => ['title' => (string) $root['title'], 'language' => (string) $root['language']], $roots),
            'used_by' => (string) ($lang['usedBy'] ?? ''),
            'used_by_none' => (string) ($lang['usedByNone'] ?? ''),
        ]));

        return class_exists(RecordLabel::class) ? RecordLabel::fromHtml($html) : $html;
    }
}
