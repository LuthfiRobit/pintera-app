# Design Doc — RBAC: Permission Granular + Redesign Halaman Roles
**Tanggal:** 13 Juli 2026
**Status:** Disetujui untuk breakdown ke implementation plan
**Referensi:** `docs/superpowers/specs/2026-07-12-m0-fondasi-design.md` (bagian 3, RBAC dinamis), laporan manual testing bug `PermissionDoesNotExist`

---

## 1. Latar Belakang & Tujuan

Manual testing M0 menemukan dua hal:

1. **Bug:** menambah permission ke role lewat form Roles gagal dengan `PermissionDoesNotExist: There is no permission named '6'`. Root cause: checkbox HTML mengirim ID permission sebagai **string** lewat POST asli; `$request->validate()` dengan rule `integer` memvalidasi tapi tidak meng-cast tipe; Spatie's `getStoredPermission()` memakai `is_int()` (strict) untuk memutuskan resolusi by-ID vs by-name, sehingga string numerik jatuh ke pencarian by-name dan gagal. **Sudah diperbaiki** di sesi ini (`RoleController.php`, permission id di-resolve eksplisit lewat `Permission::whereIn('id', ...)->get()` sebelum `syncPermissions()`), dengan regression test yang mereproduksi submission form asli (string, bukan literal PHP array test yang menutupi bug).
2. **Permintaan desain ulang:** sistem permission saat ini datar (satu string per modul, mis. `manage-guru` mencakup semua aksi CRUD guru sekaligus). Yayasan ingin permission granular per aksi (View/Create/Edit/Delete per fitur), ditampilkan sebagai matriks terkelompok per modul di halaman Role Builder, dengan pengalaman CRUD Role tanpa reload halaman (datatable server-side + AJAX + toast notification).

Dokumen ini mencakup butir 2 — restrukturisasi model permission dan desain ulang halaman Roles.

### Termasuk
- Permission granular `modul.aksi` menggantikan seluruh permission datar yang ada.
- Migrasi **seluruh** `$this->authorize()` di controller admin (bukan cuma Roles) ke permission baru — cutover bersih, bukan berdampingan dengan yang lama.
- Migrasi sidebar (`Auth::user()->can(...)`) ke permission baru.
- Migrasi seluruh test yang mereferensikan nama permission lama.
- Redesain halaman **Roles** (index/create/edit) saja: datatable server-side, CRUD tanpa reload (Alpine.js + fetch ke endpoint JSON), matriks permission terkelompok per modul, toast notification.

### Tidak termasuk (menyusul di sesi terpisah)
- Menerapkan pola datatable/no-reload yang sama ke halaman admin lain (Users, Lembaga, Guru, Tahun Ajaran, modul SPMB) — disepakati eksplisit ditunda.
- Menambah aksi CRUD yang sengaja tidak ada (mis. `destroy` untuk Lembaga/Guru/Gelombang/Jalur) — keputusan M0/M1 sebelumnya ("nonaktifkan", bukan "hapus") tetap dihormati; permission granular hanya dibuat untuk aksi yang benar-benar punya rute.
- Fitur audit-log viewer (permission `view-audit-log` sudah ada di seeder tapi belum dipakai controller manapun — akan tetap ada padanan granularnya, `audit-log.view`, tapi tetap tidak terhubung ke rute apa pun sampai fitur itu dibangun).

---

## 2. Inventori Modul & Aksi

Diturunkan dari rute admin yang benar-benar terdaftar (`routes/admin.php`), bukan disalin dari referensi visual eksternal.

| Modul (prefix) | Aksi | Controller / method asal |
|---|---|---|
| `roles` | view, create, edit, delete | RoleController::index/create+store/edit+update/destroy |
| `users` | view, create, edit, toggle-active | UserController::index/create+store/edit+update/toggleActive |
| `lembaga` | view, create, edit | LembagaController::index/create+store/edit+update |
| `guru` | view, create, edit | GuruController::index/create+store/edit+update |
| `tahun-ajaran` | view, create, activate | TahunAjaranController::index/create+store/activate |
| `semester` | create, activate | SemesterController::store/activate |
| `jenis-tes` | view, create, delete | JenisTesMasterController::index/store/destroy |
| `gelombang-ppdb` | view, create, edit | GelombangPpdbController::index/create+store/edit+update |
| `jalur-ppdb` | view, create, edit | JalurPpdbController::index/create+store/edit+update |
| `formulir-field` | create, delete | FormulirFieldController::store/destroy |
| `dokumen-syarat` | create, delete | DokumenSyaratController::store/destroy |
| `seleksi` | create, delete | SeleksiController::store/destroy |
| `spmb-konfigurasi` | duplikasi | SpmbKonfigurasiController::duplikasi |
| `audit-log` | view | *(belum ada controller — permission disiapkan, tidak dipakai)* |

**Konvensi penamaan:** `{modul}.{aksi}`, contoh: `guru.view`, `guru.create`, `guru.edit`, `roles.delete`. Total permission baru: 4+4+3+3+3+2+3+3+3+2+2+2+1+1 = **36 permission**, menggantikan 8 permission datar yang ada sekarang (`manage-roles`, `manage-users`, `manage-yayasan`, `manage-lembaga`, `manage-tahun-ajaran`, `manage-guru`, `view-audit-log`, `manage-ppdb`).

Catatan: `manage-yayasan` di seeder saat ini tidak dipakai oleh controller manapun (tidak ada YayasanController admin). Padanannya tidak dibuat granular karena tidak ada rute nyata untuk didasarkan — dihapus saja dari daftar permission sampai fitur pengelolaan Yayasan dibangun.

---

## 3. Strategi Migrasi (Cutover Bersih)

Permission lama **dihapus total**, bukan ditambah berdampingan — permission granular baru langsung menjadi satu-satunya sumber kebenaran otorisasi sejak migration/seeder ini dijalankan.

### 3.1 Seeder (`RolePermissionSeeder`)
- Daftar 38 permission granular menggantikan 8 permission datar.
- `yayasan_super_admin`: tetap `syncPermissions()` ke seluruh daftar (tidak berubah polanya).
- `admin_administrasi`: sebelumnya default dapat `manage-ppdb` saja. Penggantinya: seluruh permission bertema SPMB (`jenis-tes.*`, `gelombang-ppdb.*`, `jalur-ppdb.*`, `formulir-field.*`, `dokumen-syarat.*`, `seleksi.*`, `spmb-konfigurasi.duplikasi`) — 16 permission, digrant eksplisit sebagai array literal (bukan wildcard query) supaya daftar tetap terbaca jelas di source.

### 3.2 Controller (13 file)
Setiap `$this->authorize('manage-X')` diganti dengan permission spesifik per method:

| Controller | Method → permission |
|---|---|
| RoleController | index→`roles.view`, create/store→`roles.create`, edit/update→`roles.edit`, destroy→`roles.delete` |
| UserController | index→`users.view`, create/store→`users.create`, edit/update→`users.edit`, toggleActive→`users.toggle-active` |
| LembagaController | index→`lembaga.view`, create/store→`lembaga.create`, edit/update→`lembaga.edit` |
| GuruController | index→`guru.view`, create/store→`guru.create`, edit/update→`guru.edit` |
| TahunAjaranController | index→`tahun-ajaran.view`, create/store→`tahun-ajaran.create`, activate→`tahun-ajaran.activate` |
| SemesterController | store→`semester.create`, activate→`semester.activate` |
| JenisTesMasterController | index→`jenis-tes.view`, store→`jenis-tes.create`, destroy→`jenis-tes.delete` |
| GelombangPpdbController | index→`gelombang-ppdb.view`, create/store→`gelombang-ppdb.create`, edit/update→`gelombang-ppdb.edit` |
| JalurPpdbController | index→`jalur-ppdb.view`, create/store→`jalur-ppdb.create`, edit/update→`jalur-ppdb.edit` |
| FormulirFieldController | store→`formulir-field.create`, destroy→`formulir-field.delete` |
| DokumenSyaratController | store→`dokumen-syarat.create`, destroy→`dokumen-syarat.delete` |
| SeleksiController | store→`seleksi.create`, destroy→`seleksi.delete` |
| SpmbKonfigurasiController | duplikasi→`spmb-konfigurasi.duplikasi` |

### 3.3 Sidebar (`resources/views/layouts/sidebar.blade.php`)
Setiap `Auth::user()->can('manage-X')` untuk item navigasi diganti `Auth::user()->can('{modul}.view')`.

### 3.4 Test Suite
Setiap test yang membuat/mereferensikan permission lama (`Permission::firstOrCreate(['name' => 'manage-X', ...])`, `$role->givePermissionTo('manage-X')`) diperbarui ke permission granular yang sesuai method yang diuji. Ini menyentuh mayoritas file di `tests/Feature/Admin/*` dan beberapa di `tests/Feature/*`.

---

## 4. Redesain Halaman Roles

### 4.1 Index — Datatable Server-Side
- Endpoint baru `GET /admin/roles/data` mengembalikan JSON: daftar role (dengan `users_count`, `scope_level`, ringkasan permission), total baris, ter-filter/tersortir/terpaginasi sesuai query param (`search`, `sort`, `direction`, `page`, `per_page`).
- View index memuat shell halaman (kartu, kontrol Filter Scope/Refresh/Tambah Role, elemen tabel kosong) lalu Alpine.js `fetch()` ke endpoint tsb saat mount dan setiap kontrol berubah (search input dengan debounce, ganti halaman, ganti kolom sortir).
- Kolom: No, Aksi (dropdown gear: Edit Role/Hapus), Nama Role & Scope (dengan badge protected bila relevan), jumlah Permission.

### 4.2 Create/Edit — Tanpa Reload
- Layout dua kolom: kiri form Nama Role + Scope + tombol Simpan/Batal; kanan panel "Hak Akses" — permission dikelompokkan per modul (card per modul, checkbox per aksi yang tersedia untuk modul itu — **jumlah checkbox mengikuti tabel §2, tidak dipaksa seragam 4 kolom**), toggle "Pilih Semua", tombol "Sync Permission" (fetch ulang katalog permission terbaru dari server, re-render checkbox tanpa reload).
- Submit lewat `fetch()` ke `POST/PUT /admin/roles` (endpoint sama, controller mendeteksi request AJAX via header `Accept: application/json` dan merespons JSON alih-alih redirect) — sukses memicu toast + navigasi Alpine ke index tanpa reload; gagal menampilkan error inline di form tanpa kehilangan state checkbox yang sudah dicentang.
- Hapus role: konfirmasi ringan (Alpine modal/`confirm()`), `fetch DELETE`, baris hilang dari tabel in-place + toast.

### 4.3 Toast Notification
Komponen Alpine.js baru (`x-toast` atau store global `$store.toast`), posisi fixed top-right, stack vertikal, animasi slide-in dari kanan + fade, ikon centang (sukses)/silang (gagal), auto-dismiss 4 detik + tombol tutup manual. Dipakai oleh semua aksi AJAX di halaman Roles (create/update/delete/sync permission berhasil atau gagal). Palet mengikuti token brand yang sudah ada (`signal-green`/`signal-red`/`brass`).

---

## 5. Keputusan Arsitektur

| Area | Keputusan |
|---|---|
| Pendekatan frontend | Blade + Alpine.js + `fetch()`, tetap **tanpa Livewire** — konsisten keputusan M0 |
| Endpoint AJAX | Controller yang sama menangani request biasa (redirect) dan request AJAX (JSON) berdasarkan `$request->wantsJson()` / header `Accept`, bukan controller terpisah — menghindari duplikasi logic otorisasi & validasi |
| Migrasi permission | Cutover bersih dalam satu pass (seeder + 13 controller + sidebar + test), bukan berdampingan dengan permission lama |
| Cakupan UI | Hanya halaman Roles pada sesi ini; pola tidak digulirkan ke halaman lain sampai diminta eksplisit |
| Tidak menambah aksi CRUD baru | Permission granular hanya dibuat untuk aksi yang benar-benar punya rute — tidak diam-diam menambah `destroy` pada modul yang sengaja tidak punya (Lembaga, Guru, Gelombang, Jalur) |

---

## 6. Rencana Pengujian

- **Migrasi permission**: test seeder diperbarui — memastikan 36 permission granular ter-seed, `yayasan_super_admin` dapat semuanya, `admin_administrasi` dapat 16 permission SPMB.
- **Per controller**: test permission-denial yang sudah ada diperbarui ke nama granular yang sesuai method; tidak ada regresi pada test cross-tenant yang sudah ada.
- **Endpoint datatable**: test search/sort/pagination mengembalikan subset data yang benar, termasuk saat lembaga-scoped admin (jika relevan untuk Roles — Roles sendiri tidak tenant-scoped, jadi ini murni soal permission `roles.view`).
- **CRUD Role via AJAX**: test yang mengirim header `Accept: application/json` dan menegaskan response JSON (status, body) alih-alih redirect, mencakup kasus sukses dan validasi gagal.
- **Regression test bug asli**: test string-typed permission ids (sudah ditambahkan di sesi bug-fix) tetap dipertahankan dan disesuaikan namanya ke permission baru.
