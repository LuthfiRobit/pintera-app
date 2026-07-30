# Manual Book Modul Akademik Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce a user-facing manual book (Markdown, one file per chapter, with real screenshots) covering the academic module end-to-end — from Lembaga setup by the yayasan admin to Kenaikan Kelas — using the "Yayasan Permata" dev-seed scenario.

**Architecture:** A small reusable Playwright script (`scripts/manual-book-screenshots.mjs`) logs in as the relevant seeded account and captures screenshots into `docs/manual-book/akademik/images/`, driven by a per-chapter config table inside the script. Each chapter is authored as its own Markdown file that embeds the screenshots and is written/verified bab-by-bab, not all at once.

**Tech Stack:** Laravel 11 (existing app), Playwright (new dev dependency) for screenshot automation, plain Markdown for the manual book itself.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-30-manual-book-akademik-design.md` — follow its file structure, chapter template, and scenario choices exactly (SMP Permata only, not SMA Permata).
- Seeded accounts (all password `password`, from `EssentialUserSeeder`, already on `main`): `superadmin@sistem.test` (yayasan_super_admin), `akademik@sistem.test` (admin_akademik), `guru@sistem.test` (guru).
- Base URL for the running app must be confirmed by whoever executes the script — `.env` currently has `APP_URL=http://localhost`, but the real dev host may differ (e.g. a Laragon virtual host like `http://pintera-app.test`). The script reads it from `MANUAL_BOOK_BASE_URL`, falling back to `http://localhost`.
- Screenshot viewport is fixed at 1440×900 for every capture (per spec).
- The script is a dev-only tool: not wired into `composer.json`/CI/test suite.
- Every chapter file follows the template from the spec: **Untuk siapa**, **Prasyarat**, **Langkah-langkah** (numbered, screenshots embedded inline), **Kesalahan umum**.
- Commit after each task (chapter or infra piece), per this project's established frequent-commit convention.

---

## File Structure

```
scripts/manual-book-screenshots.mjs      (new — Playwright capture script)
package.json                              (modified — add playwright devDependency)
docs/manual-book/akademik/
  00-setup-lembaga.md                     (new)
  01-data-master.md                       (new)
  02-penjadwalan.md                       (new)
  03-presensi-jurnal.md                   (new)
  04-asesmen-nilai.md                     (new)
  05-rekap-rapor.md                       (new)
  06-kenaikan-kelas.md                    (new)
  lampiran-lintas-lembaga.md              (new)
  images/                                 (new — PNG output from the script)
```

Confirmed route names used below (verified against `routes/admin.php` and `routes/web.php` on 2026-07-30 — re-check if this plan is executed much later and the routes may have moved):

| Area | Route(s) |
|---|---|
| Login | `POST /login` (fields `name="email"`, `name="password"`, submit `button[type=submit]`) |
| Lembaga | `/admin/lembaga`, `/admin/lembaga/create` |
| Users (role assignment) | `/admin/users`, `/admin/users/create` |
| Tahun Ajaran + Semester (inline on same page) | `/admin/tahun-ajaran`, `/admin/tahun-ajaran/create` |
| Mata Pelajaran | `/admin/mata-pelajaran`, `/admin/mata-pelajaran/create` |
| Kelas | `/admin/kelas`, `/admin/kelas/create` |
| Siswa | `/admin/siswa`, `/admin/siswa/create`, `/admin/siswa-import`, `/admin/siswa-spmb-daftar` |
| Pengaturan Akademik (Hari Aktif + Kalender Akademik) | `/admin/pengaturan/akademik` |
| Pola Jam (+ Jam Pelajaran nested in edit) | `/admin/pola-jam`, `/admin/pola-jam/create`, `/admin/pola-jam/{id}/edit` |
| Jadwal Pelajaran | `/admin/jadwal-pelajaran`, `/admin/jadwal-pelajaran/create` |
| Komponen Penilaian | `/admin/komponen-penilaian`, `/admin/komponen-penilaian/create` |
| Rekap Rapor | `/admin/rapor`, `/admin/rapor/cetak` |
| Kenaikan Kelas | `/admin/kenaikan-kelas` |
| Guru — Ruang Guru (Presensi & Jurnal) | `/guru/sesi`, `/guru/sesi/{id}` |
| Guru — Asesmen | `/guru/asesmen`, `/guru/asesmen/create`, `/guru/asesmen/{id}` |

---

### Task 1: Playwright infrastructure + capture script skeleton

**Files:**
- Modify: `package.json`
- Create: `scripts/manual-book-screenshots.mjs`

**Interfaces:**
- Produces: `TARGETS` object (keyed by chapter id: `'00'`, `'01'`, `'02'`, `'03'`, `'04'`, `'05'`, `'06'`, `'lampiran'`), each value an array of target objects `{ account, path, file, before?, fullPage? }`. Later tasks populate these arrays — they do not change the shape.
- Produces: CLI usage `node scripts/manual-book-screenshots.mjs --bab=<id>` (omit `--bab` to run all chapters).

- [ ] **Step 1: Install Playwright as a dev dependency**

Run: `npm install -D playwright`
Expected: `package.json` gains `"playwright"` under `devDependencies`, `package-lock.json` updates.

- [ ] **Step 2: Install the Chromium browser binary**

Run: `npx playwright install chromium`
Expected: download completes, prints an install-location summary (no error).

- [ ] **Step 3: Create the capture script**

Create `scripts/manual-book-screenshots.mjs`:

```js
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
  '00': [],
  '01': [],
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
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  let currentAccount = null;
  for (const babKey of babsToRun) {
    for (const target of TARGETS[babKey] ?? []) {
      if (target.account !== currentAccount) {
        await login(page, target.account);
        currentAccount = target.account;
      }
      await capture(page, target);
    }
  }

  await browser.close();
}

run().catch((err) => {
  console.error(err);
  process.exit(1);
});
```

- [ ] **Step 4: Verify the script runs with no targets configured yet**

Run: `node scripts/manual-book-screenshots.mjs`
Expected: exits with code 0, no output (no targets registered yet — this only proves the script loads Playwright and the CLI parsing works, not that login succeeds).

- [ ] **Step 5: Verify the unknown-bab guard**

Run: `node scripts/manual-book-screenshots.mjs --bab=99`
Expected: prints `Unknown --bab=99. Valid: 00, 01, 02, 03, 04, 05, 06, lampiran` and exits with code 1.

- [ ] **Step 6: Create the output directory placeholder and commit**

```bash
git add package.json package-lock.json scripts/manual-book-screenshots.mjs
git commit -m "chore: add Playwright screenshot script for the academic manual book"
```

---

### Task 2: Bab 00 — Setup Lembaga (yayasan admin)

**Files:**
- Modify: `scripts/manual-book-screenshots.mjs` (populate `TARGETS['00']`)
- Create: `docs/manual-book/akademik/00-setup-lembaga.md`

**Interfaces:**
- Consumes: `TARGETS` object shape from Task 1.
- Produces: `docs/manual-book/akademik/images/00-01.png` .. `00-04.png`.

- [ ] **Step 1: Add screenshot targets for this chapter**

In `scripts/manual-book-screenshots.mjs`, replace `'00': [],` with:

```js
  '00': [
    { account: 'yayasan', path: '/admin/lembaga', file: '00-01-daftar-lembaga.png' },
    { account: 'yayasan', path: '/admin/lembaga/create', file: '00-02-form-lembaga.png' },
    { account: 'yayasan', path: '/admin/users', file: '00-03-daftar-user.png' },
    { account: 'yayasan', path: '/admin/users/create', file: '00-04-form-user-role.png' },
  ],
```

- [ ] **Step 2: Ensure the dev DB has the Yayasan Permata scenario**

Run: `php artisan migrate:fresh --seed`
Expected: seeders complete without error (this is the same command already verified in the prior session — see `git log` for commit `adec145`).

- [ ] **Step 3: Run the app and the script for this chapter**

Run (in one terminal, if not already running): `php artisan serve` (or confirm Laragon's vhost is up), then:
Run: `MANUAL_BOOK_BASE_URL=http://localhost:8000 node scripts/manual-book-screenshots.mjs --bab=00` (adjust the URL to whatever the running app actually resolves to)
Expected: 4 lines of `captured 00-...png`, no errors.

- [ ] **Step 4: Verify the images exist**

Run: `ls docs/manual-book/akademik/images/00-*.png`
Expected: 4 files listed.

- [ ] **Step 5: Write the chapter markdown**

Create `docs/manual-book/akademik/00-setup-lembaga.md`:

```markdown
# Bab 0 — Setup Lembaga

## Untuk siapa

Admin Yayasan (`yayasan_super_admin`). Ini adalah langkah paling awal sebelum modul
akademik bisa dipakai sama sekali — semua bab berikutnya butuh minimal satu Lembaga aktif.

## Prasyarat

- Akun dengan role `yayasan_super_admin` sudah ada (dibuat oleh Super Admin sistem saat
  instalasi awal).
- Data Yayasan sudah diisi.

## Langkah-langkah

1. Login sebagai admin yayasan, lalu buka menu **Lembaga**.

   ![Daftar Lembaga](images/00-01-daftar-lembaga.png)

2. Klik **Tambah Lembaga**. Isi data identitas lembaga: nama, jenjang (`bentuk_pendidikan`
   — TK/SD/SMP/SMA/SMK/dst), NPSN, NSS, alamat, kepala sekolah, dan data rekening.

   ![Form tambah Lembaga](images/00-02-form-lembaga.png)

3. Setelah Lembaga dibuat, buka menu **Pengguna** untuk membuat akun staf lembaga tersebut
   (Kepala Sekolah, Admin Akademik, Admin Administrasi, Admin Keuangan, Guru).

   ![Daftar Pengguna](images/00-03-daftar-user.png)

4. Saat membuat pengguna baru, pilih Lembaga yang bersangkutan dan pilih role yang sesuai
   perannya. Untuk yang akan mengelola data akademik (Kelas, Siswa, Jadwal, Presensi,
   Asesmen), pilih role **Admin Akademik**.

   ![Form tambah pengguna dengan pilihan role](images/00-04-form-user-role.png)

5. Jika yayasan menaungi lebih dari satu Lembaga, gunakan **pemilih lembaga (switcher)** di
   navbar untuk berpindah konteks — hampir semua halaman admin lembaga-scoped hanya
   menampilkan data lembaga yang sedang aktif dipilih.

## Kesalahan umum

- **Lupa memilih Lembaga aktif lewat switcher.** Sebagai admin yayasan, beberapa halaman
  pengaturan (mis. Pengaturan Akademik) mengharuskan satu Lembaga aktif dipilih dulu —
  kalau belum, halaman akan mengarahkan balik ke dashboard dengan pesan error, bukan
  crash.
- **Membuat pengguna tanpa memilih Lembaga.** Role seperti Admin Akademik dan Guru wajib
  terikat ke satu Lembaga (`lembaga_id`) — tanpa itu mereka tidak akan melihat data apa pun
  saat login.
```

- [ ] **Step 6: Read the chapter back and cross-check against the running app**

Open each screenshot in `docs/manual-book/akademik/images/00-*.png` and confirm it actually
shows the described screen (correct URL, correct role logged in, no error page captured by
accident). Fix the script config or re-run Step 3 if any screenshot is wrong.

- [ ] **Step 7: Commit**

```bash
git add scripts/manual-book-screenshots.mjs docs/manual-book/akademik/00-setup-lembaga.md docs/manual-book/akademik/images/00-*.png
git commit -m "docs: add Bab 0 (Setup Lembaga) to the academic manual book"
```

---

### Task 3: Bab 01 — Data Master (admin_akademik)

**Files:**
- Modify: `scripts/manual-book-screenshots.mjs` (populate `TARGETS['01']`)
- Create: `docs/manual-book/akademik/01-data-master.md`

**Interfaces:**
- Consumes: same `TARGETS`/`capture` contract as Task 2.
- Produces: `docs/manual-book/akademik/images/01-01.png` .. `01-07.png`.

- [ ] **Step 1: Add screenshot targets for this chapter**

Replace `'01': [],` with:

```js
  '01': [
    { account: 'akademik', path: '/admin/tahun-ajaran', file: '01-01-tahun-ajaran-semester.png' },
    { account: 'akademik', path: '/admin/mata-pelajaran', file: '01-02-daftar-mapel.png' },
    { account: 'akademik', path: '/admin/mata-pelajaran/create', file: '01-03-form-mapel.png' },
    { account: 'akademik', path: '/admin/kelas', file: '01-04-daftar-kelas.png' },
    { account: 'akademik', path: '/admin/kelas/create', file: '01-05-form-kelas.png' },
    { account: 'akademik', path: '/admin/siswa', file: '01-06-daftar-siswa.png' },
    { account: 'akademik', path: '/admin/pengaturan/akademik', file: '01-07-pengaturan-akademik.png' },
  ],
```

- [ ] **Step 2: Run the script for this chapter**

Run: `MANUAL_BOOK_BASE_URL=<url-lokal-anda> node scripts/manual-book-screenshots.mjs --bab=01`
Expected: 7 lines of `captured 01-...png`, no errors.

- [ ] **Step 3: Verify the images exist**

Run: `ls docs/manual-book/akademik/images/01-*.png`
Expected: 7 files listed.

- [ ] **Step 4: Write the chapter markdown**

Create `docs/manual-book/akademik/01-data-master.md`:

```markdown
# Bab 1 — Data Master Akademik

## Untuk siapa

Admin Akademik (`admin_akademik`), per Lembaga.

## Prasyarat

- [Bab 0 — Setup Lembaga](00-setup-lembaga.md) sudah selesai: Lembaga dan akun Admin
  Akademik sudah ada.

## Langkah-langkah

### 1. Tahun Ajaran & Semester

Kelola dari satu halaman yang sama — buat Tahun Ajaran dulu (mis. "2026/2027"), lalu
tambahkan Semester Ganjil/Genap di dalamnya. Hanya satu Tahun Ajaran dan satu Semester
yang boleh berstatus aktif dalam satu waktu; mengaktifkan yang baru otomatis
menonaktifkan yang lama.

![Halaman Tahun Ajaran & Semester](images/01-01-tahun-ajaran-semester.png)

### 2. Mata Pelajaran

Mata Pelajaran bersifat per-Lembaga dan **tidak terikat tahun ajaran** — sekali dibuat,
otomatis tersedia untuk tahun ajaran berikutnya juga, tidak perlu dibuat ulang tiap tahun.

![Daftar Mata Pelajaran](images/01-02-daftar-mapel.png)
![Form tambah Mata Pelajaran](images/01-03-form-mapel.png)

### 3. Kelas

Sama seperti Mata Pelajaran, Kelas juga per-Lembaga dan persisten lintas tahun ajaran.
Saat membuat Kelas, tentukan tingkat, wali kelas, dan Pola Jam yang dipakai (Pola Jam
sendiri dibuat di [Bab 2 — Penjadwalan](02-penjadwalan.md) — kalau belum ada, field ini
boleh dikosongkan dulu dan diisi belakangan).

![Daftar Kelas](images/01-04-daftar-kelas.png)
![Form tambah Kelas](images/01-05-form-kelas.png)

### 4. Siswa

Ada 3 cara siswa masuk ke sistem: input manual satu-satu, import massal via Excel, atau
konversi otomatis dari pendaftar SPMB yang sudah diterima dan melakukan daftar ulang.
Halaman **Siswa** menampilkan hasil dari ketiganya dalam satu daftar yang sama.

![Daftar Siswa](images/01-06-daftar-siswa.png)

### 5. Kalender Akademik & Hari Libur

Di halaman **Pengaturan Akademik**, atur hari aktif sekolah dalam seminggu (mis. Senin-Sabtu
aktif, Minggu libur) dan tambahkan hari libur/rentang libur khusus (libur nasional, libur
semester, dll). Ini menentukan hari mana yang dianggap "hari sekolah" oleh fitur Jadwal,
Presensi, dan Kenaikan Kelas.

![Halaman Pengaturan Akademik](images/01-07-pengaturan-akademik.png)

## Kesalahan umum

- **Membuat Kelas baru tiap tahun ajaran seperti membuat Mata Pelajaran baru.** Tidak
  perlu — Kelas dan Mata Pelajaran memang didesain persisten. Yang berganti tiap tahun
  ajaran adalah Tahun Ajaran itu sendiri dan isi Jadwal Pelajarannya (lihat Bab 2), bukan
  daftar Kelas/Mapel-nya.
- **Menambah siswa manual padahal sudah pernah diimpor/dikonversi dari SPMB dengan NIS
  yang sama.** Sistem menolak NIS/NISN duplikat dalam satu Lembaga — kalau muncul error
  duplikat saat input manual, cek dulu apakah siswa itu sudah masuk lewat jalur SPMB atau
  import Excel.
```

- [ ] **Step 5: Read the chapter back and cross-check against the running app**

Same verification approach as Task 2 Step 6 — open each `01-*.png`, confirm it matches the
described screen and role.

- [ ] **Step 6: Commit**

```bash
git add scripts/manual-book-screenshots.mjs docs/manual-book/akademik/01-data-master.md docs/manual-book/akademik/images/01-*.png
git commit -m "docs: add Bab 1 (Data Master) to the academic manual book"
```

---

### Task 4: Bab 02 — Penjadwalan (admin_akademik)

**Files:**
- Modify: `scripts/manual-book-screenshots.mjs` (populate `TARGETS['02']`)
- Create: `docs/manual-book/akademik/02-penjadwalan.md`

**Interfaces:**
- Consumes: same `TARGETS`/`capture` contract as Task 2.
- Produces: `docs/manual-book/akademik/images/02-01.png` .. `02-04.png`.

- [ ] **Step 1: Add screenshot targets for this chapter**

Replace `'02': [],` with:

```js
  '02': [
    { account: 'akademik', path: '/admin/pola-jam', file: '02-01-daftar-pola-jam.png' },
    { account: 'akademik', path: '/admin/pola-jam/create', file: '02-02-form-pola-jam.png' },
    { account: 'akademik', path: '/admin/jadwal-pelajaran', file: '02-03-daftar-jadwal.png' },
    { account: 'akademik', path: '/admin/jadwal-pelajaran/create', file: '02-04-form-jadwal.png' },
  ],
```

- [ ] **Step 2: Run the script for this chapter**

Run: `MANUAL_BOOK_BASE_URL=<url-lokal-anda> node scripts/manual-book-screenshots.mjs --bab=02`
Expected: 4 lines of `captured 02-...png`, no errors.

- [ ] **Step 3: Verify the images exist**

Run: `ls docs/manual-book/akademik/images/02-*.png`
Expected: 4 files listed.

- [ ] **Step 4: Write the chapter markdown**

Create `docs/manual-book/akademik/02-penjadwalan.md`:

```markdown
# Bab 2 — Penjadwalan

## Untuk siapa

Admin Akademik (`admin_akademik`), per Lembaga.

## Prasyarat

- [Bab 1 — Data Master](01-data-master.md) sudah selesai: Kelas, Mata Pelajaran, Guru, dan
  Semester aktif sudah ada.

## Langkah-langkah

### 1. Pola Jam & Jam Pelajaran

Pola Jam adalah template slot waktu (mis. Jam ke-1 07:00-07:40, Istirahat 08:20-08:50, dst)
yang bisa dipakai ulang oleh banyak Kelas. Buat Pola Jam dulu, lalu di halaman edit-nya
tambahkan Jam Pelajaran per hari (bisa dipilih beberapa hari sekaligus dalam satu submit).
Tandai slot istirahat/upacara sebagai "bukan jam pelajaran" supaya tidak muncul saat memilih
slot untuk Jadwal Pelajaran.

![Daftar Pola Jam](images/02-01-daftar-pola-jam.png)
![Form tambah Pola Jam](images/02-02-form-pola-jam.png)

### 2. Kaitkan Pola Jam ke Kelas

Setiap Kelas harus terhubung ke satu Pola Jam (diisi saat membuat/mengedit Kelas di Bab 1,
atau lewat aksi "Terapkan ke Kelas" di halaman Pola Jam).

### 3. Jadwal Pelajaran

Jadwal Pelajaran menghubungkan satu Kelas + satu slot Jam Pelajaran + satu Mata Pelajaran +
satu Guru, untuk Semester yang sedang aktif. Diisi per slot per kelas.

![Daftar Jadwal Pelajaran](images/02-03-daftar-jadwal.png)
![Form tambah Jadwal Pelajaran](images/02-04-form-jadwal.png)

## Kesalahan umum

- **Menghapus Pola Jam atau Jam Pelajaran yang masih dipakai.** Sistem akan menolak
  (menampilkan pesan error yang jelas) kalau Pola Jam masih dipakai Kelas, atau Jam
  Pelajaran masih punya Jadwal Pelajaran — hapus/pindahkan pemakainya dulu.
- **Guru terjadwal bentrok di dua kelas pada jam yang sama.** Sistem memvalidasi ini saat
  menyimpan Jadwal — kalau muncul error bentrok jadwal, cek jadwal guru yang sama di kelas
  lain pada hari & jam yang sama.
- **Filter tanpa memilih Semester dulu.** Halaman Jadwal Pelajaran menampilkan data spesifik
  per Semester — pastikan Semester aktif sudah benar sebelum menambah jadwal, supaya tidak
  salah tempel ke semester yang keliru.
```

- [ ] **Step 5: Read the chapter back and cross-check against the running app**

Same verification approach as prior tasks.

- [ ] **Step 6: Commit**

```bash
git add scripts/manual-book-screenshots.mjs docs/manual-book/akademik/02-penjadwalan.md docs/manual-book/akademik/images/02-*.png
git commit -m "docs: add Bab 2 (Penjadwalan) to the academic manual book"
```

---

### Task 5: Bab 03 — Presensi & Jurnal (guru)

**Files:**
- Modify: `scripts/manual-book-screenshots.mjs` (populate `TARGETS['03']`)
- Create: `docs/manual-book/akademik/03-presensi-jurnal.md`

**Interfaces:**
- Consumes: same `TARGETS`/`capture` contract as Task 2.
- Produces: `docs/manual-book/akademik/images/03-01.png`, `03-02.png`.

- [ ] **Step 1: Add screenshot targets for this chapter**

Replace `'03': [],` with:

```js
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
```

- [ ] **Step 2: Run the script for this chapter**

Run: `MANUAL_BOOK_BASE_URL=<url-lokal-anda> node scripts/manual-book-screenshots.mjs --bab=03`
Expected: 2 lines of `captured 03-...png`, no errors. If `03-02` fails because no session
link is found, seed at least one `SesiPembelajaran` for today first (the existing
`AcademicDummySeeder` seeds one for "kemarin" only — see Step 2a below).

- [ ] **Step 2a (only if needed): confirm a session exists for "today"**

`SesiPembelajaran` rows are normally generated by `SesiPembelajaranGenerator` from the
Jadwal Pelajaran for the current day. If `/guru/sesi` shows an empty list when you run this
on a day the seeded schedule doesn't cover, check the app's own "generate sesi hari ini"
entry point (triggered from the `/guru/sesi` page itself per the existing UI) before
capturing — do not hand-create fixture rows outside the app's normal flow, since that would
produce a screenshot that doesn't match what a real guru would see.

- [ ] **Step 3: Verify the images exist**

Run: `ls docs/manual-book/akademik/images/03-*.png`
Expected: 2 files listed.

- [ ] **Step 4: Write the chapter markdown**

Create `docs/manual-book/akademik/03-presensi-jurnal.md`:

```markdown
# Bab 3 — Presensi & Jurnal

## Untuk siapa

Guru (role `guru`), dari halaman "Ruang Guru".

## Prasyarat

- [Bab 2 — Penjadwalan](02-penjadwalan.md) sudah selesai: guru yang bersangkutan sudah
  punya Jadwal Pelajaran untuk hari ini.

## Langkah-langkah

1. Login sebagai guru, buka **Ruang Guru → Sesi Pembelajaran**. Halaman ini menampilkan
   sesi mengajar hari ini, digabung otomatis dari slot Jam Pelajaran yang berurutan untuk
   guru dan mata pelajaran yang sama (mis. Jam ke-1 dan ke-2 Matematika di kelas yang sama
   digabung jadi satu sesi, bukan dua sesi terpisah).

   ![Daftar Sesi Pembelajaran](images/03-01-daftar-sesi.png)

2. Klik salah satu sesi untuk membuka detailnya. Di sini guru mengisi:
   - **Presensi** per siswa: Hadir / Sakit / Izin / Alfa, dengan keterangan opsional.
   - **Jurnal**: materi yang diajarkan pada sesi tersebut.

   ![Detail Sesi — presensi & jurnal](images/03-02-detail-sesi.png)

3. Simpan. Data ini menjadi dasar rekap kehadiran siswa dan riwayat pengajaran per kelas.

## Kesalahan umum

- **Sesi tidak muncul di daftar.** Sesi hanya muncul kalau memang ada Jadwal Pelajaran
  untuk guru tsb pada hari itu (lihat Bab 2) dan hari itu bukan hari libur (lihat Bab 1,
  Kalender Akademik). Kalau kosong padahal seharusnya ada jadwal, cek dulu dua hal itu.
- **Mengisi presensi dua kali untuk siswa yang sama pada sesi yang sama.** Sistem
  menimpa (bukan menduplikasi) entri presensi yang sudah ada untuk kombinasi sesi+siswa
  yang sama — aman untuk dibuka & diedit ulang kalau ada salah input.
```

- [ ] **Step 5: Read the chapter back and cross-check against the running app**

Same verification approach as prior tasks.

- [ ] **Step 6: Commit**

```bash
git add scripts/manual-book-screenshots.mjs docs/manual-book/akademik/03-presensi-jurnal.md docs/manual-book/akademik/images/03-*.png
git commit -m "docs: add Bab 3 (Presensi & Jurnal) to the academic manual book"
```

---

### Task 6: Bab 04 — Asesmen & Nilai (admin_akademik + guru)

**Files:**
- Modify: `scripts/manual-book-screenshots.mjs` (populate `TARGETS['04']`)
- Create: `docs/manual-book/akademik/04-asesmen-nilai.md`

**Interfaces:**
- Consumes: same `TARGETS`/`capture` contract as Task 2.
- Produces: `docs/manual-book/akademik/images/04-01.png` .. `04-04.png`.

- [ ] **Step 1: Add screenshot targets for this chapter**

Replace `'04': [],` with:

```js
  '04': [
    { account: 'akademik', path: '/admin/komponen-penilaian', file: '04-01-daftar-komponen.png' },
    { account: 'akademik', path: '/admin/komponen-penilaian/create', file: '04-02-form-komponen.png' },
    { account: 'guru', path: '/guru/asesmen', file: '04-03-daftar-asesmen.png' },
    { account: 'guru', path: '/guru/asesmen/create', file: '04-04-form-asesmen.png' },
  ],
```

- [ ] **Step 2: Run the script for this chapter**

Run: `MANUAL_BOOK_BASE_URL=<url-lokal-anda> node scripts/manual-book-screenshots.mjs --bab=04`
Expected: 4 lines of `captured 04-...png`, no errors.

- [ ] **Step 3: Verify the images exist**

Run: `ls docs/manual-book/akademik/images/04-*.png`
Expected: 4 files listed.

- [ ] **Step 4: Write the chapter markdown**

Create `docs/manual-book/akademik/04-asesmen-nilai.md`:

```markdown
# Bab 4 — Asesmen & Nilai

## Untuk siapa

Dua peran berurutan: **Admin Akademik** menyiapkan Komponen Penilaian (Tujuan Pembelajaran),
lalu **Guru** membuat Asesmen dan mengisi nilai berdasarkan komponen tersebut.

## Prasyarat

- [Bab 1 — Data Master](01-data-master.md): Mata Pelajaran dan Semester aktif sudah ada.
- [Bab 2 — Penjadwalan](02-penjadwalan.md): guru sudah punya Jadwal Pelajaran ke kelas yang
  akan dinilai.

## Langkah-langkah

### 1. Admin Akademik — Komponen Penilaian

Komponen Penilaian (Tujuan Pembelajaran/TP, mengikuti Kurikulum Merdeka) didefinisikan per
Mata Pelajaran + Semester. Ini adalah acuan penilaian yang nanti dipilih guru saat membuat
Asesmen.

![Daftar Komponen Penilaian](images/04-01-daftar-komponen.png)
![Form tambah Komponen Penilaian](images/04-02-form-komponen.png)

Komponen Penilaian yang sudah dipakai oleh Asesmen atau sudah ada nilainya terkunci
sebagian (Mata Pelajaran & Semester-nya tidak bisa diganti lagi) — untuk menjaga histori
nilai tetap konsisten.

### 2. Guru — Asesmen & Input Nilai

Dari **Ruang Guru → Asesmen**, guru membuat Asesmen baru: pilih Kelas, Mata Pelajaran,
jenis asesmen (Sumatif Lingkup Materi / Sumatif Akhir Semester / Sumatif Akhir Jenjang),
dan satu atau lebih Komponen Penilaian yang diukur.

![Daftar Asesmen](images/04-03-daftar-asesmen.png)
![Form tambah Asesmen](images/04-04-form-asesmen.png)

Setelah Asesmen dibuat, buka halaman detailnya untuk mengisi nilai tiap siswa di kelas
tersebut, per Komponen Penilaian yang dipilih, lengkap dengan catatan opsional.

## Kesalahan umum

- **Guru tidak melihat Komponen Penilaian yang diharapkan saat membuat Asesmen.** Cek
  apakah Admin Akademik sudah membuat Komponen Penilaian untuk kombinasi Mata Pelajaran +
  Semester yang sama dengan Asesmen yang sedang dibuat — Komponen Penilaian mata pelajaran
  atau semester lain tidak akan muncul.
- **Mengubah Mata Pelajaran/Semester pada Komponen Penilaian yang sudah dipakai.** Field ini
  sengaja dikunci begitu ada Asesmen atau nilai yang mereferensikannya — buat Komponen
  Penilaian baru kalau butuh acuan yang berbeda, jangan ubah yang lama.
```

- [ ] **Step 5: Read the chapter back and cross-check against the running app**

Same verification approach as prior tasks.

- [ ] **Step 6: Commit**

```bash
git add scripts/manual-book-screenshots.mjs docs/manual-book/akademik/04-asesmen-nilai.md docs/manual-book/akademik/images/04-*.png
git commit -m "docs: add Bab 4 (Asesmen & Nilai) to the academic manual book"
```

---

### Task 7: Bab 05 — Rekap Rapor (admin_akademik)

**Files:**
- Modify: `scripts/manual-book-screenshots.mjs` (populate `TARGETS['05']`)
- Create: `docs/manual-book/akademik/05-rekap-rapor.md`

**Interfaces:**
- Consumes: same `TARGETS`/`capture` contract as Task 2.
- Produces: `docs/manual-book/akademik/images/05-01.png`, `05-02.png`.

- [ ] **Step 1: Add screenshot targets for this chapter**

Replace `'05': [],` with:

```js
  '05': [
    { account: 'akademik', path: '/admin/rapor', file: '05-01-filter-rekap-rapor.png' },
    {
      account: 'akademik',
      path: '/admin/rapor',
      file: '05-02-hasil-rekap-rapor.png',
      before: async (page) => {
        await page.selectOption('select[name="tahun_ajaran_id"]', { index: 1 });
        await page.waitForLoadState('networkidle');
        await page.selectOption('select[name="kelas_id"]', { index: 1 });
        await page.waitForLoadState('networkidle');
        await page.selectOption('select[name="semester_id"]', { index: 1 });
        await page.waitForLoadState('networkidle');
      },
    },
  ],
```

- [ ] **Step 2: Run the script for this chapter**

Run: `MANUAL_BOOK_BASE_URL=<url-lokal-anda> node scripts/manual-book-screenshots.mjs --bab=05`
Expected: 2 lines of `captured 05-...png`, no errors. If the `select` names above don't
match the real filter form, inspect `/admin/rapor` in a real browser first (`view-source`
or devtools) and adjust the `before` selectors accordingly — don't guess blindly.

- [ ] **Step 3: Verify the images exist**

Run: `ls docs/manual-book/akademik/images/05-*.png`
Expected: 2 files listed.

- [ ] **Step 4: Write the chapter markdown**

Create `docs/manual-book/akademik/05-rekap-rapor.md`:

```markdown
# Bab 5 — Rekap Rapor

## Untuk siapa

Admin Akademik, dan Kepala Sekolah (lihat-saja) untuk meninjau hasil.

## Prasyarat

- [Bab 4 — Asesmen & Nilai](04-asesmen-nilai.md) sudah selesai: minimal satu Asesmen dengan
  nilai terisi untuk kelas yang ingin direkap.

## Langkah-langkah

1. Buka menu **Rekap Rapor**. Pilih **Tahun Ajaran** terlebih dahulu — pilihan Kelas dan
   Semester akan otomatis menyesuaikan (menyaring hanya yang benar-benar milik tahun ajaran
   tsb, supaya tidak salah pilih kombinasi lintas tahun).

   ![Filter Rekap Rapor](images/05-01-filter-rekap-rapor.png)

2. Setelah Kelas dan Semester dipilih, tabel rekap nilai per siswa per Mata Pelajaran
   tampil langsung tanpa reload halaman. Nilai di bawah ambang tuntas (default 75, bisa
   diubah lewat konfigurasi sistem) ditandai berbeda dari yang tuntas.

   ![Hasil Rekap Rapor](images/05-02-hasil-rekap-rapor.png)

3. Klik **Export PDF** untuk mengunduh rekap dalam bentuk siap cetak.

## Kesalahan umum

- **Memilih Kelas dulu sebelum Tahun Ajaran.** Selalu pilih Tahun Ajaran terlebih dahulu —
  ini pernah jadi sumber bug (kombinasi Kelas/Semester dari tahun ajaran berbeda ikut
  tercampur) sebelum filter dibuat bertingkat seperti sekarang; urutan pengisian di
  halaman ini bukan sekadar preferensi tampilan.
- **Rekap kosong padahal nilai sudah diisi guru.** Pastikan nilai diisi untuk Semester yang
  sama dengan yang dipilih di filter rekap — nilai dari semester lain sengaja tidak ikut
  tercampur.
```

- [ ] **Step 5: Read the chapter back and cross-check against the running app**

Same verification approach as prior tasks. Pay particular attention to whether the
cascading filter's real `<select name="...">` attributes match what Step 1's `before`
callback assumes — fix the script if they don't, then re-run Step 2.

- [ ] **Step 6: Commit**

```bash
git add scripts/manual-book-screenshots.mjs docs/manual-book/akademik/05-rekap-rapor.md docs/manual-book/akademik/images/05-*.png
git commit -m "docs: add Bab 5 (Rekap Rapor) to the academic manual book"
```

---

### Task 8: Bab 06 — Kenaikan Kelas (admin_akademik)

**Files:**
- Modify: `scripts/manual-book-screenshots.mjs` (populate `TARGETS['06']`)
- Create: `docs/manual-book/akademik/06-kenaikan-kelas.md`

**Interfaces:**
- Consumes: same `TARGETS`/`capture` contract as Task 2.
- Produces: `docs/manual-book/akademik/images/06-01.png`.

- [ ] **Step 1: Add screenshot targets for this chapter**

Replace `'06': [],` with:

```js
  '06': [
    { account: 'akademik', path: '/admin/kenaikan-kelas', file: '06-01-kenaikan-kelas.png' },
  ],
```

- [ ] **Step 2: Run the script for this chapter**

Run: `MANUAL_BOOK_BASE_URL=<url-lokal-anda> node scripts/manual-book-screenshots.mjs --bab=06`
Expected: 1 line `captured 06-01-kenaikan-kelas.png`, no errors.

- [ ] **Step 3: Verify the image exists**

Run: `ls docs/manual-book/akademik/images/06-*.png`
Expected: 1 file listed.

- [ ] **Step 4: Write the chapter markdown**

Create `docs/manual-book/akademik/06-kenaikan-kelas.md`:

```markdown
# Bab 6 — Kenaikan Kelas

## Untuk siapa

Admin Akademik. Ini bab penutup siklus tahun ajaran — dikerjakan sekali di akhir tahun
ajaran, biasanya setelah Rekap Rapor (Bab 5) selesai dan sudah diverifikasi.

## Prasyarat

- [Bab 5 — Rekap Rapor](05-rekap-rapor.md) sudah dicek dan dianggap final untuk semester
  akhir tahun ajaran yang berjalan.
- Tahun Ajaran baru untuk tahun berikutnya sudah dibuat (lihat Bab 1) — Kenaikan Kelas
  memindahkan siswa KE tahun ajaran baru itu, bukan membuatkannya secara otomatis.

## Langkah-langkah

1. Buka menu **Kenaikan Kelas**. Untuk setiap Kelas asal, pilih Kelas tujuan di tahun ajaran
   baru (naik tingkat), atau tandai sebagai **Lulus** untuk siswa tingkat akhir.

   ![Halaman Kenaikan Kelas](images/06-01-kenaikan-kelas.png)

2. Konfirmasi dan proses. Seluruh siswa di kelas asal dipindahkan sekaligus (bukan satu per
   satu) ke kelas tujuan yang dipilih.

## Kesalahan umum

- **Proses ini tidak bisa dibatalkan begitu selesai.** Pastikan Rekap Rapor semester
  terakhir sudah benar-benar final sebelum menjalankan Kenaikan Kelas — data presensi dan
  nilai semester lama tetap tersimpan (tidak terhapus), tapi siswa akan langsung terasosiasi
  ke kelas & tahun ajaran baru begitu diproses.
- **Kelas tujuan berasal dari tahun ajaran yang salah.** Kelas tujuan yang ditawarkan harus
  berasal dari Tahun Ajaran yang sudah dibuat untuk periode berikutnya — kalau daftar
  pilihan kosong atau tidak sesuai ekspektasi, cek dulu apakah Tahun Ajaran barunya sudah
  ada (Bab 1).
```

- [ ] **Step 5: Read the chapter back and cross-check against the running app**

Same verification approach as prior tasks.

- [ ] **Step 6: Commit**

```bash
git add scripts/manual-book-screenshots.mjs docs/manual-book/akademik/06-kenaikan-kelas.md docs/manual-book/akademik/images/06-*.png
git commit -m "docs: add Bab 6 (Kenaikan Kelas) to the academic manual book"
```

---

### Task 9: Lampiran — Kalender Nasional & Lintas Lembaga (yayasan admin)

**Files:**
- Modify: `scripts/manual-book-screenshots.mjs` (populate `TARGETS['lampiran']`)
- Create: `docs/manual-book/akademik/lampiran-lintas-lembaga.md`

**Interfaces:**
- Consumes: same `TARGETS`/`capture` contract as Task 2.
- Produces: `docs/manual-book/akademik/images/lampiran-01.png`.

- [ ] **Step 1: Add screenshot target for this appendix**

Replace `'lampiran': [],` with:

```js
  lampiran: [
    { account: 'yayasan', path: '/admin/pengaturan/akademik', file: 'lampiran-01-pengaturan-akademik-nasional.png' },
  ],
```

- [ ] **Step 2: Run the script for this chapter**

Run: `MANUAL_BOOK_BASE_URL=<url-lokal-anda> node scripts/manual-book-screenshots.mjs --bab=lampiran`
Expected: 1 line `captured lampiran-01-...png`, no errors. Note: as the yayasan admin, this
page requires an **active Lembaga selected via the switcher first** (see Bab 0's note on
this) — if the script hits the dashboard-redirect error instead of the settings page, the
`before` hook needs to select a Lembaga via the switcher before navigating; adjust and
re-run if that happens.

- [ ] **Step 3: Verify the image exists**

Run: `ls docs/manual-book/akademik/images/lampiran-*.png`
Expected: 1 file listed.

- [ ] **Step 4: Write the appendix markdown**

Create `docs/manual-book/akademik/lampiran-lintas-lembaga.md`:

```markdown
# Lampiran — Kalender Nasional & Pengaturan Lintas Lembaga

## Untuk siapa

Admin Yayasan (`yayasan_super_admin`).

## Prasyarat

- [Bab 0 — Setup Lembaga](00-setup-lembaga.md) selesai, minimal satu Lembaga aktif dipilih
  lewat switcher.

## Langkah-langkah

Peran Admin Yayasan di modul akademik ini sempit — bukan pengguna harian data akademik
(itu domain Admin Akademik per Lembaga, lihat Bab 1-6). Yang jadi bagiannya:

1. **Kalender Akademik Nasional.** Dari halaman **Pengaturan Akademik** (dengan satu
   Lembaga aktif terpilih via switcher), Admin Yayasan bisa menambahkan entri kalender yang
   berlaku untuk **semua Lembaga** di bawah yayasan sekaligus (mis. libur nasional yang
   sama untuk seluruh sekolah), berbeda dari entri kalender biasa yang hanya berlaku untuk
   satu Lembaga.

   ![Pengaturan Akademik — tampilan Admin Yayasan](images/lampiran-01-pengaturan-akademik-nasional.png)

2. Entri nasional ini otomatis muncul di kalender setiap Lembaga tanpa perlu diinput ulang
   oleh masing-masing Admin Akademik.

## Kesalahan umum

- **Mengira halaman ini bisa diakses tanpa memilih Lembaga aktif dulu.** Meskipun entri
  nasional berlaku lintas-lembaga, halamannya sendiri tetap butuh satu Lembaga aktif
  terpilih (untuk menampilkan konteks kalender gabungan nasional + lembaga tsb) — pilih
  lewat switcher navbar dulu kalau halaman mengarahkan balik ke dashboard.
```

- [ ] **Step 5: Read the chapter back and cross-check against the running app**

Same verification approach as prior tasks.

- [ ] **Step 6: Commit**

```bash
git add scripts/manual-book-screenshots.mjs docs/manual-book/akademik/lampiran-lintas-lembaga.md docs/manual-book/akademik/images/lampiran-*.png
git commit -m "docs: add appendix (Kalender Nasional & Lintas Lembaga) to the academic manual book"
```

---

## Self-Review Notes

- **Spec coverage:** every chapter from the spec's file list (00 through 06 + lampiran) has
  a task; the spec's screenshot pipeline (Playwright, config-driven, per-bab CLI flag,
  1440×900 viewport, `docs/manual-book/akademik/images/` output) is implemented in Task 1
  and consumed identically by every later task; the spec's Maintenance section is satisfied
  structurally (per-chapter file + per-bab `--bab=` flag) without needing its own task.
- **Placeholder scan:** no TBD/TODO — the two spots requiring on-the-day judgment (Task 5's
  Step 1 note about verifying the `<select>` names, Task 9's Step 2 note about the
  Lembaga-switcher precondition) are flagged explicitly as "verify against the real app and
  adjust," not left as unresolved placeholders, because the actual DOM structure of
  Blade-rendered forms isn't visible from source alone without rendering the page.
- **Type/interface consistency:** every task's `TARGETS['<id>']` array uses the exact same
  object shape (`account`, `path`, `file`, optional `before`/`fullPage`) defined in Task 1's
  `TARGETS`/`capture` contract — verified by re-reading each task's Step 1 code block
  against Task 1's script.

## Execution

Plan complete and saved to `docs/superpowers/plans/2026-07-30-manual-book-akademik.md`.
