import { execFileSync } from "node:child_process";
import { randomUUID } from "node:crypto";
import path from "node:path";
import { expect, test, type Page } from "@playwright/test";

/* eslint-disable @typescript-eslint/no-explicit-any */

// Batch B: consent log governance (logging switch, retention, page address,
// wide columns) and the CSV export of the log.

test.beforeEach(async ({ context }) => {
	await context.route("**/*", (route) => {
		const url = new URL(route.request().url());
		if (url.hostname === "127.0.0.1") return route.continue();
		return route.fulfill({ status: 200, body: "", contentType: route.request().resourceType() === "script" ? "application/javascript" : "text/html" });
	});
});

const db = (query: string): string =>
	execFileSync("docker", ["exec", "juhe-consent-browser-db", "mariadb", "-uroot", `-p${process.env.CONSENT_DB_PW}`, "-N", "-B", "contao", "-e", query]).toString().trim();
const clearCache = (): void => {
	execFileSync("docker", ["exec", "juhe-consent-browser-php", "rm", "-rf", "/var/www/html/var/cache/prod/http_cache"]);
};
const banner = (set: string): void => {
	db(`UPDATE tl_juhe_consent_banner SET ${set} WHERE id=1`);
	clearCache();
};
const resetLog = (): void => banner("logConsent=1, logRetention=36, logPage='path', version=1");
const console_ = (...args: string[]): string =>
	execFileSync("docker", ["exec", "-u", "www-data", "juhe-consent-browser-php", "php", "/var/www/html/vendor/bin/contao-console", ...args]).toString();
const logCount = (): number => Number(db("SELECT COUNT(*) FROM tl_juhe_consent_log"));
const config = (page: Page) => page.evaluate(() => JSON.parse(document.getElementById("juhe-consent-config")?.textContent ?? "{}"));
const ok = { banner: 1, version: 1, id: "0f8e7d6c-5b4a-4321-8abc-0123456789ab", groups: ["notwendig", "marketing"], services: [], url: "/" };

test.describe.configure({ mode: "serial" });
test.beforeAll(resetLog);
test.afterAll(resetLog);

test.describe("consent log governance", () => {
	test.afterEach(resetLog);

	test("logging off: the browser posts nothing and the server refuses a post", async ({ page }) => {
		banner("logConsent=0");
		const posts: string[] = [];
		page.on("request", (request) => {
			if (request.url().includes("/_juhe_consent/log")) posts.push(request.method());
		});
		const before = logCount();
		await page.goto("/");
		expect((await config(page)).logUrl).toBeNull();
		await page.locator("[data-juhe-consent-action='all']").click();
		expect(await page.evaluate(() => (window as any).juheConsent.has("marketing"))).toBe(true);
		await page.waitForTimeout(500);
		expect(posts).toEqual([]);
		// A page from before the switch still posts: the server stores nothing
		const response = await page.request.post("/_juhe_consent/log", { data: ok });
		expect(response.status()).toBe(403);
		expect(logCount()).toBe(before);
	});

	test("logging on: the page path is stored without query string and fragment", async ({ page }) => {
		await page.goto("/kontakt?token=geheim-123#oben");
		expect((await config(page)).logUrl).toBe("/_juhe_consent/log");
		const post = page.waitForRequest((request) => request.url().includes("/_juhe_consent/log"));
		await page.locator("[data-juhe-consent-action='all']").click();
		const body = JSON.parse((await post).postData() ?? "{}");
		expect(body.url).toBe("/kontakt");
		const id = await page.evaluate(() => JSON.parse(localStorage.getItem("juhe_consent_1") ?? "{}").id);
		await expect.poll(() => db(`SELECT url FROM tl_juhe_consent_log WHERE consentId='${id}'`)).toBe("/kontakt");
		// The server strips them as well
		const direct = { ...ok, id: randomUUID(), url: "/kontakt?token=geheim#x" };
		expect((await page.request.post("/_juhe_consent/log", { data: direct })).status()).toBe(204);
		expect(db(`SELECT url FROM tl_juhe_consent_log WHERE consentId='${direct.id}'`)).toBe("/kontakt");
	});

	test("page address none: the browser sends no page and the log keeps none", async ({ page }) => {
		banner("logPage='none'");
		await page.goto("/kontakt");
		expect((await config(page)).logPage).toBe("none");
		const post = page.waitForRequest((request) => request.url().includes("/_juhe_consent/log"));
		await page.locator("[data-juhe-consent-action='essential']").click();
		const body = JSON.parse((await post).postData() ?? "{}");
		expect(body).not.toHaveProperty("url");
		const id = await page.evaluate(() => JSON.parse(localStorage.getItem("juhe_consent_1") ?? "{}").id);
		await expect.poll(() => db(`SELECT CONCAT('[', url, ']') FROM tl_juhe_consent_log WHERE consentId='${id}'`)).toBe("[]");
		const direct = { ...ok, id: randomUUID(), url: "/konto/4711" };
		expect((await page.request.post("/_juhe_consent/log", { data: direct })).status()).toBe(204);
		expect(db(`SELECT CONCAT('[', url, ']') FROM tl_juhe_consent_log WHERE consentId='${direct.id}'`)).toBe("[]");
	});

	test("a choice longer than 255 bytes is stored in full", async ({ page }) => {
		const aliases = Array.from({ length: 12 }, (_, i) => `lange-gruppe-mit-schluessel-${i}`);
		db(
			`INSERT INTO tl_juhe_consent_group (id, pid, sorting, tstamp, title, alias, essential, description, published) VALUES ${aliases
				.map((alias, i) => `(${100 + i}, 1, ${1000 + i}, UNIX_TIMESTAMP(), 'Lang ${i}', '${alias}', 0, '', 1)`)
				.join(", ")}`,
		);
		try {
			const data = { ...ok, id: randomUUID(), groups: ["notwendig", ...aliases] };
			expect(JSON.stringify(data.groups).length).toBeGreaterThan(255);
			expect((await page.request.post("/_juhe_consent/log", { data })).status()).toBe(204);
			expect(db(`SELECT \`groups\` FROM tl_juhe_consent_log WHERE consentId='${data.id}'`)).toBe(data.groups.join(","));
			// The 4 KiB body limit still holds
			const big = { ...data, id: randomUUID(), groups: Array.from({ length: 200 }, () => aliases[0]) };
			expect((await page.request.post("/_juhe_consent/log", { data: big })).status()).toBe(413);
		} finally {
			db("DELETE FROM tl_juhe_consent_group WHERE id >= 100");
			clearCache();
		}
	});

	test("the daily purge follows each banner's retention", async () => {
		// Banner 1 keeps 6 months, banner 99 does not exist (default 36 months)
		banner("logRetention=6");
		db("DELETE FROM tl_juhe_consent_log WHERE consentId LIKE 'aaaaaaaa-%'");
		const row = (id: string, pid: number, months: number, offset: number): string =>
			`(${pid}, 1, 'aaaaaaaa-0000-4000-8000-${id}', 'notwendig', '', '/', UNIX_TIMESTAMP(UTC_TIMESTAMP() - INTERVAL ${months} MONTH) + ${offset})`;
		db(
			`INSERT INTO tl_juhe_consent_log (pid, version, consentId, \`groups\`, services, url, tstamp) VALUES ${[
				row("000000000001", 1, 6, -3600),
				row("000000000002", 1, 6, 3600),
				row("000000000003", 1, 5, 0),
				row("000000000004", 99, 36, -3600),
				row("000000000005", 99, 36, 3600),
				row("000000000006", 99, 24, 0),
			].join(", ")}`,
		);
		const list = JSON.parse(console_("contao:cron:list", "--format=json"));
		const name = (list as any[]).map((job) => job.name ?? job.Name ?? job.service ?? JSON.stringify(job)).find((n: string) => /PurgeConsentLogCron/.test(n));
		expect(name, JSON.stringify(list)).toBeTruthy();
		console_("contao:cron", name, "--force");
		expect(db("SELECT RIGHT(consentId, 1) FROM tl_juhe_consent_log WHERE consentId LIKE 'aaaaaaaa-%' ORDER BY consentId").split("\n")).toEqual(["2", "3", "5", "6"]);
		// 36 months on banner 1 keeps the 6 months entries
		banner("logRetention=36");
		console_("contao:cron", name, "--force");
		expect(db("SELECT COUNT(*) FROM tl_juhe_consent_log WHERE consentId LIKE 'aaaaaaaa-%'")).toBe("4");
		db("DELETE FROM tl_juhe_consent_log WHERE consentId LIKE 'aaaaaaaa-%'");
	});
});

test("backend: the log fields show with their defaults", async ({ page, browserName }) => {
	test.skip(browserName === "webkit", "Secure session cookie over http");
	await page.goto("/contao/login");
	await page.locator("input[name='username']").fill("check");
	await page.locator("input[name='password']").fill("check-pass-123");
	await page.locator("button[type='submit']").first().click();
	await page.waitForLoadState("load");
	await page.goto("/contao?do=juhe_consent&act=edit&id=1");
	await expect(page.locator("input[type='checkbox'][name='logConsent']")).toBeChecked();
	await expect(page.locator("select[name='logRetention']")).toHaveValue("36");
	expect(await page.locator("select[name='logRetention'] option").evaluateAll((o) => o.map((e) => (e as HTMLOptionElement).value))).toEqual(["6", "12", "24", "36"]);
	await expect(page.locator("select[name='logPage']")).toHaveValue("path");
	await page.locator("select[name='logPage']").selectOption("none");
	await page.locator("select[name='logRetention']").selectOption("12");
	await Promise.all([page.waitForResponse((r) => r.request().method() === "POST"), page.locator("button[name='save'], #save").first().click()]);
	await page.waitForLoadState("load");
	await expect.poll(() => db("SELECT CONCAT(logRetention, ' ', logPage) FROM tl_juhe_consent_banner WHERE id=1")).toBe("12 none");
	expect(await page.content()).not.toMatch(/An exception occurred|Fatal error|Unknown column/);
	resetLog();
});

test.describe("consent log export", () => {
	const SEEDED = 50000;
	const exportUrl = "/contao/juhe-consent/log-export";
	const login = async (page: Page, user: string, password: string): Promise<void> => {
		await page.goto("/contao/login");
		await page.locator("input[name='username']").fill(user);
		await page.locator("input[name='password']").fill(password);
		await page.locator("button[type='submit']").first().click();
		await page.waitForLoadState("load");
		await expect(page.locator("input[name='password']")).toHaveCount(0);
	};
	const rows = (csv: string): string[][] =>
		csv
			.replace(/^﻿/, "")
			.split("\r\n")
			.filter((line) => line !== "")
			.map((line) => line.match(/("([^"]|"")*"|[^,]*)(,|$)/g)!.filter((c) => c !== "").map((c) => c.replace(/,$/, "").replace(/^"(.*)"$/s, "$1").replace(/""/g, '"')));
	const setFilter = async (page: Page, values: Record<string, string>): Promise<void> => {
		await page.goto("/contao?do=juhe_consent_log");
		await page.evaluate((v) => {
			const form = (document.querySelector("input[name='FORM_SUBMIT'][value='tl_filters']") as HTMLInputElement).form!;
			for (const [name, value] of Object.entries(v)) {
				const field = form.elements.namedItem(name) as HTMLInputElement | HTMLSelectElement;
				if (field instanceof HTMLSelectElement && ![...field.options].some((o) => o.value === value)) field.add(new Option(value, value));
				field.value = value;
			}
		}, values);
		await Promise.all([page.waitForResponse((r) => r.request().method() === "POST" && r.url().includes("do=juhe_consent_log")), page.evaluate(() => (document.querySelector("input[name='FORM_SUBMIT'][value='tl_filters']") as HTMLInputElement).form!.requestSubmit())]);
		await page.waitForLoadState("load");
	};

	test.beforeAll(() => {
		db("DELETE FROM tl_juhe_consent_log WHERE consentId LIKE 'bbbbbbbb-%' OR consentId LIKE 'cccccccc-%'");
		db("DELETE FROM tl_juhe_consent_banner WHERE id=2");
		db(`INSERT INTO tl_juhe_consent_banner (id, tstamp, title, headline, text, version) VALUES (2, UNIX_TIMESTAMP(), '=HYPERLINK("http://example.org")', 'X', '', 2)`);
		db(
			`SET SESSION max_recursive_iterations = 100000; INSERT INTO tl_juhe_consent_log (pid, version, consentId, \`groups\`, services, url, tstamp) WITH RECURSIVE seq(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM seq WHERE n < ${SEEDED}) SELECT 1, 1, CONCAT('bbbbbbbb-0000-4000-8000-', LPAD(n, 12, '0')), IF(n % 2, 'notwendig', 'notwendig,marketing'), '', CONCAT('/seite-', n), UNIX_TIMESTAMP() - n FROM seq`,
		);
		db(
			`INSERT INTO tl_juhe_consent_log (pid, version, consentId, \`groups\`, services, url, tstamp) VALUES (2, 2, 'cccccccc-0000-4000-8000-000000000001', '@SUM(1)', '+1', '/a', UNIX_TIMESTAMP()), (2, 2, 'cccccccc-0000-4000-8000-000000000002', '-2', '=1+1', '/b', UNIX_TIMESTAMP()), (2, 2, 'cccccccc-0000-4000-8000-000000000003', 'notwendig', '', '/c', UNIX_TIMESTAMP())`,
		);
		db(`UPDATE tl_user SET admin=0, inherit='custom', modules='a:1:{i:0;s:12:"juhe_consent";}' WHERE username='editor'`);
		db(`UPDATE tl_user SET admin=0, inherit='custom', modules='a:1:{i:0;s:16:"juhe_consent_log";}' WHERE username='auditor'`);
	});
	test.afterAll(() => {
		db("DELETE FROM tl_juhe_consent_log WHERE consentId LIKE 'bbbbbbbb-%' OR consentId LIKE 'cccccccc-%'");
		db("DELETE FROM tl_juhe_consent_banner WHERE id=2");
	});

	test("a user without the log module gets a 403, an anonymous visitor the login", async ({ page, browserName }) => {
		test.skip(browserName === "webkit", "Secure session cookie over http");
		const anonymous = await page.request.get(exportUrl, { maxRedirects: 0 });
		expect(anonymous.status()).not.toBe(200);
		expect(anonymous.headers()["content-type"] ?? "").not.toMatch(/csv/);
		await login(page, "editor", "editor-pass-123");
		const denied = await page.request.get(exportUrl, { maxRedirects: 0 });
		expect(denied.status()).toBe(403);
		expect(denied.headers()["content-type"] ?? "").not.toMatch(/csv/);
		expect(await denied.text()).not.toContain("bbbbbbbb-");
	});

	test("the export follows the list filter and search, cells are formula-safe, no IP or user agent", async ({ page, browserName }) => {
		test.skip(browserName === "webkit", "Secure session cookie over http");
		await login(page, "auditor", "auditor-pass-123");
		await setFilter(page, { pid: "2", tl_search: "consentId", tl_value: "" });
		// The operation sits in the list header and downloads the file
		const link = page.locator("a.export, a[href*='log-export']").first();
		await expect(link).toBeVisible();
		await expect(link).toHaveAccessibleName(/CSV/);
		const [download] = await Promise.all([page.waitForEvent("download"), link.click()]);
		expect(download.suggestedFilename()).toMatch(/^consent-log-\d{4}-\d{2}-\d{2}\.csv$/);
		const csv = rows((await import("node:fs")).readFileSync((await download.path())!, "utf8"));
		expect(csv[0]).toEqual(["id", "time", "banner_id", "banner", "version", "consent_id", "groups", "services", "page"]);
		expect(csv.slice(1).map((r) => r[5])).toEqual(["cccccccc-0000-4000-8000-000000000003", "cccccccc-0000-4000-8000-000000000002", "cccccccc-0000-4000-8000-000000000001"]);
		expect(csv[1][3]).toBe(`'=HYPERLINK("http://example.org")`);
		expect(csv[2].slice(6, 8)).toEqual(["'-2", "'=1+1"]);
		expect(csv[3].slice(6, 8)).toEqual(["'@SUM(1)", "'+1"]);
		const text = csv.flat().join(" ");
		expect(text).not.toMatch(/\b\d{1,3}(\.\d{1,3}){3}\b|Mozilla|HeadlessChrome/);
		// Search narrows it further, like the list
		await setFilter(page, { pid: "2", tl_search: "consentId", tl_value: "0002$" });
		const list = page.locator("#main, main").first();
		await expect(list).toContainText("cccccccc-0000-4000-8000-000000000002");
		await expect(list).not.toContainText("cccccccc-0000-4000-8000-000000000001");
		const searched = rows(await (await page.request.get(exportUrl)).text());
		expect(searched.slice(1).map((r) => r[5])).toEqual(["cccccccc-0000-4000-8000-000000000002"]);
		// Clearing the filter exports everything
		await setFilter(page, { pid: "tl_pid", tl_search: "consentId", tl_value: "" });
		const started = Date.now();
		const response = await page.request.get(exportUrl, { timeout: 120_000 });
		expect(response.status()).toBe(200);
		expect(response.headers()["content-type"]).toBe("text/csv; charset=UTF-8");
		const all = rows(await response.text());
		const total = Number(db("SELECT COUNT(*) FROM tl_juhe_consent_log"));
		expect(total).toBeGreaterThanOrEqual(SEEDED + 3);
		expect(all.length - 1).toBe(total);
		expect(new Set(all.slice(1).map((r) => r[0])).size).toBe(total);
		console.log(`export of ${total} rows: ${Date.now() - started} ms`);
		await setFilter(page, { pid: "tl_pid", tl_search: "consentId", tl_value: "" });
	});

	test("memory stays flat with the size of the log", () => {
		// The export service writes to /dev/null in the container. The peak is one
		// chunk of 1000 rows: 25000 and 50000 rows peak the same, a few rows lower.
		const run = (filter: string): { rows: number; peak: number } =>
			JSON.parse(execFileSync("docker", ["exec", "-u", "www-data", "juhe-consent-browser-php", "php", "-d", "memory_limit=64M", "/tmp/export-memory.php", filter]).toString());
		execFileSync("docker", ["cp", path.join(__dirname, "..", "support", "export-memory.php"), "juhe-consent-browser-php:/tmp/export-memory.php"]);
		const small = run("pid=2");
		const half = run("groups=notwendig,marketing");
		const large = run("");
		console.log(`export memory: ${small.rows} rows ${small.peak} B, ${half.rows} rows ${half.peak} B, ${large.rows} rows ${large.peak} B`);
		expect(small.rows).toBe(3);
		expect(half.rows).toBeGreaterThanOrEqual(SEEDED / 2);
		expect(large.rows).toBeGreaterThanOrEqual(SEEDED);
		expect(Math.abs(large.peak - half.peak)).toBeLessThan(256 * 1024);
		expect(large.peak).toBeLessThan(8 * 1024 * 1024);
	});
});
