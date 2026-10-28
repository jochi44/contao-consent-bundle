import AxeBuilder from "@axe-core/playwright";
import { execFileSync } from "node:child_process";
import { expect, test, type Page } from "@playwright/test";
import { launchDefaultFont, readRootFontSize } from "../helpers/default-font";
import { launchNativeZoom } from "../helpers/native-zoom";

/* eslint-disable @typescript-eslint/no-explicit-any */

// Banner-level options (batch A): page exclusions, additional information and
// further links, position, choice lifetime, privacy signals, logged-in users.

const providerRequests: string[] = [];

test.beforeEach(async ({ context }) => {
	providerRequests.length = 0;
	await context.route("**/*", (route) => {
		const url = new URL(route.request().url());
		if (url.hostname === "127.0.0.1") return route.continue();
		providerRequests.push(url.hostname);
		return route.fulfill({ status: 200, body: "", contentType: route.request().resourceType() === "script" ? "application/javascript" : "text/html" });
	});
});

const clearCache = (): void => {
	execFileSync("docker", ["exec", "juhe-consent-browser-php", "rm", "-rf", "/var/www/html/var/cache/prod/http_cache"]);
};
const sql = (query: string): void => {
	execFileSync("docker", ["exec", "juhe-consent-browser-db", "mariadb", "-uroot", `-p${process.env.CONSENT_DB_PW}`, "contao", "-e", query]);
	clearCache();
};
const banner = (set: string): void => sql(`UPDATE tl_juhe_consent_banner SET ${set} WHERE id=1`);
const reset = (): void =>
	banner(
		"version=1, layout='card', blocking=0, askOnFirstVisit=1, excludePages=NULL, infoText=NULL, infoPages=NULL, position='bottom-left', barPosition='bottom', lifetime=365, privacySignals=0, noOptionalForUsers=0",
	);

// Contao 5.7 stores backend input with HTML entities, 6.0 stores it decoded
const core6 = execFileSync("docker", ["exec", "juhe-consent-browser-php", "php", "-r", 'require "/var/www/html/vendor/autoload.php"; echo Composer\\InstalledVersions::getVersion("contao/core-bundle");']).toString().startsWith("6.");
const stored6or5 = (decoded: string, encoded: string): string => (core6 ? decoded : encoded);

const dialog = (page: Page) => page.locator("#juhe-consent");
const isModal = (page: Page) => dialog(page).evaluate((d) => d.matches(":modal"));
const settled = async (page: Page): Promise<void> => {
	await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
	await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== "running"));
};
const axe = async (page: Page, include: string): Promise<void> => {
	await settled(page);
	const result = await new AxeBuilder({ page }).include(include).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
	expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`), include).toEqual([]);
};
const tabOut = async (page: Page): Promise<void> => {
	for (let i = 0; i < 40; i++) {
		await page.keyboard.press("Tab");
		if (await page.evaluate(() => document.activeElement !== document.body && !document.activeElement?.closest("#juhe-consent"))) return;
	}
	throw new Error("keyboard focus is trapped in the banner");
};
const cacheHeader = async (page: Page, path: string): Promise<string> => (await page.request.get(path)).headers()["contao-cache"] ?? "";
const stored = (page: Page) => page.evaluate(() => localStorage.getItem("juhe_consent_1"));

test.describe.configure({ mode: "serial" });
test.beforeAll(reset);
test.afterAll(reset);

test.describe("excluded pages", () => {
	test.beforeAll(() => banner("excludePages='a:1:{i:0;s:1:\"4\";}', blocking=1"));
	test.afterAll(reset);

	test("the banner stays closed on an excluded page and asks on the others", async ({ page }) => {
		for (const pass of ["uncached", "cached"]) {
			await page.goto("/kontakt");
			await expect(page.locator(".juhe-consent-placeholder"), pass).toHaveCount(1);
			await expect(dialog(page), pass).toBeHidden();
			expect(await page.evaluate(() => (window as any).juheConsent.pending()), pass).toBe(true);
			expect(await page.evaluate(() => (window as any).juheConsent.has("marketing")), pass).toBe(false);
			await page.goto("/");
			await expect(dialog(page), pass).toBeVisible();
			expect(await isModal(page), pass).toBe(true);
		}
		expect(await cacheHeader(page, "/kontakt")).toMatch(/fresh/);
		expect(providerRequests).toEqual([]);
	});

	test("the settings still open there, Esc closes them and keeps the page free", async ({ page }) => {
		await page.goto("/kontakt");
		await page.evaluate(() => (window as any).juheConsent.open());
		await expect(dialog(page)).toBeVisible();
		expect(await isModal(page)).toBe(true);
		await page.keyboard.press("Escape");
		await expect(dialog(page)).toBeHidden();
		// The placeholder still loads on request
		await page.locator("[data-juhe-consent-load]").first().click();
		await expect(page.locator("iframe[src*='youtube']")).toHaveCount(1);
	});

	test("after a version change the excluded page stays closed, the others ask again", async ({ page }) => {
		await page.goto("/");
		await page.locator("[data-juhe-consent-action='all']").click();
		banner("version=2");
		await page.goto("/kontakt");
		await expect(dialog(page)).toBeHidden();
		expect(await page.evaluate(() => (window as any).juheConsent.has("marketing"))).toBe(false);
		await page.goto("/");
		await expect(dialog(page)).toBeVisible();
		banner("version=1");
	});
});

test.describe("additional information and further links", () => {
	test.beforeAll(() => {
		sql(`UPDATE tl_page SET title='${stored6or5("Kontakt & Hilfe <b>", "Kontakt &amp; Hilfe &#60;b&#62;")}' WHERE id=4`);
		banner("infoText='<p>Mehr zu <strong>Cookies</strong> {{date::Y}}.</p>', infoPages='a:2:{i:0;s:1:\"4\";i:1;s:1:\"5\";}'");
	});
	test.afterAll(() => {
		sql("UPDATE tl_page SET title='Kontakt' WHERE id=4");
		reset();
	});

	test("the text shows in the settings, the links in their order, labels escaped", async ({ page }) => {
		await page.goto("/");
		const info = page.locator(".juhe-consent__info");
		await expect(info).toBeHidden();
		await page.evaluate(() => (window as any).juheConsent.open());
		await expect(info).toBeVisible();
		await expect(info).toHaveText(`Mehr zu Cookies ${new Date().getFullYear()}.`);
		const links = page.locator(".juhe-consent__links a");
		await expect(links).toHaveText(["Datenschutzerklärung", "Kontakt & Hilfe <b>", "Login"]);
		await expect(links.nth(1)).toHaveAttribute("href", /\/kontakt$/);
		await expect(links.nth(2)).toHaveAttribute("href", /\/login$/);
		await expect(page.locator(".juhe-consent__links b")).toHaveCount(0);
		for (const href of await links.evaluateAll((list) => list.map((a) => (a as HTMLAnchorElement).href))) {
			expect((await page.request.get(href)).status(), href).toBe(200);
		}
		await axe(page, "#juhe-consent");
		// The card shows the links too
		await page.keyboard.press("Escape");
		await expect(links).toHaveCount(3);
	});

	test("an unpublished page drops out of the links", async ({ page }) => {
		sql("UPDATE tl_page SET published=0 WHERE id=5");
		await page.goto("/");
		await expect(page.locator(".juhe-consent__links a")).toHaveText(["Datenschutzerklärung", "Kontakt & Hilfe <b>"]);
		sql("UPDATE tl_page SET published=1 WHERE id=5");
	});
});

const cardPositions = ["bottom-left", "bottom-right", "bottom-center", "center", "top-left", "top-right", "top-center"];

test.describe("position", () => {
	test.afterAll(reset);

	for (const position of cardPositions) {
		test(`card ${position}: in view, not modal, no trap, closes`, async ({ page }) => {
			banner(`layout='card', position='${position}'`);
			for (const [width, height] of [
				[320, 640],
				[1280, 800],
				[2560, 1080],
			]) {
				await page.setViewportSize({ width, height });
				await page.goto("/");
				await expect(dialog(page)).toBeVisible();
				await settled(page);
				const box = (await dialog(page).boundingBox())!;
				const where = `${position} at ${width}`;
				expect(box.x, where).toBeGreaterThanOrEqual(0);
				expect(box.y, where).toBeGreaterThanOrEqual(0);
				expect(box.x + box.width, where).toBeLessThanOrEqual(width + 0.5);
				expect(box.y + box.height, where).toBeLessThanOrEqual(height + 0.5);
				// Part of the page stays uncovered and the page can still scroll under it
				expect(box.width * box.height, where).toBeLessThan(width * height * 0.9);
				if (width > 600) {
					if (position.endsWith("left")) expect(box.x, where).toBeLessThan(width / 4);
					if (position.endsWith("right")) expect(box.x + box.width, where).toBeGreaterThan((width * 3) / 4);
					if (position.endsWith("center")) expect(Math.abs(box.x + box.width / 2 - width / 2), where).toBeLessThan(2);
					if (position.startsWith("top")) expect(box.y, where).toBeLessThan(height / 4);
					if (position.startsWith("bottom")) expect(box.y + box.height, where).toBeGreaterThan((height * 3) / 4);
					if (position === "center") expect(Math.abs(box.y + box.height / 2 - height / 2), where).toBeLessThan(2);
				}
				expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), where).toBe(true);
				expect(await isModal(page), where).toBe(false);
				await page.keyboard.press("Tab");
				await tabOut(page);
			}
			await axe(page, "#juhe-consent");
			// The settings stay a centred dialog whatever the position
			await page.setViewportSize({ width: 1280, height: 800 });
			await page.evaluate(() => (window as any).juheConsent.open());
			await settled(page);
			const box = (await dialog(page).boundingBox())!;
			expect(Math.abs(box.x + box.width / 2 - 640)).toBeLessThan(2);
			await page.locator("[data-juhe-consent-action='essential']").click();
			await expect(dialog(page)).toBeHidden();
		});
	}

	for (const barPosition of ["bottom", "top"]) {
		test(`bar ${barPosition}: full width at the edge, no overflow, blocking releases`, async ({ page }) => {
			banner(`layout='bar', barPosition='${barPosition}', blocking=1`);
			for (const [width, height] of [
				[320, 640],
				[1280, 800],
			]) {
				await page.setViewportSize({ width, height });
				await page.goto("/");
				await settled(page);
				const box = (await dialog(page).boundingBox())!;
				expect(box.width).toBeCloseTo(width, 0);
				if (barPosition === "top") expect(box.y).toBeLessThan(1);
				else expect(box.y + box.height).toBeGreaterThan(height - 1);
				expect(await dialog(page).evaluate((d) => d.scrollWidth <= d.clientWidth)).toBe(true);
				expect(await isModal(page)).toBe(true);
				await page.keyboard.press("Escape");
				expect(await isModal(page)).toBe(false);
				await tabOut(page);
			}
			await axe(page, "#juhe-consent");
		});
	}

	test("real 200 percent zoom and a 32 px default font: every position stays in view and Tab leads out", async ({ browserName }) => {
		test.skip(browserName !== "chromium", "Chrome zoom and font preferences");
		const check = async (page: Page, label: string): Promise<void> => {
			await page.route("**/*", (route) => (new URL(route.request().url()).hostname === "127.0.0.1" ? route.continue() : route.fulfill({ status: 200, body: "" })));
			for (const position of [...cardPositions, "bar-top", "bar-bottom"]) {
				banner(`blocking=0, ${position.startsWith("bar-") ? `layout='bar', barPosition='${position.slice(4)}'` : `layout='card', position='${position}'`}`);
				await page.goto("http://127.0.0.1:8199/");
				await expect(page.locator("#juhe-consent")).toBeVisible();
				await settled(page);
				const where = `${label} ${position}`;
				const [vw, vh] = await page.evaluate(() => [window.innerWidth, window.innerHeight]);
				const box = (await page.locator("#juhe-consent").boundingBox())!;
				expect(box.x >= 0 && box.y >= -0.5 && box.x + box.width <= vw + 0.5 && box.y + box.height <= vh + 0.5, where).toBe(true);
				expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), where).toBe(true);
				expect(await page.locator("#juhe-consent").evaluate((d) => d.scrollWidth <= d.clientWidth), where).toBe(true);
				await page.keyboard.press("Tab");
				await tabOut(page);
			}
		};
		const zoom = await launchNativeZoom({ percent: 200, cssWidth: 640, cssHeight: 400, host: "127.0.0.1" });
		try {
			await zoom.page.goto("http://127.0.0.1:8199/");
			expect(await zoom.page.evaluate(() => Math.round(window.devicePixelRatio * 100))).toBeGreaterThanOrEqual(200);
			await check(zoom.page, "zoom 200");
		} finally {
			await zoom.close();
		}
		const font = await launchDefaultFont({ size: 32, width: 320, height: 640 });
		try {
			await font.page.goto("http://127.0.0.1:8199/");
			expect(await readRootFontSize(font.page)).toBe(32);
			await check(font.page, "font 32 at 320");
		} finally {
			await font.close();
		}
		reset();
	});

	test("forced colors and reduced motion in a top position", async ({ page }) => {
		banner("layout='card', position='top-right'");
		await page.emulateMedia({ forcedColors: "active", reducedMotion: "reduce" });
		await page.goto("/");
		expect(await dialog(page).evaluate((e) => getComputedStyle(e).animationName)).toBe("none");
		expect(await dialog(page).evaluate((e) => getComputedStyle(e).borderTopStyle)).toBe("solid");
	});
});

test.describe("choice lifetime", () => {
	test.afterAll(reset);

	const visitWithChoiceAged = async (page: Page, seconds: number): Promise<boolean> => {
		const now = new Date("2026-06-01T12:00:00Z");
		await page.clock.setFixedTime(now);
		await page.goto("/");
		await page.evaluate(([t]) => localStorage.setItem("juhe_consent_1", JSON.stringify({ v: 1, g: ["notwendig", "marketing"], s: [], id: "0f8e7d6c-5b4a-4321-8abc-0123456789ab", t })), [now.getTime() / 1000 - seconds]);
		await page.reload();
		return page.evaluate(() => (window as any).juheConsent.pending());
	};

	test("a choice counts for the configured days, to the second", async ({ page }) => {
		banner("lifetime=1");
		expect(await visitWithChoiceAged(page, 86400)).toBe(false);
		expect(await visitWithChoiceAged(page, 86401)).toBe(true);
		banner("lifetime=30");
		expect(await visitWithChoiceAged(page, 29 * 86400)).toBe(false);
		expect(await visitWithChoiceAged(page, 31 * 86400)).toBe(true);
		// Default: 365 days
		banner("lifetime=365");
		expect(await visitWithChoiceAged(page, 364 * 86400)).toBe(false);
		expect(await visitWithChoiceAged(page, 366 * 86400)).toBe(true);
	});

	test("a version bump asks again within the lifetime, malformed times ask", async ({ page }) => {
		banner("lifetime=395");
		expect(await visitWithChoiceAged(page, 10)).toBe(false);
		banner("version=2");
		await page.reload();
		expect(await page.evaluate(() => (window as any).juheConsent.pending())).toBe(true);
		banner("version=1");
		for (const t of ['"x"', "-1", "1e20", "null"]) {
			await page.evaluate((raw) => localStorage.setItem("juhe_consent_1", `{"v":1,"g":["marketing"],"s":[],"id":"a","t":${raw}}`), t);
			await page.reload();
			expect(await page.evaluate(() => (window as any).juheConsent.pending()), t).toBe(true);
		}
	});
});

test.describe("privacy signals", () => {
	test.afterAll(reset);

	const withSignal = (page: Page, gpc: boolean, dnt: string | null) =>
		page.addInitScript(([g, d]) => {
			Object.defineProperty(Navigator.prototype, "globalPrivacyControl", { get: () => g, configurable: true });
			Object.defineProperty(Navigator.prototype, "doNotTrack", { get: () => d, configurable: true });
		}, [gpc, dnt] as const);

	test("off by default: a signal changes nothing", async ({ page }) => {
		await withSignal(page, true, "1");
		await page.goto("/");
		await expect(dialog(page)).toBeVisible();
	});

	for (const [name, gpc, dnt] of [
		["Global Privacy Control", true, null],
		["Do Not Track", false, "1"],
	] as const) {
		test(`${name}: no banner, only essential, nothing stored, a saved choice wins`, async ({ page }) => {
			banner("privacySignals=1");
			await withSignal(page, gpc, dnt);
			await page.goto("/");
			await expect(dialog(page)).toBeHidden();
			expect(await page.evaluate(() => (window as any).juheConsent.has("marketing"))).toBe(false);
			expect(await page.evaluate(() => (window as any).juheCheckCode)).toBeUndefined();
			expect(await stored(page)).toBeNull();
			expect(providerRequests).toEqual([]);
			// The visitor opens the settings and allows everything: that wins, also on the next page
			await page.evaluate(() => (window as any).juheConsent.open());
			await page.locator("[data-juhe-consent-action='all']").click();
			await expect.poll(() => page.evaluate(() => (window as any).juheCheckCode)).toBe(1);
			await page.reload();
			expect(await page.evaluate(() => (window as any).juheConsent.has("marketing"))).toBe(true);
			await expect(dialog(page)).toBeHidden();
		});
	}

	test("no signal or an explicit 0: the banner asks", async ({ page }) => {
		banner("privacySignals=1");
		await withSignal(page, false, "0");
		await page.goto("/");
		await expect(dialog(page)).toBeVisible();
	});
});

test.describe("logged-in users", () => {
	test.afterAll(reset);

	const loginMember = async (page: Page): Promise<void> => {
		await page.goto("/login");
		const essential = page.locator("[data-juhe-consent-action='essential']");
		if (await essential.isVisible()) await essential.click();
		await page.locator("input[name='username']").fill("member");
		await page.locator("input[name='password']").fill("member-pass-123");
		await page.locator("form button[type='submit'], form input[type='submit']").first().click();
		await page.waitForLoadState("load");
	};

	test("a member gets no optional services, a private response, and the cache keeps the public page", async ({ page, browser }) => {
		banner("noOptionalForUsers=1");
		// Anonymous first: the public page goes into the cache
		await page.goto("/");
		await expect(dialog(page)).toBeVisible();
		await page.locator("[data-juhe-consent-action='all']").click();
		expect(await cacheHeader(page, "/")).toMatch(/fresh/);
		// The login page itself is still anonymous and runs the stored choice
		await loginMember(page);
		providerRequests.length = 0;
		const response = await page.goto("/");
		expect(response?.headers()["cache-control"] ?? "").toMatch(/private/);
		expect(response?.headers()["contao-cache"] ?? "").not.toMatch(/fresh/);
		await expect(dialog(page)).toBeHidden();
		expect(await page.evaluate(() => (window as any).juheConsent.has("marketing"))).toBe(false);
		expect(await page.evaluate(() => (window as any).juheConsent.pending())).toBe(false);
		expect(await page.evaluate(() => (window as any).juheCheckCode)).toBeUndefined();
		await expect(page.locator(".juhe-consent-placeholder")).toHaveCount(2);
		expect(providerRequests).toEqual([]);
		// The earlier choice is kept for later, not overwritten
		expect(JSON.parse((await stored(page)) ?? "null")?.g).toContain("marketing");
		// A fresh anonymous visitor still gets the public, cached page with the banner
		const anon = await browser.newContext();
		const other = await anon.newPage();
		const pub = await other.goto("/");
		expect(await other.content()).not.toContain('"restricted":true');
		expect(pub?.headers()["cache-control"] ?? "").not.toMatch(/private/);
		await expect(other.locator("#juhe-consent")).toBeVisible();
		await anon.close();
	});

	test("with always load from cache, a member's page never reaches the shared cache", async ({ page, browser }) => {
		banner("noOptionalForUsers=1");
		sql("UPDATE tl_page SET alwaysLoadFromCache=1 WHERE id=1");
		try {
			await loginMember(page);
			clearCache();
			// The member comes first: the restricted page is private and not stored
			const member = await page.goto("/");
			expect(await page.content()).toContain('"restricted":true');
			expect(member?.headers()["cache-control"] ?? "").toMatch(/private/);
			const anon = await browser.newContext();
			const other = await anon.newPage();
			for (let i = 0; i < 2; i++) {
				await other.goto("/");
				expect(await other.content(), `anonymous ${i}`).toContain('"restricted":false');
				await expect(other.locator("#juhe-consent"), `anonymous ${i}`).toBeVisible();
			}
			expect(await cacheHeader(other, "/")).toMatch(/fresh/);
			await anon.close();
			// Documented limit: now the member can get the public page from the cache
			await page.goto("/");
		} finally {
			sql("UPDATE tl_page SET alwaysLoadFromCache=0 WHERE id=1");
		}
	});

	test("off by default: a member is asked like everybody", async ({ page }) => {
		banner("noOptionalForUsers=0");
		await loginMember(page);
		await page.evaluate(() => localStorage.clear());
		await page.goto("/");
		await expect(dialog(page)).toBeVisible();
	});

	test("a backend user in the front end preview gets no optional services", async ({ page, browserName }) => {
		test.skip(browserName === "webkit", "Secure session cookie over http");
		banner("noOptionalForUsers=1");
		await page.goto("/contao/login");
		await page.locator("input[name='username']").fill("check");
		await page.locator("input[name='password']").fill("check-pass-123");
		await page.locator("button[type='submit']").first().click();
		await page.waitForLoadState("load");
		const response = await page.goto("/preview.php/");
		expect(response?.status()).toBeLessThan(400);
		await expect(dialog(page)).toBeHidden();
		expect(await page.evaluate(() => (window as any).juheConsent.has("marketing"))).toBe(false);
		expect(response?.headers()["cache-control"] ?? "").toMatch(/private/);
		expect(providerRequests).toEqual([]);
		// Outside the preview the back end login does not count
		await page.goto("/");
		await expect(dialog(page)).toBeVisible();
	});
});

test("backend: the new banner fields show and save", async ({ page, browserName }) => {
	test.skip(browserName === "webkit", "Secure session cookie over http");
	await page.goto("/contao/login");
	await page.locator("input[name='username']").fill("check");
	await page.locator("input[name='password']").fill("check-pass-123");
	await page.locator("button[type='submit']").first().click();
	await page.waitForLoadState("load");
	await page.goto("/contao?do=juhe_consent&act=edit&id=1");
	for (const name of ["infoText", "privacySignals", "noOptionalForUsers", "position", "lifetime"]) {
		await expect(page.locator(`[name='${name}']`).first(), name).toHaveCount(1);
	}
	await expect(page.locator("[name='barPosition']")).toHaveCount(0);
	await expect(page.locator("#ctrl_excludePages, [id^='ctrl_excludePages']").first()).toBeAttached();
	await expect(page.locator("#ctrl_infoPages, [id^='ctrl_infoPages']").first()).toBeAttached();
	// The bar shows its own position field
	await page.locator("select[name='layout']").selectOption("bar");
	await page.waitForLoadState("load");
	await expect(page.locator("[name='barPosition']")).toHaveCount(1);
	await expect(page.locator("select[name='position']")).toHaveCount(0);
	// The lifetime is limited to 13 months
	await page.locator("input[name='lifetime']").fill("400");
	await page.locator("button[name='save'], #save").first().click();
	await page.waitForLoadState("load");
	await expect(page.locator(".tl_error, p.tl_error").first()).toBeVisible();
	expect(await page.content()).not.toMatch(/An exception occurred|Fatal error|Unknown column/);
	reset();
});
