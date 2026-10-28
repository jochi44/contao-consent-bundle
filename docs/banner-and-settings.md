# Banner and settings

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
- **Single services:** a group switch turns all its services on or off. With
  only some services on, the group switch shows a mixed state (a native
  indeterminate checkbox, "mixed" for screen readers). The choice stores the
  groups that are fully on and the single services of the others, so a
  visitor can allow YouTube and keep Vimeo off in the same group. Essential
  services have no switch. A template without the service switches (see
  Styling and templates) keeps the group switches only.
- **Withdrawal:** saving the settings replaces earlier choices, including
  individual "Always allow" approvals (they show as switched-on services).
  Rejection also stops one-time embeds by reloading the page. "Immer
  erlauben" on a placeholder only adds that service: content loaded once on
  the page stays. Withdrawal
  deletes the cookies listed at the withdrawn services (`name*` for a prefix;
  a bare `*` matches nothing, and names an essential service lists are kept):
  - In the browser, every listed cookie that scripts can see, at path `/` and
    every parent of the current page's path (a cookie set on `/shop/cart`
    without a path lives at `/shop`), as a host-only cookie and on the host
    and its parent domains (browsers ignore a public suffix such as `co.uk`,
    so the registrable domain is the last that takes effect). A `__Secure-`
    cookie is expired with `Secure`, a `__Host-` cookie only at `/` without a
    domain, as browsers require for these prefixes (in any letter case).
  - On the server, the HttpOnly first-party cookies: if a withdrawn service
    lists cookies, the browser posts the banner ID, the withdrawn service keys
    and the page path to `/_juhe_consent/cookies` (JSON only, at most 4 KiB,
    rate limited like the log: 120 per 10 minutes and IP,
    `framework.rate_limiter.juhe_consent_cookies`). The request never names a
    cookie: the server expires only the names configured at those services
    that the request carries, for the same paths and domains, and refuses
    essential and unknown services. It expires at most 64 names per request,
    and for a long host only the host and its eight nearest parent domains.
    Before a reload, the page waits for the answer (at most 2 seconds).

  Not reachable: third-party cookies (set by the provider's own domain, for
  example `doubleclick.net`), and HttpOnly cookies at a path the cleanup
  request does not see (only cookies at `/` reach it). Configure those
  providers' server-side withdrawal separately.
- **New version = ask again:** raise the banner version after big changes.
  Without a change a choice counts for the banner's lifetime (see [Storage](storage.md)).
