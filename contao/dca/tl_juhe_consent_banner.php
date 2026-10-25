<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

// One banner per site (root page): texts, version and its consent groups
$GLOBALS['TL_DCA']['tl_juhe_consent_banner'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'ctable' => ['tl_juhe_consent_group'],
        'switchToEdit' => true,
        'enableVersioning' => true,
        'markAsCopy' => 'title',
        'sql' => ['keys' => ['id' => 'primary']],
    ],
    'list' => [
        'sorting' => ['mode' => DataContainer::MODE_SORTED, 'fields' => ['title'], 'flag' => DataContainer::SORT_INITIAL_LETTER_ASC, 'panelLayout' => 'search,limit'],
        'label' => ['fields' => ['title', 'version'], 'format' => '%s <span class="label-info">(Version %s)</span>'],
    ],
    'palettes' => [
        'default' => '{title_legend},title;{text_legend},headline,text,placeholderText;{link_legend},privacyPage,imprintPage;{version_legend},version',
    ],
    'fields' => [
        'id' => ['sql' => ['type' => 'integer', 'unsigned' => true, 'autoincrement' => true]],
        'tstamp' => ['sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0]],
        'title' => [
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 128, 'tl_class' => 'w50'],
            'sql' => ['type' => 'string', 'length' => 128, 'default' => ''],
        ],
        'headline' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 128, 'tl_class' => 'w50 clr'],
            'sql' => ['type' => 'string', 'length' => 128, 'default' => ''],
        ],
        'text' => [
            'inputType' => 'textarea',
            'eval' => ['mandatory' => true, 'rte' => 'tinyMCE', 'tl_class' => 'clr'],
            'sql' => ['type' => 'text', 'notnull' => false],
        ],
        'placeholderText' => [
            'inputType' => 'textarea',
            'eval' => ['maxlength' => 500, 'tl_class' => 'clr'],
            'sql' => ['type' => 'text', 'notnull' => false],
        ],
        'privacyPage' => [
            'inputType' => 'pageTree',
            'foreignKey' => 'tl_page.title',
            'eval' => ['fieldType' => 'radio', 'tl_class' => 'w50'],
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
            'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
        ],
        'imprintPage' => [
            'inputType' => 'pageTree',
            'foreignKey' => 'tl_page.title',
            'eval' => ['fieldType' => 'radio', 'tl_class' => 'w50'],
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
            'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
        ],
        'version' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'rgxp' => 'natural', 'minval' => 1, 'maxlength' => 6, 'tl_class' => 'w50'],
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 1],
        ],
    ],
];
