<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The package that Composer installs (git archive with the export-ignore rules of
 * .gitattributes) holds only the bundle, and no file in it carries a secret, a
 * local path, a session link, an editor leftover or a planning reference of
 * another project.
 */
final class ShippedFilesTest extends TestCase
{
    /**
     * Top-level entries that ship.
     */
    private const SHIPS = ['LICENSE', 'README.md', 'composer.json', 'config', 'contao', 'public', 'src', 'translations'];

    private const JUNK_NAME = '#(^|/)(\.DS_Store|Thumbs\.db|desktop\.ini|\.phpunit\.result\.cache|node_modules(/|$)|\.idea/|\.vscode/|[^/]*(\.orig|\.rej|\.bak|\.swp|\.swo|\.log|\.map|~))$#i';

    /**
     * @var array<string, string>
     */
    private const FORBIDDEN = [
        'private key block' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
        'API token' => '/\b(?:sk|rk)_(?:live|test)_[0-9A-Za-z]{16,}|\bwhsec_[0-9A-Za-z]{16,}|\bgh[pousr]_[0-9A-Za-z]{30,}|\bgithub_pat_[0-9A-Za-z_]{30,}|\bAKIA[0-9A-Z]{16}\b|\bxox[abpr]-[0-9A-Za-z-]{10,}/',
        'local path' => '#/home/[a-z_][a-z0-9_-]*/|/Users/[A-Za-z][\w.-]*/|/mnt/[a-z]/Users/|[A-Za-z]:\\\\Users\\\\|\blocal/contao-#',
        'session link' => '#claude\.ai/(?:code/)?(?:session|chat)|chatgpt\.com/(?:c|share)/#i',
        'planning reference' => '/\b[A-Z]{3}-\d{2,5}\b|\bG\d{2}\b|docs\/implementation|AGENTS\.md/',
    ];

    public function testOnlyTheBundleShipsAndNothingInItLeaks(): void
    {
        $root = \dirname(__DIR__);
        if (!is_dir($root.'/.git')) {
            $this->markTestSkipped('not a git checkout');
        }
        $problems = [];

        foreach ($this->shippedFiles($root) as $path) {
            foreach ($this->problems($path, (string) file_get_contents($root.'/'.$path)) as $problem) {
                $problems[] = $path.': '.$problem;
            }
        }

        $this->assertSame([], $problems, 'Fix the file, or add it to .gitattributes with export-ignore.');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refused(): iterable
    {
        yield 'outside the allowlist' => ['notes.md', "x\n", 'not a shipped path'];
        yield 'editor leftover' => ['src/Foo.php.orig', "<?php\n", 'editor or tool leftover'];
        yield 'private key' => ['config/key.php', "-----BEGIN PRIVATE KEY-----\n", 'private key block'];
        yield 'API token' => ['src/Foo.php', '$k = "gh'.'p_'.str_repeat('a1B2', 9).'";', 'API token'];
        yield 'local path' => ['README.md', "Run it in /home/someone/projects/x\n", 'local path'];
        yield 'session link' => ['src/Foo.php', "// https://claude.ai/code/session_0123\n", 'session link'];
        yield 'planning id' => ['src/Foo.php', "// see ABC-1234\n", 'planning reference'];
        yield 'gate id' => ['README.md', "Decided in G12.\n", 'planning reference'];
    }

    #[DataProvider('refused')]
    public function testRefuses(string $path, string $bytes, string $expected): void
    {
        $this->assertStringContainsString($expected, implode("\n", $this->problems($path, $bytes)));
    }

    public function testAllowsTheBundleItself(): void
    {
        $this->assertSame([], $this->problems('contao/templates/juhe_consent/placeholder.html.twig', "<div class=\"juhe-consent-placeholder\"></div>\n"));
        $this->assertSame([], $this->problems('README.md', "Requires Contao 5.7 or 6.0 and PHP 8.3.\n"));
    }

    /**
     * @return list<string>
     */
    private function problems(string $path, string $bytes): array
    {
        $problems = [];
        $top = explode('/', $path, 2)[0];
        if (!\in_array($top, self::SHIPS, true)) {
            $problems[] = 'not a shipped path';
        }
        if (1 === preg_match(self::JUNK_NAME, $path)) {
            $problems[] = 'editor or tool leftover';
        }

        foreach (self::FORBIDDEN as $reason => $pattern) {
            if (1 === preg_match($pattern, $bytes, $match, PREG_OFFSET_CAPTURE)) {
                $problems[] = $reason.' on line '.(substr_count($bytes, "\n", 0, $match[0][1]) + 1);
            }
        }

        return $problems;
    }

    /**
     * Tracked files that git archive keeps: neither the file nor a parent directory
     * is export-ignored.
     *
     * @return list<string>
     */
    private function shippedFiles(string $root): array
    {
        exec('git -C '.escapeshellarg($root).' ls-files 2>/dev/null', $files, $status);
        $this->assertSame(0, $status, 'git ls-files failed');
        $paths = [];

        foreach ($files as $file) {
            for ($path = $file; '.' !== $path; $path = \dirname($path)) {
                $paths[$path] = true;
            }
        }
        exec('git -C '.escapeshellarg($root).' check-attr export-ignore -- '.implode(' ', array_map('escapeshellarg', array_keys($paths))).' 2>/dev/null', $lines);
        $ignored = [];

        foreach ($lines as $line) {
            if (str_ends_with($line, ': export-ignore: set')) {
                $ignored[substr($line, 0, -\strlen(': export-ignore: set'))] = true;
            }
        }

        return array_values(array_filter($files, static function (string $file) use ($ignored, $root): bool {
            for ($path = $file; '.' !== $path; $path = \dirname($path)) {
                if (isset($ignored[$path])) {
                    return false;
                }
            }

            return is_file($root.'/'.$file) && !is_link($root.'/'.$file);
        }));
    }
}
