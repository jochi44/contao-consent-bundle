<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\Consent;

use JuheItSolutions\JuheConsentBundle\Consent\CookieCleanup;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class CookieCleanupTest extends TestCase
{
    public function testOnlyConfiguredNamesThatTheRequestCarries(): void
    {
        $carried = ['_ga', '_ga_ABC123', '_gid', 'PHPSESSID', '_fbp', 'other'];

        $this->assertSame(['_ga', '_ga_ABC123', '_fbp'], CookieCleanup::names(['_ga*', '_fbp', '_gcl_au'], $carried));
    }

    public function testABarePrefixMatchesNothing(): void
    {
        $this->assertSame([], CookieCleanup::names(['*', ''], ['a', 'PHPSESSID']));
    }

    public function testNamesOfEssentialServicesAreKept(): void
    {
        $this->assertSame(['_pk_id'], CookieCleanup::names(['_pk*', 'PHPSESSID'], ['_pk_id', '_pk_ses_keep', 'PHPSESSID'], ['PHPSESSID', '_pk_ses*']));
    }

    #[TestWith(['/', ['/']])]
    #[TestWith(['', ['/']])]
    #[TestWith(['/shop', ['/', '/shop', '/shop/']])]
    #[TestWith(['/shop/cart/', ['/', '/shop', '/shop/', '/shop/cart', '/shop/cart/']])]
    #[TestWith(['//x//y', ['/', '/x', '/x/', '/x/y', '/x/y/']])]
    public function testRootAndEveryParentPath(string $path, array $expected): void
    {
        $this->assertSame($expected, CookieCleanup::paths($path));
    }

    public function testDeepPathsStopAtTheLimit(): void
    {
        $paths = CookieCleanup::paths('/'.implode('/', range(1, 20)));

        $this->assertCount(1 + 2 * CookieCleanup::MAX_DEPTH, $paths);
        $this->assertSame('/1/2/3/4/5/6/7/8/', end($paths));
    }

    #[TestWith(['www.example.org', [null, 'www.example.org', 'example.org']])]
    #[TestWith(['Shop.Example.CO.UK', [null, 'shop.example.co.uk', 'example.co.uk', 'co.uk']])]
    #[TestWith(['example.org', [null, 'example.org']])]
    #[TestWith(['localhost', [null]])]
    #[TestWith(['127.0.0.1', [null]])]
    #[TestWith(['[::1]', [null]])]
    #[TestWith(['', [null]])]
    public function testHostAndParentDomains(string $host, array $expected): void
    {
        $this->assertSame($expected, CookieCleanup::domains($host));
    }

    public function testManyCarriedNamesStopAtTheLimit(): void
    {
        $carried = array_map(static fn (int $i): string => '_ga'.$i, range(1, 1500));

        $names = CookieCleanup::names(['_ga*'], $carried);

        $this->assertCount(CookieCleanup::MAX_NAMES, $names);
        $this->assertSame('_ga1', $names[0]);
    }

    public function testALongHostKeepsItselfAndItsNearestParents(): void
    {
        $host = implode('.', array_map(static fn (int $i): string => 'a'.$i, range(1, 120))).'.example.org';

        $domains = CookieCleanup::domains($host);

        $this->assertCount(2 + CookieCleanup::MAX_DEPTH, $domains);
        $this->assertSame([null, $host], \array_slice($domains, 0, 2));
        $this->assertSame('example.org', end($domains));
    }

    public function testCarriedNamesKeepDotsAndSpaces(): void
    {
        $this->assertSame(['_ga', 'a.b', 'x'], CookieCleanup::carried('_ga=GA1.1; a.b=1;x=2; _ga=again; =broken'));
    }
}
