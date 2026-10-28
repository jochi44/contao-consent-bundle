# Consent log, CSP and cache

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
