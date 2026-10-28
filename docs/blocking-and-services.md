# Blocking and services

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
  content element, because it runs on the finished page. The host is read as
  a browser reads the address (extra slashes such as `https:///host`,
  backslashes, percent-encoding, a trailing dot), and international domain
  names count in their ASCII form, so `münchen.de` at a service also covers
  `xn--mnchen-3ya.de` in the page.

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
  `position`, `order`, `template`, and the "Kennung" as `id` and the
  "Adresse" as `url`, ending with a slash). Twig escapes values for HTML; inside a
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
  report when a service's external scripts have loaded (see [JavaScript API](javascript-api.md)).
