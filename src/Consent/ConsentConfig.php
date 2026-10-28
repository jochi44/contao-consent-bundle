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
     * Where the code of a service goes after consent; the first one is the default.
     */
    public const CODE_POSITIONS = ['body', 'head'];

    /**
     * Where the code after consent comes from: the code field (the default) or
     * a Twig template (see ServiceTemplates).
     */
    public const SERVICE_TYPES = ['code', 'template'];

    /**
     * How many days a choice counts: one year by default, at most 13 months.
     */
    public const DEFAULT_LIFETIME = 365;

    public const MAX_LIFETIME = 395;

    /**
     * How many months a consent log entry is kept; the last one is the default.
     */
    public const LOG_RETENTIONS = [6, 12, 24, 36];

    public const DEFAULT_LOG_RETENTION = 36;

    /**
     * What the log keeps of the page: its path without query string and
     * fragment (the default), or nothing.
     */
    public const LOG_PAGES = ['path', 'none'];

    /**
     * @var array<int, array<string, mixed>|null>
     */
    private array $loaded = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly ContentUrlGenerator $urlGenerator,
        private readonly InsertTagParser $insertTagParser,
        private readonly ServiceTemplates $templates,
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
            'lifetime' => self::lifetime($banner['lifetime'] ?? null),
            'log' => (bool) ($banner['logConsent'] ?? true),
            'log_page' => self::logPage($banner['logPage'] ?? null),
            'headline' => (string) $banner['headline'],
            'text' => $this->insertTagParser->replaceInline((string) $banner['text']),
            'info' => trim($this->insertTagParser->replaceInline((string) ($banner['infoText'] ?? ''))),
            'placeholder' => trim((string) $banner['placeholderText']),
            'ask' => (bool) $banner['askOnFirstVisit'],
            'privacy_signals' => (bool) ($banner['privacySignals'] ?? false),
            'restrict_users' => (bool) ($banner['noOptionalForUsers'] ?? false),
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
     * The banner of a page's website, as that page shows it (see forPage()), or
     * null if the website has no banner. The root page's "Consent banner" decides,
     * so two websites (or two language roots) each get their own banner.
     *
     * @return array<string, mixed>|null
     */
    public function bannerForPage(PageModel $page): array|null
    {
        $bannerId = (int) $this->connection->fetchOne('SELECT juheConsentBanner FROM tl_page WHERE id = ?', [(int) $page->loadDetails()->rootId]);
        $banner = $bannerId > 0 ? $this->banner($bannerId) : null;

        return null === $banner ? null : self::forPage($banner, (int) $page->id);
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
     * The days a choice counts, within 1 and MAX_LIFETIME; anything else is the default.
     */
    public static function lifetime(mixed $value): int
    {
        $days = filter_var($value, FILTER_VALIDATE_INT);

        return false !== $days && $days >= 1 && $days <= self::MAX_LIFETIME ? $days : self::DEFAULT_LIFETIME;
    }

    /**
     * The months a log entry of the banner is kept; anything unknown is the default.
     */
    public static function logRetention(mixed $value): int
    {
        $months = filter_var($value, FILTER_VALIDATE_INT);

        return \in_array($months, self::LOG_RETENTIONS, true) ? $months : self::DEFAULT_LOG_RETENTION;
    }

    /**
     * "path" or "none"; anything unknown is "path".
     */
    public static function logPage(mixed $value): string
    {
        return 'none' === $value ? 'none' : 'path';
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
     * The cookies of a service, one "name: lifetime" per line.
     *
     * @return list<array{name: string, duration: string}>
     */
    public static function cookies(string $value): array
    {
        $cookies = [];

        foreach (StringUtil::trimsplit('\n', $value) as $line) {
            [$name, $duration] = array_map('trim', explode(':', $line, 2)) + [1 => ''];

            if ('' !== $name) {
                $cookies[] = ['name' => $name, 'duration' => $duration];
            }
        }

        return $cookies;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function service(array $row): array
    {
        $cookies = self::cookies((string) $row['cookies']);

        // "https://www.youtube.com/embed" or "youtube.com" both end up as "youtube.com"
        $hosts = array_values(array_filter(array_map(
            static fn (string $host): string => preg_replace('/^www\./', '', rtrim(strtolower((string) (parse_url(str_contains($host, '//') ? $host : 'https://'.$host, PHP_URL_HOST) ?? '')), '.')),
            StringUtil::trimsplit('\n', (string) $row['hosts']),
        )));

        $service = [
            'key' => 's'.$row['id'],
            'title' => (string) $row['title'],
            'provider' => (string) $row['provider'],
            'description' => (string) $row['description'],
            'privacy_url' => (string) $row['privacyUrl'],
            'cookies' => $cookies,
            'hosts' => $hosts,
            // The notice of a blocked iframe: the service's own text and template, or empty for the banner's
            'notice' => trim((string) ($row['noticeText'] ?? '')),
            'notice_template' => ConsentNotices::isName((string) ($row['noticeTemplate'] ?? '')) ? (string) $row['noticeTemplate'] : '',
            'template' => null,
            'code' => trim((string) $row['code']),
            'code_denied' => trim((string) ($row['codeDenied'] ?? '')),
            'position' => \in_array($row['codePosition'] ?? null, self::CODE_POSITIONS, true) ? $row['codePosition'] : self::CODE_POSITIONS[0],
            'order' => max(0, (int) ($row['loadOrder'] ?? 0)),
        ];

        /*
         * A template service: its template gives the code; the code field does not
         * count. A preset renders only with a valid identifier (and address), which
         * the back end checks on saving; this check also covers rows from elsewhere.
         */
        if ('template' === ($row['serviceType'] ?? 'code')) {
            $template = (string) ($row['serviceTemplate'] ?? '');
            $id = trim((string) ($row['serviceId'] ?? ''));
            $url = trim((string) ($row['serviceUrl'] ?? ''));
            $service['template'] = $template;
            $service['id'] = $id;
            $service['url'] = ServicePresets::baseUrl($url) ?? '';
            $service['code'] = '' !== $template && ServicePresets::valid($template, $id, $url)
                ? $this->templates->render($template, ['service' => array_diff_key($service, ['code' => true, 'code_denied' => true])])
                : '';
        }

        return $service;
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
