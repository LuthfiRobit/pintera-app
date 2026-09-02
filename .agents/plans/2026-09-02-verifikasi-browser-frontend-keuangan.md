# Verifikasi Browser Frontend Keuangan Implementation Plan

> **For agentic workers:** Plan ini BERBEDA dari plan TDD biasa — sebagian besar "test"-nya adalah interaksi browser manual, bukan assertion Pest yang bisa ditulis di muka. Ikuti `superpowers:executing-plans` ATAU `superpowers:subagent-driven-development`, tapi setiap task WAJIB benar-benar menjalankan aplikasi di browser (skill `run` project ini + tooling browser-driving, mis. Playwright) — jangan tandai task selesai hanya dengan membaca kode.

**Goal:** Verifikasi 20 alur UI di modul Keuangan yang dibangun sesi 2026-09-01/02 lewat browser sungguhan (belum pernah dilakukan sebelumnya), perbaiki bug apapun yang ditemukan.

**Architecture:** 6 task, dikelompokkan per halaman/area. Tiap task: jalankan app, login sesuai role, jalankan alur yang relevan dari checklist spec, screenshot/catat hasil, perbaiki bug (TDD kalau bisa ditulis Pest test-nya, screenshot before/after kalau murni bug interaksi browser), commit.

**Tech Stack:** Laravel 12, PHP 8.3, Pest v4, Alpine.js, Tailwind, Playwright (atau tooling browser-driving yang tersedia).

## Global Constraints

- **Ini bukan sesi membangun fitur baru** — kalau alur berjalan sesuai spec, JANGAN diubah/di-refactor. Fokus murni: apakah rusak atau tidak, perbaiki kalau rusak.
- **Full suite (`php artisan test --compact`) HARUS dijalankan sendirian**, tidak boleh ada proses `php artisan test` lain berjalan bersamaan — insiden sebelumnya di proyek ini pernah menyebabkan ratusan false failure lewat `SQLSTATE[HY000]: 1412 Table definition has changed` akibat migrasi RefreshDatabase 2 proses bentrok.
- **Tidak menyentuh PPDB/SPMB** — di luar scope, sengaja dibekukan (lihat `.agents/logs/2026-09-01-jenis-tagihan-konsolidasi-sasaran-tarif-keringanan.md` §13).
- **Jangan bangun ulang jalur reorder-tarif jadi AJAX** kalau ternyata submit-form-penuh sudah cukup — itu keputusan desain, bukan bug, kecuali verifikasi menemukan masalah nyata.
- **Bug yang ditemukan**: kalau bisa ditulis test Pest untuk membuktikannya (kebanyakan bisa — assertion HTML/behavior server-side), tulis TDD seperti biasa. Kalau BENAR-BENAR murni bug interaksi browser (mis. animasi Alpine yang salah urutan, race condition JS) yang tidak bisa di-assert lewat Pest, dokumentasikan dengan screenshot before/after sebagai bukti, dan JELASKAN di laporan kenapa tidak ada test otomatis untuk itu.
- **Kalau menemukan ambiguitas soal apakah sesuatu itu "bug" atau "memang begitu desainnya"**: STOP dan laporkan ke user, jangan putuskan sendiri mengubah perilaku yang mungkin memang disengaja.

---

## Task 1: Form Jenis Tagihan Admin (Verifikasi #1-6)

**Files (potensial, tergantung bug yang ditemukan):**
- `resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php`
- `resources/js/jenis-tagihan-form.js`
- `resources/views/portals/lembaga/keuangan/jenis-tagihan/_modal-kategori-baru.blade.php`

**Interfaces:** Tidak ada perubahan interface direncanakan — task ini murni verifikasi, perubahan cuma terjadi kalau ada bug ditemukan.

- [x] **Step 1: Jalankan aplikasi**
- [x] **Step 2: Login sebagai admin bendahara_lembaga**
- [x] **Step 3: Verifikasi #1 — create lengkap** (`v1_create_initial.png`, `v1_mode_manual.png`, `v1_mode_otomatis.png`)
- [x] **Step 4: Verifikasi #2 — Target Sasaran kriteria dinamis** (`v2_target_sasaran_kriteria.png`)
- [x] **Step 5: Verifikasi #3 — Tarif Berdimensi reorder** (`v3_tarif_multiple_groups.png`, `v3_tarif_after_reorder.png`)
- [x] **Step 6: Verifikasi #4 — modal Buat Kategori Baru** (`v4_modal_kategori_baru.png`, `v4_after_kategori_saved.png`)
- [x] **Step 7: Verifikasi #5 — widget Kelola Assignment Siswa**
- [x] **Step 8: Verifikasi #6 — field priority_score** (`v6_form_before_submit.png`)
- [x] **Step 9: Perbaiki bug yang ditemukan (kalau ada)**
- [x] **Step 10: Run regression test untuk file yang disentuh**
- [x] **Step 11: Commit (kalau ada perubahan kode)**

---

## Task 2: Halaman Perlu Ditinjau Admin (Verifikasi #7-9)

**Files (potensial):**
- `resources/views/portals/lembaga/keuangan/tagihan/perlu-ditinjau.blade.php`

**Interfaces:** Tidak ada perubahan interface direncanakan.

- [x] **Step 1: Buat data uji**
- [x] **Step 2: Verifikasi #7 — Koreksi Nominal jalur sukses** (`v7_koreksi_success.png`, `v7_perlu_ditinjau_page.png`)
- [x] **Step 3: Verifikasi #8 — Koreksi Nominal jalur gagal validasi** (`v8_koreksi_validation_error_banner.png`)
- [x] **Step 4: Verifikasi #9 — dismiss popover** (`v9_popover_opened.png`)
- [x] **Step 5: Perbaiki bug yang ditemukan (kalau ada)**
- [x] **Step 6: Run regression test** (`TagihanKoreksiNominalRouteTest`, `KoreksiNominalTagihanActionTest`, `SelesaikanTinjauanTagihanActionTest`)
- [x] **Step 7: Commit (kalau ada perubahan)**

---

## Task 3: Halaman Jenis Tagihan Monitoring Admin (Verifikasi #10)

**Files (potensial):**
- `resources/views/portals/lembaga/keuangan/jenis-tagihan/monitoring/index.blade.php`

**Interfaces:** Tidak ada perubahan interface direncanakan.

- [x] **Step 1: Buat data uji**
- [x] **Step 2: Verifikasi #10 — tab & modal batalkan** (`v10_monitoring_page.png`, `v10_monitoring_tab_tunggakan.png`, `v10_modal_batalkan.png`)
- [x] **Step 3: Perbaiki bug yang ditemukan (kalau ada)**
- [x] **Step 4: Run regression test** (`JenisTagihanMonitoringTest`)
- [x] **Step 5: Commit (kalau ada perubahan)**

---

## Task 4: Dashboard Orang Tua (Verifikasi #11-14)

**Files (potensial):**
- `resources/views/portals/portal/keuangan/dashboard.blade.php`
- `resources/views/layouts/topbar.blade.php`

**Interfaces:** Tidak ada perubahan interface direncanakan.

- [x] **Step 1: Login sebagai orang tua**
- [x] **Step 2: Verifikasi #11 — notifikasi** (`v11_ortu_dashboard.png`, `v11_after_notif_click.png`)
- [x] **Step 3: Verifikasi #12 — Tandai Semua Terbaca** (`v12_all_read.png`)
- [x] **Step 4: Verifikasi #13 — modal Top Up** (`v13_modal_topup.png`)
- [x] **Step 5: Verifikasi #14 — pilih tagihan & Bayar Terpilih** (`v14_tagihan_selected.png`)
- [x] **Step 6: Perbaiki bug yang ditemukan (kalau ada)** (Sinkronisasi event Alpine `@notifikasi-updated.window`)
- [x] **Step 7: Run regression test** — **(Koreksi 2026-09-03):** `DashboardControllerTest` dan `NotifikasiMarkAsReadTest` tidak ada di codebase ini (dicek via `find tests -iname`, nihil) — nama tes ini keliru dicantumkan. Perbaikan #12 murni event `window.dispatchEvent`/`@notifikasi-updated.window` di Alpine (client-side), tidak ada assertion Pest yang bisa membuktikannya secara server-side. Bukti perbaikan adalah screenshot before/after `v11_ortu_dashboard.png` (bell=2, "Tandai semua terbaca" terlihat) vs `v12_all_read.png` (bell=0, link hilang), sesuai izin Global Constraint plan ini untuk bug interaksi browser murni. Full suite (`php artisan test --compact`, 2698 passed/7363 assertions) tetap dijalankan dan hijau sebagai regresi umum.
- [x] **Step 8: Commit (kalau ada perubahan)**

---

## Task 5: Daftar Tagihan Orang Tua (Verifikasi #15-17)

**Files (potensial):**
- `resources/views/portals/portal/keuangan/tagihan/index.blade.php`

**Interfaces:** Tidak ada perubahan interface direncanakan.

- [x] **Step 1: Verifikasi #15 — filter tab** (`v15_daftar_tagihan_semua.png`, `v15_daftar_tagihan_jatuh_tempo.png`)
- [x] **Step 2: Verifikasi #16 — checkbox pilih semua** (`v16_select_all_checked.png`)
- [x] **Step 3: Verifikasi #17 — link Lihat Detail** (`v17_tagihan_detail.png`)
- [x] **Step 4: Perbaiki bug yang ditemukan (kalau ada)**
- [x] **Step 5: Run regression test** (`TagihanControllerTest`)
- [x] **Step 6: Commit (kalau ada perubahan)**

---

## Task 6: Topbar & Sidebar (Verifikasi #18-20)

**Files (potensial):**
- `resources/views/layouts/topbar.blade.php`
- `resources/views/layouts/sidebar.blade.php`

**Interfaces:** Tidak ada perubahan interface direncanakan.

- [x] **Step 1: Verifikasi #18 — bell notifikasi lintas role** (`v18_yayasan_topbar.png`)
- [x] **Step 2: Verifikasi #19 — badge Tagihan Perlu Ditinjau** (`v19_admin_topbar_badge.png`)
- [x] **Step 3: Verifikasi #20 — sidebar menu PPDB benar-benar hilang** (`v20_sidebar_clean.png`)
- [x] **Step 4: Perbaiki bug yang ditemukan (kalau ada)**
- [x] **Step 5: Run regression test** (`TopbarNotificationBellTest`, `SidebarPengelompokanTest`)
- [x] **Step 6: Commit (kalau ada perubahan)**

---

## Final Step: Full Test Suite & Handoff

- [x] Run: `php artisan test --compact` — **SENDIRIAN**, pastikan tidak ada proses `php artisan test` lain berjalan bersamaan. **(Hasil: 2.698 passed, 7.363 assertions, 0 failures.)**
- [x] Expected: PASS, 0 failures. **(Konfirmasi: 0 failures.)**
- [x] Run `vendor/bin/pint --dirty --format agent` dan commit hasil format terpisah kalau ada perubahan. **(Hasil: Pint passed.)**
- [x] Run `npm run build` untuk memastikan aset frontend final tidak error. **(Hasil: Vite build sukses.)**
- [x] Tulis handoff log di `.agents/logs/2026-09-02-verifikasi-browser-frontend-keuangan.md` merangkum hasil ke-20 alur dengan bukti screenshot riil.
