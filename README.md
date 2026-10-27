# JUHE Consent Bundle for Contao

Consent banner for Contao 5.7 and 6 by JUHE IT-solutions.

## Requirements and installation

- PHP 8.3 or newer, Contao 5.7 or 6.0.
- No release is tagged yet. Until then, require the development branch:

```sh
composer require juhe-it-solutions/contao-consent-bundle:dev-main
```

Then update the database (Contao Manager or `contao:migrate`), create a
banner under "System > Consent" and select it in the root page. In the
banner's group list, "Notwendige Gruppe anlegen" adds the essential group
("Technisch notwendig", published, always on) and a service "Einwilligung"
that describes the stored choice (`juhe_consent_<banner id>` in
localStorage, with the banner's lifetime), in the editor's language. It adds
only what is missing: a banner that already has an essential group keeps it,
and a service that lists `juhe_consent_<id>` (or a pattern such as
`juhe_consent_*`) counts as the description. Running it twice adds nothing.
It assumes no optional provider; add those groups and services yourself. The
action is a POST form with the request token and needs the rights to create
groups and services.

## What it does

- **Banner per site:** a root page picks a banner (`tl_page.juheConsentBanner`).
  A banner has groups ("Statistik", "Marketing", ...), a group has services
  (Google Analytics, YouTube, ...). One group is "technisch notwendig".
- **Card and settings:** first visit shows a small card at the bottom left
  that leaves the page usable: "Nur notwendige", "Alle akzeptieren" (same
  look), "Einstellungen". The settings are a modal dialog with one switch
  per group and, in the service details, one switch per optional service.
  They open again from the content element and the front end module
  "Cookie-Einstellungen", the insert tag `{{juhe_consent::settings}}`, any
  link to `#juhe-consent` (or the older `#cookie-einstellungen`) and any
  `[data-juhe-consent-open]` element (see Settings openers). A banner can skip the card ("Beim ersten Besuch fragen"
  off): then only the placeholders and the settings link remain, for sites
  where another consent solution already asks (e.g. Google's CMP for ads).
- **Settings openers:** the content element and the front end module
  "Cookie-Einstellungen" (for a layout, e.g. the footer; "Eigene Bezeichnung"
  sets its text) and the insert tag `{{juhe_consent::settings}}` (in a text)
  print the same opener (`juhe_consent/opener.html.twig`). With a privacy
  policy page in the banner it is a link to that page with `#juhe-consent`:
  without JavaScript it leads there, with JavaScript it opens the settings,
  prefilled with the stored choice. Without a privacy policy page it is a
  button. A page opened with `#juhe-consent` (a link opened in a new tab)
  shows the settings at once. For a navigation entry, add a page of the type
  "Externe Weiterleitung" with the address `{{link_url::<privacy page ID>}}#juhe-consent`
  (or just `#juhe-consent`); the navigation shows it like any page and the
  click opens the settings.
- **Display:** "Karte" (default) or "Leiste über die ganze Breite", a
  full-width bar that shows the groups right away. "Position" places the card
  at the bottom left (default), bottom centre, bottom right, in the centre,
  top left, top centre or top right, and the bar at the bottom (default) or
  the top. The card keeps 1rem from the edges and never grows beyond the
  viewport, and the settings always open in the centre. "Seite bis zur Entscheidung
  sperren" makes the first-visit banner modal with a scrim. The banner's
  privacy policy and imprint pages are never blocked: there it asks as a
  non-modal dialog, so visitors can read them before they choose.
- **Pages without automatic opening:** "Nicht automatisch öffnen auf" lists
  pages where the banner does not open by itself (a contact or landing page,
  for example). Blocked content stays blocked there, nothing is allowed
  without a choice, and the settings still open from a link or button. The
  next ordinary page asks as usual. The pages are part of the banner, so the
  cached pages stay the same for every visitor.
- **More information and links:** "Weitere Informationen" is a text shown in
  the settings below the groups. "Weitere Links" adds pages after the privacy
  policy and imprint, in the chosen order, each named by its page title;
  unpublished pages drop out. Texts and links belong to the banner: a site
  with several languages has one banner per language root (see
  Translations), there is no per-root override.
- **Privacy signals (optional):** with "Datenschutzsignale beachten", a browser
  that sends Global Privacy Control (`navigator.globalPrivacyControl`) or Do
  Not Track (`navigator.doNotTrack` "1") and has no stored choice gets no
  banner by itself, and only essential services run. Nothing is stored, so
  the page stays the same for everybody and in the cache. The visitor can
  still open the settings, and a choice saved there wins. Off by default. This
  is a policy option, not a statement that it meets any law.
- **Logged-in users (optional):** with "Keine optionalen Dienste für angemeldete
  Benutzer", a logged-in front end member and a back end user in the front end
  preview get no banner and no optional service by themselves. Their stored
  choice is kept but not applied; a choice made on the page (a placeholder, the
  settings) counts for that page only and is neither stored nor logged. Such a
  page is sent with `Cache-Control: private` before the banner is added, so it
  never enters a shared cache; anonymous visitors keep the public, cached page.
  Limit: a page that the cache serves without asking PHP never sees the login.
  With "Immer aus dem Shared-Cache laden" on the page, a member can get the
  public page from the cache, banner included. Back end users outside the
  preview count as visitors. Off by default.
- **Blocking on the server:** iframes and external scripts whose host (or a
  subdomain) is listed at a service of a non-essential group are held back
  in the HTML. Iframes become a placeholder with "Inhalt laden" (this time)
  and "Immer erlauben" (remembered for this service). Scripts get
  `type="text/plain"`. Resource links to those hosts (`<link>` with
  `rel` `preconnect`, `dns-prefetch`, `preload`, `prefetch`,
  `modulepreload` or `stylesheet`) lose `rel` and `href` until consent
  (kept in `data-juhe-consent-rel` and `data-juhe-consent-href`), so the
  browser does not contact the host early; after consent they come back with
  their other attributes. Hyperlinks (`<a href>`) and other link types (icon,
  canonical, alternate) stay as they are. Works for any layout, theme or
  content element, because it runs on the finished page.

  What the blocker cannot hold back, because it only reads the HTML of the
  page:
  - requests that inline JavaScript makes itself (`fetch()`, an `Image`, a
    script or iframe it creates, a tracking pixel built in code): put such
    code into the service's "Code nach Einwilligung" instead;
  - CSS that loads from a provider inside an allowed stylesheet or a
    `<style>` block (`@import`, `url()` of fonts or images);
  - anything that loads before the page or outside it: HTTP `Link` headers
    (preload, preconnect), a service worker, browser extensions;
  - hosts that are not listed at a service, and `<img>`, `<video>`, `<audio>`,
    `<object>` and `<embed>` from listed hosts.
  Check a page in the browser's network panel before consent: no optional
  provider should appear.
- **Notice per service (optional):** a service's "Hinweistext" replaces the
  banner's text for blocked content for that service (`##service##` is the
  service name), and its "Hinweis-Template" picks
  `juhe_consent/placeholder` (default) or a variant under
  `juhe_consent/placeholder/` (for example `placeholder/map.html.twig` that
  extends the default and overrides a block). The buttons, their names and
  the remembered choice stay the same.
- **Code after consent:** a service can carry HTML/JS that is inserted once
  consent exists (e.g. the tracking snippet). This field can run arbitrary code
  on your website. Grant its field permission only to trusted administrators.
  Configure every optional provider's hosts, and put its initialization code
  here rather than in an ordinary inline script. The blocker does not inspect
  JavaScript or requests created by other scripts (see Blocking on the server).
- **Template services:** a service of the type "Template" takes its code
  after consent from a Twig template instead of the code field. The select
  lists every `juhe_consent/service/*` template: the shipped presets and the
  website's own (`templates/juhe_consent/service/my_service.html.twig`); a
  stored name that is not such a template renders nothing. The output goes
  into the same inert `<template>` as the code, so nothing of it runs or loads
  before consent, and it starts once, like the code. The template gets
  `service` (`key`, `title`, `provider`, `privacy_url`, `hosts`, `cookies`,
  `position`, `order`, `template`). Twig escapes values for HTML; inside a
  `<script>` use `|e('js')`. Same permission warning as the code field: a
  template can run any code.
- **Provider presets:** shipped service templates for Google Analytics 4
  (`google_analytics`), Google Tag Manager (`google_tag_manager`), Matomo
  (`matomo`), Matomo Tag Manager (`matomo_tag_manager`), Meta Pixel
  (`meta_pixel`) and etracker (`etracker`), under `juhe_consent/service/`. Pick
  one at a service of the type "Template" and enter the "Kennung" (measurement,
  container, website or pixel ID, or the etracker account key); Matomo and
  Matomo Tag Manager also need the "Adresse" of the installation. Saving checks
  both against the preset's format, and a stored value that does not fit
  renders nothing. Saving also fills in the preset's blocked domains, provider,
  privacy policy and cookies where these fields are empty (a self-hosted
  Matomo adds its own host, unless it is the back end's host); check them
  against the provider's current documentation and your privacy policy. Each
  preset follows the provider's own snippet, without the `<noscript>` image or
  iframe, and loads only after consent, like all service code. A Tag Manager
  loads the tags of its container: give those their own consent there, or
  list their domains at their own services. Google Consent Mode is not part of
  the presets. A theme extends a preset and overrides its block (`config`,
  `commands`, `container`, `events`, `attributes`), or the website adds its
  own template next to them.
- **Code without consent (optional):** HTML/JS that runs once per page while
  the service is not allowed after the stored choice is applied: on the first
  visit, after a refusal, and after a withdrawal (which reloads the page, or,
  if the service was not running yet, at once). It never runs for an allowed
  service, and it is not undone when the visitor allows the service later on
  the same page. It must not contact the provider or load anything from it:
  the blocker does not hold it back, and running it is not consent. Use it for
  local fallbacks, for example to tell a tag manager or a site script that
  consent is missing. Same permission warning, position and order as the code
  after consent.
- **Position and order:** "Position des Codes" puts the code at the end of the
  page (default) or in the head. "Ladereihenfolge" decides which service
  starts first: the smaller number, and with the same number the order in the
  back end. A service starts with the page's blocked scripts of its hosts,
  then its code. Scripts run in document order: an external script without
  `async` has loaded (or failed) before the next script runs, so a library is
  there before the inline setup that follows it. Each service waits for the
  one before it. Scripts keep their attributes (`async`, `defer`, `type`,
  `data-*` and others) and get the page's CSP nonce. Each service starts once
  per page. `juheConsent.whenLoaded(key)` and the event `juhe-consent:loaded`
  report when a service's external scripts have loaded (see JavaScript API).
- **Single services:** a group switch turns all its services on or off. With
  only some services on, the group switch shows a mixed state (a native
  indeterminate checkbox, "mixed" for screen readers). The choice stores the
  groups that are fully on and the single services of the others, so a
  visitor can allow YouTube and keep Vimeo off in the same group. Essential
  services have no switch. A template without the service switches (see
  Styling and templates) keeps the group switches only.
- **Withdrawal:** saving the settings replaces earlier choices, including
  individual "Always allow" approvals (they show as switched-on services).
  Rejection also stops one-time embeds by reloading the page. Withdrawal
  deletes the cookies listed at the withdrawn services (`name*` for a prefix;
  a bare `*` matches nothing, and names an essential service lists are kept):
  - In the browser, every listed cookie that scripts can see, at path `/` and
    every parent of the current page's path (a cookie set on `/shop/cart`
    without a path lives at `/shop`), as a host-only cookie and on the host
    and its parent domains (browsers ignore a public suffix such as `co.uk`,
    so the registrable domain is the last that takes effect).
  - On the server, the HttpOnly first-party cookies: if a withdrawn service
    lists cookies, the browser posts the banner ID, the withdrawn service keys
    and the page path to `/_juhe_consent/cookies` (JSON only, at most 4 KiB,
    rate limited like the log: 120 per 10 minutes and IP,
    `framework.rate_limiter.juhe_consent_cookies`). The request never names a
    cookie: the server expires only the names configured at those services
    that the request carries, for the same paths and domains, and refuses
    essential and unknown services. Before a reload, the page waits for the
    answer (at most 2 seconds).

  Not reachable: third-party cookies (set by the provider's own domain, for
  example `doubleclick.net`), and HttpOnly cookies at a path the cleanup
  request does not see (only cookies at `/` reach it). Configure those
  providers' server-side withdrawal separately.
- **New version = ask again:** raise the banner version after big changes.
  Without a change a choice counts for the banner's lifetime (see Storage).
- **Consent log** (`tl_juhe_consent_log`): banner, version, random consent ID from
  the stored choice, groups, services, page path, time. No IP, no user agent.
  The random ID is pseudonymous: the log is not anonymous, and nothing here is
  a statement that it meets any law. Each banner decides in "Einwilligungs-Protokoll":
  - "Auswahl protokollieren" (on by default). Off, the browser sends nothing,
    and the server refuses a post for that banner (HTTP 403) from a page that
    is still cached, so nothing is stored.
  - "Einträge aufbewahren für" 6, 12, 24 or 36 months (default 36). A daily
    cron job deletes older entries per banner, counted in calendar months;
    entries of a deleted banner follow the default.
  - "Seite im Protokoll": "Pfad" (default) stores the path without domain,
    query string and fragment (the server strips them too); "Keine" stores no
    page. Choose "Keine" if paths can carry personal data (account or token
    pages).

  Posts are rate limited (120 per 10 minutes and IP, so visitors behind one
  office or carrier IP are still logged; override
  `framework.rate_limiter.juhe_consent_log` to change it). The rate limiter
  processes the client IP as its key; it is not stored in the log. A rejected
  post loses only the log row, never the visitor's choice. A post is at most
  4 KiB, which also bounds the stored group and service lists. Check the
  retention period and the legal basis with your privacy adviser.
- **Log export:** "Exportieren (CSV)" in the log list downloads the entries of
  the current filter and search, newest first, for example to answer an
  audit request. Only back end users with access to the log
  module get it (`contao_user.modules` for `juhe_consent_log`); others get
  HTTP 403. Columns: `id`, `time` (ISO 8601), `banner_id`, `banner`, `version`,
  `consent_id`, `groups`, `services`, `page`; no IP address or user agent. The
  file is UTF-8 with a byte order mark and CRLF line ends. A cell that starts
  with `=`, `+`, `-`, `@`, a tab or a carriage return gets a leading `'`, so a
  spreadsheet shows it as text instead of running it as a formula. Rows are
  read and sent in chunks of 1000, so a large log does not fill the memory.
- **CSP:** the banner script gets a nonce accepted by each enforced and
  report-only policy. Existing policy headers and restrictions for other
  resource types stay intact. A policy with `'unsafe-inline'` and no nonce or
  hash stays as it is. Allow each provider's external resources in your policy.
- **Cache:** apart from the logged-in option above, the banner is the same for
  everybody and the choice stays in the browser (`localStorage`, no cookie), so
  pages stay in Contao's HTTP cache. Backend changes clear the cache tags.

## Accessibility

The banner targets WCAG 2.2 AA; `axe-core` reports no violations in any state.

- **No keyboard trap.** The card is a non-modal dialog placed first in the
  page, so Tab reaches it first and then moves on into the page. Esc closes the
  settings. On a blocking banner, Esc releases the page: the banner stays
  visible as a non-modal dialog and the page is usable again.
- **Focus:** the settings focus the first switch, a blocking banner or the
  bar its first button. The dialog opens at its start: when that control is
  not in view (a long text on a phone), focus starts at the headline. After the choice or Esc, focus returns to the button or
  link that opened the settings. "Inhalt laden" moves focus to the loaded
  content. In the settings, the sticky buttons never cover the focused
  element, and they stop being sticky on short screens (e.g. 400 percent zoom).
- **Names:** the dialog is named by its headline and described by its text;
  each group switch is a real checkbox named by its group and described by the
  group description, each service switch one named by its service; each placeholder is a group named by its title, and its buttons
  and privacy link name the service ("Inhalt laden (YouTube)"), so a list of links and buttons
  tells several placeholders apart; the "Dienste" toggles
  name their group; links that open a new window say so; openers carry
  `aria-haspopup="dialog"`.
- **Visuals:** text contrast at least 4.5:1, the switch track and knob at
  least 3:1 in light and dark; targets at least 44 px; visible focus ring
  (`--juhe-consent-focus`); no motion with `prefers-reduced-motion`; native
  controls in forced colors mode; no horizontal scrolling at 320 px. The
  placeholder glyph is decorative (`aria-hidden`) and drawn in system colors in
  forced colors mode; a video or map placeholder keeps its aspect ratio but
  grows when its text needs more room (200 percent zoom, large fonts). Group
  headings wrap and service details stack on narrow screens, including with a
  larger browser default font.

A theme that changes the custom properties or templates keeps these
properties. Do not put `[data-juhe-consent-open]` on an element that is not a
button or a link.

## Storage

One `localStorage` entry `juhe_consent_<banner id>`, JSON:
`{v: version, g: [groups], s: [services], id: uuid, t: timestamp}`. It counts
for the banner's "Auswahl gültig für (Tage)" from `t`: 365 days by default,
1 to 395 days (13 months) allowed, never unlimited. The lifetime is read on
every page, so shortening it also ends older choices sooner. Invalid or future
timestamps and a new banner version ask again at once. No cookie: Contao's
cache proxy passes every request with an unknown cookie to PHP, and the server
never needs the choice. List the entry as a necessary service in the banner
(for example `juhe_consent_*: 1 year`). Without storage (private mode, blocked
site data) the banner asks on every page.

## JavaScript API

`window.juheConsent` exists once `juhe-consent.js` has run (it loads with
`defer`). The names are this bundle's own; compatibility with the API of other
consent tools is not a goal.

```js
window.juheConsent.has('statistik');  // effective permission for a group alias or service key "s<id>"; unknown names: false
window.juheConsent.state();           // a copy: {decided, groups, services}
window.juheConsent.subscribe((state) => {}); // returns a function that unsubscribes
window.juheConsent.whenLoaded('s12').then((detail) => {}); // detail: {service, failed}
window.juheConsent.open();            // settings; focus returns to the active element afterwards
window.juheConsent.pending();         // true while the banner waits for a first choice
window.juheConsent.onChange((api) => {});
document.addEventListener('juhe-consent:ready', (event) => event.detail.groups);
document.addEventListener('juhe-consent:loaded', (event) => event.detail.service);
document.addEventListener('juhe-consent', (event) => event.detail.groups);
document.addEventListener('juhe-consent:open', (event) => event.detail.settings);
// Cancelable: preventDefault() keeps focus where it is instead of returning it to the opener
document.addEventListener('juhe-consent:close', (event) => {});
```

- `has(name)`: true if the group (by alias) or the service (by key `s<id>`,
  the service ID) may run now. Essential groups and their services are
  always allowed. A group with only some services on is not allowed as a
  group, its allowed services are. Unknown names are false.
- `state()`: a new object on every call, so changing it changes nothing.
  `decided` is true once the visitor chose (stored or on this page); `groups`
  lists the aliases of the allowed groups, essential ones included; `services`
  lists the key of every allowed service, whether allowed by its group or on
  its own. Before a choice, only the essential groups and services are in it.
- `subscribe(callback)`: calls `callback(state)` at once with the current state
  and again after every change of the choice, with a fresh copy. It returns a
  function that ends the subscription. A subscriber that throws does not stop
  the others; its error shows in the console.
- `onChange(callback)`: older form, called after every change with the API
  object (not at once).
- `whenLoaded(key)`: a Promise for the service key `s<id>`. It resolves once
  the service started and all its external scripts (blocked page scripts and
  scripts in its code, `async` ones included) have loaded, or at once after
  the start for a service without external scripts. A script that fails to
  load (blocked, offline) counts as finished; its URL is in `detail.failed`.
  For a service that is not allowed, and for an unknown key, it stays pending
  until the service is allowed on this page, so code that waits for it never
  runs without consent.
- `juhe-consent:loaded` on `document`: once per service and page, at the same
  moment, with the same `detail` (`service`, `failed`). Essential services
  report too.
- `juhe-consent:ready` on `document`: once, when the API exists and before any
  service starts; `detail` is the state.
- `juhe-consent` on `document`: after every change; `detail.groups` are the
  allowed groups, `detail.services` the services allowed one by one.
- When a withdrawal stops a service that already runs, the page reloads. The
  subscribers, `onChange` and the `juhe-consent` event still run before the
  reload, so an integration can pass the withdrawal on; on the reloaded page
  they start with the new state.

Code that runs before `juhe-consent.js` (an inline script in the head, for
example) cannot call the API yet. It pushes functions into a queue instead;
they run with the API as their argument as soon as it exists, before any
service starts. Functions pushed after that run at once.

```js
window.juheConsentQueue = window.juheConsentQueue || [];
window.juheConsentQueue.push((consent) => {
    consent.subscribe((state) => {
        // e.g. pass state.services on to a tag manager
    });
});
```

In PHP, `JuheItSolutions\JuheConsentBundle\Consent\ConsentConfig` (an
autowirable service) gives the banner as the templates get it:
`bannerForPage(PageModel $page)` returns the banner of the page's website as
that page shows it (privacy, imprint and excluded pages applied), or null if
the website has no banner. It reads the root page's banner, so every website
and language root gets its own. `banner(int $id)` returns a banner by ID, and
the static `ConsentConfig::forPage(array $banner, int $pageId)` applies the
page rules to it. The arrays carry `id`, `version`, `groups` (each with
`alias`, `title`, `essential`, `services`) and the services (each with `key`,
`title`, `provider`, `cookies`, `hosts`).

## Styling and templates

`public/juhe-consent.css` holds a neutral default. Themes override the custom
properties on `.juhe-consent, .juhe-consent-placeholder` (`--juhe-consent-background`,
`--juhe-consent-text`, `--juhe-consent-muted`, `--juhe-consent-line`, `--juhe-consent-surface`,
`--juhe-consent-accent`, `--juhe-consent-accent-text`, `--juhe-consent-switch-on`,
`--juhe-consent-switch-off`, `--juhe-consent-knob`, `--juhe-consent-focus`,
`--juhe-consent-scrim`, `--juhe-consent-radius`, `--juhe-consent-shadow`).

The templates are Twig with blocks, so a theme extends them and overrides only
what it needs (a template of the same name in the theme's `templates/` folder):

- `@Contao/juhe_consent/banner.html.twig`: `dialog_attributes`, `headline`,
  `text`, `settings`, `group`, `service`, `service_switch` (inside `service`),
  `info` (inside `settings`), `buttons`, `links`, `code`, `config`, `script`.
  `service_switch` is the switch of an optional service: a
  `label.juhe-consent-service__switch` around an
  `input.juhe-consent-switch[data-juhe-consent-service-checkbox]` with the
  service key as `value`, the group alias in `data-juhe-consent-group` and the
  service title as its hidden name. A theme that overrides `service` prints it
  with `{{ block('service_switch') }}`; without it the settings show the group
  switches only. `banner.info` is the additional
  information (HTML from the editor), `banner.links` the further links
  (`url`, `title`); a theme that overrides `links` prints them too. The class
  names and `data-juhe-consent-*` attributes are what the script relies on;
  keep them. `data-juhe-consent-position` on the dialog carries the position
  (`bottom-left`, `bottom-center`, `bottom-right`, `center`, `top-left`,
  `top-center`, `top-right`; for the bar `bottom` or `top`) for a theme's own
  stylesheet.
- The `code` block prints one inert `<template data-juhe-consent-code>` per
  service with code and one `<template data-juhe-consent-denied>` per optional
  service with code without consent, each with `data-juhe-consent-group`, `data-juhe-consent-service`
  and `data-juhe-consent-placement` (`body` or `head`). Keep them when
  overriding it.
- `@Contao/juhe_consent/head.html.twig`: block `stylesheet`. A theme with its
  own skin overrides it with an empty block.
- `@Contao/juhe_consent/placeholder.html.twig`: the placeholder for a blocked
  iframe (a service can pick a variant under `juhe_consent/placeholder/`; a
  variant extends this template and keeps its blocks), blocks `icon`, `title`, `text`, `actions` and `privacy`. Variables:
  `id`, `kind`, `group`, `service` (`key`, `title`, `provider`, `privacy_url`),
  `text`, `load_once`, `load_always`, `load_attributes` and `allow_attributes`
  (`attrs()` objects a theme sets to style the buttons), and `original`, the blocked iframe,
  printed raw inside the `<template>`. Never print `original` anywhere else,
  and never print other values raw.
- `@Contao/juhe_consent/glyph.html.twig`: the inline SVG the `icon` block
  shows for `kind`, one of `video` (YouTube, Vimeo, Dailymotion and other video
  hosts), `map` (Google Maps, OpenStreetMap, Bing Maps and others), `audio`
  (Spotify, SoundCloud and other audio hosts) or `embed` (anything else). The
  kind comes from the iframe's address (`EmbedKind`). Nothing in the
  placeholder loads from the provider. A theme colors the glyph through the
  classes `juhe-consent-glyph__frame`, `__line`, `__stroke`, `__mark`, `__dot`
  and `__knock`, or replaces the template.
- `@Contao/juhe_consent/opener.html.twig`: the settings opener, block
  `opener`; a link (`a.juhe-consent-settings-button[data-juhe-consent-open]`
  with `href` to the privacy policy page and `#juhe-consent`) or, without
  that page, a `button`. Variables: `label`, `url` (the privacy policy page or
  null) and `button_attributes`. The insert tag renders it directly.
- `@Contao/content_element/juhe_consent_settings.html.twig` and
  `@Contao/frontend_module/juhe_consent_settings.html.twig`: block `button`,
  which includes the opener; a theme sets `button_attributes` (an `attrs()`
  object) to style it, or overrides the opener template for all three.

## Translations

German, English and Italian are complete, in the backend and for visitors:

- Visitor texts (buttons, labels, the placeholder): `translations/juhe_consent.{de,en,it}.yaml`,
  in the page language. Other page languages use the installation's fallback
  locale (`framework.translator.fallbacks`, English in a default Contao install).
- Labels for themes that override the banner (a details toggle, a close button):
  `details_show`, `details_hide`, `details_for` (`%group%` is the group title) and
  `close`, in the same files. The bundle's own banner does not use them.
- Backend labels and help texts: `contao/languages/{de,en,it}/default.php`, in
  the backend user's language.
- Headline, text, groups and services come from the banner records, so a site
  per language gets its own banner (one per root page). The banner has no
  language field: the root page's language decides. The banner list names the
  websites that use each banner, with their language, and marks a banner no
  website uses.

`tests/LanguageParityTest.php` fails when a key is missing in one of the three
languages; a new text goes into all three in the same change.

## License

LGPL-3.0-or-later, the license Contao itself uses. See LICENSE.
