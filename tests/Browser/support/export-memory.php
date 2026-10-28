<?php
// Peak memory of the consent log export, written to /dev/null. Argument: "" or "field=value" (a list filter).
require '/var/www/html/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use JuheItSolutions\JuheConsentBundle\Consent\ConsentLogExport;

$connection = DriverManager::getConnection((new DsnParser(['mysql' => 'pdo_mysql']))->parse((string) getenv('DATABASE_URL')));
$export = new ConsentLogExport($connection);
$session = [];

if ('' !== ($argv[1] ?? '')) {
    [$field, $value] = explode('=', $argv[1], 2);
    $session = ['filter' => ['tl_juhe_consent_log' => [$field => $value]]];
}

[$where, $values] = $export->conditions($session);
$rows = (int) $connection->fetchOne('SELECT COUNT(*) FROM tl_juhe_consent_log l'.($where ? ' WHERE '.implode(' AND ', $where) : ''), $values);
$stream = fopen('/dev/null', 'w');
$before = memory_get_usage();
memory_reset_peak_usage();
$export->write($stream, $where, $values);
echo json_encode(['rows' => $rows, 'peak' => memory_get_peak_usage() - $before]), "\n";
