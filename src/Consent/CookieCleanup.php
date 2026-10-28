<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Consent;

/**
 * Which first-party cookies a withdrawal expires, and where: the names come
 * from the services' configuration ("name*" stands for a prefix), never from
 * the visitor, and a prefix only matches names the request actually carries.
 * The same rules apply in juhe-consent.js for the cookies scripts can read.
 */
final class CookieCleanup
{
    /**
     * Parent paths beyond this depth are left alone.
     */
    public const MAX_DEPTH = 8;

    /**
     * At most this many carried names per request: the names and the host come
     * from the visitor, and each name costs one header per path and domain.
     */
    public const MAX_NAMES = 64;

    /**
     * The configured names that the request carries. A pattern "name*" matches
     * every carried name that starts with "name"; a bare "*" matches nothing.
     * Names that an essential service lists are kept.
     *
     * @param list<string> $patterns configured names of the withdrawn services
     * @param list<string> $carried  names of the request's cookies
     * @param list<string> $keep     configured names of the essential services
     *
     * @return list<string>
     */
    public static function names(array $patterns, array $carried, array $keep = []): array
    {
        $names = array_filter($carried, static fn (string $name): bool => self::matches($patterns, $name) && !self::matches($keep, $name));

        return \array_slice(array_values(array_unique($names)), 0, self::MAX_NAMES);
    }

    /**
     * "/" and each parent of the path, with and without a trailing slash, and
     * the path itself: a cookie set without a path attribute on /shop/cart has
     * the path /shop.
     *
     * @return list<string>
     */
    public static function paths(string $path): array
    {
        $paths = ['/'];
        $current = '';
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => '' !== $segment));

        foreach (\array_slice($segments, 0, self::MAX_DEPTH) as $segment) {
            $current .= '/'.$segment;
            $paths[] = $current;
            $paths[] = $current.'/';
        }

        return array_values(array_unique($paths));
    }

    /**
     * Null (the host-only cookie), the host and each parent domain with at
     * least two labels. Browsers ignore a public suffix such as "co.uk", so
     * the registrable domain is the last one that takes effect. An IP address
     * or a single-label host has no parent domains. A long host keeps itself
     * and its MAX_DEPTH nearest parents of the registrable end.
     *
     * @return list<string|null>
     */
    public static function domains(string $host): array
    {
        $host = strtolower(trim($host, '[].'));

        if ('' === $host || str_contains($host, ':') || false !== filter_var($host, FILTER_VALIDATE_IP) || !str_contains($host, '.')) {
            return [null];
        }

        $labels = explode('.', $host);
        $domains = [null, $host];

        for ($i = max(1, \count($labels) - 1 - self::MAX_DEPTH); $i < \count($labels) - 1; ++$i) {
            $domains[] = implode('.', \array_slice($labels, $i));
        }

        return $domains;
    }

    /**
     * Cookie names from a Cookie request header, unchanged. The header, not
     * the cookie bag: Contao's HTTP cache removes known tracking cookies such
     * as "_ga" from the bag, and PHP turns dots and spaces into underscores.
     *
     * @return list<string>
     */
    public static function carried(string $header): array
    {
        $names = [];

        foreach (explode(';', $header) as $pair) {
            $name = trim(explode('=', $pair, 2)[0]);

            if ('' !== $name) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param list<string> $patterns
     */
    private static function matches(array $patterns, string $name): bool
    {
        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '*')) {
                $prefix = substr($pattern, 0, -1);

                if ('' !== $prefix && str_starts_with($name, $prefix)) {
                    return true;
                }
            } elseif ($pattern === $name) {
                return true;
            }
        }

        return false;
    }
}
