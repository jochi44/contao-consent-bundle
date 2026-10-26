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
- **Display:** "Karte unten links" (default) or "Leiste am unteren Rand", a
  full-width bar that shows the groups right away. "Seite bis zur Entscheidung
  sperren" makes the first-visit banner modal with a scrim.
- **Blocking on the server:** iframes and external scripts whose host (or a
  subdomain) is listed at a service of a non-essential group are held back
  in the HTML. Iframes become a placeholder with "Inhalt laden" (this time)
  and "Immer erlauben" (remembered for this service). Scripts get
  `type="text/plain"`. Works for any layout, theme or content element,
  because it runs on the finished page.
- **Code after consent:** a service can carry HTML/JS that is inserted once
  consent exists (e.g. the tracking snippet).
- **Withdrawal:** switching a group off deletes the listed cookies (also on
  parent domains, `name*` for prefixes) and reloads if its scripts already ran.
- **New version = ask again:** raise the banner version after big changes.
- **Consent log** (`tl_juhe_consent_log`): banner, version, random consent ID from
  the cookie, groups, services, page path, time. No IP, no user agent.
  Rate limited (20 per 10 minutes and IP), deleted after three years.
- **CSP:** the banner script gets the page's nonce, or a nonce is added to
  the CSP header. A policy with `'unsafe-inline'` and no nonce stays as it is.
- **Cache:** the banner is the same for everybody (the choice is in a
  cookie), so pages stay cacheable. Backend changes clear the cache tags.

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
  description; the placeholder buttons name the service; the "Dienste" toggles
  name their group; links that open a new window say so; openers carry
  `aria-haspopup="dialog"`.
- **Visuals:** text contrast at least 4.5:1, the switch track and knob at
  least 3:1 in light and dark; targets at least 44 px; visible focus ring
  (`--juhe-consent-focus`); no motion with `prefers-reduced-motion`; native
  controls in forced colors mode; no horizontal scrolling at 320 px.

A theme that changes the custom properties or templates keeps these
properties. Do not put `[data-juhe-consent-open]` on an element that is not a
button or a link.

## Storage

One first-party cookie `juhe_consent_<banner id>`, one year, `SameSite=Lax`,
JSON: `{v: version, g: [groups], s: [services], id: uuid, t: timestamp}`.
List it as a necessary service in the banner.

## JavaScript API

```js
window.juheConsent.has('statistik');  // group alias or service key "s<id>"
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
  `text`, `settings`, `group`, `service`, `buttons`, `links`, `code`, `config`,
  `script`. The class names and `data-juhe-consent-*` attributes are what the
  script relies on; keep them.
- `@Contao/juhe_consent/head.html.twig`: block `stylesheet`. A theme with its
  own skin overrides it with an empty block.
- `@Contao/juhe_consent/placeholder.html.twig`: the placeholder for a blocked
  iframe, blocks `icon` (empty), `text` and `actions`. Variables: `id`,
  `group`, `service` (`key`, `title`), `text`, `load_once`, `load_always`, and
  `original`, the blocked iframe, printed raw inside the `<template>`. Never
  print `original` anywhere else, and never print other values raw.
- `@Contao/content_element/juhe_consent_settings.html.twig`: block `button`;
  a theme sets `button_attributes` (an `attrs()` object) to style the opener.

## Translations

Button and label texts: `translations/juhe_consent.{de,en,it}.yaml` (in the
page language). Headline and text come from the banner record, so a site per
language gets its own banner.

## License

LGPL-3.0-or-later, the license Contao itself uses. See LICENSE.
