<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['MOD']['juhe_consent'] = ['Consenso', 'Banner del consenso, gruppi e servizi'];
$GLOBALS['TL_LANG']['MOD']['juhe_consent_log'] = ['Registro dei consensi', 'Registro dei consensi senza indirizzi IP o user agent'];
$GLOBALS['TL_LANG']['MOD']['tl_juhe_consent_group'] = 'Gruppi';
$GLOBALS['TL_LANG']['MOD']['tl_juhe_consent_service'] = 'Servizi';
$GLOBALS['TL_LANG']['CTE']['juhe_consent_settings'] = ['Impostazioni cookie', 'Link che apre le impostazioni del consenso.'];

$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['title_legend'] = 'Banner';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['text_legend'] = 'Testi';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['layout_legend'] = 'Visualizzazione';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['pages_legend'] = 'Pagine senza apertura automatica';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['link_legend'] = 'Link';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['version_legend'] = 'Versione';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['title'] = ['Titolo', 'Solo nel backend, ad es. "Sito IT".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['headline'] = ['Titolo del banner', 'Ad es. "La tua privacy".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['text'] = ['Testo', 'Spiega in breve per che cosa si chiede il consenso.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['infoText'] = ['Informazioni aggiuntive', 'Appare nelle impostazioni sotto i gruppi, ad es. una nota sui trasferimenti verso paesi terzi o su come modificare la scelta in seguito. Vuoto = nessun testo.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['placeholderText'] = ['Testo per i contenuti bloccati', 'Appare al posto di video, mappe ecc. finché manca il consenso. ##service## viene sostituito dal nome del servizio. Vuoto = testo predefinito.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['askOnFirstVisit'] = ['Chiedi alla prima visita', 'Mostra la scheda alla prima visita. Senza spunta non appare alcun banner: i contenuti bloccati mostrano solo il segnaposto e le impostazioni si aprono dal link "Impostazioni cookie". Utile quando un\'altra soluzione per il consenso (ad es. quella di Google per la pubblicità) chiede già e qui restano solo contenuti da caricare con un clic.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['layout'] = ['Visualizzazione', 'La scheda lascia la pagina utilizzabile; la barra mostra subito i gruppi.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['layoutOptions'] = ['card' => 'Scheda in basso a sinistra', 'bar' => 'Barra in basso'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['blocking'] = ['Blocca la pagina fino alla scelta', 'Alla prima visita oscura la pagina finché non viene fatta una scelta. Il tasto Esc libera comunque la pagina: il banner resta visibile ma non la blocca più.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['excludePages'] = ['Non aprire automaticamente su', 'Su queste pagine il banner non si apre da solo, ad es. su una pagina di contatto o una landing page. I contenuti bloccati restano bloccati e le impostazioni si aprono comunque dal link "Impostazioni cookie". L\'informativa sulla privacy e le note legali non bloccano mai la pagina.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['privacyPage'] = ['Informativa sulla privacy', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['imprintPage'] = ['Note legali', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['infoPages'] = ['Altri link', 'Pagine collegate dopo l\'informativa sulla privacy e le note legali, in questo ordine. Il link mostra il titolo della pagina. Le pagine non pubblicate vengono omesse.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['version'] = ['Versione', 'Da aumentare quando gruppi o servizi cambiano in modo sostanziale: a tutti i visitatori viene chiesto di nuovo il consenso.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['usedBy'] = 'Usato da:';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['usedByNone'] = 'Non assegnato a nessun sito';

$GLOBALS['TL_LANG']['tl_juhe_consent_group']['title_legend'] = 'Gruppo';
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['text_legend'] = 'Descrizione';
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['publish_legend'] = 'Pubblicazione';
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['title'] = ['Titolo', 'Ad es. "Statistiche".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['alias'] = ['Chiave', 'Breve e univoca nel banner, ad es. "statistiche". Viene salvata con la scelta del visitatore; gli script la usano per verificare il consenso.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['essential'] = ['Tecnicamente necessario', 'Sempre attivo, non può essere deselezionato.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['description'] = ['Descrizione', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['published'] = ['Pubblicato', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['aliasExists'] = 'La chiave "%s" esiste già in questo banner.';

$GLOBALS['TL_LANG']['tl_juhe_consent_service']['title_legend'] = 'Servizio';
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['text_legend'] = 'Informazioni per i visitatori';
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['blocking_legend'] = 'Blocco e caricamento';
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['publish_legend'] = 'Pubblicazione';
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['title'] = ['Nome', 'Ad es. "YouTube".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['provider'] = ['Fornitore', 'Ad es. "Google Ireland Limited, Gordon House, Dublino 4, Irlanda".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['privacyUrl'] = ['Informativa privacy del fornitore', 'URL dell\'informativa sulla privacy del fornitore.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['description'] = ['Scopo', 'Per che cosa viene usato il servizio.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['cookies'] = ['Cookie', 'Un cookie per riga: "nome: durata", ad es. "_ga: 2 anni". Viene mostrato ai visitatori e cancellato alla revoca (se possibile).'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['hosts'] = ['Domini bloccati', 'Un dominio per riga, ad es. "youtube.com". Iframe e script da questi domini (sottodomini inclusi) si caricano solo dopo il consenso.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['code'] = ['Codice dopo il consenso', 'HTML/JavaScript inserito alla fine della pagina dopo il consenso, ad es. lo script di tracciamento.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['published'] = ['Pubblicato', ''];

$GLOBALS['TL_LANG']['tl_juhe_consent_log']['tstamp'] = ['Data e ora', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['pid'] = ['Banner', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['version'] = ['Versione', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['consentId'] = ['ID del consenso', 'ID casuale dalla scelta salvata del visitatore.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['groups'] = ['Gruppi', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['services'] = ['Servizi consentiti singolarmente', 'ID dei servizi consentiti con "Consenti sempre".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['url'] = ['Pagina', ''];

$GLOBALS['TL_LANG']['tl_page']['juhe_consent_legend'] = 'Consenso';
$GLOBALS['TL_LANG']['tl_page']['juheConsentBanner'] = ['Banner del consenso', 'Il banner per questo sito. Vuoto = nessun banner.'];
