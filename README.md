# JUHE Consent Bundle for Contao

Consent banner for Contao 5.7 and 6 by JUHE IT-solutions. It asks visitors
before any optional service loads, blocks iframes and external scripts on
the server until consent is given, and keeps a consent log without personal
data.

## Requirements

PHP 8.3 or newer, Contao 5.7 or 6.0.

## Installation

```sh
composer require juhe-it-solutions/contao-consent-bundle
```

Then update the database (Contao Manager or `contao:migrate`), create a
banner under "System > Consent" and select it in the root page. In the
banner's group list, "Notwendige Gruppe anlegen" adds the essential group and
the service that describes the stored choice. Add the optional groups and
services (or the shipped provider presets) yourself.

To update, update the package and the database the same way and clear the
cache. Read `CHANGELOG.md` first when the website overrides templates.

## Documentation

- [Installation and updating](docs/installation.md)
- [Banner and settings](docs/banner-and-settings.md): card or bar, settings
  dialog, openers, privacy signals, withdrawal
- [Blocking and services](docs/blocking-and-services.md): server-side
  blocking, placeholders, code after consent, provider presets
- [Consent log, CSP and cache](docs/log-csp-cache.md)
- [Accessibility](docs/accessibility.md)
- [Storage](docs/storage.md)
- [JavaScript API](docs/javascript-api.md)
- [Styling and templates](docs/styling-and-templates.md)
- [Translations](docs/translations.md)
- [Testing](docs/testing.md)

## License

LGPL-3.0-or-later, the license Contao itself uses. See LICENSE.
