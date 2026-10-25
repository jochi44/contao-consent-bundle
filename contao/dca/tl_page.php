<?php

declare(strict_types=1);

use Contao\CoreBundle\DataContainer\PaletteManipulator;

$GLOBALS['TL_DCA']['tl_page']['fields']['consentBanner'] = [
    'inputType' => 'select',
    'foreignKey' => 'tl_consent_banner.title',
    'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
    'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
];

foreach (['root', 'rootfallback'] as $palette) {
    if (isset($GLOBALS['TL_DCA']['tl_page']['palettes'][$palette])) {
        PaletteManipulator::create()
            ->addLegend('consent_legend', 'global_legend', PaletteManipulator::POSITION_AFTER)
            ->addField('consentBanner', 'consent_legend', PaletteManipulator::POSITION_APPEND)
            ->applyToPalette($palette, 'tl_page');
    }
}
