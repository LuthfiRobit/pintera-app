# Spec: Perbaikan Halaman Peran — Keamanan Nama Role, Scope Platform, Chip Filter, & UX Matriks

**Tanggal**: 2026-08-25
**Branch**: `rbac-v2`
**Terkait**: `.agents/specs/2026-08-25-rbac-pengguna-scope-filter.md`, `.agents/specs/2026-08-25-form-pengguna-multirole-redesign.md` (keduanya SELESAI)

## 1. Latar Belakang & Masalah

Review terhadap halaman "Peran" (`admin.roles.*`, `RoleController.php`, `Role.php`, blade `admin/roles/*`) menemukan 9 masalah, dikonfirmasi langsung ke kode (bukan asumsi):

1. **HIGH — Nama role protected bisa diedit bebas.** `Role.php`'s `saving()` guard hanya melindungi `scope_level` (`isDirty('scope_level')`) dan `deleting()` untuk hapus — TIDAK ADA guard untuk `name`. `RoleController::update()` juga memvalidasi+menyimpan `name` TANPA syarat `is_protected`. `edit.blade.php`'s input nama (`x-model="name"`) tidak punya `:disabled` apa pun. Mengubah nama role protected (mis. `guru` → `Guru Pengajar`) akan BERHASIL tersimpan dan merusak SEMUA `hasRole('guru')`/middleware gate di seluruh codebase secara diam-diam.
2. **`RoleController::scopeRank()` (privat, TERPISAH dari `UserController::scopeRank()`) belum punya cabang `'platform'`** — masih versi pra-RBAC-v2 (`yayasan=>3, lembaga=>2, default=>1`). `platform_super_admin` yang membuat/edit role lain akan salah dianggap rank 1 (paling rendah).
3. **Validasi `scope_level` di `store()`/`update()` TIDAK mengizinkan nilai `platform`** (`'in:yayasan,lembaga,diri_sendiri'`) — mustahil membuat role baru dengan scope `platform` lewat form ini, walau kolom DB (`roles.scope_level` ENUM) sudah mendukung nilai itu sejak RBAC v2.
4. **Filter scope di daftar Peran cuma punya opsi Yayasan/Lembaga** (`index.blade.php:83-86`) — tidak bisa filter role `platform_super_admin` atau role `diri_sendiri` (guru, wali_kelas, siswa, dst) secara spesifik.
5. **Stat card cuma hitung Total/Yayasan/Lembaga** (`RoleController::index()` baris 42-44) — absen untuk Platform dan Diri Sendiri.
6. **Nama role ditampilkan snake_case mentah** (`{{ $role->name }}`) — tidak ramah baca.
7. **Filter scope pakai `<select>` polos**, inkonsisten dengan pola chip yang sudah dipakai halaman Pengguna.
8. **Kolom Users/Permissions murni angka statis** — tidak ada link ke daftar Pengguna terfilter, tidak ada preview isi permission.
9. **Matriks permission (`_permission-matrix.blade.php`) tidak punya live search** — semua modul dirender penuh, admin harus scroll manual untuk mencari 1 permission spesifik saat modul makin banyak.

## 2. Tujuan

1. Nama role `is_protected` TIDAK BISA diubah — dikunci di 3 lapis: model (`Role::saving()` guard baru), controller (`update()` drop field `name` dari validasi/proses saat `is_protected`), dan UI (`edit.blade.php` input `:disabled`).
2. `RoleController::scopeRank()` dan validasi `scope_level` mengenali `platform` (rank 4, tertinggi) — konsisten dengan `UserController::scopeRank()`.
3. Opsi scope `platform` HANYA muncul di dropdown create/edit (untuk role tidak-protected) kalau actor yang login berscope `platform` — defense-in-depth di atas validasi rank yang sudah ada.
4. Chip filter scope di daftar Peran: 5 chip berbasis kolom `scope_level` LANGSUNG (Semua, Platform, Yayasan, Lembaga, Diri Sendiri) — BUKAN taksonomi fungsional seperti chip halaman Pengguna (beda konsep: di sini kita mengelola ROLE itu sendiri yang `scope_level`-nya sudah eksplisit, bukan mengelompokkan user berdasar role yang mereka pegang).
5. Stat card tambah 2 kartu baru: Scope Platform, Scope Diri Sendiri.
6. Nama role ditampilkan Title Case (`ucwords(str_replace('_', ' ', $role->name))`) untuk TAMPILAN saja — nilai `name` asli (snake_case) tetap dipakai untuk `value`/`hasRole()`/logic apa pun.
7. Kolom Users jadi link ke `admin.users.index` terfilter `role={nama role ini}` (reuse filter `role` yang sudah ada di halaman Pengguna).
8. Kolom Permissions dapat tooltip hover berisi beberapa nama permission pertama + "+N lainnya".
9. Form create/edit dapat blok info edukatif statis menjelaskan cakupan tiap `scope_level`.
10. Matriks permission dapat 1 input live search (client-side, Alpine) yang memfilter permission per modul berdasarkan nama/label.

## 3. Non-Goals

- TIDAK mengubah struktur data `roles`/`permissions` atau migration apa pun.
- TIDAK mengubah `PermissionCatalog::grouped()` atau `PermissionAuditService`.
- TIDAK menambah kemampuan platform admin mengedit permission role protected melebihi yang sudah ada (form ini sudah mengizinkan edit permission untuk role protected, itu TETAP diizinkan — yang dikunci CUMA `name` dan `scope_level`).
- TIDAK mengubah halaman Pengguna (`admin.users.*`) selain memastikan filter `role` yang sudah ada di sana bisa menerima link dari halaman Peran (tidak perlu perubahan kode di `UserController`, filter itu sudah ada).
- TIDAK menambah dukungan drag-drop/reorder role, atau fitur duplikasi role (di luar cakupan 9 temuan di atas).

## 4. Detail Perbaikan Keamanan (Prioritas Tertinggi)

### 4.1 `app/Models/Role.php`

Tambah kondisi baru di `saving()` guard yang sudah ada (di samping guard `scope_level`):
```php
if ($role->exists && $role->is_protected && $role->isDirty('name')) {
    throw new RuntimeException('Nama role yang dilindungi tidak dapat diubah.');
}
```

### 4.2 `RoleController::update()`

Field `name` di `$rules` menjadi kondisional — TIDAK divalidasi/diproses sama sekali kalau `$role->is_protected` (bukan cuma mengandalkan exception dari model sebagai satu-satunya lapis pertahanan):
- Kalau `is_protected`: `$role->name` TIDAK disentuh sama sekali (baris `$role->name = $data['name']` di-skip).
- Kalau TIDAK protected: perilaku sekarang (validasi + assign) tetap sama.

### 4.3 `edit.blade.php`

Input nama role: `<x-text-input type="text" x-model="name" :disabled="isProtected" ...>` — tambah styling visual (opacity/cursor-not-allowed) mengikuti pola yang sudah dipakai untuk field scope_level yang terkunci.

## 5. Detail Perbaikan Scope Platform

### 5.1 `RoleController::scopeRank()`

```php
private function scopeRank(string $level): int
{
    return match ($level) {
        'platform' => 4,
        'yayasan' => 3,
        'lembaga' => 2,
        default => 1, // diri_sendiri
    };
}
```

### 5.2 Validasi `store()`/`update()`

`'scope_level' => ['required', 'in:yayasan,lembaga,diri_sendiri,platform']` (di kedua method, untuk cabang yang memvalidasi scope_level).

### 5.3 Visibilitas opsi "Platform" di dropdown

`RoleController::create()` dan `edit()` passing variabel baru `$isPlatformActor = auth()->user()->widestScopeLevel() === 'platform'` ke view. Di `create.blade.php` dan `edit.blade.php` (cabang tidak-protected), opsi `<option value="platform">Platform</option>` HANYA dirender `@if($isPlatformActor)`.

## 6. Detail Chip Filter & Stat Card

### 6.1 `RoleController::index()`

Tambah 2 query count baru:
```php
$totalPlatform = Role::where('scope_level', 'platform')->count();
$totalDiriSendiri = Role::where('scope_level', 'diri_sendiri')->count();
```
Passing ke view bersama `$totalYayasan`/`$totalLembaga`/`$totalRoles` yang sudah ada.

### 6.2 `index.blade.php`

- Stat card grid jadi 5 kolom (dari 3), tambah kartu Platform dan Diri Sendiri.
- Blok filter `<select>` scope diganti chip (pola sama seperti chip halaman Pengguna): 5 tombol (Semua, Platform, Yayasan, Lembaga, Diri Sendiri), `@click` men-set `filters.scope` lalu `muatUlangDaftar()` — REUSE `dataTableFilter` yang sudah ada, TIDAK perlu method Alpine baru (beda dengan chip halaman Pengguna yang butuh `setScopeGroup()` khusus karena ada logic refresh select Role dinamis; di sini filter scope BUKAN mengontrol opsi lain, jadi cukup `filters.scope = 'xxx'; muatUlangDaftar()` inline).
- Badge jumlah di tiap chip diisi dari `$totalRoles`/`$totalPlatform`/`$totalYayasan`/`$totalLembaga`/`$totalDiriSendiri`.

## 7. Detail Format Nama, Link Users, Tooltip Permissions

### 7.1 `_daftar.blade.php` — Format nama

Ganti `{{ $role->name }}` (di baris tampilan nama) jadi `{{ ucwords(str_replace('_', ' ', $role->name)) }}`. Nama asli (snake_case) TETAP dipakai di tempat lain yang butuh value asli (tidak ada di file ini, murni tampilan).

### 7.2 `_daftar.blade.php` — Kolom Users jadi link

Ganti:
```blade
<td class="px-5 py-3 align-top font-mono text-gray-600">{{ $role->users_count }}</td>
```
Jadi:
```blade
<td class="px-5 py-3 align-top font-mono text-gray-600">
    <a href="{{ route('admin.users.index', ['role' => $role->name]) }}" class="text-brand-600 hover:underline">{{ $role->users_count }}</a>
</td>
```
(Route `admin.users.index` dan query param `role` SUDAH ADA dan berfungsi — `UserController::index()` sudah membaca `$request->input('role')` sebagai filter `whereHas('roles', ...)`, tidak perlu perubahan di `UserController`.)

### 7.3 `_daftar.blade.php` — Kolom Permissions jadi tooltip

`RoleController::index()`: tambah eager-load permission (dibatasi, hindari N+1 besar):
```php
$query = Role::withCount(['users', 'permissions'])->with(['permissions' => fn ($q) => $q->orderBy('name')->limit(5)]);
```
Blade: bungkus angka dengan `<x-tooltip>`, isi `text` dari string gabungan:
```blade
@php
    $previewNames = $role->permissions->pluck('name')->implode(', ');
    $sisa = max(0, $role->permissions_count - $role->permissions->count());
    $tooltipText = $role->permissions_count > 0
        ? $previewNames . ($sisa > 0 ? ", +{$sisa} lainnya" : '')
        : 'Belum ada permission';
@endphp
<x-tooltip :text="$tooltipText">
    <span class="font-mono text-gray-600 cursor-help border-b border-dashed border-gray-300">{{ $role->permissions_count }}</span>
</x-tooltip>
```

## 8. Detail Helper Edukatif Scope Level

Di `create.blade.php` dan `edit.blade.php` (dalam card "Identitas Peran", setelah field Scope Level), tambah blok statis:
```blade
<div class="mt-2 space-y-1.5 rounded-lg bg-gray-50 p-3 text-[11px] text-gray-500">
    <p><strong class="text-gray-700">Platform:</strong> Akses lintas SEMUA yayasan (hanya untuk admin sistem tertinggi).</p>
    <p><strong class="text-gray-700">Yayasan:</strong> Akses ke semua lembaga dalam 1 yayasan.</p>
    <p><strong class="text-gray-700">Lembaga:</strong> Akses terbatas ke 1 lembaga/sekolah spesifik.</p>
    <p><strong class="text-gray-700">Diri Sendiri:</strong> Akses terbatas ke data milik sendiri (mis. guru, siswa, orang tua).</p>
</div>
```

## 9. Detail Live Search Matriks Permission

### 9.1 `resources/js/role-form.js`

Tambah state `permissionSearch: ''` dan method:
```js
filteredModuleGroups() {
    const query = this.permissionSearch.trim().toLowerCase();
    if (!query) return this.moduleGroups;

    return this.moduleGroups
        .map((group) => ({
            ...group,
            permissions: group.permissions.filter((permission) =>
                permission.label.toLowerCase().includes(query) || permission.name.toLowerCase().includes(query)
            ),
        }))
        .filter((group) => group.permissions.length > 0);
},
```

### 9.2 `_permission-matrix.blade.php`

Tambah input search di header card (sebelum tombol Pilih Semua/Kosongkan/Sync), dan ganti `x-for="group in moduleGroups"` jadi `x-for="group in filteredModuleGroups()"`:
```blade
<div class="relative">
    <input type="text" x-model="permissionSearch" placeholder="Cari permission... (mis. tagihan, rapor)" class="w-64 rounded-lg border-gray-200 bg-gray-50 py-1.5 pl-8 pr-3 text-xs focus:border-brand-500 focus:ring-brand-500">
    <x-icon name="search" class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
</div>
```

## 10. Testing

1. **Regresi keamanan nama protected**: coba `update()` role `is_protected=true` (mis. `yayasan_super_admin`) dengan `name` baru — assert nama TIDAK berubah setelah update (permission BOLEH berubah di request yang sama).
2. **Model guard**: langsung `$role->name = 'x'; $role->save();` untuk role protected via test unit — assert exception `RuntimeException` dilempar.
3. **`scopeRank` platform**: actor `platform_super_admin` membuat role baru dengan `scope_level=platform` — assert BERHASIL (rank 4 <= rank 4).
4. **Validasi menerima `platform`**: `store()` dengan `scope_level=platform` oleh actor platform — assert lolos validasi (sebelumnya pasti gagal `in:` rule).
5. **Non-platform actor tidak bisa buat role scope platform**: actor `yayasan_super_admin` mencoba `store()` dengan `scope_level=platform` — assert ditolak (rank 4 > rank 3), SAMA seperti pengujian existing untuk kombinasi rank lain.
6. **Opsi Platform tersembunyi untuk non-platform actor**: render `create.blade.php` sebagai `yayasan_super_admin` — assert `assertDontSee('value="platform"', false)` (perlu hati-hati string match, sesuaikan assertion presisi saat implementasi berdasarkan HTML aktual).
7. **Chip filter scope_level**: masing-masing 5 chip menampilkan role yang tepat (query `scope_level` langsung, sudah didukung `RoleController::index()` yang sudah ada, tidak perlu logic precedence seperti chip Pengguna).
8. **Stat card**: `$totalPlatform`/`$totalDiriSendiri` menghitung benar.
9. **Link kolom Users**: assert HTML mengandung `href` ke `admin.users.index` dengan query `role=<nama>`.
10. **Tooltip Permissions**: assert tooltip text mengandung nama permission yang benar + angka "+N lainnya" sesuai jumlah sisa.
11. **Live search matriks**: test JS di luar cakupan Pest (client-side Alpine) — verifikasi manual di browser cukup untuk fitur ini, dicatat di handoff log sebagai checklist manual (pola sama seperti fitur scanner QR kamera di sub-project SDM sebelumnya yang juga tidak bisa diuji otomatis penuh).

## 11. Ringkasan File yang Disentuh

- `app/Models/Role.php` — guard `name` untuk role protected.
- `app/Http/Controllers/Admin/RoleController.php` — `scopeRank()`, validasi `scope_level`, `index()` (2 count baru + eager-load permission terbatas), `create()`/`edit()` (passing `$isPlatformActor`), `update()` (skip field `name` untuk protected).
- `resources/views/admin/roles/index.blade.php` — chip filter + 2 stat card baru.
- `resources/views/admin/roles/_daftar.blade.php` — format nama, link Users, tooltip Permissions.
- `resources/views/admin/roles/create.blade.php` — opsi Platform kondisional, blok edukatif.
- `resources/views/admin/roles/edit.blade.php` — opsi Platform kondisional, blok edukatif, `:disabled` untuk nama protected.
- `resources/views/admin/roles/_permission-matrix.blade.php` — input live search.
- `resources/js/role-form.js` — state + method `filteredModuleGroups()`.
- Test baru/diperbarui: kemungkinan besar file baru `tests/Feature/Admin/RoleManagementSecurityTest.php` (grep sebelumnya menunjukkan TIDAK ada `RoleManagementTest.php` existing di project ini) + test unit untuk guard model di `tests/Unit/RoleModelTest.php` atau serupa (perlu digrep ulang saat menulis plan, jangan asumsikan nama file test existing).
