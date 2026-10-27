<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * All output comes from Twig templates: no legacy .html5 or .xhtml templates,
 * no Contao template classes, no legacy module, element or widget classes, and
 * no HTML built in PHP strings. The blocker is the one exception: it rewrites
 * the attributes of markup the editor wrote.
 */
final class TwigOnlyTest extends TestCase
{
    private const HTML_ALLOWED = ['src/Consent/HtmlBlocker.php'];

    private const SKIP_DIRS = ['.git', 'vendor', 'node_modules'];

    public function testNoLegacyTemplateFiles(): void
    {
        $root = \dirname(__DIR__);
        $found = [];

        foreach ($this->files($root) as $path) {
            if (preg_match('/\.(html5|xhtml)$/i', $path)) {
                $found[] = $path;
            }

            // Templates of the bundle are Twig
            if (str_starts_with($path, 'contao/templates/') && !preg_match('/(\.twig|\/\.twig-root)$/', $path)) {
                $found[] = $path;
            }
        }

        $this->assertSame([], $found, 'Use a Twig template (.html.twig) instead.');
    }

    public function testTheSourceIsTwigOnly(): void
    {
        $root = \dirname(__DIR__);
        $problems = [];

        foreach ($this->files($root.'/src') as $path) {
            if (str_ends_with($path, '.php')) {
                foreach ($this->problems('src/'.$path, (string) file_get_contents($root.'/src/'.$path)) as $problem) {
                    $problems[] = 'src/'.$path.': '.$problem;
                }
            }
        }

        $this->assertSame([], $problems, 'Render a Twig template instead.');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        yield 'FrontendTemplate' => ["<?php\nuse Contao\\FrontendTemplate;\n\$t = new FrontendTemplate('x');\n", 'template class'];
        yield 'BackendTemplate' => ["<?php\n\$t = new \\Contao\\BackendTemplate('be_x');\n", 'template class'];
        yield 'Contao Template' => ["<?php\nuse Contao\\Template;\n", 'template class'];
        yield 'legacy module' => ["<?php\nuse Contao\\Module;\nclass Foo extends Module {}\n", 'legacy class'];
        yield 'legacy element' => ["<?php\nclass Foo extends \\Contao\\ContentElement {}\n", 'legacy class'];
        yield 'legacy widget' => ["<?php\nuse Contao\\Widget as Base;\nclass Foo extends Base {}\n", 'legacy class'];
        yield 'tag in a string' => ["<?php\nreturn '<span class=\"x\">'.\$a.'</span>';\n", 'HTML in a PHP string'];
        yield 'tag in sprintf' => ["<?php\nreturn sprintf('<a href=\"%s\">', \$u);\n", 'HTML in a PHP string'];
        yield 'tag in a heredoc' => ["<?php\nreturn <<<HTML\n<div>\$x</div>\nHTML;\n", 'HTML in a PHP string'];
        yield 'bare tag' => ["<?php\nreturn \"<br>\";\n", 'HTML in a PHP string'];
    }

    #[DataProvider('refused')]
    public function testRefuses(string $php, string $expected): void
    {
        $this->assertStringContainsString($expected, implode("\n", $this->problems('src/Foo.php', $php)));
    }

    public function testAllowsSearchingMarkupAndDocBlocks(): void
    {
        $php = "<?php\n/** @return list<int> a <template> in a comment */\n\$p = strripos(\$html, '</head>');\npreg_match('/<body\\\\b/i', \$html);\n\$t = \$this->twig->render('@Contao/x.html.twig');\n";

        $this->assertSame([], $this->problems('src/Foo.php', $php));
    }

    public function testTheBlockerIsTheOnlyException(): void
    {
        $this->assertSame([], $this->problems('src/Consent/HtmlBlocker.php', "<?php\nreturn '<script type=\"text/plain\">';\n"));
    }

    /**
     * @return list<string>
     */
    private function problems(string $path, string $php): array
    {
        $problems = [];
        $code = '';
        $strings = [];

        foreach (token_get_all($php) as $token) {
            if (!\is_array($token)) {
                $code .= $token;
                continue;
            }

            [$id, $text] = $token;

            if (\in_array($id, [T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML], true)) {
                continue;
            }

            if (\in_array($id, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                $strings[] = $text;
            }

            $code .= $text;
        }

        if (preg_match('/\b(?:FrontendTemplate|BackendTemplate)\b|\bContao\\\\Template\b(?!\w)/', $code)) {
            $problems[] = 'template class';
        }

        // "extends \Contao\Module", or "use Contao\Module [as Base]" and "extends Module" (or "Base")
        $legacy = '(Module|ContentElement|Widget|Hybrid)';
        $parents = [];

        if (preg_match('/\bextends\s+\\\\?Contao\\\\'.$legacy.'\b/', $code)) {
            $problems[] = 'legacy class';
        } elseif (preg_match_all('/\buse\s+\\\\?Contao\\\\'.$legacy.'(?:\s+as\s+(\w+))?\s*;/', $code, $uses, PREG_SET_ORDER)) {
            foreach ($uses as $use) {
                $parents[] = preg_quote($use[2] ?? $use[1], '/');
            }

            if (preg_match('/\bextends\s+(?:'.implode('|', $parents).')\b/', $code)) {
                $problems[] = 'legacy class';
            }
        }

        if (!\in_array($path, self::HTML_ALLOWED, true)) {
            foreach ($strings as $string) {
                // An opening tag: "<span", "<a href=", "<br>"; closing tags and "<body\b" in a search stay allowed
                if (preg_match('/<[a-z][a-z0-9-]*(?:\s+[a-z@:_-][^<>]*)?\/?>|<[a-z][a-z0-9-]*\s+[a-z_:-]+\s*=/i', stripcslashes($string))) {
                    $problems[] = 'HTML in a PHP string: '.trim(substr($string, 0, 60));
                    break;
                }
            }
        }

        return $problems;
    }

    /**
     * @return \Generator<string> paths relative to $dir
     */
    private function files(string $dir): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                static fn (\SplFileInfo $file): bool => !($file->isDir() && \in_array($file->getFilename(), self::SKIP_DIRS, true)),
            ),
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                yield str_replace('\\', '/', substr($file->getPathname(), \strlen($dir) + 1));
            }
        }
    }
}
