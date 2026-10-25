<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

// What visitors agree to, e.g. "Statistik" or "Marketing"
$GLOBALS['TL_DCA']['tl_consent_group'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'ptable' => 'tl_consent_banner',
        'ctable' => ['tl_consent_service'],
        'switchToEdit' => true,
        'enableVersioning' => true,
        'sql' => ['keys' => ['id' => 'primary', 'pid' => 'index']],
    ],
    'list' => [
        'sorting' => ['mode' => DataContainer::MODE_PARENT, 'fields' => ['sorting'], 'headerFields' => ['title', 'version'], 'panelLayout' => 'limit'],
        'label' => ['fields' => ['title', 'alias'], 'format' => '%s <span class="label-info">[%s]</span>'],
    ],
    'palettes' => [
        'default' => '{title_legend},title,alias,essential;{text_legend},description;{publish_legend},published',
    ],
    'fields' => [
        'id' => ['sql' => ['type' => 'integer', 'unsigned' => true, 'autoincrement' => true]],
        'pid' => ['foreignKey' => 'tl_consent_banner.title', 'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0], 'relation' => ['type' => 'belongsTo', 'load' => 'lazy']],
        'sorting' => ['sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0]],
        'tstamp' => ['sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0]],
        'title' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64, 'tl_class' => 'w50'],
            'sql' => ['type' => 'string', 'length' => 64, 'default' => ''],
        ],
        'alias' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'rgxp' => 'alias', 'maxlength' => 32, 'tl_class' => 'w50'],
            'sql' => ['type' => 'string', 'length' => 32, 'default' => ''],
        ],
        'essential' => [
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50 m12'],
            'sql' => ['type' => 'boolean', 'default' => false],
        ],
        'description' => [
            'inputType' => 'textarea',
            'eval' => ['maxlength' => 1000, 'tl_class' => 'clr'],
            'sql' => ['type' => 'text', 'notnull' => false],
        ],
        'published' => [
            'toggle' => true,
            'inputType' => 'checkbox',
            'eval' => ['doNotCopy' => true],
            'sql' => ['type' => 'boolean', 'default' => false],
        ],
    ],
];
