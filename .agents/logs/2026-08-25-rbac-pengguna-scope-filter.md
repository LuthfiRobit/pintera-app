# Handoff Log: Halaman Pengguna Filter Scope Chip & Visibilitas Lintas-Tenant Platform Admin

**Tanggal**: 2026-08-25  
**Branch**: `rbac-v2`  
**Spec**: [`.agents/specs/2026-08-25-rbac-pengguna-scope-filter.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-25-rbac-pengguna-scope-filter.md)  
**Plan**: [`.agents/plans/2026-08-25-rbac-pengguna-scope-filter.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-25-rbac-pengguna-scope-filter.md)  

---

## 1. Apa yang Dikerjakan

Menyempurnakan halaman Pengguna (`admin.users.index`) dan fondasi RBAC scope level:
1. **Fondasi Scope Awareness**:
   - `User::widestScopeLevel()` sekarang mengenali `'platform'` sebagai scope tertinggi.
   - `TenantScope::apply()` menambahkan bypass khusus untuk model `User` saat acting user berscope `'platform'` (model lain seperti `Karyawan`, `Siswa`, dll tetap terbatasi tenant).
   - `UserController::scopeRank()` memberikan rank `4` untuk `'platform'` (di atas `'yayasan' => 3`).
2. **Backend Query & Filter Halaman Pengguna**:
   - `UserController::index()` menerima parameter `scope_group` (`platform`, `yayasan`, `lembaga`, `staf`, `orang_tua`, `siswa`).
   - Pencarian diperluas mencakup `username` selain `name` dan `email`.
   - Role `siswa` tidak lagi dikecualikan secara permanen dari daftar Pengguna; siswa tampil pada chip "Semua" dan chip "Siswa".
   - Menghitung 7 badge count per chip scope secara dinamis sesuai isolasi tenant viewer.
   - Mengirim `$isPlatformViewer` ke view untuk kontrol kolom kondisional.
3. **Frontend Blade & Alpine.js**:
   - `resources/views/admin/users/index.blade.php`: Menampilkan 7 tombol chip filter dengan badge hitungan dan placeholder search terupdate ("Cari nama, email, atau username...").
   - `resources/views/admin/users/_daftar.blade.php`: Menampilkan kolom "Yayasan" khusus jika viewer adalah `platform_super_admin`.
   - `resources/js/data-table-filter.js`: Menambahkan method `setScopeGroup()` dan `refreshRoleOptions()` untuk memperbarui opsi dropdown role secara dinamis saat chip berganti (tetap 100% backward-compatible untuk halaman lain).
4. **Testing Suite**:
   - Menambahkan unit test untuk platform scope di `tests/Unit/UserScopeTest.php` dan `tests/Unit/TenantScopePlatformBypassTest.php`.
   - Memperbarui test existing `tests/Feature/Admin/UserManagementTest.php`.
   - Menambahkan feature test komprehensif di `tests/Feature/Admin/UserPenggunaScopeChipTest.php`.

---

## 2. Daftar Commit

| Task | Commit Hash | Pesan Commit |
|---|---|---|
| Task 1 | `a41ad31` | `feat(rbac): widestScopeLevel() mengenali scope platform` |
| Task 2 | `79436b9` | `feat(rbac): TenantScope bypass khusus model User untuk scope platform` |
| Task 3 | `0344453` | `feat(rbac): scopeRank() beri platform rank tertinggi (4)` |
| Task 4+5 | `4682a11` | `feat(rbac): UserController index tambah scope_group filter, search username, siswa tidak lagi selalu dikecualikan` |
| Task 6 | `1557317` | `feat(rbac): tambah 7 chip filter scope + placeholder search username di halaman Pengguna` |
| Task 7 | `8833c64` | `feat(rbac): kolom Yayasan kondisional di tabel Pengguna untuk viewer platform_super_admin` |
| Task 8 | `96554c4` | `feat(rbac): dataTableFilter tambah setScopeGroup() + refresh opsi role dinamis` |
| Task 9 | `7e6c371` & `ab35d85` | `test(rbac): perbaiki resolusi yayasan lembaga dan tuntaskan test chip scope Pengguna` |

---

## 3. Keputusan Penting yang Diambil

1. **Bypass TenantScope Sangat Terisolasi**:
   Bypass `TenantScope` hanya dieksekusi jika `$actingUser->widestScopeLevel() === 'platform' && $model instanceof User`. Model `Karyawan`, `Siswa`, dan model tenant-scoped lainnya tetap terisolasi penuh secara fail-closed.
2. **Resolusi Nama Yayasan pada Tabel**:
   Untuk menampilkan nama yayasan bagi viewer platform secara lengkap dan null-safe, kolom Yayasan membaca `$user->yayasan?->nama ?? $user->lembaga?->yayasan?->nama ?? '—'`. Ini menangani baik akun pool yayasan (`user.yayasan_id`) maupun akun berbasis lembaga (`user.lembaga.yayasan_id`). Controller juga melakukan eager-loading `with('roles', 'lembaga.yayasan', 'yayasan')` untuk mencegah N+1 query.
3. **Penyesuaian Inklusi Siswa**:
   Siswa sekarang tampil pada daftar Pengguna default ("Semua") dan chip "Siswa" sebagai entitas user dasar (nama, email, username, status), tanpa menggantikan modul detail akademik di "Data Siswa". Stat card "Total Akun" pada halaman Pengguna mencakup seluruh akun termasuk siswa.
4. **Alpine & TomSelect Dinamis**:
   Pola filter chip meng-extend `dataTableFilter()` yang sudah ada tanpa membuat komponen terpisah. Perubahan opsi dropdown TomSelect saat chip dipilih dihandle melalui `refreshRoleOptions()` sehingga ramah performa dan tetap konsisten di seluruh halaman aplikasi.

---

## 4. Bukti Verifikasi

1. **Grep Audit Clean**:
   - `grep -n "whereDoesntHave.*siswa" app/Http/Controllers/Admin/UserController.php` → **0 hasil (bersih)**.
2. **Scoped Plan Tests**:
   - Perintah: `php artisan test tests/Unit/UserScopeTest.php tests/Unit/TenantScopePlatformBypassTest.php tests/Feature/Admin/UserManagementTest.php tests/Feature/Admin/UserPenggunaScopeChipTest.php`
   - Hasil: **31 passed (74 assertions)**, durasi: 14.00s.
3. **Frontend Build**:
   - Perintah: `npm.cmd run build`
   - Hasil: **Vite build sukses tanpa error** (3.06s).
4. **Full Test Suite SOLO**:
   - Perintah: `php artisan test`
   - Hasil: **2085 passed (5818 assertions)**, 0 failed, durasi: 911.49s (15m 11s).

---

## 5. Hal yang Perlu Direview Manusia / Claude

- **Branch State**: Seluruh commit berada di branch `rbac-v2` dan siap di-merge/PR jika diinginkan user.
- **Tidak ada open issue atau technical debt yang tertinggal**.
