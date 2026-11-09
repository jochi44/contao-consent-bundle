# Storage

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

A theme's close button (`data-juhe-consent-action="close"`, from 1.1) closes the
banner before a choice without storing one. It sets the `sessionStorage` entry
`juhe_consent_<banner id>_dismissed` to `1`, so the banner does not ask again
until the browser session ends; no consent is assumed and only essential
services run. The entry is strictly necessary for that; list it with the
consent entry (for example `juhe_consent_*_dismissed: session`).
