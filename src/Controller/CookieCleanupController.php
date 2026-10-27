<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Controller;

use Doctrine\DBAL\Connection;
use JuheItSolutions\JuheConsentBundle\Consent\ConsentConfig;
use JuheItSolutions\JuheConsentBundle\Consent\CookieCleanup;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Expires first-party cookies of withdrawn services that scripts cannot reach
 * (HttpOnly). The request names a banner and, optionally, the withdrawn
 * services and the page path; never a cookie. The names come from the
 * services' configuration and must be carried by the request. Cookies of
 * other sites (third-party cookies) are out of reach.
 */
#[Route('/_juhe_consent/cookies', name: 'juhe_consent_cookies', methods: ['POST'], defaults: ['_scope' => 'frontend', '_token_check' => false])]
final class CookieCleanupController
{
    private const MAX_BODY = 4096;

    public function __construct(
        private readonly Connection $connection,
        private readonly RateLimiterFactoryInterface $juheConsentCookiesLimiter,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->juheConsentCookiesLimiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
            return new Response('', Response::HTTP_TOO_MANY_REQUESTS);
        }

        // JSON only: a cross-site form cannot send it without a CORS preflight
        if ('json' !== $request->getContentTypeFormat()) {
            return new Response('', Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
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

        $services = \is_array($data) ? ($data['services'] ?? null) : null;
        $path = \is_array($data) ? ($data['path'] ?? '/') : null;

        if (
            !\is_array($data)
            || !\is_int($data['banner'] ?? null)
            || (null !== $services && (!\is_array($services) || !array_is_list($services)))
            || !\is_string($path)
            || !preg_match('#^/[^\x00-\x20;,\x7f]{0,254}$#', $path)
            || str_starts_with($path, '//')
        ) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        [$withdrawn, $essential] = $this->patterns($data['banner'], $services);

        if (null === $withdrawn) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $response = new Response('', Response::HTTP_NO_CONTENT);
        $response->headers->set('Cache-Control', 'no-store');

        $names = CookieCleanup::names($withdrawn, CookieCleanup::carried((string) $request->headers->get('Cookie', '')), $essential);
        $paths = CookieCleanup::paths($path);
        $domains = CookieCleanup::domains($request->getHost());

        foreach ($names as $name) {
            // A __Host- cookie only exists with path / and no domain, both prefixes only with Secure
            $secure = $request->isSecure() || str_starts_with($name, '__Secure-') || str_starts_with($name, '__Host-');

            foreach (str_starts_with($name, '__Host-') ? ['/'] : $paths as $cookiePath) {
                foreach (str_starts_with($name, '__Host-') ? [null] : $domains as $domain) {
                    $response->headers->clearCookie($name, $cookiePath, $domain, $secure, true, 'lax');
                }
            }
        }

        return $response;
    }

    /**
     * The configured cookie names of the withdrawn services (all optional ones
     * if the request names none) and of the essential services.
     *
     * @param list<mixed>|null $keys
     *
     * @return array{0: list<string>|null, 1: list<string>}
     */
    private function patterns(int $banner, array|null $keys): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT s.id, s.cookies, g.essential FROM tl_juhe_consent_service s INNER JOIN tl_juhe_consent_group g ON g.id = s.pid WHERE g.pid = ? AND g.published = 1 AND s.published = 1',
            [$banner],
        );

        if (!$rows) {
            return [null, []];
        }

        $optional = [];
        $essential = [];

        foreach ($rows as $row) {
            $names = array_column(ConsentConfig::cookies((string) $row['cookies']), 'name');

            if ($row['essential']) {
                $essential = [...$essential, ...$names];
            } else {
                $optional['s'.$row['id']] = $names;
            }
        }

        // Only optional services of this banner; an essential or unknown key is refused
        foreach ($keys ?? [] as $key) {
            if (!\is_string($key) || !isset($optional[$key])) {
                return [null, []];
            }
        }

        $withdrawn = null === $keys ? $optional : array_intersect_key($optional, array_flip($keys));

        return [array_values(array_merge(...array_values($withdrawn) ?: [[]])), $essential];
    }
}
