# Spec: Perbaikan Visibilitas Menu & Keamanan Scope Yayasan/Lembaga

> **Branch**: `rbac-v2` (setara `akademik-v2`, sudah merge-ready)
> **Tanggal**: 7 September 2026
> **Latar belakang**: Client request — "fitur yang hanya bisa diakses lembaga tidak usah tampil di yayasan, kalau ada cenderung diklik. Contoh rekap kehadiran yang cuma aktif di lembaga jangan muncul di yayasan, atau di yayasan tampil rekapan semua lembaga." Diaudit lewat 3 subagent riset paralel yang membaca kode semua controller di sidebar (kecuali SPMB/PPDB yang sengaja dibekukan, TIDAK disentuh sama sekali oleh spec ini).

## Konteks Mekanisme (WAJIB dipahami sebelum baca detail fix)

- **Scope `lembaga`**: `user.lembaga_id` terisi, semua query otomatis terbatas ke lembaga itu.
- **Scope `yayasan`**: `user.widestScopeLevel() === 'yayasan'`. Punya switcher lembaga di topbar (`resources/views/layouts/topbar.blade.php`) yang mengatur `session('active_lembaga_id')` lewat query param `switch_lembaga` (`app/Http/Middleware/ResolveTenant.php`): `switch_lembaga=all` → `session()->forget('active_lembaga_id')` (mode "Semua Lembaga"); `switch_lembaga={id}` → `session(['active_lembaga_id' => id])`.
- **`TenantScope`** (`app/Models/Scopes/TenantScope.php`), global scope pada model dengan trait `BelongsToTenant`: untuk aktor yayasan dengan `active_lembaga_id` kosong, otomatis `whereIn('lembaga_id', semua lembaga milik yayasan aktor)` — **agregat otomatis**, bukan unscoped total. Dengan `active_lembaga_id` terisi, filter ke lembaga itu saja.
- Mayoritas fitur di codebase ini SUDAH BENAR karena murni mengandalkan `TenantScope` otomatis. Spec ini HANYA menyasar controller yang menulis query manual sendiri dan salah menanganinya.

## Ruang Lingkup

4 kategori perbaikan independen (A, B, C, D) — dikerjakan sebagai 1 plan karena semuanya kecil & saling tidak bergantung, tapi masing-masing testable terpisah. Section "Di Luar Scope" di akhir mencantumkan apa yang SENGAJA tidak dikerjakan.

---

## Kategori A — Bug Keamanan Kritis: Bocor Data Lintas-YAYASAN

**Prioritas tertinggi.** Ditemukan sebagai efek samping audit, tidak terkait langsung topik menu, tapi HARUS diperbaiki: 3 controller berikut, untuk aktor scope **yayasan**, sama sekali tidak membatasi query ke yayasan milik aktor — menampilkan data milik yayasan LAIN di seluruh sistem.

### A.1 — `KasusAksesLogController::index()` (`app/Http/Controllers/Admin/KasusAksesLogController.php`)

Kode saat ini (baris 25-31):
```php
$baseQuery = Activity::query()
    ->where('log_name', 'akses_klinis')
    ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->whereHasMorph(
        'subject',
        [Kasus::class],
        fn ($subQuery) => $subQuery->withoutGlobalScopes()->withTrashed()->where('lembaga_id', $user->lembaga_id)
    ));
```
Untuk `widestScopeLevel() === 'yayasan'`, cabang `when()` di atas TIDAK PERNAH jalan — `$baseQuery` sama sekali tidak difilter, menampilkan log akses kasus klinis milik SEMUA yayasan di sistem.

**Fix**: ganti kondisi `when()` tunggal menjadi 2 cabang eksplisit — lembaga tunggal (kondisi sekarang, tidak berubah) DAN yayasan (baru, filter ke daftar lembaga milik yayasan aktor, dipersempit ke 1 lembaga kalau `active_lembaga_id` sedang dipilih via switcher):

```php
$lembagaIdsYayasan = Lembaga::where('yayasan_id', $user->yayasan_id)->pluck('id');
$activeLembagaId = session('active_lembaga_id');

$baseQuery = Activity::query()
    ->where('log_name', 'akses_klinis')
    ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->whereHasMorph(
        'subject',
        [Kasus::class],
        fn ($subQuery) => $subQuery->withoutGlobalScopes()->withTrashed()->where('lembaga_id', $user->lembaga_id)
    ))
    ->when($user->widestScopeLevel() === 'yayasan', fn ($q) => $q->whereHasMorph(
        'subject',
        [Kasus::class],
        function ($subQuery) use ($lembagaIdsYayasan, $activeLembagaId) {
            $subQuery->withoutGlobalScopes()->withTrashed();
            $activeLembagaId
                ? $subQuery->where('lembaga_id', $activeLembagaId)
                : $subQuery->whereIn('lembaga_id', $lembagaIdsYayasan);
        }
    ));
```
Tambah `use App\Models\Lembaga;` di import kalau belum ada.

**Acceptance criteria**:
1. Yayasan A, mode "Semua Lembaga" → hanya lihat log akses kasus dari lembaga-lembaga milik yayasan A, TIDAK ada baris dari yayasan lain.
2. Yayasan A, switcher pilih lembaga X (milik yayasan A) → hanya lihat log akses kasus milik lembaga X.
3. User lembaga-scope → perilaku TIDAK BERUBAH (regresi test wajib hijau).
4. Test baru: buat `Activity` log utk kasus di yayasan B, login sebagai yayasan A mode "Semua Lembaga", assert baris itu TIDAK muncul.

### A.2 — `KasusTerhapusController::index()` (`app/Http/Controllers/Admin/KasusTerhapusController.php`)

Kode saat ini (baris 26-28):
```php
$baseQuery = Kasus::onlyTrashed()
    ->withoutGlobalScope(TenantScope::class)
    ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->where('lembaga_id', $user->lembaga_id));
```
Pola sama persis dengan A.1, lebih sederhana karena `Kasus` punya kolom `lembaga_id` langsung (tidak lewat morph).

**Fix**:
```php
$lembagaIdsYayasan = Lembaga::where('yayasan_id', $user->yayasan_id)->pluck('id');
$activeLembagaId = session('active_lembaga_id');

$baseQuery = Kasus::onlyTrashed()
    ->withoutGlobalScope(TenantScope::class)
    ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->where('lembaga_id', $user->lembaga_id))
    ->when($user->widestScopeLevel() === 'yayasan', fn ($q) => $activeLembagaId
        ? $q->where('lembaga_id', $activeLembagaId)
        : $q->whereIn('lembaga_id', $lembagaIdsYayasan));
```

**Acceptance criteria**: sama persis pola A.1 (poin 1-4), disesuaikan ke `Kasus::onlyTrashed()`.

### A.3 — `OrangTuaController::index()` (`app/Http/Controllers/Admin/OrangTuaController.php`)

Kode saat ini (baris 35-39):
```php
$orangTuaList = OrangTua::with(['user' => fn ($q) => $q->withoutGlobalScope(TenantScope::class), 'person'])
    ->withCount('siswa')
    ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->where(fn ($q2) => $q2
        ->whereDoesntHave('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class))
        ->orWhereHas('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class)->where('siswa.lembaga_id', $user->lembaga_id))))
    ->when($search, fn ($q) => $q->search($search))
    ->orderByNama()
    ->get();
```
`OrangTua.lembaga_id` SELALU `null` by design (lihat komentar di kode) — scoping-nya lewat relasi `siswa.lembaga_id`. Untuk yayasan, `when()` di atas tidak pernah jalan — semua orang tua di SELURUH sistem (lintas yayasan lain) ikut tampil.

**Fix**: tambah cabang paralel untuk yayasan, dengan struktur `whereDoesntHave`/`whereHas` yang SAMA (supaya orang tua tanpa siswa terdaftar tetap konsisten diikutkan seperti perilaku lembaga-scope sekarang), tapi filter siswa ke daftar lembaga milik yayasan (atau 1 lembaga kalau `active_lembaga_id` terisi):

```php
$lembagaIdsYayasan = Lembaga::where('yayasan_id', $user->yayasan_id)->pluck('id');
$activeLembagaId = session('active_lembaga_id');

$orangTuaList = OrangTua::with(['user' => fn ($q) => $q->withoutGlobalScope(TenantScope::class), 'person'])
    ->withCount('siswa')
    ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->where(fn ($q2) => $q2
        ->whereDoesntHave('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class))
        ->orWhereHas('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class)->where('siswa.lembaga_id', $user->lembaga_id))))
    ->when($user->widestScopeLevel() === 'yayasan', fn ($q) => $q->where(function ($q2) use ($lembagaIdsYayasan, $activeLembagaId) {
        $q2->whereDoesntHave('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class))
            ->orWhereHas('siswa', function ($q3) use ($lembagaIdsYayasan, $activeLembagaId) {
                $q3->withoutGlobalScope(TenantScope::class);
                $activeLembagaId
                    ? $q3->where('siswa.lembaga_id', $activeLembagaId)
                    : $q3->whereIn('siswa.lembaga_id', $lembagaIdsYayasan);
            });
    }))
    ->when($search, fn ($q) => $q->search($search))
    ->orderByNama()
    ->get();
```
Tambah `use App\Models\Lembaga;` kalau belum ada (file sudah import `App\Models\Yayasan`, cek dulu apakah `Lembaga` sudah ke-import — dari baca kode terakhir belum).

**Acceptance criteria**:
1. Yayasan A mode "Semua Lembaga" → hanya lihat orang tua yang siswa-nya terdaftar di lembaga milik yayasan A (+ orang tua tanpa siswa sama sekali, konsisten dgn perilaku lama), TIDAK ada orang tua dari yayasan lain.
2. Yayasan A pilih lembaga X → hanya orang tua dengan siswa di lembaga X.
3. Perilaku lembaga-scope TIDAK BERUBAH.
4. Test baru: siswa+orangtua di yayasan B, login yayasan A mode "Semua Lembaga", assert orang tua itu TIDAK muncul di list maupun `totalOrangTua`/`totalAktif`.

---

## Kategori B — Menu "Ruang Guru": Gerbang Identitas yang Salah

**Bukan soal cakupan data — soal apakah user ini SECARA IDENTITAS adalah guru.** `yayasan_super_admin` (dan role yayasan lain) mendapat SEMUA permission lewat `RolePermissionAssignmentSeeder` → `Role::findByName('yayasan_super_admin')->syncPermissions(Permission::all())`. Akibatnya menu di `resources/views/layouts/sidebar.blade.php` grup "Ruang Guru" yang cuma dicek lewat `can('permission')` ikut tampil untuk user yang bukan guru sama sekali (tidak punya record `Guru`).

### Verifikasi pola acuan (SUDAH ADA di file yang sama, TIDAK diubah)

Baris 19-22 sidebar.blade.php sudah pakai pola benar:
```php
Auth::user()->hasRole('guru') && Auth::user()->can('rpp.view') ? [...] : null,
Auth::user()->hasRole('guru') && Auth::user()->can('kehadiran-sdm.lihat-qr-sendiri') ? [...] : null,
Auth::user()->hasRole('guru') && Auth::user()->can('kehadiran-sdm.izin.lihat-sendiri') ? [...] : null,
Auth::user()->hasRole('guru') && Auth::user()->can('viewAny', \App\Domains\Kasus\Models\Kasus::class) ? [...] : null,
```
Pola acuan ini pakai `hasRole('guru')` (label role), BUKAN `Auth::user()->guru !== null` (record relasi). Terverifikasi `Auth::user()->guru` adalah relasi `HasOneThrough` yang valid (`app/Models/User.php:76`). Karena baris 19-22 sudah lama ada & konsisten dipakai berulang di file yang sama sebagai pola tunggal, **spec ini mengikuti pola yang SAMA PERSIS (`hasRole('guru')`)** untuk 5 item yang diperbaiki di bawah — BUKAN `guru !== null` — supaya konsisten dengan konvensi yang sudah mapan di file ini. (Kedua cara secara praktik menghasilkan hasil sama untuk data demo saat ini, karena setiap akun dengan role `guru` juga punya record `Guru`; memilih `hasRole('guru')` semata-mata untuk konsistensi pola dalam 1 file, bukan karena `guru !== null` salah.)

### Fix — 5 item baris 14-18 (`resources/views/layouts/sidebar.blade.php`, grup "Ruang Guru")

Sebelum:
```php
Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.index', 'pattern' => 'guru.jurnal-kbm.index', 'label' => 'Jurnal & Presensi', 'icon' => 'file-pen'] : null,
Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.rekap', 'pattern' => 'guru.jurnal-kbm.rekap', 'label' => 'Rekap Kehadiran', 'icon' => 'chart-bar'] : null,
Auth::user()->can('komponen-penilaian.kelola-sendiri') ? ['route' => 'guru.komponen-penilaian.index', 'pattern' => 'guru.komponen-penilaian.*', 'label' => 'Komponen Penilaian (TP)', 'icon' => 'list-todo'] : null,
Auth::user()->can('asesmen.kelola') ? ['route' => 'guru.asesmen.index', 'pattern' => 'guru.asesmen.*', 'label' => 'Asesmen & Nilai', 'icon' => 'bar-chart-3'] : null,
Auth::user()->can('rapor.input-wali') ? ['route' => 'guru.rapor.catatan.index', 'pattern' => 'guru.rapor.*', 'label' => 'Rapor Wali Kelas', 'icon' => 'book-text'] : null,
```
Sesudah (tambah `Auth::user()->hasRole('guru') &&` di depan tiap kondisi, JANGAN ubah permission yang sudah ada):
```php
Auth::user()->hasRole('guru') && Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.index', 'pattern' => 'guru.jurnal-kbm.index', 'label' => 'Jurnal & Presensi', 'icon' => 'file-pen'] : null,
Auth::user()->hasRole('guru') && Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.rekap', 'pattern' => 'guru.jurnal-kbm.rekap', 'label' => 'Rekap Kehadiran', 'icon' => 'chart-bar'] : null,
Auth::user()->hasRole('guru') && Auth::user()->can('komponen-penilaian.kelola-sendiri') ? ['route' => 'guru.komponen-penilaian.index', 'pattern' => 'guru.komponen-penilaian.*', 'label' => 'Komponen Penilaian (TP)', 'icon' => 'list-todo'] : null,
Auth::user()->hasRole('guru') && Auth::user()->can('asesmen.kelola') ? ['route' => 'guru.asesmen.index', 'pattern' => 'guru.asesmen.*', 'label' => 'Asesmen & Nilai', 'icon' => 'bar-chart-3'] : null,
Auth::user()->hasRole('guru') && Auth::user()->can('rapor.input-wali') ? ['route' => 'guru.rapor.catatan.index', 'pattern' => 'guru.rapor.*', 'label' => 'Rapor Wali Kelas', 'icon' => 'book-text'] : null,
```

**Catatan penting — ini MURNI perbaikan navigasi sidebar (visibility), BUKAN perubahan authorization backend.** Route/controller di baliknya (`JurnalKbmController`, dsb) TIDAK disentuh sama sekali oleh spec ini — mereka sudah py guard sendiri lewat `authorizeMilikGuru()`/`PiketAccessChecker` (dari Proyek C, akademik-v2) yang independen dari sidebar. Spec ini hanya soal APAKAH LINK-NYA MUNCUL, bukan APAKAH AKSESNYA DITOLAK.

**Acceptance criteria**:
1. Test render sidebar (atau test yang sudah ada kalau ada — cek dulu apakah ada test sidebar existing selain `SidebarPengelompokanTest.php` yang disebut di riwayat project) — login sebagai `yayasan_super_admin` (tanpa record Guru) → assertDontSee ke-5 label menu ini.
2. Login sebagai akun demo dengan role `guru` (py permission terkait) → assertSee ke-5 label seperti sebelumnya (regresi, tidak boleh hilang untuk guru asli).
3. Login sebagai admin lembaga biasa (bukan guru, bukan yayasan) yang entah kenapa py salah satu permission ini (skenario edge, kalau ada) → assertDontSee juga (fix ini menyasar SEMUA non-guru, bukan cuma yayasan — sesuai catatan "argumen" di section ini).

---

## Kategori C — Menu "Scan QR": Aksi Fisik On-Site, Wajib 1 Lembaga Aktif

Route `admin.kehadiran-sdm.scan.index` → `AttendanceQrScanController::index()` (`app/Http/Controllers/Admin/AttendanceQrScanController.php`). Scan QR presensi adalah aksi yang secara fisik terjadi di 1 lokasi lembaga — tidak masuk akal dilakukan yayasan dalam mode "Semua Lembaga".

### C.1 — Sembunyikan menu di sidebar saat mode "Semua Lembaga"

`resources/views/layouts/sidebar.blade.php` baris 88:
```php
Auth::user()->can('kehadiran-sdm.catat') ? ['route' => 'admin.kehadiran-sdm.scan.index', 'pattern' => 'admin.kehadiran-sdm.scan.*', 'label' => 'Scan QR', 'icon' => 'qr-code'] : null,
```
Fix — tambah kondisi: kalau `widestScopeLevel() === 'yayasan'`, WAJIB `session('active_lembaga_id')` terisi. User lembaga-scope biasa otomatis lolos (dia selalu "di lembaganya sendiri"):
```php
Auth::user()->can('kehadiran-sdm.catat') && (Auth::user()->widestScopeLevel() !== 'yayasan' || session('active_lembaga_id') !== null) ? ['route' => 'admin.kehadiran-sdm.scan.index', 'pattern' => 'admin.kehadiran-sdm.scan.*', 'label' => 'Scan QR', 'icon' => 'qr-code'] : null,
```

### C.2 — Guard server-side di `AttendanceQrScanController::index()`

Kode saat ini (baris 20-28) TIDAK abort sama sekali kalau `lembagaId` null — cuma diam-diam menampilkan `titikAbsen` kosong. `store()` (baris 30-44) SUDAH BENAR (abort 422 kalau `lembagaId === null`, baris 42-44). Samakan `index()` dengan pola `store()`:
```php
public function index(Request $request): View
{
    $this->authorize('kehadiran-sdm.catat');

    $lembagaId = $this->resolveLembagaId($request);

    abort_if($lembagaId === null, 422, 'Pilih lembaga aktif melalui pengalih lembaga sebelum scan QR.');

    $titikAbsen = AttendancePoint::where('lembaga_id', $lembagaId)->where('is_active', true)->orderBy('nama')->get();

    return view('admin.kehadiran-sdm.scan', ['titikAbsen' => $titikAbsen]);
}
```
(Perlu import `Illuminate\Http\Request` — cek sudah ada, sudah dipakai di signature saat ini.)

**Acceptance criteria**:
1. Yayasan mode "Semua Lembaga" → menu "Scan QR" TIDAK muncul di sidebar.
2. Yayasan pilih 1 lembaga via switcher → menu MUNCUL, dan `index()` render normal (bukan 422).
3. Yayasan mode "Semua Lembaga" AKSES LANGSUNG via URL (`GET admin/kehadiran-sdm/scan`) → 422, BUKAN 200 dengan halaman kosong (menutup celah "sembunyikan link doang").
4. User lembaga-scope biasa → tidak ada perubahan perilaku (regresi test wajib hijau).

---

## Kategori D — Bug Data "Diam-Diam Salah/Kosong" Saat Mode "Semua Lembaga"

**Bukan soal sembunyikan menu — semua ini harus TETAP tampil, cuma datanya harus benar (agregat), bukan salah/kosong.**

### D.1 — `Lembaga\Keuangan\VirtualAccountController::index()` (`app/Http/Controllers/Lembaga/Keuangan/VirtualAccountController.php`)

`lembagaId()` (baris 180-185) return `session('active_lembaga_id')` mentah (bisa `null`), dipakai langsung sebagai `where('lembaga_id', $lembagaId)` di 4 tempat dalam `index()` (baris 33-35, 58-59, 62-63, 67, 72). Saat null → Eloquent compile jadi `whereNull('lembaga_id')`, SEMUA angka jadi nol/kosong diam-diam (bukan error, bukan agregat).

`Siswa` model SUDAH pakai `BelongsToTenant` (terverifikasi via TenantScope: agregat otomatis ke semua lembaga milik yayasan aktor saat `active_lembaga_id` null). **Fix paling sederhana: bungkus tiap `where('lembaga_id', $lembagaId)` dengan `when($lembagaId !== null, ...)`** — kalau `$lembagaId` terisi (lembaga-scope ATAU yayasan yang sudah pilih lembaga), filter jalan seperti sekarang (tidak berubah); kalau null (yayasan mode "Semua Lembaga"), filter di-skip dan `Siswa`'s TenantScope sendiri yang menangani agregat otomatis.

Terapkan di SEMUA closure `where('lembaga_id', $lembagaId)` dalam method `index()` (baris ~34, 58-59, 62-63, 67):
```php
// Sebelum: $q->where('lembaga_id', $lembagaId);
// Sesudah:
$q->when($lembagaId !== null, fn ($q2) => $q2->where('lembaga_id', $lembagaId));
```
Untuk `$kelasList` (baris 72, query langsung ke `Kelas::where('lembaga_id', $lembagaId)`, bukan closure) — `Kelas` juga pakai `BelongsToTenant`, jadi:
```php
$kelasList = Kelas::when($lembagaId !== null, fn ($q) => $q->where('lembaga_id', $lembagaId))
    ->with('tahunAjaran')->orderBy('nama')->get();
```

**Catatan scope**: method lain di controller ini (`riwayat()`, `calonGenerate()`, `generate()`, `export()`) memakai helper `lembagaId()` yang sama dan berpotensi py bug null-mode serupa, TAPI di luar scope numbered fix ini (spec eksplisit cuma `index()` sesuai keputusan awal) — catat sebagai temuan tambahan di bagian akhir plan/handoff nanti, JANGAN diperbaiki sekarang supaya scope tidak melebar tanpa keputusan eksplisit.

**Acceptance criteria**:
1. Yayasan mode "Semua Lembaga" → `totalVa`, `totalSaldo`, `totalBelumVa`, list VA menampilkan AGREGAT dari semua lembaga milik yayasan (bukan nol).
2. Yayasan pilih 1 lembaga → perilaku sama seperti sekarang (narrow ke lembaga itu).
3. Lembaga-scope biasa → tidak berubah.
4. Test baru: 2 lembaga beda di 1 yayasan, masing-masing punya VA, assert `totalVa`/list menghitung KEDUANYA saat mode "Semua Lembaga".

### D.2 — `Lembaga\Keuangan\ManualPaymentController::index()` (`app/Http/Controllers/Lembaga/Keuangan/ManualPaymentController.php`)

Bug identik D.1, di **3 tempat** (bukan 2 — `totalNominalMenunggu` di baris 61-63 gampang terlewat karena identik dengan `totalMenunggu` di atasnya): baris 28 (`whereHas('pembayaran', ...)` → `whereHas('siswa', ...)`), baris 58-59 (`totalMenunggu`), dan baris 61-63 (`totalNominalMenunggu`) — semuanya filter manual lewat relasi `pembayaran.siswa`. Fix sama: bungkus SEMUA `where('lembaga_id', $lembagaId)` dengan `when($lembagaId !== null, ...)`.

**Acceptance criteria**: sama pola D.1, disesuaikan ke `ManualPaymentRequest`.

### D.3 — `Admin\AttendanceConfigurationController::index()` (`app/Http/Controllers/Admin/AttendanceConfigurationController.php`)

Baris 53-94: 5 query (`$konfigurasi`, `$kalenderEntriList`, `$policyList`, `$jenisShiftList`, `$kuotaCutiList`) semuanya sudah `where('yayasan_id', $yayasanId)` (BENAR, sudah scoped ke yayasan), lalu ditambah `->where(fn($q) => $q->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id'))`. Saat `$lembagaId` null (yayasan blm pilih lembaga), ini jadi cuma nasional (`lembaga_id IS NULL`) — semua entri per-lembaga hilang dari tampilan padahal ada.

**Fix**: kalau `$lembagaId` null, JANGAN tambahkan sub-where lembaga_id/null sama sekali — cukup filter `yayasan_id` saja (yang sudah otomatis mencakup entri nasional + semua lembaga di bawah yayasan itu, karena SEMUA baris entah nasional atau per-lembaga tetap simpan `yayasan_id` yang sama). Contoh utk `$konfigurasi` (baris 53-58), pola sama diterapkan ke 4 query lain (`$kalenderEntriList`, `$policyList`, `$jenisShiftList`, `$kuotaCutiList`):
```php
$konfigurasi = AttendanceMethodConfiguration::withoutGlobalScope(TenantScope::class)
    ->where('yayasan_id', $yayasanId)
    ->when($lembagaId !== null, fn ($q) => $q->where(function ($q2) use ($lembagaId) {
        $q2->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
    }))
    ->get();
```
**JANGAN ubah** `$titikAbsen`, `$penugasanShiftList`, `$guruList`, `$karyawanList` (baris 60, 96-109) — ini daftar-daftar utk keperluan FORM TAMBAH BARU (create titik absen/penugasan shift/dst), yang MEMANG butuh 1 lembaga spesifik (tidak ada konsep "titik absen nasional lintas lembaga"). Tetap kosong saat `$lembagaId` null — ini SUDAH BENAR (pola "BERSYARAT: create wajib pilih lembaga dulu"), bukan bagian dari bug ini.

**Acceptance criteria**:
1. Yayasan mode "Semua Lembaga" → `$konfigurasi`/`$kalenderEntriList`/`$policyList`/`$jenisShiftList`/`$kuotaCutiList` menampilkan GABUNGAN entri nasional + SEMUA entri per-lembaga milik yayasan itu.
2. Yayasan pilih 1 lembaga → perilaku sama seperti sekarang (nasional + lembaga itu saja).
3. `$titikAbsen`/`$penugasanShiftList`/`$guruList`/`$karyawanList` TIDAK berubah (tetap kosong saat blm pilih lembaga).
4. Test baru: 2 lembaga beda 1 yayasan masing2 py `AttendancePolicy` sendiri + 1 nasional, assert mode "Semua Lembaga" menampilkan ke-3nya.

### D.4 — Kartu Ringkasan Statistik Sarpras/Pengadaan (4 controller)

Pola bug identik di 4 file — query LIST sudah py fallback benar (`if ($lembagaId) {...} elseif ($yayasanId) {...}`), tapi query STATS terpisah tidak pakai fallback yang sama:

1. `app/Http/Controllers/Lembaga/Sarpras/GedungController.php::index()` — baris 58-60 (`$totalGedung`, `$totalLantai`, `$totalRuangan`) vs fallback yang benar di baris 34-39.
2. `app/Http/Controllers/Lembaga/Sarpras/RuanganController.php::index()` — `$totalRuangan`, `$totalKelas`, `$totalLab`, `$totalShared` (baris ~69-72) vs fallback benar di baris ~39-46.
3. `app/Http/Controllers/Lembaga/Sarpras/KategoriAsetController.php::index()` — baris 56-57: `$totalKategori = KategoriAset::where('lembaga_id', $lembagaId)->count();` dan `$totalAset = \App\Domains\Sarpras\Models\AsetBarang::where('lembaga_id', $lembagaId)->count();`, dibanding fallback benar di baris 32-36 (`if ($lembagaId) {...} elseif ($yayasanId) {...}`).
4. `app/Http/Controllers/Lembaga/Pengadaan/PengajuanPengadaanController.php::index()` — baris 60-65, array `$stats` (`total`, `draft`, `in_review`, `disbursed`, `completed`) semuanya `PengajuanPengadaan::where('lembaga_id', $lembagaId)->...`, dibanding fallback benar di baris 41-45.

**Fix — pola seragam untuk ke-4 file**: gunakan query builder yang SAMA dengan yang dipakai list (`clone $query` sebelum `paginate()`, atau bungkus filter fallback yang identik dari list query) untuk menghitung stats, JANGAN bikin query stats terpisah dengan filter polos `where('lembaga_id', $lembagaId)`. Contoh konkret untuk `GedungController` (pola yang SAMA persis wajib dicontek untuk 3 file lain, dengan model/kolom masing-masing):
```php
$statsFilter = function ($query) use ($lembagaId, $yayasanId) {
    $query->where(function ($q) use ($lembagaId, $yayasanId) {
        if ($lembagaId) {
            $q->where('lembaga_id', $lembagaId);
        } elseif ($yayasanId) {
            $q->where('yayasan_id', $yayasanId);
        }
    });
};

$totalGedung = Gedung::where($statsFilter)->count();
$totalLantai = (int) Gedung::where($statsFilter)->sum('jumlah_lantai');
$totalRuangan = \App\Domains\Sarpras\Models\Ruangan::where($statsFilter)->count();
```
(`Ruangan::where($statsFilter)` valid dipakai di sini — model `Ruangan` terverifikasi punya kolom `lembaga_id` dan `yayasan_id` di `$fillable` (`app/Domains/Sarpras/Models/Ruangan.php`). Untuk `totalRuangan`/`totalKelas`/`totalLab`/`totalShared` di `RuanganController` sendiri, list query-nya (baris ~39-46) punya tambahan cabang `orWhere(yayasan_id + is_shared)` yang lebih spesifik dari `$statsFilter` polos di atas — REUSE closure fallback milik `RuanganController` sendiri (bukan `$statsFilter` versi `GedungController`) untuk ke-4 stats itu, supaya konsisten dengan aturan "shared ruangan" yang sudah ada di list-nya.)

Untuk `PengajuanPengadaanController::index()`, `$stats` berbentuk array (bukan variabel individual) — pola fix yang sama, cukup bungkus tiap `where('lembaga_id', $lembagaId)` di baris 61-65 dengan `$statsFilter` yang sama:
```php
$statsFilter = function ($query) use ($lembagaId, $yayasanId) {
    $query->where(function ($q) use ($lembagaId, $yayasanId) {
        if ($lembagaId) {
            $q->where('lembaga_id', $lembagaId);
        } elseif ($yayasanId) {
            $q->where('yayasan_id', $yayasanId);
        }
    });
};

$stats = [
    'total' => PengajuanPengadaan::where($statsFilter)->count(),
    'draft' => PengajuanPengadaan::where($statsFilter)->where('status', StatusPengajuan::Draft)->count(),
    'in_review' => PengajuanPengadaan::where($statsFilter)->whereIn('status', [StatusPengajuan::Submitted, StatusPengajuan::InReview])->count(),
    'disbursed' => PengajuanPengadaan::where($statsFilter)->where('status', StatusPengajuan::Disbursed)->count(),
    'completed' => PengajuanPengadaan::where($statsFilter)->where('status', StatusPengajuan::Completed)->count(),
];
```

**Acceptance criteria** (per 4 file):
1. Yayasan mode "Semua Lembaga" → kartu ringkasan MENCERMINKAN angka yang sama dengan yang bisa dihitung manual dari tabel list-nya (agregat semua lembaga milik yayasan), TIDAK nol/tidak kontradiktif dengan tabel.
2. Yayasan pilih 1 lembaga → kartu tetap narrow ke lembaga itu (tidak berubah).
3. Lembaga-scope biasa → tidak berubah.
4. Test baru per file: 2 lembaga beda 1 yayasan masing2 py data, assert kartu ringkasan menghitung gabungan keduanya saat mode "Semua Lembaga".

### D.5 — `Admin\RaporController::index()` — Dropdown Tahun Ajaran Tanpa Label Lembaga

Baris 70: `'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get()`. `TahunAjaran` pakai `BelongsToTenant` — saat yayasan mode "Semua Lembaga", ini agregat SEMUA tahun ajaran dari SEMUA lembaga milik yayasan, tapi dropdown-nya cuma menampilkan `nama` tahun ajaran (mis. "2026/2027") tanpa label lembaga — kalau 2 lembaga sama-sama punya tahun ajaran "2026/2027", user tidak bisa membedakan mana yang mana.

`TahunAjaran::lembaga(): BelongsTo` (`app/Models/TahunAjaran.php:29`) terverifikasi ada. View pemakai `$tahunAjaranList` adalah `resources/views/portals/lembaga/akademik/rapor/index.blade.php` baris 35-36:
```blade
@foreach ($tahunAjaranList as $tahunAjaran)
    <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
@endforeach
```

**Fix** — controller (baris 70), eager-load relasi lembaga:
```php
'tahunAjaranList' => TahunAjaran::with('lembaga')->orderByDesc('id')->get(),
```
View, tambahkan label lembaga hanya saat yayasan-scope & mode "Semua Lembaga":
```blade
@foreach ($tahunAjaranList as $tahunAjaran)
    <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>
        {{ $tahunAjaran->nama }}
        @if (Auth::user()->widestScopeLevel() === 'yayasan' && ! session('active_lembaga_id'))
            — {{ $tahunAjaran->lembaga->nama }}
        @endif
    </option>
@endforeach
```

**Acceptance criteria**:
1. Yayasan mode "Semua Lembaga", 2 lembaga py tahun ajaran nama sama → dropdown menampilkan 2 option dengan label lembaga berbeda, bisa dibedakan.
2. Yayasan pilih 1 lembaga, ATAU lembaga-scope biasa → dropdown TIDAK berubah (tanpa label tambahan, seperti sekarang).

---

## Di Luar Scope / Backlog Terpisah (JANGAN dikerjakan di plan ini)

1. **3 item global lintas SEMUA yayasan**: `JenisKaryawanMasterController`, `JabatanTambahanMasterController`, `WhatsAppTemplateController` — tabelnya tidak punya kolom `lembaga_id`/`yayasan_id` sama sekali, datanya shared lintas SEMUA yayasan di sistem (bukan cuma lintas lembaga dalam 1 yayasan). Ini beda kelas masalah dari spec ini (batas yayasan, bukan batas lembaga) dan BUTUH keputusan produk dulu: apakah memang disengaja sebagai katalog nasional, atau harus dibatasi per-yayasan. Belum ada keputusan — jangan diasumsikan/dikerjakan.
2. **`JadwalPelajaranController`** — dropdown guru/mapel lintas lembaga sebelum kelas dipilih (baris di `index()`/`create()`/`edit()`, cek detail saat plan ditulis kalau nanti mau diambil). Dinilai minor (tervalidasi lagi saat submit, tidak menyebabkan data salah tersimpan), boleh diabaikan untuk plan ini.
3. **Method lain di `VirtualAccountController`/`ManualPaymentController`** selain `index()` (`riwayat()`, `calonGenerate()`, `generate()`, `export()`, dan method non-`index()` di `ManualPaymentController`) yang memakai helper `lembagaId()` yang sama — berpotensi py bug serupa D.1/D.2 tapi TIDAK diverifikasi/diperbaiki di spec ini (scope eksplisit dibatasi ke `index()` saja).
4. **Modul SPMB/PPDB** — sengaja dikecualikan total dari audit ini, sedang dibekukan/nunggu rombakan terpisah.

---

## Ringkasan Test yang Wajib Ditambahkan

| Kategori | File test (buat baru atau tambah ke existing — cek dulu saat plan ditulis) |
|---|---|
| A.1 | Feature test `KasusAksesLogController` — assert isolasi lintas yayasan |
| A.2 | Feature test `KasusTerhapusController` — assert isolasi lintas yayasan |
| A.3 | Feature test `OrangTuaController` — assert isolasi lintas yayasan |
| B | Feature/sidebar test — assertDontSee utk non-guru, assertSee utk guru asli |
| C | Feature test `AttendanceQrScanController` — assert 422 saat blm pilih lembaga, assert menu hilang dari sidebar |
| D.1-D.5 | Feature test per controller — assert agregat benar saat mode "Semua Lembaga" (2 lembaga beda data, assert keduanya terhitung) |

Regresi wajib: jalankan test existing utk SEMUA file yang disentuh (Kasus*, OrangTua*, VirtualAccount*, ManualPayment*, AttendanceConfiguration*, AttendanceQrScan*, Gedung*, Ruangan*, KategoriAset*, PengajuanPengadaan*, Rapor*, sidebar) — pastikan 0 regresi sebelum dianggap selesai.
