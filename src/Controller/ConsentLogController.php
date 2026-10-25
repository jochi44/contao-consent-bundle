<?php

declare(strict_types=1);

namespace JuheItSolutions\ConsentBundle\Controller;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Stores a choice in the anonymous consent log: banner, version, the random
 * ID from the visitor's cookie, the allowed groups and services and the page.
 * No IP address or user agent is stored; the IP is only the rate limit key.
 */
#[Route('/_consent/log', name: 'contao_consent_log', methods: ['POST'], defaults: ['_scope' => 'frontend', '_token_check' => false])]
final class ConsentLogController
{
    private const MAX_BODY = 4096;

    public function __construct(
        private readonly Connection $connection,
        private readonly RateLimiterFactoryInterface $contaoConsentLogLimiter,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->contaoConsentLogLimiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
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

        $entry = \is_array($data) ? $this->entry($data) : null;

        if (null === $entry) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $this->connection->insert('tl_consent_log', $entry + ['tstamp' => time()]);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Only takes what matches the current banner, so the log holds no free text.
     *
     * @param array<mixed> $data
     *
     * @return array<string, int|string>|null
     */
    private function entry(array $data): array|null
    {
        $bannerId = $data['banner'] ?? null;
        $groups = $data['groups'] ?? null;
        $services = $data['services'] ?? [];
        $url = $data['url'] ?? null;

        if (
            !\is_int($bannerId)
            || !\is_int($data['version'] ?? null)
            || !\is_string($data['id'] ?? null)
            || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $data['id'])
            || !\is_array($groups) || !array_is_list($groups)
            || !\is_array($services) || !array_is_list($services)
            || !\is_string($url) || !str_starts_with($url, '/') || str_starts_with($url, '//') || \strlen($url) > 255
        ) {
            return null;
        }

        $version = $this->connection->fetchOne('SELECT version FROM tl_consent_banner WHERE id = ?', [$bannerId]);

        if (false === $version || (int) $version !== $data['version']) {
            return null;
        }

        $known = $this->connection->fetchAllKeyValue('SELECT alias, id FROM tl_consent_group WHERE pid = ? AND published = 1', [$bannerId]);
        $knownServices = $known ? array_map(
            static fn (int|string $id): string => 's'.$id,
            $this->connection->fetchFirstColumn('SELECT id FROM tl_consent_service WHERE published = 1 AND pid IN (?)', [array_values($known)], [ArrayParameterType::INTEGER]),
        ) : [];

        foreach ([[$groups, array_keys($known)], [$services, $knownServices]] as [$values, $allowed]) {
            foreach ($values as $value) {
                if (!\in_array($value, $allowed, true)) {
                    return null;
                }
            }
        }

        return [
            'pid' => $bannerId,
            'version' => $data['version'],
            'consentId' => $data['id'],
            'groups' => implode(',', array_unique($groups)),
            'services' => implode(',', array_unique($services)),
            'url' => $url,
        ];
    }
}
