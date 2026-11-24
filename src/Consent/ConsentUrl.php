<?php

declare(strict_types=1);

/*
 * This file is part of JUHE Consent.
 *
 * (c) JUHE IT-solutions
 *
 * @license proprietary
 */

namespace JuheItSolutions\JuheConsentBundle\Consent;

/**
 * Allowlist for URLs that end up in an href: same-site paths, fragments and
 * absolute http, https, mailto or tel URLs only. Everything else (javascript:,
 * data:, vbscript:, entity- or whitespace-obfuscated schemes) is refused with
 * an empty string, so a row written by an import or SQL instead of the backend
 * form renders no link. The check runs on the entity-decoded value, because the
 * browser decodes attribute entities before it parses the URL.
 */
final class ConsentUrl
{
    private const SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function safe(string $raw): string
    {
        $raw = trim($raw);
        if ('' === $raw || \strlen($raw) > 2048) {
            return '';
        }
        $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (1 === preg_match('/[\x00-\x1f\x7f]/', $decoded)
            || str_contains($decoded, '{{')
            || str_contains($decoded, '}}')
            || str_contains($decoded, '\\')
            || str_starts_with($decoded, '//')
        ) {
            return '';
        }
        if (1 === preg_match('#^([a-z][a-z0-9+.-]*):#i', $decoded, $match)) {
            $parts = parse_url($decoded);
            $scheme = strtolower($match[1]);
            if (!\is_array($parts) || !\in_array($scheme, self::SCHEMES, true)) {
                return '';
            }
            if (isset($parts['user']) || isset($parts['pass'])) {
                return '';
            }
            if ((\in_array($scheme, ['http', 'https'], true)) && (!isset($parts['host']) || !\is_string($parts['host']) || '' === $parts['host'])) {
                return '';
            }

            return $raw;
        }
        $path = substr($decoded, 0, strcspn($decoded, '?#'));
        if (str_contains($path, ':') || str_contains($path, '..')) {
            return '';
        }

        return $raw;
    }
}
