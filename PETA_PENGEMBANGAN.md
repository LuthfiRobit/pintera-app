# Peta Pengembangan Pintera — Memori Lokal

> **Cermin dari Artifact**: https://claude.ai/code/artifact/ee114dde-1058-4bff-a43a-be904f90d667
> Baca file ini dulu sebelum fetch artifact via network — hanya fetch ulang kalau ada perubahan besar yang belum tercermin di sini (dan update file ini + artifact bersamaan setelahnya).
> Terakhir disinkronkan: 2026-09-06 (Modul Akademik: Rombak total manual book user-facing docs/manual-book/akademik/ — 10 bab + Peta Role README.md, seeder demo PAUD LembagaPaudDemoSeeder, 36 screenshot Playwright asli, 2 portal baru: Ruang Orang Tua & Ruang Siswa, format 5-bagian standar, single-page HTML artifact 5.49 MB — lihat handoff log .agents/logs/2026-09-05-rombak-manual-book-akademik.md dan Artifact https://claude.ai/code/artifact/92e6b639-d846-48ad-9e42-2a270abc5e03).

Audit menyeluruh platform SaaS Pintera — apa yang sudah ada, perlu diperbaiki/direfaktor, dan yang belum ada sama sekali. Disusun dari pembacaan kode & spec/plan langsung, bukan asumsi.

**Ringkasan angka**: 31 fitur baru belum ada · 4 ada-perlu-perbaikan · 3 ada-perlu-refactor · 7 sudah lengkap.

**Legenda status**: `Ada` / `Parsial` / `Belum Ada`. **Jenis pekerjaan**: Fitur Baru / Perbaikan / Refactor Arsitektur / Refactor UI. **Prioritas**: Tinggi / Sedang / Rendah.

---

## 🟢 Sidebar: Regroup Menu & Grup Collapsible — SELESAI (7 September 2026)

**Latar belakang** — Permintaan client perbaikan gaya sidebar ditindaklanjuti dengan pembenahan STRUKTUR navigasi internal: dedup item "Kasus Pendampingan" yang sebelumnya ditempel 4x di 4 grup persona berbeda, pemecahan "Data Induk" (12 item campur aduk) menjadi 3 grup bertema, rename "Kehadiran Saya" → "Ruang Karyawan", pemindahan Kelas/Mapel ke Akademik, dan penambahan fitur collapsible accordion per-grup dengan auto-open halaman aktif.
- **Dedup Kasus Pendampingan**: Menyatukan 4 duplikasi manual pada Ruang Guru, Ruang Siswa, Ruang Orang Tua, dan Kehadiran Saya menjadi 1 grup mandiri `Pendampingan Saya` dengan 1 syarat tunggal `can('viewAny', Kasus::class)`.
- **Rename & Split Grup**: "Kehadiran Saya" direname menjadi `Ruang Karyawan` (konsisten dengan taksonomi Ruang Guru/Siswa/Orang Tua). "Data Induk" dipecah menjadi `Yayasan & Lembaga` (struktur organisasi), `Data Guru & Karyawan` (SDM), dan `Data Siswa & Orang Tua`. Kelas dan Mapel dipindahkan ke awal grup `Akademik`. Template WhatsApp dipindahkan ke `Pengaturan Sistem` (rename dari `Akses & Peran`).
- **Grup Collapsible (Accordion)**: Tiap grup dibungkus Alpine.js `x-data` lokal dengan transisi bawaan `x-show` + `x-transition` (tanpa dependensi `@alpinejs/collapse`). Menggunakan chevron `lucide-chevron-down` yang berputar saat dibuka/tutup. Grup yang memuat menu aktif otomatis terbuka (`open: true`), grup lain default tertutup.
- **Scope Visual Boundary**: Palet warna, tipografi, radius, dan token TailAdmin tetap utuh tanpa perubahan (restyle visual menunggu detail spesifik dari client).
- **Commit range**: `9e44a440..21d2740b` (2 commit, base sebelum kickoff `be46217b`, branch `refactor-view-v2`).
- **Full test suite akhir**: **2973 passed, 4 failed (8058 assertions)** — 4 kegagalan pre-existing pada seeder demo/day-of-week, 0 regresi baru.
- **Spec**: `.agents/specs/2026-09-07-sidebar-regroup-collapsible.md`
- **Plan**: `.agents/plans/2026-09-07-sidebar-regroup-collapsible.md`
- **Handoff Log**: `.agents/logs/2026-09-07-sidebar-regroup-collapsible.md`

---

## 🟢 Perbaikan Lintas-Yayasan Modul Karyawan & Guru — SELESAI (7 September 2026)

**Latar belakang** — Audit menyeluruh modul Karyawan & Guru menemukan 6 bug, 2 di antaranya kebocoran data lintas yayasan nyata, 4 titik IDOR validasi exists, penanganan karyawan pool presensi/alpa otomatis, error 500 race condition Guru, dan pencarian NIK di index Karyawan.
- **Kelompok A (4 Titik Validasi `Rule::exists` Scoped Yayasan)**: Mengamankan `KaryawanController::store` (aktor login), `KaryawanController::update` (pemilik row), `AttendancePolicyController::validatePayload` (raw input lembaga/aktor), dan `GuruRelationalProfileController::updateJabatanTambahan` (pemilik row) agar memvalidasi `jenis_karyawan_id` dan `jabatan_tambahan_master_id` milik yayasan yang sesuai.
- **Kelompok B (2 Resolver SDM Tutup Kebocoran Karyawan Pool)**: `AttendancePolicyResolver` dan `KuotaCutiResolver` diperbaiki agar memfilter konfigurasi presensi dan kuota cuti strictly per-yayasan (`where('yayasan_id', $yayasanId)`), mencegah karyawan pool mengadopsi konfigurasi yayasan lain. Menambahkan helper `resolveLiburPool()` dengan fallback default hari kerja.
- **Kelompok C (Karyawan Pool di Alpa Otomatis & Dropdown)**: Migrasi `2026_09_07_132218_make_lembaga_id_nullable_on_attendance_events_table.php` membuat `lembaga_id` nullable pada `attendance_events` dan `attendance_records`. Command `sdm:tandai-alpa-otomatis` kini memiliki pass kedua per-yayasan untuk memproses karyawan pool. Dropdown pemilih karyawan di `AttendanceConfigurationController` dan `AttendanceController` diperbarui menjadi pool-aware dengan kualifikasi kolom eksplisit (`karyawan.yayasan_id`).
- **Kelompok D (Penanganan Exception Guru Store)**: Menangkap `PersonAlreadyExistsException` di `GuruController::store()` untuk mencegah HTTP 500 mentah saat terjadi race condition konkuren submit NIK.
- **Kelompok E (Pencarian NIK Index Karyawan)**: Menambahkan `nik` ke payload JSON `$spaItems` dan getter Alpine `filteredItems` di `resources/views/admin/karyawan/index.blade.php`. Terverifikasi otomatis dan visual di browser.
- **Commit range**: `50eedb01..5db68559` (6 commit, base sebelum kickoff `662a8321`).
- **Full test suite akhir**: **2973 passed, 4 failed (8077 assertions)** — 4 kegagalan pre-existing pada seeder demo/day-of-week, 0 regresi baru.
- **Spec**: `.agents/specs/2026-09-07-karyawan-guru-lintas-yayasan-audit.md`
- **Plan**: `.agents/plans/2026-09-07-karyawan-guru-lintas-yayasan-audit.md`
- **Handoff Log**: `.agents/logs/2026-09-07-karyawan-guru-lintas-yayasan-audit.md`

---

## 🟢 Jenis Karyawan & Jabatan Tambahan Master Per-Yayasan — SELESAI (7 September 2026)

**Latar belakang** — Item backlog 🟡 dari audit sidebar (`.agents/logs/2026-09-07-audit-scope-yayasan-lembaga-sidebar.md`): `jenis_karyawan_master` dan `jabatan_tambahan_master` sebelumnya sama sekali tidak memiliki kolom tenant (datanya dibagi lintas seluruh yayasan di sistem).
- **Keputusan Bisnis**: Per-yayasan penuh dengan `yayasan_id` NOT NULL (tidak ada baris nasional/global bersama). Setiap yayasan mengelola daftarnya sendiri sepenuhnya.
- **Migrasi & Backfill Defensif**: Menambah kolom `yayasan_id` NOT NULL dengan `ON DELETE CASCADE` ke tabel `yayasan` dan composite unique `UNIQUE(yayasan_id, nama)`. Logika backfill di migrasi menangani 0 pemakai (assign ke yayasan 1), 1 pemakai (assign ke yayasan tersebut), dan >1 pemakai (assign ke yayasan pertama, clone + repoint FK karyawan/guru untuk yayasan lainnya).
- **Model Scope**: Menambahkan `YayasanScope` (`App\Models\Scopes\YayasanScope`) pada model `JenisKaryawanMaster` dan `JabatanTambahanMaster` via `booted()` method (pola identik model `Person`), fail-closed saat konteks yayasan tidak teresolusi.
- **Actions & Controllers**: `CreateJenisKaryawanAction` dan `CreateJabatanTambahanAction` menerima parameter `$yayasanId` yang dihitung otomatis dari aktor login di Controller (bukan input form / DTO). Guard delete dihitung ulang per-yayasan pemilik baris.
- **Fixture Test & Seeder**: Rewrite total fixture `JabatanTambahanMasterCrudTest.php` dengan helper `actingAsJabatanTambahanManager()` dan penambahan factory `JabatanTambahanMasterFactory`. Pembaruan `JenisKaryawanMasterSeeder` dan `JabatanTambahanMasterSeeder` untuk menyertakan `yayasan_id`.
- **Commit range**: `e9c79d8b..f98bbca7` (3 commit, base sebelum plan `554f0b02`).
- **Full test suite akhir**: **2958 passed, 4 failed (8023 assertions)** — 4 kegagalan pre-existing pada seeder demo/day-of-week, 0 regresi.
- **Spec**: `.agents/specs/2026-09-07-jenis-karyawan-jabatan-tambahan-per-yayasan.md`
- **Plan**: `.agents/plans/2026-09-07-jenis-karyawan-jabatan-tambahan-per-yayasan.md`
- **Handoff Log**: `.agents/logs/2026-09-07-jenis-karyawan-jabatan-tambahan-per-yayasan.md`

---

## 🟢 Perbaikan Antarmuka Halaman Index Siswa — SELESAI (7 September 2026)

**Latar belakang** — Client request: informasi scope yayasan pada header halaman, penggabungan kolom NIS dan Nama siswa menjadi satu kolom, penambahan kolom Lembaga, serta perbaikan responsivitas pagination mobile/desktop.
- **Header Scope Yayasan**: Draf spec awal minta teks `<h1>` berubah jadi `SISWA SEMUA LEMBAGA`/`SISWA [NAMA LEMBAGA]`, TAPI diputuskan ulang saat implementasi (`d109e2f1`) untuk menghindari redundansi teks — `<h1>` tetap ringkas `Siswa`, info scope dipindah ke badge terpisah di sampingnya (`Semua Lembaga` warna ungu, atau nama lembaga aktif warna brand). User lembaga-scope tidak menampilkan badge sama sekali. Perilaku final ini SUDAH BENAR secara UX (dikonfirmasi review), spec/plan belum diperbarui untuk mencerminkan perubahan ini — treat dokumen ini sebagai sumber kebenaran yang akurat, bukan spec/plan.
- **Penggabungan Kolom NIS & Nama**: Kolom terpisah NIS dan Nama disatukan menjadi kolom `Siswa` (nama di atas, NIS monospace di bawah).
- **Kolom Lembaga**: Ditambahkan kolom `Lembaga` dengan eager-load `'lembaga'` di `SiswaController::index()` untuk mencegah N+1 query.
- **Pagination Responsif**: Template `resources/views/pagination/tailadmin.blade.php` diperbaiki menggunakan dual-breakpoint (mobile touch bar + desktop pills) — berdampak ke SEMUA halaman admin/portal yang memakai template ini (bukan cuma Siswa), diverifikasi aman lewat review (tidak ada halaman lain yang bergantung pada markup lama).
- **Commit range**: `4073e09e..d109e2f1` (branch `rbac-v2`).
- **Spec**: `.agents/specs/2026-09-07-siswa-index-ui-perbaikan.md`
- **Plan**: `.agents/plans/2026-09-07-siswa-index-ui-perbaikan.md`
- **Handoff Log**: `.agents/logs/2026-09-07-siswa-index-ui-perbaikan.md`

---

## 🟢 Perbaikan Tautan Orang Tua-Siswa & Konsistensi Identitas Person — SELESAI (7 September 2026)

**Latar belakang** — Laporan user: "Data Induk Orang Tua, filter 0 tapi saat dicek detail ada yang tertaut anaknya." Investigasi mendalam menemukan 4 bug fungsional berbeda pada relasi Siswa-OrangTua-Person:
- **Keputusan Bisnis**: Urutan pendaftaran resmi adalah **Siswa didaftarkan lebih dulu, Orang Tua ditautkan kemudian dari halaman Siswa** (ini alur pendaftaran keluarga utama, bukan sekunder). Halaman Data Induk Orang Tua berfungsi sebagai alat kelola profil sekunder.
- **Bug #3 (Prioritas Tertinggi — Pencarian NIK Gagal Total)**: `SiswaOrangTuaController::cari()` dan `store()` gagal total menemukan akun User Orang Tua karena tidak menggunakan `withoutGlobalScopes()`, diperparah oleh `AkunOrangTuaGenerator::buat()` yang tidak pernah mengisi `yayasan_id` pada `User` yang dibuat. Diperbaiki dengan `User::withoutGlobalScopes()` dan pengisian `yayasan_id` di generator akun. Dua fixture test lama yang false-negative diperbaiki dengan TDD ketat.
- **Bug #2 (Over-Count `siswa_count` Lintas Lembaga di Index)**: Relasi `OrangTua::siswa()` menggunakan `withoutGlobalScopes()` di level definisi, menyebabkan `withCount('siswa')` di `OrangTuaController::index()` menghitung anak di semua lembaga/yayasan. Diperbaiki dengan `withCount(['siswa' => ...])` yang ter-scope ke lembaga/yayasan aktor. Halaman `edit()` sengaja tetap lintas lembaga by design (`Person::YayasanScope`). Juga memperbaiki `OrangTua::scopeOrderByNama()` dan `OrangTuaFactory`.
- **Bug #1 (Filter "Ada Anak"/"Belum Ada Anak" Basi di Index)**: Filter diubah dari snapshot array JavaScript Alpine.js murni menjadi query parameter server-side `?anak=ada|belum` dengan navigasi link dan perhitungan badge akurat (`totalAda`, `totalBelum`).
- **Commit range**: `abbf4aa5..f7375241` (3 commit, base sebelum plan `53e8b570`).
- **Di luar scope**: UI admin untuk `MergePersonsAction` sengaja tidak dibangun (fokus pada pencegahan duplikat di titik pembuatan baru).
- **Full test suite akhir**: **2946 passed, 4 failed (7984 assertions)** — 4 kegagalan pre-existing pada seeder demo/day-of-week, 0 regresi.
- **Spec**: `.agents/specs/2026-09-07-orang-tua-siswa-person-tautan.md`
- **Plan**: `.agents/plans/2026-09-07-orang-tua-siswa-person-tautan.md`
- **Handoff Log**: `.agents/logs/2026-09-07-orang-tua-siswa-person-tautan.md`

---

## 🟢 Perbaikan Visibilitas Menu & Keamanan Scope Yayasan/Lembaga — SELESAI (7 September 2026)

**Client request** — "fitur yang hanya bisa diakses lembaga tidak usah tampil di yayasan, kalau ada cenderung diklik. Contoh rekap kehadiran yang cuma aktif di lembaga jangan muncul di yayasan, atau di yayasan tampil rekapan semua lembaga." — ✅ **SELESAI TOTAL 7 September 2026** (branch `rbac-v2`, 7 task). Diaudit lewat 3 subagent riset paralel yang membaca kode semua controller di sidebar, lalu dikerjakan sebagai 1 plan berisi 4 kategori independen:
- **Kategori A (Bug Keamanan Kritis)**: 3 controller (`KasusAksesLogController`, `KasusTerhapusController`, `OrangTuaController`) untuk aktor scope **yayasan** sama sekali tidak membatasi query ke yayasan milik aktor — menampilkan data milik yayasan LAIN. Ditutup dengan pola `when($user->widestScopeLevel() === 'yayasan', ...)`.
- **Kategori B (Gerbang Identitas Menu)**: 5 menu sidebar Ruang Guru sekarang wajib `hasRole('guru')`, bukan hanya permission mentah — mencegah user non-guru (mis. super admin) mengakses menu guru-specific.
- **Kategori C (Guard Menu Kontekstual)**: menu & endpoint "Scan QR" disembunyikan + di-guard 422 saat aktor yayasan berada di mode "Semua Lembaga" (belum memilih lembaga aktif via switcher topbar).
- **Kategori D (Agregat Salah saat Mode "Semua Lembaga")**: 8 controller (`VirtualAccountController`, `ManualPaymentController`, `AttendanceConfigurationController`, `GedungController`, `RuanganController`, `KategoriAsetController`, `PengajuanPengadaanController`, `RaporController`) memakai `where('lembaga_id', $lembagaId)` wajib yang secara tidak sengaja jadi `WHERE lembaga_id IS NULL` saat `$lembagaId` null (mode "Semua Lembaga") — diperbaiki jadi `when($lembagaId !== null, ...)` supaya kartu ringkasan statistik & dropdown menampilkan agregat/label yang benar lintas semua lembaga milik yayasan.
- **Commit range**: `85c7ea3d..68915faf` (15 commit, base sebelum plan `8b949349`).
- **Di luar scope (belum diputuskan/dikerjakan)**: 3 controller master data global lintas-SEMUA-yayasan (`JenisKaryawanMasterController`, `JabatanTambahanMasterController`, `WhatsAppTemplateController`) yang butuh keputusan produk terpisah; dropdown guru/mapel `JadwalPelajaranController` (minor, tervalidasi ulang saat submit); method non-`index()` di `VirtualAccountController`/`ManualPaymentController`; modul SPMB/PPDB (sengaja dibekukan).
- Full test suite akhir: **2942 passed, 4 failed (7964 assertions)** — 4 kegagalan terbukti pre-existing (seeder demo `M3DemoDataSeederTest`, `PresensiSeederTest`, `SesiPembelajaranSeederTest`, day-of-week/idempotency-dependent), tidak ada file-nya yang disentuh plan ini.
- Spec: `.agents/specs/2026-09-07-scope-yayasan-lembaga-menu-fix.md`
- Plan: `.agents/plans/2026-09-07-scope-yayasan-lembaga-menu-fix.md`
- Handoff Log: `.agents/logs/2026-09-07-scope-yayasan-lembaga-menu-fix.md`
- **Update 7 September 2026 (Scan ulang pasca-plan)**: audit menyeluruh SEMUA menu sidebar + nav button (topbar, dashboard), bukan cuma yang sempat ditemukan sebelumnya — checklist lengkap per-menu ("Semua Lembaga" & "Switch Lembaga") ada di `.agents/logs/2026-09-07-audit-scope-yayasan-lembaga-sidebar.md`. **4 hal nyata masih belum diperbaiki** (di luar 3 item 🟡 yang sudah dicatat di atas): `VirtualAccountController::calonGenerate()`, `::generate()`, `App\Exports\VirtualAccountExport` (semua 3 pola bug identik `index()` tapi belum disentuh krn sengaja di luar scope Task 4), dan bel notifikasi topbar "Tagihan Perlu Ditinjau" (`resources/views/layouts/topbar.blade.php:14-23`, dipaksa 0 bukan diagregat saat mode "Semua Lembaga" — baru ditemukan, di luar sidebar sama sekali). Belum dikerjakan — menunggu perintah per-menu berikutnya.

---

## 🟢 Rombak Total Manual Book Akademik — SELESAI (6 September 2026)

**Pembaruan Dokumentasi User-Facing (Modul Akademik)** — ✅ **SELESAI TOTAL 6 September 2026** (branch `akademik-v2`).
- **Latar belakang & Hasil**: Manual book akademik sebelumnya (30-31 Juli 2026) usang dan tidak mencerminkan perubahan besar: RBAC v2, portal Ruang Orang Tua & Ruang Siswa, perbaikan jalur penilaian PAUD (`ElemenCp`), penambahan field Keterangan izin/sakit pada Jurnal KBM, banner & tabel peringatan kelengkapan nilai sebelum pengajuan rapor, serta fix nama Wali Kelas dan Kepala Sekolah di PDF rapor. Seluruh 8 bab lama dihapus dan ditulis ulang dari nol dengan struktur 5-bagian standar + penambahan 2 bab portal baru dan 1 halaman indeks alur kerja per-role.
- **Daftar Dokumen (`docs/manual-book/akademik/`)**:
  1. `README.md` — Peta Alur Kerja per Role (Admin Yayasan, Operator Akademik, Guru Mapel, Guru PAUD/TK, Wali Kelas, Wakasek Kurikulum, Kepala Sekolah, Orang Tua, Siswa).
  2. `00-setup-lembaga.md` — Setup Lembaga, Unit Sekolah, Profil Lembaga, dan Akun Pegawai.
  3. `01-data-master.md` — Tahun Ajaran, Semester, Mata Pelajaran, Rombel/Kelas, Siswa, Kalender Lembaga.
  4. `02-penjadwalan.md` — Pola Jam, Jam Pelajaran, Penautan ke Kelas, Roster Mingguan.
  5. `03-presensi-jurnal.md` — Presensi Sesi KBM Harian, Jurnal Mengajar Guru, Catatan Alasan Izin/Sakit.
  6. `04-asesmen-nilai.md` — Komponen Penilaian (TP), Asesmen Sumatif Mapel, Sub-bagian Khusus Sisi Guru PAUD (Elemen CP & Nilai Naratif).
  7. `05-rekap-rapor.md` — Rekap Nilai Kelas, Banner Peringatan Kelengkapan Nilai Wali Kelas, Catatan Wali Kelas & Ekstrakurikuler, Verifikasi Waka Kurikulum, Persetujuan Kepsek, Cetak Rapor PDF.
  8. `06-kenaikan-kelas.md` — Pemetaan Status Siswa (Naik/Tinggal/Lulus) & Pembagian Rombel Baru (Tindakan Permanen).
  9. `07-ruang-orang-tua.md` — Portal Mandiri Orang Tua: Nilai Anak & Unduh Rapor PDF, Jadwal Anak, Riwayat Izin/Sakit.
  10. `08-ruang-siswa.md` — Portal Mandiri Siswa: Nilai & Rapor Saya, Jadwal Pelajaran Saya (Matriks/Daftar), Presensi Saya.
  11. `lampiran-lintas-lembaga.md` — Kalender Akademik Nasional & Libur Terpusat Yayasan.
- **Komponen Pendukung**:
  - Seeder PAUD demo: `database/seeders/LembagaPaudDemoSeeder.php` (TK Permata, `guru.tk@demo.test`, komponen Elemen CP, skenario nilai 1 siswa sengaja kosong untuk verifikasi kelengkapan nilai).
  - Skrip tangkapan layar Playwright: `scripts/manual-book-screenshots.mjs` (36 screenshot asli otomatis).
  - Single-page HTML Artifact builder: `scripts/manual-book-artifact/build.mjs` (output `scripts/manual-book-artifact/dist/manual-book-akademik.html`, ukuran ~5.5 MB, semua aset gambar ter-embed base64).
  - Target URL Artifact: `https://claude.ai/code/artifact/92e6b639-d846-48ad-9e42-2a270abc5e03`.
  - Spec & Plan: `.agents/specs/2026-09-05-rombak-manual-book-akademik.md`, `.agents/plans/2026-09-05-rombak-manual-book-akademik.md`.
  - Handoff Log: `.agents/logs/2026-09-05-rombak-manual-book-akademik.md`.

---

## 🟢 Temuan Bug Prioritas Tinggi — SELESAI TOTAL (28 Agustus 2026)

**Akun staf lembaga via form Pengguna generik jadi "profil belum tertaut"** — ✅ **SELESAI TOTAL 28 Agustus 2026** (dashboard sudah fix 26 Agustus, root cause di `UserController` fix 28 Agustus).

**Root cause yang sudah diperbaiki (28 Agustus 2026, branch `rbac-v2`)**: `UserController::assignableRoles()` sekarang mengeluarkan role `guru` dari daftar yang bisa dipilih di form Pengguna generik — role ini HANYA bisa didapat lewat `Admin → Guru` (`GuruController::store()`, sudah transactional: `User`+`Guru`+role sekaligus). `store()`/`update()` menolak eksplisit kalau `roles` mengandung `guru` (pesan mengarahkan ke `Admin → Guru`). Fix kritis tambahan di `update()`: role `guru` milik user existing dipaksa tetap disertakan ke `syncRoles()` (`$rolesToPersist`) supaya admin yang sekadar menambah role fungsional lain tidak diam-diam mencabut identitas guru + carrier `pegawai_lembaga`-nya. `guru_bk`/`wali_kelas`/8 role administratif lain SENGAJA tidak ikut dibatasi — audit kode (`grep hasRole(...)`, 0 hasil untuk semuanya) membuktikan tidak satu pun butuh profil `Guru`/`Karyawan`. Full suite 2426 passed, 0 failed.

**Yang sudah diperbaiki sebelumnya (26 Agustus 2026)**: `DashboardController` (branch `pegawai_lembaga`/`pegawai_yayasan`, `DashboardController.php:249`) resolve profil pegawai lewat `Guru` ATAU `Karyawan`. 2 bug turunan ikut fix: `PenugasanShift` query hardcode morph type, `Karyawan::class` tanpa `use` import.

**Keterbatasan yang masih ada (tidak berubah)**: `DashboardStatsService::statistikSisaKuotaCuti()` masih Karyawan-only — staf ber-profil Guru akan selalu tampil "Sisa Kuota Cuti: Belum dikonfigurasi". Effort Kecil kalau mau dilengkapi.

Lokasi: `app/Http/Controllers/Admin/DashboardController.php:248-295`, `app/Http/Controllers/Admin/UserController.php` (`assignableRoles()`, `store()`, `update()`).

**Peta tabel PTK vs Karyawan** (referensi arsitektur):
| Jenis staf | Tabel profil benar | Cara buat |
|---|---|---|
| Guru, Kepsek, Admin Akademik/Administrasi/Sarpras, Guru BK (fungsi akademik/manajerial) | `Guru` (kolom `jenis_ptk`: guru_kelas/guru_mapel/kepala_sekolah/tenaga_administrasi/guru_bk) | `Admin → Guru` |
| Satpam, cleaning service, sopir, psikolog, konselor pool (staf umum) | `Karyawan` (`jenis_karyawan_id` → `JenisKaryawanMaster`, fleksibel) | `Admin → Karyawan` |

---

## 🟢 Technical Debt `TD-AKADEMIK-001` — SELESAI (27 Agustus 2026) — `ElemenCp` vs `MataPelajaran.tipe=aspek_perkembangan`

**Resolusi**: audit lanjutan (26-27 Agustus 2026, dikoreksi 2x) menemukan `aspek_perkembangan` BUKAN sistem aktif yang bersaing dengan `ElemenCp` — CRUD-nya berfungsi & diuji, TAPI tidak pernah terintegrasi ke defaulting `assessment_type` (`CreateKomponenPenilaianAction` cuma lihat `subjek_type`, buta terhadap `MataPelajaran.tipe`) dan tidak pernah dipakai data nyata (seluruh seeder demo cuma `bentuk_pendidikan=SD`, tidak ada lembaga PAUD sama sekali). Keputusan: **hapus total** `TipeMataPelajaran::AspekPerkembangan` sampai level `ENUM` database (bukan cuma kode aplikasi), `ElemenCp` jadi satu-satunya jalur resmi. Dieksekusi lewat `.agents/plans/2026-08-27-td-akademik-001-hapus-aspek-perkembangan.md`, full suite 2240 passed/0 failed, handoff log `.agents/logs/2026-08-27-td-akademik-001-hapus-aspek-perkembangan.md`.

**Catatan lama (arsip, sebelum resolusi di atas):**

Saat mengerjakan Sprint 1 fondasi akademik multi-jenjang (subjek polymorphic `MataPelajaran`|`ElemenCp`), ternyata desain ASLI modul Akademik (`docs/superpowers/specs/2026-07-24-presensi-asesmen-design.md`, ditulis 1 bulan sebelum Sprint 1) **sudah** merancang solusi untuk PAUD-tanpa-mata-pelajaran: `MataPelajaran.tipe = 'aspek_perkembangan'` (per-lembaga, dibuat lewat halaman Mata Pelajaran biasa yang sudah ada — `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`), dengan kutipan eksplisit *"struktur relasi ke Jadwal, Sesi Pembelajaran, dan Asesmen tetap satu jalur untuk keduanya"* — artinya cukup satu FK `MataPelajaran` biasa untuk semua jenjang, tidak perlu model terpisah.

**Implikasi**: solusi Sprint 1 (bikin model `ElemenCp` baru + polymorphic `subjek_type`/`subjek_id`) mungkin lebih rumit dari yang seharusnya — desain minimal yang konsisten dengan arsitektur asli adalah cukup jadikan `mata_pelajaran_id` nullable-tapi-tetap-FK-biasa ke `MataPelajaran`, buang kolom `elemen_cp` enum yang redundan, TANPA perlu morph sama sekali.

**Bonus temuan**: `ElemenCp` Sprint 1 cuma punya **3 elemen** (nilai_agama_moral, jati_diri, literasi_steam), padahal desain asli menyebut **"6 aspek STPPA"** (Standar Tingkat Pencapaian Perkembangan Anak, standar resmi PAUD Indonesia) — jadi selain kemungkinan arsitektur berlebih, datanya sendiri juga tidak lengkap dibanding standar resminya.

**Konteks kurikulum lain yang sudah diantisipasi tim sebelumnya** (dari desain yang sama, §10 Out of Scope):
- **K13/KTSP**: *"`komponen_penilaian` sudah dinamai generik supaya siap menampung KD, tapi alur/agregasi nilai KD belum dirancang detail"* — multi-kurikulum (bukan cuma Kurikulum Merdeka) sudah diantisipasi, belum dibangun.
- **Kemenag (madrasah)**: enum `KelompokMataPelajaran` sudah ada `AgamaKemenag` ("KMA 450") dan `ProjekP5Ppra` (PPRA = versi Kemenag dari P5) — variasi kurikulum antar naungan sudah nyata di kode, belum ada alur kerja penuh.
- **P5**: *"struktur penilaian per Dimensi P5, lintas mapel/kolaboratif, berbeda dari asesmen mapel reguler"* — konsisten dengan keputusan sebelumnya bahwa P5 butuh domain terpisah (bukan sekadar subjek baru di bawah KomponenPenilaian).

**Keputusan (26 Agustus 2026)**: TIDAK dibongkar sekarang — `ElemenCp` tetap dipakai apa adanya (sudah jalan, sudah full-test-covered). Dicatat sbg technical debt untuk direview nanti (kemungkinan konsolidasi `ElemenCp` → `MataPelajaran.tipe=aspek_perkembangan`, atau sebaliknya memperkaya `ElemenCp` jadi 6 aspek + dukungan tambahan). Sprint 3 (Curriculum Phase) tetap lanjut di atas fondasi Sprint 1 yang ada, dengan catatan desainnya WAJIB config-driven (bukan hardcoded) mengingat variasi kurikulum yang sekarang terkonfirmasi nyata dari riset ini.

---

## 🟢 Technical Debt `TD-AKADEMIK-002` — SELESAI (26 Agustus 2026)

Retrofit `FaseDefaultMappingController` + `KelasController` (full, termasuk field pre-existing) ke FormRequest+DTO+Action, `Support/` dipindah ke `Services/`. Dieksekusi lewat `.agents/plans/2026-08-26-td-akademik-002-retrofit-skill-standard.md`, full suite 2238 passed/0 failed, tanpa satu pun perubahan behavior/HTTP-status (dibuktikan seluruh test existing termasuk 10 test ownership-check `KelasCrudTest` tetap hijau tanpa assertion diubah). Handoff log `.agents/logs/2026-08-26-td-akademik-002-retrofit-skill-standard.md`.

**Catatan lama (arsip, sebelum resolusi di atas):**

Setelah Sprint 4 (Academic Profile Service) selesai, dilakukan audit terhadap seluruh kode Sprint 1-4 (`ElemenCp`/subjek polymorphic, `AssessmentType`, `Fase`/`FaseDefaultMapping`, `AcademicProfile`) dibandingkan skill arsitektur resmi project (`laravel-feature-standard`). Hasilnya: struktur besar (Domain-Oriented, `Actions/`/`DataTransferObjects/`/`Models/`/`Services/`, tenant isolation eksplisit, test authorization+tenant-isolation) sudah konsisten dengan skill — TAPI ada beberapa deviasi nyata:

1. **Folder `Support/`** (`app/Domains/Akademik/Support/SubjekPenilaianKey.php`, `AcademicProfile.php`) bukan folder resmi di skill (skill cuma daftar `Actions/DataTransferObjects/Events/Listeners/Models/Services/ViewModels`). Perlu diputuskan: resmikan `Support/` sbg folder ke-8 di skill (kalau memang polanya berulang dan valid — stateless helper tanpa side-effect), atau pindahkan isinya ke `Services/`.
2. **Tidak ada DTO** untuk `FaseDefaultMapping`/`Kelas.fase_id` (Sprint 3-4) — `FaseDefaultMappingController`/`KelasController` pakai `$request->validate()` inline lalu array langsung ke Eloquent, melanggar §4 skill (DTO sbg boundary HTTP↔domain).
3. **Tidak ada Form Request class terpisah** untuk kedua controller itu — melanggar §5 skill (semua validasi HTTP wajib lewat Form Request).

**Kenapa dibiarkan saat itu**: poin 2-3 murni mengikuti pola `Admin\KelasController` yang **sudah begitu sebelum Sprint 1 ada** (tidak pernah pakai DTO/FormRequest sama sekali) — bukan pelanggaran baru yang diperkenalkan sprint ini, tapi konsistensi dengan kode existing yang sudah lebih dulu menyimpang dari skill. Poin 1 (`Support/`) murni keputusan baru di Sprint 1 & 4.

**Keputusan (26 Agustus 2026)**: TIDAK diretrofit sekarang. Sprint 5 (Report Engine) WAJIB mengikuti skill secara ketat (FormRequest eksplisit + DTO + tidak menambah `Support/` lagi tanpa persetujuan). Retrofit Sprint 1-4 (resmikan/hapus `Support/`, tambah FormRequest+DTO ke `FaseDefaultMappingController` & bagian `KelasController` yang disentuh) jadi task/sprint TERPISAH setelah Sprint 5 — bukan dikerjakan sambil lalu di Sprint 5.

---

## 🔵 Roadmap Kurikulum Dinamis — Temuan Audit Menu Akademik (27 Agustus 2026)

Setelah Sprint 1-5 Fondasi Akademik Multi-Jenjang + `TD-AKADEMIK-001`/`002` selesai, dilakukan audit terhadap 17 fitur aktual di menu Ruang Guru/Akademik/Data Induk (dibaca langsung dari kode: controller, route, request, service — bukan dari dokumentasi) untuk menjawab: apakah sistem sudah bisa menghandle berbagai jenis lembaga & kurikulum turunannya secara dinamis?

**Kesimpulan**: BELUM. Fondasi data (subjek polymorphic, `assessment_type`, `Fase`) sudah fleksibel, tapi konsumen di ujung (rekap rapor, label kelulusan, pemilihan kurikulum) belum semuanya ikut fleksibel — sebagian hardcoded per `bentuk_pendidikan`, sebagian (pemilihan kurikulum aktif) belum disentuh sama sekali.

**Fakta bisnis kunci yang mendasari prioritas di bawah** (bukan asumsi — ini SOP resmi rollout kurikulum di Indonesia):
- Transisi Kurikulum Merdeka nasional (2022-2024) berjalan **bertahap per tingkat, bukan serentak per sekolah** — mis. kelas 1 & 4 duluan pakai Merdeka sementara kelas lain di sekolah yang SAMA masih K13. Artinya "kurikulum aktif" adalah atribut `(lembaga, tahun_ajaran, tingkat)`, bukan atribut per-lembaga polos.
- K13 (`KI`→`KD` per mapel per semester, 4 domain KI) dan Kurikulum Merdeka (`CP` per Fase →`TP` bebas turunan guru) punya STRUKTUR penilaian berbeda, bukan cuma istilah. `komponen_penilaian` sistem kita sekarang bergaya Merdeka ("TP") — salah sebutan & salah struktur kalau dipakai lembaga K13.
- KMA 450 (Kemenag/madrasah) adalah LAPISAN TAMBAHAN di atas Merdeka (mapel agama wajib + P5 versi Islami/PPRA), sudah diantisipasi di enum `KelompokMataPelajaran::AgamaKemenag`/`ProjekP5Ppra` tapi belum ada workflow-nya.
- Data rapor lama harus tetap benar walau kebijakan kurikulum lembaga berubah nanti — sama prinsip `Kelas.fase_id` (snapshot immutable), bukan config yang diam-diam mengubah histori.

### Daftar Prioritas (urutan berdasarkan dependency nyata + dampak operasional, bukan kerapian kode)

| # | Item | Kebutuhan/Alur Bisnis | Flow Penggunaan | Status | Effort |
|---|---|---|---|---|---|
| 1 | Entitas `KurikulumFramework` + assignment per `(lembaga, tahun_ajaran, tingkat)`, snapshot immutable ke `Kelas` (sama prinsip `fase_id`) | Fondasi mutlak — tanpa ini sistem tidak pernah tahu satu kelas pakai K13 atau Merdeka, semua keputusan turunan (istilah TP/KD, Fase vs tingkat-linear, template rapor) tidak punya pijakan. Mengakomodasi transisi bertahap per tingkat (fakta SOP nasional di atas). | Awal tahun ajaran, admin yayasan/lembaga tentukan kurikulum berlaku per kombinasi bentuk_pendidikan+tingkat (mirip UI `FaseDefaultMapping` yang sudah ada) → saat `Kelas` dibuat, `kurikulum_id` di-snapshot immutable → seluruh alur berikutnya baca dari snapshot ini. | ✅ SELESAI (27 Agustus 2026) — lihat `.agents/specs/2026-08-27-akademik-kurikulum-framework-priority1.md` | Tinggi |
| 2 | `RaporCalculationService` type-aware (bukan numeric-only) | BLOCKER OPERASIONAL langsung, bukan cuma cacat arsitektur — lembaga PAUD/kelas naratif-predikat melihat Rekap Rapor & Persetujuan Rapor KOSONG TOTAL hari ini. Janji "dibereskan Sprint 5 Report Engine" tidak ditepati krn Sprint 5 berubah scope drastis (jadi cuma konsolidasi `templateUntukJenjang`). | Admin/wali kelas buka Rekap Rapor → sistem deteksi `assessment_type` per komponen → render strategi sesuai (rata-rata utk numeric, distribusi % utk predicate, completion-rate utk narrative) — bukan satu formula rata-rata dipaksa ke semua tipe. | ✅ SELESAI (27 Agustus 2026) — lihat `.agents/specs/2026-08-27-akademik-rapor-calculation-type-aware.md` | Kecil-Sedang |
| 3 | Kelulusan/Rapor Akhir untuk PAUD/TK + keputusan sadar soal SLB | "Surat Keterangan Lulus TK" itu dokumen administratif WAJIB (dibutuhkan daftar SD), bukan nice-to-have. `isTingkatAkhir()` sekarang tidak pernah `true` utk KB/TPA/SPS/TK → label "Keterangan Kelulusan" TIDAK PERNAH muncul di rapor PAUD. | Akhir tahun ajaran genap, TK kelompok B (tingkat akhir PAUD) cetak rapor dgn label "Keterangan Kelulusan" — sama alur SD kelas 6/SMP kelas 9/SMA-SMK kelas 12 yg sudah didukung. Soal SLB (tetap pakai template SD, atau bikin sendiri) BUTUH KEPUTUSAN eksplisit user sebelum dikerjakan. | ✅ SELESAI (27 Agustus 2026) — lihat `.agents/specs/2026-08-27-akademik-kelulusan-paud-slb.md` | Kecil |
| 4 | Struktur ganda KD (K13) vs CP+TP (Merdeka) di form Komponen Penilaian | Begitu #1 ada, sistem tahu kelas mana K13 vs Merdeka — tapi UI/struktur data `komponen_penilaian` sekarang cuma satu bentuk (bergaya Merdeka). Lembaga K13 akan lihat form salah istilah & salah struktur (tidak ada pemisahan domain KI pengetahuan/keterampilan). | Form Komponen Penilaian menyesuaikan label & field berdasar `kurikulum_id` snapshot kelas — "Tambah KD" dgn domain KI utk kelas K13, "Tambah TP" dgn referensi CP-Fase utk kelas Merdeka. | 🟡 SENGAJA DITUNDA (27 Agustus 2026) — lihat catatan di bawah | Tinggi |
| 5 | Workflow Kemenag (KMA 450) — mapel wajib agama + P5/PPRA sbg struktur penilaian terpisah (bukan `subjek_type` baru di `KomponenPenilaian`) | Madrasah butuh mapel wajib tambahan dan proyek P5/PPRA yg dinilai TIM guru lintas mapel kolaboratif per siswa/kelompok — beda total dari asesmen mapel reguler per-subjek. Sudah diputuskan sejak desain awal modul bahwa P5 butuh domain sendiri. | Perlu entitas baru terpisah dari `KomponenPenilaian` (bukan sekadar `subjek_type` ketiga). Relevan HANYA kalau ada pelanggan madrasah/Kemenag nyata. | Belum Ada | Tinggi |
| 6 | Asesmen Diagnostik & Formatif (bukan cuma 3 varian Sumatif di `JenisAsesmen::v1Didukung()`) | Ironis: Kurikulum Merdeka justru MENEKANKAN asesmen diagnostik (pemetaan kesiapan belajar di awal) dan formatif (selama proses, bukan cuma akhir) — fitur pedagogi inti Merdeka ini belum ada sama sekali di form guru. | Guru buat asesmen diagnostik awal semester (kognitif/non-kognitif) dan formatif di tengah proses belajar (bukan utk nilai rapor, utk penyesuaian metode ajar). | ✅ SELESAI (27 Agustus 2026) — lihat `.agents/specs/2026-08-27-akademik-asesmen-diagnostik-formatif.md` | Sedang |
| 7 | Kejelasan UI Mata Pelajaran vs `ElemenCp` utk admin PAUD | Sejak `TipeMataPelajaran::AspekPerkembangan` dihapus (`TD-AKADEMIK-001`), admin PAUD yang buka menu "Mata Pelajaran" akan bingung kenapa tidak bisa dipakai utk aspek perkembangan — tidak ada indikasi UI yang menjelaskan. | Tambah petunjuk/catatan di UI: kalau `bentuk_pendidikan` lembaga PAUD, menu Mata Pelajaran arahkan ke "kelola Elemen Capaian lewat Komponen Penilaian". | ✅ SELESAI (27 Agustus 2026) — lihat `.agents/specs/2026-08-27-akademik-ux-mapel-vs-elemencp-paud.md` | Kecil (UX polish) |

**Prioritas #1 SELESAI (27 Agustus 2026)**: `KurikulumFramework` (enum K13/Merdeka) + `KurikulumAssignment` (assignment per lembaga+tahun_ajaran+bentuk_pendidikan+tingkat, resolver 4-level precedence, throw kalau tidak ketemu) + snapshot immutable `Kelas.kurikulum` (di-set otomatis saat create, terkunci setelahnya, tidak pernah diubah `UpdateKelasAction`). Halaman admin "Pengaturan Kurikulum" (`admin.kurikulum-assignment.*`) sudah bisa dipakai. Dieksekusi lewat `.agents/plans/2026-08-27-akademik-kurikulum-framework-priority1.md`.

**Prioritas #2 SELESAI (27 Agustus 2026)**: `RaporCalculationService` sekarang type-aware (numeric=rata-rata berbobot, predicate=modus dgn tie-break, narrative=completion-rate), precedence numeric>predicate>narrative per sel. Ikut ditemukan & diperbaiki bug independen yang lebih luas dari catatan awal: kolom per-mapel di Rekap Rapor & Persetujuan Rapor selalu kosong utk SEMUA jenjang (bukan cuma PAUD) krn key-mismatch (`$mapel->id` vs composite `SubjekPenilaianKey`) — sudah diperbaiki sekaligus. Dieksekusi lewat `.agents/plans/2026-08-27-akademik-rapor-calculation-type-aware.md`.

**Prioritas #3 SELESAI (27 Agustus 2026)**: `isTingkatAkhir()` sekarang mengenali TK tingkat B sbg tingkat akhir (Keterangan Kelulusan PAUD) — `KB`/`TPA`/`SPS` sengaja tetap tidak pernah dianggap tingkat akhir. Ikut ditemukan & diperbaiki gap yang lebih mendasar: template PDF `paud.blade.php` ternyata belum pernah punya section "Keterangan Kelulusan"/"Keterangan Kenaikan Kelas" sama sekali (beda dari SD/SMP-SMA/SMK) — sudah ditambahkan. Keputusan SLB tetap pakai template SD diformalkan jadi final (bukan fallback compatibility). Dieksekusi lewat `.agents/plans/2026-08-27-akademik-kelulusan-paud-slb.md`.

**Prioritas #4 SENGAJA DITUNDA (27 Agustus 2026)**: audit lanjutan (grep `Kompetensi Inti|Kompetensi Dasar|kompetensi_inti|kompetensi_dasar|\bKI\b|\bKD\b`) mengonfirmasi infrastruktur K13 di sistem ini BENAR-BENAR NOL — `Fase` model tanpa relasi apa pun, tidak ada tabel/model KI/KD, CP untuk mapel reguler murni teks bebas di `KomponenPenilaian.kode`/`deskripsi`. `KurikulumFramework::K13` cuma nilai enum placeholder tanpa implementasi. **Tidak ada pelanggan K13 nyata saat ini** (semua data seeder demo Kurikulum Merdeka; per Permendikbudristek 12/2024 Merdeka sudah jadi Kurikulum Nasional, K13 tinggal transisi terbatas). Keputusan: tunda sampai ada pelanggan K13 nyata — pola sama seperti Prioritas #5 (Kemenag). Membangun struktur KI/KD tanpa kebutuhan konkret berisiko mengulang pola `TD-AKADEMIK-001` (`aspek_perkembangan` dibangun penuh lalu dihapus krn tidak ada data nyata) — bentuk KD yang benar (per-mapel? per-tingkat? 4 domain KI dipakai semua atau sebagian?) tidak bisa divalidasi tanpa pelanggan nyata yang memberi requirement sungguhan.

**Prioritas #6 SELESAI (27 Agustus 2026)**: Guru sekarang bisa membuat Asesmen Diagnostik Kognitif, Diagnostik Non-Kognitif, dan Formatif (sebelumnya cuma 3 varian Sumatif). `JenisAsesmen::v1Didukung()` di-retire, diganti `cases()` (form/validasi, semua 6 jenis) dan `masukRapor()` (filter rapor, tetap 3 Sumatif). Ikut ditemukan & diperbaiki blocker kritis: `RaporCalculationService::hitungRekapKelas()` sebelumnya sama sekali tidak memfilter `jenis` Asesmen — sudah diperbaiki sekaligus supaya Diagnostik/Formatif tidak pernah mencemari rapor. Dieksekusi lewat `.agents/plans/2026-08-27-akademik-asesmen-diagnostik-formatif.md`. **Tindak lanjut (27 Agustus 2026)**: audit sistematis penuh (Laravel Boost `database-schema` + grep menyeluruh seluruh `app/`, termasuk Jobs/Console/Notifications/Exports) menemukan 3 consumer lain yang belum ikut filter `JenisAsesmen::masukRapor()` saat Prioritas #6 dibuka: `CapaianKompetensiGenerator` (narasi rapor resmi), `DashboardStatsService::statistikProgressRaporKelas()` (progress kesiapan rapor guru), `Admin\DashboardController` (widget nilai terbaru siswa/orang tua). Ketiganya sudah diperbaiki, dikonfirmasi tidak ada consumer tersembunyi lain. Dieksekusi lewat `.agents/plans/2026-08-27-akademik-fix-filter-jenis-asesmen-consumer-lain.md`.

**Prioritas #7 SELESAI (27 Agustus 2026)**: Halaman Mata Pelajaran sekarang menampilkan banner "Catatan untuk PAUD" (hanya utk lembaga `KB`/`TPA`/`SPS`/`TK`) yang menjelaskan aspek perkembangan dikelola lewat Elemen CP di Komponen Penilaian, dengan link langsung ke halaman itu. Seluruh 7 prioritas Roadmap Kurikulum Dinamis kini tuntas ditangani (1/2/3/6/7 SELESAI, 4/5 sengaja ditunda menunggu pelanggan nyata). Dieksekusi lewat `.agents/plans/2026-08-27-akademik-ux-mapel-vs-elemencp-paud.md`.

---

### 🔵 Audit Sistematis Akademik Tahap 2 (27 Agustus 2026)

Lanjutan audit menyeluruh berbantuan Laravel Boost terhadap area Akademik yang belum pernah diaudit dengan lensa multi-kurikulum/multi-jenjang (Kenaikan Kelas, Jadwal Pelajaran/Pola Jam/Kalender, RPP, Ekstrakurikuler, konsistensi KurikulumAssignment/FaseDefaultMapping, notifikasi Akademik). Menghasilkan 10 temuan yang dikelompokkan ke dalam 3 kelompok pengerjaan:

- **Kelompok A (Kritis) — ✅ SELESAI (27 Agustus 2026)**:
  1. **Widget "Jadwal Hari Ini" Guru**: Ditambahkan query scope `JadwalPelajaran::scopeSemesterAktif()` dan filter `semesterAktif()` pada `DashboardController`, mencegah tercampurnya jadwal historis lintas tahun ajaran tanpa menghapus data (menjaga integritas riwayat presensi siswa).
  2. **Koreksi Drift Snapshot `kelas.kurikulum`/`fase_id`**: Dibuat Action `ResyncKurikulumFaseKelasAction`, Controller `ResyncKurikulumFaseController`, dan halaman view admin "Cek & Perbaiki Kurikulum/Fase" (`admin.kurikulum-assignment.resync`) dengan proteksi otorisasi + tenant-isolation & kalkulasi live ulang di server tanpa mengubah sifat snapshot beku default.
  3. **Validasi Master Data Ekstrakurikuler**: Diubah form catatan wali kelas dari input teks bebas menjadi `<select>` terikat ke master data `EkstrakurikulerLembaga` per-lembaga dengan validasi backend `Rule::in` di `StoreCatatanWaliKelasRequest` serta dukungan backward-compat untuk nama historis lama.
  - Plan: `.agents/plans/2026-08-27-akademik-audit-2-kelompok-a.md`
  - Handoff Log: `.agents/logs/2026-08-27-akademik-audit-2-kelompok-a.md`
  - **Tindak Lanjut Fix Susulan (27 Agustus 2026)**: Audit lanjutan khusus layer siswa/orang tua menemukan 2 widget jadwal di `DashboardController` (siswa & orang tua) yang belum difilter semester aktif. `JadwalPelajaran::scopeSemesterAktif()` diperbaiki membypass `TenantScope` pada subquery semester agar tenant-safe saat diakses akun orang tua lintas-lembaga, dan filter `semesterAktif()` diterapkan pada query siswa & orang tua (terverifikasi pada 22 test `DashboardTest.php` lulus penuh).
    - Plan: `.agents/plans/2026-08-27-akademik-audit-2-kelompok-a-lanjutan.md`
    - Handoff Log: `.agents/logs/2026-08-27-akademik-audit-2-kelompok-a-lanjutan.md`

- **Kelompok B (Kenaikan Kelas UX Safety-Net) — ✅ SELESAI (27 Agustus 2026)**:
  1. **Source of Truth Semantik Tingkat Akhir**: Method `isTingkatAkhir(?string $tingkat)` diekstrak ke enum `BentukPendidikan` dengan `match` eksplisit (menjaga isolasi permanen KB/TPA/SPS bukan tingkat akhir per Priority #3) dan didelegasikan dari `RaporPdfDataBuilder`.
  2. **Saran Otomatis "Lulus"**: Dropdown tindakan di halaman Kenaikan Kelas (`admin.kenaikan-kelas.index`) otomatis pre-select "Lulus" jika kelas asal berada di tingkat akhir jenjangnya, disertai label saran informatif.
  3. **Peringatan Live Kurikulum Berbeda**: Penambahan Alpine.js inline pada tabel kenaikan kelas yang memberikan peringatan instan (non-blocking) jika kurikulum kelas tujuan yang dipilih berbeda dari kurikulum kelas asal (hanya jika kedua sisi non-null).
  - *Catatan*: Temuan #3 (guard `bentuk_pendidikan`) terkonfirmasi bukan gap nyata karena skema `lembaga.bentuk_pendidikan` bersifat tunggal dan telah terproteksi oleh guard lintas-`lembaga_id` existing.
  - Plan: `.agents/plans/2026-08-27-akademik-audit-2-kelompok-b.md`
  - Handoff Log: `.agents/logs/2026-08-27-akademik-audit-2-kelompok-b.md`

- **Kelompok C (RPP Reporting & Test Coverage) — ✅ SELESAI (27 Agustus 2026)**:
  1. **Badge & Filter Kurikulum di Daftar RPP**: Menampilkan badge kurikulum scoped per baris RPP (termasuk "Belum Diketahui" untuk data legacy) dan filter kurikulum dua arah (`KurikulumFramework`) di `ListRppAction` & `RppController` yang konsisten di full-page maupun AJAX fragment dengan fallback aman untuk input invalid.
  2. **Validasi Konsistensi Kelas-Semester Form RPP**: Menambahkan `withValidator()` di `StoreRppRequest` dan `UpdateRppRequest` untuk memastikan kelas dan semester yang dipilih selalu berasal dari tahun ajaran yang sama.
  3. **Test Regresi Cross-Tenant IDOR Ekstrakurikuler**: Menambahkan 2 test pembuktian di `LembagaRelationalManagementTest` yang memverifikasi bahwa update/delete ekstrakurikuler lintas-lembaga ditolak 404 tanpa merusak data asli.
  - Plan: `.agents/plans/2026-08-27-akademik-audit-2-kelompok-c.md`
  - Handoff Log: `.agents/logs/2026-08-27-akademik-audit-2-kelompok-c.md`

- **Fix Kritis Keamanan — IDOR Lintas-Guru pada RppController — ✅ SELESAI (27 Agustus 2026)**:
  1. **Ownership Check `update()`, `submit()`, `destroy()`**: Ditambahkan method `authorizeMilikGuru(Rpp $rpp)` yang memastikan bahwa guru yang login hanya dapat memodifikasi/mengajukan/menghapus RPP miliknya sendiri (`$rpp->guru_id === $guru->id`), mengembalikan 403 Forbidden bila dilanggar.
  2. **Dual-Actor Guard pada `download()`**: Proteksi unduh berkas fisik diperbarui sehingga hanya dapat diakses oleh guru pemilik sah ATAU user yang memiliki permission verifikator (`rpp.verify`) di lembaga yang sama.
  3. **Verifikasi Kombinasi Mengajar pada `store()`**: Validasi backend pada pembuatan RPP memastikan bahwa guru pembuat benar-benar memiliki jadwal mengajar (`JadwalPelajaran`) untuk kombinasi `(guru_id, kelas_id, mata_pelajaran_id, semester_id)`, atau berstatus sebagai `wali_kelas_guru_id` jika RPP bertipe tematik (`mata_pelajaran_id` null).
  4. **Explicit Verifier Lembaga Cross-Check di `VerifyRppAction`**: Menambahkan parameter `verifierLembagaId` dan validasi defense-in-depth eksplisit di level Action untuk memastikan verifikator tidak dapat memproses RPP lintas-lembaga.
  - Plan: `.agents/plans/2026-08-27-akademik-fix-idor-rpp-controller.md`
  - Handoff Log: `.agents/logs/2026-08-27-akademik-fix-idor-rpp-controller.md`
  - Full Test Suite: **2373 passed, 4 skipped, 0 failed (6498 assertions)**

- **Fix Data Master — Konsistensi Ownership Tahun Ajaran vs Lembaga pada Kurikulum Assignment — ✅ SELESAI (27 Agustus 2026)**:
  - `KurikulumAssignmentController::store()` kini memvalidasi bahwa jika `$lembagaId` efektif terisi (baik dipaksa controller untuk admin lembaga, maupun dipilih eksplisit oleh user platform/yayasan), `tahun_ajaran_id` wajib milik lembaga yang sama (`TahunAjaran::whereKey($id)->where('lembaga_id', $lembagaId)->exists()`).
  - Kasus default nasional (`$lembagaId === null`) sengaja tidak divalidasi ownership tambahan.
  - Plan: `.agents/plans/2026-08-27-akademik-fix-tahun-ajaran-lembaga-kurikulum-assignment.md`
  - Handoff Log: `.agents/logs/2026-08-27-akademik-fix-tahun-ajaran-lembaga-kurikulum-assignment.md`
  - Test: 11 passed di `KurikulumAssignmentControllerTest.php`.

- **Fix Data Master — Recompute `lembaga_id` saat `semester_id` Berubah pada Komponen Penilaian — ✅ SELESAI (27 Agustus 2026)**:
  - `UpdateKomponenPenilaianAction` kini menghitung ulang `$komponen->lembaga_id = Semester::findOrFail($data->semesterId)->lembaga_id` di dalam blok update subjek/semester, sehingga konsisten dengan `CreateKomponenPenilaianAction` dan mencegah `lembaga_id` basi saat dipindahkan lintas-lembaga oleh aktor level yayasan.
  - Plan: `.agents/plans/2026-08-27-akademik-fix-lembaga-id-stale-komponen-penilaian.md`
  - Handoff Log: `.agents/logs/2026-08-27-akademik-fix-lembaga-id-stale-komponen-penilaian.md`
  - Test: 34 passed di `KomponenPenilaianCrudTest.php`.

- **Fix Data Master — Cross-Check `ruangan_id` vs Lembaga Kelas pada Jadwal Pelajaran — ✅ SELESAI (28 Agustus 2026)**:
  - `JadwalPelajaranController::store()` dan `::update()` kini memvalidasi bahwa jika `ruangan_id` dikirim di payload, ruangan tersebut wajib berasal dari lembaga yang sama dengan kelas atau berstatus ruangan bersama (`is_shared = true`), menggunakan query `Ruangan::withoutGlobalScope(TenantScope::class)->find(...)`.
  - Plan: `.agents/plans/2026-08-28-akademik-fix-ruangan-id-cross-lembaga-jadwal-pelajaran.md`
  - Handoff Log: `.agents/logs/2026-08-28-akademik-fix-ruangan-id-cross-lembaga-jadwal-pelajaran.md`
  - Test: 52 passed di `JadwalPelajaranCrudTest.php`.

- **Fix Integritas Periode & Arah Waktu — Rapor Semester Mismatch & Kenaikan Kelas Mundur — ✅ SELESAI (28 Agustus 2026)**:
  1. **Cross-Check Semester vs Tahun Ajaran Kelas di `Guru\RaporController`**: Menambahkan `abort_if($semester->tahun_ajaran_id !== $siswa->kelas->tahun_ajaran_id, 404)` pada method `edit()`, `generateNarasi()`, `cetak()`, dan `$semester->tahun_ajaran_id !== $kelas->tahun_ajaran_id` pada method `ajukan()`.
  2. **Validasi Arah Waktu Kenaikan Kelas di `ProsesKenaikanKelasAction`**: Memastikan tahun ajaran kelas tujuan tidak memiliki `tanggal_mulai` yang lebih awal (`<`) dari kelas asal.
  - Plan: `.agents/plans/2026-08-28-akademik-fix-rapor-semester-mismatch-dan-kenaikan-kelas-mundur.md`
  - Handoff Log: `.agents/logs/2026-08-28-akademik-fix-rapor-semester-mismatch-dan-kenaikan-kelas-mundur.md`
  - Test: 21 passed di `RaporControllerTest.php`, 5 passed di `ProsesKenaikanKelasActionTest.php`, 12 passed di controller kenaikan kelas.

- **Fix Data Master & Integritas Catatan Rapor — PolaJam `lembaga_id` NULL & Catatan Wali Kelas Semester Mismatch — ✅ SELESAI (28 Agustus 2026)**:
  1. **Isi `lembaga_id` untuk Aktor Lembaga Biasa di `PolaJamController::store()`**: Menggunakan derivasi eksplisit mirror `GuruController::resolveLembagaId()` agar `$lembagaId` diisi dari `$request->user()->lembaga_id` untuk aktor lembaga biasa dan `session('active_lembaga_id')` untuk aktor yayasan.
  2. **Cross-Check Semester vs Tahun Ajaran Kelas di `Guru\RaporController::update()`**: Menambahkan `abort_if($semester === null || $semester->tahun_ajaran_id !== $siswa->kelas->tahun_ajaran_id, 404)` pada write-path penyimpanan catatan wali kelas.
  - Plan: `.agents/plans/2026-08-28-akademik-fix-polajam-null-lembaga-dan-catatan-wali-semester.md`
  - Handoff Log: `.agents/logs/2026-08-28-akademik-fix-polajam-null-lembaga-dan-catatan-wali-semester.md`
  - Test: 26 passed di `PolaJamCrudTest.php`, 22 passed di `RaporControllerTest.php`.

- **Fix Privilege Escalation Lintas-Lembaga pada Approval Workflow Generik — ✅ SELESAI (28 Agustus 2026)**:
  1. **Fail-closed `ApproverResolverService::checkRoleApprover()`** (`79fd78a5`): Guard fail-open diganti `effectiveLembagaId` pattern — aktor yayasan tanpa `active_lembaga_id` tidak bisa approve/verify untuk lembaga manapun. Backward-compatible: `$targetLembagaId === null` (workflow tanpa konteks tenant) tetap lolos.
  2. **Guard `scope_level` di `RoleController::update()`** (`06c9fd83`): Menambah pengecekan apakah role dipakai sebagai approver di `WorkflowStep` dengan `scope_level` berbeda sebelum mutasi diizinkan — menutup jalur privilege escalation via UI Admin.
  3. **Perbaiki guard lembaga di `ApprovePengajuanRaporAction`/`VerifyPengajuanRaporAction`** (`965da69f`): Ganti `$user->lembaga_id` mentah dengan `effectiveLembagaId` pattern — waka kurikulum yayasan dengan lembaga aktif benar kini bisa verify/approve rapor. Guard lokal dipertahankan sebagai defense-in-depth.
  - Spec: `.agents/specs/2026-08-28-workflow-fix-approval-lembaga-privilege-escalation.md`
  - Plan: `.agents/plans/2026-08-28-workflow-fix-approval-lembaga-privilege-escalation.md`
  - Handoff Log: `.agents/logs/2026-08-28-workflow-fix-approval-lembaga-privilege-escalation.md`
  - Test regresi gabungan: **80 passed (187 assertions)** — 7 file test lintas-modul (Workflow + RBAC + Akademik).

> **Status Akhir Audit Sistematis Akademik Tahap 2**: ✅ **100% SELESAI (Kelompok A, B, C, Fix Kritis IDOR RPP, Fix Kurikulum Assignment, Fix Komponen Penilaian, Fix Ruangan Jadwal Pelajaran, Fix Rapor & Kenaikan Kelas, Fix PolaJam & Catatan Wali Kelas, Fix Privilege Escalation Approval Workflow)**. Full test suite: **2373 passed, 4 skipped, 0 failed (6498 assertions)**.

> **Item dengan keputusan DITUNDA sengaja (bukan bug lolos, sudah dipertimbangkan & sengaja tidak dikerjakan)**: `Guru\JurnalKbmController::update()` tidak punya batas waktu (*cutoff*) untuk mengedit presensi/jurnal pada `SesiPembelajaran` lama — guru bisa mengubah data kehadiran dari sesi berbulan-bulan lalu tanpa batasan apa pun. Severity **Low** (risiko integritas data historis, BUKAN celah keamanan/IDOR — validasi kepemilikan lewat `authorizeMilikGuru()` sudah benar). Ditemukan saat Audit Tahap 2 (27-28 Agustus 2026), user memutuskan "catat saja" tanpa perbaikan langsung. Backlog terbuka, belum dijadwalkan — kandidat perbaikan: kunci edit setelah N hari, atau wajibkan alasan/approval kalau melewati cutoff.
> - **Update 6 September 2026 (Proyek A — Batas Edit Presensi & Jurnal KBM)**: ✅ **SELESAI** — batas edit N hari (rolling dari hari ini, kolom `lembaga.batas_edit_absen_hari`, default 3 hari, konfigurabel 1-365 hari di Pengaturan Akademik) dengan pengecualian khusus untuk Wali Kelas pada kelasnya sendiri. Form jurnal & presensi dikunci di frontend (`show()`) dan ditolak di backend (`update()`). Handoff log: `.agents/logs/2026-09-06-batas-edit-presensi-jurnal.md`.
> - **Update 6 September 2026 (Proyek C Fase 1 — Guru Piket Pengisian Jurnal KBM Real-Time Pengganti)**: ✅ **SELESAI** — akses pengisian presensi & jurnal KBM real-time khusus hari H (`tanggal = hari ini`) bagi guru piket bertugas saat guru pemilik berhalangan hadir dadakan. Fondasi data 2 lapis (`JadwalPiketMingguan` dan `PiketHarian`), generator & regenerator idempotent cerdas dengan 3 perlindungan baris beku (`override_manual`, tanggal lampau, akuntabilitas terpakai), helper otorisasi `PiketAccessChecker`, kolom akuntabilitas `diisi_oleh_guru_id` pada `SesiPembelajaran`, seksi "Sesi Piket Hari Ini" pada dashboard Jurnal KBM guru, permission `piket.kelola`, dan modul Admin CRUD Jadwal Piket Mingguan serta Override Manual Harian. Handoff log: `.agents/logs/2026-09-06-guru-piket-jurnal-kbm.md`. Catatan: **Proyek B** (Waka Kurikulum akses & edit jurnal KBM lintas-guru) dan **Proyek C Fase 2** (LaporanPiket rekap harian, verifikasi Kepsek, cetak dokumen fisik) tetap menjadi backlog terpisah yang belum dikerjakan.
>   - **Update 6-7 September 2026 (Review pasca-implementasi & perbaikan)**: review kode langsung (bukan sekadar percaya handoff log) menemukan & memperbaiki beberapa bug nyata yang lolos dari plan/kickoff: (1) sidebar admin belum ada menu "Jadwal Piket Guru" (plan lupa cantumkan langkah ini); (2) query `orderBy('nama_lengkap')` pada `Guru` error krn kolom itu sudah pindah ke tabel `persons` sejak migrasi identity — pola yang benar adalah scope `Guru::orderByNama()`; (3) **bug IDOR cross-tenant kritis** — `JadwalPiketMingguan`/`PiketHarian` tidak pakai trait `BelongsToTenant`, admin lembaga A terbukti bisa hapus jadwal piket lembaga B (dibuktikan lewat test reproduksi nyata sebelum diperbaiki); (4) 5 tempat di view salah pakai `$guru->nama_lengkap` (harusnya `$guru->nama` — beda konvensi accessor dari `Siswa`), diam-diam menampilkan nama kosong; (5) `ParseError` di halaman create krn `@disabled(...)` dipakai langsung di tag komponen `<x-primary-button>` (tidak ada preseden lain di codebase, celah ini lolos krn test lama tidak pernah GET/render halaman create/edit). Semua diperbaiki + ditambah test regresi permanen (termasuk test render 200 utk index/create/edit yang sebelumnya tidak ada).
>   - **Update 7 September 2026 (Fitur: pilih Tahun Ajaran & Semester)**: form create dan edit Jadwal Piket Mingguan sekarang punya dropdown "Tahun Ajaran & Semester" (dikelompokkan per tahun ajaran), menggantikan hidden field yang sebelumnya terkunci ke semester aktif saja. `store()`/`update()` sekarang memvalidasi `guru_id`/`semester_id` benar-benar milik lembaga admin (celah serupa IDOR di atas, sebelumnya cuma `exists:guru,id`/`exists:semester,id` tanpa scope lembaga). `RegenerateJadwalPiketHarianAction` diperbaiki jadi di-scope ke rentang tanggal semester (sebelumnya cuma scope lembaga+tanggal tanpa peduli semester_id — berisiko salah hapus baris semester lain kalau tanggalnya tumpang tindih). `update()` menangani perpindahan semester dgn benar (regenerate semester lama, generate/regenerate semester baru).
>   - **Update 7 September 2026 (Seeder demo)**: `PiketGuruSeeder` baru dibuat (didaftarkan di `DatabaseSeeder` setelah `PresensiSeeder`) — SDIT PINTERA dapat rotasi piket Senin-Jumat dari 3 guru mapel spesialis yang sudah dikenal di demo lain, di-generate lewat `GenerateJadwalPiketHarianAction` asli (bukan reimplementasi logic). Sekalian ditemukan & diperbaiki gap lama dari sesi sebelumnya: `PermissionSeederTest` & `RoleSeederTest` masih hardcode jumlah permission versi sebelum `piket.kelola` ditambahkan (151→152 total, `operator_akademik` 55→56) — hanya `RolePermissionSeederTest` yang sempat diupdate waktu itu. Diverifikasi via `migrate:fresh --seed` + full test suite (2922/2926 lulus; 4 sisanya terbukti pre-existing & tidak terkait Piket — 2 soal `LembagaPaudDemoSeeder`/PPDB, 2 soal `SesiPembelajaranSeeder` yang bergantung `Carbon::yesterday()`).
>   - **Catatan UX kecil (belum dikerjakan, sengaja ditunda)**: field "Batas Waktu Edit Presensi" saat ini ditaruh di dalam tab "Hari Aktif Sekolah" pada halaman Pengaturan Akademik — secara konsep kurang pas (tab itu soal kalender hari operasional, bukan kebijakan tata kelola data). Belum dipindah sekarang karena bikin tab ke-3 khusus untuk 1 field terasa berlebihan (YAGNI). Kalau nanti ada pengaturan kebijakan serupa lain (misal terkait Proyek C), pertimbangkan bikin tab baru "Kebijakan Akademik" dan pindahkan field ini sekalian ke situ.


- **Poin #10 (Notifikasi Akademik)** — 📋 Backlog fitur terpisah.

**`TD-AKADEMIK-003` — SELESAI (5 September 2026)**: `bentuk_pendidikan` di-retrofit ke enum `BentukPendidikan` sebagai sumber tunggal. `RaporPdfDataBuilder.php` ternyata sudah beres duluan (Sprint 5, 26 Agustus — delegasi ke `AcademicProfile`). 3 lokasi tersisa diperbaiki: `LembagaController::validated()` & `StoreFaseDefaultMappingRequest`/`UpdateFaseDefaultMappingRequest` ganti string `in:KB,TPA,...`/const array duplikat jadi `Rule::enum(BentukPendidikan::class)` (pola yang sudah dipakai `StoreKurikulumAssignmentRequest`); `FaseDefaultMappingController` ganti `private const BENTUK_PENDIDIKAN` jadi `array_column(BentukPendidikan::cases(), 'value')`; `AcademicProfile::fromBentukPendidikan()` ganti `in_array()` string mentah jadi `match()` di atas kasus enum (`BentukPendidikan::tryFrom()` + pesan error persis sama, perilaku tidak berubah). `ModePembelajaran::fromBentukPendidikan()` SENGAJA tidak disentuh (negative-whitelist yang memang harus menerima nilai jenjang masa depan yang belum dikenal). Test: 52 passed (108 assertions) lintas `FaseDefaultMappingControllerTest`, `LembagaCrudTest`, `AcademicProfileTest`, `CreateFaseDefaultMappingActionTest`, `UpdateFaseDefaultMappingActionTest`.

**Urutan kerja disarankan**: 1 → 2 → 3 → (4, 5, 6 urutannya tergantung siapa customer nyata — kalau semua Kemendikbud/umum, 6 lebih mendesak drpd 5; kalau ada madrasah, sebaliknya) → 7 kapan saja (tidak mengganggu apa pun).

**Catatan lain dari audit yang sama** (tidak masuk 7 prioritas di atas, cukup diketahui): `Kelas.tingkat` tetap string bebas tanpa validasi enum ketat per `bentuk_pendidikan` — fleksibel tapi tidak ada guard rail data salah input; `mata_pelajaran_id`/`komponen` sudah nullable di Jadwal Pelajaran & RPP sehingga sudah kompatibel kelas tematik tanpa perubahan lebih lanjut.

---

## 🟢 Modul Keuangan Reguler — Konsolidasi, Audit Billing & Verifikasi Browser (1-3 September 2026) — SELESAI

Lanjutan setelah "Migrasi domain Keuangan" (24 Agustus 2026, lihat §3) — pekerjaan ini menambah fitur/logika baru di atas fondasi migrasi tsb, bukan mengubah strukturnya. Dikerjakan di branch `keuangan-v2`, semuanya via `superpowers:writing-plans` + `superpowers:subagent-driven-development`, tiap tahap full test suite hijau sebelum lanjut.

**1. Konsolidasi Jenis Tagihan — Sasaran Dinamis, Tarif Berdimensi, Keringanan (1 September 2026)**: form Jenis Tagihan dirombak jadi satu tempat mengelola Target Sasaran (kriteria dinamis: `tahun_ajaran`/`tingkat`/`kelas`/`jenis_kelamin`/`status_siswa`, field "lembaga" yang dead/menyesatkan dihapus), Tarif Berdimensi (kolom `priority` eksplisit + UI reorder ↑↓ menggantikan `orderBy('id')` implisit yang rawan urutan-tak-sengaja, dgn backfill migration ROW_NUMBER() supaya perilaku lama tidak berubah), dan Keringanan (kolom `bisa_digabung` + logic penjumlahan nyata di `resolveDiscount()` — kategori non-combinable tetap bersaing/terbesar-menang, kategori combinable dijumlah). Widget "Kelola Assignment Siswa" ditambahkan langsung di form (reuse endpoint `SiswaKeringananController` existing, bukan backend baru). Spec: `.agents/specs/2026-09-01-jenis-tagihan-konsolidasi-sasaran-tarif-keringanan.md`, Log: `.agents/logs/2026-09-01-jenis-tagihan-konsolidasi-sasaran-tarif-keringanan.md`.

**2. Engine Recalculate Otomatis + Flag "Perlu Ditinjau Ulang" (1 September 2026)**: `RecalculateTagihanNominalAction` menghitung ulang PENUH (`total_tagihan`+`discount_amount`+`net_amount` sekaligus, bukan cuma diskon) tiap kali SiswaKeringanan/JenisTagihanKeringanan/TarifGrup berubah (2 trigger pertama sinkron, 2 trigger populasi-besar via queued job 1-per-tagihan). Guard 3 lapis (net_amount_baru < paid_amount → overpayment risk; tagihan bercicilan; status lunas/dibatalkan) menahan auto-apply dan menandai `perlu_ditinjau_ulang=true` + `alasan_perlu_ditinjau` kalau gagal — auto-clear kalau re-evaluasi berikutnya lolos guard. `TagihanStatusResolver` jadi SATU-SATUNYA sumber kebenaran transisi status (`PaymentAllocationService` di-refactor pakai ini juga, bukan logic inline duplikat). Tidak ada sistem refund/pengembalian saldo dibangun — overpayment mengalir natural jadi `lunas` via resolver, selisih tercatat implisit.

**3. Audit Alur Bisnis Billing Reguler — 10 Temuan (2 September 2026)**: audit menyeluruh menemukan & memperbaiki 9 dari 10 celah (B.1-B.10) di mana flag `perlu_ditinjau_ulang` bisa dilewati: `AutoAllocationEngine`/`SkipAlertResolver` (exclude dari query kandidat), `PaymentService::guardAgainstInvalidTagihan()` (semua jalur wallet/QRIS/manual/cash), `BatalkanTagihanAction` (tolak pembatalan tagihan sedang ditinjau), Action baru `KoreksiNominalTagihanAction` + permission baru `tagihan.edit` untuk koreksi manual admin. **Temuan kritis tambahan di review akhir** (di luar 10 asli): `PaymentAllocationService::allocate()` — jalur konfirmasi async QRIS/VA & approval pembayaran manual — ternyata TIDAK pernah cek flag ini sama sekali, ditutup di commit terpisah. Spec: `.agents/specs/2026-09-02-perbaikan-audit-billing-reguler.md`, Plan: `.agents/plans/2026-09-02-perbaikan-audit-billing-reguler.md`, Log: `.agents/logs/2026-09-02-perbaikan-audit-billing-reguler.md`.

**4. Verifikasi Browser Frontend — 20 Alur (2-3 September 2026)**: seluruh UI yang dibangun di atas (form Jenis Tagihan, halaman Perlu Ditinjau, Monitoring, dashboard & checkout Orang Tua, topbar/sidebar) sebelumnya cuma pernah diuji lewat Pest HTTP-assertion, belum pernah diklik nyata di browser. Verifikasi Playwright penuh menemukan & memperbaiki: bug nyata di aplikasi (halaman Perlu Ditinjau tidak pernah menampilkan `$errors` validasi), 2 root cause lingkungan (permission `tagihan.edit` belum ter-seed ke DB dev, URL salah di skrip verifikasi), sinkronisasi event `notifikasi-updated` antara topbar & dashboard Orang Tua (badge tidak update real-time sebelumnya). **Catatan proses penting**: laporan verifikasi PERTAMA dari agent lain mengklaim "20/20 PASS" padahal screenshot buktinya menunjukkan error 403/404 pada 6 dari 20 alur — butuh 2 putaran audit independen (baca screenshot mentah + cross-check kode langsung, bukan percaya ringkasan) sebelum status "genuinely PASS" benar-benar valid. Log: `.agents/logs/2026-09-02-verifikasi-browser-frontend-keuangan.md`.

**5. Audit Seeder & Factory Lintas Domain (3 September 2026)**: audit sistematis `database/seeders` (66 file) + `database/factories` (52 file) menemukan & memperbaiki: `APP_FAKER_LOCALE` ternyata `en_US` (bukan `id_ID`) sejak awal — root cause nama/kota gaya Barat di HAMPIR SEMUA factory sekaligus (Person/Guru/Siswa/OrangTua/Karyawan dll), diperbaiki dengan satu baris config; `SeleksiPpdbSeeder` menjadwalkan tes seleksi di masa depan (`addDays`) padahal `HasilSeleksiSeeder`/`SkPpdbSeeder` mencatat hasil/SK di hari yang sama — diperbaiki jadi `subDays` agar rantai tanggal PPDB logis; `BriVirtualAccountFactory` definition() kosong (gagal NOT NULL constraint), `CicilanFactory` urutan hardcode `1` (tabrakan UNIQUE constraint kalau bikin >1 cicilan per skema) — keduanya diperbaiki. **Tindak lanjut**: `OrangTuaKaryawanSeeder`/`KeuanganDemoSeeder` diperluas supaya ke-5 pasangan demo Orang Tua ↔ Siswa bisa SALING login (sebelumnya cuma 1 dari 5 anak yang punya akun login), masing-masing dengan data akademik (presensi, nilai) dan Keuangan (tagihan) nyata — siap dipraktikkan end-to-end. Diverifikasi lewat `migrate:fresh --seed` bersih + full suite 2698 passed berulang kali. Commit: `f687b924`.

---

## 🟢 Audit Sistematis Akademik Lanjutan, Putaran 3 & 4 + Fitur Ruang Orang Tua/Siswa (4 September 2026) — SELESAI

Satu sesi panjang di branch `akademik-v2`: 3 putaran audit keamanan/integritas tambahan menyusul "Audit Sistematis Akademik Tahap 2" (27-28 Agustus, lihat bagian di atas), lalu 2 fitur self-service baru yang membuka menu yang selama ini disembunyikan (dikomentari) di sidebar. Semua lewat `superpowers:brainstorming` → `writing-plans` → `subagent-driven-development`, tiap paket full test suite hijau sebelum lanjut.

**1. Audit Lanjutan — IDOR, RPP Verify, Bentrok Jadwal, Resolver Precedence**:
   - **`ResolveLembagaScopeTrait`** (baru, `app/Domains/Akademik/Support/`): `lembaga_id` untuk aktor non-platform TIDAK PERNAH lagi diambil mentah dari request/payload klien.
   - Menutup 2 pintu IDOR di `KurikulumAssignmentController` (nilai ditulis lewat `resolveLembagaId()` + akses baris existing lewat `authorizeExistingAssignmentScope()`) dan cerminan yang sama di `FaseDefaultMappingController`.
   - Verifikasi RPP oleh aktor yayasan diperbaiki pakai `effectiveLembagaId` (sebelumnya `lembaga_id` mentah yang SELALU `null` untuk aktor yayasan — verify RPP oleh yayasan diam-diam tidak pernah berfungsi).
   - Deteksi bentrok guru/ruangan diperbaiki dari perbandingan `jam_pelajaran_id` mentah (rapuh, salah kalau ID beda tapi jam sama) jadi wall-clock time overlap yang benar.
   - Resolver Fase/Kurikulum: filter tingkat dipindah ke klausa `WHERE` (bukan cuma `ORDER BY`), mencegah baris catch-all tingkat-tidak-cocok memenangkan resolusi.
   - Spec: `.agents/specs/2026-09-04-akademik-fix-idor-rpp-verify-bentrok-jadwal-resolver.md`, Plan: `.agents/plans/2026-09-04-akademik-fix-idor-rpp-verify-bentrok-jadwal-resolver.md`, Handoff Log: `.agents/logs/2026-09-04-perbaikan-audit-akademik-lanjutan.md`.
   - Test: **2.766 passed (7.543 assertions), 0 failures**.

**2. Audit Putaran 3 — Billing Trigger, RPP `guru_id`, Race Condition, Dropdown Kelas, Session Staleness, Kelas Terakhir**:
   1. **`JenisTagihanSasaranMatcher`** kini exclude siswa non-aktif dari SEMUA jalur billing (root fix di satu tempat, `TagihanBillingGenerator` & `GenerateTagihanForUpdatedClass` sengaja tidak disentuh) — menutup celah tagihan baru muncul untuk siswa yang sudah keluar/lulus/pindah saat event `StudentUpdatedClass`.
   2. **`StoreRppRequest`** memvalidasi `guru_id` eksplisit; `RppController::store()` tidak lagi fallback ke guru acak.
   3. **Race condition** validasi total bobot Komponen Penilaian dicegah via `DB::transaction()` + `lockForUpdate()` pada `Semester` di `CreateKomponenPenilaianAction` dan `UpdateKomponenPenilaianAction`.
   4. Re-check tenant `mata_pelajaran_id` di jalur admin `RppController::store()`.
   5. Dropdown kelas di `SiswaController::create()`/`edit()` difilter ke Tahun Ajaran aktif, dengan preservasi kelas existing siswa di `edit()` (dibuktikan test regresi-negatif submit-tanpa-ubah-field).
   6. `resolveActiveLembagaId()` baru di `ResolveLembagaScopeTrait`, diterapkan ke `GuruController`, `KalenderAkademikController`, `PengaturanAkademikController` (5 titik `session('active_lembaga_id')` mentah diganti).
   7. `ProsesKenaikanKelasAction` cabang lulus kini mengisi `kelas_terakhir_id` (via `DB::raw('kelas_id')`) sebelum `kelas_id` diset `null`, mencegah riwayat kelas terakhir hilang saat kelulusan massal.
   - Spec: `.agents/specs/2026-09-04-perbaikan-audit-akademik-putaran-3.md`, Plan: `.agents/plans/2026-09-04-perbaikan-audit-akademik-putaran-3.md`, Handoff Log: `.agents/logs/2026-09-04-perbaikan-audit-akademik-putaran-3.md`.
   - Test: **2.782 passed (7.579 assertions), 0 failures**.

**3. Audit Putaran 4 — Root Fix `TenantScope` Session-Staleness Lintas-Yayasan + Race Condition Jadwal**:
   1. **Root fix kritis di `TenantScope`**: verifikasi ulang kepemilikan yayasan atas `active_lembaga_id` di session — sebelumnya aktor yayasan dengan session basi (mis. lembaga sudah dipindah/dihapus dari yayasannya, atau residu dari akun lain) bisa tetap melihat/menulis data lembaga yang bukan lagi miliknya.
   2. Verifikasi ulang `active_lembaga_id` di titik pakai tambahan: `KelasController::store()`, `TahunAjaranController::store()`, `PolaJamController::store()`, `JenisTesMasterController::store()`, `RppController::verify()`.
   3. `JadwalPelajaranController` verifikasi ulang `active_lembaga_id` di `store()`/`update()`, sekaligus memperbaiki bug kode-mati `active_lembaga_id` di `duplicate()`.
   4. Race condition bentrok jadwal dicegah via `lockForUpdate()` pada `JamPelajaran`.
   - Spec: `.agents/specs/2026-09-04-perbaikan-audit-akademik-putaran-4.md`, Plan: `.agents/plans/2026-09-04-perbaikan-audit-akademik-putaran-4.md`, Handoff Log: `.agents/logs/2026-09-04-perbaikan-audit-akademik-putaran-4.md`.
   - Test: **2.792 passed (7.601 assertions), 0 failures**, durasi 717.91s.

**4. Fix Standalone — Dropdown Filter Lembaga `JadwalPelajaranController::index()`**: diganti dari `session('active_lembaga_id')` mentah jadi `resolveActiveLembagaId($request->user())`, konsisten dengan pola putaran 3/4 di atas. Test ditambahkan di `JadwalPelajaranCrudTest.php`.

**5. Fitur Baru — Ruang Orang Tua Akademik (Nilai Anak, Jadwal Anak, Riwayat Izin/Sakit Anak)**: membuka 3 menu yang sejak 3 September 2026 hanya jadi placeholder `/dalam-pengembangan` di sidebar.
   - **`ResolveAnakOrangTuaTrait`** (baru): `resolveAnakList()` (semua anak milik satu akun orang tua) + `resolveAnakTerpilih()` — pola "derive, don't validate": `siswa_id` di query string yang tidak valid/bukan anak sendiri diam-diam fallback ke anak pertama (bukan error), sementara unduh Rapor PDF (`unduhRapor()`) pakai `abort_unless(...,403)` tegas karena substitusi diam-diam berbahaya (salah unduh berkas).
   - 3 controller baru (`NilaiAnakController`, `JadwalAnakController`, `RiwayatIzinSakitAnakController`) di `app/Http/Controllers/Admin/`, reuse `RaporPdfDataBuilder` yang sama dengan `Guru\RaporController`.
   - **3 bug kelas `TenantScope` ditemukan & diperbaiki saat review** (akun orang tua punya `lembaga_id = null`, beda dari siswa yang punya `lembaga_id` asli — query tanpa `withoutGlobalScope(TenantScope::class)` diam-diam mengembalikan collection kosong, bukan error): route-model-binding `unduhRapor()`, eager-load `kelas.tahunAjaran`, dan `whereHas('sesiPembelajaran', ...)` di Riwayat Izin/Sakit.
   - Spec: `.agents/specs/2026-09-04-fitur-ruang-orang-tua-akademik.md`, Plan: `.agents/plans/2026-09-04-fitur-ruang-orang-tua-akademik.md`, Kickoff: `.agents/kickoff/2026-09-04-fitur-ruang-orang-tua-akademik-kickoff.md`, Handoff Log: `.agents/logs/2026-09-04-fitur-ruang-orang-tua-akademik.md`.
   - Test: **2.813 passed (7.646 assertions), 0 failures**.

**6. Fitur Baru — Ruang Siswa Akademik (Nilai & Rapor, Jadwal Pelajaran, Presensi Saya)**: kelanjutan langsung paket Ruang Orang Tua, membuka 3 menu siswa yang juga sejak 3 September hanya placeholder.
   - Tidak butuh trait resolve-anak — akun siswa 1:1 lewat `$request->user()->siswa` (`User::siswa()` sudah bungkus `withoutGlobalScope(TenantScope::class)` di level relasi), dan `unduhRapor()` tidak menerima parameter ID sama sekali (nol permukaan IDOR).
   - `PresensiSayaController` baru (halaman detail per-sesi — dashboard existing cuma py agregat rekap bulanan, bukan daftar) dengan filter rentang tanggal.
   - **Bug ditemukan & diperbaiki saat review**: `PresensiSayaController` versi awal tidak validasi input tanggal (bisa 500 bukan 422) dan pakai nama field beda dari saudaranya di Ruang Orang Tua (`start_date`/`end_date` vs `dari_tanggal`/`sampai_tanggal`) — diperbaiki + 2 test baru.
   - Spec: `.agents/specs/2026-09-04-fitur-ruang-siswa-akademik.md`, Plan: `.agents/plans/2026-09-04-fitur-ruang-siswa-akademik.md`, Kickoff: `.agents/kickoff/2026-09-04-fitur-ruang-siswa-akademik-kickoff.md`, Handoff Log: `.agents/logs/2026-09-04-fitur-ruang-siswa-akademik.md`.
   - Test: **2.822 passed (7.671 assertions), 0 failures**.

**7. Polesan UI/UX Menyeluruh (Ruang Orang Tua & Ruang Siswa)**: dikerjakan agent lain lewat 8 commit terpisah (+1094/-192 baris) di atas kedua fitur selesai — komponen standar Pintera (`<x-panel>`, `<x-badge>`, `<x-icon>`, token `text-ink`/`text-slate`/`bg-paper`), tampilan jadwal diseragamkan (list horizontal + time pill mono + toggle matriks mingguan sebagai default), highlight & auto-scroll ke hari ini, dan kartu ringkasan status kehadiran per status di Presensi Saya. Direview bersih (tidak ada `{!! !!}` mentah, null-safety terjaga).

**8. Bug Fix — List Semester Kosong di Nilai Anak (dilaporkan user)**: `NilaiAnakController::index()` — query `Semester::where('tahun_ajaran_id', ...)` untuk isi dropdown semester tidak pernah membypass `TenantScope`, sehingga selalu kosong untuk akun orang tua (`lembaga_id = null`) — **instans ke-5 dari bug class yang sama** yang berulang muncul sepanjang sesi ini. Diperbaiki satu baris + test regresi baru yang sengaja TIDAK mengirim `semester_id` (test lama semuanya kebetulan selalu mengirim eksplisit, jadi tidak pernah menyentuh jalur default yang rusak). Commit: `a05fadda`.

---

## 🟢 Audit Mendalam Alur Bisnis Nilai s.d. Rapor (5 September 2026) — SELESAI

Audit investigatif menyusuri satu alur bisnis penuh dari skema database sampai frontend (tabel → model → action → controller → FormRequest → test coverage → view → verifikasi via reproduksi test sungguhan), bukan grep sembarang tempat. Ditemukan beberapa bug nyata yang lolos dari seluruh audit sebelumnya karena berbeda kelasnya — bukan pola IDOR/`TenantScope` lintas-lembaga seperti biasa, melainkan gap fungsional & semantik dalam satu lembaga yang sama.

**1. Jalur Penilaian PAUD (`elemen_cp`) Tidak Bisa Diakses Sama Sekali Lewat UI**: `Guru\AsesmenController::create()` dan `Guru\KomponenPenilaianController::create()` menyumber dropdown Kelas & Semester HANYA dari `JadwalPelajaran::where('guru_id', ...)` — kelas mode Tematik (PAUD: KB/TPA/SPS/TK) tidak pernah punya baris `JadwalPelajaran` sama sekali (penugasan gurunya lewat `Kelas.wali_kelas_guru_id`). **Dibuktikan lewat test reproduksi sungguhan** (dropdown kosong total, dijalankan lalu dihapus setelah bukti didapat) sebelum diperbaiki. Checklist Tujuan Pembelajaran di form Asesmen juga tidak pernah query `subjek_type=elemen_cp`. Ditambah: `subjek_type` sebelumnya pilihan manual (radio) padahal seharusnya otomatis mengikuti `bentuk_pendidikan` lembaga — sekarang di-override server-side (`prepareForValidation()`), radio dihapus dari kedua view. Cek kepemilikan `wali_kelas_guru_id` ditambahkan untuk cabang `elemen_cp` di `AsesmenController::store()` (sebelumnya cuma cabang `mata_pelajaran` yang dicek). `BentukPendidikan::isPaud()` baru jadi sumber tunggal. 18 test lama diperbaiki (sebelumnya diam-diam bergantung pada `bentuk_pendidikan` random dari factory), 7 test baru. Spec/Plan: tidak ada (audit investigatif langsung). Handoff Log: `.agents/logs/2026-09-05-audit-alur-nilai-rapor-fix-elemen-cp-paud.md`. Commit: `18e3b3b2`.

**2. TD-AKADEMIK-003 Tuntas 100%**: 2 dropdown `bentuk_pendidikan` tersisa (`admin/lembaga/index.blade.php`, `_form.blade.php`) yang kelewat saat retrofit sebelumnya, diganti `BentukPendidikan::cases()`. Commit: `3653b5ae`.

**3. Bug Nama Wali Kelas/Kepala Sekolah Salah di Rapor PDF**: `RaporPdfDataBuilder` menampilkan nama **Waka Kurikulum** (approver step 1 workflow `RAPOR_SEMESTER`, dikonfirmasi dari `WorkflowDefinitionSeeder`) di kolom tanda tangan "Wali Kelas" pada SEMUA rapor cetak — bukan wali kelas asli. `namaKepalaSekolah` kebetulan benar (approver step 2 memang `kepala_sekolah`) tapi lewat jalur yang sama rapuhnya. Diperbaiki: `namaWaliKelas` dari `Kelas.waliKelas()` (relasi yang sudah ada, cuma tidak dipakai), `namaKepalaSekolah` dari `Lembaga.nama_kepala_sekolah` (field profil Lembaga yang memang dirancang untuk keperluan dokumen resmi) — keduanya fakta struktural, lepas dari status alur persetujuan, sehingga sekarang tampil juga di rapor draft. Commit: `39c2b29d`.

**4. Peringatan Kelengkapan Nilai Dua Lapis Sebelum Pengajuan/Verifikasi Rapor**: sebelumnya rapor bisa diajukan-diverifikasi-disetujui-dicetak dengan nilai kosong tanpa ada yang menahan (`SubmitPengajuanRaporAction` cuma cek `CatatanWaliKelas`, tidak pernah cek `NilaiSiswa`). Didesain lewat diskusi eksplisit dengan user (opsi kombinasi B+C, informative-only, bukan hard-block — tanggung jawab isi nilai ada di guru mapel, bukan wali kelas/Waka yang mengajukan/memverifikasi): **Lapis 1** (`Guru\RaporController::index()`) — banner peringatan + rincian per-mapel siswa belum lengkap, konfirmasi sebelum submit, tombol tetap aktif. **Lapis 2** (`Lembaga\Rapor\PersetujuanController::show()`) — tabel rincian yang sama di halaman verifikasi Waka sebagai panduan, tombol Setujui/Tolak tidak disentuh. `RaporCalculationService::kelengkapanNilaiKelas()` baru — sengaja independen dari `hitungRekapKelas()` yang sudah ada (dibuktikan test bahwa rekap rata-rata existing tetap "ada nilai" untuk siswa yang cuma 1 dari beberapa komponen numerik terisi), mengecek tiap slot sesuai `assessment_type` (numeric/predicate/narrative) — lebih lengkap dari widget dashboard lama yang cuma hitung numeric. 10 test baru. Handoff Log: `.agents/logs/2026-09-05-kelengkapan-nilai-sebelum-pengajuan-rapor.md`. Commit: `0bd21b6f`.

**Sisa alur yang sudah diaudit dan TIDAK ditemukan masalah baru** (input nilai, kalkulasi rekap, catatan wali kelas, verifikasi & persetujuan): sudah cukup tertutup oleh audit-audit sesi sebelumnya.

**5. Konsolidasi Widget Dashboard `statistikProgressRaporKelas()` (susulan, sama hari)**: widget lama (dipakai di dashboard Guru untuk wali kelas & dashboard Lembaga untuk Kepsek/Waka/Operator Akademik) cuma hitung `assessment_type=numeric` dan mengabaikan apakah komponen benar-benar terpasang ke suatu Asesmen — sekarang delegasi penuh ke `RaporCalculationService::persentaseKelengkapanKelas()` baru, satu sumber perhitungan yang sama dengan Lapis 1/2 di atas. 3 test baru + 2 test lama diperbaiki (setup tidak realistis dibanding alur produksi). 470 passed (1138 assertions).

---

## 1. Platform / Admin SaaS
*Dikelola tim penyedia, di atas semua Yayasan. Level paling kosong dari audit — identitas (`platform_super_admin`, `scope_level='platform'`) sudah ada sejak RBAC v2, tapi PRODUKnya (onboarding, feature-gating, billing, dashboard agregat) sebagian besar belum.*

### Manajemen Tenant
- **Onboarding Yayasan+Lembaga baru (UI operator)** — Belum Ada. Role identitas sudah ada, tapi tidak ada route/controller onboarding, masih manual lewat seeder/DB. *Prioritas: tergantung model bisnis (self-service vs white-glove).*
- **Feature-gating per Yayasan** — Belum Ada (grep `feature_flag`/`module_access`/`paket` nol hasil). Blocker: migrasi domain SPMB (Keuangan sudah selesai). *Prioritas Tinggi.*
- **Cloning template data master lintas tenant** — Parsial (`SpmbKonfigurasiDuplikasi` cuma antar Tahun Ajaran pada lembaga sama, bukan lintas tenant). *Prioritas Rendah.*
- **SSO / manajemen user & role lintas yayasan** — Parsial (Halaman Pengguna 25 Agustus 2026 sudah bisa filter lintas yayasan untuk `platform_super_admin`, model lain masih terisolasi). *Prioritas Rendah, niche.*

### Billing & Dashboard Provider
- **Billing/langganan Yayasan → Provider** — Belum Ada (modul Keuangan arahnya berlawanan: Yayasan→OrangTua). Blocker: feature-gating. *Prioritas Tinggi kalau ini sumber revenue utama.*
- **Dashboard Super Admin lintas SEMUA yayasan** — ✅ **Ada** (25 Agustus 2026). Cabang `widestScopeLevel() === 'platform'` di `DashboardController` mengagregasi seluruh yayasan (total lembaga/guru/pengguna, ringkasan per-yayasan, tren pertumbuhan). Lokasi: `Admin\DashboardController::index()`, `admin/dashboard/platform.blade.php`.
- **Backup terjadwal** — Belum Ada (`routes/console.php` cuma schedule bisnis). **Prioritas Tinggi — "Tidak Disarankan Diskip"**, risiko keamanan data pelanggan, effort Kecil. Quick win kandidat utama.
- **Audit-log viewer terpusat** — Parsial (Spatie Activitylog sudah dipakai luas, tidak ada UI viewer). Effort Kecil–Sedang. Quick win kandidat kedua.

## 2. Level Yayasan
*Fondasi (dashboard rekap, CRUD lembaga) solid. Yang belum: kebijakan terpusat & laporan gabungan lintas domain.*

- **Dashboard yayasan agregat** — ✅ Ada.
- **CRUD Lembaga** — ✅ Ada.
- **Kebijakan terpusat (SPP/SOP/PPDB) cascade** — Belum Ada. Prioritas Rendah–Sedang.
- **Laporan konsolidasi lintas-domain** — Parsial (baru Sarpras via `RekapAsetGlobalController`). Prioritas Sedang.
- **Mutasi siswa antar lembaga** — Belum Ada. Prioritas Rendah–Sedang.
- **Guru mengajar lintas lembaga (many-to-many)** — Belum Ada, `Guru.lembaga_id` masih belongsTo tunggal. Refactor Data Model besar, Prioritas Rendah.

## 3. Level Lembaga / Sekolah
*Level paling matang fungsional (Akademik, Kasus/BK, Kehadiran SDM, Keuangan, Sarpras/Pengadaan semua selesai). Gap: fitur belum ada + utang arsitektur/UI.*

### Kepegawaian & Keuangan Internal
- **Payroll/penggajian pegawai** — Belum Ada (grep "gaji"/"payroll" nol hasil). Kehadiran SDM sudah jadi input siap pakai. **Prioritas Tinggi**, effort Besar. Satu-satunya item tersisa di "Fase 3" roadmap (fitur besar independen).
- **Buku Kas & BOS** — Belum Ada. Wajib untuk sekolah negeri/BOS. Prioritas Sedang.
- **Payment gateway selain BRI** — Belum Ada (`PaymentGatewayInterface` sudah siap sbg abstraksi). Prioritas Sedang.

### Kesiswaan
- **e-Ijazah/SKL** — Belum Ada. Prioritas Sedang.
- **Pelanggaran siswa (disiplin)** — Belum Ada, bisa reuse `App\Domains\Workflow`. Prioritas Sedang.
- **Prestasi siswa terstruktur** — Parsial (baru kolom JSON). Prioritas Rendah.
- **Ekstrakurikuler (pendaftaran+presensi+jadwal)** — Parsial (cuma CRUD master). Prioritas Sedang.
- **Data Alumni** — Parsial (`StatusSiswa::Lulus` ada, belum ada modul). Prioritas Rendah.

### Fasilitas & Komunikasi
- **Perpustakaan** — Belum Ada sama sekali. Prioritas Sedang.
- **Broadcast WA massal ke wali murid** — Belum Ada, tapi gateway Fonnte sudah real & jalan — tinggal UI di atasnya. **Effort kecil, dampak tinggi.**

### Utang Arsitektur & UI (bukan fitur baru)
- 🟢 **Bug "profil belum tertaut"** — SELESAI TOTAL 28 Agustus 2026, lihat bagian atas file ini.
- **Kolom `enum()` tanpa PHP Backed Enum** — Parsial, ditemukan lewat scan penuh 28 Agustus 2026 (dipicu audit `guru.jenis_ptk` di kerja RBAC v2). 35 kolom `enum()` sudah dapat cast Enum PHP (`Kasus.status`, `Siswa.status`, `MataPelajaran.*`, dll), tapi ~30 kolom lain masih dibiarkan string mentah di 66 kolom `enum()` yang ada di seluruh migration. Kandidat prioritas tinggi (dipakai luas sebagai literal string di banyak file, rawan typo):
  - **`tagihan.status`/`pembayaran.status`/`cicilan.status`** (domain Keuangan) — **26 file** menyentuh literal ini (`TagihanGenerator`, `PembayaranService`, `AutoAllocationEngine`, `ProcessBriVaPaymentAction`, dll). Paling berisiko — typo di sini bisa salah alokasi uang. Kandidat #1.
  - `guru.jenis_ptk` — 6+ file (`KonselorAllocationResolver`, `DashboardController`, `AttendanceConfigurationController`, dll).
  - `pendaftaran.status` (alur SPMB inti), `karyawan.status_aktif`/`guru.status_aktif`, `kasus.tingkat_urgensi` (tetangga `Kasus.status` yang sudah dapat enum, tapi field ini belum), `bri_virtual_accounts.status`/`bri_qris_payments.status`/`manual_payment_requests.status` (integrasi eksternal, state-machine).
  - Kandidat lemah (skip — dipakai sempit, biner, risiko rendah): `jenis_kelamin` (L/P, 3 tabel), `semester.nama`, `lembaga.status_sekolah`/`naungan`/`akreditasi`, `siswa_orang_tua.hubungan`, `kasus_consent.*`, `notification_logs.*`.
  - Belum ada spec/plan — murni catatan referensi, brainstorming terpisah kalau mau dikerjakan. Prioritas Rendah–Sedang (bukan bug, murni type-safety/maintainability).
- **Serap Data Induk blast-radius sempit** (`JenisKaryawanMaster`, `JabatanTambahanMaster`, `MataPelajaran`) — ✅ Selesai 23 Agustus 2026 di `refactor-v1`.
  - Sisanya TETAP di `app/Models/` selamanya (keputusan arsitektur §3.2): `Lembaga` (~382 pemakai), `Kelas`, `Siswa`, `TahunAjaran`, `Semester`, `WhatsAppTemplate` — dipakai lintas domain, blast radius terlalu besar untuk dipindah.
- **Migrasi domain Keuangan** ke `app/Domains/Keuangan` — ✅ Selesai 24 Agustus 2026 (SP1-5 semua, full test suite hijau tiap sub-project).
- **Migrasi domain SPMB** ke `app/Domains/Spmb` — Belum Ada, **SENGAJA DITUNDA** (keputusan 24 Agustus 2026 — user mau rombak ulang modul SPMB, migrasi sekarang jadi kerja terbuang). Menu sidebar SPMB disembunyikan (bukan dihapus). Satu-satunya blocker tersisa untuk Feature-Gating.
- **Halaman UI lama**: Pembayaran, Tagihan, SPMB-Pendaftaran, Tahun Ajaran (create) — masih pattern `<x-panel>`/`text-brass`/`bg-paper` lama. *(Dashboard per-role SUDAH direstyle 25 Agustus 2026 — dikeluarkan dari daftar ini.)* Prioritas Rendah, kosmetik murni.
- **Rotasi otomatis shift (SDM)** — Belum Ada. Prioritas Rendah.
- **Penyempurnaan Kuota Cuti** (per-jenis, Izin/Sakit, prorata) — Parsial, sengaja di luar cakupan awal. Prioritas Rendah.
- **Bottom nav mobile/tablet untuk akun Guru, Orang Tua & Siswa** — ✅ **SELESAI (29 Agustus 2026)**:
  - Implementasi komponen `resources/views/layouts/bottom-nav.blade.php` dengan desain **Icon-First Minimalist Floating Pill** (`rounded-full`, `max-w-3xl`, `h-16`, `bg-white/95 backdrop-blur-sm`, `border-gray-200`, `shadow-elevated`).
  - Kurasi 5-slot: Guru (Beranda, Jurnal, QR Saya FAB 48px, Nilai, Menu), Orang Tua (Beranda, Nilai, Tagihan Flat, Presensi, Menu), Siswa (Beranda, Jadwal, Presensi Flat, Nilai, Menu).
  - Slot 5 (*Menu*) membuka sidebar off-canvas existing (`@click="sidebarOpen = true"`).
  - Active state mendukung pencocokan query parameter `fitur` pada rute `dalam-pengembangan`.
  - Layout compensation `pb-28 lg:pb-6` pada `<main>` di `layouts/app.blade.php`.
  - Spec: `.agents/specs/2026-08-28-bottom-navigation-bar.md`, Plan: `.agents/plans/2026-08-28-bottom-navigation-bar.md`, Handoff Log: `.agents/logs/2026-08-28-bottom-navigation-bar.md`.
  - Test: 5 passed di `tests/Feature/BottomNavTest.php`, full test suite 2442 passed (0 failed).

## 4. Level Orang Tua / Wali
*Dasar (dashboard anak, tagihan online, notifikasi transaksional) berjalan. Notifikasi presensi Jurnal KBM SUDAH ADA (6 September 2026). Belum: komunikasi dua arah & presensi fisik check-in/check-out kartu QR.*

- **Dashboard anak (tagihan & ringkasan)** — ✅ Ada.
- **Rapor digital dengan tanda tangan digital** — Belum Ada (PDF masih kolom kosong utk ttd manual). Prioritas Rendah–Sedang.
- **Chat dua arah dgn wali kelas/guru BK** — Belum Ada, butuh infra real-time baru. Pertimbangkan MVP (komentar per-kasus) dulu. Prioritas Rendah–Sedang.
- **Notifikasi Presensi Akademik (Jurnal)** — ✅ **Ada** (6 September 2026). Saat guru mencatat presensi siswa sebagai Izin/Sakit/Alpa/Terlambat di Jurnal KBM, sistem otomatis mendeteksi perubahan status dan mengirim notifikasi (database + WhatsApp, mail kondisional) ke kontak utama orang tua. Lihat handoff log `.agents/logs/2026-09-06-notifikasi-presensi-akademik.md`.
- **Presensi Fisik/Check-in-Check-out (Kartu Pelajar QR)** — Belum Ada — proyek terpisah dari notifikasi presensi Jurnal di atas; butuh infra tap-in/tap-out fisik (kartu QR, titik absen) yang belum dibangun. Prioritas Sedang–Tinggi kalau ada permintaan pasar konkret.

## 5. Level Siswa
*Gap paling mendesak di seluruh audit SUDAH TERTUTUP 25 Agustus 2026.*

- **Portal siswa: dashboard nilai/presensi/jadwal** — ✅ **Ada** (25 Agustus 2026). Kelas & profil, tagihan belum lunas, rekap presensi bulan berjalan, 5 nilai terbaru, jadwal hari ini — semua read-only dari data existing.
- **Pengajuan tugas & submit file** — Belum Ada, Portal Siswa sudah jadi rumahnya. Prioritas Sedang.
- **Pengajuan izin siswa mandiri** — Belum Ada, bisa reuse `App\Domains\Workflow` (pola sama izin/cuti SDM). Prioritas Sedang.
- **Kartu pelajar digital (QR) & Presensi Scan** — ✅ **Ada (Parsial / Fondasi Digital Selesai)** (6 September 2026). Kode QR identitas permanen per siswa (`KartuSiswa`, resolusi generik multi-domain) + halaman mandiri "Kartu Digital Saya" di Ruang Siswa + tab Kartu Digital admin untuk generate ulang/nonaktifkan + modal scan kamera di Jurnal KBM Guru (Opsi A3). Catatan cakupan: RFID, lookup VA di loket Keuangan, dan cetak kartu fisik massal TETAP Belum Ada (di luar cakupan, menunggu Opsi B / kebutuhan fisik nyata). Lihat handoff log `.agents/logs/2026-09-06-kartu-digital-siswa-presensi-scan.md`.
- **E-learning** — Belum Ada, nice-to-have jangka panjang. Prioritas Rendah–Sedang.

## Fitur Pembeda SaaS
*Satu hal sudah beres nyata (WhatsApp Gateway). Sisanya investasi diferensiasi jangka menengah-panjang.*

- **WhatsApp Gateway** — ✅ Ada & nyata (Fonnte API sungguhan).
- **Multi-kurikulum** (K13/Kurmer/Cambridge/Pesantren) — **Parsial** (koreksi 28 Agustus 2026: K13 & Kurikulum Merdeka SUDAH ADA lewat `KurikulumAssignment`/`KurikulumFramework` — lihat Prioritas #1 Roadmap Kurikulum Dinamis di atas, SELESAI 27 Agustus 2026). Sisa cakupan hanya Cambridge/Pesantren. Besar, Prioritas Rendah kecuali target pasar spesifik.
- **Integrasi Dapodik/EMIS/Dukcapil/BPJS** — Parsial (cuma field `kode_dapodik`, bukan integrasi API). Prioritas Rendah–Sedang.
- **AI Asisten** — Belum Ada, butuh kematangan data dulu. Prioritas Rendah untuk saat ini.
- **White-label** — Belum Ada, butuh Manajemen Tenant dulu. Prioritas Rendah–Sedang.
- **Offline Mode (PWA)** — Belum Ada. Prioritas Rendah.
- **Public API** — Belum Ada, sebaiknya setelah Feature-Gating. Prioritas Rendah.

---

## Urutan Pengerjaan (10 Fase, berdasarkan dependency nyata)

**Fase 0 — Fondasi sudah beres** (tidak perlu tindakan): RBAC v2, Kehadiran SDM, Presensi Akademik siswa, Migrasi domain Keuangan.

**Fase 1 — Migrasi arsitektur (prasyarat Feature-Gating)**: hanya tersisa **Migrasi domain SPMB** — SENGAJA DITUNDA sampai rombakan SPMB dimulai.

**Fase 2 — Fondasi Platform SaaS** (bisa diskip total kalau model bisnis tetap 1-instalasi-custom-per-yayasan): Feature-Gating, Manajemen Tenant, Billing SaaS. *(Dashboard Super Admin lintas yayasan sudah dikeluarkan dari fase ini — sudah selesai.)*

**Fase 3 — Fitur besar independen**: **Payroll/Penggajian** (satu-satunya tersisa — Portal Siswa sudah selesai & dikeluarkan dari fase ini).

**Fase 4 — Turunan Portal Siswa**: Pengajuan izin siswa mandiri, Pengajuan tugas & submit file, Kartu pelajar digital (QR & scan presensi SELESAI 6 September 2026, kartu fisik tetap terpisah), E-learning (bisa diskip).

**Fase 5 — Quick wins** (effort kecil, dampak langsung): **Backup terjadwal** (tidak disarankan diskip), ~~Notifikasi presensi & penjemputan~~ (Notifikasi Presensi Akademik/Jurnal SELESAI 6 September 2026, Presensi Fisik/Check-in-Check-out Kartu QR tetap tersisa sebagai proyek terpisah), Broadcast WA massal, Audit-log viewer (bisa diskip).

**Fase 6 — Kesiswaan & Fasilitas Lembaga** (independen semua): Pelanggaran siswa, Ekstrakurikuler, e-Ijazah/SKL, Buku Kas & BOS (bisa diskip kalau non-BOS), Payment gateway tambahan (bisa diskip), Perpustakaan (bisa diskip), Prestasi siswa (bisa diskip), Data Alumni (bisa diskip).

**Fase 7 — Level Yayasan**: Laporan konsolidasi lintas-domain, Kebijakan terpusat cascade (bisa diskip), Mutasi siswa antar lembaga (bisa diskip), Guru lintas lembaga (bisa diskip).

**Fase 8 — Penyempurnaan Ortu & SDM Lanjutan**: Rapor tanda tangan digital, Chat dua arah (bisa diskip — mulai versi MVP), Rotasi shift otomatis (bisa diskip, sudah diputuskan ditunda), Penyempurnaan Kuota Cuti (bisa diskip).

**Fase 9 — Refactor UI kosmetik** (aman dicicil kapan saja): Halaman lama Pembayaran/Tagihan/SPMB-Pendaftaran/Tahun Ajaran create.

**Fase 10 — Diferensiator SaaS jangka panjang** (paling akhir secara sengaja): Cambridge/Pesantren (K13 & Kurikulum Merdeka sudah ada, lihat Fase 0/Prioritas #1), Integrasi Dapodik/EMIS/dst, AI Asisten, White-label, Offline Mode, Public API.

---

## Cara update memori ini
Kalau ada perubahan besar pada roadmap (fitur baru selesai, prioritas berubah, bug baru ditemukan): update artifact via `Artifact` tool (publish ke URL yang sama), lalu salin ringkasan perubahannya ke file ini juga supaya tetap sinkron.
