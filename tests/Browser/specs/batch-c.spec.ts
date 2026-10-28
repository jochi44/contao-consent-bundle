import AxeBuilder from "@axe-core/playwright";
import { execFileSync } from "node:child_process";
import { expect, test, type Page } from "@playwright/test";

/* eslint-disable @typescript-eslint/no-explicit-any */

// Batch C: service switches, the integration API, withdrawal cleanup, script
// placement and order, code without consent.

const providerRequests: string[] = [];

test.beforeEach(async ({ context }) => {
	providerRequests.length = 0;
	await context.route("**/*", (route) => {
		const url = new URL(route.request().url());
		if (url.hostname === "127.0.0.1") return route.continue();
		providerRequests.push(url.href);
		return route.fulfill({ status: 200, body: "", contentType: route.request().resourceType() === "script" ? "application/javascript" : "text/html" });
	});
});

const clearCache = (): void => {
	execFileSync("docker", ["exec", "juhe-consent-browser-php", "rm", "-rf", "/var/www/html/var/cache/prod/http_cache"]);
};
const db = (query: string): string =>
	execFileSync("docker", ["exec", "juhe-consent-browser-db", "mariadb", "-uroot", `-p${process.env.CONSENT_DB_PW}`, "-N", "-B", "contao", "-e", query]).toString().trim();
const sql = (query: string): void => {
	db(query);
	clearCache();
};
const reset = (): void => sql("UPDATE tl_juhe_consent_banner SET version=1, layout='card', blocking=0, askOnFirstVisit=1 WHERE id=1");

// A test that navigates right after a save can be faster than the browser hands the
// localStorage write to a new page process (seen in Chromium and Firefox). People are
// not that fast; the tests leave the page a moment before they navigate themselves.
const pause = (page: Page) => page.waitForTimeout(400);
const goto = async (page: Page, url: string) => {
	await pause(page);
	return page.goto(url);
};
const reload = async (page: Page) => {
	await pause(page);
	return page.reload();
};

const dialog = (page: Page) => page.locator("#juhe-consent");
const stored = async (page: Page): Promise<any> => JSON.parse((await page.evaluate(() => localStorage.getItem("juhe_consent_1"))) ?? "null");
const has = (page: Page, name: string) => page.evaluate((n) => (window as any).juheConsent.has(n), name);
const openSettings = async (page: Page): Promise<void> => {
	await page.evaluate(() => (window as any).juheConsent.open());
	await expect(dialog(page)).toBeVisible();
	await page.evaluate(() => document.querySelectorAll<HTMLDetailsElement>("#juhe-consent details").forEach((d) => (d.open = true)));
};
const settled = async (page: Page): Promise<void> => {
	await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
	await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== "running"));
};
const axe = async (page: Page, include: string): Promise<void> => {
	await settled(page);
	const result = await new AxeBuilder({ page }).include(include).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
	expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`), include).toEqual([]);
};
const groupSwitch = (page: Page, alias: string) => page.locator(`#juhe-consent input[data-juhe-consent-checkbox][value='${alias}']`);
const serviceSwitch = (page: Page, key: string) => page.locator(`#juhe-consent input[data-juhe-consent-service-checkbox][value='${key}']`);

// The log and cleanup routes allow 120 posts per 10 minutes and IP. Three browsers in a
// row exceed that, so this file starts and ends with an empty limiter.
const clearLimiter = (): void => {
	execFileSync("docker", ["exec", "-u", "www-data", "juhe-consent-browser-php", "php", "/var/www/html/vendor/bin/contao-console", "cache:pool:clear", "cache.rate_limiter", "--no-interaction"]);
};

test.describe.configure({ mode: "serial" });
test.beforeAll(() => {
	clearLimiter();
	reset();
});
test.afterAll(() => {
	clearLimiter();
	reset();
});

test.describe("service switches", () => {
	test.afterEach(reset);

	test("one service on, the other off in the same group: mixed group, stored as a single service", async ({ page }) => {
		await goto(page, "/");
		await openSettings(page);
		// Essential services have no switch, optional ones do
		await expect(serviceSwitch(page, "s1")).toHaveCount(0);
		await expect(serviceSwitch(page, "s2")).toHaveAccessibleName("YouTube");
		await expect(serviceSwitch(page, "s3")).toHaveAccessibleName("Tag Manager");
		await expect(serviceSwitch(page, "s2")).not.toBeChecked();
		// The group switch sets both
		await groupSwitch(page, "marketing").check();
		await expect(serviceSwitch(page, "s2")).toBeChecked();
		await expect(serviceSwitch(page, "s3")).toBeChecked();
		await serviceSwitch(page, "s3").uncheck();
		expect(await groupSwitch(page, "marketing").evaluate((e: HTMLInputElement) => [e.checked, e.indeterminate])).toEqual([false, true]);
		// Screen readers get "mixed"
		expect(await groupSwitch(page, "marketing").ariaSnapshot()).toMatch(/checkbox "Marketing" \[checked=mixed\]/);
		await page.locator("[data-juhe-consent-action='save']").click();
		await expect(dialog(page)).toBeHidden();
		const choice = await stored(page);
		expect(choice.g).toEqual(["notwendig"]);
		expect(choice.s).toEqual(["s2"]);
		expect([await has(page, "s2"), await has(page, "s3"), await has(page, "marketing")]).toEqual([true, false, false]);
		await expect(page.locator("iframe[src*='youtube']")).toHaveCount(2);
		expect(await page.evaluate(() => (window as any).juheCheckCode)).toBeUndefined();
		await expect(page.locator("script[data-juhe-consent-src*='googletagmanager']")).toHaveAttribute("type", "text/plain");
		expect(providerRequests.filter((u) => u.includes("googletagmanager"))).toEqual([]);
		// The settings show it again after a reload
		await reload(page);
		await openSettings(page);
		await expect(serviceSwitch(page, "s2")).toBeChecked();
		await expect(serviceSwitch(page, "s3")).not.toBeChecked();
		expect(await groupSwitch(page, "marketing").evaluate((e: HTMLInputElement) => e.indeterminate)).toBe(true);
		// Both on again: the group is stored, the single service drops out
		await serviceSwitch(page, "s3").check();
		await expect(groupSwitch(page, "marketing")).toBeChecked();
		expect(await groupSwitch(page, "marketing").evaluate((e: HTMLInputElement) => e.indeterminate)).toBe(false);
		await page.locator("[data-juhe-consent-action='save']").click();
		expect((await stored(page)).g).toEqual(["notwendig", "marketing"]);
		expect((await stored(page)).s).toEqual([]);
		await expect.poll(() => page.evaluate(() => (window as any).juheCheckCode)).toBe(1);
	});

	test("keyboard: Space toggles a service switch, a mixed group turns all on; axe clean; 44 px; 320 px", async ({ page }) => {
		await page.setViewportSize({ width: 320, height: 640 });
		await goto(page, "/");
		await openSettings(page);
		await serviceSwitch(page, "s2").focus();
		await page.keyboard.press("Space");
		await expect(serviceSwitch(page, "s2")).toBeChecked();
		expect(await groupSwitch(page, "marketing").evaluate((e: HTMLInputElement) => e.indeterminate)).toBe(true);
		await axe(page, "#juhe-consent");
		await groupSwitch(page, "marketing").focus();
		await page.keyboard.press("Space");
		await expect(serviceSwitch(page, "s3")).toBeChecked();
		await expect(groupSwitch(page, "marketing")).toBeChecked();
		const small = await page.evaluate(() =>
			[...document.querySelectorAll<HTMLElement>("#juhe-consent .juhe-consent-service__switch")]
				.filter((e) => e.getBoundingClientRect().height < 43.5 || e.getBoundingClientRect().width < 43.5)
				.map((e) => e.outerHTML.slice(0, 80)),
		);
		expect(small).toEqual([]);
		expect(await dialog(page).evaluate((d) => d.scrollWidth <= d.clientWidth)).toBe(true);
		// Focus is visible on the service switch
		await serviceSwitch(page, "s3").focus();
		await page.keyboard.press("Shift+Tab");
		await page.keyboard.press("Tab");
		expect(await page.evaluate(() => getComputedStyle(document.activeElement!, null).outlineStyle)).not.toBe("none");
	});

	test("forced colors: the service switch is a native checkbox", async ({ page }) => {
		await page.emulateMedia({ forcedColors: "active", reducedMotion: "reduce" });
		await goto(page, "/");
		await openSettings(page);
		expect(await serviceSwitch(page, "s2").evaluate((e) => getComputedStyle(e).appearance)).toBe("auto");
	});

	test("revoking one running service reloads, removes its cookies, and keeps the other running", async ({ page, context }) => {
		await goto(page, "/");
		await page.locator("[data-juhe-consent-action='all']").click();
		await expect.poll(() => page.evaluate(() => (window as any).juheCheckCode)).toBe(1);
		await page.evaluate(() => {
			document.cookie = "_ga=GA1.1; path=/";
			document.cookie = "_ga_TEST=GS1.1; path=/";
			document.cookie = "keep_me=1; path=/";
		});
		await openSettings(page);
		await serviceSwitch(page, "s3").uncheck();
		const reloaded = page.waitForEvent("load");
		await page.locator("[data-juhe-consent-action='save']").click();
		await reloaded;
		await page.waitForLoadState("load");
		// Firefox hands document.cookie writes to its cookie store asynchronously
		await expect.poll(async () => (await context.cookies()).map((c) => c.name).sort()).toEqual(["keep_me"]);
		expect(await page.evaluate(() => (window as any).juheCheckCode)).toBeUndefined();
		expect((await stored(page)).s).toEqual(["s2"]);
		await expect.poll(async () => {
			if (!(await has(page, "s2"))) await reload(page);
			return [await has(page, "s2"), await has(page, "s3")];
		}).toEqual([true, false]);
		await expect(page.locator("iframe[src*='youtube']")).toHaveCount(2);
		expect(await page.evaluate(() => (window as any).juheCheckCode)).toBeUndefined();
		await page.evaluate(() => (document.cookie = "keep_me=; path=/; max-age=0"));
	});
});

test.describe("integration API", () => {
	test.afterEach(reset);

	test("the queue runs before the services start, ready fires once, subscribe reports at once and on every change", async ({ page }) => {
		await page.addInitScript(() => {
			const w = window as any;
			w.calls = [];
			w.juheConsentQueue = w.juheConsentQueue || [];
			w.juheConsentQueue.push((api: any) => {
				w.calls.push(["queue", typeof api.state, w.juheCheckCode ?? null, w.juheConsent === api]);
				api.subscribe((state: any) => w.calls.push(["early", state]));
			});
			document.addEventListener("juhe-consent:ready", (event: any) => w.calls.push(["ready", event.detail, !!w.juheConsent, w.juheCheckCode ?? null]));
		});
		await goto(page, "/");
		const calls = await page.evaluate(() => (window as any).calls);
		expect(calls[0]).toEqual(["queue", "function", null, true]);
		expect(calls[1]).toEqual(["early", { decided: false, groups: ["notwendig"], services: ["s1"] }]);
		expect(calls[2]).toEqual(["ready", { decided: false, groups: ["notwendig"], services: ["s1"] }, true, null]);
		expect(calls).toHaveLength(3);
		// A late subscriber is called at once; a push after the start runs at once
		await page.evaluate(() => {
			const w = window as any;
			w.late = [];
			w.stop = w.juheConsent.subscribe((state: any) => w.late.push(state));
			w.juheConsentQueue.push(() => w.late.push("pushed"));
		});
		expect(await page.evaluate(() => (window as any).late)).toEqual([{ decided: false, groups: ["notwendig"], services: ["s1"] }, "pushed"]);
		await page.locator("[data-juhe-consent-action='all']").click();
		const all = { decided: true, groups: ["notwendig", "marketing"], services: ["s1", "s2", "s3"] };
		expect(await page.evaluate(() => (window as any).late.at(-1))).toEqual(all);
		expect((await page.evaluate(() => (window as any).calls)).at(-1)).toEqual(["early", all]);
		// state() is a copy
		expect(await page.evaluate(() => {
			const w = window as any;
			w.juheConsent.state().groups.push("x");
			return w.juheConsent.state();
		})).toEqual(all);
		// Unsubscribed: no more calls
		await page.evaluate(() => (window as any).stop());
		const count = await page.evaluate(() => (window as any).late.length);
		await openSettings(page);
		await serviceSwitch(page, "s2").uncheck();
		// The withdrawal of YouTube (running) reloads; the early subscriber hears it first
		await page.evaluate(() => {
			const w = window as any;
			w.juheConsent.subscribe((state: any) => sessionStorage.setItem("beforeReload", JSON.stringify(state)));
		});
		const reloaded = page.waitForEvent("load");
		await page.locator("[data-juhe-consent-action='save']").click();
		await reloaded;
		expect(JSON.parse((await page.evaluate(() => sessionStorage.getItem("beforeReload"))) ?? "null")).toEqual({ decided: true, groups: ["notwendig"], services: ["s1", "s3"] });
		expect(count).toBeGreaterThan(0);
		// After the reload the queue starts with the new state
		expect((await page.evaluate(() => (window as any).calls))[1]).toEqual(["early", { decided: true, groups: ["notwendig"], services: ["s1", "s3"] }]);
	});

	test("unknown names report false, a throwing subscriber does not stop the others", async ({ page }) => {
		const errors: string[] = [];
		page.on("pageerror", (e) => errors.push(e.message));
		await goto(page, "/");
		expect(await page.evaluate(() => {
			const c = (window as any).juheConsent;
			return [c.has("s999"), c.has("nope"), c.has(""), c.has(undefined), c.has("s1"), c.has("notwendig")];
		})).toEqual([false, false, false, false, true, true]);
		await page.evaluate(() => {
			const w = window as any;
			w.ok = 0;
			w.juheConsent.subscribe(() => {
				throw new Error("subscriber failed");
			});
			w.juheConsent.subscribe(() => w.ok++);
		});
		await page.locator("[data-juhe-consent-action='essential']").click();
		expect(await page.evaluate(() => (window as any).ok)).toBe(2);
		await expect.poll(() => errors).toContain("subscriber failed");
		expect(await page.evaluate(() => {
			try {
				(window as any).juheConsent.subscribe("x");
				return "no error";
			} catch (e) {
				return (e as Error).name;
			}
		})).toBe("TypeError");
	});

	test("two roots: each website reports its own banner and choice", async ({ page }) => {
		await goto(page, "/");
		await page.locator("[data-juhe-consent-action='all']").click();
		await goto(page, "/en/home");
		await expect(dialog(page)).toBeVisible();
		await expect(dialog(page)).toHaveAccessibleName("Cookies EN");
		expect(await page.evaluate(() => (window as any).juheConsent.state())).toEqual({ decided: false, groups: ["necessary"], services: [] });
		expect(await has(page, "marketing")).toBe(false);
		expect(await has(page, "s2")).toBe(false);
		await page.locator("[data-juhe-consent-action='all']").click();
		expect(await page.evaluate(() => (window as any).juheConsent.state())).toEqual({ decided: true, groups: ["necessary", "statistics"], services: ["s10"] });
		await expect.poll(() => page.evaluate(() => (window as any).juheEnCode)).toBe(1);
		expect(await page.evaluate(() => [!!localStorage.getItem("juhe_consent_1"), !!localStorage.getItem("juhe_consent_3")])).toEqual([true, true]);
		// Withdrawing on one website leaves the other alone
		await goto(page, "/");
		await page.locator("#juhe-consent").evaluate(() => (window as any).juheConsent.open());
		// The withdrawal reloads (after the cookie cleanup answered); wait for it before leaving
		const reloaded = page.waitForEvent("load");
		await page.locator("[data-juhe-consent-action='essential']").click();
		await reloaded;
		// Under load a new page process can briefly miss a fresh localStorage write; the stored
		// choice is what counts, so the page is loaded again until it reads it
		await expect.poll(async () => {
			await goto(page, "/en/home");
			return has(page, "statistics");
		}).toBe(true);
	});
});

test.describe("withdrawal cleanup", () => {
	test.afterEach(reset);

	test("root and nested-path cookies, prefixes and HttpOnly cookies go; unrelated and essential ones stay", async ({ page, context }) => {
		await goto(page, "/kontakt");
		await page.locator("[data-juhe-consent-action='all']").click();
		await expect.poll(() => page.evaluate(() => (window as any).juheCheckCode)).toBe(1);
		await page.evaluate(() => {
			document.cookie = "_ga=GA1; path=/";
			document.cookie = "_ga_ROOT=GS1; path=/";
			document.cookie = "_ga_NESTED=GS2; path=/kontakt";
			document.cookie = "keep_me=1; path=/";
			document.cookie = "keep_nested=1; path=/kontakt";
		});
		// HttpOnly cookies, as a server-side tag manager would set them
		await context.addCookies([
			{ name: "_ga_SERVER", value: "1", domain: "127.0.0.1", path: "/", httpOnly: true },
			{ name: "juhe_consent_1", value: "listed-as-essential", domain: "127.0.0.1", path: "/", httpOnly: true },
		]);
		const posts: string[] = [];
		page.on("request", (request) => {
			if (request.url().includes("/_juhe_consent/cookies")) posts.push(request.postData() ?? "");
		});
		await openSettings(page);
		await groupSwitch(page, "marketing").uncheck();
		const reloaded = page.waitForEvent("load");
		await page.locator("[data-juhe-consent-action='save']").click();
		await reloaded;
		await page.waitForLoadState("load");
		// The request names banner, services and page, never a cookie
		expect(posts.map((p) => JSON.parse(p))).toEqual([{ banner: 1, services: ["s2", "s3"], path: "/kontakt" }]);
		await expect.poll(async () => (await context.cookies()).map((c) => `${c.name}@${c.path}`).sort()).toEqual(["juhe_consent_1@/", "keep_me@/", "keep_nested@/kontakt"]);
		expect(await page.evaluate(() => (window as any).juheCheckCode)).toBeUndefined();
		await context.clearCookies();
	});

	test("no cleanup request when the withdrawn services list no cookies", async ({ page }) => {
		await goto(page, "/");
		await page.locator("[data-juhe-consent-action='all']").click();
		const posts: string[] = [];
		page.on("request", (request) => {
			if (request.url().includes("/_juhe_consent/cookies")) posts.push(request.method());
		});
		await openSettings(page);
		await serviceSwitch(page, "s2").uncheck();
		const reloaded = page.waitForEvent("load");
		await page.locator("[data-juhe-consent-action='save']").click();
		await reloaded;
		expect(posts).toEqual([]);
	});

	test("the route expires nothing a visitor names", async ({ page }) => {
		// (A stale PHPSESSID is expired by the session handling of every route, so another name is used.)
		const response = await page.request.post("/_juhe_consent/cookies", { data: { banner: 1, services: ["visitor_pick"] }, headers: { Cookie: "visitor_pick=1" } });
		expect(response.status()).toBe(400);
		const other = await page.request.post("/_juhe_consent/cookies", { data: { banner: 1, cookies: ["visitor_pick"] }, headers: { Cookie: "visitor_pick=1; _ga=2" } });
		expect(other.status()).toBe(204);
		const names = other.headersArray().filter((h) => h.name.toLowerCase() === "set-cookie").map((h) => h.value.split("=")[0]);
		expect([...new Set(names)]).toEqual(["_ga"]);
	});
});

test.describe("script placement and order", () => {
	const lib = "https://lib.example.org";
	const push = (entry: string): string => `<script>(window.order = window.order || []).push(${entry})</script>`;
	test.beforeAll(() => {
		sql(`DELETE FROM tl_juhe_consent_service WHERE id BETWEEN 20 AND 29`);
		sql(`INSERT INTO tl_juhe_consent_service (id, pid, sorting, tstamp, title, provider, cookies, hosts, code, codePosition, loadOrder, published) VALUES
			(20, 2, 1000, UNIX_TIMESTAMP(), 'Lib', 'X', '', '', '<script src="${lib}/lib.js" data-x="kept"></script>${push(`"init:" + (window.lib ? "lib" : "none")`)}', 'head', 5, 1),
			(21, 2, 1100, UNIX_TIMESTAMP(), 'First', 'X', '', '', '${push(`"first"`)}', 'body', 1, 1),
			(22, 2, 1200, UNIX_TIMESTAMP(), 'Async', 'X', '', '', '<script async src="${lib}/slow.js"></script>${push(`"after-async"`)}', 'body', 5, 1),
			(23, 2, 1300, UNIX_TIMESTAMP(), 'Broken', 'X', '', '', '<script src="${lib}/missing.js"></script>', 'body', 9, 1)`);
	});
	test.afterAll(() => sql(`DELETE FROM tl_juhe_consent_service WHERE id BETWEEN 20 AND 29`));

	test.beforeEach(async ({ context }) => {
		await context.route(`${lib}/**`, async (route) => {
			const path = new URL(route.request().url()).pathname;
			providerRequests.push(route.request().url());
			if (path === "/missing.js") return route.fulfill({ status: 404, body: "" });
			await new Promise((resolve) => setTimeout(resolve, path === "/slow.js" ? 1500 : 700));
			const body = path === "/lib.js" ? "window.lib = true; (window.order = window.order || []).push('lib');" : "(window.order = window.order || []).push('slow');";
			return route.fulfill({ status: 200, body, contentType: "application/javascript" });
		});
	});

	test("libraries load before their setup, in service order, once, with the nonce; nothing before consent", async ({ page }) => {
		await page.addInitScript(() => {
			const w = window as any;
			w.loadedEvents = [];
			document.addEventListener("juhe-consent:loaded", (event: any) => w.loadedEvents.push(event.detail.service));
		});
		await goto(page, "/");
		await page.waitForTimeout(300);
		expect(providerRequests).toEqual([]);
		expect(await page.evaluate(() => (window as any).order)).toBeUndefined();
		// Not allowed: whenLoaded stays pending
		const pending = () =>
			page.evaluate(() => Promise.race([(window as any).juheConsent.whenLoaded("s20").then(() => "loaded"), new Promise((resolve) => setTimeout(() => resolve("pending"), 300))]));
		expect(await pending()).toBe("pending");
		expect(await page.evaluate(() => (window as any).loadedEvents)).toEqual(["s1"]);
		await page.evaluate(() => {
			const w = window as any;
			w.results = {};
			for (const key of ["s20", "s21", "s22", "s23"]) w.juheConsent.whenLoaded(key).then((detail: any) => (w.results[key] = { ...detail, at: [...(w.order || [])] }));
			w.juheConsent.whenLoaded("s999").then(() => (w.results.s999 = true));
		});
		await page.locator("[data-juhe-consent-action='all']").click();
		await expect.poll(() => page.evaluate(() => Object.keys((window as any).results).sort()), { timeout: 10_000 }).toEqual(["s20", "s21", "s22", "s23"]);
		const order = await page.evaluate(() => (window as any).order);
		expect(order).toEqual(["first", "lib", "init:lib", "after-async", "slow"]);
		const results = await page.evaluate(() => (window as any).results);
		expect(results.s21.at).toEqual(["first"]);
		expect(results.s20).toMatchObject({ service: "s20", failed: [] });
		expect(results.s20.at).toEqual(["first", "lib", "init:lib"]);
		// The async script did not hold the next one, but whenLoaded waits for it
		expect(results.s22.at).toContain("slow");
		expect(results.s23.failed).toEqual([`${lib}/missing.js`]);
		// Head placement, kept attributes, the page's nonce
		const script = await page.evaluate(() => {
			const el = document.querySelector<HTMLScriptElement>("script[src$='/lib.js']")!;
			const own = document.querySelector<HTMLScriptElement>("script[src*='juhe-consent.js']")!;
			return { head: el.parentElement === document.head, data: el.dataset.x, nonce: el.nonce !== "" && el.nonce === own.nonce };
		});
		expect(script).toEqual({ head: true, data: "kept", nonce: true });
		expect(await page.evaluate(() => (window as any).loadedEvents)).toEqual(expect.arrayContaining(["s1", "s2", "s3", "s20", "s21", "s22", "s23"]));
		// Saving again starts nothing twice
		await openSettings(page);
		await page.locator("[data-juhe-consent-action='save']").click();
		await page.waitForTimeout(1000);
		expect(await page.evaluate(() => (window as any).order)).toEqual(order);
		expect(await page.evaluate(() => document.querySelectorAll("script[src$='/lib.js']").length)).toBe(1);
		expect(await page.evaluate(() => (window as any).results.s999)).toBeUndefined();
		expect(await page.evaluate(() => (window as any).loadedEvents.filter((k: string) => k === "s20").length)).toBe(1);
	});

	test("a stored choice starts the services on load, in the same order", async ({ page }) => {
		await goto(page, "/");
		await page.locator("[data-juhe-consent-action='all']").click();
		await reload(page);
		await expect.poll(() => page.evaluate(() => (window as any).order?.length), { timeout: 10_000 }).toBe(5);
		expect(await page.evaluate(() => (window as any).order)).toEqual(["first", "lib", "init:lib", "after-async", "slow"]);
	});

	test("backend: position and loading order show and save", async ({ page, browserName }) => {
		test.skip(browserName === "webkit", "Secure session cookie over http");
		await goto(page, "/contao/login");
		await page.locator("input[name='username']").fill("check");
		await page.locator("input[name='password']").fill("check-pass-123");
		await page.locator("button[type='submit']").first().click();
		await page.waitForLoadState("load");
		await goto(page, "/contao?do=juhe_consent&table=tl_juhe_consent_service&act=edit&id=21");
		await expect(page.locator("select[name='codePosition']")).toHaveValue("body");
		expect(await page.locator("select[name='codePosition'] option").evaluateAll((o) => o.map((e) => (e as HTMLOptionElement).value))).toEqual(["body", "head"]);
		await expect(page.locator("input[name='loadOrder']")).toHaveValue("1");
		await page.locator("select[name='codePosition']").selectOption("head");
		await page.locator("input[name='loadOrder']").fill("3");
		await Promise.all([page.waitForResponse((r) => r.request().method() === "POST"), page.locator("button[name='save'], #save").first().click()]);
		await page.waitForLoadState("load");
		await expect.poll(() => db("SELECT CONCAT(codePosition, ' ', loadOrder) FROM tl_juhe_consent_service WHERE id=21")).toBe("head 3");
		expect(await page.content()).not.toMatch(/An exception occurred|Fatal error|Unknown column/);
		sql("UPDATE tl_juhe_consent_service SET codePosition='body', loadOrder=1 WHERE id=21");
	});
});

test.describe("code without consent", () => {
	const mark = (name: string, key: string): string => `<script>(window.${name} = window.${name} || []).push("${key}")</script>`;
	test.beforeAll(() => {
		sql(`DELETE FROM tl_juhe_consent_service WHERE id BETWEEN 20 AND 29`);
		sql(`INSERT INTO tl_juhe_consent_service (id, pid, sorting, tstamp, title, provider, cookies, hosts, code, codeDenied, published) VALUES
			(24, 2, 1400, UNIX_TIMESTAMP(), 'Fallback', 'X', '', '', '${mark("granted", "s24")}', '${mark("denied", "s24")}', 1),
			(25, 1, 1500, UNIX_TIMESTAMP(), 'Essential', 'X', '', '', '', '${mark("denied", "s25")}', 1),
			(26, 2, 1600, UNIX_TIMESTAMP(), 'Only fallback', 'X', '', '', '', '${mark("denied", "s26")}', 1)`);
	});
	test.afterAll(() => sql(`DELETE FROM tl_juhe_consent_service WHERE id BETWEEN 20 AND 29`));

	const denied = (page: Page) => page.evaluate(() => (window as any).denied ?? null);
	const granted = (page: Page) => page.evaluate(() => (window as any).granted ?? null);

	test("first visit runs it once; consent runs the real code and does not undo or repeat it; a stored consent skips it", async ({ page }) => {
		await goto(page, "/");
		await expect.poll(() => denied(page)).toEqual(["s24", "s26"]);
		expect(await granted(page)).toBeNull();
		// The code without consent contacts nobody, and nothing optional loads
		expect(providerRequests).toEqual([]);
		// Never for essential services: they have no template
		expect(await page.locator("template[data-juhe-consent-denied][data-juhe-consent-service='s25']").count()).toBe(0);
		await page.locator("[data-juhe-consent-action='all']").click();
		await expect.poll(() => granted(page)).toEqual(["s24"]);
		expect(await denied(page)).toEqual(["s24", "s26"]);
		await reload(page);
		await expect.poll(() => granted(page)).toEqual(["s24"]);
		await page.waitForTimeout(300);
		expect(await denied(page)).toBeNull();
	});

	test("a refusal keeps it at once, a withdrawal runs it after the reload", async ({ page }) => {
		await goto(page, "/");
		await page.locator("[data-juhe-consent-action='essential']").click();
		await page.waitForTimeout(300);
		expect(await denied(page)).toEqual(["s24", "s26"]);
		await page.evaluate(() => (window as any).juheConsent.open());
		await page.locator("[data-juhe-consent-action='all']").click();
		await expect.poll(() => granted(page)).toEqual(["s24"]);
		await reload(page);
		await expect.poll(() => granted(page)).toEqual(["s24"]);
		expect(await denied(page)).toBeNull();
		await openSettings(page);
		await groupSwitch(page, "marketing").uncheck();
		const reloaded = page.waitForEvent("load");
		await page.locator("[data-juhe-consent-action='save']").click();
		await reloaded;
		await expect.poll(() => denied(page)).toEqual(["s24", "s26"]);
		expect(await granted(page)).toBeNull();
	});

	test("withdrawing a service that did not run yet runs it at once, without a reload", async ({ page }) => {
		await goto(page, "/");
		await openSettings(page);
		await serviceSwitch(page, "s26").check();
		await page.locator("[data-juhe-consent-action='save']").click();
		await expect(dialog(page)).toBeHidden();
		expect((await stored(page)).s, "stored single service").toEqual(["s26"]);
		await reload(page);
		await expect.poll(() => denied(page), { message: "after the reload" }).toEqual(["s24"]);
		await page.evaluate(() => ((window as any).stayed = true));
		await openSettings(page);
		await expect(serviceSwitch(page, "s26"), "switch reflects the stored choice").toBeChecked();
		await serviceSwitch(page, "s26").uncheck();
		await page.locator("[data-juhe-consent-action='save']").click();
		await expect.poll(() => denied(page), { message: "after the withdrawal" }).toEqual(["s24", "s26"]);
		expect(await page.evaluate(() => (window as any).stayed), "no reload").toBe(true);
		// Once per page: allowing and withdrawing again does not repeat it
		await openSettings(page);
		await serviceSwitch(page, "s26").check();
		await page.locator("[data-juhe-consent-action='save']").click();
		await openSettings(page);
		await serviceSwitch(page, "s26").uncheck();
		await page.locator("[data-juhe-consent-action='save']").click();
		await page.waitForTimeout(300);
		expect(await denied(page)).toEqual(["s24", "s26"]);
	});

	test("backend: the field shows with its help text", async ({ page, browserName }) => {
		test.skip(browserName === "webkit", "Secure session cookie over http");
		await goto(page, "/contao/login");
		await page.locator("input[name='username']").fill("check");
		await page.locator("input[name='password']").fill("check-pass-123");
		await page.locator("button[type='submit']").first().click();
		await page.waitForLoadState("load");
		await goto(page, "/contao?do=juhe_consent&table=tl_juhe_consent_service&act=edit&id=24");
		await expect(page.locator("textarea[name='codeDenied']")).toHaveCount(1);
		await expect(page.locator("#ctrl_codeDenied").locator("xpath=ancestor::div[contains(@class,'widget')]").first()).toContainText("Anbieter nicht kontaktieren");
	});
});
