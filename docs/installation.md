# Installation and updating

## First setup

Then update the database (Contao Manager or `contao:migrate`), create a
banner under "System > Consent" and select it in the root page. In the
banner's group list, "Notwendige Gruppe anlegen" adds the essential group
("Technisch notwendig", published, always on) and a service "Einwilligung"
that describes the stored choice (`juhe_consent_<banner id>` in
localStorage, with the banner's lifetime), in the editor's language. It adds
only what is missing: a banner that already has an essential group keeps it,
and a service that lists `juhe_consent_<id>` (or a pattern such as
`juhe_consent_*`) counts as the description. Running it twice adds nothing.
It assumes no optional provider; add those groups and services yourself. The
action is a POST form with the request token and needs the rights to create
groups and services.

## Updating

Update the package with Composer (or the Contao Manager), then update the
database the same way as on installation and clear the cache. The bundle has
no migrations: new settings come as new columns with defaults, so existing
banners keep their behaviour. `CHANGELOG.md` lists what changed; read it
before an update when the website overrides the bundle's templates, because a
template override must keep the blocks, class names and `data-juhe-consent-*`
attributes it relies on.
