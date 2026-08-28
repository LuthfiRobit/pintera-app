# Pengelompokan Ulang Sidebar — Personal Context Precedence Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perbaiki pengelompokan menu `resources/views/layouts/sidebar.blade.php` supaya item personal (milik guru/siswa/orang tua sendiri) berkumpul di grup identitasnya masing-masing (bukan tercampur ke grup domain admin), tambah grup `Ruang Siswa`/`Ruang Orang Tua` baru, dan tutup gap gate "Kasus Pendampingan" yang masih pakai `can('kasus.view')` alih-alih `can('viewAny', Kasus::class)`.

**Architecture:** Satu aturan presedensi tunggal (`hasRole('guru')` → `hasRole('siswa')` → `orangTua !== null` → fallback) menentukan DI GRUP MANA item personal tampil — bukan APAKAH item itu boleh dilihat (guard `can(...)` per item tetap sama, kecuali Kasus Pendampingan). Satu route + satu view generik baru ("Dalam Pengembangan") menampung 6 item baru (3 Siswa + 3 Orang Tua) yang belum punya halaman sungguhan.

**Tech Stack:** Laravel 12, PHP 8.3, Blade, Pest v4.

## Global Constraints

- HANYA `resources/views/layouts/sidebar.blade.php`, `routes/web.php`, dan 1 view baru yang disentuh. TIDAK ADA perubahan controller/permission/migration/model APA PUN.
- Precedence (`hasRole('guru')` → `hasRole('siswa')` → `orangTua !== null` → fallback) adalah **aturan navigasi UI**, BUKAN hierarki RBAC — jangan tafsirkan/implementasikan sebagai `scopeRank()` atau semacamnya.
- Role `guru_bk`/`wali_kelas` TIDAK BOLEH ikut menentukan konteks personal — HANYA `hasRole('guru')` (role literal) yang dicek untuk poin pertama presedensi.
- Guard tiap item TIDAK BOLEH berubah kecuali "Kasus Pendampingan" (dari `can('kasus.view')` ke `can('viewAny', Kasus::class)`) — item lain murni PINDAH LOKASI, guard-nya disalin persis.
- `KasusPolicy::viewAny()` TIDAK diubah — sudah benar dari sesi RBAC v2 sebelumnya.
- 3 item Siswa + 3 item Orang Tua HANYA mengarah ke halaman "Dalam Pengembangan" generik — TIDAK ADA controller/query baru untuk nilai/jadwal/presensi.
- Full spec: `.agents/specs/2026-08-28-sidebar-pengelompokan-ulang.md`.

---

### Task 1: Halaman "Dalam Pengembangan" (route + view generik)

**Files:**
- Modify: `routes/web.php` (tambah 1 route di dalam blok `Route::middleware('auth')->group(...)`, baris 19-24)
- Create: `resources/views/shared/dalam-pengembangan.blade.php`
- Test: `tests/Feature/DalamPengembanganTest.php`

**Interfaces:**
- Consumes: tidak ada (task pertama).
- Produces: route bernama `dalam-pengembangan` menerima query param `fitur` (string, mis. `nilai-anak`) — dipakai Task 3 & 4 untuk 6 item menu baru via `route('dalam-pengembangan', ['fitur' => 'nilai-anak'])`.

- [ ] **Step 1: Baca ulang `routes/web.php` baris 15-24 untuk konfirmasi baseline sebelum edit**

Jalankan:
```bash
sed -n '15,24p' routes/web.php
```
Pastikan isinya PERSIS:
```php
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/notification-preference', [ProfileController::class, 'updateNotificationPreference'])->name('profile.notification-preference.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
```
Kalau berbeda, STOP dan laporkan ke user — jangan lanjut edit di atas asumsi yang salah.

- [ ] **Step 2: Tulis failing test — halaman render dengan judul sesuai parameter `fitur`**

Buat file `tests/Feature/DalamPengembanganTest.php`:
```php
<?php

use App\Models\User;

it('renders the dalam-pengembangan page with a human-readable title from the fitur query param', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dalam-pengembangan', ['fitur' => 'nilai-anak']));

    $response->assertOk();
    $response->assertSee('Nilai Anak');
    $response->assertSee('dalam pengembangan');
});

it('falls back to a generic title when fitur query param is missing', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dalam-pengembangan'));

    $response->assertOk();
    $response->assertSee('Fitur Ini');
});

it('redirects a guest to login', function () {
    $this->get(route('dalam-pengembangan'))->assertRedirect(route('login'));
});
```

- [ ] **Step 2b: Jalankan test, pastikan GAGAL (route belum ada)**

Run: `php artisan test --filter="dalam-pengembangan\|DalamPengembangan" --compact`
Expected: FAIL — `Route [dalam-pengembangan] not defined.`

- [ ] **Step 3: Tambah route di `routes/web.php`**

Sisipkan SETELAH baris `Route::middleware('auth')->group(function () {` (baris 19), SEBELUM baris `Route::get('/profile', ...)`:
```php
Route::middleware('auth')->group(function () {
    Route::get('/dalam-pengembangan', function (\Illuminate\Http\Request $request) {
        $labelMap = [
            'nilai-rapor' => 'Nilai & Rapor',
            'jadwal-pelajaran' => 'Jadwal Pelajaran',
            'presensi-saya' => 'Presensi Saya',
            'nilai-anak' => 'Nilai Anak',
            'jadwal-anak' => 'Jadwal Anak',
            'riwayat-izin-sakit-anak' => 'Riwayat Izin/Sakit Anak',
        ];
        $fitur = $labelMap[$request->query('fitur')] ?? 'Fitur Ini';

        return view('shared.dalam-pengembangan', ['fitur' => $fitur]);
    })->name('dalam-pengembangan');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
```

- [ ] **Step 4: Buat view `resources/views/shared/dalam-pengembangan.blade.php`**

Pola layout sudah dikonfirmasi (dibaca langsung dari `resources/views/admin/dashboard/orang-tua.blade.php:1` sebelum plan ini ditulis — project ini memakai component `<x-app-layout>`, BUKAN `@extends`):
```blade
<x-app-layout>
    <div class="mx-auto flex max-w-xl flex-col items-center gap-4 py-16 text-center">
        <x-dynamic-component :component="'lucide-hammer'" class="h-12 w-12 text-gray-300" />
        <h1 class="font-display text-xl font-bold text-gray-900">{{ $fitur }} sedang dalam pengembangan</h1>
        <p class="text-sm text-gray-500">Fitur ini akan segera tersedia. Terima kasih atas kesabaran Anda.</p>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Jalankan `php -l` untuk cek syntax route file**

Run: `php -l routes/web.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Jalankan test Step 2, HARUS PASS**

Run: `php artisan test --filter="DalamPengembangan" --compact`
Expected: 3 passed.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php resources/views/shared/dalam-pengembangan.blade.php tests/Feature/DalamPengembanganTest.php
git commit -m "$(cat <<'EOF'
feat(sidebar): tambah halaman generik 'Dalam Pengembangan'

Satu route + view dipakai bersama untuk 6 item menu baru (Siswa: Nilai &
Rapor/Jadwal Pelajaran/Presensi Saya; Orang Tua: Nilai Anak/Jadwal Anak/
Riwayat Izin-Sakit Anak) yang belum punya halaman sungguhan. Judul dinamis
dari query param 'fitur', tanpa logic tambahan apa pun.
EOF
)"
```

---

### Task 2: Restrukturisasi `Ruang Guru` (tambah RPP, QR, Izin, Kasus)

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php:10-20` (grup `Ruang Guru`)
- Test: `tests/Feature/SidebarPengelompokanTest.php` (file baru, dipakai bersama Task 2-6)

**Interfaces:**
- Consumes: route `dalam-pengembangan` dari Task 1 (tidak dipakai langsung task ini, tapi test file yang sama akan dipakai Task 3/4).
- Produces: tidak ada interface baru untuk task lain — tiap task sidebar independen memodifikasi bagian array `$navGroups` yang berbeda.

- [ ] **Step 1: Baca ulang `sidebar.blade.php` baris 1-20 untuk konfirmasi baseline**

Jalankan:
```bash
sed -n '1,20p' resources/views/layouts/sidebar.blade.php
```
Pastikan grup `Ruang Guru` (baris 10-20) PERSIS seperti berikut sebelum edit:
```php
        [
            'label' => 'Ruang Guru',
            'group_icon' => 'graduation-cap',
            'items' => array_filter([
                Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.index', 'pattern' => 'guru.jurnal-kbm.index', 'label' => 'Jurnal & Presensi', 'icon' => 'file-pen'] : null,
                Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.rekap', 'pattern' => 'guru.jurnal-kbm.rekap', 'label' => 'Rekap Kehadiran', 'icon' => 'chart-bar'] : null,
                Auth::user()->can('komponen-penilaian.kelola-sendiri') ? ['route' => 'guru.komponen-penilaian.index', 'pattern' => 'guru.komponen-penilaian.*', 'label' => 'Komponen Penilaian (TP)', 'icon' => 'list-todo'] : null,
                Auth::user()->can('asesmen.kelola') ? ['route' => 'guru.asesmen.index', 'pattern' => 'guru.asesmen.*', 'label' => 'Asesmen & Nilai', 'icon' => 'bar-chart-3'] : null,
                Auth::user()->can('rapor.input-wali') ? ['route' => 'guru.rapor.catatan.index', 'pattern' => 'guru.rapor.*', 'label' => 'Rapor Wali Kelas', 'icon' => 'book-text'] : null,
            ]),
        ],
```
Kalau berbeda, STOP dan laporkan ke user.

- [ ] **Step 2: Tulis failing test — item RPP/QR/Izin/Kasus muncul di `Ruang Guru` untuk guru**

Buat file `tests/Feature/SidebarPengelompokanTest.php`:
```php
<?php

use App\Domains\Kasus\Enums\StatusKasus;
use App\Domains\Kasus\Models\Kasus;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanGuruUntukSidebar(): User
{
    foreach (['presensi.isi', 'komponen-penilaian.kelola-sendiri', 'asesmen.kelola', 'rapor.input-wali', 'rpp.view', 'rpp.kelola', 'kehadiran-sdm.lihat-qr-sendiri', 'kehadiran-sdm.izin.lihat-sendiri', 'kasus.view'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi', 'komponen-penilaian.kelola-sendiri', 'asesmen.kelola', 'rapor.input-wali', 'rpp.view', 'rpp.kelola', 'kehadiran-sdm.lihat-qr-sendiri', 'kehadiran-sdm.izin.lihat-sendiri', 'kasus.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('guru');
    Guru::factory()->create(['user_id' => $user->id, 'lembaga_id' => $lembaga->id]);

    return $user;
}

it('shows RPP, QR Kehadiran, Izin/Cuti, and Kasus Pendampingan under Ruang Guru for a guru account', function () {
    $guru = siapkanGuruUntukSidebar();

    $response = $this->actingAs($guru)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSeeInOrder(['Ruang Guru', 'Perangkat Ajar (RPP)']);
    $response->assertSee('QR Kehadiran Saya');
    $response->assertSee('Izin/Cuti Saya');
});
```

- [ ] **Step 3: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="shows RPP, QR Kehadiran" --compact`
Expected: FAIL — item-item ini belum ada di grup `Ruang Guru` (masih di grup lama).

- [ ] **Step 4: Edit grup `Ruang Guru`**

Ganti PERSIS blok dari Step 1 menjadi:
```php
        [
            'label' => 'Ruang Guru',
            'group_icon' => 'graduation-cap',
            'items' => array_filter([
                Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.index', 'pattern' => 'guru.jurnal-kbm.index', 'label' => 'Jurnal & Presensi', 'icon' => 'file-pen'] : null,
                Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.rekap', 'pattern' => 'guru.jurnal-kbm.rekap', 'label' => 'Rekap Kehadiran', 'icon' => 'chart-bar'] : null,
                Auth::user()->can('komponen-penilaian.kelola-sendiri') ? ['route' => 'guru.komponen-penilaian.index', 'pattern' => 'guru.komponen-penilaian.*', 'label' => 'Komponen Penilaian (TP)', 'icon' => 'list-todo'] : null,
                Auth::user()->can('asesmen.kelola') ? ['route' => 'guru.asesmen.index', 'pattern' => 'guru.asesmen.*', 'label' => 'Asesmen & Nilai', 'icon' => 'bar-chart-3'] : null,
                Auth::user()->can('rapor.input-wali') ? ['route' => 'guru.rapor.catatan.index', 'pattern' => 'guru.rapor.*', 'label' => 'Rapor Wali Kelas', 'icon' => 'book-text'] : null,
                Auth::user()->hasRole('guru') && Auth::user()->can('rpp.view') ? ['route' => 'admin.rpp.index', 'pattern' => 'admin.rpp.*', 'label' => 'Perangkat Ajar (RPP)', 'icon' => 'file-text'] : null,
                Auth::user()->hasRole('guru') && Auth::user()->can('kehadiran-sdm.lihat-qr-sendiri') ? ['route' => 'sdm.qr-saya', 'pattern' => 'sdm.qr-saya', 'label' => 'QR Kehadiran Saya', 'icon' => 'qr-code'] : null,
                Auth::user()->hasRole('guru') && Auth::user()->can('kehadiran-sdm.izin.lihat-sendiri') ? ['route' => 'sdm.izin-cuti.index', 'pattern' => 'sdm.izin-cuti.*', 'label' => 'Izin/Cuti Saya', 'icon' => 'calendar-days'] : null,
                Auth::user()->hasRole('guru') && Auth::user()->can('viewAny', \App\Domains\Kasus\Models\Kasus::class) ? ['route' => 'kasus.index', 'pattern' => 'kasus.*', 'label' => 'Kasus Pendampingan', 'icon' => 'stethoscope'] : null,
            ]),
        ],
```

- [ ] **Step 5: Jalankan `php -l`**

Run: `php -l resources/views/layouts/sidebar.blade.php`
Expected: `No syntax errors detected` (Blade file dengan tag `@php`/`@endphp` — `php -l` tetap valid untuk mengecek blok PHP murni di dalamnya, sisanya HTML/Blade directive tidak dicek olehnya, itu wajar).

- [ ] **Step 6: Jalankan test Step 2, HARUS PASS**

Run: `php artisan test --filter="shows RPP, QR Kehadiran" --compact`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/views/layouts/sidebar.blade.php tests/Feature/SidebarPengelompokanTest.php
git commit -m "$(cat <<'EOF'
refactor(sidebar): pindahkan RPP, QR Kehadiran, Izin/Cuti, Kasus ke Ruang Guru

Item personal milik guru sebelumnya tersebar di grup Akademik/Kehadiran
Saya/Pendampingan (domain), sekarang berkumpul di Ruang Guru berdasarkan
identitas hasRole('guru'). Guard tiap item disalin persis, KECUALI Kasus
Pendampingan yang diganti dari can('kasus.view') ke
can('viewAny', Kasus::class) -- menutup gap RBAC v2 sebelumnya.
EOF
)"
```

---

### Task 3: Grup baru `Ruang Siswa`

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php` (sisipkan grup baru setelah `Ruang Guru`)
- Test: `tests/Feature/SidebarPengelompokanTest.php`

**Interfaces:**
- Consumes: route `dalam-pengembangan` (Task 1).
- Produces: tidak ada interface baru untuk task lain.

- [ ] **Step 1: Tulis failing test — grup Ruang Siswa muncul untuk siswa**

Tambahkan di `tests/Feature/SidebarPengelompokanTest.php`:
```php
it('shows Ruang Siswa group with 3 dalam-pengembangan links for a siswa account', function () {
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.view']);

    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('siswa');
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Ruang Siswa');
    $response->assertSee('Nilai &amp; Rapor', false);
    $response->assertSee('Jadwal Pelajaran');
    $response->assertSee('Presensi Saya');
});
```
Catatan: `assertSee('Nilai &amp; Rapor', false)` dipakai karena `&` di-escape Blade jadi `&amp;` — kalau ternyata Blade output-nya beda (mis. label ditulis tanpa `&`), sesuaikan assertion dengan hasil render sungguhan, JANGAN mengubah label di kode supaya cocok dengan test.

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="Ruang Siswa" --compact`
Expected: FAIL — grup belum ada.

- [ ] **Step 3: Sisipkan grup `Ruang Siswa` di `sidebar.blade.php`, TEPAT SETELAH penutup grup `Ruang Guru` (setelah `],` yang menutup grup Task 2), SEBELUM grup `Kehadiran Saya`**

```php
        [
            'label' => 'Ruang Siswa',
            'group_icon' => 'backpack',
            'items' => array_filter([
                Auth::user()->hasRole('siswa') ? ['route' => 'dalam-pengembangan', 'params' => ['fitur' => 'nilai-rapor'], 'pattern' => 'dalam-pengembangan', 'label' => 'Nilai & Rapor', 'icon' => 'award'] : null,
                Auth::user()->hasRole('siswa') ? ['route' => 'dalam-pengembangan', 'params' => ['fitur' => 'jadwal-pelajaran'], 'pattern' => 'dalam-pengembangan', 'label' => 'Jadwal Pelajaran', 'icon' => 'calendar-clock'] : null,
                Auth::user()->hasRole('siswa') ? ['route' => 'dalam-pengembangan', 'params' => ['fitur' => 'presensi-saya'], 'pattern' => 'dalam-pengembangan', 'label' => 'Presensi Saya', 'icon' => 'clipboard-check'] : null,
                Auth::user()->hasRole('siswa') && Auth::user()->can('viewAny', \App\Domains\Kasus\Models\Kasus::class) ? ['route' => 'kasus.index', 'pattern' => 'kasus.*', 'label' => 'Kasus Pendampingan', 'icon' => 'stethoscope'] : null,
            ]),
        ],
```

**PENTING — array `$item` sekarang punya key `params` untuk item yang butuh query string.** Blok `@foreach` yang me-render link (baris ~185 di file lama) memanggil `route($item['route'])` TANPA parameter — item lama semuanya route tanpa param jadi tidak masalah sebelumnya. Task ini WAJIB juga mengubah baris render link supaya mendukung `params` opsional. Cari baris:
```php
                                    href="{{ route($item['route']) }}"
```
Ganti menjadi:
```php
                                    href="{{ route($item['route'], $item['params'] ?? []) }}"
```
Ini SATU-SATUNYA perubahan di luar `$navGroups` array yang dibutuhkan task ini — item lama yang tidak punya key `params` tetap jalan normal karena fallback `?? []`.

- [ ] **Step 4: Jalankan `php -l`**

Run: `php -l resources/views/layouts/sidebar.blade.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Jalankan test Step 1, HARUS PASS**

Run: `php artisan test --filter="Ruang Siswa" --compact`
Expected: PASS. Kalau assertion `Nilai &amp; Rapor` gagal karena escaping berbeda, cek HTML sungguhan dulu (`$response->getContent()` di tinker atau print langsung) sebelum menyesuaikan.

- [ ] **Step 6: Commit**

```bash
git add resources/views/layouts/sidebar.blade.php tests/Feature/SidebarPengelompokanTest.php
git commit -m "$(cat <<'EOF'
feat(sidebar): tambah grup Ruang Siswa (3 item Dalam Pengembangan + Kasus)

Siswa belum punya backend nilai/jadwal/presensi sama sekali (dikonfirmasi
route:list, 0 hasil) -- 3 item baru murni arahkan ke halaman generik
Dalam Pengembangan (Task 1). Render link sekarang mendukung key 'params'
opsional untuk item yang butuh query string.
EOF
)"
```

---

### Task 4: Grup baru `Ruang Orang Tua`

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php` (sisipkan grup baru setelah `Ruang Siswa`, hapus 3 item lama dari grup `Keuangan`)
- Test: `tests/Feature/SidebarPengelompokanTest.php`

**Interfaces:**
- Consumes: route `dalam-pengembangan` (Task 1), pola `params` (Task 3).
- Produces: tidak ada interface baru untuk task lain.

- [ ] **Step 1: Baca ulang grup `Keuangan` untuk konfirmasi baseline sebelum edit**

Jalankan:
```bash
grep -n "'label' => 'Keuangan'" -A 14 resources/views/layouts/sidebar.blade.php
```
Pastikan 3 baris berikut ADA (index 5-7 dari items array grup ini, persis seperti dikutip di spec §1.1):
```php
                Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.dashboard', 'pattern' => 'keuangan.dashboard', 'label' => 'Dompet & Tagihan Saya', 'icon' => 'wallet'] : null,
                Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.tagihan.index', 'pattern' => 'keuangan.tagihan.*', 'label' => 'Tagihan', 'icon' => 'receipt'] : null,
                Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.riwayat.index', 'pattern' => 'keuangan.riwayat.*', 'label' => 'Riwayat', 'icon' => 'history'] : null,
```
Kalau berbeda, STOP dan laporkan ke user.

- [ ] **Step 2: Tulis failing test — grup Ruang Orang Tua muncul, grup Keuangan tidak lagi punya item orang tua**

Tambahkan di `tests/Feature/SidebarPengelompokanTest.php`:
```php
it('shows Ruang Orang Tua group with dalam-pengembangan links plus keuangan self-service items, moved out of the Keuangan group', function () {
    foreach (['kasus.view', 'keuangan.akses'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.view', 'keuangan.akses']);

    $orangTuaUser = User::factory()->create();
    $orangTuaUser->assignRole('orang_tua');
    \App\Models\OrangTua::factory()->create(['user_id' => $orangTuaUser->id]);

    $response = $this->actingAs($orangTuaUser)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Ruang Orang Tua');
    $response->assertSee('Nilai Anak');
    $response->assertSee('Jadwal Anak');
    $response->assertSee('Riwayat Izin/Sakit Anak');
    $response->assertSee('Dompet &amp; Tagihan Saya', false);
});
```
Namespace `\App\Models\OrangTua` sudah diverifikasi benar (`app/Models/OrangTua.php:11`) sebelum plan ditulis.

- [ ] **Step 3: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="Ruang Orang Tua" --compact`
Expected: FAIL.

- [ ] **Step 4: Hapus 3 item lama dari grup `Keuangan`**

Hapus PERSIS 3 baris yang dikutip di Step 1 dari dalam `items => array_filter([...])` grup `Keuangan` — baris SEBELUM dan SESUDAHNYA (item billing staf: Virtual Account, Jenis Tagihan, Tagihan admin, Verifikasi Pembayaran, Verifikasi Transfer Manual) TETAP ADA, TIDAK diubah.

- [ ] **Step 5: Sisipkan grup `Ruang Orang Tua`, SETELAH grup `Ruang Siswa` (Task 3), SEBELUM grup `Kehadiran Saya`**

```php
        [
            'label' => 'Ruang Orang Tua',
            'group_icon' => 'users',
            'items' => array_filter([
                Auth::user()->orangTua !== null ? ['route' => 'dalam-pengembangan', 'params' => ['fitur' => 'nilai-anak'], 'pattern' => 'dalam-pengembangan', 'label' => 'Nilai Anak', 'icon' => 'award'] : null,
                Auth::user()->orangTua !== null ? ['route' => 'dalam-pengembangan', 'params' => ['fitur' => 'jadwal-anak'], 'pattern' => 'dalam-pengembangan', 'label' => 'Jadwal Anak', 'icon' => 'calendar-clock'] : null,
                Auth::user()->orangTua !== null ? ['route' => 'dalam-pengembangan', 'params' => ['fitur' => 'riwayat-izin-sakit-anak'], 'pattern' => 'dalam-pengembangan', 'label' => 'Riwayat Izin/Sakit Anak', 'icon' => 'clipboard-check'] : null,
                Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.dashboard', 'pattern' => 'keuangan.dashboard', 'label' => 'Dompet & Tagihan Saya', 'icon' => 'wallet'] : null,
                Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.tagihan.index', 'pattern' => 'keuangan.tagihan.*', 'label' => 'Tagihan', 'icon' => 'receipt'] : null,
                Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.riwayat.index', 'pattern' => 'keuangan.riwayat.*', 'label' => 'Riwayat', 'icon' => 'history'] : null,
                Auth::user()->orangTua !== null && Auth::user()->can('viewAny', \App\Domains\Kasus\Models\Kasus::class) ? ['route' => 'kasus.index', 'pattern' => 'kasus.*', 'label' => 'Kasus Pendampingan', 'icon' => 'stethoscope'] : null,
            ]),
        ],
```

- [ ] **Step 6: Jalankan `php -l`**

Run: `php -l resources/views/layouts/sidebar.blade.php`
Expected: `No syntax errors detected`

- [ ] **Step 7: Jalankan test Step 2, HARUS PASS**

Run: `php artisan test --filter="Ruang Orang Tua" --compact`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/views/layouts/sidebar.blade.php tests/Feature/SidebarPengelompokanTest.php
git commit -m "$(cat <<'EOF'
feat(sidebar): tambah grup Ruang Orang Tua, pindahkan 3 item Keuangan Saya

Nilai/Jadwal/Riwayat Izin-Sakit Anak: data-flow sudah ada di
DashboardController tapi belum punya halaman dedicated -- arahkan ke
Dalam Pengembangan (Task 1), BUKAN placeholder generik seperti Siswa
(alasannya beda, dicatat di spec). Item Dompet/Tagihan/Riwayat dipindah
dari grup Keuangan (domain) ke sini, guard tidak berubah.
EOF
)"
```

---

### Task 5: Grup `Akademik` — RPP hanya untuk non-guru

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php` (grup `Akademik`)
- Test: `tests/Feature/SidebarPengelompokanTest.php`

**Interfaces:**
- Consumes: tidak ada dependency langsung ke task lain.
- Produces: tidak ada interface baru.

- [ ] **Step 1: Baca ulang baris RPP di grup `Akademik` untuk konfirmasi baseline**

Jalankan:
```bash
grep -n "admin.rpp.index" resources/views/layouts/sidebar.blade.php
```
Harus ADA TEPAT 2 hasil sekarang: satu di grup `Ruang Guru` (ditambahkan Task 2), satu di grup `Akademik` (baris lama, akan diedit task ini):
```php
                Auth::user()->can('rpp.view') ? ['route' => 'admin.rpp.index', 'pattern' => 'admin.rpp.*', 'label' => 'Perangkat Ajar (RPP)', 'icon' => 'file-text'] : null,
```
Kalau hasilnya bukan 2, atau baris di grup `Akademik` sudah berbeda dari kutipan di atas, STOP dan laporkan ke user.

- [ ] **Step 2: Tulis failing test — RPP tidak dobel untuk guru, TETAP muncul untuk kepala sekolah**

Tambahkan di `tests/Feature/SidebarPengelompokanTest.php`:
```php
it('does not duplicate RPP into Akademik for a guru account, but still shows it there for kepala_sekolah', function () {
    $guru = siapkanGuruUntukSidebar();

    $responseGuru = $this->actingAs($guru)->get(route('dashboard'));
    $responseGuru->assertOk();
    expect(substr_count($responseGuru->getContent(), 'Perangkat Ajar (RPP)'))->toBe(1);

    foreach (['spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi', 'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk', 'tagihan.view', 'komponen-penilaian.kelola', 'rapor.view', 'rapor.approve', 'kenaikan-kelas.kelola', 'rpp.view', 'rpp.verify', 'kehadiran-sdm.izin.approve'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $kepsekRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $kepsekRole->givePermissionTo(['rpp.view', 'rpp.verify']);
    $kepsek = User::factory()->create(['lembaga_id' => Lembaga::factory()->create()->id]);
    $kepsek->assignRole('kepala_sekolah');

    $responseKepsek = $this->actingAs($kepsek)->get(route('dashboard'));
    $responseKepsek->assertOk();
    $responseKepsek->assertSee('Perangkat Ajar (RPP)');
});
```

- [ ] **Step 3: Jalankan test, pastikan GAGAL pada bagian pertama (RPP muncul 2x untuk guru)**

Run: `php artisan test --filter="does not duplicate RPP" --compact`
Expected: FAIL — `substr_count(...)` menghasilkan 2, bukan 1, karena baris lama di `Akademik` belum diberi guard `! hasRole('guru')`.

- [ ] **Step 4: Edit baris RPP di grup `Akademik`**

Ganti baris yang dikutip di Step 1 (yang di grup `Akademik`, BUKAN yang di `Ruang Guru`) dari:
```php
                Auth::user()->can('rpp.view') ? ['route' => 'admin.rpp.index', 'pattern' => 'admin.rpp.*', 'label' => 'Perangkat Ajar (RPP)', 'icon' => 'file-text'] : null,
```
menjadi:
```php
                ! Auth::user()->hasRole('guru') && Auth::user()->can('rpp.view') ? ['route' => 'admin.rpp.index', 'pattern' => 'admin.rpp.*', 'label' => 'Perangkat Ajar (RPP)', 'icon' => 'file-text'] : null,
```

- [ ] **Step 5: Jalankan `php -l`**

Run: `php -l resources/views/layouts/sidebar.blade.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Jalankan test Step 2, HARUS PASS**

Run: `php artisan test --filter="does not duplicate RPP" --compact`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/views/layouts/sidebar.blade.php tests/Feature/SidebarPengelompokanTest.php
git commit -m "$(cat <<'EOF'
fix(sidebar): cegah RPP dobel di Akademik untuk guru, tetap tampil utk kepsek/wakasek/operator

Guard baris RPP lama di grup Akademik ditambah !hasRole('guru') --
RPP guru sudah dipindah ke Ruang Guru (Task 2), baris ini sekarang
eksklusif untuk role administratif (kepala_sekolah/wakasek_kurikulum/
operator_akademik) yang tidak hasRole('guru').
EOF
)"
```

---

### Task 6: Grup `Pendampingan` — hapus Kasus self-service, `Kehadiran Saya` jadi fallback

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php` (grup `Pendampingan` dan `Kehadiran Saya`)
- Test: `tests/Feature/SidebarPengelompokanTest.php`

**Interfaces:**
- Consumes: tidak ada dependency langsung.
- Produces: tidak ada interface baru — task terakhir untuk sidebar sebelum full regression check.

- [ ] **Step 1: Baca ulang grup `Pendampingan` dan `Kehadiran Saya` saat ini untuk konfirmasi baseline**

Jalankan:
```bash
grep -n "'label' => 'Pendampingan'" -A 10 resources/views/layouts/sidebar.blade.php
grep -n "'label' => 'Kehadiran Saya'" -A 8 resources/views/layouts/sidebar.blade.php
```
Grup `Pendampingan` harus masih mengandung baris:
```php
                Auth::user()->can('kasus.view') ? ['route' => 'kasus.index', 'pattern' => 'kasus.*', 'label' => 'Kasus Pendampingan', 'icon' => 'stethoscope'] : null,
```
Grup `Kehadiran Saya` (dari Task 2, item QR/Izin di sini sudah punya guard baru dari `sidebar.blade.php` HASIL Task 2 — **PENTING**: Task 2 menambah item BARU di `Ruang Guru`, TAPI item LAMA di `Kehadiran Saya` (baris asli, sebelum Task 2) BELUM disentuh task manapun sampai titik ini — masih:
```php
                Auth::user()->can('kehadiran-sdm.lihat-qr-sendiri') ? ['route' => 'sdm.qr-saya', 'pattern' => 'sdm.qr-saya', 'label' => 'QR Kehadiran Saya', 'icon' => 'qr-code'] : null,
                Auth::user()->can('kehadiran-sdm.izin.lihat-sendiri') ? ['route' => 'sdm.izin-cuti.index', 'pattern' => 'sdm.izin-cuti.*', 'label' => 'Izin/Cuti Saya', 'icon' => 'calendar-days'] : null,
```
Kalau salah satu kutipan di atas tidak cocok, STOP dan laporkan ke user.

- [ ] **Step 2: Tulis failing test — karyawan pool (bukan guru/siswa/orang tua) melihat Kasus Pendampingan di Kehadiran Saya, BUKAN di Pendampingan; guru TIDAK melihat QR/Izin dobel**

Tambahkan di `tests/Feature/SidebarPengelompokanTest.php`:
```php
it('shows Kasus Pendampingan under Kehadiran Saya (not Pendampingan) for a pool konselor karyawan without kasus.view', function () {
    (new \Database\Seeders\RoleSeeder)->run();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('pegawai_yayasan');
    $jenis = \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create(['is_konselor' => true]);
    $karyawan = \App\Models\Karyawan::withoutGlobalScopes()->create([
        'user_id' => $user->id, 'yayasan_id' => $yayasan->id, 'lembaga_id' => null,
        'jenis_karyawan_id' => $jenis->id, 'nama' => 'Karyawan Pool',
        'nik' => fake()->unique()->numerify('################'), 'status_aktif' => 'aktif',
    ]);
    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
        'status' => StatusKasus::Ditugaskan, 'konselor_karyawan_id' => $karyawan->id,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSeeInOrder(['Kehadiran Saya', 'Kasus Pendampingan']);
});

it('does not show QR Kehadiran or Izin/Cuti twice for a guru account', function () {
    $guru = siapkanGuruUntukSidebar();

    $response = $this->actingAs($guru)->get(route('dashboard'));

    $response->assertOk();
    expect(substr_count($response->getContent(), 'QR Kehadiran Saya'))->toBe(1);
    expect(substr_count($response->getContent(), 'Izin/Cuti Saya'))->toBe(1);
});
```

- [ ] **Step 3: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="pool konselor karyawan without kasus.view|does not show QR Kehadiran or Izin.Cuti twice" --compact`
Expected: FAIL — Kasus Pendampingan belum dipindah, QR/Izin masih dobel untuk guru (sekali di `Ruang Guru` dari Task 2, sekali lagi di `Kehadiran Saya` yang belum diberi guard eksklusi).

- [ ] **Step 4: Hapus baris Kasus Pendampingan dari grup `Pendampingan`**

Hapus baris:
```php
                Auth::user()->can('kasus.view') ? ['route' => 'kasus.index', 'pattern' => 'kasus.*', 'label' => 'Kasus Pendampingan', 'icon' => 'stethoscope'] : null,
```
dari dalam `items => array_filter([...])` grup `Pendampingan`. 3 baris lain di grup ini (Triase Kasus, Log Akses Klinis, Kasus Terhapus) TIDAK disentuh.

- [ ] **Step 5: Edit grup `Kehadiran Saya` — tambah guard eksklusi + item Kasus untuk fallback**

Ganti isi `items => array_filter([...])` grup `Kehadiran Saya` dari:
```php
                Auth::user()->can('kehadiran-sdm.lihat-qr-sendiri') ? ['route' => 'sdm.qr-saya', 'pattern' => 'sdm.qr-saya', 'label' => 'QR Kehadiran Saya', 'icon' => 'qr-code'] : null,
                Auth::user()->can('kehadiran-sdm.izin.lihat-sendiri') ? ['route' => 'sdm.izin-cuti.index', 'pattern' => 'sdm.izin-cuti.*', 'label' => 'Izin/Cuti Saya', 'icon' => 'calendar-days'] : null,
```
menjadi:
```php
                ! Auth::user()->hasRole('guru') && Auth::user()->can('kehadiran-sdm.lihat-qr-sendiri') ? ['route' => 'sdm.qr-saya', 'pattern' => 'sdm.qr-saya', 'label' => 'QR Kehadiran Saya', 'icon' => 'qr-code'] : null,
                ! Auth::user()->hasRole('guru') && Auth::user()->can('kehadiran-sdm.izin.lihat-sendiri') ? ['route' => 'sdm.izin-cuti.index', 'pattern' => 'sdm.izin-cuti.*', 'label' => 'Izin/Cuti Saya', 'icon' => 'calendar-days'] : null,
                ! Auth::user()->hasRole('guru') && ! Auth::user()->hasRole('siswa') && Auth::user()->orangTua === null && Auth::user()->can('viewAny', \App\Domains\Kasus\Models\Kasus::class) ? ['route' => 'kasus.index', 'pattern' => 'kasus.*', 'label' => 'Kasus Pendampingan', 'icon' => 'stethoscope'] : null,
```

- [ ] **Step 6: Jalankan `php -l`**

Run: `php -l resources/views/layouts/sidebar.blade.php`
Expected: `No syntax errors detected`

- [ ] **Step 7: Jalankan test Step 2, HARUS PASS**

Run: `php artisan test --filter="pool konselor karyawan without kasus.view|does not show QR Kehadiran or Izin.Cuti twice" --compact`
Expected: 2 passed.

- [ ] **Step 8: Commit**

```bash
git add resources/views/layouts/sidebar.blade.php tests/Feature/SidebarPengelompokanTest.php
git commit -m "$(cat <<'EOF'
fix(sidebar): Kasus Pendampingan self-service pindah ke Kehadiran Saya (fallback)

Dihapus dari grup Pendampingan (domain, sekarang murni staf-triase).
Kehadiran Saya diberi guard eksklusi !hasRole('guru') supaya tidak dobel
dengan item yang sudah dipindah ke Ruang Guru (Task 2), dan jadi fallback
Kasus Pendampingan untuk karyawan pool yang bukan guru/siswa/orang tua.
EOF
)"
```

---

### Task 7: Test kombinasi identitas (spec §4.2, §4.4) + full regression check

**Files:**
- Modify: `tests/Feature/SidebarPengelompokanTest.php`

**Interfaces:**
- Consumes: seluruh perubahan Task 1-6.
- Produces: tidak ada — task terakhir.

- [ ] **Step 1: Tulis test — guru yang JUGA punya role administratif tidak kehilangan/menduplikasi item (spec §4.2)**

Tambahkan:
```php
it('keeps guru personal items in Ruang Guru and shows administrative items normally when the same user also holds wakasek_kurikulum', function () {
    $guru = siapkanGuruUntukSidebar();
    foreach (['komponen-penilaian.kelola', 'rapor.view', 'rapor.verify', 'kenaikan-kelas.kelola', 'jadwal-pelajaran.kelola'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $wakasekRole = Role::firstOrCreate(['name' => 'wakasek_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $wakasekRole->givePermissionTo(['komponen-penilaian.kelola', 'rapor.view', 'rapor.verify', 'kenaikan-kelas.kelola', 'jadwal-pelajaran.kelola']);
    $guru->assignRole('wakasek_kurikulum');

    $response = $this->actingAs($guru)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Ruang Guru');
    expect(substr_count($response->getContent(), 'Perangkat Ajar (RPP)'))->toBe(1);
    $response->assertSee('Kenaikan Kelas');
});
```

- [ ] **Step 2: Tulis test — role `guru_bk` TANPA role `guru` TIDAK mengubah konteks jadi Ruang Guru (spec §4.4)**

Tambahkan:
```php
it('does not treat guru_bk as guru identity for sidebar grouping purposes', function () {
    (new \Database\Seeders\RoleSeeder)->run();
    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('guru_bk');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Ruang Guru');
});
```

- [ ] **Step 3: Jalankan kedua test, HARUS PASS**

Run: `php artisan test --filter="also holds wakasek_kurikulum|does not treat guru_bk as guru identity" --compact`
Expected: 2 passed.

- [ ] **Step 4: Jalankan SELURUH `SidebarPengelompokanTest.php` — checkpoint**

Run: `php artisan test tests/Feature/SidebarPengelompokanTest.php tests/Feature/DalamPengembanganTest.php --compact`
Expected: SEMUA test PASS. Catat angka pasti.

- [ ] **Step 5: Cari test lain di seluruh suite yang mungkin menyentuh render dashboard/sidebar untuk role guru/siswa/orang_tua dan bisa terpengaruh perpindahan item**

Run:
```bash
grep -rln "assertSee.*Perangkat Ajar\|assertSee.*QR Kehadiran\|assertSee.*Izin/Cuti\|assertSee.*Kasus Pendampingan\|assertSee.*Dompet & Tagihan" tests/ | grep -v SidebarPengelompokanTest
```
Kalau ADA file yang muncul, buka dan periksa apakah assertion-nya bergantung pada LOKASI GRUP lama (kemungkinan kecil — assertion `assertSee` biasa tidak peduli grup, tapi kalau ada assertion urutan/`assertSeeInOrder` yang menyertakan nama grup lama, itu perlu diupdate). Kalau ketemu, STOP dan laporkan ke user sebelum mengubah file di luar yang disebut plan ini.

- [ ] **Step 6: Jalankan full test suite**

Run: `php artisan test --compact`
Expected: 0 failed. Catat angka pasti (jumlah passed/skipped) untuk laporan akhir.

- [ ] **Step 7: Jalankan `vendor/bin/pint --dirty --format agent`**

Run: `vendor/bin/pint --dirty --format agent`
Expected: tidak ada error. Kalau ada file yang diformat ulang:
```bash
git add -u
git commit -m "style: pint formatting untuk perubahan pengelompokan sidebar"
```

- [ ] **Step 8: Commit test kombinasi**

```bash
git add tests/Feature/SidebarPengelompokanTest.php
git commit -m "$(cat <<'EOF'
test(sidebar): tambah test kombinasi identitas (guru+wakasek, guru_bk tanpa guru)

Membuktikan precedence hanya menentukan LOKASI item personal, tidak
menyembunyikan kapabilitas administratif (spec 4.2); dan role
assignment-only (guru_bk) tidak pernah mengubah konteks jadi Ruang Guru
(spec 4.4) -- role != identitas profil, pelajaran dari sesi RBAC v2.
EOF
)"
```

- [ ] **Step 9: Laporkan hasil akhir ke user**

Ringkasan yang WAJIB disampaikan:
- Angka pasti full suite (`X passed, Y skipped, 0 failed`).
- Daftar commit hash Task 1-7 (+ Task 7 Step 7 kalau ada commit pint terpisah).
- Konfirmasi eksplisit: RPP/QR/Izin/Kasus guru berkumpul di Ruang Guru (tidak dobel di Akademik/Kehadiran Saya/Pendampingan); Ruang Siswa & Ruang Orang Tua tampil dengan link Dalam Pengembangan; Kasus Pendampingan pakai `viewAny()` (konselor pool sekarang terlihat); kombinasi guru+wakasek dan guru_bk-tanpa-guru sudah diverifikasi tidak salah kaprah.

---

## Self-Review (dilakukan penulis plan)

**Spec coverage**: §1.3 (gap `viewAny()`) → Task 2/3/4/6 (semua pemunculan Kasus Pendampingan). §2.2(a) → Task 2. §2.2(b) → Task 3. §2.2(c) → Task 4. §2.2(d) → Task 6. §2.2(e) → Task 5. §2.2(f) → Task 6 Step 4. §2.2(g) → Task 4 Step 4. §2.3 → Task 1. §4.1 → Task 2. §4.2 → Task 7 Step 1. §4.3 → Task 5. §4.4 → Task 7 Step 2. §4.5 → Task 3. §4.6 → Task 4. §4.7 → Task 6 Step 2 (test pool konselor) + Task 2 Step 2 (baseline guru/siswa/orang_tua tidak berubah, dibuktikan implisit lewat test yang sama menegaskan mereka tetap melihat menu). §4.8 → Task 7 Step 5. Semua requirement spec punya task yang menutupinya.

**Placeholder scan**: tidak ada "TBD"/"implement later" — semua step berisi kode lengkap atau command lengkap dengan expected output. Pola layout Blade (Task 1 Step 4) sudah diverifikasi langsung dari `admin/dashboard/orang-tua.blade.php:1` sebelum plan ditulis (`<x-app-layout>`) — bukan tebakan.

**Type consistency**: struktur array `$item` (`route`, `pattern`, `label`, `icon`, `+params` opsional) konsisten dipakai Task 3/4, dan perubahan render `route($item['route'], $item['params'] ?? [])` di Task 3 Step 3 kompatibel mundur dengan seluruh item lama yang tidak punya key `params`.
