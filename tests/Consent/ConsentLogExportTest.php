<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\Consent;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use JuheItSolutions\JuheConsentBundle\Consent\ConsentLogExport;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ConsentLogExportTest extends TestCase
{
    #[TestWith(['=SUM(A1)', "'=SUM(A1)"])]
    #[TestWith(['+1', "'+1"])]
    #[TestWith(['-1', "'-1"])]
    #[TestWith(['@cmd', "'@cmd"])]
    #[TestWith(["\tx", "'\tx"])]
    #[TestWith(["\rx", "'\rx"])]
    #[TestWith(['/kontakt', '/kontakt'])]
    #[TestWith(['a=b', 'a=b'])]
    #[TestWith(['', ''])]
    #[TestWith([12, '12'])]
    public function testCellsThatLookLikeFormulasBecomeText(mixed $value, string $expected): void
    {
        $this->assertSame($expected, ConsentLogExport::cell($value));
    }

    public function testTakesTheFilterAndSearchOfTheList(): void
    {
        $session = [
            'filter' => [
                'tl_juhe_consent_log' => ['pid' => '1', 'version' => 2, 'groups' => 'notwendig', 'limit' => '0,30', 'url' => '/x'],
                'tl_other' => ['pid' => '9'],
            ],
            'search' => ['tl_juhe_consent_log' => ['field' => 'consentId', 'value' => '^0f8e']],
        ];

        [$where, $values] = $this->export($this->createStub(Connection::class))->conditions($session);

        $this->assertSame(['l.pid = ?', 'l.version = ?', 'l.`groups` = ?', 'LOWER(CAST(l.consentId AS CHAR)) REGEXP LOWER(?)'], $where);
        $this->assertSame(['1', '2', 'notwendig', '^0f8e'], $values);
    }

    /**
     * @param array<mixed> $session
     */
    #[TestWith([[]])]
    #[TestWith([['filter' => 'x', 'search' => 'y']])]
    #[TestWith([['filter' => ['tl_juhe_consent_log' => ['pid' => ['1'], 'tstamp' => '5']]]])]
    #[TestWith([['search' => ['tl_juhe_consent_log' => ['field' => 'url', 'value' => 'x']]]])]
    #[TestWith([['search' => ['tl_juhe_consent_log' => ['field' => 'consentId; DROP', 'value' => 'x']]]])]
    #[TestWith([['search' => ['tl_juhe_consent_log' => ['field' => 'consentId', 'value' => '']]]])]
    public function testIgnoresAnythingElse(array $session): void
    {
        $this->assertSame([[], []], $this->export($this->createStub(Connection::class))->conditions($session));
    }

    public function testSearchesPlainTextIfTheValueIsNoRegularExpression(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willThrowException($this->createStub(DriverException::class));

        [, $values] = $this->export($connection)->conditions(['search' => ['tl_juhe_consent_log' => ['field' => 'consentId', 'value' => 'a(b']]]);

        $this->assertSame(['a\(b'], $values);
    }

    public function testStreamsTheRowsInChunksNewestFirst(): void
    {
        $queries = [];
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnCallback(static function (string $sql, array $params) use (&$queries): array {
                $queries[] = [$sql, $params];

                // 1000 rows from id 1500 down, then the last 3
                $first = 1 === \count($queries) ? 1500 : 500;
                $count = 1 === \count($queries) ? 1000 : 3;

                return array_map(static fn (int $id): array => [
                    'id' => $id, 'tstamp' => 1793102400, 'pid' => 1, 'title' => '=Banner', 'version' => 2,
                    'consentId' => '0f8e7d6c-5b4a-4321-8abc-0123456789ab', 'groups' => 'notwendig,marketing', 'services' => '', 'url' => '/kontakt',
                ], range($first, $first - $count + 1));
            })
        ;

        $chunks = 0;
        $stream = fopen('php://memory', 'w+');
        $this->export($connection)->write($stream, ['l.pid = ?'], ['1'], static function () use (&$chunks): void {
            ++$chunks;
        });
        rewind($stream);
        $csv = stream_get_contents($stream);

        $this->assertCount(2, $queries);
        $this->assertSame(2, $chunks);
        $this->assertStringContainsString('WHERE l.pid = ? AND l.id < ? ORDER BY l.id DESC LIMIT 1000', $queries[0][0]);
        $this->assertSame(['1', PHP_INT_MAX], $queries[0][1]);
        $this->assertSame(['1', 501], $queries[1][1]);
        // The log has no IP address or user agent, and the export reads only these columns
        $this->assertStringContainsString('SELECT l.id, l.tstamp, l.pid, b.title, l.version, l.consentId, l.`groups`, l.services, l.url FROM', $queries[0][0]);

        $this->assertStringStartsWith("\u{FEFF}id,time,banner_id,banner,version,consent_id,groups,services,page\r\n", $csv);
        $lines = explode("\r\n", rtrim(substr($csv, 3), "\r\n"));
        $this->assertCount(1004, $lines);
        $this->assertSame("1500,".date('c', 1793102400).",1,'=Banner,2,0f8e7d6c-5b4a-4321-8abc-0123456789ab,\"notwendig,marketing\",,/kontakt", $lines[1]);
        $this->assertStringStartsWith('498,', $lines[1003]);
    }

    public function testWritesOnlyTheHeaderForAnEmptyLog(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);
        $stream = fopen('php://memory', 'w+');

        $this->export($connection)->write($stream, [], []);
        rewind($stream);

        $this->assertSame("\u{FEFF}".implode(',', ConsentLogExport::COLUMNS)."\r\n", stream_get_contents($stream));
    }

    private function export(Connection $connection): ConsentLogExport
    {
        return new ConsentLogExport($connection);
    }
}
