<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

// Consent choices without IP addresses or user agents; the random ID is pseudonymous
$GLOBALS['TL_DCA']['tl_juhe_consent_log'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'closed' => true,
        'notEditable' => true,
        'notCopyable' => true,
        'sql' => ['keys' => ['id' => 'primary', 'consentId' => 'index', 'tstamp' => 'index']],
    ],
    'list' => [
        'sorting' => ['mode' => DataContainer::MODE_SORTED, 'fields' => ['tstamp'], 'flag' => DataContainer::SORT_DAY_DESC, 'panelLayout' => 'filter;search,limit'],
        'label' => ['fields' => ['tstamp', 'consentId', 'groups', 'version'], 'showColumns' => true],
        'operations' => ['show', 'delete'],
    ],
    'fields' => [
        'id' => ['sql' => ['type' => 'integer', 'unsigned' => true, 'autoincrement' => true]],
        'tstamp' => ['flag' => DataContainer::SORT_DAY_DESC, 'eval' => ['rgxp' => 'datim'], 'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0]],
        'pid' => ['filter' => true, 'foreignKey' => 'tl_juhe_consent_banner.title', 'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0], 'relation' => ['type' => 'belongsTo', 'load' => 'lazy']],
        'version' => ['filter' => true, 'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0]],
        'consentId' => ['search' => true, 'sql' => ['type' => 'string', 'length' => 36, 'default' => '', 'platformOptions' => ['collation' => 'ascii_bin']]],
        // Text columns: the 4 KiB limit of a log post bounds both lists
        'groups' => ['filter' => true, 'sql' => ['type' => 'text', 'notnull' => false]],
        // Services allowed one by one via "always allow" on a placeholder ("s<id>")
        'services' => ['sql' => ['type' => 'text', 'notnull' => false]],
        'url' => ['sql' => ['type' => 'string', 'length' => 255, 'default' => '']],
    ],
];
