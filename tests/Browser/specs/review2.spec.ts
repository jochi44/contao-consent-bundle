import { execFileSync } from "node:child_process";
import { expect, test, type Page } from "@playwright/test";

/* eslint-disable @typescript-eslint/no-explicit-any */

// Provider URLs a browser reads differently from PHP, and prefixed cookies on
// withdrawal.

const providerRequests: string[] = [];

test.beforeEach(async ({ context }) => {
	providerRequests.length = 0;
	await context.route("**/*", (route) => {
		const url = new URL(route.request().url());
		if (url.hostname === "127.0.0.1" || url.hostname === "localhost") return route.continue();
		providerRequests.push(url.href);
		const type = route.request().resourceType();
		return route.fulfill({ status: 200, body: "", contentType: type === "script" ? "application/javascript" : "text/html" });
	});
});

const db = (query: string): string =>
	execFileSync("docker", ["exec", "juhe-consent-browser-db", "mariadb", "-uroot", `-p${process.env.CONSENT_DB_PW}`, "-N", "-B", "contao", "-e", query]).toString().trim();
const sql = (query: string): void => {
	db(query);
	execFileSync("docker", ["exec", "juhe-consent-browser-php", "rm", "-rf", "/var/www/html/var/cache/prod/http_cache"]);
};
const settle = (page: Page) => page.waitForTimeout(400);

test.describe.serial("odd addresses and prefixed cookies", () => {
	test("iframes and scripts with extra slashes after https: wait for consent", async ({ page }) => {
		// The layout's own scripts field prints raw HTML at the end of the body
		sql(`UPDATE tl_layout SET script = CONCAT(script, '<iframe src="https:///www.youtube.com/embed/r2slash" title="r2"></iframe><script src="https:////www.googletagmanager.com/r2slash.js"></script>') WHERE id = 1`);
		try {
			await settle(page);
			await page.goto("/");
			await page.waitForLoadState("networkidle");
			expect(providerRequests.filter((url) => url.includes("r2slash")), "provider contacted before consent").toEqual([]);
			expect(await page.evaluate(() => [...document.querySelectorAll(".juhe-consent-placeholder template")].some((t) => t.innerHTML.includes("r2slash")))).toBe(true);
			// The placeholder knows it is a video (EmbedKind reads the host like the blocker)
			expect(await page.evaluate(() => Boolean([...document.querySelectorAll(".juhe-consent-placeholder")].find((p) => p.querySelector("template")?.innerHTML.includes("r2slash"))?.querySelector(".juhe-consent-glyph--video")))).toBe(true);
			expect(await page.locator("script[type='text/plain'][data-juhe-consent-src*='r2slash']").count()).toBe(1);
			await page.locator("[data-juhe-consent-action='all']").click();
			// The iframe comes back as written (Chromium then refuses to load https:/// in a frame,
			// so its request is not asserted); the held script loads
			await expect(page.locator("iframe[src='https:///www.youtube.com/embed/r2slash']")).toHaveCount(1);
			await expect.poll(() => providerRequests.filter((url) => url.includes("r2slash.js")).length).toBe(1);
		} finally {
			sql("UPDATE tl_layout SET script = SUBSTRING_INDEX(script, '<iframe src=\"https:///www.youtube.com/embed/r2slash', 1) WHERE id = 1");
		}
	});

	test("withdrawal expires __Secure- and __Host- cookies in the browser", async ({ page, browserName }) => {
		sql("UPDATE tl_juhe_consent_service SET cookies = CONCAT(cookies, '\\n__Secure-r2*: 1 Tag\\n__Host-r2: 1 Tag') WHERE id = 3");
		try {
			// Only the script's own removal counts here: the server cleanup is cut off
			await page.route("**/_juhe_consent/cookies", (route) => route.abort());
			await settle(page);
			await page.goto("http://localhost:8199/");
			await page.locator("[data-juhe-consent-action='all']").click();
			await page.evaluate(() => {
				document.cookie = "__Secure-r2a=1; path=/; secure";
				document.cookie = "__Host-r2=1; path=/; secure";
			});
			const set = await page.evaluate(() => document.cookie);
			test.skip(!set.includes("__Host-r2"), `${browserName} sets no Secure cookie on http://localhost`);
			await page.evaluate(() => (window as any).juheConsent.open());
			const reloaded = page.waitForEvent("load");
			await page.locator("[data-juhe-consent-action='essential']").click();
			await reloaded;
			expect(await page.evaluate(() => document.cookie)).not.toMatch(/__(Secure|Host)-r2/);
		} finally {
			sql("UPDATE tl_juhe_consent_service SET cookies = SUBSTRING_INDEX(cookies, '\\n__Secure-r2', 1) WHERE id = 3");
		}
	});
});
