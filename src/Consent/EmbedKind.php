<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Consent;

/**
 * What a blocked iframe shows, for the placeholder's glyph: a video, a map, an
 * audio player or any other embedded content. Taken from the iframe's host (and
 * path, for Google Maps on google.com); an unknown host is "embed".
 */
final class EmbedKind
{
    public const VIDEO = 'video';

    public const MAP = 'map';

    public const AUDIO = 'audio';

    public const EMBED = 'embed';

    /**
     * Hosts per kind; a host also covers its subdomains.
     */
    private const HOSTS = [
        self::VIDEO => ['youtube.com', 'youtube-nocookie.com', 'youtu.be', 'vimeo.com', 'dailymotion.com', 'dai.ly', 'wistia.com', 'wistia.net', 'twitch.tv', 'loom.com', 'vidyard.com', 'ted.com'],
        self::MAP => ['openstreetmap.org', 'umap.openstreetmap.fr', 'maps.google.com', 'mapbox.com', 'here.com', 'bing.com/maps', 'komoot.com', 'komoot.de'],
        self::AUDIO => ['spotify.com', 'soundcloud.com', 'mixcloud.com', 'bandcamp.com', 'podigee.io', 'podbean.com', 'anchor.fm', 'podcasters.spotify.com'],
    ];

    public static function fromIframe(string $iframe): string
    {
        if (!preg_match('/\ssrc\s*=\s*(["\'])(.*?)\1/i', $iframe, $src)) {
            return self::EMBED;
        }

        // The host as the blocker and the browser read it ("https:///www.youtube.com/..." included)
        $url = html_entity_decode($src[2], ENT_QUOTES | ENT_HTML5);
        $host = HtmlBlocker::urlHost($url);

        if (null === $host) {
            return self::EMBED;
        }

        $rest = ltrim((string) preg_replace('#^[a-z][a-z0-9+.-]*:#i', '', str_replace('\\', '/', trim($url))), '/');
        $path = strtolower((string) preg_replace('#[?\#].*$#s', '', (string) strpbrk($rest, '/?#')));

        // google.com serves maps under /maps; the rest of google.com is something else
        if (preg_match('/(^|\.)google\.[a-z.]+$/', $host) && str_starts_with($path, '/maps')) {
            return self::MAP;
        }

        foreach (self::HOSTS as $kind => $hosts) {
            foreach ($hosts as $entry) {
                [$entryHost, $entryPath] = explode('/', $entry, 2) + [1 => ''];

                if (($host === $entryHost || str_ends_with($host, '.'.$entryHost)) && ('' === $entryPath || str_starts_with($path, '/'.$entryPath))) {
                    return $kind;
                }
            }
        }

        return self::EMBED;
    }
}
