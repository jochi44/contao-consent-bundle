<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;
use JuheItSolutions\JuheConsentBundle\Consent\ConsentConfig;

// One provider in a group: what it loads, which hosts wait for consent, its cookies
$GLOBALS['TL_DCA']['tl_juhe_consent_service'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'ptable' => 'tl_juhe_consent_group',
        'enableVersioning' => true,
        'sql' => ['keys' => ['id' => 'primary', 'pid' => 'index']],
    ],
    'list' => [
        'sorting' => ['mode' => DataContainer::MODE_PARENT, 'fields' => ['sorting'], 'headerFields' => ['title', 'alias'], 'panelLayout' => 'limit'],
        'label' => ['fields' => ['title', 'provider'], 'format' => '%s <span class="label-info">(%s)</span>'],
    ],
    'palettes' => [
        'default' => '{title_legend},title,provider,privacyUrl;{text_legend},description,cookies;{blocking_legend},hosts,code,codePosition,loadOrder;{publish_legend},published',
    ],
    'fields' => [
        'id' => ['sql' => ['type' => 'integer', 'unsigned' => true, 'autoincrement' => true]],
        'pid' => ['foreignKey' => 'tl_juhe_consent_group.title', 'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0], 'relation' => ['type' => 'belongsTo', 'load' => 'lazy']],
        'sorting' => ['sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0]],
        'tstamp' => ['sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0]],
        'title' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 128, 'tl_class' => 'w50'],
            'sql' => ['type' => 'string', 'length' => 128, 'default' => ''],
        ],
        'provider' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => ['type' => 'string', 'length' => 255, 'default' => ''],
        ],
        'privacyUrl' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'httpurl', 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql' => ['type' => 'string', 'length' => 255, 'default' => ''],
        ],
        'description' => [
            'inputType' => 'textarea',
            'eval' => ['maxlength' => 1000, 'tl_class' => 'clr'],
            'sql' => ['type' => 'text', 'notnull' => false],
        ],
        'cookies' => [
            'inputType' => 'textarea',
            'eval' => ['maxlength' => 2000, 'tl_class' => 'clr'],
            'sql' => ['type' => 'text', 'notnull' => false],
        ],
        'hosts' => [
            'inputType' => 'textarea',
            'eval' => ['maxlength' => 2000, 'tl_class' => 'clr'],
            'sql' => ['type' => 'text', 'notnull' => false],
        ],
        'code' => [
            'inputType' => 'textarea',
            'eval' => ['allowHtml' => true, 'preserveTags' => true, 'decodeEntities' => true, 'class' => 'monospace', 'rte' => 'ace|html', 'tl_class' => 'clr'],
            'sql' => ['type' => 'text', 'notnull' => false],
        ],
        'codePosition' => [
            'inputType' => 'select',
            'options' => ConsentConfig::CODE_POSITIONS,
            'reference' => &$GLOBALS['TL_LANG']['tl_juhe_consent_service']['codePositionOptions'],
            'eval' => ['tl_class' => 'w50'],
            'sql' => ['type' => 'string', 'length' => 8, 'default' => 'body'],
        ],
        'loadOrder' => [
            'inputType' => 'text',
            'eval' => ['rgxp' => 'natural', 'maxlength' => 5, 'tl_class' => 'w50'],
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        ],
        'published' => [
            'toggle' => true,
            'inputType' => 'checkbox',
            'eval' => ['doNotCopy' => true],
            'sql' => ['type' => 'boolean', 'default' => false],
        ],
    ],
];
