/*
 * Consent banner of juhe-it-solutions/contao-consent-bundle. No dependencies.
 *
 * The choice is stored in localStorage under juhe_consent_<banner id> (JSON:
 * version, groups, services allowed one by one, random ID, time), not in a
 * cookie: a request cookie makes Contao's HTTP cache pass every page to PHP, and
 * the server never needs the choice. It expires after one year; without
 * storage (private mode, blocked site data) the banner simply asks again.
 * Blocked content is marked by
 * the server: script[type="text/plain"][data-juhe-consent-src], placeholders with
 * the original iframe in a <template>, and template[data-juhe-consent-code] with
 * the code of a service. All of it carries data-juhe-consent-group/-service.
 *
 * API: window.juheConsent.has('statistik'), .open(), .pending() (the banner
 * waits for a first choice), .onChange(callback), and these events on document:
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
    const running = new Set();

    const essential = config.groups.filter((group) => group.essential).map((group) => group.alias);
    const aliases = config.groups.map((group) => group.alias);
    const services = config.groups.flatMap((group) => group.services.map((service) => ({ ...service, group: group.alias })));

    const maxAge = 31536000;

    const read = () => {
        try {
            const value = JSON.parse(localStorage.getItem(config.storage) || 'null');

            // A new banner version or a choice older than a year asks again
            if (!value || value.v !== config.version || !Array.isArray(value.g) || Date.now() / 1000 - Number(value.t) > maxAge) {
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

    let state = read();

    const allows = (consent, group, service) => essential.includes(group) || (!!consent && (consent.groups.includes(group) || consent.services.includes(service)));
    const allowed = (element) => allows(state, element.dataset.juheConsentGroup, element.dataset.juheConsentService);

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

    const apply = () => {
        document.querySelectorAll('script[type="text/plain"][data-juhe-consent-src]').forEach((old) => {
            if (allowed(old)) {
                running.add(old.dataset.juheConsentService);
                old.replaceWith(runnable(old));
            }
        });

        document.querySelectorAll('.juhe-consent-placeholder').forEach((placeholder) => {
            if (allowed(placeholder)) {
                running.add(placeholder.dataset.juheConsentService);
                reveal(placeholder);
            }
        });

        document.querySelectorAll('template[data-juhe-consent-code]').forEach((template) => {
            if (allowed(template)) {
                running.add(template.dataset.juheConsentService);
                document.body.append(content(template));
                template.remove();
            }
        });
    };

    const removeCookies = (patterns) => {
        const names = document.cookie.split('; ').map((cookie) => cookie.split('=')[0]).filter(Boolean);
        const parts = location.hostname.split('.');
        const domains = [''];

        // Trackers often set their cookies on the parent domain
        for (let i = 0; i < parts.length - 1; i += 1) {
            domains.push(`; domain=.${parts.slice(i).join('.')}`);
        }

        patterns.forEach((pattern) => {
            const matches = pattern.endsWith('*') ? names.filter((name) => name.startsWith(pattern.slice(0, -1))) : [pattern];

            matches.forEach((name) => domains.forEach((domain) => {
                document.cookie = `${name}=; path=/; max-age=0${domain}`;
            }));
        });
    };

    const log = () => {
        if (!config.logUrl) {
            return;
        }

        fetch(config.logUrl, {
            method: 'POST',
            keepalive: true,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ banner: config.banner, version: config.version, id: state.id, groups: state.groups, services: state.services, url: location.pathname }),
        }).catch(() => {});
    };

    const save = (groups, allowedServices) => {
        const previous = state;

        state = {
            id: previous?.id || uuid(),
            groups: [...new Set([...essential, ...groups])],
            services: [...new Set(allowedServices)],
        };

        try {
            localStorage.setItem(config.storage, JSON.stringify({ v: config.version, g: state.groups, s: state.services, id: state.id, t: Math.floor(Date.now() / 1000) }));
        } catch {
            // No storage: the choice holds for this page only
        }

        log();

        // Withdrawn: the cookies go, and scripts that already run only stop with a reload
        const withdrawn = services.filter((service) => !allows(state, service.group, service.key) && (running.has(service.key) || allows(previous, service.group, service.key)));
        removeCookies(withdrawn.flatMap((service) => service.cookies));

        if (withdrawn.some((service) => running.has(service.key))) {
            location.reload();

            return;
        }

        apply();
        listeners.forEach((callback) => callback(api));
        document.dispatchEvent(new CustomEvent('juhe-consent', { detail: { groups: [...state.groups], services: [...state.services] } }));
    };

    // The dialog
    const settings = dialog.querySelector('[data-juhe-consent-settings]');
    const button = (action) => dialog.querySelector(`[data-juhe-consent-action="${action}"]`);
    const checkboxes = [...dialog.querySelectorAll('[data-juhe-consent-checkbox]')];
    const bar = 'bar' === config.layout;
    const pending = () => !state && !!config.ask;
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
        has: (name) => essential.includes(name) || (!!state && (state.groups.includes(name) || state.services.includes(name))),
        open: () => open(document.activeElement),
        pending,
        onChange: (callback) => {
            listeners.push(callback);
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
        if (pending()) {
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
            // The group switches replace all optional choices, including single-service approvals.
            choose(checkboxes.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value), []);
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
                reveal(placeholder);
            }

            return;
                running.add(placeholder.dataset.juheConsentService);
        }

        const trigger = event.target.closest(openers);

        if (trigger) {
            event.preventDefault();
            open(trigger);
        }
    });

    document.querySelectorAll(openers).forEach((element) => element.setAttribute('aria-haspopup', 'dialog'));

    window.juheConsent = api;

    apply();

    if (pending()) {
        show(false);
    }
})();
