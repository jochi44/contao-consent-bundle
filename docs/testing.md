# Testing

The repository holds tests that do not ship with the package (`tests/`,
export-ignored in `.gitattributes` and in `archive.exclude` of
`composer.json`).

- PHPUnit: `composer install` and `vendor/bin/phpunit`. `AssetsFreshTest`
  fails when `public/juhe-consent.js` or `.css` changed without a rebuild.
- Minified assets: the readable `public/juhe-consent.js` and `.css` are the
  sources. After changing one, run `cd tools && npm ci && node build-assets.mjs`
  and commit `public/juhe-consent.min.js`, `.min.css` and `public/manifest.json`
  (`node build-assets.mjs --check` only checks). Contao serves the minified
  files through the manifest with a content hash in the URL, so a new release
  never hits a stale cached copy. `tools/` does not ship.
- Browser tests: `tests/Browser/run.sh 5.7` (Contao 5.7 on PHP 8.3),
  `tests/Browser/run.sh 6.0` (Contao 6.0 on PHP 8.5) or `tests/Browser/run.sh all`.
  The two runs cover both ends of what the bundle supports (Julle,
  2026-11-11, same policy as the Nexus lanes): 5.7 is the floor (PHP 8.3,
  Symfony 7.4), 6.0 the newest stack (newest PHP and the newest compatible
  packages, Symfony 8.x). A 6.0 run on PHP 8.4.0 resolved Symfony 7.4, because
  Symfony 8.0 ended with open advisories and 8.1 needs PHP 8.4.1.
  The script builds a throwaway Contao install with this checkout in Docker
  (its own network, MariaDB, Apache with PHP on `127.0.0.1:8199`), seeds a
  test site, runs HTTP checks and the Playwright specs in `tests/Browser/specs`
  in Chromium, Firefox and WebKit, and removes the containers at the end.
  It needs Docker, Node.js 20 or newer and npm; the first run installs
  Playwright and its browsers. Extra arguments go to Playwright, for example
  `tests/Browser/run.sh 5.7 specs/consent.spec.ts --project=chromium`.
  `KEEP=1` keeps the install running for a look in the browser,
  `SKIP_PW=1` runs only the HTTP checks.
