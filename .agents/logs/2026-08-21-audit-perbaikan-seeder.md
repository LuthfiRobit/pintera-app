# Handoff Log: Audit & Perbaikan Seeder Pintera

- **Tanggal**: 2026-08-21
- **Branch**: `rbac-v2`
- **Spec**: `.agents/specs/2026-08-21-audit-perbaikan-seeder.md`
- **Plan**: `.agents/plans/2026-08-21-audit-perbaikan-seeder.md`
- **Skill Konvensi**: `.agents/skills/seeder-standard/SKILL.md`
- **Status Git**: 7 commit tersimpan di branch `rbac-v2`

---

## 1. Apa yang Dikerjakan

Telah dilakukan perbaikan dan refactoring menyeluruh pada arsitektur seeder Laravel 12 multi-tenant di Pintera (mencakup 59 file seeder), mengatasi 18 temuan audit melalui 6 task terstruktur:

1. **Restrukturisasi RBAC 1-Tabel-1-Seeder (Task 1, Commit `695d315`)**:
   - Mendaftarkan 21 granular permissions modul Sarpras dan Pengadaan ke `database/seeders/PermissionSeeder.php` (total permission: 134).
   - Menambahkan role `bendahara_yayasan` (scope yayasan) dan `admin_sarpras` (scope yayasan) ke `database/seeders/RoleSeeder.php` (total role: 12).
   - Membuat `database/seeders/RolePermissionAssignmentSeeder.php` sebagai pemilik tunggal tabel pivot `role_has_permissions`, dipanggil sebagai seeder paling akhir di `DatabaseSeeder::run()`.
   - Menghapus seeder redundan `SarprasPermissionSeeder.php` dan `PengadaanPermissionSeeder.php`.
   - Menambahkan demo user `sarpras@sistem.test` di `database/seeders/EssentialUserSeeder.php`.
   - Mengupdate seluruh test fixture yang merujuk seeder lama ke `PermissionSeeder::class` & `RolePermissionAssignmentSeeder::class`.

2. **Perbaikan Idempotensi `PendampinganSeeder.php` (Task 2, Commit `7f254c3`)**:
   - Memperbaiki ke-10 fungsi pembuatan data kasus di `database/seeders/PendampinganSeeder.php` agar menggunakan composite key stabil (lembaga, jenis kasus, nama subjek) menggantikan lookup kolom status dinamis.
   - Terverifikasi idempotent: total baris Kasus tetap 10 saat seeder dijalankan berulang.

3. **Environment Guard untuk Seeder Password Demo (Task 3, Commit `9102eb4`)**:
   - Memasang `if (! app()->environment(['local', 'testing'])) { ... return; }` pada 6 seeder yang memuat kredensial demo / data sintetis: `EssentialUserSeeder`, `UserSeeder`, `OrangTuaKaryawanSeeder`, `SiswaSeeder`, `SarprasPengadaanDemoSeeder`, dan `AkunPendaftarSeeder`.

4. **Kerapian Relasi & Validasi Workflow (Task 4, Commit `cd4727b`)**:
   - Menambahkan doc-comment penjelas pada `database/seeders/RolePermissionSeeder.php`.
   - Mengonsolidasikan `SarprasPengadaanDemoSeeder.php` agar tidak menduplikasi pembuatan role dan `givePermissionTo` yang sudah ditangani pivot seeder.
   - Menambahkan validasi `assertRoleExists(...)` di `database/seeders/WorkflowDefinitionSeeder.php` untuk memastikan role approver valid di runtime.

5. **Cleanup Low-Priority & Tanggal Relatif (Task 5, Commit `7c9fb94`)**:
   - Membuat migration `database/migrations/2026_08_21_000001_cleanup_legacy_permissions_and_sync_pivot.php` untuk membersihkan permission orphan (`sarpras.manage`, `pengadaan.manage`) dan mereset cache Spatie.
   - Mengganti tanggal statis tahun 2026 menjadi kalkulasi dinamis `now()->addDays(...)` di `database/seeders/SeleksiPpdbSeeder.php`.
   - Memperjelas pesan peringatan di `database/seeders/KeuanganDemoSeeder.php`.

6. **Dokumentasi Konvensi & Sinkronisasi Test (Task 6, Commit `76087a8` & `184427d`)**:
   - Menulis standar baku seeder di `.agents/skills/seeder-standard/SKILL.md`.
   - Menyesuaikan fallback `PermissionSeeder` di `RoleSeeder` dan mengupdate ekspektasi test count (134 permissions, 12 roles).
   - Menjalankan verifikasi lokal `migrate:fresh --seed` dan full test suite (1893+ tests passing).

---

## 2. Keputusan Penting yang Diambil

1. **Fallback `PermissionSeeder` pada `RoleSeeder`**:
   Untuk unit test terisolasi yang hanya memanggil `(new RoleSeeder())->run()` tanpa seeding permissions terlebih dahulu, `RoleSeeder` mengecek `if (Permission::count() === 0) { (new PermissionSeeder())->run(); }`. Hal ini mencegah `PermissionDoesNotExist` dari Spatie saat `RoleSeeder` memberikan baseline permissions, tanpa mengganggu alur `DatabaseSeeder` utama.
2. **Penetapan `yayasan_super_admin` Permissions**:
   `yayasan_super_admin` otomatis diberikan snapshot `Permission::all()` pada `RoleSeeder` saat roles dibuat, dan disinkronkan kembali di tahap akhir `DatabaseSeeder` via `RolePermissionAssignmentSeeder`.
3. **Penyelarasan NIK Dummy `EssentialUserSeeder`**:
   Tabel `users` tidak memiliki kolom `nik` (NIK tersimpan pada entitas profil `Guru` dan `OrangTua`, sedangkan `users` menggunakan kolom `username`/`email`). Oleh karena itu, pembuatan akun `superadmin@sistem.test` tetap menggunakan kolom standar `users`.

---

## 3. Hal yang Perlu Direview Manusia / Claude

1. **Status Produksi Seeder Downstream**:
   Pada `APP_ENV=production`, seeder akun demo dilewati. Sebagian seeder hilir (seperti `GuruSeeder`) melakukan `User::where(...)->firstOrFail()`. Jika di kemudian hari `DatabaseSeeder` dijalankan pada environment produksi murni tanpa data pengguna awal yang di-create sebelumnya via form/wizard, perlu dipertimbangkan seeder khusus akun produksi non-demo.
2. **Git State Saat Ini**:
   - Branch: `rbac-v2`
   - Semua perubahan tersimpan rapi dalam commit-commit atomik di branch lokal `rbac-v2`.
   - Belum di-push ke remote origin (dapat di-push/merge sesuai arahan user).
