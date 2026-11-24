import { expect, test, type Page } from "@playwright/test";

/* eslint-disable @typescript-eslint/no-explicit-any */

// A page restored from the back/forward cache shows the state it was left in.
// A choice made on another page meanwhile must count there too. The restore is
// simulated with the pageshow event the browser fires (persisted: true).
// Banner 3 (/en/home): service s10 (group "statistics") sets window.juheEnCode.

const dialog = (page: Page) => page.locator("#juhe-consent");

const restore = (page: Page, persisted = true): Promise<void> =>
	page.evaluate((value) => {
		window.dispatchEvent(new PageTransitionEvent("pageshow", { persisted: value }));
	}, persisted);

test("a restored page takes the choice made on another page and closes the banner", async ({ page, context }) => {
	await page.goto("/en/home");
	await expect(dialog(page)).toBeVisible();

	const other = await context.newPage();
	await other.goto("/en/home");
	await other.locator("[data-juhe-consent-action='all']").click();
	await expect.poll(() => other.evaluate(() => (window as any).juheEnCode)).toBe(1);

	// An ordinary pageshow (no restore) changes nothing
	await restore(page, false);
	await expect(dialog(page)).toBeVisible();

	await restore(page);
	await expect(dialog(page)).toBeHidden();
	await expect.poll(() => page.evaluate(() => (window as any).juheEnCode)).toBe(1);
	expect(await page.evaluate(() => (window as any).juheConsent.state().decided)).toBe(true);
});

test("a restored page reloads when another page withdrew a running service", async ({ page, context }) => {
	await page.goto("/en/home");
	await page.locator("[data-juhe-consent-action='all']").click();
	await expect.poll(() => page.evaluate(() => (window as any).juheEnCode)).toBe(1);

	const other = await context.newPage();
	await other.goto("/en/home");
	const reloadedOther = other.waitForEvent("load");
	await other.evaluate(() => (window as any).juheConsent.open());
	await other.locator("[data-juhe-consent-action='essential']").click();
	await reloadedOther;

	const reloaded = page.waitForEvent("load");
	await restore(page);
	await reloaded;
	await page.waitForLoadState("load");
	expect(await page.evaluate(() => (window as any).juheEnCode), "withdrawn service ran after the restore").toBeUndefined();
	expect(await page.evaluate(() => (window as any).juheConsent.state().groups)).toEqual(["necessary"]);
});
