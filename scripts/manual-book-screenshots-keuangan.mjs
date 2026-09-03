// scripts/manual-book-screenshots-keuangan.mjs
// Dev-only tool: generates screenshots for docs/manual-book/keuangan/*.md.
// Usage: node scripts/manual-book-screenshots-keuangan.mjs [--bab=<00|01|02|03|04|05|06|07|08|lampiran>]

import { chromium } from 'playwright';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUTPUT_DIR = path.join(__dirname, '..', 'docs', 'manual-book', 'keuangan', 'images');
const BASE_URL = process.env.MANUAL_BOOK_BASE_URL || 'http://127.0.0.1:8000';

const ACCOUNTS = {
  bendahara: { email: 'bendahara.test@pintera.id', password: 'password', loginUrl: '/login' },
  yayasan: { email: 'yayasan.test@pintera.id', password: 'password', loginUrl: '/login' },
  ortu: { email: 'orangtua.test@pintera.id', password: 'password', loginUrl: '/login' },
};

/** @type {Record<string, Array<{account: keyof typeof ACCOUNTS, path: string, file: string, before?: (page: import('playwright').Page) => Promise<void>, fullPage?: boolean}>>} */
const TARGETS = {
  '00': [
    { account: 'bendahara', path: '/admin/jenis-tagihan', file: '00-01-overview-jenis-tagihan.png' },
  ],
  '01': [
    { account: 'bendahara', path: '/admin/jenis-tagihan', file: '01-01-daftar-jenis-tagihan.png' },
    { account: 'bendahara', path: '/admin/jenis-tagihan/create', file: '01-02-form-identitas-mode.png' },
    { account: 'bendahara', path: '/admin/jenis-tagihan/create', file: '01-03-pilihan-tipe-penjadwalan.png' },
    { account: 'bendahara', path: '/admin/jenis-tagihan/create', file: '01-04-priority-score-autodebit.png' },
  ],
  '02': [
    { account: 'bendahara', path: '/admin/jenis-tagihan/create', file: '02-01-target-sasaran-kriteria.png' },
    { account: 'bendahara', path: '/admin/jenis-tagihan/create', file: '02-02-tarif-berdimensi-grup.png' },
    { account: 'bendahara', path: '/admin/jenis-tagihan/create', file: '02-03-hitung-siswa-preview.png' },
  ],
  '03': [
    {
      account: 'bendahara',
      path: '/admin/jenis-tagihan/create',
      file: '03-01-modal-kategori-keringanan.png',
      before: async (page) => {
        const btn = page.locator('button:has-text("Buat Kategori Baru")').first();
        if (await btn.isVisible()) {
          await btn.click();
          await page.waitForTimeout(400);
        }
      },
    },
    { account: 'bendahara', path: '/admin/jenis-tagihan/create', file: '03-02-widget-assignment-siswa.png' },
    {
      account: 'bendahara',
      path: '/admin/siswa',
      file: '03-03-keringanan-per-siswa.png',
      before: async (page) => {
        // Find first student row's Keringanan button or direct link
        const keringananLink = page.locator('a[href*="/keringanan"]').first();
        if (await keringananLink.isVisible()) {
          await keringananLink.click();
          await page.waitForLoadState('networkidle');
        }
      },
    },
  ],
  '04': [
    { account: 'bendahara', path: '/admin/jenis-tagihan', file: '04-01-monitoring-penerima.png',
      before: async (page) => {
        const monitoringLink = page.locator('a[href*="/monitoring"]').first();
        if (await monitoringLink.isVisible()) {
          await monitoringLink.click();
          await page.waitForLoadState('networkidle');
        }
      }
    },
    {
      account: 'bendahara',
      path: '/admin/jenis-tagihan',
      file: '04-02-monitoring-tunggakan.png',
      before: async (page) => {
        const monitoringLink = page.locator('a[href*="/monitoring"]').first();
        if (await monitoringLink.isVisible()) {
          await monitoringLink.click();
          await page.waitForLoadState('networkidle');
          const tunggakanTab = page.locator('a:has-text("Daftar Tunggakan"), button:has-text("Daftar Tunggakan")').first();
          if (await tunggakanTab.isVisible()) {
            await tunggakanTab.click();
            await page.waitForTimeout(300);
          }
        }
      },
    },
    {
      account: 'bendahara',
      path: '/admin/jenis-tagihan',
      file: '04-03-modal-batalkan-tagihan.png',
      before: async (page) => {
        const monitoringLink = page.locator('a[href*="/monitoring"]').first();
        if (await monitoringLink.isVisible()) {
          await monitoringLink.click();
          await page.waitForLoadState('networkidle');
          const batalkanBtn = page.locator('button:has-text("Batalkan")').first();
          if (await batalkanBtn.isVisible()) {
            await batalkanBtn.click();
            await page.waitForTimeout(300);
          }
        }
      },
    },
  ],
  '05': [
    { account: 'bendahara', path: '/admin/tagihan/perlu-ditinjau', file: '05-01-daftar-perlu-ditinjau.png' },
    {
      account: 'bendahara',
      path: '/admin/tagihan/perlu-ditinjau',
      file: '05-02-popover-koreksi-nominal.png',
      before: async (page) => {
        const koreksiBtn = page.locator('button:has-text("Koreksi Nominal")').first();
        if (await koreksiBtn.isVisible()) {
          await koreksiBtn.click();
          await page.waitForTimeout(400);
        }
      },
    },
  ],
  '06': [
    { account: 'bendahara', path: '/admin/virtual-account', file: '06-01-daftar-virtual-account.png' },
    { account: 'bendahara', path: '/admin/manual-payment', file: '06-02-verifikasi-transfer-manual.png' },
  ],
  '07': [
    { account: 'ortu', path: '/keuangan', file: '07-01-dashboard-ortu-dompet.png' },
    {
      account: 'ortu',
      path: '/keuangan',
      file: '07-02-modal-topup-va.png',
      before: async (page) => {
        const topupBtn = page.locator('button:has-text("Top Up"), button:has-text("Isi Saldo")').first();
        if (await topupBtn.isVisible()) {
          await topupBtn.click();
          await page.waitForTimeout(400);
        }
      },
    },
    {
      account: 'ortu',
      path: '/keuangan',
      file: '07-03-halaman-checkout.png',
      before: async (page) => {
        const checkbox = page.locator('input[type="checkbox"][value]:not([disabled])').first();
        if (await checkbox.isVisible()) {
          await checkbox.check();
          await page.waitForTimeout(200);
          const bayarBtn = page.locator('button:has-text("Bayar Terpilih")').first();
          if (await bayarBtn.isVisible()) {
            await bayarBtn.click();
            await page.waitForLoadState('networkidle');
          }
        }
      },
    },
  ],
  '08': [
    { account: 'ortu', path: '/keuangan/tagihan', file: '08-01-daftar-tagihan-ortu.png' },
    {
      account: 'ortu',
      path: '/keuangan/tagihan',
      file: '08-02-detail-breakdown-tagihan.png',
      before: async (page) => {
        const detailLink = page.locator('a[href*="/keuangan/tagihan/"]').first();
        if (await detailLink.isVisible()) {
          await detailLink.click();
          await page.waitForLoadState('networkidle');
        }
      },
    },
    { account: 'ortu', path: '/keuangan/riwayat', file: '08-03-riwayat-pembayaran-ortu.png' },
  ],
  lampiran: [
    { account: 'bendahara', path: '/dashboard', file: 'lampiran-01-topbar-bendahara.png' },
    { account: 'yayasan', path: '/dashboard?switch_lembaga=1', file: 'lampiran-02-topbar-yayasan.png' },
    { account: 'ortu', path: '/keuangan', file: 'lampiran-03-topbar-ortu.png' },
  ],
};

async function login(page, accountKey) {
  const account = ACCOUNTS[accountKey];
  await page.goto(`${BASE_URL}${account.loginUrl}`);
  await page.fill('input[name="email"]', account.email);
  await page.fill('input[name="password"]', account.password);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
}

async function capture(page, target) {
  await page.goto(`${BASE_URL}${target.path}`);
  await page.waitForLoadState('networkidle');
  if (target.before) {
    await target.before(page);
  }
  await page.waitForTimeout(300);
  const outPath = path.join(OUTPUT_DIR, target.file);
  await page.screenshot({ path: outPath, fullPage: target.fullPage ?? false });
  console.log(`captured ${target.file}`);
}

async function run() {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });

  const babArg = process.argv.find((arg) => arg.startsWith('--bab='));
  const requestedBab = babArg ? babArg.split('=')[1] : null;
  if (requestedBab && !(requestedBab in TARGETS)) {
    console.error(`Unknown --bab=${requestedBab}. Valid: ${Object.keys(TARGETS).join(', ')}`);
    process.exit(1);
  }
  const babsToRun = requestedBab ? [requestedBab] : Object.keys(TARGETS);

  const browser = await chromium.launch();

  let context = null;
  let page = null;
  let currentAccount = null;
  for (const babKey of babsToRun) {
    for (const target of TARGETS[babKey] ?? []) {
      if (target.account !== currentAccount) {
        if (context) {
          await context.close();
        }
        context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        page = await context.newPage();
        await login(page, target.account);
        currentAccount = target.account;
      }
      await capture(page, target);
    }
  }

  if (context) {
    await context.close();
  }
  await browser.close();
}

run().catch((err) => {
  console.error(err);
  process.exit(1);
});
