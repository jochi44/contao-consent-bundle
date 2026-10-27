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
        'label' => ['fields' => ['title', 'version'], 'format' => '%s <span class="label-info">(v%s)</span>'],
    ],
    'palettes' => [
        'default' => '{title_legend},title;{text_legend},headline,text,infoText,placeholderText,askOnFirstVisit;{layout_legend},layout,blocking;{pages_legend:hide},excludePages;{link_legend},privacyPage,imprintPage,infoPages;{version_legend},version',
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
        'infoText' => [
            'inputType' => 'textarea',
            'eval' => ['rte' => 'tinyMCE', 'tl_class' => 'clr'],
            'sql' => ['type' => 'text', 'notnull' => false],
        ],
        'placeholderText' => [
            'inputType' => 'textarea',
            'eval' => ['maxlength' => 500, 'tl_class' => 'clr'],
            'sql' => ['type' => 'text', 'notnull' => false],
        ],
        'askOnFirstVisit' => [
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50 m12 clr'],
            'sql' => ['type' => 'boolean', 'default' => true],
        ],
        'layout' => [
            'inputType' => 'select',
            'options' => ['card', 'bar'],
            'reference' => &$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['layoutOptions'],
            'eval' => ['tl_class' => 'w50'],
            'sql' => ['type' => 'string', 'length' => 8, 'default' => 'card'],
        ],
        'blocking' => [
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50 m12'],
            'sql' => ['type' => 'boolean', 'default' => false],
        ],
        'excludePages' => [
            'inputType' => 'pageTree',
            'foreignKey' => 'tl_page.title',
            'eval' => ['multiple' => true, 'fieldType' => 'checkbox', 'tl_class' => 'clr'],
            'sql' => ['type' => 'blob', 'notnull' => false],
            'relation' => ['type' => 'hasMany', 'load' => 'lazy'],
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
        'infoPages' => [
            'inputType' => 'pageTree',
            'foreignKey' => 'tl_page.title',
            'eval' => ['multiple' => true, 'fieldType' => 'checkbox', 'isSortable' => true, 'tl_class' => 'clr'],
            'sql' => ['type' => 'blob', 'notnull' => false],
            'relation' => ['type' => 'hasMany', 'load' => 'lazy'],
        ],
        'version' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'rgxp' => 'natural', 'minval' => 1, 'maxlength' => 6, 'tl_class' => 'w50'],
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 1],
        ],
    ],
];
