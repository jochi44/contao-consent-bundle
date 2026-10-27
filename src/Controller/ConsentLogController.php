<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Controller;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use JuheItSolutions\JuheConsentBundle\Consent\ConsentConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Stores a choice in the pseudonymous consent log: banner, version, the random
 * ID from the visitor's browser storage, the allowed groups and services and,
 * if the banner keeps it, the page path. No IP address or user agent is stored;
 * the IP is only the rate limit key. A banner with logging off gets no entry.
 */
#[Route('/_juhe_consent/log', name: 'juhe_consent_log', methods: ['POST'], defaults: ['_scope' => 'frontend', '_token_check' => false])]
final class ConsentLogController
{
    private const MAX_BODY = 4096;

    public function __construct(
        private readonly Connection $connection,
        private readonly RateLimiterFactoryInterface $juheConsentLogLimiter,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->juheConsentLogLimiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
            return new Response('', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $body = $request->getContent();

        if (\strlen($body) > self::MAX_BODY) {
            return new Response('', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        try {
            $data = json_decode($body, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $banner = \is_array($data) && \is_int($data['banner'] ?? null)
            ? $this->connection->fetchAssociative('SELECT version, logConsent, logPage FROM tl_juhe_consent_banner WHERE id = ?', [$data['banner']])
            : false;

        // Logging is off for this banner (a page from before the change may still post)
        if (false !== $banner && !$banner['logConsent']) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $entry = false !== $banner ? $this->entry($data, $banner) : null;

        if (null === $entry) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $this->connection->insert('tl_juhe_consent_log', $entry + ['tstamp' => time()]);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Checks choices against the current banner and accepts only relative page
     * paths. The log keeps the path without query string and fragment, or no
     * page at all if the banner says so.
     *
     * @param array<mixed>         $data
     * @param array<string, mixed> $banner
     *
     * @return array<string, int|string>|null
     */
    private function entry(array $data, array $banner): array|null
    {
        $bannerId = $data['banner'];
        $groups = $data['groups'] ?? null;
        $services = $data['services'] ?? [];
        $url = $data['url'] ?? null;
        $keepPage = 'none' !== ConsentConfig::logPage($banner['logPage'] ?? null);

        if (\is_string($url)) {
            $url = preg_replace('/[?#].*$/s', '', $url);
        }

        if (
            !\is_int($data['version'] ?? null)
            || !\is_string($data['id'] ?? null)
            || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $data['id'])
            || !\is_array($groups) || !array_is_list($groups)
            || !\is_array($services) || !array_is_list($services)
            || ($keepPage && (!\is_string($url) || !str_starts_with($url, '/') || str_starts_with($url, '//') || \strlen($url) > 255))
        ) {
            return null;
        }

        if ((int) $banner['version'] !== $data['version']) {
            return null;
        }

        $known = $this->connection->fetchAllKeyValue('SELECT alias, id FROM tl_juhe_consent_group WHERE pid = ? AND published = 1', [$bannerId]);
        $knownServices = $known ? array_map(
            static fn (int|string $id): string => 's'.$id,
            $this->connection->fetchFirstColumn('SELECT id FROM tl_juhe_consent_service WHERE published = 1 AND pid IN (?)', [array_values($known)], [ArrayParameterType::INTEGER]),
        ) : [];

        foreach ([[$groups, array_keys($known)], [$services, $knownServices]] as [$values, $allowed]) {
            foreach ($values as $value) {
                if (!\in_array($value, $allowed, true)) {
                    return null;
                }
            }
        }

        // The body limit bounds both lists, so they always fit their text columns
        return [
            'pid' => $bannerId,
            'version' => $data['version'],
            'consentId' => $data['id'],
            'groups' => implode(',', array_unique($groups)),
            'services' => implode(',', array_unique($services)),
            'url' => $keepPage ? $url : '',
        ];
    }
}
