/*
 * Consent banner of juhe-it-solutions/contao-juhe-consent-bundle. No dependencies.
 *
 * The choice is stored in a first-party cookie (JSON: version, groups,
 * services allowed one by one, random ID, time). Blocked content is marked by
 * the server: script[type="text/plain"][data-juhe-consent-src], placeholders with
 * the original iframe in a <template>, and template[data-juhe-consent-code] with
 * the code of a service. All of it carries data-juhe-consent-group/-service.
 *
 * API: window.juheConsent.has('statistik'), .open(), .onChange(callback),
 * and the "juhe-consent" event on document after every change.
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

    const read = () => {
        const match = document.cookie.match(new RegExp(`(?:^|; )${config.cookie}=([^;]*)`));

        if (!match) {
            return null;
        }

        try {
            const value = JSON.parse(decodeURIComponent(match[1]));

            // A new banner version asks again
            if (value.v !== config.version || !Array.isArray(value.g)) {
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

    const reveal = (placeholder) => {
        const template = placeholder.querySelector('template');

        if (template) {
            placeholder.replaceWith(content(template));
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

    const api = {
        has: (name) => essential.includes(name) || (!!state && (state.groups.includes(name) || state.services.includes(name))),
        open: () => show(true),
        onChange: (callback) => {
            listeners.push(callback);
        },
    };

    const save = (groups, allowedServices) => {
        const previous = state;

        state = {
            id: previous?.id || uuid(),
            groups: [...new Set([...essential, ...groups])],
            services: [...new Set(allowedServices)],
        };

        const value = encodeURIComponent(JSON.stringify({ v: config.version, g: state.groups, s: state.services, id: state.id, t: Math.floor(Date.now() / 1000) }));
        document.cookie = `${config.cookie}=${value}; path=/; max-age=31536000; SameSite=Lax${'https:' === location.protocol ? '; Secure' : ''}`;
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

    const toggleSettings = (open) => {
        settings.hidden = !open;
        button('settings').hidden = open;
        button('save').hidden = !open;
    };

    // The card leaves the page usable; the settings are a modal dialog
    const show = (withSettings) => {
        checkboxes.forEach((checkbox) => {
            if (!checkbox.disabled) {
                checkbox.checked = !!state?.groups.includes(checkbox.value);
            }
        });

        toggleSettings(withSettings);
        dialog.classList.toggle('juhe-consent--settings', withSettings);

        if (dialog.open) {
            dialog.close();
        }

        if (withSettings) {
            dialog.showModal();
            settings.querySelector('input:not([disabled])')?.focus();
        } else {
            // The card is not modal: first in the page, so the Tab key reaches it first
            if (document.body.firstElementChild !== dialog) {
                document.body.prepend(dialog);
            }

            dialog.show();
        }
    };

    const close = () => {
        dialog.close();
    };

    // Closed without a choice (Esc in the settings): back to the card, if the banner asks at all
    dialog.addEventListener('close', () => {
        if (!state && !dialog.open && config.ask) {
            show(false);
        }
    });

    dialog.addEventListener('click', (event) => {
        const action = event.target.closest('[data-juhe-consent-action]')?.dataset.juheConsentAction;

        if ('all' === action) {
            save(aliases, state?.services || []);
            close();
        } else if ('essential' === action) {
            save([], []);
            close();
        } else if ('settings' === action) {
            show(true);
        } else if ('save' === action) {
            save(checkboxes.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value), state?.services || []);
            close();
        }
    });

    document.addEventListener('click', (event) => {
        const load = event.target.closest('.juhe-consent-placeholder [data-juhe-consent-load], .juhe-consent-placeholder [data-juhe-consent-allow]');

        if (load) {
            const placeholder = load.closest('.juhe-consent-placeholder');

            if (load.hasAttribute('data-juhe-consent-allow')) {
                save(state?.groups || [], [...(state?.services || []), placeholder.dataset.juheConsentService]);
            } else {
                reveal(placeholder);
            }

            return;
        }

        const opener = event.target.closest('[data-juhe-consent-open], a[href$="#cookie-einstellungen"]');

        if (opener) {
            event.preventDefault();
            show(true);
        }
    });

    window.juheConsent = api;

    apply();

    if (!state && config.ask) {
        show(false);
    }
})();
