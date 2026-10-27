<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Consent;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;

/**
 * Writes the consent log as CSV, in the order of the back end list (newest
 * first) and limited to its current filter and search. Rows are read in chunks,
 * so a large log never sits in memory at once. A cell that a spreadsheet could
 * read as a formula gets a leading apostrophe. The log has no IP address or user
 * agent, and the export adds none.
 */
final class ConsentLogExport
{
    public const TABLE = 'tl_juhe_consent_log';

    public const COLUMNS = ['id', 'time', 'banner_id', 'banner', 'version', 'consent_id', 'groups', 'services', 'page'];

    /**
     * The fields of the filter panel and the search field, as in the DCA.
     * Only these reach the query, as fixed column names.
     */
    private const FILTER_FIELDS = ['pid' => 'l.pid', 'version' => 'l.version', 'groups' => 'l.`groups`'];

    private const SEARCH_FIELDS = ['consentId' => 'l.consentId'];

    private const CHUNK = 1000;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * The conditions of the list from the back end session data (the
     * "contao_backend" bag): the filter panel values and the search.
     *
     * @param array<mixed> $session
     *
     * @return array{0: list<string>, 1: list<string>} the SQL conditions and their values
     */
    public function conditions(array $session): array
    {
        $where = [];
        $values = [];
        $filter = $session['filter'][self::TABLE] ?? [];

        foreach (self::FILTER_FIELDS as $field => $column) {
            if (\is_array($filter) && isset($filter[$field]) && \is_scalar($filter[$field])) {
                $where[] = $column.' = ?';
                $values[] = (string) $filter[$field];
            }
        }

        $search = $session['search'][self::TABLE] ?? [];
        $field = \is_array($search) ? $search['field'] ?? null : null;
        $value = \is_array($search) ? $search['value'] ?? null : null;

        if (\is_string($field) && isset(self::SEARCH_FIELDS[$field]) && \is_scalar($value) && '' !== (string) $value) {
            // Like the list: a regular expression, or the plain text if it is not one
            $where[] = 'LOWER(CAST('.self::SEARCH_FIELDS[$field].' AS CHAR)) REGEXP LOWER(?)';
            $values[] = $this->isRegexp((string) $value) ? (string) $value : preg_quote((string) $value);
        }

        return [$where, $values];
    }

    /**
     * @param resource     $stream
     * @param list<string> $where
     * @param list<string> $values
     */
    public function write($stream, array $where, array $values, \Closure|null $afterChunk = null): void
    {
        // Byte order mark: spreadsheet programs then read the file as UTF-8
        fwrite($stream, "\u{FEFF}");
        $this->line($stream, self::COLUMNS);

        $sql = 'SELECT l.id, l.tstamp, l.pid, b.title, l.version, l.consentId, l.`groups`, l.services, l.url'
            .' FROM tl_juhe_consent_log l LEFT JOIN tl_juhe_consent_banner b ON b.id = l.pid'
            .' WHERE '.implode(' AND ', [...$where, 'l.id < ?'])
            .' ORDER BY l.id DESC LIMIT '.self::CHUNK;

        $last = PHP_INT_MAX;

        do {
            $rows = $this->connection->fetchAllAssociative($sql, [...$values, $last]);

            foreach ($rows as $row) {
                $last = (int) $row['id'];

                $this->line($stream, [
                    $row['id'],
                    date('c', (int) $row['tstamp']),
                    $row['pid'],
                    $row['title'] ?? '',
                    $row['version'],
                    $row['consentId'],
                    $row['groups'] ?? '',
                    $row['services'] ?? '',
                    $row['url'],
                ]);
            }

            if (null !== $afterChunk) {
                $afterChunk();
            }
        } while (\count($rows) === self::CHUNK);
    }

    /**
     * A cell that starts with = + - @, a tab or a carriage return is text, not
     * a formula: it gets a leading apostrophe.
     */
    public static function cell(mixed $value): string
    {
        $value = (string) $value;

        return '' !== $value && str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
    }

    /**
     * @param resource    $stream
     * @param list<mixed> $cells
     */
    private function line($stream, array $cells): void
    {
        fputcsv($stream, array_map(self::cell(...), $cells), ',', '"', '', "\r\n");
    }

    private function isRegexp(string $value): bool
    {
        try {
            $this->connection->fetchOne("SELECT '' REGEXP ?", [$value]);
        } catch (DbalException) {
            return false;
        }

        return true;
    }
}
