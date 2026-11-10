# Changelog

All notable changes to this bundle. Versions follow semantic versioning.

## Unreleased

- Package metadata: the author is written "JUHE IT-solutions." with its dot,
  the company's name everywhere. No code change.

## 1.1.1 (2026-11-09)

- Esc on a banner with a close button does what the close button does: before
  a choice the banner closes without consent and does not ask again in this
  browser session, like Esc in oveleon's cookie bar. On a blocking banner Esc
  no longer leaves a non-modal bar behind. In a card's settings Esc still goes
  back to the banner; without a close button Esc is unchanged.

## 1.1.0 (2026-11-09)

- Close action for a theme's close button (`data-juhe-consent-action="close"`):
  before a choice it closes the banner without consent and does not ask again
  in this browser session; only essential services run. After a choice it
  only closes the settings. Esc is unchanged.
- Minified script and stylesheet (about 4.6 KB and 2.5 KB gzip instead of
  10 KB and 3.8 KB), served through `public/manifest.json` with a content hash
  in the URL, so browsers never keep an old copy after an update. The
  readable files stay next to them.

## 1.0.0 (2026-10-28)

First release. Consent banner for Contao 5.7 and 6.0 on PHP 8.3 or newer.

- Banners per website root with groups and services, an essential group that
  is always on, and "Notwendige Gruppe anlegen" for the essential group and
  the service that describes the stored choice.
- Card (seven positions) or full-width bar (top or bottom), an optional
  blocking overlay, pages without automatic opening, additional information
  and further links, and a non-modal banner on the privacy and imprint pages.
- Settings dialog with a switch per group and per optional service, a mixed
  group state, and openers: content element, front end module, insert tag
  `{{juhe_consent::settings}}`, links to `#juhe-consent` and
  `[data-juhe-consent-open]`, with a privacy page link as the no-script
  fallback.
- Server-side blocking on the finished page: iframes become click-to-load
  placeholders ("Inhalt laden", "Immer erlauben"), external scripts and
  resource links (preconnect, preload, stylesheet and others) to listed hosts
  wait for consent. Hosts are read as browsers read the address.
- Services with code after consent, code without consent, position (head or
  body) and loading order, or with a Twig template under
  `juhe_consent/service/`; shipped presets for Google Analytics 4, Google Tag
  Manager, Matomo, Matomo Tag Manager, Meta Pixel and etracker.
- Per-service notice text and notice template for blocked content.
- The choice in `localStorage` (no cookie, so pages stay in Contao's HTTP
  cache), a configurable lifetime of 1 to 395 days, a banner version that asks
  again, optional respect for Global Privacy Control and Do Not Track, and an
  option without optional services for logged-in users.
- Withdrawal stops running services by reloading the page and expires the
  listed first-party cookies in the browser and, for HttpOnly cookies, on the
  server (`/_juhe_consent/cookies`).
- Pseudonymous consent log without IP address or user agent, per banner on or
  off, retention of 6 to 36 months, page path or none, rate limited, with a
  CSV export for back end users with access to the log.
- JavaScript API `window.juheConsent` (`has`, `state`, `subscribe`,
  `whenLoaded`, `open`, `pending`, `onChange`), events and the queue
  `window.juheConsentQueue`; PHP `ConsentConfig::bannerForPage()`.
- CSP nonce for the banner script and activated scripts, accepted by every
  enforced and report-only policy.
- Accessible dialog (WCAG 2.2 AA target): no keyboard trap, focus return,
  named controls, 44 px targets, reduced motion, forced colors, large fonts.
- Twig templates with blocks, `--juhe-consent-*` custom properties, and
  German, English and Italian texts.
