<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Consent;

use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;

/**
 * A banner with its published groups and services, as the templates and the
 * script need it.
 */
final class ConsentConfig
{
    /**
     * Where the card sits; the first one is the default.
     */
    public const CARD_POSITIONS = ['bottom-left', 'bottom-center', 'bottom-right', 'center', 'top-left', 'top-center', 'top-right'];

    /**
     * Where the bar sits; the first one is the default.
     */
    public const BAR_POSITIONS = ['bottom', 'top'];

    /**
     * @var array<int, array<string, mixed>|null>
     */
    private array $loaded = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly ContentUrlGenerator $urlGenerator,
        private readonly InsertTagParser $insertTagParser,
    ) {
    }

    /**
     * @return array<string, mixed>|null null if the banner is missing or has no groups
     */
    public function banner(int $id): array|null
    {
        if (\array_key_exists($id, $this->loaded)) {
            return $this->loaded[$id];
        }

        $banner = $this->connection->fetchAssociative('SELECT * FROM tl_juhe_consent_banner WHERE id = ?', [$id]);
        $groups = $banner ? $this->connection->fetchAllAssociative('SELECT * FROM tl_juhe_consent_group WHERE pid = ? AND published = 1 ORDER BY sorting', [$id]) : [];

        if (!$groups) {
            return $this->loaded[$id] = null;
        }

        $services = $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_juhe_consent_service WHERE published = 1 AND pid IN (?) ORDER BY sorting',
            [array_column($groups, 'id')],
            [\Doctrine\DBAL\ArrayParameterType::INTEGER],
        );

        return $this->loaded[$id] = [
            'id' => (int) $banner['id'],
            'version' => (int) $banner['version'],
            'headline' => (string) $banner['headline'],
            'text' => $this->insertTagParser->replaceInline((string) $banner['text']),
            'info' => trim($this->insertTagParser->replaceInline((string) ($banner['infoText'] ?? ''))),
            'placeholder' => trim((string) $banner['placeholderText']),
            'ask' => (bool) $banner['askOnFirstVisit'],
            'layout' => self::layout($banner['layout'] ?? null),
            'position' => self::position($banner),
            'blocking' => (bool) ($banner['blocking'] ?? false),
            'privacy_url' => $this->pageUrl((int) $banner['privacyPage']),
            'imprint_url' => $this->pageUrl((int) $banner['imprintPage']),
            'links' => $this->links(self::pageIds($banner['infoPages'] ?? null)),
            'legal_pages' => array_values(array_filter([(int) $banner['privacyPage'], (int) $banner['imprintPage']])),
            'quiet_pages' => self::pageIds($banner['excludePages'] ?? null),
            'quiet' => false,
            'groups' => array_map(fn (array $group): array => [
                'alias' => (string) $group['alias'],
                'title' => (string) $group['title'],
                'description' => (string) $group['description'],
                'essential' => (bool) $group['essential'],
                'services' => array_values(array_map($this->service(...), array_filter($services, static fn (array $service): bool => $service['pid'] === $group['id']))),
            ], $groups),
        ];
    }

    /**
     * The banner as a page shows it. On the banner's own privacy policy and imprint
     * the page is never blocked: a visitor must be able to read them before
     * choosing. The banner still asks there, as a non-modal dialog.
     *
     * On the pages the banner excludes ("quiet" pages) it does not open by
     * itself. Blocked content stays blocked there, and the settings still open
     * from a link or a button.
     *
     * @param array<string, mixed> $banner
     *
     * @return array<string, mixed>
     */
    public static function forPage(array $banner, int $pageId): array
    {
        if (\in_array($pageId, $banner['legal_pages'] ?? [], true)) {
            $banner['blocking'] = false;
        }

        if (\in_array($pageId, $banner['quiet_pages'] ?? [], true)) {
            $banner['quiet'] = true;
            $banner['blocking'] = false;
        }

        return $banner;
    }

    /**
     * Page IDs from a page picker with several pages, in their order.
     *
     * @return list<int>
     */
    public static function pageIds(mixed $value): array
    {
        $ids = \is_string($value) || \is_array($value) ? StringUtil::deserialize($value, true) : [];

        return array_values(array_unique(array_filter(array_map('intval', array_filter($ids, 'is_scalar')), static fn (int $id): bool => $id > 0)));
    }

    /**
     * "card" (the small card at the bottom left) or "bar" (full width at the bottom,
     * groups always shown); anything else is a card.
     */
    public static function layout(mixed $value): string
    {
        return 'bar' === $value ? 'bar' : 'card';
    }

    /**
     * The position of the card or the bar; anything unknown is the default
     * (bottom left for the card, bottom for the bar).
     *
     * @param array<string, mixed> $banner the banner record
     */
    public static function position(array $banner): string
    {
        [$positions, $value] = 'bar' === self::layout($banner['layout'] ?? null)
            ? [self::BAR_POSITIONS, $banner['barPosition'] ?? null]
            : [self::CARD_POSITIONS, $banner['position'] ?? null];

        return \in_array($value, $positions, true) ? $value : $positions[0];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function service(array $row): array
    {
        $cookies = [];

        foreach (StringUtil::trimsplit('\n', (string) $row['cookies']) as $line) {
            [$name, $duration] = array_map('trim', explode(':', $line, 2)) + [1 => ''];

            if ('' !== $name) {
                $cookies[] = ['name' => $name, 'duration' => $duration];
            }
        }

        // "https://www.youtube.com/embed" or "youtube.com" both end up as "youtube.com"
        $hosts = array_values(array_filter(array_map(
            static fn (string $host): string => preg_replace('/^www\./', '', strtolower((string) (parse_url(str_contains($host, '//') ? $host : 'https://'.$host, PHP_URL_HOST) ?? ''))),
            StringUtil::trimsplit('\n', (string) $row['hosts']),
        )));

        return [
            'key' => 's'.$row['id'],
            'title' => (string) $row['title'],
            'provider' => (string) $row['provider'],
            'description' => (string) $row['description'],
            'privacy_url' => (string) $row['privacyUrl'],
            'cookies' => $cookies,
            'hosts' => $hosts,
            'code' => trim((string) $row['code']),
        ];
    }

    /**
     * The further links: published pages with an address, in the chosen order.
     *
     * @param list<int> $ids
     *
     * @return list<array{url: string, title: string}>
     */
    private function links(array $ids): array
    {
        $links = [];

        foreach ($ids as $id) {
            $page = PageModel::findPublishedById($id);
            $url = $page ? $this->url($page) : null;

            if (null !== $url) {
                $links[] = ['url' => $url, 'title' => (string) $page->title];
            }
        }

        return $links;
    }

    private function pageUrl(int $id): string|null
    {
        $page = $id ? PageModel::findPublishedById($id) : null;

        return $page ? $this->url($page) : null;
    }

    private function url(PageModel $page): string|null
    {
        // A root or folder page has no address of its own
        try {
            return $this->urlGenerator->generate($page);
        } catch (RoutingException) {
            return null;
        }
    }
}
