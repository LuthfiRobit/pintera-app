// scripts/keuangan-6a-browser-check.mjs
// Dev-only tool: interactive Playwright verification for Keuangan Sub-project 6a
// (orang tua dashboard, child switcher, notification bell). Unlike
// manual-book-screenshots.mjs, this clicks/asserts DOM state rather than only
// capturing screenshots — needed because Alpine.js interactivity bugs (e.g. a
// @js() escaping bug inside @click) do not show up in Pest HTTP tests.
// Usage: node scripts/keuangan-6a-browser-check.mjs [--check=<bell|switcher|dashboard|all>]

import { chromium } from 'playwright';

const BASE_URL = process.env.KEUANGAN_CHECK_BASE_URL || 'http://localhost';
const DEMO_ACCOUNT = { email: 'ortu.demo@permatakraksaan.sch.id', password: 'password' };

async function login(page) {
  await page.goto(`${BASE_URL}/login`);
  await page.fill('input[name=email]', DEMO_ACCOUNT.email);
  await page.fill('input[name=password]', DEMO_ACCOUNT.password);
  await page.click('button[type=submit]');
  await page.waitForURL(/\/(dashboard|keuangan)/);
}

async function checkBell(page) {
  await page.goto(`${BASE_URL}/dashboard`);
  const bellButton = page.locator('button[aria-label="Notifikasi"]');
  await bellButton.click();
  const panel = page.locator('text=Notifikasi').first();
  await panel.waitFor({ state: 'visible', timeout: 3000 });
  console.log('[bell] dropdown opened and panel visible: OK');
}

const args = process.argv.slice(2);
const checkArg = args.find((a) => a.startsWith('--check='))?.split('=')[1] ?? 'all';

const browser = await chromium.launch();
const page = await browser.newPage();

try {
  await login(page);
  if (checkArg === 'all' || checkArg === 'bell') {
    await checkBell(page);
  }
} finally {
  await browser.close();
}
