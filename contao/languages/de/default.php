<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['MOD']['juhe_consent'] = ['Einwilligung', 'Consent-Banner, Gruppen und Dienste'];
$GLOBALS['TL_LANG']['MOD']['juhe_consent_log'] = ['Einwilligungs-Protokoll', 'Protokoll der Einwilligungen ohne IP-Adresse oder User-Agent'];
$GLOBALS['TL_LANG']['MOD']['tl_juhe_consent_group'] = 'Gruppen';
$GLOBALS['TL_LANG']['MOD']['tl_juhe_consent_service'] = 'Dienste';
$GLOBALS['TL_LANG']['CTE']['juhe_consent_settings'] = ['Cookie-Einstellungen', 'Link, der die Einwilligungs-Einstellungen öffnet.'];

$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['title_legend'] = 'Banner';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['text_legend'] = 'Texte';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['layout_legend'] = 'Darstellung';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['pages_legend'] = 'Seiten ohne automatisches Öffnen';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['link_legend'] = 'Links';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['version_legend'] = 'Version';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['log_legend'] = 'Einwilligungs-Protokoll';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['title'] = ['Titel', 'Nur im Backend, z. B. "Website DE".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['headline'] = ['Überschrift', 'z. B. "Ihre Privatsphäre".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['text'] = ['Text', 'Erklärt kurz, wofür um Einwilligung gebeten wird.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['infoText'] = ['Weitere Informationen', 'Erscheint in den Einstellungen unter den Gruppen, z. B. ein Hinweis auf Drittländer oder wie man die Auswahl später ändert. Leer = kein Text.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['placeholderText'] = ['Text für blockierte Inhalte', 'Steht anstelle von Videos, Karten usw., solange keine Einwilligung besteht. ##service## wird durch den Namen des Dienstes ersetzt. Leer = Standardtext.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['askOnFirstVisit'] = ['Beim ersten Besuch fragen', 'Zeigt die Karte beim ersten Besuch. Ohne Haken erscheint kein Banner: blockierte Inhalte zeigen nur ihren Platzhalter, die Einstellungen öffnen sich über den Link "Cookie-Einstellungen". Sinnvoll, wenn eine andere Einwilligungslösung (z. B. die von Google für Werbung) schon fragt und hier nur Inhalte zum Anklicken übrig sind.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['privacySignals'] = ['Datenschutzsignale beachten', 'Sendet der Browser Global Privacy Control oder Do Not Track und gibt es noch keine Auswahl, öffnet sich der Banner nicht von selbst und es laufen nur notwendige Dienste. Gespeichert wird dabei nichts. Speichert der Besucher selbst eine Auswahl, gilt diese. Ob das Ihre rechtlichen Pflichten erfüllt, klären Sie bitte mit Ihrer Datenschutzberatung.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['noOptionalForUsers'] = ['Keine optionalen Dienste für angemeldete Benutzer', 'Für angemeldete Mitglieder und für Backend-Benutzer in der Frontend-Vorschau öffnet sich der Banner nicht, und optionale Dienste laufen nicht von selbst. Eine gespeicherte Auswahl bleibt erhalten, gilt aber nicht; eine Auswahl auf der Seite gilt nur für diese Seite. Solche Seiten werden privat ausgeliefert. Mit "Immer aus dem Shared-Cache laden" kann ein Mitglied trotzdem die öffentliche Seite aus dem Cache bekommen.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['layout'] = ['Darstellung', 'Die Karte lässt die Seite frei; die Leiste zeigt die Gruppen gleich mit an. Die Position wählen Sie darunter.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['layoutOptions'] = ['card' => 'Karte', 'bar' => 'Leiste über die ganze Breite'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['position'] = ['Position', 'Wo die Karte sitzt. Die Einstellungen öffnen sich immer in der Mitte.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['positionOptions'] = ['bottom-left' => 'Unten links', 'bottom-center' => 'Unten in der Mitte', 'bottom-right' => 'Unten rechts', 'center' => 'In der Mitte', 'top-left' => 'Oben links', 'top-center' => 'Oben in der Mitte', 'top-right' => 'Oben rechts'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['barPosition'] = ['Position', 'Ob die Leiste am unteren oder oberen Rand sitzt.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['barPositionOptions'] = ['bottom' => 'Am unteren Rand', 'top' => 'Am oberen Rand'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['blocking'] = ['Seite bis zur Entscheidung sperren', 'Dunkelt die Seite beim ersten Besuch ab, bis eine Auswahl getroffen ist. Mit der Esc-Taste wird die Seite trotzdem bedienbar: der Banner bleibt dann sichtbar, sperrt aber nicht mehr.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['excludePages'] = ['Nicht automatisch öffnen auf', 'Auf diesen Seiten öffnet sich der Banner nicht von selbst, z. B. auf einer Kontakt- oder Landingpage. Blockierte Inhalte bleiben blockiert, und die Einstellungen öffnen sich weiterhin über den Link "Cookie-Einstellungen". Datenschutzerklärung und Impressum sperren die Seite ohnehin nie.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['privacyPage'] = ['Datenschutzerklärung', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['imprintPage'] = ['Impressum', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['infoPages'] = ['Weitere Links', 'Seiten, die nach Datenschutzerklärung und Impressum verlinkt werden, in dieser Reihenfolge. Der Link trägt den Seitentitel. Nicht veröffentlichte Seiten fallen weg.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['version'] = ['Version', 'Erhöhen, wenn sich Gruppen oder Dienste wesentlich ändern: alle Besucher werden dann erneut gefragt.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['lifetime'] = ['Auswahl gültig für (Tage)', 'So lange gilt die Auswahl eines Besuchers, danach wird er erneut gefragt. 1 bis 395 Tage (13 Monate), Standard 365. Eine höhere Version fragt unabhängig davon sofort erneut.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['logConsent'] = ['Auswahl protokollieren', 'Legt jede gespeicherte Auswahl im Einwilligungs-Protokoll ab: Banner, Version, eine zufällige ID aus dem Browser des Besuchers, die erlaubten Gruppen und Dienste, die Zeit und, wenn unten gewählt, den Pfad der Seite. Keine IP-Adresse und kein User-Agent. Die zufällige ID ist pseudonym, das Protokoll ist also nicht anonym. Ohne Haken sendet der Browser nichts und der Server speichert nichts.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['logRetention'] = ['Einträge aufbewahren für', 'Ältere Einträge werden einmal täglich gelöscht. Klären Sie die Dauer mit Ihrer Datenschutzberatung.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['logRetentionOptions'] = [6 => '6 Monate', 12 => '12 Monate', 24 => '24 Monate', 36 => '36 Monate'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['logPage'] = ['Seite im Protokoll', 'Pfad: die Adresse der Seite ohne Domain, Parameter und Anker, z. B. "/kontakt". Keine: Es wird keine Seite gespeichert. Wählen Sie "Keine", wenn Seitenpfade personenbezogene Daten enthalten können.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['logPageOptions'] = ['path' => 'Pfad', 'none' => 'Keine'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['usedBy'] = 'Verwendet von:';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['usedByNone'] = 'Keiner Website zugeordnet';

$GLOBALS['TL_LANG']['tl_juhe_consent_group']['title_legend'] = 'Gruppe';
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['text_legend'] = 'Beschreibung';
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['publish_legend'] = 'Veröffentlichung';
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['title'] = ['Titel', 'z. B. "Statistik".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['alias'] = ['Kennung', 'Kurz und eindeutig im Banner, z. B. "statistik". Wird mit der Auswahl des Besuchers gespeichert; Skripte fragen damit ab.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['essential'] = ['Technisch notwendig', 'Immer aktiv, kann nicht abgewählt werden.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['description'] = ['Beschreibung', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['published'] = ['Veröffentlicht', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['aliasExists'] = 'Die Kennung "%s" gibt es in diesem Banner schon.';

$GLOBALS['TL_LANG']['tl_juhe_consent_service']['title_legend'] = 'Dienst';
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['text_legend'] = 'Angaben für Besucher';
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['blocking_legend'] = 'Blockieren und Laden';
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['publish_legend'] = 'Veröffentlichung';
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['title'] = ['Name', 'z. B. "YouTube".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['provider'] = ['Anbieter', 'z. B. "Google Ireland Limited, Gordon House, Dublin 4, Irland".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['privacyUrl'] = ['Datenschutz des Anbieters', 'URL der Datenschutzerklärung des Anbieters.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['description'] = ['Zweck', 'Wofür der Dienst eingesetzt wird.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['cookies'] = ['Cookies', 'Ein Cookie pro Zeile: "Name: Speicherdauer", z. B. "_ga: 2 Jahre". Wird angezeigt und beim Widerruf gelöscht (soweit möglich).'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['hosts'] = ['Blockierte Domains', 'Eine Domain pro Zeile, z. B. "youtube.com". Iframes und Skripte von diesen Domains (inkl. Subdomains) laden erst nach Einwilligung.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['code'] = ['Code nach Einwilligung', 'HTML/JavaScript, das nach der Einwilligung am Ende der Seite eingefügt wird, z. B. das Tracking-Skript.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['published'] = ['Veröffentlicht', ''];

$GLOBALS['TL_LANG']['tl_juhe_consent_log']['tstamp'] = ['Zeitpunkt', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['pid'] = ['Banner', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['version'] = ['Version', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['consentId'] = ['Einwilligungs-ID', 'Zufällige ID aus der gespeicherten Auswahl des Besuchers.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['groups'] = ['Gruppen', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['services'] = ['Einzeln erlaubte Dienste', 'IDs der Dienste, die über "Immer erlauben" freigegeben wurden.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['url'] = ['Seite', ''];

$GLOBALS['TL_LANG']['tl_page']['juhe_consent_legend'] = 'Einwilligung';
$GLOBALS['TL_LANG']['tl_page']['juheConsentBanner'] = ['Consent-Banner', 'Der Banner für diese Website. Leer = kein Banner.'];
