# Pembersihan Seeder — Sub-project 1: RBAC — Design Spec

**Tanggal:** 2026-07-17
**Status:** Disetujui, siap masuk writing-plans

## 1. Latar Belakang & Tujuan

Ini adalah sub-project 1 dari 3 inisiatif "pembersihan arsitektur seeder": memecah seeder monolitik yang ada sekarang (`DemoDataSeeder` menyentuh ~15 tabel sekaligus, `M3DemoDataSeeder`/`PembayaranDemoSeeder` menyentuh ~15 tabel transaksional lainnya) jadi satu seeder per tabel, mengikuti konvensi standar Laravel.

Sub-project ini secara spesifik menutup permintaan kedua: seeder RBAC khusus yang mendaftarkan **semua** permission yang ada, **role**, dan **akun real yang sementara dibutuhkan** — terpisah dari data master/referensi (Sub-project 2, belum dimulai) dan data skenario/transaksional (Sub-project 3, belum dimulai). RBAC dikerjakan lebih dulu karena semua seeder lain (di kedua sub-project berikutnya) butuh Role untuk `assignRole()` pada akun yang mereka buat.

## 2. Lingkup

**Termasuk:**
- `database/seeders/PermissionSeeder.php` — mendaftarkan seluruh 51 permission yang ada sekarang (isi persis dari `RolePermissionSeeder::$permissions`), tanpa logic pembersihan permission legacy (`manage-roles`, `manage-users`, dst) — logic itu cuma relevan untuk upgrade database lama, tidak relevan untuk seeder bersih yang ditujukan untuk `migrate:fresh --seed`.
- `database/seeders/RoleSeeder.php` — membuat 5 role (`yayasan_super_admin`, `kepala_sekolah`, `admin_administrasi`, `admin_keuangan`, `guru`) beserta `scope_level`/`is_protected`, dan mengaitkan permission ke tiap role persis seperti logic `givePermissionTo` yang sudah ada di `RolePermissionSeeder`.
- `database/seeders/EssentialUserSeeder.php` — 5 akun minimal (satu per role), email generik terpisah dari akun demo per-lembaga yang sudah ada, ditujukan murni untuk memverifikasi tiap role bisa login & berfungsi.
- `RolePermissionSeeder.php` (sudah ada) **dipertahankan**, diubah jadi pemanggil tipis (`PermissionSeeder` lalu `RoleSeeder` secara berurutan) — supaya seluruh test file yang sudah memanggilnya langsung (banyak, di seluruh sub-project sebelumnya) tetap valid tanpa perlu ditulis ulang.
- Update `database/seeders/DatabaseSeeder.php`: `PermissionSeeder` dan `RoleSeeder` dipanggil di awal (menggantikan posisi `RolePermissionSeeder` yang sekarang). `EssentialUserSeeder` dipanggil **setelah** `LembagaSeeder` — yang belum ada, akan dibuat di Sub-project 2 — jadi posisi pemanggilan `EssentialUserSeeder` di `DatabaseSeeder` untuk sementara diletakkan di akhir urutan yang ada sekarang (setelah `M3DemoDataSeeder`), dan akan dipindah lebih awal (tepat setelah `LembagaSeeder`) saat Sub-project 2 selesai.

**Tidak termasuk (ditangani sub-project lain):**
- `LembagaSeeder`, `GuruSeeder`, `TahunAjaranSeeder`, dan seluruh seeder data master/referensi lain — Sub-project 2.
- `CalonMuridSeeder`, `PendaftaranSeeder`, `TagihanSeeder`, dan seluruh seeder data skenario/transaksional — Sub-project 3, menggantikan `M3DemoDataSeeder`/`PembayaranDemoSeeder`.
- Akun demo per-lembaga yang sudah ada (`kepsek.smp@alhikmah.sch.id`, dst, dipakai di seluruh panduan manual testing yang sudah ditulis) — **tidak dihapus**, dipindah ke Sub-project 2 (bagian dari data master per-lembaga), bukan diganti oleh `EssentialUserSeeder`.

## 3. Detail `EssentialUserSeeder`

5 akun, email generik (bukan milik lembaga tertentu), password `password`, satu per role:

| Role | Email | Nama |
|---|---|---|
| yayasan_super_admin | superadmin@sistem.test | Admin Sistem |
| kepala_sekolah | kepsek@sistem.test | Kepala Sekolah (Contoh) |
| admin_administrasi | adm@sistem.test | Admin Administrasi (Contoh) |
| admin_keuangan | keuangan@sistem.test | Admin Keuangan (Contoh) |
| guru | guru@sistem.test | Guru (Contoh) |

`superadmin@sistem.test` (yayasan-scoped) selalu dibuat, tidak butuh `lembaga_id`. 4 akun lainnya (lembaga-scoped) di-attach ke `Lembaga::first()`. Kalau belum ada `Lembaga` sama sekali saat seeder ini dijalankan, 4 akun itu **dilewati** dengan pesan info di console (`$this->command?->warn(...)`), bukan error — supaya seeder ini tetap bisa dijalankan sendiri (`php artisan db:seed --class=EssentialUserSeeder`) di database yang belum ada lembaga-nya sama sekali, tanpa exception. Semua pakai `firstOrCreate(['email' => ...], [...])` + `assignRole()` — idempoten.

## 4. Rencana Pengujian

- `PermissionSeeder`: jumlah permission persis 51 setelah dijalankan; idempoten (dijalankan dua kali tidak menghasilkan duplikat).
- `RoleSeeder`: 5 role dengan `scope_level` yang benar; tiap role punya permission yang benar sesuai daftar yang sudah divalidasi di `RolePermissionSeederTest.php` yang sudah ada (mis. `admin_keuangan` dapat semua permission `jenis-tagihan.*`/`tagihan.*`/`pembayaran.*`/`cicilan.*` + `spmb-pendaftaran.view`).
- `EssentialUserSeeder`: dengan `Lembaga` tersedia → 5 akun dengan role yang benar, 4 di antaranya punya `lembaga_id` terisi; tanpa `Lembaga` sama sekali → cuma `superadmin@sistem.test` yang dibuat, tidak error; idempoten.
- Regresi: `RolePermissionSeederTest.php` yang sudah ada tetap lolos tanpa modifikasi (`RolePermissionSeeder` tetap berperilaku sama persis, cuma didelegasikan ke 2 seeder baru).

## 5. Non-Tujuan / Catatan

- Posisi pemanggilan `EssentialUserSeeder` di `DatabaseSeeder` bersifat sementara sampai Sub-project 2 (`LembagaSeeder`) selesai — dicatat eksplisit di komentar kode supaya tidak terlupa saat itu tiba.
- Sub-project 2 dan 3 masing-masing akan brainstorming & spec terpisah setelah sub-project ini selesai.
