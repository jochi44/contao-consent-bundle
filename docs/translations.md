# Translations

German, English and Italian are complete, in the backend and for visitors:

- Visitor texts (buttons, labels, the placeholder): `translations/juhe_consent.{de,en,it}.yaml`,
  in the page language. Other page languages use the installation's fallback
  locale (`framework.translator.fallbacks`, English in a default Contao install).
- Labels for themes that override the banner (a details toggle, a close button):
  `details_show`, `details_hide`, `details_for` (`%group%` is the group title) and
  `close`, in the same files. The bundle's own banner does not use them.
- Backend labels and help texts: `contao/languages/{de,en,it}/default.php`, in
  the backend user's language.
- Headline, text, groups and services come from the banner records, so a site
  per language gets its own banner (one per root page). The banner has no
  language field: the root page's language decides. The banner list names the
  websites that use each banner, with their language, and marks a banner no
  website uses.

`tests/LanguageParityTest.php` fails when a key is missing in one of the three
languages; a new text goes into all three in the same change.
