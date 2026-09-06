# Perbaikan Visibilitas Menu & Keamanan Scope Yayasan/Lembaga Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tutup 3 celah keamanan bocor data lintas-yayasan, perbaiki 1 gerbang identitas menu yang salah, sembunyikan+guard 1 menu aksi fisik on-site, dan perbaiki 5 bug data yang diam-diam salah/kosong saat user yayasan berada di mode "Semua Lembaga".

**Architecture:** Semua fix adalah modifikasi kecil pada controller/view yang sudah ada — tidak ada model/migrasi/tabel baru. Pola inti yang berulang: (1) untuk bug keamanan (Kategori A), tambah cabang query eksplisit untuk aktor yayasan yang sebelumnya tidak ada sama sekali; (2) untuk bug data kosong (Kategori D), bungkus filter manual dengan kondisi `$lembagaId !== null` supaya saat kosong, filter di-skip dan `TenantScope` otomatis (atau filter `yayasan_id`) yang menangani agregat.

**Tech Stack:** Laravel 12, Pest (mayoritas file test proyek ini pakai gaya Pest `it()`, sebagian pakai gaya PHPUnit class-based `test_xxx()` — plan ini mengikuti gaya file yang SEDANG diedit, jangan mengubah gaya file existing).

## Global Constraints

- Spec sumber: `.agents/specs/2026-09-07-scope-yayasan-lembaga-menu-fix.md` — baca dulu sebelum mulai task manapun, semua kode fix persis ada di sana.
- **Kategori A**: filter yayasan HARUS respect `session('active_lembaga_id')` juga (kalau yayasan sudah pilih 1 lembaga spesifik via switcher, narrow ke situ, JANGAN tetap tampilkan semua lembaga yayasan). Perilaku user lembaga-scope TIDAK BOLEH berubah sama sekali (cabang existing untuk non-yayasan TETAP, cuma nambah cabang baru untuk yayasan).
- **Kategori B**: ini PURE navigasi sidebar (visibility), BUKAN perubahan authorization backend — controller di baliknya TIDAK disentuh.
- **Kategori C**: JANGAN cuma sembunyikan link doang — WAJIB guard server-side juga, karena user bisa akses URL langsung.
- **Kategori D**: SEMUA fix di sini WAJIB tetap menampilkan data (agregat benar), BUKAN menyembunyikan apapun — beda filosofi dari Kategori C.
- **Kategori D.3** (AttendanceConfiguration): `$titikAbsen`/`$penugasanShiftList`/`$guruList`/`$karyawanList` SENGAJA TIDAK diubah (tetap kosong saat belum pilih lembaga) — ini bukan bug, itu constraint "create wajib pilih lembaga dulu" yang sudah benar.
- **Kategori D.4**: `RuanganController` WAJIB reuse closure fallback miliknya SENDIRI (yang punya tambahan cabang `is_shared`) untuk stats, BUKAN closure generic `GedungController`.
- **TESTING — WAJIB dibaca sebelum menulis test manapun di plan ini**: `TenantContext::activeYayasanId()` (`app/Domains/Shared/Context/TenantContext.php`) resolusi yayasan_id-nya BERURUTAN: `$user->yayasan_id` → `$user->lembaga->yayasan_id` (kalau `lembaga_id` terisi) → `Lembaga::find(session('active_lembaga_id'))->yayasan_id` → **`Yayasan::first()?->id` (fallback TERAKHIR, yayasan ARBITRARY pertama di DB)**. Kalau test membuat user yayasan-scope TANPA `yayasan_id` eksplisit DAN TANPA `active_lembaga_id` di session, resolusinya jatuh ke fallback arbitrary itu — di test dengan lebih dari 1 Yayasan, ini bisa salah resolve ke yayasan yang BUKAN milik aktor. **SELALU set `'yayasan_id' => $yayasan->id` eksplisit saat membuat user yayasan-scope test** (pola ini sudah dipakai di `tests/Feature/Admin/OrangTuaCrudTest.php:235`, `tests/Feature/Admin/OrangTuaCrudTest.php:290`).
- Di luar scope (JANGAN dikerjakan): 3 item global lintas-SEMUA-yayasan (`JenisKaryawanMasterController`, `JabatanTambahanMasterController`, `WhatsAppTemplateController`), dropdown `JadwalPelajaranController`, method lain `VirtualAccountController`/`ManualPaymentController` selain `index()`, modul SPMB/PPDB.

---

### Task 1: Kategori A — Tutup Bocor Data Lintas-Yayasan (3 controller)

**Files:**
- Modify: `app/Http/Controllers/Admin/KasusAksesLogController.php:25-31`
- Modify: `app/Http/Controllers/Admin/KasusTerhapusController.php:26-28`
- Modify: `app/Http/Controllers/Admin/OrangTuaController.php:35-39`
- Test: `tests/Feature/Admin/KasusAksesLogViewTest.php` (modify existing test + tambah baru)
- Test: `tests/Feature/Admin/KasusTerhapusViewTest.php` (tambah baru)
- Test: `tests/Feature/Admin/OrangTuaCrudTest.php` (tambah baru, JANGAN ubah test existing baris 219-240 & 273-312)

**Interfaces:**
- Tidak ada interface baru — ini murni memperbaiki query internal 3 method `index()` yang sudah ada. Tidak ada perubahan signature/route/view yang dikonsumsi task lain.

- [ ] **Step 1: Perbaiki test existing yang akan REGRESI kalau tidak diupdate dulu**

`tests/Feature/Admin/KasusAksesLogViewTest.php` baris 67-89, test `'lets yayasan_super_admin see akses_klinis log rows across all lembaga'` membuat `$superAdmin = User::factory()->create();` TANPA `yayasan_id`. Setelah Step 2 di bawah membuat controller filter berdasarkan `$user->yayasan_id`, user tanpa `yayasan_id` akan resolve ke `Lembaga::where('yayasan_id', null)` (kosong) — test ini akan GAGAL kalau tidak diupdate DULU. Ubah baris `$superAdmin = User::factory()->create();` menjadi:

```php
$superAdmin = User::factory()->create(['yayasan_id' => $yayasan->id]);
```

Jalankan test ini SEKARANG (sebelum Step 2) untuk konfirmasi masih PASS di kode lama (perubahan test tidak mengubah assertion, cuma nambah field):

Run: `php artisan test --filter="lets yayasan_super_admin see akses_klinis log rows across all lembaga"`
Expected: PASS (1 passed)

- [ ] **Step 2: Commit perbaikan test existing**

```bash
git add tests/Feature/Admin/KasusAksesLogViewTest.php
git commit -m "test(kasus): set yayasan_id eksplisit pada user yayasan_super_admin di test akses log"
```

- [ ] **Step 3: Tulis test baru yang GAGAL — KasusAksesLogController bocor lintas yayasan**

Tambahkan ke `tests/Feature/Admin/KasusAksesLogViewTest.php`:

```php
it('does not leak akses_klinis log rows from a DIFFERENT yayasan to a yayasan_super_admin', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $siswaA = Siswa::factory()->create(['lembaga_id' => $lembagaA->id]);
    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kasusA = Kasus::factory()->create(['siswa_id' => $siswaA->id, 'lembaga_id' => $lembagaA->id, 'status' => StatusKasus::Berjalan]);
    $kasusB = Kasus::factory()->create(['siswa_id' => $siswaB->id, 'lembaga_id' => $lembagaB->id, 'status' => StatusKasus::Berjalan]);
    bukaHalamanKasusSebagaiKonselor($kasusA, $lembagaA);
    bukaHalamanKasusSebagaiKonselor($kasusB, $lembagaB);

    Permission::firstOrCreate(['name' => 'kasus.lihat-log-akses', 'guard_name' => 'web']);
    $superAdminRole = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $superAdminRole->givePermissionTo('kasus.lihat-log-akses');
    $superAdminYayasanA = User::factory()->create(['yayasan_id' => $yayasanA->id]);
    $superAdminYayasanA->assignRole($superAdminRole);

    $response = $this->actingAs($superAdminYayasanA)->get(route('admin.kasus.log-akses'));

    $response->assertOk();
    $response->assertSee($siswaA->nama_lengkap);
    $response->assertDontSee($siswaB->nama_lengkap);
});

it('narrows yayasan_super_admin akses_klinis log to the switcher-selected lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaX = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaY = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaX = Siswa::factory()->create(['lembaga_id' => $lembagaX->id]);
    $siswaY = Siswa::factory()->create(['lembaga_id' => $lembagaY->id]);
    $kasusX = Kasus::factory()->create(['siswa_id' => $siswaX->id, 'lembaga_id' => $lembagaX->id, 'status' => StatusKasus::Berjalan]);
    $kasusY = Kasus::factory()->create(['siswa_id' => $siswaY->id, 'lembaga_id' => $lembagaY->id, 'status' => StatusKasus::Berjalan]);
    bukaHalamanKasusSebagaiKonselor($kasusX, $lembagaX);
    bukaHalamanKasusSebagaiKonselor($kasusY, $lembagaY);

    Permission::firstOrCreate(['name' => 'kasus.lihat-log-akses', 'guard_name' => 'web']);
    $superAdminRole = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $superAdminRole->givePermissionTo('kasus.lihat-log-akses');
    $superAdmin = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $superAdmin->assignRole($superAdminRole);

    session(['active_lembaga_id' => $lembagaX->id]);
    $response = $this->actingAs($superAdmin)->get(route('admin.kasus.log-akses'));

    $response->assertOk();
    $response->assertSee($siswaX->nama_lengkap);
    $response->assertDontSee($siswaY->nama_lengkap);
});
```

- [ ] **Step 4: Jalankan test baru, pastikan GAGAL (bukti bug nyata)**

Run: `php artisan test --filter="does not leak akses_klinis log rows from a DIFFERENT yayasan"`
Expected: FAIL — `$response` mengandung `$siswaB->nama_lengkap` (assertDontSee gagal), membuktikan bocor.

- [ ] **Step 5: Perbaiki `KasusAksesLogController::index()`**

Buka `app/Http/Controllers/Admin/KasusAksesLogController.php`. Tambah `use App\Models\Lembaga;` di baris import (setelah `use App\Domains\Kasus\Models\Kasus;`). Ganti baris 24-31:

```php
        $user = auth()->user();
        $search = request('search');
        $perPage = in_array((int) request('per_page'), [10, 20, 25, 50]) ? (int) request('per_page') : 20;

        // Query dasar
        $baseQuery = Activity::query()
            ->where('log_name', 'akses_klinis')
            ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->whereHasMorph(
                'subject',
                [Kasus::class],
                fn ($subQuery) => $subQuery->withoutGlobalScopes()->withTrashed()->where('lembaga_id', $user->lembaga_id)
            ));
```

menjadi:

```php
        $user = auth()->user();
        $search = request('search');
        $perPage = in_array((int) request('per_page'), [10, 20, 25, 50]) ? (int) request('per_page') : 20;
        $lembagaIdsYayasan = Lembaga::where('yayasan_id', $user->yayasan_id)->pluck('id');
        $activeLembagaId = session('active_lembaga_id');

        // Query dasar
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

- [ ] **Step 6: Jalankan test, pastikan PASS**

Run: `php artisan test tests/Feature/Admin/KasusAksesLogViewTest.php`
Expected: semua test di file ini PASS (termasuk 2 test baru dari Step 3 dan test existing yang sudah diupdate di Step 1).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/KasusAksesLogController.php tests/Feature/Admin/KasusAksesLogViewTest.php
git commit -m "fix(kasus): batasi log akses klinis ke yayasan aktor sendiri untuk viewer scope yayasan"
```

- [ ] **Step 8: Tulis test baru yang GAGAL — KasusTerhapusController bocor lintas yayasan**

Tambahkan ke `tests/Feature/Admin/KasusTerhapusViewTest.php`:

```php
it('does not leak soft-deleted kasus from a DIFFERENT yayasan to a yayasan_super_admin', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $siswaA = Siswa::factory()->create(['lembaga_id' => $lembagaA->id]);
    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kasusA = Kasus::factory()->create(['siswa_id' => $siswaA->id, 'lembaga_id' => $lembagaA->id, 'status' => StatusKasus::Selesai]);
    $kasusB = Kasus::factory()->create(['siswa_id' => $siswaB->id, 'lembaga_id' => $lembagaB->id, 'status' => StatusKasus::Selesai]);
    $kasusA->delete();
    $kasusB->delete();

    Permission::firstOrCreate(['name' => 'kasus.lihat-log-akses', 'guard_name' => 'web']);
    $superAdminRole = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $superAdminRole->givePermissionTo('kasus.lihat-log-akses');
    $superAdminYayasanA = User::factory()->create(['yayasan_id' => $yayasanA->id]);
    $superAdminYayasanA->assignRole($superAdminRole);

    $response = $this->actingAs($superAdminYayasanA)->get(route('admin.kasus.terhapus'));

    $response->assertOk();
    $response->assertSee($siswaA->nama_lengkap);
    $response->assertDontSee($siswaB->nama_lengkap);
});

it('narrows yayasan_super_admin kasus terhapus to the switcher-selected lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaX = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaY = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaX = Siswa::factory()->create(['lembaga_id' => $lembagaX->id]);
    $siswaY = Siswa::factory()->create(['lembaga_id' => $lembagaY->id]);
    $kasusX = Kasus::factory()->create(['siswa_id' => $siswaX->id, 'lembaga_id' => $lembagaX->id, 'status' => StatusKasus::Selesai]);
    $kasusY = Kasus::factory()->create(['siswa_id' => $siswaY->id, 'lembaga_id' => $lembagaY->id, 'status' => StatusKasus::Selesai]);
    $kasusX->delete();
    $kasusY->delete();

    Permission::firstOrCreate(['name' => 'kasus.lihat-log-akses', 'guard_name' => 'web']);
    $superAdminRole = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $superAdminRole->givePermissionTo('kasus.lihat-log-akses');
    $superAdmin = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $superAdmin->assignRole($superAdminRole);

    session(['active_lembaga_id' => $lembagaX->id]);
    $response = $this->actingAs($superAdmin)->get(route('admin.kasus.terhapus'));

    $response->assertOk();
    $response->assertSee($siswaX->nama_lengkap);
    $response->assertDontSee($siswaY->nama_lengkap);
});
```

- [ ] **Step 9: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="does not leak soft-deleted kasus from a DIFFERENT yayasan"`
Expected: FAIL — `assertDontSee($siswaB->nama_lengkap)` gagal.

- [ ] **Step 10: Perbaiki `KasusTerhapusController::index()`**

Buka `app/Http/Controllers/Admin/KasusTerhapusController.php`. Tambah `use App\Models\Lembaga;`. Ganti baris 21-28:

```php
        $user = auth()->user();
        $search = request('search');
        $perPage = in_array((int) request('per_page'), [10, 20, 25, 50]) ? (int) request('per_page') : 20;

        // Query Dasar
        $baseQuery = Kasus::onlyTrashed()
            ->withoutGlobalScope(TenantScope::class)
            ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->where('lembaga_id', $user->lembaga_id));
```

menjadi:

```php
        $user = auth()->user();
        $search = request('search');
        $perPage = in_array((int) request('per_page'), [10, 20, 25, 50]) ? (int) request('per_page') : 20;
        $lembagaIdsYayasan = Lembaga::where('yayasan_id', $user->yayasan_id)->pluck('id');
        $activeLembagaId = session('active_lembaga_id');

        // Query Dasar
        $baseQuery = Kasus::onlyTrashed()
            ->withoutGlobalScope(TenantScope::class)
            ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->where('lembaga_id', $user->lembaga_id))
            ->when($user->widestScopeLevel() === 'yayasan', fn ($q) => $activeLembagaId
                ? $q->where('lembaga_id', $activeLembagaId)
                : $q->whereIn('lembaga_id', $lembagaIdsYayasan));
```

- [ ] **Step 11: Jalankan test, pastikan PASS**

Run: `php artisan test tests/Feature/Admin/KasusTerhapusViewTest.php`
Expected: semua test PASS.

- [ ] **Step 12: Commit**

```bash
git add app/Http/Controllers/Admin/KasusTerhapusController.php tests/Feature/Admin/KasusTerhapusViewTest.php
git commit -m "fix(kasus): batasi kasus terhapus ke yayasan aktor sendiri untuk viewer scope yayasan"
```

- [ ] **Step 13: Tulis test baru yang GAGAL — OrangTuaController bocor lintas yayasan (index)**

Cari file `tests/Feature/Admin/OrangTuaCrudTest.php`, baca bagian atas file untuk konvensi import (`OrangTua`, `Person`, `AkunOrangTuaGenerator`, dll — sudah dipakai test lain di file yang sama). Tambahkan test baru di file itu (dekat test `'lets yayasan_super_admin see an orang tua regardless of which lembaga their siswa belongs to'` baris 219):

```php
it('does not leak an orang tua from a DIFFERENT yayasan to a yayasan_super_admin on the index page', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $orangTuaA = app(AkunOrangTuaGenerator::class)->buat('Wali Yayasan A Index', '3201234567897777', '081234567810');
    $siswaA = Siswa::factory()->create(['lembaga_id' => $lembagaA->id]);
    $siswaA->orangTua()->attach($orangTuaA->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $orangTuaB = app(AkunOrangTuaGenerator::class)->buat('Wali Yayasan B Index', '3201234567898888', '081234567811');
    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembagaB->id]);
    $siswaB->orangTua()->attach($orangTuaB->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    Permission::firstOrCreate(['name' => 'orang-tua.view', 'guard_name' => 'web']);
    $superAdminRole = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $superAdminRole->givePermissionTo('orang-tua.view');
    $superAdminYayasanA = User::factory()->create(['yayasan_id' => $yayasanA->id]);
    $superAdminYayasanA->assignRole($superAdminRole);

    $response = $this->actingAs($superAdminYayasanA)->get(route('admin.orang-tua.index'));

    $response->assertOk();
    $response->assertSee('Wali Yayasan A Index');
    $response->assertDontSee('Wali Yayasan B Index');
});

it('narrows yayasan_super_admin orang tua index to the switcher-selected lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaX = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaY = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $orangTuaX = app(AkunOrangTuaGenerator::class)->buat('Wali Lembaga X Index', '3201234567899999', '081234567812');
    $siswaX = Siswa::factory()->create(['lembaga_id' => $lembagaX->id]);
    $siswaX->orangTua()->attach($orangTuaX->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $orangTuaY = app(AkunOrangTuaGenerator::class)->buat('Wali Lembaga Y Index', '3201234567900000', '081234567813');
    $siswaY = Siswa::factory()->create(['lembaga_id' => $lembagaY->id]);
    $siswaY->orangTua()->attach($orangTuaY->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    Permission::firstOrCreate(['name' => 'orang-tua.view', 'guard_name' => 'web']);
    $superAdminRole = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $superAdminRole->givePermissionTo('orang-tua.view');
    $superAdmin = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $superAdmin->assignRole($superAdminRole);

    session(['active_lembaga_id' => $lembagaX->id]);
    $response = $this->actingAs($superAdmin)->get(route('admin.orang-tua.index'));

    $response->assertOk();
    $response->assertSee('Wali Lembaga X Index');
    $response->assertDontSee('Wali Lembaga Y Index');
});
```

- [ ] **Step 14: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="does not leak an orang tua from a DIFFERENT yayasan to a yayasan_super_admin on the index page"`
Expected: FAIL — `assertDontSee('Wali Yayasan B Index')` gagal.

- [ ] **Step 15: Perbaiki `OrangTuaController::index()`**

Buka `app/Http/Controllers/Admin/OrangTuaController.php`. Tambah `use App\Models\Lembaga;` (cek dulu belum ada di import list — file ini sudah punya `use App\Models\Yayasan;` tapi belum `Lembaga`). Ganti baris 27-42:

```php
        $user = auth()->user();
        $search = $request->query('search');

        // OrangTua accounts always have lembaga_id = null by design, so eager-loading `user`
        // must bypass TenantScope or a lembaga-scoped viewer's own scope silently filters it
        // to null (Eloquent turns `where('lembaga_id', null)` into `whereNull`, which happens
        // to only pass when the viewer ALSO has a null lembaga_id — masking this for any
        // fixture/manager that never set one).
        $orangTuaList = OrangTua::with(['user' => fn ($q) => $q->withoutGlobalScope(TenantScope::class), 'person'])
            ->withCount('siswa')
            ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->where(fn ($q2) => $q2
                ->whereDoesntHave('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class))
                ->orWhereHas('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class)->where('siswa.lembaga_id', $user->lembaga_id))))
            ->when($search, fn ($q) => $q->search($search))
            ->orderByNama()
            ->get();
```

menjadi:

```php
        $user = auth()->user();
        $search = $request->query('search');
        $lembagaIdsYayasan = Lembaga::where('yayasan_id', $user->yayasan_id)->pluck('id');
        $activeLembagaId = session('active_lembaga_id');

        // OrangTua accounts always have lembaga_id = null by design, so eager-loading `user`
        // must bypass TenantScope or a lembaga-scoped viewer's own scope silently filters it
        // to null (Eloquent turns `where('lembaga_id', null)` into `whereNull`, which happens
        // to only pass when the viewer ALSO has a null lembaga_id — masking this for any
        // fixture/manager that never set one).
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

- [ ] **Step 16: Jalankan SEMUA test terkait, pastikan PASS termasuk test existing lama**

Run: `php artisan test tests/Feature/Admin/OrangTuaCrudTest.php`
Expected: semua test PASS — TERMASUK test existing baris 219-240 (`'lets yayasan_super_admin see an orang tua regardless of which lembaga their siswa belongs to'`, yang sudah pakai `yayasan_id` eksplisit sejak awal, harus tetap hijau tanpa perlu diubah) dan baris 273-312 (IDOR edit/update, tidak tersentuh perubahan ini sama sekali karena itu jalur berbeda/`show`-`edit`, bukan `index`).

- [ ] **Step 17: Commit**

```bash
git add app/Http/Controllers/Admin/OrangTuaController.php tests/Feature/Admin/OrangTuaCrudTest.php
git commit -m "fix(orang-tua): batasi index() ke yayasan aktor sendiri untuk viewer scope yayasan"
```

---

### Task 2: Kategori B — Perbaiki Gerbang Identitas Menu "Ruang Guru"

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php:14-18`
- Test: `tests/Feature/SidebarPengelompokanTest.php` (extend existing + tambah baru)

**Interfaces:**
- Tidak ada — murni kondisi tampil/tidak 5 baris array di sidebar. Tidak menyentuh route/controller manapun.

- [ ] **Step 1: Tulis test baru yang GAGAL — 5 menu Ruang Guru tampil untuk guru asli**

Buka `tests/Feature/SidebarPengelompokanTest.php`. Fungsi `siapkanGuruUntukSidebar()` (baris 17-32) SUDAH memberi semua permission yang dibutuhkan (`presensi.isi`, `komponen-penilaian.kelola-sendiri`, `asesmen.kelola`, `rapor.input-wali`, dll) — TIDAK perlu diubah. Tambahkan assertion baru ke test existing `'shows RPP, QR Kehadiran, Izin/Cuti, and Kasus Pendampingan under Ruang Guru for a guru account'` (baris 34-43), ubah body-nya jadi:

```php
it('shows RPP, QR Kehadiran, Izin/Cuti, and Kasus Pendampingan under Ruang Guru for a guru account', function () {
    $guru = siapkanGuruUntukSidebar();

    $response = $this->actingAs($guru)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSeeInOrder(['Ruang Guru', 'Perangkat Ajar (RPP)']);
    $response->assertSee('QR Kehadiran Saya');
    $response->assertSee('Izin/Cuti Saya');
    $response->assertSee('Jurnal & Presensi');
    $response->assertSee('Rekap Kehadiran');
    $response->assertSee('Komponen Penilaian (TP)');
    $response->assertSee('Asesmen & Nilai');
    $response->assertSee('Rapor Wali Kelas');
});
```

Lalu tambahkan test BARU (fungsi terpisah) di file yang sama:

```php
it('hides Jurnal Presensi, Rekap Kehadiran, Komponen Penilaian, Asesmen, dan Rapor Wali Kelas dari user yang bukan guru walau punya semua permission terkait', function () {
    foreach (['presensi.isi', 'komponen-penilaian.kelola-sendiri', 'asesmen.kelola', 'rapor.input-wali'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_sidebar_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['presensi.isi', 'komponen-penilaian.kelola-sendiri', 'asesmen.kelola', 'rapor.input-wali']);

    $yayasan = Yayasan::factory()->create();
    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Jurnal & Presensi');
    $response->assertDontSee('Rekap Kehadiran');
    $response->assertDontSee('Komponen Penilaian (TP)');
    $response->assertDontSee('Asesmen & Nilai');
    $response->assertDontSee('Rapor Wali Kelas');
});
```

(Tambah `use App\Models\Yayasan;` kalau belum ada di import — file ini SUDAH import `App\Models\Yayasan` di baris 13, cukup pastikan tetap ada.)

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="hides Jurnal Presensi, Rekap Kehadiran, Komponen Penilaian, Asesmen, dan Rapor Wali Kelas"`
Expected: FAIL — kelima `assertDontSee` gagal karena user ini punya semua permission tapi belum ada cek `hasRole('guru')`.

- [ ] **Step 3: Perbaiki `resources/views/layouts/sidebar.blade.php`**

Ganti baris 14-18 (dalam grup `'Ruang Guru'`):

```php
                Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.index', 'pattern' => 'guru.jurnal-kbm.index', 'label' => 'Jurnal & Presensi', 'icon' => 'file-pen'] : null,
                Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.rekap', 'pattern' => 'guru.jurnal-kbm.rekap', 'label' => 'Rekap Kehadiran', 'icon' => 'chart-bar'] : null,
                Auth::user()->can('komponen-penilaian.kelola-sendiri') ? ['route' => 'guru.komponen-penilaian.index', 'pattern' => 'guru.komponen-penilaian.*', 'label' => 'Komponen Penilaian (TP)', 'icon' => 'list-todo'] : null,
                Auth::user()->can('asesmen.kelola') ? ['route' => 'guru.asesmen.index', 'pattern' => 'guru.asesmen.*', 'label' => 'Asesmen & Nilai', 'icon' => 'bar-chart-3'] : null,
                Auth::user()->can('rapor.input-wali') ? ['route' => 'guru.rapor.catatan.index', 'pattern' => 'guru.rapor.*', 'label' => 'Rapor Wali Kelas', 'icon' => 'book-text'] : null,
```

menjadi:

```php
                Auth::user()->hasRole('guru') && Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.index', 'pattern' => 'guru.jurnal-kbm.index', 'label' => 'Jurnal & Presensi', 'icon' => 'file-pen'] : null,
                Auth::user()->hasRole('guru') && Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.rekap', 'pattern' => 'guru.jurnal-kbm.rekap', 'label' => 'Rekap Kehadiran', 'icon' => 'chart-bar'] : null,
                Auth::user()->hasRole('guru') && Auth::user()->can('komponen-penilaian.kelola-sendiri') ? ['route' => 'guru.komponen-penilaian.index', 'pattern' => 'guru.komponen-penilaian.*', 'label' => 'Komponen Penilaian (TP)', 'icon' => 'list-todo'] : null,
                Auth::user()->hasRole('guru') && Auth::user()->can('asesmen.kelola') ? ['route' => 'guru.asesmen.index', 'pattern' => 'guru.asesmen.*', 'label' => 'Asesmen & Nilai', 'icon' => 'bar-chart-3'] : null,
                Auth::user()->hasRole('guru') && Auth::user()->can('rapor.input-wali') ? ['route' => 'guru.rapor.catatan.index', 'pattern' => 'guru.rapor.*', 'label' => 'Rapor Wali Kelas', 'icon' => 'book-text'] : null,
```

- [ ] **Step 4: Jalankan test, pastikan PASS**

Run: `php artisan test tests/Feature/SidebarPengelompokanTest.php`
Expected: semua test PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/sidebar.blade.php tests/Feature/SidebarPengelompokanTest.php
git commit -m "fix(sidebar): 5 menu Ruang Guru wajib hasRole('guru'), bukan cuma permission mentah"
```

---

### Task 3: Kategori C — Sembunyikan + Guard "Scan QR" Saat Mode "Semua Lembaga"

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php:88`
- Modify: `app/Http/Controllers/Admin/AttendanceQrScanController.php:20-28`
- Test: `tests/Feature/Admin/AttendanceQrScanViewTest.php` (tambah baru)
- Test: `tests/Feature/SidebarPengelompokanTest.php` (tambah baru)

**Interfaces:**
- `AttendanceQrScanController::index()` sekarang bisa mengembalikan HTTP 422 (sebelumnya selalu 200) — tidak ada task lain yang mengonsumsi response ini secara terprogram (murni halaman UI), aman.

- [ ] **Step 1: Tulis test baru yang GAGAL — guard server-side belum ada**

Tambahkan ke `tests/Feature/Admin/AttendanceQrScanViewTest.php`:

```php
it('returns 422 when a yayasan-scope user has not picked an active lembaga', function () {
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.catat', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_scan_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kehadiran-sdm.catat']);
    $yayasan = Yayasan::factory()->create();
    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('admin.kehadiran-sdm.scan.index'));

    $response->assertStatus(422);
});

it('renders the scan page normally once a yayasan-scope user picks an active lembaga', function () {
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.catat', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_scan_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kehadiran-sdm.catat']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    session(['active_lembaga_id' => $lembaga->id]);
    $response = $this->actingAs($user)->get(route('admin.kehadiran-sdm.scan.index'));

    $response->assertOk();
});
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="returns 422 when a yayasan-scope user has not picked an active lembaga"`
Expected: FAIL — sekarang mengembalikan 200 (halaman dengan `titikAbsen` kosong), bukan 422.

- [ ] **Step 3: Perbaiki `AttendanceQrScanController::index()`**

Buka `app/Http/Controllers/Admin/AttendanceQrScanController.php`. Ganti baris 20-28:

```php
    public function index(Request $request): View
    {
        $this->authorize('kehadiran-sdm.catat');

        $lembagaId = $this->resolveLembagaId($request);
        $titikAbsen = $lembagaId ? AttendancePoint::where('lembaga_id', $lembagaId)->where('is_active', true)->orderBy('nama')->get() : collect();

        return view('admin.kehadiran-sdm.scan', ['titikAbsen' => $titikAbsen]);
    }
```

menjadi:

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

- [ ] **Step 4: Jalankan test dari Step 1, pastikan PASS**

Run: `php artisan test tests/Feature/Admin/AttendanceQrScanViewTest.php`
Expected: semua test PASS (termasuk test lama `'renders the scan page with both camera and manual mode toggles'` yang pakai user lembaga-scope biasa — `$lembagaId` untuk user lembaga-scope selalu terisi dari `user->lembaga_id`, jadi TIDAK terpengaruh guard baru ini).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/AttendanceQrScanController.php tests/Feature/Admin/AttendanceQrScanViewTest.php
git commit -m "fix(kehadiran-sdm): guard server-side 422 di scan QR saat yayasan blm pilih lembaga aktif"
```

- [ ] **Step 6: Tulis test baru yang GAGAL — menu "Scan QR" tampil di sidebar saat mode "Semua Lembaga"**

Tambahkan ke `tests/Feature/SidebarPengelompokanTest.php`:

```php
it('hides Scan QR from sidebar when a yayasan-scope user has not picked an active lembaga', function () {
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.catat', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_scan_sidebar_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kehadiran-sdm.catat']);
    $yayasan = Yayasan::factory()->create();
    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Scan QR');
});

it('shows Scan QR in sidebar once a yayasan-scope user picks an active lembaga', function () {
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.catat', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_scan_sidebar_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kehadiran-sdm.catat']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    session(['active_lembaga_id' => $lembaga->id]);
    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Scan QR');
});
```

- [ ] **Step 7: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="hides Scan QR from sidebar when a yayasan-scope user has not picked an active lembaga"`
Expected: FAIL — menu masih tampil.

- [ ] **Step 8: Perbaiki `resources/views/layouts/sidebar.blade.php`**

Ganti baris 88:

```php
                Auth::user()->can('kehadiran-sdm.catat') ? ['route' => 'admin.kehadiran-sdm.scan.index', 'pattern' => 'admin.kehadiran-sdm.scan.*', 'label' => 'Scan QR', 'icon' => 'qr-code'] : null,
```

menjadi:

```php
                Auth::user()->can('kehadiran-sdm.catat') && (Auth::user()->widestScopeLevel() !== 'yayasan' || session('active_lembaga_id') !== null) ? ['route' => 'admin.kehadiran-sdm.scan.index', 'pattern' => 'admin.kehadiran-sdm.scan.*', 'label' => 'Scan QR', 'icon' => 'qr-code'] : null,
```

- [ ] **Step 9: Jalankan test, pastikan PASS**

Run: `php artisan test tests/Feature/SidebarPengelompokanTest.php`
Expected: semua test PASS.

- [ ] **Step 10: Commit**

```bash
git add resources/views/layouts/sidebar.blade.php tests/Feature/SidebarPengelompokanTest.php
git commit -m "fix(sidebar): sembunyikan menu Scan QR saat yayasan mode Semua Lembaga"
```

---

### Task 4: Kategori D — Virtual Account & Manual Payment (Keuangan)

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Keuangan/VirtualAccountController.php:33-77` (method `index()` saja)
- Modify: `app/Http/Controllers/Lembaga/Keuangan/ManualPaymentController.php:24-60` (method `index()` saja)
- Test: `tests/Feature/Admin/VirtualAccountControllerTest.php` (tambah baru)
- Test: `tests/Feature/Admin/ManualPaymentControllerTest.php` (tambah baru)

**Interfaces:**
- Tidak ada perubahan signature. `index()` tetap mengembalikan `View` yang sama, hanya isi datanya yang benar saat yayasan mode "Semua Lembaga".

- [ ] **Step 1: Tulis test baru yang GAGAL — VirtualAccount agregat semua lembaga saat mode "Semua Lembaga"**

Tambahkan ke `tests/Feature/Admin/VirtualAccountControllerTest.php` (helper `buatSiswaDenganVa()` sudah ada di file ini, baris 31-51):

```php
it('aggregates VA count and list across all lembaga when yayasan scope has no active lembaga selected', function () {
    Permission::firstOrCreate(['name' => 'pembayaran.virtual-account', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'bendahara_yayasan_va_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo('pembayaran.virtual-account');

    $yayasan = Yayasan::factory()->create();
    $lembagaX = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaY = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    [$siswaX] = buatSiswaDenganVa($lembagaX, 'Siswa VA Lembaga X');
    [$siswaY] = buatSiswaDenganVa($lembagaY, 'Siswa VA Lembaga Y');

    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('admin.virtual-account.index'));

    $response->assertOk();
    $response->assertViewHas('totalVa', 2);
    $response->assertSee('Siswa VA Lembaga X');
    $response->assertSee('Siswa VA Lembaga Y');
});
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="aggregates VA count and list across all lembaga when yayasan scope has no active lembaga selected"`
Expected: FAIL — `totalVa` bernilai 0 (bukan 2), karena `where('lembaga_id', null)` menjadi `whereNull`.

- [ ] **Step 3: Perbaiki `VirtualAccountController::index()`**

Buka `app/Http/Controllers/Lembaga/Keuangan/VirtualAccountController.php`. Ganti baris 33-77:

```php
        $query = BriVirtualAccount::where('va_type', 'WALLET_PERMANENT')
            ->whereHas('wallet.siswa', function ($q) use ($lembagaId, $search, $kelasId) {
                $q->where('lembaga_id', $lembagaId);

                if ($search) {
                    $q->search($search);
                }

                if ($kelasId) {
                    $q->where('kelas_id', $kelasId);
                }
            })
            ->with(['wallet.siswa.kelas'])
            ->latest('created_at');

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;
        $paginated = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.lembaga.keuangan.virtual-account._daftar', [
                'vaList' => $paginated,
                'perPage' => $perPage,
            ]);
        }

        $totalVa = BriVirtualAccount::where('va_type', 'WALLET_PERMANENT')
            ->whereHas('wallet.siswa', fn ($q) => $q->where('lembaga_id', $lembagaId))
            ->count();

        $totalSaldo = (float) BriVirtualAccount::where('va_type', 'WALLET_PERMANENT')
            ->whereHas('wallet.siswa', fn ($q) => $q->where('lembaga_id', $lembagaId))
            ->join('wallets', 'bri_virtual_accounts.wallet_id', '=', 'wallets.id')
            ->sum('wallets.balance');

        $totalBelumVa = Siswa::where('lembaga_id', $lembagaId)
            ->where('status', StatusSiswa::Aktif->value)
            ->whereDoesntHave('wallet.briVirtualAccounts', fn ($q) => $q->where('va_type', 'WALLET_PERMANENT'))
            ->count();

        $kelasList = Kelas::where('lembaga_id', $lembagaId)
            ->with('tahunAjaran')
            ->orderBy('nama')
            ->get();
```

menjadi:

```php
        $query = BriVirtualAccount::where('va_type', 'WALLET_PERMANENT')
            ->whereHas('wallet.siswa', function ($q) use ($lembagaId, $search, $kelasId) {
                $q->when($lembagaId !== null, fn ($q2) => $q2->where('lembaga_id', $lembagaId));

                if ($search) {
                    $q->search($search);
                }

                if ($kelasId) {
                    $q->where('kelas_id', $kelasId);
                }
            })
            ->with(['wallet.siswa.kelas'])
            ->latest('created_at');

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;
        $paginated = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.lembaga.keuangan.virtual-account._daftar', [
                'vaList' => $paginated,
                'perPage' => $perPage,
            ]);
        }

        $totalVa = BriVirtualAccount::where('va_type', 'WALLET_PERMANENT')
            ->whereHas('wallet.siswa', fn ($q) => $q->when($lembagaId !== null, fn ($q2) => $q2->where('lembaga_id', $lembagaId)))
            ->count();

        $totalSaldo = (float) BriVirtualAccount::where('va_type', 'WALLET_PERMANENT')
            ->whereHas('wallet.siswa', fn ($q) => $q->when($lembagaId !== null, fn ($q2) => $q2->where('lembaga_id', $lembagaId)))
            ->join('wallets', 'bri_virtual_accounts.wallet_id', '=', 'wallets.id')
            ->sum('wallets.balance');

        $totalBelumVa = Siswa::when($lembagaId !== null, fn ($q) => $q->where('lembaga_id', $lembagaId))
            ->where('status', StatusSiswa::Aktif->value)
            ->whereDoesntHave('wallet.briVirtualAccounts', fn ($q) => $q->where('va_type', 'WALLET_PERMANENT'))
            ->count();

        $kelasList = Kelas::when($lembagaId !== null, fn ($q) => $q->where('lembaga_id', $lembagaId))
            ->with('tahunAjaran')
            ->orderBy('nama')
            ->get();
```

- [ ] **Step 4: Jalankan test, pastikan PASS**

Run: `php artisan test tests/Feature/Admin/VirtualAccountControllerTest.php tests/Feature/Admin/VirtualAccountAuthorizationTest.php`
Expected: semua test PASS (regresi test lembaga-scope existing juga tetap hijau, karena `$lembagaId` untuk lembaga-scope selalu non-null).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Lembaga/Keuangan/VirtualAccountController.php tests/Feature/Admin/VirtualAccountControllerTest.php
git commit -m "fix(keuangan): agregat Virtual Account lintas lembaga saat yayasan mode Semua Lembaga"
```

- [ ] **Step 6: Tulis test baru yang GAGAL — ManualPayment agregat semua lembaga**

Tambahkan ke `tests/Feature/Admin/ManualPaymentControllerTest.php`:

```php
it('aggregates pending manual payment requests across all lembaga when yayasan scope has no active lembaga selected', function () {
    Permission::firstOrCreate(['name' => 'pembayaran.verifikasi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'bendahara_yayasan_mp_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo('pembayaran.verifikasi');

    $yayasan = Yayasan::factory()->create();
    $lembagaX = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaY = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $siswaX = Siswa::factory()->create(['lembaga_id' => $lembagaX->id, 'nama_lengkap' => 'Siswa MP Lembaga X']);
    $pembayaranX = Pembayaran::factory()->create(['siswa_id' => $siswaX->id, 'status' => 'menunggu_verifikasi']);
    ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaranX->id, 'requested_by' => $siswaX->id, 'amount' => 100000,
        'transfer_proof_path' => 'x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $siswaY = Siswa::factory()->create(['lembaga_id' => $lembagaY->id, 'nama_lengkap' => 'Siswa MP Lembaga Y']);
    $pembayaranY = Pembayaran::factory()->create(['siswa_id' => $siswaY->id, 'status' => 'menunggu_verifikasi']);
    ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaranY->id, 'requested_by' => $siswaY->id, 'amount' => 150000,
        'transfer_proof_path' => 'y.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('admin.manual-payment.index'));

    $response->assertOk();
    $response->assertViewHas('totalMenunggu', 2);
    $response->assertSee('Siswa MP Lembaga X');
    $response->assertSee('Siswa MP Lembaga Y');
});
```

- [ ] **Step 7: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="aggregates pending manual payment requests across all lembaga when yayasan scope has no active lembaga selected"`
Expected: FAIL — `totalMenunggu` bernilai 0.

- [ ] **Step 8: Perbaiki `ManualPaymentController::index()`**

Buka `app/Http/Controllers/Lembaga/Keuangan/ManualPaymentController.php`. Method `index()` (baris 20-65) berisi **3 tempat** dengan bug `where('lembaga_id', $lembagaId)` (baris 28, 58-59, dan 61-62 — yang terakhir ini, `totalNominalMenunggu`, gampang terlewat karena identik dengan `totalMenunggu` di atasnya). Ganti PERSIS baris 24-64:

```php
        $lembagaId = $this->lembagaId($request);

        $query = ManualPaymentRequest::where('status', 'PENDING')
            ->whereHas('pembayaran', function ($q) use ($lembagaId) {
                $q->whereHas('siswa', fn ($q2) => $q2->where('lembaga_id', $lembagaId));
            })
            ->with(['pembayaran.siswa', 'pembayaran.pembayaranTagihan', 'requestedBy'])
            ->latest('transfer_date');

        if ($search = $request->input('search')) {
            $query->whereHas('pembayaran.siswa', fn ($q) => $q->search($search));
        }

        if ($dari = $request->input('dari')) {
            $query->where('transfer_date', '>=', $dari);
        }

        if ($sampai = $request->input('sampai')) {
            $query->where('transfer_date', '<=', $sampai);
        }

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;
        $paginated = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.lembaga.keuangan.manual-payment._daftar', [
                'requestList' => $paginated,
                'perPage' => $perPage,
            ]);
        }

        return view('portals.lembaga.keuangan.manual-payment.index', [
            'requestList' => $paginated,
            'perPage' => $perPage,
            'totalMenunggu' => ManualPaymentRequest::where('status', 'PENDING')
                ->whereHas('pembayaran.siswa', fn ($q) => $q->where('lembaga_id', $lembagaId))
                ->count(),
            'totalNominalMenunggu' => ManualPaymentRequest::where('status', 'PENDING')
                ->whereHas('pembayaran.siswa', fn ($q) => $q->where('lembaga_id', $lembagaId))
                ->sum('amount'),
        ]);
```

menjadi (SEMUA 3 closure `where('lembaga_id', $lembagaId)` dibungkus `when($lembagaId !== null, ...)`, TIDAK ADA baris lain yang berubah):

```php
        $lembagaId = $this->lembagaId($request);

        $query = ManualPaymentRequest::where('status', 'PENDING')
            ->whereHas('pembayaran', function ($q) use ($lembagaId) {
                $q->whereHas('siswa', fn ($q2) => $q2->when($lembagaId !== null, fn ($q3) => $q3->where('lembaga_id', $lembagaId)));
            })
            ->with(['pembayaran.siswa', 'pembayaran.pembayaranTagihan', 'requestedBy'])
            ->latest('transfer_date');

        if ($search = $request->input('search')) {
            $query->whereHas('pembayaran.siswa', fn ($q) => $q->search($search));
        }

        if ($dari = $request->input('dari')) {
            $query->where('transfer_date', '>=', $dari);
        }

        if ($sampai = $request->input('sampai')) {
            $query->where('transfer_date', '<=', $sampai);
        }

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;
        $paginated = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.lembaga.keuangan.manual-payment._daftar', [
                'requestList' => $paginated,
                'perPage' => $perPage,
            ]);
        }

        return view('portals.lembaga.keuangan.manual-payment.index', [
            'requestList' => $paginated,
            'perPage' => $perPage,
            'totalMenunggu' => ManualPaymentRequest::where('status', 'PENDING')
                ->whereHas('pembayaran.siswa', fn ($q) => $q->when($lembagaId !== null, fn ($q2) => $q2->where('lembaga_id', $lembagaId)))
                ->count(),
            'totalNominalMenunggu' => ManualPaymentRequest::where('status', 'PENDING')
                ->whereHas('pembayaran.siswa', fn ($q) => $q->when($lembagaId !== null, fn ($q2) => $q2->where('lembaga_id', $lembagaId)))
                ->sum('amount'),
        ]);
```

- [ ] **Step 9: Jalankan test, pastikan PASS**

Run: `php artisan test tests/Feature/Admin/ManualPaymentControllerTest.php tests/Feature/Admin/ManualPaymentIndexAuthorizationTest.php tests/Feature/Admin/ManualPaymentIndexControllerTest.php tests/Feature/Admin/ManualPaymentNotificationTest.php`
Expected: semua test PASS.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Lembaga/Keuangan/ManualPaymentController.php tests/Feature/Admin/ManualPaymentControllerTest.php
git commit -m "fix(keuangan): agregat Manual Payment lintas lembaga saat yayasan mode Semua Lembaga"
```

---

### Task 5: Kategori D — AttendanceConfigurationController (Kehadiran SDM)

**Files:**
- Modify: `app/Http/Controllers/Admin/AttendanceConfigurationController.php:53-94` (bagian `index()` — HANYA 5 query `$konfigurasi`/`$kalenderEntriList`/`$policyList`/`$jenisShiftList`/`$kuotaCutiList`, JANGAN sentuh `$titikAbsen`/`$penugasanShiftList`/`$guruList`/`$karyawanList`)
- Test: `tests/Feature/Admin/AttendanceConfigurationControllerTest.php` (tambah baru)

**Interfaces:**
- Tidak ada perubahan signature.

- [ ] **Step 1: Tulis test baru yang GAGAL**

Tambahkan ke `tests/Feature/Admin/AttendanceConfigurationControllerTest.php`. Tambah helper baru (fungsi terpisah, jangan ubah `actingAsAdminSdm` yang sudah ada):

```php
function actingAsAdminSdmYayasan(Yayasan $yayasan): User
{
    foreach (['kehadiran-sdm.view', 'kehadiran-sdm.kelola-konfigurasi'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_sdm_yayasan_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kehadiran-sdm.view', 'kehadiran-sdm.kelola-konfigurasi']);

    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    return $user;
}

it('shows AttendancePolicy from ALL lembaga plus the national one when yayasan scope has no active lembaga selected', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaX = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaY = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    \App\Domains\Sdm\Models\AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'jenis_ptk' => 'guru_kelas',
        'jam_masuk' => '07:00', 'jam_pulang' => '15:00', 'toleransi_menit' => 15, 'hari_kerja' => ['senin', 'selasa'],
    ]);
    \App\Domains\Sdm\Models\AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaX->id, 'jenis_ptk' => 'guru_kelas',
        'jam_masuk' => '06:45', 'jam_pulang' => '15:00', 'toleransi_menit' => 10, 'hari_kerja' => ['senin'],
    ]);
    \App\Domains\Sdm\Models\AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaY->id, 'jenis_ptk' => 'guru_kelas',
        'jam_masuk' => '07:15', 'jam_pulang' => '15:30', 'toleransi_menit' => 20, 'hari_kerja' => ['selasa'],
    ]);

    $user = actingAsAdminSdmYayasan($yayasan);

    $response = $this->actingAs($user)->get(route('admin.kehadiran-sdm.konfigurasi.index'));

    $response->assertOk();
    $response->assertViewHas('policyList', function ($policyList) {
        return $policyList->count() === 3;
    });
});
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="shows AttendancePolicy from ALL lembaga plus the national one when yayasan scope has no active lembaga selected"`
Expected: FAIL — `policyList` cuma berisi 1 (entri nasional saja), bukan 3.

- [ ] **Step 3: Perbaiki `AttendanceConfigurationController::index()`**

Buka `app/Http/Controllers/Admin/AttendanceConfigurationController.php`. Ganti baris 53-94 (5 query `$konfigurasi`, `$kalenderEntriList`, `$policyList`, `$jenisShiftList`, `$kuotaCutiList` — JANGAN ubah `$titikAbsen` baris 60, `$penugasanShiftList` baris 96-100, `$guruList`/`$karyawanList` baris 102-109):

```php
        $konfigurasi = AttendanceMethodConfiguration::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->get();

        $titikAbsen = $lembagaId ? AttendancePoint::where('lembaga_id', $lembagaId)->orderBy('nama')->get() : collect();

        $lembaga = $lembagaId ? Lembaga::find($lembagaId) : null;

        $kalenderEntriList = $yayasanId ? KalenderKerjaSdm::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->orderBy('tanggal')
            ->get() : collect();

        $policyList = $yayasanId ? AttendancePolicy::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->with('jenisKaryawan')
            ->get() : collect();

        $jenisShiftList = $yayasanId ? JenisShift::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->orderBy('nama')
            ->get() : collect();

        $kuotaCutiList = $yayasanId ? KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->orderByRaw('lembaga_id IS NULL')
            ->get() : collect();
```

menjadi:

```php
        $konfigurasi = AttendanceMethodConfiguration::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->when($lembagaId !== null, fn ($query) => $query->where(function ($q) use ($lembagaId) {
                $q->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            }))
            ->get();

        $titikAbsen = $lembagaId ? AttendancePoint::where('lembaga_id', $lembagaId)->orderBy('nama')->get() : collect();

        $lembaga = $lembagaId ? Lembaga::find($lembagaId) : null;

        $kalenderEntriList = $yayasanId ? KalenderKerjaSdm::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->when($lembagaId !== null, fn ($query) => $query->where(function ($q) use ($lembagaId) {
                $q->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            }))
            ->orderBy('tanggal')
            ->get() : collect();

        $policyList = $yayasanId ? AttendancePolicy::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->when($lembagaId !== null, fn ($query) => $query->where(function ($q) use ($lembagaId) {
                $q->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            }))
            ->with('jenisKaryawan')
            ->get() : collect();

        $jenisShiftList = $yayasanId ? JenisShift::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->when($lembagaId !== null, fn ($query) => $query->where(function ($q) use ($lembagaId) {
                $q->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            }))
            ->orderBy('nama')
            ->get() : collect();

        $kuotaCutiList = $yayasanId ? KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->when($lembagaId !== null, fn ($query) => $query->where(function ($q) use ($lembagaId) {
                $q->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            }))
            ->orderByRaw('lembaga_id IS NULL')
            ->get() : collect();
```

- [ ] **Step 4: Jalankan test, pastikan PASS**

Run: `php artisan test tests/Feature/Admin/AttendanceConfigurationControllerTest.php tests/Feature/Admin/AttendanceConfigurationKalenderControllerTest.php`
Expected: semua test PASS, termasuk test existing `'shows the yayasan-level default method configuration (lembaga_id null) to a lembaga-scoped admin_sdm'` (baris 60) yang TIDAK terpengaruh karena itu pakai actor lembaga-scope (`$lembagaId` selalu terisi untuk mereka, jadi cabang `when()` tetap jalan seperti sebelumnya).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/AttendanceConfigurationController.php tests/Feature/Admin/AttendanceConfigurationControllerTest.php
git commit -m "fix(kehadiran-sdm): tampilkan konfigurasi gabungan nasional+semua lembaga saat yayasan mode Semua Lembaga"
```

---

### Task 6: Kategori D — Kartu Ringkasan Statistik Sarpras & Pengadaan (4 controller)

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Sarpras/GedungController.php:58-60`
- Modify: `app/Http/Controllers/Lembaga/Sarpras/RuanganController.php` (baris ~69-72, REUSE closure fallback list-nya sendiri, BUKAN closure generic)
- Modify: `app/Http/Controllers/Lembaga/Sarpras/KategoriAsetController.php:56-57`
- Modify: `app/Http/Controllers/Lembaga/Pengadaan/PengajuanPengadaanController.php:60-65`
- Test: `tests/Feature/Sarpras/CrossTenantIsolationTest.php` (tambah baru, PHPUnit class-based style — ikuti gaya file ini)
- Test: `tests/Feature/Pengadaan/CrossTenantIsolationTest.php` (tambah baru, PHPUnit class-based style)

**Interfaces:**
- Tidak ada perubahan signature — hanya nilai `$totalXxx`/`$stats` yang dikirim ke view.

- [ ] **Step 1: Tulis test baru yang GAGAL — GedungController**

Tambahkan method baru ke class `Tests\Feature\Sarpras\CrossTenantIsolationTest` (`tests/Feature/Sarpras/CrossTenantIsolationTest.php`) — baca `setUp()` (baris 35-97) dulu untuk konteks `$yayasan`/`$lembaga`/`$lembagaLain` yang sudah dibuat di sana (nama variabel lokal di `setUp()`, cek definisi property di baris 25-33 — TIDAK ada `$yayasan`/`$lembagaLain` sebagai property; itu variabel lokal di `setUp()`, jadi test baru harus buat sendiri fixture serupa, JANGAN asumsikan property itu ada). Tambahkan method baru:

```php
    public function test_kartu_ringkasan_gedung_menghitung_agregat_semua_lembaga_saat_yayasan_mode_semua_lembaga(): void
    {
        $yayasan = Yayasan::create(['nama' => 'Yayasan Kartu Ringkasan']);
        $lembagaX = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'Lembaga X', 'jenjang' => 'SD', 'npsn' => '9001', 'status_aktif' => true]);
        $lembagaY = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'Lembaga Y', 'jenjang' => 'SD', 'npsn' => '9002', 'status_aktif' => true]);

        Gedung::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaX->id, 'kode_gedung' => 'GD-X', 'nama_gedung' => 'Gedung X', 'jumlah_lantai' => 2, 'is_aktif' => true]);
        Gedung::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaY->id, 'kode_gedung' => 'GD-Y', 'nama_gedung' => 'Gedung Y', 'jumlah_lantai' => 3, 'is_aktif' => true]);

        $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_gedung_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $role->givePermissionTo(['sarpras.gedung.view']);
        $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('admin.sarpras.gedung.index'));

        $response->assertOk();
        $response->assertViewHas('totalGedung', 2);
        $response->assertViewHas('totalLantai', 5);
    }
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="test_kartu_ringkasan_gedung_menghitung_agregat_semua_lembaga_saat_yayasan_mode_semua_lembaga"`
Expected: FAIL — `totalGedung`/`totalLantai` bernilai 0.

- [ ] **Step 3: Perbaiki `GedungController::index()`**

Buka `app/Http/Controllers/Lembaga/Sarpras/GedungController.php`. Ganti baris 58-60:

```php
        $totalGedung = Gedung::where('lembaga_id', $lembagaId)->count();
        $totalLantai = (int) Gedung::where('lembaga_id', $lembagaId)->sum('jumlah_lantai');
        $totalRuangan = \App\Domains\Sarpras\Models\Ruangan::where('lembaga_id', $lembagaId)->count();
```

menjadi:

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

- [ ] **Step 4: Jalankan test dari Step 1, pastikan PASS**

Run: `php artisan test tests/Feature/Sarpras/CrossTenantIsolationTest.php tests/Feature/Sarpras/GedungRuanganControllerTest.php`
Expected: semua test PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Lembaga/Sarpras/GedungController.php tests/Feature/Sarpras/CrossTenantIsolationTest.php
git commit -m "fix(sarpras): kartu ringkasan Gedung agregat semua lembaga saat yayasan mode Semua Lembaga"
```

- [ ] **Step 6: Baca `RuanganController::index()` untuk salin PERSIS closure fallback list-nya sendiri**

Buka `app/Http/Controllers/Lembaga/Sarpras/RuanganController.php`, baca method `index()` baris ~28-82 SELURUHNYA — catat PERSIS bentuk closure fallback yang dipakai query list (baris ~39-46, sudah dikonfirmasi py cabang tambahan `orWhere(yayasan_id + is_shared)`, BUKAN cuma `if/elseif` biasa seperti Gedung). Ini WAJIB dibaca dari file asli karena strukturnya beda dari `GedungController`, JANGAN salin pola `$statsFilter` dari Step 3 di atas untuk file ini.

- [ ] **Step 7: Tulis test baru yang GAGAL — RuanganController**

Tambahkan ke `tests/Feature/Sarpras/CrossTenantIsolationTest.php`:

```php
    public function test_kartu_ringkasan_ruangan_menghitung_agregat_semua_lembaga_saat_yayasan_mode_semua_lembaga(): void
    {
        $yayasan = Yayasan::create(['nama' => 'Yayasan Kartu Ringkasan Ruangan']);
        $lembagaX = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'Lembaga X Ruangan', 'jenjang' => 'SD', 'npsn' => '9101', 'status_aktif' => true]);
        $lembagaY = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'Lembaga Y Ruangan', 'jenjang' => 'SD', 'npsn' => '9102', 'status_aktif' => true]);

        $gedungX = Gedung::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaX->id, 'kode_gedung' => 'GD-RX', 'nama_gedung' => 'Gedung RX', 'jumlah_lantai' => 1, 'is_aktif' => true]);
        $gedungY = Gedung::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaY->id, 'kode_gedung' => 'GD-RY', 'nama_gedung' => 'Gedung RY', 'jumlah_lantai' => 1, 'is_aktif' => true]);

        Ruangan::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaX->id, 'gedung_id' => $gedungX->id, 'kode_ruangan' => 'R-X1', 'nama_ruangan' => 'Ruang X1', 'lantai' => 1, 'jenis_ruangan' => JenisRuangan::KelasTeori, 'is_shared' => false, 'is_aktif' => true]);
        Ruangan::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaY->id, 'gedung_id' => $gedungY->id, 'kode_ruangan' => 'R-Y1', 'nama_ruangan' => 'Ruang Y1', 'lantai' => 1, 'jenis_ruangan' => JenisRuangan::KelasTeori, 'is_shared' => false, 'is_aktif' => true]);

        $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_ruangan_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $role->givePermissionTo(['sarpras.ruangan.view']);
        $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('admin.sarpras.ruangan.index'));

        $response->assertOk();
        $response->assertViewHas('totalRuangan', 2);
    }
```

- [ ] **Step 8: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="test_kartu_ringkasan_ruangan_menghitung_agregat_semua_lembaga_saat_yayasan_mode_semua_lembaga"`
Expected: FAIL — `totalRuangan` bernilai 0.

- [ ] **Step 9: Perbaiki `RuanganController::index()`**

Ganti 4 baris `$totalRuangan`, `$totalKelas`, `$totalLab`, `$totalShared` (query stats terpisah, ~baris 69-72) supaya memakai closure fallback yang SAMA PERSIS dengan yang dipakai query list di method yang sama (dibaca di Step 6) — bukan `$statsFilter` sederhana dari `GedungController`. Ekstrak closure fallback list itu ke variabel (mis. `$scopeFilter`) sebelum dipakai di query list MAUPUN 4 query stats, supaya tidak duplikasi logic 2 kali dalam 1 method.

- [ ] **Step 10: Jalankan test dari Step 7, pastikan PASS**

Run: `php artisan test tests/Feature/Sarpras/CrossTenantIsolationTest.php`
Expected: semua test PASS (termasuk test existing yang menguji `is_shared` ruangan lintas lembaga, kalau ada — jalankan file penuh untuk pastikan tidak ada regresi pada logic `is_shared`).

- [ ] **Step 11: Commit**

```bash
git add app/Http/Controllers/Lembaga/Sarpras/RuanganController.php tests/Feature/Sarpras/CrossTenantIsolationTest.php
git commit -m "fix(sarpras): kartu ringkasan Ruangan agregat semua lembaga saat yayasan mode Semua Lembaga"
```

- [ ] **Step 12: Tulis test baru yang GAGAL — KategoriAsetController**

Tambahkan ke `tests/Feature/Sarpras/CrossTenantIsolationTest.php`:

```php
    public function test_kartu_ringkasan_kategori_aset_menghitung_agregat_semua_lembaga_saat_yayasan_mode_semua_lembaga(): void
    {
        $yayasan = Yayasan::create(['nama' => 'Yayasan Kartu Ringkasan Kategori']);
        $lembagaX = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'Lembaga X Kategori', 'jenjang' => 'SD', 'npsn' => '9201', 'status_aktif' => true]);
        $lembagaY = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'Lembaga Y Kategori', 'jenjang' => 'SD', 'npsn' => '9202', 'status_aktif' => true]);

        KategoriAset::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaX->id, 'kode_kategori' => 'KAT-X', 'nama_kategori' => 'Kategori X']);
        KategoriAset::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaY->id, 'kode_kategori' => 'KAT-Y', 'nama_kategori' => 'Kategori Y']);

        $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_kategori_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $role->givePermissionTo(['sarpras.kategori.view']);
        $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('admin.sarpras.kategori.index'));

        $response->assertOk();
        $response->assertViewHas('totalKategori', 2);
    }
```

- [ ] **Step 13: Jalankan test, pastikan GAGAL, lalu perbaiki `KategoriAsetController::index()`**

Run: `php artisan test --filter="test_kartu_ringkasan_kategori_aset_menghitung_agregat_semua_lembaga_saat_yayasan_mode_semua_lembaga"` → Expected FAIL.

Ganti baris 56-57 di `app/Http/Controllers/Lembaga/Sarpras/KategoriAsetController.php`:

```php
        $totalKategori = KategoriAset::where('lembaga_id', $lembagaId)->count();
        $totalAset = \App\Domains\Sarpras\Models\AsetBarang::where('lembaga_id', $lembagaId)->count();
```

menjadi:

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

        $totalKategori = KategoriAset::where($statsFilter)->count();
        $totalAset = \App\Domains\Sarpras\Models\AsetBarang::where($statsFilter)->count();
```

- [ ] **Step 14: Jalankan test, pastikan PASS**

Run: `php artisan test tests/Feature/Sarpras/CrossTenantIsolationTest.php`
Expected: semua test PASS.

- [ ] **Step 15: Commit**

```bash
git add app/Http/Controllers/Lembaga/Sarpras/KategoriAsetController.php tests/Feature/Sarpras/CrossTenantIsolationTest.php
git commit -m "fix(sarpras): kartu ringkasan Kategori Aset agregat semua lembaga saat yayasan mode Semua Lembaga"
```

- [ ] **Step 16: Tulis test baru yang GAGAL — PengajuanPengadaanController**

Tambahkan method baru ke class `Tests\Feature\Pengadaan\CrossTenantIsolationTest` (`tests/Feature/Pengadaan/CrossTenantIsolationTest.php`):

```php
    public function test_kartu_ringkasan_stats_pengajuan_pengadaan_menghitung_agregat_semua_lembaga_saat_yayasan_mode_semua_lembaga(): void
    {
        PengajuanPengadaan::create([
            'yayasan_id' => $this->yayasanA->id,
            'lembaga_id' => $this->lembagaA1->id,
            'nomor_pengajuan' => 'PGD-A1-STATS',
            'judul_pengajuan' => 'Pengajuan Stats Lembaga A1',
            'tingkat_urgensi' => 'biasa',
            'total_estimasi' => 500000,
            'status' => StatusPengajuan::Draft,
        ]);

        $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_stats_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $role->givePermissionTo(['pengadaan.proposal.view']);
        $user = User::factory()->create(['yayasan_id' => $this->yayasanA->id]);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('admin.pengadaan.proposal.index'));

        $response->assertOk();
        $response->assertViewHas('stats', function ($stats) {
            // proposalA2 (dari setUp, status Submitted) + PGD-A1-STATS (Draft) = total 2
            return $stats['total'] === 2 && $stats['draft'] === 1;
        });
    }
```

- [ ] **Step 17: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="test_kartu_ringkasan_stats_pengajuan_pengadaan_menghitung_agregat_semua_lembaga_saat_yayasan_mode_semua_lembaga"`
Expected: FAIL — `$stats['total']`/`$stats['draft']` bernilai 0.

- [ ] **Step 18: Perbaiki `PengajuanPengadaanController::index()`**

Ganti baris 60-65 di `app/Http/Controllers/Lembaga/Pengadaan/PengajuanPengadaanController.php`:

```php
        $stats = [
            'total' => PengajuanPengadaan::where('lembaga_id', $lembagaId)->count(),
            'draft' => PengajuanPengadaan::where('lembaga_id', $lembagaId)->where('status', StatusPengajuan::Draft)->count(),
            'in_review' => PengajuanPengadaan::where('lembaga_id', $lembagaId)->whereIn('status', [StatusPengajuan::Submitted, StatusPengajuan::InReview])->count(),
            'disbursed' => PengajuanPengadaan::where('lembaga_id', $lembagaId)->where('status', StatusPengajuan::Disbursed)->count(),
            'completed' => PengajuanPengadaan::where('lembaga_id', $lembagaId)->where('status', StatusPengajuan::Completed)->count(),
        ];
```

menjadi:

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

- [ ] **Step 19: Jalankan test, pastikan PASS**

Run: `php artisan test tests/Feature/Pengadaan/CrossTenantIsolationTest.php`
Expected: semua test PASS.

- [ ] **Step 20: Commit**

```bash
git add app/Http/Controllers/Lembaga/Pengadaan/PengajuanPengadaanController.php tests/Feature/Pengadaan/CrossTenantIsolationTest.php
git commit -m "fix(pengadaan): kartu ringkasan stats Pengajuan Pengadaan agregat semua lembaga saat yayasan mode Semua Lembaga"
```

---

### Task 7: Kategori D.5 — RaporController Dropdown Label Lembaga + Penutup

**Files:**
- Modify: `app/Http/Controllers/Admin/RaporController.php:70`
- Modify: `resources/views/portals/lembaga/akademik/rapor/index.blade.php:35-36`
- Test: `tests/Feature/Admin/RaporControllerTest.php` (tambah baru)
- Create: `.agents/logs/2026-09-07-scope-yayasan-lembaga-menu-fix.md` (handoff log)
- Modify: `PETA_PENGEMBANGAN.md`

**Interfaces:**
- Tidak ada — murni tambah eager-load relasi + label kondisional di view.

- [ ] **Step 1: Tulis test baru yang GAGAL**

Tambahkan ke `tests/Feature/Admin/RaporControllerTest.php` (helper `actingAsRaporViewer($lembaga)` sudah ada di file ini, baris 20-30):

```php
it('labels tahun ajaran options with lembaga name when yayasan scope has no active lembaga selected', function () {
    Permission::firstOrCreate(['name' => 'rapor.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_rapor_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['rapor.view']);

    $yayasan = Yayasan::factory()->create();
    $lembagaX = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SDIT Lembaga X']);
    $lembagaY = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SDIT Lembaga Y']);
    TahunAjaran::factory()->create(['lembaga_id' => $lembagaX->id, 'nama' => '2026/2027']);
    TahunAjaran::factory()->create(['lembaga_id' => $lembagaY->id, 'nama' => '2026/2027']);

    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('admin.rapor.index'));

    $response->assertOk();
    $response->assertSee('2026/2027 — SDIT Lembaga X');
    $response->assertSee('2026/2027 — SDIT Lembaga Y');
});

it('does not add a lembaga label to tahun ajaran options for a lembaga-scoped viewer', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SDIT Solo Lembaga']);
    TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027']);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index'));

    $response->assertOk();
    $response->assertDontSee('2026/2027 — SDIT Solo Lembaga');
    $response->assertSee('2026/2027');
});
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="labels tahun ajaran options with lembaga name when yayasan scope has no active lembaga selected"`
Expected: FAIL — dropdown belum punya label lembaga.

- [ ] **Step 3: Perbaiki controller & view**

`app/Http/Controllers/Admin/RaporController.php` baris 70, ganti:

```php
            'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
```

menjadi:

```php
            'tahunAjaranList' => TahunAjaran::with('lembaga')->orderByDesc('id')->get(),
```

`resources/views/portals/lembaga/akademik/rapor/index.blade.php` baris 35-36, ganti:

```blade
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                            @endforeach
```

menjadi:

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

- [ ] **Step 4: Jalankan test, pastikan PASS**

Run: `php artisan test tests/Feature/Admin/RaporControllerTest.php`
Expected: semua test PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/RaporController.php resources/views/portals/lembaga/akademik/rapor/index.blade.php tests/Feature/Admin/RaporControllerTest.php
git commit -m "fix(akademik): label lembaga di dropdown tahun ajaran Rekap Rapor saat yayasan mode Semua Lembaga"
```

- [ ] **Step 6: Cek proses PHP lain sebelum full suite (MySQL deadlock risk)**

Run (PowerShell): `Get-CimInstance Win32_Process -Filter "Name='php.exe'"`
Kalau ada proses `php artisan test`/`php artisan serve` lain yang sedang jalan, tunggu selesai dulu — JANGAN jalankan full suite paralel dengan proses lain (riwayat project ini pernah deadlock MySQL karena ini).

- [ ] **Step 7: Jalankan full test suite**

Run: `php artisan test --compact`
Expected: 0 failed. Kalau ada test lama yang gagal DI LUAR file yang disentuh plan ini (mis. `SesiPembelajaranSeederTest`/`M3DemoDataSeederTest` yang sudah diketahui pre-existing flaky/tidak terkait dari sesi sebelumnya), catat di handoff log sebagai "pre-existing, tidak terkait", JANGAN diperbaiki di sini (di luar scope).

- [ ] **Step 8: Jalankan Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` atau auto-fix lalu commit ulang perubahan formatting.

- [ ] **Step 9: Tulis handoff log**

Create `.agents/logs/2026-09-07-scope-yayasan-lembaga-menu-fix.md`, ikuti format handoff log lain di folder yang sama (lihat `.agents/logs/2026-09-06-guru-piket-jurnal-kbm.md` sebagai referensi struktur: "1. Apa yang Dikerjakan" per task+commit hash, "2. Keputusan Penting", "3. Hal yang Masih Perlu Direview"). WAJIB sebut: commit hash tiap task, hasil full suite, dan bagian "Di Luar Scope" persis seperti di spec (3 item global lintas-yayasan, dropdown JadwalPelajaranController, method lain VirtualAccount/ManualPayment, SPMB/PPDB).

- [ ] **Step 10: Update `PETA_PENGEMBANGAN.md`**

Tambahkan entri baru (cari lokasi yang sesuai — dekat entri Proyek A/C sebelumnya kalau ada bagian Akademik, atau bagian umum/RBAC kalau ada) mencatat: tanggal selesai, ringkasan 4 kategori (A/B/C/D), commit range, dan link ke spec+plan+handoff log. Ikuti gaya penulisan entri lain yang sudah ada di file ini (lihat entri Proyek C Fase 1 sebagai referensi format).

- [ ] **Step 11: Commit dokumentasi**

```bash
git add .agents/logs/2026-09-07-scope-yayasan-lembaga-menu-fix.md PETA_PENGEMBANGAN.md
git commit -m "docs(rbac): handoff log & update roadmap -- perbaikan visibilitas menu & keamanan scope yayasan/lembaga selesai"
```

---

## Self-Review

**1. Spec coverage:**
- Kategori A (A.1, A.2, A.3) → Task 1 ✅
- Kategori B → Task 2 ✅
- Kategori C (C.1, C.2) → Task 3 ✅
- Kategori D.1, D.2 → Task 4 ✅
- Kategori D.3 → Task 5 ✅
- Kategori D.4 (4 controller) → Task 6 ✅
- Kategori D.5 → Task 7 ✅
- "Di Luar Scope" → dicatat eksplisit di Global Constraints & handoff log (Task 7 Step 9), TIDAK ada task yang mengerjakannya.

**2. Placeholder scan:** Tidak ada "TBD"/"TODO". Satu tempat sengaja mengarahkan implementer membaca file asli dulu sebelum menulis (Task 6 Step 6, `RuanganController`) karena closure fallback aslinya PERLU dibaca langsung (beda dari 3 file lain) — ini instruksi eksplisit "baca lalu reuse", bukan "isi detailnya nanti", jadi bukan placeholder yang melanggar aturan.

**3. Type consistency:** Semua nama variabel (`$lembagaIdsYayasan`, `$activeLembagaId`, `$statsFilter`, `$lembagaId`, `$yayasanId`) konsisten dipakai sama persis dengan yang didefinisikan di tiap task — tidak ada task yang mengasumsikan nama beda dari yang ditulis.

**4. Test-regresi kritis yang WAJIB tidak lolos**: Task 1 Step 1 secara eksplisit menangani gotcha `TenantContext`/`yayasan_id`-fallback SEBELUM mengubah kode manapun (test existing `KasusAksesLogViewTest.php` baris 67-89 akan regresi kalau tidak diupdate duluan) — ini bug tersembunyi yang ditemukan saat riset sebelum menulis plan, sudah ditangani sebagai Step 1 terpisah, bukan disatukan dengan Step fix utama, supaya urutannya jelas: update test dulu (verifikasi masih pass di kode lama) → baru ubah kode.
