# Spec: Redesain Form Create/Edit Pengguna — Multi-Role Checkbox & Redirect Siswa/Orang Tua

**Tanggal**: 2026-08-25
**Branch**: `rbac-v2`
**Terkait**: `.agents/specs/2026-08-25-rbac-pengguna-scope-filter.md` (halaman list Pengguna, SELESAI)

## 1. Latar Belakang & Masalah

Setelah menyempurnakan halaman list Pengguna (chip filter scope, visibilitas lintas-tenant), review terhadap form create/edit (`create.blade.php`, `edit.blade.php`, `_form.blade.php`, `tabs/profil.blade.php`, `UserController::create/store/edit/update`) menemukan beberapa masalah:

1. **Bug destruktif (HIGH)**: `update()` memanggil `$user->syncRoles([$data['role']])` — `syncRoles` mengganti SELURUH role user dengan satu role yang dipilih di form. Form hanya mendukung 1 role. Karena RBAC v2 mewajibkan akun staf lembaga (guru, kepala sekolah, dst) punya role fungsional **+** `pegawai_lembaga`/`pegawai_yayasan` (baseline scope-carrier, lihat `.agents/specs/2026-08-24-rbac-v2-role-taxonomy.md` §5.5, §7), mengedit profil user manapun lewat form ini akan **menghapus diam-diam** baseline carrier-nya tanpa peringatan apa pun.
2. **Role select bukan Tom Select**, padahal sekarang ada 18 role (konvensi project: dropdown >7-10 opsi wajib searchable).
3. **Tampilan "Role Utama" pakai `roles->first()`** yang arbitrary untuk akun multi-role — bisa menampilkan `pegawai_lembaga` (carrier, bukan identitas) alih-alih role fungsional sungguhan (`guru`, dst).
4. **Dead code** di `tabs/profil.blade.php`: `@if ($targetUser->roles->first()?->name === 'Lembaga / Sekolah')` — tidak ada role bernama itu (role names snake_case), kondisi tidak pernah true, field "Lembaga Tertaut" mati total.
5. **Form generik ini membolehkan membuat/mengedit akun `siswa`/`orang_tua`** tanpa mengisi field wajib modul masing-masing (NIS/NISN/kelas untuk siswa via Data Siswa, NIK/siswa-link untuk orang tua via `OrangTuaController`). `siswa` sudah diblokir sebagian (`edit()`/`update()`/`toggleActive()` punya `abort_if($user->hasRole('siswa'), 404)`), tapi `orang_tua` **belum diblokir sama sekali** di backend — celah nyata.
6. **Halaman list Pengguna** (`_daftar.blade.php`) masih menampilkan link "Edit Akun" ke `admin.users.edit` untuk baris `siswa`/`orang_tua` — begitu spec ini diimplementasi (role tersebut dikeluarkan dari pilihan Role), link itu akan mengarah ke form yang tidak bisa lagi memprosesnya dengan benar.
7. **RBAC v2 memang mendukung multi-role fungsional sungguhan** (Spatie `HasRoles`, `assignRole(array)`) — misalnya di sekolah kecil, satu orang bisa merangkap `wakasek_kurikulum` DAN `guru` sekaligus. Form single-select saat ini tidak bisa merepresentasikan itu sama sekali.

## 2. Tujuan

1. Form create/edit Pengguna mendukung **multi-role fungsional** via checkbox, dikelompokkan per kategori scope (Platform/Yayasan/Lembaga/Staf — taksonomi sama persis dengan chip halaman list, lihat spec sebelumnya §4).
2. Role scope-carrier (`pegawai_lembaga`/`pegawai_yayasan`) **tidak pernah ditampilkan/dipilih manual** — di-auto-assign backend berdasarkan `scope_level` role fungsional yang dipilih + apakah user punya `lembaga_id`, meniru logic `AkunKaryawanGenerator` yang sudah ada.
3. `store()`/`update()` menyertakan role fungsional terpilih + baseline carrier SEKALIGUS (bukan replace total) — bug #1 di atas tertutup.
4. `siswa`/`orang_tua` dihapus total dari daftar Role yang bisa dipilih di form ini, DAN diblokir eksplisit di validasi backend (defense-in-depth, bukan cuma UI hiding).
5. Tampilan role (list, checkbox pre-check, tab profil) pakai role fungsional saja (mengecualikan carrier), bukan `roles->first()` yang arbitrary.
6. Dead code perbandingan role dengan string `'Lembaga / Sekolah'` dihapus, diganti kondisi berbasis `$targetUser->lembaga_id` langsung.
7. Halaman list Pengguna: baris `siswa` diarahkan ke `admin.siswa.edit`, baris `orang_tua` diarahkan ke `admin.orang-tua.edit`. Aksi "Aktifkan/Nonaktifkan" dihapus dari dropdown baris ini (dikelola dari modul masing-masing).
8. `edit()`/`update()`/`toggleActive()` diperluas dari `hasRole('siswa')` jadi `hasRole(['siswa', 'orang_tua'])`.

## 3. Non-Goals

- TIDAK mengubah modul "Data Siswa" atau `OrangTuaController` — hanya menambahkan link dari halaman Pengguna KE modul tersebut.
- TIDAK mengubah `create()`'s dukungan pemilihan `lembaga_id` (switcher yayasan-scope) — tetap seperti sekarang.
- TIDAK mengubah `AkunKaryawanGenerator`/`KaryawanController` — form ini (Pengguna) dan form Karyawan adalah 2 alur berbeda yang sudah ada; spec ini hanya menyamakan LOGIC baseline carrier-nya, bukan menyatukan kedua form.
- TIDAK menambahkan kemampuan mengubah `lembaga_id` user existing lewat form edit (di luar cakupan, form edit saat ini memang tidak expose field itu).
- TIDAK mengubah halaman list Pengguna chip/filter (sudah selesai di spec sebelumnya) selain 2 perubahan spesifik di §2 poin 7.

## 4. Taksonomi Checkbox Role (Form Create/Edit)

Sama persis dengan taksonomi chip di `.agents/specs/2026-08-25-rbac-pengguna-scope-filter.md` §4, MINUS carrier role dan MINUS `siswa`/`orang_tua` (keduanya tidak pernah muncul di form ini):

| Grup Checkbox | Role yang ditampilkan |
|---|---|
| **Platform** | `platform_super_admin` |
| **Yayasan** | `yayasan_super_admin`, `bendahara_yayasan` |
| **Lembaga** | `kepala_sekolah`, `wakasek_kurikulum`, `wakasek_kesiswaan`, `operator_akademik`, `admin_sdm`, `bendahara_lembaga`, `admin_sarpras`, `admin_administrasi` |
| **Staf** | `guru`, `wali_kelas`, `guru_bk` |

Grup checkbox HANYA untuk pengelompokan visual — gating "boleh/tidak boleh assign role ini" TETAP per-role individual via `scopeRank($role->scope_level) <= $actingRank` yang sudah ada (karena role dalam 1 grup bisa punya `scope_level` berbeda, mis. `guru` di grup Staf punya `scope_level = diri_sendiri`).

Minimal 1 role wajib dipilih (validasi `required|array|min:1`).

## 5. Logic Baseline Carrier (Backend)

Method privat baru `UserController::baselineCarrierRole(Collection $selectedRoles, ?int $lembagaId): ?string`:
- Kalau ADA role di `$selectedRoles` yang `scope_level` bernilai `lembaga` ATAU `diri_sendiri`, DAN `$lembagaId` tidak null → kembalikan `'pegawai_lembaga'`.
- Selain itu → kembalikan `null` (tidak ada baseline yang ditambahkan; berlaku untuk role scope `yayasan`/`platform` murni, atau kasus tidak ada `lembaga_id`).

Ini SENGAJA berbasis `scope_level` (bukan hardcode daftar nama role) supaya otomatis berlaku untuk role fungsional baru di masa depan (mis. kandidat role `kepala_tu`/`staff_tu` di spec RBAC v2 §14) tanpa perlu edit kode ini lagi.

`pegawai_yayasan` TIDAK pernah di-auto-assign dari form ini (form ini untuk staf ber-`lembaga_id`, bukan alur pool karyawan yayasan — itu tetap domain `AkunKaryawanGenerator`/`KaryawanController` yang tidak disentuh spec ini).

`store()`: `$user->assignRole([...$data['roles'], $baselineRole])` (filter null).
`update()`: `$user->syncRoles([...$data['roles'], $baselineRole])` (filter null) — sekarang `syncRoles` menyertakan baseline juga, tidak lagi menghapusnya.

## 6. Validasi Backend

`store()`/`update()` request validation:
- `'roles' => ['required', 'array', 'min:1']`
- `'roles.*' => ['exists:roles,name', Rule::notIn(['siswa', 'orang_tua', 'pegawai_lembaga', 'pegawai_yayasan'])]`
- Loop tiap role terpilih, tolak (redirect back with error) kalau ADA yang `scopeRank($role->scope_level) > $actingRank` (logic existing dipertahankan, diterapkan per-item bukan sekali untuk satu role).

## 7. Tampilan Role (Accessor Baru)

`App\Models\User::functionalRoles(): \Illuminate\Support\Collection` — `$this->roles->whereNotIn('name', ['pegawai_lembaga', 'pegawai_yayasan'])`. Dipakai di:
- `_daftar.blade.php` kolom Role (ganti `$user->roles->pluck('name')->implode(', ')` → `$user->functionalRoles()->pluck('name')->implode(', ')`).
- `_form.blade.php` checkbox pre-check state saat edit.
- `tabs/profil.blade.php` tampilan "Role / Peran Utama" (jadi list, bukan singular "Utama" — ganti label jadi "Role / Peran Akses" karena sekarang bisa lebih dari satu).

## 8. Dead Code Fix

`tabs/profil.blade.php` — hapus blok:
```blade
@if ($targetUser->roles->first()?->name === 'Lembaga / Sekolah')
    <div class="flex justify-between py-2.5">
        <dt class="text-gray-500">Lembaga Tertaut</dt>
        <dd class="text-gray-900">{{ $targetUser->karyawan?->lembaga?->nama ?: 'Bukan Karyawan Aktif' }}</dd>
    </div>
@endif
```
Ganti dengan kondisi berbasis data langsung:
```blade
@if ($targetUser->lembaga_id)
    <div class="flex justify-between py-2.5">
        <dt class="text-gray-500">Lembaga Tertaut</dt>
        <dd class="text-gray-900">{{ $targetUser->lembaga?->nama ?: '—' }}</dd>
    </div>
@endif
```

## 9. Redirect Siswa/Orang Tua di Halaman List

`_daftar.blade.php`, di dalam `<x-table-actions>` per baris:
- Kalau `$user->hasRole('siswa')`: link Edit → `route('admin.siswa.edit', $user->siswa)` (kalau `$user->siswa` null — data tidak konsisten — sembunyikan link Edit sepenuhnya untuk baris itu, jangan crash).
- Kalau `$user->hasRole('orang_tua')`: link Edit → `route('admin.orang-tua.edit', $user->orangTua)` (null-safe sama).
- Kalau bukan keduanya: link Edit tetap ke `route('admin.users.edit', $user)` seperti sekarang.
- Aksi "Aktifkan/Nonaktifkan" (form toggle-active) HANYA muncul untuk baris BUKAN `siswa`/`orang_tua` — dua kategori itu dikelola statusnya dari modul masing-masing (`orang-tua.update-status` sudah ada; siswa diasumsikan preseden serupa di `SiswaController`, TIDAK diverifikasi ulang di spec ini karena di luar cakupan — kalau ternyata belum ada, itu gap terpisah untuk modul Siswa, bukan tanggung jawab spec ini).

## 10. Guard Backend Diperluas

`UserController::edit()`, `update()`, `toggleActive()`: ganti `abort_if($user->hasRole('siswa'), 404)` → `abort_if($user->hasRole(['siswa', 'orang_tua']), 404)`.

`create()`/`store()`: query `$roles` dan validasi eksplisit menolak `siswa`/`orang_tua`/carrier (lihat §6) — `create()` tidak perlu guard `abort_if` (tidak ada target user untuk membuat baru).

## 11. Testing

1. **Regresi bug destruktif**: buat user dengan role `['guru', 'pegawai_lembaga']`, edit lewat `update()` hanya mengubah nama — assert user MASIH punya `pegawai_lembaga` setelah update.
2. **Multi-role assignment**: pilih 2 role fungsional (`wakasek_kurikulum` + `guru`) di `store()`, assert user dapat KEDUA role tersebut PLUS `pegawai_lembaga` (karena keduanya `scope_level` lembaga/diri_sendiri).
3. **Baseline carrier tidak ditambahkan untuk role yayasan murni**: pilih `bendahara_yayasan` saja, assert user TIDAK dapat `pegawai_lembaga` maupun `pegawai_yayasan`.
4. **Validasi menolak siswa/orang_tua/carrier role** dikirim langsung via request (bypass UI) — assert `assertSessionHasErrors('roles.0')` atau sejenisnya.
5. **Rank-gating per-role**: actor lembaga-scope mencoba assign 2 role sekaligus di mana salah satunya `scope_level` yayasan — assert ditolak.
6. **Guard `edit`/`update`/`toggleActive` untuk orang_tua**: assert 404 untuk ketiganya, meniru pola test siswa yang sudah ada.
7. **Redirect link di list**: baris siswa → `assertSee(route('admin.siswa.edit', $siswaTerkait))`; baris orang_tua → `assertSee(route('admin.orang-tua.edit', $orangTuaTerkait))`; baris staf biasa → link tetap `admin.users.edit`.
8. **Dead code fix**: test `tabs/profil.blade.php` merender "Lembaga Tertaut" untuk user ber-`lembaga_id`, TIDAK merender untuk user pool (`lembaga_id` null).
9. **`functionalRoles()`**: test unit langsung di `User` model — user dengan `['guru', 'pegawai_lembaga']` → `functionalRoles()` cuma berisi `guru`.

## 12. Ringkasan File yang Disentuh

- `app/Models/User.php` — tambah accessor `functionalRoles()`.
- `app/Http/Controllers/Admin/UserController.php` — `create()`, `store()`, `edit()`, `update()`, `toggleActive()`, tambah `baselineCarrierRole()` privat.
- `resources/views/admin/users/_form.blade.php` — checkbox grup per scope, hapus select Role lama.
- `resources/views/admin/users/_daftar.blade.php` — kolom Role pakai `functionalRoles()`, redirect Edit untuk siswa/orang_tua, sembunyikan toggle-active untuk keduanya.
- `resources/views/admin/users/tabs/profil.blade.php` — hapus dead code, label "Role / Peran Akses" jadi list, pakai `functionalRoles()`.
- Test baru/diperbarui: kemungkinan besar di `tests/Feature/Admin/UserManagementTest.php` (existing) + test baru untuk `functionalRoles()` di `tests/Unit/UserScopeTest.php` atau file unit baru.
