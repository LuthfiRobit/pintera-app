# Perbaikan 33 Test Seeder + 3 Seeder Cacat Pasca Penyusutan ke 1 Lembaga (SD) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memperbaiki 45 test gagal (33 file) di `tests/Unit`/`tests/Feature` yang meng-hardcode dunia-4-lembaga, PLUS memperbaiki 3 seeder aplikasi (`GuruJabatanTambahanSeeder`, `RiwayatPendidikanGuruSeeder`, `SertifikasiGuruSeeder`) yang masih silent-skip karena hardcode guru SMP yang sudah dihapus.

**Architecture:** Task 1 (3 seeder) WAJIB pertama — 3 test bergantung padanya. Sisanya dikelompokkan: batch mekanis (ganti angka/string literal), lalu per-file untuk yang perlu restrukturisasi (anchor siswa/guru baru, isolasi lintas-lembaga pakai `Lembaga::factory()` ad-hoc).

**Tech Stack:** Laravel 12, Pest.

## Global Constraints

- Baseline kode: commit `d5a6dbd` di branch `rbac-v2`. Kalau isi file berbeda signifikan dari yang dikutip plan, STOP, laporkan ke user.
- **WAJIB baca ulang file existing SEBELUM edit** — kutipan di plan ini hasil pembacaan langsung, tapi tetap verifikasi baseline belum berubah.
- Nilai dunia baru (referensi wajib untuk semua task, lihat spec §2): `Lembaga`=1, `Guru`=15, `User`(dari `UserSeeder` saja)=19, `Kelas`(×2TA)=24, `Siswa`=336, `PolaJam`=1, `JamPelajaran`=49, `MataPelajaran`=9, `JenisTesMaster`=3, `TahunAjaran`=2, `Semester`=4, `JenisTagihan`=2, `CalonMurid`=4, `Pendaftaran`=4, `Tagihan`=3, `TagihanItem`=3, `Pembayaran`=3, `SkPpdb`=1, `SkemaCicilan`=1, `Cicilan`=3, `AkunPendaftar`=1.
- Siswa pertama SD (deterministik): NIS `3333001`, nama **Muhammad Santoso**, `jenis_kelamin` L.
- Test scoped SEBELUM commit. Full suite HANYA task terakhir, izin eksplisit user dulu.
- JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan.

---

## Task 1: Perbaiki 3 Seeder Aplikasi — Retarget Guru SMP Dead ke Guru SD

**Files:**
- Modify: `database/seeders/GuruJabatanTambahanSeeder.php`
- Modify: `database/seeders/RiwayatPendidikanGuruSeeder.php`
- Modify: `database/seeders/SertifikasiGuruSeeder.php`

**Interfaces:**
- Consumes: `Guru`/`User` (12 wali kelas SD dari `GuruSeeder.php`/`UserSeeder.php`, sudah ada).
- Produces: data lengkap (tidak silent-skip) — dipakai Task 9, 15, 16.

- [ ] **Step 1: Baca ulang ketiga file, konfirmasi masih persis seperti dikutip di bawah**

```bash
cat database/seeders/GuruJabatanTambahanSeeder.php
cat database/seeders/RiwayatPendidikanGuruSeeder.php
cat database/seeders/SertifikasiGuruSeeder.php
```

- [ ] **Step 2: `GuruJabatanTambahanSeeder.php` — ganti 2 baris array `$data`**

Ganti:
```php
        $data = [
            'siti.rahmawati@demo.test' => ['jabatan' => 'Wali Kelas', 'tmt_tugas' => '2015-07-01'],
            'andi.wijaya@demo.test' => ['jabatan' => 'Pembina Ekstrakurikuler', 'tmt_tugas' => '2019-07-01'],
            'hendra.gunawan@demo.test' => ['jabatan' => 'Wakil Kepala Sekolah Kurikulum', 'tmt_tugas' => '2008-01-01'],
            'taufik.hidayat@demo.test' => ['jabatan' => 'Koordinator BK', 'tmt_tugas' => '2016-07-01'],
        ];
```
Menjadi:
```php
        $data = [
            'sari.wulandari@demo.test' => ['jabatan' => 'Wali Kelas', 'tmt_tugas' => '2015-07-01'],
            'agus.setiawan@demo.test' => ['jabatan' => 'Pembina Ekstrakurikuler', 'tmt_tugas' => '2019-07-01'],
            'hendra.gunawan@demo.test' => ['jabatan' => 'Wakil Kepala Sekolah Kurikulum', 'tmt_tugas' => '2008-01-01'],
            'taufik.hidayat@demo.test' => ['jabatan' => 'Koordinator BK', 'tmt_tugas' => '2016-07-01'],
        ];
```

- [ ] **Step 3: `RiwayatPendidikanGuruSeeder.php` — ganti 3 key array (`budi.santoso`/`siti.rahmawati`/`andi.wijaya` → SD)**

Ganti:
```php
            'budi.santoso@demo.test' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Universitas Pendidikan Indonesia', 'fakultas' => 'FPMIPA', 'bidang_studi' => 'Pendidikan Matematika', 'kependidikan' => true, 'tahun_masuk' => 2003, 'tahun_lulus' => 2007],
            ],
            'siti.rahmawati@demo.test' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Universitas Islam Negeri Sunan Gunung Djati', 'fakultas' => 'Tarbiyah', 'bidang_studi' => 'Pendidikan Guru Madrasah Ibtidaiyah', 'kependidikan' => true, 'tahun_masuk' => 2006, 'tahun_lulus' => 2010],
            ],
            'andi.wijaya@demo.test' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Institut Agama Islam Negeri Bandung', 'fakultas' => 'Tarbiyah', 'bidang_studi' => 'Pendidikan Agama Islam', 'kependidikan' => true, 'tahun_masuk' => 2008, 'tahun_lulus' => 2012],
            ],
```
Menjadi:
```php
            'sari.wulandari@demo.test' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Universitas Pendidikan Indonesia', 'fakultas' => 'FIP', 'bidang_studi' => 'Pendidikan Guru Sekolah Dasar', 'kependidikan' => true, 'tahun_masuk' => 2003, 'tahun_lulus' => 2007],
            ],
            'agus.setiawan@demo.test' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Universitas Islam Negeri Sunan Gunung Djati', 'fakultas' => 'Tarbiyah', 'bidang_studi' => 'Pendidikan Guru Madrasah Ibtidaiyah', 'kependidikan' => true, 'tahun_masuk' => 2006, 'tahun_lulus' => 2010],
            ],
            'nita.kurniawati@demo.test' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Institut Agama Islam Negeri Bandung', 'fakultas' => 'Tarbiyah', 'bidang_studi' => 'Pendidikan Agama Islam', 'kependidikan' => true, 'tahun_masuk' => 2008, 'tahun_lulus' => 2012],
            ],
```

Baris `hendra.gunawan@demo.test`, `maya.anggraini@demo.test`, `taufik.hidayat@demo.test` (sudah guru SD, alive) **TIDAK BERUBAH**.

- [ ] **Step 4: `SertifikasiGuruSeeder.php` — ganti 1 key array (`budi.santoso` → SD)**

Ganti:
```php
            'budi.santoso@demo.test' => ['jenis_sertifikasi' => 'Sertifikasi Guru (Portofolio)', 'nomor_sertifikat' => '123456789012', 'bidang_studi_sertifikasi' => 'Matematika', 'nrg' => '112233445566', 'tahun_sertifikasi' => 2012],
```
Menjadi:
```php
            'sari.wulandari@demo.test' => ['jenis_sertifikasi' => 'Sertifikasi Guru (Portofolio)', 'nomor_sertifikat' => '123456789012', 'bidang_studi_sertifikasi' => 'Guru Kelas', 'nrg' => '112233445566', 'tahun_sertifikasi' => 2012],
```

Baris `hendra.gunawan@demo.test`, `maya.anggraini@demo.test` **TIDAK BERUBAH**.

- [ ] **Step 5: Verifikasi syntax**

```bash
php -l database/seeders/GuruJabatanTambahanSeeder.php
php -l database/seeders/RiwayatPendidikanGuruSeeder.php
php -l database/seeders/SertifikasiGuruSeeder.php
```

- [ ] **Step 6: Commit**

```bash
git add database/seeders/GuruJabatanTambahanSeeder.php database/seeders/RiwayatPendidikanGuruSeeder.php database/seeders/SertifikasiGuruSeeder.php
git commit -m "fix(seeder): retarget 3 seeder guru SMP dead (GuruJabatanTambahan/RiwayatPendidikan/Sertifikasi) ke guru SD"
```

---

## Task 2: Perbaiki Test MEKANIS Batch A (7 file)

**Files:** `tests/Unit/LembagaSeederTest.php`, `tests/Unit/AkunPendaftarSeederTest.php`, `tests/Unit/CalonMuridSeederTest.php`, `tests/Unit/CicilanSeederTest.php`, `tests/Unit/EssentialUserSeederTest.php`, `tests/Unit/JamPelajaranSeederTest.php`, `tests/Unit/JenisTagihanSeederTest.php`

Untuk SETIAP file: baca isi lengkap dulu, lalu terapkan perubahan berikut.

- [ ] **Step 1: `LembagaSeederTest.php`** — ganti assertion `Lembaga::count())->toBe(4)` → `toBe(1)` (2 tempat, termasuk test idempoten). Hapus/ganti assertion yang lookup NPSN KB/TK/SMP (`20223311`/`20223322`/`20223344`) — sisakan HANYA verifikasi lembaga SD (`20223333`→`SDIT PINTERA`, `kode_lembaga`=`SDITPTR`). Judul test yang menyebut "4 K-9 institutions without SMA" → ganti jadi menyebut lembaga SD tunggal.

- [ ] **Step 2: `AkunPendaftarSeederTest.php`** — ganti `AkunPendaftar::count())->toBe(4)` → `toBe(1)`. Hapus assertion lookup email `pendaftar.kb@`/`pendaftar.tk@`/`pendaftar.smp@demo.test`, sisakan `pendaftar.sd@demo.test`.

- [ ] **Step 3: `CalonMuridSeederTest.php`** — ganti `CalonMurid::count())->toBe(16)` → `toBe(4)`. Judul `"4 calon per lembaga across all K-9 institutions"` → sesuaikan (mis. `"4 calon murid for the SD institution"`). Loop `foreach(Lembaga::all())` TIDAK diubah (generik, tetap valid).

- [ ] **Step 4: `CicilanSeederTest.php`** — ganti `Cicilan::count())->toBe(12)` → `toBe(3)`. Judul yang menyebut "K-9 institutions" disesuaikan. Loop generik TIDAK diubah.

- [ ] **Step 5: `EssentialUserSeederTest.php`** — baca file dulu (belum dikutip lengkap di plan ini, TAPI seeder aslinya sudah dikonfirmasi §4.2 kode aktual pakai email `.sd@demo.test`). Ganti SEMUA lookup email `.kb@demo.test` (`kepsek.kb@`, `adm.kb@`, `keuangan.kb@`, `guru.kb1@`, `kurikulum.kb@`, `sarpras.kb@`) → padanan `.sd@demo.test` (`kepsek.sd@`, `adm.sd@`, `keuangan.sd@`, `guru.sd1@`, `kurikulum.sd@`, `sarpras.sd@`) — 6 lookup, cek `database/seeders/EssentialUserSeeder.php` (Task 10 spec penyusutan) untuk memastikan nama akun cocok persis.

- [ ] **Step 6: `JamPelajaranSeederTest.php`** — baca file dulu. Hapus assertion `match` untuk cabang KB/TK/default (lembaga itu tak pernah ada lagi di `Lembaga::all()`), sisakan cabang SD (count=7/hari tetap benar, TIDAK berubah). Idempoten: `168` → `49`.

- [ ] **Step 7: `JenisTagihanSeederTest.php`** — ganti `JenisTagihan::count())->toBe(8)` → `toBe(2)`. Judul "per lembaga across all K-9 institutions" disesuaikan.

- [ ] **Step 8: Verifikasi syntax semua 7 file**

```bash
php -l tests/Unit/LembagaSeederTest.php tests/Unit/AkunPendaftarSeederTest.php tests/Unit/CalonMuridSeederTest.php tests/Unit/CicilanSeederTest.php tests/Unit/EssentialUserSeederTest.php tests/Unit/JamPelajaranSeederTest.php tests/Unit/JenisTagihanSeederTest.php
```

- [ ] **Step 9: Jalankan test scoped**

```bash
php artisan test tests/Unit/LembagaSeederTest.php tests/Unit/AkunPendaftarSeederTest.php tests/Unit/CalonMuridSeederTest.php tests/Unit/CicilanSeederTest.php tests/Unit/EssentialUserSeederTest.php tests/Unit/JamPelajaranSeederTest.php tests/Unit/JenisTagihanSeederTest.php
```
Expected: semua PASS.

- [ ] **Step 10: Commit**

```bash
git add tests/Unit/LembagaSeederTest.php tests/Unit/AkunPendaftarSeederTest.php tests/Unit/CalonMuridSeederTest.php tests/Unit/CicilanSeederTest.php tests/Unit/EssentialUserSeederTest.php tests/Unit/JamPelajaranSeederTest.php tests/Unit/JenisTagihanSeederTest.php
git commit -m "fix(test): update 7 seeder test mekanis ke dunia 1-lembaga SD (batch A)"
```

---

## Task 3: Perbaiki Test MEKANIS Batch B (7 file)

**Files:** `tests/Unit/LembagaDataPeriodikSeederTest.php`, `tests/Unit/LembagaProfileSeedersTest.php`, `tests/Unit/NominalTagihanJalurSeederTest.php`, `tests/Unit/PolaJamSeederTest.php`, `tests/Unit/PpdbConfigurationSeedersTest.php`, `tests/Unit/SemesterSeederTest.php`, `tests/Unit/SkPpdbSeederTest.php`

- [ ] **Step 1: `LembagaDataPeriodikSeederTest.php`** — baca file, cari `$smp = Lembaga::where('npsn','20223344')->first()`, ganti jadi `$sdit = Lembaga::where('npsn','20223333')->first()` dan semua pemakaian `$smp`→`$sdit` di test itu. Verifikasi 1x nilai `sumber_listrik`/`daya_listrik` yang di-assert memang generik (sama untuk semua jenjang) dengan baca `database/seeders/LembagaDataPeriodikSeeder.php` — kalau ternyata beda per jenjang, sesuaikan nilai expected ke versi SD.

- [ ] **Step 2: `LembagaProfileSeedersTest.php`** — baca file, hapus/ganti test ke-3 yang lookup `$smp = Lembaga::where('npsn','20223344')->first()` lalu assert `EkstrakurikulerLembaga` "Futsal" (jenjang SMP, cabang `default` di seeder sekarang dead code) — kalau ada padanan "Pramuka" untuk SD yang sudah di-assert test lain, HAPUS test SMP-Futsal ini total (bukan diganti nilai, karena skenarionya sendiri sudah tak relevan). 2 test lain (LayananKhusus/ProgramInklusi, sudah cek SD) TIDAK berubah.

- [ ] **Step 3: `NominalTagihanJalurSeederTest.php`** — baca file, ganti `$smp = Lembaga::where('npsn','20223344')->first()` → `$sdit = Lembaga::where('npsn','20223333')->first()`, sesuaikan semua pemakaian `$smp`→`$sdit`.

- [ ] **Step 4: `PolaJamSeederTest.php`** — ganti idempoten `PolaJam::count())->toBe(4)` → `toBe(1)`.

- [ ] **Step 5: `PpdbConfigurationSeedersTest.php`** — baca file, ganti `$smp = Lembaga::where('npsn','20223344')->first()` → `$sdit`, sesuaikan pemakaian. Verifikasi assertion jalur Reguler/Prestasi/Afirmasi, formulir "Sekolah Asal", dokumen "Akta Kelahiran" tetap berlaku generik untuk SD (baca seeder terkait kalau ragu).

- [ ] **Step 6: `SemesterSeederTest.php`** — ganti idempoten `Semester::count())->toBe(16)` → `toBe(4)`.

- [ ] **Step 7: `SkPpdbSeederTest.php`** — ganti idempoten `SkPpdb::count())->toBe(4)` → `toBe(1)`. Judul "per lembaga across all K-9 institutions" disesuaikan.

- [ ] **Step 8: Verifikasi syntax semua 7 file**

```bash
php -l tests/Unit/LembagaDataPeriodikSeederTest.php tests/Unit/LembagaProfileSeedersTest.php tests/Unit/NominalTagihanJalurSeederTest.php tests/Unit/PolaJamSeederTest.php tests/Unit/PpdbConfigurationSeedersTest.php tests/Unit/SemesterSeederTest.php tests/Unit/SkPpdbSeederTest.php
```

- [ ] **Step 9: Jalankan test scoped**

```bash
php artisan test tests/Unit/LembagaDataPeriodikSeederTest.php tests/Unit/LembagaProfileSeedersTest.php tests/Unit/NominalTagihanJalurSeederTest.php tests/Unit/PolaJamSeederTest.php tests/Unit/PpdbConfigurationSeedersTest.php tests/Unit/SemesterSeederTest.php tests/Unit/SkPpdbSeederTest.php
```
Expected: semua PASS.

- [ ] **Step 10: Commit**

```bash
git add tests/Unit/LembagaDataPeriodikSeederTest.php tests/Unit/LembagaProfileSeedersTest.php tests/Unit/NominalTagihanJalurSeederTest.php tests/Unit/PolaJamSeederTest.php tests/Unit/PpdbConfigurationSeedersTest.php tests/Unit/SemesterSeederTest.php tests/Unit/SkPpdbSeederTest.php
git commit -m "fix(test): update 7 seeder test mekanis ke dunia 1-lembaga SD (batch B)"
```

---

## Task 4: Perbaiki Test MEKANIS Batch C (3 file)

**Files:** `tests/Unit/SkemaCicilanSeederTest.php`, `tests/Unit/TagihanItemSeederTest.php`, `tests/Unit/TahunAjaranSeederTest.php`

- [ ] **Step 1: `SkemaCicilanSeederTest.php`** — ganti idempoten `SkemaCicilan::count())->toBe(4)` → `toBe(1)`. Judul disesuaikan.

- [ ] **Step 2: `TagihanItemSeederTest.php`** — ganti idempoten `TagihanItem::count())->toBe(12)` → `toBe(3)`. Judul disesuaikan.

- [ ] **Step 3: `TahunAjaranSeederTest.php`** — ganti idempoten `TahunAjaran::count())->toBe(8)` → `toBe(2)`. Judul disesuaikan.

- [ ] **Step 4: Verifikasi syntax**

```bash
php -l tests/Unit/SkemaCicilanSeederTest.php tests/Unit/TagihanItemSeederTest.php tests/Unit/TahunAjaranSeederTest.php
```

- [ ] **Step 5: Jalankan test scoped**

```bash
php artisan test tests/Unit/SkemaCicilanSeederTest.php tests/Unit/TagihanItemSeederTest.php tests/Unit/TahunAjaranSeederTest.php
```
Expected: semua PASS.

- [ ] **Step 6: Commit**

```bash
git add tests/Unit/SkemaCicilanSeederTest.php tests/Unit/TagihanItemSeederTest.php tests/Unit/TahunAjaranSeederTest.php
git commit -m "fix(test): update 3 seeder test mekanis ke dunia 1-lembaga SD (batch C)"
```

---

## Task 5: `GuruSeederTest.php`

**Files:** Modify `tests/Unit/GuruSeederTest.php`

Isi lengkap baseline sudah dikutip di riset — timpa dengan versi berikut:

- [ ] **Step 1: Timpa seluruh isi file**

```php
<?php
// tests/Unit/GuruSeederTest.php

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\GuruSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new UserSeeder())->run();
});

it('seeds Guru profiles for the SD institution', function () {
    (new GuruSeeder())->run();

    $sdit = Lembaga::where('npsn', '20223333')->first();
    $gurus = Guru::where('lembaga_id', $sdit->id)->get();
    expect($gurus->count())->toBe(15);

    $user = User::where('email', 'hendra.gunawan@demo.test')->first();
    $guru = Guru::where('user_id', $user->id)->first();

    expect($guru)->not->toBeNull();
    expect($guru->lembaga_id)->toBe($sdit->id);
    expect($guru->nik)->toBe('3273010108820004');
    expect($guru->status_kepegawaian)->toBe('PNS');
});

it('is idempotent when run twice', function () {
    (new GuruSeeder())->run();
    (new GuruSeeder())->run();

    expect(Guru::count())->toBe(15);
});
```

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l tests/Unit/GuruSeederTest.php
```

- [ ] **Step 3: Jalankan test scoped**

```bash
php artisan test tests/Unit/GuruSeederTest.php
```
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/GuruSeederTest.php
git commit -m "fix(test): GuruSeederTest retarget ke SD (15 guru), anchor hendra.gunawan"
```

---

## Task 6: `UserSeederTest.php`

**Files:** Modify `tests/Unit/UserSeederTest.php`

- [ ] **Step 1: Timpa seluruh isi file**

```php
<?php

use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\YayasanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    (new YayasanSeeder())->run();
    (new LembagaSeeder())->run();
});

it('seeds the yayasan admin and per-lembaga staff for the SD institution', function () {
    (new UserSeeder())->run();

    $adminYayasan = User::where('email', 'adm.yayasan@demo.test')->first();
    expect($adminYayasan)->not->toBeNull();
    expect($adminYayasan->hasRole('yayasan_super_admin'))->toBeTrue();
    expect($adminYayasan->lembaga_id)->toBeNull();
    expect($adminYayasan->email_verified_at)->not->toBeNull();

    $sdit = Lembaga::where('npsn', '20223333')->first();
    $kepsekSd = User::where('email', 'kepsek.sd@demo.test')->first();
    expect($kepsekSd->hasRole('kepala_sekolah'))->toBeTrue();
    expect($kepsekSd->lembaga_id)->toBe($sdit->id);

    $admSd = User::where('email', 'adm.sd@demo.test')->first();
    expect($admSd->hasRole('admin_administrasi'))->toBeTrue();
    expect($admSd->lembaga_id)->toBe($sdit->id);

    $keuanganSd = User::where('email', 'keuangan.sd@demo.test')->first();
    expect($keuanganSd->hasRole('admin_keuangan'))->toBeTrue();
    expect($keuanganSd->lembaga_id)->toBe($sdit->id);

    $guruSd = User::where('email', 'hendra.gunawan@demo.test')->first();
    expect($guruSd->hasRole('guru'))->toBeTrue();
    expect($guruSd->lembaga_id)->toBe($sdit->id);
});

it('is idempotent when run twice', function () {
    (new UserSeeder())->run();
    (new UserSeeder())->run();

    expect(User::count())->toBe(19);
});
```

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l tests/Unit/UserSeederTest.php
```

- [ ] **Step 3: Jalankan test scoped**

```bash
php artisan test tests/Unit/UserSeederTest.php
```
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/UserSeederTest.php
git commit -m "fix(test): UserSeederTest retarget ke SD tunggal (19 user), hapus assertion KB/TK/SMP"
```

---

## Task 7: `KelasSeederTest.php`

**Files:** Modify `tests/Unit/KelasSeederTest.php`

- [ ] **Step 1: Ganti array `$expectedNames` dalam `match` — hanya isi `'SD' =>`**

Ganti:
```php
        $expectedNames = match ($lembaga->bentuk_pendidikan) {
            'KB' => ['KB A-1', 'KB B-1'],
            'TK' => ['TK A-1', 'TK B-1'],
            'SD' => ['Kelas 1-A', 'Kelas 2-A', 'Kelas 3-A', 'Kelas 4-A', 'Kelas 5-A', 'Kelas 6-A'],
            default => ['VII-A', 'VII-B', 'VIII-A', 'VIII-B', 'IX-A'],
        };
```
Menjadi:
```php
        $expectedNames = match ($lembaga->bentuk_pendidikan) {
            'SD' => ['Kelas 1-A', 'Kelas 1-B', 'Kelas 2-A', 'Kelas 2-B', 'Kelas 3-A', 'Kelas 3-B', 'Kelas 4-A', 'Kelas 4-B', 'Kelas 5-A', 'Kelas 5-B', 'Kelas 6-A', 'Kelas 6-B'],
            default => [],
        };
```

(Cabang `KB`/`TK`/`default` lama sudah dead — tidak pernah lagi dieksekusi karena `Lembaga::all()` cuma berisi SD. Disederhanakan jadi 1 cabang + fallback kosong, bukan dihapus totalnya `match` supaya tetap valid PHP.)

- [ ] **Step 2: Ganti komentar + angka idempoten**

Ganti:
```php
    expect(Kelas::count())->toBe($sebelum);
    // Across 2 tahun ajaran per lembaga: (2 + 2 + 6 + 5) * 2 = 30 kelas total
    expect(Kelas::count())->toBe(30);
```
Menjadi:
```php
    expect(Kelas::count())->toBe($sebelum);
    // Across 2 tahun ajaran, 1 lembaga (SD): 12 kelas * 2 = 24 kelas total
    expect(Kelas::count())->toBe(24);
```

- [ ] **Step 3: Ganti judul test**

Ganti `'seeds appropriate classes for every K-9 institution and links them to PolaJam and Wali Kelas'` → `'seeds appropriate classes for the SD institution and links them to PolaJam and Wali Kelas'`.

- [ ] **Step 4: Verifikasi syntax**

```bash
php -l tests/Unit/KelasSeederTest.php
```

- [ ] **Step 5: Jalankan test scoped**

```bash
php artisan test tests/Unit/KelasSeederTest.php
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add tests/Unit/KelasSeederTest.php
git commit -m "fix(test): KelasSeederTest 12 kelas SD (2 rombel/tingkat), idempoten 24"
```

---

## Task 8: `SiswaSeederTest.php`

**Files:** Modify `tests/Unit/SiswaSeederTest.php`

- [ ] **Step 1: Ganti blok anchor siswa + judul**

Ganti:
```php
it('seeds students into active classes across all K-9 institutions', function () {
    (new SiswaSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
        $kelasIds = Kelas::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->pluck('id');
        
        $siswaCount = Siswa::whereIn('kelas_id', $kelasIds)->count();
        expect($siswaCount)->toBeGreaterThan(0);

        $siswaWithUser = Siswa::whereIn('kelas_id', $kelasIds)->whereNotNull('user_id')->first();
        expect($siswaWithUser)->not->toBeNull();
        expect($siswaWithUser->user->hasRole('siswa'))->toBeTrue();
    }

    $smp = Lembaga::where('npsn', '20223344')->first();
    $aditya = Siswa::where('lembaga_id', $smp->id)->where('nis', '2627001')->first();
    expect($aditya)->not->toBeNull();
    expect($aditya->nama_lengkap)->toBe('Aditya Pratama');
});
```
Menjadi:
```php
it('seeds students into active classes for the SD institution', function () {
    (new SiswaSeeder())->run();

    $sdit = Lembaga::where('npsn', '20223333')->first();
    $aktif = TahunAjaran::where('lembaga_id', $sdit->id)->where('status_aktif', true)->first();
    $kelasIds = Kelas::where('lembaga_id', $sdit->id)->where('tahun_ajaran_id', $aktif->id)->pluck('id');

    $siswaCount = Siswa::whereIn('kelas_id', $kelasIds)->count();
    expect($siswaCount)->toBe(336);

    $siswaWithUser = Siswa::whereIn('kelas_id', $kelasIds)->whereNotNull('user_id')->first();
    expect($siswaWithUser)->not->toBeNull();
    expect($siswaWithUser->user->hasRole('siswa'))->toBeTrue();

    $siswaPertama = Siswa::where('lembaga_id', $sdit->id)->where('nis', '3333001')->first();
    expect($siswaPertama)->not->toBeNull();
    expect($siswaPertama->nama_lengkap)->toBe('Muhammad Santoso');
});
```

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l tests/Unit/SiswaSeederTest.php
```

- [ ] **Step 3: Jalankan test scoped**

```bash
php artisan test tests/Unit/SiswaSeederTest.php
```
Expected: PASS. Kalau anchor "Muhammad Santoso"/NIS `3333001` TIDAK cocok (tergantung urutan `Kelas::all()` yang dipakai `SiswaSeeder::seedGenericStudents()` — kelas pertama hasil query BUKAN JAMINAN "Kelas 1-A" kalau tidak ada `orderBy`), STOP dan verifikasi dulu urutan aktual lewat tinker sebelum memaksakan nilai ini.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/SiswaSeederTest.php
git commit -m "fix(test): SiswaSeederTest retarget ke SD (336 siswa), anchor Muhammad Santoso NIS 3333001"
```

---

## Task 9: `GuruJabatanTambahanSeederTest.php` (butuh Task 1 selesai)

**Files:** Modify `tests/Unit/GuruJabatanTambahanSeederTest.php`

- [ ] **Step 1: Ganti anchor `siti.rahmawati` → `sari.wulandari`, idempoten count**

Ganti:
```php
it('assigns Wali Kelas to the guru who has one, and Wakil Kepala Sekolah Kurikulum to another', function () {
    (new GuruJabatanTambahanSeeder())->run();

    $siti = User::where('email', 'siti.rahmawati@demo.test')->first();
    $guruSiti = Guru::where('user_id', $siti->id)->first();
    expect(GuruJabatanTambahan::where('guru_id', $guruSiti->id)->exists())->toBeTrue();

    $hendra = User::where('email', 'hendra.gunawan@demo.test')->first();
    $guruHendra = Guru::where('user_id', $hendra->id)->first();
    expect(GuruJabatanTambahan::where('guru_id', $guruHendra->id)->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new GuruJabatanTambahanSeeder())->run();
    (new GuruJabatanTambahanSeeder())->run();

    expect(GuruJabatanTambahan::count())->toBe(4);
});
```
Menjadi:
```php
it('assigns Wali Kelas to the guru who has one, and Wakil Kepala Sekolah Kurikulum to another', function () {
    (new GuruJabatanTambahanSeeder())->run();

    $sari = User::where('email', 'sari.wulandari@demo.test')->first();
    $guruSari = Guru::where('user_id', $sari->id)->first();
    expect(GuruJabatanTambahan::where('guru_id', $guruSari->id)->exists())->toBeTrue();

    $hendra = User::where('email', 'hendra.gunawan@demo.test')->first();
    $guruHendra = Guru::where('user_id', $hendra->id)->first();
    expect(GuruJabatanTambahan::where('guru_id', $guruHendra->id)->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new GuruJabatanTambahanSeeder())->run();
    (new GuruJabatanTambahanSeeder())->run();

    expect(GuruJabatanTambahan::count())->toBe(4);
});
```

(Count idempoten TETAP 4 — cuma nama guru pengganti yang berubah, jumlah entri array tidak berubah.)

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l tests/Unit/GuruJabatanTambahanSeederTest.php
```

- [ ] **Step 3: Jalankan test scoped**

```bash
php artisan test tests/Unit/GuruJabatanTambahanSeederTest.php
```
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/GuruJabatanTambahanSeederTest.php
git commit -m "fix(test): GuruJabatanTambahanSeederTest anchor sari.wulandari pengganti siti.rahmawati"
```

---

## Task 10: `JenisTesMasterSeederTest.php`

**Files:** Modify `tests/Unit/JenisTesMasterSeederTest.php`

- [ ] **Step 1: Timpa seluruh isi file**

```php
<?php
// tests/Unit/JenisTesMasterSeederTest.php

use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
});

it('seeds jenis tes for the SD institution', function () {
    (new JenisTesMasterSeeder())->run();

    $sdit = Lembaga::where('npsn', '20223333')->first();
    expect(JenisTesMaster::where('lembaga_id', $sdit->id)->where('nama', 'Observasi Kesiapan Sekolah')->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new JenisTesMasterSeeder())->run();
    (new JenisTesMasterSeeder())->run();

    // SD saja: 3
    expect(JenisTesMaster::count())->toBe(3);
});
```

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l tests/Unit/JenisTesMasterSeederTest.php
```

- [ ] **Step 3: Jalankan test scoped**

```bash
php artisan test tests/Unit/JenisTesMasterSeederTest.php
```
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/JenisTesMasterSeederTest.php
git commit -m "fix(test): JenisTesMasterSeederTest retarget ke SD tunggal (3 jenis tes)"
```

---

## Task 11: `KomponenPenilaianSeederTest.php`

**Files:** Modify `tests/Unit/KomponenPenilaianSeederTest.php`

**PENTING**: `KomponenPenilaianSeeder.php` SEKARANG SELALU lewat `seedGenericKomponen()` untuk SD (kode `TP.1`, bobot 100) — cabang `seedSmpKomponen()` (kode `TP.1.1`, bobot 50) jadi dead code karena npsn SMP tak pernah cocok lagi. Assertion HARUS disesuaikan ke perilaku generik, BUKAN perilaku SMP lama.

- [ ] **Step 1: Ganti blok anchor**

Ganti:
```php
    $smp = Lembaga::where('npsn', '20223344')->first();
    $mtk = MataPelajaran::where('lembaga_id', $smp->id)->where('nama', 'Matematika')->first();
    expect(KomponenPenilaian::where('mata_pelajaran_id', $mtk->id)->where('kode', 'TP.1.1')->where('bobot', 50)->exists())->toBeTrue();
    expect((int) KomponenPenilaian::where('mata_pelajaran_id', $mtk->id)->sum('bobot'))->toBe(100);
```
Menjadi:
```php
    $sdit = Lembaga::where('npsn', '20223333')->first();
    $mtk = MataPelajaran::where('lembaga_id', $sdit->id)->where('nama', 'Matematika')->first();
    expect(KomponenPenilaian::where('mata_pelajaran_id', $mtk->id)->where('kode', 'TP.1')->where('bobot', 100)->exists())->toBeTrue();
    expect((int) KomponenPenilaian::where('mata_pelajaran_id', $mtk->id)->sum('bobot'))->toBe(100);
```

- [ ] **Step 2: Ganti judul test**

Ganti `'seeds assessment components across all K-9 institutions'` → `'seeds assessment components for the SD institution'`.

- [ ] **Step 3: Verifikasi syntax**

```bash
php -l tests/Unit/KomponenPenilaianSeederTest.php
```

- [ ] **Step 4: Jalankan test scoped**

```bash
php artisan test tests/Unit/KomponenPenilaianSeederTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/KomponenPenilaianSeederTest.php
git commit -m "fix(test): KomponenPenilaianSeederTest sesuaikan ke cabang generik SD (kode TP.1, bobot 100)"
```

---

## Task 12: `MataPelajaranSeederTest.php`

**Files:** Modify `tests/Unit/MataPelajaranSeederTest.php`

- [ ] **Step 1: Timpa seluruh isi file**

```php
<?php
// tests/Unit/MataPelajaranSeederTest.php

use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Yayasan;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\MataPelajaranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
});

it('seeds correct mata pelajaran for the SD institution', function () {
    (new MataPelajaranSeeder())->run();

    $sd = Lembaga::where('npsn', '20223333')->first();
    expect(MataPelajaran::where('lembaga_id', $sd->id)->where('nama', 'Ilmu Pengetahuan Alam dan Sosial (IPAS)')->exists())->toBeTrue();
    expect(MataPelajaran::where('lembaga_id', $sd->id)->where('nama', 'Matematika')->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new MataPelajaranSeeder())->run();
    (new MataPelajaranSeeder())->run();

    // SD saja: 9
    expect(MataPelajaran::count())->toBe(9);
});
```

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l tests/Unit/MataPelajaranSeederTest.php
```

- [ ] **Step 3: Jalankan test scoped**

```bash
php artisan test tests/Unit/MataPelajaranSeederTest.php
```
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/MataPelajaranSeederTest.php
git commit -m "fix(test): MataPelajaranSeederTest retarget ke SD tunggal (9 mapel)"
```

---

## Task 13: `NilaiSiswaSeederTest.php`

**Files:** Modify `tests/Unit/NilaiSiswaSeederTest.php`

- [ ] **Step 1: Ganti blok utama + judul**

Ganti:
```php
it('seeds student grades across all K-9 institutions', function () {
    (new NilaiSiswaSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
        $kelasIds = Kelas::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->pluck('id');
        $asesmenIds = Asesmen::whereIn('kelas_id', $kelasIds)->pluck('id');

        $nilaiCount = NilaiSiswa::whereIn('asesmen_id', $asesmenIds)->count();
        expect($nilaiCount)->toBeGreaterThan(0);
    }

    $smp = Lembaga::where('npsn', '20223344')->first();
    $aditya = Siswa::where('lembaga_id', $smp->id)->where('nis', '2627001')->first();
    expect(NilaiSiswa::where('siswa_id', $aditya->id)->count())->toBeGreaterThan(0);
});
```
Menjadi:
```php
it('seeds student grades for the SD institution', function () {
    (new NilaiSiswaSeeder())->run();

    $sdit = Lembaga::where('npsn', '20223333')->first();
    $aktif = TahunAjaran::where('lembaga_id', $sdit->id)->where('status_aktif', true)->first();
    $kelasIds = Kelas::where('lembaga_id', $sdit->id)->where('tahun_ajaran_id', $aktif->id)->pluck('id');
    $asesmenIds = Asesmen::whereIn('kelas_id', $kelasIds)->pluck('id');

    $nilaiCount = NilaiSiswa::whereIn('asesmen_id', $asesmenIds)->count();
    expect($nilaiCount)->toBeGreaterThan(0);

    $siswaPertama = Siswa::where('lembaga_id', $sdit->id)->where('nis', '3333001')->first();
    expect(NilaiSiswa::where('siswa_id', $siswaPertama->id)->count())->toBeGreaterThan(0);
});
```

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l tests/Unit/NilaiSiswaSeederTest.php
```

- [ ] **Step 3: Jalankan test scoped**

```bash
php artisan test tests/Unit/NilaiSiswaSeederTest.php
```
Expected: PASS. Kalau anchor NIS `3333001` tidak dapat nilai (bukan bagian dari "Kelas 1-A" yang dapat detail treatment — cek Task 15-18 plan susut-seeder, HANYA Kelas 1-A yang dapat asesmen detail), STOP dan verifikasi lewat tinker apakah siswa NIS `3333001` benar-benar masuk Kelas 1-A. Kalau ternyata bukan, ganti ke siswa NIS pertama yang PASTI masuk Kelas 1-A (query `Siswa::whereHas('kelas', fn($q)=>$q->where('nama','Kelas 1-A'))->orderBy('nis')->first()`).

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/NilaiSiswaSeederTest.php
git commit -m "fix(test): NilaiSiswaSeederTest retarget ke SD tunggal, anchor siswa Kelas 1-A"
```

---

## Task 14: `PresensiSeederTest.php`

**Files:** Modify `tests/Unit/PresensiSeederTest.php`

**PENTING**: `PresensiSeeder.php` sekarang pakai variasi modulo (`$index % 10 === 0` → sakit, `$index % 15 === 1` → izin) berdasarkan URUTAN siswa dalam `Siswa::where('kelas_id', $sesi->kelas_id)->get()`, BUKAN NIS spesifik. Cari siswa index ke-0 (index%10===0 pertama) di Kelas 1-A untuk anchor "sakit".

- [ ] **Step 1: Ganti blok utama + judul**

Ganti:
```php
it('seeds student attendance records across all K-9 institutions', function () {
    (new PresensiSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
        $kelasIds = Kelas::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->pluck('id');
        $sesiIds = SesiPembelajaran::whereIn('kelas_id', $kelasIds)->pluck('id');

        $presensiCount = Presensi::whereIn('sesi_pembelajaran_id', $sesiIds)->count();
        expect($presensiCount)->toBeGreaterThan(0);
    }

    $smp = Lembaga::where('npsn', '20223344')->first();
    $aditya = Siswa::where('lembaga_id', $smp->id)->where('nis', '2627001')->first();
    expect(Presensi::where('siswa_id', $aditya->id)->where('status', 'sakit')->exists())->toBeTrue();
});
```
Menjadi:
```php
it('seeds student attendance records for the SD institution, with sakit/izin variation', function () {
    (new PresensiSeeder())->run();

    $sdit = Lembaga::where('npsn', '20223333')->first();
    $aktif = TahunAjaran::where('lembaga_id', $sdit->id)->where('status_aktif', true)->first();
    $kelasIds = Kelas::where('lembaga_id', $sdit->id)->where('tahun_ajaran_id', $aktif->id)->pluck('id');
    $sesiIds = SesiPembelajaran::whereIn('kelas_id', $kelasIds)->pluck('id');

    $presensiCount = Presensi::whereIn('sesi_pembelajaran_id', $sesiIds)->count();
    expect($presensiCount)->toBeGreaterThan(0);

    // Formula PresensiSeeder: index 0 dari tiap kelas (index % 10 === 0) selalu 'sakit'.
    $siswaPertamaKelas1A = Siswa::whereHas('kelas', fn ($q) => $q->where('nama', 'Kelas 1-A'))->orderBy('nis')->first();
    expect(Presensi::where('siswa_id', $siswaPertamaKelas1A->id)->where('status', 'sakit')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l tests/Unit/PresensiSeederTest.php
```

- [ ] **Step 3: Jalankan test scoped**

```bash
php artisan test tests/Unit/PresensiSeederTest.php
```
Expected: PASS. Kalau anchor tidak match status 'sakit' (tergantung urutan `Siswa::where('kelas_id',...)->get()` yang dipakai `PresensiSeeder` — TIDAK PASTI sama dengan `orderBy('nis')` di test ini), STOP, cek urutan asli `PresensiSeeder.php` (apakah ada `orderBy` eksplisit atau urutan insertion default) dan sesuaikan query anchor di test supaya urutannya PERSIS SAMA.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/PresensiSeederTest.php
git commit -m "fix(test): PresensiSeederTest retarget ke SD tunggal, anchor siswa pertama Kelas 1-A (index modulo sakit)"
```

---

## Task 15: `RiwayatPendidikanGuruSeederTest.php` (butuh Task 1 selesai)

**Files:** Modify `tests/Unit/RiwayatPendidikanGuruSeederTest.php`

- [ ] **Step 1: Ganti anchor `budi.santoso` → `sari.wulandari`**

Ganti:
```php
it('seeds education history for a guru with a known S1 record', function () {
    (new RiwayatPendidikanGuruSeeder())->run();

    $user = User::where('email', 'budi.santoso@demo.test')->first();
    $guru = Guru::where('user_id', $user->id)->first();

    $riwayat = RiwayatPendidikanGuru::where('guru_id', $guru->id)->where('jenjang_pendidikan', 'S1')->first();
    expect($riwayat)->not->toBeNull();
    expect($riwayat->sekolah_formal)->toBe('Universitas Pendidikan Indonesia');
    expect($riwayat->bidang_studi)->toBe('Pendidikan Matematika');
});
```
Menjadi:
```php
it('seeds education history for a guru with a known S1 record', function () {
    (new RiwayatPendidikanGuruSeeder())->run();

    $user = User::where('email', 'sari.wulandari@demo.test')->first();
    $guru = Guru::where('user_id', $user->id)->first();

    $riwayat = RiwayatPendidikanGuru::where('guru_id', $guru->id)->where('jenjang_pendidikan', 'S1')->first();
    expect($riwayat)->not->toBeNull();
    expect($riwayat->sekolah_formal)->toBe('Universitas Pendidikan Indonesia');
    expect($riwayat->bidang_studi)->toBe('Pendidikan Guru Sekolah Dasar');
});
```

Test kedua (`'seeds a guru with two education records (S1 and S2)'`, anchor `hendra.gunawan`) dan test ketiga (idempoten, count=7) **TIDAK BERUBAH** — `hendra.gunawan` tetap alive, jumlah total entri array tidak berubah (cuma 3 nama guru diganti, bukan ditambah/dikurangi).

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l tests/Unit/RiwayatPendidikanGuruSeederTest.php
```

- [ ] **Step 3: Jalankan test scoped**

```bash
php artisan test tests/Unit/RiwayatPendidikanGuruSeederTest.php
```
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/RiwayatPendidikanGuruSeederTest.php
git commit -m "fix(test): RiwayatPendidikanGuruSeederTest anchor sari.wulandari pengganti budi.santoso"
```

---

## Task 16: `SertifikasiGuruSeederTest.php` (butuh Task 1 selesai)

**Files:** Modify `tests/Unit/SertifikasiGuruSeederTest.php`

- [ ] **Step 1: Ganti anchor `budi.santoso`→`sari.wulandari`, `siti.rahmawati`→`agus.setiawan` (agus TIDAK punya entri di `SertifikasiGuruSeeder.php`, jadi cocok untuk kasus "tanpa sertifikat")**

Ganti:
```php
it('seeds certification only for guru who have one, leaving others without', function () {
    (new SertifikasiGuruSeeder())->run();

    $bersertifikat = User::where('email', 'budi.santoso@demo.test')->first();
    $guruBersertifikat = Guru::where('user_id', $bersertifikat->id)->first();
    expect(SertifikasiGuru::where('guru_id', $guruBersertifikat->id)->exists())->toBeTrue();

    $tanpaSertifikat = User::where('email', 'siti.rahmawati@demo.test')->first();
    $guruTanpaSertifikat = Guru::where('user_id', $tanpaSertifikat->id)->first();
    expect(SertifikasiGuru::where('guru_id', $guruTanpaSertifikat->id)->exists())->toBeFalse();
});
```
Menjadi:
```php
it('seeds certification only for guru who have one, leaving others without', function () {
    (new SertifikasiGuruSeeder())->run();

    $bersertifikat = User::where('email', 'sari.wulandari@demo.test')->first();
    $guruBersertifikat = Guru::where('user_id', $bersertifikat->id)->first();
    expect(SertifikasiGuru::where('guru_id', $guruBersertifikat->id)->exists())->toBeTrue();

    $tanpaSertifikat = User::where('email', 'agus.setiawan@demo.test')->first();
    $guruTanpaSertifikat = Guru::where('user_id', $tanpaSertifikat->id)->first();
    expect(SertifikasiGuru::where('guru_id', $guruTanpaSertifikat->id)->exists())->toBeFalse();
});
```

Test idempoten (count=3) **TIDAK BERUBAH**.

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l tests/Unit/SertifikasiGuruSeederTest.php
```

- [ ] **Step 3: Jalankan test scoped**

```bash
php artisan test tests/Unit/SertifikasiGuruSeederTest.php
```
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/SertifikasiGuruSeederTest.php
git commit -m "fix(test): SertifikasiGuruSeederTest anchor sari.wulandari/agus.setiawan pengganti budi/siti"
```

---

## Task 17: `PendaftaranSeederTest.php` — Isolasi Lintas-Lembaga via Factory Ad-Hoc

**Files:** Modify `tests/Unit/PendaftaranSeederTest.php`

**Keputusan desain (spec §3.4)**: test isolasi lintas-lembaga dipertahankan (bukan dihapus) dengan menambah lembaga KEDUA via `Lembaga::factory()->create()` LANGSUNG di test, lalu me-replay seeder chain yang sama dari `beforeEach` supaya lembaga kedua itu dapat data lengkap (seeder-seeder terkait generik/`Lembaga::all()`, jadi otomatis memproses lembaga baru tanpa duplikasi lembaga pertama berkat `firstOrCreate`).

- [ ] **Step 1: Ganti test ke-1 (hapus loop multi-institusi, sisakan SD)**

Ganti:
```php
it('links each pendaftaran to the correct calon murid and lembaga, with decision fields set for diterima/ditolak across all K-9 institutions', function () {
    (new PendaftaranSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $staf = User::where('lembaga_id', $lembaga->id)->first();

        $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
        expect($diterima)->not->toBeNull();
        expect($diterima->calonMurid->nama_lengkap)->toBe('Calon Diterima ('.$lembaga->nama.')');
        expect($diterima->status)->toBe('diterima');
        expect($diterima->ditetapkan_oleh_user_id)->toBe($staf->id);

        $ditolak = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.ditolak@demo.test')->first();
        expect($ditolak->status)->toBe('ditolak');

        $menunggu = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.menunggu@demo.test')->first();
        expect($menunggu->status)->toBe('menunggu_verifikasi');

        $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan@demo.test')->first();
        expect($cicilanDemo->status)->toBe('diterima');
        expect($cicilanDemo->kode_pendaftaran)->toBe('REG-PEMBAYARAN-DEMO-'.$lembaga->id);
    }
});
```
Menjadi:
```php
it('links each pendaftaran to the correct calon murid and lembaga, with decision fields set for diterima/ditolak', function () {
    (new PendaftaranSeeder())->run();

    $lembaga = Lembaga::where('npsn', '20223333')->firstOrFail();
    $staf = User::where('lembaga_id', $lembaga->id)->first();

    $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
    expect($diterima)->not->toBeNull();
    expect($diterima->calonMurid->nama_lengkap)->toBe('Calon Diterima ('.$lembaga->nama.')');
    expect($diterima->status)->toBe('diterima');
    expect($diterima->ditetapkan_oleh_user_id)->toBe($staf->id);

    $ditolak = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.ditolak@demo.test')->first();
    expect($ditolak->status)->toBe('ditolak');

    $menunggu = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.menunggu@demo.test')->first();
    expect($menunggu->status)->toBe('menunggu_verifikasi');

    $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan@demo.test')->first();
    expect($cicilanDemo->status)->toBe('diterima');
    expect($cicilanDemo->kode_pendaftaran)->toBe('REG-PEMBAYARAN-DEMO-'.$lembaga->id);
});
```

- [ ] **Step 2: Ganti test ke-2 (isolasi) — tambah lembaga kedua via factory + replay seeder chain**

Ganti:
```php
it('does not mix up the same scenario email between different institutions', function () {
    (new PendaftaranSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $sdit = Lembaga::where('npsn', '20223333')->first();

    $pendaftaranSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
    $pendaftaranSdit = Pendaftaran::where('lembaga_id', $sdit->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();

    expect($pendaftaranSmp->id)->not->toBe($pendaftaranSdit->id);
    expect($pendaftaranSmp->calon_murid_id)->not->toBe($pendaftaranSdit->calon_murid_id);
});
```
Menjadi:
```php
it('does not mix up the same scenario email between different institutions', function () {
    $lembagaKedua = Lembaga::factory()->create(['yayasan_id' => Lembaga::first()->yayasan_id]);

    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
    (new JenisTesMasterSeeder())->run();
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new CalonMuridSeeder())->run();
    (new PendaftaranSeeder())->run();

    $sdit = Lembaga::where('npsn', '20223333')->first();

    $pendaftaranLembagaKedua = Pendaftaran::where('lembaga_id', $lembagaKedua->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
    $pendaftaranSdit = Pendaftaran::where('lembaga_id', $sdit->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();

    expect($pendaftaranLembagaKedua)->not->toBeNull();
    expect($pendaftaranSdit)->not->toBeNull();
    expect($pendaftaranLembagaKedua->id)->not->toBe($pendaftaranSdit->id);
    expect($pendaftaranLembagaKedua->calon_murid_id)->not->toBe($pendaftaranSdit->calon_murid_id);
});
```

**Tambahkan `use Database\Seeders\TahunAjaranSeeder;` dan `use Database\Seeders\SemesterSeeder;` di bagian atas file** (belum ada di `use` statement asli karena `beforeEach` sudah memanggilnya duluan, tapi test ini butuh panggil ulang untuk lembaga kedua).

- [ ] **Step 3: Ganti idempoten**

Ganti `expect(Pendaftaran::count())->toBe(16);` → `expect(Pendaftaran::count())->toBe(4);`.

- [ ] **Step 4: Verifikasi syntax**

```bash
php -l tests/Unit/PendaftaranSeederTest.php
```

- [ ] **Step 5: Jalankan test scoped**

```bash
php artisan test tests/Unit/PendaftaranSeederTest.php
```
Expected: PASS. Kalau test isolasi (Step 2) gagal karena lembaga kedua tidak lengkap datanya (misal `TahunAjaranSeeder`/`GelombangPpdbSeeder` butuh field tambahan yang tidak otomatis ter-generate factory), STOP dan laporkan detail error — mungkin perlu tambahan setup manual (mis. `status_aktif` eksplisit) untuk lembaga kedua yang tidak tercakup asumsi plan ini.

- [ ] **Step 6: Commit**

```bash
git add tests/Unit/PendaftaranSeederTest.php
git commit -m "fix(test): PendaftaranSeederTest sederhanakan ke SD tunggal + isolasi via Lembaga::factory() ad-hoc"
```

---

## Task 18: `TagihanSeederTest.php` — Isolasi Lintas-Lembaga via Factory Ad-Hoc

**Files:** Modify `tests/Unit/TagihanSeederTest.php`

Pola identik Task 17.

- [ ] **Step 1: Ganti test ke-1 (perbandingan SMP vs SDIT) — pakai lembaga kedua via factory**

Ganti:
```php
it('sets total_tagihan to the real configured NominalTagihanJalur value for each lembaga across K-9 institutions', function () {
    (new TagihanSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $sdit = Lembaga::where('npsn', '20223333')->first();

    $aktifSmp = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->first();
    $jalurSmp = JalurPpdb::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $aktifSmp->id)->where('nama', 'Reguler')->first();
    $jenisDaftarUlangSmp = JenisTagihan::where('lembaga_id', $smp->id)->where('nama', 'Uang Pangkal')->first();
    $nominalSmp = NominalTagihanJalur::where('jenis_tagihan_id', $jenisDaftarUlangSmp->id)->where('jalur_ppdb_id', $jalurSmp->id)->first();

    $diterimaSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
    $tagihanDaftarUlangSmp = Tagihan::where('pendaftaran_id', $diterimaSmp->id)->where('kategori', 'daftar_ulang')->first();
    expect((int) $tagihanDaftarUlangSmp->total_tagihan)->toBe((int) $nominalSmp->nominal);

    $aktifSdit = TahunAjaran::where('lembaga_id', $sdit->id)->where('status_aktif', true)->first();
    $jalurSdit = JalurPpdb::where('lembaga_id', $sdit->id)->where('tahun_ajaran_id', $aktifSdit->id)->where('nama', 'Reguler')->first();
    $jenisDaftarUlangSdit = JenisTagihan::where('lembaga_id', $sdit->id)->where('nama', 'Uang Pangkal')->first();
    $nominalSdit = NominalTagihanJalur::where('jenis_tagihan_id', $jenisDaftarUlangSdit->id)->where('jalur_ppdb_id', $jalurSdit->id)->first();

    $diterimaSdit = Pendaftaran::where('lembaga_id', $sdit->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
    $tagihanDaftarUlangSdit = Tagihan::where('pendaftaran_id', $diterimaSdit->id)->where('kategori', 'daftar_ulang')->first();
    expect((int) $tagihanDaftarUlangSdit->total_tagihan)->toBe((int) $nominalSdit->nominal);

    expect((int) $tagihanDaftarUlangSmp->total_tagihan)->not->toBe((int) $tagihanDaftarUlangSdit->total_tagihan);
});
```
Menjadi:
```php
it('sets total_tagihan to the real configured NominalTagihanJalur value, distinct per lembaga', function () {
    $lembagaKedua = Lembaga::factory()->create(['yayasan_id' => Lembaga::first()->yayasan_id]);

    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
    (new JenisTesMasterSeeder())->run();
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new JenisTagihanSeeder())->run();
    (new NominalTagihanJalurSeeder())->run();
    (new CalonMuridSeeder())->run();
    (new PendaftaranSeeder())->run();
    (new TagihanSeeder())->run();

    $sdit = Lembaga::where('npsn', '20223333')->first();

    $aktifSdit = TahunAjaran::where('lembaga_id', $sdit->id)->where('status_aktif', true)->first();
    $jalurSdit = JalurPpdb::where('lembaga_id', $sdit->id)->where('tahun_ajaran_id', $aktifSdit->id)->where('nama', 'Reguler')->first();
    $jenisDaftarUlangSdit = JenisTagihan::where('lembaga_id', $sdit->id)->where('nama', 'Uang Pangkal')->first();
    $nominalSdit = NominalTagihanJalur::where('jenis_tagihan_id', $jenisDaftarUlangSdit->id)->where('jalur_ppdb_id', $jalurSdit->id)->first();

    $diterimaSdit = Pendaftaran::where('lembaga_id', $sdit->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
    $tagihanDaftarUlangSdit = Tagihan::where('pendaftaran_id', $diterimaSdit->id)->where('kategori', 'daftar_ulang')->first();
    expect((int) $tagihanDaftarUlangSdit->total_tagihan)->toBe((int) $nominalSdit->nominal);

    $aktifKedua = TahunAjaran::where('lembaga_id', $lembagaKedua->id)->where('status_aktif', true)->first();
    $jalurKedua = JalurPpdb::where('lembaga_id', $lembagaKedua->id)->where('tahun_ajaran_id', $aktifKedua->id)->where('nama', 'Reguler')->first();
    $jenisDaftarUlangKedua = JenisTagihan::where('lembaga_id', $lembagaKedua->id)->where('nama', 'Uang Pangkal')->first();
    $nominalKedua = NominalTagihanJalur::where('jenis_tagihan_id', $jenisDaftarUlangKedua->id)->where('jalur_ppdb_id', $jalurKedua->id)->first();

    $diterimaKedua = Pendaftaran::where('lembaga_id', $lembagaKedua->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
    $tagihanDaftarUlangKedua = Tagihan::where('pendaftaran_id', $diterimaKedua->id)->where('kategori', 'daftar_ulang')->first();
    expect((int) $tagihanDaftarUlangKedua->total_tagihan)->toBe((int) $nominalKedua->nominal);
});
```

**Tambahkan `use Database\Seeders\SemesterSeeder;` di bagian atas file.**

- [ ] **Step 2: Ganti test ke-2 (loop `foreach(Lembaga::all())`, hapus komentar "per K-9 lembaga" — logic TIDAK berubah, generik tetap valid)**

Ganti judul `'creates 2 tagihan for the diterima candidate and 1 for the cicilan-demo candidate, per K-9 lembaga'` → `'creates 2 tagihan for the diterima candidate and 1 for the cicilan-demo candidate'`. Isi loop TIDAK berubah.

- [ ] **Step 3: Ganti idempoten**

Ganti `expect(Tagihan::count())->toBe(12);` → `expect(Tagihan::count())->toBe(3);`.

- [ ] **Step 4: Verifikasi syntax**

```bash
php -l tests/Unit/TagihanSeederTest.php
```

- [ ] **Step 5: Jalankan test scoped**

```bash
php artisan test tests/Unit/TagihanSeederTest.php
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add tests/Unit/TagihanSeederTest.php
git commit -m "fix(test): TagihanSeederTest sederhanakan ke SD tunggal + isolasi via Lembaga::factory() ad-hoc"
```

---

## Task 19: `PembayaranSeederTest.php` — Isolasi Lintas-Lembaga via Factory Ad-Hoc

**Files:** Modify `tests/Unit/PembayaranSeederTest.php`

Pola identik Task 17-18.

- [ ] **Step 1: Sederhanakan test ke-1 dan ke-2 (loop `foreach(Lembaga::all())` — TIDAK diubah isinya, cuma hapus frasa "per K-9 lembaga" di judul)**

Judul test 1: `'creates a pending payment for each of the diterima candidate 2 tagihan, per K-9 lembaga'` → `'creates a pending payment for each of the diterima candidate 2 tagihan'`.
Judul test 2: `'creates a pending payment for termin 1 of the cicilan-demo candidate, per K-9 lembaga'` → `'creates a pending payment for termin 1 of the cicilan-demo candidate'`.

- [ ] **Step 2: Ganti test ke-3 (isolasi SMP vs SDIT) — pakai lembaga kedua via factory**

Ganti:
```php
it('traces the full chain from a diterima pendaftaran through to its pembayaran across all institutions without mixups', function () {
    (new PembayaranSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();

        expect($diterima->calonMurid->nama_lengkap)->toBe('Calon Diterima ('.$lembaga->nama.')');
        expect($diterima->lembaga_id)->toBe($lembaga->id);
        expect($diterima->skPpdb->lembaga_id)->toBe($lembaga->id);

        $tagihanDaftarUlang = Tagihan::where('pendaftaran_id', $diterima->id)->where('kategori', 'daftar_ulang')->first();
        expect($tagihanDaftarUlang)->not->toBeNull();

        $item = TagihanItem::where('tagihan_id', $tagihanDaftarUlang->id)->first();
        expect($item->jenisTagihan->lembaga_id)->toBe($lembaga->id);
        expect((int) $item->jumlah)->toBe((int) $tagihanDaftarUlang->total_tagihan);

        $pembayaran = Pembayaran::where('tagihan_id', $tagihanDaftarUlang->id)->first();
        expect($pembayaran->tagihan->pendaftaran->id)->toBe($diterima->id);
    }

    $smp = Lembaga::where('npsn', '20223344')->first();
    $sdit = Lembaga::where('npsn', '20223333')->first();
    $diterimaSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
    $diterimaSdit = Pendaftaran::where('lembaga_id', $sdit->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();

    expect($diterimaSmp->sk_ppdb_id)->not->toBe($diterimaSdit->sk_ppdb_id);
    expect($diterimaSmp->calon_murid_id)->not->toBe($diterimaSdit->calon_murid_id);
});
```
Menjadi:
```php
it('traces the full chain from a diterima pendaftaran through to its pembayaran without mixups', function () {
    (new PembayaranSeeder())->run();

    $sdit = Lembaga::where('npsn', '20223333')->first();
    $diterima = Pendaftaran::where('lembaga_id', $sdit->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();

    expect($diterima->calonMurid->nama_lengkap)->toBe('Calon Diterima ('.$sdit->nama.')');
    expect($diterima->lembaga_id)->toBe($sdit->id);
    expect($diterima->skPpdb->lembaga_id)->toBe($sdit->id);

    $tagihanDaftarUlang = Tagihan::where('pendaftaran_id', $diterima->id)->where('kategori', 'daftar_ulang')->first();
    expect($tagihanDaftarUlang)->not->toBeNull();

    $item = TagihanItem::where('tagihan_id', $tagihanDaftarUlang->id)->first();
    expect($item->jenisTagihan->lembaga_id)->toBe($sdit->id);
    expect((int) $item->jumlah)->toBe((int) $tagihanDaftarUlang->total_tagihan);

    $pembayaran = Pembayaran::where('tagihan_id', $tagihanDaftarUlang->id)->first();
    expect($pembayaran->tagihan->pendaftaran->id)->toBe($diterima->id);

    // Isolasi lintas-lembaga: tambah lembaga kedua ad-hoc, replay seeder chain.
    $lembagaKedua = Lembaga::factory()->create(['yayasan_id' => $sdit->yayasan_id]);
    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
    (new JenisTesMasterSeeder())->run();
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new JenisTagihanSeeder())->run();
    (new NominalTagihanJalurSeeder())->run();
    (new CalonMuridSeeder())->run();
    (new PendaftaranSeeder())->run();
    (new SkPpdbSeeder())->run();
    (new TagihanSeeder())->run();
    (new TagihanItemSeeder())->run();
    (new SkemaCicilanSeeder())->run();
    (new CicilanSeeder())->run();
    (new PembayaranSeeder())->run();

    $diterimaKedua = Pendaftaran::where('lembaga_id', $lembagaKedua->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();

    expect($diterima->sk_ppdb_id)->not->toBe($diterimaKedua->sk_ppdb_id);
    expect($diterima->calon_murid_id)->not->toBe($diterimaKedua->calon_murid_id);
});
```

**Tambahkan `use Database\Seeders\SemesterSeeder;` di bagian atas file.**

- [ ] **Step 3: Ganti idempoten**

Ganti `expect(Pembayaran::count())->toBe(12);` → `expect(Pembayaran::count())->toBe(3);`.

- [ ] **Step 4: Verifikasi syntax**

```bash
php -l tests/Unit/PembayaranSeederTest.php
```

- [ ] **Step 5: Jalankan test scoped**

```bash
php artisan test tests/Unit/PembayaranSeederTest.php
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add tests/Unit/PembayaranSeederTest.php
git commit -m "fix(test): PembayaranSeederTest sederhanakan ke SD tunggal + isolasi via Lembaga::factory() ad-hoc"
```

---

## Task 20: `GelombangJalurRestrictionTest.php` — Balik Ekspektasi (SD Sekarang Restricted)

**Files:** Modify `tests/Feature/GelombangJalurRestrictionTest.php`

**PENTING**: HANYA test terakhir (`'seeds a restricted demo gelombang for SMP...'`) yang perlu diedit — 12 test lain di file ini pakai helper `buatGelombangDenganDuaJalur()` yang fully independen (`Lembaga::factory()`), TIDAK bergantung `DatabaseSeeder` sama sekali, TIDAK disentuh.

- [ ] **Step 1: Ganti test terakhir**

Ganti:
```php
it('seeds a restricted demo gelombang for SMP alongside unrestricted ones', function () {
    $this->seed();

    $smp = \App\Models\Lembaga::where('npsn', '20223344')->firstOrFail();
    $smpAktif = \App\Models\TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->firstOrFail();
    $gelombang1 = GelombangPpdb::where('lembaga_id', $smp->id)
        ->where('tahun_ajaran_id', $smpAktif->id)
        ->where('nama', 'Gelombang 1')
        ->firstOrFail();

    expect($gelombang1->jalur()->pluck('jalur_ppdb.nama')->sort()->values()->all())->toBe(['Prestasi', 'Reguler']);

    $sd = \App\Models\Lembaga::where('npsn', '20223333')->firstOrFail();
    $sdAktif = \App\Models\TahunAjaran::where('lembaga_id', $sd->id)->where('status_aktif', true)->firstOrFail();
    $sdGelombang1 = GelombangPpdb::where('lembaga_id', $sd->id)
        ->where('tahun_ajaran_id', $sdAktif->id)
        ->where('nama', 'Gelombang 1')
        ->firstOrFail();

    expect($sdGelombang1->jalur()->exists())->toBeFalse();
});
```
Menjadi:
```php
it('seeds a restricted demo gelombang for the SD institution', function () {
    $this->seed();

    $sd = \App\Models\Lembaga::where('npsn', '20223333')->firstOrFail();
    $sdAktif = \App\Models\TahunAjaran::where('lembaga_id', $sd->id)->where('status_aktif', true)->firstOrFail();
    $gelombang1 = GelombangPpdb::where('lembaga_id', $sd->id)
        ->where('tahun_ajaran_id', $sdAktif->id)
        ->where('nama', 'Gelombang 1')
        ->firstOrFail();

    expect($gelombang1->jalur()->pluck('jalur_ppdb.nama')->sort()->values()->all())->toBe(['Prestasi', 'Reguler']);
});
```

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l tests/Feature/GelombangJalurRestrictionTest.php
```

- [ ] **Step 3: Jalankan test scoped**

```bash
php artisan test tests/Feature/GelombangJalurRestrictionTest.php
```
Expected: semua 13 test PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/GelombangJalurRestrictionTest.php
git commit -m "fix(test): GelombangJalurRestrictionTest balik ekspektasi - SD sekarang restricted (bukan SMP)"
```

---

## Task 21: Verifikasi Akhir Menyeluruh + Handoff Log

**Files:**
- Create: `.agents/logs/2026-08-24-perbaikan-test-pasca-susut-seeder.md`

- [ ] **Step 1: Grep gabungan — pastikan tidak ada sisa identitas lama**

```bash
grep -rn "20223311\|20223322\|20223344\|budi.santoso\|siti.rahmawati\|andi.wijaya" database/seeders/*.php tests/Unit/*.php tests/Feature/GelombangJalurRestrictionTest.php
```
Expected: KOSONG total.

- [ ] **Step 2: Jalankan seluruh test yang disentuh plan ini sekaligus**

```bash
php artisan test tests/Unit tests/Feature/GelombangJalurRestrictionTest.php
```
Expected: 0 failed.

- [ ] **Step 3: Verifikasi 3 seeder aplikasi via migrate:fresh --seed + tinker**

```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="
echo 'GuruJabatanTambahan: '.\App\Models\GuruJabatanTambahan::count().PHP_EOL;
echo 'RiwayatPendidikanGuru: '.\App\Models\RiwayatPendidikanGuru::count().PHP_EOL;
echo 'SertifikasiGuru: '.\App\Models\SertifikasiGuru::count().PHP_EOL;
"
```
Expected: `GuruJabatanTambahan`=4, `RiwayatPendidikanGuru`=7, `SertifikasiGuru`=3 (angka sama seperti sebelumnya — cuma nama guru pengganti yang berubah, bukan jumlah).

- [ ] **Step 4: Minta izin user untuk full test suite**

Tanya ke user: "Task 1-20 selesai, grep gabungan kosong total, seluruh test Unit+GelombangJalurRestrictionTest hijau, 3 seeder aplikasi terverifikasi tidak lagi silent-skip. Boleh saya jalankan full test suite untuk verifikasi akhir?" — TUNGGU jawaban eksplisit.

- [ ] **Step 5: Jalankan full suite SOLO**

```bash
php artisan test
```
Catat angka PASTI passed/failed/duration.

- [ ] **Step 6: Tulis handoff log**

Buat `.agents/logs/2026-08-24-perbaikan-test-pasca-susut-seeder.md` (Bahasa Indonesia): ringkasan Task 1-20 dengan commit hash, hasil grep Step 1, hasil test Step 2 dan Step 5 (angka pasti, jangan dicampur), konfirmasi 3 seeder aplikasi Step 3.

- [ ] **Step 7: Commit**

```bash
git add .agents/logs/2026-08-24-perbaikan-test-pasca-susut-seeder.md
git commit -m "docs(test): handoff log perbaikan 33 test + 3 seeder pasca susut seeder 1-lembaga"
```
