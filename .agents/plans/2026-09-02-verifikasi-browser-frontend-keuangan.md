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

- [ ] **Step 1: Jalankan aplikasi**

Gunakan skill `run` project ini untuk start dev server (`composer run dev` atau `npm run dev` + `php artisan serve`, sesuai konvensi project — cek `composer.json`/`package.json` scripts kalau belum familiar). Pastikan `npm run build` sudah dijalankan minimal sekali sebelumnya supaya aset ter-compile.

- [ ] **Step 2: Login sebagai admin bendahara_lembaga**

Buat/pakai user dengan role `bendahara_lembaga` (lihat `database/seeders/RoleSeeder.php` untuk cara seed role ini kalau belum ada user siap pakai di database dev).

- [ ] **Step 3: Verifikasi #1 — create lengkap**

Buka `/lembaga/keuangan/jenis-tagihan/create`. Pilih kategori "SPP". Ganti Mode Otomatis/Manual dan Tipe (harian/mingguan/bulanan/tahunan/sekali) satu-satu, screenshot tiap kombinasi, konfirmasi field pendukung (tanggal_mulai, hari_generate, dst) muncul/hilang sesuai tipe. Cek console browser untuk error JS.

- [ ] **Step 4: Verifikasi #2 — Target Sasaran kriteria dinamis**

Pindah ke mode "Berdasarkan Kriteria Khusus" di kartu Target Sasaran. Tambah Grup, tambah beberapa kriteria. Ganti field kriteria (`lembaga` → `kelas` → `tahun_ajaran` → balik lagi) berkali-kali di baris yang sama, screenshot tiap perubahan. **Fokus**: apakah TomSelect widget re-render dengan opsi yang benar tiap kali field diganti, atau ada opsi lama yang nyangkut/duplikat elemen di DOM.

- [ ] **Step 5: Verifikasi #3 — Tarif Berdimensi reorder**

Tambah 3+ Grup Tarif dengan nominal berbeda. Klik tombol ↑/↓ berkali-kali, screenshot urutan sebelum/sesudah. Klik "Hitung Siswa" di tiap grup setelah reorder, konfirmasi angka preview tetap merujuk ke grup yang benar (bukan salah index). **Sekaligus jawab**: apakah reorder ini butuh klik Simpan form dulu baru ter-persist, atau ada ekspektasi tersimpan instan? Laporkan temuan ini secara eksplisit di laporan akhir, JANGAN ubah kodenya sendiri kalau ternyata cuma soal ekspektasi UX (itu keputusan user).

- [ ] **Step 6: Verifikasi #4 — modal Buat Kategori Baru**

Di kartu Keringanan, klik "Buat Kategori Baru". Isi nama, submit. Konfirmasi: modal tertutup, toast sukses muncul, kategori baru LANGSUNG muncul di dropdown Keringanan tanpa reload halaman.

- [ ] **Step 7: Verifikasi #5 — widget Kelola Assignment Siswa**

Buka panel "Kelola Assignment Siswa". Coba search nama siswa, coba filter by kelas. Centang 1 checkbox keringanan untuk 1 siswa, tunggu, konfirmasi state tersimpan (tidak flicker balik ke unchecked). Hilangkan centang, konfirmasi state ter-update lagi. Selama proses toggle sedang berjalan, konfirmasi CUMA checkbox yang sedang diproses yang disabled, bukan seluruh baris/tabel.

- [ ] **Step 8: Verifikasi #6 — field priority_score**

Isi angka di field "Prioritas Auto-Debit", simpan form. Buka lagi halaman edit Jenis Tagihan yang sama, konfirmasi angka itu masih terisi (tersimpan dengan benar).

- [ ] **Step 9: Perbaiki bug yang ditemukan (kalau ada)**

Untuk tiap bug: tulis test Pest kalau bisa dibuktikan lewat assertion server-side/HTML (RED → fix → GREEN). Kalau murni bug visual/interaksi JS, screenshot before/after + penjelasan.

- [ ] **Step 10: Run regression test untuk file yang disentuh**

Run: `php artisan test --filter='JenisTagihanFormTest|JenisTagihanFormKeringananWidgetTest|JenisTagihanPreviewSiswaKeringananTest|JenisTagihanFormLivePreviewUiTest|TarifPriorityBackfillTest'`
Expected: PASS.

- [ ] **Step 11: Commit (kalau ada perubahan kode)**

```bash
git add <file-file yang diubah>
git commit -m "fix(keuangan): <deskripsi bug spesifik yang ditemukan saat verifikasi browser form Jenis Tagihan>"
```

Kalau TIDAK ADA bug ditemukan di task ini, cukup catat "Task 1: semua 6 alur lolos verifikasi browser, tidak ada bug" di laporan — tidak perlu commit kosong.

---

## Task 2: Halaman Perlu Ditinjau Admin (Verifikasi #7-9)

**Files (potensial):**
- `resources/views/portals/lembaga/keuangan/tagihan/perlu-ditinjau.blade.php`

**Interfaces:** Tidak ada perubahan interface direncanakan.

**Konteks penting**: bug `$errors` yang tidak pernah dirender di halaman ini SUDAH DIPERBAIKI sebelum plan ini dibuat (lihat spec §2, atau `git log -- resources/views/portals/lembaga/keuangan/tagihan/perlu-ditinjau.blade.php` untuk commit fix-nya). Verifikasi #8 di bawah untuk MENGONFIRMASI perbaikan itu benar-benar terlihat di browser, bukan menemukan bug baru dari nol.

- [ ] **Step 1: Buat data uji**

Perlu minimal 1 `Tagihan` dengan `perlu_ditinjau_ulang=true` untuk muncul di halaman ini. Bisa lewat tinker atau lewat alur nyata (ubah Keringanan sebuah Jenis Tagihan yang siswanya sudah bayar sebagian, biarkan engine recalculate men-flag otomatis — tapi cara tercepat untuk verifikasi UI murni cukup lewat tinker/factory langsung di database dev).

- [ ] **Step 2: Verifikasi #7 — Koreksi Nominal jalur sukses**

Buka `/lembaga/keuangan/tagihan/perlu-ditinjau`. Klik "Koreksi Nominal" di 1 baris, isi Total Tagihan & Potongan yang valid, submit. Konfirmasi redirect menampilkan pesan hijau "Nominal tagihan berhasil dikoreksi." dan baris itu hilang dari daftar.

- [ ] **Step 3: Verifikasi #8 — Koreksi Nominal jalur gagal validasi (prioritas tinggi)**

Buat ulang 1 tagihan `perlu_ditinjau_ulang=true`. Klik "Koreksi Nominal", isi `discount_amount` LEBIH BESAR dari `total_tagihan`, submit. **Konfirmasi pesan error SEKARANG terlihat** (banner merah di atas tabel) — screenshot sebagai bukti perbaikan bug sebelumnya benar-benar berfungsi di browser.

- [ ] **Step 4: Verifikasi #9 — dismiss popover**

Buka popover "Koreksi Nominal" lagi. Klik di LUAR popover (area lain halaman) → popover harus tertutup. Buka lagi, klik DI DALAM area input popover (mis. klik ke dalam field Total Tagihan) → popover TIDAK BOLEH tertutup (kalau tertutup, itu bug `@click.outside` yang salah target).

- [ ] **Step 5: Perbaiki bug yang ditemukan (kalau ada)**

Sama seperti Task 1 Step 9.

- [ ] **Step 6: Run regression test**

Run: `php artisan test --filter='TagihanKoreksiNominalRouteTest|KoreksiNominalTagihanActionTest|SelesaikanTinjauanTagihanActionTest'`
Expected: PASS.

- [ ] **Step 7: Commit (kalau ada perubahan)**

```bash
git add <file-file yang diubah>
git commit -m "fix(keuangan): <deskripsi bug spesifik dari verifikasi browser halaman Perlu Ditinjau>"
```

---

## Task 3: Halaman Jenis Tagihan Monitoring Admin (Verifikasi #10)

**Files (potensial):**
- `resources/views/portals/lembaga/keuangan/jenis-tagihan/monitoring/index.blade.php`

**Interfaces:** Tidak ada perubahan interface direncanakan.

- [ ] **Step 1: Buat data uji**

Siapkan 1 Jenis Tagihan dengan minimal 2-3 Tagihan siswa berstatus `belum_bayar` (untuk uji tombol Batalkan) dan 1-2 dengan `sebagian`/`lunas` (untuk memastikan tombol Batalkan TIDAK muncul/aktif di baris itu).

- [ ] **Step 2: Verifikasi #10 — tab & modal batalkan**

Buka halaman Monitoring Jenis Tagihan itu. Pindah antara tab "Daftar Penerima" dan "Daftar Tunggakan", konfirmasi konten berganti dengan benar. Klik "Batalkan" di SATU baris `belum_bayar`, konfirmasi modal terbuka dengan `cancelUrl` mengarah ke tagihan yang benar (cek atribut `action` form di dalam modal via browser dev tools kalau perlu). Tutup modal tanpa submit. Klik "Batalkan" di baris LAIN, konfirmasi `cancelUrl` sudah berganti ke tagihan yang baru (tidak "nyangkut" dari baris sebelumnya).

- [ ] **Step 3: Perbaiki bug yang ditemukan (kalau ada)**

Sama seperti task sebelumnya.

- [ ] **Step 4: Run regression test**

Run: `php artisan test --filter=JenisTagihanMonitoringTest`
Expected: PASS.

- [ ] **Step 5: Commit (kalau ada perubahan)**

```bash
git add <file-file yang diubah>
git commit -m "fix(keuangan): <deskripsi bug spesifik dari verifikasi browser Monitoring>"
```

---

## Task 4: Dashboard Orang Tua (Verifikasi #11-14)

**Files (potensial):**
- `resources/views/portals/portal/keuangan/dashboard.blade.php`

**Interfaces:** Tidak ada perubahan interface direncanakan.

- [ ] **Step 1: Login sebagai orang tua**

Perlu user dengan role `orang_tua` yang punya minimal 1 anak (`Siswa`) dengan wallet, beberapa `Tagihan` outstanding, dan minimal 1 notifikasi dengan `tagihan_id` + 1 tanpa `tagihan_id` (buat lewat tinker kalau perlu, notifikasi asli baru muncul dari trigger recalculate/pembayaran).

- [ ] **Step 2: Verifikasi #11 — notifikasi**

Buka dashboard. Klik notifikasi yang punya `tagihan_id`, konfirmasi: titik indikator unread hilang, `unreadCount` di badge berkurang, DAN halaman navigasi ke detail tagihan yang benar. Kembali ke dashboard, klik notifikasi TANPA `tagihan_id`, konfirmasi cuma tertandai terbaca (titik hilang) TANPA navigasi ke halaman lain.

- [ ] **Step 3: Verifikasi #12 — Tandai Semua Terbaca**

Kalau masih ada notifikasi unread, klik "Tandai Semua Terbaca". Konfirmasi semua titik indikator hilang DAN tombol "Tandai Semua Terbaca" itu sendiri ikut hilang (karena `unreadCount` sekarang 0).

- [ ] **Step 4: Verifikasi #13 — modal Top Up**

Buka modal Top Up (tombol yang relevan di kartu wallet). Konfirmasi bisa dibuka & ditutup lewat tombol close DAN klik area backdrop di luar modal. Klik "Salin VA" (kalau ada), konfirmasi nomor VA tersalin ke clipboard (cek lewat paste manual atau clipboard API browser dev tools) dan toast konfirmasi muncul. Cek console untuk error permission clipboard.

- [ ] **Step 5: Verifikasi #14 — pilih tagihan & Bayar Terpilih**

Centang beberapa tagihan di daftar dashboard. Konfirmasi tagihan yang `perlu_ditinjau_ulang=true` (kalau ada di data uji) checkbox-nya disabled/tidak bisa dicentang. Klik "Bayar Terpilih", konfirmasi URL checkout yang dihasilkan (`tagihan_ids[]=...` di query string) berisi persis id yang dicentang.

- [ ] **Step 6: Perbaiki bug yang ditemukan (kalau ada)**

Sama seperti task sebelumnya.

- [ ] **Step 7: Run regression test**

Run: `php artisan test --filter='DashboardControllerTest|DashboardNotificationMarkAsReadTest'`
Expected: PASS.

- [ ] **Step 8: Commit (kalau ada perubahan)**

```bash
git add <file-file yang diubah>
git commit -m "fix(keuangan): <deskripsi bug spesifik dari verifikasi browser Dashboard Orang Tua>"
```

---

## Task 5: Daftar Tagihan Orang Tua (Verifikasi #15-17)

**Files (potensial):**
- `resources/views/portals/portal/keuangan/tagihan/index.blade.php`

**Interfaces:** Tidak ada perubahan interface direncanakan.

- [ ] **Step 1: Verifikasi #15 — filter tab**

Buka `/keuangan/tagihan`. Toggle antara tab "Semua" dan "Jatuh Tempo". Konfirmasi: pilihan checkbox (`selected`) ke-reset tiap ganti tab, dan angka di kedua tab (`countSemua`/`countJatuhTempo`) benar-benar cocok dengan jumlah tagihan yang ditampilkan/terlambat.

- [ ] **Step 2: Verifikasi #16 — checkbox pilih semua**

Klik checkbox di header tabel. Konfirmasi cuma memilih tagihan yang BISA dipilih (bukan yang `perlu_ditinjau_ulang`). Centang manual sebagian, konfirmasi checkbox header menunjukkan state "sebagian terpilih" dengan benar (bukan langsung full-checked meski belum semua terpilih).

- [ ] **Step 3: Verifikasi #17 — link Lihat Detail**

Klik nama salah satu tagihan di daftar. Konfirmasi navigasi ke halaman detail tagihan yang benar, dan angka breakdown (Nominal Awal/Potongan/Nominal Akhir) di halaman detail cocok dengan yang ditampilkan di daftar.

- [ ] **Step 4: Perbaiki bug yang ditemukan (kalau ada)**

Sama seperti task sebelumnya.

- [ ] **Step 5: Run regression test**

Run: `php artisan test --filter=TagihanControllerTest`
Expected: PASS.

- [ ] **Step 6: Commit (kalau ada perubahan)**

```bash
git add <file-file yang diubah>
git commit -m "fix(keuangan): <deskripsi bug spesifik dari verifikasi browser Daftar Tagihan Orang Tua>"
```

---

## Task 6: Topbar & Sidebar (Verifikasi #18-20)

**Files (potensial):**
- `resources/views/layouts/topbar.blade.php`
- `resources/views/layouts/sidebar.blade.php`

**Interfaces:** Tidak ada perubahan interface direncanakan.

- [ ] **Step 1: Verifikasi #18 — bell notifikasi lintas role**

Login sebagai user yayasan (widestScopeLevel yayasan) DAN sebagai user lembaga biasa, cek bell notifikasi tampil wajar di keduanya. Login sebagai orang tua DAN sebagai admin biasa (bukan orang tua), konfirmasi link "Lihat Detail" di notifikasi CUMA muncul untuk user yang punya profil OrangTua, tidak muncul untuk admin biasa meski notifikasinya kebetulan punya `tagihan_id`.

- [ ] **Step 2: Verifikasi #19 — badge Tagihan Perlu Ditinjau**

Login sebagai admin dengan permission `tagihan.view`/`tagihan.edit`, pastikan ada minimal 1 tagihan `perlu_ditinjau_ulang=true` di lembaga aktifnya. Konfirmasi badge angka di topbar cocok dengan jumlah sebenarnya, dan klik badge itu mengarah ke halaman Perlu Ditinjau yang benar.

- [ ] **Step 3: Verifikasi #20 — sidebar menu PPDB benar-benar hilang**

Login sebagai admin dengan permission `tagihan.view` DAN `pembayaran.view` (yang tadinya akan melihat menu "Tagihan"/"Verifikasi Pembayaran"). Konfirmasi kedua menu itu TIDAK muncul di sidebar manapun. Buka beberapa halaman admin (dashboard, jenis tagihan, dst), konfirmasi TIDAK ADA PHP warning/error terkait array/sidebar yang muncul di halaman (sanity check comment-out array di `sidebar.blade.php` tidak merusak parsing).

- [ ] **Step 4: Perbaiki bug yang ditemukan (kalau ada)**

Sama seperti task sebelumnya.

- [ ] **Step 5: Run regression test**

Run: `php artisan test --filter='TopbarNotificationBellTest|SidebarPengelompokanTest'`
Expected: PASS.

- [ ] **Step 6: Commit (kalau ada perubahan)**

```bash
git add <file-file yang diubah>
git commit -m "fix(keuangan): <deskripsi bug spesifik dari verifikasi browser Topbar/Sidebar>"
```

---

## Final Step: Full Test Suite & Handoff

- [ ] Run: `php artisan test --compact` — **SENDIRIAN**, pastikan tidak ada proses `php artisan test` lain berjalan bersamaan.
- [ ] Expected: PASS, 0 failures.
- [ ] Run `vendor/bin/pint --dirty --format agent` dan commit hasil format terpisah kalau ada perubahan.
- [ ] Run `npm run build` untuk memastikan aset frontend final tidak error.
- [ ] Tulis handoff log baru di `.agents/logs/2026-09-02-verifikasi-browser-frontend-keuangan.md` merangkum hasil ke-20 alur (lolos / bug ditemukan+diperbaiki dengan commit SHA / catatan desain seperti soal reorder-tarif di Task 1 Step 5). Commit handoff log itu.

**Plan selesai ketika semua 20 alur benar-benar sudah diklik di browser, full suite hijau, dan handoff log tertulis.**
