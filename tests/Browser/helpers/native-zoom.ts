import { chromium, type BrowserContext, type Page } from "@playwright/test";
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import path from "node:path";

// Real browser page zoom (as with Ctrl and plus), headless. A device scale factor
// or a narrow viewport only imitates it. Chromium reads the zoom level from the
// profile Preferences file, so the profile is written before the launch. It needs
// channel "chromium" (the new headless mode); the headless shell ignores the file.
// Zoom level = log(percent / 100) / log(1.2). The window is cssWidth * percent / 100
// px wide, so the page sees exactly cssWidth by cssHeight CSS px.

export type NativeZoom = {
	context: BrowserContext;
	page: Page;
	close: () => Promise<void>;
};

export async function launchNativeZoom(options: { percent: number; cssWidth: number; cssHeight?: number; host?: string }): Promise<NativeZoom> {
	const { percent, cssWidth, cssHeight = 900, host = "127.0.0.1" } = options;
	const level = Math.log(percent / 100) / Math.log(1.2);
	const profile = mkdtempSync(path.join(tmpdir(), "juhe-consent-zoom-"));
	mkdirSync(path.join(profile, "Default"), { recursive: true });
	writeFileSync(
		path.join(profile, "Default", "Preferences"),
		JSON.stringify({ partition: { default_zoom_level: { x: level }, per_host_zoom_levels: { x: { [host]: { zoom_level: level } } } } }),
	);
	const context = await chromium.launchPersistentContext(profile, {
		headless: true,
		channel: "chromium",
		viewport: null,
		// The project device adds a scale factor to every launch, which Chromium refuses
		// next to a null viewport; the zoom sets the ratio.
		deviceScaleFactor: undefined,
		isMobile: undefined,
		hasTouch: undefined,
		args: [`--window-size=${Math.round((cssWidth * percent) / 100)},${Math.round((cssHeight * percent) / 100)}`],
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

// Proves the zoom took effect. Call after the first navigation.
export async function readZoomState(page: Page): Promise<{ cssWidth: number; dpr: number }> {
	return page.evaluate(() => ({ cssWidth: window.innerWidth, dpr: window.devicePixelRatio }));
}

// page.screenshot() under native zoom keeps only part of the viewport; the
// DevTools protocol captures all of it.
export async function captureNativeZoom(zoom: NativeZoom, file: string): Promise<void> {
	const session = await zoom.context.newCDPSession(zoom.page);
	const shot = await session.send("Page.captureScreenshot", { format: "png", captureBeyondViewport: false });
	writeFileSync(file, Buffer.from(shot.data, "base64"));
	await session.detach();
}
