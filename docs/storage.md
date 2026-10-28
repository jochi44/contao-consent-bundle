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
