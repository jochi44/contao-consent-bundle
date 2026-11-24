<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\Consent;

use JuheItSolutions\JuheConsentBundle\Consent\ConsentUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConsentUrlTest extends TestCase
{
    #[DataProvider('refused')]
    public function testDangerousOrMalformedTargetsAreRefused(string $raw): void
    {
        $this->assertSame('', ConsentUrl::safe($raw), $raw);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'mixed case' => ['JaVaScRiPt:alert(1)'];
        yield 'leading space' => [' javascript:alert(1)'];
        yield 'tab inside scheme' => ["java\tscript:alert(1)"];
        yield 'newline inside scheme' => ["java\nscript:alert(1)"];
        yield 'entity-encoded tab in scheme' => ['java&#x09;script:alert(1)'];
        yield 'entity-encoded scheme' => ['&#106;avascript:alert(1)'];
        yield 'data url' => ['data:text/html,<script>alert(1)</script>'];
        yield 'vbscript' => ['vbscript:msgbox(1)'];
        yield 'protocol relative' => ['//evil.example/x'];
        yield 'userinfo' => ['https://user:pass@example.org/'];
        yield 'missing host' => ['https:///path'];
        yield 'insert tag' => ['{{link_url::5}}'];
        yield 'backslash' => ['https://example.org\\@evil.example'];
        yield 'dotdot path' => ['/a/../b'];
        yield 'colon in path' => ['foo:bar'];
        yield 'empty' => [''];
        yield 'whitespace only' => ['   '];
    }

    #[DataProvider('allowed')]
    public function testAllowedTargetsPassUnchanged(string $raw): void
    {
        $this->assertSame($raw, ConsentUrl::safe($raw), $raw);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function allowed(): iterable
    {
        yield 'https' => ['https://example.org/privacy'];
        yield 'http with query and fragment' => ['http://example.org/p?a=1&amp;b=2#f'];
        yield 'site path' => ['/datenschutz'];
        yield 'fragment' => ['#main'];
        yield 'relative path' => ['datenschutz'];
        yield 'mailto' => ['mailto:a@example.org'];
        yield 'entity-encoded mailto' => ['mailto:a&#64;example&#46;org'];
        yield 'tel' => ['tel:+4312345678'];
    }
}
