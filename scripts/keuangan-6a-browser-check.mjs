// scripts/keuangan-6a-browser-check.mjs
// Dev-only tool: interactive Playwright verification for Keuangan Sub-project 6a
// (orang tua dashboard, child switcher, notification bell). Unlike
// manual-book-screenshots.mjs, this clicks/asserts DOM state rather than only
// capturing screenshots — needed because Alpine.js interactivity bugs (e.g. a
// @js() escaping bug inside @click) do not show up in Pest HTTP tests.
// Usage: node scripts/keuangan-6a-browser-check.mjs [--check=<bell|switcher|dashboard|tagihan-checkout|all>]

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

async function checkSwitcher(page) {
  await page.goto(`${BASE_URL}/keuangan`);
  const switcherButton = page.locator('button:has-text("Pilih Profil Anak")');
  const count = await switcherButton.count();
  if (count === 0) {
    console.log('[switcher] demo account has 1 child (or none) — dropdown correctly hidden: OK');
    return;
  }
  await switcherButton.click();
  const firstOption = page.locator('a[href*="switch_siswa"]').first();
  await firstOption.waitFor({ state: 'visible', timeout: 3000 });
  await firstOption.click();
  await page.waitForLoadState('networkidle');
  console.log('[switcher] dropdown opened, option clicked, page reloaded: OK');
}

async function checkDashboard(page) {
  await page.goto(`${BASE_URL}/keuangan`);
  const walletCard = page.locator('text=Saldo Wallet');
  await walletCard.waitFor({ state: 'visible', timeout: 3000 });
  const topupButton = page.locator('button:has-text("+ Top Up")');
  await topupButton.waitFor({ state: 'visible', timeout: 3000 });
  console.log('[dashboard] wallet card and top-up button rendered: OK');
}

async function checkTagihanAndWalletCheckout(page) {
  await page.goto(`${BASE_URL}/keuangan/tagihan`);
  const firstCheckbox = page.locator('input[type="checkbox"]').first();
  await firstCheckbox.waitFor({ state: 'visible', timeout: 3000 });
  await firstCheckbox.check();

  const bayarButton = page.locator('a:has-text("Bayar Terpilih")');
  await bayarButton.waitFor({ state: 'visible', timeout: 3000 });
  await bayarButton.click();

  await page.waitForURL(/\/keuangan\/checkout/, { timeout: 5000 });

  const walletTab = page.getByRole('button', { name: 'Saldo Wallet', exact: true });
  await walletTab.click();
  const walletSubmit = page.locator('form[action*="checkout/wallet"] button[type="submit"]');
  await walletSubmit.waitFor({ state: 'visible', timeout: 3000 });
  await walletSubmit.click();

  await page.waitForURL(/\/keuangan\/checkout\/\d+\/sukses/, { timeout: 5000 });
  const successMessage = page.locator('text=Pembayaran dari Saldo Wallet berhasil diproses.');
  await successMessage.waitFor({ state: 'visible', timeout: 3000 });
  console.log('[tagihan+wallet] tagihan list -> checkout tabs -> wallet payment succeeded: OK');
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
  if (checkArg === 'all' || checkArg === 'switcher') {
    await checkSwitcher(page);
  }
  if (checkArg === 'all' || checkArg === 'dashboard') {
    await checkDashboard(page);
  }
  if (checkArg === 'all' || checkArg === 'tagihan-checkout') {
    await checkTagihanAndWalletCheckout(page);
  }
} finally {
  await browser.close();
}
