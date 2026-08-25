# Handoff Log: Redesain Form Create/Edit Pengguna (Multi-Role Checkbox & Redirect Siswa/Orang Tua)

**Tanggal**: 2026-08-25  
**Branch**: `rbac-v2`  
**Spec**: [`.agents/specs/2026-08-25-form-pengguna-multirole-redesign.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-25-form-pengguna-multirole-redesign.md)  
**Plan**: [`.agents/plans/2026-08-25-form-pengguna-multirole-redesign.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-25-form-pengguna-multirole-redesign.md)  
**Baseline Commit**: `af0bade` / `9322144`  

---

## 1. Apa yang Dikerjakan

Sub-project ini menyelesaikan 7 masalah usability dan integritas data pada form Create/Edit Pengguna (`admin/users`), tampilan profil akun, dan tabel daftar pengguna:
1. **Dukungan Multi-Role Checkbox**: Mengganti `<select name="role">` tunggal dengan checkbox `<input type="checkbox" name="roles[]">` yang dikelompokkan per scope (`Platform`, `Yayasan`, `Lembaga`, `Staf`).
2. **Eliminasi Bug Destruktif `syncRoles()`**: `UserController::update()` kini mempertahankan dan memastikan role baseline carrier (`pegawai_lembaga`) tetap terikat pada user ber-`lembaga_id` ketika mengedit akun.
3. **Auto-Assignment Role Carrier Berbasis `scope_level`**: `UserController::baselineCarrierRole()` otomatis menambahkan `pegawai_lembaga` saat role fungsional ber-`scope_level` `lembaga` atau `diri_sendiri` dipilih untuk user yang memiliki `lembaga_id`. Role carrier tidak pernah ditampilkan sebagai checkbox.
4. **Pencegahan Pembuatan/Edit Siswa & Orang Tua dari Form Pengguna**: Validasi menolak role `siswa`, `orang_tua`, `pegawai_lembaga`, dan `pegawai_yayasan`. Method `edit()`, `update()`, dan `toggleActive()` pada `UserController` kini me-return `404` jika target user memiliki role `siswa` atau `orang_tua`.
5. **Redirect Edit Siswa & Orang Tua di Daftar Pengguna**: Aksi "Edit Akun" pada baris siswa dialihkan ke `admin.siswa.edit` (`/admin/siswa/{siswa}/edit`), dan baris orang tua dialihkan ke `admin.orang-tua.edit` (`/admin/orang-tua/{orangTua}/edit`). Tombol toggle status akun disembunyikan untuk kedua role tersebut.
6. **Perbaikan Tampilan Role Menggunakan `User::functionalRoles()`**: Tampilan role di daftar pengguna, hero card edit, dan tab profil menggunakan accessor `functionalRoles()` sehingga role carrier teknis (`pegawai_lembaga`, `pegawai_yayasan`) tidak membingungkan pengguna UI.
7. **Pembersihan Dead Code**: Menghapus kondisi lama `@if ($targetUser->roles->first()?->name === 'Lembaga / Sekolah')` di tab profil dan menggantinya dengan pengecekan `$targetUser->lembaga_id`.

---

## 2. Rincian Task & Riwayat Commit

| Task | Deskripsi | Commit |
|------|-----------|--------|
| **Task 1** | Accessor `User::functionalRoles()` mengecualikan role scope-carrier (`pegawai_lembaga`, `pegawai_yayasan`) + Unit test | `e2a5a82` |
| **Task 2** | Helper `assignableRoles()`, `formRoleGroups()`, `groupRolesForForm()`, `baselineCarrierRole()` di `UserController` | `c4907d1` |
| **Task 3** | Update `create()` & `store()` di `UserController` (validasi `roles[]`, auto-assign baseline carrier, tolak role terlarang) | `21694b5` |
| **Task 4** | Update `edit()`, `update()`, `toggleActive()` di `UserController` (multi-role update, guard `orang_tua`, cegah hapus carrier) | `93bc089`, `f69e338` |
| **Task 5** | Test regresi bug destruktif `syncRoles`, multi-role, baseline carrier auto-assignment, dan validasi role terlarang | `e0575a3` |
| **Task 6** | Test guard `404` pada `edit`, `update`, dan `toggleActive` untuk user `orang_tua` | `10d455e` |
| **Task 7** | Blade `resources/views/admin/users/_form.blade.php`: Checkbox multi-role terkelompok per scope | `2d9d64f` |
| **Task 8** | Blade `edit.blade.php` & `tabs/profil.blade.php`: Ganti `roles->first()` ke `functionalRoles()`, hapus dead code `Lembaga Tertaut` | `1de464e` |
| **Task 9** | Blade `_daftar.blade.php`: Redirect Edit siswa/orang tua ke modul masing-masing, sembunyikan toggle-active, pakai `functionalRoles()` | `5712490` |
| **Task 10** | Feature test `UserPenggunaFormRedesignTest.php`: Verifikasi redirect link siswa/ortu, hide toggle-active, dan profile tab Lembaga Tertaut | `36d9ece` |

---

## 3. Hasil Verifikasi

### 3.1 Grep Verifikasi Kode & Template Lama
- `grep -rn "name=\"role\"\|'role' =>" app/Http/Controllers/Admin/UserController.php resources/views/admin/users/` → **0 kemunculan** (bersih, semua sudah `roles`/`roles[]`).
- `grep -rn "roles->first\|Lembaga / Sekolah" resources/views/admin/users/` → **0 kemunculan** (bersih).

### 3.2 Test Scoped (Task 11 Step 2)
Perintah:
```bash
php artisan test tests/Unit/UserScopeTest.php tests/Feature/Admin/UserManagementTest.php tests/Feature/Admin/UserPenggunaScopeChipTest.php tests/Feature/Admin/UserPenggunaFormRedesignTest.php tests/Feature/Admin/OrangTuaCrudTest.php
```
Hasil: **61 passed (177 assertions)**, 0 failed, 17.04s.

### 3.3 Full Test Suite (Task 11 Step 4)
Perintah:
```bash
php artisan test
```
Hasil: **2101 passed (5866 assertions)**, 0 failed, Duration: 583.18s.

---

## 4. Keputusan Penting yang Diambil

1. **Auto-Assignment Carrier Berbasis `scope_level`**: Menggunakan logika `$needsCarrier = $selectedRoles->contains(fn ($role) => in_array($role->scope_level, ['lembaga', 'diri_sendiri'], true))` dan mengecek `$lembagaId !== null` untuk menentukan auto-assignment `'pegawai_lembaga'`. Tidak menggunakan hardcode daftar nama role sehingga aman dan extensible untuk penambahan role fungsional baru.
2. **Scope Carrier `pegawai_yayasan` Tidak Di-assign dari Form Ini**: Karena form pengguna ini diperuntukkan bagi staf yang terikat pada lembaga atau role super-admin langsung, `pegawai_yayasan` (pool yayasan) tidak pernah di-generate dari form ini.
3. **Pemisahan Modul Siswa & Orang Tua**: Alih-alih membiarkan data siswa/orang tua di-edit secara setengah-setengah dari form generic pengguna (yang berisiko merusak relasi dan data profil), form pengguna memblokir role tersebut dan tabel daftar langsung mengarahkan ke modul profil masing-masing.

---

## 5. Hal yang Perlu Direview Manusia / Claude

- **Status Git**: Semua commit berada di branch `rbac-v2`, working tree bersih.
- **Relasi Model Siswa/Orang Tua**: Link edit pada tabel pengguna memanfaatkan relasi `$user->siswa` dan `$user->orangTua` yang sudah ada di model. Jika sebuah user memiliki role `siswa`/`orang_tua` namun belum memiliki profil terkait, dropdown aksi secara aman tidak menampilkan tombol edit yang broken.
