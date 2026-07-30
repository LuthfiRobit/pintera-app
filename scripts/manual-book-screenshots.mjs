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
  // NOTE: guru@sistem.test ("Guru (Contoh)") has no linked Guru profile row (Guru.user_id
  // never points to it) in the seeded data, so /guru/sesi is permanently empty for it
  // regardless of day. Using the real seeded teacher (Budi Santoso, wali kelas VII-A,
  // Guru id 1) instead so this chapter's screenshots show real presensi/jurnal data.
  guru: { email: 'budi.santoso@permata.sch.id', password: 'password' },
};

/** @type {Record<string, Array<{account: keyof typeof ACCOUNTS, path: string, file: string, before?: (page: import('playwright').Page) => Promise<void>, fullPage?: boolean}>>} */
const TARGETS = {
  '00': [
    { account: 'yayasan', path: '/admin/lembaga', file: '00-01-daftar-lembaga.png' },
    { account: 'yayasan', path: '/admin/lembaga/create', file: '00-02-form-lembaga.png' },
    { account: 'yayasan', path: '/admin/users', file: '00-03-daftar-user.png' },
    { account: 'yayasan', path: '/admin/users/create', file: '00-04-form-user-role.png' },
    // Guru accounts are NOT created via /admin/users (that would leave a User with role
    // "guru" but no linked Guru profile — a real bug this project hit before). They go
    // through the dedicated Data Guru module instead, which creates the User + Guru profile
    // together (NIP becomes the initial password).
    { account: 'yayasan', path: '/admin/guru', file: '00-05-daftar-guru.png' },
    { account: 'yayasan', path: '/admin/guru/create', file: '00-06-form-guru.png' },
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
  '02': [
    { account: 'akademik', path: '/admin/pola-jam', file: '02-01-daftar-pola-jam.png' },
    { account: 'akademik', path: '/admin/pola-jam/create', file: '02-02-form-pola-jam.png' },
    { account: 'akademik', path: '/admin/jadwal-pelajaran', file: '02-03-daftar-jadwal.png' },
    // kelas_id=1 -> VII-A, semester_id=3 -> Ganjil 2026/2027, both lembaga_id=1 (SMP Permata),
    // matching the akademik@sistem.test account used throughout this chapter.
    { account: 'akademik', path: '/admin/jadwal-pelajaran/create?kelas_id=1&semester_id=3', file: '02-04-form-jadwal.png' },
  ],
  '03': [
    { account: 'guru', path: '/guru/sesi', file: '03-01-daftar-sesi.png' },
    {
      account: 'guru',
      path: '/guru/sesi',
      file: '03-02-detail-sesi.png',
      before: async (page) => {
        const firstRow = page.locator('a[href*="/guru/sesi/"]').first();
        await firstRow.click();
        await page.waitForLoadState('networkidle');
      },
    },
  ],
  '04': [
    { account: 'akademik', path: '/admin/komponen-penilaian', file: '04-01-daftar-komponen.png' },
    { account: 'akademik', path: '/admin/komponen-penilaian/create', file: '04-02-form-komponen.png' },
    { account: 'guru', path: '/guru/komponen-penilaian', file: '04-03-guru-daftar-komponen.png' },
    { account: 'guru', path: '/guru/komponen-penilaian/create', file: '04-04-guru-form-komponen.png' },
    { account: 'guru', path: '/guru/asesmen', file: '04-05-daftar-asesmen.png' },
    { account: 'guru', path: '/guru/asesmen/create', file: '04-06-form-asesmen.png' },
  ],
  '05': [
    { account: 'akademik', path: '/admin/rapor', file: '05-01-filter-rekap-rapor.png' },
    // The filter selects (x-ref="tahunAjaranSelect" etc., no name attribute) are upgraded
    // to Tom Select widgets on page load and drive an AJAX fragment reload — rather than
    // fight that JS widget with selectOption()/clicks, we rely on the fact that
    // RaporController@index already accepts tahun_ajaran_id/kelas_id/semester_id as GET
    // query params and full-page-renders the same result view for them (see
    // app/Http/Controllers/Admin/RaporController.php lines 28-71). tahun_ajaran_id=2 ->
    // "2026/2027" (status_aktif, lembaga_id=1), kelas_id=1 -> VII-A, semester_id=3 ->
    // "Ganjil" — the same tenant/kelas/semester combo Bab 4's Asesmen+Nilai data was
    // created against, so this reproduces exactly what a real user's cascading selection
    // would land on, with real non-empty data, without depending on Tom Select internals.
    {
      account: 'akademik',
      path: '/admin/rapor?tahun_ajaran_id=2&kelas_id=1&semester_id=3',
      file: '05-02-hasil-rekap-rapor.png',
    },
  ],
  '06': [
    { account: 'akademik', path: '/admin/kenaikan-kelas', file: '06-01-kenaikan-kelas-pilih-tahun.png' },
    // tahun_ajaran_id=2 -> 2026/2027 (source, lembaga_id=1), tahun_ajaran_tujuan_id=5 -> 2027/2028
    // (target, seeded by AcademicDummySeeder alongside Kelas VIII-A/VIII-B for exactly this screenshot).
    { account: 'akademik', path: '/admin/kenaikan-kelas?tahun_ajaran_id=2&tahun_ajaran_tujuan_id=5', file: '06-02-kenaikan-kelas-pemetaan.png' },
  ],
  lampiran: [
    // As a yayasan-scoped account, /admin/pengaturan/akademik requires an active Lembaga
    // selected in session first, or ResolveTenant redirects to the dashboard. Passing
    // switch_lembaga=1 (SMP Permata, lembaga_id=1, the same lembaga used throughout every
    // other chapter) as a query param sets session('active_lembaga_id') via the global
    // ResolveTenant middleware before the page renders, so no separate switcher-UI
    // click-through is needed.
    { account: 'yayasan', path: '/admin/pengaturan/akademik?switch_lembaga=1', file: 'lampiran-01-pengaturan-akademik-nasional.png' },
  ],
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
