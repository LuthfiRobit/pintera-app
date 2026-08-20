# Rebranding Data Demo Pintera Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pendekkan email dummy jadi `@demo.test` per role/scope, rebranding Yayasan/Lembaga ke "Pintera" (tanpa "Kraksaan" di nama, alamat asli dipertahankan), dan ganti nama staf dari honorifik keagamaan ke pola netral — di seluruh `database/seeders/*.php`.

**Architecture:** Edit teks murni pada seeder Laravel yang sudah ada (`database/seeders/*.php`), tidak ada perubahan skema/model. Setiap file yang menyimpan email/nama ATAU yang men-query email/nama sebagai lookup key harus diubah BERSAMAAN (satu commit) supaya tidak ada pasangan tulis/baca yang tidak sinkron.

**Tech Stack:** PHP 8.2+, Laravel 11, Pest (`php artisan test`), MySQL lokal (Laragon).

## Global Constraints

- Spec sumber: `.agents/specs/2026-08-21-rebranding-data-demo-pintera.md` — baca file ini lebih dulu kalau ada keraguan.
- Role name di kode (`admin_keuangan`, dst) **TIDAK diubah sama sekali** di plan ini — keputusan eksplisit user, rename role slug ditunda ke fase lain di luar cakupan.
- **Plan ini INDEPENDEN dari plan Spec 1** (`.agents/plans/2026-08-21-audit-perbaikan-seeder.md`) — boleh dieksekusi sebelum, sesudah, atau tanpa Spec 1 sama sekali. Beberapa file (`EssentialUserSeeder.php`, `SarprasPengadaanDemoSeeder.php`) mungkin sudah berubah bentuk kalau Spec 1 sudah dieksekusi lebih dulu (ada tambahan environment-guard di awal `run()`, dan `SarprasPengadaanDemoSeeder.php` mungkin sudah tidak lagi punya blok `Role::firstOrCreate()`/`givePermissionTo()`). **Setiap step yang mengedit kode WAJIB dimulai dengan mencari string LAMA yang persis disebut di step itu** — kalau string itu TIDAK DITEMUKAN (karena sudah diubah plan/commit lain), CATAT di laporan task dan LEWATI step itu, jangan menebak-nebak bentuk baru.
- Scoped test per task. **Full suite HANYA di Task 6 (task terakhir), HARUS minta izin user dulu.**
- Setiap task yang mengubah data yang dipakai lintas-file (email, `kode_lembaga`, nama Lembaga) WAJIB diverifikasi dengan `php artisan migrate:fresh --seed` + query nyata, bukan cuma baca kode.
- Commit terpisah per task, pesan commit Bahasa Indonesia.
- **Executor plan ini KEMUNGKINAN adalah sesi/agent lain** tanpa akses ke percakapan yang menulis spec/plan — setiap task berisi kode lengkap dan command verifikasi nyata.
- **Temuan penting saat plan ditulis (bukan dari spec):** `CalonMuridSeeder.php` (baris 31) dan `PendaftaranSeeder.php` (baris 84, 91) membentuk key pencarian `CalonMurid`/`Pendaftaran` dengan `$namaDasar.' ('.$lembaga->nama.')'` — ini mengambil `$lembaga->nama` SECARA DINAMIS dari database, BUKAN string hardcode. Artinya begitu Task 1 (rebranding nama Lembaga) selesai, kedua file ini **otomatis ikut benar tanpa perlu diedit** — jangan sentuh `CalonMuridSeeder.php`/`PendaftaranSeeder.php` untuk urusan nama Lembaga, itu bukan bug, itu sudah baca dari sumber yang benar.

---

### Task 1: Rebranding Yayasan & Lembaga ke "Pintera"

**Files:**
- Modify: `database/seeders/YayasanSeeder.php`
- Modify: `database/seeders/LembagaSeeder.php`
- Modify: `database/seeders/KeuanganDemoSeeder.php` (method `cariAdminKeuangan()`)
- Modify: `database/seeders/SarprasPengadaanDemoSeeder.php` (fallback create, baris ~47-61)

**Interfaces:**
- Produces: `Lembaga.nama` = `"KBIT PINTERA"` / `"TKIT PINTERA"` / `"SDIT PINTERA"` / `"SMPIT PINTERA"`; `Lembaga.kode_lembaga` = `KBITPTR` / `TKITPTR` / `SDITPTR` / `SMPITPTR`; `Lembaga.bentuk_pendidikan` TIDAK berubah (`KB`/`TK`/`SD`/`SMP`) — Task 2-5 memakai `bentuk_pendidikan` (bukan `kode_lembaga`) untuk menentukan kode email pendek (`kb`/`tk`/`sd`/`smp`).

- [ ] **Step 1: `YayasanSeeder.php` — ganti nama & email institusi**

Cari string berikut di `database/seeders/YayasanSeeder.php`:

```php
        Yayasan::firstOrCreate(
            ['nama' => 'Yayasan Permata Kraksaan'],
            [
                'npwp_yayasan' => '01.234.567.8-901.000',
                'akta_pendirian_nomor' => '12',
                'akta_pendirian_tanggal' => '2005-03-15',
                'sk_kemenkumham_nomor' => 'AHU-0001234.AH.01.04.Tahun 2005',
                'alamat' => 'Jl. Panglima Sudirman No. 88, Kraksaan, Kabupaten Probolinggo, Jawa Timur 67282',
                'telepon' => '0335-771234',
                'email' => 'info@permatakraksaan.sch.id',
                'website' => 'https://permatakraksaan.sch.id',
                'nama_ketua_pembina' => 'KH. Ahmad Fauzi, Lc., M.Pd.',
                'nama_ketua_pengurus' => 'Hj. Siti Maryam, S.Ag.',
            ]
        );
```

Ganti jadi (alamat TIDAK berubah — kecamatan Kraksaan, kabupaten Probolinggo tetap; hanya nama, email, website yang berubah):

```php
        Yayasan::firstOrCreate(
            ['nama' => 'Yayasan Pintera'],
            [
                'npwp_yayasan' => '01.234.567.8-901.000',
                'akta_pendirian_nomor' => '12',
                'akta_pendirian_tanggal' => '2005-03-15',
                'sk_kemenkumham_nomor' => 'AHU-0001234.AH.01.04.Tahun 2005',
                'alamat' => 'Jl. Panglima Sudirman No. 88, Kraksaan, Kabupaten Probolinggo, Jawa Timur 67282',
                'telepon' => '0335-771234',
                'email' => 'info@pintera.sch.id',
                'website' => 'https://pintera.sch.id',
                'nama_ketua_pembina' => 'KH. Ahmad Fauzi, Lc., M.Pd.',
                'nama_ketua_pengurus' => 'Hj. Siti Maryam, S.Ag.',
            ]
        );
```

- [ ] **Step 2: `LembagaSeeder.php` — ganti nama, kode, email, website, rekening 4 lembaga**

Di `database/seeders/LembagaSeeder.php`, untuk MASING-MASING dari 4 blok `Lembaga::firstOrCreate(...)`, ganti field berikut (field lain SAMA PERSIS, TIDAK disentuh):

**Blok KB (npsn `20223311`):**
- `'kode_lembaga' => 'KBITPRM'` → `'kode_lembaga' => 'KBITPTR'`
- `'nama' => 'KBIT PERMATA KRAKSAAN'` → `'nama' => 'KBIT PINTERA'`
- `'email' => 'kbit@permatakraksaan.sch.id'` → `'email' => 'kbit@pintera.sch.id'`
- `'website' => 'https://kbit.permatakraksaan.sch.id'` → `'website' => 'https://kbit.pintera.sch.id'`
- `'rekening_atas_nama' => 'KBIT PERMATA KRAKSAAN'` → `'rekening_atas_nama' => 'KBIT PINTERA'`
- `'nama_wajib_pajak' => 'KBIT PERMATA KRAKSAAN'` → `'nama_wajib_pajak' => 'KBIT PINTERA'`

**Blok TK (npsn `20223322`):**
- `'kode_lembaga' => 'TKITPRM'` → `'kode_lembaga' => 'TKITPTR'`
- `'nama' => 'TKIT PERMATA KRAKSAAN'` → `'nama' => 'TKIT PINTERA'`
- `'email' => 'tkit@permatakraksaan.sch.id'` → `'email' => 'tkit@pintera.sch.id'`
- `'website' => 'https://tkit.permatakraksaan.sch.id'` → `'website' => 'https://tkit.pintera.sch.id'`
- `'rekening_atas_nama' => 'TKIT PERMATA KRAKSAAN'` → `'rekening_atas_nama' => 'TKIT PINTERA'`
- `'nama_wajib_pajak' => 'TKIT PERMATA KRAKSAAN'` → `'nama_wajib_pajak' => 'TKIT PINTERA'`

**Blok SD (npsn `20223333`):**
- `'kode_lembaga' => 'SDITPRM'` → `'kode_lembaga' => 'SDITPTR'`
- `'nama' => 'SDIT PERMATA KRAKSAAN'` → `'nama' => 'SDIT PINTERA'`
- `'email' => 'sdit@permatakraksaan.sch.id'` → `'email' => 'sdit@pintera.sch.id'`
- `'website' => 'https://sdit.permatakraksaan.sch.id'` → `'website' => 'https://sdit.pintera.sch.id'`
- `'rekening_atas_nama' => 'SDIT PERMATA KRAKSAAN'` → `'rekening_atas_nama' => 'SDIT PINTERA'`
- `'nama_wajib_pajak' => 'SDIT PERMATA KRAKSAAN'` → `'nama_wajib_pajak' => 'SDIT PINTERA'`

**Blok SMP (npsn `20223344`):**
- `'kode_lembaga' => 'SMPITPRM'` → `'kode_lembaga' => 'SMPITPTR'`
- `'nama' => 'SMPIT PERMATA KRAKSAAN'` → `'nama' => 'SMPIT PINTERA'`
- `'email' => 'smpit@permatakraksaan.sch.id'` → `'email' => 'smpit@pintera.sch.id'`
- `'website' => 'https://smpit.permatakraksaan.sch.id'` → `'website' => 'https://smpit.pintera.sch.id'`
- `'rekening_atas_nama' => 'SMPIT PERMATA KRAKSAAN'` → `'rekening_atas_nama' => 'SMPIT PINTERA'`
- `'nama_wajib_pajak' => 'SMPIT PERMATA KRAKSAAN'` → `'nama_wajib_pajak' => 'SMPIT PINTERA'`

`npsn`, `nss`, alamat (`alamat_jalan`, `rt`, `rw`, `desa_kelurahan`, `kecamatan`, `kabupaten_kota`, `provinsi`, `kode_pos`, `lintang`, `bujur`), `telepon`, `nama_bank`, `cabang_kcp_unit`, `nomor_rekening`, `npwp`, `bentuk_pendidikan`, `nama_kepala_sekolah`, `nama_bendahara_bosp` **TIDAK diubah di task ini** (`nama_kepala_sekolah`/`nama_bendahara_bosp` ditangani Task 5).

- [ ] **Step 3: `KeuanganDemoSeeder.php` — perbaiki `cariAdminKeuangan()`**

Cari string berikut di `database/seeders/KeuanganDemoSeeder.php`:

```php
    private function cariAdminKeuangan(?Lembaga $lembaga): ?User
    {
        if (! $lembaga) {
            return null;
        }

        $prefix = strtolower(preg_replace('/PRM$/', '', $lembaga->kode_lembaga));

        return User::where('email', "keuangan.{$prefix}@permatakraksaan.sch.id")->first();
    }
```

Ganti jadi (memakai `bentuk_pendidikan`, bukan mengutak-atik `kode_lembaga`, supaya tetap benar walau format `kode_lembaga` berubah lagi di masa depan):

```php
    private function cariAdminKeuangan(?Lembaga $lembaga): ?User
    {
        if (! $lembaga) {
            return null;
        }

        $kode = match ($lembaga->bentuk_pendidikan) {
            'KB' => 'kb',
            'TK' => 'tk',
            'SD' => 'sd',
            default => 'smp',
        };

        return User::where('email', "keuangan.{$kode}@demo.test")->first();
    }
```

(Email `keuangan.{kode}@demo.test` ini akan benar-benar ADA setelah Task 2 mengubah email `UserSeeder.php`/`EssentialUserSeeder.php` — kalau Task 2 belum dikerjakan, method ini akan mengembalikan `null` untuk sementara, dan pemanggilnya di `KeuanganDemoSeeder::seedForSiswa()` sudah punya fallback `?? $orangTua->user`, jadi TIDAK error, cuma kasir demo-nya sementara bukan admin keuangan yang tepat sampai Task 2 selesai.)

- [ ] **Step 4: `SarprasPengadaanDemoSeeder.php` — sinkronkan fallback create**

Cari string berikut di `database/seeders/SarprasPengadaanDemoSeeder.php` (blok fallback, hanya jalan kalau belum ada Yayasan/Lembaga sama sekali di database — jarang terpakai tapi tetap harus konsisten):

```php
        $yayasan = Yayasan::first();
        if (! $yayasan) {
            $yayasan = Yayasan::create(['nama' => 'Yayasan Pendidikan Islam Permata']);
        }

        $lembaga = Lembaga::where('npsn', '20223344')->first() ?? Lembaga::first();
        if (! $lembaga) {
            $lembaga = Lembaga::create([
                'yayasan_id' => $yayasan->id,
                'nama' => 'SMP IT PERMATA KRAKSAAN',
                'jenjang' => 'SMP',
                'npsn' => '20223344',
                'status_aktif' => true,
            ]);
        }
```

Ganti jadi:

```php
        $yayasan = Yayasan::first();
        if (! $yayasan) {
            $yayasan = Yayasan::create(['nama' => 'Yayasan Pintera']);
        }

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

- [ ] **Step 5: Verifikasi manual**

```bash
php artisan migrate:fresh --seed
```

Expected: selesai tanpa error.

```bash
php artisan tinker --execute="echo App\Models\Lembaga::pluck('nama', 'kode_lembaga')->toJson();"
```

Expected: 4 baris, semua `kode_lembaga` berakhiran `PTR`, semua `nama` mengandung `PINTERA` dan TIDAK mengandung `PERMATA` atau `KRAKSAAN`.

```bash
php artisan tinker --execute="echo App\Models\Lembaga::first()->kecamatan;"
```

Expected: `Kraksaan` (alamat administratif TETAP, cuma nama brand yang berubah — pastikan tidak ikut ke-strip).

- [ ] **Step 6: Jalankan scoped test**

```bash
php artisan test --filter=Lembaga
php artisan test --filter=Yayasan
```

Expected: PASS. Kalau ada test yang meng-assert `'KBIT PERMATA KRAKSAAN'` dkk secara hardcode, update ke `'KBIT PINTERA'` dkk.

- [ ] **Step 7: Commit**

```bash
git add database/seeders/YayasanSeeder.php database/seeders/LembagaSeeder.php database/seeders/KeuanganDemoSeeder.php database/seeders/SarprasPengadaanDemoSeeder.php
git commit -m "feat(seeder): rebranding Yayasan & Lembaga ke Pintera, perbaiki mapping kode lembaga di KeuanganDemoSeeder"
```

---

### Task 2: Email Staff/RBAC — `EssentialUserSeeder`, `UserSeeder`, `GuruSeeder`, `SiswaSeeder`, `OrangTuaKaryawanSeeder`, `SarprasPengadaanDemoSeeder`, `PendampinganSeeder`

**Files:**
- Modify: `database/seeders/EssentialUserSeeder.php`
- Modify: `database/seeders/UserSeeder.php`
- Modify: `database/seeders/GuruSeeder.php`
- Modify: `database/seeders/SiswaSeeder.php`
- Modify: `database/seeders/OrangTuaKaryawanSeeder.php`
- Modify: `database/seeders/SarprasPengadaanDemoSeeder.php`
- Modify: `database/seeders/PendampinganSeeder.php`

**Interfaces:**
- Consumes: `Lembaga.bentuk_pendidikan` (`KB`/`TK`/`SD`/`SMP`) dari Task 1 (TIDAK berubah oleh Task 1, jadi task ini independen — boleh dikerjakan sebelum ATAU sesudah Task 1).
- Produces: akun login `kepsek.kb@demo.test`, `adm.kb@demo.test`, `keuangan.kb@demo.test`, `kurikulum.kb@demo.test`, `guru.kb1@demo.test` dst (dan padanan `tk`/`sd`/`smp`), `adm.yayasan@demo.test`, `superadmin@demo.test`, dipakai Task 3 (`KeuanganDemoSeeder` sudah diperbaiki Task 1 untuk mencari `keuangan.{kode}@demo.test`).

- [ ] **Step 1: `EssentialUserSeeder.php`**

Cari string berikut (array `$akunLembagaScoped`):

```php
        $akunLembagaScoped = [
            'kepsek@sistem.test' => ['name' => 'Kepala Sekolah (Contoh)', 'role' => 'kepala_sekolah'],
            'adm@sistem.test' => ['name' => 'Admin Administrasi (Contoh)', 'role' => 'admin_administrasi'],
            'keuangan@sistem.test' => ['name' => 'Admin Keuangan (Contoh)', 'role' => 'admin_keuangan'],
            'akademik@sistem.test' => ['name' => 'Admin Akademik (Contoh)', 'role' => 'admin_akademik'],
            'guru@sistem.test' => ['name' => 'Guru (Contoh)', 'role' => 'guru'],
        ];
```

Ganti jadi (akun ini merepresentasikan 1 lembaga contoh, dipetakan ke kode `kb` — lembaga pertama):

```php
        $akunLembagaScoped = [
            'kepsek.kb@demo.test' => ['name' => 'Kepala Sekolah (Contoh)', 'role' => 'kepala_sekolah'],
            'adm.kb@demo.test' => ['name' => 'Admin Administrasi (Contoh)', 'role' => 'admin_administrasi'],
            'keuangan.kb@demo.test' => ['name' => 'Admin Keuangan (Contoh)', 'role' => 'admin_keuangan'],
            'kurikulum.kb@demo.test' => ['name' => 'Admin Akademik (Contoh)', 'role' => 'admin_akademik'],
            'guru.kb1@demo.test' => ['name' => 'Guru (Contoh)', 'role' => 'guru'],
        ];
```

Cari string:

```php
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@sistem.test'],
```

Ganti jadi:

```php
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@demo.test'],
```

**Kalau Spec 1 SUDAH dieksekusi lebih dulu**, file ini juga akan punya baris tambahan `'sarpras@sistem.test' => ['name' => 'Admin Sarpras (Contoh)', 'role' => 'admin_sarpras'],` di array yang sama — kalau baris itu ADA, ganti jadi `'sarpras.kb@demo.test' => ['name' => 'Admin Sarpras (Contoh)', 'role' => 'admin_sarpras'],` mengikuti pola yang sama (masuk ke array `$akunLembagaScoped`, BUKAN array terpisah). Kalau baris itu TIDAK ADA (Spec 1 belum dieksekusi), lewati bagian ini — tidak perlu ditambahkan di plan ini (itu tanggung jawab plan Spec 1).

- [ ] **Step 2: `UserSeeder.php`**

Cari string:

```php
        if (! User::where('email', 'admin.yayasan@permatakraksaan.sch.id')->exists()) {
            $adminYayasan = User::create([
                'name' => 'Ahmad Fauzi (Admin Yayasan)',
                'email' => 'admin.yayasan@permatakraksaan.sch.id',
```

Ganti jadi:

```php
        if (! User::where('email', 'adm.yayasan@demo.test')->exists()) {
            $adminYayasan = User::create([
                'name' => 'Ahmad Fauzi (Admin Yayasan)',
                'email' => 'adm.yayasan@demo.test',
```

Cari string (blok `seedStaf($kbit, ...)`, email pimpinan+guru KBIT):

```php
        // KBIT
        $this->seedStaf($kbit, [
            ['name' => 'Ustadzah Aisyah, S.Psi.', 'email' => 'kepsek.kbit@permatakraksaan.sch.id', 'role' => 'kepala_sekolah'],
            ['name' => 'Ustadzah Nurul, S.Pd.', 'email' => 'adm.kbit@permatakraksaan.sch.id', 'role' => 'admin_administrasi'],
            ['name' => 'Ustadzah Halimah, S.E.', 'email' => 'keuangan.kbit@permatakraksaan.sch.id', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Ustadzah Fatimah, S.Psi.', 'email' => 'guru.kbit1@permatakraksaan.sch.id'],
            ['name' => 'Ustadzah Zahra, S.Pd.', 'email' => 'guru.kbit2@permatakraksaan.sch.id'],
            ['name' => 'Ustadzah Rini, S.Pd.', 'email' => 'guru.kbit3@permatakraksaan.sch.id'],
        ]);
```

Ganti jadi (nama TETAP `Ustadzah ...` di step ini — nama netral ditangani Task 5, task ini HANYA email):

```php
        // KBIT
        $this->seedStaf($kbit, [
            ['name' => 'Ustadzah Aisyah, S.Psi.', 'email' => 'kepsek.kb@demo.test', 'role' => 'kepala_sekolah'],
            ['name' => 'Ustadzah Nurul, S.Pd.', 'email' => 'adm.kb@demo.test', 'role' => 'admin_administrasi'],
            ['name' => 'Ustadzah Halimah, S.E.', 'email' => 'keuangan.kb@demo.test', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Ustadzah Fatimah, S.Psi.', 'email' => 'guru.kb1@demo.test'],
            ['name' => 'Ustadzah Zahra, S.Pd.', 'email' => 'guru.kb2@demo.test'],
            ['name' => 'Ustadzah Rini, S.Pd.', 'email' => 'guru.kb3@demo.test'],
        ]);
```

Cari string (blok TKIT):

```php
        // TKIT
        $this->seedStaf($tkit, [
            ['name' => 'Ustadzah Maryam, S.Pd.I.', 'email' => 'kepsek.tkit@permatakraksaan.sch.id', 'role' => 'kepala_sekolah'],
            ['name' => 'Ustadzah Indria, S.Pd.', 'email' => 'adm.tkit@permatakraksaan.sch.id', 'role' => 'admin_administrasi'],
            ['name' => 'Ustadzah Khadijah, S.E.', 'email' => 'keuangan.tkit@permatakraksaan.sch.id', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Ustadzah Dewi, S.Pd.I.', 'email' => 'guru.tkit1@permatakraksaan.sch.id'],
            ['name' => 'Ustadzah Latifah, S.Pd.', 'email' => 'guru.tkit2@permatakraksaan.sch.id'],
            ['name' => 'Ustadzah Amel, S.Psi.', 'email' => 'guru.tkit3@permatakraksaan.sch.id'],
        ]);
```

Ganti jadi:

```php
        // TKIT
        $this->seedStaf($tkit, [
            ['name' => 'Ustadzah Maryam, S.Pd.I.', 'email' => 'kepsek.tk@demo.test', 'role' => 'kepala_sekolah'],
            ['name' => 'Ustadzah Indria, S.Pd.', 'email' => 'adm.tk@demo.test', 'role' => 'admin_administrasi'],
            ['name' => 'Ustadzah Khadijah, S.E.', 'email' => 'keuangan.tk@demo.test', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Ustadzah Dewi, S.Pd.I.', 'email' => 'guru.tk1@demo.test'],
            ['name' => 'Ustadzah Latifah, S.Pd.', 'email' => 'guru.tk2@demo.test'],
            ['name' => 'Ustadzah Amel, S.Psi.', 'email' => 'guru.tk3@demo.test'],
        ]);
```

Cari string (blok SDIT — guru SD emailnya `@permata.sch.id`, BUKAN `@permatakraksaan.sch.id`, TIDAK termasuk cakupan rebranding domain login karena bukan pola `@sistem.test`/`@permatakraksaan.sch.id` yang disebut spec; HANYA pimpinan SDIT yang diganti):

```php
        // SDIT
        $this->seedStaf($sdit, [
            ['name' => 'Ustadz Abdullah, M.Pd.', 'email' => 'kepsek.sdit@permatakraksaan.sch.id', 'role' => 'kepala_sekolah'],
            ['name' => 'Ustadz Lukman, S.Kom.', 'email' => 'adm.sdit@permatakraksaan.sch.id', 'role' => 'admin_administrasi'],
            ['name' => 'Ustadz Hasan, S.E.', 'email' => 'keuangan.sdit@permatakraksaan.sch.id', 'role' => 'admin_keuangan'],
        ], [
```

Ganti bagian pimpinan (baris `[...], [` penutup dan isi array guru di bawahnya TIDAK berubah):

```php
        // SDIT
        $this->seedStaf($sdit, [
            ['name' => 'Ustadz Abdullah, M.Pd.', 'email' => 'kepsek.sd@demo.test', 'role' => 'kepala_sekolah'],
            ['name' => 'Ustadz Lukman, S.Kom.', 'email' => 'adm.sd@demo.test', 'role' => 'admin_administrasi'],
            ['name' => 'Ustadz Hasan, S.E.', 'email' => 'keuangan.sd@demo.test', 'role' => 'admin_keuangan'],
        ], [
```

Cari string (blok SMPIT — sama pola, guru SMP emailnya `@permata.sch.id` tidak ikut berubah):

```php
        // SMPIT
        $this->seedStaf($smpit, [
            ['name' => 'Ustadz Bambang Suryadi, M.Pd.', 'email' => 'kepsek.smpit@permatakraksaan.sch.id', 'role' => 'kepala_sekolah'],
            ['name' => 'Dewi Lestari, S.Pd.', 'email' => 'adm.smpit@permatakraksaan.sch.id', 'role' => 'admin_administrasi'],
            ['name' => 'Nur Aisyah, S.Pd.', 'email' => 'keuangan.smpit@permatakraksaan.sch.id', 'role' => 'admin_keuangan'],
        ], [
```

Ganti jadi:

```php
        // SMPIT
        $this->seedStaf($smpit, [
            ['name' => 'Ustadz Bambang Suryadi, M.Pd.', 'email' => 'kepsek.smp@demo.test', 'role' => 'kepala_sekolah'],
            ['name' => 'Dewi Lestari, S.Pd.', 'email' => 'adm.smp@demo.test', 'role' => 'admin_administrasi'],
            ['name' => 'Nur Aisyah, S.Pd.', 'email' => 'keuangan.smp@demo.test', 'role' => 'admin_keuangan'],
        ], [
```

**PENTING — jangan lupa 4 file lain yang mereferensikan email SDIT/SMPIT pimpinan di atas** (lihat Step 3-7 di bawah, `GuruSeeder.php` dan `PendampinganSeeder.php` juga mengandung email yang sama persis — HARUS diubah bersamaan di task yang sama supaya tidak ada referensi putus).

- [ ] **Step 3: `GuruSeeder.php` — sinkronkan email guru KB/TK**

Ganti 3 email berikut (email SDIT/SMPIT guru, `@permata.sch.id`, TIDAK berubah — hanya KB dan TK):

| Cari | Ganti |
|---|---|
| `'email' => 'guru.kbit1@permatakraksaan.sch.id', 'name' => 'Ustadzah Fatimah, S.Psi.',` | `'email' => 'guru.kb1@demo.test', 'name' => 'Ustadzah Fatimah, S.Psi.',` |
| `'email' => 'guru.kbit2@permatakraksaan.sch.id', 'name' => 'Ustadzah Zahra, S.Pd.',` | `'email' => 'guru.kb2@demo.test', 'name' => 'Ustadzah Zahra, S.Pd.',` |
| `'email' => 'guru.kbit3@permatakraksaan.sch.id', 'name' => 'Ustadzah Rini, S.Pd.',` | `'email' => 'guru.kb3@demo.test', 'name' => 'Ustadzah Rini, S.Pd.',` |
| `'email' => 'guru.tkit1@permatakraksaan.sch.id', 'name' => 'Ustadzah Dewi, S.Pd.I.',` | `'email' => 'guru.tk1@demo.test', 'name' => 'Ustadzah Dewi, S.Pd.I.',` |
| `'email' => 'guru.tkit2@permatakraksaan.sch.id', 'name' => 'Ustadzah Latifah, S.Pd.',` | `'email' => 'guru.tk2@demo.test', 'name' => 'Ustadzah Latifah, S.Pd.',` |
| `'email' => 'guru.tkit3@permatakraksaan.sch.id', 'name' => 'Ustadzah Amel, S.Psi.',` | `'email' => 'guru.tk3@demo.test', 'name' => 'Ustadzah Amel, S.Psi.',` |

(Method `seedGuru()` sendiri memakai `User::where('email', $data['email'])->firstOrFail()` — kalau email di sini TIDAK SAMA PERSIS dengan yang di `UserSeeder.php` Step 2, `migrate:fresh --seed` akan gagal dengan `ModelNotFoundException`. Jalankan verifikasi Step 8 di bawah untuk memastikan.)

- [ ] **Step 4: `SiswaSeeder.php` — email akun demo siswa**

Cari string:

```php
        $emailMap = [
            '20223311' => 'siswa.kbit@permatakraksaan.sch.id',
            '20223322' => 'siswa.tkit@permatakraksaan.sch.id',
            '20223333' => 'siswa.sdit@permatakraksaan.sch.id',
            '20223344' => 'siswa.smpit@permatakraksaan.sch.id',
        ];

        $email = $emailMap[$lembaga->npsn] ?? "siswa.{$lembaga->id}@permatakraksaan.sch.id";
```

Ganti jadi:

```php
        $emailMap = [
            '20223311' => 'siswa.kb@demo.test',
            '20223322' => 'siswa.tk@demo.test',
            '20223333' => 'siswa.sd@demo.test',
            '20223344' => 'siswa.smp@demo.test',
        ];

        $email = $emailMap[$lembaga->npsn] ?? "siswa.{$lembaga->id}@demo.test";
```

- [ ] **Step 5: `OrangTuaKaryawanSeeder.php` — email psikolog pool & orang tua demo**

Cari:

```php
            'email'                => 'psikolog.pool@permatakraksaan.sch.id',
```

Ganti jadi:

```php
            'email'                => 'psikolog.pool@demo.test',
```

Cari (muncul 2× — baris `'email' => 'ortu.demo@permatakraksaan.sch.id',` di `$user = User::create([...])` DAN di `$orangTua = OrangTua::create([...])`):

```php
            'email'                => 'ortu.demo@permatakraksaan.sch.id',
```

dan

```php
            'email'        => 'ortu.demo@permatakraksaan.sch.id',
```

Ganti KEDUA kemunculan jadi:

```php
            'email'                => 'ortu@demo.test',
```

dan

```php
            'email'        => 'ortu@demo.test',
```

(perhatikan spasi sebelum `=>` beda antara 2 kemunculan — pertahankan spasi aslinya, cuma value-nya yang berubah)

Cari (2× untuk `ortu.kb.demo`):

```php
            'email'                => 'ortu.kb.demo@permatakraksaan.sch.id',
```

```php
            'email'        => 'ortu.kb.demo@permatakraksaan.sch.id',
```

Ganti jadi:

```php
            'email'                => 'ortu.kb@demo.test',
```

```php
            'email'        => 'ortu.kb@demo.test',
```

Cari (2× untuk `ortu.tk.demo`):

```php
            'email'                => 'ortu.tk.demo@permatakraksaan.sch.id',
```

```php
            'email'        => 'ortu.tk.demo@permatakraksaan.sch.id',
```

Ganti jadi:

```php
            'email'                => 'ortu.tk@demo.test',
```

```php
            'email'        => 'ortu.tk@demo.test',
```

(Email `ortu.lintaslembaga@gmail.com` di fungsi `seedOrangTuaLintasLembaga()` **TIDAK diubah** — itu sengaja memakai domain publik `gmail.com` untuk mensimulasikan akun asli, di luar pola `@sistem.test`/`@permatakraksaan.sch.id` yang jadi cakupan rebranding.)

- [ ] **Step 6: `SarprasPengadaanDemoSeeder.php` — email 4 akun**

Cari (muncul di 2 tempat berbeda: baris `$superAdmin = User::firstOrCreate(['email' => 'superadmin@sistem.test'], ...)`):

```php
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@sistem.test'],
```

Ganti jadi:

```php
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@demo.test'],
```

Cari:

```php
        $bendaharaYayasan = User::firstOrCreate(
            ['email' => 'bendahara.yayasan@sistem.test'],
```

Ganti jadi:

```php
        $bendaharaYayasan = User::firstOrCreate(
            ['email' => 'keuangan.yayasan@demo.test'],
```

Cari:

```php
        $kepsek = User::firstOrCreate(
            ['email' => 'kepsek@sistem.test'],
```

Ganti jadi:

```php
        $kepsek = User::firstOrCreate(
            ['email' => 'kepsek.kb@demo.test'],
```

Cari:

```php
        $adm = User::firstOrCreate(
            ['email' => 'adm@sistem.test'],
```

Ganti jadi:

```php
        $adm = User::firstOrCreate(
            ['email' => 'adm.kb@demo.test'],
```

(Catatan: `$lembaga` yang dipakai di sini adalah `npsn '20223344'` = SMP, tapi email `kepsek.kb@demo.test`/`adm.kb@demo.test` sengaja tetap ikut pola akun REPRESENTATIF `kb` yang sama dengan `EssentialUserSeeder.php` Step 1 — karena email ini me-MATCH-kan ke akun yang SAMA yang sudah dibuat `EssentialUserSeeder`/`UserSeeder`, bukan membuat akun baru untuk SMP. Kalau bingung: `firstOrCreate` di sini akan membuat baris BARU dengan email `kepsek.kb@demo.test` HANYA JIKA belum ada — karena `EssentialUserSeeder` sudah membuat email yang sama lebih dulu di urutan `DatabaseSeeder`, baris ini akan MENEMUKAN akun yang sudah ada, bukan membuat baru. Ini SAMA seperti perilaku lama dengan `kepsek@sistem.test`/`adm@sistem.test`, cuma emailnya diganti.)

- [ ] **Step 7: `PendampinganSeeder.php` — query `$adminUser`**

Cari:

```php
        $adminUser  = User::where('email', 'adm.smpit@permatakraksaan.sch.id')->first();
```

Ganti jadi:

```php
        $adminUser  = User::where('email', 'adm.smp@demo.test')->first();
```

- [ ] **Step 8: Verifikasi manual — seluruh rantai email sinkron**

```bash
php artisan migrate:fresh --seed
```

Expected: selesai TANPA error (`ModelNotFoundException` di `GuruSeeder`/`PendampinganSeeder` berarti ada email yang tidak sinkron antar file — cek ulang Step 2-7 di atas).

```bash
php artisan tinker --execute="echo App\Models\User::whereIn('email', ['superadmin@demo.test','adm.yayasan@demo.test','kepsek.kb@demo.test','adm.kb@demo.test','keuangan.kb@demo.test','kurikulum.kb@demo.test','guru.kb1@demo.test','kepsek.smp@demo.test','adm.smp@demo.test'])->count();"
```

Expected: `9` (semua akun ketemu dengan email baru).

```bash
grep -rn "permatakraksaan.sch.id\|sistem.test" database/seeders/EssentialUserSeeder.php database/seeders/UserSeeder.php database/seeders/GuruSeeder.php database/seeders/SiswaSeeder.php database/seeders/OrangTuaKaryawanSeeder.php database/seeders/SarprasPengadaanDemoSeeder.php database/seeders/PendampinganSeeder.php
```

Expected: TIDAK ADA output (semua kemunculan sudah diganti). Kalau ada sisa, itu file domain institusi (`permata.sch.id` guru SDIT/SMPIT, `gmail.com` lintas lembaga) yang MEMANG tidak termasuk cakupan — pastikan sisa yang muncul cuma itu, bukan `permatakraksaan.sch.id`/`sistem.test` yang lolos.

- [ ] **Step 9: Jalankan scoped test**

```bash
php artisan test --filter=EssentialUser
php artisan test --filter=User
php artisan test --filter=Guru
php artisan test --filter=Siswa
```

Expected: PASS. Update assertion test yang meng-hardcode email lama.

- [ ] **Step 10: Commit**

```bash
git add database/seeders/EssentialUserSeeder.php database/seeders/UserSeeder.php database/seeders/GuruSeeder.php database/seeders/SiswaSeeder.php database/seeders/OrangTuaKaryawanSeeder.php database/seeders/SarprasPengadaanDemoSeeder.php database/seeders/PendampinganSeeder.php
git commit -m "feat(seeder): pendekkan email staff/RBAC ke pola role.kode@demo.test"
```

---

### Task 3: Email `KeuanganDemoSeeder` — Sinkronkan NIK-Map dengan Task 2

**Files:**
- Modify: `database/seeders/KeuanganDemoSeeder.php`

**Interfaces:**
- Consumes: akun `ortu@demo.test`/`ortu.kb@demo.test`/`ortu.tk@demo.test` dari Task 2 Step 5 — TAPI lookup fungsionalnya memakai `username` (NIK), bukan email, jadi task ini murni kosmetik (teks di array `$demoParents` dan pesan warning).

- [ ] **Step 1: Update array `$demoParents`**

Cari string:

```php
        $demoParents = [
            ['nik' => '3273019901850001', 'email' => 'ortu.demo@permatakraksaan.sch.id'],
            ['nik' => '3273019901850002', 'email' => 'ortu.kb.demo@permatakraksaan.sch.id'],
            ['nik' => '3273019901850003', 'email' => 'ortu.tk.demo@permatakraksaan.sch.id'],
        ];
```

Ganti jadi (NIK TIDAK berubah di task ini — itu urusan Task 5 Spec 1 kalau plan itu dieksekusi terpisah; task ini HANYA email):

```php
        $demoParents = [
            ['nik' => '3273019901850001', 'email' => 'ortu@demo.test'],
            ['nik' => '3273019901850002', 'email' => 'ortu.kb@demo.test'],
            ['nik' => '3273019901850003', 'email' => 'ortu.tk@demo.test'],
        ];
```

**Kalau Spec 1 SUDAH dieksekusi lebih dulu**, NIK di sini mungkin sudah berubah jadi `'0000019901850001'` dst (bukan `'3273...'`) — kalau begitu, cari `'0000019901850001'`/`'0000019901850002'`/`'0000019901850003'` sebagai gantinya, dan HANYA ganti bagian `'email' => ...`, biarkan NIK apa adanya.

- [ ] **Step 2: Verifikasi manual**

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=KeuanganDemoSeeder
```

Expected: TIDAK ADA baris warning `"Lewati: user demo ... tidak ditemukan"` untuk ketiga akun (kalau muncul, berarti NIK di array ini tidak cocok dengan NIK yang dipakai `OrangTuaKaryawanSeeder.php` — cek ulang, JANGAN ubah NIK di file ini tanpa mengubah `OrangTuaKaryawanSeeder.php` juga, keduanya harus selalu sinkron).

- [ ] **Step 3: Jalankan scoped test**

```bash
php artisan test --filter=KeuanganDemo
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add database/seeders/KeuanganDemoSeeder.php
git commit -m "chore(seeder): sinkronkan email demo orang tua di KeuanganDemoSeeder dengan pola @demo.test"
```

---

### Task 4: Email PPDB — Akun Pendaftar & Pola `wali.*` (9 file)

**Files:**
- Modify: `database/seeders/AkunPendaftarSeeder.php`
- Modify: `database/seeders/PendaftaranSeeder.php`
- Modify: `database/seeders/PembayaranSeeder.php`
- Modify: `database/seeders/SkPpdbSeeder.php`
- Modify: `database/seeders/TagihanSeeder.php`
- Modify: `database/seeders/SkemaCicilanSeeder.php`
- Modify: `database/seeders/CicilanSeeder.php`
- Modify: `database/seeders/DokumenPendaftaranSeeder.php`
- Modify: `database/seeders/HasilSeleksiSeeder.php`

**Interfaces:**
- Produces: `wali.menunggu@demo.test`, `wali.diterima@demo.test`, `wali.ditolak@demo.test`, `wali.cicilan@demo.test` (dipakai SEMUA 8 file lookup di bawah — HARUS diganti BERSAMAAN dalam task ini, karena semuanya string literal yang harus sama persis).

Untuk SETIAP dari 4 nilai berikut, cari SEMUA kemunculannya di 8 file (`PendaftaranSeeder.php` mendefinisikan, 7 file lain mencari) dan ganti:

| Cari (literal string) | Ganti dengan |
|---|---|
| `'wali.menunggu@example.test'` | `'wali.menunggu@demo.test'` |
| `'wali.diterima@example.test'` | `'wali.diterima@demo.test'` |
| `'wali.ditolak@example.test'` | `'wali.ditolak@demo.test'` |
| `'wali.cicilan-demo@example.test'` | `'wali.cicilan@demo.test'` |

- [ ] **Step 1: `PendaftaranSeeder.php`**

4 kemunculan (parameter `$email` di pemanggilan `$this->seedPendaftaran(...)`, baris ~43, 48, 57, 66) — ganti keempatnya sesuai tabel di atas. Contoh baris ke-1, cari:

```php
            $this->seedPendaftaran($lembaga, $tahunAjaranAktif, $jalur, $gelombang, 'Calon Menunggu Verifikasi', 'wali.menunggu@example.test', [
```

Ganti jadi:

```php
            $this->seedPendaftaran($lembaga, $tahunAjaranAktif, $jalur, $gelombang, 'Calon Menunggu Verifikasi', 'wali.menunggu@demo.test', [
```

Lakukan pola yang sama untuk 3 baris `seedPendaftaran(...)` lainnya (`'Calon Diterima', 'wali.diterima@example.test'`, `'Calon Ditolak', 'wali.ditolak@example.test'`, `'Calon Cicilan Demo', 'wali.cicilan-demo@example.test'`) sesuai tabel mapping.

- [ ] **Step 2: `PembayaranSeeder.php`**

2 kemunculan:

```php
            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
            $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();
```

Ganti jadi:

```php
            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
            $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan@demo.test')->first();
```

- [ ] **Step 3: `SkPpdbSeeder.php`**

2 kemunculan:

```php
            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
            $ditolak = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.ditolak@example.test')->first();
```

Ganti jadi:

```php
            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
            $ditolak = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.ditolak@demo.test')->first();
```

- [ ] **Step 4: `TagihanSeeder.php`**

2 kemunculan:

```php
            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
            $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();
```

Ganti jadi:

```php
            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
            $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan@demo.test')->first();
```

- [ ] **Step 5: `SkemaCicilanSeeder.php`**

1 kemunculan:

```php
            $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();
```

Ganti jadi:

```php
            $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan@demo.test')->first();
```

- [ ] **Step 6: `CicilanSeeder.php`**

1 kemunculan (identik dengan Step 5):

```php
            $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();
```

Ganti jadi:

```php
            $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan@demo.test')->first();
```

- [ ] **Step 7: `DokumenPendaftaranSeeder.php`**

2 kemunculan:

```php
            ->where('email_pendaftaran', 'wali.menunggu@example.test')
```

```php
            ->where('email_pendaftaran', 'wali.diterima@example.test')
```

Ganti jadi:

```php
            ->where('email_pendaftaran', 'wali.menunggu@demo.test')
```

```php
            ->where('email_pendaftaran', 'wali.diterima@demo.test')
```

- [ ] **Step 8: `HasilSeleksiSeeder.php`**

1 baris dengan 2 argumen:

```php
            $this->seedHasil($lembaga, $seleksiList, 'wali.diterima@example.test', 75, 95, $staf);
            $this->seedHasil($lembaga, $seleksiList, 'wali.ditolak@example.test', 30, 55, $staf);
```

Ganti jadi:

```php
            $this->seedHasil($lembaga, $seleksiList, 'wali.diterima@demo.test', 75, 95, $staf);
            $this->seedHasil($lembaga, $seleksiList, 'wali.ditolak@demo.test', 30, 55, $staf);
```

- [ ] **Step 9: `AkunPendaftarSeeder.php`**

Cari:

```php
        $emailPerNpsn = [
            '20223311' => ['email' => 'pendaftar.kbit@example.test', 'nama' => 'Wali KBIT (Contoh)'],
            '20223322' => ['email' => 'pendaftar.tkit@example.test', 'nama' => 'Wali TKIT (Contoh)'],
            '20223333' => ['email' => 'pendaftar.sdit@example.test', 'nama' => 'Wali SDIT (Contoh)'],
            '20223344' => ['email' => 'pendaftar.smpit@example.test', 'nama' => 'Wali SMPIT (Contoh)'],
        ];
```

Ganti jadi:

```php
        $emailPerNpsn = [
            '20223311' => ['email' => 'pendaftar.kb@demo.test', 'nama' => 'Wali KB (Contoh)'],
            '20223322' => ['email' => 'pendaftar.tk@demo.test', 'nama' => 'Wali TK (Contoh)'],
            '20223333' => ['email' => 'pendaftar.sd@demo.test', 'nama' => 'Wali SD (Contoh)'],
            '20223344' => ['email' => 'pendaftar.smp@demo.test', 'nama' => 'Wali SMP (Contoh)'],
        ];
```

Cari:

```php
            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
```

Ganti jadi:

```php
            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
```

- [ ] **Step 10: Verifikasi manual**

```bash
php artisan migrate:fresh --seed
```

Expected: selesai tanpa error.

```bash
grep -rln "example.test" database/seeders/
```

Expected: TIDAK ADA output (semua kemunculan `example.test` sudah diganti ke `demo.test` di seluruh direktori seeder).

```bash
php artisan tinker --execute="echo App\Models\Pendaftaran::where('email_pendaftaran', 'wali.cicilan@demo.test')->count();"
```

Expected: angka LEBIH BESAR dari 0 (data cicilan demo tetap kebentuk dengan email baru).

- [ ] **Step 11: Jalankan scoped test**

```bash
php artisan test --filter=Pendaftaran
php artisan test --filter=Pembayaran
php artisan test --filter=Tagihan
php artisan test --filter=Cicilan
php artisan test --filter=AkunPendaftar
```

Expected: PASS. Update test yang meng-hardcode `wali.*@example.test`/`pendaftar.*@example.test`.

- [ ] **Step 12: Commit**

```bash
git add database/seeders/AkunPendaftarSeeder.php database/seeders/PendaftaranSeeder.php database/seeders/PembayaranSeeder.php database/seeders/SkPpdbSeeder.php database/seeders/TagihanSeeder.php database/seeders/SkemaCicilanSeeder.php database/seeders/CicilanSeeder.php database/seeders/DokumenPendaftaranSeeder.php database/seeders/HasilSeleksiSeeder.php
git commit -m "feat(seeder): pendekkan email PPDB wali/pendaftar ke pola @demo.test"
```

---

### Task 5: Nama Manusia Netral

**Files:**
- Modify: `database/seeders/UserSeeder.php`
- Modify: `database/seeders/GuruSeeder.php`
- Modify: `database/seeders/LembagaSeeder.php`
- Modify: `database/seeders/SarprasPengadaanDemoSeeder.php`

**Interfaces:**
- Consumes: tidak bergantung pada Task 1-4 secara fungsional (kolom `name`/`nama`, terpisah dari kolom `email` yang diubah task-task sebelumnya) — boleh dikerjakan kapan saja relatif terhadap task lain di plan ini.

- [ ] **Step 1: `UserSeeder.php` — hapus honorifik**

Ganti SETIAP baris berikut (cari persis, ganti persis — hanya bagian `'name' => ...` yang berubah, bagian `'email'` sudah berbentuk baru dari Task 2 kalau Task 2 sudah dikerjakan, atau masih lama kalau belum; JANGAN sentuh bagian email di step ini):

| Cari (fragmen `'name' => ...`) | Ganti dengan |
|---|---|
| `'name' => 'Ustadzah Aisyah, S.Psi.',` | `'name' => 'Aisyah, S.Psi.',` |
| `'name' => 'Ustadzah Nurul, S.Pd.',` | `'name' => 'Nurul, S.Pd.',` |
| `'name' => 'Ustadzah Halimah, S.E.',` | `'name' => 'Halimah, S.E.',` |
| `'name' => 'Ustadzah Fatimah, S.Psi.',` | `'name' => 'Fatimah, S.Psi.',` |
| `'name' => 'Ustadzah Zahra, S.Pd.',` | `'name' => 'Zahra, S.Pd.',` |
| `'name' => 'Ustadzah Rini, S.Pd.',` | `'name' => 'Rini, S.Pd.',` |
| `'name' => 'Ustadzah Maryam, S.Pd.I.',` | `'name' => 'Maryam, S.Pd.I.',` |
| `'name' => 'Ustadzah Indria, S.Pd.',` | `'name' => 'Indria, S.Pd.',` |
| `'name' => 'Ustadzah Khadijah, S.E.',` | `'name' => 'Khadijah, S.E.',` |
| `'name' => 'Ustadzah Dewi, S.Pd.I.',` | `'name' => 'Dewi, S.Pd.I.',` |
| `'name' => 'Ustadzah Latifah, S.Pd.',` | `'name' => 'Latifah, S.Pd.',` |
| `'name' => 'Ustadzah Amel, S.Psi.',` | `'name' => 'Amel, S.Psi.',` |
| `'name' => 'Ustadz Abdullah, M.Pd.',` | `'name' => 'Abdullah, M.Pd.',` |
| `'name' => 'Ustadz Lukman, S.Kom.',` | `'name' => 'Lukman, S.Kom.',` |
| `'name' => 'Ustadz Hasan, S.E.',` | `'name' => 'Hasan, S.E.',` |
| `'name' => 'Ustadz Bambang Suryadi, M.Pd.',` | `'name' => 'Bambang Suryadi, M.Pd.',` |

(`'Dewi Lestari, S.Pd.'` dan `'Nur Aisyah, S.Pd.'` sudah netral — TIDAK diubah.)

- [ ] **Step 2: `GuruSeeder.php` — hapus honorifik (6 baris, subset dari Step 1)**

| Cari | Ganti dengan |
|---|---|
| `'name' => 'Ustadzah Fatimah, S.Psi.',` | `'name' => 'Fatimah, S.Psi.',` |
| `'name' => 'Ustadzah Zahra, S.Pd.',` | `'name' => 'Zahra, S.Pd.',` |
| `'name' => 'Ustadzah Rini, S.Pd.',` | `'name' => 'Rini, S.Pd.',` |
| `'name' => 'Ustadzah Dewi, S.Pd.I.',` | `'name' => 'Dewi, S.Pd.I.',` |
| `'name' => 'Ustadzah Latifah, S.Pd.',` | `'name' => 'Latifah, S.Pd.',` |
| `'name' => 'Ustadzah Amel, S.Psi.',` | `'name' => 'Amel, S.Psi.',` |

(HARUS identik dengan Step 1 — `GuruSeeder` query `User::where('email', ...)->firstOrFail()` untuk MEMAKAI akun yang sama, nama `Guru.nama` diisi ulang dari `$data['name']` di file ini secara independen, jadi kalau tidak sinkron dengan `UserSeeder.php`, `User.name` dan `Guru.nama` untuk orang yang sama akan beda — bukan error fatal, tapi data tidak konsisten.)

- [ ] **Step 3: `LembagaSeeder.php` — `nama_kepala_sekolah` & `nama_bendahara_bosp`**

Untuk MASING-MASING dari 4 blok `Lembaga::firstOrCreate(...)`, ganti:

**Blok KB:**
```php
                'nama_kepala_sekolah' => 'Ustadzah Aisyah, S.Psi.',
                'nama_bendahara_bosp' => 'Ustadzah Halimah, S.E.',
```
menjadi:
```php
                'nama_kepala_sekolah' => 'Aisyah, S.Psi.',
                'nama_bendahara_bosp' => 'Halimah, S.E.',
```

**Blok TK:**
```php
                'nama_kepala_sekolah' => 'Ustadzah Maryam, S.Pd.I.',
                'nama_bendahara_bosp' => 'Ustadzah Khadijah, S.E.',
```
menjadi:
```php
                'nama_kepala_sekolah' => 'Maryam, S.Pd.I.',
                'nama_bendahara_bosp' => 'Khadijah, S.E.',
```

**Blok SD:**
```php
                'nama_kepala_sekolah' => 'Ustadz Abdullah, M.Pd.',
                'nama_bendahara_bosp' => 'Ustadz Hasan, S.E.',
```
menjadi:
```php
                'nama_kepala_sekolah' => 'Abdullah, M.Pd.',
                'nama_bendahara_bosp' => 'Hasan, S.E.',
```

**Blok SMP:**
```php
                'nama_kepala_sekolah' => 'Ustadz Bambang Suryadi, M.Pd.',
                'nama_bendahara_bosp' => 'Ustadz Fajar Ramadhan, S.E.',
```
menjadi:
```php
                'nama_kepala_sekolah' => 'Bambang Suryadi, M.Pd.',
                'nama_bendahara_bosp' => 'Fajar Ramadhan, S.E.',
```

(Nilai `nama_kepala_sekolah` di sini HARUS identik persis dengan `'name'` akun kepala sekolah di `UserSeeder.php` Step 1 untuk lembaga yang sama — ini sinkronisasi yang SUDAH ADA di kode asli, bukan aturan baru, dipertahankan di sini.)

- [ ] **Step 4: `SarprasPengadaanDemoSeeder.php` — nama Bendahara Yayasan**

Cari:

```php
        $bendaharaYayasan = User::firstOrCreate(
            ['email' => 'keuangan.yayasan@demo.test'],
            ['name' => 'Ustadz Farid (Bendahara Yayasan)', 'password' => 'password', 'is_active' => true]
        );
```

(Email di atas adalah HASIL Task 2 Step 6. **Kalau Task 2 belum dikerjakan**, email di baris yang kamu temukan masih `'bendahara.yayasan@sistem.test'` — cari dengan email versi itu sebagai gantinya, HANYA ganti bagian `'name' => ...`.)

Ganti bagian `'name' => ...` jadi:

```php
            ['name' => 'Farid', 'password' => 'password', 'is_active' => true]
```

**Kalau Task 4 dari plan Spec 1 (`.agents/plans/2026-08-21-audit-perbaikan-seeder.md`) SUDAH dieksekusi lebih dulu**, baris ini kemungkinan sudah tidak berbentuk `User::firstOrCreate(['email' => ...], ['name' => 'Ustadz Farid (Bendahara Yayasan)', ...])` lagi — Spec 1 mungkin sudah mengganti nama ini jadi `'Farid'` juga (opsional di plan itu). Kalau string lama `'Ustadz Farid (Bendahara Yayasan)'` TIDAK DITEMUKAN, berarti sudah diganti lebih dulu — CATAT di laporan task dan lewati step ini.

- [ ] **Step 5: Verifikasi manual**

```bash
php artisan migrate:fresh --seed
```

Expected: selesai tanpa error.

```bash
grep -rn "Ustadz" database/seeders/
```

Expected: TIDAK ADA output (semua honorifik sudah dihapus dari seluruh direktori seeder).

```bash
php artisan tinker --execute="echo App\Models\Lembaga::where('npsn', '20223311')->value('nama_kepala_sekolah') === App\Models\User::where('email', 'kepsek.kb@demo.test')->value('name') ? 'sinkron (benar)' : 'TIDAK SINKRON (salah)';"
```

Expected: `sinkron (benar)`.

- [ ] **Step 6: Jalankan scoped test**

```bash
php artisan test --filter=User
php artisan test --filter=Guru
php artisan test --filter=Lembaga
```

Expected: PASS. Update test yang meng-hardcode nama lama (`'Ustadzah ...'`, dst).

- [ ] **Step 7: Commit**

```bash
git add database/seeders/UserSeeder.php database/seeders/GuruSeeder.php database/seeders/LembagaSeeder.php database/seeders/SarprasPengadaanDemoSeeder.php
git commit -m "feat(seeder): ganti nama staf dari honorifik keagamaan ke pola netral"
```

---

### Task 6: Verifikasi Akhir + Full Suite

**Files:** (tidak ada file baru — task ini murni verifikasi)

**Interfaces:**
- Consumes: seluruh hasil Task 1-5.

- [ ] **Step 1: Login manual/otomatis — akun representatif**

```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="
\$emails = ['superadmin@demo.test', 'adm.yayasan@demo.test', 'kepsek.kb@demo.test', 'guru.kb1@demo.test', 'siswa.kb@demo.test'];
foreach (\$emails as \$e) {
    \$u = App\Models\User::where('email', \$e)->first();
    echo \$e . ': ' . (\$u && \Illuminate\Support\Facades\Hash::check('password', \$u->password) ? 'OK' : 'GAGAL') . PHP_EOL;
}
"
```

Expected: semua baris `OK`.

- [ ] **Step 2: Grep menyeluruh — pastikan tidak ada sisa pola lama**

```bash
grep -rln "permatakraksaan.sch.id\|sistem\.test\|example\.test\|Ustadz\|PERMATA KRAKSAAN" database/seeders/
```

Expected: TIDAK ADA output SAMA SEKALI. Kalau ada, itu berarti ada file/baris yang terlewat di Task 1-5 — kembali ke task yang relevan dan perbaiki sebelum lanjut.

- [ ] **Step 3: Minta izin user untuk full suite**

**JANGAN jalankan langsung.** Tanyakan ke user secara eksplisit: *"Task 1-6 selesai, semua scoped test PASS, grep menyeluruh bersih. Boleh saya jalankan full test suite (`php artisan test`) sekarang sebagai verifikasi akhir?"* Tunggu jawaban user sebelum lanjut ke Step 4.

- [ ] **Step 4: Full suite (hanya setelah izin didapat)**

```bash
php artisan test
```

Expected: SEMUA test PASS, 0 failed, 0 error. Kalau ada FAIL, kemungkinan besar test lama yang meng-assert email/nama/kode lembaga lama secara hardcode — perbaiki test tersebut (BUKAN kode aplikasi), sesuai nilai baru dari task terkait.

- [ ] **Step 5: Laporkan hasil ke user**

Setelah full suite PASS, laporkan ringkas: jumlah task selesai, jumlah commit, hasil full suite (jumlah test/assertion). Kalau plan Spec 1 (`.agents/plans/2026-08-21-audit-perbaikan-seeder.md`) belum dieksekusi, ingatkan user bahwa itu masih tersedia untuk dikerjakan terpisah.
