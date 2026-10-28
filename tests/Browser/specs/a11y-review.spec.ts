import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import { launchNativeZoom, readZoomState, captureNativeZoom } from "../helpers/native-zoom";
import { launchDefaultFont, readRootFontSize } from "../helpers/default-font";

// Chromium only: the dialog at a 24 and 32 px default font on a 320 px wide window,
// and at real 200 percent page zoom from phone to ultrawide width. Nothing may
// scroll sideways, and Tab must reach a visible control in the dialog.

async function inspect(context: BrowserContext, page: Page, name: string) {
  await context.route('**/*', route => new URL(route.request().url()).hostname === '127.0.0.1' ? route.continue() : route.fulfill({status:200, body:''}));
  await page.goto('http://127.0.0.1:8199/');
  await page.locator('.juhe-consent-settings-button').click();
  await page.locator('#juhe-consent details').evaluateAll(elements => elements.forEach(element => {if (element instanceof HTMLDetailsElement) element.open = true;}));
  await page.waitForFunction(() => document.getAnimations().every(animation => animation.playState !== 'running'));
  await page.screenshot({path:test.info().outputPath(`${name}.png`)});
  expect(await page.locator('#juhe-consent').evaluate(element => element.scrollWidth <= element.clientWidth),name).toBe(true);
  await page.keyboard.press('Tab');
  await expect(page.locator('#juhe-consent :focus')).toHaveCount(1);
  await page.screenshot({path:test.info().outputPath(`${name}-focus.png`)});
}

for (const size of [24,32]) {
 test(`real browser default font ${size} at 320px`,async ({browserName})=>{
  test.skip(browserName !== 'chromium');
  const run = await launchDefaultFont({size,width:320,height:800});
  try {await inspect(run.context,run.page,`font-${size}`);expect(await readRootFontSize(run.page)).toBe(size);} finally {await run.close();}
 });
}
for(const width of [320,1440,2560]) {
 test(`native 200 percent zoom at ${width} CSS px`,async({browserName})=>{
  test.skip(browserName !== 'chromium');
  const run = await launchNativeZoom({percent:200,cssWidth:width,host:'127.0.0.1'});
  try {await inspect(run.context,run.page,`zoom-${width}`);const state=await readZoomState(run.page);expect(state.cssWidth).toBe(width);expect(state.dpr).toBe(2);await captureNativeZoom(run,test.info().outputPath(`native-zoom-${width}.png`));} finally {await run.close();}
 });
}
