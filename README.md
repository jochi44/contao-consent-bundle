# Contao Consent Bundle

Consent banner for Contao 6 by JUHE IT-solutions. Written from scratch; no
code or templates from other consent extensions. Proprietary, see LICENSE.

## What it does

- **Banner per site:** a root page picks a banner (`tl_page.consentBanner`).
  A banner has groups ("Statistik", "Marketing", ...), a group has services
  (Google Analytics, YouTube, ...). One group is "technisch notwendig".
- **Card and settings:** first visit shows a small card at the bottom left
  that leaves the page usable: "Nur notwendige", "Alle akzeptieren" (same
  look), "Einstellungen". The settings are a modal dialog with one switch
  per group and the service details. Links to `#cookie-einstellungen`, any
  `[data-consent-open]` element and the content element "Cookie-Einstellungen"
  open it again.
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
- **Consent log** (`tl_consent_log`): banner, version, random consent ID from
  the cookie, groups, services, page path, time. No IP, no user agent.
  Rate limited (20 per 10 minutes and IP), deleted after three years.
- **CSP:** the banner script gets the page's nonce, or a nonce is added to
  the CSP header. A policy with `'unsafe-inline'` and no nonce stays as it is.
- **Cache:** the banner is the same for everybody (the choice is in a
  cookie), so pages stay cacheable. Backend changes clear the cache tags.

## Storage

One first-party cookie `contao_consent_<banner id>`, one year, `SameSite=Lax`,
JSON: `{v: version, g: [groups], s: [services], id: uuid, t: timestamp}`.
List it as a necessary service in the banner.

## JavaScript API

```js
window.contaoConsent.has('statistik');  // group alias or service key "s<id>"
window.contaoConsent.open();            // settings
window.contaoConsent.onChange((consent) => {});
document.addEventListener('contao-consent', (event) => event.detail.groups);
```

## Styling

`public/consent.css` holds a neutral default. Themes override the custom
properties on `.consent, .consent-placeholder` (`--consent-background`,
`--consent-text`, `--consent-muted`, `--consent-line`, `--consent-surface`,
`--consent-accent`, `--consent-accent-text`, `--consent-switch-on`,
`--consent-radius`, `--consent-shadow`) or replace the templates
`consent/banner.html.twig` and `consent/head.html.twig`.

## Translations

Button and label texts: `translations/contao_consent.{de,en,it}.yaml` (in the
page language). Headline and text come from the banner record, so a site per
language gets its own banner.
