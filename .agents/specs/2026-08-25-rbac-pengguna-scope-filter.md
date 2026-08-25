# Spec: Halaman Pengguna — Filter Scope & Visibilitas Lintas-Tenant untuk Platform Admin

**Tanggal**: 2026-08-25
**Branch**: `rbac-v2`
**Terkait**: `.agents/specs/2026-08-24-rbac-v2-role-taxonomy.md` (RBAC v2, 18 role baseline, sudah SELESAI)

## 1. Latar Belakang & Masalah

Halaman "Pengguna" (`admin.users.index`, `app/Http/Controllers/Admin/UserController.php`) saat ini:
- Punya search (nama/email) + filter Role tunggal + pagination, memakai pola AJAX partial-swap `dataTableFilter` yang konsisten dengan halaman lain di project (Peran, Siswa, Guru, Kelas, Lembaga).
- **Sengaja mengecualikan role `siswa`** dari daftar (`whereDoesntHave('roles', fn ($q) => $q->where('name', 'siswa'))`), karena siswa punya halaman "Data Siswa" tersendiri untuk data akademik.
- **Dibatasi total oleh `TenantScope`** (global scope pada model `User`, dari trait `BelongsToTenant`) — setiap query `User::` otomatis difilter: scope `yayasan` melihat semua lembaga DALAM yayasannya sendiri (sudah berjalan), scope lain (`lembaga`/`diri_sendiri`) dibatasi ke `lembaga_id` miliknya sendiri.
- **Tidak ada jalur kode yang mengenali scope `platform`** sama sekali. RBAC v2 (`.agents/specs/2026-08-24-rbac-v2-role-taxonomy.md`) menambahkan role `platform_super_admin` dengan `scope_level = 'platform'` di database, tapi `User::widestScopeLevel()` tidak punya cabang untuk nilai ini — sehingga user dengan role ini justru jatuh ke cabang PALING restriktif (`default => 'diri_sendiri'`), dan `TenantScope` akan membatasinya ke `lembaga_id` miliknya sendiri (kemungkinan `null` → tidak melihat siapa pun). Ini bug murni akibat RBAC v2 menambah nilai enum baru tanpa mengalirkannya ke logic scope yang sudah ada.

Permintaan: sempurnakan halaman Pengguna agar (a) menampilkan SEMUA kategori user (termasuk siswa) dengan filter chip berbasis scope/kategori, (b) `platform_super_admin` bisa benar-benar melihat lintas-tenant (satu-satunya scope yang boleh begitu), dan (c) search mencakup username juga.

## 2. Tujuan

1. `platform_super_admin` bisa melihat SEMUA user dari SEMUA yayasan/lembaga di halaman Pengguna, dengan kolom tambahan yang menunjukkan asal yayasan/lembaga tiap baris.
2. Scope lain (`yayasan`, `lembaga`, `diri_sendiri`) tetap terbatas seperti sekarang — TIDAK ADA perubahan visibilitas untuk mereka, kecuali munculnya siswa di daftar (lihat §2 poin 4) dan chip baru sebagai alat bantu filter di dalam batas visibilitas yang sudah ada.
3. Search mencakup `username`, bukan cuma `name`/`email`.
4. Role `siswa` tidak lagi dikecualikan secara permanen — muncul saat chip "Siswa" (atau "Semua") dipilih, ditampilkan sebagai akun dasar (nama/email/username/role/status), TANPA data akademik (halaman "Data Siswa" tetap jadi rujukan detail akademik, tidak digantikan).
5. Chip filter kategori: Semua, Platform, Yayasan, Lembaga, Staf, Orang Tua, Siswa — masing-masing dengan badge jumlah, dan select Role di sampingnya menyesuaikan opsi sesuai chip aktif.

## 3. Non-Goals

- TIDAK mengubah visibilitas model lain (`Karyawan`, `Siswa` [model akademik], `Tagihan`, `Pembayaran`, dll) untuk `platform_super_admin` — bypass `TenantScope` di spec ini SENGAJA dibatasi hanya untuk model `User`. Kebutuhan platform-level visibility di model lain adalah keputusan terpisah di masa depan, tidak termasuk cakupan ini.
- TIDAK membuat halaman baru — perluasan terhadap `admin.users.index` yang sudah ada.
- TIDAK mengubah halaman "Data Siswa" atau data akademik siswa mana pun.
- TIDAK mengubah perilaku switcher `active_lembaga_id` yang sudah ada untuk `yayasan_super_admin`.
- TIDAK menambahkan filter tanggal/status aktif baru di luar yang diminta (status aktif SUDAH ada di stat card, tidak berubah).

## 4. Taksonomi Chip & Aturan Precedence

Setiap user masuk **tepat satu** chip kategori, ditentukan oleh role pertama yang cocok pada urutan prioritas berikut (dari atas = prioritas tertinggi):

| Urutan | Chip | Role yang termasuk |
|---|---|---|
| 1 | **Platform** | `platform_super_admin` |
| 2 | **Yayasan** | `yayasan_super_admin`, `bendahara_yayasan`, `pegawai_yayasan` |
| 3 | **Lembaga** | `kepala_sekolah`, `wakasek_kurikulum`, `wakasek_kesiswaan`, `operator_akademik`, `admin_sdm`, `bendahara_lembaga`, `admin_sarpras`, `admin_administrasi`, `pegawai_lembaga` |
| 4 | **Staf** | `guru`, `wali_kelas`, `guru_bk` |
| 5 | **Orang Tua** | `orang_tua` |
| 6 | **Siswa** | `siswa` |

Chip **Semua** tidak memfilter role sama sekali (tapi tetap kena `TenantScope` sesuai viewer).

Precedence ini HANYA relevan untuk *pengelompokan tampilan* (agar guru yang juga punya `pegawai_lembaga` tidak dobel-hitung di 2 chip) — query filter per chip menggunakan `whereIn('name', [...daftar role grup itu...])` yang salah eksklusif berdasarkan tabel di atas, sehingga tidak perlu mengecek precedence penuh saat query (setiap role hanya muncul di satu grup dalam tabel ini, tidak ada role yang tercantum di 2 baris).

**Asumsi**: setiap user secara normal hanya representasi satu kategori dominan. Kombinasi role lintas-kategori yang tidak wajar (mis. `siswa` yang juga punya `guru`) adalah kasus data-integrity yang seharusnya tidak terjadi kalau RBAC dijaga benar; spec ini tidak menambahkan penanganan khusus untuk anomali semacam itu.

## 5. Perbaikan Backend — Scope & Visibilitas

### 5.1 `App\Models\User::widestScopeLevel()`

Tambah cabang `'platform'` di urutan PALING atas (paling luas):

```php
public function widestScopeLevel(): string
{
    $levels = $this->roles->pluck('scope_level');

    return match (true) {
        $levels->contains('platform') => 'platform',
        $levels->contains('yayasan') || $this->hasRole(['yayasan_super_admin', 'super_admin', 'bendahara_yayasan']) => 'yayasan',
        $levels->contains('lembaga') => 'lembaga',
        default => 'diri_sendiri',
    };
}
```

### 5.2 `App\Models\Scopes\TenantScope::apply()`

Tambah cabang PALING atas: kalau `$actingUser->widestScopeLevel() === 'platform'` **DAN** `$model instanceof \App\Models\User`, skip filtering sama sekali (`return`, tidak menambah `where` apa pun). Cabang existing (`yayasan`, default) TIDAK diubah. Bypass ini SENGAJA dibatasi ke model `User` saja (lihat §3 Non-Goals) — model lain yang memakai `BelongsToTenant` TETAP terbatasi tenant seperti sekarang untuk semua scope termasuk `platform`.

### 5.3 `Admin\UserController::scopeRank()` (private helper)

Tambah `'platform' => 4` (lebih tinggi dari `'yayasan' => 3`) supaya gate rank-assignment role (dipakai saat admin menetapkan role ke user lain) tidak keliru menganggap `platform` sebagai rank terendah (saat ini jatuh ke `default => 1`, kebalikan dari yang seharusnya).

## 6. Perbaikan Backend — Query & Filter

### 6.1 `UserController::index()`

- Tambah query param `scope_group` (nilai valid: `platform`, `yayasan`, `lembaga`, `staf`, `orang_tua`, `siswa`; kosong/tidak ada = "Semua").
- Tambah `username` ke kondisi `orWhere` pencarian existing (`name`, `email`) → jadi `name`, `email`, `username`.
- **Hapus** `whereDoesntHave('roles', fn ($q) => $q->where('name', 'siswa'))` dari query utama DAN dari 3 query count (`$totalUsers`, `$totalAktif`, `$totalNonaktif`) — siswa sekarang ikut terhitung dan tampil di semua chip yang relevan (Semua, Siswa).
- Filter `scope_group` diterapkan via `whereHas('roles', fn ($q) => $q->whereIn('name', [...daftar role grup sesuai §4...]))`.
- Filter `role` (select existing) tetap berlaku bersamaan dengan `scope_group` (AND, bukan OR) — jadi user bisa pilih chip Lembaga lalu mempersempit lagi ke role `kepala_sekolah` spesifik via select.
- Tambah 7 query `count()` (satu per chip: `semua`, `platform`, `yayasan`, `lembaga`, `staf`, `orang_tua`, `siswa`), masing-masing kena `TenantScope` yang sama seperti query utama (otomatis konsisten dengan visibilitas viewer yang login) — dipakai untuk badge angka di tiap chip.
- Tambah variabel `$isPlatformViewer = auth()->user()->widestScopeLevel() === 'platform'`, dipassing ke view — dipakai untuk menampilkan kolom Yayasan/Lembaga di tabel HANYA untuk viewer ini.
- Query utama tetap `->with('roles', 'lembaga')`, tambah `->with('yayasan')` juga (untuk kolom Yayasan saat `$isPlatformViewer`).
- Response AJAX (`_daftar` partial) dan response biasa (`index` full page) sama-sama menerima variabel baru ini.

### 6.2 Daftar Role per Grup (untuk query `whereIn` §6.1 dan Select Role dinamis §7)

Constant/array berikut didefinisikan sekali di controller (atau helper method `scopeGroups(): array`), dipakai baik untuk filter query maupun untuk opsi select role per chip:

```php
private function scopeGroups(): array
{
    return [
        'platform' => ['platform_super_admin'],
        'yayasan' => ['yayasan_super_admin', 'bendahara_yayasan', 'pegawai_yayasan'],
        'lembaga' => ['kepala_sekolah', 'wakasek_kurikulum', 'wakasek_kesiswaan', 'operator_akademik', 'admin_sdm', 'bendahara_lembaga', 'admin_sarpras', 'admin_administrasi', 'pegawai_lembaga'],
        'staf' => ['guru', 'wali_kelas', 'guru_bk'],
        'orang_tua' => ['orang_tua'],
        'siswa' => ['siswa'],
    ];
}
```

## 7. Perbaikan Frontend

### 7.1 Chip Scope (`resources/views/admin/users/index.blade.php` + `resources/js/data-table-filter.js`)

- Tambah state `scopeGroup` (default `''`/null = Semua) ke komponen Alpine `dataTableFilter()` yang sudah ada di `data-table-filter.js`.
- 7 chip button (Semua + 6 kategori), pola visual sama seperti chip Kehadiran SDM (pill + badge angka), tapi `@click` men-set `scopeGroup` lalu memanggil `muatUlangDaftar()` yang SUDAH ADA (fetch AJAX + swap `#tabel-container` + `history.pushState`) — TIDAK membuat komponen Alpine baru, murni extend yang sudah ada.
- Badge angka tiap chip diisi dari 7 nilai count yang dikirim controller (§6.1), bukan dihitung ulang di client.

### 7.2 Select Role Dinamis

- Data role-per-grup (hasil `scopeGroups()`, sudah di-resolve jadi nama-nama role aktual dari DB) di-embed sebagai JSON di Blade (kecil, aman inline, tidak perlu endpoint AJAX terpisah).
- Saat `scopeGroup` berubah, JS memanggil Tom Select API (`clearOptions()` lalu `addOption()`) untuk mengisi ulang opsi select Role sesuai grup aktif. Saat "Semua" dipilih, select Role kembali menampilkan seluruh role (perilaku existing).

### 7.3 Kolom Yayasan/Lembaga (`resources/views/admin/users/_daftar.blade.php`)

- Header kolom "Yayasan" dan "Lembaga" muncul kondisional `@if($isPlatformViewer)`, diisi dari relasi `$user->yayasan->nama` / `$user->lembaga->nama` (null-safe, tampilkan "-" kalau kosong — relevan untuk akun pool yang `lembaga_id` null).
- Untuk viewer non-platform, kolom ini TIDAK muncul (perilaku tabel existing dipertahankan).

### 7.4 Search Placeholder

- Update teks placeholder input search dari "Cari nama atau email..." (atau teks existing serupa) menjadi "Cari nama, email, atau username...".

## 8. Testing

1. **Regresi isolasi tenant (paling kritis)** — test baru yang membuktikan `yayasan_super_admin` dan role `lembaga`/`diri_sendiri` TETAP TIDAK BISA melihat user lintas-tenant setelah perubahan `TenantScope` — bukti bypass benar-benar sempit ke `platform` + model `User` saja.
2. **`widestScopeLevel()` untuk `platform_super_admin`** → harus resolve ke `'platform'` (belum ada test untuk ini sama sekali sebelumnya).
3. **Test halaman Pengguna**: masing-masing dari 6 chip kategori menampilkan role yang tepat sesuai §4, chip Semua menampilkan semua termasuk siswa, search mencakup username, kolom Yayasan/Lembaga hanya muncul untuk viewer `platform_super_admin`.
4. **Test count badge**: angka di tiap chip cocok dengan hasil query filter chip yang sama.
5. **Test `platform_super_admin` melihat lintas-tenant** — buat 2 lembaga di 2 yayasan berbeda, buat user di masing-masing, buktikan `platform_super_admin` melihat KEDUANYA dalam satu query/halaman.
6. Tidak perlu test tambahan untuk model lain (`Karyawan`, `Tagihan`, dst) karena bypass `TenantScope` sengaja tidak menyentuhnya (§3 Non-Goals, §5.2).

## 9. Ringkasan File yang Disentuh

- `app/Models/User.php` — perbaikan `widestScopeLevel()`.
- `app/Models/Scopes/TenantScope.php` — tambah cabang bypass `platform` + model `User`.
- `app/Http/Controllers/Admin/UserController.php` — `scopeRank()`, `index()` (query scope_group, hapus exclude siswa, tambah username ke search, tambah count per chip, tambah `scopeGroups()` helper, tambah `$isPlatformViewer`).
- `resources/views/admin/users/index.blade.php` — 7 chip scope, placeholder search.
- `resources/views/admin/users/_daftar.blade.php` — kolom Yayasan/Lembaga kondisional.
- `resources/js/data-table-filter.js` — state `scopeGroup` + refresh Tom Select role dinamis.
- Test baru: isolasi tenant regresi, `widestScopeLevel` platform, filter chip, count badge, cross-tenant visibility platform admin (lokasi test disesuaikan konvensi existing, kemungkinan `tests/Feature/Admin/UserManagementTest.php` yang sudah ada + `tests/Unit/UserScopeLevelTest.php` baru kalau belum ada test unit untuk method ini).
