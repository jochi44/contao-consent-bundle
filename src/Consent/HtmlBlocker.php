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

        $html = preg_replace_callback('/<iframe\b[^>]*>.*?<\/iframe>/is', function (array $match) use ($services, $placeholder): string {
            $service = $this->serviceFor($match[0], $services);

            if (null === $service) {
                return $match[0];
            }

            return $placeholder(['group' => $service['group'], 'key' => $service['key'], 'title' => $service['title'], 'provider' => $service['provider'], 'privacy_url' => $service['privacy_url']], $match[0]);
        }, $html) ?? $html;

        return preg_replace_callback('/<script\b([^>]*)>(\s*)<\/script>/i', function (array $match) use ($services): string {
            if (!preg_match('/\ssrc\s*=\s*(["\'])(.*?)\1/i', $match[1], $src) || null === ($service = $this->serviceFor($match[0], $services))) {
                return $match[0];
            }

            // The original type (e.g. "module") is kept for consent.js
            $type = preg_match('/\stype\s*=\s*(["\'])(.*?)\1/i', $match[1], $typeMatch) ? \sprintf(' data-juhe-consent-type="%s"', $typeMatch[2]) : '';
            $attributes = preg_replace('/\s(src|type)\s*=\s*(["\']).*?\2/i', '', $match[1]);

            return \sprintf('<script type="text/plain" data-juhe-consent-group="%s" data-juhe-consent-service="%s" data-juhe-consent-src="%s"%s%s></script>', $service['group'], $service['key'], $src[2], $type, $attributes);
        }, $html) ?? $html;
    }

    /**
     * @param list<array<string, mixed>> $services
     *
     * @return array<string, mixed>|null
     */
    private function serviceFor(string $tag, array $services): array|null
    {
        if (!preg_match('/\ssrc\s*=\s*(["\'])(.*?)\1/i', $tag, $src)) {
            return null;
        }

        $url = html_entity_decode($src[2], ENT_QUOTES | ENT_HTML5);
        $host = strtolower((string) parse_url(str_starts_with($url, '//') ? 'https:'.$url : $url, PHP_URL_HOST));

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
}
