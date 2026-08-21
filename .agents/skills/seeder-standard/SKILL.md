---
name: seeder-standard
description: Standar konvensi penulisan seeder di Pintera (idempotensi, hierarki RBAC 1-table-1-seeder, environment guard, dynamic dates, cleanup legacy).
---

# Standar Konvensi Penulisan Seeder Pintera

Dokumen ini adalah acuan baku dalam membuat dan memodifikasi file seeder (`database/seeders/*.php`) pada aplikasi Pintera.

## 1. Prinsip Idempotensi (Wajib `firstOrCreate` / `updateOrCreate`)

Semua seeder di Pintera **WAJIB** idempotent (bisa dijalankan berkali-kali tanpa menghasilkan duplikasi data atau melempar error constraint).

- **Gunakan composite key yang stabil** pada lookup array:
  ```php
  // BENAR: lookup berdasarkan kunci identitas permanen (misal: jenis kasus + subjek)
  Kasus::firstOrCreate(
      ['lembaga_id' => $lembaga->id, 'kategori' => 'bully', 'nama_korban' => 'Ahmad'],
      ['status' => StatusKasus::Diajukan, ...]
  );

  // SALAH: lookup berdasarkan status dinamis (berubah saat workflow jalan)
  Kasus::firstOrCreate(['status' => 'diajukan'], [...]);
  ```
- **Hindari `create()` langsung** tanpa pengecekan keberadaan data terlebih dahulu.

---

## 2. Arsitektur RBAC 1-Tabel-1-Seeder

Pemisahan tanggung jawab seeder RBAC (Spatie Permission) adalah mutlak:

| File Seeder | Tanggung Jawab / Tabel Target | Aturan Khusus |
|---|---|---|
| `PermissionSeeder.php` | `permissions` | Hanya mendaftarkan semua nama permission. DILARANG membuat role atau meng-assign permission ke role di sini. |
| `RoleSeeder.php` | `roles` | Hanya mendaftarkan nama-nama role dengan `scope_level`-nya. Boleh meng-assign baseline default permissions jika diperlukan fixture test dasar. |
| `RolePermissionAssignmentSeeder.php` | `role_has_permissions` (pivot) | Satu-satunya tempat resmi untuk mapping lengkap relasi Role $\leftrightarrow$ Permission (termasuk modul Sarpras, Pengadaan, Kasus, Keuangan, dll). **Wajib dipanggil paling akhir di `DatabaseSeeder::run()`**. |
| `RolePermissionSeeder.php` | Helper Fixture Test | Hanya memanggil `PermissionSeeder` dan `RoleSeeder` untuk kebutuhan unit/feature test ringan. DILARANG dipanggil dari `DatabaseSeeder::run()`. |

**Dilarang Keras:** Membuat permission atau role baru di dalam seeder domain/demo (misal di dalam `SarprasPengadaanDemoSeeder.php` atau `KeuanganDemoSeeder.php`). Semua nama permission baru harus didaftarkan di `PermissionSeeder.php` dan mapping-nya di `RolePermissionAssignmentSeeder.php`.

---

## 3. Environment Guard untuk Data Sensitif / Password Demo

Seeder yang membuat akun dengan kata sandi lemah (misal `'password'`) atau data demo sintetis **WAJIB** memiliki guard di awal method `run()`:

```php
public function run(): void
{
    if (! app()->environment(['local', 'testing'])) {
        $this->command?->warn(static::class.': dilewati, hanya boleh jalan di environment local/testing.');
        return;
    }

    // ... logika seeding akun / password demo ...
}
```

File yang wajib menerapkan guard ini:
- `EssentialUserSeeder.php`
- `UserSeeder.php`
- `OrangTuaKaryawanSeeder.php`
- `SiswaSeeder.php`
- `SarprasPengadaanDemoSeeder.php`
- `AkunPendaftarSeeder.php`

---

## 4. Penggunaan Tanggal Dinamis (Bukan Hardcoded)

Untuk data transaksi, jadwal seleksi, tagihan, atau mutasi aset:
- **Gunakan helper `now()` dengan kalkulasi relatif** (misal `now()->addDays(7)`, `now()->subMonths(1)`).
- **DILARANG** meng-hardcode tahun statis (seperti `'2025-02-20'` atau `'2026-08-20'`) agar data demo selalu relevan saat aplikasi dijalankan di masa depan.

---

## 5. Pembersihan Legacy Permission via Migration

Jika ada permission usang/orphan yang dihapus dari codebase:
- Hapus dari `PermissionSeeder.php`.
- Buat migration pembersihan database (misal `cleanup_legacy_permissions_and_sync_pivot`) agar database staging/production yang sudah berjalan ikut terbersihkan tanpa perlu `migrate:fresh`.
- Panggil `app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();` di dalam migration.
