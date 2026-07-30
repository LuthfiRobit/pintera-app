// scripts/manual-book-screenshots.mjs
// Dev-only tool: generates screenshots for docs/manual-book/akademik/*.md.
// Usage: node scripts/manual-book-screenshots.mjs [--bab=<00|01|02|03|04|05|06|lampiran>]

import { chromium } from 'playwright';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUTPUT_DIR = path.join(__dirname, '..', 'docs', 'manual-book', 'akademik', 'images');
const BASE_URL = process.env.MANUAL_BOOK_BASE_URL || 'http://localhost';

const ACCOUNTS = {
  yayasan: { email: 'superadmin@sistem.test', password: 'password' },
  akademik: { email: 'akademik@sistem.test', password: 'password' },
  guru: { email: 'guru@sistem.test', password: 'password' },
};

/** @type {Record<string, Array<{account: keyof typeof ACCOUNTS, path: string, file: string, before?: (page: import('playwright').Page) => Promise<void>, fullPage?: boolean}>>} */
const TARGETS = {
  '00': [
    { account: 'yayasan', path: '/admin/lembaga', file: '00-01-daftar-lembaga.png' },
    { account: 'yayasan', path: '/admin/lembaga/create', file: '00-02-form-lembaga.png' },
    { account: 'yayasan', path: '/admin/users', file: '00-03-daftar-user.png' },
    { account: 'yayasan', path: '/admin/users/create', file: '00-04-form-user-role.png' },
  ],
  '01': [
    { account: 'akademik', path: '/admin/tahun-ajaran', file: '01-01-tahun-ajaran-semester.png' },
    { account: 'akademik', path: '/admin/mata-pelajaran', file: '01-02-daftar-mapel.png' },
    { account: 'akademik', path: '/admin/mata-pelajaran/create', file: '01-03-form-mapel.png' },
    { account: 'akademik', path: '/admin/kelas', file: '01-04-daftar-kelas.png' },
    { account: 'akademik', path: '/admin/kelas/create', file: '01-05-form-kelas.png' },
    { account: 'akademik', path: '/admin/siswa', file: '01-06-daftar-siswa.png' },
    { account: 'akademik', path: '/admin/pengaturan/akademik', file: '01-07-pengaturan-akademik.png' },
  ],
  '02': [],
  '03': [],
  '04': [],
  '05': [],
  '06': [],
  lampiran: [],
};

async function login(page, accountKey) {
  const account = ACCOUNTS[accountKey];
  await page.goto(`${BASE_URL}/login`);
  await page.fill('input[name="email"]', account.email);
  await page.fill('input[name="password"]', account.password);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
}

async function capture(page, target) {
  await page.goto(`${BASE_URL}${target.path}`);
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
        // Fresh context per account switch: reusing one page across accounts hits Laravel's
        // guest-only /login redirect once already authenticated, so login() would hang
        // waiting for a form that never renders.
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
