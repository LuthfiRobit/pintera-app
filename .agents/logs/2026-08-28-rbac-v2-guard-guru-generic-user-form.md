# Handoff Log — RBAC v2 — Guard Role `guru` di Form Pengguna Generik

- **Spec**: [.agents/specs/2026-08-28-rbac-v2-guard-guru-generic-user-form.md](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-28-rbac-v2-guard-guru-generic-user-form.md)
- **Plan**: [.agents/plans/2026-08-28-rbac-v2-guard-guru-generic-user-form.md](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-28-rbac-v2-guard-guru-generic-user-form.md)

---

## Apa Yang Dikerjakan

1. **Task 1: Keluarkan `guru` dari `assignableRoles()`** (`2590cce3`)
   - Menambahkan `'guru'` ke daftar `$excluded` di `UserController::assignableRoles()` ([UserController.php](file:///d:/laragon/www/pintera-app/app/Http/Controllers/Admin/UserController.php)).
   - Menambahkan feature test di [UserManagementTest.php](file:///d:/laragon/www/pintera-app/tests/Feature/Admin/UserManagementTest.php) untuk memverifikasi bahwa `guru` tidak lagi muncul di options form create/edit, sementara `guru_bk` dan `wali_kelas` tetap tampil.

2. **Task 2: Server-side Guard di `store()`** (`d71fe229`)
   - Menambahkan guard eksplisit di `UserController::store()` yang menolak payload jika `roles` mengandung `'guru'` dengan pesan: `'Role Guru harus dibuat melalui Admin → Guru agar profil Guru dibuat dan tertaut dengan benar.'`.
   - Menambahkan 2 unit/feature test di [UserManagementTest.php](file:///d:/laragon/www/pintera-app/tests/Feature/Admin/UserManagementTest.php) (uji coba role `guru` tunggal dan kombinasi `guru` + role lain).

3. **Task 3: Server-side Guard & Preservasi Paksa di `update()`** (`72a900ea`)
   - Menambahkan guard penolakan penambahan `'guru'` baru di `UserController::update()`.
   - Menambahkan logika preservasi paksa (`$rolesToPersist`): jika user target sudah memiliki role `guru`, role `guru` akan otomatis dipertahankan dan disertakan ke `syncRoles()` serta perhitungan `baselineCarrierRole()` meskipun request form edit tidak menyertakan `guru` (karena checkbox `guru` sudah tidak ada di UI).
   - Menambahkan 2 test di [UserManagementTest.php](file:///d:/laragon/www/pintera-app/tests/Feature/Admin/UserManagementTest.php) untuk menguji penolakan update penambahan `guru` dan preservasi role `guru` + carrier `pegawai_lembaga`.

4. **Task 4: Rewrite 2 Test Lama & Acceptance Tests** (`1653a460`)
   - Mengganti 2 test lama yang sebelumnya melakukan submit `roles: ['guru']` secara eksplisit dengan pengujian valid (self-healing missing carrier saat menambah role non-guru, dan multi-role shared carrier `['wakasek_kurikulum', 'admin_sdm']`).
   - Menambahkan 2 test acceptance untuk membuktikan role `guru_bk` dan `wali_kelas` tetap bebas dibuat mandiri melalui form Pengguna generik.

5. **Task 5: Regression & Formatting Verification**
   - Menjalankan suite pengujian `UserManagementTest.php` dan `RoleSeederTest.php` (total **44 passed**, **0 failed**, 169 assertions).
   - Memastikan tidak ada pelanggaran code style via `vendor/bin/pint --dirty --format agent`.

---

## Keputusan Penting yang Diambil

- **Pemisahan `$data['roles']` vs `$rolesToPersist`**:
  Guard penolakan di `update()` secara ketat menggunakan `$data['roles']` (input asli yang dikirim admin) sehingga penambahan baru role `guru` ditolak. Sedangkan proses resolusi scope dan `syncRoles()` menggunakan `$rolesToPersist` agar role `guru` yang sudah ada pada user tidak hilang saat admin hanya memperbarui role fungsional lainnya.
- **Isolasi Guard Hanya Pada Role `guru`**:
  Sesuai hasil audit dan spec §1.2 & §4.3-4.4, guard ini secara sengaja **tidak** diterapkan pada `guru_bk` atau `wali_kelas` karena keduanya tidak memiliki dependensi foreign key ke tabel/profil `guru`.

---

## Hal yang Masih Perlu Direview Manusia / Claude

- **Git State**:
  Terdapat 4 commit baru di branch `rbac-v2` yang belum di-push ke remote.
- **Konfirmasi Perilaku**:
  Semua akun Guru sekarang hanya dapat dibuat secara transactional melalui menu `Admin → Guru`. Form `Admin → Pengguna` terkunci dari manipulasi penambahan role `guru` secara langsung.
