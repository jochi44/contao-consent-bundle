<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The minified files and public/manifest.json (built by tools/build-assets.mjs)
 * belong to the current sources: each minified file starts with the SHA-256 of
 * its source, and the manifest points at it with the hash of its content.
 */
final class AssetsFreshTest extends TestCase
{
    private const PUBLIC = __DIR__.'/../public/';

    public function testMinifiedFilesMatchTheirSources(): void
    {
        foreach (['juhe-consent.js', 'juhe-consent.css'] as $source) {
            $minified = preg_replace('/\.(js|css)$/', '.min.$1', $source);
            $first = strtok((string) file_get_contents(self::PUBLIC.$minified), "\n");
            $hash = hash_file('sha256', self::PUBLIC.$source);

            $this->assertSame("/*! $source sha256:$hash */", $first, "public/$minified is stale: cd tools && npm ci && node build-assets.mjs");
        }
    }

    public function testTheManifestServesTheMinifiedFilesWithTheirHash(): void
    {
        $manifest = json_decode((string) file_get_contents(self::PUBLIC.'manifest.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['juhe-consent.js', 'juhe-consent.css'], array_keys($manifest));

        foreach ($manifest as $source => $target) {
            $minified = preg_replace('/\.(js|css)$/', '.min.$1', $source);
            $this->assertSame($minified.'?v='.substr(hash_file('sha256', self::PUBLIC.$minified), 0, 10), $target);
        }
    }
}
