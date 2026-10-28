# Accessibility

The banner targets WCAG 2.2 AA; `axe-core` reports no violations in any state.

- **No keyboard trap.** The card is a non-modal dialog placed first in the
  page, so Tab reaches it first and then moves on into the page. Esc closes the
  settings. On a blocking banner, Esc releases the page: the banner stays
  visible as a non-modal dialog and the page is usable again.
- **Focus:** the settings focus the first switch, a blocking banner or the
  bar its first button. The dialog opens at its start: when that control is
  not in view (a long text on a phone), focus starts at the headline. After the choice or Esc, focus returns to the button or
  link that opened the settings. "Inhalt laden" moves focus to the loaded
  content. In the settings, the sticky buttons never cover the focused
  element, and they stop being sticky on short screens (e.g. 400 percent zoom).
- **Names:** the dialog is named by its headline and described by its text;
  each group switch is a real checkbox named by its group and described by the
  group description, each service switch one named by its service; each placeholder is a group named by its title, and its buttons
  and privacy link name the service ("Inhalt laden (YouTube)"), so a list of links and buttons
  tells several placeholders apart; the "Dienste" toggles
  name their group; links that open a new window say so; openers carry
  `aria-haspopup="dialog"`.
- **Visuals:** text contrast at least 4.5:1, the switch track and knob at
  least 3:1 in light and dark; targets at least 44 px; visible focus ring
  (`--juhe-consent-focus`); no motion with `prefers-reduced-motion`; native
  controls in forced colors mode; no horizontal scrolling at 320 px. The
  placeholder glyph is decorative (`aria-hidden`) and drawn in system colors in
  forced colors mode; a video or map placeholder keeps its aspect ratio but
  grows when its text needs more room (200 percent zoom, large fonts). Group
  headings wrap and service details stack on narrow screens, including with a
  larger browser default font.

A theme that changes the custom properties or templates keeps these
properties. Do not put `[data-juhe-consent-open]` on an element that is not a
button or a link.
