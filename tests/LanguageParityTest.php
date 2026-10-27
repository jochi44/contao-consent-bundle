<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * German, English and Italian are complete: every label and every visitor text
 * exists in all three languages.
 */
final class LanguageParityTest extends TestCase
{
    private const LANGUAGES = ['de', 'en', 'it'];

    public function testBackendLabelsExistInEveryLanguage(): void
    {
        $keys = [];

        foreach (self::LANGUAGES as $language) {
            $keys[$language] = $this->backendKeys($language);
        }

        foreach (self::LANGUAGES as $language) {
            $this->assertSame($keys['de'], $keys[$language], $language);
        }
    }

    #[DataProvider('languages')]
    public function testBackendLabelsAreFilledIn(string $language): void
    {
        $labels = $this->backendLabels($language);

        array_walk_recursive($labels, function (string $value, int|string $key) use ($language): void {
            // Help texts may stay empty (the second entry of a field label), labels may not
            if (1 !== $key) {
                $this->assertNotSame('', trim($value), $language);
            }
        });
    }

    public function testVisitorTextsExistInEveryLanguage(): void
    {
        $keys = [];

        foreach (self::LANGUAGES as $language) {
            $texts = $this->visitorTexts($language);
            $this->assertNotContains('', $texts, $language);
            $keys[$language] = array_keys($texts);
            sort($keys[$language]);
        }

        foreach (self::LANGUAGES as $language) {
            $this->assertSame($keys['de'], $keys[$language], $language);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function languages(): iterable
    {
        foreach (self::LANGUAGES as $language) {
            yield $language => [$language];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function backendLabels(string $language): array
    {
        $backup = $GLOBALS['TL_LANG'] ?? null;
        $GLOBALS['TL_LANG'] = [];

        require __DIR__.'/../contao/languages/'.$language.'/default.php';

        $labels = $GLOBALS['TL_LANG'];
        $GLOBALS['TL_LANG'] = $backup;

        return $labels;
    }

    /**
     * @return list<string>
     */
    private function backendKeys(string $language): array
    {
        $keys = [];
        $walk = static function (array $labels, string $prefix) use (&$walk, &$keys): void {
            foreach ($labels as $key => $value) {
                // A field label is [label, help]; its parts are not separate keys
                if (\is_array($value) && !array_is_list($value)) {
                    $walk($value, $prefix.$key.'.');
                } else {
                    $keys[] = $prefix.$key;
                }
            }
        };
        $walk($this->backendLabels($language), '');
        sort($keys);

        return $keys;
    }

    /**
     * @return array<string, string>
     */
    private function visitorTexts(string $language): array
    {
        preg_match_all("/^([a-z_]+): '(.*)'$/m", (string) file_get_contents(__DIR__.'/../translations/juhe_consent.'.$language.'.yaml'), $matches);

        return array_combine($matches[1], $matches[2]);
    }
}
