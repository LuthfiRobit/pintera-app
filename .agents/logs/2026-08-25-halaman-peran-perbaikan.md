# Handoff Log: Perbaikan Halaman Peran (Keamanan Nama Role, Scope Platform, Chip Filter, & UX Matriks)

**Tanggal**: 2026-08-25  
**Branch**: `rbac-v2`  
**Spec**: [2026-08-25-halaman-peran-perbaikan.md](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-25-halaman-peran-perbaikan.md)  
**Plan**: [2026-08-25-halaman-peran-perbaikan.md](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-25-halaman-peran-perbaikan.md)  

---

## 1. Apa yang Dikerjakan

Menyelesaikan 11 task perbaikan pada modul manajemen peran (`admin.roles.*`) yang mencakup penutupan celah keamanan nama role protected, standardisasi dukungan scope `platform`, dan modernisasi UI/UX halaman Peran:

1. **Task 1 (`f1b6ad8`)**: Menambahkan guard pada model `Role::saving()` agar melempar `RuntimeException` jika field `name` dari role yang dilindungi (`is_protected = true`) dicoba untuk diubah.
2. **Task 2 (`8d8e5c9`)**: Memodifikasi `RoleController::update()` agar mengecualikan field `name` dari validasi dan mutasi jika `$role->is_protected`, sehingga tidak memicu HTTP 500 (lapis pertahanan pertama sebelum guard model).
3. **Task 3 (`b2b7a3b`)**: Memperbarui `RoleController::scopeRank()` untuk memetakan `platform` ke ranking tertinggi (4) serta memperbarui validasi `store()` dan `update()` agar menerima nilai `platform`.
4. **Task 4 (`7daf1a1`)**: Mengirimkan variabel `$isPlatformActor` ke view `create` dan `edit`, serta menampilkan opsi `<option value="platform">` hanya jika pengguna yang login memiliki scope `platform`.
5. **Task 5 (`1ab25bb`)**: Mengunci input nama role di [edit.blade.php](file:///d:/laragon/www/pintera-app/resources/views/admin/roles/edit.blade.php) (`::disabled="isProtected"`) untuk role yang dilindungi disertai pesan penjelas.
6. **Task 6 (`05aa266`)**: Menghitung jumlah role untuk scope `platform` (`$totalPlatform`) dan `diri_sendiri` (`$totalDiriSendiri`), serta menerapkan eager-loading terbatas 5 permission pada `RoleController::index()`.
7. **Task 7 (`c01b6ee`)**: Mengganti 3 stat card menjadi 5 stat card di [index.blade.php](file:///d:/laragon/www/pintera-app/resources/views/admin/roles/index.blade.php) serta mengganti select dropdown tradisional dengan 5 Scope Chip Filter interaktif (`Semua`, `Platform`, `Yayasan`, `Lembaga`, `Diri Sendiri`).
8. **Task 8 (`a7e8897`)**: Menampilkan nama role dalam format Title Case pada tabel [_daftar.blade.php](file:///d:/laragon/www/pintera-app/resources/views/admin/roles/_daftar.blade.php), mengubah angka kolom *Users* menjadi tautan langsung ke filter halaman Pengguna (`admin.users.index?role={name}`), dan menambahkan tooltip hover pada kolom *Permissions*.
9. **Task 9 (`a07572f`, `a46fc7a`)**: Menambahkan state `permissionSearch` dan fungsi `filteredModuleGroups()` di [role-form.js](file:///d:/laragon/www/pintera-app/resources/js/role-form.js) untuk fitur pencarian izin (live search) di matriks hak akses, mendesain ulang layout header matriks menjadi dua baris (Row 1: teks & 2 tombol [Sync + Switcher Pilih Semua/Kosongkan], Row 2: search input), serta menambahkan blok edukatif penjelasan scope level di form create dan edit.
10. **Task 10**: Menjalankan checklist verifikasi manual browser secara komprehensif menggunakan browser agent.
11. **Task 11**: Menjalankan verifikasi akhir (grep, scoped tests, full test suite) dan mendokumentasikan log serah terima.

---

## 2. Keputusan Penting yang Diambil

1. **Penguncian 3-Lapis (Defense in Depth) untuk Nama Role Protected**:
   - *Lapis 1 (UI)*: Input `name` pada [edit.blade.php](file:///d:/laragon/www/pintera-app/resources/views/admin/roles/edit.blade.php) berstatus `disabled` dan berlatar abu-abu jika `isProtected` bernilai true.
   - *Lapis 2 (Controller)*: `RoleController::update()` tidak memasukkan `name` ke dalam array rules validasi atau pengisian model `$role->name` ketika role berstatus protected.
   - *Lapis 3 (Model)*: Hook `Role::saving()` melempar exception `RuntimeException` jika atribut `name` kotor (`isDirty('name')`) pada record protected yang sudah ada di database.
   - *Catatan Otorisasi*: Pengaturan permission (`syncPermissions`) untuk role protected tetap diperbolehkan (tidak dikunci).

2. **Sinkronisasi `scopeRank()` Antar Controller**:
   - Menyamakan implementasi `RoleController::scopeRank()` dengan `UserController::scopeRank()` sehingga `platform` = 4, `yayasan` = 3, `lembaga` = 2, dan `diri_sendiri` = 1.

3. **Format Tampilan Nama vs Nilai Teknis**:
   - Tampilan nama role pada UI (Halaman Peran & Halaman Pengguna) didekorasi menggunakan `ucwords(str_replace('_', ' ', $role->name))` (Title Case), sedangkan identifier teknis database dan routing tetap mempertahankan nilai string asli `snake_case`.

4. **Konsistensi Tampilan di Halaman Pengguna**:
   - Untuk menjaga konsistensi, seluruh tampilan role fungsional di halaman Pengguna (tabel kolom *Role*, dropdown select filter, header hero card profile, dan detail data akses di tab *Profil & Identitas*) telah diselaraskan ke format Title Case yang bersih.

---

## 3. Hasil Pengujian & Verifikasi

### A. Verifikasi Kode (Grep Checks)
- `grep "in:yayasan,lembaga,diri_sendiri'" app/Http/Controllers/Admin/RoleController.php`: **0 matches** (Semua validasi scope level sudah menyertakan `platform`).
- `grep "<select x-model=\"filters.scope\"" resources/views/admin/roles/index.blade.php`: **0 matches** (Filter dropdown lama telah bersih digantikan oleh Chip Filter).

### B. Pengujian Scoped (Unit & Feature Terkait)
Command: `php artisan test tests/Unit/RoleModelGuardTest.php tests/Feature/Admin/RoleBuilderTest.php tests/Feature/Admin/PermissionAuditTest.php tests/Feature/Admin/RoleFormAuditBannerTest.php`
- **Hasil**: **38 passed (100 assertions)**
- **Durasi**: ~15.3 detik

### C. Pengujian Penuh (Full Test Suite)
Command: `php artisan test`
- **Hasil**: **2116 passed (5899 assertions), 0 failed**
- **Durasi**: ~549.9 detik (9 menit 9 detik)

### D. Checklist Manual Browser (Task 10)
Verifikasi dilakukan secara otomatis via browser agent (rekaman video: `roles_page_verification_1787642172946.webp`):
1. **5 Stat Card**: Total Roles (19), Scope Platform (1), Scope Yayasan (3), Scope Lembaga (8), Diri Sendiri (7) tampil lengkap.
2. **5 Scope Chip**: Filter Semua, Platform, Yayasan, Lembaga, dan Diri Sendiri merender tabel secara reaktif via AJAX dan meng-highlight chip aktif.
3. **Title Case**: Nama-nama role tampil rapi (contoh: "Kepala Sekolah", "Wakasek Kurikulum", "Yayasan Super Admin").
4. **Tooltip Permissions**: Hover pada angka izin menampilkan daftar nama permission awal + jumlah sisanya.
5. **Link Users**: Mengklik angka pengguna pada baris role berhasil mengarahkan ke `/admin/users?role={role_name}` dengan filter role aktif.
6. **Form Edit Protected**: Membuka role `yayasan_super_admin` menampilkan input nama berstatus disabled, bertuliskan pesan proteksi sistem, dan scope level berstatus terkunci.
7. **Form Create Scope Level**: Opsi "Platform" tidak muncul untuk user yayasan admin, dan blok edukatif 4 tingkatan scope tampil jelas di bawah dropdown.
8. **Live Search Matriks**: Mengetik kata kunci (misal "tagihan" atau "rapor") langsung menyaring modul dan permission yang relevan secara realtime tanpa reload halaman. Menghapus pencarian mengembalikan seluruh modul.

---

## 4. Hal yang Perlu Direview Manusia / Claude

- **Status Git**:
  - Branch: `rbac-v2`
  - Working tree: Clean (seluruh file telah di-commit secara modular per task).
  - Tidak ada perubahan struktural pada database / skema migration.
