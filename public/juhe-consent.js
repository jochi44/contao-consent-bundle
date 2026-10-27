/*
 * Consent banner of juhe-it-solutions/contao-consent-bundle. No dependencies.
 *
 * The choice is stored in localStorage under juhe_consent_<banner id> (JSON:
 * version, groups, services allowed one by one, random ID, time), not in a
 * cookie: a request cookie makes Contao's HTTP cache pass every page to PHP, and
 * the server never needs the choice. It expires after the banner's lifetime
 * (one year by default); without
 * storage (private mode, blocked site data) the banner simply asks again.
 * Blocked content is marked by
 * the server: script[type="text/plain"][data-juhe-consent-src], placeholders with
 * the original iframe in a <template>, and template[data-juhe-consent-code] with
 * the code of a service, template[data-juhe-consent-denied] with its code
 * without consent. All of it carries data-juhe-consent-group/-service.
 * The settings have a switch per group ([data-juhe-consent-checkbox]) and, if
 * the template prints them, per optional service ([data-juhe-consent-service-checkbox]).
 *
 * API: window.juheConsent.has('statistik'), .open(), .pending() (the banner
 * waits for a first choice), .onChange(callback), .state() (a copy: decided,
 * groups, services), .subscribe(callback) (called at once and after every
 * change, returns the unsubscribe function), the queue window.juheConsentQueue
 * for code that runs before this script, and these events on document:
 * .whenLoaded(key) (a Promise for when the external scripts of a service have
 * loaded), "juhe-consent:ready" (detail: the state) once the API exists,
 * "juhe-consent:loaded" (detail.service, detail.failed) per started service,
 * "juhe-consent" after every change, "juhe-consent:open" (detail.settings) and
 * "juhe-consent:close" (cancelable: preventDefault() keeps focus where it is
 * instead of returning it to the element that opened the settings).
 *
 * Keyboard: Esc always gets out. It closes the settings, and on a banner that
 * blocks the page it releases the page; the banner then stays as a non-modal
 * dialog. Focus goes back to the opener, and to the content a placeholder loads.
 */
(() => {
    'use strict';

    const configElement = document.getElementById('juhe-consent-config');
    const dialog = document.querySelector('[data-juhe-consent-dialog]');

    if (!configElement || !dialog) {
        return;
    }

    const config = JSON.parse(configElement.textContent);
    const nonce = document.currentScript?.nonce || '';
    const listeners = [];
    const subscribers = new Set();
    const running = new Set();

    const essential = config.groups.filter((group) => group.essential).map((group) => group.alias);
    const aliases = config.groups.map((group) => group.alias);
    const services = config.groups.flatMap((group) => group.services.map((service) => ({ ...service, group: group.alias })));

    // A choice counts for the banner's lifetime in days (one year by default)
    const maxAge = (Number.isInteger(config.lifetime) && config.lifetime > 0 ? config.lifetime : 365) * 86400;

    const read = () => {
        try {
            const value = JSON.parse(localStorage.getItem(config.storage) || 'null');

            // A new banner version or a choice older than the lifetime asks again
            const now = Date.now() / 1000;

            if (!value || value.v !== config.version || !Array.isArray(value.g) || !Number.isFinite(value.t) || value.t < 0 || value.t > now || now - value.t > maxAge) {
                return null;
            }

            return {
                id: String(value.id || ''),
                groups: [...new Set([...essential, ...value.g.filter((alias) => aliases.includes(alias))])],
                services: (Array.isArray(value.s) ? value.s : []).filter((key) => services.some((service) => service.key === key)),
            };
        } catch {
            return null;
        }
    };

    /*
     * A logged-in member or a back end user in the preview on a banner with "No
     * optional services for logged-in users": the stored choice is ignored (and
     * kept), the banner does not ask, and choices made on the page count for
     * this page only. The server only sends this flag on private pages.
     */
    const restricted = !!config.restricted;

    let state = restricted ? null : read();

    const allows = (consent, group, service) => essential.includes(group) || (!!consent && (consent.groups.includes(group) || consent.services.includes(service)));
    const allowed = (element) => allows(state, element.dataset.juheConsentGroup, element.dataset.juheConsentService);

    // A copy of the current state: allowed groups and services, and whether the visitor decided
    const snapshot = () => ({
        decided: !!state,
        groups: aliases.filter((alias) => allows(state, alias, undefined)),
        services: services.filter((service) => allows(state, service.group, service.key)).map((service) => service.key),
    });

    // A failing callback must not stop the others; its error still reaches the console
    const call = (callback, value) => {
        try {
            callback(value);
        } catch (error) {
            setTimeout(() => {
                throw error;
            });
        }
    };

    const changed = () => {
        listeners.forEach((callback) => call(callback, api));
        subscribers.forEach((callback) => call(callback, snapshot()));
        document.dispatchEvent(new CustomEvent('juhe-consent', { detail: { groups: [...state.groups], services: [...state.services] } }));
    };

    const uuid = () => {
        if (crypto.randomUUID) {
            return crypto.randomUUID();
        }

        const bytes = crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');

        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    };

    // Scripts from a template or a blocked tag only run as new elements, and they need the nonce
    const runnable = (old) => {
        const script = document.createElement('script');
        const skip = ['type', 'nonce', 'data-juhe-consent-src', 'data-juhe-consent-type', 'data-juhe-consent-group', 'data-juhe-consent-service'];

        for (const { name, value } of old.attributes) {
            if (!skip.includes(name)) {
                script.setAttribute(name, value);
            }
        }

        const type = old.dataset.juheConsentSrc ? old.dataset.juheConsentType : old.getAttribute('type');

        if (type) {
            script.type = type;
        }

        if (old.dataset.juheConsentSrc) {
            script.src = old.dataset.juheConsentSrc;
        } else {
            script.textContent = old.textContent;
        }

        // Keep the order of scripts that were not async (e.g. a library and its setup)
        if (!old.hasAttribute('async')) {
            script.async = false;
        }

        if (nonce) {
            script.nonce = nonce;
        }

        return script;
    };

    const content = (template) => {
        const fragment = document.importNode(template.content, true);
        fragment.querySelectorAll('script').forEach((old) => old.replaceWith(runnable(old)));

        return fragment;
    };

    // The placeholder whose button was used: its content gets the focus once it is there
    let focusAfterReveal = null;

    const reveal = (placeholder) => {
        const template = placeholder.querySelector('template');

        if (!template) {
            return;
        }

        const fragment = content(template);
        const first = fragment.firstElementChild;
        placeholder.replaceWith(fragment);

        if (placeholder === focusAfterReveal) {
            focusAfterReveal = null;
            first?.focus();
        }
    };

    /*
     * Services start in their order (smaller first, ties as in the back end). In
     * a service, the page's blocked scripts come first, then its code, at the end
     * of the body or in the head. Scripts run in document order: an external
     * script without async has loaded (or failed) before the next one runs, so a
     * library is there before its setup. whenLoaded(key) and the event
     * "juhe-consent:loaded" report when all external scripts of a service have
     * finished; a service without them reports as soon as it started.
     */
    const rank = new Map(services.map((service, index) => [service.key, [Number.isInteger(service.order) ? service.order : 0, index]]));
    const byRank = (a, b) => {
        const [x, i] = rank.get(a) || [0, services.length];
        const [y, j] = rank.get(b) || [0, services.length];

        return x - y || i - j;
    };

    const readiness = new Map();
    const loaded = (key) => {
        if (!readiness.has(key)) {
            let resolve;
            const promise = new Promise((done) => {
                resolve = done;
            });
            readiness.set(key, { promise, resolve });
        }

        return readiness.get(key);
    };

    const started = new Set();
    const taken = new WeakSet();
    let queue = Promise.resolve();

    // Browsers fetch no external script of another type, and no nomodule script
    const fetched = (script) => script.hasAttribute('src') && !script.noModule && (!script.type || /^(module|(text|application)\/(x-)?(java|ecma)script)$/i.test(script.type));

    // [slot, original] pairs: each original becomes a runnable script in its slot, in order
    const runScripts = async (pairs, failed) => {
        const pending = [];

        for (const [slot, old] of pairs) {
            const script = runnable(old);
            const done = fetched(script) ? new Promise((resolve) => {
                script.addEventListener('load', () => resolve(), { once: true });
                script.addEventListener('error', () => {
                    failed.push(script.src);
                    resolve();
                }, { once: true });
            }) : null;

            slot.replaceWith(script);

            if (done) {
                pending.push(done);

                if (!old.hasAttribute('async')) {
                    await done;
                }
            }
        }

        await Promise.all(pending);
    };

    // The code of a template goes in place first; its scripts start one by one afterwards
    const insertCode = (template) => {
        const fragment = document.importNode(template.content, true);
        const pairs = [...fragment.querySelectorAll('script')].map((old) => {
            const slot = document.createComment('');
            old.replaceWith(slot);

            return [slot, old];
        });

        ('head' === template.dataset.juheConsentPlacement ? document.head : document.body).append(fragment);
        template.remove();

        return pairs;
    };

    const start = async (key, scripts, templates) => {
        const failed = [];
        await runScripts(scripts.map((old) => [old, old]), failed);

        for (const template of templates) {
            await runScripts(insertCode(template), failed);
        }

        if (!started.has(key)) {
            started.add(key);
            const detail = { service: key, failed };
            loaded(key).resolve(detail);
            document.dispatchEvent(new CustomEvent('juhe-consent:loaded', { detail: { ...detail, failed: [...failed] } }));
        }
    };

    const apply = () => {
        const batch = new Map();
        const entry = (key) => {
            if (!batch.has(key)) {
                batch.set(key, { scripts: [], templates: [] });
            }

            return batch.get(key);
        };

        document.querySelectorAll('script[type="text/plain"][data-juhe-consent-src]').forEach((old) => {
            if (!taken.has(old) && allowed(old)) {
                taken.add(old);
                entry(old.dataset.juheConsentService).scripts.push(old);
            }
        });

        document.querySelectorAll('.juhe-consent-placeholder').forEach((placeholder) => {
            if (allowed(placeholder)) {
                running.add(placeholder.dataset.juheConsentService);
                reveal(placeholder);
            }
        });

        document.querySelectorAll('template[data-juhe-consent-code]').forEach((template) => {
            if (!taken.has(template) && allowed(template)) {
                taken.add(template);
                entry(template.dataset.juheConsentService).templates.push(template);
            }
        });

        // Allowed services without scripts or code still report that they started
        services.forEach((service) => {
            if (!started.has(service.key) && allows(state, service.group, service.key)) {
                entry(service.key);
            }
        });

        [...batch.keys()].sort(byRank).forEach((key) => {
            const { scripts, templates } = batch.get(key);

            if (scripts.length || templates.length) {
                running.add(key);
            }

            queue = queue.then(() => start(key, scripts, templates)).catch(() => {});
        });
    };

    const matching = (patterns, name) => patterns.some((pattern) => (pattern.endsWith('*') ? pattern.length > 1 && name.startsWith(pattern.slice(0, -1)) : pattern === name));

    /*
     * Code without consent: once per page for each service that is not allowed
     * after the choice is applied (first visit, refusal, withdrawal), in the
     * services' order. It never runs for an allowed service.
     */
    const deny = () => {
        [...document.querySelectorAll('template[data-juhe-consent-denied]')]
            .filter((template) => !taken.has(template) && !allowed(template))
            .sort((a, b) => byRank(a.dataset.juheConsentService, b.dataset.juheConsentService))
            .forEach((template) => {
                taken.add(template);
                queue = queue.then(() => runScripts(insertCode(template), [])).catch(() => {});
            });
    };

    const removeCookies = (patterns) => {
        const names = document.cookie.split(';').map((cookie) => cookie.split('=')[0].trim()).filter(Boolean);
        const host = location.hostname;
        const parts = host.split('.');
        const domains = [''];
        const paths = ['/'];

        if (!/^[\d.]+$|:|^\[/.test(host)) {
            for (let i = 0; i < parts.length - 1; i += 1) {
                domains.push(`; domain=.${parts.slice(i).join('.')}`);
            }
        }

        // A cookie set without a path on /shop/cart has the path /shop
        location.pathname.split('/').filter(Boolean).slice(0, 8).reduce((path, segment) => {
            const next = `${path}/${segment}`;
            paths.push(next, `${next}/`);

            return next;
        }, '');

        // Names that an essential service lists stay
        const keep = services.filter((service) => essential.includes(service.group)).flatMap((service) => service.cookies);
        const exact = patterns.filter((pattern) => !pattern.endsWith('*'));
        const matches = [...new Set([...exact, ...names.filter((name) => matching(patterns, name))])].filter((name) => !matching(keep, name));

        matches.forEach((name) => paths.forEach((path) => domains.forEach((domain) => {
            document.cookie = `${name}=; path=${path}; max-age=0${domain}`;
        })));
    };

    /*
     * HttpOnly first-party cookies: the server expires the configured names of the
     * withdrawn services that the request carries. The request names the banner,
     * the services and the page path, never a cookie. Third-party cookies stay out
     * of reach.
     */
    const cleanup = (withdrawn) => {
        if (!config.cookieUrl || !withdrawn.some((service) => service.cookies.length)) {
            return Promise.resolve();
        }

        return fetch(config.cookieUrl, {
            method: 'POST',
            keepalive: true,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ banner: config.banner, services: withdrawn.map((service) => service.key), path: location.pathname }),
        }).catch(() => {});
    };

    const log = () => {
        if (!config.logUrl) {
            return;
        }

        fetch(config.logUrl, {
            method: 'POST',
            keepalive: true,
            headers: { 'Content-Type': 'application/json' },
            // The path only (no query string or fragment), or no page at all
            body: JSON.stringify({ banner: config.banner, version: config.version, id: state.id, groups: state.groups, services: state.services, ...(config.logPage === 'none' ? {} : { url: location.pathname }) }),
        }).catch(() => {});
    };

    const save = (groups, allowedServices) => {
        const previous = state;

        state = {
            id: previous?.id || uuid(),
            groups: [...new Set([...essential, ...groups])],
            services: [...new Set(allowedServices)],
        };

        if (!restricted) {
            try {
                localStorage.setItem(config.storage, JSON.stringify({ v: config.version, g: state.groups, s: state.services, id: state.id, t: Math.floor(Date.now() / 1000) }));
            } catch {
                // No storage: the choice holds for this page only
            }

            log();
        }

        // Withdrawn: the cookies go, and scripts that already run only stop with a reload
        const withdrawn = services.filter((service) => !allows(state, service.group, service.key) && (running.has(service.key) || allows(previous, service.group, service.key)));
        removeCookies(withdrawn.flatMap((service) => service.cookies));
        const cleaned = cleanup(withdrawn);
        const reload = withdrawn.some((service) => running.has(service.key));

        if (!reload) {
            apply();
            deny();
        }

        // Before a reload too, so integrations can pass a withdrawal on (e.g. to a consent mode)
        changed();

        // The reloaded page must not send the expired cookies again; wait at most 2 s for the server
        if (reload) {
            Promise.race([cleaned, new Promise((resolve) => {
                setTimeout(resolve, 2000);
            })]).then(() => location.reload());
        }
    };

    // The dialog
    const settings = dialog.querySelector('[data-juhe-consent-settings]');
    const button = (action) => dialog.querySelector(`[data-juhe-consent-action="${action}"]`);
    const checkboxes = [...dialog.querySelectorAll('[data-juhe-consent-checkbox]')];
    const serviceCheckboxes = [...dialog.querySelectorAll('[data-juhe-consent-service-checkbox]')];
    const bar = 'bar' === config.layout;
    const pending = () => !state && !!config.ask && !restricted;

    /*
     * Global Privacy Control or Do Not Track, if the banner respects them: the
     * banner then does not ask by itself, only essential services run and
     * nothing is stored. A choice the visitor saves still wins. A missing or
     * "0" preference is no signal and never counts as permission.
     */
    const privacySignal = !!config.privacySignals && (navigator.globalPrivacyControl === true || navigator.doNotTrack === '1' || window.doNotTrack === '1');

    // Waiting for a choice and allowed to ask by itself: not on a page the banner excludes
    const prompting = () => pending() && !config.quiet && !privacySignal;
    const openers = '[data-juhe-consent-open], a[href$="#cookie-einstellungen"]';

    let shown = false;
    let shownWithSettings = false;
    let released = !config.blocking;
    let opener = null;

    const setHidden = (action, hidden) => {
        const element = button(action);

        if (element) {
            element.hidden = hidden;
        }
    };

    const firstAction = () => [...dialog.querySelectorAll('[data-juhe-consent-action]')].find((element) => !element.hidden);
    const headline = document.getElementById(dialog.getAttribute('aria-labelledby') || '');

    if (headline && !headline.hasAttribute('tabindex')) {
        headline.tabIndex = -1;
    }

    // Fully inside the dialog's visible area and not under the sticky buttons
    const visible = (element) => {
        const box = element.getBoundingClientRect();
        const view = dialog.getBoundingClientRect();
        const buttons = dialog.querySelector('.juhe-consent__buttons');
        const bottom = buttons && !buttons.contains(element) && 'sticky' === getComputedStyle(buttons).position ? buttons.getBoundingClientRect().top : view.bottom;

        return box.top >= view.top && box.bottom <= bottom;
    };

    /*
     * The dialog opens at its start. If the control that should get focus would
     * scroll the headline and text away (a long text on a phone), focus starts at
     * the headline instead, as the APG dialog pattern recommends.
     */
    const focusStart = (target) => {
        dialog.scrollTop = 0;
        const start = target && visible(target) ? target : headline || target;
        start?.focus({ preventScroll: true });

        // Firefox scrolls to the control showModal() focused only after this
        if (start === headline) {
            requestAnimationFrame(() => {
                if (document.activeElement === headline) {
                    dialog.scrollTop = 0;
                }
            });
        }
    };

    /*
     * Service switches (if the template has them): a group switch is on when all
     * its services are on, and mixed (indeterminate, "mixed" for screen readers)
     * when only some are. Turning a group on or off sets all its services.
     */
    const servicesOf = (alias) => serviceCheckboxes.filter((checkbox) => checkbox.dataset.juheConsentGroup === alias);

    const mixed = (groupCheckbox) => {
        const own = servicesOf(groupCheckbox.value);

        if (groupCheckbox.disabled || !own.length) {
            return;
        }

        const on = own.filter((checkbox) => checkbox.checked).length;
        groupCheckbox.checked = on === own.length;
        groupCheckbox.indeterminate = on > 0 && on < own.length;
    };

    dialog.addEventListener('change', (event) => {
        const target = event.target;

        if (checkboxes.includes(target)) {
            target.indeterminate = false;
            servicesOf(target.value).forEach((checkbox) => {
                checkbox.checked = target.checked;
            });
        } else if (serviceCheckboxes.includes(target)) {
            checkboxes.filter((checkbox) => checkbox.value === target.dataset.juheConsentGroup).forEach(mixed);
        }
    });

    // Stored: the groups that are fully on, and the single services of the others
    const selection = () => {
        const groups = checkboxes.filter((checkbox) => checkbox.checked && !checkbox.indeterminate).map((checkbox) => checkbox.value);
        const single = serviceCheckboxes.filter((checkbox) => checkbox.checked && !groups.includes(checkbox.dataset.juheConsentGroup)).map((checkbox) => checkbox.value);

        return [groups, single];
    };

    const toggleSettings = (open) => {
        settings.hidden = !open;
        setHidden('settings', open);
        setHidden('save', !open);
    };

    /*
     * The card leaves the page usable and sits first in the page, so the Tab key
     * reaches it first. Modal are the settings, the bar when it is reopened, and a
     * blocking banner until Esc releases the page.
     */
    const show = (withSettings) => {
        checkboxes.forEach((checkbox) => {
            if (!checkbox.disabled) {
                checkbox.checked = !!state?.groups.includes(checkbox.value);
            }
        });

        serviceCheckboxes.forEach((checkbox) => {
            checkbox.checked = allows(state, checkbox.dataset.juheConsentGroup, checkbox.value);
        });

        checkboxes.forEach(mixed);

        // The bar always shows the groups
        toggleSettings(withSettings || bar);
        dialog.classList.toggle('juhe-consent--settings', withSettings && !bar);
        shownWithSettings = withSettings;

        // The close event of this switch arrives later and is ignored
        if (dialog.open) {
            shown = false;
            dialog.close();
        }

        if (document.body.firstElementChild !== dialog) {
            document.body.prepend(dialog);
        }

        if (withSettings || !released) {
            dialog.showModal();
            focusStart(withSettings && !bar ? settings.querySelector('input:not([disabled])') : firstAction());
        } else {
            dialog.show();
        }

        shown = true;
        document.dispatchEvent(new CustomEvent('juhe-consent:open', { detail: { settings: withSettings } }));
    };

    // Opened from a link, a button or the API: focus returns there afterwards
    const open = (from) => {
        opener = from && from !== document.body && !dialog.contains(from) ? from : null;
        show(true);
    };

    const api = {
        has: (name) => aliases.includes(name)
            ? allows(state, name, undefined)
            : services.some((service) => service.key === name && allows(state, service.group, service.key)),
        open: () => open(document.activeElement),
        pending,
        onChange: (callback) => {
            listeners.push(callback);
        },
        state: snapshot,
        // Resolves once all external scripts of the service have loaded; never for a service that is not allowed
        whenLoaded: (key) => loaded(String(key)).promise,
        // Called at once with the current state and after every change; returns the unsubscribe function
        subscribe: (callback) => {
            if ('function' !== typeof callback) {
                throw new TypeError('juheConsent.subscribe() needs a function');
            }

            const subscriber = (value) => callback(value);
            subscribers.add(subscriber);
            call(subscriber, snapshot());

            return () => {
                subscribers.delete(subscriber);
            };
        },
    };

    // After a choice or Esc: focus back to the opener, unless a listener cancels "juhe-consent:close"
    const closed = () => {
        const target = opener;
        shown = false;
        opener = null;

        if (document.dispatchEvent(new CustomEvent('juhe-consent:close', { cancelable: true })) && target?.isConnected) {
            target.focus();
        }
    };

    const choose = (groups, allowedServices) => {
        save(groups, allowedServices);
        shown = false;
        dialog.close();
        closed();
    };

    /*
     * The close event only handles what the script did not do itself, which is
     * Esc. The script's own closes set "shown" first, because their close event
     * can arrive much later.
     */
    dialog.addEventListener('close', () => {
        const withSettings = shownWithSettings;

        if (dialog.open || !shown) {
            return;
        }

        shown = false;

        /*
         * Closed without a choice, which only Esc does (the "cancel" event does not
         * always fire): the settings go back to the banner, and the blocking banner
         * releases the page and stays as a non-modal dialog.
         */
        if (prompting()) {
            if (!withSettings) {
                released = true;
            }

            show(false);

            const back = withSettings && !bar ? button('settings') : null;
            const target = !dialog.matches(':modal') && opener?.isConnected ? opener : back || firstAction();
            opener = null;
            target?.focus();

            return;
        }

        closed();
    });

    // Keep the focused element above the sticky buttons; WebKit ignores scroll-padding when focusing
    dialog.addEventListener('focusin', (event) => {
        requestAnimationFrame(() => {
            const buttons = event.target.closest('.juhe-consent__buttons') ? null : dialog.querySelector('.juhe-consent__buttons');

            if (!buttons || 'sticky' !== getComputedStyle(buttons).position) {
                return;
            }

            const overlap = event.target.getBoundingClientRect().bottom - buttons.getBoundingClientRect().top;

            if (overlap > 0) {
                dialog.scrollBy(0, overlap + 8);
            }
        });
    });

    dialog.addEventListener('click', (event) => {
        const action = event.target.closest('[data-juhe-consent-action]')?.dataset.juheConsentAction;

        if ('all' === action) {
            choose(aliases, state?.services || []);
        } else if ('essential' === action) {
            choose([], []);
        } else if ('settings' === action) {
            show(true);
        } else if ('save' === action) {
            // The switches replace all optional choices. Without service switches, single-service approvals go.
            choose(...selection());
        }
    });

    document.addEventListener('click', (event) => {
        const load = event.target.closest('.juhe-consent-placeholder [data-juhe-consent-load], .juhe-consent-placeholder [data-juhe-consent-allow]');

        if (load) {
            const placeholder = load.closest('.juhe-consent-placeholder');
            focusAfterReveal = placeholder;

            if (load.hasAttribute('data-juhe-consent-allow')) {
                save(state?.groups || [], [...(state?.services || []), placeholder.dataset.juheConsentService]);
            } else {
                running.add(placeholder.dataset.juheConsentService);
                reveal(placeholder);
            }

            return;
        }

        const trigger = event.target.closest(openers);

        if (trigger) {
            event.preventDefault();
            open(trigger);
        }
    });

    document.querySelectorAll(openers).forEach((element) => element.setAttribute('aria-haspopup', 'dialog'));

    window.juheConsent = api;

    /*
     * Code that ran before this script queued functions in window.juheConsentQueue.
     * They run now, before any service starts, and later pushes run at once.
     */
    const queued = Array.isArray(window.juheConsentQueue) ? window.juheConsentQueue : [];
    const run = (callback) => {
        if ('function' === typeof callback) {
            call(callback, api);
        }
    };

    window.juheConsentQueue = {
        push: (...callbacks) => {
            callbacks.forEach(run);

            return 0;
        },
    };

    queued.forEach(run);
    document.dispatchEvent(new CustomEvent('juhe-consent:ready', { detail: snapshot() }));

    apply();
    deny();

    if (prompting()) {
        show(false);
    }
})();
