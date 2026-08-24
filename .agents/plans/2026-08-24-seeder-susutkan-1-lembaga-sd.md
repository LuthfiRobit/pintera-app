# Susutkan Seeder Demo ke 1 Lembaga (SD), Data Volume Realistis Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyusutkan `DatabaseSeeder.php` supaya hanya membuat 1 lembaga (SDIT PINTERA, NPSN 20223333), dengan volume data di dalamnya proporsional realistis (12 kelas, ~336 siswa, ~15 guru, dst) — bukan sekadar hapus lembaga lain lalu biarkan sisa seeder crash/kosong.

**Architecture:** 27 file seeder dikategorikan 6 grup berdasarkan tingkat risiko (crash-total, silent-skip, volume-rendah, tidak disentuh) — dikerjakan berurutan sesuai dependency: `LembagaSeeder` dulu, lalu 5 file yang akan CRASH kalau tidak diperbaiki, checkpoint verifikasi, baru 3 file silent-skip + volume/kualitas.

**Tech Stack:** Laravel 12, Eloquent seeder standar (`firstOrCreate`/`updateOrCreate`, idempotent).

## Global Constraints

- **Data buang-pakai** — TIDAK ada migrasi data lama yang dipertahankan. `migrate:fresh --seed` adalah cara verifikasi normal di setiap task.
- **Lembaga yang dipertahankan: SD (SDIT PINTERA, NPSN 20223333, kode SDITPTR)** — satu-satunya.
- **Zero-crash adalah syarat KERAS** — `php artisan migrate:fresh --seed` HARUS selesai tanpa exception di task terakhir.
- **6 file TIDAK disentuh** (generik penuh, otomatis ikut menyusut): `TahunAjaranSeeder.php`, `SemesterSeeder.php`, `MataPelajaranSeeder.php`, `GelombangPpdbSeeder.php`, `JalurPpdbSeeder.php`, `PendaftaranSeeder.php`, `CalonMuridSeeder.php`, `WorkflowDefinitionSeeder.php`, `KomponenPenilaianSeeder.php` — JANGAN diedit di plan ini kecuali task eksplisit menyebutkannya.
- Baseline kode: commit `bf0c4d8` di branch `rbac-v2`. Kalau isi file berbeda signifikan dari yang dikutip plan, STOP, laporkan ke user.
- Target volume (dari spec §4): 12 kelas (2 rombel × 6 tingkat), ~336 siswa (28/kelas), ~15 guru (12 wali kelas + 3 guru mapel spesialis existing), 4-6 orang tua dengan akun login + 1 demo-login, 7 skenario Kasus Pendampingan.
- Detail treatment Asesmen/Jadwal/Nilai/Sesi Pembelajaran HANYA untuk 2 kelas pertama ("Kelas 1-A"/"Kelas 1-B") — meniru kedalaman yang SMP miliki sebelumnya (SMP juga cuma detail di VII-A/VII-B, kelas VIII/IX tidak dapat treatment detail). Kelas 2-6 tetap pakai fallback generik existing (`seedGenericAsesmen` dkk, TIDAK diubah).

---

## Task 1: `LembagaSeeder.php` — Sisakan 1 Blok SD

**Files:**
- Modify: `database/seeders/LembagaSeeder.php`

**Interfaces:**
- Produces: `Lembaga::count() === 1`, satu-satunya row NPSN `20223333`/kode `SDITPTR` — dipakai SEMUA task berikutnya sebagai satu-satunya target lembaga.

Ini WAJIB dikerjakan PERTAMA — file lain bergantung pada lembaga apa yang tersedia untuk mendeteksi crash sejak dini.

- [ ] **Step 1: Baca ulang file existing untuk konfirmasi 4 blok sama persis dengan baseline**

```bash
cat database/seeders/LembagaSeeder.php
```

- [ ] **Step 2: Timpa seluruh isi file, sisakan blok SD saja**

```php
<?php
// database/seeders/LembagaSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Seeder;

class LembagaSeeder extends Seeder
{
    public function run(): void
    {
        $yayasan = Yayasan::firstOrFail();

        Lembaga::firstOrCreate(
            ['npsn' => '20223333'],
            [
                'yayasan_id' => $yayasan->id,
                'nss' => '102026001033',
                'kode_lembaga' => 'SDITPTR',
                'nama' => 'SDIT PINTERA',
                'bentuk_pendidikan' => 'SD',
                'status_sekolah' => 'swasta',
                'status_kepemilikan' => 'Yayasan',
                'naungan' => 'kemendikdasmen',
                'sk_pendirian_nomor' => '421.2/SK.033/Disdik/2010',
                'sk_pendirian_tanggal' => '2010-06-01',
                'sk_izin_operasional_nomor' => '421.2/IOP.033/Disdik/2010',
                'sk_izin_operasional_tanggal' => '2010-07-15',
                'akreditasi' => 'A',
                'sk_akreditasi_nomor' => '033/BAN-SM/SK/2022',
                'tanggal_sk_akreditasi' => '2022-11-10',
                'nama_kepala_sekolah' => 'Abdullah, M.Pd.',
                'nama_bendahara_bosp' => 'Hasan, S.E.',
                'alamat_jalan' => 'Jl. Panglima Sudirman No. 88C',
                'rt' => '001',
                'rw' => '003',
                'desa_kelurahan' => 'Sidomulyo',
                'kecamatan' => 'Kraksaan',
                'kabupaten_kota' => 'Kabupaten Probolinggo',
                'provinsi' => 'Jawa Timur',
                'kode_pos' => '67282',
                'lintang' => '-7.7552000',
                'bujur' => '113.4152000',
                'telepon' => '0335-771233',
                'email' => 'sdit@pintera.sch.id',
                'website' => 'https://sdit.pintera.sch.id',
                'nama_bank' => 'Bank BSI',
                'cabang_kcp_unit' => 'KCP Kraksaan',
                'rekening_atas_nama' => 'SDIT PINTERA',
                'nomor_rekening' => '7123456033',
                'mbs' => true,
                'nama_wajib_pajak' => 'SDIT PINTERA',
                'npwp' => '02.345.673.3-012.000',
                'status_aktif' => true,
            ]
        );
    }
}
```

- [ ] **Step 3: Verifikasi**

```bash
php artisan tinker --execute="echo \App\Models\Lembaga::count();"
```
Expected: kalau dijalankan setelah `migrate:fresh --seed` penuh nanti = 1. Untuk sekarang cukup pastikan file syntax-valid: `php -l database/seeders/LembagaSeeder.php`.

- [ ] **Step 4: Commit**

```bash
git add database/seeders/LembagaSeeder.php
git commit -m "refactor(seeder): sisakan 1 lembaga (SD) di LembagaSeeder, hapus KB/TK/SMP"
```

---

## Task 2: `GuruSeeder.php` — Hapus Lookup KB/TK/SMP, Tambah 12 Wali Kelas

**Files:**
- Modify: `database/seeders/GuruSeeder.php`

**Interfaces:**
- Consumes: `Lembaga` (Task 1, NPSN `20223333`).
- Produces: 15 record `Guru` untuk SD (3 `guru_mapel` existing: hendra.gunawan/maya.anggraini/taufik.hidayat + 12 `guru_kelas` baru) — dipakai `UserSeeder.php` (Task 3), `KelasSeeder.php` (Task 4, sebagai wali kelas).

**PENTING**: file ini WAJIB dikerjakan bersamaan alurnya dengan Task 3 (`UserSeeder.php`) — `GuruSeeder::seedGuru()` mencari `User::where('email', ...)` yang HARUS sudah dibuat `UserSeeder`. Tapi karena `DatabaseSeeder.php` memanggil `UserSeeder` SEBELUM `GuruSeeder`, urutan yang benar: Task 3 dulu baru Task 2 — TAPI karena keduanya saling mereferensikan email yang sama, kerjakan KEDUANYA sebelum verifikasi, urutan commit boleh Task 2 dulu (definisi data), Task 3 nyusul (pembuatan user).

- [ ] **Step 1: Timpa seluruh isi file**

```php
<?php
// database/seeders/GuruSeeder.php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Database\Seeder;

class GuruSeeder extends Seeder
{
    public function run(): void
    {
        $sdit = Lembaga::where('npsn', '20223333')->firstOrFail();

        // 12 wali kelas (guru_kelas) -- 1 per rombel, 2 rombel x 6 tingkat
        $this->seedGuru($sdit, [
            [
                'email' => 'sari.wulandari@demo.test', 'name' => 'Sari Wulandari, S.Pd.',
                'nik' => '3273011101880101', 'nuptk' => '5234567890123401', 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Probolinggo', 'tanggal_lahir' => '1988-02-14',
                'no_hp' => '081234568101', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2015-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'agus.setiawan@demo.test', 'name' => 'Agus Setiawan, S.Pd.',
                'nik' => '3273011203870102', 'nuptk' => '5234567890123402', 'nip' => null,
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Kraksaan', 'tanggal_lahir' => '1987-05-20',
                'no_hp' => '081234568102', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2016-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'nita.kurniawati@demo.test', 'name' => 'Nita Kurniawati, S.Pd.',
                'nik' => '3273011304890103', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Probolinggo', 'tanggal_lahir' => '1989-08-11',
                'no_hp' => '081234568103', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'Honorer',
                'golongan_pangkat' => null, 'tmt_tugas' => '2019-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'rudi.hartono@demo.test', 'name' => 'Rudi Hartono, S.Pd.',
                'nik' => '3273011405860104', 'nuptk' => '5234567890123404', 'nip' => '198605042009011010',
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Jember', 'tanggal_lahir' => '1986-05-04',
                'no_hp' => '081234568104', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'PNS',
                'golongan_pangkat' => 'Penata Muda / III-a', 'tmt_tugas' => '2009-01-01', 'tmt_pns' => '2009-01-01',
            ],
            [
                'email' => 'wahyu.astuti@demo.test', 'name' => 'Wahyu Astuti, S.Pd.',
                'nik' => '3273011506910105', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Malang', 'tanggal_lahir' => '1991-06-15',
                'no_hp' => '081234568105', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'PPPK',
                'golongan_pangkat' => 'IX', 'tmt_tugas' => '2020-03-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'dedi.iskandar@demo.test', 'name' => 'Dedi Iskandar, S.Pd.',
                'nik' => '3273011607850106', 'nuptk' => '5234567890123406', 'nip' => null,
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Probolinggo', 'tanggal_lahir' => '1985-07-19',
                'no_hp' => '081234568106', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2014-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'fitriani.rahmawati@demo.test', 'name' => 'Fitriani Rahmawati, S.Pd.',
                'nik' => '3273011708920107', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Bondowoso', 'tanggal_lahir' => '1992-08-23',
                'no_hp' => '081234568107', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'Honorer',
                'golongan_pangkat' => null, 'tmt_tugas' => '2021-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'bambang.wijaya@demo.test', 'name' => 'Bambang Wijaya, S.Pd.',
                'nik' => '3273011809840108', 'nuptk' => '5234567890123408', 'nip' => '198409082008011008',
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Situbondo', 'tanggal_lahir' => '1984-09-08',
                'no_hp' => '081234568108', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'PNS',
                'golongan_pangkat' => 'Penata / III-c', 'tmt_tugas' => '2008-01-01', 'tmt_pns' => '2008-01-01',
            ],
            [
                'email' => 'ratna.puspita@demo.test', 'name' => 'Ratna Puspita, S.Pd.',
                'nik' => '3273011910930109', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Probolinggo', 'tanggal_lahir' => '1993-10-30',
                'no_hp' => '081234568109', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'Honorer',
                'golongan_pangkat' => null, 'tmt_tugas' => '2022-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'yusuf.maulana@demo.test', 'name' => 'Yusuf Maulana, S.Pd.',
                'nik' => '3273012011890110', 'nuptk' => '5234567890123410', 'nip' => null,
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Kraksaan', 'tanggal_lahir' => '1989-11-05',
                'no_hp' => '081234568110', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2017-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'lina.marlina@demo.test', 'name' => 'Lina Marlina, S.Pd.',
                'nik' => '3273012112870111', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Probolinggo', 'tanggal_lahir' => '1987-12-12',
                'no_hp' => '081234568111', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2013-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'irfan.hakim@demo.test', 'name' => 'Irfan Hakim, S.Pd.',
                'nik' => '3273012213900112', 'nuptk' => '5234567890123412', 'nip' => null,
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Lumajang', 'tanggal_lahir' => '1990-01-13',
                'no_hp' => '081234568112', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'PPPK',
                'golongan_pangkat' => 'VIII', 'tmt_tugas' => '2020-08-01', 'tmt_pns' => null,
            ],
        ]);

        // 3 guru mapel spesialis (existing, dipertahankan persis) + 1 diangkat jadi guru_bk
        // (Task 6 mengangkat hendra.gunawan sbg guru_bk pengganti budi.santoso SMP yang dihapus)
        $this->seedGuru($sdit, [
            [
                'email' => 'hendra.gunawan@demo.test', 'name' => 'Hendra Gunawan, S.Pd.',
                'nik' => '3273010108820004', 'nuptk' => '2234567890123456', 'nip' => '198201082008011002',
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1982-01-08',
                'no_hp' => '081234567804', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'PNS',
                'golongan_pangkat' => 'Penata / III-c', 'tmt_tugas' => '2008-01-01', 'tmt_pns' => '2008-01-01',
            ],
            [
                'email' => 'maya.anggraini@demo.test', 'name' => 'Maya Anggraini, S.Pd.',
                'nik' => '3273014412910005', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Sumedang', 'tanggal_lahir' => '1991-12-04',
                'no_hp' => '081234567805', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'PPPK',
                'golongan_pangkat' => 'IX', 'tmt_tugas' => '2021-03-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'taufik.hidayat@demo.test', 'name' => 'Taufik Hidayat, S.Pd.',
                'nik' => '3273011511870006', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1987-11-15',
                'no_hp' => '081234567806', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2016-07-01', 'tmt_pns' => null,
            ],
        ]);
    }

    private function seedGuru(Lembaga $lembaga, array $guruList): void
    {
        foreach ($guruList as $data) {
            $user = User::where('email', $data['email'])->first();

            if (! $user) {
                $this->command?->warn("GuruSeeder: user {$data['email']} tidak ditemukan (UserSeeder mungkin dilewati di env non-local/testing), dilewati.");

                continue;
            }

            Guru::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'lembaga_id' => $lembaga->id,
                    'nik' => $data['nik'],
                    'nuptk' => $data['nuptk'],
                    'nip' => $data['nip'],
                    'nama' => $data['name'],
                    'jenis_kelamin' => $data['jenis_kelamin'],
                    'tempat_lahir' => $data['tempat_lahir'],
                    'tanggal_lahir' => $data['tanggal_lahir'],
                    'agama' => 'Islam',
                    'kewarganegaraan' => 'WNI',
                    'alamat_jalan' => 'Jl. Panglima Sudirman No. 88C',
                    'rt' => '001',
                    'rw' => '003',
                    'desa_kelurahan' => 'Sidomulyo',
                    'kecamatan' => 'Kraksaan',
                    'kabupaten_kota' => 'Kabupaten Probolinggo',
                    'provinsi' => 'Jawa Timur',
                    'kode_pos' => '67282',
                    'no_hp' => $data['no_hp'],
                    'email' => $data['email'],
                    'jenis_ptk' => $data['jenis_ptk'],
                    'status_kepegawaian' => $data['status_kepegawaian'],
                    'golongan_pangkat' => $data['golongan_pangkat'],
                    'tmt_tugas' => $data['tmt_tugas'],
                    'tmt_pns' => $data['tmt_pns'],
                    'status_aktif' => 'aktif',
                ]
            );
        }
    }
}
```

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l database/seeders/GuruSeeder.php
```

- [ ] **Step 3: Commit (bersamaan dengan Task 3, lihat catatan di atas)**

Tunda commit sampai Task 3 (`UserSeeder.php`) juga selesai — keduanya saling bergantung, commit sekali di akhir Task 3.

---

## Task 3: `UserSeeder.php` — Lepas Ketergantungan Admin Yayasan dari KB, Buat 12 User Wali Kelas Baru

**Files:**
- Modify: `database/seeders/UserSeeder.php`

**Interfaces:**
- Consumes: `Lembaga` (Task 1).
- Produces: 15 `User` ber-role `guru` untuk SD (email harus PERSIS cocok dengan `GuruSeeder.php` Task 2) + 3 staf (`kepsek.sd`, `adm.sd`, `keuangan.sd`) + 1 `adm.yayasan@demo.test` (TIDAK lagi bergantung ke lembaga manapun).

- [ ] **Step 1: Timpa seluruh isi file**

```php
<?php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn(static::class.': dilewati, hanya boleh jalan di environment local/testing.');

            return;
        }

        // Admin yayasan scope-nya yayasan, TIDAK bergantung pada lembaga manapun -- sebelumnya
        // salah query ke Lembaga NPSN KB yang sekarang sudah dihapus.
        $yayasan = Yayasan::firstOrFail();

        if (! User::where('email', 'adm.yayasan@demo.test')->exists()) {
            $adminYayasan = User::create([
                'name' => 'Ahmad Fauzi (Admin Yayasan)',
                'email' => 'adm.yayasan@demo.test',
                'password' => 'password',
                'yayasan_id' => $yayasan->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
            $adminYayasan->assignRole('yayasan_super_admin');
        }

        $sdit = Lembaga::where('npsn', '20223333')->firstOrFail();

        // SDIT -- pimpinan + 12 wali kelas + 3 guru mapel spesialis (total 15 guru)
        $this->seedStaf($sdit, [
            ['name' => 'Abdullah, M.Pd.', 'email' => 'kepsek.sd@demo.test', 'role' => 'kepala_sekolah'],
            ['name' => 'Lukman, S.Kom.', 'email' => 'adm.sd@demo.test', 'role' => 'admin_administrasi'],
            ['name' => 'Hasan, S.E.', 'email' => 'keuangan.sd@demo.test', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Sari Wulandari, S.Pd.', 'email' => 'sari.wulandari@demo.test'],
            ['name' => 'Agus Setiawan, S.Pd.', 'email' => 'agus.setiawan@demo.test'],
            ['name' => 'Nita Kurniawati, S.Pd.', 'email' => 'nita.kurniawati@demo.test'],
            ['name' => 'Rudi Hartono, S.Pd.', 'email' => 'rudi.hartono@demo.test'],
            ['name' => 'Wahyu Astuti, S.Pd.', 'email' => 'wahyu.astuti@demo.test'],
            ['name' => 'Dedi Iskandar, S.Pd.', 'email' => 'dedi.iskandar@demo.test'],
            ['name' => 'Fitriani Rahmawati, S.Pd.', 'email' => 'fitriani.rahmawati@demo.test'],
            ['name' => 'Bambang Wijaya, S.Pd.', 'email' => 'bambang.wijaya@demo.test'],
            ['name' => 'Ratna Puspita, S.Pd.', 'email' => 'ratna.puspita@demo.test'],
            ['name' => 'Yusuf Maulana, S.Pd.', 'email' => 'yusuf.maulana@demo.test'],
            ['name' => 'Lina Marlina, S.Pd.', 'email' => 'lina.marlina@demo.test'],
            ['name' => 'Irfan Hakim, S.Pd.', 'email' => 'irfan.hakim@demo.test'],
            ['name' => 'Hendra Gunawan, S.Pd.', 'email' => 'hendra.gunawan@demo.test'],
            ['name' => 'Maya Anggraini, S.Pd.', 'email' => 'maya.anggraini@demo.test'],
            ['name' => 'Taufik Hidayat, S.Pd.', 'email' => 'taufik.hidayat@demo.test'],
        ]);
    }

    private function seedStaf(Lembaga $lembaga, array $pimpinan, array $guruList): void
    {
        foreach ($pimpinan as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'lembaga_id' => $lembaga->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->update(['name' => $data['name'], 'lembaga_id' => $lembaga->id]);
            $user->assignRole($data['role']);
        }

        foreach ($guruList as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'lembaga_id' => $lembaga->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->update(['name' => $data['name'], 'lembaga_id' => $lembaga->id]);
            $user->assignRole('guru');
        }
    }
}
```

- [ ] **Step 2: Verifikasi syntax kedua file (Task 2 + Task 3)**

```bash
php -l database/seeders/GuruSeeder.php
php -l database/seeders/UserSeeder.php
```

- [ ] **Step 3: Commit gabungan Task 2 + 3**

```bash
git add database/seeders/GuruSeeder.php database/seeders/UserSeeder.php
git commit -m "refactor(seeder): retarget GuruSeeder+UserSeeder ke SD, tambah 12 wali kelas (total 15 guru)"
```

---

## Task 4: `KelasSeeder.php` — 12 Kelas (2 Rombel/Tingkat)

**Files:**
- Modify: `database/seeders/KelasSeeder.php`

**Interfaces:**
- Consumes: `Guru` (Task 2, 12 wali kelas + 3 guru mapel = 15 total, dipakai `$gurus[$idx % $gurus->count()]` untuk assign wali kelas).
- Produces: 12 `Kelas` untuk SD ("Kelas 1-A" s.d. "Kelas 6-B") — dipakai `SiswaSeeder.php` (Task 5).

File ini GENERIK (`Lembaga::all()`, tidak hardcode NPSN) — HANYA array `$kelasConfigs` untuk kasus `'SD'` yang diubah.

- [ ] **Step 1: Baca ulang file existing, konfirmasi struktur sama dengan baseline**

```bash
cat database/seeders/KelasSeeder.php
```

- [ ] **Step 2: Ubah HANYA array `'SD' =>` di dalam `match ($lembaga->bentuk_pendidikan)`**

Ganti:
```php
                'SD' => [
                    ['nama' => 'Kelas 1-A', 'tingkat' => '1'],
                    ['nama' => 'Kelas 2-A', 'tingkat' => '2'],
                    ['nama' => 'Kelas 3-A', 'tingkat' => '3'],
                    ['nama' => 'Kelas 4-A', 'tingkat' => '4'],
                    ['nama' => 'Kelas 5-A', 'tingkat' => '5'],
                    ['nama' => 'Kelas 6-A', 'tingkat' => '6'],
                ],
```

Menjadi:
```php
                'SD' => [
                    ['nama' => 'Kelas 1-A', 'tingkat' => '1'],
                    ['nama' => 'Kelas 1-B', 'tingkat' => '1'],
                    ['nama' => 'Kelas 2-A', 'tingkat' => '2'],
                    ['nama' => 'Kelas 2-B', 'tingkat' => '2'],
                    ['nama' => 'Kelas 3-A', 'tingkat' => '3'],
                    ['nama' => 'Kelas 3-B', 'tingkat' => '3'],
                    ['nama' => 'Kelas 4-A', 'tingkat' => '4'],
                    ['nama' => 'Kelas 4-B', 'tingkat' => '4'],
                    ['nama' => 'Kelas 5-A', 'tingkat' => '5'],
                    ['nama' => 'Kelas 5-B', 'tingkat' => '5'],
                    ['nama' => 'Kelas 6-A', 'tingkat' => '6'],
                    ['nama' => 'Kelas 6-B', 'tingkat' => '6'],
                ],
```

Sisa file (namespace, `use`, blok `KB`/`TK`/`default`, logic `foreach`) TIDAK berubah — blok `default` (dipakai SMP) akan jadi dead-code tak terpakai setelah lembaga lain dihapus, TAPI TIDAK PERLU dihapus (tidak crash, tidak ada ruginya dibiarkan, lebih aman daripada menyentuh logic `match` yang berisiko salah ketik).

- [ ] **Step 3: Verifikasi syntax**

```bash
php -l database/seeders/KelasSeeder.php
```

- [ ] **Step 4: Commit**

```bash
git add database/seeders/KelasSeeder.php
git commit -m "refactor(seeder): KelasSeeder SD jadi 12 kelas (2 rombel/tingkat)"
```

---

## Task 5: `SiswaSeeder.php` — 28 Siswa/Kelas (~336 Total), Bersihkan Email Map

**Files:**
- Modify: `database/seeders/SiswaSeeder.php`

**Interfaces:**
- Consumes: `Kelas` (Task 4, 12 kelas).
- Produces: ~336 `Siswa` untuk SD — dipakai SEMUA task berikutnya yang butuh data siswa (OrangTuaKaryawan, Pendampingan, KeuanganDemo, Nilai, Presensi).

- [ ] **Step 1: Timpa seluruh isi file**

```php
<?php
// database/seeders/SiswaSeeder.php

namespace Database\Seeders;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;

class SiswaSeeder extends Seeder
{
    private const NAMA_DEPAN = [
        'Ahmad', 'Muhammad', 'Aisyah', 'Fatimah', 'Rizky', 'Putri', 'Bagus', 'Siti', 'Rian', 'Nabila',
        'Fajar', 'Salsabila', 'Dimas', 'Zahra', 'Reza', 'Kirana', 'Fauzan', 'Alya', 'Yusuf', 'Naila',
        'Arif', 'Anisa', 'Bayu', 'Indah', 'Galih', 'Rania', 'Hafiz', 'Keisya', 'Iqbal', 'Maryam',
    ];

    private const NAMA_BELAKANG = [
        'Pratama', 'Santoso', 'Wijaya', 'Hidayat', 'Kurniawan', 'Saputra', 'Anggraini', 'Ramadhan',
        'Lestari', 'Nugroho', 'Firmansyah', 'Utami', 'Setiawan', 'Permata', 'Wibowo', 'Handayani',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn(static::class.': dilewati, hanya boleh jalan di environment local/testing.');

            return;
        }

        foreach (Lembaga::all() as $lembaga) {
            $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

            if (! $aktif) {
                continue;
            }

            $this->seedGenericStudents($lembaga, $aktif);
            $this->seedSiswaAccount($lembaga);
        }
    }

    private function seedGenericStudents(Lembaga $lembaga, TahunAjaran $aktif): void
    {
        $kelasList = Kelas::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->get();
        $prefix = substr($lembaga->npsn, -4); // e.g. 3333

        $counter = 1;
        foreach ($kelasList as $kelas) {
            for ($i = 1; $i <= 28; $i++) {
                $numStr = str_pad((string) $counter, 3, '0', STR_PAD_LEFT);
                $nis = $prefix . $numStr;
                $nisn = '00' . $prefix . $numStr;

                $depan = self::NAMA_DEPAN[$counter % count(self::NAMA_DEPAN)];
                $belakang = self::NAMA_BELAKANG[$counter % count(self::NAMA_BELAKANG)];

                Siswa::firstOrCreate(
                    ['lembaga_id' => $lembaga->id, 'nis' => $nis],
                    [
                        'kelas_id' => $kelas->id,
                        'nisn' => $nisn,
                        'nama_lengkap' => "{$depan} {$belakang}",
                        'jenis_kelamin' => ($i % 2 === 1) ? 'L' : 'P',
                        'tempat_lahir' => 'Kraksaan',
                        'tanggal_lahir' => '2016-01-10',
                        'agama' => 'Islam',
                        'sumber_data' => 'manual',
                        'status' => 'aktif',
                    ]
                );

                $counter++;
            }
        }
    }

    private function seedSiswaAccount(Lembaga $lembaga): void
    {
        $firstSiswa = Siswa::where('lembaga_id', $lembaga->id)->first();
        if (! $firstSiswa) {
            return;
        }

        $emailMap = [
            '20223333' => 'siswa.sd@demo.test',
        ];

        $email = $emailMap[$lembaga->npsn] ?? "siswa.{$lembaga->id}@demo.test";

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $firstSiswa->nama_lengkap,
                'password' => 'password',
                'lembaga_id' => $lembaga->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $user->assignRole('siswa');

        if ($firstSiswa->user_id !== $user->id) {
            $firstSiswa->update(['user_id' => $user->id]);
        }
    }
}
```

Catatan: nama siswa memakai kombinasi `NAMA_DEPAN`×`NAMA_BELAKANG` (30×16 = 480 kombinasi unik, cukup untuk 336 siswa tanpa banyak pengulangan persis) — bukan nama placeholder "Siswa {kelas} No.{i}" seperti sebelumnya, supaya "seperti data real" tanpa perlu 336 baris literal.

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l database/seeders/SiswaSeeder.php
```

- [ ] **Step 3: Commit**

```bash
git add database/seeders/SiswaSeeder.php
git commit -m "refactor(seeder): SiswaSeeder 28 siswa/kelas (~336 total SD), nama realistis bukan placeholder"
```

---

## Task 6: `OrangTuaKaryawanSeeder.php` — Hapus Fungsi Lintas-Lembaga, Replikasi Pola Kaya SMP ke SD

**Files:**
- Modify: `database/seeders/OrangTuaKaryawanSeeder.php`

**Interfaces:**
- Consumes: `Guru` (Task 2, `hendra.gunawan@demo.test` — diangkat jadi `guru_bk`), `Siswa` (Task 5), `JenisKaryawanMaster::where('nama', 'Psikolog')`.
- Produces: 4 `OrangTua` dengan akun login (siswa idx 0-3) + 1 demo-login utama `ortu.sd@demo.test` (siswa idx 4) — dipakai `PendampinganSeeder.php` (Task 8), `KeuanganDemoSeeder.php` (Task 13).

**PENTING**: fungsi `seedGuruBk()` sebelumnya mengangkat `budi.santoso@demo.test` (guru SMP, akan dihapus) jadi `guru_bk` — HARUS diganti jadi `hendra.gunawan@demo.test` (guru SD). Fungsi `seedOrangTuaLintasLembaga($sdit, $smpit)` dan `seedOrangTuaDemoKb`/`seedOrangTuaDemoTk` DIHAPUS TOTAL (KB/TK/SMP tidak ada lagi).

- [ ] **Step 1: Timpa seluruh isi file**

```php
<?php
// database/seeders/OrangTuaKaryawanSeeder.php

namespace Database\Seeders;

use App\Models\Guru;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OrangTuaKaryawanSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn(static::class.': dilewati, hanya boleh jalan di environment local/testing.');

            return;
        }

        $sdit    = Lembaga::where('npsn', '20223333')->firstOrFail();
        $yayasan = Yayasan::firstOrFail();

        // ── 1. Upgrade Guru BK di SDIT ───────────────────────────────────────
        $this->seedGuruBk($sdit);

        // ── 2. Karyawan Pool Yayasan (Psikolog) ─────────────────────────────
        $this->seedKaryawanPool($yayasan);

        // ── 3. Orang Tua siswa SDIT ───────────────────────────────────────────
        $this->seedOrangTua($sdit);
    }

    // ── Guru BK ─────────────────────────────────────────────────────────────

    private function seedGuruBk(Lembaga $sdit): void
    {
        // Jadikan Hendra Gunawan sebagai Guru BK SDIT
        $user = User::where('email', 'hendra.gunawan@demo.test')->first();
        if (! $user) {
            return;
        }

        Guru::where('user_id', $user->id)->update([
            'jenis_ptk'            => 'guru_bk',
            'kapasitas_kasus_aktif' => null, // tidak dibatasi
        ]);
    }

    // ── Karyawan Pool ────────────────────────────────────────────────────────

    private function seedKaryawanPool(Yayasan $yayasan): void
    {
        $jenisKaryawan = JenisKaryawanMaster::where('nama', 'Psikolog')->firstOrFail();

        $nik = '0000019901900099';

        if (User::where('username', $nik)->exists()) {
            return;
        }

        $user = User::create([
            'name'                 => 'Dr. Rahma Aulia, M.Psi.',
            'email'                => 'psikolog.pool@demo.test',
            'username'             => $nik,
            'password'             => Hash::make($nik),
            'lembaga_id'           => null, // pool = lintas lembaga
            'email_verified_at'    => now(),
            'is_active'            => true,
            'must_change_password' => true,
        ]);
        $user->assignRole('karyawan_pool');

        Karyawan::create([
            'user_id'              => $user->id,
            'yayasan_id'           => $yayasan->id,
            'lembaga_id'           => null,
            'jenis_karyawan_id'    => $jenisKaryawan->id,
            'nama'                 => 'Dr. Rahma Aulia, M.Psi.',
            'nik'                  => $nik,
            'no_hp'                => '081298765099',
            'status_aktif'         => 'aktif',
            'kapasitas_kasus_aktif' => null,
        ]);
    }

    // ── Orang Tua SDIT ─────────────────────────────────────────────────────

    private function seedOrangTua(Lembaga $sdit): void
    {
        $siswas = Siswa::where('lembaga_id', $sdit->id)->take(6)->get();

        if ($siswas->isEmpty()) {
            return;
        }

        $data = [
            [
                'nik'          => '0000019901850051',
                'nama_lengkap' => 'Drs. Ahmad Pratama',
                'no_hp'        => '081234560051',
                'email'        => null,
                'hubungan'     => 'ayah',
                'siswa_idx'    => 0,
            ],
            [
                'nik'          => '0000019902860052',
                'nama_lengkap' => 'Ibu Sari Dewi',
                'no_hp'        => '081234560052',
                'email'        => null,
                'hubungan'     => 'ibu',
                'siswa_idx'    => 1,
            ],
            [
                'nik'          => '0000019903870053',
                'nama_lengkap' => 'Bp. Rizky Hidayat',
                'no_hp'        => '081234560053',
                'email'        => null,
                'hubungan'     => 'ayah',
                'siswa_idx'    => 2,
            ],
            [
                'nik'          => '0000019904880054',
                'nama_lengkap' => 'Ibu Nurhayati',
                'no_hp'        => '081234560054',
                'email'        => null,
                'hubungan'     => 'ibu',
                'siswa_idx'    => 3,
            ],
        ];

        foreach ($data as $item) {
            $siswa = $siswas[$item['siswa_idx']] ?? null;

            if (User::where('username', $item['nik'])->exists()) {
                $existing = OrangTua::where('nik', $item['nik'])->first();
                if ($existing && $siswa) {
                    $this->tautkanOrangTuaSiswa($existing, $siswa, $item['hubungan'], true);
                }
                continue;
            }

            if (! $siswa) {
                continue;
            }

            $user = User::create([
                'name'                 => $item['nama_lengkap'],
                'email'                => $item['email'],
                'username'             => $item['nik'],
                'password'             => Hash::make($item['nik']),
                'lembaga_id'           => null,
                'email_verified_at'    => now(),
                'is_active'            => true,
                'must_change_password' => true,
            ]);
            $user->assignRole('orang_tua');

            $orangTua = OrangTua::create([
                'user_id'      => $user->id,
                'nama_lengkap' => $item['nama_lengkap'],
                'nik'          => $item['nik'],
                'no_hp'        => $item['no_hp'],
                'email'        => $item['email'],
            ]);

            $this->tautkanOrangTuaSiswa($orangTua, $siswa, $item['hubungan'], true);
        }

        // 1 Akun Orang Tua Demo untuk Login (password: 'password')
        $this->seedOrangTuaDemoLogin($sdit);
    }

    private function seedOrangTuaDemoLogin(Lembaga $sdit): void
    {
        $nik = '0000019901850001';

        if (User::where('username', $nik)->exists()) {
            return;
        }

        $siswaTarget = Siswa::where('lembaga_id', $sdit->id)->skip(4)->first();
        if (! $siswaTarget) {
            return;
        }

        $user = User::create([
            'name'                 => 'Ibu Eliana (Demo Login)',
            'email'                => 'ortu.sd@demo.test',
            'username'             => $nik,
            'password'             => Hash::make('password'),
            'lembaga_id'           => null,
            'email_verified_at'    => now(),
            'is_active'            => true,
            'must_change_password' => false, // demo account, tidak perlu ganti password
        ]);
        $user->assignRole('orang_tua');

        $orangTua = OrangTua::create([
            'user_id'      => $user->id,
            'nama_lengkap' => 'Ibu Eliana (Demo Login)',
            'nik'          => $nik,
            'no_hp'        => '081234560001',
            'email'        => 'ortu.sd@demo.test',
        ]);

        $this->tautkanOrangTuaSiswa($orangTua, $siswaTarget, 'ibu', true);
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    private function tautkanOrangTuaSiswa(OrangTua $orangTua, Siswa $siswa, string $hubungan, bool $isKontakUtama): void
    {
        $alreadyLinked = DB::table('siswa_orang_tua')
            ->where('siswa_id', $siswa->id)
            ->where('orang_tua_id', $orangTua->id)
            ->exists();

        if ($alreadyLinked) {
            return;
        }

        if ($isKontakUtama) {
            DB::table('siswa_orang_tua')
                ->where('siswa_id', $siswa->id)
                ->update(['is_kontak_utama' => false]);
        }

        DB::table('siswa_orang_tua')->insert([
            'siswa_id'        => $siswa->id,
            'orang_tua_id'    => $orangTua->id,
            'hubungan'        => $hubungan,
            'is_kontak_utama' => $isKontakUtama,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
}
```

Catatan: email demo-login diganti `ortu@demo.test` → `ortu.sd@demo.test` (Task 13 `KeuanganDemoSeeder.php` HARUS diperbarui mengikuti ini).

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l database/seeders/OrangTuaKaryawanSeeder.php
```

- [ ] **Step 3: Commit**

```bash
git add database/seeders/OrangTuaKaryawanSeeder.php
git commit -m "refactor(seeder): OrangTuaKaryawanSeeder retarget SD, hapus fungsi lintas-lembaga & demo KB/TK"
```

---

## Task 7: `GelombangJalurSeeder.php` — Retarget SMP → SD

**Files:**
- Modify: `database/seeders/GelombangJalurSeeder.php`

**Interfaces:**
- Consumes: `Lembaga` (Task 1), `GelombangPpdb`/`JalurPpdb` (TIDAK disentuh, generik, sudah otomatis buat data untuk SD).

- [ ] **Step 1: Timpa seluruh isi file**

```php
<?php
// database/seeders/GelombangJalurSeeder.php

namespace Database\Seeders;

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class GelombangJalurSeeder extends Seeder
{
    public function run(): void
    {
        $sdit = Lembaga::where('npsn', '20223333')->firstOrFail();
        $sditAktif = TahunAjaran::where('lembaga_id', $sdit->id)->where('status_aktif', true)->firstOrFail();

        $gelombang1 = GelombangPpdb::where('lembaga_id', $sdit->id)
            ->where('tahun_ajaran_id', $sditAktif->id)
            ->where('nama', 'Gelombang 1')
            ->firstOrFail();

        $jalurDiizinkan = JalurPpdb::where('lembaga_id', $sdit->id)
            ->where('tahun_ajaran_id', $sditAktif->id)
            ->whereIn('nama', ['Reguler', 'Prestasi'])
            ->pluck('id');

        $gelombang1->jalur()->sync($jalurDiizinkan);
    }
}
```

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l database/seeders/GelombangJalurSeeder.php
```

- [ ] **Step 3: Commit**

```bash
git add database/seeders/GelombangJalurSeeder.php
git commit -m "refactor(seeder): GelombangJalurSeeder retarget dari SMP ke SD"
```

---

## Task 8: `PendampinganSeeder.php` — Pindahkan 7 Skenario dari SMP ke SD, Hapus Fungsi Lintas-Jenjang

**Files:**
- Modify: `database/seeders/PendampinganSeeder.php`

**Interfaces:**
- Consumes: `Lembaga` (Task 1), `Guru` (Task 2, `guru_bk` = hendra.gunawan hasil Task 6), `Karyawan` (Task 6, psikolog pool), `Siswa` (Task 5), `User` (`adm.sd@demo.test` dari Task 3).
- Produces: 7 `Kasus` untuk SD mencakup semua status siklus (diajukan→menunggu_consent→ditugaskan→berjalan→eskalasi→selesai + 1 ringan) — TIDAK ada lagi kasus lintas-jenjang KB/TK.

**Keputusan desain**: skenario ringan SD (`buatKasusRinganSd`, sudah ada isinya di baseline — tugas batch harian) DIJADIKAN skenario ke-7 (menggantikan `buatKasusRinganSmp`), bukan didobel. Fungsi `buatSkenarioRinganLintasJenjang` beserta `buatKasusRinganKb`/`buatKasusRinganTk` DIHAPUS TOTAL.

- [ ] **Step 1: Baca ulang file existing untuk konfirmasi 7 fungsi skenario sama persis dengan baseline (lihat kutipan lengkap di spec/riset — 622 baris)**

```bash
wc -l database/seeders/PendampinganSeeder.php
```
Expected: 622 baris (baseline). Kalau beda signifikan, STOP dan laporkan.

- [ ] **Step 2: Ganti bagian `run()` — retarget ke SD, hapus panggilan skenario 7 SMP + lintas-jenjang**

Ganti blok:
```php
    public function run(): void
    {
        $smpit = Lembaga::where('npsn', '20223344')->firstOrFail();

        $gurubk     = $this->resolveGuruBk($smpit);
        $psikolog   = $this->resolveKaryawanPool();
        $adminUser  = User::where('email', 'adm.smp@demo.test')->first();
        $siswas     = Siswa::where('lembaga_id', $smpit->id)->take(10)->get();

        if ($siswas->count() < 5) {
            $this->command->warn('PendampinganSeeder: kurang dari 5 siswa SMPIT, skip.');
            return;
        }

        // ── Skenario 1: Diajukan oleh guru ───────────────────────────────────
        $this->buatKasusDiajukan($siswas[0], $smpit, $gurubk);

        // ── Skenario 2: Menunggu Consent ─────────────────────────────────────
        $this->buatKasusMenungguConsent($siswas[1], $smpit, $gurubk, $psikolog);

        // ── Skenario 3: Ditugaskan (consent disetujui, belum ada sesi/tugas) ─
        $this->buatKasusDitugaskan($siswas[2], $smpit, $gurubk);

        // ── Skenario 4: Berjalan (ada sesi & tugas) ──────────────────────────
        $this->buatKasusBerjalan($siswas[3], $smpit, $gurubk, $adminUser);

        // ── Skenario 5: Eskalasi ──────────────────────────────────────────────
        $this->buatKasusEskalasi($siswas[4], $smpit, $psikolog, $adminUser);

        // ── Skenario 6: Selesai ───────────────────────────────────────────────
        if ($siswas->count() >= 6) {
            $this->buatKasusSelesai($siswas[5], $smpit, $gurubk, $adminUser);
        }

        // ── Skenario 7: Kasus ringan SMP (percaya diri presentasi, selesai) ──
        if ($siswas->count() >= 7 && $gurubk) {
            $this->buatKasusRinganSmp($siswas[6], $smpit, $gurubk);
        }

        // ── Skenario ringan lintas jenjang: KB, TK, SD ───────────────────────
        // Menunjukkan fitur ini juga cocok untuk kasus sehari-hari yang ringan,
        // bukan hanya kasus berat — bukan cuma jenjang SMP.
        $this->buatSkenarioRinganLintasJenjang($psikolog);
    }

    private function buatSkenarioRinganLintasJenjang(?Karyawan $psikolog): void
    {
        $kbit = Lembaga::where('npsn', '20223311')->first();
        $tkit = Lembaga::where('npsn', '20223322')->first();
        $sdit = Lembaga::where('npsn', '20223333')->first();

        if ($kbit) {
            $siswaKb = Siswa::where('lembaga_id', $kbit->id)->first();
            $guruKb  = Guru::where('lembaga_id', $kbit->id)->first();
            if ($siswaKb && $guruKb) {
                $this->buatKasusRinganKb($siswaKb, $kbit, $guruKb);
            }
        }

        if ($tkit) {
            $siswaTk = Siswa::where('lembaga_id', $tkit->id)->first();
            if ($siswaTk) {
                $orangTuaTk = $this->resolveOrangTuaKontakUtama($siswaTk);
                if ($orangTuaTk) {
                    $this->buatKasusRinganTk($siswaTk, $tkit, $orangTuaTk, $psikolog);
                }
            }
        }

        if ($sdit) {
            $siswaSd = Siswa::where('lembaga_id', $sdit->id)->first();
            $guruSd  = Guru::where('lembaga_id', $sdit->id)->first();
            if ($siswaSd && $guruSd) {
                $this->buatKasusRinganSd($siswaSd, $sdit, $guruSd);
            }
        }
    }
```

Menjadi:
```php
    public function run(): void
    {
        $sdit = Lembaga::where('npsn', '20223333')->firstOrFail();

        $gurubk     = $this->resolveGuruBk($sdit);
        $psikolog   = $this->resolveKaryawanPool();
        $adminUser  = User::where('email', 'adm.sd@demo.test')->first();
        $siswas     = Siswa::where('lembaga_id', $sdit->id)->take(10)->get();
        $guruUmum   = Guru::where('lembaga_id', $sdit->id)->where('jenis_ptk', 'guru_kelas')->first();

        if ($siswas->count() < 5) {
            $this->command->warn('PendampinganSeeder: kurang dari 5 siswa SDIT, skip.');
            return;
        }

        // ── Skenario 1: Diajukan oleh guru ───────────────────────────────────
        $this->buatKasusDiajukan($siswas[0], $sdit, $gurubk);

        // ── Skenario 2: Menunggu Consent ─────────────────────────────────────
        $this->buatKasusMenungguConsent($siswas[1], $sdit, $gurubk, $psikolog);

        // ── Skenario 3: Ditugaskan (consent disetujui, belum ada sesi/tugas) ─
        $this->buatKasusDitugaskan($siswas[2], $sdit, $gurubk);

        // ── Skenario 4: Berjalan (ada sesi & tugas) ──────────────────────────
        $this->buatKasusBerjalan($siswas[3], $sdit, $gurubk, $adminUser);

        // ── Skenario 5: Eskalasi ──────────────────────────────────────────────
        $this->buatKasusEskalasi($siswas[4], $sdit, $psikolog, $adminUser);

        // ── Skenario 6: Selesai ───────────────────────────────────────────────
        if ($siswas->count() >= 6) {
            $this->buatKasusSelesai($siswas[5], $sdit, $gurubk, $adminUser);
        }

        // ── Skenario 7: Kasus ringan (tugas batch harian, status berjalan) ───
        if ($siswas->count() >= 7 && $guruUmum) {
            $this->buatKasusRinganSd($siswas[6], $sdit, $guruUmum);
        }
    }
```

- [ ] **Step 3: Ganti seluruh isi 6 fungsi skenario 1-6 (`buatKasusDiajukan` s.d. `buatKasusSelesai`) — ganti parameter `Lembaga $smpit` → `Lembaga $sdit` di signature DAN isi (`'lembaga_id' => $smpit->id` → `'lembaga_id' => $sdit->id`)**

Untuk SETIAP dari 6 fungsi (`buatKasusDiajukan`, `buatKasusMenungguConsent`, `buatKasusDitugaskan`, `buatKasusBerjalan`, `buatKasusEskalasi`, `buatKasusSelesai`): ganti nama parameter `Lembaga $smpit` menjadi `Lembaga $sdit` di signature method, dan `$smpit->id`/`$smpit` di badan method menjadi `$sdit->id`/`$sdit`. **Deskripsi kasus, kategori masalah, tingkat urgensi, isi consent/sesi/tugas/evaluasi TIDAK BERUBAH SAMA SEKALI** — konten psikologis/naratif itu generik untuk anak sekolah manapun, tidak spesifik jenjang SMP.

- [ ] **Step 4: Hapus fungsi `buatKasusRinganSmp` (skenario 7 lama, digantikan `buatKasusRinganSd` di Step 2), `buatKasusRinganKb`, `buatKasusRinganTk`**

Hapus total 3 fungsi ini dari file (sekitar baris 450-561 di baseline).

- [ ] **Step 5: Pastikan `buatKasusRinganSd` (fungsi terakhir, sudah ada isinya) TIDAK diubah sama sekali** — dia sudah generik menerima parameter `Siswa $siswa, Lembaga $sdit, Guru $guruSd` yang PERSIS cocok dengan signature yang dipanggil di Step 2.

- [ ] **Step 6: Verifikasi syntax**

```bash
php -l database/seeders/PendampinganSeeder.php
```

- [ ] **Step 7: Commit**

```bash
git add database/seeders/PendampinganSeeder.php
git commit -m "refactor(seeder): PendampinganSeeder pindahkan 7 skenario dari SMP ke SD, hapus fungsi lintas-jenjang"
```

---

## Task 9: Verifikasi Checkpoint — Zero-Crash

**Files:**
- Tidak ada file baru — task ini murni verifikasi gate.

Task 1-8 menutup SEMUA 5 file yang berpotensi CRASH TOTAL (`GuruSeeder`, `UserSeeder`, `OrangTuaKaryawanSeeder`, `GelombangJalurSeeder`, `PendampinganSeeder`) plus 2 file pendukung (`KelasSeeder`, `SiswaSeeder`). Task ini WAJIB membuktikan `migrate:fresh --seed` sudah tidak crash SEBELUM lanjut ke file silent-skip.

- [ ] **Step 1: Jalankan migrate:fresh --seed, tangkap SELURUH output**

```bash
php artisan migrate:fresh --seed 2>&1 | tail -100
```

**Expected**: TIDAK ADA baris `Exception`/`Error`/`firstOrFail()` yang gagal. Warning `command?->warn(...)` (misal dari `KehadiranSdmDemoSeeder`/`SarprasPengadaanDemoSeeder` yang BELUM diperbaiki Task 10-12) BOLEH muncul — itu bukan crash, cuma silent-skip yang memang belum ditangani sampai task berikutnya.

- [ ] **Step 2: Kalau ADA exception, STOP — identifikasi seeder mana yang gagal dari stack trace, perbaiki sebelum lanjut Task 10.**

- [ ] **Step 3: Verifikasi angka dasar**

```bash
php artisan tinker --execute="echo 'Lembaga: '.\App\Models\Lembaga::count().PHP_EOL; echo 'Kelas: '.\App\Models\Kelas::count().PHP_EOL; echo 'Siswa: '.\App\Models\Siswa::count().PHP_EOL; echo 'Guru: '.\App\Models\Guru::count().PHP_EOL;"
```
Expected: Lembaga=1, Kelas=12 (dikali jumlah TahunAjaran aktif yang di-seed — cek `TahunAjaranSeeder.php` untuk konfirmasi berapa tahun ajaran dibuat per lembaga, biasanya 1-2), Siswa≈336 (dikali jumlah tahun ajaran juga kalau `KelasSeeder`/`SiswaSeeder` jalan per tahun ajaran), Guru=15.

Tidak ada commit di task ini — murni verifikasi gate. Kalau ada temuan tidak sesuai, STOP dan perbaiki sebelum lanjut Task 10.

---

## Task 10: `EssentialUserSeeder.php` — Retarget KB → SD, Perbaiki Label Email

**Files:**
- Modify: `database/seeders/EssentialUserSeeder.php`

**Interfaces:**
- Consumes: `Lembaga` (Task 1).

- [ ] **Step 1: Timpa seluruh isi file**

```php
<?php
// database/seeders/EssentialUserSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Seeder;

class EssentialUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn(static::class.': dilewati, hanya boleh jalan di environment local/testing.');

            return;
        }

        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@demo.test'],
            [
                'name' => 'Admin Sistem',
                'password' => 'password',
                'yayasan_id' => Yayasan::first()?->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $superAdmin->assignRole('yayasan_super_admin');

        $lembaga = Lembaga::where('npsn', '20223333')->first() ?? Lembaga::first();

        if (! $lembaga) {
            $this->command?->warn('Belum ada Lembaga -- akun kepala_sekolah/admin_administrasi/admin_keuangan/guru dilewati.');

            return;
        }

        $akunLembagaScoped = [
            'kepsek.sd@demo.test' => ['name' => 'Abdullah, M.Pd.', 'role' => 'kepala_sekolah'],
            'adm.sd@demo.test' => ['name' => 'Lukman, S.Kom.', 'role' => 'admin_administrasi'],
            'keuangan.sd@demo.test' => ['name' => 'Hasan, S.E.', 'role' => 'admin_keuangan'],
            'kurikulum.sd@demo.test' => ['name' => 'Kurikulum (Contoh)', 'role' => 'admin_akademik'],
            'guru.sd1@demo.test' => ['name' => 'Sari Wulandari, S.Pd.', 'role' => 'guru'],
            'sarpras.sd@demo.test' => ['name' => 'Sarpras (Contoh)', 'role' => 'admin_sarpras'],
        ];

        foreach ($akunLembagaScoped as $email => $data) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'lembaga_id' => $lembaga->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->update(['lembaga_id' => $lembaga->id]);
            $user->assignRole($data['role']);
        }
    }
}
```

Catatan: `kepsek.sd@demo.test`/`adm.sd@demo.test`/`keuangan.sd@demo.test` SUDAH dibuat `UserSeeder.php` (Task 3) — `firstOrCreate` di sini idempotent, tidak duplikat, cuma memastikan role ter-assign juga kalau `EssentialUserSeeder` dijalankan sendirian di context tertentu. `kurikulum.sd@demo.test`, `guru.sd1@demo.test` (nama BARU, sengaja beda dari 15 guru utama Task 2 supaya tidak bentrok `firstOrCreate` key), `sarpras.sd@demo.test` adalah akun BARU murni dari file ini.

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l database/seeders/EssentialUserSeeder.php
```

- [ ] **Step 3: Commit**

```bash
git add database/seeders/EssentialUserSeeder.php
git commit -m "refactor(seeder): EssentialUserSeeder retarget KB ke SD, perbaiki label email"
```

---

## Task 11: `KehadiranSdmDemoSeeder.php` — Retarget SMP → SD

**Files:**
- Modify: `database/seeders/KehadiranSdmDemoSeeder.php`

**Interfaces:**
- Consumes: `Lembaga` (Task 1), `Guru` (Task 2 — `hendra.gunawan`/`maya.anggraini`/`taufik.hidayat` menggantikan `budi.santoso`/`siti.rahmawati`/`andi.wijaya`), `User` (`adm.sd@demo.test`/`kepsek.sd@demo.test` dari Task 3).

- [ ] **Step 1: Baca ulang file existing, konfirmasi struktur sama dengan baseline (198 baris)**

```bash
wc -l database/seeders/KehadiranSdmDemoSeeder.php
```

- [ ] **Step 2: Ganti HANYA baris-baris berikut (isi logic lain — QR generation, attendance policy, shift, izin/cuti — TIDAK berubah)**

Baris ~50:
```php
        $lembaga = Lembaga::where('npsn', '20223344')->first() ?? Lembaga::first();
```
Menjadi:
```php
        $lembaga = Lembaga::where('npsn', '20223333')->first() ?? Lembaga::first();
```

Baris ~57-59:
```php
        $guruAbsensi = Guru::where('email', 'budi.santoso@demo.test')->first();
        $guruPolicy = Guru::where('email', 'siti.rahmawati@demo.test')->first();
        $guruShift = Guru::where('email', 'andi.wijaya@demo.test')->first();
```
Menjadi:
```php
        $guruAbsensi = Guru::where('email', 'hendra.gunawan@demo.test')->first();
        $guruPolicy = Guru::where('email', 'maya.anggraini@demo.test')->first();
        $guruShift = Guru::where('email', 'taufik.hidayat@demo.test')->first();
```

Baris ~62:
```php
        $this->command?->warn(static::class.': data Guru demo SMPIT belum lengkap (jalankan GuruSeeder dulu), dilewati.');
```
Menjadi:
```php
        $this->command?->warn(static::class.': data Guru demo SDIT belum lengkap (jalankan GuruSeeder dulu), dilewati.');
```

Baris ~68:
```php
        $adminSdm = User::where('email', 'adm.smp@demo.test')->first();
```
Menjadi:
```php
        $adminSdm = User::where('email', 'adm.sd@demo.test')->first();
```

Baris ~73:
```php
        $kepsek = User::where('email', 'kepsek.smp@demo.test')->first();
```
Menjadi:
```php
        $kepsek = User::where('email', 'kepsek.sd@demo.test')->first();
```

Sisa isi file (konfigurasi metode absensi, titik absen "Gerbang Utama", kalender kerja, attendance policy `guru_kelas`, shift, riwayat kehadiran 3 hari, pengajuan izin/cuti) **TIDAK BERUBAH SAMA SEKALI** — semuanya generik/tanggal-dinamis, tidak menyebut identitas SMP.

- [ ] **Step 3: Verifikasi syntax**

```bash
php -l database/seeders/KehadiranSdmDemoSeeder.php
```

- [ ] **Step 4: Commit**

```bash
git add database/seeders/KehadiranSdmDemoSeeder.php
git commit -m "refactor(seeder): KehadiranSdmDemoSeeder retarget dari SMP ke SD"
```

---

## Task 12: `SarprasPengadaanDemoSeeder.php` — Retarget SMP → SD, Ganti Identitas Ruang

**Files:**
- Modify: `database/seeders/SarprasPengadaanDemoSeeder.php`

**Interfaces:**
- Consumes: `Lembaga` (Task 1), `User` (`kepsek.sd@demo.test`/`adm.sd@demo.test` dari Task 3).

- [ ] **Step 1: Baca ulang file existing, konfirmasi struktur sama dengan baseline (362 baris)**

```bash
wc -l database/seeders/SarprasPengadaanDemoSeeder.php
```

- [ ] **Step 2: Ganti bagian lookup lembaga + auto-create fallback (baris ~54-63)**

Ganti:
```php
        $lembaga = Lembaga::where('npsn', '20223344')->first() ?? Lembaga::first();
        if (! $lembaga) {
            $lembaga = Lembaga::create([
                'yayasan_id' => $yayasan->id,
                'nama' => 'SMPIT PINTERA',
                'jenjang' => 'SMP',
                'npsn' => '20223344',
                'status_aktif' => true,
            ]);
        }
```
Menjadi:
```php
        $lembaga = Lembaga::where('npsn', '20223333')->first() ?? Lembaga::first();
        if (! $lembaga) {
            $lembaga = Lembaga::create([
                'yayasan_id' => $yayasan->id,
                'nama' => 'SDIT PINTERA',
                'jenjang' => 'SD',
                'npsn' => '20223333',
                'status_aktif' => true,
            ]);
        }
```

- [ ] **Step 3: Ganti akun `kepsek`/`adm` (baris ~79-91)**

Ganti:
```php
        $kepsek = User::firstOrCreate(
            ['email' => 'kepsek.smp@demo.test'],
            ['name' => 'Dr. H. Ahmad Dahlan (Kepala Sekolah)', 'password' => 'password', 'is_active' => true, 'lembaga_id' => $lembaga->id]
        );
        $kepsek->update(['lembaga_id' => $lembaga->id]);
        $kepsek->assignRole('kepala_sekolah');

        $adm = User::firstOrCreate(
            ['email' => 'adm.smp@demo.test'],
            ['name' => 'Admin Sarpras & Operasional', 'password' => 'password', 'is_active' => true, 'lembaga_id' => $lembaga->id]
        );
        $adm->update(['lembaga_id' => $lembaga->id]);
        $adm->assignRole('admin_administrasi');
```
Menjadi:
```php
        $kepsek = User::firstOrCreate(
            ['email' => 'kepsek.sd@demo.test'],
            ['name' => 'Abdullah, M.Pd. (Kepala Sekolah)', 'password' => 'password', 'is_active' => true, 'lembaga_id' => $lembaga->id]
        );
        $kepsek->update(['lembaga_id' => $lembaga->id]);
        $kepsek->assignRole('kepala_sekolah');

        $adm = User::firstOrCreate(
            ['email' => 'adm.sd@demo.test'],
            ['name' => 'Admin Sarpras & Operasional', 'password' => 'password', 'is_active' => true, 'lembaga_id' => $lembaga->id]
        );
        $adm->update(['lembaga_id' => $lembaga->id]);
        $adm->assignRole('admin_administrasi');
```

- [ ] **Step 4: Ganti nama ruangan kelas (baris ~113-138) — "Ruang Kelas VII-A/B" → "Ruang Kelas 1-A/1-B"**

Ganti:
```php
        $rKelasA = Ruangan::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_ruangan' => 'R-101'],
            [
                'gedung_id' => $gedungUtama->id,
                'nama_ruangan' => 'Ruang Kelas VII-A',
                'lantai' => 1,
                'jenis_ruangan' => JenisRuangan::KelasTeori,
                'kapasitas_siswa' => 36,
                'is_shared' => false,
                'is_aktif' => true,
            ]
        );

        $rKelasB = Ruangan::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_ruangan' => 'R-102'],
            [
                'gedung_id' => $gedungUtama->id,
                'nama_ruangan' => 'Ruang Kelas VII-B',
                'lantai' => 1,
                'jenis_ruangan' => JenisRuangan::KelasTeori,
                'kapasitas_siswa' => 36,
                'is_shared' => false,
                'is_aktif' => true,
            ]
        );
```
Menjadi:
```php
        $rKelasA = Ruangan::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_ruangan' => 'R-101'],
            [
                'gedung_id' => $gedungUtama->id,
                'nama_ruangan' => 'Ruang Kelas 1-A',
                'lantai' => 1,
                'jenis_ruangan' => JenisRuangan::KelasTeori,
                'kapasitas_siswa' => 28,
                'is_shared' => false,
                'is_aktif' => true,
            ]
        );

        $rKelasB = Ruangan::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_ruangan' => 'R-102'],
            [
                'gedung_id' => $gedungUtama->id,
                'nama_ruangan' => 'Ruang Kelas 1-B',
                'lantai' => 1,
                'jenis_ruangan' => JenisRuangan::KelasTeori,
                'kapasitas_siswa' => 28,
                'is_shared' => false,
                'is_aktif' => true,
            ]
        );
```

Catatan: `kapasitas_siswa` diturunkan dari 36 → 28 supaya konsisten dengan target volume siswa/kelas (§4 spec) — bukan angka acak.

- [ ] **Step 5: Ganti sisa referensi tekstual "VII-A"/"VII-B"/"Kelas VII" di komentar/deskripsi non-fungsional (baris ~242, ~310, ~324, ~341) — hanya STRING, bukan logic**

Ganti setiap kemunculan string `'Meja Belajar Siswa Kayu Jati'` dan sekitarnya yang menyebut "Kelas VII-A"/"Kelas VII-B" di `spesifikasi`/`alasan_mutasi`/`latar_belakang` (deskriptif, tidak mempengaruhi query) — ganti "VII-A" → "1-A", "VII-B" → "1-B", "kelas VII" → "kelas 1". Field `qty`/`harga_perolehan` (36 unit meja/kursi) diturunkan mengikuti kapasitas baru: `qty => 28` untuk baris `INV/2026/MEB/001` dan `INV/2026/MEB/002`, `harga_perolehan` dihitung ulang proporsional (450rb × 28 = 12.600.000; 250rb × 28 = 7.000.000).

- [ ] **Step 6: Verifikasi syntax**

```bash
php -l database/seeders/SarprasPengadaanDemoSeeder.php
```

- [ ] **Step 7: Commit**

```bash
git add database/seeders/SarprasPengadaanDemoSeeder.php
git commit -m "refactor(seeder): SarprasPengadaanDemoSeeder retarget SMP ke SD, identitas ruang & kapasitas disesuaikan"
```

---

## Task 13: `KeuanganDemoSeeder.php` — Tambah Entri Orang Tua SD

**Files:**
- Modify: `database/seeders/KeuanganDemoSeeder.php`

**Interfaces:**
- Consumes: `User`/`OrangTua` (Task 6, `ortu.sd@demo.test`, NIK `0000019901850001`).

- [ ] **Step 1: Ganti array `$demoParents`**

Ganti:
```php
        $demoParents = [
            ['nik' => '0000019901850001', 'email' => 'ortu@demo.test'],
            ['nik' => '0000019901850002', 'email' => 'ortu.kb@demo.test'],
            ['nik' => '0000019901850003', 'email' => 'ortu.tk@demo.test'],
        ];
```
Menjadi:
```php
        $demoParents = [
            ['nik' => '0000019901850001', 'email' => 'ortu.sd@demo.test'],
        ];
```

Sisa file (`seedForSiswa`, `buatTagihan`, `cariAdminKeuangan` — sudah generik lewat `match($lembaga->bentuk_pendidikan)` termasuk cabang `'SD' => 'sd'`) **TIDAK BERUBAH SAMA SEKALI**.

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l database/seeders/KeuanganDemoSeeder.php
```

- [ ] **Step 3: Commit**

```bash
git add database/seeders/KeuanganDemoSeeder.php
git commit -m "refactor(seeder): KeuanganDemoSeeder tambah entri orang tua SD, hapus KB/TK/SMP"
```

---

## Task 14: `AkunPendaftarSeeder.php` — Bersihkan Entri Mati KB/TK/SMP

**Files:**
- Modify: `database/seeders/AkunPendaftarSeeder.php`

- [ ] **Step 1: Ganti array `$emailPerNpsn`**

Ganti:
```php
        $emailPerNpsn = [
            '20223311' => ['email' => 'pendaftar.kb@demo.test', 'nama' => 'Wali KB (Contoh)'],
            '20223322' => ['email' => 'pendaftar.tk@demo.test', 'nama' => 'Wali TK (Contoh)'],
            '20223333' => ['email' => 'pendaftar.sd@demo.test', 'nama' => 'Wali SD (Contoh)'],
            '20223344' => ['email' => 'pendaftar.smp@demo.test', 'nama' => 'Wali SMP (Contoh)'],
        ];
```
Menjadi:
```php
        $emailPerNpsn = [
            '20223333' => ['email' => 'pendaftar.sd@demo.test', 'nama' => 'Wali SD (Contoh)'],
        ];
```

Sisa file (`whereIn('npsn', array_keys(...))`, logic `firstOrCreate`) **TIDAK BERUBAH** — sudah generik terhadap isi array.

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l database/seeders/AkunPendaftarSeeder.php
```

- [ ] **Step 3: Commit**

```bash
git add database/seeders/AkunPendaftarSeeder.php
git commit -m "refactor(seeder): AkunPendaftarSeeder bersihkan entri mati KB/TK/SMP"
```

---

## Task 15: `AsesmenSeeder.php` — Detail Treatment untuk Kelas 1-A/1-B

**Files:**
- Modify: `database/seeders/AsesmenSeeder.php`

**Interfaces:**
- Consumes: `Kelas` ("Kelas 1-A" Task 4), `MataPelajaran` (Matematika/IPAS — sudah ada dari `MataPelajaranSeeder`, TIDAK disentuh), `Guru` (hendra.gunawan/maya.anggraini Task 2), `KomponenPenilaian` (TIDAK disentuh).

**Cakupan**: HANYA Kelas 1-A yang dapat detail treatment (meniru kedalaman SMP yang cuma VII-A) — Kelas 1-B s.d. 6-B tetap pakai `seedGenericAsesmen` (TIDAK diubah).

- [ ] **Step 1: Baca ulang `database/seeders/MataPelajaranSeeder.php` untuk konfirmasi nama PERSIS mapel SD yang tersedia (kode `KomponenPenilaian` TP untuk MTK/IPAS)**

```bash
grep -A3 "'SD'" database/seeders/MataPelajaranSeeder.php | head -20
```

- [ ] **Step 2: Ganti kondisi npsn + rename fungsi**

Ganti:
```php
            if ($lembaga->npsn === '20223344') {
                $this->seedSmpAsesmen($lembaga, $aktif, $semester);
            } else {
                $this->seedGenericAsesmen($lembaga, $aktif, $semester);
            }
```
Menjadi:
```php
            if ($lembaga->npsn === '20223333') {
                $this->seedSdAsesmen($lembaga, $aktif, $semester);
            } else {
                $this->seedGenericAsesmen($lembaga, $aktif, $semester);
            }
```

- [ ] **Step 3: Ganti isi `seedSmpAsesmen` → `seedSdAsesmen`**

Ganti signature dan seluruh isi fungsi `private function seedSmpAsesmen(Lembaga $smp, TahunAjaran $aktif, Semester $semester): void` — retarget:
- `$smp` → `$sd`
- `'nama', 'VII-A'` → `'nama', 'Kelas 1-A'`
- `where('nama', 'Matematika')` TETAP (nama mapel sama persis semua jenjang)
- `where('nama', 'Ilmu Pengetahuan Alam (IPA)')` → `where('nama', 'Ilmu Pengetahuan Alam dan Sosial (IPAS)')` — **WAJIB dikonfirmasi dulu nama PERSIS dari Step 1**, kalau nama di `MataPelajaranSeeder.php` berbeda dari tebakan ini, pakai nama PERSIS hasil Step 1.
- `where('email', 'budi.santoso@demo.test')` → `where('email', 'hendra.gunawan@demo.test')`
- `where('email', 'siti.rahmawati@demo.test')` → `where('email', 'maya.anggraini@demo.test')`
- Judul asesmen "Sumatif Lingkup Materi 1: Bilangan & Aljabar"/"Sumatif Lingkup Materi 1: Besaran & Pengukuran" — SESUAIKAN konteks SD (lebih dasar), misal "Sumatif Lingkup Materi 1: Bilangan Cacah" / "Sumatif Lingkup Materi 1: Pengenalan Ekosistem".
- Kode `KomponenPenilaian` (`TP.1.1`, `TP.1.2`, `TP.IPA.1`) — WAJIB dicek ulang terhadap kode TP yang benar-benar ada untuk mapel SD (hasil Step 1), JANGAN asumsikan kode sama persis dengan SMP.

- [ ] **Step 4: Verifikasi syntax**

```bash
php -l database/seeders/AsesmenSeeder.php
```

- [ ] **Step 5: Commit**

```bash
git add database/seeders/AsesmenSeeder.php
git commit -m "refactor(seeder): AsesmenSeeder detail treatment Kelas 1-A SD (retarget dari SMP)"
```

---

## Task 16: `JadwalPelajaranSeeder.php` — Detail Treatment Kelas 1-A/1-B

**Files:**
- Modify: `database/seeders/JadwalPelajaranSeeder.php`

**Interfaces:**
- Sama seperti Task 15, plus `JamPelajaran`/`PolaJam` (TIDAK disentuh, generik per lembaga).

- [ ] **Step 1: Ganti kondisi npsn + rename fungsi**

Ganti `$lembaga->npsn === '20223344'` → `$lembaga->npsn === '20223333'`, `seedSmpJadwal` → `seedSdJadwal`.

- [ ] **Step 2: Ganti isi `seedSmpJadwal` → `seedSdJadwal`**

Retarget seperti Task 15 Step 3: `$smp`→`$sd`, `'VII-A'`→`'Kelas 1-A'`, `'VII-B'`→`'Kelas 1-B'`, `budi.santoso`→`hendra.gunawan`, `siti.rahmawati`→`maya.anggraini`, nama mapel IPA→IPAS (sesuai hasil konfirmasi Task 15 Step 1). Struktur alternating jam 1&2 vs jam 4&5 antar 2 kelas **TIDAK BERUBAH** (logic penjadwalan silang sudah benar, cuma ganti identitas).

- [ ] **Step 3: Verifikasi syntax**

```bash
php -l database/seeders/JadwalPelajaranSeeder.php
```

- [ ] **Step 4: Commit**

```bash
git add database/seeders/JadwalPelajaranSeeder.php
git commit -m "refactor(seeder): JadwalPelajaranSeeder detail treatment Kelas 1-A/1-B SD"
```

---

## Task 17: `SesiPembelajaranSeeder.php` — Detail Treatment Kelas 1-A

**Files:**
- Modify: `database/seeders/SesiPembelajaranSeeder.php`

**Interfaces:**
- Consumes: `JadwalPelajaran` (hasil Task 16).

- [ ] **Step 1: Ganti kondisi npsn + rename fungsi**

Ganti `$lembaga->npsn === '20223344'` → `$lembaga->npsn === '20223333'`, `seedSmpSesi` → `seedSdSesi`.

- [ ] **Step 2: Ganti isi `seedSmpSesi` → `seedSdSesi`**

Retarget: `$smp`→`$sd`, `'VII-A'`→`'Kelas 1-A'`, `budi.santoso`→`hendra.gunawan`, `siti.rahmawati`→`maya.anggraini`, mapel IPA→IPAS. Materi "Pengenalan Aljabar dan Persamaan Linier Satu Variabel"/"Pengenalan Metode Ilmiah dan Pengukuran Fisika" — sesuaikan konteks SD dasar, misal "Mengenal Bilangan Cacah dan Operasi Penjumlahan"/"Mengenal Lingkungan Sekitar dan Makhluk Hidup".

- [ ] **Step 3: Verifikasi syntax**

```bash
php -l database/seeders/SesiPembelajaranSeeder.php
```

- [ ] **Step 4: Commit**

```bash
git add database/seeders/SesiPembelajaranSeeder.php
git commit -m "refactor(seeder): SesiPembelajaranSeeder detail treatment Kelas 1-A SD"
```

---

## Task 18: `NilaiSiswaSeeder.php` — Detail Treatment Kelas 1-A, Skor Bervariasi

**Files:**
- Modify: `database/seeders/NilaiSiswaSeeder.php`

**Interfaces:**
- Consumes: `Asesmen` (hasil Task 15), `Siswa` (28 siswa Kelas 1-A hasil Task 5 — BUKAN 5 seperti SMP, jadi array skor literal perlu diperpanjang atau diganti pola variatif).

- [ ] **Step 1: Ganti kondisi npsn + rename fungsi**

Ganti `$lembaga->npsn === '20223344'` → `$lembaga->npsn === '20223333'`, `seedSmpNilai` → `seedSdNilai`.

- [ ] **Step 2: Ganti isi `seedSmpNilai` → `seedSdNilai` — skor variatif untuk 28 siswa, bukan array literal 5 elemen**

```php
    private function seedSdNilai(Lembaga $sd, TahunAjaran $aktif): void
    {
        $kelasA = Kelas::where('lembaga_id', $sd->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', 'Kelas 1-A')->first();
        if (! $kelasA) {
            return;
        }

        $siswaA = Siswa::where('kelas_id', $kelasA->id)->orderBy('nis')->get();
        $mtk = MataPelajaran::where('lembaga_id', $sd->id)->where('nama', 'Matematika')->first();
        $ipas = MataPelajaran::where('lembaga_id', $sd->id)->where('nama', 'Ilmu Pengetahuan Alam dan Sosial (IPAS)')->first();

        $catatanVariasi = [
            'Menunjukkan pemahaman yang sangat baik, mampu mengerjakan latihan tanpa bantuan.',
            'Cukup baik, masih perlu bimbingan pada beberapa soal cerita.',
            'Sangat unggul, selalu aktif bertanya dan mengerjakan tugas tambahan.',
            'Perlu penguatan pada pemahaman konsep dasar, disarankan les tambahan.',
            'Baik, konsisten dalam menyelesaikan latihan harian.',
        ];

        if ($mtk && $siswaA->isNotEmpty()) {
            $asesmenMtk = Asesmen::where('kelas_id', $kelasA->id)->where('mata_pelajaran_id', $mtk->id)->first();
            $tpMtk1 = KomponenPenilaian::where('mata_pelajaran_id', $mtk->id)->first();

            if ($asesmenMtk && $tpMtk1) {
                foreach ($siswaA as $i => $siswa) {
                    $skor = 70 + (($i * 7 + 13) % 30); // rentang 70-99, bervariasi per siswa
                    NilaiSiswa::updateOrCreate(
                        ['asesmen_id' => $asesmenMtk->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $tpMtk1->id],
                        ['nilai_angka' => $skor, 'catatan' => $catatanVariasi[$i % count($catatanVariasi)]]
                    );
                }
            }
        }

        if ($ipas && $siswaA->isNotEmpty()) {
            $asesmenIpas = Asesmen::where('kelas_id', $kelasA->id)->where('mata_pelajaran_id', $ipas->id)->first();
            $tpIpas1 = KomponenPenilaian::where('mata_pelajaran_id', $ipas->id)->first();

            if ($asesmenIpas && $tpIpas1) {
                foreach ($siswaA as $i => $siswa) {
                    $skor = 72 + (($i * 5 + 9) % 28); // rentang 72-99, bervariasi per siswa
                    NilaiSiswa::updateOrCreate(
                        ['asesmen_id' => $asesmenIpas->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $tpIpas1->id],
                        ['nilai_angka' => $skor, 'catatan' => $catatanVariasi[($i + 2) % count($catatanVariasi)]]
                    );
                }
            }
        }
    }
```

Catatan: skor dihitung formula `70 + (($i * 7 + 13) % 30)` — deterministik (idempotent, hasil sama tiap `migrate:fresh --seed`), tapi bervariasi antar siswa (bukan flat 85 semua) tanpa perlu 28 baris literal.

- [ ] **Step 3: Verifikasi syntax**

```bash
php -l database/seeders/NilaiSiswaSeeder.php
```

- [ ] **Step 4: Commit**

```bash
git add database/seeders/NilaiSiswaSeeder.php
git commit -m "refactor(seeder): NilaiSiswaSeeder detail treatment Kelas 1-A SD, skor bervariasi deterministik"
```

---

## Task 19: `PresensiSeeder.php` — Variasi Status untuk SD

**Files:**
- Modify: `database/seeders/PresensiSeeder.php`

**Interfaces:**
- Consumes: `SesiPembelajaran` (hasil Task 17), `Siswa` (hasil Task 5).

- [ ] **Step 1: Timpa seluruh isi file — ganti kondisi npsn SMP + NIS spesifik literal menjadi variasi modulo generik (karena SD punya 28 siswa/kelas, bukan 5 seperti SMP dulu)**

```php
<?php
// database/seeders/PresensiSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class PresensiSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
            if (! $aktif) {
                continue;
            }

            $kelasIds = Kelas::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->pluck('id');
            $sesiList = SesiPembelajaran::whereIn('kelas_id', $kelasIds)->get();

            foreach ($sesiList as $sesi) {
                $siswaList = Siswa::where('kelas_id', $sesi->kelas_id)->get();

                foreach ($siswaList as $index => $siswa) {
                    // Variasi status: 1 dari setiap 10 siswa sakit, 1 dari setiap 15 izin, sisanya hadir.
                    // Modulo deterministik (idempotent), bukan random -- supaya migrate:fresh --seed
                    // berulang menghasilkan data identik.
                    if ($lembaga->npsn === '20223333' && $index % 10 === 0) {
                        Presensi::firstOrCreate(
                            ['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id],
                            ['status' => 'sakit', 'keterangan' => 'Demam, surat dokter menyusul']
                        );
                    } elseif ($lembaga->npsn === '20223333' && $index % 15 === 1) {
                        Presensi::firstOrCreate(
                            ['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id],
                            ['status' => 'izin', 'keterangan' => 'Acara keluarga']
                        );
                    } else {
                        Presensi::firstOrCreate(
                            ['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id],
                            ['status' => 'hadir', 'keterangan' => null]
                        );
                    }
                }
            }
        }
    }
}
```

- [ ] **Step 2: Verifikasi syntax**

```bash
php -l database/seeders/PresensiSeeder.php
```

- [ ] **Step 3: Commit**

```bash
git add database/seeders/PresensiSeeder.php
git commit -m "refactor(seeder): PresensiSeeder variasi status sakit/izin/hadir untuk SD (modulo deterministik)"
```

---

## Task 20: Verifikasi Akhir Menyeluruh + Handoff Log

**Files:**
- Create: `.agents/logs/2026-08-24-seeder-susutkan-1-lembaga-sd.md`
- Modify: `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` (opsional — hanya kalau user minta didaftarkan di roadmap induk; TIDAK WAJIB karena ini bukan migrasi domain arsitektur)

- [ ] **Step 1: Grep gabungan — pastikan tidak ada sisa string NPSN/kode lembaga lama**

```bash
grep -rn "20223311\|20223322\|20223344\|KBITPTR\|TKITPTR\|SMPITPTR" database/seeders/*.php
```
Expected: KOSONG total. Kalau ADA, catat file+baris, evaluasi apakah itu benar-benar sisa yang terlewat atau ada alasan sah (dicek manual satu-per-satu, JANGAN asumsikan aman).

- [ ] **Step 2: Jalankan migrate:fresh --seed final, tangkap SELURUH output**

```bash
php artisan migrate:fresh --seed 2>&1 | tee /tmp/seed-output.log | tail -150
```

Expected: TIDAK ADA exception. Warning `command?->warn(...)` sisa (kalau ada) WAJIB dibaca detail — pastikan bukan indikasi silent-skip yang belum tertangani.

- [ ] **Step 3: Verifikasi volume data sesuai target §4 spec**

```bash
php artisan tinker --execute="
echo 'Lembaga: '.\App\Models\Lembaga::count().PHP_EOL;
echo 'Kelas: '.\App\Models\Kelas::count().PHP_EOL;
echo 'Siswa: '.\App\Models\Siswa::count().PHP_EOL;
echo 'Guru: '.\App\Models\Guru::count().PHP_EOL;
echo 'OrangTua: '.\App\Models\OrangTua::count().PHP_EOL;
echo 'Kasus: '.\App\Domains\Kasus\Models\Kasus::count().PHP_EOL;
"
```
Catat angka PASTI, bandingkan dengan target §4 spec (12 kelas, ~336 siswa, ~15 guru, 4-6 orang tua+login, 7 kasus — dikali jumlah TahunAjaran aktif kalau relevan).

- [ ] **Step 4: Verifikasi tidak ada email berlabel salah (mis. `*.kb@demo.test` tersisa untuk data SD)**

```bash
php artisan tinker --execute="echo \App\Models\User::where('email', 'like', '%.kb@demo.test')->orWhere('email', 'like', '%.tk@demo.test')->orWhere('email', 'like', '%.smp@demo.test')->count();"
```
Expected: 0.

- [ ] **Step 5: Minta izin user untuk full test suite**

Tanya ke user: "Task 1-19 selesai, migrate:fresh --seed sukses tanpa exception, volume data sesuai target, tidak ada sisa NPSN/label lama. Boleh saya jalankan full test suite (`php artisan test`) untuk verifikasi akhir?" — TUNGGU jawaban eksplisit.

**PENTING**: JANGAN jalankan proses `php artisan test` bersamaan dengan proses lain yang mengakses MySQL test database (`pintera_app_test`) — pelajaran dari review Keuangan SP3/SP4, tabrakan proses menyebabkan kegagalan palsu.

- [ ] **Step 6: Jalankan full suite SOLO (hanya setelah izin didapat)**

```bash
php artisan test
```
Catat angka PASTI passed/failed/duration. Kalau ada test yang GAGAL dan bergantung pada `DatabaseSeeder::run()` (jarang, karena test biasanya pakai `RefreshDatabase`+factory independen — tapi WAJIB dicek, bukan diasumsikan), catat sebagai temuan terpisah, JANGAN diperbaiki diam-diam di luar scope plan ini kecuali user setuju.

- [ ] **Step 7: Tulis handoff log**

Buat `.agents/logs/2026-08-24-seeder-susutkan-1-lembaga-sd.md` (Bahasa Indonesia): ringkasan Task 1-19 dengan commit hash, hasil grep Step 1 (kosong), hasil volume data Step 3 (angka pasti), hasil full suite Step 6.

- [ ] **Step 8: Commit**

```bash
git add .agents/logs/2026-08-24-seeder-susutkan-1-lembaga-sd.md
git commit -m "docs(seeder): handoff log penyusutan seeder demo ke 1 lembaga (SD)"
```
