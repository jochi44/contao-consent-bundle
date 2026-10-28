import AxeBuilder from "@axe-core/playwright";
import { execFileSync } from "node:child_process";
import { expect, test, type Page } from "@playwright/test";

/* eslint-disable @typescript-eslint/no-explicit-any */

// Batch D: Twig-only output, template services and presets, per-service notices,
// the essential starter group, the settings module and insert tag, resource hints.

const providerRequests: string[] = [];

test.beforeEach(async ({ context }) => {
	providerRequests.length = 0;
	await context.route("**/*", (route) => {
		const url = new URL(route.request().url());
		if (url.hostname === "127.0.0.1") return route.continue();
		providerRequests.push(url.href);
		const type = route.request().resourceType();
		return route.fulfill({ status: 200, body: "", contentType: type === "script" ? "application/javascript" : type === "stylesheet" ? "text/css" : "text/html" });
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
const pause = (page: Page) => page.waitForTimeout(400);
const goto = async (page: Page, url: string) => {
	await pause(page);
	return page.goto(url);
};
const dialog = (page: Page) => page.locator("#juhe-consent");
const settled = async (page: Page): Promise<void> => {
	await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
	await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== "running"));
};
const axe = async (page: Page, include: string): Promise<void> => {
	await settled(page);
	const result = await new AxeBuilder({ page }).include(include).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
	expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`), include).toEqual([]);
};
const backendLogin = async (page: Page): Promise<void> => {
	await page.goto("/contao/login");
	await page.locator("input[name='username']").fill("check");
	await page.locator("input[name='password']").fill("check-pass-123");
	await page.locator("button[type='submit']").first().click();
	await page.waitForLoadState("load");
	await expect(page.locator("input[name='password']")).toHaveCount(0);
};
const noError = async (page: Page): Promise<void> => {
	expect(await page.content()).not.toMatch(/An exception occurred|Fatal error|Unknown column|Internal Server Error/);
};

const consoleCmd = (...args: string[]): string =>
	execFileSync("docker", ["exec", "-u", "www-data", "juhe-consent-browser-php", "php", "/var/www/html/vendor/bin/contao-console", ...args]).toString();
// A template in the website's own templates folder (as the agency would add it)
const siteTemplate = (name: string, body: string | null): void => {
	const file = `/var/www/html/templates/juhe_consent/${name}.html.twig`;
	if (body === null) execFileSync("docker", ["exec", "juhe-consent-browser-php", "rm", "-f", file]);
	else execFileSync("docker", ["exec", "-i", "juhe-consent-browser-php", "sh", "-c", `mkdir -p "$(dirname ${file})" && cat > ${file} && chown -R www-data:www-data /var/www/html/templates`], { input: body });
};
const refreshTemplates = (): void => {
	consoleCmd("cache:clear", "--no-warmup");
	clearCache();
};
const codeTemplate = (page: Page, key: string) => page.locator(`template[data-juhe-consent-code][data-juhe-consent-service='${key}']`);
const acceptAll = async (page: Page): Promise<void> => {
	await page.locator("[data-juhe-consent-action='all']").click();
	await expect(dialog(page)).toBeHidden();
};
const services = (rows: string): void =>
	sql(`REPLACE INTO tl_juhe_consent_service (id, pid, sorting, tstamp, title, provider, privacyUrl, description, cookies, hosts, code, published, serviceType, serviceTemplate) VALUES ${rows}`);
const dropTestRows = (): void => sql("DELETE FROM tl_juhe_consent_service WHERE id >= 20; DELETE FROM tl_juhe_consent_group WHERE id >= 20");

test.describe.configure({ mode: "serial" });

test.describe("twig only", () => {
	test("the banner list names its websites from the label template", async ({ page, browserName }) => {
		test.skip(browserName === "webkit", "Secure session cookie over http");
		await backendLogin(page);
		await page.goto("/contao?do=juhe_consent");
		await noError(page);
		await expect(page.locator(".tl_listing, #tl_listing").getByText("Check (de)")).toBeVisible();
	});
});

test.describe("template services", () => {
	test.beforeAll(() => {
		siteTemplate("service/check", "<script>window.juheTemplateRuns = (window.juheTemplateRuns || 0) + 1; window.juheTemplateTitle = '{{ service.title|e('js') }}';</script><p class=\"check-template\" data-key=\"{{ service.key }}\">{{ service.title }}</p>\n");
		refreshTemplates();
		sql("REPLACE INTO tl_juhe_consent_group (id, pid, sorting, tstamp, title, alias, essential, description, published) VALUES (20, 1, 512, UNIX_TIMESTAMP(), 'Statistik', 'statistik', 0, '', 1)");
		services(`(20, 20, 128, UNIX_TIMESTAMP(), 'Check <b>"x"</b>', '', '', '', '', '', '<script>window.juheFieldCode = 1;</script>', 1, 'template', 'juhe_consent/service/check'),
			(21, 20, 256, UNIX_TIMESTAMP(), 'Bad name', '', '', '', '', '', '', 1, 'template', 'juhe_consent/service/../placeholder')`);
	});
	test.afterAll(() => {
		dropTestRows();
		siteTemplate("service/check", null);
		refreshTemplates();
	});

	test("the template output stays inert until consent, then runs once; values are escaped", async ({ page }) => {
		await goto(page, "/");
		await expect(codeTemplate(page, "s20")).toHaveCount(1);
		const inner = await codeTemplate(page, "s20").evaluate((t: HTMLTemplateElement) => t.innerHTML);
		expect(inner).toContain("Check &lt;b&gt;\"x\"&lt;/b&gt;");
		expect(inner).not.toContain("juheFieldCode");
		expect(await page.evaluate(() => (window as any).juheTemplateRuns)).toBeUndefined();
		await expect(page.locator("p.check-template")).toHaveCount(0);
		// A stored name that is not a listed template renders nothing
		await expect(codeTemplate(page, "s21")).toHaveCount(0);
		await acceptAll(page);
		await expect.poll(() => page.evaluate(() => (window as any).juheTemplateRuns)).toBe(1);
		expect(await page.evaluate(() => (window as any).juheTemplateTitle)).toBe('Check <b>"x"</b>');
		await expect(page.locator("p.check-template")).toHaveText('Check <b>"x"</b>');
		await expect(page.locator("p.check-template b")).toHaveCount(0);
		// Saving again does not start it a second time
		await page.evaluate(() => (window as any).juheConsent.open());
		await page.locator("[data-juhe-consent-action='save']").click();
		await page.waitForTimeout(300);
		expect(await page.evaluate(() => (window as any).juheTemplateRuns)).toBe(1);
		expect(await page.evaluate(() => (window as any).juheFieldCode)).toBeUndefined();
	});

	test("backend: the template select lists only juhe_consent/service templates and refuses others", async ({ page, browserName }) => {
		test.skip(browserName === "webkit", "Secure session cookie over http");
		await backendLogin(page);
		await page.goto("/contao?do=juhe_consent&table=tl_juhe_consent_service&act=edit&id=20");
		await noError(page);
		await expect(page.locator("select[name='serviceType']")).toHaveValue("template");
		await expect(page.locator("textarea[name='code']")).toHaveCount(0);
		const options = await page.locator("select[name='serviceTemplate'] option").evaluateAll((o) => o.map((e) => (e as HTMLOptionElement).value));
		expect(options).toContain("juhe_consent/service/check");
		expect(options.filter((o) => o !== "").every((o) => o.startsWith("juhe_consent/service/"))).toBe(true);
		// A forged value is refused
		await page.locator("select[name='serviceTemplate']").evaluate((select: HTMLSelectElement) => {
			select.add(new Option("x", "juhe_consent/placeholder"));
			select.value = "juhe_consent/placeholder";
		});
		await Promise.all([page.waitForResponse((r) => r.request().method() === "POST"), page.locator("button[name='save'], #save").first().click()]);
		await page.waitForLoadState("load");
		expect(db("SELECT serviceTemplate FROM tl_juhe_consent_service WHERE id=20")).toBe("juhe_consent/service/check");
		// Switching to "Code" shows the code field again
		await page.goto("/contao?do=juhe_consent&table=tl_juhe_consent_service&act=edit&id=20");
		await page.locator("select[name='serviceType']").selectOption("code");
		await expect(page.locator("textarea[name='code']")).toHaveCount(1);
		await noError(page);
	});
});

test.describe("provider presets", () => {
	const presets = [
		{ id: 22, title: "GA4", template: "google_analytics", account: "G-AB12CD34", url: "", request: "googletagmanager.com/gtag/js?id=G-AB12CD34" },
		{ id: 23, title: "GTM", template: "google_tag_manager", account: "GTM-AB12CD", url: "", request: "googletagmanager.com/gtm.js?id=GTM-AB12CD" },
		{ id: 24, title: "Matomo", template: "matomo", account: "3", url: "https://stats.example.org/", request: "stats.example.org/matomo.js" },
		{ id: 25, title: "MTM", template: "matomo_tag_manager", account: "aBcD1234", url: "https://stats.example.org/", request: "stats.example.org/js/container_aBcD1234.js" },
		{ id: 26, title: "Meta", template: "meta_pixel", account: "123456789012345", url: "", request: "connect.facebook.net/en_US/fbevents.js" },
		{ id: 27, title: "etracker", template: "etracker", account: "aBc123", url: "", request: "code.etracker.com/code/e.js" },
	];
	const providers = /googletagmanager|google-analytics|stats\.example\.org|facebook|etracker/;

	test.beforeAll(() => {
		sql("REPLACE INTO tl_juhe_consent_group (id, pid, sorting, tstamp, title, alias, essential, description, published) VALUES (20, 1, 512, UNIX_TIMESTAMP(), 'Statistik', 'statistik', 0, '', 1)");
		sql(
			`REPLACE INTO tl_juhe_consent_service (id, pid, sorting, tstamp, title, provider, privacyUrl, description, cookies, hosts, code, published, serviceType, serviceTemplate, serviceId, serviceUrl) VALUES ${presets
				.map((p) => `(${p.id}, 20, ${p.id * 10}, UNIX_TIMESTAMP(), '${p.title}', '', '', '', '', '', '', 1, 'template', 'juhe_consent/service/${p.template}', '${p.account}', '${p.url}')`)
				.join(", ")}`,
		);
	});
	test.afterAll(dropTestRows);

	test("no preset contacts its provider before consent or after a refusal; each loads after consent", async ({ page }) => {
		await goto(page, "/");
		await page.waitForLoadState("networkidle");
		expect(providerRequests.filter((u) => providers.test(u))).toEqual([]);
		for (const p of presets) await expect(codeTemplate(page, `s${p.id}`)).toHaveCount(1);
		// Refused: still nothing
		await page.locator("[data-juhe-consent-action='essential']").click();
		await goto(page, "/");
		await page.waitForLoadState("networkidle");
		expect(providerRequests.filter((u) => providers.test(u))).toEqual([]);
		// Allowed: every preset loads its script once
		await page.evaluate(() => (window as any).juheConsent.open());
		await page.locator("#juhe-consent input[data-juhe-consent-checkbox][value='statistik']").check();
		await page.locator("[data-juhe-consent-action='save']").click();
		for (const p of presets) await expect.poll(() => providerRequests.filter((u) => u.includes(p.request)).length, p.title).toBe(1);
		expect(await page.evaluate(() => [typeof (window as any).gtag, typeof (window as any).fbq, Array.isArray((window as any)._paq), Array.isArray((window as any)._mtm)])).toEqual(["function", "function", true, true]);
		// Withdrawn: the page reloads and the next pages contact no provider again
		await page.evaluate(() => (window as any).juheConsent.open());
		await page.locator("#juhe-consent input[data-juhe-consent-checkbox][value='statistik']").uncheck();
		await Promise.all([page.waitForEvent("load"), page.locator("[data-juhe-consent-action='save']").click()]);
		providerRequests.length = 0;
		await goto(page, "/");
		await page.waitForLoadState("networkidle");
		expect(providerRequests.filter((u) => providers.test(u))).toEqual([]);
	});

	test("backend: a preset checks its identifier and fills in its hosts, provider and cookies", async ({ page, browserName }) => {
		test.skip(browserName === "webkit", "Secure session cookie over http");
		await backendLogin(page);
		await page.goto("/contao?do=juhe_consent&table=tl_juhe_consent_service&act=edit&id=22");
		const options = await page.locator("select[name='serviceTemplate'] option").evaluateAll((o) => o.map((e) => (e as HTMLOptionElement).textContent));
		expect(options.join("|")).toContain("Google Analytics 4");
		expect(options.join("|")).toContain("Matomo Tag Manager");
		await page.locator("input[name='serviceId']").fill("UA-1234-1");
		await Promise.all([page.waitForResponse((r) => r.request().method() === "POST"), page.locator("button[name='save'], #save").first().click()]);
		await page.waitForLoadState("load");
		await expect(page.locator(".tl_error", { hasText: "G-XXXXXXXXXX" })).toBeVisible();
		expect(db("SELECT serviceId FROM tl_juhe_consent_service WHERE id=22")).toBe("G-AB12CD34");
		await page.goto("/contao?do=juhe_consent&table=tl_juhe_consent_service&act=edit&id=22");
		await page.locator("input[name='serviceId']").fill("G-ZZ99YY88");
		await Promise.all([page.waitForResponse((r) => r.request().method() === "POST"), page.locator("button[name='save'], #save").first().click()]);
		await page.waitForLoadState("load");
		await noError(page);
		expect(db("SELECT CONCAT_WS('|', serviceId, REPLACE(hosts, '\\n', ','), provider, privacyUrl, REPLACE(cookies, '\\n', ',')) FROM tl_juhe_consent_service WHERE id=22")).toBe(
			"G-ZZ99YY88|googletagmanager.com,google-analytics.com,analytics.google.com|Google Ireland Limited|https://policies.google.com/privacy|_ga: 2 Jahre,_ga_*: 2 Jahre",
		);
		// Filled fields stay as the editor wrote them
		db("UPDATE tl_juhe_consent_service SET hosts='example.org' WHERE id=22");
		await page.goto("/contao?do=juhe_consent&table=tl_juhe_consent_service&act=edit&id=22");
		await Promise.all([page.waitForResponse((r) => r.request().method() === "POST"), page.locator("button[name='save'], #save").first().click()]);
		await page.waitForLoadState("load");
		expect(db("SELECT hosts FROM tl_juhe_consent_service WHERE id=22")).toBe("example.org");
		// Matomo needs the address of the installation
		await page.goto("/contao?do=juhe_consent&table=tl_juhe_consent_service&act=edit&id=24");
		await page.locator("input[name='serviceUrl']").fill("https://stats.example.org/?x=1");
		await Promise.all([page.waitForResponse((r) => r.request().method() === "POST"), page.locator("button[name='save'], #save").first().click()]);
		await page.waitForLoadState("load");
		await expect(page.locator(".tl_error", { hasText: "Matomo-Installation" })).toBeVisible();
		expect(db("SELECT serviceUrl FROM tl_juhe_consent_service WHERE id=24")).toBe("https://stats.example.org/");
	});
});

test.describe("service notices", () => {
	const placeholder = (page: Page, key: string) => page.locator(`.juhe-consent-placeholder[data-juhe-consent-service='${key}']`).first();

	test.beforeAll(() => {
		siteTemplate("placeholder/compact", "{% extends '@Contao/juhe_consent/placeholder.html.twig' %}\n{% block text %}<p class=\"juhe-consent-placeholder__text check-compact\" id=\"{{ id }}\">{{ text }}</p>{% endblock %}\n");
		refreshTemplates();
		sql("UPDATE tl_juhe_consent_service SET noticeText='Video von ##service##: <b>erst</b> nach Klick.', noticeTemplate='juhe_consent/placeholder/compact' WHERE id=2");
		services(`(28, 2, 384, UNIX_TIMESTAMP(), 'Vimeo', 'Vimeo Inc.', '', '', '', 'vimeo.com', '', 1, 'code', ''),
			(29, 11, 256, UNIX_TIMESTAMP(), 'YouTube EN', 'Google', '', '', '', 'youtube.com', '', 1, 'code', '')`);
		sql("UPDATE tl_juhe_consent_service SET noticeText='Watch it on ##service##.' WHERE id=29");
		sql(`REPLACE INTO tl_content (id, pid, ptable, sorting, tstamp, type, vimeo, youtube) VALUES (20, 1, 'tl_article', 512, UNIX_TIMESTAMP(), 'vimeo', '76979871', ''), (21, 10, 'tl_article', 128, UNIX_TIMESTAMP(), 'youtube', '', 'defghijklmn')`);
	});
	test.afterAll(() => {
		sql("UPDATE tl_juhe_consent_service SET noticeText=NULL, noticeTemplate='' WHERE id=2; DELETE FROM tl_content WHERE id IN (20, 21); UPDATE tl_page SET language='en' WHERE id=10");
		dropTestRows();
		siteTemplate("placeholder/compact", null);
		refreshTemplates();
	});

	test("two services show their own accessible notices; load once and always allow still work (DE, 320 px)", async ({ page }) => {
		await page.setViewportSize({ width: 320, height: 720 });
		await goto(page, "/");
		await page.locator("[data-juhe-consent-action='essential']").click();
		const youtube = placeholder(page, "s2");
		const vimeo = placeholder(page, "s28");
		await expect(youtube.locator(".juhe-consent-placeholder__text")).toHaveText("Video von YouTube: <b>erst</b> nach Klick.");
		await expect(youtube.locator(".check-compact")).toHaveCount(1);
		await expect(vimeo.locator(".juhe-consent-placeholder__text")).toHaveText("Hier können Sie einen Inhalt von Vimeo laden. Dabei werden Daten an den Anbieter übertragen.");
		await expect(vimeo.locator(".check-compact")).toHaveCount(0);
		await expect(youtube.getByRole("button", { name: "Inhalt laden (YouTube)" })).toHaveAccessibleDescription("Video von YouTube: <b>erst</b> nach Klick.");
		await expect(vimeo.getByRole("button", { name: "Immer erlauben (Vimeo)" })).toHaveAccessibleDescription(/Inhalt von Vimeo/);
		expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
		await axe(page, ".juhe-consent-placeholder");
		// Load once: this YouTube iframe only
		await youtube.getByRole("button", { name: "Inhalt laden (YouTube)" }).click();
		await expect(page.locator("iframe[src*='youtube']")).toHaveCount(1);
		// Always allow: Vimeo, remembered; the YouTube video loaded once stays (no reload)
		await page.evaluate(() => ((window as any).sameDocument = true));
		await vimeo.getByRole("button", { name: "Immer erlauben (Vimeo)" }).click();
		await expect(page.locator("iframe[src*='vimeo']")).toHaveCount(1);
		await expect(page.locator("iframe[src*='youtube']")).toHaveCount(1);
		expect(await page.evaluate(() => (window as any).sameDocument)).toBe(true);
		await goto(page, "/");
		await expect(page.locator("iframe[src*='vimeo']")).toHaveCount(1);
		await expect(placeholder(page, "s2")).toBeVisible();
		expect(providerRequests.some((u) => u.includes("vimeo"))).toBe(true);
	});

	test("the notice text of the service in EN and IT", async ({ page }) => {
		await page.setViewportSize({ width: 320, height: 720 });
		await goto(page, "/en/home");
		await expect(placeholder(page, "s29").locator(".juhe-consent-placeholder__text")).toHaveText("Watch it on YouTube EN.");
		await expect(placeholder(page, "s29").getByRole("button", { name: "Load content (YouTube EN)" })).toBeVisible();
		sql("UPDATE tl_page SET language='it' WHERE id=10");
		await goto(page, "/en/home");
		await expect(placeholder(page, "s29").locator(".juhe-consent-placeholder__text")).toHaveText("Watch it on YouTube EN.");
		await expect(placeholder(page, "s29").getByRole("button", { name: "Carica contenuto (YouTube EN)" })).toBeVisible();
		await placeholder(page, "s29").getByRole("button", { name: "Consenti sempre (YouTube EN)" }).click();
		await expect(page.locator("iframe[src*='youtube']")).toHaveCount(1);
	});

	test("backend: the notice template offers the default and its variants", async ({ page, browserName }) => {
		test.skip(browserName === "webkit", "Secure session cookie over http");
		await backendLogin(page);
		await page.goto("/contao?do=juhe_consent&table=tl_juhe_consent_service&act=edit&id=2");
		await noError(page);
		const options = await page.locator("select[name='noticeTemplate'] option").evaluateAll((o) => o.map((e) => (e as HTMLOptionElement).value));
		expect(options).toEqual(["", "juhe_consent/placeholder/compact"]);
		await expect(page.locator("select[name='noticeTemplate']")).toHaveValue("juhe_consent/placeholder/compact");
	});
});

test.describe("essential starter", () => {
	test.beforeAll(() => {
		sql("DELETE FROM tl_juhe_consent_banner WHERE id=4; INSERT INTO tl_juhe_consent_banner (id, tstamp, title, headline, text, version, lifetime) VALUES (4, UNIX_TIMESTAMP(), 'Starter', 'Cookies', '', 1, 180)");
	});
	test.afterAll(() => {
		sql("DELETE FROM tl_juhe_consent_service WHERE pid IN (SELECT id FROM tl_juhe_consent_group WHERE pid=4); DELETE FROM tl_juhe_consent_group WHERE pid=4; DELETE FROM tl_juhe_consent_banner WHERE id=4");
	});
	const counts = (banner: number): string =>
		db(`SELECT CONCAT((SELECT COUNT(*) FROM tl_juhe_consent_group WHERE pid=${banner}), '/', (SELECT COUNT(*) FROM tl_juhe_consent_service s JOIN tl_juhe_consent_group g ON g.id=s.pid WHERE g.pid=${banner}))`);

	test("creates the essential group and the stored-choice service once; a second run adds nothing", async ({ page, browserName }) => {
		test.skip(browserName === "webkit", "Secure session cookie over http");
		await backendLogin(page);
		await page.goto("/contao?do=juhe_consent&table=tl_juhe_consent_group&id=4");
		await noError(page);
		const button = page.getByRole("button", { name: "Notwendige Gruppe anlegen" });
		await expect(button).toBeVisible();
		if (process.env.SHOT) await page.screenshot({ path: test.info().outputPath("essential.png") });
		await button.click();
		await page.waitForLoadState("load");
		await expect(page.getByText('Die Gruppe "Technisch notwendig" wurde angelegt.')).toBeVisible();
		expect(counts(4)).toBe("1/1");
		expect(db("SELECT CONCAT_WS('|', title, alias, essential, published) FROM tl_juhe_consent_group WHERE pid=4")).toBe("Technisch notwendig|essential|1|1");
		expect(db("SELECT CONCAT_WS('|', s.title, s.cookies, s.published, COALESCE(s.hosts, ''), s.code IS NULL OR s.code = '') FROM tl_juhe_consent_service s JOIN tl_juhe_consent_group g ON g.id=s.pid WHERE g.pid=4")).toBe("Einwilligung|juhe_consent_4: 180 Tage|1||1");
		await page.getByRole("button", { name: "Notwendige Gruppe anlegen" }).click();
		await page.waitForLoadState("load");
		await expect(page.getByText("sind schon vorhanden")).toBeVisible();
		expect(counts(4)).toBe("1/1");
		// Banner 1 already has both: nothing is added
		const before = counts(1);
		await page.goto("/contao?do=juhe_consent&table=tl_juhe_consent_group&id=1");
		await page.getByRole("button", { name: "Notwendige Gruppe anlegen" }).click();
		await page.waitForLoadState("load");
		await expect(page.getByText("sind schon vorhanden")).toBeVisible();
		expect(counts(1)).toBe(before);
		await noError(page);
	});

	test("refuses a post without the request token and without a login", async ({ page, browserName, request }) => {
		test.skip(browserName === "webkit", "Secure session cookie over http");
		const anonymous = await request.post("/contao/juhe-consent/essential/4", { form: { REQUEST_TOKEN: "x" }, maxRedirects: 0 });
		expect(anonymous.status()).not.toBe(303);
		expect(anonymous.status()).not.toBe(200);
		await backendLogin(page);
		db("DELETE FROM tl_juhe_consent_service WHERE pid IN (SELECT id FROM tl_juhe_consent_group WHERE pid=4); DELETE FROM tl_juhe_consent_group WHERE pid=4");
		const forged = await page.request.post("/contao/juhe-consent/essential/4", { form: { REQUEST_TOKEN: "forged" }, maxRedirects: 0 });
		expect(forged.status()).toBeGreaterThanOrEqual(400);
		const get = await page.request.get("/contao/juhe-consent/essential/4", { maxRedirects: 0 });
		expect([404, 405]).toContain(get.status());
		expect(counts(4)).toBe("0/0");
	});
});

test.describe("settings opener: module, insert tag, navigation", () => {
	test.beforeAll(() => {
		sql(`REPLACE INTO tl_module (id, pid, tstamp, name, type, customLabel) VALUES (20, 1, UNIX_TIMESTAMP(), 'Cookie settings', 'juhe_consent_settings', 'Datenschutz-Einstellungen'), (21, 1, UNIX_TIMESTAMP(), 'Navigation', 'navigation', '')`);
		sql(`REPLACE INTO tl_page (id, pid, sorting, tstamp, title, alias, type, url, published) VALUES (21, 1, 640, UNIX_TIMESTAMP(), 'Cookies', 'cookies', 'redirect', '{{link_url::3}}#juhe-consent', 1)`);
		sql(`REPLACE INTO tl_content (id, pid, ptable, sorting, tstamp, type, module, text) VALUES
			(22, 2, 'tl_article', 256, UNIX_TIMESTAMP(), 'module', 20, NULL),
			(23, 2, 'tl_article', 384, UNIX_TIMESTAMP(), 'text', 0, '<p>Ihre Wahl ändern: {{juhe_consent::settings}}</p>'),
			(24, 2, 'tl_article', 512, UNIX_TIMESTAMP(), 'module', 21, NULL),
			(25, 10, 'tl_article', 256, UNIX_TIMESTAMP(), 'text', 0, '<p>Change: {{juhe_consent::settings}}</p>')`);
	});
	test.afterAll(() => {
		sql("DELETE FROM tl_content WHERE id IN (22, 23, 24, 25); DELETE FROM tl_module WHERE id IN (20, 21); DELETE FROM tl_page WHERE id=21");
	});
	const moduleOpener = (page: Page) => page.locator(".module-juhe-consent-settings .juhe-consent-settings-button");
	const tagOpener = (page: Page) => page.locator(".content-text .juhe-consent-settings-button");

	test("module and insert tag render the opener as a link to the privacy page; it opens the prefilled settings by keyboard", async ({ page }) => {
		await page.setViewportSize({ width: 320, height: 720 });
		await goto(page, "/kontakt");
		await page.locator("[data-juhe-consent-action='all']").click();
		for (const opener of [moduleOpener(page), tagOpener(page)]) {
			await expect(opener).toHaveCount(1);
			expect(await opener.evaluate((e) => e.tagName)).toBe("A");
			await expect(opener).toHaveAttribute("href", /datenschutz.*#juhe-consent$/);
			await expect(opener).toHaveAttribute("aria-haspopup", "dialog");
		}
		await expect(moduleOpener(page)).toHaveText("Datenschutz-Einstellungen");
		await expect(tagOpener(page)).toHaveText("Cookie-Einstellungen");
		await moduleOpener(page).focus();
		await page.keyboard.press("Enter");
		await expect(dialog(page)).toBeVisible();
		await expect(page.getByRole("dialog", { name: "Cookies" })).toBeVisible();
		await expect(page.locator("#juhe-consent input[data-juhe-consent-checkbox][value='marketing']")).toBeChecked();
		expect(new URL(page.url()).pathname).toBe("/kontakt");
		await page.keyboard.press("Escape");
		await expect(dialog(page)).toBeHidden();
		await expect(moduleOpener(page)).toBeFocused();
		await tagOpener(page).click();
		await expect(dialog(page)).toBeVisible();
		await page.keyboard.press("Escape");
		await expect(tagOpener(page)).toBeFocused();
		expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
		await axe(page, ".module-juhe-consent-settings");
		await axe(page, ".content-text");
	});

	test("a navigation entry (redirect page) opens the settings; a link with #juhe-consent opens them on arrival", async ({ page }) => {
		await goto(page, "/kontakt");
		await page.locator("[data-juhe-consent-action='essential']").click();
		const entry = page.locator(".mod_navigation a, .module-navigation a", { hasText: "Cookies" });
		await expect(entry).toHaveAttribute("href", /datenschutz.*#juhe-consent$/);
		await entry.click();
		await expect(dialog(page)).toBeVisible();
		expect(new URL(page.url()).pathname).toBe("/kontakt");
		await page.keyboard.press("Escape");
		await goto(page, (await entry.getAttribute("href"))!);
		await expect(page.getByRole("dialog", { name: "Cookies" })).toBeVisible();
		await expect(page.locator("#juhe-consent [data-juhe-consent-settings]")).toBeVisible();
	});

	test("without a privacy page the opener is a button", async ({ page }) => {
		await goto(page, "/en/home");
		const button = page.locator(".content-text .juhe-consent-settings-button");
		expect(await button.evaluate((e) => [e.tagName, e.getAttribute("type")])).toEqual(["BUTTON", "button"]);
		await page.locator("[data-juhe-consent-action='essential']").click();
		await button.click();
		await expect(dialog(page)).toBeVisible();
	});

	test("backend: the module type offers its label field", async ({ page, browserName }) => {
		test.skip(browserName === "webkit", "Secure session cookie over http");
		await backendLogin(page);
		await page.goto("/contao?do=themes&table=tl_module&act=edit&id=20");
		await noError(page);
		await expect(page.locator("select[name='type']")).toHaveValue("juhe_consent_settings");
		await expect(page.locator("input[name='customLabel']")).toHaveValue("Datenschutz-Einstellungen");
	});
});

test.describe("settings opener without JavaScript", () => {
	test.use({ javaScriptEnabled: false });
	test.afterAll(() => {
		sql("DELETE FROM tl_content WHERE id IN (22, 23); DELETE FROM tl_module WHERE id=20");
	});

	test("the module leads to the privacy page", async ({ page }) => {
		sql(`REPLACE INTO tl_module (id, pid, tstamp, name, type, customLabel) VALUES (20, 1, UNIX_TIMESTAMP(), 'Cookie settings', 'juhe_consent_settings', '')`);
		sql(`REPLACE INTO tl_content (id, pid, ptable, sorting, tstamp, type, module, text) VALUES (22, 2, 'tl_article', 256, UNIX_TIMESTAMP(), 'module', 20, NULL)`);
		await page.goto("/kontakt");
		await page.locator(".module-juhe-consent-settings .juhe-consent-settings-button").click();
		await page.waitForLoadState("load");
		expect(new URL(page.url()).pathname).toMatch(/datenschutz/);
		await expect(page.locator("h1, title").first()).toBeAttached();
	});
});

test.describe("resource hints", () => {
	const head = [
		'<link rel="preconnect" href="https://fonts.blocked.example">',
		'<link rel="dns-prefetch" href="https://www.googletagmanager.com">',
		'<link rel="preload" as="script" href="https://www.googletagmanager.com/gtm.js?id=GTM-PRELOAD">',
		'<link rel="stylesheet" href="https://fonts.blocked.example/fonts.css" media="all">',
		'<link rel="canonical" href="https://www.googletagmanager.com/canonical">',
	].join("\n");
	test.beforeAll(() => {
		db(`UPDATE tl_layout SET head='${head.replace(/'/g, "\\'")}' WHERE id=1`);
		// The check policy allows no external stylesheet: without it, the restored links show up as requests
		db("UPDATE tl_page SET enableCsp=0 WHERE id=1");
		services(`(30, 2, 512, UNIX_TIMESTAMP(), 'Fonts', 'Example', '', '', '', 'fonts.blocked.example', '', 1, 'code', '')`);
		sql(`REPLACE INTO tl_content (id, pid, ptable, sorting, tstamp, type, text) VALUES (26, 1, 'tl_article', 640, UNIX_TIMESTAMP(), 'text', '<p><a href="https://www.googletagmanager.com/plain">Plain link</a></p>')`);
	});
	test.afterAll(() => {
		db("UPDATE tl_layout SET head=NULL WHERE id=1; UPDATE tl_page SET enableCsp=1 WHERE id=1");
		sql("DELETE FROM tl_content WHERE id=26");
		dropTestRows();
	});
	const blocked = /googletagmanager|fonts\.blocked\.example/;

	test("preconnect, dns-prefetch, preload and stylesheet wait for consent; plain links stay", async ({ page }) => {
		await goto(page, "/");
		await page.waitForLoadState("networkidle");
		expect(providerRequests.filter((u) => blocked.test(u))).toEqual([]);
		await expect(page.locator("link[data-juhe-consent-href]")).toHaveCount(4);
		await expect(page.locator("link[data-juhe-consent-href][rel], link[data-juhe-consent-href][href]")).toHaveCount(0);
		await expect(page.locator("link[rel='canonical'][href*='googletagmanager']")).toHaveCount(1);
		await expect(page.locator("a", { hasText: "Plain link" })).toHaveAttribute("href", "https://www.googletagmanager.com/plain");
		// Refused: still nothing
		await page.locator("[data-juhe-consent-action='essential']").click();
		await goto(page, "/");
		await page.waitForLoadState("networkidle");
		expect(providerRequests.filter((u) => blocked.test(u))).toEqual([]);
		// Allowed: the links come back with their attributes and the browser fetches them
		await page.evaluate(() => (window as any).juheConsent.open());
		await page.locator("#juhe-consent input[data-juhe-consent-checkbox][value='marketing']").check();
		await page.locator("[data-juhe-consent-action='save']").click();
		await expect(page.locator("link[data-juhe-consent-href]")).toHaveCount(0);
		await expect(page.locator("link[rel='stylesheet'][href='https://fonts.blocked.example/fonts.css'][media='all']")).toHaveCount(1);
		await expect(page.locator("link[rel='preload'][as='script'][href*='GTM-PRELOAD']")).toHaveCount(1);
		await expect(page.locator("link[rel='preconnect'][href^='https://fonts.blocked.example']")).toHaveCount(1);
		await expect(page.locator("link[rel='dns-prefetch']")).toHaveCount(1);
		await expect.poll(() => providerRequests.filter((u) => u.includes("fonts.blocked.example/fonts.css")).length).toBe(1);
		await expect.poll(() => providerRequests.filter((u) => u.includes("GTM-PRELOAD")).length).toBeGreaterThanOrEqual(1);
	});
});
