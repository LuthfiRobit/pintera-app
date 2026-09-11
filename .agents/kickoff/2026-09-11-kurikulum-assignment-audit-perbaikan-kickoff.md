# Kickoff: Audit & Perbaikan Modul Assignment Kurikulum

**Base commit**: `819eac56` (`docs(kurikulum-assignment): tulis implementation plan, 5 putaran self-review...`)
**Branch**: `rbac-v2` (TETAP di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Modul Assignment Kurikulum (`resources/views/admin/kurikulum-assignment/*`) adalah rule engine yang menentukan kurikulum+fase default kelas baru lewat hirarki fallback 3 tingkat (spesifik tingkat → catch-all lembaga → platform default). Audit UI/UX menyeluruh sesi ini menemukan domain layer (`KurikulumAssignmentResolver`, `AssignKurikulumAction`, `UpdateKurikulumAssignmentAction`, form-request validation) **sudah solid, TIDAK ada bug keamanan/data-integrity**. Semua temuan murni di lapisan presentasi: 1 gap UX-yang-berisiko-jadi-human-error (input `tingkat` free-text), 1 gap keselamatan operasional (sinkronisasi massal tanpa konfirmasi), sisanya polish (layout, filter, badge, wording).

User secara eksplisit minta item kritis + polish **digabung dalam satu siklus** (bukan dipisah jadi 2 sub-proyek seperti tawaran awal).

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-11-kurikulum-assignment-audit-perbaikan.md` — spec lengkap, 5 putaran self-review, kode current-vs-fix KONKRET untuk 7 kelompok temuan (§2.1-§2.7), plus item yang SENGAJA dikeluarkan dari scope (§4) — termasuk 1 koreksi penting: opsi tingkat `"13"` (SMK 4 tahun) yang disebut laporan audit awal TIDAK ADA di kode aktual, JANGAN ditambahkan.
2. `.agents/plans/2026-09-11-kurikulum-assignment-audit-perbaikan.md` — 7 task, 5 putaran self-review. SEMUA task berisi kode lengkap siap salin (tidak ada task instruksional/analisa-manual seperti sesi Jadwal Piket Guru sebelumnya — modul ini murni presentational, tidak ada keputusan desain yang perlu dianalisis ulang oleh pelaksana).

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **JANGAN sentuh** `KurikulumAssignmentResolver`, `AssignKurikulumAction`, `UpdateKurikulumAssignmentAction` — domain layer sudah diverifikasi solid di audit, di luar scope plan ini sepenuhnya.
- **JANGAN tambah KPI "status drift global"** di halaman index — sengaja dikeluarkan (§4 spec), butuh loop `hitungDiff()` untuk SETIAP kombinasi lembaga×tahun-ajaran, terlalu mahal untuk index() yang saat ini ringan.
- **JANGAN convert `resync.blade.php` jadi full AJAX-SPA** — sengaja tetap GET-submit biasa + `confirmDialog`, disproporsional untuk halaman diagnostik low-traffic (dipakai sesekali, bukan CRUD harian).
- **JANGAN tambah opsi tingkat `"13"`** — `BentukPendidikan::validTingkatValues()` untuk SMK hanya `['10','11','12']`, TIDAK ADA elemen ke-4. Kalau tergoda "kan SMK ada yang 4 tahun", itu asumsi laporan audit awal yang SUDAH DIKOREKSI di spec §4 — jangan diikuti, ikuti kode aktual.
- **Filter AJAX baru di `index()` (Task 3) WAJIB ditempatkan SETELAH blok tenant-scoping yang sudah ada** (`if ($scope === 'yayasan') {...} elseif ($scope !== 'platform') {...}`) — plan sudah menuliskan urutan yang benar, JANGAN direstruktur supaya filter jalan "lebih dulu" atau semacamnya, itu akan membuka celah bypass tenant scope.
- **Nilai valid `tingkat` HARUS selalu dari `BentukPendidikan::validTingkatValues()`** — JANGAN di-hardcode ulang di Blade/JS manapun, walau cuma untuk 1 bentuk pendidikan saja.
- **Icon sinkronisasi yang benar adalah `sync`, BUKAN `sync_alt`** — `sync_alt` TIDAK ADA di `resources/views/components/icon.blade.php`, akan bikin Blade error kalau dipakai. Ini salah satu dari 3 koreksi nyata yang ditemukan saat menulis plan (lihat §7).

## 4. Urutan Task — Ada Ketergantungan File, BUKAN Independen Semua

Berbeda dari beberapa plan sebelumnya di sesi ini, task-task di plan ini **TIDAK semuanya independen** — ada 2 pasang yang menyentuh file yang sama secara berurutan:

- **Task 1** (pill selector tingkat, `_form.blade.php`) dan **Task 2** (confirmDialog resync, `resync.blade.php`) BISA dikerjakan duluan, bebas urutan di antara keduanya, dan bisa paralel kalau pakai subagent-driven (file berbeda).
- **Task 3** (index + `_daftar.blade.php` baru, `dataTableFilter`) independen dari Task 1/2, bisa paralel.
- **Task 4** (`_form.blade.php` — metadata card + callout + breadcrumb) **WAJIB SETELAH Task 1 selesai** — sama-sama edit `_form.blade.php`, Task 4 membangun di atas struktur `x-data` yang Task 1 buat. JANGAN dikerjakan sebagai subagent paralel dengan Task 1.
- **Task 5** (`resync.blade.php` lanjutan — diff chip, floating bar, `faseLamaNama`) **WAJIB SETELAH Task 2 selesai** — Task 5 memakai state Alpine `terpilih` yang Task 2 perkenalkan di form yang sama. JANGAN paralel dengan Task 2.
- **Task 6** (wording — verifikasi via test negatif) **WAJIB SETELAH Task 1-5 semua selesai** — SEMUA wording target sudah ditulis langsung di kode Task 1-5 (dicek eksplisit di plan), Task 6 murni grep-style verification, BUKAN menulis wording baru dari nol. Kalau pelaksana Task 6 mendapati test-nya FAIL, itu tanda ada teks yang KELEWAT di Task 1-5 — perbaiki file terkait, jangan tulis wording versi baru yang beda dari yang sudah disepakati di plan.
- **Task 7** (regression sweep) wajib paling akhir.

Urutan eksekusi yang disarankan (aman untuk subagent-driven sequential ATAU sebagian paralel): **Task 1 → Task 4 → Task 2 → Task 5 → Task 3 (kapan saja, independen) → Task 6 → Task 7**. Kalau mau maksimalkan paralelisme: Task 1 & Task 3 paralel dulu, lalu Task 4 (setelah Task 1 selesai) & Task 2 paralel, lalu Task 5 (setelah Task 2), lalu Task 6, lalu Task 7.

## 5. Fakta Operasional

- **Test file existing yang WAJIB direuse, JANGAN dibuat ulang**: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` (helper `actingAsKurikulumAssignmentManager()`, `actingAsYayasanKurikulumManager()`, `actingAsPlatformScopeKurikulumManager()` — SEMUA sudah ada, dipakai apa adanya di Task 1/3/4/6), `tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php`, `tests/Feature/Akademik/ResyncKurikulumFaseKelasTest.php` (helper `siapkanResyncFixture()` sudah ada, dipakai di Task 5).
- **Kalau Task 2 menambahkan helper `actingAsKurikulumAssignmentManager()` ke `ResyncKurikulumFaseControllerTest.php`**: plan Task 2 Step 1 SUDAH mewanti-wanti untuk `grep -rn "function actingAsKurikulumAssignmentManager" tests/` dulu sebelum menambahkan — Pest/PHP akan fatal error "cannot redeclare" kalau fungsi dengan nama sama sudah ada di file lain yang ikut ter-load bareng. Pelaksana WAJIB jalankan grep itu, bukan asumsi.
- **9 test lama existing di `KurikulumAssignmentControllerTest.php`** (permission denial, create/update/delete, cross-yayasan/lembaga guard, dll — sudah ditulis sebelum sesi ini) **HARUS TETAP PASS** di setiap task yang menyentuh file ini (Task 1, 3, 4, 6) — ini regression baseline paling penting di seluruh plan, karena test-test itu SUDAH membuktikan otorisasi/tenant-scope bekerja benar; task manapun yang bikin salah satu dari 9 test itu gagal berarti ada regresi otorisasi, bukan sekadar test rewrite.
- **`ResyncKurikulumFaseKelasTest.php` punya 5 test lama** (deteksi drift, exclude-yang-sudah-cocok, exclude-lembaga-lain, apply-hanya-yang-dicentang) — Task 5 Step 3 mengubah `hitungDiff()` HANYA menambah 1 key baca-saja (`faseLamaNama`), TIDAK mengubah kondisi `continue`/skip apa pun — 5 test lama ini harus tetap PASS tanpa modifikasi.

## 6. Instruksi Stop-and-Report

- **Kalau test baru di task manapun ("harus GAGAL sebelum fix") ternyata sudah PASS** — STOP, laporkan detail, jangan asumsikan "berarti sudah aman", ada kemungkinan file yang dimaksud sudah berubah dari yang diasumsikan plan.
- **Kalau salah satu dari 9 test lama `KurikulumAssignmentControllerTest.php` atau 5 test lama `ResyncKurikulumFaseKelasTest.php` gagal SETELAH perubahan Task manapun** — STOP TOTAL sebelum lanjut task berikutnya, laporkan detail. Ini baseline regresi paling kritis di plan ini (lihat §5).
- **Kalau Task 3 Step 1 test `'filter index() TIDAK bisa dipakai lembaga-scope actor untuk melihat assignment lembaga lain'` gagal** — STOP TOTAL, JANGAN lanjut ke Task lain. Ini test yang secara eksplisit membuktikan filter baru tidak membuka celah tenant-scope bypass; kegagalan di sini berarti ada regresi keamanan nyata, bukan sekadar bug kosmetik.
- **JANGAN jalankan full suite bersamaan dengan proses test lain yang sedang berjalan** — insiden nyata pernah terjadi di sesi ini (audit Pengadaan), 2 proses test paralel ke database test yang sama menghasilkan kegagalan palsu massal.
- **Task 7 Step 5 eksplisit: JANGAN jalankan full suite (`php artisan test` tanpa filter) tanpa izin user terlebih dahulu** — tanyakan dulu, baru jalankan sendirian (tidak paralel) kalau disetujui.

## 7. Catatan Serah Terima

- Spec ditulis dan direview 5 kali. Plan ditulis dan direview 5 kali, DAN menangkap **3 koreksi nyata** pasca-self-review (bukan cuma placeholder-scan biasa):
  1. Variabel bantu `$lembagaBentukPendidikanUntukPill` di draf spec salah untuk assignment GLOBAL mode edit (`$assignment->lembaga` bernilai `null` untuk assignment platform, jadi pill kosong total) — Task 1 plan sudah pakai `$assignment->bentuk_pendidikan` langsung sebagai gantinya.
  2. Icon `sync_alt` (dipakai draf spec §2.6) TIDAK ADA di komponen icon aplikasi ini — dicek langsung ke `resources/views/components/icon.blade.php`, hanya ada `sync`. Task 5 plan sudah pakai `sync`.
  3. Field **Bentuk Pendidikan** disebut spec §2.4 sebagai "immutable" (masuk metadata card read-only) — TERNYATA SALAH untuk aktor platform di mode edit (kode `KurikulumAssignmentController@update` baris 187-190 membuktikan platform BOLEH mengubahnya, hanya non-platform yang dikunci ikut lembaga). Task 4 plan sudah mengoreksi: metadata card cuma "Berlaku Untuk" + "Tahun Ajaran", Bentuk Pendidikan tetap dropdown/hidden-input seperti biasa.
  Ini bukti proses "baca kode asli, jangan asumsikan dari laporan audit" bekerja dan penting — pelaksana task JUGA harus siap melakukan verifikasi serupa kalau menemukan detail yang tidak cocok dengan asumsi plan, bukan memaksakan kode plan mentah-mentah kalau ternyata salah.
- Plan ini TIDAK meminta update handoff log sebagai bagian dari task-nya (Task 7 murni regression sweep + laporan). Kalau user menghendaki handoff log terpisah setelah eksekusi, itu permintaan tambahan mengikuti pola proyek-proyek sebelumnya di sesi ini — tanyakan ke user, jangan berasumsi otomatis wajib.
- Setelah semua task selesai, JANGAN merge/push branch — keputusan terpisah milik user.

## 8. Mulai dari mana

Disarankan mulai dari **Task 1** (`_form.blade.php` pill selector — paling kritis secara bisnis, dan hasilnya jadi prasyarat langsung buat Task 4) di `.agents/plans/2026-09-11-kurikulum-assignment-audit-perbaikan.md`. Kalau pakai subagent-driven dan ingin memanfaatkan paralelisme, Task 3 (index) bisa didispatch bersamaan karena filenya sama sekali terpisah dari Task 1/2/4/5.
