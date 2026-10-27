<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Consent;

/**
 * What the shipped service templates (juhe_consent/service/*) need: the format
 * of the identifier, whether they need the address of an installation, and
 * the values the back end fills in when the fields are empty. The snippets
 * themselves are the templates. The values follow the providers' public
 * documentation; the editor checks and changes them.
 */
final class ServicePresets
{
    /**
     * A website's own template: any identifier of this form, or none.
     */
    public const OTHER_ID = '/^[A-Za-z0-9._:-]{1,64}$/';

    /**
     * @var array<string, array{id: string, example: string, url: bool, hosts: list<string>, provider: string, privacy: string, cookies: list<array{0: string, 1: string}>}>
     */
    public const PRESETS = [
        'juhe_consent/service/google_analytics' => [
            'id' => '/^G-[A-Z0-9]{4,16}$/',
            'example' => 'G-XXXXXXXXXX',
            'url' => false,
            'hosts' => ['googletagmanager.com', 'google-analytics.com', 'analytics.google.com'],
            'provider' => 'Google Ireland Limited',
            'privacy' => 'https://policies.google.com/privacy',
            'cookies' => [['_ga', 'years2'], ['_ga_*', 'years2']],
        ],
        'juhe_consent/service/google_tag_manager' => [
            'id' => '/^GTM-[A-Z0-9]{4,12}$/',
            'example' => 'GTM-XXXXXXX',
            'url' => false,
            'hosts' => ['googletagmanager.com'],
            'provider' => 'Google Ireland Limited',
            'privacy' => 'https://policies.google.com/privacy',
            'cookies' => [],
        ],
        'juhe_consent/service/matomo' => [
            'id' => '/^[1-9][0-9]{0,9}$/',
            'example' => '1',
            'url' => true,
            'hosts' => [],
            'provider' => '',
            'privacy' => '',
            'cookies' => [['_pk_id.*', 'months13'], ['_pk_ses.*', 'minutes30']],
        ],
        'juhe_consent/service/matomo_tag_manager' => [
            'id' => '/^[A-Za-z0-9]{4,16}$/',
            'example' => 'aBcD1234',
            'url' => true,
            'hosts' => [],
            'provider' => '',
            'privacy' => '',
            'cookies' => [['_pk_id.*', 'months13'], ['_pk_ses.*', 'minutes30']],
        ],
        'juhe_consent/service/meta_pixel' => [
            'id' => '/^[0-9]{6,20}$/',
            'example' => '123456789012345',
            'url' => false,
            'hosts' => ['connect.facebook.net', 'facebook.com'],
            'provider' => 'Meta Platforms Ireland Limited',
            'privacy' => 'https://www.facebook.com/privacy/policy/',
            'cookies' => [['_fbp', 'months3']],
        ],
        'juhe_consent/service/etracker' => [
            'id' => '/^[A-Za-z0-9]{4,16}$/',
            'example' => 'aBc123',
            'url' => false,
            'hosts' => ['etracker.com', 'etracker.de'],
            'provider' => 'etracker GmbH',
            'privacy' => '',
            'cookies' => [['_et_coid', 'years2']],
        ],
    ];

    /**
     * @return array{id: string, example: string, url: bool, hosts: list<string>, provider: string, privacy: string, cookies: list<array{0: string, 1: string}>}|null
     */
    public static function get(string $template): array|null
    {
        return self::PRESETS[$template] ?? null;
    }

    /**
     * Whether a template service with this identifier and address may render:
     * a preset needs its identifier (and its address), a website's own template
     * accepts a missing identifier.
     */
    public static function valid(string $template, string $id, string $url): bool
    {
        $preset = self::get($template);

        if (null === $preset) {
            return '' === $id || 1 === preg_match(self::OTHER_ID, $id);
        }

        return 1 === preg_match($preset['id'], $id) && (!$preset['url'] || null !== self::baseUrl($url));
    }

    /**
     * The address of an installation as the templates use it: http or https,
     * with a host, without query string or fragment, ending with a slash.
     */
    public static function baseUrl(string $url): string|null
    {
        $url = trim($url);
        $parts = parse_url($url);

        if (
            !\is_array($parts)
            || !\in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || '' === ($parts['host'] ?? '')
            || isset($parts['user'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || preg_match('/[\s"\'<>\\\\]/', $url)
        ) {
            return null;
        }

        return rtrim($url, '/').'/';
    }

    /**
     * The host of an installation address, e.g. "analytics.example.org".
     */
    public static function host(string $url): string|null
    {
        $base = self::baseUrl($url);

        return null === $base ? null : preg_replace('/^www\./', '', strtolower((string) parse_url($base, PHP_URL_HOST)));
    }
}
