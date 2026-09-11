# Perbaikan Audit Jadwal Piket Guru Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup 7 kelompok temuan audit (3 High, 4 Medium) pada fitur Jadwal Piket Guru — gerbang keamanan yang menentukan siapa boleh mengisi jurnal/presensi sesi guru lain — plus 1 pass konsistensi UI/UX sesuai standar proyek.

**Architecture:** 8 task independen-sebisa-mungkin di domain Akademik (`app/Domains/Akademik/Actions/Piket/*`, `app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php`, `app/Http/Controllers/{Admin,Guru/Akademik}/*`, 2 view Blade, test baru). TIDAK menyentuh `app/Domains/Workflow/*`.

**Tech Stack:** Laravel 12 (PHP 8.3), Pest (test file fitur ini pakai Pest function-style `it(...)` dengan helper function biasa), Blade, Alpine.js, TomSelect (via `tomSelectPegawai` Alpine component yang sudah ada).

## Global Constraints

- Task 1 (timezone) HARUS pakai `now('Asia/Jakarta')` di titik-titik SPESIFIK yang disebut di bawah — JANGAN mengubah `config/app.php` atau `.env` `APP_TIMEZONE` secara global, itu SENGAJA ditolak (butuh audit terpisah, blast radius terlalu besar melampaui fitur ini).
- Task 1 WAJIB implementer verifikasi dulu bagaimana `Carbon::setTestNow()` berinteraksi dengan `now('Asia/Jakarta')` SEBELUM menulis assertion test baru — JANGAN diasumsikan otomatis benar.
- Task 4 (`diisi_oleh_guru_id`) PALING BERISIKO REGRESI — WAJIB jalankan ulang `tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php` SEBELUM task lain dianggap final, verifikasi asumsi null→null no-op benar-benar teruji, bukan cuma dipercaya dari pembacaan kode.
- Task 3 (kalender admin) HARUS aditif — JANGAN mengganti/menghapus variabel `overrides` yang sudah ada dan dipakai form override existing, TAMBAH variabel baru `piketHarianMendatang` di sampingnya.
- 4 item SENGAJA TIDAK masuk scope, JANGAN dikerjakan: perubahan `APP_TIMEZONE` global, validasi semester overlap, race condition locking (`lockForUpdate`), item Fase 2 (LaporanPiket, alur verifikasi Kepala Sekolah, cetak dokumen, dll — sudah di-exclude spec `.agents/specs/2026-09-06-guru-piket-jurnal-kbm.md` §5).
- JANGAN sentuh `app/Domains/Workflow/*` sama sekali.

---

## Task 1: Timezone Scoped `Asia/Jakarta` + Perbaikan Mismatch Tanggal

**Files:**
- Modify: `app/Domains/Akademik/Actions/Piket/GenerateJadwalPiketHarianAction.php`
- Modify: `app/Domains/Akademik/Actions/Piket/RegenerateJadwalPiketHarianAction.php`
- Modify: `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`
- Test: `tests/Feature/Guru/JurnalKbmSesiPiketTest.php` (tambah di akhir file)

**Interfaces:**
- Consumes: tidak ada dari task lain.
- Produces: tidak ada interface baru untuk task lain — perubahan murni internal ke logic "hari ini".

- [x] **Step 1: Tulis test yang gagal — guru piket tetap bisa akses di jam pagi WIB yang setara "kemarin" di UTC**
- [x] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**
- [x] **Step 3: Ubah `GenerateJadwalPiketHarianAction.php`**
- [x] **Step 4: Ubah `RegenerateJadwalPiketHarianAction.php`**
- [x] **Step 5: Ubah `JurnalKbmController::index()` — perbaiki timezone DAN mismatch tanggal sekaligus**
- [x] **Step 6: Jalankan test untuk memastikan LOLOS**
- [x] **Step 7: Jalankan test file terkait lain untuk cek regresi**
- [x] **Step 8: Commit**

```bash
git add app/Domains/Akademik/Actions/Piket/GenerateJadwalPiketHarianAction.php app/Domains/Akademik/Actions/Piket/RegenerateJadwalPiketHarianAction.php app/Http/Controllers/Guru/Akademik/JurnalKbmController.php tests/Feature/Guru/JurnalKbmSesiPiketTest.php
git commit -m "fix(piket): scoped timezone Asia/Jakarta di titik penentuan hari-ini + perbaiki mismatch tanggal browsing vs hari sungguhan

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: Badge "Mode Piket" di Halaman Isi Jurnal

**Files:**
- Modify: `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`
- Modify: `resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php`
- Test: `tests/Feature/Guru/JurnalKbmPiketAksesTest.php` (tambah di akhir file)

**Interfaces:**
- Consumes: `siapkanSesiDanGuruPiketUntukAksesTest()` (helper existing di file test yang sama, TIDAK diubah).
- Produces: tidak ada interface baru.

- [x] **Step 1: Tulis test yang gagal — banner muncul untuk guru piket, tidak muncul untuk pemilik**
- [x] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**
- [x] **Step 3: Eager-load relasi `guru` di controller**
- [x] **Step 4: Tambah banner di `show.blade.php`**
- [x] **Step 5: Jalankan test untuk memastikan LOLOS**
- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Guru/Akademik/JurnalKbmController.php resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php tests/Feature/Guru/JurnalKbmPiketAksesTest.php
git commit -m "fix(piket): tampilkan banner Mode Piket di halaman isi jurnal saat guru mengisi sesi guru lain

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 3: Kalender `PiketHarian` Read-Only untuk Admin (Semua Sumber)

**Files:**
- Modify: `app/Http/Controllers/Admin/JadwalPiketMingguanController.php`
- Modify: `resources/views/portals/lembaga/akademik/piket-guru/index.blade.php`
- Test: `tests/Feature/Admin/JadwalPiketMingguanControllerTest.php` (tambah di akhir file)

**Interfaces:**
- Consumes: tidak ada dari task lain.
- Produces: view `portals.lembaga.akademik.piket-guru.index` menerima variabel baru `piketHarianMendatang` (Collection `PiketHarian` kedua sumber) — TIDAK menggantikan `overrides` (tetap ada, tetap `override_manual` saja, tetap dipakai form hapus existing).

- [x] **Step 1: Tulis test yang gagal — view data berisi kedua sumber PiketHarian**
- [x] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**
- [x] **Step 3: Tambah query baru di `index()`**
- [x] **Step 4: Tambah seksi baru di `index.blade.php`**
- [x] **Step 5: Jalankan test untuk memastikan LOLOS**
- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/JadwalPiketMingguanController.php resources/views/portals/lembaga/akademik/piket-guru/index.blade.php tests/Feature/Admin/JadwalPiketMingguanControllerTest.php
git commit -m "feat(piket): tambah kalender read-only PiketHarian (semua sumber) di halaman admin, admin bisa verifikasi hasil generate otomatis

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 4: `diisi_oleh_guru_id` Tidak Ditimpa Null Saat Guru Pemilik Submit Ulang

**Files:**
- Modify: `app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php`
- Test: `tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php` (tambah di akhir file)

**Interfaces:**
- Consumes: tidak ada dari task lain.
- Produces: tidak ada interface baru — signature `execute()` TIDAK berubah.

**PALING BERISIKO REGRESI dari semua task di plan ini** — baca instruksi Step 5 dengan sangat teliti sebelum menganggap task ini selesai.

- [x] **Step 1: Tulis test yang gagal — pemilik submit ulang setelah pernah diisi piket, jejak TIDAK hilang**
- [x] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**
- [x] **Step 3: Ubah `RecordJurnalDanPresensiAction.php`**
- [x] **Step 4: Jalankan test baru untuk memastikan LOLOS**
- [x] **Step 5: WAJIB — verifikasi eksplisit test lama "diisi_oleh_guru_id TETAP null" masih benar-benar lolos untuk ALASAN YANG BENAR**
- [x] **Step 6: Jalankan seluruh test JurnalKbm untuk cek regresi lebih luas**
- [x] **Step 7: Commit**

```bash
git add app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php
git commit -m "fix(piket): jangan timpa diisi_oleh_guru_id jadi null saat guru pemilik submit ulang, pertahankan jejak akuntabilitas piket

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 5: Ganti `confirm()` Native Jadi `confirmDialog()` Standar Proyek

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/piket-guru/index.blade.php`

**Interfaces:**
- Consumes: `window.confirmDialog(title, message, options): Promise<boolean>` (global function, sudah terdaftar, TIDAK perlu registrasi baru).
- Produces: tidak ada interface baru.

- [x] **Step 1: Ubah form hapus Jadwal Piket Mingguan**
- [x] **Step 2: Ubah form hapus Override Manual**
- [x] **Step 3: Build asset frontend**
- [x] **Step 4: Verifikasi manual dev-server**
- [x] **Step 5: Jalankan test existing untuk memastikan tidak ada regresi**
- [x] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/piket-guru/index.blade.php
git commit -m "fix(piket): ganti confirm() native jadi confirmDialog standar proyek utk hapus jadwal/override piket

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 6: Test Regresi `resolveKartu()` untuk Guru Piket

**Files:**
- Test: `tests/Feature/Guru/JurnalKbmResolveKartuTest.php` (tambah di akhir file)

**Interfaces:**
- Consumes: `siapkanSesiDanGuruPiketUntukAksesTest()` (helper existing di `tests/Feature/Guru/JurnalKbmPiketAksesTest.php` — PENTING: helper ini didefinisikan TANPA `function_exists()` guard di file itu, cek dulu apakah perlu di-duplicate ke file target atau bisa dipanggil langsung karena Pest memuat semua file test dalam 1 process — kalau ada konflik nama function saat dijalankan bersamaan, implementer perlu menyesuaikan, JANGAN memaksa reuse kalau ternyata bentrok).
- Produces: tidak ada interface baru.

- [x] **Step 1: Tulis test baru — guru piket bisa resolve-kartu untuk sesi yang dia isi sebagai piket**

Tambahkan di `tests/Feature/Guru/JurnalKbmResolveKartuTest.php`, di akhir file:

```php
it('resolve-kartu berfungsi untuk guru piket yang mengisi sesi guru lain, bukan cuma guru pemilik', function () {
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_piket_resolve_kartu_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $guruPemilik = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $sesi = \App\Domains\Akademik\Models\SesiPembelajaran::factory()->create([
        'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'guru_id' => $guruPemilik->id, 'tanggal' => now()->toDateString(),
    ]);

    $guruPiket = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $userPiket = User::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Domains\Identity\Models\Person::where('id', $guruPiket->person_id)->update(['user_id' => $userPiket->id]);
    $userPiket->assignRole($role);
    \App\Domains\Akademik\Models\PiketHarian::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guruPiket->id, 'tanggal' => now()->toDateString(), 'sumber' => 'override_manual',
    ]);

    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-guru-piket-scan', 'is_active' => true]);

    $response = $this->actingAs($userPiket)->postJson(route('guru.jurnal-kbm.resolve-kartu', $sesi), ['kode' => 'kode-guru-piket-scan']);

    $response->assertOk();
    $response->assertJson(['siswa_id' => $siswa->id, 'nama_lengkap' => $siswa->nama_lengkap]);
});
```

**Catatan**: pakai import class yang SUDAH ADA di bagian atas file (`Guru`, `Kelas`, `Lembaga`, `Role`, `Semester`, `Siswa`, `TahunAjaran`, `User`, `Yayasan`, `KartuSiswa`, `Permission` — semua sudah di-import file existing) — untuk `SesiPembelajaran` dan `Person` yang mungkin belum di-import (dipakai versi `siapkanGuruDenganJadwalHariIni()` yang existing lewat cara lain), pakai namespace penuh seperti dicontohkan di atas, ATAU tambah `use` statement baru kalau lebih konsisten dengan gaya file — cek dulu bagaimana file ini biasa melakukannya.

- [x] **Step 2: Jalankan test untuk memastikan LOLOS (bukan TDD merah-hijau — ini test regresi murni, fitur sudah ada)**

Run: `vendor/bin/pest tests/Feature/Guru/JurnalKbmResolveKartuTest.php --compact`
Expected: PASS — semua test di file ini hijau (4 test lama + 1 test baru). Test baru ini TIDAK diharapkan gagal sebelum ada perubahan kode apa pun (tidak ada bug yang diperbaiki di task ini, murni menutup gap cakupan test — `resolveKartu()` secara kode sudah mendukung piket lewat `authorizeMilikGuru()` yang sama).

**Kalau test baru ini GAGAL** — itu tanda ada bug nyata di `resolveKartu()` untuk kasus guru piket yang SEBELUMNYA tidak terdeteksi karena tidak ada test-nya. STOP, laporkan detail kegagalan, JANGAN modifikasi test supaya lolos tanpa investigasi lebih dulu.

- [x] **Step 3: Commit**

```bash
git add tests/Feature/Guru/JurnalKbmResolveKartuTest.php
git commit -m "test(piket): tambah regresi resolveKartu() untuk guru piket, menutup gap cakupan test skenario 12 spec

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 7: Pass Konsistensi UI/UX — Analisa & Sesuaikan ke Standar Proyek

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/piket-guru/{index,create,edit}.blade.php`
- Controller `JadwalPiketMingguanController.php` TIDAK PERLU diubah — `guruList` yang sudah dikirim controller ke ketiga view CUKUP, `guruOptions` yang dibutuhkan `tomSelectPegawai` diturunkan LOKAL di Blade lewat blok `@php`, lihat Step 1.

**Interfaces:**
- Consumes: pola `tomSelectPegawai` Alpine component (SUDAH ADA di `resources/js/`, dipakai `resources/views/admin/kelas/_form.blade.php`) dan komponen `<x-select>` (SUDAH ADA di `resources/views/components/select.blade.php`).
- Produces: tidak ada interface baru untuk task lain (task terakhir sebelum penutup).

**INI BUKAN TASK KODE MEKANIS SEPERTI TASK LAIN — ini instruksi AUDIT-DAN-SESUAIKAN.** Halaman `piket-guru/{index,create,edit}.blade.php` saat ini pakai `<select>` native untuk pemilihan guru (tanpa search) dan tidak pakai `<x-select>` di select lain — TAPI JANGAN langsung copy-paste kode dari halaman lain tanpa analisa, karena konteks bisa berbeda (pernah terjadi di sesi audit sebelumnya: `<x-select>` yang dipasang di elemen dengan `:name` dinamis Alpine bentrok dengan compiler Blade, harus dicek dulu case-per-case).

**PRASYARAT: Task 3 dan Task 5 WAJIB sudah selesai & di-commit** (task ini menyentuh file `index.blade.php` yang sama, dikerjakan terakhir untuk hindari konflik).

- [x] **Step 1: Baca pola established `tomSelectPegawai` — JANGAN asumsikan bentuk datanya**
- [x] **Step 2: Baca 2-3 pemakaian `<x-select>` di halaman lain untuk konvensi prop**
- [x] **Step 3: Bandingkan dengan kondisi `piket-guru/{index,create,edit}.blade.php` SAAT INI, per elemen `<select>`**
- [x] **Step 4: Terapkan penyesuaian sesuai hasil analisa Step 3**
- [x] **Step 5: Build asset frontend**
- [x] **Step 6: Verifikasi manual dev-server**
- [x] **Step 7: Jalankan test existing untuk memastikan tidak ada regresi**
- [x] **Step 8: Commit**

```bash
git add resources/views/portals/lembaga/akademik/piket-guru/index.blade.php resources/views/portals/lembaga/akademik/piket-guru/create.blade.php resources/views/portals/lembaga/akademik/piket-guru/edit.blade.php
git commit -m "style(piket): select guru jadi searchable via tomSelectPegawai, <x-select> utk dropdown singkat, perbaiki a11y label

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 8: Regresi Penutup

**Files:**
- Tidak ada file yang dimodifikasi — task ini murni verifikasi.

**Interfaces:**
- Consumes: seluruh perubahan dari Task 1-7.
- Produces: tidak ada.

- [x] **Step 1: Jalankan seluruh test terkait fitur ini**

Run: `vendor/bin/pest tests/Feature/Admin/JadwalPiketMingguanControllerTest.php tests/Feature/Admin/PiketHarianControllerTest.php tests/Feature/Guru/JurnalKbmPiketAksesTest.php tests/Feature/Guru/JurnalKbmSesiPiketTest.php tests/Unit/Domains/Akademik/GenerateJadwalPiketHarianActionTest.php tests/Unit/Domains/Akademik/PiketAccessCheckerTest.php tests/Unit/Domains/Akademik/PiketModelsTest.php tests/Unit/Domains/Akademik/RegenerateJadwalPiketHarianActionTest.php tests/Feature/Akademik/JurnalKbmTanggalSusulanTest.php tests/Feature/Guru/JurnalKbmResolveKartuTest.php tests/Feature/Guru/JurnalKbmBatasEditTest.php tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php tests/Feature/Akademik/JurnalKbmAdaptiveTest.php tests/Feature/Guru/JurnalKbmControllerTest.php tests/Feature/Guru/JurnalKbmTenantScopeTest.php --compact`
Expected: semua PASS, tidak ada yang gagal.

- [x] **Step 2: Jalankan Pint pada semua file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` (jalankan ulang sampai `passed` kalau ada auto-fix diterapkan).

- [x] **Step 3: Jalankan full test suite proyek**

Run: `php artisan test --compact`
Expected: HANYA 3 kegagalan pre-existing yang sudah dikenal (`Tests\Unit\M3DemoDataSeederTest` x2, `Tests\Feature\Akademik\SubjekTenantValidationTest`) yang muncul. KALAU ADA kegagalan lain — STOP, jangan lanjut, laporkan detail (nama test, pesan error) alih-alih mengasumsikan pre-existing. **WAJIB jalankan SENDIRIAN** — bukan bersamaan dengan proses `pest`/`artisan test` lain yang sedang berjalan (pelajaran dari insiden sesi ini sebelumnya: 2 proses test paralel ke database test yang sama menghasilkan kegagalan palsu massal akibat rebutan koneksi).

- [x] **Step 4: Verifikasi manual dev-server — rekap checklist UI dari Task 2, 3, 5, 7**

Checklist ulang (boleh screenshot untuk laporan handoff): banner "Mode Piket" muncul saat guru piket isi sesi guru lain (Task 2), kalender read-only PiketHarian tampil dengan badge sumber yang benar (Task 3), confirmDialog custom muncul untuk hapus jadwal/override (Task 5), dropdown guru searchable + styling konsisten (Task 7).

- [x] **Step 5: Commit penutup (kalau ada sisa perubahan dari Pint)**

```bash
git status
```

Kalau ada perubahan tersisa dari auto-fix Pint yang belum ter-commit:

```bash
git add -u
git commit -m "style(piket): rapikan format Pint hasil perbaikan audit jadwal piket guru

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

Kalau working tree bersih, tidak perlu commit apa pun di step ini.

---

## Self-Review — Putaran 1 (cakupan spec + placeholder + konsistensi tipe)

**Cakupan spec**: §2.1+§2.5→Task 1, §2.2→Task 2, §2.3→Task 3, §2.4→Task 4, §2.6→Task 5, §2.7→Task 6, item §3 poin 3 (UI polish)→Task 7, §5 (pengujian)→tersebar ke tiap task + Task 8. §3 (item di luar scope) dikonfirmasi TIDAK ADA task yang menyentuh `config/app.php`/`.env` APP_TIMEZONE, validasi overlap semester, `lockForUpdate()`, atau item Fase 2 spec lama.

**Placeholder scan**: tidak ditemukan "TBD"/"TODO" di Task 1-6, 8 — semua kode lengkap siap salin. Task 7 SENGAJA instruksional (bukan kode template) sesuai permintaan eksplisit user, TAPI tetap punya langkah konkret bernomor (baca-bandingkan-analisa-terapkan-verifikasi), bukan instruksi kosong.

**Konsistensi tipe**: `now('Asia/Jakarta')` dipakai KONSISTEN persis sama di 3 titik Task 1. Variabel view `piketHarianMendatang` didefinisikan Task 3, tidak dikonsumsi task lain (murni tampilan). `array_filter` pattern Task 4 tidak dipakai di task lain (independen).

## Self-Review — Putaran 2 (verifikasi terhadap kode aktual & test existing)

- Dikonfirmasi ulang `RecordJurnalDanPresensiAction.php` baris 21-24 PERSIS seperti dikutip di Task 4 — dibaca langsung dari file sebelum plan ditulis.
- Dikonfirmasi ulang helper `siapkanSesiDanGuruPiketUntukAksesTest()` dan `siapkanGuruPiketDanSesiUntukAkuntabilitasTest()` MEMANG ada di file test yang dirujuk, dengan struktur return yang dipakai persis di Task 2 dan Task 4.
- Dikonfirmasi ulang pola `tomSelectPegawai` di `admin/kelas/_form.blade.php:91-108` — struktur wrapper `x-data`+`x-ref="selectElement"` dikutip akurat di Task 7 Step 1.
- Ditambahkan CATATAN EKSPLISIT di Task 1 Step 1 soal eksperimen `tinker` WAJIB dijalankan dulu untuk memverifikasi interaksi `Carbon::setTestNow()`+`now('Asia/Jakarta')` SEBELUM menulis assertion — sesuai instruksi kickoff, bukan diasumsikan.
- Ditambahkan CATATAN EKSPLISIT di Task 4 Step 5 yang memisahkan "PASS" dari "PASS untuk alasan yang benar" — supaya implementer tidak cuma lihat centang hijau tapi paham MENGAPA test lama itu tetap valid setelah perubahan (null→null via "tidak disentuh" ekuivalen null→null via "di-set eksplisit").

## Self-Review — Putaran 3 (dependency antar-task & urutan risiko)

- **Task 1 dan Task 2 sama-sama menyentuh file di bawah `Guru/Akademik/JurnalKbmController.php`** — dikonfirmasi ulang BEDA method (Task 1 di `index()`, Task 2 di `show()`) dan BEDA baris — aman independen, TIDAK perlu urutan khusus di antara keduanya.
- **Task 3 dan Task 5 SAMA-SAMA menyentuh `piket-guru/index.blade.php`** — dikonfirmasi baris yang disentuh BERBEDA (Task 3 menambah seksi baru SETELAH baris 101; Task 5 mengubah `onsubmit` di baris 34 dan 90, SEBELUM baris 101) — TIDAK overlap persis, tapi Task 7 (yang JUGA menyentuh file sama) tetap diurutkan PALING TERAKHIR di antara ketiganya untuk kehati-hatian ekstra (dicatat eksplisit sebagai PRASYARAT di Task 7).
- **Task 4 PALING BERISIKO** karena mengubah Action inti yang dipakai SEMUA alur isi jurnal (bukan cuma piket) — Step 6 di Task 4 SENGAJA menjalankan `tests/Feature/Guru` PENUH (bukan cuma file piket) untuk menangkap regresi di alur non-piket juga, sudah dicatat eksplisit.
- **Task 6 murni tambahan test, TIDAK ADA risiko regresi kode** — aman dikerjakan kapan saja, tapi tetap diurutkan sebelum Task 7/8 mengikuti pola plan lain di sesi ini (task test-only biasanya di tengah, bukan di awal/akhir mutlak).

## Self-Review — Putaran 4 (baca ulang dengan mata segar, cek instruksi Task 7 & kickoff)

- Dicek ulang Task 7 — SENGAJA ditulis TIDAK sebagai kode template siap-tempel (beda dari Task 1-6), melainkan 8 step yang memandu ANALISA dulu (Step 1-3) baru TERAPKAN (Step 4) — ini SELARAS dengan permintaan eksplisit user di percakapan untuk kickoff nanti memuat instruksi ke "agent lain" soal analisa UI/UX, bukan instruksi mekanis. Kickoff yang akan ditulis setelah ini WAJIB merujuk balik ke Task 7 dengan penekanan yang sama.
- Dicek ulang: Task 7 Step 3 poin 1 (cek elemen `x-for`/`:name` dinamis) SENGAJA mengingatkan risiko konflik binding `<x-select>` vs Alpine yang PERNAH ditemukan sesi ini sebelumnya (modul lain) — dicatat sebagai preseden konkret, bukan kekhawatiran abstrak, supaya implementer tahu ini BUKAN teori tapi kejadian nyata yang pernah terjadi.
- Dicek ulang total 8 task — cocok dengan 7 kelompok temuan spec §2 (Task 1-6, digabung sesuai §2.1+§2.5) + 1 task UI polish dari §3 poin 3 (Task 7) + 1 penutup (Task 8) = 8. Tidak ada yang tertukar posisi atau hilang.
- Dicek ulang commit message tiap task — semua pakai prefix `fix(piket)`/`feat(piket)`/`style(piket)`/`test(piket)` konsisten, memudahkan `git log --oneline | grep piket` untuk review nanti.
