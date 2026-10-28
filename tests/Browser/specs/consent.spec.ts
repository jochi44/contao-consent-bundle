import AxeBuilder from "@axe-core/playwright";
import { execFileSync } from "node:child_process";
import { expect, test, type Page } from "@playwright/test";

/* eslint-disable @typescript-eslint/no-explicit-any */

// Review isolation: fulfil provider requests locally, never contact live services.
test.beforeEach(async ({ context }) => {
    await context.route('**/*', route => new URL(route.request().url()).hostname === '127.0.0.1'
        ? route.continue()
        : route.fulfill({ status: 200, body: '', contentType: route.request().resourceType() === 'script' ? 'application/javascript' : 'text/html' }));
});

// A direct SQL change skips Contao's cache tag invalidation, and the seeded root
// caches pages for 300 s, so the HTTP cache is dropped after every change.
const sql = (query: string): void => {
	execFileSync("docker", ["exec", "juhe-consent-browser-db", "mariadb", "-uroot", `-p${process.env.CONSENT_DB_PW}`, "contao", "-e", query]);
	execFileSync("docker", ["exec", "juhe-consent-browser-php", "rm", "-rf", "/var/www/html/var/cache/prod/http_cache"]);
};
const setBanner = (layout: string, blocking: boolean): void => sql(`UPDATE tl_juhe_consent_banner SET layout='${layout}', blocking=${blocking ? 1 : 0} WHERE id=1`);

const dialog = (page: Page) => page.locator("#juhe-consent");
const isModal = (page: Page) => dialog(page).evaluate((d) => d.matches(":modal"));
const active = (page: Page) =>
	page.evaluate(() => {
		const el = document.activeElement;
		return el ? `${el.tagName.toLowerCase()}${el.getAttribute("data-juhe-consent-action") ? "@" + el.getAttribute("data-juhe-consent-action") : ""}${el.getAttribute("value") ? "=" + el.getAttribute("value") : ""}${el.className ? "." + String(el.className).split(" ")[0] : ""}` : "none";
	});
const focusInBanner = (page: Page) => page.evaluate(() => !!document.activeElement?.closest("#juhe-consent"));

const axe = async (page: Page, include: string): Promise<void> => {
	// Measure the end state, not the fade-in (WebKit lists a new animation only from the next frame)
	await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
	await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== "running"));
	const result = await new AxeBuilder({ page }).include(include).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
	expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`), include).toEqual([]);
};

// WCAG 2.1.2: Tab must lead out of a non-modal banner into the page
const tabOut = async (page: Page): Promise<void> => {
	for (let i = 0; i < 40; i++) {
		await page.keyboard.press("Tab");
		if (await page.evaluate(() => document.activeElement !== document.body && !document.activeElement?.closest("#juhe-consent"))) return;
	}
	throw new Error("keyboard focus is trapped in the banner");
};

// WCAG 2.4.11: every focus stop inside the dialog is visible, not covered by the sticky buttons
const tabThroughUnobscured = async (page: Page): Promise<string[]> => {
	const count = await page.evaluate(() => [...document.querySelectorAll<HTMLElement>("#juhe-consent :is(a[href], button, input, summary)")].filter((e) => e.checkVisibility() && !(e as HTMLInputElement).disabled).length);
	const problems: string[] = [];
	await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== "running"));
	for (let i = 0; i < count; i++) {
		// What a person sees: the state after the next frames
		await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
		const problem = await page.evaluate(() => {
			const el = document.activeElement as HTMLElement | null;
			if (!el?.closest("#juhe-consent")) return null;
			const r = el.getBoundingClientRect();
			const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
			return hit && (hit === el || el.contains(hit) || hit.contains(el)) ? null : `${el.outerHTML.slice(0, 70)} covered by ${hit?.outerHTML.slice(0, 70)}`;
		});
		if (problem) problems.push(problem);
		await page.keyboard.press("Tab");
	}
	return problems;
};

const openDetails = (page: Page) => page.evaluate(() => document.querySelectorAll<HTMLDetailsElement>("#juhe-consent details").forEach((d) => (d.open = true)));

test("first visit, accept all, reopen and withdraw", async ({ page, context }) => {
	const errors: string[] = [];
	page.on("pageerror", (e) => errors.push(e.message));
	await page.goto("/");
	await expect(dialog(page)).toBeVisible();
	await expect(page.locator(".juhe-consent-placeholder")).toHaveCount(2);
	expect(await page.evaluate(() => (window as any).juheCheckCode)).toBeUndefined();
	await page.locator("[data-juhe-consent-action='all']").click();
	await expect(dialog(page)).toBeHidden();
	await expect(page.locator("iframe[src*='youtube.com']")).toHaveCount(2);
	await expect.poll(() => page.evaluate(() => (window as any).juheCheckCode)).toBe(1);
	// The choice lives in localStorage; a cookie would make Contao's cache proxy pass every page to PHP
	expect((await context.cookies()).some((c) => c.name.startsWith("juhe_consent"))).toBe(false);
	expect(await page.evaluate(() => JSON.parse(localStorage.getItem("juhe_consent_1") ?? "null")?.g)).toContain("marketing");
	expect(await page.evaluate(() => (window as any).juheConsent.has("marketing"))).toBe(true);
	await page.reload();
	await expect(dialog(page)).toBeHidden();
	await page.locator(".juhe-consent-settings-button").click();
	await expect(dialog(page)).toBeVisible();
	await expect(page.locator("[data-juhe-consent-settings]")).toBeVisible();
	await page.locator("input[value='marketing']").uncheck();
	await page.locator("[data-juhe-consent-action='save']").click();
	await page.waitForLoadState("load");
	expect(await page.evaluate(() => (window as any).juheConsent.has("marketing"))).toBe(false);
	expect(errors).toEqual([]);
});

test("card: not modal, no keyboard trap, axe clean", async ({ page }) => {
	await page.goto("/");
	await expect(dialog(page)).toBeVisible();
	expect(await isModal(page)).toBe(false);
	expect(await page.evaluate(() => (window as any).juheConsent.pending())).toBe(true);
	await page.keyboard.press("Tab");
	expect(await focusInBanner(page)).toBe(true);
	await tabOut(page);
	// Esc on the card changes nothing
	await page.keyboard.press("Escape");
	await expect(dialog(page)).toBeVisible();
	await axe(page, "#juhe-consent");
});

test("settings from the opener: modal, Esc goes back, focus returns to the opener", async ({ page }) => {
	await page.goto("/");
	const opener = page.locator(".juhe-consent-settings-button");
	await expect(opener).toHaveAttribute("aria-haspopup", "dialog");
	const events: string[] = [];
	await page.exposeFunction("logEvent", (e: string) => events.push(e));
	await page.evaluate(() => {
		document.addEventListener("juhe-consent:open", (e) => (window as any).logEvent(`open:${(e as CustomEvent).detail.settings}`));
		document.addEventListener("juhe-consent:close", () => (window as any).logEvent("close"));
	});
	await opener.focus();
	await page.keyboard.press("Enter");
	expect(await isModal(page)).toBe(true);
	expect(await active(page)).toBe("input=marketing.juhe-consent-switch");
	await openDetails(page);
	await axe(page, "#juhe-consent");
	// Space toggles the switch
	await page.keyboard.press("Space");
	await expect(page.locator("input[value='marketing']")).toBeChecked();
	await page.keyboard.press("Escape");
	await expect(dialog(page)).toBeVisible();
	expect(await isModal(page)).toBe(false);
	expect(await active(page)).toBe("a.juhe-consent-settings-button");
	// After a choice from the settings, focus is back on the opener as well
	await page.keyboard.press("Enter");
	await page.locator("[data-juhe-consent-action='essential']").focus();
	await page.keyboard.press("Enter");
	await expect(dialog(page)).toBeHidden();
	expect(await active(page)).toBe("a.juhe-consent-settings-button");
	await page.keyboard.press("Enter");
	await page.keyboard.press("Escape");
	await expect(dialog(page)).toBeHidden();
	expect(await active(page)).toBe("a.juhe-consent-settings-button");
	// The first "open:false" (page load) came before the listeners
	await expect.poll(() => events).toEqual(["open:true", "open:false", "open:true", "close", "open:true", "close"]);
});

test("placeholders: named buttons, focus moves to the loaded content", async ({ page }) => {
	await page.goto("/");
	await page.locator("[data-juhe-consent-action='essential']").click();
	const described = await page.locator(".juhe-consent-placeholder button").evaluateAll((buttons) =>
		buttons.map((b) => document.getElementById(b.getAttribute("aria-describedby") ?? "")?.textContent ?? "missing"),
	);
	expect(described).toHaveLength(4);
	expect(described.every((text) => text.includes("YouTube"))).toBe(true);
	expect(new Set(await page.locator(".juhe-consent-placeholder__text").evaluateAll((p) => p.map((e) => e.id))).size).toBe(2);
	await axe(page, ".juhe-consent-placeholder");
	await page.locator("[data-juhe-consent-load]").nth(1).focus();
	await page.keyboard.press("Enter");
	await expect(page.locator("iframe[src*='youtube.com']")).toHaveCount(1);
	expect(await active(page)).toBe("iframe");
	// "Always allow" loads the other one and focuses it
	await page.locator("[data-juhe-consent-allow]").first().focus();
	await page.keyboard.press("Enter");
	await expect(page.locator("iframe[src*='youtube.com']")).toHaveCount(2);
	expect(await active(page)).toBe("iframe");
});

test("placeholders: kind, decorative glyph, group names, unique control names", async ({ page }) => {
	await page.goto("/");
	await page.locator("[data-juhe-consent-action='essential']").click();
	const placeholders = page.locator(".juhe-consent-placeholder");
	await expect(placeholders.first()).toHaveAttribute("data-juhe-consent-kind", "video");
	await expect(placeholders.first()).toHaveAttribute("role", "group");
	await expect(placeholders.first().locator("svg.juhe-consent-glyph")).toHaveAttribute("aria-hidden", "true");
	await expect(placeholders.first()).toHaveAccessibleName(/YouTube/);
	await expect(placeholders.first().getByRole("button", { name: /^Inhalt laden\s*\(YouTube\)$/ })).toHaveCount(1);
	await expect(placeholders.first().getByRole("link", { name: /^Datenschutz des Anbieters\s+YouTube\s+\(öffnet/ })).toHaveAttribute("href", "https://policies.google.com/privacy");
	// Nothing in the placeholder reaches the provider: no src or href outside the template but the privacy link
	const external = await placeholders.evaluateAll((list) =>
		list.flatMap((p) => [...p.querySelectorAll("[src], [srcset], image, use")].map((e) => e.outerHTML.slice(0, 60))),
	);
	expect(external).toEqual([]);
	for (const [width, font] of [
		[320, 16],
		[320, 32],
		[1280, 16],
	]) {
		await page.setViewportSize({ width, height: 700 });
		await page.evaluate((size) => (document.documentElement.style.fontSize = `${size}px`), font);
		const clipped = await placeholders.evaluateAll((list) =>
			list.filter((p) => p.scrollHeight > p.clientHeight + 1 || p.scrollWidth > p.clientWidth + 1).length,
		);
		expect(clipped, `${width}px at ${font}px`).toBe(0);
		// The parts stack without overlapping (a ratio box once squeezed the glyph row)
		const overlaps = await placeholders.evaluateAll((list) =>
			list.flatMap((p) => {
				const parts = [...p.children].filter((e) => e.tagName !== "TEMPLATE").map((e) => e.getBoundingClientRect());
				return parts.slice(1).filter((r, i) => r.top < parts[i].bottom - 0.5).map(() => p.dataset.juheConsentKind);
			}),
		);
		expect(overlaps, `${width}px at ${font}px`).toEqual([]);
		expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
	}
});

test("placeholders in forced colors: glyph in system colors, buttons framed", async ({ page }) => {
	await page.emulateMedia({ forcedColors: "active" });
	await page.goto("/");
	await page.locator("[data-juhe-consent-action='essential']").click();
	const frame = page.locator(".juhe-consent-glyph__frame").first();
	expect(await frame.evaluate((e) => getComputedStyle(e).stroke)).not.toBe("none");
	expect(await page.locator(".juhe-consent-placeholder button").first().evaluate((e) => getComputedStyle(e).borderTopStyle)).toBe("solid");
});

test("a long text on a phone: the dialog opens at its start, focus on the headline", async ({ page }) => {
	await page.setViewportSize({ width: 390, height: 844 });
	await page.goto("/");
	await page.evaluate(() => {
		const block = document.createElement("p");
		block.textContent = "Werbung";
		block.style.blockSize = "900px";
		document.querySelector(".juhe-consent__text")?.append(block);
	});
	await page.evaluate(() => (window as any).juheConsent.open());
	await expect(page.locator("#juhe-consent-headline")).toBeFocused();
	expect(await dialog(page).evaluate((d) => d.scrollTop)).toBe(0);
	// Tab goes on into the dialog
	await page.keyboard.press("Tab");
	expect(await focusInBanner(page)).toBe(true);
	expect(await active(page)).not.toBe("h2.juhe-consent__headline");
	// Without the long text the first switch gets focus as before
	await page.reload();
	await page.evaluate(() => (window as any).juheConsent.open());
	expect(await active(page)).toBe("input=marketing.juhe-consent-switch");
});

test("names and descriptions", async ({ page }) => {
	await page.goto("/");
	await page.evaluate(() => (window as any).juheConsent.open());
	await expect(dialog(page)).toHaveAccessibleName("Cookies");
	await expect(dialog(page)).toHaveAccessibleDescription("Wir verwenden Cookies.");
	await expect(page.locator("input[value='marketing']")).toHaveAccessibleName("Marketing");
	await expect(page.locator("input[value='marketing']")).toHaveAccessibleDescription("Videos und Statistik.");
	await expect(page.locator("input[value='notwendig']")).toHaveAccessibleName("Notwendig Immer aktiv");
	await expect(page.locator("#juhe-consent summary").nth(1)).toHaveAccessibleName(/Marketing/);
	await openDetails(page);
	await expect(page.locator(".juhe-consent-service a")).toHaveAccessibleName(/öffnet in einem neuen Fenster/);
});

test("targets are at least 44 px", async ({ page }) => {
	await page.goto("/");
	await page.evaluate(() => (window as any).juheConsent.open());
	await openDetails(page);
	const small = await page.evaluate(() =>
		[...document.querySelectorAll<HTMLElement>("#juhe-consent :is(button, summary, .juhe-consent__links a, .juhe-consent-service a, .juhe-consent-group__head), .juhe-consent-placeholder :is(button, a)")]
			.filter((e) => e.checkVisibility())
			.filter((e) => e.getBoundingClientRect().height < 43.5 || e.getBoundingClientRect().width < 43.5)
			.map((e) => e.outerHTML.slice(0, 80)),
	);
	expect(small).toEqual([]);
});

for (const [width, height] of [
	[320, 256],
	[320, 640],
	[1280, 800],
]) {
	test(`focus is never hidden at ${width}x${height}`, async ({ page }) => {
		await page.setViewportSize({ width, height });
		await page.goto("/");
		// Details first: opened later, they would push the focused switch out of view
		await openDetails(page);
		await page.evaluate(() => (window as any).juheConsent.open());
		expect(await tabThroughUnobscured(page)).toEqual([]);
		expect(await dialog(page).evaluate((d) => d.scrollWidth <= d.clientWidth)).toBe(true);
	});
}

test("forced colors and reduced motion", async ({ page }) => {
	await page.emulateMedia({ forcedColors: "active", reducedMotion: "reduce" });
	await page.goto("/");
	await page.evaluate(() => (window as any).juheConsent.open());
	const swatch = page.locator("input[value='marketing']");
	expect(await swatch.evaluate((e) => getComputedStyle(e).appearance)).toBe("auto");
	expect(await swatch.evaluate((e) => getComputedStyle(e).transitionDuration)).toBe("0s");
	expect(await dialog(page).evaluate((e) => getComputedStyle(e).animationName)).toBe("none");
});

test.describe.serial("bar layout, blocking", () => {
	test.beforeAll(() => setBanner("bar", true));
	test.afterAll(() => setBanner("card", false));

	test("blocks the page, Esc releases it, reopening is modal", async ({ page }) => {
		await page.goto("/");
		await expect(dialog(page)).toBeVisible();
		await expect(dialog(page)).toHaveAttribute("data-juhe-consent-layout", "bar");
		expect(await isModal(page)).toBe(true);
		await expect(page.locator("[data-juhe-consent-settings]")).toBeVisible();
		await expect(page.locator("[data-juhe-consent-action='save']")).toBeVisible();
		await expect(page.locator("[data-juhe-consent-action='settings']")).toBeHidden();
		expect(await active(page)).toBe("button@essential.juhe-consent__button");
		await axe(page, "#juhe-consent");
		expect(await tabThroughUnobscured(page)).toEqual([]);
		await page.keyboard.press("Escape");
		await expect(dialog(page)).toBeVisible();
		expect(await isModal(page)).toBe(false);
		expect(await focusInBanner(page)).toBe(true);
		expect(await page.evaluate(() => (window as any).juheConsent.pending())).toBe(true);
		await tabOut(page);
		await page.locator("[data-juhe-consent-action='essential']").click();
		await expect(dialog(page)).toBeHidden();
		expect(await page.evaluate(() => (window as any).juheConsent.pending())).toBe(false);
		await page.locator(".juhe-consent-settings-button").focus();
		await page.keyboard.press("Enter");
		expect(await isModal(page)).toBe(true);
		await page.keyboard.press("Escape");
		await expect(dialog(page)).toBeHidden();
		expect(await active(page)).toBe("a.juhe-consent-settings-button");
	});

	test("bar at 320 px: no horizontal overflow, axe clean", async ({ page }) => {
		await page.setViewportSize({ width: 320, height: 640 });
		await page.goto("/");
		expect(await dialog(page).evaluate((d) => d.scrollWidth <= d.clientWidth)).toBe(true);
		await axe(page, "#juhe-consent");
	});
});

test("backend screens load", async ({ page, browserName }) => {
	// WebKit drops Contao's Secure session cookie on plain http://127.0.0.1; Chromium and Firefox keep it
	test.skip(browserName === "webkit", "Secure session cookie over http");
	await page.goto("/contao/login");
	await page.locator("input[name='username']").fill("check");
	await page.locator("input[name='password']").fill("check-pass-123");
	await page.locator("button[type='submit']").first().click();
	await page.waitForLoadState("load");
	for (const url of [
		"/contao?do=juhe_consent",
		"/contao?do=juhe_consent&act=edit&id=1",
		"/contao?do=juhe_consent&table=tl_juhe_consent_group&id=1",
		"/contao?do=juhe_consent&table=tl_juhe_consent_group&act=edit&id=2",
		"/contao?do=juhe_consent&table=tl_juhe_consent_service&id=2",
		"/contao?do=juhe_consent&table=tl_juhe_consent_service&act=edit&id=3",
		"/contao?do=juhe_consent_log",
		"/contao?do=page&act=edit&id=1",
		"/contao?do=article&table=tl_content&act=edit&id=2",
	]) {
		const response = await page.goto(url);
		expect(response?.status(), url).toBeLessThan(400);
		await expect(page.locator("#main, main").first(), url).toBeVisible();
		await expect(page.locator("input[name='password']"), `${url}: still logged in`).toHaveCount(0);
		expect(await page.content(), url).not.toMatch(/An exception occurred|Fatal error|Unknown column/);
	}
	await page.goto("/contao?do=juhe_consent&act=edit&id=1");
	await expect(page.locator("select[name='layout']")).toHaveCount(1);
	await expect(page.locator("input[type='checkbox'][name='blocking']")).toHaveCount(1);
	await page.goto("/contao?do=page&act=edit&id=1");
	await expect(page.locator("[name='juheConsentBanner']")).toHaveCount(1);
});
