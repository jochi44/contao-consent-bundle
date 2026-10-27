<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use JuheItSolutions\JuheConsentBundle\Consent\ServicePresets;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Template services in the back end: the identifier and the address are
 * checked against the selected template, and a preset fills in its hosts,
 * provider, privacy policy and cookies where the fields are empty.
 */
final class ServicePresetListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[AsCallback(table: 'tl_juhe_consent_service', target: 'fields.serviceId.save')]
    public function checkId(mixed $value, DataContainer $dc): string
    {
        $value = trim((string) $value);
        [$type, $template] = $this->selected($dc);

        if ('template' !== $type) {
            return $value;
        }

        $preset = ServicePresets::get($template);
        $lang = $GLOBALS['TL_LANG']['tl_juhe_consent_service'] ?? [];

        // An automatic reload (the template was just picked) does not complain about a missing value
        if ('' === $value && (null === $preset || $this->autoSubmit())) {
            return $value;
        }

        if (1 !== preg_match($preset['id'] ?? ServicePresets::OTHER_ID, $value)) {
            throw new \RuntimeException(\sprintf((string) ($lang['serviceIdInvalid'] ?? '%s'), $preset['example'] ?? 'abc-123'));
        }

        return $value;
    }

    #[AsCallback(table: 'tl_juhe_consent_service', target: 'fields.serviceUrl.save')]
    public function checkUrl(mixed $value, DataContainer $dc): string
    {
        $value = trim((string) $value);
        [$type, $template] = $this->selected($dc);
        $preset = 'template' === $type ? ServicePresets::get($template) : null;

        if (null === $preset || !$preset['url'] || ('' === $value && $this->autoSubmit())) {
            return $value;
        }

        if (null === ServicePresets::baseUrl($value)) {
            throw new \RuntimeException((string) ($GLOBALS['TL_LANG']['tl_juhe_consent_service']['serviceUrlInvalid'] ?? ''));
        }

        return $value;
    }

    /**
     * Fills the empty fields of a preset service with the preset's values.
     */
    #[AsCallback(table: 'tl_juhe_consent_service', target: 'config.onsubmit')]
    public function prefill(DataContainer $dc): void
    {
        $id = (int) $dc->id;
        $row = $this->connection->fetchAssociative('SELECT serviceType, serviceTemplate, serviceUrl, hosts, provider, privacyUrl, cookies FROM tl_juhe_consent_service WHERE id = ?', [$id]);

        if (!$row || 'template' !== $row['serviceType']) {
            return;
        }

        $values = self::prefilled($row, $GLOBALS['TL_LANG']['tl_juhe_consent_service']['presetDurations'] ?? [], $this->requestStack->getCurrentRequest()?->getHost());

        if ($values) {
            $this->connection->update('tl_juhe_consent_service', $values, ['id' => $id]);
        }
    }

    /**
     * The values a preset adds to the empty fields of a row.
     *
     * @param array<string, mixed>  $row
     * @param array<string, string> $durations labels for the cookie lifetimes
     * @param string|null           $ownHost   the host of the back end: never blocked
     *
     * @return array<string, string>
     */
    public static function prefilled(array $row, array $durations, string|null $ownHost = null): array
    {
        $preset = ServicePresets::get((string) $row['serviceTemplate']);

        if (null === $preset) {
            return [];
        }

        $hosts = $preset['hosts'];

        // A self-hosted installation: its host, unless it is this website's own
        if ($preset['url'] && null !== ($host = ServicePresets::host((string) $row['serviceUrl'])) && $host !== preg_replace('/^www\./', '', strtolower((string) $ownHost))) {
            $hosts[] = $host;
        }

        $cookies = array_map(static fn (array $cookie): string => $cookie[0].': '.($durations[$cookie[1]] ?? $cookie[1]), $preset['cookies']);

        $values = [
            'hosts' => implode("\n", $hosts),
            'provider' => $preset['provider'],
            'privacyUrl' => $preset['privacy'],
            'cookies' => implode("\n", $cookies),
        ];

        return array_filter($values, static fn (string $value, string $field): bool => '' !== $value && '' === trim((string) ($row[$field] ?? '')), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * The type and template of the submitted form, else of the stored record.
     *
     * @return array{0: string, 1: string}
     */
    private function selected(DataContainer $dc): array
    {
        $post = $this->requestStack->getCurrentRequest()?->request;
        $record = $dc->getCurrentRecord() ?? [];

        return [
            (string) ($post?->get('serviceType') ?? $record['serviceType'] ?? 'code'),
            (string) ($post?->get('serviceTemplate') ?? $record['serviceTemplate'] ?? ''),
        ];
    }

    private function autoSubmit(): bool
    {
        return 'auto' === $this->requestStack->getCurrentRequest()?->request->get('SUBMIT_TYPE');
    }
}
