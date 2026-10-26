# JUHE Consent Bundle

Consent banner for Contao 5.7 and 6 (`juhe-it-solutions/contao-juhe-consent-bundle`),
LGPL-3.0-or-later. What it does: README.md. This repository is public; never
commit local paths, host names, credentials, session links or notes about
private projects.

## Rules

- **Clean room.** Written from scratch. Never copy, paraphrase or port code,
  templates, CSS or texts from oveleon/contao-cookiebar (AGPL) or any other
  consent extension. Looking at how a site is configured is fine; reading
  their source to reproduce it is not.
- **Version floor.** PHP 8.3 and Contao 5.7. Use only APIs that exist in both
  Contao 5.7 and 6.0, and no PHP 8.4 syntax (property hooks, asymmetric
  visibility, `new Foo()->bar()`). PHP 8.4 functions such as `array_find()`
  come from `symfony/polyfill-php84`.
- Contao conventions: Twig templates in `contao/templates` (namespace root,
  `.twig-root`), attributes (`#[AsCallback]`, `#[AsCronJob]`,
  `#[AsContentElement]`), DCA in `contao/dca`, no `.html5`.
- No personal data in the consent log: no IP address, no user agent.
- No dependencies in `public/juhe-consent.js`; it must run without a build step.
- Keep the look neutral and themable through the `--juhe-consent-*` custom
  properties and template overrides; the site design comes from the theme.
- Writing style: no en or em dashes; plain, short sentences.
- No tags or releases without the maintainer's decision.

## Testing

Test every change in a Contao 5.7 install on PHP 8.3 and a Contao 6.0 install
on PHP 8.4: banner, settings, blocking, placeholders, consent log and CSP
nonce. Lint with `php -l` on PHP 8.3.
