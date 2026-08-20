# Perbaikan Bug Seeder + Konvensi Seeder Pintera — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perbaiki 4 Critical + 4 High + 3 Medium + 6 Low temuan audit seeder (branch `rbac-v2`), dan tambahkan dokumen konvensi permanen supaya seeder modul baru ke depan konsisten.

**Architecture:** Laravel 11 seeder classes di `database/seeders/*.php`, dipanggil dari `database/seeders/DatabaseSeeder.php::run()`. Tidak ada perubahan skema database kecuali 1 migration baru untuk memindahkan cleanup-logic legacy dari seeder. RBAC pakai package `spatie/laravel-permission` (`Spatie\Permission\Models\Permission`, `App\Models\Role extends Spatie\Permission\Models\Role`).

**Tech Stack:** PHP 8.2+, Laravel 11, Pest (test runner, `php artisan test` / `vendor/bin/pest`), MySQL (lokal via Laragon).

## Global Constraints

- Spec sumber: `.agents/specs/2026-08-21-audit-perbaikan-seeder.md` — baca file ini lebih dulu kalau ada keraguan, plan ini adalah turunannya.
- **Zero-behavior-change untuk data yang SUDAH benar** — hanya bagian yang eksplisit disebutkan di tiap task yang berubah.
- Scoped test per task (jalankan test yang relevan dengan file yang diubah task itu). **Full suite HANYA di Task 6 (task terakhir), dan HARUS eksplisit minta izin user dulu sebelum dijalankan** — jangan auto-run `php artisan test` tanpa argumen filter di task manapun sebelum itu.
- Setiap task yang menyentuh `DatabaseSeeder::run()` atau urutan pemanggilan seeder WAJIB diverifikasi dengan benar-benar menjalankan `php artisan migrate:fresh --seed` di database lokal (bukan cuma baca kode) dan mengecek hasilnya lewat `php artisan tinker` atau test — bukan asumsi dari membaca kode saja.
- Commit terpisah per task, pesan commit dalam Bahasa Indonesia mengikuti gaya commit history repo ini (`git log --oneline -10` untuk referensi gaya kalau perlu).
- **Executor plan ini KEMUNGKINAN adalah sesi/agent lain** yang tidak punya akses ke percakapan yang menulis spec/plan ini — setiap task di bawah berisi kode PHP lengkap, path file exact, dan command verifikasi nyata. Jangan berasumsi ada konteks tambahan di luar apa yang tertulis di sini dan di file spec.
- **Temuan tambahan saat plan ditulis (bukan dari spec, WAJIB ditangani di Task 2 dan Task 4):**
  1. Spec §4 menyebut 7 fungsi di `PendampinganSeeder.php` yang punya bug idempotency (key `firstOrCreate` memakai kolom `status`). Saat kode dibaca ulang untuk menulis plan ini, ditemukan **3 fungsi tambahan** dengan pola bug yang SAMA persis (`status` ikut jadi bagian key): `buatKasusDiajukan()`, `buatKasusRinganKb()`, `buatKasusRinganTk()`. Total jadi **10 fungsi**, bukan 7. Task 2 di bawah mencakup semua 10.
  2. `SarprasPengadaanDemoSeeder.php` (baris 41-45) memanggil `$this->callOnce([SarprasPermissionSeeder::class, PengadaanPermissionSeeder::class, WorkflowDefinitionSeeder::class]);` — setelah Task 1 menghapus 2 class pertama, baris ini akan fatal error (`Class not found`). Task 1 WAJIB mengupdate baris ini sebagai bagian dari task itu sendiri (bukan ditunda ke Task 4), supaya `migrate:fresh --seed` tidak pernah dalam keadaan rusak di antara task.
  3. `SarprasPengadaanDemoSeeder.php` juga membuat ULANG role `bendahara_yayasan`/`kepala_sekolah` via `Role::firstOrCreate()` + `givePermissionTo()` langsung (baris 70-116), dan `$superAdmin->givePermissionTo(Permission::all())` langsung ke user (baris 68) — ini melanggar prinsip "1-tabel-1-seeder untuk pivot `role_has_permissions`" yang baru ditegakkan Task 1. Ditangani di Task 4 (bagian dari konsolidasi §6.2 spec).

---

### Task 1: Restrukturisasi RBAC (Permission, Role, Pivot Assignment)

**Files:**
- Modify: `database/seeders/PermissionSeeder.php`
- Modify: `database/seeders/RoleSeeder.php`
- Modify: `database/seeders/SarprasPengadaanDemoSeeder.php:38-45` (baris `callOnce`)
- Create: `database/seeders/RolePermissionAssignmentSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Delete: `database/seeders/SarprasPermissionSeeder.php`
- Delete: `database/seeders/PengadaanPermissionSeeder.php`
- Test: (tidak ada test file baru di task ini — verifikasi lewat `migrate:fresh --seed` + tinker query, ditulis jadi automated test permanen di Task 6 bagian testing §8 spec, supaya tidak duplikasi kerja)

**Interfaces:**
- Produces: role `bendahara_yayasan` dan `admin_sarpras` sudah ada di tabel `roles` (dipakai Task 4, `SarprasPengadaanDemoSeeder.php`, dan referensi `WorkflowDefinitionSeeder.php` yang sudah ada). Role `yayasan_super_admin` punya SEMUA permission via pivot setelah full seed selesai.

- [ ] **Step 1: Modifikasi `PermissionSeeder.php` — gabung 21 permission Sarpras+Pengadaan**

Tambahkan 21 nama permission berikut ke array `$permissions` yang sudah ada di `database/seeders/PermissionSeeder.php` (letakkan sebagai blok baru sebelum baris `foreach ($permissions as $name)`, JANGAN hapus/ubah permission yang sudah ada):

```php
            // Sarpras (dipindah dari SarprasPermissionSeeder.php, dihapus — lihat Task 1 §3 spec)
            'sarpras.gedung.view', 'sarpras.gedung.manage',
            'sarpras.ruangan.view', 'sarpras.ruangan.manage',
            'sarpras.kategori.view', 'sarpras.kategori.manage',
            'sarpras.aset.view', 'sarpras.aset.manage',
            'sarpras.mutasi.create', 'sarpras.mutasi.view',
            'sarpras.kir.export',
            // Pengadaan (dipindah dari PengadaanPermissionSeeder.php, dihapus — lihat Task 1 §3 spec)
            'pengadaan.proposal.create', 'pengadaan.proposal.view', 'pengadaan.proposal.edit', 'pengadaan.proposal.delete',
            'pengadaan.approval.internal', 'pengadaan.approval.yayasan',
            'pengadaan.disbursement.manage',
            'pengadaan.lpj.submit', 'pengadaan.lpj.verify',
            'workflow.config.manage',
```

Sisipkan tepat sebelum baris terakhir array (`'rpp.view', 'rpp.kelola', 'rpp.verify',`) dan sebelum `];`. Jangan ubah blok `Permission::whereIn(...)->delete()` di baris 18-22 — itu ditangani terpisah di Task 5.

- [ ] **Step 2: Modifikasi `RoleSeeder.php` — tambah 2 role, hapus `syncPermissions()`**

Di `database/seeders/RoleSeeder.php`, ubah array `$roles` (baris 13-24) — tambahkan 2 baris baru sebelum `'karyawan_lembaga'`:

```php
        $roles = [
            'yayasan_super_admin' => ['scope_level' => 'yayasan', 'is_protected' => true],
            'kepala_sekolah' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'admin_administrasi' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'admin_keuangan' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'admin_akademik' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'guru' => ['scope_level' => 'diri_sendiri', 'is_protected' => false],
            'siswa' => ['scope_level' => 'diri_sendiri', 'is_protected' => false],
            'orang_tua' => ['scope_level' => 'diri_sendiri', 'is_protected' => false],
            'karyawan_pool' => ['scope_level' => 'yayasan', 'is_protected' => false],
            'karyawan_lembaga' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'bendahara_yayasan' => ['scope_level' => 'yayasan', 'is_protected' => false],
            'admin_sarpras' => ['scope_level' => 'yayasan', 'is_protected' => false],
        ];
```

Hapus blok berikut (baris 32-34 di file asli):

```php
            if ($name === 'yayasan_super_admin') {
                $role->syncPermissions(Permission::pluck('name')->all());
            }
```

Setelah dihapus, `use Spatie\Permission\Models\Permission;` di baris 7 jadi tidak dipakai lagi di file ini — hapus baris `use` itu juga (PHP akan tetap jalan kalau tidak dihapus, tapi biarkan bersih sesuai konvensi tanpa import mati).

- [ ] **Step 3: Buat `RolePermissionAssignmentSeeder.php`**

Buat file baru `database/seeders/RolePermissionAssignmentSeeder.php` dengan isi lengkap berikut:

```php
<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RolePermissionAssignmentSeeder extends Seeder
{
    /**
     * Satu-satunya pemilik pivot role_has_permissions. WAJIB jadi entri paling
     * akhir di DatabaseSeeder::run() — yayasan_super_admin di bawah ini
     * mengambil snapshot SEMUA permission yang ada di tabel permissions,
     * jadi harus dijamin seluruh seeder permission lain sudah selesai jalan.
     */
    public function run(): void
    {
        $sarprasPermissions = [
            'sarpras.gedung.view',
            'sarpras.gedung.manage',
            'sarpras.ruangan.view',
            'sarpras.ruangan.manage',
            'sarpras.kategori.view',
            'sarpras.kategori.manage',
            'sarpras.aset.view',
            'sarpras.aset.manage',
            'sarpras.mutasi.create',
            'sarpras.mutasi.view',
            'sarpras.kir.export',
        ];

        $pengadaanPermissions = [
            'pengadaan.proposal.create',
            'pengadaan.proposal.view',
            'pengadaan.proposal.edit',
            'pengadaan.proposal.delete',
            'pengadaan.approval.internal',
            'pengadaan.approval.yayasan',
            'pengadaan.disbursement.manage',
            'pengadaan.lpj.submit',
            'pengadaan.lpj.verify',
            'workflow.config.manage',
        ];

        // ── Sarpras: admin_sarpras & admin_administrasi dapat semua ──────────
        Role::firstOrCreate(['name' => 'admin_sarpras', 'guard_name' => 'web'])
            ->givePermissionTo($sarprasPermissions);
        Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'])
            ->givePermissionTo($sarprasPermissions);

        // ── Sarpras: kepala_sekolah cuma view ─────────────────────────────────
        Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'])
            ->givePermissionTo([
                'sarpras.gedung.view',
                'sarpras.ruangan.view',
                'sarpras.kategori.view',
                'sarpras.aset.view',
                'sarpras.mutasi.view',
            ]);

        // ── Sarpras: bendahara_yayasan cuma lihat aset ────────────────────────
        Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web'])
            ->givePermissionTo(['sarpras.aset.view']);

        // ── Pengadaan: admin_sarpras & admin_administrasi (proposal + lpj) ────
        Role::firstOrCreate(['name' => 'admin_sarpras', 'guard_name' => 'web'])
            ->givePermissionTo([
                'pengadaan.proposal.create',
                'pengadaan.proposal.view',
                'pengadaan.proposal.edit',
                'pengadaan.proposal.delete',
                'pengadaan.lpj.submit',
            ]);
        Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'])
            ->givePermissionTo([
                'pengadaan.proposal.create',
                'pengadaan.proposal.view',
                'pengadaan.proposal.edit',
                'pengadaan.proposal.delete',
                'pengadaan.lpj.submit',
            ]);

        // ── Pengadaan: kepala_sekolah (view + approval internal) ──────────────
        Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'])
            ->givePermissionTo([
                'pengadaan.proposal.view',
                'pengadaan.approval.internal',
            ]);

        // ── Pengadaan: bendahara_yayasan (approval yayasan + disbursement) ────
        Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web'])
            ->givePermissionTo([
                'pengadaan.proposal.view',
                'pengadaan.approval.yayasan',
                'pengadaan.disbursement.manage',
                'pengadaan.lpj.verify',
            ]);

        // ── Super admin: snapshot LENGKAP semua permission ─────────────────────
        // Aman diambil di sini karena entri ini dijamin paling akhir di
        // DatabaseSeeder::run() — seluruh permission dari seeder lain
        // (termasuk Sarpras/Pengadaan di atas) sudah pasti dibuat sebelum baris
        // ini jalan.
        Role::findByName('yayasan_super_admin')->syncPermissions(Permission::all());
    }
}
```

Catatan: nama role yang dulu salah ditulis `'super_admin'` di `SarprasPermissionSeeder.php`/`PengadaanPermissionSeeder.php` TIDAK dimasukkan lagi ke file baru ini — itu adalah bug (Critical Finding 1.2 di spec) dan sengaja tidak dipertahankan.

- [ ] **Step 4: Update `SarprasPengadaanDemoSeeder.php` — hapus referensi 2 class yang akan dihapus**

Di `database/seeders/SarprasPengadaanDemoSeeder.php`, ganti baris 41-45:

```php
        $this->callOnce([
            SarprasPermissionSeeder::class,
            PengadaanPermissionSeeder::class,
            WorkflowDefinitionSeeder::class,
        ]);
```

menjadi:

```php
        $this->callOnce([
            WorkflowDefinitionSeeder::class,
        ]);
```

(Permission & role Sarpras/Pengadaan sekarang dibuat oleh `PermissionSeeder`/`RoleSeeder`/`RolePermissionAssignmentSeeder` yang dipanggil `DatabaseSeeder` secara terpisah — file ini tidak perlu memanggilnya lagi.)

- [ ] **Step 5: Update `DatabaseSeeder.php`**

Di `database/seeders/DatabaseSeeder.php`, hapus 2 baris berikut dari array `$this->call([...])`:

```php
            SarprasPermissionSeeder::class,
            PengadaanPermissionSeeder::class,
```

(Baris `WorkflowDefinitionSeeder::class,` dan `SarprasPengadaanDemoSeeder::class,` yang tadinya mengikuti setelahnya TETAP ADA, tidak dihapus.)

Tambahkan `RolePermissionAssignmentSeeder::class,` sebagai baris PALING TERAKHIR sebelum penutup `]);` — setelah `SarprasPengadaanDemoSeeder::class,`. Hasil akhir urutan penutup array jadi:

```php
            OrangTuaKaryawanSeeder::class,
            PendampinganSeeder::class,
            KeuanganDemoSeeder::class,
            WorkflowDefinitionSeeder::class,
            SarprasPengadaanDemoSeeder::class,
            RolePermissionAssignmentSeeder::class,
        ]);
```

- [ ] **Step 6: Hapus 2 file lama**

```bash
git rm database/seeders/SarprasPermissionSeeder.php
git rm database/seeders/PengadaanPermissionSeeder.php
```

- [ ] **Step 7: Tambah akun demo `admin_sarpras` di `EssentialUserSeeder.php`**

Di `database/seeders/EssentialUserSeeder.php`, tambahkan 1 baris baru ke array `$akunLembagaScoped` (setelah baris `'guru@sistem.test' => [...]`):

```php
            'sarpras@sistem.test' => ['name' => 'Admin Sarpras (Contoh)', 'role' => 'admin_sarpras'],
```

(Pola email `sarpras@sistem.test` ini akan disesuaikan lagi oleh Spec 2/plan rebranding terpisah — di sini cukup memastikan akunnya ADA dan role-nya benar, sesuai §3.4 spec.)

- [ ] **Step 8: Verifikasi manual — jalankan `migrate:fresh --seed`**

```bash
php artisan migrate:fresh --seed
```

Expected: seluruh proses selesai TANPA error (`Class not found`, dsb). Kalau ada error di seeder yang masih mereferensikan `SarprasPermissionSeeder`/`PengadaanPermissionSeeder`, cari dengan:

```bash
grep -rn "SarprasPermissionSeeder\|PengadaanPermissionSeeder" database/ app/ tests/
```

Perbaiki setiap kemunculan yang tersisa (di luar 3 file yang sudah ditangani di atas) sebelum lanjut.

- [ ] **Step 9: Verifikasi manual — permission super admin lengkap**

```bash
php artisan tinker --execute="echo App\Models\Role::findByName('yayasan_super_admin')->permissions->count() . ' / ' . Spatie\Permission\Models\Permission::count();"
```

Expected: dua angka yang tampil SAMA (mis. `114 / 114`). Kalau berbeda, cek urutan `DatabaseSeeder::run()` — `RolePermissionAssignmentSeeder::class` harus benar-benar jadi entri terakhir.

- [ ] **Step 10: Verifikasi manual — role name tidak ada `super_admin` (bukan `yayasan_super_admin`)**

```bash
php artisan tinker --execute="echo App\Models\Role::where('name', 'super_admin')->exists() ? 'ADA (BUG)' : 'tidak ada (benar)';"
```

Expected: `tidak ada (benar)`.

- [ ] **Step 11: Jalankan scoped test RBAC**

```bash
php artisan test tests/Unit/PermissionConsistencyTest.php tests/Feature/Console/SyncPermissionsTest.php tests/Feature/RolePermissionSeederTest.php
```

Expected: semua PASS. Kalau ada FAIL yang menyebut nama permission/role spesifik yang berubah di task ini, baca isi test tersebut dan sesuaikan assertion-nya mengikuti struktur baru (permission Sarpras/Pengadaan sekarang berasal dari `PermissionSeeder`, bukan file terpisah).

- [ ] **Step 12: Commit**

```bash
git add database/seeders/PermissionSeeder.php database/seeders/RoleSeeder.php database/seeders/RolePermissionAssignmentSeeder.php database/seeders/SarprasPengadaanDemoSeeder.php database/seeders/DatabaseSeeder.php database/seeders/EssentialUserSeeder.php
git commit -m "fix(seeder): restrukturisasi RBAC jadi 1-tabel-1-seeder, perbaiki snapshot permission super admin"
```

---

### Task 2: Fix Idempotency `PendampinganSeeder.php` (10 fungsi)

**Files:**
- Modify: `database/seeders/PendampinganSeeder.php`

**Interfaces:**
- Consumes: tidak bergantung pada Task 1.

Ganti KEY `firstOrCreate` (parameter pertama) di 10 fungsi berikut. Untuk SETIAP fungsi, field `status` (dan, di 3 fungsi tambahan, field lain yang menyertainya) dipindah dari array pertama (key) ke array kedua (values) — nilai yang di-assign SAMA PERSIS, cuma posisinya pindah array.

- [ ] **Step 1: `buatKasusDiajukan()` (baris ~159-167)**

Sebelum:
```php
        Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'diajukan_oleh_guru_id' => $gurubk->id, 'status' => StatusKasus::Diajukan],
            [
                'lembaga_id'           => $smpit->id,
                'kategori_masalah'     => 'Perilaku',
                'deskripsi'            => 'Siswa sering tidak mengumpulkan tugas dan terlihat menarik diri dari pergaulan teman sebaya. Guru wali kelas sudah berkomunikasi tapi perlu penanganan lebih lanjut.',
                'tingkat_urgensi'      => 'sedang',
            ]
        );
```

Sesudah:
```php
        Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'diajukan_oleh_guru_id' => $gurubk->id, 'kategori_masalah' => 'Perilaku'],
            [
                'lembaga_id'           => $smpit->id,
                'status'               => StatusKasus::Diajukan,
                'deskripsi'            => 'Siswa sering tidak mengumpulkan tugas dan terlihat menarik diri dari pergaulan teman sebaya. Guru wali kelas sudah berkomunikasi tapi perlu penanganan lebih lanjut.',
                'tingkat_urgensi'      => 'sedang',
            ]
        );
```

- [ ] **Step 2: `buatKasusMenungguConsent()` (baris ~180-192)**

Sebelum:
```php
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'status' => StatusKasus::MenungguConsent],
            [
                'lembaga_id'              => $smpit->id,
                'diajukan_oleh_guru_id'   => $gurubk->id,
                'kategori_masalah'        => 'Sosial-Emosional',
                'deskripsi'               => 'Siswa menunjukkan tanda-tanda kecemasan berlebih saat ujian dan menghindari interaksi dengan guru.',
                'tingkat_urgensi'         => 'tinggi',
                'konselor_karyawan_id'    => $psikolog?->id,
                'konselor_guru_id'        => $psikolog ? null : $gurubk->id,
                'dikonfirmasi_pihak_lain_at' => null,
            ]
        );
```

Sesudah:
```php
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'kategori_masalah' => 'Sosial-Emosional'],
            [
                'lembaga_id'              => $smpit->id,
                'diajukan_oleh_guru_id'   => $gurubk->id,
                'status'                  => StatusKasus::MenungguConsent,
                'deskripsi'               => 'Siswa menunjukkan tanda-tanda kecemasan berlebih saat ujian dan menghindari interaksi dengan guru.',
                'tingkat_urgensi'         => 'tinggi',
                'konselor_karyawan_id'    => $psikolog?->id,
                'konselor_guru_id'        => $psikolog ? null : $gurubk->id,
                'dikonfirmasi_pihak_lain_at' => null,
            ]
        );
```

- [ ] **Step 3: `buatKasusDitugaskan()` (baris ~209-219)**

Sebelum:
```php
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'status' => StatusKasus::Ditugaskan],
            [
                'lembaga_id'            => $smpit->id,
                'diajukan_oleh_guru_id' => $gurubk->id,
                'kategori_masalah'      => 'Akademik',
                'deskripsi'             => 'Performa akademik menurun drastis dalam 2 bulan terakhir. Nilai rata-rata turun dari 80 ke 62. Perlu asesmen penyebab.',
                'tingkat_urgensi'       => 'rendah',
                'konselor_guru_id'      => $gurubk->id,
            ]
        );
```

Sesudah:
```php
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'kategori_masalah' => 'Akademik', 'tingkat_urgensi' => 'rendah'],
            [
                'lembaga_id'            => $smpit->id,
                'diajukan_oleh_guru_id' => $gurubk->id,
                'status'                => StatusKasus::Ditugaskan,
                'deskripsi'             => 'Performa akademik menurun drastis dalam 2 bulan terakhir. Nilai rata-rata turun dari 80 ke 62. Perlu asesmen penyebab.',
                'konselor_guru_id'      => $gurubk->id,
            ]
        );
```

Catatan: `kategori_masalah` saja ('Akademik') TIDAK cukup unik di fungsi ini karena `buatKasusSelesai()` (Step 6) juga memakai `'Akademik'` untuk siswa yang berbeda — tapi karena key SELALU menyertakan `siswa_id` juga, dan tiap fungsi dipanggil dengan `$siswa` yang berbeda (`$siswas[2]` vs `$siswas[5]`, lihat `run()`), kombinasi `siswa_id + kategori_masalah` tetap unik per baris. Ditambahkan `tingkat_urgensi` di sini murni sebagai lapisan aman tambahan, tidak wajib secara teknis.

- [ ] **Step 4: `buatKasusBerjalan()` (baris ~242-252)**

Sebelum:
```php
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'status' => StatusKasus::Berjalan],
            [
                'lembaga_id'            => $smpit->id,
                'diajukan_oleh_guru_id' => $gurubk->id,
                'kategori_masalah'      => 'Perilaku',
                'deskripsi'             => 'Siswa menunjukkan agresi verbal di kelas, sudah terjadi 3 insiden dalam sebulan.',
                'tingkat_urgensi'       => 'tinggi',
                'konselor_guru_id'      => $gurubk->id,
            ]
        );
```

Sesudah:
```php
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'kategori_masalah' => 'Perilaku', 'tingkat_urgensi' => 'tinggi'],
            [
                'lembaga_id'            => $smpit->id,
                'diajukan_oleh_guru_id' => $gurubk->id,
                'status'                => StatusKasus::Berjalan,
                'deskripsi'             => 'Siswa menunjukkan agresi verbal di kelas, sudah terjadi 3 insiden dalam sebulan.',
                'konselor_guru_id'      => $gurubk->id,
            ]
        );
```

Catatan: `buatKasusDiajukan()` (Step 1) juga memakai `kategori_masalah => 'Perilaku'`, tapi untuk `$siswa` berbeda (`$siswas[0]` vs `$siswas[3]`) DAN sudah punya `diajukan_oleh_guru_id` di key-nya sendiri yang unik dari sisi kombinasi siswa — jadi tidak bentrok. Ditambahkan `tingkat_urgensi => 'tinggi'` di sini sebagai pembeda ekstra.

- [ ] **Step 5: `buatKasusEskalasi()` (baris ~332-343)**

Sebelum:
```php
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'status' => StatusKasus::Eskalasi],
            [
                'lembaga_id'              => $smpit->id,
                'diajukan_oleh_guru_id'   => null,
                'diajukan_oleh_orang_tua_id' => $this->resolveOrangTuaKontakUtama($siswa)?->id,
                'kategori_masalah'        => 'Kesehatan Mental',
                'deskripsi'               => 'Orang tua melaporkan anak menolak makan dan tidak tidur selama beberapa hari. Ada indikasi depresi ringan.',
                'tingkat_urgensi'         => 'tinggi',
                'konselor_karyawan_id'    => $psikolog->id,
            ]
        );
```

Sesudah:
```php
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'kategori_masalah' => 'Kesehatan Mental'],
            [
                'lembaga_id'              => $smpit->id,
                'diajukan_oleh_guru_id'   => null,
                'diajukan_oleh_orang_tua_id' => $this->resolveOrangTuaKontakUtama($siswa)?->id,
                'status'                  => StatusKasus::Eskalasi,
                'deskripsi'               => 'Orang tua melaporkan anak menolak makan dan tidak tidur selama beberapa hari. Ada indikasi depresi ringan.',
                'tingkat_urgensi'         => 'tinggi',
                'konselor_karyawan_id'    => $psikolog->id,
            ]
        );
```

- [ ] **Step 6: `buatKasusSelesai()` (baris ~385-395)**

Sebelum:
```php
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'status' => StatusKasus::Selesai],
            [
                'lembaga_id'            => $smpit->id,
                'diajukan_oleh_guru_id' => $gurubk->id,
                'kategori_masalah'      => 'Akademik',
                'deskripsi'             => 'Siswa mengalami kesulitan konsentrasi dan sering absen. Sudah ditangani 6 sesi.',
                'tingkat_urgensi'       => 'sedang',
                'konselor_guru_id'      => $gurubk->id,
            ]
        );
```

Sesudah:
```php
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'kategori_masalah' => 'Akademik', 'tingkat_urgensi' => 'sedang'],
            [
                'lembaga_id'            => $smpit->id,
                'diajukan_oleh_guru_id' => $gurubk->id,
                'status'                => StatusKasus::Selesai,
                'deskripsi'             => 'Siswa mengalami kesulitan konsentrasi dan sering absen. Sudah ditangani 6 sesi.',
                'konselor_guru_id'      => $gurubk->id,
            ]
        );
```

- [ ] **Step 7: `buatKasusRinganSmp()` (baris ~460-469) — key SUDAH punya `kategori_masalah`, tinggal buang `status`**

Sebelum:
```php
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'status' => StatusKasus::Selesai, 'kategori_masalah' => 'Kepercayaan Diri'],
            [
                'lembaga_id'            => $smpit->id,
                'diajukan_oleh_guru_id' => $gurubk->id,
                'deskripsi'             => 'Siswa terlihat gugup dan menghindar setiap kali diminta presentasi di depan kelas, meski persiapannya sudah baik.',
                'tingkat_urgensi'       => 'rendah',
                'konselor_guru_id'      => $gurubk->id,
            ]
        );
```

Sesudah:
```php
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'kategori_masalah' => 'Kepercayaan Diri'],
            [
                'lembaga_id'            => $smpit->id,
                'diajukan_oleh_guru_id' => $gurubk->id,
                'status'                => StatusKasus::Selesai,
                'deskripsi'             => 'Siswa terlihat gugup dan menghindar setiap kali diminta presentasi di depan kelas, meski persiapannya sudah baik.',
                'tingkat_urgensi'       => 'rendah',
                'konselor_guru_id'      => $gurubk->id,
            ]
        );
```

- [ ] **Step 8: `buatKasusRinganKb()` (baris ~529-537)**

Sebelum:
```php
        Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'diajukan_oleh_guru_id' => $guruKb->id, 'status' => StatusKasus::Diajukan],
            [
                'lembaga_id'       => $kbit->id,
                'kategori_masalah' => 'Kebiasaan Makan',
                'deskripsi'        => 'Ananda selalu menolak sayur saat makan bersama di kelas dan hanya mau makan nasi putih. Mohon saran pendampingan sederhana untuk orang tua di rumah.',
                'tingkat_urgensi'  => 'rendah',
            ]
        );
```

Sesudah:
```php
        Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'diajukan_oleh_guru_id' => $guruKb->id, 'kategori_masalah' => 'Kebiasaan Makan'],
            [
                'lembaga_id'       => $kbit->id,
                'status'           => StatusKasus::Diajukan,
                'deskripsi'        => 'Ananda selalu menolak sayur saat makan bersama di kelas dan hanya mau makan nasi putih. Mohon saran pendampingan sederhana untuk orang tua di rumah.',
                'tingkat_urgensi'  => 'rendah',
            ]
        );
```

- [ ] **Step 9: `buatKasusRinganTk()` (baris ~549-559)**

Sebelum:
```php
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'diajukan_oleh_orang_tua_id' => $orangTuaTk->id, 'status' => StatusKasus::MenungguConsent],
            [
                'lembaga_id'           => $tkit->id,
                'diajukan_oleh_guru_id' => null,
                'kategori_masalah'     => 'Adaptasi Sekolah',
                'deskripsi'            => 'Ananda masih menangis dan sulit dilepas setiap kali diantar ke sekolah, sudah berlangsung 2 minggu sejak masuk TK.',
                'tingkat_urgensi'      => 'rendah',
                'konselor_karyawan_id' => $psikolog?->id,
            ]
        );
```

Sesudah:
```php
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'diajukan_oleh_orang_tua_id' => $orangTuaTk->id, 'kategori_masalah' => 'Adaptasi Sekolah'],
            [
                'lembaga_id'           => $tkit->id,
                'diajukan_oleh_guru_id' => null,
                'status'               => StatusKasus::MenungguConsent,
                'deskripsi'            => 'Ananda masih menangis dan sulit dilepas setiap kali diantar ke sekolah, sudah berlangsung 2 minggu sejak masuk TK.',
                'tingkat_urgensi'      => 'rendah',
                'konselor_karyawan_id' => $psikolog?->id,
            ]
        );
```

- [ ] **Step 10: `buatKasusRinganSd()` (baris ~573-582) — key SUDAH punya `kategori_masalah`, tinggal buang `status`**

Sebelum:
```php
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'status' => StatusKasus::Berjalan, 'kategori_masalah' => 'Konsentrasi Belajar'],
            [
                'lembaga_id'            => $sdit->id,
                'diajukan_oleh_guru_id' => $guruSd->id,
                'deskripsi'             => 'Ananda mudah teralihkan dan sulit duduk tenang selama 10-15 menit saat pelajaran berlangsung.',
                'tingkat_urgensi'       => 'rendah',
                'konselor_guru_id'      => $guruSd->id,
            ]
        );
```

Sesudah:
```php
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'kategori_masalah' => 'Konsentrasi Belajar'],
            [
                'lembaga_id'            => $sdit->id,
                'diajukan_oleh_guru_id' => $guruSd->id,
                'status'                => StatusKasus::Berjalan,
                'deskripsi'             => 'Ananda mudah teralihkan dan sulit duduk tenang selama 10-15 menit saat pelajaran berlangsung.',
                'tingkat_urgensi'       => 'rendah',
                'konselor_guru_id'      => $guruSd->id,
            ]
        );
```

- [ ] **Step 11: Verifikasi manual — double-seed tidak menduplikasi `Kasus`**

```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="echo App\Domains\Kasus\Models\Kasus::count();"
php artisan db:seed
php artisan tinker --execute="echo App\Domains\Kasus\Models\Kasus::count();"
```

Expected: kedua angka `Kasus::count()` SAMA PERSIS (mis. `10` lalu `10` lagi, bukan `10` lalu `20`).

- [ ] **Step 12: Jalankan scoped test Kasus**

```bash
php artisan test --filter=Kasus
```

Expected: semua PASS. Test yang relevan ada di `tests/Feature/Kasus/` dan sejenisnya — kalau ada test yang meng-assert jumlah `Kasus` row hasil `PendampinganSeeder` secara hardcode dan berubah karena perubahan key ini, sesuaikan assertion-nya (jumlah baris yang di-create TIDAK berubah, hanya key pencarian idempotency-nya).

- [ ] **Step 13: Commit**

```bash
git add database/seeders/PendampinganSeeder.php
git commit -m "fix(seeder): perbaiki idempotency PendampinganSeeder, key firstOrCreate tidak lagi pakai kolom status"
```

---

### Task 3: Environment Guard untuk Password Demo (6 file)

**Files:**
- Modify: `database/seeders/EssentialUserSeeder.php`
- Modify: `database/seeders/UserSeeder.php`
- Modify: `database/seeders/OrangTuaKaryawanSeeder.php`
- Modify: `database/seeders/SiswaSeeder.php`
- Modify: `database/seeders/SarprasPengadaanDemoSeeder.php`
- Modify: `database/seeders/AkunPendaftarSeeder.php`

**Interfaces:**
- Consumes: tidak bergantung pada Task 1/2 secara fungsional, tapi kalau dikerjakan setelah Task 1, `SarprasPengadaanDemoSeeder.php` sudah punya bentuk baru dari Task 1 Step 4 — guard ditambahkan di ATAS baris `$this->command?->info('Menyiapkan Permissions, Roles, dan Workflow...');` yang sudah ada.

Pola guard identik untuk SEMUA 6 file — tambahkan sebagai baris PERTAMA di dalam method `run(): void`, sebelum baris kode apapun yang sudah ada:

```php
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn(static::class.': dilewati, hanya boleh jalan di environment local/testing.');

            return;
        }

```

- [ ] **Step 1: Tambahkan guard ke `EssentialUserSeeder.php`**

Buka `database/seeders/EssentialUserSeeder.php`. Method `run(): void` saat ini dimulai:

```php
    public function run(): void
    {
        $superAdmin = User::firstOrCreate(
```

Sisipkan blok guard di antara `{` dan baris `$superAdmin = ...`.

- [ ] **Step 2: Tambahkan guard ke `UserSeeder.php`**

Method `run(): void` saat ini dimulai:

```php
    public function run(): void
    {
        $kbit = Lembaga::where('npsn', '20223311')->firstOrFail();
```

Sisipkan blok guard di antara `{` dan baris `$kbit = ...`.

- [ ] **Step 3: Tambahkan guard ke `OrangTuaKaryawanSeeder.php`**

Method `run(): void` saat ini dimulai:

```php
    public function run(): void
    {
        $kbit    = Lembaga::where('npsn', '20223311')->firstOrFail();
```

Sisipkan blok guard di antara `{` dan baris `$kbit    = ...`.

- [ ] **Step 4: Tambahkan guard ke `SiswaSeeder.php`**

Method `run(): void` saat ini dimulai:

```php
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
```

Sisipkan blok guard di antara `{` dan baris `foreach (...)`.

- [ ] **Step 5: Tambahkan guard ke `SarprasPengadaanDemoSeeder.php`**

Method `run(): void` (setelah Task 1 Step 4, kalau Task 1 sudah dikerjakan lebih dulu) dimulai:

```php
    public function run(): void
    {
        $this->command?->info('Menyiapkan Permissions, Roles, dan Workflow...');
```

Sisipkan blok guard di antara `{` dan baris `$this->command?->info(...)`.

- [ ] **Step 6: Tambahkan guard ke `AkunPendaftarSeeder.php`**

Method `run(): void` saat ini dimulai:

```php
    public function run(): void
    {
        $emailPerNpsn = [
```

Sisipkan blok guard di antara `{` dan baris `$emailPerNpsn = [`.

- [ ] **Step 7: Verifikasi manual — `migrate:fresh --seed` tetap normal di `local`**

```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="echo App\Models\User::where('email', 'superadmin@sistem.test')->exists() ? 'ADA (benar)' : 'TIDAK ADA (salah)';"
```

Expected: `ADA (benar)` — guard tidak mengganggu environment `local`.

- [ ] **Step 8: Verifikasi manual — seeder skip aman di `production`, tidak ada exception**

```bash
APP_ENV=production php artisan migrate:fresh --seed --force
```

Kalau shell tidak mendukung `VAR=value command` (mis. PowerShell), pakai:

```bash
php artisan migrate:fresh --seed --env=production --force
```

Expected: proses selesai TANPA exception fatal — hanya baris warning `"...: dilewati, hanya boleh jalan di environment local/testing."` untuk 6 seeder di atas, dan seeder lain yang bergantung pada datanya (`TagihanSeeder`, `SkemaCicilanSeeder`, `CicilanSeeder`, `PembayaranSeeder`, `KeuanganDemoSeeder`, dll.) ikut skip lewat pola `if (! $x) { continue/return; }` yang SUDAH ada di file-file itu (tidak perlu diubah). Kalau ternyata ADA exception fatal dari seeder downstream, catat nama file & baris errornya, laporkan sebagai temuan baru (JANGAN langsung menambah guard baru tanpa memahami dulu kenapa pola lookup-skip yang sudah ada tidak menutupinya).

Setelah verifikasi selesai, jalankan ulang di local supaya DB kembali ke state normal untuk task berikutnya:

```bash
php artisan migrate:fresh --seed
```

- [ ] **Step 9: Jalankan scoped test yang bergantung pada seeder ini**

```bash
php artisan test --filter=Essential
php artisan test --filter=OrangTua
```

Expected: PASS. Kalau ada test lain di luar filter ini yang memanggil salah satu dari 6 seeder secara langsung dan gagal karena environment `testing` — cek dulu apakah test itu jalan di bawah `APP_ENV=testing` (default Pest/PHPUnit config repo ini) sebelum menganggap ini bug; guard mengizinkan `testing` secara eksplisit jadi seharusnya tidak masalah.

- [ ] **Step 10: Commit**

```bash
git add database/seeders/EssentialUserSeeder.php database/seeders/UserSeeder.php database/seeders/OrangTuaKaryawanSeeder.php database/seeders/SiswaSeeder.php database/seeders/SarprasPengadaanDemoSeeder.php database/seeders/AkunPendaftarSeeder.php
git commit -m "fix(seeder): tambah environment-guard supaya seeder password lemah tidak jalan di luar local/testing"
```

---

### Task 4: Kerapian Relasi (RolePermissionSeeder, konsolidasi SarprasPengadaanDemoSeeder, validasi WorkflowDefinitionSeeder)

**Files:**
- Modify: `database/seeders/RolePermissionSeeder.php`
- Modify: `database/seeders/SarprasPengadaanDemoSeeder.php`
- Modify: `database/seeders/WorkflowDefinitionSeeder.php`

**Interfaces:**
- Consumes: WAJIB dikerjakan SETELAH Task 1 (butuh role `bendahara_yayasan`/`kepala_sekolah`/`admin_administrasi` sudah punya permission Sarpras/Pengadaan lengkap dari `RolePermissionAssignmentSeeder`, supaya bisa menghapus direct-grant yang redundan di `SarprasPengadaanDemoSeeder`) dan SETELAH Task 3 (guard sudah ada di kepala method `run()`, sisipan baru di task ini ditambahkan SETELAH blok guard, bukan sebelum).

- [ ] **Step 1: Doc-comment `RolePermissionSeeder.php`**

Tambahkan doc-comment di atas `class RolePermissionSeeder`:

```php
<?php
// database/seeders/RolePermissionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Fixture RBAC ringan khusus untuk test (dipanggil via $this->seed(RolePermissionSeeder::class)
 * di puluhan file test). Sengaja TIDAK dipanggil dari DatabaseSeeder::run() — hanya membuat
 * permission + role tanpa data domain lain, supaya test RBAC-only bisa jalan cepat.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        (new PermissionSeeder())->run();
        (new RoleSeeder())->run();
    }
}
```

(Isi method `run()` TIDAK berubah — cuma menambahkan doc-comment sebelum `class`.)

- [ ] **Step 2: Konsolidasi `SarprasPengadaanDemoSeeder.php` — hapus role/permission duplikat**

Ganti seluruh blok dari `// Setup User Accounts & Permissions` sampai sebelum `$operatorUser = $adm ?? $superAdmin;` (persis baris 63-117 di file asli, SETELAH guard dari Task 3 ditambahkan posisinya bergeser tapi isinya sama) — dari:

```php
        // Setup User Accounts & Permissions
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@sistem.test'],
            ['name' => 'Admin Sistem', 'password' => 'password', 'is_active' => true]
        );
        $superAdmin->givePermissionTo(Permission::all());

        $bendaharaYayasanRole = Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $bendaharaYayasanRole->givePermissionTo([
            'pengadaan.proposal.view',
            'pengadaan.approval.yayasan',
            'pengadaan.disbursement.manage',
            'pengadaan.lpj.verify',
            'sarpras.aset.view',
        ]);

        $bendaharaYayasan = User::firstOrCreate(
            ['email' => 'bendahara.yayasan@sistem.test'],
            ['name' => 'Ustadz Farid (Bendahara Yayasan)', 'password' => 'password', 'is_active' => true]
        );
        $bendaharaYayasan->assignRole($bendaharaYayasanRole);

        $kepsekRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $kepsekRole->givePermissionTo([
            'sarpras.gedung.view', 'sarpras.ruangan.view', 'sarpras.kategori.view', 'sarpras.aset.view', 'sarpras.mutasi.view',
            'pengadaan.proposal.view', 'pengadaan.approval.internal',
        ]);

        $kepsek = User::firstOrCreate(
            ['email' => 'kepsek@sistem.test'],
            ['name' => 'Dr. H. Ahmad Dahlan (Kepala Sekolah)', 'password' => 'password', 'is_active' => true, 'lembaga_id' => $lembaga->id]
        );
        $kepsek->update(['lembaga_id' => $lembaga->id]);
        $kepsek->assignRole($kepsekRole);
        $kepsek->givePermissionTo([
            'sarpras.gedung.view', 'sarpras.ruangan.view', 'sarpras.kategori.view', 'sarpras.aset.view', 'sarpras.mutasi.view',
            'pengadaan.proposal.view', 'pengadaan.approval.internal',
        ]);

        $adm = User::firstOrCreate(
            ['email' => 'adm@sistem.test'],
            ['name' => 'Admin Sarpras & Operasional', 'password' => 'password', 'is_active' => true, 'lembaga_id' => $lembaga->id]
        );
        $adm->update(['lembaga_id' => $lembaga->id]);
        $adm->assignRole('admin_administrasi');
        $adm->givePermissionTo([
            'sarpras.gedung.view', 'sarpras.gedung.manage',
            'sarpras.ruangan.view', 'sarpras.ruangan.manage',
            'sarpras.kategori.view', 'sarpras.kategori.manage',
            'sarpras.aset.view', 'sarpras.aset.manage',
            'sarpras.mutasi.create', 'sarpras.mutasi.view', 'sarpras.kir.export',
            'pengadaan.proposal.create', 'pengadaan.proposal.view', 'pengadaan.proposal.edit', 'pengadaan.proposal.delete',
            'pengadaan.lpj.submit',
        ]);
```

menjadi (role/permission SUDAH lengkap dari `RolePermissionAssignmentSeeder` Task 1 — di sini cukup MEMAKAI akun yang sudah ada / assign role by name, tanpa givePermissionTo langsung):

```php
        // Setup User Accounts — role & permission sudah lengkap dari
        // RolePermissionAssignmentSeeder (lihat database/seeders/RolePermissionAssignmentSeeder.php),
        // file ini HANYA membuat/memakai akun, tidak lagi mengulang assignment role/permission.
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@sistem.test'],
            ['name' => 'Admin Sistem', 'password' => 'password', 'is_active' => true]
        );

        $bendaharaYayasan = User::firstOrCreate(
            ['email' => 'bendahara.yayasan@sistem.test'],
            ['name' => 'Farid', 'password' => 'password', 'is_active' => true]
        );
        $bendaharaYayasan->assignRole('bendahara_yayasan');

        $kepsek = User::firstOrCreate(
            ['email' => 'kepsek@sistem.test'],
            ['name' => 'Dr. H. Ahmad Dahlan (Kepala Sekolah)', 'password' => 'password', 'is_active' => true, 'lembaga_id' => $lembaga->id]
        );
        $kepsek->update(['lembaga_id' => $lembaga->id]);
        $kepsek->assignRole('kepala_sekolah');

        $adm = User::firstOrCreate(
            ['email' => 'adm@sistem.test'],
            ['name' => 'Admin Sarpras & Operasional', 'password' => 'password', 'is_active' => true, 'lembaga_id' => $lembaga->id]
        );
        $adm->update(['lembaga_id' => $lembaga->id]);
        $adm->assignRole('admin_administrasi');
```

Catatan: nama `'Ustadz Farid (Bendahara Yayasan)'` diganti jadi `'Farid'` di step ini SEKALIAN (bagian dari Bagian C Spec 2 — nama netral. Kalau Spec 2/plan rebranding terpisah belum/tidak dieksekusi lebih dulu, boleh dibiarkan nama lama `'Ustadz Farid (Bendahara Yayasan)'` di sini dan diserahkan ke plan Spec 2; TIDAK WAJIB diubah di task ini, cuma penghapusan role/permission block yang wajib).

Setelah blok di atas, import `use App\Models\Role;` dan `use Spatie\Permission\Models\Permission;` di kepala file (`database/seeders/SarprasPengadaanDemoSeeder.php`) jadi tidak dipakai lagi — hapus kedua baris `use` itu.

- [ ] **Step 3: Validasi role di `WorkflowDefinitionSeeder.php`**

Tambahkan import di kepala file, setelah `use App\Domains\Workflow\Models\WorkflowStep;`:

```php
use App\Models\Role;
```

Sebelum SETIAP dari 4 pemanggilan `WorkflowStep::updateOrCreate(...)` yang punya `'approver_type' => ApproverType::Role`, tambahkan validasi. Method `run()` jadi:

```php
    public function run(): void
    {
        // 1. Workflow Pengadaan Sarpras Sekolah
        $pengadaan = WorkflowDefinition::updateOrCreate(
            ['code' => 'PENGADAAN_SARPRAS'],
            [
                'nama_workflow' => 'Pengadaan Sarana & Prasarana',
                'deskripsi' => 'Alur persetujuan usulan belanja & inventaris dari unit lembaga ke yayasan.',
                'is_active' => true,
            ]
        );

        $this->assertRoleExists('kepala_sekolah');
        WorkflowStep::updateOrCreate(
            ['workflow_definition_id' => $pengadaan->id, 'step_number' => 1],
            [
                'step_name' => 'Verifikasi Internal Kepala Sekolah',
                'approver_type' => ApproverType::Role,
                'approver_value' => 'kepala_sekolah',
                'scope_level' => 'lembaga',
                'is_final_step' => false,
            ]
        );

        $this->assertRoleExists('bendahara_yayasan');
        WorkflowStep::updateOrCreate(
            ['workflow_definition_id' => $pengadaan->id, 'step_number' => 2],
            [
                'step_name' => 'Persetujuan & Pencairan Yayasan',
                'approver_type' => ApproverType::Role,
                'approver_value' => 'bendahara_yayasan',
                'scope_level' => 'yayasan',
                'is_final_step' => true,
            ]
        );

        // 2. Workflow Persetujuan Rapor Semester
        $rapor = WorkflowDefinition::updateOrCreate(
            ['code' => 'RAPOR_SEMESTER'],
            [
                'nama_workflow' => 'Persetujuan Rapor Semester',
                'deskripsi' => 'Alur verifikasi Waka Kurikulum dan persetujuan akhir Kepala Sekolah untuk pengajuan rapor per kelas per semester.',
                'is_active' => true,
            ]
        );

        $this->assertRoleExists('admin_akademik');
        WorkflowStep::updateOrCreate(
            ['workflow_definition_id' => $rapor->id, 'step_number' => 1],
            [
                'step_name' => 'Verifikasi Waka Kurikulum',
                'approver_type' => ApproverType::Role,
                'approver_value' => 'admin_akademik',
                'scope_level' => 'lembaga',
                'is_final_step' => false,
            ]
        );

        $this->assertRoleExists('kepala_sekolah');
        WorkflowStep::updateOrCreate(
            ['workflow_definition_id' => $rapor->id, 'step_number' => 2],
            [
                'step_name' => 'Persetujuan Akhir Kepala Sekolah',
                'approver_type' => ApproverType::Role,
                'approver_value' => 'kepala_sekolah',
                'scope_level' => 'lembaga',
                'is_final_step' => true,
            ]
        );
    }

    private function assertRoleExists(string $roleName): void
    {
        abort_unless(
            Role::where('name', $roleName)->exists(),
            500,
            "WorkflowDefinitionSeeder: role '{$roleName}' tidak ditemukan — cek RoleSeeder."
        );
    }
```

- [ ] **Step 4: Verifikasi manual**

```bash
php artisan migrate:fresh --seed
```

Expected: selesai tanpa error (kalau `WorkflowDefinitionSeeder` sampai abort, berarti urutan `RoleSeeder` vs `WorkflowDefinitionSeeder` di `DatabaseSeeder` salah — `RoleSeeder` HARUS tetap di posisi awal, jauh sebelum `WorkflowDefinitionSeeder` yang ada di posisi akhir; ini seharusnya sudah benar dari struktur asli, cuma pastikan Task 1 tidak mengubah posisi `RoleSeeder`).

```bash
php artisan tinker --execute="echo App\Models\User::where('email', 'kepsek@sistem.test')->first()->getAllPermissions()->count();"
```

Expected: angka LEBIH BESAR dari 0 (kepsek sekarang dapat permission Sarpras/Pengadaan lewat role, bukan direct-grant).

- [ ] **Step 5: Jalankan scoped test**

```bash
php artisan test --filter=Workflow
php artisan test --filter=Sarpras
php artisan test --filter=Pengadaan
```

Expected: PASS. Kalau ada test yang meng-assert `$kepsek->hasDirectPermission(...)` (permission LANGSUNG di user, bukan lewat role) — itu akan gagal karena sekarang permission-nya cuma lewat role, BUKAN direct grant lagi. Ini PERUBAHAN YANG DISENGAJA (bagian dari konsolidasi); update assertion test itu jadi `$kepsek->hasPermissionTo(...)` (cek permission lewat role JUGA, bukan cuma direct) kalau ditemukan.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/RolePermissionSeeder.php database/seeders/SarprasPengadaanDemoSeeder.php database/seeders/WorkflowDefinitionSeeder.php
git commit -m "refactor(seeder): konsolidasi role/permission SarprasPengadaanDemoSeeder ke RolePermissionAssignmentSeeder, validasi role di WorkflowDefinitionSeeder"
```

---

### Task 5: Cleanup Low-Priority

**Files:**
- Modify: `database/seeders/PermissionSeeder.php`
- Create: `database/migrations/2026_08_21_100000_cleanup_legacy_flat_permissions.php`
- Modify: `database/seeders/OrangTuaKaryawanSeeder.php`
- Modify: `database/seeders/CalonMuridSeeder.php`
- Modify: `database/seeders/KeuanganDemoSeeder.php`
- Modify: `database/seeders/SeleksiPpdbSeeder.php`
- Modify: `database/seeders/SesiPembelajaranSeeder.php`

**Interfaces:**
- Consumes: tidak bergantung pada Task 1-4, independen.

- [ ] **Step 1: Pindahkan legacy-delete dari `PermissionSeeder.php` ke migration baru**

Hapus blok berikut dari `database/seeders/PermissionSeeder.php` (baris 13-22, di dalam `run()`):

```php
        // Legacy flat-name permissions from an earlier RBAC iteration. Matches zero rows on
        // a clean install, so this is a harmless no-op there -- kept so this seeder alone
        // stays safe to run against a database that still has them (mirrors what
        // RolePermissionSeeder used to do, so its own pre-existing regression test keeps
        // passing unmodified).
        Permission::whereIn('name', [
            'manage-roles', 'manage-users', 'manage-yayasan',
            'manage-lembaga', 'manage-tahun-ajaran', 'manage-guru',
            'view-audit-log', 'manage-ppdb',
        ])->delete();

```

Buat file migration baru:

```bash
php artisan make:migration cleanup_legacy_flat_permissions
```

Command di atas membuat file dengan nama otomatis (timestamp saat ini) — cari nama file yang baru dibuat:

```bash
ls -t database/migrations | head -1
```

Isi file yang baru dibuat itu dengan:

```php
<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Cleanup satu-arah untuk 8 permission flat-name dari iterasi RBAC lama
     * (dipindah dari PermissionSeeder.php, yang tidak seharusnya berisi
     * migration-logic permanen). No-op kalau baris-baris ini sudah tidak ada.
     */
    public function up(): void
    {
        \Spatie\Permission\Models\Permission::whereIn('name', [
            'manage-roles', 'manage-users', 'manage-yayasan',
            'manage-lembaga', 'manage-tahun-ajaran', 'manage-guru',
            'view-audit-log', 'manage-ppdb',
        ])->delete();
    }

    public function down(): void
    {
        // Cleanup satu-arah -- tidak ada rollback yang bermakna (data lama
        // sudah dianggap tidak valid).
    }
};
```

- [ ] **Step 2: Ganti NIK dummy ke prefix `0000` (3 file)**

Di `database/seeders/OrangTuaKaryawanSeeder.php`, ganti SEMUA 8 literal NIK berikut (cari-ganti persis, satu per satu — jangan pakai regex blanket karena beberapa NIK dipakai juga sebagai bagian dari string lain seperti komentar):

| Lama | Baru |
|---|---|
| `'3273019901900099'` (baris 68) | `'0000019901900099'` |
| `'3273019901850051'` (baris 111) | `'0000019901850051'` |
| `'3273019902860052'` (baris 119) | `'0000019902860052'` |
| `'3273019903870053'` (baris 127) | `'0000019903870053'` |
| `'3273019904880054'` (baris 135) | `'0000019904880054'` |
| `'3273019901850001'` (baris 189) | `'0000019901850001'` |
| `'3273019901850002'` (baris 227) | `'0000019901850002'` |
| `'3273019901850003'` (baris 263) | `'0000019901850003'` |
| `'3273019905890055'` (baris 308) | `'0000019905890055'` |

Di `database/seeders/CalonMuridSeeder.php` baris 34, ganti:

```php
                'nik' => (string) random_int(3200000000000000, 3299999999999999),
```

menjadi:

```php
                'nik' => '0000'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
```

Di `database/seeders/KeuanganDemoSeeder.php` baris 30-32, ganti:

```php
        $demoParents = [
            ['nik' => '3273019901850001', 'email' => 'ortu.demo@permatakraksaan.sch.id'],
            ['nik' => '3273019901850002', 'email' => 'ortu.kb.demo@permatakraksaan.sch.id'],
            ['nik' => '3273019901850003', 'email' => 'ortu.tk.demo@permatakraksaan.sch.id'],
        ];
```

menjadi:

```php
        $demoParents = [
            ['nik' => '0000019901850001', 'email' => 'ortu.demo@permatakraksaan.sch.id'],
            ['nik' => '0000019901850002', 'email' => 'ortu.kb.demo@permatakraksaan.sch.id'],
            ['nik' => '0000019901850003', 'email' => 'ortu.tk.demo@permatakraksaan.sch.id'],
        ];
```

(Nilai NIK ini HARUS identik dengan yang dipakai `OrangTuaKaryawanSeeder.php` di atas — `KeuanganDemoSeeder::run()` mencari User lewat `User::where('username', $demo['nik'])->first()`, jadi kalau tidak sinkron, seeder ini akan selalu skip dengan warning "user demo tidak ditemukan".)

- [ ] **Step 3: Tanggal relatif di `SeleksiPpdbSeeder.php`**

Ganti 3 method private (`seleksiPaud()`, `seleksiSd()`, `seleksiSmp()`) — ganti SEMUA nilai `'jadwal'` dari string absolut ke ekspresi `now()->...`. Method `run()` TIDAK berubah.

```php
    private function seleksiPaud(): array
    {
        return [
            'Reguler' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Observasi Anak', 'jadwal' => now()->addDays(20)->setTime(8, 0)->format('Y-m-d H:i:s'), 'kriteria' => 'Perkembangan usia sesuai', 'bobot' => 60],
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Wawancara Orang Tua', 'jadwal' => now()->addDays(21)->setTime(8, 0)->format('Y-m-d H:i:s'), 'kriteria' => 'Komitmen pola asuh', 'bobot' => 40],
            ],
            'Prestasi' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Observasi Anak', 'jadwal' => now()->addDays(22)->setTime(9, 0)->format('Y-m-d H:i:s'), 'kriteria' => 'Verifikasi bakat khusus', 'bobot' => 100],
            ],
            'Afirmasi' => [],
        ];
    }

    private function seleksiSd(): array
    {
        return [
            'Reguler' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Observasi Kesiapan Sekolah', 'jadwal' => now()->addDays(20)->setTime(8, 0)->format('Y-m-d H:i:s'), 'kriteria' => 'Kematangan motorik & emosional', 'bobot' => 60],
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Wawancara Orang Tua', 'jadwal' => now()->addDays(21)->setTime(8, 0)->format('Y-m-d H:i:s'), 'kriteria' => 'Komitmen kemitraan orang tua', 'bobot' => 40],
            ],
            'Prestasi' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Tes Baca Al-Qur\'an', 'jadwal' => now()->addDays(22)->setTime(9, 0)->format('Y-m-d H:i:s'), 'kriteria' => 'Kemampuan membaca / tahfizh', 'bobot' => 100],
            ],
            'Afirmasi' => [],
        ];
    }

    private function seleksiSmp(): array
    {
        return [
            'Reguler' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Tes Tulis', 'jadwal' => now()->addDays(15)->setTime(8, 0)->format('Y-m-d H:i:s'), 'kriteria' => 'Nilai minimal 65', 'bobot' => 60],
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Wawancara', 'jadwal' => now()->addDays(16)->setTime(8, 0)->format('Y-m-d H:i:s'), 'kriteria' => 'Lolos wawancara motivasi', 'bobot' => 40],
            ],
            'Prestasi' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Wawancara', 'jadwal' => now()->addDays(17)->setTime(9, 0)->format('Y-m-d H:i:s'), 'kriteria' => 'Verifikasi keaslian sertifikat & wawancara', 'bobot' => 100],
            ],
            'Afirmasi' => [],
        ];
    }
```

Catatan: nilai `addDays(N)` dipilih supaya urutan tanggal antar jenis tes dalam 1 jalur tetap logis (tes pertama sebelum tes kedua), sama seperti pola aslinya. Angka pasti tidak penting selama urutannya konsisten.

- [ ] **Step 4: Komentar di `SesiPembelajaranSeeder.php`**

Tambahkan komentar di atas baris `$kemarin = Carbon::yesterday();` (baris 22):

```php
        // Memakai "kemarin" (bukan tanggal tetap) supaya sesi selalu terlihat baru saat
        // di-demo. Efek samping: reseed di HARI KALENDER YANG BERBEDA akan menambah baris
        // baru (bukan menimpa baris lama), karena kombinasi key firstOrCreate di bawah
        // menyertakan tanggal — ini bukan bug, murni karakteristik data yang bertambah
        // seiring waktu kalau seeder dijalankan ulang di hari berbeda-beda.
        $kemarin = Carbon::yesterday();
```

- [ ] **Step 5: Perbaiki komentar `KeuanganDemoSeeder.php` (§6.5 spec)**

Ganti doc-comment kepala file (baris 15-24) dari:

```php
/**
 * Data demo untuk mencoba Modul Keuangan lewat browser secara end-to-end.
 *
 * Standalone -- TIDAK dipanggil dari DatabaseSeeder::run(), supaya tidak
 * mengubah jumlah baris yang diasumsikan test/seeder lain. Jalankan manual:
 *   php artisan db:seed --class=KeuanganDemoSeeder
 *
 * Butuh DatabaseSeeder sudah pernah dijalankan (butuh akun demo orang tua
 * dari OrangTuaKaryawanSeeder + wallet otomatis dari StudentCreated event).
 */
```

menjadi:

```php
/**
 * Data demo untuk mencoba Modul Keuangan lewat browser secara end-to-end.
 *
 * Dipanggil dari DatabaseSeeder::run() (posisi setelah OrangTuaKaryawanSeeder dan
 * PendampinganSeeder) untuk demo end-to-end otomatis setiap fresh seed. Bergantung pada
 * akun demo orang tua dari OrangTuaKaryawanSeeder + wallet otomatis dari StudentCreated event
 * -- keduanya sudah terjamin ada di titik ini karena urutan DatabaseSeeder.
 */
```

Baca ulang method `seedForSiswa()` dan `buatTagihan()` (baris 55-132) — TIDAK ADA perubahan logic yang diperlukan di sini; kedua method sudah tidak berasumsi apapun soal "dijalankan terpisah" (semua lookup memakai `firstOrCreate`/query eksplisit dengan null-check, bukan mengasumsikan state kosong). Kalau saat membaca ulang ditemukan asumsi tersembunyi yang bertentangan dengan posisi barunya di akhir `DatabaseSeeder` (mis. mengasumsikan tidak ada seeder lain yang jalan sesudahnya), catat sebagai temuan baru dan laporkan — jangan diperbaiki diam-diam tanpa mencatat di commit message.

- [ ] **Step 6: Verifikasi manual**

```bash
php artisan migrate:fresh --seed
```

Expected: selesai tanpa error. Cek migration baru benar-benar jalan:

```bash
php artisan tinker --execute="echo Spatie\Permission\Models\Permission::whereIn('name', ['manage-roles','manage-users'])->count();"
```

Expected: `0` (permission lama tetap bersih, sekarang lewat migration bukan seeder).

```bash
php artisan tinker --execute="echo App\Models\User::where('username', '0000019901850001')->exists() ? 'sinkron (benar)' : 'TIDAK SINKRON (salah)';"
```

Expected: `sinkron (benar)` — membuktikan NIK di `OrangTuaKaryawanSeeder` dan `KeuanganDemoSeeder` cocok.

- [ ] **Step 7: Jalankan scoped test**

```bash
php artisan test --filter=Seleksi
php artisan test --filter=SesiPembelajaran
php artisan test --filter=KeuanganDemo
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add database/seeders/PermissionSeeder.php database/migrations/*cleanup_legacy_flat_permissions.php database/seeders/OrangTuaKaryawanSeeder.php database/seeders/CalonMuridSeeder.php database/seeders/KeuanganDemoSeeder.php database/seeders/SeleksiPpdbSeeder.php database/seeders/SesiPembelajaranSeeder.php
git commit -m "chore(seeder): cleanup low-priority - migration untuk legacy permission, NIK dummy jelas-fiktif, tanggal seleksi relatif, dokumentasi KeuanganDemoSeeder"
```

---

### Task 6: Dokumen Konvensi Seeder + Full Suite

**Files:**
- Create: `.agents/skills/seeder-standard/SKILL.md`

**Interfaces:**
- Consumes: seluruh pelajaran dari Task 1-5 (dokumen ini MERANGKUM keputusan yang sudah diimplementasikan, bukan mendesain baru).

- [ ] **Step 1: Buat `.agents/skills/seeder-standard/SKILL.md`**

Ikuti format frontmatter yang dipakai `.agents/skills/laravel-feature-standard/SKILL.md` (baca file itu dulu kalau perlu referensi format persis). Isi:

```markdown
---
name: seeder-standard
description: Standar wajib untuk seeder Laravel di repo ini (database/seeders/*.php) -- kepemilikan tabel RBAC, pola key idempotency, environment-guard kredensial, dan urutan DatabaseSeeder. Gunakan saat membuat seeder baru atau mengubah DatabaseSeeder::run().
---

# Standar Seeder Pintera

Lahir dari audit menyeluruh 59 file seeder (2026-08-21) yang menemukan 18 bug/inkonsistensi -- lihat `.agents/specs/2026-08-21-audit-perbaikan-seeder.md` untuk detail lengkap tiap temuan. Dokumen ini adalah rangkuman aturan yang WAJIB diikuti seeder baru ke depan.

## 1. 1-Tabel-1-Seeder untuk RBAC

Tabel `permissions`, `roles`, dan pivot `role_has_permissions` masing-masing HARUS punya TEPAT SATU seeder pemilik:

- `PermissionSeeder.php` -- satu-satunya yang boleh `Permission::firstOrCreate(...)`.
- `RoleSeeder.php` -- satu-satunya yang boleh `Role::firstOrCreate(...)`.
- `RolePermissionAssignmentSeeder.php` -- satu-satunya yang boleh `$role->givePermissionTo(...)` / `$role->syncPermissions(...)`.

Modul baru yang butuh permission BARU: tambahkan ke `PermissionSeeder.php`, JANGAN buat file permission sendiri. Modul baru yang butuh role BARU: tambahkan ke `RoleSeeder.php`. Assignment permission ke role manapun: tambahkan ke `RolePermissionAssignmentSeeder.php`.

**Kenapa:** sebelum aturan ini ditegakkan, `SarprasPermissionSeeder.php`/`PengadaanPermissionSeeder.php` membuat role/permission SENDIRI secara terpisah dari `RoleSeeder`/`PermissionSeeder`, menyebabkan role `yayasan_super_admin` mengambil snapshot permission SEBELUM permission modul-modul itu ada (karena `RoleSeeder` jalan di awal `DatabaseSeeder`, sementara seeder permission modul itu jalan di akhir). Super admin jadi tidak punya akses ke modul Sarpras/Pengadaan sampai ditambal manual.

## 2. Pola Key Idempotency

`firstOrCreate($key, $values)` / `updateOrCreate($key, $values)` -- `$key` (parameter pertama) HANYA boleh berisi kolom yang STABIL (tidak berubah oleh aplikasi setelah baris dibuat): kode unik, kombinasi FK, nama, dsb.

`$key` DILARANG berisi kolom state-machine/status yang bisa berubah lewat alur aplikasi (mis. kolom `status` pada model yang punya siklus hidup seperti `Diajukan -> Berjalan -> Selesai`).

**Contoh SALAH** (menyebabkan duplikasi baris kalau seeder dijalankan ulang setelah status berubah):

```php
Kasus::firstOrCreate(
    ['siswa_id' => $siswa->id, 'status' => StatusKasus::MenungguConsent], // status BISA berubah!
    [...]
);
```

**Contoh BENAR:**

```php
Kasus::firstOrCreate(
    ['siswa_id' => $siswa->id, 'kategori_masalah' => 'Sosial-Emosional'], // stabil
    ['status' => StatusKasus::MenungguConsent, ...] // status masuk values, bukan key
);
```

## 3. Environment-Guard Wajib untuk Kredensial

Seeder yang membuat akun/kredensial dengan password yang diketahui publik (termasuk hardcode di source code, seperti `'password'`) WAJIB guard baris pertama di `run()`:

```php
public function run(): void
{
    if (! app()->environment(['local', 'testing'])) {
        $this->command?->warn(static::class.': dilewati, hanya boleh jalan di environment local/testing.');

        return;
    }

    // ... kode seeder
}
```

Seeder LAIN yang bergantung pada data dari seeder berkredensial ini (query `User::where('email', ...)`, dst) HARUS memakai pola lookup-lalu-skip (`if (! $x) { continue/return; }`), BUKAN `firstOrFail()`/asumsi data pasti ada -- supaya rantai skip berjalan aman kalau environment guard aktif.

## 4. Urutan Wajib di `DatabaseSeeder::run()`

1. `PermissionSeeder::class` -- SELALU paling awal.
2. `RoleSeeder::class` -- SETELAH `PermissionSeeder`, SEBELUM seeder domain apapun yang butuh role sudah ada (assign role ke user).
3. Seeder domain (data master, transaksional, demo) -- urutan parent-sebelum-child sesuai dependency FK masing-masing.
4. `RolePermissionAssignmentSeeder::class` -- SELALU entri PALING AKHIR di seluruh array `$this->call([...])`. Ini yang menjamin snapshot `syncPermissions()` untuk super admin selalu lengkap, karena semua permission dari seeder manapun sudah pasti dibuat sebelum baris ini jalan.

## 5. Checklist Sebelum Menambah Seeder Baru

- [ ] Idempotency key sudah pakai kolom stabil (bukan status/tanggal berjalan)?
- [ ] Dependency ke tabel lain sudah di-assert (`firstOrFail()` atau early-return eksplisit), bukan diasumsikan ada?
- [ ] Tidak ada password/kredensial hardcode tanpa environment-guard?
- [ ] Posisi di `DatabaseSeeder::run()` sudah benar (parent dulu, `RolePermissionAssignmentSeeder` di akhir)?
- [ ] Kalau menambah permission baru: masuk ke `PermissionSeeder`, bukan file terpisah?
- [ ] Kalau menambah role baru: masuk ke `RoleSeeder`, bukan file terpisah?
```

- [ ] **Step 2: Commit dokumen konvensi**

```bash
git add .agents/skills/seeder-standard/SKILL.md
git commit -m "docs(seeder): tambah dokumen konvensi wajib seeder Pintera"
```

- [ ] **Step 3: Minta izin user untuk full suite**

**JANGAN jalankan langsung.** Tanyakan ke user secara eksplisit: *"Task 1-6 selesai, semua scoped test PASS. Boleh saya jalankan full test suite (`php artisan test`) sekarang sebagai verifikasi akhir?"* Tunggu jawaban user sebelum lanjut ke Step 4.

- [ ] **Step 4: Full suite (hanya setelah izin didapat)**

```bash
php artisan test
```

Expected: SEMUA test PASS, 0 failed, 0 error. Kalau ada FAIL, baca pesan errornya — kemungkinan besar test lama yang meng-assert struktur RBAC/nama akun/NIK yang berubah di Task 1-5 tapi belum ter-update. Perbaiki test tersebut (BUKAN kode aplikasi) kalau assertion-nya memang tentang detail seeder yang sengaja diubah plan ini.

- [ ] **Step 5: Laporkan hasil ke user**

Setelah full suite PASS, laporkan ringkas ke user: jumlah task selesai, jumlah commit, hasil full suite (jumlah test/assertion), dan tanyakan apakah plan Spec 2 (rebranding data demo) mau dilanjutkan sekarang atau nanti.
