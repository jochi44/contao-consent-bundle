<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Consent;

/**
 * Holds back iframes and external scripts from the hosts of a service until
 * the visitor agrees: an iframe becomes a placeholder with the original in a
 * <template>, a script gets type="text/plain" and its URL in data-juhe-consent-src.
 * consent.js brings both back after consent.
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
     * @param array<string, mixed>                                   $banner      see ConsentConfig::banner()
     * @param callable(array{group: string, key: string, title: string, provider: string, privacy_url: string}, string): string $placeholder renders the placeholder for a service and the original iframe
     */
    public function block(string $html, array $banner, callable $placeholder): string
    {
        $services = [];

        foreach ($banner['groups'] as $group) {
            foreach ($group['services'] as $service) {
                if ($service['hosts'] && !$group['essential']) {
                    $services[] = ['group' => $group['alias'], 'key' => $service['key'], 'title' => $service['title'], 'provider' => $service['provider'] ?? '', 'privacy_url' => $service['privacy_url'] ?? '', 'hosts' => $service['hosts']];
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

            return $placeholder(['group' => $service['group'], 'key' => $service['key'], 'title' => $service['title'], 'provider' => $service['provider'], 'privacy_url' => $service['privacy_url']], $match[0]);
        }, $html) ?? $html;

        return preg_replace_callback('/'.self::NON_ELEMENTS.'<script\b(?<attributes>'.self::ATTRIBUTES.')>(?<body>.*?)<\/script\s*>/is', function (array $match) use ($services): string {
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

        if (null === $src) {
            return null;
        }

        // HTTP URLs in HTML follow browser normalization, not PHP's URL grammar.
        $url = str_replace(["\t", "\n", "\r"], '', trim($src, "\x00..\x20"));
        $url = str_replace('\\', '/', $url);
        $host = strtolower(rawurldecode((string) parse_url(str_starts_with($url, '//') ? 'https:'.$url : $url, PHP_URL_HOST)));

        if ('' === $host) {
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
