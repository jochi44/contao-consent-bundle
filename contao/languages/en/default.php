<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['MOD']['juhe_consent'] = ['Consent', 'Consent banner, groups and services'];
$GLOBALS['TL_LANG']['MOD']['juhe_consent_log'] = ['Consent log', 'Consent log without IP addresses or user agents'];
$GLOBALS['TL_LANG']['MOD']['tl_juhe_consent_group'] = 'Groups';
$GLOBALS['TL_LANG']['MOD']['tl_juhe_consent_service'] = 'Services';
$GLOBALS['TL_LANG']['CTE']['juhe_consent_settings'] = ['Cookie settings', 'Link that opens the consent settings.'];

$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['title_legend'] = 'Banner';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['text_legend'] = 'Texts';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['layout_legend'] = 'Display';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['link_legend'] = 'Links';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['version_legend'] = 'Version';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['title'] = ['Title', 'Backend only, e.g. "Website EN".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['headline'] = ['Headline', 'e.g. "Your privacy".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['text'] = ['Text', 'Briefly explains what consent is asked for.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['placeholderText'] = ['Text for blocked content', 'Shown instead of videos, maps etc. until consent is given. ##service## is replaced with the service name. Empty = default text.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['askOnFirstVisit'] = ['Ask on the first visit', 'Shows the card on the first visit. Unticked, no banner appears: blocked content shows only its placeholder, and the settings open from the "Cookie settings" link. Useful when another consent solution (e.g. Google\'s for ads) already asks and only click-to-load content is left here.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['layout'] = ['Display', 'The card leaves the page usable; the bar shows the groups right away.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['layoutOptions'] = ['card' => 'Card at the bottom left', 'bar' => 'Bar at the bottom'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['blocking'] = ['Block the page until the visitor decides', 'Dims the page on the first visit until a choice is made. The Esc key still releases the page: the banner stays visible but no longer blocks it.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['privacyPage'] = ['Privacy policy', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['imprintPage'] = ['Imprint', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['version'] = ['Version', 'Increase when groups or services change substantially: all visitors are asked again.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['usedBy'] = 'Used by:';
$GLOBALS['TL_LANG']['tl_juhe_consent_banner']['usedByNone'] = 'Not assigned to a website';

$GLOBALS['TL_LANG']['tl_juhe_consent_group']['title_legend'] = 'Group';
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['text_legend'] = 'Description';
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['publish_legend'] = 'Publishing';
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['title'] = ['Title', 'e.g. "Statistics".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['alias'] = ['Key', 'Short and unique within the banner, e.g. "statistics". Stored with the visitor\'s choice; scripts check consent with it.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['essential'] = ['Essential', 'Always on, cannot be deselected.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['description'] = ['Description', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['published'] = ['Published', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_group']['aliasExists'] = 'The key "%s" already exists in this banner.';

$GLOBALS['TL_LANG']['tl_juhe_consent_service']['title_legend'] = 'Service';
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['text_legend'] = 'Information for visitors';
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['blocking_legend'] = 'Blocking and loading';
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['publish_legend'] = 'Publishing';
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['title'] = ['Name', 'e.g. "YouTube".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['provider'] = ['Provider', 'e.g. "Google Ireland Limited, Gordon House, Dublin 4, Ireland".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['privacyUrl'] = ['Provider privacy policy', 'URL of the provider\'s privacy policy.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['description'] = ['Purpose', 'What the service is used for.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['cookies'] = ['Cookies', 'One cookie per line: "name: lifetime", e.g. "_ga: 2 years". Shown to visitors and deleted on withdrawal (where possible).'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['hosts'] = ['Blocked domains', 'One domain per line, e.g. "youtube.com". Iframes and scripts from these domains (incl. subdomains) load only after consent.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['code'] = ['Code after consent', 'HTML/JavaScript added at the end of the page after consent, e.g. a tracking script.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_service']['published'] = ['Published', ''];

$GLOBALS['TL_LANG']['tl_juhe_consent_log']['tstamp'] = ['Time', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['pid'] = ['Banner', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['version'] = ['Version', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['consentId'] = ['Consent ID', 'Random ID from the visitor\'s stored choice.'];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['groups'] = ['Groups', ''];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['services'] = ['Services allowed one by one', 'IDs of the services allowed via "Always allow".'];
$GLOBALS['TL_LANG']['tl_juhe_consent_log']['url'] = ['Page', ''];

$GLOBALS['TL_LANG']['tl_page']['juhe_consent_legend'] = 'Consent';
$GLOBALS['TL_LANG']['tl_page']['juheConsentBanner'] = ['Consent banner', 'The banner for this website. Empty = no banner.'];
