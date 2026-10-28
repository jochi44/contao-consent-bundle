<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Consent;

/**
 * Holds back iframes, external scripts and resource links from the hosts of a
 * service until the visitor agrees: an iframe becomes a placeholder with the
 * original in a <template>, a script gets type="text/plain" and its URL in
 * data-juhe-consent-src, a <link> (preconnect, dns-prefetch, preload, prefetch,
 * modulepreload, stylesheet) keeps its rel and href only in data-juhe-consent-rel
 * and data-juhe-consent-href. consent.js brings them back after consent.
 * Hyperlinks (<a href>) and other links (icon, canonical, alternate) stay.
 *
 * The placeholder markup comes from the caller (the juhe_consent/placeholder
 * template), so this class has no template dependency.
 */
final class HtmlBlocker
{
    private const ATTRIBUTES = '(?:[^>"\']|"[^"]*"|\'[^\']*\')*';
    private const NON_ELEMENTS = '(?:<!--.*?-->|<(?<raw>style|textarea|title)\b'.self::ATTRIBUTES.'>.*?<\/\k<raw>\s*>)(*SKIP)(*F)|';
    private const ATTRIBUTE = '/\\s+([^\\s=\/>]+)(?:\\s*=\\s*(?:"([^"]*)"|\'([^\']*)\'|([^\\s>]+)))?/';

    /**
     * Link types that make the browser contact a host before anything else runs.
     */
    public const LINK_TYPES = ['preconnect', 'dns-prefetch', 'preload', 'prefetch', 'modulepreload', 'stylesheet'];

    /**
     * @param array<string, mixed>                                   $banner      see ConsentConfig::banner()
     * @param callable(array{group: string, key: string, title: string, provider: string, privacy_url: string, notice: string, notice_template: string}, string): string $placeholder renders the placeholder for a service and the original iframe
     */
    public function block(string $html, array $banner, callable $placeholder): string
    {
        $services = [];

        foreach ($banner['groups'] as $group) {
            foreach ($group['services'] as $service) {
                if ($service['hosts'] && !$group['essential']) {
                    $services[] = ['group' => $group['alias'], 'key' => $service['key'], 'title' => $service['title'], 'provider' => $service['provider'] ?? '', 'privacy_url' => $service['privacy_url'] ?? '', 'notice' => $service['notice'] ?? '', 'notice_template' => $service['notice_template'] ?? '', 'hosts' => $service['hosts']];
                }
            }
        }

        if (!$services) {
            return $html;
        }

        $html = preg_replace_callback('/' .self::NON_ELEMENTS.'<script\b'.self::ATTRIBUTES.'>.*?<\/script\s*>(*SKIP)(*F)|<iframe\b'.self::ATTRIBUTES.'>.*?<\/iframe\s*>/is', function (array $match) use ($services, $placeholder): string {
            $service = $this->serviceFor($match[0], $services);

            if (null === $service) {
                return $match[0];
            }

            return $placeholder(array_diff_key($service, ['hosts' => true]), $match[0]);
        }, $html) ?? $html;

        $html = preg_replace_callback('/'.self::NON_ELEMENTS.'<script\b(?<attributes>'.self::ATTRIBUTES.')>(?<body>.*?)<\/script\s*>/is', function (array $match) use ($services): string {
            $src = $this->attribute($match['attributes'], 'src');

            if (null === $src || null === ($service = $this->serviceFor($match[0], $services))) {
                return $match[0];
            }

            // The original type (e.g. "module") is kept for consent.js
            $originalType = $this->attribute($match['attributes'], 'type');
            $type = null !== $originalType ? \sprintf(' data-juhe-consent-type="%s"', htmlspecialchars($originalType, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) : '';
            $attributes = preg_replace_callback(self::ATTRIBUTE, static fn (array $attribute): string => \in_array(strtolower($attribute[1]), ['src', 'type'], true) ? '' : $attribute[0], $match['attributes']);

            return \sprintf('<script type="text/plain" data-juhe-consent-group="%s" data-juhe-consent-service="%s" data-juhe-consent-src="%s"%s%s>%s</script>', $service['group'], $service['key'], htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $type, $attributes, $match['body']);
        }, $html) ?? $html;

        return preg_replace_callback('/'.self::NON_ELEMENTS.'<script\b'.self::ATTRIBUTES.'>.*?<\/script\s*>(*SKIP)(*F)|<link\b(?<attributes>'.self::ATTRIBUTES.')>/is', function (array $match) use ($services): string {
            $rel = strtolower((string) $this->attribute($match['attributes'], 'rel'));
            $href = $this->attribute($match['attributes'], 'href');

            if (null === $href || !array_intersect(preg_split('/\s+/', trim($rel)) ?: [], self::LINK_TYPES) || null === ($service = $this->serviceForUrl($href, $services))) {
                return $match[0];
            }

            // Without rel and href the browser ignores the link; consent.js puts them back
            $attributes = preg_replace_callback(self::ATTRIBUTE, static fn (array $attribute): string => \in_array(strtolower($attribute[1]), ['rel', 'href'], true) ? '' : $attribute[0], $match['attributes']);
            // A self-closing "/" after a space or a quote is no part of a value
            $attributes = preg_replace('#(?:\s+|(?<=["\']))/\s*$#', '', rtrim((string) $attributes));

            return \sprintf('<link data-juhe-consent-group="%s" data-juhe-consent-service="%s" data-juhe-consent-rel="%s" data-juhe-consent-href="%s"%s>', $service['group'], $service['key'], htmlspecialchars($rel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $attributes);
        }, $html) ?? $html;
    }

    /**
     * @param list<array<string, mixed>> $services
     *
     * @return array<string, mixed>|null
     */
    private function serviceFor(string $tag, array $services): array|null
    {
        preg_match('/^<\\w+('.self::ATTRIBUTES.')>/s', $tag, $opening);
        $src = $this->attribute($opening[1] ?? '', 'src');

        return null === $src ? null : $this->serviceForUrl($src, $services);
    }

    /**
     * @param list<array<string, mixed>> $services
     *
     * @return array<string, mixed>|null
     */
    private function serviceForUrl(string $src, array $services): array|null
    {
        $host = self::urlHost($src);

        if (null === $host) {
            return null;
        }

        foreach ($services as $service) {
            foreach ($service['hosts'] as $blocked) {
                if ($host === $blocked || str_ends_with($host, '.'.$blocked)) {
                    return $service;
                }
            }
        }

        return null;
    }

    /**
     * The host a browser contacts for a URL in an attribute, or null for a
     * relative URL and a scheme that loads nothing from a host (data:, about:).
     * Browsers, not PHP's URL grammar, decide: they trim the URL, drop tabs and
     * line breaks, read "\" as "/" and, for http and https, skip any number of
     * slashes after the scheme, so "https:///www.youtube.com/embed" loads from
     * www.youtube.com. "https:youtube.com" counts as that host too (a browser
     * reads it so on a page of the other scheme). The host is percent-decoded
     * and compared in its ASCII form (see asciiHost()).
     */
    public static function urlHost(string $src): string|null
    {
        $url = str_replace(["\t", "\n", "\r"], '', trim($src, "\x00..\x20"));
        $url = str_replace('\\', '/', $url);

        if (preg_match('#^([a-z][a-z0-9+.-]*):(.*)$#is', $url, $match)) {
            if (!\in_array(strtolower($match[1]), ['http', 'https', 'ws', 'wss', 'ftp'], true)) {
                return null;
            }

            $rest = ltrim($match[2], '/');
        } elseif (str_starts_with($url, '//')) {
            $rest = ltrim($url, '/');
        } else {
            return null;
        }

        // The authority ends at the path, query or fragment; user info and port are no part of the host
        $authority = (string) preg_replace('#[/?\#].*$#s', '', $rest);
        $authority = substr($authority, (int) strrpos('@'.$authority, '@'));
        $authority = str_starts_with($authority, '[') ? strstr($authority.']', ']', true).']' : (string) preg_replace('/:[^:]*$/', '', $authority);
        $host = self::asciiHost(rawurldecode($authority));

        return '' === $host ? null : $host;
    }

    /**
     * A host as browsers compare it: lowercase, an international name in its
     * ASCII form (IDNA, so "münchen.de" is "xn--mnchen-3ya.de" and full-width
     * letters are plain ones) and without a trailing dot ("youtube.com." loads
     * like "youtube.com"). Configured hosts and page URLs both go through it.
     */
    public static function asciiHost(string $host): string
    {
        $host = trim($host);
        $ascii = '' !== $host && \function_exists('idn_to_ascii') ? idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46) : false;

        return rtrim(strtolower(false === $ascii ? $host : $ascii), '.');
    }

    private function attribute(string $attributes, string $name): string|null
    {
        preg_match_all(self::ATTRIBUTE, $attributes, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        foreach ($matches as $match) {
            if (strtolower($match[1]) === $name) {
                return html_entity_decode($match[2] ?? $match[3] ?? $match[4] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return null;
    }
}
