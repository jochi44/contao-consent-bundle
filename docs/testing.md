# Testing

The repository holds tests that do not ship with the package (`tests/`,
export-ignored in `.gitattributes` and in `archive.exclude` of
`composer.json`).

- PHPUnit: `composer install` and `vendor/bin/phpunit`.
- Browser tests: `tests/Browser/run.sh 5.7` (Contao 5.7 on PHP 8.3),
  `tests/Browser/run.sh 6.0` (Contao 6.0 on PHP 8.4) or `tests/Browser/run.sh all`.
  The script builds a throwaway Contao install with this checkout in Docker
  (its own network, MariaDB, Apache with PHP on `127.0.0.1:8199`), seeds a
  test site, runs HTTP checks and the Playwright specs in `tests/Browser/specs`
  in Chromium, Firefox and WebKit, and removes the containers at the end.
  It needs Docker, Node.js 20 or newer and npm; the first run installs
  Playwright and its browsers. Extra arguments go to Playwright, for example
  `tests/Browser/run.sh 5.7 specs/consent.spec.ts --project=chromium`.
  `KEEP=1` keeps the install running for a look in the browser,
  `SKIP_PW=1` runs only the HTTP checks.
