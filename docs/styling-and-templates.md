# Styling and templates

`public/juhe-consent.css` holds a neutral default. Themes override the custom
properties on `.juhe-consent, .juhe-consent-placeholder` (`--juhe-consent-background`,
`--juhe-consent-text`, `--juhe-consent-muted`, `--juhe-consent-line`, `--juhe-consent-surface`,
`--juhe-consent-accent`, `--juhe-consent-accent-text`, `--juhe-consent-switch-on`,
`--juhe-consent-switch-off`, `--juhe-consent-knob`, `--juhe-consent-focus`,
`--juhe-consent-scrim`, `--juhe-consent-radius`, `--juhe-consent-shadow`).

The templates are Twig with blocks, so a theme extends them and overrides only
what it needs (a template of the same name in the theme's `templates/` folder):

- `@Contao/juhe_consent/banner.html.twig`: `dialog_attributes`, `headline`,
  `text`, `settings`, `group`, `service`, `service_switch` (inside `service`),
  `info` (inside `settings`), `buttons`, `links`, `code`, `config`, `script`.
  `service_switch` is the switch of an optional service: a
  `label.juhe-consent-service__switch` around an
  `input.juhe-consent-switch[data-juhe-consent-service-checkbox]` with the
  service key as `value`, the group alias in `data-juhe-consent-group` and the
  service title as its hidden name. A theme that overrides `service` prints it
  with `{{ block('service_switch') }}`; without it the settings show the group
  switches only. `banner.info` is the additional
  information (HTML from the editor), `banner.links` the further links
  (`url`, `title`); a theme that overrides `links` prints them too. The class
  names and `data-juhe-consent-*` attributes are what the script relies on;
  keep them. `data-juhe-consent-position` on the dialog carries the position
  (`bottom-left`, `bottom-center`, `bottom-right`, `center`, `top-left`,
  `top-center`, `top-right`; for the bar `bottom` or `top`) for a theme's own
  stylesheet.
- The `code` block prints one inert `<template data-juhe-consent-code>` per
  service with code and one `<template data-juhe-consent-denied>` per optional
  service with code without consent, each with `data-juhe-consent-group`, `data-juhe-consent-service`
  and `data-juhe-consent-placement` (`body` or `head`). Keep them when
  overriding it.
- `@Contao/juhe_consent/head.html.twig`: block `stylesheet`. A theme with its
  own skin overrides it with an empty block.
- `@Contao/juhe_consent/placeholder.html.twig`: the placeholder for a blocked
  iframe (a service can pick a variant under `juhe_consent/placeholder/`; a
  variant extends this template and keeps its blocks), blocks `icon`, `title`, `text`, `actions` and `privacy`. Variables:
  `id`, `kind`, `group`, `service` (`key`, `title`, `provider`, `privacy_url`),
  `text`, `load_once`, `load_always`, `load_attributes` and `allow_attributes`
  (`attrs()` objects a theme sets to style the buttons), and `original`, the blocked iframe,
  printed raw inside the `<template>`. Never print `original` anywhere else,
  and never print other values raw.
- `@Contao/juhe_consent/glyph.html.twig`: the inline SVG the `icon` block
  shows for `kind`, one of `video` (YouTube, Vimeo, Dailymotion and other video
  hosts), `map` (Google Maps, OpenStreetMap, Bing Maps and others), `audio`
  (Spotify, SoundCloud and other audio hosts) or `embed` (anything else). The
  kind comes from the iframe's address (`EmbedKind`). Nothing in the
  placeholder loads from the provider. A theme colors the glyph through the
  classes `juhe-consent-glyph__frame`, `__line`, `__stroke`, `__mark`, `__dot`
  and `__knock`, or replaces the template.
- `@Contao/juhe_consent/opener.html.twig`: the settings opener, block
  `opener`; a link (`a.juhe-consent-settings-button[data-juhe-consent-open]`
  with `href` to the privacy policy page and `#juhe-consent`) or, without
  that page, a `button`. Variables: `label`, `url` (the privacy policy page or
  null) and `button_attributes`. The insert tag renders it directly.
- `@Contao/content_element/juhe_consent_settings.html.twig` and
  `@Contao/frontend_module/juhe_consent_settings.html.twig`: block `button`,
  which includes the opener; a theme sets `button_attributes` (an `attrs()`
  object) to style it, or overrides the opener template for all three.
