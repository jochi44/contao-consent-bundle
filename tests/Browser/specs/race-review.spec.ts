import { expect, test, type Page } from "@playwright/test";

/* eslint-disable @typescript-eslint/no-explicit-any */

// Review probe: a withdrawal reloads the page itself. The reloaded page must not
// run a withdrawn service, even if it reads the previous localStorage value.
// Banner 3 (/en/home): service s10 (group "statistics") sets window.juheEnCode
// and lists no cookies, so the withdrawal reloads at once (no cleanup request).

const KEY = "juhe_consent_3";
const dialog = (page: Page) => page.locator("#juhe-consent");

const acceptAll = async (page: Page): Promise<void> => {
	await page.goto("/en/home");
	await page.locator("[data-juhe-consent-action='all']").click();
	await expect.poll(() => page.evaluate(() => (window as any).juheEnCode)).toBe(1);
};

const withdrawAndReload = async (page: Page): Promise<void> => {
	await page.evaluate(() => (window as any).juheConsent.open());
	await expect(dialog(page)).toBeVisible();
	const reloaded = page.waitForEvent("load");
	await page.locator("[data-juhe-consent-action='essential']").click();
	await reloaded;
	await page.waitForLoadState("load");
};

test("the reloaded page keeps the withdrawal even if it reads the old stored choice", async ({ page, context }) => {
	await acceptAll(page);
	const old = await page.evaluate((key) => localStorage.getItem(key), KEY);
	expect(JSON.parse(old ?? "null")?.g).toContain("statistics");
	// The next document reads the previous value once, as a page in a process that
	// has not seen the write yet would
	await page.addInitScript(([key, value]) => {
		const original = Storage.prototype.getItem;
		let once = true;
		Storage.prototype.getItem = function (name: string) {
			if (once && name === key) {
				once = false;
				return value;
			}
			return original.call(this, name);
		};
	}, [KEY, old] as const);
	await withdrawAndReload(page);
	await page.waitForTimeout(300);
	expect(await page.evaluate(() => (window as any).juheEnCode), "withdrawn service ran after the reload").toBeUndefined();
	expect(await page.evaluate(() => (window as any).juheConsent.state())).toEqual({ decided: true, groups: ["necessary"], services: [] });
	// The marker is used once: the history entry no longer carries it
	expect(await page.evaluate(() => JSON.stringify(history.state ?? null))).not.toContain("juheConsentReload");
	// A page without the stub reads the storage, which holds the withdrawal
	const fresh = await context.newPage();
	await fresh.goto("/en/home");
	expect(await fresh.evaluate(() => (window as any).juheEnCode)).toBeUndefined();
	expect(JSON.parse((await fresh.evaluate((key) => localStorage.getItem(key), KEY)) ?? "null")?.g).toEqual(["necessary"]);
});

test("withdraw and reload 20 times without a stubbed storage", async ({ page }) => {
	let ran = 0;
	for (let i = 0; i < 20; i += 1) {
		await acceptAll(page);
		await withdrawAndReload(page);
		if (await page.evaluate(() => (window as any).juheEnCode)) ran += 1;
		await page.evaluate((key) => localStorage.removeItem(key), KEY);
	}
	expect(ran, "withdrawn service ran after the own reload").toBe(0);
});

test("links to #juhe-consent and the older #cookie-einstellungen open the settings, also on load", async ({ page }) => {
	for (const hash of ["#juhe-consent", "#cookie-einstellungen"]) {
		await page.goto("/");
		await page.locator("[data-juhe-consent-action='essential']").click();
		await expect(dialog(page)).toBeHidden();
		// A link an editor wrote into a text, on this page and to another page
		await page.evaluate((h) => {
			document.body.insertAdjacentHTML("beforeend", `<p><a id="probe-local" href="${h}">local</a> <a id="probe-other" href="/kontakt${h}">other</a></p>`);
		}, hash);
		await page.locator("#probe-local").click();
		await expect(dialog(page), hash).toBeVisible();
		await expect(page.locator("#juhe-consent [data-juhe-consent-settings]")).toBeVisible();
		await page.keyboard.press("Escape");
		await expect(dialog(page)).toBeHidden();
		await page.locator("#probe-other").click();
		await expect(dialog(page), `${hash} on another page`).toBeVisible();
		await expect(page.locator("#juhe-consent [data-juhe-consent-settings]")).toBeVisible();
		await page.keyboard.press("Escape");
		await expect(dialog(page)).toBeHidden();
		// A real load (from another page), not a hash change in the same document
		await page.goto("/kontakt");
		await page.goto(`/${hash}`);
		await expect(dialog(page), `${hash} on load`).toBeVisible();
		await page.evaluate((key) => localStorage.removeItem(key), "juhe_consent_1");
	}
});
