# JavaScript API

`window.juheConsent` exists once `juhe-consent.js` has run (it loads with
`defer`). The names are this bundle's own; compatibility with the API of other
consent tools is not a goal.

```js
window.juheConsent.has('statistik');  // effective permission for a group alias or service key "s<id>"; unknown names: false
window.juheConsent.state();           // a copy: {decided, groups, services}
window.juheConsent.subscribe((state) => {}); // returns a function that unsubscribes
window.juheConsent.whenLoaded('s12').then((detail) => {}); // detail: {service, failed}
window.juheConsent.open();            // settings; focus returns to the active element afterwards
window.juheConsent.pending();         // true while the banner waits for a first choice
window.juheConsent.onChange((api) => {});
document.addEventListener('juhe-consent:ready', (event) => event.detail.groups);
document.addEventListener('juhe-consent:loaded', (event) => event.detail.service);
document.addEventListener('juhe-consent', (event) => event.detail.groups);
document.addEventListener('juhe-consent:open', (event) => event.detail.settings);
// Cancelable: preventDefault() keeps focus where it is instead of returning it to the opener
document.addEventListener('juhe-consent:close', (event) => {});
```

- `has(name)`: true if the group (by alias) or the service (by key `s<id>`,
  the service ID) may run now. Essential groups and their services are
  always allowed. A group with only some services on is not allowed as a
  group, its allowed services are. Unknown names are false.
- `state()`: a new object on every call, so changing it changes nothing.
  `decided` is true once the visitor chose (stored or on this page); `groups`
  lists the aliases of the allowed groups, essential ones included; `services`
  lists the key of every allowed service, whether allowed by its group or on
  its own. Before a choice, only the essential groups and services are in it.
- `subscribe(callback)`: calls `callback(state)` at once with the current state
  and again after every change of the choice, with a fresh copy. It returns a
  function that ends the subscription. A subscriber that throws does not stop
  the others; its error shows in the console.
- `onChange(callback)`: older form, called after every change with the API
  object (not at once).
- `whenLoaded(key)`: a Promise for the service key `s<id>`. It resolves once
  the service started and all its external scripts (blocked page scripts and
  scripts in its code, `async` ones included) have loaded, or at once after
  the start for a service without external scripts. A script that fails to
  load (blocked, offline) counts as finished; its URL is in `detail.failed`.
  For a service that is not allowed, and for an unknown key, it stays pending
  until the service is allowed on this page, so code that waits for it never
  runs without consent.
- `juhe-consent:loaded` on `document`: once per service and page, at the same
  moment, with the same `detail` (`service`, `failed`). Essential services
  report too.
- `juhe-consent:ready` on `document`: once, when the API exists and before any
  service starts; `detail` is the state.
- `juhe-consent` on `document`: after every change; `detail.groups` are the
  allowed groups, `detail.services` the services allowed one by one.
- When a withdrawal stops a service that already runs, the page reloads. The
  subscribers, `onChange` and the `juhe-consent` event still run before the
  reload, so an integration can pass the withdrawal on; on the reloaded page
  they start with the new state. The reload carries the new choice in its
  history entry (`history.state.juheConsentReload`, used once), because a
  page loaded right after a write can still read the previous value from
  `localStorage`.

Code that runs before `juhe-consent.js` (an inline script in the head, for
example) cannot call the API yet. It pushes functions into a queue instead;
they run with the API as their argument as soon as it exists, before any
service starts. Functions pushed after that run at once.

```js
window.juheConsentQueue = window.juheConsentQueue || [];
window.juheConsentQueue.push((consent) => {
    consent.subscribe((state) => {
        // e.g. pass state.services on to a tag manager
    });
});
```

In PHP, `JuheItSolutions\JuheConsentBundle\Consent\ConsentConfig` (an
autowirable service) gives the banner as the templates get it:
`bannerForPage(PageModel $page)` returns the banner of the page's website as
that page shows it (privacy, imprint and excluded pages applied), or null if
the website has no banner. It reads the root page's banner, so every website
and language root gets its own. `banner(int $id)` returns a banner by ID, and
the static `ConsentConfig::forPage(array $banner, int $pageId)` applies the
page rules to it. The arrays carry `id`, `version`, `groups` (each with
`alias`, `title`, `essential`, `services`) and the services (each with `key`,
`title`, `provider`, `cookies`, `hosts`).
