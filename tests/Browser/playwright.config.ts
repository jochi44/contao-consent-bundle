import { defineConfig, devices } from "@playwright/test";

// Runs against the throwaway Contao install that run.sh builds on 127.0.0.1:8199.
export default defineConfig({
	testDir: "./specs",
	outputDir: "./var/test-results",
	timeout: 60_000,
	workers: 1,
	reporter: "line",
	use: { baseURL: "http://127.0.0.1:8199", headless: true, locale: "de-DE" },
	projects: [
		{ name: "chromium", use: { ...devices["Desktop Chrome"] } },
		{ name: "firefox", use: { ...devices["Desktop Firefox"] } },
		{ name: "webkit", use: { ...devices["Desktop Safari"] } },
	],
});
