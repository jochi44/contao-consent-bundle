<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['MOD']['consent'] = ['Einwilligung', 'Consent-Banner, Gruppen und Dienste'];
$GLOBALS['TL_LANG']['MOD']['consent_log'] = ['Einwilligungs-Protokoll', 'Anonymes Protokoll der Einwilligungen'];
$GLOBALS['TL_LANG']['MOD']['tl_consent_group'] = 'Gruppen';
$GLOBALS['TL_LANG']['MOD']['tl_consent_service'] = 'Dienste';
$GLOBALS['TL_LANG']['CTE']['consent_settings'] = ['Cookie-Einstellungen', 'Link, der die Einwilligungs-Einstellungen öffnet.'];

$GLOBALS['TL_LANG']['tl_consent_banner']['title_legend'] = 'Banner';
$GLOBALS['TL_LANG']['tl_consent_banner']['text_legend'] = 'Texte';
$GLOBALS['TL_LANG']['tl_consent_banner']['link_legend'] = 'Links';
$GLOBALS['TL_LANG']['tl_consent_banner']['version_legend'] = 'Version';
$GLOBALS['TL_LANG']['tl_consent_banner']['title'] = ['Titel', 'Nur im Backend, z. B. "Website DE".'];
$GLOBALS['TL_LANG']['tl_consent_banner']['headline'] = ['Überschrift', 'z. B. "Ihre Privatsphäre".'];
$GLOBALS['TL_LANG']['tl_consent_banner']['text'] = ['Text', 'Erklärt kurz, wofür um Einwilligung gebeten wird.'];
$GLOBALS['TL_LANG']['tl_consent_banner']['placeholderText'] = ['Text für blockierte Inhalte', 'Steht anstelle von Videos, Karten usw., solange keine Einwilligung besteht. ##service## wird durch den Namen des Dienstes ersetzt. Leer = Standardtext.'];
$GLOBALS['TL_LANG']['tl_consent_banner']['privacyPage'] = ['Datenschutzerklärung', ''];
$GLOBALS['TL_LANG']['tl_consent_banner']['imprintPage'] = ['Impressum', ''];
$GLOBALS['TL_LANG']['tl_consent_banner']['version'] = ['Version', 'Erhöhen, wenn sich Gruppen oder Dienste wesentlich ändern: alle Besucher werden dann erneut gefragt.'];

$GLOBALS['TL_LANG']['tl_consent_group']['title_legend'] = 'Gruppe';
$GLOBALS['TL_LANG']['tl_consent_group']['text_legend'] = 'Beschreibung';
$GLOBALS['TL_LANG']['tl_consent_group']['publish_legend'] = 'Veröffentlichung';
$GLOBALS['TL_LANG']['tl_consent_group']['title'] = ['Titel', 'z. B. "Statistik".'];
$GLOBALS['TL_LANG']['tl_consent_group']['alias'] = ['Kennung', 'Kurz und eindeutig im Banner, z. B. "statistik". Wird im Einwilligungs-Cookie gespeichert; Skripte fragen damit ab.'];
$GLOBALS['TL_LANG']['tl_consent_group']['essential'] = ['Technisch notwendig', 'Immer aktiv, kann nicht abgewählt werden.'];
$GLOBALS['TL_LANG']['tl_consent_group']['description'] = ['Beschreibung', ''];
$GLOBALS['TL_LANG']['tl_consent_group']['published'] = ['Veröffentlicht', ''];
$GLOBALS['TL_LANG']['tl_consent_group']['aliasExists'] = 'Die Kennung "%s" gibt es in diesem Banner schon.';

$GLOBALS['TL_LANG']['tl_consent_service']['title_legend'] = 'Dienst';
$GLOBALS['TL_LANG']['tl_consent_service']['text_legend'] = 'Angaben für Besucher';
$GLOBALS['TL_LANG']['tl_consent_service']['blocking_legend'] = 'Blockieren und Laden';
$GLOBALS['TL_LANG']['tl_consent_service']['publish_legend'] = 'Veröffentlichung';
$GLOBALS['TL_LANG']['tl_consent_service']['title'] = ['Name', 'z. B. "YouTube".'];
$GLOBALS['TL_LANG']['tl_consent_service']['provider'] = ['Anbieter', 'z. B. "Google Ireland Limited, Gordon House, Dublin 4, Irland".'];
$GLOBALS['TL_LANG']['tl_consent_service']['privacyUrl'] = ['Datenschutz des Anbieters', 'URL der Datenschutzerklärung des Anbieters.'];
$GLOBALS['TL_LANG']['tl_consent_service']['description'] = ['Zweck', 'Wofür der Dienst eingesetzt wird.'];
$GLOBALS['TL_LANG']['tl_consent_service']['cookies'] = ['Cookies', 'Ein Cookie pro Zeile: "Name: Speicherdauer", z. B. "_ga: 2 Jahre". Wird angezeigt und beim Widerruf gelöscht (soweit möglich).'];
$GLOBALS['TL_LANG']['tl_consent_service']['hosts'] = ['Blockierte Domains', 'Eine Domain pro Zeile, z. B. "youtube.com". Iframes und Skripte von diesen Domains (inkl. Subdomains) laden erst nach Einwilligung.'];
$GLOBALS['TL_LANG']['tl_consent_service']['code'] = ['Code nach Einwilligung', 'HTML/JavaScript, das nach der Einwilligung am Ende der Seite eingefügt wird, z. B. das Tracking-Skript.'];
$GLOBALS['TL_LANG']['tl_consent_service']['published'] = ['Veröffentlicht', ''];

$GLOBALS['TL_LANG']['tl_consent_log']['tstamp'] = ['Zeitpunkt', ''];
$GLOBALS['TL_LANG']['tl_consent_log']['pid'] = ['Banner', ''];
$GLOBALS['TL_LANG']['tl_consent_log']['version'] = ['Version', ''];
$GLOBALS['TL_LANG']['tl_consent_log']['consentId'] = ['Einwilligungs-ID', 'Zufällige ID aus dem Cookie des Besuchers.'];
$GLOBALS['TL_LANG']['tl_consent_log']['groups'] = ['Gruppen', ''];
$GLOBALS['TL_LANG']['tl_consent_log']['services'] = ['Einzeln erlaubte Dienste', 'IDs der Dienste, die über "Immer erlauben" freigegeben wurden.'];
$GLOBALS['TL_LANG']['tl_consent_log']['url'] = ['Seite', ''];

$GLOBALS['TL_LANG']['tl_page']['consent_legend'] = 'Einwilligung';
$GLOBALS['TL_LANG']['tl_page']['consentBanner'] = ['Consent-Banner', 'Der Banner für diese Website. Leer = kein Banner.'];
