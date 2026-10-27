# JUHE Consent Bundle for Contao

Consent banner for Contao 5.7 and 6 by JUHE IT-solutions.

## Requirements and installation

- PHP 8.3 or newer, Contao 5.7 or 6.0.
- No release is tagged yet. Until then, require the development branch:

```sh
composer require juhe-it-solutions/contao-consent-bundle:dev-main
```

Then update the database (Contao Manager or `contao:migrate`), create a
banner under "System > Consent" and select it in the root page.

## What it does

- **Banner per site:** a root page picks a banner (`tl_page.juheConsentBanner`).
  A banner has groups ("Statistik", "Marketing", ...), a group has services
  (Google Analytics, YouTube, ...). One group is "technisch notwendig".
- **Card and settings:** first visit shows a small card at the bottom left
  that leaves the page usable: "Nur notwendige", "Alle akzeptieren" (same
  look), "Einstellungen". The settings are a modal dialog with one switch
  per group and the service details. Links to `#cookie-einstellungen`, any
  `[data-juhe-consent-open]` element and the content element "Cookie-Einstellungen"
  open it again. A banner can skip the card ("Beim ersten Besuch fragen"
  off): then only the placeholders and the settings link remain, for sites
  where another consent solution already asks (e.g. Google's CMP for ads).
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
- **Blocking on the server:** iframes and external scripts whose host (or a
  subdomain) is listed at a service of a non-essential group are held back
  in the HTML. Iframes become a placeholder with "Inhalt laden" (this time)
  and "Immer erlauben" (remembered for this service). Scripts get
  `type="text/plain"`. Works for any layout, theme or content element,
  because it runs on the finished page.
- **Code after consent:** a service can carry HTML/JS that is inserted once
  consent exists (e.g. the tracking snippet). This field can run arbitrary code
  on your website. Grant its field permission only to trusted administrators.
  Configure every optional provider's hosts, and put its initialization code
  here rather than in an ordinary inline script. The blocker does not inspect
  JavaScript, stylesheets, resource hints or requests created by other scripts.
- **Withdrawal:** saving the group switches replaces earlier choices, including
  individual "Always allow" approvals. Rejection also stops one-time embeds by
  reloading the page. Withdrawal deletes listed cookies at path `/` (also on
  parent domains, `name*` for prefixes). JavaScript cannot remove HttpOnly or
  third-party cookies, or cookies at other paths. Configure those providers'
  server-side withdrawal separately.
- **New version = ask again:** raise the banner version after big changes.
  Without a change a choice counts for the banner's lifetime (see Storage).
- **Consent log** (`tl_juhe_consent_log`): banner, version, random consent ID from
  the stored choice, groups, services, page path, time. No IP, no user agent.
  Rate limited (120 per 10 minutes and IP, so visitors behind one office or
  carrier IP are still logged; override `framework.rate_limiter.juhe_consent_log`
  to change it). A rejected post loses only the log row, never the visitor's
  choice. Deleted after three years. The random
  ID is pseudonymous, not a guarantee of anonymity. Avoid personal data in page
  paths. The rate limiter also processes the client IP as its key. Check the
  retention period and legal basis with your privacy adviser. Each serialized
  group or service list is limited to 255 bytes. Larger choices receive HTTP 400
  and are not logged.
- **CSP:** the banner script gets a nonce accepted by each enforced and
  report-only policy. Existing policy headers and restrictions for other
  resource types stay intact. A policy with `'unsafe-inline'` and no nonce or
  hash stays as it is. Allow each provider's external resources in your policy.
- **Cache:** the banner is the same for everybody and the choice stays in the
  browser (`localStorage`, no cookie), so pages stay in Contao's HTTP cache.
  Backend changes clear the cache tags.

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
  each switch is a real checkbox named by its group and described by the group
  description; each placeholder is a group named by its title, and its buttons
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

```js
window.juheConsent.has('statistik');  // effective permission for a group alias or service key "s<id>"
window.juheConsent.open();            // settings; focus returns to the active element afterwards
window.juheConsent.pending();         // true while the banner waits for a first choice
window.juheConsent.onChange((consent) => {});
document.addEventListener('juhe-consent', (event) => event.detail.groups);
document.addEventListener('juhe-consent:open', (event) => event.detail.settings);
// Cancelable: preventDefault() keeps focus where it is instead of returning it to the opener
document.addEventListener('juhe-consent:close', (event) => {});
```

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
  `text`, `settings`, `group`, `service`, `info` (inside `settings`), `buttons`,
  `links`, `code`, `config`, `script`. `banner.info` is the additional
  information (HTML from the editor), `banner.links` the further links
  (`url`, `title`); a theme that overrides `links` prints them too. The class
  names and `data-juhe-consent-*` attributes are what the script relies on;
  keep them. `data-juhe-consent-position` on the dialog carries the position
  (`bottom-left`, `bottom-center`, `bottom-right`, `center`, `top-left`,
  `top-center`, `top-right`; for the bar `bottom` or `top`) for a theme's own
  stylesheet.
- `@Contao/juhe_consent/head.html.twig`: block `stylesheet`. A theme with its
  own skin overrides it with an empty block.
- `@Contao/juhe_consent/placeholder.html.twig`: the placeholder for a blocked
  iframe, blocks `icon`, `title`, `text`, `actions` and `privacy`. Variables:
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
- `@Contao/content_element/juhe_consent_settings.html.twig`: block `button`;
  a theme sets `button_attributes` (an `attrs()` object) to style the opener.

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
