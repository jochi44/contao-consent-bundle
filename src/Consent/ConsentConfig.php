<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Consent;

use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

/**
 * A banner with its published groups and services, as the templates and the
 * script need it.
 */
final class ConsentConfig
{
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
            'placeholder' => trim((string) $banner['placeholderText']),
            'privacy_url' => $this->pageUrl((int) $banner['privacyPage']),
            'imprint_url' => $this->pageUrl((int) $banner['imprintPage']),
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

    private function pageUrl(int $id): string|null
    {
        $page = $id ? PageModel::findPublishedById($id) : null;

        return $page ? $this->urlGenerator->generate($page) : null;
    }
}
