import { chromium, type BrowserContext, type Page } from "@playwright/test";
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import path from "node:path";

// A larger browser default font size, headless, as a phone with large text sets
// it. Page zoom and `html { font-size: 200% }` leave the default untouched, so
// `rem` media queries behave as at 16 px. Chromium reads the preference from the
// profile Preferences file, written before the launch; it needs channel "chromium".

export type DefaultFont = {
	context: BrowserContext;
	page: Page;
	close: () => Promise<void>;
};

export async function launchDefaultFont(options: { size: number; width: number; height?: number; baseURL?: string }): Promise<DefaultFont> {
	const { size, width, height = 852 } = options;
	const profile = mkdtempSync(path.join(tmpdir(), "juhe-consent-font-"));
	mkdirSync(path.join(profile, "Default"), { recursive: true });
	writeFileSync(path.join(profile, "Default", "Preferences"), JSON.stringify({ webkit: { webprefs: { default_font_size: size } } }));
	const context = await chromium.launchPersistentContext(profile, {
		headless: true,
		channel: "chromium",
		viewport: { width, height },
		baseURL: options.baseURL,
		deviceScaleFactor: undefined,
		isMobile: undefined,
		hasTouch: undefined,
	});
	const page = context.pages()[0] ?? (await context.newPage());
	return {
		context,
		page,
		close: async () => {
			await context.close();
			rmSync(profile, { recursive: true, force: true });
		},
	};
}

/** The root font size the page sees, in px; proves the preference took effect. */
export async function readRootFontSize(page: Page): Promise<number> {
	return page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize));
}
